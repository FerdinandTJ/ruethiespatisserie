<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
function seo_check(bool $ok,string $label): void {
  if (!$ok) throw new RuntimeException($label);
  echo "PASS: $label\n";
}
function seo_document(string $html): DOMXPath {
  $doc = new DOMDocument();
  libxml_use_internal_errors(true); $doc->loadHTML($html); libxml_clear_errors();
  return new DOMXPath($doc);
}
function seo_schemas(DOMXPath $xpath): array {
  $types = [];
  foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
    $schema = json_decode($script->textContent,true,512,JSON_THROW_ON_ERROR);
    $types[] = $schema['@type'];
  }
  return $types;
}
$html = file_get_contents($root.'/index.html');
$xpath = seo_document($html);
seo_check(seo_schemas($xpath)===['CafeOrCoffeeShop','Menu','WebSite','FAQPage'],'all four structured-data blocks parse independently');
$inline = [];
foreach ($xpath->query('//*') as $node) foreach ($node->attributes as $attr) if (preg_match('/^on/i',$attr->name)) $inline[]=$attr->name;
seo_check(!$inline,'no inline event handlers conflict with CSP');
foreach ($xpath->query('//img') as $img) seo_check((int)$img->getAttribute('width')>0 && (int)$img->getAttribute('height')>0,'image dimensions: '.$img->getAttribute('src'));
$map = $xpath->query('//iframe[@id="visit-map"]')->item(0);
seo_check($map && !$map->hasAttribute('src') && $map->hasAttribute('hidden'),'Maps does not load before interaction');
seo_check($xpath->query('//a[contains(@href,"google.com/maps/dir")]')->length>0,'directions remain available without JavaScript');
seo_check($xpath->query('//link[@id="site-fonts"]')->length===1 && $xpath->query('//script[@src and @defer]')->length===1,'font activation and deferred application script are present');
$xml = simplexml_load_file($root.'/sitemap.xml');
$xml->registerXPathNamespace('s','http://www.sitemaps.org/schemas/sitemap/0.9');
$urls = $xml->xpath('//s:loc');
seo_check(count($urls)===1 && (string)$urls[0]==='https://ruethiespatisserie.com/','sitemap contains only the canonical page, no fragments');
foreach (['wine','white'] as $color) foreach ([320,640] as $width) {
  $file=$root.'/images/brand/logo-'.$color.'-'.$width.'.webp';$size=getimagesize($file);
  seo_check($size[0]===$width && filesize($file)<filesize($root.'/logo-'.$color.'.png'),'responsive logo '.$color.' '.$width.' is smaller than source');
}
$tmp = sys_get_temp_dir().'/ruethies-seo-'.bin2hex(random_bytes(6));mkdir($tmp,0700);
define('RUETHIES_ADMIN',true);define('RUETHIES_PUBLIC',$tmp);
function ruethies_data_dir(): string { global $tmp; return $tmp.'/private'; }
require $root.'/admin/lib.php';
try {
  file_put_contents($tmp.'/index.html',$html);copy($root.'/llms.txt',$tmp.'/llms.txt');copy($root.'/faq.json',$tmp.'/faq.json');
  [$ok,$message]=faq_apply([['q'=>'Pertanyaan baru?','a'=>'Jawaban dengan "kutipan" dan tautan [lokasi](https://example.com/).','qe'=>'New question?','ae'=>'New answer.']]);
  seo_check($ok,'FAQ admin save succeeds: '.$message);
  $updated=file_get_contents($tmp.'/index.html');$after=seo_document($updated);
  seo_check(seo_schemas($after)===['CafeOrCoffeeShop','Menu','WebSite','FAQPage'],'FAQ update retains valid JSON-LD blocks');
  seo_check(str_contains($updated,'id="map-placeholder"') && str_contains($updated,'app.js?v=20261025-seo'),'FAQ update preserves map facade and new asset version');
  seo_check(str_contains($updated,'Pertanyaan baru?') && str_contains(file_get_contents($tmp.'/llms.txt'),'Pertanyaan baru?'),'FAQ HTML and llms content update together');
} finally {
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
  foreach($it as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($tmp);
}
