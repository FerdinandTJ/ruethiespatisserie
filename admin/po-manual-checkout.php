<?php
defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.');
const PO_PAYMENT_LABELS=['unpaid'=>'Menunggu transfer','under_review'=>'Transfer dilaporkan · menunggu pemeriksaan','paid'=>'Lunas','paid_review'=>'Pembayaran perlu ditinjau pemilik','untracked'=>'Demo tanpa pembayaran','rejected'=>'Laporan ditolak pemilik'];
function po_manual_enabled(): bool {
  if(!function_exists('ruethies_data_dir'))return false;
  try{return (po_config()['manual_checkout_enabled']??false)===true;}catch(RuntimeException $e){return false;}
}
function po_manual_report(PDO $db,string $token,array $input): void {
  if(!preg_match('/\A[a-f0-9]{64}\z/',$token))throw new DomainException('Pesanan tidak ditemukan.');
  $name=po_text($input['sender_name']??'','Nama pengirim',2,80);
  $amount=po_int($input['transfer_amount']??0,1,2000000000);
  $reference=po_text($input['transfer_reference']??'','Referensi/keterangan transfer',3,160);
  $row=po_query($db,'SELECT id,slot_id FROM po_orders WHERE token_hash=?',[hash('sha256',$token)])->fetch();
  if(!$row)throw new DomainException('Pesanan tidak ditemukan.');
  po_manual_transaction($db,static function()use($db,$row,$name,$amount,$reference){
    po_slot($db,(int)$row['slot_id'],true);
    $o=po_query($db,'SELECT * FROM po_orders WHERE id=? FOR UPDATE',[$row['id']])->fetch();
    if($o['flow_version']!=='manual_v1'||$o['payment_status']!=='unpaid'||$o['fulfillment_status']!=='awaiting_transfer'||$o['reservation_status']!=='held'||(int)$o['expires_at']<=time())throw new DomainException('Batas pembayaran berakhir atau status sudah berubah. Jika sudah transfer, hubungi toko dengan kode pesanan; jangan transfer ulang.');
    if($amount!==(int)$o['total'])throw new DomainException('Nominal laporan harus sama dengan total pesanan. Untuk selisih nominal, hubungi toko.');
    $count=(int)$o['payment_report_count']+1;
    if($count>3)throw new DomainException('Batas perbaikan laporan tercapai. Hubungi toko.');
    po_query($db,"UPDATE po_orders SET payment_status='under_review',fulfillment_status='under_review',payment_sender=?,payment_report_amount=?,payment_report_reference=?,payment_reported_at=?,payment_report_count=? WHERE id=?",[$name,$amount,$reference,time(),$count,$o['id']]);
    po_manual_event($db,(int)$o['id'],'transfer_reported','Pelanggan','Laporan transfer; belum diverifikasi.',$o);
    po_wa_enqueue($db,(int)$o['id'],'reported','reported:'.$count,'owner');
  });
}
function po_manual_actor(array $actor,bool $owner=false): void {
  if(!in_array($actor['role']??'',['owner','staff'],true)||($owner&&$actor['role']!=='owner'))throw new DomainException('Tindakan ini hanya untuk pemilik.');
  // Re-read the account for HTTP actions; isolated domain tests use explicit fixture actors.
  if(function_exists('current_user')){
    $fresh=current_user();
    if(!$fresh||$fresh['name']!==($actor['name']??'')||$fresh['role']!==$actor['role'])throw new DomainException('Akses akun sudah berubah. Masuk kembali.');
  }
}
function po_manual_decide(PDO $db,int $id,string $target,array $input,array $actor): void {
  po_manual_actor($actor,true);
  if(!in_array($target,['paid','reject','reject-final'],true))throw new DomainException('Tindakan pembayaran tidak valid.');
  if($target==='reject-final'&&($input['verified_no_transfer']??'')!=='yes')throw new DomainException('Pastikan mutasi sudah diperiksa dan tidak ada dana masuk sebelum menutup laporan.');
  $ref=po_text($input['admin_reference']??'','Referensi mutasi/alasan penolakan',3,160);
  $expectedReport=po_int($input['expected_report']??0,1,3);
  $amount=$target==='paid'?po_int($input['verified_amount']??0,1,2000000000):0;
  $sid=po_query($db,'SELECT slot_id FROM po_orders WHERE id=?',[$id])->fetchColumn();if(!$sid)throw new DomainException('Pesanan tidak ditemukan.');
  po_manual_transaction($db,static function()use($db,$sid,$id,$target,$ref,$amount,$actor,$expectedReport){
    po_slot($db,(int)$sid,true);$o=po_query($db,'SELECT * FROM po_orders WHERE id=? FOR UPDATE',[$id])->fetch();
    if($o['flow_version']!=='manual_v1'||$o['payment_status']!=='under_review'||$o['fulfillment_status']!=='under_review'||$o['reservation_status']!=='held'||(int)$o['payment_report_count']!==$expectedReport)throw new DomainException('Status laporan sudah berubah. Muat ulang daftar.');
    if($target==='paid'){
      if($amount!==(int)$o['total'])throw new DomainException('Nominal mutasi harus sesuai total pesanan.');
      po_query($db,"UPDATE po_orders SET status='confirmed',payment_status='paid',fulfillment_status='processing',reservation_status='committed',payment_admin_reference=? WHERE id=?",[$ref,$id]);
      po_query($db,'INSERT INTO po_history(order_id,status,actor,created_at) VALUES(?,?,?,?)',[$id,'confirmed',$actor['name'],time()]);
      po_wa_enqueue($db,$id,'paid','paid');po_wa_enqueue($db,$id,'paid','paid','owner');
    }elseif($target==='reject-final'){
      foreach(po_query($db,'SELECT product_id,qty FROM po_lines WHERE order_id=? ORDER BY product_id',[$id])->fetchAll()as$line){
        if(po_query($db,'UPDATE po_products SET reserved=reserved-? WHERE id=? AND slot_id=? AND reserved>=?',[$line['qty'],$line['product_id'],$sid,$line['qty']])->rowCount()!==1)throw new RuntimeException('Reservasi tidak konsisten.');
      }
      po_query($db,"UPDATE po_orders SET status='rejected',payment_status='rejected',fulfillment_status='cancelled',reservation_status='released',payment_admin_reference=? WHERE id=?",[$ref,$id]);
      po_query($db,'INSERT INTO po_history(order_id,status,actor,created_at) VALUES(?,?,?,?)',[$id,'rejected',$actor['name'],time()]);
      po_wa_enqueue($db,$id,'closed','closed');
    }else{
      if((int)$o['payment_report_count']>=3)throw new DomainException('Dua kesempatan perbaikan sudah digunakan. Hubungi pelanggan dan selesaikan pemeriksaan; reservasi tidak dilepas otomatis.');
      po_query($db,"UPDATE po_orders SET payment_status='unpaid',fulfillment_status='awaiting_transfer',expires_at=?,payment_admin_reference=? WHERE id=?",[time()+900,$ref,$id]);
      po_wa_enqueue($db,$id,'correction','correction:'.$o['payment_report_count']);
    }
    po_manual_event($db,$id,$target==='paid'?'payment_verified':($target==='reject-final'?'payment_report_closed':'report_rejected'),$actor['name'],$ref,$o);
  });
}
function po_manual_fulfill(PDO $db,int $id,string $expected,string $target,array $actor): void {
  po_manual_actor($actor);
  if(!in_array([$expected,$target],[['confirmed','ready'],['ready','completed']],true))throw new DomainException('Perubahan pemenuhan tidak valid.');
  $sid=po_query($db,'SELECT slot_id FROM po_orders WHERE id=?',[$id])->fetchColumn();if(!$sid)throw new DomainException('Pesanan tidak ditemukan.');
  po_manual_transaction($db,static function()use($db,$sid,$id,$expected,$target,$actor){
    po_slot($db,(int)$sid,true);$o=po_query($db,'SELECT * FROM po_orders WHERE id=? FOR UPDATE',[$id])->fetch();
    if(!in_array($o['flow_version'],['manual_v1','midtrans_v1'],true)||$o['payment_status']!=='paid'||$o['reservation_status']!=='committed'||$o['status']!==$expected)throw new DomainException('Status sudah berubah atau pembayaran belum diverifikasi.');
    po_query($db,'UPDATE po_orders SET status=?,fulfillment_status=? WHERE id=?',[$target,$target,$id]);
    po_query($db,'INSERT INTO po_history(order_id,status,actor,created_at) VALUES(?,?,?,?)',[$id,$target,$actor['name'],time()]);
    po_manual_event($db,$id,$target,$actor['name'],'Pemenuhan pesanan.',$o);po_wa_enqueue($db,$id,$target,$target);
  });
}
function po_manual_verify_identity(array $actor,array $input): void {
  po_manual_actor($actor,true);
  if(function_exists('security_require_reauth')){security_require_reauth();return;}
  // Until the independently validated security release is merged into staging.
  $fresh=user_get($actor['name']);
  $pass=$input['current_password']??null;$code=$input['otp']??'';
  if(!$fresh||!is_string($pass)||strlen($pass)>1024||!is_string($code)||!password_verify($pass,$fresh['hash'])||(!empty($fresh['totp'])&&!totp_verify($fresh['totp'],$code)))throw new DomainException('Konfirmasi kata sandi dan OTP pemilik (bila aktif) diperlukan.');
}
