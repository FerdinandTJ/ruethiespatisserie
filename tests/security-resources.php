<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=sys_get_temp_dir().'/ruethies-resources-'.bin2hex(random_bytes(6));mkdir($root,0700);mkdir($root.'/images/menu',0700,true);
define('RUETHIES_ADMIN',true);define('RUETHIES_PUBLIC',$root);define('RUETHIES_IMGDIR',$root.'/images/menu');
function ruethies_data_dir():string{global $root;return $root;}
require dirname(__DIR__).'/admin/lib.php';
function check_resource(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
function remove_resource(string $p):void{if(!is_dir($p)){unlink($p);return;}foreach(scandir($p)as$n)if($n!=='.'&&$n!=='..')remove_resource($p.'/'.$n);rmdir($p);}
try{
 ruethies_log(str_repeat('pemilik',500),'uji',str_repeat("\0\"é<",5000));$line=file_get_contents($root.'/log.jsonl');check_resource(strlen($line)<=4096&&is_array(json_decode($line,true)),'log entries remain valid JSON within 4 KiB');
 file_put_contents($root.'/log.jsonl',str_repeat(' ',5*1024*1024-200)."\n");
 $jobs=[];for($i=0;$i<8;$i++){$code='define("RUETHIES_ADMIN",true);define("RUETHIES_PUBLIC",'.var_export($root,true).');function ruethies_data_dir():string{return '.var_export($root,true).';}require '.var_export(dirname(__DIR__).'/admin/lib.php',true).';for($i=0;$i<30;$i++)ruethies_log("worker-'.$i.'","parallel",(string)$i);';$p=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$jobs[]=[$p,$pipes];}
 foreach($jobs as[$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);if($err!=='')fwrite(STDERR,$err);check_resource($exit===0&&$err==='','parallel log worker completes');}
 $parallel=0;foreach(glob($root.'/log.jsonl*')as$f)foreach(file($f)as$line){$row=json_decode($line,true);if(($row['aksi']??'')==='parallel')$parallel++;}
 check_resource($parallel===240&&is_file($root.'/log.jsonl.1'),'concurrent appends survive rotation without losing entries');
 for($i=0;$i<5;$i++){$fh=fopen($root.'/log.jsonl','ab');ftruncate($fh,5*1024*1024);fclose($fh);ruethies_log('owner','rotate',(string)$i);}
 check_resource(is_file($root.'/log.jsonl.3')&&!is_file($root.'/log.jsonl.4')&&filesize($root.'/log.jsonl')<4096,'log rotation retains three files and bounds active file');
 $rows=ruethies_logs(999);check_resource(count($rows)<=100&&$rows[0]['detail']==='4','bounded tail reader returns newest entries first');
 for($i=0;$i<150;$i++)ruethies_log('owner','recent',(string)$i);check_resource(count(ruethies_logs())===100&&ruethies_logs()[0]['detail']==='149','dashboard returns at most the latest 100 entries');
 $fh=fopen($root.'/log.jsonl','wb');ftruncate($fh,128*1024*1024);fseek($fh,0,SEEK_END);fwrite($fh,"\n".json_encode(['t'=>date('c'),'aksi'=>'large-tail'])."\n");fclose($fh);
 $code='ini_set("memory_limit","16M");define("RUETHIES_ADMIN",true);define("RUETHIES_PUBLIC",'.var_export($root,true).');function ruethies_data_dir():string{return '.var_export($root,true).';}require '.var_export(dirname(__DIR__).'/admin/lib.php',true).';$rows=ruethies_logs();echo $rows[0]["aksi"];';
 $p=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check_resource(proc_close($p)===0&&$out==='large-tail'&&$err==='','128 MiB log can be tailed under 16 MiB memory limit');
 // Header-only PNG: metadata rejection is independent of the image decoder.
 $pngHeader=static function($w,$h){$data=pack('NNCCCCC',$w,$h,8,2,0,0,0);return "\x89PNG\r\n\x1a\n".pack('N',13).'IHDR'.$data.pack('N',crc32('IHDR'.$data));};
 foreach([[8193,2],[3000,3000],[1,2]]as[$w,$h])check_resource(str_contains(security_image_metadata($pngHeader($w,$h),10000)??'','Dimensi'),'oversized/undersized dimensions rejected before decoding '.$w.'x'.$h);
 $im=imagecreatetruecolor(32,24);ob_start();imagepng($im);$raw=ob_get_clean();unset($im);
 check_resource(security_image_metadata($raw,10000)===null,'normal PNG metadata accepted');
 $_POST['foto_b64']='data:image/png;base64,'.base64_encode($raw);[$decoded,$err]=b64_image('foto_b64',3*1024*1024);[$path,$err2]=photo_upload_raw($decoded,'gambar-uji');
 check_resource($err===null&&$err2===null&&getimagesize($root.'/'.$path)[0]===640,'base64 upload is re-encoded into a menu image');
 [$small,$large,$err]=galeri_upload_raw($raw,'galeri-uji');check_resource(!$err&&getimagesize($root.'/'.$small)[0]===640&&getimagesize($root.'/'.$large)[0]===1200,'gallery re-encoding creates both sizes');
 for($i=0;$i<20;$i++)security_rate_take('upload','owner',20,false);security_rate_reset('owner');
 try{security_rate_take('upload','owner',20,false);throw new LogicException('upload limit bypass');}catch(RuethiesRateLimit $e){check_resource(true,'21st upload rejected and login reset does not clear upload quota');}
 try{security_upload_input(['foto'=>['name'=>['array']]]);throw new LogicException('malformed file accepted');}catch(DomainException $e){check_resource(true,'malformed multipart metadata rejected');}
}finally{remove_resource($root);}
