<?php
defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.');

function ruethies_log(string $actor, string $aksi, string $detail = ''): void {
  // Only explicit audit fields: never pass request bodies or authentication credentials.
  $entry=['t'=>date('c'),'ip'=>substr(ruethies_ip(),0,128),'oleh'=>mb_strcut($actor,0,256,'UTF-8'),'aksi'=>mb_strcut($aksi,0,128,'UTF-8'),'detail'=>mb_strcut($detail,0,2048,'UTF-8')];
  do {
    $line=json_encode($entry,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR)."\n";
    if(strlen($line)<=4096)break;
    $entry['detail']=mb_strcut($entry['detail'],0,(int)(strlen($entry['detail'])/2),'UTF-8');
  } while(true);
  security_lock('logs',static function()use($line){
    $f=ruethies_data_dir().'/log.jsonl';clearstatcache(true,$f);
    if(is_file($f)&&filesize($f)+strlen($line)>5*1024*1024){
      if(is_file($f.'.3')&&!unlink($f.'.3'))throw new RuntimeException('Rotasi log gagal.');
      for($i=2;$i>=0;$i--){$src=$f.($i?'.'.$i:'');if(is_file($src)&&!rename($src,$f.'.'.($i+1)))throw new RuntimeException('Rotasi log gagal.');}
    }
    $fh=@fopen($f,'ab');if(!$fh)throw new RuntimeException('Log tidak bisa ditulis.');
    try{if(!chmod($f,0600))throw new RuntimeException('Izin log tidak dapat diatur.');if(fwrite($fh,$line)!==strlen($line)||!fflush($fh))throw new RuntimeException('Penulisan log gagal.');}finally{fclose($fh);}
  });
}
function ruethies_logs(int $n=100): array {
  $n=max(0,min(100,$n));if(!$n)return [];
  return security_lock('logs',static function()use($n){
    $out=[];$budget=512*1024;$base=ruethies_data_dir().'/log.jsonl';
    for($i=0;$i<=3&&$budget>0&&count($out)<$n;$i++){
      $f=$base.($i?'.'.$i:'');if(!is_file($f))continue;
      $fh=@fopen($f,'rb');if(!$fh)throw new RuntimeException('Log tidak bisa dibaca.');
      try{$size=fstat($fh)['size'];$bytes=min($size,$budget);$offset=$size-$bytes;fseek($fh,$offset);$raw=$bytes?fread($fh,$bytes):'';$budget-=$bytes;}finally{fclose($fh);}
      if($offset>0){$newline=strpos($raw,"\n");$raw=$newline===false?'':substr($raw,$newline+1);}
      foreach(array_reverse(explode("\n",rtrim($raw,"\n")))as$line){$d=json_decode($line,true);if(is_array($d)&&is_string($d['t']??null)&&is_string($d['aksi']??null)&&is_string($d['oleh']??'')&&is_string($d['detail']??''))$out[]=$d+['oleh'=>'','detail'=>''];if(count($out)>=$n)break;}
    }
    return $out;
  });
}
function security_image_metadata(string $raw,int $maxBytes): ?string {
  if(strlen($raw)>$maxBytes)return 'Foto terlalu besar.';
  $info=@getimagesizefromstring($raw);
  if(!$info||!in_array($info['mime']??'', ['image/jpeg','image/png','image/webp'],true))return 'File harus JPG, PNG, atau WebP.';
  [$w,$h]=$info;
  if($w<2||$h<2||$w>8192||$h>8192||$w*$h>8000000)return 'Dimensi foto harus minimal 2 piksel, maksimal 8 megapiksel dan 8.192 piksel per sisi.';
  return null;
}
function security_image_decode(string $raw,int $maxBytes): array {
  if($error=security_image_metadata($raw,$maxBytes))return [null,$error];
  $im=@imagecreatefromstring($raw);
  return $im ? [$im,null] : [null,'File gambar tidak valid.'];
}
function security_upload_input(array $files): void {
  foreach($files as$file){
    if(!is_array($file)||!is_int($file['error']??null)||!is_int($file['size']??null)||!is_string($file['tmp_name']??null)||!is_string($file['name']??null))throw new DomainException('Format unggahan tidak valid.');
    if($file['size']<0 || ($file['error']===UPLOAD_ERR_OK && ($file['tmp_name']==='' || !is_file($file['tmp_name']))))throw new DomainException('Berkas unggahan tidak tersedia.');
  }
}
