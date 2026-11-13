<?php
// CLI fixtures only: no actual hosting files/accounts/network.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('RUETHIES_ADMIN',true);$tmp=sys_get_temp_dir().'/ruethies-variants-'.bin2hex(random_bytes(6));define('RUETHIES_PUBLIC',$tmp.'/public_html');define('RUETHIES_MENU',RUETHIES_PUBLIC.'/menu.json');define('RUETHIES_IMGDIR',RUETHIES_PUBLIC.'/images/menu');
function ruethies_data_dir():string{global$tmp;return $tmp.'/ruethies-data';}
require dirname(__DIR__).'/admin/lib.php';
function iv_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
function iv_remove(string $p):void{if(!is_dir($p)||is_link($p)){unlink($p);return;}foreach(scandir($p)as$n)if($n!=='.'&&$n!=='..')iv_remove($p.'/'.$n);rmdir($p);}
try{
 mkdir(RUETHIES_IMGDIR,0700,true);mkdir(RUETHIES_PUBLIC.'/admin',0700);mkdir(ruethies_data_dir(),0700);
 $source=dirname(__DIR__);foreach(['config.php','lib.php','catalog.php','security.php','security-resources.php','image-upgrade.php']as$f)copy($source.'/admin/'.$f,RUETHIES_PUBLIC.'/admin/'.$f);
 $image=imagecreatetruecolor(700,500);imagefill($image,0,0,imagecolorallocate($image,120,60,20));imagewebp($image,RUETHIES_PUBLIC.'/images/hero-marble-cake.webp',85);imagejpeg($image,RUETHIES_IMGDIR.'/snackbox.jpg',85);unset($image);
 foreach(['index.html','styles.css','app.js','llms.txt']as$f)file_put_contents(RUETHIES_PUBLIC.'/'.$f,'');
 file_put_contents(RUETHIES_MENU,json_encode([['img'=>'images/menu/snackbox.jpg']]));file_put_contents(RUETHIES_PUBLIC.'/atmosphere.json',json_encode([['img'=>'images/menu/snackbox.jpg']]));file_put_contents(RUETHIES_PUBLIC.'/faq.json','[]');
 $paths=[RUETHIES_MENU,RUETHIES_PUBLIC.'/atmosphere.json',RUETHIES_IMGDIR.'/snackbox.jpg',RUETHIES_PUBLIC.'/images/hero-marble-cake.webp'];$hashes=array_map(fn($p)=>hash_file('sha256',$p),$paths);
 $run=function(string $mode):array{$p=proc_open([PHP_BINARY,RUETHIES_PUBLIC.'/admin/image-upgrade.php',$mode],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return[proc_close($p),json_decode($out,true),$err];};
 [$code,$result]=$run('--check');iv_check($code===0&&$result['changes_needed']===2&&!glob(RUETHIES_IMGDIR.'/*-w*.webp'),'check is read-only and detects referenced photos without metadata migration');
 [$code,$result,$err]=$run('--apply');iv_check($code===0&&$result['created']===6&&is_file($result['backup'].'/manifest.json'),'apply generates variants with a private backup manifest');
 foreach([320,480,640]as$w){$p=RUETHIES_IMGDIR.'/snackbox-w'.$w.'.webp';$m=getimagesize($p);iv_check($m[0]===$w&&$m[1]===(int)round(500*$w/700)&&$m['mime']==='image/webp','variant preserves aspect ratio: '.$w);}
 iv_check(!is_file(RUETHIES_IMGDIR.'/snackbox-w960.webp'),'variants never upscale smaller sources');
 [$code,$result]=$run('--apply');iv_check($code===0&&$result['changes_needed']===0&&$result['backup']===null,'repeated apply is idempotent');
 iv_check(array_map(fn($p)=>hash_file('sha256',$p),$paths)===$hashes,'JSON and original photos are unchanged');
 $image=imagecreatetruecolor(900,600);ob_start();imagejpeg($image);$raw=ob_get_clean();unset($image);[$uploaded,$error]=photo_upload_raw($raw,'Upload test');iv_check($uploaded!==null&&$error===null,'normal upload still succeeds');$base=preg_replace('/\.jpg$/','',$uploaded);iv_check(is_file(RUETHIES_PUBLIC.'/'.$base.'-w320.webp')&&is_file(RUETHIES_PUBLIC.'/'.$base.'-full.webp'),'new upload creates small companions while preserving full detail image');
 file_put_contents(RUETHIES_PUBLIC.'/atmosphere.json','{broken');[$code]=$run('--apply');iv_check($code!==0,'corrupt hosting data fails before modifying companions');file_put_contents(RUETHIES_PUBLIC.'/atmosphere.json','[]');
 $outside=$tmp.'/outside.webp';file_put_contents($outside,'keep');unlink(RUETHIES_IMGDIR.'/snackbox-w320.webp');symlink($outside,RUETHIES_IMGDIR.'/snackbox-w320.webp');[$code]=$run('--apply');iv_check($code!==0&&file_get_contents($outside)==='keep','symlink variants are rejected without touching their targets');
}finally{iv_remove($tmp);}
