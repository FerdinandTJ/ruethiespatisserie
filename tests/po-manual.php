<?php
// CLI only. Database uji baru; tidak menggunakan konfigurasi/konten hosting.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
set_error_handler(static function($s,$m,$f,$l){ throw new ErrorException($m,0,$s,$f,$l); });
define('RUETHIES_ADMIN',true);
require dirname(__DIR__).'/admin/po-lib.php';
date_default_timezone_set('Asia/Jakarta');
$dsn=getenv('PO_TEST_DSN');
if (!$dsn || !str_starts_with($dsn,'mysql:')) { fwrite(STDERR,"Set PO_TEST_DSN ke server MySQL pengujian.\n"); exit(1); }
$user=getenv('PO_TEST_USER') ?: 'root';$pass=getenv('PO_TEST_PASSWORD') ?: '';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$server=new PDO($dsn,$user,$pass,$options);$database='ruethies_po_test_'.bin2hex(random_bytes(6));
$server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4");$testDsn=$dsn.';dbname='.$database;$db=new PDO($testDsn,$user,$pass,$options);
$root=sys_get_temp_dir().'/'.$database;define('RUETHIES_PUBLIC',$root);$secret=bin2hex(random_bytes(32));
function check(bool $ok,string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS: $label\n"; }
function denied(callable $fn,string $label): void { try {$fn();} catch (DomainException $e) {check(true,$label);return;} throw new RuntimeException('Expected rejection: '.$label); }
function make_manual_slot(PDO $db,string $title='Manual test'): int {
  $sid=po_save_slot($db,['title'=>$title,'flow_version'=>'manual_v1','opens_at'=>date('Y-m-d\TH:i',time()-3600),'closes_at'=>date('Y-m-d\TH:i',time()+3600),'pickup_from'=>date('Y-m-d\TH:i',time()+7200),'pickup_until'=>date('Y-m-d\TH:i',time()+10800)]);
  // Hanya fixture backend. Aksi admin normal belum boleh membuka manual_v1.
  po_query($db,'UPDATE po_slots SET is_open=1 WHERE id=?',[$sid]);return $sid;
}
function make_manual_product(PDO $db,int $sid,int $quota=5): int {
  $catalog=json_decode(file_get_contents(RUETHIES_PUBLIC.'/menu.json'),true);$uid=bin2hex(random_bytes(16));
  $catalog[]=['uid'=>$uid,'cat'=>'cakery','id'=>['n'=>'Croissant uji','d'=>'Contoh'],'available'=>true,'img'=>''];
  file_put_contents(RUETHIES_PUBLIC.'/menu.json',json_encode($catalog));
  return po_save_product($db,['slot_id'=>$sid,'menu_uid'=>$uid,'price'=>25000,'quota'=>$quota,'active'=>1]);
}
function manual_input(int $sid,int $pid,int $qty=1): array {
  static $phone=6281290000000;$phone++;
  return ['slot_id'=>$sid,'qty'=>[$pid=>$qty],'name'=>'Pelanggan uji','phone'=>(string)$phone,'note'=>'Contoh','consent'=>'yes'];
}
function manual_submit(PDO $db,array $input): array {
  global $secret;
  $nonce=bin2hex(random_bytes(32));$quote=po_quote_hash(po_quote($db,$input));
  $token=po_submit($db,$input,$nonce,$quote,$secret);
  $order=po_query($db,'SELECT * FROM po_orders WHERE request_hash=?',[hash('sha256',$nonce)])->fetch();
  return [$order,$token,$nonce,$quote];
}
function parallel_manual(array $bodies): array {
  global $testDsn,$user,$pass,$options;
  $children=[];
  foreach($bodies as $body) {
    $code='define("RUETHIES_ADMIN",true);define("RUETHIES_PUBLIC",'.var_export(RUETHIES_PUBLIC,true).');require '.var_export(dirname(__DIR__).'/admin/po-lib.php',true).';date_default_timezone_set("Asia/Jakarta");$db=new PDO('.var_export($testDsn,true).','.var_export($user,true).','.var_export($pass,true).','.var_export($options,true).');'.$body;
    $pipes=[];$p=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$children[]=[$p,$pipes];
  }
  $out=[];
  foreach($children as [$p,$pipes]) {$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($p)===0 && $err==='','parallel worker completed');}
  sort($out);return $out;
}
try {
  mkdir($root,0700,true);file_put_contents($root.'/menu.json','[]');
  foreach(explode(';',file_get_contents(dirname(__DIR__).'/admin/po-schema.sql')) as $sql) if(trim($sql)!=='')$db->exec($sql);
  // Pesanan lama sebelum kolom versi/status baru ada, dengan kuota terkonfirmasi.
  po_query($db,'INSERT INTO po_slots(title,opens_at,closes_at,pickup_from,pickup_until) VALUES(?,?,?,?,?)',['Legacy',time()-100,time()+1000,time()+2000,time()+3000]);$legacySlot=(int)$db->lastInsertId();
  po_query($db,'INSERT INTO po_products(slot_id,name,price,quota,reserved) VALUES(?,?,?,?,?)',[$legacySlot,'Legacy',25000,10,2]);$legacyProduct=(int)$db->lastInsertId();
  po_query($db,'INSERT INTO po_orders(slot_id,code,token_hash,request_hash,customer_name,phone,note,status,total,created_at,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$legacySlot,'LEGACY',str_repeat('a',64),str_repeat('b',64),'Contoh','628123456789','','confirmed',50000,time(),time()+2000]);$legacyOrder=(int)$db->lastInsertId();
  $legacyBefore=po_query($db,'SELECT status,total,expires_at FROM po_orders WHERE id=?',[$legacyOrder])->fetch();
  po_migrate_manual($db);po_migrate_manual($db);
  check(po_query($db,'SELECT status,total,expires_at FROM po_orders WHERE id=?',[$legacyOrder])->fetch()===$legacyBefore,'migration preserves legacy status, total and expiry');
  check(po_query($db,'SELECT flow_version FROM po_orders WHERE id=?',[$legacyOrder])->fetchColumn()==='legacy' && (int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$legacyProduct])->fetchColumn()===2,'migration marks legacy without reinterpreting reserved units');
  check((int)$db->query('SELECT COUNT(*) FROM po_schema_migrations')->fetchColumn()===1,'repeated migration records version once');
  $sid=make_manual_slot($db);$pid=make_manual_product($db,$sid,10);$input=manual_input($sid,$pid,2);
  denied(fn()=>po_toggle_slot($db,$sid,1),'admin cannot activate incomplete manual payment flow');
  denied(fn()=>po_save_slot($db,['id'=>$sid,'flow_version'=>'legacy','title'=>'Changed','opens_at'=>date('Y-m-d\TH:i',time()-3600),'closes_at'=>date('Y-m-d\TH:i',time()+3600),'pickup_from'=>date('Y-m-d\TH:i',time()+7200),'pickup_until'=>date('Y-m-d\TH:i',time()+10800)]),'schedule flow cannot be changed to mix quota semantics');
  denied(fn()=>po_quote($db,$input),'manual quote requires configured active bank');
  $bank=['bank_name'=>'Bank Contoh','account_number'=>'0012345678','account_name'=>'Pemilik Uji','instructions'=>'Cantumkan kode order','enabled'=>1,'version'=>0];$owner=['role'=>'owner','name'=>'owner'];
  po_payment_save($db,$bank,$owner);
  [$o,$token,$nonce,$hash]=manual_submit($db,$input);
  check($o['flow_version']==='manual_v1' && $o['payment_status']==='unpaid' && $o['fulfillment_status']==='awaiting_transfer' && $o['reservation_status']==='held','checkout stores independent manual states');
  check((int)$o['expires_at']-(int)$o['created_at']===900 && (int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$pid])->fetchColumn()===2,'checkout reserves quota for exactly 15 minutes');
  check((int)po_query($db,'SELECT quota FROM po_products WHERE id=?',[$pid])->fetchColumn()===10,'unpaid reservation does not consume total quota');
  check(json_decode($o['bank_snapshot'],true)['account_number']==='0012345678','checkout stores a bank snapshot with leading zeros');
  check(po_submit($db,$input,$nonce,$hash,$secret)===$token && (int)po_query($db,'SELECT COUNT(*) FROM po_orders WHERE request_hash=?',[hash('sha256',$nonce)])->fetchColumn()===1,'checkout retry returns same token without duplicate order');
  check((int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$pid])->fetchColumn()===2,'checkout retry does not reserve twice');
  denied(fn()=>po_submit($db,array_replace($input,['note'=>'Changed']),$nonce,$hash,$secret),'same checkout key rejects changed customer payload');
  denied(fn()=>po_submit($db,array_replace($input,['slot_id'=>$legacySlot]),$nonce,$hash,$secret),'same manual key cannot be replayed via a legacy slot');
  denied(fn()=>po_transition($db,(int)$o['id'],'pending','confirmed','owner'),'legacy confirmation cannot mark a manual order accepted');
  $changedInput=manual_input($sid,$pid);$oldHash=po_quote_hash(po_quote($db,$changedInput));
  po_payment_save($db,array_replace($bank,['account_number'=>'0098765432','version'=>1]),$owner);
  $bankAudit=po_query($db,'SELECT * FROM po_payment_events WHERE version=2')->fetch();
  check(json_decode($bankAudit['before_snapshot'],true)['account_number']==='0012345678' && json_decode($bankAudit['after_snapshot'],true)['account_number']==='0098765432','bank updates store before/after audit atomically');
  denied(fn()=>po_submit($db,$changedInput,bin2hex(random_bytes(32)),$oldHash,$secret),'changed bank requires a fresh review');
  check(po_query($db,'SELECT bank_snapshot FROM po_orders WHERE id=?',[$o['id']])->fetchColumn()===$o['bank_snapshot'],'existing order bank snapshot remains unchanged');
  po_payment_save($db,array_replace($bank,['enabled'=>0,'version'=>2]),$owner);
  denied(fn()=>po_submit($db,$changedInput,bin2hex(random_bytes(32)),$oldHash,$secret),'direct backend submit rejects disabled bank');
  check(po_submit($db,$input,$nonce,$hash,$secret)===$token,'retry still retrieves original order after bank is disabled');
  po_payment_save($db,array_replace($bank,['version'=>3]),$owner);
  denied(fn()=>po_manual_release($db,(int)$o['id'],'cancelled',['role'=>'staff','name'=>'staff'],'Uji'),'staff cannot cancel manual payment reservation');
  check(po_manual_release($db,(int)$o['id'],'cancelled',$owner,'Pelanggan membatalkan.') && !po_manual_release($db,(int)$o['id'],'cancelled',$owner,'Ulang'),'cancellation releases reservation once');
  check((int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$pid])->fetchColumn()===0,'cancelled order restores all held units');

  $raceSlot=make_manual_slot($db,'Last unit');$racePid=make_manual_product($db,$raceSlot,1);$jobs=[];
  foreach([manual_input($raceSlot,$racePid),manual_input($raceSlot,$racePid)] as $in) {
    $body='try{po_submit($db,'.var_export($in,true).','.var_export(bin2hex(random_bytes(32)),true).','.var_export(po_quote_hash(po_quote($db,$in)),true).','.var_export($secret,true).');echo "created";}catch(DomainException $e){echo "rejected";}';$jobs[]=$body;
  }
  check(parallel_manual($jobs)===['created','rejected'],'two simultaneous checkouts cannot reserve the same last unit');
  check((int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$racePid])->fetchColumn()===1,'last-unit race preserves quota invariant');
  $doubleSlot=make_manual_slot($db,'Double submit');$doublePid=make_manual_product($db,$doubleSlot,5);$in=manual_input($doubleSlot,$doublePid,2);$nonce2=bin2hex(random_bytes(32));
  $body='po_submit($db,'.var_export($in,true).','.var_export($nonce2,true).','.var_export(po_quote_hash(po_quote($db,$in)),true).','.var_export($secret,true).');echo "ok";';
  check(parallel_manual([$body,$body])===['ok','ok'],'simultaneous retries both resolve the same checkout');
  check((int)po_query($db,'SELECT COUNT(*) FROM po_orders WHERE request_hash=?',[hash('sha256',$nonce2)])->fetchColumn()===1 && (int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$doublePid])->fetchColumn()===2,'simultaneous retries create one order and one reservation');

  $bankRaceSlot=make_manual_slot($db,'Bank edit race');$bankRacePid=make_manual_product($db,$bankRaceSlot,5);$bankInput=manual_input($bankRaceSlot,$bankRacePid);
  $beforeBank=po_manual_bank($db);$bankHash=po_quote_hash(po_quote($db,$bankInput));
  $editBank=po_payment_settings($db);$editBank['account_number']='0077777777';
  $checkoutBody='try{po_submit($db,'.var_export($bankInput,true).','.var_export(bin2hex(random_bytes(32)),true).','.var_export($bankHash,true).','.var_export($secret,true).');echo "created";}catch(DomainException $e){echo "rejected";}';
  $editBody='po_payment_save($db,'.var_export($editBank,true).','.var_export($owner,true).');echo "edited";';
  $bankOut=parallel_manual([$checkoutBody,$editBody]);
  check(in_array($bankOut,[['created','edited'],['edited','rejected']],true),'bank edit versus checkout either preserves reviewed bank or requires new review');
  $bankOrder=po_query($db,'SELECT bank_snapshot FROM po_orders WHERE slot_id=?',[$bankRaceSlot])->fetch();
  check(!$bankOrder || json_decode($bankOrder['bank_snapshot'],true)===$beforeBank,'concurrent bank edit never silently substitutes payment instructions');

  $editSlot=make_manual_slot($db,'Product edit race');$editPid=make_manual_product($db,$editSlot,5);$editInput=manual_input($editSlot,$editPid,2);$editHash=po_quote_hash(po_quote($db,$editInput));
  $editProduct=['id'=>$editPid,'slot_id'=>$editSlot,'price'=>30000,'quota'=>1,'active'=>1];
  $checkoutBody='try{po_submit($db,'.var_export($editInput,true).','.var_export(bin2hex(random_bytes(32)),true).','.var_export($editHash,true).','.var_export($secret,true).');echo "created";}catch(DomainException $e){echo "checkout-rejected";}';
  $editBody='try{po_save_product($db,'.var_export($editProduct,true).');echo "edited";}catch(DomainException $e){echo "edit-rejected";}';
  $editOut=parallel_manual([$checkoutBody,$editBody]);
  check(in_array($editOut,[['checkout-rejected','edited'],['created','edit-rejected']],true),'quota reduction versus checkout serializes without overselling or changing reviewed price');

  $multiSlot=make_manual_slot($db,'Rollback');$p1=make_manual_product($db,$multiSlot,5);$p2=make_manual_product($db,$multiSlot,1);$in=manual_input($multiSlot,$p1,2);$in['qty'][$p2]=1;$before=po_quote_hash(po_quote($db,$in));
  manual_submit($db,manual_input($multiSlot,$p2));
  denied(fn()=>po_submit($db,$in,bin2hex(random_bytes(32)),$before,$secret),'one exhausted item rejects an entire multi-product order');
  check((int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$p1])->fetchColumn()===0,'failed basket leaves other product unreserved');
  $db->exec("CREATE TRIGGER fail_manual_audit BEFORE INSERT ON po_order_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test failure'");
  try { manual_submit($db,manual_input($multiSlot,$p1)); throw new RuntimeException('Expected storage failure'); } catch(PDOException $e) {check(true,'failure after reservation aborts checkout');} finally {$db->exec('DROP TRIGGER fail_manual_audit');}
  check((int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$p1])->fetchColumn()===0 && (int)po_query($db,'SELECT COUNT(*) FROM po_orders WHERE slot_id=?',[$multiSlot])->fetchColumn()===1,'order, lines, reservation and audit roll back together');

  [$expired]=manual_submit($db,manual_input($sid,$pid,3));
  po_query($db,'UPDATE po_orders SET expires_at=? WHERE id=?',[time()-1,$expired['id']]);
  $body='echo po_manual_release($db,'.(int)$expired['id'].',"expired",["role"=>"system","name"=>"Sistem"],"Uji") ? "changed" : "noop";';
  check(parallel_manual([$body,$body])===['changed','noop'],'two expiry workers release the same order once');
  check((int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$pid])->fetchColumn()===0 && (int)po_query($db,"SELECT COUNT(*) FROM po_order_events WHERE order_id=? AND event='expired'",[$expired['id']])->fetchColumn()===1,'expiry records one event and no negative reserved count');
  [$review]=manual_submit($db,manual_input($sid,$pid));
  po_query($db,"UPDATE po_orders SET payment_status='under_review',fulfillment_status='payment_review',expires_at=? WHERE id=?",[time()-1,$review['id']]);
  po_expire($db);
  check(po_query($db,'SELECT reservation_status FROM po_orders WHERE id=?',[$review['id']])->fetchColumn()==='held','expiry does not release a payment awaiting verification');
  denied(fn()=>po_manual_release($db,(int)$review['id'],'cancelled',$owner,'Uji'),'unpaid cancellation cannot release a payment under review');
  po_migrate_manual($db);
  check(po_query($db,'SELECT bank_snapshot FROM po_orders WHERE id=?',[$o['id']])->fetchColumn()===$o['bank_snapshot'],'migration rerun preserves manual snapshots and order data');
  check((int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$legacyProduct])->fetchColumn()===2,'all manual tests leave legacy reservations untouched');
  echo "All manual foundation tests passed.\n";
} finally {
  $db=null;$server->exec("DROP DATABASE `$database`");
  if (is_file($root.'/menu.json')) unlink($root.'/menu.json');
  if (is_dir($root)) rmdir($root);
}
