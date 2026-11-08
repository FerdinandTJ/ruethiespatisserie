<?php
// All API calls are simulated. Uses a disposable local database and Hostinger-layout HTTP fixture.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
set_error_handler(static function($s,$m,$f,$l){if(!(error_reporting()&$s))return false;throw new ErrorException($m,0,$s,$f,$l);});
define('RUETHIES_ADMIN',true);require dirname(__DIR__).'/admin/po-lib.php';
date_default_timezone_set('Asia/Jakarta');
$dsn=getenv('PO_TEST_DSN');if(!$dsn||!str_starts_with($dsn,'mysql:')){fwrite(STDERR,"Set PO_TEST_DSN to an isolated test server.\n");exit(1);}
$user=getenv('PO_TEST_USER')?:'root';$pass=getenv('PO_TEST_PASSWORD')?:'';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$server=new PDO($dsn,$user,$pass,$options);$database='ruethies_po_test_'.bin2hex(random_bytes(6));$server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4");
$testDsn=$dsn.';dbname='.$database;$db=new PDO($testDsn,$user,$pass,$options);
$root=sys_get_temp_dir().'/'.$database;$public=$root.'/public_html/staging';$private=$root.'/ruethies-data-staging';define('RUETHIES_PUBLIC',$public);
function ruethies_data_dir():string{global $private;return $private;}
$secret=bin2hex(random_bytes(32));$process=null;$curl=null;
function wa_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
function wa_denied(callable $f,string $label):void{try{$f();}catch(DomainException $e){wa_check(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
function wa_remove(string $p):void{if(!is_dir($p)||is_link($p)){unlink($p);return;}foreach(scandir($p)as$n)if($n!=='.'&&$n!=='..')wa_remove($p.'/'.$n);rmdir($p);}
function wa_order(PDO $db,int $sid,int $pid):array{global $secret;static $phone=6281290000000;$phone++;$in=['slot_id'=>$sid,'qty'=>[$pid=>1],'name'=>'Pelanggan Contoh','phone'=>(string)$phone,'note'=>'','consent'=>'yes'];$nonce=bin2hex(random_bytes(32));$hash=po_quote_hash(po_quote($db,$in));$token=po_submit($db,$in,$nonce,$hash,$secret);$o=po_order($db,$token);return [$o,$token,$in,$nonce,$hash];}
function wa_parallel(array $jobs):array{global $testDsn,$user,$pass,$options;$children=[];foreach($jobs as$job){$code='define("RUETHIES_ADMIN",true);require '.var_export(dirname(__DIR__).'/admin/po-lib.php',true).';$db=new PDO('.var_export($testDsn,true).','.var_export($user,true).','.var_export($pass,true).','.var_export($options,true).');'.$job;$p=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$children[]=[$p,$pipes];}$out=[];foreach($children as[$p,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);wa_check(proc_close($p)===0&&$err==='','parallel worker completes');}sort($out);return $out;}
try{
 mkdir($public.'/admin',0700,true);mkdir($private,0700,true);
 foreach(array_merge(glob(dirname(__DIR__).'/admin/*.php'),glob(dirname(__DIR__).'/admin/*.css'),glob(dirname(__DIR__).'/admin/*.js'))as$file)copy($file,$public.'/admin/'.basename($file));
 foreach(['po.php','po.css','po.js','menu.json','atmosphere.json','faq.json','index.html','llms.txt']as$file)copy(dirname(__DIR__).'/'.$file,$public.'/'.$file);
 file_put_contents($public.'/menu.json',json_encode([['uid'=>str_repeat('a',32),'cat'=>'cakery','id'=>['n'=>'Croissant Contoh','d'=>'Test'],'available'=>true,'img'=>'']]));
 $config=['dsn'=>$testDsn,'user'=>$user,'password'=>$pass,'secret'=>$secret,'manual_checkout_enabled'=>true,'fonnte'=>['enabled'=>false]];
 file_put_contents($private.'/po-config.php','<?php return '.var_export($config,true).';');
 foreach(explode(';',file_get_contents(dirname(__DIR__).'/admin/po-schema.sql'))as$sql)if(trim($sql)!=='')$db->exec($sql);po_migrate_manual($db);po_migrate_manual($db);
 $owner=['name'=>'owner','role'=>'owner'];$staff=['name'=>'staff','role'=>'staff'];
 file_put_contents($private.'/users.json',json_encode([['name'=>'owner','role'=>'owner','hash'=>password_hash('Demo-test-password',PASSWORD_DEFAULT),'totp'=>''],['name'=>'staff','role'=>'staff','hash'=>password_hash('Demo-test-password',PASSWORD_DEFAULT),'totp'=>'']]));
 po_payment_save($db,['enabled'=>1,'version'=>0,'bank_name'=>'Bank Contoh','account_number'=>'00123456','account_name'=>'Pemilik Contoh','instructions'=>'Uji tanpa transfer nyata.'],$owner);
 $sid=po_save_slot($db,['title'=>'Manual WhatsApp Test','flow_version'=>'manual_v1','opens_at'=>date('Y-m-d\TH:i',time()-3600),'closes_at'=>date('Y-m-d\TH:i',time()+3600),'pickup_from'=>date('Y-m-d\TH:i',time()+7200),'pickup_until'=>date('Y-m-d\TH:i',time()+10800)]);po_toggle_slot($db,$sid,1);
 $pid=po_save_product($db,['slot_id'=>$sid,'menu_uid'=>str_repeat('a',32),'price'=>25000,'quota'=>200,'active'=>1]);
 [$o,$token,$in,$nonce,$hash]=wa_order($db,$sid,$pid);po_submit($db,$in,$nonce,$hash,$secret);
 wa_check((int)po_query($db,'SELECT COUNT(*) FROM po_whatsapp_outbox WHERE order_id=?',[$o['id']])->fetchColumn()===1,'idempotent checkout enqueues one invoice transactionally');
 wa_check($o['notification_access']!==$token&&po_wa_decrypt($o['notification_access'],$secret)===$token,'notification access token is encrypted at rest');
 try{po_wa_decrypt($o['notification_access'],str_repeat('b',64));throw new LogicException('decrypt accepted');}catch(RuntimeException $e){wa_check(true,'wrong key cannot decrypt magic link');}
 $disabledCalls=0;po_wa_run($db,$config,5,static function()use(&$disabledCalls){$disabledCalls++;return [];});wa_check($disabledCalls===0,'disabled Fonnte makes no network requests');
 $enabled=$config;$enabled['fonnte']=['enabled'=>true,'token'=>'FAKE_TEST_ONLY','test_recipient'=>'628111111111','owner_phone'=>'628222222222','allowed_recipients'=>['628111111111']];
 $calls=[];$mock=static function($settings,$phone,$message)use(&$calls){$calls[]=[$phone,$message];return ['outcome'=>'accepted','provider_id'=>'fixture-id'];};
 $result=po_wa_run($db,$enabled,5,$mock);wa_check($result['accepted']===1&&$calls[0][0]==='628111111111'&&str_contains($calls[0][1],$token)&&str_contains($calls[0][1],'00123456'),'worker redirects to allowlisted test number and sends invoice snapshot plus magic link');
 po_wa_run($db,$enabled,5,$mock);wa_check(count($calls)===1,'accepted notification is not sent twice');
 wa_check(po_wa_response(200,'{"status":true,"id":["abc"]}')['outcome']==='accepted'&&po_wa_response(200,'{"status":false}')['outcome']==='retry','provider response distinguishes acceptance from rejection');
 wa_check(po_wa_response(0,'',28)['outcome']==='uncertain'&&po_wa_response(500,'error')['outcome']==='uncertain'&&po_wa_response(200,'not json')['outcome']==='uncertain','timeout, server errors and malformed responses require review');
 wa_check(po_wa_response(429,'')['outcome']==='retry'&&po_wa_response(401,'')['outcome']==='failed','rate limit retries and invalid credentials fail safely');
 [$second,$secondToken]=wa_order($db,$sid,$pid);po_wa_run($db,$enabled,1,static fn()=>['outcome'=>'retry','code'=>'provider_rejected']);$job=po_query($db,'SELECT * FROM po_whatsapp_outbox WHERE order_id=?',[$second['id']])->fetch();wa_check($job['status']==='pending'&&(int)$job['attempts']===1&&(int)$job['available_at']>time(),'safe rejection uses delayed bounded retry');
 po_query($db,'UPDATE po_whatsapp_outbox SET available_at=0 WHERE id=?',[$job['id']]);po_wa_run($db,$enabled,1,static fn()=>['outcome'=>'uncertain','code'=>'transport_28']);$job=po_query($db,'SELECT * FROM po_whatsapp_outbox WHERE id=?',[$job['id']])->fetch();
 wa_check($job['status']==='uncertain','ambiguous transmission is not automatically retried');
 wa_denied(fn()=>po_wa_retry($db,(int)$job['id'],(int)$job['version'],$staff),'staff cannot retry ambiguous notifications');po_wa_retry($db,(int)$job['id'],(int)$job['version'],$owner);wa_denied(fn()=>po_wa_retry($db,(int)$job['id'],(int)$job['version'],$owner),'stale retry cannot requeue twice');
 $claimCode='$job=po_wa_claim($db);echo $job ? "claimed" : "empty";';wa_check(wa_parallel([$claimCode,$claimCode])===['claimed','empty'],'parallel workers claim each queue item once');
 po_query($db,"UPDATE po_whatsapp_outbox SET lease_until=0 WHERE status='processing'");po_wa_claim($db);wa_check(po_query($db,'SELECT status FROM po_whatsapp_outbox WHERE id=?',[$job['id']])->fetchColumn()==='uncertain','crashed worker lease expires into manual review');
 $report=['sender_name'=>'Pengirim Uji','transfer_amount'=>25000,'transfer_reference'=>'Mutasi demo 08 Nov'];
 wa_denied(fn()=>po_manual_report($db,$token,array_replace($report,['transfer_amount'=>1])),'wrong transfer amount cannot enter review');po_manual_report($db,$token,$report);
 wa_check(po_order($db,$token)['payment_status']==='under_review','customer transfer report never marks order paid');
 po_query($db,'UPDATE po_orders SET expires_at=0 WHERE id=?',[$o['id']]);po_expire($db);wa_check(po_order($db,$token)['reservation_status']==='held','reviewed transfer holds quota even after original deadline');
 wa_denied(fn()=>po_manual_decide($db,(int)$o['id'],'paid',['admin_reference'=>'Mutasi test','verified_amount'=>25000,'expected_report'=>1],$staff),'staff cannot verify money');
 $decide='try{po_manual_decide($db,'.(int)$o['id'].',"paid",["admin_reference"=>"Mutasi test","verified_amount"=>25000,"expected_report"=>1],["name"=>"owner","role"=>"owner"]);echo "paid";}catch(DomainException $e){echo "stale";}';wa_check(wa_parallel([$decide,$decide])===['paid','stale'],'concurrent payment approvals apply once');
 wa_check(po_order($db,$token)['payment_status']==='paid'&&(int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$pid])->fetchColumn()===2,'approval commits existing reservation without double-counting quota');
 wa_denied(fn()=>po_manual_release($db,(int)$o['id'],'cancelled',$owner,'test'),'paid reservation cannot be silently cancelled');po_manual_fulfill($db,(int)$o['id'],'confirmed','ready',$staff);po_manual_fulfill($db,(int)$o['id'],'ready','completed',$staff);wa_check(po_order($db,$token)['status']==='completed','staff can fulfill paid order through ready to completed');
 [$third,$thirdToken]=wa_order($db,$sid,$pid);po_query($db,'UPDATE po_orders SET expires_at=0 WHERE id=?',[$third['id']]);po_expire($db);wa_denied(fn()=>po_manual_report($db,$thirdToken,$report),'late report cannot revive an expired reservation');
 po_query($db,"UPDATE po_whatsapp_outbox SET status='accepted' WHERE order_id<>? AND status='pending'",[$third['id']]);$beforeCalls=count($calls);$summary=po_wa_run($db,$enabled,5,$mock);wa_check($summary['skipped']===1&&count($calls)===$beforeCalls,'expired invoice is suppressed before API call');
 [$fourth,$fourthToken]=wa_order($db,$sid,$pid);$noOverride=$enabled;$noOverride['fonnte']['test_recipient']='';$noOverride['fonnte']['owner_phone']='';$beforeCalls=count($calls);$summary=po_wa_run($db,$noOverride,1,$mock);wa_check($summary['failed']===1&&count($calls)===$beforeCalls,'non-allowlisted customer number never reaches provider');
 [$retryOrder,$retryToken]=wa_order($db,$sid,$pid);for($i=0;$i<3;$i++){po_manual_report($db,$retryToken,$report);if($i===1)wa_denied(fn()=>po_manual_decide($db,(int)$retryOrder['id'],'paid',['admin_reference'=>'Tab lama','verified_amount'=>25000,'expected_report'=>1],$owner),'stale approval cannot verify a newer transfer report');if($i<2)po_manual_decide($db,(int)$retryOrder['id'],'reject',['admin_reference'=>'Periksa ulang contoh','expected_report'=>$i+1],$owner);}
 wa_denied(fn()=>po_manual_decide($db,(int)$retryOrder['id'],'reject',['admin_reference'=>'Periksa lagi','expected_report'=>3],$owner),'after two corrections unresolved transfer stays reserved for owner resolution');
 wa_denied(fn()=>po_manual_decide($db,(int)$retryOrder['id'],'reject-final',['admin_reference'=>'Tidak ada mutasi','expected_report'=>3],$owner),'final rejection requires explicit no-funds confirmation');
 $heldBefore=(int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$pid])->fetchColumn();po_manual_decide($db,(int)$retryOrder['id'],'reject-final',['admin_reference'=>'Mutasi diperiksa, tidak ada dana','verified_no_transfer'=>'yes','expected_report'=>3],$owner);
 wa_check(po_order($db,$retryToken)['status']==='rejected'&&(int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$pid])->fetchColumn()===$heldBefore-1,'explicit owner final rejection releases quota once with audit');
 wa_denied(fn()=>po_manual_decide($db,(int)$retryOrder['id'],'reject-final',['admin_reference'=>'Tidak ada dana','verified_no_transfer'=>'yes','expected_report'=>3],$owner),'final rejection cannot release quota twice');
 [$racing,$racingToken]=wa_order($db,$sid,$pid);po_query($db,'UPDATE po_orders SET expires_at=? WHERE id=?',[time()+1,$racing['id']]);
 $raceReport='usleep(900000);try{po_manual_report($db,'.var_export($racingToken,true).','.var_export($report,true).');echo "reported";}catch(DomainException $e){echo "late";}';
 $raceExpire='usleep(1100000);po_manual_release($db,'.(int)$racing['id'].',"expired",["name"=>"Sistem","role"=>"system"]);echo "expiry_checked";';$raceResult=wa_parallel([$raceReport,$raceExpire]);$afterRace=po_order($db,$racingToken);
 wa_check(in_array('reported',$raceResult,true)?($afterRace['payment_status']==='under_review'&&$afterRace['reservation_status']==='held'):($afterRace['status']==='expired'&&$afterRace['reservation_status']==='released'),'transfer report versus expiry cannot release a reservation under review');
 po_query($db,"UPDATE po_whatsapp_outbox SET status='accepted' WHERE status='pending'");[$bounded,$boundedToken]=wa_order($db,$sid,$pid);$rejectCalls=0;
 for($i=0;$i<5;$i++){po_query($db,'UPDATE po_whatsapp_outbox SET available_at=0 WHERE order_id=?',[$bounded['id']]);po_wa_run($db,$enabled,1,static function()use(&$rejectCalls){$rejectCalls++;return ['outcome'=>'retry','code'=>'provider_rejected'];});}
 po_wa_run($db,$enabled,1,static function()use(&$rejectCalls){$rejectCalls++;return ['outcome'=>'accepted'];});wa_check($rejectCalls===5&&po_query($db,'SELECT status FROM po_whatsapp_outbox WHERE order_id=?',[$bounded['id']])->fetchColumn()==='failed','provider rejection stops automatically after five attempts');
 // HTTP end-to-end with API disabled in private fixture config.
 $socket=stream_socket_server('tcp://127.0.0.1:0');$address=stream_socket_get_name($socket,false);fclose($socket);
 $process=proc_open([PHP_BINARY,'-d','opcache.enable=0','-S',$address,'-t',$public,dirname(__DIR__).'/tests/po-router.php'],[0=>['pipe','r'],1=>['file',$root.'/http.log','a'],2=>['file',$root.'/http.log','a']],$pipes);
 $curl=curl_init();curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>10]);
 $http=static function(string $path,?array $data=null)use($curl,$address):array{curl_setopt_array($curl,[CURLOPT_URL=>'http://'.$address.$path,CURLOPT_POST=>$data!==null]);if($data!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($data));$body=curl_exec($curl);return [(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),(string)$body,(string)curl_getinfo($curl,CURLINFO_REDIRECT_URL)];};
 $csrf=static function(string $body):string{if(!preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m))throw new RuntimeException('Missing CSRF');return $m[1];};
 for($i=0;$i<40;$i++){usleep(100000);[$code,$html]=$http('/pre-order/?slot='.$sid);if($code===200)break;}
 wa_check($code===200&&str_contains($html,'Manual WhatsApp Test'),'enabled manual schedule appears in customer catalog');$customerCsrf=$csrf($html);
 $httpInput=['slot_id'=>$sid,'qty'=>[$pid=>1],'name'=>'Web Contoh','phone'=>'081234567890','note'=>'','consent'=>'yes'];[$code,$html]=$http('/pre-order/',$httpInput+['action'=>'review','csrf'=>$customerCsrf]);wa_check($code===200&&str_contains($html,'kuota ditahan 15 menit'),'manual review explains reservation and invoice');preg_match('/name="nonce" value="([a-f0-9]+)"/',$html,$m);
 [$code,$html,$redirect]=$http('/pre-order/',['action'=>'submit','csrf'=>$customerCsrf,'nonce'=>$m[1]]);$statusPath='/pre-order/status/?'.parse_url($redirect,PHP_URL_QUERY);wa_check($code===303,'manual HTTP checkout redirects to private invoice');
 [$code,$html]=$http($statusPath);wa_check($code===200&&str_contains($html,'00123456')&&str_contains($html,'name="transfer_reference"'),'private invoice shows original bank snapshot and transfer report form');
 [$code,$html]=$http($statusPath,$report+['action'=>'report-transfer','csrf'=>'wrong']);wa_check(str_contains($html,'Sesi formulir berakhir'),'transfer report requires CSRF');
 [$code]=$http($statusPath,$report+['action'=>'report-transfer','csrf'=>$customerCsrf]);wa_check($code===303,'transfer report persists over HTTP');[$code,$html]=$http($statusPath);wa_check(str_contains($html,'sedang diperiksa')&&!str_contains($html,'name="transfer_reference"'),'invoice shows under-review state without requesting a second transfer');
 [$code]=$http('/admin/po-cron.php');wa_check($code===404,'notification worker is CLI-only');
 [$code,$html]=$http('/admin/index.php');[$code]=$http('/admin/index.php',['aksi'=>'login','u'=>'staff','p'=>'Demo-test-password']);wa_check($code===302,'staff fixture login works');[$code,$html]=$http('/admin/index.php');$adminCsrf=$csrf($html);
 $webToken=substr($statusPath,strpos($statusPath,'t=')+2);$webOrder=po_order($db,$webToken);
 [$code,$html]=$http('/admin/po.php',['csrf'=>$adminCsrf,'action'=>'manual-payment','order_id'=>$webOrder['id'],'decision'=>'paid','verified_amount'=>25000,'expected_report'=>1,'admin_reference'=>'Demo','current_password'=>'Demo-test-password']);wa_check(po_order($db,$webToken)['payment_status']==='under_review','HTTP staff cannot approve payment');
 foreach(['rekening','notifikasi']as$restricted){[$code,$restrictedHtml]=$http('/admin/po.php?view='.$restricted);wa_check($code===403&&!str_contains($restrictedHtml,'name="bank_name"')&&!str_contains($restrictedHtml,'name="job_id"'),'staff cannot open owner-only PO page: '.$restricted);}
 [$code]=$http('/admin/index.php',['aksi'=>'login','u'=>'owner','p'=>'Demo-test-password']);[$code,$html]=$http('/admin/po.php?view=notifikasi');$adminCsrf=$csrf($html);wa_check(str_contains($html,'Notifikasi WhatsApp'),'owner sees notification queue dashboard');
 // UI routes use the same lightweight admin shell and isolate editors from lists.
 $previewPages=[];
 foreach(['pesanan','jadwal','produk','rekening','notifikasi']as$view){
   $path='/admin/po.php?view='.$view.($view==='produk'?'&slot='.$sid:'');[$code,$pageHtml]=$http($path);$previewPages[$view]=$pageHtml;
   wa_check($code===200&&substr_count($pageHtml,'<h1>')===1&&str_contains($pageHtml,'class="po-tabs"'),'PO page has one heading and shared navigation: '.$view);
 }
 [$code,$filtered]=$http('/admin/po.php?view=pesanan&payment=under_review&status=pending&q=Web');wa_check($code===200&&substr_count($filtered,'class="po-order-row"')===1&&str_contains($filtered,$webOrder['code']),'combined order filters preserve the matching order');
 wa_check(!str_contains($previewPages['pesanan'],'name="bank_name"')&&!str_contains($previewPages['pesanan'],'name="verified_amount"')&&!str_contains($previewPages['pesanan'],'id="whatsapp"'),'order list excludes bank settings, payment forms and notification payload');
 wa_check(str_contains($previewPages['pesanan'],'name="payment"')&&str_contains($previewPages['pesanan'],'Lihat detail'),'order list provides payment filters and detail links');
 wa_check(!str_contains($previewPages['jadwal'],'name="title"')&&!str_contains($previewPages['produk'],'name="price"'),'schedule and product lists do not render every edit form');
 wa_check(str_contains($previewPages['rekening'],'name="bank_name"')&&!str_contains($previewPages['rekening'],'id="orders"'),'bank editor is isolated from orders');
 [$code,$pageHtml]=$http('/admin/po.php?view=jadwal&edit=new');wa_check($code===200&&str_contains($pageHtml,'name="opens_at"')&&!str_contains($pageHtml,'id="schedule-list"'),'new schedule editor opens without schedule list');$previewPages['schedule-editor']=$pageHtml;
 [$code,$pageHtml]=$http('/admin/po.php?view=jadwal&slot='.$sid.'&edit=1');wa_check(str_contains($pageHtml,'class="po-form-fields" disabled'),'schedule containing orders shows locked dates before submission');
 [$code,$pageHtml]=$http('/admin/po.php?view=produk&slot='.$sid.'&product='.$pid);wa_check($code===200&&substr_count($pageHtml,'name="price"')===1&&str_contains($pageHtml,'value="25000"'),'product edit opens only the selected product');$previewPages['product-editor']=$pageHtml;
 [$code,$pageHtml]=$http('/admin/po.php?view=produk&slot='.$sid.'&product=999999');wa_check($code===404&&!str_contains($pageHtml,'name="price"'),'unknown product cannot open another product editor');
 [$code,$pageHtml]=$http('/admin/po.php?view=pesanan&order='.$webOrder['id']);wa_check($code===200&&str_contains($pageHtml,'name="verified_amount"')&&!str_contains($pageHtml,'role="search"'),'payment actions and history appear on order details only');$previewPages['order-detail']=$pageHtml;
 [$code,$pageHtml]=$http('/admin/po.php?view=pesanan&order=999999');wa_check($code===404,'unknown order detail returns 404');
 if(getenv('PO_UI_KEEP_FIXTURE'))foreach($previewPages as$name=>$body)file_put_contents($public.'/admin/preview-'.$name.'.html',$body);
 $paymentPost=['action'=>'manual-payment','order_id'=>$webOrder['id'],'decision'=>'paid','verified_amount'=>25000,'expected_report'=>1,'admin_reference'=>'Mutasi web test','current_password'=>'Demo-test-password'];
 [$code,$html]=$http('/admin/po.php',$paymentPost+['csrf'=>'wrong']);wa_check(po_order($db,$webToken)['payment_status']==='under_review','payment approval requires CSRF');
 [$code,$html]=$http('/admin/po.php',array_replace($paymentPost,['current_password'=>'wrong','csrf'=>$adminCsrf]));wa_check(po_order($db,$webToken)['payment_status']==='under_review','payment approval requires current owner identity');
 [$code]=$http('/admin/po.php',$paymentPost+['csrf'=>$adminCsrf]);wa_check($code===303&&po_order($db,$webToken)['payment_status']==='paid','verified owner approves transfer through HTTP');
 wa_check(!preg_match('/PHP (Warning|Fatal)|Uncaught/',file_get_contents($root.'/http.log')),'new HTTP flows produce no PHP warnings or fatal errors');
 $worker=proc_open([PHP_BINARY,$public.'/admin/po-cron.php','--run'],[1=>['pipe','w'],2=>['pipe','w']],$wp);$stdout=stream_get_contents($wp[1]);$stderr=stream_get_contents($wp[2]);fclose($wp[1]);fclose($wp[2]);wa_check(proc_close($worker)===0&&$stderr===''&&is_file($private.'/po-wa-heartbeat.json')&&json_decode($stdout,true)['disabled']===true,'cron performs expiry and heartbeat with API disabled');
 [$badOrder,$badToken]=wa_order($db,$sid,$pid);po_query($db,'UPDATE po_orders SET expires_at=0 WHERE id=?',[$badOrder['id']]);$badConfig=$config;$badConfig['fonnte']=['enabled'=>true,'token'=>'FAKE_TEST_ONLY','allowed_recipients'=>[]];file_put_contents($private.'/po-config.php','<?php return '.var_export($badConfig,true).';');
 $worker=proc_open([PHP_BINARY,$public.'/admin/po-cron.php','--run'],[1=>['pipe','w'],2=>['pipe','w']],$wp);$stdout=stream_get_contents($wp[1]);$stderr=stream_get_contents($wp[2]);fclose($wp[1]);fclose($wp[2]);wa_check(proc_close($worker)===1&&json_decode($stdout,true)['notification_error']===true&&po_query($db,'SELECT status FROM po_orders WHERE id=?',[$badOrder['id']])->fetchColumn()==='expired','invalid Fonnte config cannot prevent reservation expiry');
 [$code,$html]=$http('/admin/po.php?view=notifikasi');wa_check($code===200&&str_contains($html,'Konfigurasi Fonnte belum valid'),'invalid Fonnte config is visible on notification page');[$code,$html]=$http('/admin/po.php');wa_check($code===200&&str_contains($html,'Daftar pesanan'),'invalid Fonnte config cannot block order management');
 echo "All Fonnte/manual checkout tests passed; no real messages sent.\n";
}finally{
 if($curl)$curl=null;if(is_resource($process)){proc_terminate($process);proc_close($process);} $db=null;$server->exec("DROP DATABASE `$database`");if(getenv('PO_UI_KEEP_FIXTURE'))echo 'UI preview fixture: '.$public."\n";elseif(is_dir($root))wa_remove($root);
}
