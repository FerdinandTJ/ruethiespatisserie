<?php
// CLI only. Always use the content and private directory belonging to THIS checkout.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('RUETHIES_ADMIN',true);
require __DIR__.'/config.php';
require __DIR__.'/lib.php';
function upgrade_backup(string $data): string {
  $dest=$data.'/backups/security-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
  if(!mkdir($dest,0700,true))throw new RuntimeException('Backup tidak bisa dibuat.');
  // Include all private state except locks and older backups. Preserve names/paths.
  foreach(new DirectoryIterator($data)as$f){
    if(!$f->isFile()||$f->isLink()||str_ends_with($f->getFilename(),'.lock'))continue;
    if(!copy($f->getPathname(),$dest.'/'.$f->getFilename())||!chmod($dest.'/'.$f->getFilename(),0600))throw new RuntimeException('Backup data privat gagal.');
  }
  foreach(['faq.json','index.html','llms.txt']as$name){
    if(!copy(RUETHIES_PUBLIC.'/'.$name,$dest.'/public-'.$name)||!chmod($dest.'/public-'.$name,0600))throw new RuntimeException('Backup konten gagal.');
  }
  return $dest;
}
try{
  $args=array_slice($argv,1);$mode=$args[0]??'';
  if(!in_array($mode,['--check','--apply','--revoke-sessions','--recover-owner'],true)||($mode!=='--recover-owner'&&count($args)!==1)||($mode==='--recover-owner'&&(count($args)<2||count($args)>3||isset($args[2])&&$args[2]!=='--disable-2fa'))){
    throw new DomainException("Penggunaan: php admin/security-upgrade.php --check | --apply | --revoke-sessions | --recover-owner NAMA [--disable-2fa]\nPemulihan membaca kata sandi baru dari STDIN; jangan taruh kata sandi pada argumen shell.");
  }
  // Read-only preflight: never allow --check to create an empty private store.
  $outside=RUETHIES_STAGING?dirname(RUETHIES_PUBLIC,2).'/ruethies-data-staging':dirname(RUETHIES_PUBLIC).'/ruethies-data';
  $data=is_dir($outside)?$outside:(RUETHIES_STAGING?$outside:__DIR__.'/data');
  if(is_link($data)||!is_dir($data)||!is_file($data.'/users.json'))throw new RuntimeException('Direktori akun yang sudah ada tidak ditemukan. Tidak membuat akun baru.');
  // ruethies_data_dir must select exactly this directory, without falling into another store.
  if(realpath(ruethies_data_dir())!==realpath($data))throw new RuntimeException('Lokasi data privat tidak konsisten.');
  $changes=security_upgrade_users(false);
  $faq=security_read(RUETHIES_FAQ);
  if(!$faq||!array_is_list($faq))throw new RuntimeException('faq.json kosong atau rusak.');
  foreach($faq as$item)if(!is_array($item)||!is_string($item['q']??null)||!is_string($item['a']??null)||isset($item['qe'])&&!is_string($item['qe'])||isset($item['ae'])&&!is_string($item['ae']))throw new RuntimeException('Format FAQ tidak valid.');
  faq_render_schema($faq);
  foreach(['index.html'=>['FAQ:SCHEMA','FAQ:HTML'],'llms.txt'=>['FAQ']]as$file=>$blocks){
    $raw=@file_get_contents(RUETHIES_PUBLIC.'/'.$file);if($raw===false)throw new RuntimeException('Konten tidak terbaca.');
    foreach($blocks as$block){$a=strpos($raw,'<!-- '.$block.':START -->');$b=strpos($raw,'<!-- '.$block.':END -->');if($a===false||$b===false||$a>=$b)throw new RuntimeException('Penanda FAQ hilang atau rusak.');}
  }
  $marker=$data.'/security-upgrade.json';$state=security_read($marker);if($state&&!in_array($state['version']??null,[1],true))throw new RuntimeException('Versi upgrade tidak dikenal. Gunakan kode upgrade yang sesuai.');$first=($state['version']??0)!==1;
  if($mode==='--check'){
    echo "CHECK OK: ".count(users_all())." akun; $changes akun memerlukan auth_version; ".count($faq)." FAQ dari hosting.\n";
    echo $first?"Upgrade awal akan mengarsipkan log lama dan mencabut sesi admin lama.\n":"Metadata keamanan sudah tersedia; apply ulang mempertahankan versi dan counter.\n";
    exit(0);
  }
  if($mode!=='--apply'&&($first||$changes))throw new RuntimeException('Jalankan --apply sebelum pemulihan.');
  // Stop admin activity at hosting while upgrading/recovering; locks also protect concurrent writers.
  content_lock();
  if(security_read(RUETHIES_FAQ)!==$faq)throw new RuntimeException('FAQ berubah selama pemeriksaan. Jalankan ulang upgrade.');
  $backup=upgrade_backup($data);
  if($mode==='--apply'){
    security_upgrade_users(true);
    [$ok,$error]=faq_apply($faq);if(!$ok)throw new RuntimeException($error);
    if($first){
      security_lock('logs',static function()use($data,$marker){
        $state=security_read($marker);if(($state['version']??0)===1)return;
        $archive=null;$f=$data.'/log.jsonl';
        if(is_file($f)){$archive='log-before-security-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.jsonl';if(!rename($f,$data.'/'.$archive)||!chmod($data.'/'.$archive,0600))throw new RuntimeException('Arsip log gagal.');}
        security_write($marker,['version'=>1,'applied_at'=>date('c'),'legacy_log_archive'=>$archive]);
      });
    }
    echo "APPLY OK: metadata akun, FAQ dan retensi log siap. Admin perlu login ulang setelah upgrade awal.\n";
  }elseif($mode==='--revoke-sessions'){
    users_mutate(static function(&$users){foreach($users as&$u)$u['auth_version']=bin2hex(random_bytes(32));unset($u);});
    echo "Semua sesi dan challenge dicabut; password, 2FA dan counter tetap.\n";
  }else{
    $name=$args[1];$pass=rtrim((string)fgets(STDIN,1026),"\r\n");$hash=security_new_password($pass);unset($pass);
    users_mutate(static function(&$users)use($name,$hash,$args){$i=security_user_index($users,$name);if($users[$i]['role']!=='owner')throw new DomainException('Pemulihan hanya untuk akun pemilik yang masih ada.');security_version($users[$i]);$users[$i]['hash']=$hash;$users[$i]['auth_version']=bin2hex(random_bytes(32));if(($args[2]??'')==='--disable-2fa')$users[$i]['totp']='';});
    echo "Akun pemilik dipulihkan; seluruh sesinya dicabut. Akun lain dan metadata counter dipertahankan.\n";
  }
  echo "Backup privat: $backup\n";
}catch(Throwable $e){fwrite(STDERR,"GAGAL: ".$e->getMessage()."\n");exit(1);}
