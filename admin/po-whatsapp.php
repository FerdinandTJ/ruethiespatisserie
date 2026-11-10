<?php
defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.');

function po_wa_settings(array $config): array {
  $raw=$config['fonnte']??[];
  if(!is_array($raw))throw new RuntimeException('Konfigurasi WhatsApp tidak valid.');
  if(($raw['enabled']??false)!==true)return ['enabled'=>false];
  $token=$raw['token']??null;
  if(!is_string($token)||$token===''||strlen($token)>512||preg_match('/[\r\n]/',$token))throw new RuntimeException('Token WhatsApp tidak valid.');
  $allowed=$raw['allowed_recipients']??[];
  if(!is_array($allowed)||!array_is_list($allowed)||!$allowed||count($allowed)>10)throw new RuntimeException('Daftar nomor uji WhatsApp wajib diisi.');
  $allowed=array_map('po_phone',$allowed);
  $test=($raw['test_recipient']??'')!==''?po_phone($raw['test_recipient']):'';
  $owner=($raw['owner_phone']??'')!==''?po_phone($raw['owner_phone']):'';
  if(($test&&!in_array($test,$allowed,true))||($owner&&!$test&&!in_array($owner,$allowed,true)))throw new RuntimeException('Nomor WhatsApp tidak termasuk daftar uji.');
  return ['enabled'=>true,'token'=>$token,'allowed_recipients'=>$allowed,'test_recipient'=>$test,'owner_phone'=>$owner];
}
function po_wa_encrypt(string $token,string $secret): string {
  $iv=random_bytes(12);$tag='';
  $cipher=openssl_encrypt($token,'aes-256-gcm',hash_hkdf('sha256',$secret,32,'ruethies-po-wa-v1'),OPENSSL_RAW_DATA,$iv,$tag,'po-status-token');
  if($cipher===false)throw new RuntimeException('Enkripsi tautan gagal.');
  return base64_encode($iv.$tag.$cipher);
}
function po_wa_decrypt(string $cipher,string $secret): string {
  $raw=base64_decode($cipher,true);
  if($raw===false||strlen($raw)<29)throw new RuntimeException('Tautan privat tidak tersedia.');
  $token=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash_hkdf('sha256',$secret,32,'ruethies-po-wa-v1'),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'po-status-token');
  if(!is_string($token)||!preg_match('/\A[a-f0-9]{64}\z/',$token))throw new RuntimeException('Tautan privat tidak tersedia.');
  return $token;
}
function po_wa_enqueue(PDO $db,int $id,string $kind,string $event,string $audience='customer'): void {
  if(!$db->inTransaction())throw new LogicException('Notifikasi harus mengikuti transaksi pesanan.');
  po_query($db,"INSERT IGNORE INTO po_whatsapp_outbox(order_id,kind,event_key,audience,status,available_at,created_at,updated_at) VALUES(?,?,?,?,'pending',?,?,?)",[$id,$kind,$event,$audience,time(),time(),time()]);
}
function po_wa_seed(PDO $db,int $id,string $token,string $secret): void {
  po_query($db,'UPDATE po_orders SET notification_access=? WHERE id=?',[po_wa_encrypt($token,$secret),$id]);
  po_wa_enqueue($db,$id,'invoice','invoice');
}
function po_wa_claim(PDO $db): ?array {
  return po_manual_transaction($db,static function()use($db){
    // A crashed sender may already have reached the provider: never blindly resend it.
    po_query($db,"UPDATE po_whatsapp_outbox SET status='uncertain',claim_token=NULL,last_error='worker_interrupted',version=version+1,updated_at=? WHERE status='processing' AND lease_until<=?",[time(),time()]);
    $job=po_query($db,"SELECT * FROM po_whatsapp_outbox WHERE status='pending' AND available_at<=? ORDER BY id LIMIT 1 FOR UPDATE",[time()])->fetch();
    if(!$job)return null;
    $claim=bin2hex(random_bytes(16));
    po_query($db,"UPDATE po_whatsapp_outbox SET status='processing',attempts=attempts+1,claim_token=?,lease_until=?,version=version+1,updated_at=? WHERE id=?",[$claim,time()+120,time(),$job['id']]);
    $job['claim_token']=$claim;$job['attempts']=(int)$job['attempts']+1;return $job;
  });
}
function po_wa_message(PDO $db,array $job,string $secret): ?array {
  $o=po_query($db,'SELECT o.*,s.title,s.pickup_from,s.pickup_until FROM po_orders o JOIN po_slots s ON s.id=o.slot_id WHERE o.id=?',[$job['order_id']])->fetch();
  if(!$o||!in_array($o['flow_version'],['manual_v1','midtrans_v1'],true))return null;
  if($job['kind']==='invoice'&&(int)$o['payment_report_count']>0)return null;
  if(in_array($job['kind'],['reported','correction'],true)&&$job['event_key']!==$job['kind'].':'.$o['payment_report_count'])return null;
  if(in_array($job['kind'],['invoice','correction'],true)&&(!in_array($o['fulfillment_status'],['awaiting_transfer','awaiting_payment'],true)||(int)$o['expires_at']<=time()+45))return null;
  if($job['kind']==='closed'&&$o['status']!=='rejected')return null;
  if($job['kind']==='reported'&&$o['payment_status']!=='under_review')return null;
  if(in_array($job['kind'],['paid','ready','completed'],true)&&$o['payment_status']!=='paid')return null;
  if($job['kind']==='late_paid'&&$o['payment_status']!=='paid_review')return null;
  if($job['kind']==='ready'&&$o['status']!=='ready')return null;
  $token=po_wa_decrypt($o['notification_access']??'',$secret);
  if(!hash_equals($o['token_hash'],hash('sha256',$token)))throw new RuntimeException('Tautan tidak cocok.');
  $link='https://staging.ruethiespatisserie.com/pre-order/status/?t='.$token;
  $head="[UJI STAGING] Ruethies Patisserie\nPesanan ".$o['code']."\nPelanggan: ".preg_replace('/[\r\n\t]+/',' ',$o['customer_name'])."\n";
  $messages=['invoice'=>'Pesanan tercatat. Selesaikan transfer dan laporkan melalui tautan sebelum batas waktu.', 'reported'=>'Pelanggan melaporkan transfer. Pemilik perlu memeriksa mutasi bank; belum dinyatakan lunas.', 'paid'=>'Pembayaran telah diverifikasi pemilik. Pesanan sedang diproses.', 'ready'=>'Pesanan siap diambil di toko. Tunjukkan kode pesanan.', 'completed'=>'Pesanan selesai diambil. Terima kasih.', 'correction'=>'Pemilik meminta perbaikan laporan transfer. Periksa catatan dan tenggat baru di halaman pesanan. Jangan transfer ulang.', 'closed'=>'Laporan transfer ditutup pemilik setelah pemeriksaan. Jika sudah transfer, hubungi toko dengan kode pesanan; jangan transfer ulang.'];
  $messages['late_paid']='Pembayaran Sandbox perlu ditinjau pemilik. Jangan proses atau membayar ulang; hubungi toko dengan kode pesanan.';
  if($o['flow_version']==='midtrans_v1'){
    $messages['invoice']='Invoice uji tersedia. Buka tautan pesanan untuk membayar melalui Midtrans Sandbox. Gunakan simulator, bukan uang sungguhan.';
    $messages['paid']='Pembayaran telah diverifikasi melalui Midtrans Sandbox. Pesanan sedang diproses.';
  }
  if(!isset($messages[$job['kind']]))throw new RuntimeException('Jenis notifikasi tidak valid.');
  $body=$head.$messages[$job['kind']]."\nTotal: ".po_money((int)$o['total'])."\n";
  if($job['kind']==='invoice'&&(int)$o['payment_report_count']>0)return null;
  if(in_array($job['kind'],['reported','correction'],true)&&$job['event_key']!==$job['kind'].':'.$o['payment_report_count'])return null;
  if(in_array($job['kind'],['invoice','correction'],true)&&$o['flow_version']==='manual_v1'){
    $bank=json_decode($o['bank_snapshot'],true,512,JSON_THROW_ON_ERROR);
    $body.=$bank['bank_name'].' '.$bank['account_number'].' a.n. '.$bank['account_name']."\n".$bank['instructions']."\nBatas: ".po_date((int)$o['expires_at'])."\n";
    foreach(po_query($db,'SELECT name,qty FROM po_lines WHERE order_id=? ORDER BY id',[$o['id']])->fetchAll()as$l)$body.=(int)$l['qty'].' x '.$l['name']."\n";
  }
  $body.='Pengambilan: '.po_date((int)$o['pickup_from'])."\n".$link."\nSimpan tautan pribadi ini. Staging: gunakan data dan rekening contoh; jangan transfer sungguhan.";
  return ['phone'=>$o['phone'],'message'=>$body];
}
function po_wa_response(int $http,string $body,int $curlError=0): array {
  if($curlError)return ['outcome'=>in_array($curlError,[6,7],true)?'retry':'uncertain','code'=>'transport_'.$curlError];
  if(in_array($http,[401,403],true))return ['outcome'=>'failed','code'=>'authorization'];
  if($http===429)return ['outcome'=>'retry','code'=>'rate_limited'];
  if($http<200||$http>=300)return ['outcome'=>'uncertain','code'=>'http_'.$http];
  $data=json_decode($body,true);
  if(!is_array($data)||!is_bool($data['status']??null))return ['outcome'=>'uncertain','code'=>'invalid_response'];
  if(!$data['status'])return ['outcome'=>'retry','code'=>'provider_rejected'];
  $ids=$data['id']??[];$id=is_array($ids)?($ids[0]??''):'';
  return ['outcome'=>'accepted','code'=>'','provider_id'=>is_string($id)?substr(preg_replace('/[^a-zA-Z0-9_-]/','',$id),0,128):''];
}
function po_wa_send(array $settings,string $phone,string $message): array {
  $ch=curl_init('https://api.fonnte.com/send');$body='';
  curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['target'=>$phone,'message'=>$message,'countryCode'=>'0','connectOnly'=>'1','preview'=>'0']),CURLOPT_HTTPHEADER=>['Authorization: '.$settings['token'],'Content-Type: application/x-www-form-urlencoded'],CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_WRITEFUNCTION=>static function($ch,$part)use(&$body){if(strlen($body)+strlen($part)>65536)return 0;$body.=$part;return strlen($part);}]);
  curl_exec($ch);return po_wa_response((int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE),$body,curl_errno($ch));
}
function po_wa_finish(PDO $db,array $job,array $result): void {
  $status=$result['outcome'];
  if($status==='retry')$status=$job['attempts']>=5?'failed':'pending';
  if(!in_array($status,['accepted','pending','uncertain','failed','skipped'],true))throw new LogicException('Hasil notifikasi tidak valid.');
  po_query($db,'UPDATE po_whatsapp_outbox SET status=?,last_error=?,provider_id=?,available_at=?,claim_token=NULL,lease_until=0,version=version+1,updated_at=? WHERE id=? AND status=\'processing\' AND claim_token=?',[$status,$result['code']??'',$result['provider_id']??'',time()+min(3600,60*(2**($job['attempts']-1))),time(),$job['id'],$job['claim_token']]);
}
function po_wa_run(PDO $db,array $config,int $limit=5,?callable $transport=null): array {
  $settings=po_wa_settings($config);$summary=['processed'=>0,'accepted'=>0,'pending'=>0,'uncertain'=>0,'failed'=>0,'skipped'=>0];
  if(!$settings['enabled'])return $summary+['disabled'=>true];
  for($i=0;$i<max(1,min(5,$limit));$i++){
    $job=po_wa_claim($db);if(!$job)break;
    try{
      $data=po_wa_message($db,$job,$config['secret']);
      if(!$data)$result=['outcome'=>'skipped','code'=>'obsolete'];
      else{
        $phone=$settings['test_recipient']?:($job['audience']==='owner'?$settings['owner_phone']:$data['phone']);
        if($job['audience']==='owner'&&$phone==='')$result=['outcome'=>'skipped','code'=>'owner_not_configured'];
        elseif(!$phone||!in_array($phone,$settings['allowed_recipients'],true))$result=['outcome'=>'failed','code'=>'recipient_not_allowed'];
        else $result=($transport??'po_wa_send')($settings,$phone,$data['message']);
      }
    }catch(Throwable $e){$result=['outcome'=>'uncertain','code'=>'processing_error'];}
    po_wa_finish($db,$job,$result);$summary['processed']++;
    $status=po_query($db,'SELECT status FROM po_whatsapp_outbox WHERE id=?',[$job['id']])->fetchColumn();if(isset($summary[$status]))$summary[$status]++;
  }
  return $summary;
}
function po_wa_retry(PDO $db,int $id,int $version,array $actor): void {
  if(($actor['role']??'')!=='owner')throw new DomainException('Hanya pemilik yang dapat mengulangi notifikasi.');
  if(po_query($db,"UPDATE po_whatsapp_outbox SET status='pending',attempts=0,manual_retries=manual_retries+1,last_error='',available_at=?,version=version+1,updated_at=? WHERE id=? AND version=? AND status IN ('failed','uncertain') AND manual_retries<3",[time(),time(),$id,$version])->rowCount()!==1)throw new DomainException('Notifikasi sudah berubah atau batas ulang tercapai. Muat ulang daftar.');
}
