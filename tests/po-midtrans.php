<?php
// Fresh local database + mocked provider transport. Never sends real API requests.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
set_error_handler(static function($s,$m,$f,$l){if(error_reporting()&$s)throw new ErrorException($m,0,$s,$f,$l);});
define('RUETHIES_ADMIN',true);require dirname(__DIR__).'/admin/po-lib.php';date_default_timezone_set('Asia/Jakarta');
$dsn=getenv('PO_TEST_DSN');if(!$dsn||!str_starts_with($dsn,'mysql:'))exit("Set PO_TEST_DSN to an isolated test server.\n");
$user=getenv('PO_TEST_USER')?:'root';$password=getenv('PO_TEST_PASSWORD')?:'';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$server=new PDO($dsn,$user,$password,$options);$database='ruethies_mt_test_'.bin2hex(random_bytes(5));$server->exec("CREATE DATABASE `$database`");$testDsn=$dsn.';dbname='.$database;$db=new PDO($testDsn,$user,$password,$options);
$root=sys_get_temp_dir().'/'.$database;$public=$root.'/public_html/staging';$private=$root.'/ruethies-data-staging';define('RUETHIES_PUBLIC',$public);
function ruethies_data_dir():string{global $private;return $private;}
function mt_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
function mt_denied(callable $work,string $label):void{try{$work();}catch(DomainException|RuntimeException $e){mt_check(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
function mt_remove(string $p):void{if(!is_dir($p)){unlink($p);return;}foreach(scandir($p)as$n)if($n!=='.'&&$n!=='..')mt_remove($p.'/'.$n);rmdir($p);}
function mt_new(PDO $db,int $sid,int $pid,string $secret):array{
  static $i=0;$i++;$input=['slot_id'=>$sid,'qty'=>[$pid=>1],'name'=>'Sandbox User','phone'=>'0812345'.str_pad((string)$i,5,'0',STR_PAD_LEFT),'consent'=>'yes'];$nonce=bin2hex(random_bytes(32));$hash=po_quote_hash(po_quote($db,$input));$token=po_submit($db,$input,$nonce,$hash,$secret);$o=po_order($db,$token);$p=po_query($db,'SELECT * FROM po_midtrans_payments WHERE order_id=?',[$o['id']])->fetch();return[$o,$p,$token,$input,$nonce,$hash];
}
function mt_status(array $p,string $status='settlement'):array{return ['order_id'=>$p['gateway_order_id'],'gross_amount'=>'25000.00','currency'=>'IDR','transaction_id'=>'tx-'.$p['gateway_order_id'],'transaction_status'=>$status,'status_code'=>in_array($status,['settlement','capture'],true)?'200':'201','fraud_status'=>'accept'];}
$process=null;$curl=null;
try{
 mkdir($public.'/admin',0700,true);mkdir($private,0700,true);mkdir($root.'/sessions',0700);
 foreach(glob(dirname(__DIR__).'/admin/*.php')as$f)copy($f,$public.'/admin/'.basename($f));
 foreach(['po.php','po.css','po.js','midtrans-notify.php','index.html','llms.txt','faq.json','atmosphere.json']as$f)copy(dirname(__DIR__).'/'.$f,$public.'/'.$f);
 $secret=bin2hex(random_bytes(32));$config=['dsn'=>$testDsn,'user'=>$user,'password'=>$password,'secret'=>$secret,'midtrans'=>['enabled'=>true,'environment'=>'sandbox','server_key'=>'SB-Mid-server-FAKE-TEST-ONLY'],'fonnte'=>['enabled'=>false]];
 file_put_contents($private.'/po-config.php','<?php return '.var_export($config,true).';');
 file_put_contents($private.'/users.json',json_encode([['name'=>'owner','hash'=>password_hash('Demo-test-password',PASSWORD_DEFAULT),'role'=>'owner','totp'=>'','auth_version'=>str_repeat('a',64),'last_totp_counter'=>-1]]));
 file_put_contents($public.'/menu.json',json_encode([['uid'=>str_repeat('a',32),'cat'=>'cakery','img'=>'images/menu/test.jpg','id'=>['n'=>'Kue Uji','d'=>'Uji'],'available'=>true]]));
 foreach(explode(';',file_get_contents(dirname(__DIR__).'/admin/po-schema.sql'))as$sql)if(trim($sql)!=='')$db->exec($sql);
 po_migrate_manual($db);po_mt_migrate($db);po_mt_migrate($db);$settings=po_mt_settings($config);
 mt_denied(fn()=>po_mt_settings(['midtrans'=>['enabled'=>true,'environment'=>'production','server_key'=>'Mid-server-real']]),'production environment rejected');
 mt_denied(fn()=>po_mt_settings(['midtrans'=>['enabled'=>true,'server_key'=>"SB-Mid-server-x\r\nInjected"]]),'invalid keys rejected');
 foreach(['https://evil.test/snap/v4/redirection/test','https://user@app.sandbox.midtrans.com/snap/v4/redirection/test','https://app.sandbox.midtrans.com/snap/v4/redirection/test?leak=1']as$url)mt_check(!po_mt_url($url),'external/malformed Snap redirect rejected');
 $schedule=['title'=>'Default Sandbox','opens_at'=>date('Y-m-d\TH:i',time()-3600),'closes_at'=>date('Y-m-d\TH:i',time()+3600),'pickup_from'=>date('Y-m-d\TH:i',time()+7200),'pickup_until'=>date('Y-m-d\TH:i',time()+10800)];
 $defaultSlot=po_save_slot($db,$schedule);mt_check(po_slot($db,$defaultSlot)['flow_version']==='midtrans_v1','new schedule defaults to Midtrans without type selector');
 foreach(['legacy','manual_v1'] as $retired){
   mt_denied(fn()=>po_save_slot($db,$schedule+['flow_version'=>$retired]),'retired schedule cannot be created: '.$retired);
   po_query($db,'UPDATE po_slots SET flow_version=? WHERE id=?',[$retired,$defaultSlot]);
   mt_denied(fn()=>po_toggle_slot($db,$defaultSlot,1),'retired schedule cannot reopen: '.$retired);
   mt_check(!po_mt_checkout_allowed($retired),'retired checkout rejected: '.$retired);
 }
 po_query($db,'UPDATE po_slots SET is_open=1 WHERE id=?',[$defaultSlot]);
 $sid=po_save_slot($db,['title'=>'Midtrans Test','flow_version'=>'midtrans_v1','opens_at'=>date('Y-m-d\TH:i',time()-3600),'closes_at'=>date('Y-m-d\TH:i',time()+3600),'pickup_from'=>date('Y-m-d\TH:i',time()+7200),'pickup_until'=>date('Y-m-d\TH:i',time()+10800)]);po_toggle_slot($db,$sid,1);
 $pid=po_save_product($db,['slot_id'=>$sid,'menu_uid'=>str_repeat('a',32),'price'=>25000,'quota'=>100,'active'=>1]);
 [$o,$p,$token,$input,$nonce,$hash]=mt_new($db,$sid,$pid,$secret);$id=(int)$o['id'];
 mt_check($o['bank_snapshot']===null&&$o['fulfillment_status']==='awaiting_payment'&&(int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$pid])->fetchColumn()===1,'Sandbox checkout reserves quota without requiring manual bank');
 mt_check(po_submit($db,$input,$nonce,$hash,$secret)===$token&&(int)po_query($db,'SELECT COUNT(*) FROM po_midtrans_payments')->fetchColumn()===1,'checkout retry preserves one order and payment identity');
 $calls=0;$create=static function($op,$gateway,$body)use(&$calls,$db){$calls++;mt_check(!$db->inTransaction(),'Snap API runs outside transaction');mt_check($op==='create'&&$body['transaction_details']['gross_amount']===25000&&isset($body['expiry']['start_time'])&&$body['expiry']['duration']===15&&!str_contains(json_encode($body),'?t='),'Snap payload has server amount, fixed expiry and no private magic link');return ['redirect_url'=>'https://app.sandbox.midtrans.com/snap/v4/redirection/test-token'];};
 $url=po_mt_create($db,$id,$settings,$create);po_mt_create($db,$id,$settings,$create);mt_check($calls===1&&po_mt_url($url),'repeat pay reuses existing Snap session');
 $message=po_wa_message($db,['order_id'=>$id,'kind'=>'invoice','event_key'=>'invoice'],$secret);mt_check(str_contains($message['message'],'Midtrans Sandbox')&&!str_contains($message['message'],'a.n.'),'WhatsApp links to Sandbox invoice without bank instructions');
 mt_denied(fn()=>po_mt_apply($db,$id,array_replace(mt_status($p),['gross_amount'=>'1.00'])),'wrong amount cannot mark paid');
 mt_denied(fn()=>po_mt_apply($db,$id,array_replace(mt_status($p),['order_id'=>'different'])),'wrong order cannot mark paid');
 mt_denied(fn()=>po_mt_apply($db,$id,array_replace(mt_status($p),['currency'=>'USD'])),'wrong currency cannot mark paid');
 po_mt_apply($db,$id,mt_status($p,'capture')+[]); // first accepted card-like success
 po_mt_apply($db,$id,mt_status($p));po_mt_apply($db,$id,mt_status($p,'pending'));po_mt_apply($db,$id,mt_status($p,'expire'));
 $paid=po_order($db,$token);mt_check($paid['payment_status']==='paid'&&$paid['reservation_status']==='committed'&&(int)po_query($db,"SELECT COUNT(*) FROM po_order_events WHERE order_id=? AND event='midtrans_paid'",[$id])->fetchColumn()===1,'duplicate/out-of-order success never double commits or downgrades paid order');
 mt_check((int)po_query($db,"SELECT COUNT(*) FROM po_whatsapp_outbox WHERE order_id=? AND kind='paid'",[$id])->fetchColumn()===2,'paid notifications enqueue once per audience');
 $waConfig=$config;$waConfig['fonnte']=['enabled'=>true,'token'=>'FAKE-LOCAL-ONLY','owner_phone'=>'628111111111','test_recipient'=>'','allowed_recipients'=>[$paid['phone'],'628111111111']];
 $deliveries=[];$send=static function($settings,$phone,$message)use(&$deliveries,$db){if($db->inTransaction())throw new RuntimeException('network under lock');$deliveries[]=[$phone,$message];return ['outcome'=>'accepted'];};
 $summary=po_wa_run($db,$waConfig,5,$send);$recipients=array_column($deliveries,0);sort($recipients);$expected=[$paid['phone'],'628111111111'];sort($expected);
 mt_check($summary['accepted']===2&&$summary['skipped']===1&&$recipients===$expected,'Fonnte worker sends paid notices to customer and owner, suppressing obsolete invoice');
 po_wa_run($db,$waConfig,5,$send);mt_check(count($deliveries)===2,'accepted paid notices are not delivered twice');

 po_manual_fulfill($db,$id,'confirmed','ready',['name'=>'staff','role'=>'staff']);mt_check(po_order($db,$token)['status']==='ready','verified Sandbox order can be fulfilled by staff');
 [$waOrder,$waPayment,$waToken]=mt_new($db,$sid,$pid,$secret);$override=$waConfig;$override['fonnte']['test_recipient']='628111111111';$deliveries=[];
 po_wa_run($db,$override,5,$send);mt_check(count($deliveries)===2&&array_unique(array_column($deliveries,0))===['628111111111'],'test recipient overrides invoice and ready notification without real API requests');
 [$e,$ep,$et]=mt_new($db,$sid,$pid,$secret);po_query($db,'UPDATE po_orders SET expires_at=? WHERE id=?',[time()-200,$e['id']]);$before=(int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$pid])->fetchColumn();
 mt_denied(fn()=>po_mt_reconcile($db,(int)$e['id'],$settings,static function(){throw new RuntimeException('timeout');}),'network failure keeps held quota');mt_check((int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$pid])->fetchColumn()===$before,'timeout does not release stock');
 po_query($db,'UPDATE po_midtrans_payments SET next_check=0 WHERE order_id=?',[$e['id']]);po_mt_reconcile($db,(int)$e['id'],$settings,static fn()=>['status_code'=>'404']);mt_check(po_order($db,$et)['reservation_status']==='released','authenticated 404 only expires after checkout window and grace');
 po_mt_apply($db,(int)$e['id'],mt_status($ep));mt_check(po_order($db,$et)['payment_status']==='paid_review'&&(int)po_query($db,'SELECT reserved FROM po_products WHERE id=?',[$pid])->fetchColumn()===$before-1,'late paid event flags review without taking stock again');
 [$f,$fp,$ft]=mt_new($db,$sid,$pid,$secret);po_mt_reconcile($db,(int)$f['id'],$settings,static fn()=>['status_code'=>'404']);mt_check(po_order($db,$ft)['reservation_status']==='held','404 before deadline is not a failed payment');
 [$u,$up,$ut]=mt_new($db,$sid,$pid,$secret);$uncertainCalls=0;$timeout=static function()use(&$uncertainCalls){$uncertainCalls++;throw new RuntimeException('timeout');};mt_denied(fn()=>po_mt_create($db,(int)$u['id'],$settings,$timeout),'ambiguous Snap creation reports uncertainty');po_mt_create($db,(int)$u['id'],$settings,$timeout);mt_check($uncertainCalls===1,'uncertain creation never blindly retries');
 [$n,$np,$nt]=mt_new($db,$sid,$pid,$secret);$notice=mt_status($np);$notice['signature_key']=hash('sha512',$notice['order_id'].$notice['status_code'].$notice['gross_amount'].$settings['server_key']);
 mt_denied(fn()=>po_mt_notification($db,$settings,json_encode(array_replace($notice,['signature_key'=>'forged'])),static fn()=>throw new LogicException('must not call API')),'forged webhook cannot call provider');
 po_mt_notification($db,$settings,json_encode($notice),static fn()=>mt_status($np,'pending'));mt_check(po_order($db,$nt)['payment_status']==='unpaid','signed webhook fields alone cannot fabricate paid status');
 po_query($db,'UPDATE po_midtrans_payments SET next_check=0 WHERE order_id=?',[$n['id']]);po_mt_notification($db,$settings,json_encode($notice),static fn()=>mt_status($np));mt_check(po_order($db,$nt)['payment_status']==='paid','valid webhook uses authenticated API status');
 po_mt_apply($db,(int)$n['id'],mt_status($np,'refund'));mt_check(po_order($db,$nt)['payment_status']==='paid_review','refund is flagged for owner review without silently releasing quota');
 // Concurrent browser requests can create only one Snap session.
 [$race,$rp,$rt]=mt_new($db,$sid,$pid,$secret);$workers=[];$callLog=$root.'/create-calls';
 $bootstrap='define("RUETHIES_ADMIN",true);define("RUETHIES_PUBLIC",'.var_export($public,true).');function ruethies_data_dir(){return '.var_export($private,true).';}require '.var_export(dirname(__DIR__).'/admin/po-lib.php',true).';$db=po_db();$settings=po_mt_settings(po_config());';
 for($i=0;$i<2;$i++){
   $code=$bootstrap.'po_mt_create($db,'.(int)$race['id'].',$settings,static function()use($db){if($db->inTransaction())throw new RuntimeException("network under lock");file_put_contents('.var_export($callLog,true).',"call\\n",FILE_APPEND|LOCK_EX);usleep(400000);return ["redirect_url"=>"https://app.sandbox.midtrans.com/snap/v4/redirection/race-token"];});';
   $worker=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$workers[]=[$worker,$pipes];
 }
 foreach($workers as[$worker,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);mt_check(proc_close($worker)===0&&$err==='','parallel create worker completes');}
 mt_check(substr_count(file_get_contents($callLog),'call')===1,'parallel pay attempts make one provider create request');
 $workers=[];
 for($i=0;$i<2;$i++){
   $code=$bootstrap.'po_mt_apply($db,'.(int)$race['id'].','.var_export(mt_status($rp),true).');';$worker=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$workers[]=[$worker,$pipes];
 }
 foreach($workers as[$worker,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);mt_check(proc_close($worker)===0&&$err==='','parallel paid worker completes');}
 mt_check((int)po_query($db,"SELECT COUNT(*) FROM po_order_events WHERE order_id=? AND event='midtrans_paid'",[$race['id']])->fetchColumn()===1,'concurrent paid notifications commit once');
 [$challenge,$cp,$ct]=mt_new($db,$sid,$pid,$secret);po_mt_apply($db,(int)$challenge['id'],array_replace(mt_status($cp,'capture'),['fraud_status'=>'challenge']));mt_check(po_order($db,$ct)['payment_status']==='unpaid','fraud challenge cannot mark paid');
 // HTTP requests never leave localhost. Reuse an already created mocked Snap URL.
 $socket=stream_socket_server('tcp://127.0.0.1:0');$address=stream_socket_get_name($socket,false);fclose($socket);
 $process=proc_open([PHP_BINARY,'-d','session.save_path='.$root.'/sessions','-S',$address,'-t',$public,dirname(__DIR__).'/tests/po-router.php'],[0=>['pipe','r'],1=>['file',$root.'/http.log','a'],2=>['file',$root.'/http.log','a']],$pipes);
 $curl=curl_init();curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>10]);
 $http=static function(string $path,?array $post=null)use($curl,$address){curl_setopt_array($curl,[CURLOPT_URL=>'http://'.$address.$path,CURLOPT_POST=>$post!==null]);if($post!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));$body=curl_exec($curl);return[(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),$body,(string)curl_getinfo($curl,CURLINFO_REDIRECT_URL)];};
 for($i=0;$i<40;$i++){usleep(100000);[$code,$html]=$http('/pre-order/');if($code)break;}
 mt_check($code===200&&str_contains($html,'Midtrans Test'),'Sandbox schedule available over HTTP');
 [$code,$html]=$http('/pre-order/status/?t='.$ft);preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$match);$csrf=$match[1];mt_check($code===200&&str_contains($html,'Bayar di Midtrans Sandbox')&&!str_contains($html,'sender_name'),'Sandbox invoice has pay button and no manual-transfer form');
 mt_check(str_contains($html,'Mode Sandbox')&&!str_contains($html,'password akses')&&!str_contains($html,'cron'),'client invoice discloses simulation without obsolete access or worker instructions');
 [$code,$html]=$http('/pre-order/status/?t='.$ft,['action'=>'midtrans-pay','csrf'=>'wrong']);mt_check(str_contains($html,'Sesi formulir berakhir'),'pay endpoint requires customer CSRF');
 [$code,$html]=$http('/pre-order/status/?t='.$ft,['action'=>'report-transfer','csrf'=>$csrf,'sender_name'=>'Test','transfer_amount'=>25000,'transfer_reference'=>'Test']);mt_check(po_order($db,$ft)['payment_status']==='unpaid','manual transfer report cannot affect gateway order');
 po_query($db,"UPDATE po_midtrans_payments SET create_state='ready',snap_url=? WHERE order_id=?",['https://app.sandbox.midtrans.com/snap/v4/redirection/existing-token',$f['id']]);
 [$code,$html,$redirect]=$http('/pre-order/status/?t='.$ft,['action'=>'midtrans-pay','csrf'=>$csrf]);mt_check($code===303&&po_mt_url($redirect),'pay redirects to stored validated sandbox URL without new API call');
 [$code,$html,$redirect]=$http('/pre-order/return/?transaction_status=settlement&order_id=forged');mt_check($code===303&&str_contains($redirect,'t='.$ft)&&po_order($db,$ft)['payment_status']==='unpaid','return uses own session and ignores forged payment query');
 [$code]=$http('/midtrans-notify.php');mt_check($code===405,'webhook refuses GET');
 [$code]=$http('/midtrans-notify.php',['signature_key'=>'invalid']);mt_check($code===403,'webhook refuses invalid payload over HTTP');
 [$code,$login]=$http('/admin/index.php');preg_match('/name="csrf" value="([a-f0-9]+)"/',$login,$loginMatch);
 [$code]=$http('/admin/index.php',['aksi'=>'login','csrf'=>$loginMatch[1],'u'=>'owner','p'=>'Demo-test-password']);mt_check($code===302,'owner login works with gateway release');
 [$code,$publicHtml]=$http('/pre-order/');mt_check($code===200&&!str_contains($publicHtml,'Default Sandbox'),'open retired schedule is excluded from public catalog');
 [$code,$admin]=$http('/admin/po.php?view=jadwal');mt_check($code===200&&!str_contains($admin,'Default Sandbox'),'retired schedule is excluded from admin list');
 [$code]=$http('/admin/po.php?view=jadwal&edit=1&slot='.$defaultSlot);mt_check($code===404,'direct editing of retired schedule returns 404');
 [$code,$admin]=$http('/admin/po.php?view=jadwal&edit=new');mt_check($code===200&&str_contains($admin,'Midtrans Sandbox')&&!str_contains($admin,'name="flow_version"')&&!str_contains($admin,'view=rekening'),'admin has one Midtrans flow without type selector or bank tab');
 [$code,$admin]=$http('/admin/po.php?view=pesanan&order='.$f['id']);mt_check($code===200&&str_contains($admin,'Periksa Midtrans Sandbox')&&!str_contains($admin,'name="decision"'),'gateway admin detail has status check without manual approval form');
 [$code,$admin]=$http('/admin/po.php?view=pesanan&order='.$id);mt_check($code===200&&str_contains($admin,'value="manual-fulfill"'),'paid gateway admin detail exposes fulfillment action');
 [$code,$admin]=$http('/admin/po.php?view=pesanan&order='.$e['id']);mt_check($code===200&&str_contains($admin,'Jangan proses pesanan otomatis'),'late payment is visible to owner');
 [$code,$admin]=$http('/admin/po.php?view=pesanan&payment=paid_review');mt_check($code===200&&str_contains($admin,'Perlu ditinjau')&&str_contains($admin,$e['code'])&&!str_contains($admin,$f['code']),'review filter exposes exceptional Sandbox payments without unpaid orders');
 [$code]=$http('/admin/midtrans-upgrade.php');mt_check($code===404,'gateway migration is CLI-only');
 $stockBefore=po_query($db,'SELECT quota,reserved FROM po_products WHERE id=?',[$pid])->fetch();
 po_query($db,'UPDATE po_products SET reserved=quota WHERE id=?',[$pid]);
 [$code,$stockHtml]=$http('/pre-order/?slot='.$sid);
 mt_check($code===200&&str_contains($stockHtml,'class="availability sold-out">Habis</span>')&&str_contains($stockHtml,'Semua sajian untuk jadwal ini sudah habis')&&preg_match('/<button type="submit" disabled>Periksa ringkasan/',$stockHtml),'sold-out product has clear label and disables checkout when all quota is used');
 [$code,$stockAdmin]=$http('/admin/po.php?view=produk&slot='.$sid);mt_check($code===200&&str_contains($stockAdmin,'class="badge">Habis</span>'),'admin product list also labels exhausted quota');
 po_query($db,'UPDATE po_products SET reserved=? WHERE id=?',[$stockBefore['reserved'],$pid]);
 [$code,$stockHtml]=$http('/pre-order/?slot='.$sid);mt_check($code===200&&!str_contains($stockHtml,'class="availability sold-out"')&&!preg_match('/<button type="submit" disabled>Periksa ringkasan/',$stockHtml),'available quota restores purchasable product without stale sold-out status');
 mt_check(!preg_match('/PHP (Warning|Fatal)|Uncaught/',file_get_contents($root.'/http.log')),'HTTP flow has no PHP errors');
 echo "All Midtrans Sandbox tests passed; provider calls mocked.\n";
}finally{if($curl)$curl=null;if(is_resource($process)){proc_terminate($process);proc_close($process);}$db=null;$server->exec("DROP DATABASE `$database`");if(is_dir($root))mt_remove($root);}
