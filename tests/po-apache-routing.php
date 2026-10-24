<?php
// Tes .htaccess asli dengan Apache HTTPS + CGI dummy (tanpa database/data website).
// macOS: php tests/po-apache-routing.php
// Linux: set APACHE_BINARY dan APACHE_MODULE_DIR bila berbeda.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$apache = getenv('APACHE_BINARY') ?: '/usr/sbin/httpd';
$modules = getenv('APACHE_MODULE_DIR') ?: '/usr/libexec/apache2';
if (!is_executable($apache) || !is_file($modules.'/mod_cgi.so')) { fwrite(STDERR,"Apache + mod_cgi diperlukan untuk tes routing.\n"); exit(1); }
if (!function_exists('posix_setsid') || !function_exists('pcntl_exec')) { fwrite(STDERR,"Ekstensi POSIX dan PCNTL diperlukan untuk isolasi proses Apache.\n"); exit(1); }
$tmp = sys_get_temp_dir().'/ruethies-routing-'.bin2hex(random_bytes(6));
mkdir($tmp.'/public',0700,true);
$proc = null;
function routing_check(bool $ok,string $label): void { if(!$ok)throw new RuntimeException($label);print "PASS: $label\n"; }
function routing_cleanup(string $path): void {
  if(!is_dir($path)){unlink($path);return;}
  foreach(scandir($path) as $name)if($name!=='.' && $name!=='..')routing_cleanup($path.'/'.$name);
  rmdir($path);
}
try {
  $key=openssl_pkey_new(['private_key_bits'=>2048]);
  $csr=openssl_csr_new(['commonName'=>'localhost'],$key);
  $cert=openssl_csr_sign($csr,null,$key,1);
  openssl_pkey_export($key,$keyText);openssl_x509_export($cert,$certText);
  file_put_contents($tmp.'/key.pem',$keyText);file_put_contents($tmp.'/cert.pem',$certText);
  $htaccess=file_get_contents(dirname(__DIR__).'/.htaccess');
  file_put_contents($tmp.'/public/.htaccess',$htaccess);
  file_put_contents($tmp.'/public/404.html','Not found');
  mkdir($tmp.'/public/.git');file_put_contents($tmp.'/public/.git/HEAD','PRIVATE TEST');
  file_put_contents($tmp.'/public/po.php','#!'.PHP_BINARY."\n".'<?php echo "Content-Type: application/json\r\n\r\n"; echo json_encode(["method"=>getenv("REQUEST_METHOD"),"query"=>getenv("QUERY_STRING"),"body"=>file_get_contents("php://stdin")]);');
  chmod($tmp.'/public/po.php',0700);
  $socket=stream_socket_server('tcp://127.0.0.1:0');$address=stream_socket_get_name($socket,false);fclose($socket);
  $port=substr($address,strrpos($address,':')+1);
  $config="ServerRoot \"$tmp\"\nPidFile \"$tmp/httpd.pid\"\nListen $address\nServerName localhost\n";
  foreach(['mpm_prefork','unixd','authn_core','authn_file','authz_core','authz_user','auth_basic','mime','dir','cgi','rewrite','headers','setenvif','ssl'] as $module) $config.="LoadModule {$module}_module \"$modules/mod_$module.so\"\n";
  $config.="ErrorLog \"$tmp/error.log\"\nLogLevel warn\nTypesConfig /dev/null\nDocumentRoot \"$tmp/public\"\nSSLEngine on\nSSLCertificateFile \"$tmp/cert.pem\"\nSSLCertificateKeyFile \"$tmp/key.pem\"\n<Directory \"$tmp/public\">\nAllowOverride All\nRequire all granted\nOptions +ExecCGI\nAddHandler cgi-script .php\n</Directory>\n";
  file_put_contents($tmp.'/httpd.conf',$config);
  // Apache dapat mengirim sinyal ke process group saat berhenti; pisahkan dari runner.
  $launch='posix_setsid(); pcntl_exec('.var_export($apache,true).','.var_export(['-f',$tmp.'/httpd.conf','-DFOREGROUND'],true).');';
  $proc=proc_open([PHP_BINARY,'-r',$launch],[0=>['pipe','r'],1=>['file',$tmp.'/stdout.log','a'],2=>['file',$tmp.'/stderr.log','a']],$pipes);
  $ch=curl_init();$headers=[];
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>0,CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>10,CURLOPT_RESOLVE=>["staging.ruethiespatisserie.com:$port:127.0.0.1","ruethiespatisserie.com:$port:127.0.0.1"],CURLOPT_HEADERFUNCTION=>function($ch,$line)use(&$headers){$headers[]=trim($line);return strlen($line);}]);
  $get=function(string $path,?string $body=null,string $host='staging.ruethiespatisserie.com')use($ch,$port,&$headers){
    $headers=[];curl_setopt_array($ch,[CURLOPT_URL=>"https://$host:$port$path",CURLOPT_POST=>$body!==null]);
    if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
    $text=curl_exec($ch);return [(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE),(string)$text];
  };
  for($i=0;$i<40;$i++){usleep(100000);[$status,$text]=$get('/pre-order/');if($status)break;}
  if(!$status)throw new RuntimeException('Apache could not start: '.@file_get_contents($tmp.'/stderr.log').' '.@file_get_contents($tmp.'/error.log'));
  routing_check($status===200,'Apache rewrites /pre-order/ to handler');
  foreach(['/pre-order','/pre-order/','/pre-order/status','/pre-order/status/'] as $path){
    [$status,$text]=$get($path.'?t=token%2Btest&slot=7');$body=json_decode($text,true);
    routing_check($status===200 && ($body['query']??'')==='t=token%2Btest&slot=7','query survives rewrite '.$path);
  }
  [$status,$text]=$get('/pre-order/','action=review&name=Demo%20User');$body=json_decode($text,true);
  routing_check($status===200 && $body['method']==='POST' && $body['body']==='action=review&name=Demo%20User','POST body survives real Apache rewrite');
  routing_check((bool)array_filter($headers,fn($h)=>str_contains(strtolower($h),'x-robots-tag: noindex')),'staging noindex header preserved');
  // Simulasi tambahan password protection hPanel pada .htaccess yang sama.
  file_put_contents($tmp.'/passwords','demo:'.password_hash('Demo-routing-only',PASSWORD_BCRYPT)."\n");
  file_put_contents($tmp.'/public/.htaccess',$htaccess."\nAuthType Basic\nAuthName \"Staging test\"\nAuthUserFile \"$tmp/passwords\"\nRequire valid-user\n");
  [$status]=$get('/pre-order/');routing_check($status===401,'password protection still challenges clean URL');
  curl_setopt($ch,CURLOPT_USERPWD,'demo:Demo-routing-only');
  [$status,$text]=$get('/pre-order/status/?t=private-token');
  routing_check($status===200 && (json_decode($text,true)['query']??'')==='t=private-token','authenticated clean status route works');
  [$status]=$get('/.git/HEAD');routing_check($status===403,'Git protection remains effective');
  [$status]=$get('/staging/',null,'ruethiespatisserie.com');routing_check($status===403,'production-domain staging path stays blocked');
  [$status]=$get('/pre-order/unknown/');routing_check($status===404,'unrecognized route is not sent to checkout');
} finally {
  unset($get,$ch); // Tutup koneksi keep-alive sebelum menghentikan Apache.
  foreach($pipes ?? [] as $pipe)if(is_resource($pipe))fclose($pipe);
  if(is_resource($proc)){
    proc_terminate($proc);
    for($i=0;$i<30 && proc_get_status($proc)['running'];$i++)usleep(100000);
    if(proc_get_status($proc)['running'])proc_terminate($proc,9);
    proc_close($proc);
  }
  routing_cleanup($tmp);
}
