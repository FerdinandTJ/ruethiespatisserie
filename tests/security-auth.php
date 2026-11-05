<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ob_start();
set_error_handler(static function($s,$m,$f,$l){if(!(error_reporting()&$s))return false;throw new ErrorException($m,0,$s,$f,$l);});
$root=sys_get_temp_dir().'/ruethies-auth-'.bin2hex(random_bytes(6));mkdir($root,0700);mkdir($root.'/sessions',0700);
define('RUETHIES_ADMIN',true);define('RUETHIES_PUBLIC',$root);
function ruethies_data_dir(): string {global $root;return $root;}
function ruethies_session_start(): void {if(session_status()!==PHP_SESSION_ACTIVE)session_start();}
require dirname(__DIR__).'/admin/lib.php';
ini_set('session.use_cookies','0');session_cache_limiter('');session_save_path($root.'/sessions');session_start();
$_SERVER['REMOTE_ADDR']='192.0.2.1';$_SERVER['HTTP_USER_AGENT']='SecurityFixture';
function auth_check(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
function auth_denied(callable $fn,string $class,string $label): void {try{$fn();}catch(Throwable $e){if($e instanceof $class){auth_check(true,$label);return;}throw $e;}throw new RuntimeException('Expected '.$class.': '.$label);}
function auth_seed(bool $mfa=true): array {
  $users=[['name'=>'owner','role'=>'owner','hash'=>password_hash('Demo-test-password',PASSWORD_DEFAULT),'totp'=>$mfa ? 'JBSWY3DPEHPK3PXP' : ''],['name'=>'staff','role'=>'staff','hash'=>password_hash('Demo-test-password',PASSWORD_DEFAULT),'totp'=>'']];
  security_write(users_file(),$users);security_upgrade_users(true);security_write(ruethies_data_dir().'/security-rates.json',[]);$_SESSION=[];$_SERVER['REMOTE_ADDR']='192.0.2.1';return users_all();
}
function auth_parallel(array $bodies): array {
  global $root;$children=[];
  foreach($bodies as $body){
    $code='define("RUETHIES_ADMIN",true);define("RUETHIES_PUBLIC",'.var_export($root,true).');function ruethies_data_dir():string{return '.var_export($root,true).';}require '.var_export(dirname(__DIR__).'/admin/lib.php',true).';$_SESSION=["reauth_at"=>time()];$_SERVER["REMOTE_ADDR"]="192.0.2.90";'.$body;
    $pipes=[];$p=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$children[]=[$p,$pipes];
  }
  $out=[];foreach($children as[$p,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);auth_check(proc_close($p)===0&&$err==='','parallel auth worker completed');}sort($out);return $out;
}
function auth_remove(string $p): void {if(!is_dir($p)||is_link($p)){unlink($p);return;}foreach(scandir($p)as$n)if($n!=='.'&&$n!=='..')auth_remove($p.'/'.$n);rmdir($p);}
try{
  $original=[['name'=>'owner','role'=>'owner','hash'=>password_hash('Demo-test-password',PASSWORD_DEFAULT),'totp'=>'JBSWY3DPEHPK3PXP']];security_write(users_file(),$original);
  auth_check(security_upgrade_users(false)===1&&!isset(users_all()[0]['auth_version']),'upgrade check does not alter account metadata');
  auth_check(security_upgrade_users(true)===1&&security_upgrade_users(true)===0,'upgrade is idempotent');$u=user_get('owner');
  auth_check($u['hash']===$original[0]['hash']&&$u['totp']===$original[0]['totp'],'upgrade preserves password hash and authenticator secret');
  auth_check((fileperms(users_file())&0777)===0600,'private account file has mode 0600');
  auth_seed();
  auth_check(security_password_login('owner','Demo-test-password')&&isset($_SESSION['pending'])&&!isset($_SESSION['uid']),'valid password only creates MFA challenge');
  $rates=security_read($root.'/security-rates.json');auth_check(count($rates)===2,'password quota is not reset before MFA');
  for($i=0;$i<5;$i++)auth_check(!security_challenge_login('abcdef'),'wrong OTP rejected '.($i+1));
  auth_denied(fn()=>security_challenge_login('abcdef'),DomainException::class,'sixth OTP cannot reuse exhausted challenge');
  auth_check(security_password_login('owner','Demo-test-password'),'new password challenge can be requested');
  auth_denied(fn()=>security_challenge_login('abcdef'),RuethiesRateLimit::class,'new challenge cannot bypass account MFA limit');
  auth_seed();security_password_login('owner','Demo-test-password');$_SESSION['pending']['started']=time()-300;
  auth_denied(fn()=>security_challenge_login('abcdef'),DomainException::class,'MFA expires at five minutes');
  auth_seed();security_password_login('owner','Demo-test-password');$u=user_get('owner');$code=totp_at($u['totp'],(int)floor(time()/30));
  auth_check(security_challenge_login($code)&&current_user()['name']==='owner','full MFA login establishes version-bound session');
  auth_check(security_read($root.'/security-rates.json')===[],'rate counters reset only after complete authentication');
  auth_check(security_consume_totp($u['name'],$u['auth_version'],$code)===null,'accepted TOTP cannot be replayed');
  $_SESSION['reauth_at']=time()-299;security_require_reauth();$_SESSION['reauth_at']=time()-300;
  auth_denied(fn()=>security_require_reauth(),RuethiesReauthRequired::class,'reauth grace expires at five minutes');
  $_SESSION['reauth_at']=time();$oldSession=$_SESSION;$actor=current_user();security_change_password($actor,'A-new-secure-password!','A-new-secure-password!');$_SESSION=$oldSession;
  auth_check(current_user()===null,'password change invalidates old authenticated session');ruethies_session_start();
  auth_seed();security_password_login('owner','Demo-test-password');$pending=$_SESSION['pending'];$actor=user_get('owner');$_SESSION['reauth_at']=time();security_change_password($actor,'A-new-secure-password!','A-new-secure-password!');$_SESSION['pending']=$pending;
  auth_check(!security_challenge_login(totp_at($actor['totp'],(int)floor(time()/30))),'password change invalidates pending password challenge');
  auth_seed();$u=user_get('owner');$code=totp_at($u['totp'],(int)floor(time()/30));$body='$u=security_consume_totp("owner",'.var_export($u['auth_version'],true).','.var_export($code,true).');echo $u ? "accepted" : "replay";';
  auth_check(auth_parallel([$body,$body])===['accepted','replay'],'parallel TOTP verification accepts one caller only');
  auth_seed(false);$jobs=[];for($i=0;$i<8;$i++)$jobs[]='try{security_rate_take("password","owner");echo "reserved";}catch(RuethiesRateLimit $e){echo "limited";}';
  $results=auth_parallel($jobs);auth_check(count(array_filter($results,fn($v)=>$v==='reserved'))===5&&count(array_filter($results,fn($v)=>$v==='limited'))===3,'parallel password requests cannot exceed five reservations');
  auth_seed(false);for($i=0;$i<5;$i++){$_SERVER['REMOTE_ADDR']='192.0.2.'.($i+1);security_rate_take('password','owner');}$_SERVER['REMOTE_ADDR']='192.0.2.99';
  auth_denied(fn()=>security_rate_take('password','owner'),RuethiesRateLimit::class,'changing IP cannot bypass account limit');
  $users=auth_seed(false);$owner=$users[0];$staff=$users[1];$_SESSION=['reauth_at'=>time()];
  $remove='try{security_remove_staff('.var_export($owner,true).',"staff",'.var_export($staff['auth_version'],true).');echo "removed";}catch(DomainException $e){echo "stale";}';
  $change='try{security_change_password('.var_export($staff,true).',"New-staff-password!","New-staff-password!");echo "changed";}catch(DomainException $e){echo "stale";}';
  $results=auth_parallel([$remove,$change]);auth_check(in_array($results,[['removed','stale'],['changed','stale']],true),'password/revocation race cannot report deletion then restore staff');
  if(user_get('staff'))security_remove_staff($owner,'staff',user_get('staff')['auth_version']);
  auth_denied(fn()=>security_change_password($staff,'New-staff-password!','New-staff-password!'),DomainException::class,'stale staff action cannot recreate deleted account');
  security_add_staff($owner,'staff','Replacement-password!');$_SESSION=['uid'=>'staff','auth_version'=>$staff['auth_version'],'ua'=>md5('SecurityFixture')];
  auth_check(current_user()===null,'recreating same username does not revive an old session');ruethies_session_start();
  file_put_contents(users_file(),'{broken');
  auth_denied(fn()=>users_mutate(fn(&$users)=>$users=[]),RuntimeException::class,'corrupt account store fails closed');
  auth_check(file_get_contents(users_file())==='{broken','corrupt account store is never overwritten with defaults');
  auth_seed(false);$before=file_get_contents(users_file());unlink($root.'/users.lock');mkdir($root.'/users.lock');
  auth_denied(fn()=>security_upgrade_users(true),RuntimeException::class,'unavailable account lock fails closed');auth_check(file_get_contents(users_file())===$before,'lock failure leaves account data unchanged');rmdir($root.'/users.lock');
  auth_denied(fn()=>security_input(['x'=>['array']],'x'),DomainException::class,'array inputs rejected without casting');
  echo "All auth security checks passed.\n";
}finally{if(session_status()===PHP_SESSION_ACTIVE)session_destroy();auth_remove($root);ob_end_flush();}
