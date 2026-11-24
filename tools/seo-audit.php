<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$options=getopt('',['base:','output:','max-pages:','delay-ms:','seed:','canonical-origin:','markdown:']);
$base=rtrim($options['base']??'https://ruethiespatisserie.com','/');$origin=parse_url($base);if(!in_array($origin['scheme']??'',['http','https'],true)||empty($origin['host'])||isset($origin['user'])||isset($origin['pass'])||!empty($origin['query'])||!empty($origin['fragment'])||!empty($origin['path']))exit("Use an origin such as https://ruethiespatisserie.com\n");
$canonicalBase=rtrim($options['canonical-origin']??$base,'/');$canonicalParts=parse_url($canonicalBase);if(!in_array($canonicalParts['scheme']??'',['http','https'],true)||empty($canonicalParts['host'])||isset($canonicalParts['user'])||isset($canonicalParts['pass'])||!empty($canonicalParts['path'])||!empty($canonicalParts['query'])||!empty($canonicalParts['fragment']))exit("Invalid canonical origin\n");
$max=max(1,min(300,(int)($options['max-pages']??150)));$delay=max(0,min(3000,(int)($options['delay-ms']??200)));$output=$options['output']??sys_get_temp_dir().'/ruethies-seo-audit.json';$queue=[];$seen=[];$pages=[];$map=[];$excluded=[];
function absolute_url(string $href,string $from):?string{
 $href=html_entity_decode(trim($href),ENT_QUOTES|ENT_HTML5,'UTF-8');if($href===''||$href[0]==='#'||preg_match('~^(?:mailto|tel|javascript|data):~i',$href))return null;
 $b=parse_url($from);$o=$b['scheme'].'://'.$b['host'].(isset($b['port'])?':'.$b['port']:'');
 if(str_starts_with($href,'//'))$href=$b['scheme'].':'.$href;
 elseif(!preg_match('~^https?://~i',$href))$href=$o.(str_starts_with($href,'/')?$href:(str_starts_with($href,'?')?($b['path']??'/').$href:(str_ends_with($b['path']??'/','/')?($b['path']??'/'):dirname($b['path']??'/').'/').$href));
 $p=parse_url($href);if(!$p||!in_array($p['scheme']??'',['http','https'],true)||isset($p['user'])||isset($p['pass']))return null;
 $parts=[];foreach(explode('/',$p['path']??'/')as$s){if($s===''||$s==='.')continue;if($s==='..')array_pop($parts);else $parts[]=$s;}$path='/'.implode('/',$parts);if(str_ends_with($p['path']??'/','/')&&$path!=='/')$path.='/';
 return strtolower($p['scheme']).'://'.strtolower($p['host']).(isset($p['port'])?':'.$p['port']:'').$path.(isset($p['query'])?'?'.$p['query']:'');
}
function internal_url(string $u):bool{global $origin;$p=parse_url($u);return strtolower($p['host']??'')===strtolower($origin['host'])&&($p['port']??null)===($origin['port']??null);}
function sitemap_target(string $url):?string {global $canonicalBase,$base;if(internal_url($url))return $url;if(str_starts_with($url,$canonicalBase.'/'))return $base.substr($url,strlen($canonicalBase));return null;}
function allowed_page(string $u):bool{
 $p=parse_url($u);$path=$p['path']??'/';if(preg_match('~^/(?:admin|staging)(?:/|$)|^/(?:midtrans-notify\.php|pre-order/(?:status|return))(?:/|$)~i',$path))return false;
 if(preg_match('~\.(?:json|css|js|xml|txt|png|jpe?g|webp|svg|ico|pdf|woff2?)$~i',$path))return false;
 parse_str($p['query']??'',$q);foreach($q as$key=>$v)if(!in_array($key,['kategori','page','q'],true)||!is_string($v)||strlen($v)>100)return false;
 if(isset($q['page'])&&(!ctype_digit($q['page'])||(int)$q['page']>10))return false;return true;
}
function fetch_url(string $url):array{
 $chain=[];$initial=$url;$start=microtime(true);
 for($i=0;$i<7;$i++){
  $headers=[];$body='';$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_USERAGENT=>'RuethiesReadOnlySEOAudit/1.0',CURLOPT_ENCODING=>'',CURLOPT_PROTOCOLS_STR=>'http,https',CURLOPT_HEADERFUNCTION=>static function($c,$line)use(&$headers){$p=explode(':',$line,2);if(count($p)===2)$headers[strtolower(trim($p[0]))]=trim($p[1]);return strlen($line);},CURLOPT_WRITEFUNCTION=>static function($c,$data)use(&$body){if(strlen($body)+strlen($data)>3*1024*1024)return 0;$body.=$data;return strlen($data);}]);curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);$type=$headers['content-type']??'';
  if($status>=300&&$status<400&&isset($headers['location'])){$next=absolute_url($headers['location'],$url);$chain[]=['url'=>$url,'status'=>$status,'location'=>$next];if(!$next||!internal_url($next)||in_array($next,array_column($chain,'url'),true))return ['url'=>$initial,'final_url'=>$url,'status'=>$status,'headers'=>$headers,'body'=>$body,'redirects'=>$chain,'error'=>'Redirect outside configured host or loop','elapsed_ms'=>round((microtime(true)-$start)*1000)];$url=$next;continue;}
  return ['url'=>$initial,'final_url'=>$url,'status'=>$status,'headers'=>$headers,'body'=>$body,'redirects'=>$chain,'error'=>$error?:null,'elapsed_ms'=>round((microtime(true)-$start)*1000)];
 }
 return ['url'=>$initial,'final_url'=>$url,'status'=>0,'headers'=>[],'body'=>'','redirects'=>$chain,'error'=>'Too many redirects','elapsed_ms'=>round((microtime(true)-$start)*1000)];
}
function parse_page(array $response):array{
 $doc=new DOMDocument();libxml_use_internal_errors(true);$doc->loadHTML('<?xml encoding="utf-8" ?>'.$response['body']);libxml_clear_errors();$x=new DOMXPath($doc);
 $one=static function($query,$attr=null)use($x){$n=$x->query($query)->item(0);return $n?trim($attr?$n->getAttribute($attr):$n->textContent):null;};
 $meta=static fn($name)=>$one('//meta[@name="'.$name.'" or @property="'.$name.'"]','content');$links=[];
 foreach($x->query('//a[@href]')as$n){$u=absolute_url($n->getAttribute('href'),$response['final_url']);if($u&&internal_url($u))$links[$u]=true;}
 $h1=[];foreach($x->query('//h1')as$n)$h1[]=trim($n->textContent);$schema=[];$schemaErrors=0;$articleLanguages=[];
 $walk=function($v)use(&$walk,&$schema,&$articleLanguages){if(!is_array($v))return;if(in_array('Article',(array)($v['@type']??[]),true)&&isset($v['inLanguage']))$articleLanguages[]=$v['inLanguage'];if(isset($v['@type']))foreach((array)$v['@type']as$t)$schema[$t]=true;foreach($v as$v2)if(is_array($v2))$walk($v2);};foreach($x->query('//script[@type="application/ld+json"]')as$n){$v=json_decode($n->textContent,true);if(json_last_error())$schemaErrors++;else $walk($v);}
 $lang=$one('//html','lang');$robots=$meta('robots');$headerRobots=$response['headers']['x-robots-tag']??'';$noindex=preg_match('/\bnoindex\b/i',($robots??'').' '.$headerRobots)===1;
 $alternates=[];foreach($x->query('//link[@rel="alternate" and @hreflang]')as$n)$alternates[$n->getAttribute('hreflang')]=$n->getAttribute('href');
 $headings=[];foreach($x->query('//h1|//h2|//h3')as$n)$headings[]=['level'=>(int)substr($n->tagName,1),'text'=>trim($n->textContent)];
 return ['title'=>$one('//title'),'description'=>$meta('description'),'canonical'=>$one('//link[@rel="canonical"]','href'),'robots'=>$robots,'header_robots'=>$headerRobots,'lang'=>$lang,'h1'=>$h1,'headings'=>$headings,'schema_types'=>array_keys($schema),'schema_errors'=>$schemaErrors,'article_languages'=>$articleLanguages,'native_translation_links'=>$x->query('//a[@hreflang]')->length,'internal_links'=>array_keys($links),'missing_alt'=>$x->query('//img[not(@alt)]')->length,'image_count'=>$x->query('//img')->length,'hreflang'=>$alternates,'og_title'=>$meta('og:title'),'og_description'=>$meta('og:description'),'og_url'=>$meta('og:url'),'og_image'=>$meta('og:image'),'og_type'=>$meta('og:type'),'twitter_card'=>$meta('twitter:card'),'indexable'=>$response['status']===200&&!$noindex];
}
foreach(['/','/menu/','/snack-box-cikarang/','/tentang-kami/','/hubungi-kami/','/cara-pesan/','/artikel/']as$p)$queue[$base.$p]='navigation seed';
$seeds=$options['seed']??[];if(!is_array($seeds))$seeds=[$seeds];foreach($seeds as$s){$u=absolute_url($s,$base.'/');if($u&&internal_url($u)&&allowed_page($u))$queue[$u]='diagnostic seed';}
$robots=fetch_url($base.'/robots.txt');$sitemaps=[$base.'/sitemap.xml'];if(preg_match_all('/^Sitemap:\s*(\S+)/mi',$robots['body'],$m))foreach($m[1]as$u)if($u=sitemap_target($u))$sitemaps[]=$u;
$sitemapResponses=[];$sitemapSeen=[];while($sitemaps&&count($sitemapSeen)<5){$sm=array_shift($sitemaps);if(isset($sitemapSeen[$sm]))continue;$sitemapSeen[$sm]=true;$res=fetch_url($sm);$sitemapResponses[]=['url'=>$sm,'status'=>$res['status'],'error'=>$res['error']];libxml_use_internal_errors(true);$xml=simplexml_load_string($res['body'],SimpleXMLElement::class,LIBXML_NONET);if($xml!==false){foreach($xml->xpath('//*[local-name()="loc"]')as$loc){$u=(string)$loc;if(!($u=sitemap_target($u)))continue;if(str_ends_with(parse_url($u,PHP_URL_PATH)??'','.xml'))$sitemaps[]=$u;else{$map[$u]=true;if(allowed_page($u))$queue[$u]='sitemap';}}}libxml_clear_errors();usleep($delay*1000);}
while($queue&&count($pages)<$max){$url=array_key_first($queue);$via=$queue[$url];unset($queue[$url]);if(isset($seen[$url]))continue;$seen[$url]=true;$res=fetch_url($url);$row=['url'=>$url,'final_url'=>$res['final_url'],'status'=>$res['status'],'redirects'=>$res['redirects'],'error'=>$res['error'],'elapsed_ms'=>$res['elapsed_ms'],'discovered_via'=>$via,'sitemap'=>isset($map[$url])];
 if(str_contains(strtolower($res['headers']['content-type']??''),'text/html')&&$res['body']!==''){
  $row+=parse_page($res);foreach($row['internal_links']as$u){if(allowed_page($u)){if(!isset($seen[$u])&&!isset($queue[$u]))$queue[$u]='link';}else $excluded[$u]=true;}
 }
 $pages[]=$row;fwrite(STDERR,$row['status'].' '.$url."\n");usleep($delay*1000);
}
$byUrl=array_column($pages,null,'url');$inbound=[];foreach($pages as$p)foreach($p['internal_links']??[]as$l)if($l!==$p['final_url'])$inbound[$l][]= $p['url'];$duplicates=[];
foreach($pages as&$p){$issues=[];if($p['error'])$issues[]=$p['error'];if(count($p['redirects'])>1)$issues[]='Multiple redirect hops';if(isset($p['h1'])){
 if($p['status']===200&&preg_match('~(?:\b404\b|not found|halaman ini tidak ada|halaman tidak ditemukan)~iu',($p['title']??'').' '.implode(' ',$p['h1'])))$issues[]='Possible soft 404: error wording with HTTP 200';
 if(count($p['h1'])!==1)$issues[]='Expected one H1';foreach(($p['status']===200?['title','description','canonical','og_title','og_description','og_image','twitter_card']:['title'])as$f)if(empty($p[$f]))$issues[]='Missing '.$f;
 if($p['status']===200&&$p['canonical']&&$p['canonical']!==$canonicalBase.substr($p['url'],strlen($base))&&!str_contains($p['url'],'?')&&!$p['redirects'])$issues[]='Canonical differs from expected production URL';
 if(($p['article_languages']??[])&&!in_array($p['lang'],$p['article_languages'],true))$issues[]='HTML language differs from article schema';
 if(($p['native_translation_links']??0)&&!$p['hreflang'])$issues[]='Linked translation lacks head hreflang';
 foreach($p['hreflang']as$lang=>$target){$local=sitemap_target($target);$other=$local?($byUrl[$local]??null):null;if($other&&($other['status']!==200||($other['hreflang'][$p['lang']]??null)!==$p['canonical']))$issues[]='Hreflang target is unavailable or not reciprocal';}
 if($p['missing_alt'])$issues[]='Images missing alt';if($p['schema_errors'])$issues[]='Invalid JSON-LD';
 if($p['indexable']&&!$p['redirects']){foreach(['title','description']as$f)$duplicates[$f][$p[$f]??''][]=$p['url'];if(!$p['sitemap'])$issues[]='Indexable URL absent from sitemap';if($p['url']!==$base.'/'&&empty($inbound[$p['url']]))$issues[]='No inbound links from crawled pages (possible orphan)';}
 if($p['canonical']&&internal_url($p['canonical'])&&isset($byUrl[$p['canonical']])&&$byUrl[$p['canonical']]['status']!==200)$issues[]='Canonical does not return 200';
 $p['broken_links']=array_values(array_filter($p['internal_links'],fn($u)=>isset($byUrl[$u])&&$byUrl[$u]['status']>=400));if($p['broken_links'])$issues[]='Broken internal links';
 }$p['issues']=$issues;$p['inbound_count']=count($inbound[$p['url']]??[]);
}unset($p);
foreach($duplicates as$f=>&$values)$values=array_filter($values,fn($urls)=>count($urls)>1);unset($values);
$report=['base'=>$base,'canonical_origin'=>$canonicalBase,'generated_at'=>gmdate('c'),'robots'=>['status'=>$robots['status'],'text'=>$robots['body']],'sitemaps'=>$sitemapResponses,'sitemap_urls'=>array_keys($map),'pages'=>$pages,'duplicate_metadata'=>$duplicates,'excluded_links'=>array_keys($excluded),'truncated'=>count($queue)>0];
if(file_put_contents($output,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n")===false)exit(1);echo 'Report: '.$output.' ('.count($pages)." pages)\n";

if(isset($options['markdown'])){
 $escape=static fn($v)=>str_replace(["|","\n","\r"],[" / "," "," "],(string)$v);
 $md="# SEO crawl report\n\nBase: ".$base."\n\nGenerated: ".$report['generated_at']."\n\nThis is HTTP/HTML eligibility, not Google's index status or field Core Web Vitals.\n\n| URL | Final status | Title | Description | Canonical | Robots / header | H1 | Schema types | Sitemap | Issues |\n|---|---:|---|---|---|---|---|---|---|---|\n";
 foreach($pages as$p)$md.='| '.implode(' | ',array_map($escape,[$p['url'],$p['status'],$p['title']??'',$p['description']??'',$p['canonical']??'',($p['robots']??'').' / '.($p['header_robots']??''),implode('; ',$p['h1']??[]),implode(', ',$p['schema_types']??[]),$p['sitemap']?'yes':'no',implode('; ',$p['issues'])]))." |\n";
 if(file_put_contents($options['markdown'],$md)===false)exit(1);
}
