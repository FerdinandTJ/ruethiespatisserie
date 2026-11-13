<?php
// Real .htaccess + PHP CGI over Apache HTTPS, including mod_expires/cache behavior.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$apache=getenv('APACHE_BINARY')?:'/usr/sbin/httpd';$modules=getenv('APACHE_MODULE_DIR')?:'/usr/libexec/apache2';$cgi=getenv('PHP_CGI_BINARY')?:'/opt/homebrew/bin/php-cgi';
if(!is_executable($apache)||!is_executable($cgi)){fwrite(STDERR,"Apache and PHP CGI required; set APACHE_BINARY, APACHE_MODULE_DIR and PHP_CGI_BINARY.\n");exit(1);}
define('RUETHIES_PUBLIC_RENDER',true);require dirname(__DIR__).'/admin/catalog.php';
$tmp=sys_get_temp_dir().'/ruethies-seo-apache-'.bin2hex(random_bytes(5));$public=$tmp.'/public';$source=dirname(__DIR__);mkdir($public.'/admin',0700,true);mkdir($public.'/public-lib',0700);$process=null;
function sr_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
function sr_remove(string $path):void{if(!is_dir($path)){unlink($path);return;}foreach(scandir($path)as$n)if($n!=='.'&&$n!=='..')sr_remove($path.'/'.$n);rmdir($path);}
try{
 sr_check(!str_ends_with(catalog_slug(str_repeat('a',69).' word'),'-'),'slug truncation never leaves an invalid trailing hyphen');
 mkdir($public.'/fonts',0700);copy($source.'/fonts/cormorant-garamond-400-latin.woff2',$public.'/fonts/cormorant-garamond-400-latin.woff2');
 foreach(['.htaccess','index.html','404.html','faq.json','atmosphere.json','admin/catalog.php','public-lib/seo.php','public-lib/profile.php']as$file)copy($source.'/'.$file,$public.'/'.$file);
 file_put_contents($public.'/menu.json',json_encode(catalog_metadata(catalog_read_json($source.'/menu.json'),filemtime($source.'/menu.json'))));
 file_put_contents($public.'/index.php','#!/usr/bin/env -S '.$cgi.' -d cgi.force_redirect=0'."\n".file_get_contents($source.'/index.php'));chmod($public.'/index.php',0700);
 file_put_contents($public.'/sitemap.xml','STATIC STALE SITEMAP');file_put_contents($public.'/llms.txt','STATIC STALE TEXT');
 $key=openssl_pkey_new(['private_key_bits'=>2048]);$csr=openssl_csr_new(['commonName'=>'localhost'],$key);$cert=openssl_csr_sign($csr,null,$key,1);openssl_pkey_export($key,$keyText);openssl_x509_export($cert,$certText);file_put_contents($tmp.'/key.pem',$keyText);file_put_contents($tmp.'/cert.pem',$certText);
 $socket=stream_socket_server('tcp://127.0.0.1:0');$address=stream_socket_get_name($socket,false);fclose($socket);$port=substr($address,strrpos($address,':')+1);
 $config="ServerRoot \"$tmp\"\nPidFile \"$tmp/pid\"\nListen $address\nServerName localhost\n";
 foreach(['mpm_prefork','unixd','authn_core','authz_core','mime','dir','cgi','rewrite','headers','setenvif','ssl','expires']as$module)$config.="LoadModule {$module}_module \"$modules/mod_$module.so\"\n";
 $config.="ErrorLog \"$tmp/error.log\"\nLogLevel warn\nTypesConfig /dev/null\nDocumentRoot \"$public\"\nSSLEngine on\nSSLCertificateFile \"$tmp/cert.pem\"\nSSLCertificateKeyFile \"$tmp/key.pem\"\n<Directory \"$public\">\nAllowOverride All\nRequire all granted\nOptions +ExecCGI\nAddHandler cgi-script .php\n</Directory>\n";
 file_put_contents($tmp.'/httpd.conf',$config);$launch='posix_setsid();pcntl_exec('.var_export($apache,true).','.var_export(['-f',$tmp.'/httpd.conf','-DFOREGROUND'],true).');';$process=proc_open([PHP_BINARY,'-r',$launch],[0=>['pipe','r'],1=>['file',$tmp.'/stdout','a'],2=>['file',$tmp.'/stderr','a']],$pipes);
 $headers=[];$curl=curl_init();curl_setopt_array($curl,[CURLOPT_PATH_AS_IS=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>0,CURLOPT_PROXY=>'',CURLOPT_RESOLVE=>["ruethiespatisserie.com:$port:127.0.0.1","staging.ruethiespatisserie.com:$port:127.0.0.1"],CURLOPT_HEADERFUNCTION=>function($ch,$line)use(&$headers){$pair=explode(':',$line,2);if(count($pair)===2)$headers[strtolower(trim($pair[0]))][]=trim($pair[1]);return strlen($line);}]);
 $get=function(string $path,string $host='ruethiespatisserie.com',array $extra=[])use($curl,$port,&$headers):array{$headers=[];curl_setopt_array($curl,[CURLOPT_URL=>"https://$host:$port$path",CURLOPT_HTTPHEADER=>$extra]);$body=curl_exec($curl);return[(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),(string)$body,$headers];};
 for($i=0;$i<40;$i++){usleep(100000);[$code,$body,$h]=$get('/');if($code)break;}
 if($code!==200)throw new RuntimeException('Apache/PHP CGI did not render: '.$body.' '.file_get_contents($tmp.'/error.log'));
 sr_check(str_contains($body,'id="profile-data"')&&str_contains($body,'href="/snack-box-cikarang/"'),'real Apache root rewrite executes PHP renderer');
 [$fontCode,$fontBody,$fontHeaders]=$get('/fonts/cormorant-garamond-400-latin.woff2');sr_check($fontCode===200&&str_starts_with($fontHeaders['content-type'][0]??'','font/woff2')&&str_contains(implode(',',$fontHeaders['cache-control']??[]),'max-age=2592000'),'local WOFF2 has explicit MIME type and versioned-asset retention');
 [$code,$body,$h]=$get('/');
 $cache=implode(',',$h['cache-control']??[]);sr_check(str_contains($cache,'max-age=0')&&!str_contains($cache,'max-age=600')&&str_contains($cache,'must-revalidate'),'mod_expires does not override HTML revalidation');
 $etag=$h['etag'][0];[$code,$body]=$get('/','ruethiespatisserie.com',['If-None-Match: '.$etag]);sr_check($code===304&&$body==='','real Apache ETag returns 304');
 foreach(['/snack-box-cikarang/','/menu/brownies-cokelat/']as$path){[$code,$body]=$get($path);sr_check($code===200&&str_contains($body,'class="rp-product-intro"'),'real Apache product route renders: '.$path);}
 foreach(['/menu/','/tentang-kami/','/hubungi-kami/','/cara-pesan/'] as $path){[$code,$body]=$get($path);sr_check($code===200&&str_contains($body,'id="profile-data"'),'real Apache profile route renders: '.$path);}
 foreach(['/menu'=>'https://ruethiespatisserie.com/menu/','/tentang-kami'=>'https://ruethiespatisserie.com/tentang-kami/','/hubungi-kami'=>'https://ruethiespatisserie.com/hubungi-kami/','/cara-pesan'=>'https://ruethiespatisserie.com/cara-pesan/','/index.html'=>'https://ruethiespatisserie.com/','/index.php'=>'https://ruethiespatisserie.com/','/menu/snackbox/'=>'https://ruethiespatisserie.com/snack-box-cikarang/','/snack-box-cikarang'=>'https://ruethiespatisserie.com/snack-box-cikarang/','/menu/pastel'=>'https://ruethiespatisserie.com/menu/pastel/']as$from=>$to){[$code,$body,$h]=$get($from);sr_check($code===301&&($h['location'][0]??'')===$to,'real Apache redirects once to canonical: '.$from);}
 [$code,$body]=$get('/sitemap.xml');sr_check($code===200&&str_contains($body,'<lastmod>')&&!str_contains($body,'STATIC'),'sitemap route supersedes stale tracked XML');
 [$code,$body]=$get('/llms.txt');sr_check($code===200&&str_contains($body,'/snack-box-cikarang/')&&!str_contains($body,'STATIC'),'text summary route supersedes stale tracked text');
 [$code,$body,$h]=$get('/snack-box-cikarang/','staging.ruethiespatisserie.com');sr_check($code===200&&str_contains(implode(',',$h['x-robots-tag']??[]),'noindex'),'new pages retain staging noindex');
 foreach(['/staging/','/%73taging/','/st%61ging/']as$path){[$code]=$get($path);sr_check(in_array($code,[403,404],true),'encoded staging path remains blocked: '.$path);}
 file_put_contents($public.'/menu.json','{invalid');[$code,$body,$h]=$get('/');sr_check($code===503&&str_contains(implode(',', $h['cache-control']??[]),'no-store'),'real Apache does not cache a broken catalogue as a successful page');
 [$code]=$get('/menu/%2e%2e/admin/');sr_check(in_array($code,[400,403,404],true),'encoded traversal never renders a product');
}finally{
 unset($get,$curl);foreach($pipes??[]as$pipe)if(is_resource($pipe))fclose($pipe);
 if(is_resource($process)){proc_terminate($process);for($i=0;$i<30&&proc_get_status($process)['running'];$i++)usleep(100000);if(proc_get_status($process)['running'])proc_terminate($process,9);proc_close($process);}
 sr_remove($tmp);
}
