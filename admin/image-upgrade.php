<?php
// CLI only, check is read-only. Generates companions, never rewrites JSON or original photos.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('RUETHIES_ADMIN',true);require __DIR__.'/config.php';require __DIR__.'/lib.php';
$mode=$argv[1]??'--check';if(!in_array($mode,['--check','--apply'],true)){fwrite(STDERR,"Use --check or --apply\n");exit(1);}
$lock=null;$backup=null;$created=[];$moved=[];
try{
 if($mode==='--apply')$lock=upload_lock();
 $menu=catalog_read_json(RUETHIES_MENU);$gallery=catalog_read_json(RUETHIES_PUBLIC.'/atmosphere.json');$photos=['images/hero-marble-cake.webp','images/menu/snackbox.jpg'];
 foreach($menu as$m){if(!is_array($m)||!is_string($m['img']??null))throw new RuntimeException('Menu tidak valid.');if($m['img']!=='')$photos[]=$m['img'];}
 foreach($gallery as$g){if(!is_array($g)||!is_string($g['img']??null))throw new RuntimeException('Galeri tidak valid.');$photos[]=$g['img'];}
 $plans=[];$seenBases=[];$root=realpath(RUETHIES_PUBLIC);
 foreach(array_unique($photos)as$photo){
  if(!preg_match('~\Aimages/(?:menu/)?[a-z0-9]+(?:-[a-z0-9]+)*\.(?:jpg|jpeg|png|webp)\z~D',$photo))throw new RuntimeException('Path foto tidak valid.');
  $baseKey=preg_replace('/\.(jpg|jpeg|png|webp)$/','',$photo);if(isset($seenBases[$baseKey]))continue;$seenBases[$baseKey]=true;
  $source=$root.'/'.$photo;$dir=dirname($source);
  if(is_link($root.'/images')||is_link($dir)||realpath($dir)!==$dir||is_link($source)||!is_file($source))throw new RuntimeException('Sumber foto tidak tersedia/aman: '.$photo);
  $raw=file_get_contents($source,false,null,0,8*1024*1024+1);if($error=security_image_metadata($raw,8*1024*1024))throw new RuntimeException($error);
  $size=getimagesize($source);$base=preg_replace('/\.(jpg|jpeg|png|webp)$/','',$source);$targets=[];$needs=false;
  foreach([320,480,640,960,1280]as$width)if($width<=$size[0]){$file=$base.'-w'.$width.'.webp';if(is_link($file)||file_exists($file)&&!is_file($file))throw new RuntimeException('Varian tidak aman.');$targets[]=$file;if(!is_file($file)||filemtime($file)<filemtime($source)||!($variant=@getimagesize($file))||$variant[0]!==$width||($variant['mime']??'')!=='image/webp')$needs=true;}
  if($needs)$plans[]=['source'=>$source,'base'=>$base,'files'=>$targets,'hash'=>hash_file('sha256',$source)];
 }
 if($mode==='--apply'&&$plans){
  $backup=ruethies_data_dir().'/backups/images-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));if(!mkdir($backup,0700,true))throw new RuntimeException('Backup gagal.');
  if(file_put_contents($backup.'/manifest.json',json_encode($plans,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES))===false||!chmod($backup.'/manifest.json',0600))throw new RuntimeException('Backup manifest gagal.');
  foreach($plans as$plan){
   if(hash_file('sha256',$plan['source'])!==$plan['hash'])throw new RuntimeException('Foto berubah saat upgrade; ulangi saat admin tidak digunakan.');
   foreach($plan['files']as$file)if(is_file($file)){$copy=$backup.'/'.count($moved).'-'.basename($file);if(!rename($file,$copy))throw new RuntimeException('Arsip varian gagal.');$moved[$file]=$copy;}
   $raw=file_get_contents($plan['source']);[$src,$error]=security_image_decode($raw,8*1024*1024);if($error)throw new RuntimeException($error);
   [$files,$error]=photo_write_variants($src,$plan['base']);unset($src);if($error)throw new RuntimeException($error);$created=array_merge($created,$files);
  }
 }
 echo json_encode(['mode'=>$mode,'photos'=>count($seenBases),'changes_needed'=>$mode==='--check'?count($plans):0,'created'=>count($created),'backup'=>$backup],JSON_UNESCAPED_SLASHES),PHP_EOL;
}catch(Throwable $e){foreach($created as$file)@unlink($file);foreach($moved as$file=>$copy)@rename($copy,$file);fwrite(STDERR,$e->getMessage()."\n");exit(1);}finally{if($lock)upload_unlock($lock);}
