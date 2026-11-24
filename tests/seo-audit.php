<?php
// Runs the reusable crawler against a disposable HTTP server, never a live site.
if(PHP_SAPI!=='cli')exit;
$root=sys_get_temp_dir().'/ruethies-audit-test-'.bin2hex(random_bytes(5));mkdir($root,0700);$server=null;
function audit_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
try{
 $router= <<<'ROUTER'
<?php
$p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);if(is_file(__DIR__.'/staging'))header('X-Robots-Tag: noindex, nofollow');
if($p==='/admin/'){file_put_contents(__DIR__.'/private-was-read','bad');http_response_code(403);exit;}
if($p==='/robots.txt'){header('Content-Type: text/plain');echo "User-agent: *\nDisallow: /admin/\nSitemap: https://ruethiespatisserie.com/sitemap.xml\n";exit;}
if($p==='/sitemap.xml'){header('Content-Type: application/xml');echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';foreach(['/','/guide/','/duplicate/','/orphan/','/menu/']as$url)echo '<url><loc>https://ruethiespatisserie.com'.$url.'</loc></url>';echo '</urlset>';exit;}
if($p==='/alias'){header('Location: /guide/',true,301);exit;}
if(!in_array($p,['/','/guide/','/guide/child/','/soft-error/','/duplicate/','/orphan/','/menu/'],true)){http_response_code(404);echo '<html><title>Missing</title><h1>Not here</h1></html>';exit;}
$title=$p==='/soft-error/'?'404 Not Found':(in_array($p,['/guide/','/duplicate/'],true)?'Same title':$p);
echo '<html lang="id"><head><title>'.$title.'</title><meta name="description" content="'.($title==='Same title'?'Same description':$p).'"><link rel="canonical" href="https://ruethiespatisserie.com'.$p.'"><meta name="robots" content="'.(!empty($_GET)?'noindex, follow':'index, follow').'"><meta property="og:title" content="'.$title.'"><meta property="og:description" content="Test"><meta property="og:image" content="https://ruethiespatisserie.com/test.webp"><meta name="twitter:card" content="summary_large_image"></head><body><h1>'.$title.'</h1><a href="/">Home</a><a href="'.$p.'">Self</a>';
if($p==='/')echo '<a href="/guide/">Guide</a><a href="/soft-error/">Error</a><a href="/duplicate/">Duplicate</a><a href="/alias">Alias</a><a href="/missing/">Broken</a><a href="/admin/">Private</a><a href="/menu/?kategori=cakery">Filter</a>';
if($p==='/guide/')echo '<a href="child/">Relative child</a><img src="/test.webp"><script type="application/ld+json">{"@type":"Article","inLanguage":"en"}</script>';
echo '</body></html>';
ROUTER;
 file_put_contents($root.'/router.php',$router);$socket=stream_socket_server('tcp://127.0.0.1:0');$address=stream_socket_get_name($socket,false);fclose($socket);
 $server=proc_open([PHP_BINARY,'-S',$address,$root.'/router.php'],[0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes);usleep(250000);
 $run=static function()use($root,$address){$p=proc_open([PHP_BINARY,dirname(__DIR__).'/tools/seo-audit.php','--base=http://'.$address,'--canonical-origin=https://ruethiespatisserie.com','--delay-ms=0','--output='.$root.'/report.json','--markdown='.$root.'/report.md'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);audit_check(proc_close($p)===0,'crawler finishes against local fixture');return json_decode(file_get_contents($root.'/report.json'),true);};
 $report=$run();$pages=[];foreach($report['pages']as$page)$pages[parse_url($page['url'],PHP_URL_PATH).(parse_url($page['url'],PHP_URL_QUERY)?'?'.parse_url($page['url'],PHP_URL_QUERY):'')]=$page;
 audit_check(count($report['sitemap_urls'])===5,'production canonical sitemap maps to configured staging/local origin');
 audit_check(isset($pages['/orphan/'])&&in_array('No inbound links from crawled pages (possible orphan)',$pages['/orphan/']['issues'],true),'sitemap-only orphan detected without counting its self link');
 audit_check($pages['/alias']['status']===200&&count($pages['/alias']['redirects'])===1,'redirect hops and final response retained');
 audit_check(in_array($pages['/missing/']['url'],$pages['/']['broken_links'],true),'broken internal target is reported');
 audit_check(isset($pages['/guide/child/']),'relative links resolve against trailing-slash directory');
 audit_check(in_array('Possible soft 404: error wording with HTTP 200',$pages['/soft-error/']['issues'],true),'potential soft 404 is flagged without declaring Google index status');
 audit_check($pages['/guide/']['missing_alt']===1&&in_array('HTML language differs from article schema',$pages['/guide/']['issues'],true),'missing alt and initial article language mismatch detected');
 audit_check(count($report['duplicate_metadata']['title']['Same title'])===2,'duplicate canonical page metadata reported');
 audit_check(!$pages['/menu/?kategori=cakery']['indexable'],'noindex utility is not treated as an indexable landing');
 audit_check(!is_file($root.'/private-was-read'),'admin URL is excluded, not probed');
 audit_check(str_contains(file_get_contents($root.'/report.md'),'Final status'),'human-readable report written alongside machine-readable JSON');
 file_put_contents($root.'/staging','yes');$report=$run();audit_check(count(array_filter($report['pages'],fn($p)=>$p['indexable']??false))===0,'staging X-Robots-Tag prevents indexability in report');
}finally{
 if(is_resource($server)){proc_terminate($server);proc_close($server);}
 foreach(glob($root.'/*')as$file)unlink($file);rmdir($root);
}
