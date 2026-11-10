<?php
// Sandbox only. API endpoints are fixed, secrets never leave the backend.
defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.');
function po_mt_settings(array $config): array {
  $m=$config['midtrans']??[];
  if(!is_array($m))throw new RuntimeException('Konfigurasi Midtrans tidak valid.');
  $enabled=($m['enabled']??false)===true;$key=$m['server_key']??'';
  if(($m['environment']??'sandbox')!=='sandbox')throw new RuntimeException('Hanya Midtrans Sandbox yang diizinkan.');
  if(!is_string($key)||strlen($key)>512||preg_match('/[\r\n]/',$key)||($key!==''&&!str_starts_with($key,'SB-Mid-server-'))||($enabled&&$key===''))throw new RuntimeException('Server Key Sandbox belum valid.');
  return ['enabled'=>$enabled,'server_key'=>$key];
}
function po_mt_enabled(): bool {
  if(!function_exists('po_config'))return false;
  try{return po_mt_settings(po_config())['enabled'];}catch(Throwable $e){return false;}
}
function po_mt_checkout_allowed(string $flow): bool {
  return $flow==='midtrans_v1' && po_mt_enabled();
}
function po_mt_migrate(PDO $db): void {
  $db->exec("CREATE TABLE IF NOT EXISTS po_midtrans_payments (
    order_id BIGINT UNSIGNED PRIMARY KEY,
    gateway_order_id VARCHAR(50) NOT NULL UNIQUE,
    key_fingerprint CHAR(64) NOT NULL,
    create_state VARCHAR(16) NOT NULL DEFAULT 'new',
    snap_url VARCHAR(500) NULL,
    provider_status VARCHAR(24) NOT NULL DEFAULT 'new',
    transaction_id VARCHAR(100) NULL,
    claim_token CHAR(32) NULL,
    lease_until BIGINT NOT NULL DEFAULT 0,
    next_check BIGINT NOT NULL DEFAULT 0,
    last_error VARCHAR(40) NOT NULL DEFAULT '',
    updated_at BIGINT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES po_orders(id),
    INDEX po_mt_check(next_check,lease_until)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  po_query($db,'INSERT IGNORE INTO po_schema_migrations(version,applied_at) VALUES(?,?)',['midtrans-sandbox-v1',time()]);
}
function po_mt_seed(PDO $db,int $id): void {
  if(!$db->inTransaction())throw new LogicException('Payment must follow order transaction.');
  po_query($db,'INSERT INTO po_midtrans_payments(order_id,gateway_order_id,key_fingerprint,updated_at) VALUES(?,?,?,?)',[$id,'RTSTG-'.bin2hex(random_bytes(16)),hash('sha256',po_mt_settings(po_config())['server_key']),time()]);
}
function po_mt_url(string $url): bool {
  $p=parse_url($url);
  return is_array($p)&&($p['scheme']??'')==='https'&&($p['host']??'')==='app.sandbox.midtrans.com'&&!isset($p['user'])&&!isset($p['pass'])&&!isset($p['port'])&&!isset($p['query'])&&!isset($p['fragment'])&&(bool)preg_match('~\A/snap/v[0-9]+/redirection/[a-zA-Z0-9-]+\z~D',$p['path']??'');
}
function po_mt_api(array $settings,string $operation,string $id='',array $payload=[],?callable $transport=null): array {
  if($settings['server_key']==='')throw new RuntimeException('Server Key Sandbox belum diisi.');
  if(!in_array($operation,['create','status','expire'],true)||($operation!=='create'&&!preg_match('/\ARTSTG-[a-f0-9]{32}\z/D',$id)))throw new LogicException('Invalid gateway operation.');
  $url=$operation==='create'?'https://app.sandbox.midtrans.com/snap/v1/transactions':'https://api.sandbox.midtrans.com/v2/'.$id.'/'.$operation;
  if($transport)return $transport($operation,$id,$payload);
  $body='';$ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>$settings['server_key'].':',CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json'],CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_WRITEFUNCTION=>static function($ch,$part)use(&$body){if(strlen($body)+strlen($part)>65536)return 0;$body.=$part;return strlen($part);}]);
  if($operation!=='status')curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR)]);
  $ok=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
  $data=json_decode($body,true);
  if($ok===false||!is_array($data)||$http<200||$http>=300){
    if($operation==='status'&&$http===404&&is_array($data)&&($data['status_code']??'')==='404')return ['status_code'=>'404'];
    throw new RuntimeException('Midtrans Sandbox belum dapat dihubungi.');
  }
  return $data;
}
function po_mt_claim(PDO $db,int $id,string $purpose): ?array {
  $sid=po_query($db,'SELECT slot_id FROM po_orders WHERE id=?',[$id])->fetchColumn();if(!$sid)return null;
  return po_manual_transaction($db,static function()use($db,$sid,$id,$purpose){
    po_slot($db,(int)$sid,true);$o=po_query($db,'SELECT * FROM po_orders WHERE id=? FOR UPDATE',[$id])->fetch();
    if($o['flow_version']!=='midtrans_v1')return null;
    $p=po_query($db,'SELECT * FROM po_midtrans_payments WHERE order_id=? FOR UPDATE',[$id])->fetch();
    if(!$p)throw new RuntimeException('Jalankan migrasi Midtrans.');
    if((int)$p['lease_until']>time())return null;
    if($purpose==='create'){
      if($p['create_state']!=='new'||$o['reservation_status']!=='held'||$o['payment_status']!=='unpaid'||(int)$o['expires_at']<time()+300)return null;
    }elseif((int)$p['next_check']>time())return null;
    $claim=bin2hex(random_bytes(16));
    po_query($db,'UPDATE po_midtrans_payments SET claim_token=?,lease_until=?,next_check=?,create_state=?,updated_at=? WHERE order_id=?',[$claim,time()+30,time()+20,$purpose==='create'?'creating':$p['create_state'],time(),$id]);
    return ['order'=>$o,'payment'=>$p,'claim'=>$claim];
  });
}
function po_mt_create(PDO $db,int $id,array $settings,?callable $transport=null): ?string {
  $p=po_query($db,'SELECT * FROM po_midtrans_payments WHERE order_id=?',[$id])->fetch();
  $o=po_query($db,'SELECT * FROM po_orders WHERE id=?',[$id])->fetch();
  if(!$p||!$o||$o['flow_version']!=='midtrans_v1')throw new DomainException('Invoice Sandbox tidak ditemukan.');
  if(!hash_equals($p['key_fingerprint'],hash('sha256',$settings['server_key'])))throw new RuntimeException('Server Key tidak cocok dengan invoice lama.');
  if($o['payment_status']!=='unpaid'||$o['reservation_status']!=='held'||(int)$o['expires_at']<=time())return null;
  if($p['snap_url']&&po_mt_url($p['snap_url']))return $p['snap_url'];
  if(!$settings['enabled'])throw new DomainException('Pembuatan pembayaran Sandbox sedang dinonaktifkan.');
  $job=po_mt_claim($db,$id,'create');if(!$job)return null;
  $o=$job['order'];$minutes=max(5,(int)floor(((int)$o['expires_at']-time())/60));
  $payload=['transaction_details'=>['order_id'=>$p['gateway_order_id'],'gross_amount'=>(int)$o['total']],
    'customer_details'=>['first_name'=>$o['customer_name'],'phone'=>$o['phone']],
    'enabled_payments'=>['bca_va','bni_va','bri_va','permata_va','other_va','gopay','qris'],
    'expiry'=>['start_time'=>date('Y-m-d H:i:s O',(int)$o['created_at']),'unit'=>'minutes','duration'=>15],
    'page_expiry'=>['unit'=>'minutes','duration'=>$minutes],
    'callbacks'=>['finish'=>'https://staging.ruethiespatisserie.com/pre-order/return/']];
  // Network I/O happens outside any quota/account lock or DB transaction.
  try{
    $response=po_mt_api($settings,'create','',$payload,$transport);$url=$response['redirect_url']??'';
    if(!is_string($url)||!po_mt_url($url))throw new RuntimeException('Invalid Snap response.');
    po_query($db,"UPDATE po_midtrans_payments SET create_state='ready',snap_url=?,claim_token=NULL,lease_until=0,last_error='',updated_at=? WHERE order_id=? AND claim_token=?",[$url,time(),$id,$job['claim']]);
    return $url;
  }catch(Throwable $e){
    po_query($db,"UPDATE po_midtrans_payments SET create_state='uncertain',claim_token=NULL,lease_until=0,last_error='create_uncertain',updated_at=? WHERE order_id=? AND claim_token=?",[time(),$id,$job['claim']]);
    throw new DomainException('Halaman pembayaran belum tersedia. Status akan diperiksa kembali; jangan membuat pesanan atau membayar ulang.');
  }
}
function po_mt_amount($amount,int $expected): bool {
  return is_string($amount)&&preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.00)?\z/D',$amount)&&explode('.',$amount)[0]===(string)$expected;
}
function po_mt_apply(PDO $db,int $id,array $data): void {
  $sid=po_query($db,'SELECT slot_id FROM po_orders WHERE id=?',[$id])->fetchColumn();if(!$sid)throw new DomainException('Pesanan tidak ditemukan.');
  po_manual_transaction($db,static function()use($db,$sid,$id,$data){
    po_slot($db,(int)$sid,true);$o=po_query($db,'SELECT * FROM po_orders WHERE id=? FOR UPDATE',[$id])->fetch();$p=po_query($db,'SELECT * FROM po_midtrans_payments WHERE order_id=? FOR UPDATE',[$id])->fetch();
    if(!$p||$o['flow_version']!=='midtrans_v1')throw new DomainException('Invoice Sandbox tidak ditemukan.');
    $state=$data['transaction_status']??'';
    if(!is_string($state)||!in_array($state,['pending','settlement','capture','deny','cancel','expire','refund','partial_refund','authorize'],true)||($data['order_id']??'')!==$p['gateway_order_id']||!po_mt_amount($data['gross_amount']??null,(int)$o['total'])||(isset($data['currency'])&&$data['currency']!=='IDR'))throw new DomainException('Status pembayaran tidak cocok dengan invoice.');
    $tx=$data['transaction_id']??'';
    if(!is_string($tx)||$tx===''||strlen($tx)>100||($p['transaction_id']!==null&&$p['transaction_id']!==$tx))throw new DomainException('Identitas transaksi tidak cocok.');
    $paid=in_array($state,['settlement','capture'],true)&&($data['status_code']??'')==='200'&&(!isset($data['fraud_status'])||$data['fraud_status']==='accept');
    if(in_array($state,['refund','partial_refund'],true)){
      po_query($db,"UPDATE po_midtrans_payments SET provider_status=?,transaction_id=?,last_error='review_required',updated_at=? WHERE order_id=?",[$state,$tx,time(),$id]);
      if($o['payment_status']==='paid'){
        po_query($db,"UPDATE po_orders SET payment_status='paid_review',fulfillment_status='payment_review' WHERE id=?",[$id]);
        po_manual_event($db,$id,'midtrans_refund_review','Midtrans Sandbox','Refund memerlukan tinjauan pemilik; kuota tidak diubah.',$o);
        po_wa_enqueue($db,$id,'late_paid','refund_review','owner');
      }
      return;
    }
    // Success cannot be rolled back by delayed pending/expiry notifications.
    if(in_array($o['payment_status'],['paid','paid_review'],true))return;
    if($paid){
      if($o['reservation_status']==='held'){
        po_query($db,"UPDATE po_orders SET status='confirmed',payment_status='paid',fulfillment_status='processing',reservation_status='committed' WHERE id=?",[$id]);
        po_query($db,'INSERT INTO po_history(order_id,status,actor,created_at) VALUES(?,?,?,?)',[$id,'confirmed','Midtrans Sandbox',time()]);
        po_wa_enqueue($db,$id,'paid','paid');po_wa_enqueue($db,$id,'paid','paid','owner');
      }else{
        // Late funds never silently take stock already allocated to other customers.
        po_query($db,"UPDATE po_orders SET payment_status='paid_review',fulfillment_status='payment_review' WHERE id=?",[$id]);
        po_wa_enqueue($db,$id,'late_paid','late_paid');po_wa_enqueue($db,$id,'late_paid','late_paid','owner');
      }
      po_manual_event($db,$id,'midtrans_paid','Midtrans Sandbox','Status diverifikasi melalui API Sandbox.',$o);
    }elseif(in_array($state,['expire','cancel','deny'],true)&&$o['reservation_status']==='held'){
      po_mt_release_locked($db,$o,$state==='expire'?'expired':($state==='deny'?'rejected':'cancelled'));
    }
    po_query($db,'UPDATE po_midtrans_payments SET provider_status=?,transaction_id=?,last_error=?,updated_at=? WHERE order_id=?',[$state,$tx,$paid?'':(in_array($state,['refund','partial_refund'],true)?'review_required':''),time(),$id]);
  });
}
function po_mt_release_locked(PDO $db,array $o,string $target): void {
  foreach(po_query($db,'SELECT product_id,qty FROM po_lines WHERE order_id=? ORDER BY product_id',[$o['id']])->fetchAll()as$l){
    if(po_query($db,'UPDATE po_products SET reserved=reserved-? WHERE id=? AND slot_id=? AND reserved>=?',[$l['qty'],$l['product_id'],$o['slot_id'],$l['qty']])->rowCount()!==1)throw new RuntimeException('Reservasi tidak konsisten.');
  }
  po_query($db,"UPDATE po_orders SET status=?,fulfillment_status=?,reservation_status='released' WHERE id=?",[$target,$target,$o['id']]);
  po_query($db,'INSERT INTO po_history(order_id,status,actor,created_at) VALUES(?,?,?,?)',[$o['id'],$target,'Midtrans Sandbox',time()]);
  po_manual_event($db,(int)$o['id'],'midtrans_closed','Midtrans Sandbox','Kuota dilepas setelah pemeriksaan pembayaran.',$o);
}
function po_mt_reconcile(PDO $db,int $id,array $settings,?callable $transport=null): void {
  $job=po_mt_claim($db,$id,'status');if(!$job)return;
  try{
    if(!hash_equals($job['payment']['key_fingerprint'],hash('sha256',$settings['server_key'])))throw new RuntimeException('Server Key berubah.');
    $data=po_mt_api($settings,'status',$job['payment']['gateway_order_id'],[],$transport);
    if(($data['status_code']??'')==='404'){
      // A Snap token may exist before a payment method is selected. Never equate
      // API 404 with immediate failure. Wait until both expiry windows + grace.
      $o=$job['order'];
      if((int)$o['expires_at']+120<=time())po_manual_transaction($db,static function()use($db,$o){
        po_slot($db,(int)$o['slot_id'],true);$fresh=po_query($db,'SELECT * FROM po_orders WHERE id=? FOR UPDATE',[$o['id']])->fetch();
        if($fresh['payment_status']==='unpaid'&&$fresh['reservation_status']==='held')po_mt_release_locked($db,$fresh,'expired');
      });
    }else{
      po_mt_apply($db,$id,$data);
      if(($data['transaction_status']??'')==='pending'&&(int)$job['order']['expires_at']<=time()){
        po_mt_api($settings,'expire',$job['payment']['gateway_order_id'],[],$transport);
        // Expire response alone is not sufficient: reconcile authoritative status.
        po_mt_apply($db,$id,po_mt_api($settings,'status',$job['payment']['gateway_order_id'],[],$transport));
      }
    }
    $delay=$job['order']['reservation_status']==='released'?300:60;
    po_query($db,'UPDATE po_midtrans_payments SET next_check=? WHERE order_id=? AND claim_token=?',[time()+$delay,$id,$job['claim']]);
    po_query($db,"UPDATE po_midtrans_payments SET claim_token=NULL,lease_until=0,last_error=CASE WHEN last_error='review_required' THEN last_error ELSE '' END,updated_at=? WHERE order_id=? AND claim_token=?",[time(),$id,$job['claim']]);
  }catch(Throwable $e){
    po_query($db,"UPDATE po_midtrans_payments SET claim_token=NULL,lease_until=0,last_error='status_unavailable',next_check=?,updated_at=? WHERE order_id=? AND claim_token=?",[time()+60,time(),$id,$job['claim']]);
    throw new RuntimeException('Status Sandbox belum dapat dipastikan. Kuota belum dilepas.');
  }
}
function po_mt_worker(PDO $db,array $config,?callable $transport=null): array {
  // Missing new table on an older manual-only installation must not block cron.
  if(!po_query($db,"SHOW TABLES LIKE 'po_midtrans_payments'")->fetch())return ['checked'=>0];
  $settings=po_mt_settings($config);if($settings['server_key']==='')return ['checked'=>0];
  $rows=po_query($db,"SELECT p.order_id FROM po_midtrans_payments p JOIN po_orders o ON o.id=p.order_id WHERE o.payment_status='unpaid' AND (o.reservation_status='held' OR o.created_at>?) AND p.next_check<=? AND p.lease_until<=? ORDER BY (o.reservation_status='held') DESC,p.next_check,p.order_id LIMIT 3",[time()-86400,time(),time()])->fetchAll();$errors=0;
  foreach($rows as$r){try{po_mt_reconcile($db,(int)$r['order_id'],$settings,$transport);}catch(Throwable $e){$errors++;}}
  return ['checked'=>count($rows),'errors'=>$errors];
}
function po_mt_notification(PDO $db,array $settings,string $raw,?callable $transport=null): void {
  try{$d=json_decode($raw,true,16,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new DomainException('Invalid notification.');}
  if(!is_array($d))throw new DomainException('Invalid notification.');
  foreach(['order_id','status_code','gross_amount','signature_key']as$k)if(!is_string($d[$k]??null)||strlen($d[$k])>150)throw new DomainException('Invalid notification.');
  if($settings['server_key']===''||!hash_equals(hash('sha512',$d['order_id'].$d['status_code'].$d['gross_amount'].$settings['server_key']),$d['signature_key']))throw new DomainException('Invalid signature.');
  $p=po_query($db,'SELECT order_id FROM po_midtrans_payments WHERE gateway_order_id=?',[$d['order_id']])->fetch();
  if(!$p)throw new DomainException('Unknown payment.');
  // Signature covers only selected fields; fetch the status from Midtrans instead
  // of trusting other notification fields or browser redirect parameters.
  po_mt_reconcile($db,(int)$p['order_id'],$settings,$transport);
}
