<?php
// Additive cover backfill only. Never rewrites article JSON, originals, or existing thumbnails.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('RUETHIES_ADMIN',true);
require __DIR__.'/config.php';require __DIR__.'/lib.php';require __DIR__.'/articles-lib.php';
try{
 $mode=$argv[1]??'--check';if(count($argv)>2||!in_array($mode,['--check','--apply'],true))throw new RuntimeException('Mode tidak valid.');
 $result=article_lock($mode==='--apply',static function()use($mode){
  $state=article_read_unlocked();$covers=array_values(array_unique(array_filter(array_column($state['items'],'cover'))));$missing=0;$created=0;$errors=0;
  foreach($covers as$cover){
   try{
    $info=article_image_info($cover);if(!$info)throw new RuntimeException('Sampul tidak tersedia.');$needed=0;
    foreach([480,800]as$width)if($info[0]>$width){$path=article_thumbnail_path($cover,$width);if(is_link(RUETHIES_PUBLIC.'/'.$path))throw new RuntimeException('Thumbnail tidak valid.');if(!is_file(RUETHIES_PUBLIC.'/'.$path))$needed++;elseif(!article_image_info($path))throw new RuntimeException('Thumbnail tidak valid.');}
    $missing+=$needed;if(!$needed||$mode==='--check')continue;
    $raw=@file_get_contents(RUETHIES_PUBLIC.'/'.$cover,false,null,0,32*1024*1024+1);if($raw===false)throw new RuntimeException('Sampul tidak tersedia.');
    [$image,$error]=security_image_decode($raw,32*1024*1024);unset($raw);if($error)throw new RuntimeException('Sampul tidak valid.');
    try{$created+=article_make_thumbnails($image,$cover);}finally{unset($image);}
   }catch(Throwable $e){$errors++;fwrite(STDERR,"Satu sampul/thumbnail tidak dapat diproses; periksa gambar melalui admin.\n");}
  }
  return ['mode'=>$mode,'covers'=>count($covers),'missing_before'=>$missing,'created'=>$created,'errors'=>$errors];
 });
 echo json_encode($result,JSON_UNESCAPED_SLASHES),PHP_EOL;exit($result['errors']?1:0);
}catch(Throwable $e){fwrite(STDERR,"Pemeriksaan thumbnail gagal. Periksa mode, data artikel, dan izin berkas. Tidak ada data artikel atau gambar asli yang diubah.\n");exit(1);}
