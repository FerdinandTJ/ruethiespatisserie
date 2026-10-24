<?php
// CLI only. Membuat lalu menghapus DATABASE BARU berawalan ruethies_po_test_.
// PO_TEST_DSN='mysql:unix_socket=/path/mysql.sock;charset=utf8mb4' PO_TEST_USER=root php tests/po-flow.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
set_error_handler(static function ($severity,$message,$file,$line) { throw new ErrorException($message,0,$severity,$file,$line); });
define('RUETHIES_ADMIN', true);
require dirname(__DIR__) . '/admin/po-lib.php';
date_default_timezone_set('Asia/Jakarta');
$dsn = getenv('PO_TEST_DSN');
if (!$dsn || !str_starts_with($dsn,'mysql:')) { fwrite(STDERR,"Set PO_TEST_DSN ke server MySQL pengujian, bukan produksi.\n"); exit(1); }
$user = getenv('PO_TEST_USER') ?: 'root'; $pass = getenv('PO_TEST_PASSWORD') ?: '';
$options = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$server = new PDO($dsn,$user,$pass,$options);
$database = 'ruethies_po_test_' . bin2hex(random_bytes(6));
$server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4");
$testDsn = $dsn . ';dbname=' . $database;
$db = new PDO($testDsn,$user,$pass,$options);
$secret = bin2hex(random_bytes(32));
$root = sys_get_temp_dir() . '/' . $database;
define('RUETHIES_PUBLIC',$root.'/catalog');
$process = null;
function verify(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); print "PASS: $label\n"; }
function rejects(callable $fn, string $label): void { try { $fn(); } catch (DomainException $e) { verify(true,$label); return; } throw new RuntimeException('Expected rejection: '.$label); }
function make_slot(PDO $db, string $name = 'Demo'): int {
  $id = po_save_slot($db,['title'=>$name,'opens_at'=>date('Y-m-d\TH:i',time()-3600),'closes_at'=>date('Y-m-d\TH:i',time()+7200),'pickup_from'=>date('Y-m-d\TH:i',time()+10800),'pickup_until'=>date('Y-m-d\TH:i',time()+14400)]);
  po_toggle_slot($db,$id,1); return $id;
}
function make_product(PDO $db,int $sid,int $quota=10): int {
  $catalog = json_decode(file_get_contents(RUETHIES_PUBLIC.'/menu.json'),true);
  $uid = bin2hex(random_bytes(16));
  $catalog[] = ['uid'=>$uid,'cat'=>'cakery','id'=>['n'=>'Croissant Demo','d'=>'Deskripsi katalog'],'img'=>'images/menu/example.jpg','available'=>true];
  file_put_contents(RUETHIES_PUBLIC.'/menu.json',json_encode($catalog));
  return po_save_product($db,['slot_id'=>$sid,'menu_uid'=>$uid,'name'=>'Untrusted name','price'=>25000,'quota'=>$quota,'active'=>1]);
}
function input_for(int $sid,int $pid,int $qty=1,string $phone='081234567890'): array {
  return ['slot_id'=>$sid,'qty'=>[$pid=>(string)$qty],'name'=>'Pelanggan Contoh','phone'=>$phone,'note'=>'Tanpa pembayaran','consent'=>'yes'];
}
function submit_test(PDO $db,array $input): array {
  global $secret;
  $token = po_submit($db,$input,bin2hex(random_bytes(32)),po_quote_hash(po_quote($db,$input)),$secret);
  return po_order($db,$token);
}
function remove_fixture(string $path): void {
  if (!file_exists($path) && !is_link($path)) return;
  if (is_link($path) || !is_dir($path)) { unlink($path); return; }
  foreach (scandir($path) as $n) if ($n !== '.' && $n !== '..') remove_fixture($path.'/'.$n);
  rmdir($path);
}
try {
  mkdir(RUETHIES_PUBLIC,0700,true);file_put_contents(RUETHIES_PUBLIC.'/menu.json','[]');
  foreach (preg_split('/;\s*(?:\n|$)/',file_get_contents(dirname(__DIR__).'/admin/po-schema.sql')) as $sql) if (trim($sql)!=='') $db->exec($sql);
  $db->exec('ALTER TABLE po_products DROP INDEX po_slot_menu, DROP COLUMN menu_uid, DROP COLUMN image');
  $legacySlot=make_slot($db,'Legacy migration');
  po_query($db,'INSERT INTO po_products(slot_id,name,price,quota) VALUES(?,?,?,?)',[$legacySlot,'Manual lama',12345,7]);
  $legacyId=(int)$db->lastInsertId();
  po_migrate_catalog($db);po_migrate_catalog($db);
  $legacy=po_query($db,'SELECT * FROM po_products WHERE id=?',[$legacyId])->fetch();
  verify($legacy['menu_uid']===null && (int)$legacy['price']===12345,'repeatable migration preserves legacy product and price');
  verify(po_public_products($db,$legacySlot)===[],'unlinked legacy product cannot receive new submissions');
  $sid = make_slot($db); $pid = make_product($db,$sid,3);
  $linked=po_query($db,'SELECT * FROM po_products WHERE id=?',[$pid])->fetch();
  rejects(fn()=>po_save_product($db,['slot_id'=>$sid,'menu_uid'=>$linked['menu_uid'],'price'=>27000,'quota'=>5,'active'=>1]),'duplicate catalog menu in one schedule rejected');
  $otherPriceSlot=make_slot($db,'Different price');
  po_save_product($db,['slot_id'=>$otherPriceSlot,'menu_uid'=>$linked['menu_uid'],'price'=>27000,'quota'=>5,'active'=>1]);
  verify((int)po_public_products($db,$otherPriceSlot)[0]['price']===27000 && (int)$linked['price']===25000,'each schedule stores its own price');
  $input = input_for($sid,$pid,2); $input['price'] = 1;
  $quote = po_quote($db,$input);
  verify($quote['total']===50000,'server ignores client price');
  $nonce = bin2hex(random_bytes(32));
  $token = po_submit($db,$input,$nonce,po_quote_hash($quote),$secret);
  verify(po_submit($db,$input,$nonce,po_quote_hash($quote),$secret)===$token,'repeated submit returns the same order');
  verify((int)$db->query('SELECT COUNT(*) FROM po_orders')->fetchColumn()===1,'idempotency prevents duplicate rows');
  $order = po_order($db,$token); $oid = (int)$order['id'];
  verify($order['status']==='pending' && (int)$db->query("SELECT reserved FROM po_products WHERE id=$pid")->fetchColumn()===0,'pending does not consume quota');
  verify(po_order($db,str_repeat('a',64))===null && po_order($db,'PO-123')===null,'status requires an unguessable token');
  po_transition($db,$oid,'pending','confirmed','Owner');
  verify((int)$db->query("SELECT reserved FROM po_products WHERE id=$pid")->fetchColumn()===2,'confirmation reserves quota');
  rejects(fn()=>po_transition($db,$oid,'pending','confirmed','Owner'),'stale double confirmation rejected');
  rejects(fn()=>po_save_product($db,['id'=>$pid,'slot_id'=>$sid,'name'=>'Other','price'=>30000,'quota'=>1,'active'=>1]),'quota cannot be reduced below confirmed units');
  po_save_product($db,['id'=>$pid,'slot_id'=>$sid,'name'=>'New name','price'=>35000,'quota'=>3,'active'=>1]);
  verify((int)po_order($db,$token)['lines'][0]['price']===25000 && po_order($db,$token)['lines'][0]['name']==='Croissant Demo','order snapshot survives product changes');
  po_transition($db,$oid,'confirmed','cancelled','Owner');
  verify((int)$db->query("SELECT reserved FROM po_products WHERE id=$pid")->fetchColumn()===0,'cancellation releases quota once');
  rejects(fn()=>po_transition($db,$oid,'cancelled','confirmed','Owner'),'terminal order cannot be reopened');
  rejects(fn()=>po_save_slot($db,['id'=>$sid,'title'=>'Changed','opens_at'=>date('Y-m-d\TH:i',time()-3600),'closes_at'=>date('Y-m-d\TH:i',time()+7200),'pickup_from'=>date('Y-m-d\TH:i',time()+10800),'pickup_until'=>date('Y-m-d\TH:i',time()+14400)]),'schedule with orders cannot change pickup times');
  rejects(fn()=>po_quote($db,input_for($sid,$pid,21)),'quantity limit enforced');
  rejects(fn()=>po_quote($db,['slot_id'=>$sid,'qty'=>[]]),'empty basket rejected');
  $sid2 = make_slot($db,'Second slot'); $pid2 = make_product($db,$sid2,20);
  rejects(fn()=>po_quote($db,input_for($sid,$pid2)),'products from another slot rejected');
  po_toggle_slot($db,$sid,0);
  rejects(fn()=>po_quote($db,$input),'closed schedule rejected');
  po_toggle_slot($db,$sid,1);
  $newInput = input_for($sid,$pid,1,'081234567891');
  $oldQuote = po_quote_hash(po_quote($db,$newInput));
  po_save_product($db,['id'=>$pid,'slot_id'=>$sid,'name'=>'Changed price','price'=>40000,'quota'=>3,'active'=>1]);
  rejects(fn()=>po_submit($db,$newInput,bin2hex(random_bytes(32)),$oldQuote,$secret),'changed price requires new review');
  $expiring = submit_test($db,$newInput);
  po_query($db,'UPDATE po_orders SET expires_at=? WHERE id=?',[time()-1,$expiring['id']]);
  po_expire($db);
  verify(po_query($db,'SELECT status FROM po_orders WHERE id=?',[$expiring['id']])->fetchColumn()==='expired','pending expiration applied');
  rejects(fn()=>po_transition($db,(int)$expiring['id'],'pending','confirmed','Owner'),'expired request cannot be confirmed');
  $phoneInput = input_for($sid2,$pid2,1,'081234567892');
  for ($i=0;$i<3;$i++) submit_test($db,$phoneInput);
  rejects(fn()=>submit_test($db,$phoneInput),'three pending requests per number enforced');
  po_rate($db,'test-rate',1);
  rejects(fn()=>po_rate($db,'test-rate',1),'rate limit enforced');
  rejects(fn()=>po_customer(['name'=>'Demo','phone'=>'oops','note'=>'','consent'=>'yes']),'invalid phone rejected');
  rejects(fn()=>po_customer(['name'=>'Demo','phone'=>'081234567890','note'=>'','consent'=>'']),'contact consent required');

  // Dua koneksi sungguhan mengonfirmasi kuota terakhir secara bersamaan.
  $raceSlot = make_slot($db,'Concurrent slot'); $raceProduct = make_product($db,$raceSlot,1);
  $a = submit_test($db,input_for($raceSlot,$raceProduct,1,'081234567893'));
  $b = submit_test($db,input_for($raceSlot,$raceProduct,1,'081234567894'));
  $workers = [];
  foreach ([$a,$b] as $o) {
    $code = 'define("RUETHIES_ADMIN",true);require '.var_export(dirname(__DIR__).'/admin/po-lib.php',true).';$db=new PDO('.var_export($testDsn,true).','.var_export($user,true).','.var_export($pass,true).',' . var_export($options,true) . ');try{po_transition($db,'.(int)$o['id'].',"pending","confirmed","race");echo "confirmed";}catch(DomainException $e){echo "rejected";}';
    $pipes=[]; $proc=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $workers[]=[$proc,$pipes];
  }
  $out=[];
  foreach ($workers as [$proc,$pipes]) { $out[]=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]);fclose($pipes[2]);verify(proc_close($proc)===0 && $err==='','concurrent worker completed'); }
  sort($out);
  verify($out===['confirmed','rejected'],'only one concurrent confirmation takes last quota');
  verify((int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$raceProduct])->fetchColumn()===1,'quota remains consistent after race');
  // Bila produk kedua kehabisan kuota, reservasi produk pertama juga dibatalkan.
  $multiSlot=make_slot($db,'Atomic multi-product');$p1=make_product($db,$multiSlot,2);$p2=make_product($db,$multiSlot,1);
  $multiInput=input_for($multiSlot,$p1,1,'081234567896');$multiInput['qty'][$p2]='1';
  $multiOrder=submit_test($db,$multiInput);
  $lastOrder=submit_test($db,input_for($multiSlot,$p2,1,'081234567897'));
  po_transition($db,(int)$lastOrder['id'],'pending','confirmed','Owner');
  rejects(fn()=>po_transition($db,(int)$multiOrder['id'],'pending','confirmed','Owner'),'multi-product confirmation fails when one quota is exhausted');
  verify((int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$p1])->fetchColumn()===0,'partial quota update rolled back');

  $catalog=json_decode(file_get_contents(RUETHIES_PUBLIC.'/menu.json'),true);
  foreach($catalog as &$item)if($item['uid']===$linked['menu_uid']){$item['id']['n']='Renamed catalog';$item['available']=false;}unset($item);
  file_put_contents(RUETHIES_PUBLIC.'/menu.json',json_encode($catalog));
  rejects(fn()=>po_quote($db,input_for($sid,$pid,1)),'hidden catalog menu blocks new checkout');
  verify(po_order($db,$token)['lines'][0]['name']==='Croissant Demo','catalog rename does not alter existing order snapshot');
  $catalog=array_values(array_filter($catalog,fn($m)=>$m['uid']!==$linked['menu_uid']));
  file_put_contents(RUETHIES_PUBLIC.'/menu.json',json_encode($catalog));
  rejects(fn()=>po_quote($db,input_for($sid,$pid,1)),'deleted catalog menu blocks new checkout');
  verify(po_order($db,$token)['lines'][0]['name']==='Croissant Demo','deleted catalog preserves order history');

  // HTTP integration: fixture dengan layout Hostinger, akun contoh, dan database terpisah.
  $public=$root.'/public_html/staging'; $private=$root.'/ruethies-data-staging';
  mkdir($public.'/admin',0700,true);mkdir($private,0700,true);
  foreach (['po.php','po.css','po.js','menu.json','atmosphere.json','faq.json','admin/config.php','admin/lib.php','admin/po-lib.php','admin/po.php','admin/index.php','admin/admin.css','admin/admin.js','admin/po-admin.css','admin/po-install.php','admin/po-schema.sql'] as $file) copy(dirname(__DIR__).'/'.$file,$public.'/'.$file);
  copy(RUETHIES_PUBLIC.'/menu.json',$public.'/menu.json');
  $cfg=['dsn'=>$testDsn,'user'=>$user,'password'=>$pass,'secret'=>$secret];
  file_put_contents($private.'/po-config.php','<?php return '.var_export($cfg,true).';');
  file_put_contents($private.'/users.json',json_encode([
    ['name'=>'owner','hash'=>password_hash('Demo-test-password',PASSWORD_DEFAULT),'role'=>'owner','totp'=>''],
    ['name'=>'staff','hash'=>password_hash('Demo-test-password',PASSWORD_DEFAULT),'role'=>'staff','totp'=>''],
  ]));
  $before=(int)$db->query('SELECT COUNT(*) FROM po_orders')->fetchColumn();
  $installPipes=[];$install=proc_open([PHP_BINARY,$public.'/admin/po-install.php','--seed-demo'],[1=>['pipe','w'],2=>['pipe','w']],$installPipes);
  $installOutput=stream_get_contents($installPipes[1]);$installError=stream_get_contents($installPipes[2]);fclose($installPipes[1]);fclose($installPipes[2]);
  verify(proc_close($install)===0 && $installError==='' && str_contains($installOutput,'Seed dilewati'),'installer can be rerun without reseeding');
  verify((int)$db->query('SELECT COUNT(*) FROM po_orders')->fetchColumn()===$before,'installer preserves existing orders');
  $socket=stream_socket_server('tcp://127.0.0.1:0');$address=stream_socket_get_name($socket,false);fclose($socket);
  $process=proc_open([PHP_BINARY,'-S',$address,'-t',$public,__DIR__.'/po-router.php'],[0=>['pipe','r'],1=>['file',$root.'/http.log','a'],2=>['file',$root.'/http.log','a']],$httpPipes);
  $base='http://'.$address;
  $curl=curl_init();$responseHeaders=[];curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_USERAGENT=>'RuethiesTest',CURLOPT_TIMEOUT=>10,CURLOPT_HEADERFUNCTION=>static function($ch,$header)use(&$responseHeaders){$responseHeaders[]=trim($header);return strlen($header);}]);
  function http_request(string $path,?array $data=null): array {
    global $curl,$base,$responseHeaders;
    $responseHeaders=[];
    curl_setopt_array($curl,[CURLOPT_URL=>$base.$path,CURLOPT_POST=>$data!==null,CURLOPT_HEADER=>false]);
    if($data!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($data));
    $body=curl_exec($curl);
    return [(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),(string)$body,(string)curl_getinfo($curl,CURLINFO_REDIRECT_URL)];
  }
  function csrf_from(string $body): string { if(!preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m))throw new RuntimeException('CSRF missing');return $m[1]; }
  for($i=0;$i<40;$i++){usleep(100000);[$code,$html]=http_request('/pre-order/?slot='.$sid2);if($code===200)break;}
  verify($code===200 && str_contains($html,'DEMO STAGING'),'public page renders');
  verify(str_contains($html,'action="/pre-order/"') && str_contains($html,'src="/images/menu/example.jpg"') && str_contains($html,'href="/po.css?v=2"'),'forms and assets use root-absolute URLs');
  $csrf=csrf_from($html);
  [$code,$html,$redirect]=http_request('/po.php?slot='.$sid2);
  verify($code===302 && $redirect===$base.'/pre-order/?slot='.$sid2,'legacy catalog URL retains slot in clean redirect');
  [$code,$html,$redirect]=http_request('/pre-order?slot='.$sid2);
  verify($code===302 && $redirect===$base.'/pre-order/?slot='.$sid2,'missing trailing slash is canonicalized');
  [$code,$html]=http_request('/pre-order/status/');
  verify($code===404,'status without a token cannot show the order form');
  [$code,$html]=http_request('/pre-order/',['action'=>'review','csrf'=>'invalid']);
  verify(str_contains($html,'Sesi formulir berakhir'),'public CSRF rejected');
  $webInput=input_for($sid2,$pid2,1,'081234567895');$webInput['name']='<script>alert(1)</script>';
  [$code,$html]=http_request('/pre-order/',$webInput+['action'=>'review','csrf'=>$csrf]);
  verify($code===200 && str_contains($html,'Periksa pengajuan Anda') && !str_contains($html,'<script>alert(1)</script>') && str_contains($html,'&lt;script&gt;'),'review renders and escapes customer input');
  preg_match('/name="nonce" value="([a-f0-9]+)"/',$html,$m);$webNonce=$m[1]??'';
  [$code,$html,$redirect]=http_request('/po.php?edit='.$webNonce);
  verify($code===302 && $redirect===$base.'/pre-order/?edit='.$webNonce,'legacy edit URL retains session draft key');
  [$code,$html,$redirect]=http_request('/pre-order/',['action'=>'submit','csrf'=>$csrf,'nonce'=>$webNonce]);
  verify($code===303 && str_contains($redirect,'/pre-order/status/?t='),'checkout redirects to clean private status');
  $firstRedirect=$redirect;
  [$code,$html,$redirect]=http_request('/po.php',['action'=>'submit','csrf'=>$csrf,'nonce'=>$webNonce]);
  verify($code===303 && $redirect===$firstRedirect,'legacy POST is processed without losing its body or duplicating an order');
  [$code,$html,$redirect]=http_request('/po.php?'.parse_url($firstRedirect,PHP_URL_QUERY));
  verify($code===302 && $redirect===$firstRedirect,'legacy private token survives redirect unchanged');
  [$code,$html]=http_request(parse_url($firstRedirect,PHP_URL_PATH).'?'.parse_url($firstRedirect,PHP_URL_QUERY));
  verify($code===200 && str_contains($html,'Menunggu konfirmasi') && !str_contains($html,'081234567895'),'status visible without exposing contact');
  verify((bool)array_filter($responseHeaders,fn($h)=>str_contains(strtolower($h),'cache-control: private, no-store')),'private order responses are not cacheable');
  [$code,$html]=http_request('/pre-order/status/?t=not-a-token');verify($code===404,'unknown status token returns 404');
  [$code,$html,$redirect]=http_request('/admin/po.php');verify($code===302 && str_contains($redirect,'login'),'admin requires login');
  [$code]=http_request('/admin/po-install.php');verify($code===404,'installer unavailable over HTTP');
  [$code]=http_request('/admin/index.php',['aksi'=>'login','u'=>'staff','p'=>'Demo-test-password']);verify($code===302,'staff login works');
  [$code,$html]=http_request('/admin/po.php');$adminCsrf=csrf_from($html);
  [$code,$html]=http_request('/admin/po.php',['csrf'=>$adminCsrf,'action'=>'toggle-slot','slot_id'=>$sid2,'is_open'=>0]);
  verify(str_contains($html,'hanya untuk pemilik') && (int)po_slot($db,$sid2)['is_open']===1,'staff cannot change schedule');
  [$code]=http_request('/admin/index.php',['aksi'=>'login','u'=>'owner','p'=>'Demo-test-password']);verify($code===302,'owner login works');
  [$code,$html]=http_request('/admin/po.php');$adminCsrf=csrf_from($html);
  [$code,$catalogHtml]=http_request('/admin/po.php?slot='.$sid2);
  verify(str_contains($catalogHtml,'name="menu_uid"') && str_contains($catalogHtml,'Tambah dari katalog'),'admin renders catalog selector instead of manual name field');
  $webOrder=po_query($db,'SELECT id FROM po_orders WHERE request_hash=?',[hash('sha256',$webNonce)])->fetchColumn();
  [$code,$html]=http_request('/admin/po.php',['csrf'=>'wrong','action'=>'transition','order_id'=>$webOrder,'expected'=>'pending','target'=>'confirmed']);
  verify(str_contains($html,'Sesi formulir tidak valid'),'admin CSRF rejected');
  foreach ([['pending','confirmed'],['confirmed','ready'],['ready','completed']] as [$from,$to]) {
    [$code]=http_request('/admin/po.php',['csrf'=>$adminCsrf,'action'=>'transition','order_id'=>$webOrder,'expected'=>$from,'target'=>$to]);
    verify($code===303 && po_query($db,'SELECT status FROM po_orders WHERE id=?',[$webOrder])->fetchColumn()===$to,'HTTP order transition '.$to);
  }
  $savedCatalog=json_decode(file_get_contents($public.'/menu.json'),true);
  $sourceUid=po_query($db,'SELECT menu_uid FROM po_products WHERE id=?',[$pid2])->fetchColumn();
  $menuIndex=array_search($sourceUid,array_column($savedCatalog,'uid'),true);
  [$code]=http_request('/admin/index.php',['csrf'=>$adminCsrf,'aksi'=>'save','i'=>$menuIndex,'cat'=>'cakery','n'=>'Edited from Menu','d'=>'New description','available'=>'1']);
  $editedCatalog=json_decode(file_get_contents($public.'/menu.json'),true);
  verify($code===302 && $editedCatalog[$menuIndex]['uid']===$sourceUid,'admin menu edit preserves permanent ID');
  [$code,$html]=http_request('/pre-order/?slot='.$sid2);
  verify(str_contains($html,'Edited from Menu') && str_contains($html,'New description'),'PO follows catalog name and description after editing');
  // Foto katalog yang dihapus tetap disimpan ketika masih direferensikan PO.
  mkdir($public.'/images/menu',0700,true);
  file_put_contents($public.'/images/menu/example.jpg','jpeg');file_put_contents($public.'/images/menu/example.webp','webp');
  file_put_contents($public.'/menu.json','[]');
  $photoCode='define("RUETHIES_ADMIN",true);require '.var_export($public.'/admin/config.php',true).';require '.var_export($public.'/admin/lib.php',true).';echo menu_photo_delete_unused("images/menu/example.jpg");';
  $p=[];$photoProc=proc_open([PHP_BINARY,'-r',$photoCode],[1=>['pipe','w'],2=>['pipe','w']],$p);$photoOut=stream_get_contents($p[1]);$photoErr=stream_get_contents($p[2]);fclose($p[1]);fclose($p[2]);proc_close($photoProc);
  verify($photoOut==='kept' && $photoErr==='' && is_file($public.'/images/menu/example.jpg'),'PO image reference protects deleted catalog photo');
  // Tidak dijalankan dari staging => endpoint demo ditolak sebelum akses database.
  $guard='define("RUETHIES_ADMIN",true);define("RUETHIES_STAGING",false);require '.var_export(dirname(__DIR__).'/admin/po-lib.php',true).';register_shutdown_function(function(){echo http_response_code();});po_require_staging();';
  $p=[];$guardProc=proc_open([PHP_BINARY,'-r',$guard],[1=>['pipe','w'],2=>['pipe','w']],$p);$guardOut=stream_get_contents($p[1]);fclose($p[1]);fclose($p[2]);proc_close($guardProc);
  verify(str_ends_with($guardOut,'404'),'production environment rejects demo');
  verify(!preg_match('/(?:PHP Warning|PHP Fatal|Uncaught|Warning:)/',file_get_contents($root.'/http.log')),'HTTP integration has no PHP warnings or fatal errors');
  print "All PO database and HTTP tests passed.\n";
} finally {
  if(is_resource($process)){proc_terminate($process);proc_close($process);}
  $db=null;
  $server->exec("DROP DATABASE `$database`");
  remove_fixture($root);
}
