<?php
// Reservasi manual dan migrasi kompatibel dengan pesanan legacy. Checkout dikendalikan flag privat.
defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.');

function po_migrate_manual(PDO $db): void {
  // Daftar identifier/definisi tetap, tidak berasal dari request. DDL aman diulang, bukan rollbackable.
  $changes = [
    'po_slots' => ['flow_version'=>"VARCHAR(16) NOT NULL DEFAULT 'legacy'"],
    'po_orders' => [
      'flow_version'=>"VARCHAR(16) NOT NULL DEFAULT 'legacy'",
      'payment_status'=>"VARCHAR(20) NOT NULL DEFAULT 'untracked'",
      'fulfillment_status'=>"VARCHAR(24) NOT NULL DEFAULT 'legacy'",
      'reservation_status'=>"VARCHAR(16) NOT NULL DEFAULT 'legacy'",
      'bank_snapshot'=>'TEXT NULL',
      'request_fingerprint'=>'CHAR(64) NULL',
      'notification_access'=>'TEXT NULL',
      'payment_sender'=>"VARCHAR(80) NOT NULL DEFAULT ''",
      'payment_report_amount'=>'INT UNSIGNED NULL',
      'payment_report_reference'=>"VARCHAR(160) NOT NULL DEFAULT ''",
      'payment_reported_at'=>'BIGINT NULL',
      'payment_report_count'=>'INT UNSIGNED NOT NULL DEFAULT 0',
      'payment_admin_reference'=>"VARCHAR(160) NOT NULL DEFAULT ''",
    ],
  ];
  foreach ($changes as $table=>$columns) {
    $existing = array_column($db->query("SHOW COLUMNS FROM $table")->fetchAll(), 'Field');
    foreach ($columns as $column=>$definition) if (!in_array($column,$existing,true)) $db->exec("ALTER TABLE $table ADD $column $definition");
  }
  $indexes = array_column($db->query('SHOW INDEX FROM po_orders')->fetchAll(), 'Key_name');
  if (!in_array('po_manual_expiry',$indexes,true)) $db->exec('ALTER TABLE po_orders ADD INDEX po_manual_expiry(flow_version,fulfillment_status,reservation_status,expires_at)');
  po_query($db, 'INSERT IGNORE INTO po_schema_migrations(version,applied_at) VALUES(?,?)', ['manual-v1-foundation',time()]);
}
function po_manual_transaction(PDO $db, callable $work) {
  for ($attempt=0; ; $attempt++) {
    $db->beginTransaction();
    try { $result=$work(); $db->commit(); return $result; }
    catch (Throwable $e) {
      if ($db->inTransaction()) $db->rollBack();
      if (!($e instanceof PDOException) || !in_array((int)($e->errorInfo[1] ?? 0),[1205,1213],true) || $attempt>=2) throw $e;
      usleep(random_int(10000,50000));
    }
  }
}
function po_manual_bank(PDO $db): array {
  $bank=po_payment_settings($db);
  if (!(int)$bank['enabled']) throw new DomainException('Rekening pembayaran belum aktif. Hubungi toko atau coba lagi nanti.');
  // Validasi lagi agar konfigurasi yang dimodifikasi di luar admin juga gagal secara aman.
  $valid=po_payment_validate($bank);
  return ['bank_name'=>$valid['bank_name'],'account_number'=>$valid['account_number'],
    'account_name'=>$valid['account_name'],'instructions'=>$valid['instructions'],'version'=>(int)$bank['version']];
}
function po_manual_fingerprint(array $input): string {
  $customer=po_customer($input); $qty=$input['qty'] ?? null;
  if (!is_array($qty) || count($qty)>100) throw new DomainException('Pilihan produk tidak valid.');
  $selected=[];
  foreach ($qty as $pid=>$n) {
    $n=po_int($n==='' ? 0 : $n,0,10000);
    if ($n) $selected[po_int($pid,1,PHP_INT_MAX)]=$n;
  }
  if (!$selected || count($selected)>12) throw new DomainException('Pilih 1–12 jenis produk.');
  ksort($selected,SORT_NUMERIC);
  return hash('sha256',json_encode([po_int($input['slot_id'] ?? 0,1,PHP_INT_MAX),$customer,$selected],JSON_THROW_ON_ERROR));
}
function po_manual_retry(array $order, string $fingerprint): void {
  if ($order['flow_version']!=='manual_v1' || !is_string($order['request_fingerprint']) || !hash_equals($order['request_fingerprint'],$fingerprint)) {
    throw new DomainException('Kunci checkout sudah digunakan untuk data lain. Periksa ringkasan dan buat pengajuan baru.');
  }
}
function po_manual_state(array $order): array {
  return array_intersect_key($order,array_flip(['status','payment_status','fulfillment_status','reservation_status']));
}
function po_manual_event(PDO $db, int $id, string $event, string $actor, string $reason, ?array $before): void {
  $after=po_query($db,'SELECT status,payment_status,fulfillment_status,reservation_status FROM po_orders WHERE id=?',[$id])->fetch();
  po_query($db,'INSERT INTO po_order_events(order_id,event,actor,reason,before_state,after_state,created_at) VALUES(?,?,?,?,?,?,?)',
    [$id,$event,$actor,$reason,$before===null ? null : json_encode(po_manual_state($before),JSON_THROW_ON_ERROR),json_encode($after,JSON_THROW_ON_ERROR),time()]);
}
function po_manual_submit(PDO $db, array $input, string $nonce, string $quoteHash, string $secret): string {
  if (!preg_match('/\A[a-f0-9]{64}\z/',$nonce) || strlen($secret)<64) throw new DomainException('Sesi pengajuan tidak valid.');
  [$name,$phone,$note]=po_customer($input);
  $fingerprint=po_manual_fingerprint($input); $requestHash=hash('sha256',$nonce);
  $token=hash_hmac('sha256','status:'.$nonce,$secret);
  $existing=po_query($db,'SELECT flow_version,request_fingerprint FROM po_orders WHERE request_hash=?',[$requestHash])->fetch();
  if ($existing) { po_manual_retry($existing,$fingerprint); return $token; }
  // Kunci konten dipakai hanya untuk konsistensi pembacaan katalog; tidak ada I/O jaringan.
  if (function_exists('content_lock')) content_lock();
  po_rate($db,'phone:'.hash_hmac('sha256',$phone,$secret),5);
  return po_manual_transaction($db, function () use ($db,$input,$phone,$name,$note,$fingerprint,$requestHash,$token,$quoteHash,$secret) {
    // Urutan global: mutex nomor -> rekening -> jadwal -> order -> produk berurutan.
    po_query($db,'INSERT INTO po_rate(bucket_key,attempts,expires_at) VALUES(?,0,?) ON DUPLICATE KEY UPDATE expires_at=?',
      [hash_hmac('sha256','submit-lock:'.$phone,$secret),time()+1800,time()+1800]);
    po_query($db,'INSERT INTO po_payment_settings(id) VALUES(1) ON DUPLICATE KEY UPDATE id=1');
    po_query($db,'SELECT id FROM po_payment_settings WHERE id=1 FOR UPDATE')->fetch();
    $slot=po_slot($db,po_int($input['slot_id'] ?? 0,1,PHP_INT_MAX),true);
    $existing=po_query($db,'SELECT flow_version,request_fingerprint FROM po_orders WHERE request_hash=? FOR UPDATE',[$requestHash])->fetch();
    if ($existing) { po_manual_retry($existing,$fingerprint); return $token; }
    if (($slot['flow_version'] ?? '')!=='manual_v1') throw new DomainException('Gunakan jadwal pembayaran manual yang terpisah dari demo.');
    po_query($db,'SELECT id FROM po_products WHERE slot_id=? ORDER BY id FOR UPDATE',[$slot['id']])->fetchAll();
    $q=po_quote($db,$input);
    if (!hash_equals(po_quote_hash($q),$quoteHash)) throw new DomainException('Produk, harga, jadwal, atau rekening berubah. Periksa ringkasan baru.');
    $now=time();
    $pending=(int)po_query($db,"SELECT COUNT(*) FROM po_orders WHERE phone=? AND status='pending' AND expires_at>?",[$phone,$now])->fetchColumn();
    if ($pending>=3) throw new DomainException('Nomor ini sudah memiliki 3 pengajuan yang menunggu konfirmasi.');
    foreach ($q['lines'] as $line) {
      $changed=po_query($db,'UPDATE po_products SET reserved=reserved+? WHERE id=? AND slot_id=? AND active=1 AND reserved+?<=quota',[$line['qty'],$line['product_id'],$slot['id'],$line['qty']]);
      if ($changed->rowCount()!==1) throw new DomainException('Kuota produk tidak mencukupi. Periksa pilihan kembali.');
    }
    $code='PO-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
    po_query($db,"INSERT INTO po_orders(slot_id,code,token_hash,request_hash,customer_name,phone,note,total,created_at,expires_at,flow_version,payment_status,fulfillment_status,reservation_status,bank_snapshot,request_fingerprint) VALUES(?,?,?,?,?,?,?,?,?,?,'manual_v1','unpaid','awaiting_transfer','held',?,?)",
      [$slot['id'],$code,hash('sha256',$token),$requestHash,$name,$phone,$note,$q['total'],$now,$now+900,json_encode($q['bank_snapshot'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$fingerprint]);
    $id=(int)$db->lastInsertId();
    foreach ($q['lines'] as $line) po_query($db,'INSERT INTO po_lines(order_id,product_id,name,price,qty) VALUES(?,?,?,?,?)',[$id,$line['product_id'],$line['name'],$line['price'],$line['qty']]);
    po_query($db,'INSERT INTO po_history(order_id,status,actor,created_at) VALUES(?,?,?,?)',[$id,'pending','Pelanggan manual',$now]);
    po_manual_event($db,$id,'checkout','Pelanggan','Reservasi pembayaran 15 menit.',null);
    po_wa_seed($db,$id,$token,$secret);
    return $token;
  });
}
function po_manual_release(PDO $db, int $id, string $target, array $actor, string $reason=''): bool {
  if (!in_array($target,['expired','cancelled'],true)) throw new DomainException('Tujuan pelepasan kuota tidak valid.');
  if ($target==='cancelled' && ($actor['role'] ?? '')!=='owner') throw new DomainException('Pembatalan pembayaran manual hanya untuk pemilik.');
  $name=po_text($actor['name'] ?? '', 'Pelaksana',1,80);
  $reason=po_text($reason,'Alasan',$target==='cancelled' ? 1 : 0,500);
  $sid=po_query($db,'SELECT slot_id FROM po_orders WHERE id=?',[$id])->fetchColumn();
  if (!$sid) throw new DomainException('Pesanan tidak ditemukan.');
  return po_manual_transaction($db, function () use ($db,$sid,$id,$target,$name,$reason) {
    po_slot($db,(int)$sid,true);
    $o=po_query($db,'SELECT * FROM po_orders WHERE id=? FOR UPDATE',[$id])->fetch();
    if ($o['flow_version']!=='manual_v1') throw new DomainException('Pesanan ini menggunakan alur demo lama.');
    if ($o['reservation_status']==='released') return false;
    if ($o['reservation_status']!=='held' || $o['payment_status']!=='unpaid' || $o['fulfillment_status']!=='awaiting_transfer') {
      if ($target==='expired') return false;
      throw new DomainException('Status sudah berubah; kuota tidak boleh dilepas melalui tindakan ini.');
    }
    if ($target==='expired' && (int)$o['expires_at']>time()) return false;
    $lines=po_query($db,'SELECT product_id,qty FROM po_lines WHERE order_id=? ORDER BY product_id',[$id])->fetchAll();
    foreach ($lines as $line) {
      if (po_query($db,'UPDATE po_products SET reserved=reserved-? WHERE id=? AND slot_id=? AND reserved>=?',[$line['qty'],$line['product_id'],$sid,$line['qty']])->rowCount()!==1) throw new RuntimeException('Reservasi kuota tidak konsisten.');
    }
    po_query($db,"UPDATE po_orders SET status=?,fulfillment_status=?,reservation_status='released' WHERE id=?",[$target,$target,$id]);
    po_query($db,'INSERT INTO po_history(order_id,status,actor,created_at) VALUES(?,?,?,?)',[$id,$target,$name,time()]);
    po_manual_event($db,$id,$target,$name,$reason,$o);
    return true;
  });
}
function po_manual_expire(PDO $db): void {
  $orders=po_query($db,"SELECT id FROM po_orders WHERE flow_version='manual_v1' AND fulfillment_status='awaiting_transfer' AND reservation_status='held' AND expires_at<=? ORDER BY id LIMIT 100",[time()])->fetchAll();
  foreach ($orders as $o) po_manual_release($db,(int)$o['id'],'expired',['role'=>'system','name'=>'Sistem'],'Batas pembayaran berakhir.');
}
