<?php
// Native PHP security primitives. Private state never belongs in the public JSON catalog.
defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.');

class RuethiesRateLimit extends RuntimeException {}
class RuethiesReauthRequired extends RuntimeException {}

function security_lock(string $name, callable $work) {
  $file=ruethies_data_dir().'/'.$name.'.lock';
  $fh=@fopen($file,'c');
  if (!$fh) throw new RuntimeException('Penyimpanan keamanan tidak tersedia.');
  @chmod($file,0600);$until=microtime(true)+2;
  try {
    do {
      if (flock($fh,LOCK_EX|LOCK_NB)) {
        try { return $work(); } finally { flock($fh,LOCK_UN); }
      }
      usleep(10000);
    } while (microtime(true)<$until);
    throw new RuntimeException('Penyimpanan keamanan sedang sibuk. Coba lagi.');
  } finally { fclose($fh); }
}
function security_read(string $file, array $missing=[]): array {
  if (!file_exists($file)) return $missing;
  $raw=@file_get_contents($file);
  if ($raw===false) throw new RuntimeException('Data keamanan tidak dapat dibaca.');
  try { $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR); }
  catch (JsonException $e) { throw new RuntimeException('Data keamanan rusak. Hubungi pengelola.'); }
  if (!is_array($data)) throw new RuntimeException('Format data keamanan tidak valid.');
  return $data;
}
function security_write(string $file, array $data): void {
  $raw=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
  $tmp=$file.'.tmp.'.bin2hex(random_bytes(12));$fh=@fopen($tmp,'x');
  if (!$fh) throw new RuntimeException('Data keamanan tidak dapat disimpan.');
  try {
    if (!chmod($tmp,0600)) throw new RuntimeException('Izin data keamanan tidak dapat diatur.');
    for ($offset=0;$offset<strlen($raw);$offset+=$n) {
      $n=fwrite($fh,substr($raw,$offset));
      if (!$n) throw new RuntimeException('Penulisan data keamanan gagal.');
    }
    if (!fflush($fh)) throw new RuntimeException('Penulisan data keamanan gagal.');
    fclose($fh);$fh=null;
    if (!rename($tmp,$file)) throw new RuntimeException('Penggantian data keamanan gagal.');
  } finally { if (is_resource($fh)) fclose($fh); if (is_file($tmp)) unlink($tmp); }
}
function security_input(array $data,string $key,int $max=1024): string {
  $value=$data[$key] ?? '';
  if (!is_string($value) || strlen($value)>$max) throw new DomainException('Format atau panjang isian tidak valid.');
  return $value;
}
function users_file(): string { return ruethies_data_dir().'/users.json'; }
function users_all(): array {
  $users=security_read(users_file());
  if (!array_is_list($users)) throw new RuntimeException('Format daftar akun tidak valid.');
  $seen=[];
  foreach ($users as $u) {
    if (!is_array($u) || !is_string($u['name'] ?? null) || $u['name']==='' || !is_string($u['hash'] ?? null) || !in_array($u['role'] ?? null,['owner','staff'],true) || !is_string($u['totp'] ?? null)) throw new RuntimeException('Data akun tidak valid.');
    $key=strtolower($u['name']);if(isset($seen[$key]))throw new RuntimeException('Nama akun ganda.');$seen[$key]=true;
  }
  return $users;
}
function users_mutate(callable $work) {
  return security_lock('users',function()use($work){
    $users=users_all();$before=$users;$result=$work($users);
    if ($users!==$before) security_write(users_file(),array_values($users));
    return $result;
  });
}
function user_get(string $name): ?array {
  foreach(users_all() as $u)if(strcasecmp($u['name'],$name)===0)return $u;
  return null;
}
function security_user_index(array $users,string $name): int {
  foreach($users as $i=>$u)if(strcasecmp($u['name'],$name)===0)return $i;
  throw new DomainException('Akun sudah tidak tersedia. Masuk kembali.');
}
function security_version(array $u): string {
  $v=$u['auth_version'] ?? '';
  if (!is_string($v) || !preg_match('/\A[a-f0-9]{64}\z/',$v)) throw new RuntimeException('Upgrade keamanan belum selesai. Jalankan CLI upgrade.');
  return $v;
}
function security_actor(array $users,array $actor,bool $owner=false): int {
  $i=security_user_index($users,$actor['name']);$u=$users[$i];
  if (!hash_equals(security_version($u),security_version($actor)) || ($owner && $u['role']!=='owner')) throw new DomainException('Akses akun sudah berubah. Masuk kembali.');
  return $i;
}
function security_upgrade_users(bool $apply): int {
  $work=function(&$users) {
    if (!$users) throw new RuntimeException('Daftar akun kosong. Tidak membuat atau mereset akun.');
    $changed=0;
    foreach($users as &$u){
      if (!isset($u['auth_version'])){$u['auth_version']=bin2hex(random_bytes(32));$changed++;}
      security_version($u);
      if (!isset($u['last_totp_counter'])) $u['last_totp_counter']=-1;
      if (!is_int($u['last_totp_counter']) || $u['last_totp_counter'] < -1) throw new RuntimeException('Counter OTP akun tidak valid.');
    }unset($u);return $changed;
  };
  if ($apply)return users_mutate($work);
  $users=users_all();return $work($users);
}
function security_rate_keys(string $type,string $name,bool $ip): array {
  $keys=[$type.':account:'.hash('sha256',strtolower($name))];
  if($ip)$keys[]=$type.':ip:'.hash('sha256',ruethies_ip());
  return $keys;
}
function security_rate_take(string $type,string $name,int $limit=5,bool $ip=true): void {
  security_lock('rates',function()use($type,$name,$limit,$ip){
    $file=ruethies_data_dir().'/security-rates.json';$all=security_read($file);$now=time();
    foreach($all as $k=>$b){
      if(!is_array($b) || !is_int($b['expires'] ?? null) || !is_int($b['count'] ?? null))throw new RuntimeException('Data pembatasan tidak valid.');
      if($b['expires']<=$now)unset($all[$k]);
    }
    $keys=security_rate_keys($type,$name,$ip);
    foreach($keys as $key)if(($all[$key]['count'] ?? 0)>=$limit)throw new RuethiesRateLimit('Terlalu banyak percobaan. Tunggu 15 menit.');
    if(count($all)+count($keys)>10000)throw new RuethiesRateLimit('Terlalu banyak percobaan. Coba lagi nanti.');
    foreach($keys as $key){$b=$all[$key] ?? ['expires'=>$now+900,'count'=>0];$b['count']++;$all[$key]=$b;}
    security_write($file,$all);
  });
}
function security_rate_reset(string $name): void {
  security_lock('rates',function()use($name){
    $file=ruethies_data_dir().'/security-rates.json';$all=security_read($file);
    foreach(['password','mfa'] as $type)foreach(security_rate_keys($type,$name,true) as $key)unset($all[$key]);
    security_write($file,$all);
  });
}
function totp_counter(string $secret,string $code): ?int {
  if (!ctype_digit($code) || strlen($code)!==6)return null;
  $counter=(int)floor(time()/30);
  foreach([-1,0,1] as $w)if(hash_equals(totp_at($secret,$counter+$w),$code))return $counter+$w;
  return null;
}
function totp_verify(string $secret,string $code): bool { return totp_counter($secret,$code)!==null; }
function security_consume_totp(string $name,string $version,string $code): ?array {
  return users_mutate(function(&$users)use($name,$version,$code){
    $i=security_user_index($users,$name);$u=$users[$i];
    if(!hash_equals(security_version($u),$version) || empty($u['totp']))return null;
    $counter=totp_counter($u['totp'],$code);
    if($counter===null || $counter<=(int)($u['last_totp_counter'] ?? -1))return null;
    $users[$i]['last_totp_counter']=$counter;return $users[$i];
  });
}
function security_password_login(string $name,string $pass): bool {
  security_rate_take('password',$name);
  $u=user_get($name);
  if(!$u || !password_verify($pass,$u['hash']))return false;
  security_version($u);
  $newHash=password_needs_rehash($u['hash'],PASSWORD_DEFAULT) ? password_hash($pass,PASSWORD_DEFAULT) : $u['hash'];
  $u=users_mutate(function(&$users)use($u,$newHash){
    $i=security_actor($users,$u);
    if(!hash_equals($users[$i]['hash'],$u['hash']))throw new DomainException('Akun sudah berubah. Masuk kembali.');
    $users[$i]['hash']=$newHash;return $users[$i];
  });
  session_regenerate_id(true);$_SESSION=[];
  if($u['role']==='owner' && $u['totp']!==''){
    $_SESSION['pending']=['name'=>$u['name'],'version'=>security_version($u),'started'=>time(),'attempts'=>0];return true;
  }
  ruethies_login($u);security_rate_reset($u['name']);return true;
}
function security_challenge_login(string $code): bool {
  $p=$_SESSION['pending'] ?? null;
  if(!is_array($p) || time()-(int)$p['started']>=300 || (int)$p['attempts']>=5){unset($_SESSION['pending']);throw new DomainException('Sesi kode kedaluwarsa. Masuk kembali.');}
  $_SESSION['pending']['attempts']++;
  try{security_rate_take('mfa',$p['name']);$u=security_consume_totp($p['name'],$p['version'],$code);}
  catch(Throwable $e){if($_SESSION['pending']['attempts']>=5)unset($_SESSION['pending']);throw $e;}
  if(!$u){if($_SESSION['pending']['attempts']>=5)unset($_SESSION['pending']);return false;}
  unset($_SESSION['pending']);ruethies_login($u);security_rate_reset($u['name']);return true;
}
function current_user(): ?array {
  if(empty($_SESSION['uid']))return null;
  $u=user_get($_SESSION['uid']);
  if(!$u || !is_string($_SESSION['auth_version'] ?? null) || !isset($u['auth_version']) || !hash_equals(security_version($u),$_SESSION['auth_version']) || ($_SESSION['ua'] ?? '')!==md5($_SERVER['HTTP_USER_AGENT'] ?? '')){ruethies_logout();return null;}
  return $u;
}
function is_owner(?array $u): bool{return $u && $u['role']==='owner';}
function ruethies_login(array $u): void {
  session_regenerate_id(true);$_SESSION=['uid'=>$u['name'],'auth_version'=>security_version($u),'ua'=>md5($_SERVER['HTTP_USER_AGENT'] ?? ''),'born'=>time(),'last'=>time(),'reauth_at'=>time()];
}
function ruethies_logout(): void {
  $_SESSION=[];
  if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'', ['expires'=>time()-42000,'path'=>$p['path'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>$p['samesite'] ?? 'Strict']);}
  if(session_status()===PHP_SESSION_ACTIVE)session_destroy();session_id('');
}
function ruethies_csrf(): string {
  if(session_status()!==PHP_SESSION_ACTIVE)ruethies_session_start();
  if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];
}
function ruethies_csrf_ok(): bool{return is_string($_POST['csrf'] ?? null)&&is_string($_SESSION['csrf'] ?? null)&&hash_equals($_SESSION['csrf'],$_POST['csrf']);}
function security_require_reauth(): void {
  $at=$_SESSION['reauth_at'] ?? 0;
  if(!is_int($at) || $at>time() || time()-$at>=300)throw new RuethiesReauthRequired('Verifikasi keamanan diperlukan.');
}
function security_reauth(array $actor,string $pass,string $code): bool {
  security_rate_take('reauth',$actor['name']);
  $u=user_get($actor['name']);
  if(!$u || !hash_equals(security_version($u),security_version($actor)) || !password_verify($pass,$u['hash']))return false;
  if($u['role']==='owner' && $u['totp']!==''){
    $u=security_consume_totp($u['name'],security_version($u),$code);if(!$u)return false;
  }else{users_mutate(function(&$users)use($actor){security_actor($users,$actor);});}
  $_SESSION['reauth_at']=time();return true;
}
function security_new_password(string $pass): string {
  if(mb_strlen($pass)<12 || strlen($pass)>72)throw new DomainException('Kata sandi minimal 12 karakter dan maksimal 72 byte.');
  return password_hash($pass,PASSWORD_DEFAULT);
}
function security_change_password(array $actor,string $pass,string $confirm): void {
  security_require_reauth();if($pass!==$confirm)throw new DomainException('Konfirmasi kata sandi harus sama.');$hash=security_new_password($pass);
  users_mutate(function(&$users)use($actor,$hash){$i=security_actor($users,$actor);$users[$i]['hash']=$hash;$users[$i]['auth_version']=bin2hex(random_bytes(32));});
}
function security_set_totp(array $actor,string $secret,string $code): void {
  security_require_reauth();$counter=totp_counter($secret,$code);
  if($counter===null)throw new DomainException('Kode salah. Coba kode terbaru dari Authenticator.');
  users_mutate(function(&$users)use($actor,$secret,$counter){$i=security_actor($users,$actor,true);if($users[$i]['totp']!=='')throw new DomainException('OTP sudah aktif.');$users[$i]['totp']=$secret;$users[$i]['last_totp_counter']=$counter;$users[$i]['auth_version']=bin2hex(random_bytes(32));});
}
function security_disable_totp(array $actor): void {
  security_require_reauth();users_mutate(function(&$users)use($actor){$i=security_actor($users,$actor,true);$users[$i]['totp']='';$users[$i]['last_totp_counter']=-1;$users[$i]['auth_version']=bin2hex(random_bytes(32));});
}
function security_add_staff(array $actor,string $name,string $pass): void {
  security_require_reauth();if(!preg_match('/\A[a-zA-Z0-9_.-]{1,80}\z/',$name))throw new DomainException('Nama staf harus 1–80 huruf, angka, titik, garis bawah, atau tanda hubung.');$hash=security_new_password($pass);
  users_mutate(function(&$users)use($actor,$name,$hash){security_actor($users,$actor,true);foreach($users as $u)if(strcasecmp($u['name'],$name)===0)throw new DomainException('Nama sudah digunakan.');$users[]=['name'=>$name,'hash'=>$hash,'role'=>'staff','totp'=>'','auth_version'=>bin2hex(random_bytes(32)),'last_totp_counter'=>-1];});
}
function security_remove_staff(array $actor,string $name,string $version): void {
  security_require_reauth();users_mutate(function(&$users)use($actor,$name,$version){security_actor($users,$actor,true);$i=security_user_index($users,$name);if($users[$i]['role']!=='staff'||!hash_equals(security_version($users[$i]),$version))throw new DomainException('Akun staf sudah berubah. Muat ulang daftar.');array_splice($users,$i,1);});
}
