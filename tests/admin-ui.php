<?php
// CLI only. Fixture admin terisolasi: akun/konten uji di /tmp, tanpa database atau API.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
set_error_handler(static function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});
$root=sys_get_temp_dir().'/ruethies-admin-ui-'.bin2hex(random_bytes(5));$public=$root.'/public_html';$private=$root.'/ruethies-data';$process=null;$curl=null;
function ui_check(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
function ui_xpath(string $html): DOMXPath {$doc=new DOMDocument();libxml_use_internal_errors(true);$doc->loadHTML($html);libxml_clear_errors();return new DOMXPath($doc);}
function ui_csrf(string $html): string {$x=ui_xpath($html);$node=$x->query('//input[@name="csrf"]')->item(0);if(!$node)throw new RuntimeException('CSRF missing');return $node->getAttribute('value');}
function ui_remove(string $path): void {if(!is_dir($path)||is_link($path)){unlink($path);return;}foreach(scandir($path)as$n)if($n!=='.'&&$n!=='..')ui_remove($path.'/'.$n);rmdir($path);}
try {
  mkdir($root.'/sessions',0700,true);mkdir($public.'/admin',0700,true);mkdir($private,0700,true);mkdir($public.'/images/menu',0700,true);
  $source=dirname(__DIR__);
  foreach(['admin/index.php','admin/config.php','admin/lib.php','admin/security.php','admin/security-resources.php','admin/security-upgrade.php','admin/admin.css','admin/admin.js','index.html','llms.txt']as$file)copy($source.'/'.$file,$public.'/'.$file);
  $menu=[
    ['uid'=>str_repeat('a',32),'cat'=>'cakery','available'=>true,'signature'=>true,'id'=>['n'=>'Croissant uji','d'=>'Lembut dan renyah'],'en'=>['n'=>'Test croissant','d'=>'Test'],'img'=>'images/menu/croissant-au-beurre.jpg'],
    ['uid'=>str_repeat('b',32),'cat'=>'packages','available'=>true,'signature'=>false,'id'=>['n'=>'Snackbox uji','d'=>'Paket kudapan'],'en'=>['n'=>'Test snackbox','d'=>'Test'],'img'=>'images/menu/snackbox.jpg'],
    ['uid'=>str_repeat('c',32),'cat'=>'savoury','available'=>false,'signature'=>false,'id'=>['n'=>'Risol tersembunyi','d'=>'Tidak tampil'],'en'=>['n'=>'Hidden risol','d'=>'Test'],'img'=>'images/menu/risol-mayo-smoked-beef.jpg'],
  ];
  foreach($menu as$m)copy($source.'/'.$m['img'],$public.'/'.$m['img']);
  file_put_contents($public.'/menu.json',json_encode($menu));
  file_put_contents($public.'/atmosphere.json',json_encode([['img'=>$menu[0]['img'],'imgFull'=>$menu[0]['img'],'id'=>['cap'=>'Sudut toko'],'en'=>['cap'=>'Shop corner']]]));
  file_put_contents($public.'/faq.json',json_encode([['q'=>'Jam buka?','a'=>'Setiap hari.','qe'=>'Opening hours?','ae'=>'Every day.']]));
  file_put_contents($private.'/users.json',json_encode([
    ['name'=>'owner','role'=>'owner','hash'=>password_hash('Demo-test-password',PASSWORD_DEFAULT),'totp'=>'','auth_version'=>bin2hex(random_bytes(32)),'last_totp_counter'=>-1],
    ['name'=>'staff','role'=>'staff','hash'=>password_hash('Demo-test-password',PASSWORD_DEFAULT),'totp'=>'','auth_version'=>bin2hex(random_bytes(32)),'last_totp_counter'=>-1],
  ]));
  $socket=stream_socket_server('tcp://127.0.0.1:0');$address=stream_socket_get_name($socket,false);fclose($socket);
  $process=proc_open([PHP_BINARY,'-d','session.save_path='.$root.'/sessions','-S',$address,'-t',$public],[0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes);
  $curl=curl_init();curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>5]);
  $request=static function(string $path,?array $data=null,bool $multipart=false)use($curl,$address):array{
    curl_setopt_array($curl,[CURLOPT_URL=>'http://'.$address.$path,CURLOPT_POST=>$data!==null]);if($data!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,$multipart?$data:http_build_query($data));
    $html=curl_exec($curl);return [(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),(string)$html,(string)curl_getinfo($curl,CURLINFO_REDIRECT_URL)];
  };
  for($i=0;$i<40;$i++){usleep(100000);[$code,$html]=$request('/admin/');if($code===200)break;}
  ui_check($code===200&&str_contains($html,'name="u"')&&!str_contains($html,'class="shell"'),'login renders without exposing admin content');
  [$denied]=$request('/admin/',['aksi'=>'login','u'=>'owner','p'=>'Demo-test-password']);ui_check($denied===403,'login rejects missing CSRF');
  [$denied]=$request('/admin/',['aksi'=>'kode','c'=>'123456']);ui_check($denied===403,'OTP verification rejects missing CSRF');
  [$denied]=$request('/admin/',['aksi'=>'logout']);ui_check($denied===403,'logout rejects missing CSRF');
  [$denied]=$request('/admin/',['aksi'=>'logout','csrf'=>ui_csrf($html)]);ui_check($denied===302&&!is_file($private.'/log.jsonl'),'anonymous logout never writes user activity log');
  [$code,$html]=$request('/admin/');
  [$denied]=$request('/admin/',['aksi'=>'login','csrf'=>ui_csrf($html),'u'=>['owner'],'p'=>'Demo-test-password']);ui_check($denied===200,'array authentication input produces a handled form error');
  [$denied]=$request('/admin/?p[]=pengaturan');ui_check($denied===400,'array query parameters return a generic client error');
  [$denied]=$request('/admin/security-upgrade.php');ui_check($denied===404,'upgrade and SSH recovery are inaccessible over HTTP');
  file_put_contents($public.'/admin/login-preview.html',$html);
  [$code]=$request('/admin/index.php',['aksi'=>'login','csrf'=>ui_csrf($html),'u'=>'owner','p'=>'Demo-test-password']);ui_check($code===302,'owner login works');
  [$code,$html]=$request('/admin/');$x=ui_xpath($html);$csrf=ui_csrf($html);
  ui_check($code===200&&$x->query('//h1')->length===1&&$x->query('//*[@id="item-0"]')->length===1&&$x->query('//*[@id="galeri" and contains(@class,"card")]')->length===0,'Menu has one heading and no gallery/FAQ content payload');
  ui_check($x->query('//a[@aria-current="page"]')->length===2 && !preg_match('/<[^>]+\bclass="[^"]*"[^>]+\bclass="/', $html), 'active navigation is present without JS and attributes are not duplicated');
  ui_check($x->query('//form[@role="search"]')->length===1&&str_contains($html,'name="cat"')&&str_contains($html,'name="show"'),'search/category/status filters are plain HTML forms');
  file_put_contents($public.'/admin/menu-preview.html',$html);
  [$code,$html]=$request('/admin/?q=Snackbox&cat=packages&show=visible');$x=ui_xpath($html);
  ui_check($x->query('//*[@id="item-1"]')->length===1&&$x->query('//*[@id="item-0"]')->length===0&&str_contains($html,'name="i" value="1"'),'filter retains original menu indexes for actions');
  [$code,$html]=$request('/admin/?show=hidden');$x=ui_xpath($html);ui_check($x->query('//*[@id="item-2"]')->length===1&&$x->query('//*[@id="item-1"]')->length===0,'hidden filter works without JavaScript');
  [$code,$html]=$request('/admin/?q=does-not-exist');ui_check(str_contains($html,'Tidak ada menu yang cocok.'),'empty search has a recovery message');
  [$code,$html]=$request('/admin/?q=%22%3E%3Cscript%3Ealert(1)%3C/script%3E');ui_check(!str_contains($html,'<script>alert(1)</script>')&&str_contains($html,'&lt;script&gt;'),'search input is escaped');
  [$code,$html]=$request('/admin/?p=galeri');$x=ui_xpath($html);ui_check($x->query('//*[@id="gitem-0"]')->length===1&&$x->query('//*[@id="item-0"]')->length===0&&$x->query('//*[@id="faqman"]')->length===0,'gallery renders its own list only');
  file_put_contents($public.'/admin/gallery-preview.html',$html);
  [$code,$html]=$request('/admin/?p=galeri-ubah&gi=0');$x=ui_xpath($html);ui_check($x->query('//*[@id="formulir-g"]')->length===1&&$x->query('//*[@id="gitem-0"]')->length===0,'gallery editor opens directly without list clutter');
  [$code,$html,$redirect]=$request('/admin/?p=galeri-ubah&gi=0',['aksi'=>'galeri-save','csrf'=>$csrf,'gi'=>0,'cap'=>'Sudut baru','cape'=>'New corner']);ui_check($code===302&&str_contains($redirect,'p=galeri&ok=simpan'),'gallery save returns to gallery');
  [$code,$html]=$request('/admin/?p=faq');$x=ui_xpath($html);ui_check($x->query('//*[@id="fitem-0"]')->length===1&&$x->query('//*[@id="item-0"]')->length===0,'FAQ renders its own list only');
  [$code,$html]=$request('/admin/?p=faq-ubah&fi=0');$x=ui_xpath($html);ui_check($x->query('//*[@id="formulir-f"]')->length===1&&$x->query('//*[@id="fitem-0"]')->length===0,'FAQ editor opens directly');
  [$code,$html,$redirect]=$request('/admin/?p=faq-ubah&fi=0',['aksi'=>'faq-save','csrf'=>$csrf,'fi'=>0,'q'=>'Jam buka?','a'=>'Setiap pagi.']);ui_check($code===302&&str_contains($redirect,'p=faq&ok=simpan'),'FAQ save returns to FAQ');
  [$code,$html]=$request('/admin/?p=ubah&i=1');$x=ui_xpath($html);ui_check($x->query('//*[@id="formulir"]')->length===1&&str_contains($html,'value="Snackbox uji"')&&$x->query('//*[@id="fitem-0"]')->length===0,'menu editor is isolated and prefilled');
  file_put_contents($public.'/admin/editor-preview.html',$html);
  [$code,$html]=$request('/admin/?p=pengaturan');$x=ui_xpath($html);ui_check(str_contains($html,'Ganti kata sandi')&&$x->query('//*[@id="item-0"]')->length===0&&$x->query('//div[@class="table-scroll"]')->length===1,'settings has its own heading and horizontally scrollable log');
  [$code,$html]=$request('/admin/',['aksi'=>'toggle','i'=>1,'csrf'=>'wrong']);ui_check($code===403,'mutations still require CSRF');
  [$code]=$request('/admin/',['aksi'=>'login','csrf'=>$csrf,'u'=>'staff','p'=>'Demo-test-password']);ui_check($code===302,'staff login works');
  [$code,$html]=$request('/admin/');ui_check(!str_contains($html,'name="aksi" value="hapus"')&&!str_contains($html,'href="?p=pengaturan"'),'staff does not see owner controls');
  [$code,$html]=$request('/admin/',['aksi'=>'toggle','i'=>2,'csrf'=>ui_csrf($html)]);ui_check($code===302&&json_decode(file_get_contents($public.'/menu.json'),true)[2]['available']===true,'staff visibility toggle still updates intended item');
  [$code,$html]=$request('/admin/');
  [$code]=$request('/admin/',['aksi'=>'login','csrf'=>ui_csrf($html),'u'=>'owner','p'=>'Demo-test-password']);
  [$code,$html]=$request('/admin/?p=pengaturan');$csrf=ui_csrf($html);
  [$code]=$request('/admin/',['aksi'=>'galeri-save','csrf'=>$csrf,'gi'=>'0','cap'=>'Multipart normal','gfoto'=>new CURLFile($public.'/'.$menu[0]['img'],'image/jpeg','normal.jpg')],true);$gallery=json_decode(file_get_contents($public.'/atmosphere.json'),true);
  ui_check($code===302&&getimagesize($public.'/'.$gallery[0]['img'])[0]===640,'normal multipart upload passes metadata validation and re-encoding');
  [$code]=$request('/admin/',['aksi'=>'save','csrf'=>$csrf,'i'=>'1','cat'=>'packages','n'=>'Menu base64','foto_b64'=>'data:image/jpeg;base64,'.base64_encode(file_get_contents($public.'/'.$menu[1]['img']))]);$updated=json_decode(file_get_contents($public.'/menu.json'),true);
  ui_check($code===302&&$updated[1]['id']['n']==='Menu base64'&&getimagesize($public.'/'.$updated[1]['img'])[0]===640,'base64 CMS upload succeeds end to end');
  $updateSession=static function(array $fields)use($curl,$root):void {
    $sid=null;foreach(curl_getinfo($curl,CURLINFO_COOKIELIST)as$cookie){$parts=explode("\t",$cookie);if(($parts[5]??'')==='RUETHIESADM')$sid=$parts[6];}
    if(!$sid)throw new RuntimeException('fixture session cookie missing');
    $edit='ini_set("session.use_cookies","0");session_cache_limiter("");session_save_path('.var_export($root.'/sessions',true).');session_id('.var_export($sid,true).');session_start();foreach('.var_export($fields,true).' as $k=>$v)$_SESSION[$k]=$v;session_write_close();';
    $p=proc_open([PHP_BINARY,'-r',$edit],[1=>['pipe','w'],2=>['pipe','w']],$ep);foreach($ep as$pipe){$output=stream_get_contents($pipe);fclose($pipe);if($output!=='')throw new RuntimeException('fixture session worker warning');}if(proc_close($p)!==0)throw new RuntimeException('fixture session worker failed');
  };
  $updateSession(['reauth_at'=>time()-300]);
  $before=file_get_contents($private.'/users.json');
  [$code,$html,$redirect]=$request('/admin/',['aksi'=>'user-tambah','csrf'=>$csrf,'nu'=>'newstaff','np'=>'Demo-test-password']);ui_check($code===303&&str_contains($redirect,'p=verifikasi')&&file_get_contents($private.'/users.json')===$before,'expired reauth prevents staff creation without storing pending secrets');
  [$code]=$request('/admin/',['aksi'=>'reauth','current_password'=>'Demo-test-password']);ui_check($code===403,'reauth requires CSRF');
  [$code]=$request('/admin/',['aksi'=>'reauth','csrf'=>$csrf,'current_password'=>'wrong-password']);ui_check($code===200,'wrong password cannot refresh reauth');
  [$code]=$request('/admin/',['aksi'=>'reauth','csrf'=>$csrf,'current_password'=>'Demo-test-password']);ui_check($code===302,'password reauth succeeds');
  [$code]=$request('/admin/',['aksi'=>'user-tambah','csrf'=>'wrong','nu'=>'newstaff','np'=>'Demo-test-password']);ui_check($code===403&&file_get_contents($private.'/users.json')===$before,'sensitive action also requires CSRF within grace');
  [$code]=$request('/admin/',['aksi'=>'user-tambah','csrf'=>$csrf,'nu'=>'newstaff','np'=>'Demo-test-password']);$accounts=json_decode(file_get_contents($private.'/users.json'),true);ui_check($code===302&&count($accounts)===3,'freshly verified owner can create staff');
  [$code]=$request('/admin/',['aksi'=>'user-hapus','csrf'=>$csrf,'nu'=>'newstaff','target_version'=>$accounts[2]['auth_version']]);ui_check($code===302&&count(json_decode(file_get_contents($private.'/users.json'),true))===2,'staff revocation validates current target identity');
  [$code]=$request('/admin/',['aksi'=>'pw','csrf'=>$csrf,'p1'=>'Replacement-password!','p2'=>'Replacement-password!']);ui_check($code===302,'password change succeeds after reauth');
  [$code]=$request('/admin/',['aksi'=>'toggle','csrf'=>$csrf,'i'=>0]);ui_check($code===403,'password change immediately removes the initiating session');
  [$code,$html]=$request('/admin/');[$code]=$request('/admin/',['aksi'=>'login','csrf'=>ui_csrf($html),'u'=>'owner','p'=>'Replacement-password!']);ui_check($code===302,'owner can log back in using new password');
  $updateSession(['last'=>time()-1800]);[$code,$html]=$request('/admin/');ui_check($code===200&&str_contains($html,'name="u"')&&!str_contains($html,'class="shell"'),'idle session expires at 30 minutes');
  [$code]=$request('/admin/',['aksi'=>'login','csrf'=>ui_csrf($html),'u'=>'owner','p'=>'Replacement-password!']);ui_check($code===302,'owner login after idle expiration works');
  $updateSession(['born'=>time()-28800,'last'=>time()]);[$code,$html]=$request('/admin/');ui_check($code===200&&str_contains($html,'name="u"')&&!str_contains($html,'class="shell"'),'absolute session expires at eight hours despite recent activity');
  ui_check(!preg_match('/PHP (Warning|Fatal)|Uncaught/',file_get_contents($root.'/server.log')),'admin HTTP flow has no PHP warnings or fatal errors');
  echo "All admin UI HTTP checks passed.\n";
  if(getenv('ADMIN_UI_KEEP_FIXTURE'))echo 'Preview fixture: '.$public.'/admin'."\n";
} finally {
  if($curl)$curl=null;if(is_resource($process)){proc_terminate($process);proc_close($process);}
  if(!getenv('ADMIN_UI_KEEP_FIXTURE')&&is_dir($root))ui_remove($root);
}
