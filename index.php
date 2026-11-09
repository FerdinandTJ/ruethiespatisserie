<?php
// Public renderer. Never starts an admin session or reads accounts/configuration.
define('RUETHIES_PUBLIC_RENDER',true);
require __DIR__.'/public-lib/seo.php';
function seo_reply(string $body,string $type,int $modified): void {
  $etag='"'.hash('sha256',$body).'"';
  header('Content-Type: '.$type.'; charset=UTF-8');
  header('Cache-Control: public, max-age=0, must-revalidate');
  // An explicit Expires prevents mod_expires from adding its static HTML max-age.
  header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
  header('ETag: '.$etag);header('Last-Modified: '.gmdate('D, d M Y H:i:s',$modified).' GMT');
  $matches=is_string($_SERVER['HTTP_IF_NONE_MATCH'] ?? null) ? array_map('trim',explode(',',$_SERVER['HTTP_IF_NONE_MATCH'])) : [];
  if(in_array($etag,$matches,true) || in_array('W/'.$etag,$matches,true) || in_array('*',$matches,true)) { http_response_code(304);return; }
  if(($_SERVER['REQUEST_METHOD'] ?? 'GET')!=='HEAD')echo $body;
}
try {
  if(!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET',['GET','HEAD'],true)){header('Allow: GET, HEAD');http_response_code(405);exit;}
  $path=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH);
  if(!is_string($path))throw new RuntimeException('URL tidak valid.');
  if(in_array($path,['/index.html','/index.php'],true)){header('Location: /',true,301);exit;}
  if(in_array($path,['/menu/snackbox','/menu/snackbox/','/menu/snack-box','/menu/snack-box/'],true)){header('Location: /snack-box-cikarang/',true,301);exit;}
  if(preg_match('~\A/(?:snack-box-cikarang|menu/[a-z0-9]+(?:-[a-z0-9]+)*)\z~D',$path)){header('Location: '.$path.'/',true,301);exit;}
  $known=in_array($path,['/','/sitemap.xml','/llms.txt','/snack-box-cikarang/'],true) || preg_match('~\A/menu/[a-z0-9]+(?:-[a-z0-9]+)*/\z~D',$path);
  if(!$known){http_response_code(404);header('Cache-Control: no-store');readfile(__DIR__.'/404.html');exit;}
  $items=catalog_read_json(__DIR__.'/menu.json');catalog_validate($items);
  $faq=catalog_read_json(__DIR__.'/faq.json');seo_faq($faq);
  $template=@file_get_contents(__DIR__.'/index.html');
  if($template===false)throw new RuntimeException('Template tidak tersedia.');
  $modified=max(filemtime(__DIR__.'/index.html'),filemtime(__DIR__.'/menu.json'),filemtime(__DIR__.'/faq.json'));
  if($path==='/sitemap.xml') { seo_reply(seo_sitemap($items,$modified),'application/xml',$modified);exit; }
  if($path==='/llms.txt') { seo_reply(seo_llms($template,$items,$faq),'text/plain',$modified);exit; }
  if($path==='/')$html=seo_home($template,$items,$faq);
  else {
    $found=null;foreach($items as $item)if(catalog_path($item)===$path && ($item['available'] ?? true)!==false){$found=$item;break;}
    if(!$found){http_response_code(404);header('Cache-Control: no-store');readfile(__DIR__.'/404.html');exit;}
    $html=seo_product($template,$found,$items);
  }
  seo_reply(seo_assets($html),'text/html',$modified);
} catch(Throwable $e) {
  error_log('Ruethies SEO renderer: konten tidak siap.');
  http_response_code(503);header('Retry-After: 300');header('Cache-Control: no-store');header('Content-Type: text/html; charset=UTF-8');
  echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="robots" content="noindex"><title>Website sementara tidak tersedia</title><h1>Website sementara tidak tersedia</h1><p>Silakan coba lagi atau hubungi <a href="https://wa.me/6285139281257" rel="noopener">WhatsApp Ruethies Patisserie</a>.</p></html>';
}
