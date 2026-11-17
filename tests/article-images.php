<?php
// Local, disposable image/storage fixture. No hosting data or accounts are used.
if(PHP_SAPI!=='cli')exit;
set_error_handler(static function($s,$m,$f,$l){if(error_reporting()&$s)throw new ErrorException($m,0,$s,$f,$l);return false;});
$source=dirname(__DIR__);$root=sys_get_temp_dir().'/ruethies-article-images-'.bin2hex(random_bytes(6));$public=$root.'/public_html';
function ai_test(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
function ai_remove(string $path):void{if(is_link($path)||!is_dir($path)){if(file_exists($path)||is_link($path))unlink($path);return;}foreach(scandir($path)as$name)if($name!=='.'&&$name!=='..')ai_remove($path.'/'.$name);rmdir($path);}
try{
 foreach(['admin','public-lib','images/articles']as$dir)mkdir($public.'/'.$dir,0700,true);mkdir($root.'/ruethies-data',0700);
 foreach(['admin','public-lib']as$dir)foreach(glob($source.'/'.$dir.'/*.php')as$file)copy($file,$public.'/'.$dir.'/'.basename($file));
 define('RUETHIES_ADMIN',true);require $public.'/admin/config.php';require $public.'/admin/lib.php';require $public.'/admin/articles-lib.php';
 $cover='images/articles/'.str_repeat('a',32).'.webp';$image=imagecreatetruecolor(1200,1800);
 for($y=0;$y<1800;$y++)imageline($image,0,$y,1199,$y,imagecolorallocate($image,$y%256,120,180));imagewebp($image,$public.'/'.$cover,86);unset($image);
 $hash=hash_file('sha256',$public.'/'.$cover);$now=gmdate('Y-m-d\TH:i:s\Z');$seeds=require $public.'/admin/article-seeds.php';$row=$seeds[0]+['status'=>'draft','createdAt'=>$now,'updatedAt'=>$now,'publishedAt'=>null,'createdBy'=>'Fixture','updatedBy'=>'Fixture','cover'=>$cover,'coverAlt'=>'Foto uji','lang'=>'id','translationOf'=>'','deletedAt'=>null];$state=article_empty();$state['items']=[$row];article_write($state);$before=file_get_contents(article_file());
 $run=static function($mode)use($public){$p=proc_open([PHP_BINARY,$public.'/admin/article-images.php',$mode],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($p),json_decode($out,true),$err];};
 [$code,$check]=$run('--check');ai_test($code===0&&$check['missing_before']===2&&count(glob($public.'/images/articles/*'))===1,'check finds missing thumbnails without writing');
 [$code,$apply]=$run('--apply');ai_test($code===0&&$apply['created']===2,'apply backfills both responsive variants');
 $hashes=[];foreach([480,800]as$width){$path=article_thumbnail_path($cover,$width);$info=article_image_info($path);ai_test($info===[$width,(int)($width*1.5)],'thumbnail '.$width.' preserves portrait proportions');$hashes[$width]=hash_file('sha256',$public.'/'.$path);}
 [$code,$apply]=$run('--apply');ai_test($code===0&&$apply['created']===0,'repeated apply is idempotent');foreach($hashes as$width=>$digest)ai_test(hash_file('sha256',$public.'/'.article_thumbnail_path($cover,$width))===$digest,'existing thumbnail '.$width.' is untouched');
 ai_test(hash_file('sha256',$public.'/'.$cover)===$hash&&file_get_contents(article_file())===$before,'backfill preserves original bytes and article metadata');
 $small=imagecreatetruecolor(320,240);$smallPath='images/articles/'.str_repeat('b',32).'.webp';imagewebp($small,$public.'/'.$smallPath,86);ai_test(article_make_thumbnails($small,$smallPath)===0,'small covers are never upscaled');unset($small);
 $decoded=imagecreatefromwebp($public.'/'.$cover);$new='images/articles/'.str_repeat('c',32).'.webp';imagewebp($decoded,$public.'/'.$new,86);ai_test(article_make_thumbnails($decoded,$new)===2,'upload processing reuses decoded image for variants');unset($decoded);
 article_remove_new_cover($new);ai_test(!glob($public.'/images/articles/'.str_repeat('c',32).'*'),'failed mutation cleanup removes only its new original and variants');
 $path=article_thumbnail_path($cover,480);unlink($public.'/'.$path);symlink($public.'/'.$smallPath,$public.'/'.$path);[$code,$apply]=$run('--apply');ai_test($code===1&&$apply['errors']===1&&is_link($public.'/'.$path),'backfill refuses symlink targets without overwriting');
 ai_test(article_image_info('../outside.webp')===null,'image metadata rejects traversal paths');
}finally{ai_remove($root);}
