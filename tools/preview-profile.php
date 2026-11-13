<?php
// Local-only preview. Copies public data into a disposable fixture, never private accounts.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('RUETHIES_PUBLIC_RENDER',true);
require dirname(__DIR__).'/admin/catalog.php';
$options=getopt('',['port:']);$port=$options['port']??'8872';
if(!is_string($port)||!ctype_digit($port)||(int)$port<1024||(int)$port>65535){fwrite(STDERR,"Use --port=1024..65535\n");exit(1);}
$source=dirname(__DIR__);$root=sys_get_temp_dir().'/ruethies-profile-'.bin2hex(random_bytes(6));$public=$root.'/public_html';$process=null;
function preview_remove(string $path):void{if(is_link($path)||!is_dir($path)){if(file_exists($path)||is_link($path))unlink($path);return;}foreach(scandir($path)as$name)if($name!=='.'&&$name!=='..')preview_remove($path.'/'.$name);rmdir($path);}
register_shutdown_function(static function()use(&$process,$root){if(is_resource($process)){proc_terminate($process);proc_close($process);}if(is_dir($root))preview_remove($root);});
try{
 mkdir($public.'/public-lib',0700,true);mkdir($public.'/admin',0700);
 foreach(['index.php','index.html','styles.css','profile.css','profile.js','carousel.js','faq.json','atmosphere.json','manifest.json','logo-wine.png','404.html','public-lib/seo.php','public-lib/profile.php','admin/catalog.php']as$file)if(!copy($source.'/'.$file,$public.'/'.$file))throw new RuntimeException('Unable to copy '.$file);
 if(!symlink($source.'/images',$public.'/images'))throw new RuntimeException('Unable to link public images.');
 file_put_contents($public.'/menu.json',catalog_json(catalog_metadata(catalog_read_json($source.'/menu.json'),filemtime($source.'/menu.json'))));
 $router='<?php $p=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH);if(is_string($p)&&is_file(__DIR__."/public_html".$p)&&!in_array($p,["/index.html","/sitemap.xml","/llms.txt"],true))return false;require __DIR__."/public_html/index.php";return true;';file_put_contents($root.'/router.php',$router);
 echo "Preview: http://127.0.0.1:$port/\nPublic data is copied; no production data is changed. Stop with Ctrl+C.\n";
 $process=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$public,$root.'/router.php'],[0=>STDIN,1=>STDOUT,2=>STDERR],$pipes);
 if(!is_resource($process))throw new RuntimeException('Unable to start PHP server.');
 if(function_exists('pcntl_async_signals')){pcntl_async_signals(true);pcntl_signal(SIGINT,static function()use(&$process){if(is_resource($process))proc_terminate($process);});pcntl_signal(SIGTERM,static function()use(&$process){if(is_resource($process))proc_terminate($process);});}
 do{$status=proc_get_status($process);if(!$status['running'])break;usleep(100000);}while(true);
 $exit=$status['exitcode'];proc_close($process);$process=null;exit($exit<0?0:$exit);
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
