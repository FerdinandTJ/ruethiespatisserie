<?php
if (!defined('RUETHIES_PUBLIC_RENDER')) { http_response_code(404); exit; }
require_once dirname(__DIR__).'/admin/catalog.php';
function seo_faq(array $items): void {
  foreach ($items as $row) {
    if (!is_array($row)) throw new RuntimeException('FAQ tidak valid.');
    foreach (['q','a'] as $key) if (!is_string($row[$key] ?? null)) throw new RuntimeException('FAQ tidak valid.');
    foreach (['qe','ae'] as $key) if (isset($row[$key]) && !is_string($row[$key])) throw new RuntimeException('Terjemahan FAQ tidak valid.');
  }
}
function seo_faq_html(array $items): string {
  $html='';
  foreach ($items as $item) {
    $answer=preg_replace_callback('/\[([^\[\]]{1,80})\]\((https?:\/\/[^\s()<>\"]{1,300})\)/',fn($m)=>'<a href="'.$m[2].'" target="_blank" rel="noopener">'.$m[1].'</a>',catalog_escape($item['a']));
    $html.='<details><summary>'.catalog_escape($item['q']).'</summary><p>'.$answer.'</p></details>';
  }
  return $html;
}
function seo_faq_schema(array $items): array {
  return ['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>array_map(fn($f)=>['@type'=>'Question','name'=>$f['q'],'acceptedAnswer'=>['@type'=>'Answer','text'=>preg_replace('/\[([^\[\]]{1,80})\]\((https?:\/\/[^\s()<>\"]{1,300})\)/','$1',$f['a'])]],$items)];
}
function seo_block(string $html,string $name,string $contents): string {
  $start='<!-- '.$name.':START -->'; $end='<!-- '.$name.':END -->';
  $a=strpos($html,$start);$b=strpos($html,$end);
  if($a===false || $b===false || $b<$a) throw new RuntimeException('Template tidak lengkap.');
  return substr($html,0,$a+strlen($start))."\n".$contents."\n".substr($html,$b);
}
function seo_script(array $schema): string { return '<script type="application/ld+json">'.catalog_json($schema).'</script>'; }
function seo_picture(array $item,bool $eager=false): string {
  $src='/'.$item['img']; $name=catalog_escape($item['id']['n']);
  $source=str_ends_with($src,'.jpg') ? '<source type="image/webp" srcset="'.catalog_escape(substr($src,0,-4).'.webp').'">' : '';
  return '<picture>'.$source.'<img src="'.catalog_escape($src).'" alt="'.$name.'" width="640" height="480" '.($eager?'fetchpriority="high"':'loading="lazy"').' decoding="async"></picture>';
}
function seo_row(array $m,int $index): string {
  $name=catalog_escape($m['id']['n']);$url=catalog_path($m);
  $link=' href="'.$url.'" data-product="'.$index.'" aria-haspopup="dialog"';
  return '<article class="mrow"><span class="mnum">'.str_pad((string)($index+1),2,'0',STR_PAD_LEFT).'</span><div class="mbody"><h3><a class="menu-title-link"'.$link.'>'.$name.'</a></h3><p>'.catalog_escape($m['id']['d']).'</p><div class="mtags"><span class="tagline">'.catalog_escape(CATALOG_CATEGORIES[$m['cat']]).'</span><span class="menu-actions"><a class="detail-link"'.$link.'>Lihat detail <span aria-hidden="true">↗</span></a><a class="askbtn" href="'.seo_wa($m['id']['n']).'" target="_blank" rel="noopener">Tanya via WA →</a></span></div></div><span class="mdots" aria-hidden="true"></span><a class="mpeek product-photo"'.$link.' aria-label="Lihat foto: '.$name.'">'.seo_picture($m).'</a></article>';
}
function seo_card(array $m,int $index): string {
  return '<article class="card"><a class="ph product-photo" href="'.catalog_path($m).'" data-product="'.$index.'" aria-haspopup="dialog" aria-label="Lihat detail: '.catalog_escape($m['id']['n']).'">'.seo_picture($m).'</a><div class="bd"><h3>'.catalog_escape($m['id']['n']).'</h3><p>'.catalog_escape($m['id']['d']).'</p></div></article>';
}
function seo_wa(string $name): string { return 'https://wa.me/6285139281257?text='.rawurlencode('Halo Ruethies Patisserie! Saya ingin bertanya tentang '.$name.'. Mohon informasi pilihan, harga, jumlah pesanan, dan tanggal kebutuhan.'); }
function seo_business(string $template): array {
  preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s',$template,$matches);
  foreach ($matches[1] as $raw) {
    $data=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if (($data['@type'] ?? '')==='CafeOrCoffeeShop' || ($data['@type'] ?? '')==='Bakery') {
      $data['@type']='Bakery';$data['image']=CATALOG_ORIGIN.'/images/hero-marble-cake.jpg';
      return $data;
    }
  }
  throw new RuntimeException('Identitas bisnis tidak tersedia.');
}
function seo_menu_schema(array $items): array {
  $sections=[];
  foreach(CATALOG_CATEGORIES as $category=>$label) {
    $rows=[];
    foreach($items as $m) if($m['cat']===$category) $rows[]=['@type'=>'MenuItem','name'=>$m['id']['n'],'description'=>$m['id']['d'],'url'=>CATALOG_ORIGIN.catalog_path($m),'image'=>CATALOG_ORIGIN.'/'.$m['img']];
    if($rows)$sections[]=['@type'=>'MenuSection','name'=>$label,'hasMenuItem'=>$rows];
  }
  return ['@context'=>'https://schema.org','@type'=>'Menu','name'=>'Menu Ruethies Patisserie','url'=>CATALOG_ORIGIN.'/#menu','hasMenuSection'=>$sections];
}
function seo_head(string $html,string $title,string $description,string $path,string $image,array $schemas): string {
  $html=preg_replace_callback('~<title>.*?</title>~s',fn()=>'<title>'.catalog_escape($title).'</title>',$html);
  $html=preg_replace_callback('~<meta (?:name|property)="(description|og:title|og:description|og:url|og:image|twitter:title|twitter:description|twitter:image)"[^>]*>~',function($m)use($title,$description,$path,$image){
    $key=$m[1];$value=match($key){'og:title','twitter:title'=>$title,'description','og:description','twitter:description'=>$description,'og:url'=>CATALOG_ORIGIN.$path,default=>CATALOG_ORIGIN.'/'.$image};
    return '<meta '.(str_starts_with($key,'og:')?'property':'name').'="'.$key.'" content="'.catalog_escape($value).'">';
  },$html);
  $html=preg_replace('~<link rel="canonical"[^>]*>~','<link rel="canonical" href="'.CATALOG_ORIGIN.$path.'">',$html);
  $html=preg_replace('~<link rel="alternate"[^>]*>~','',$html);
  $html=preg_replace('~<script type="application/ld\+json">.*?</script>~s','',$html);
  return str_replace('</head>',implode("\n",array_map('seo_script',$schemas))."\n</head>",$html);
}
function seo_home(string $template,array $items,array $faq): string {
  $public=array_values(array_filter($items,fn($m)=>($m['available'] ?? true)!==false));
  $html=seo_block($template,'SEO:MENU',implode("\n",array_map(fn($m,$i)=>seo_row($m,$i),$public,array_keys($public))));
  $chosen=array_slice(array_keys(array_filter($public,fn($m)=>!empty($m['signature']))),0,3);
  foreach(array_keys($public) as $key)if(count($chosen)<3 && !in_array($key,$chosen,true))$chosen[]=$key;
  $html=seo_block($html,'SEO:SIGNATURE',implode('',array_map(fn($i)=>seo_card($public[$i],$i),$chosen)));
  $html=seo_block($html,'FAQ:HTML',seo_faq_html($faq));
  $html=preg_replace('~<noscript><ul>.*?</ul></noscript>~s','',$html,1);
  $snack = array_values(array_filter($public,fn($m)=>$m['slug']==='snackbox'));
  $html=seo_block($html,'SEO:SNACKLINK', $snack ? '<p class="hero-snack"><a href="/snack-box-cikarang/" data-i18n="hero.snack">Kenali snack box kami →</a></p>' : '');
  $names=array_column(array_column($public,'id'),'n');
  $description=mb_substr(implode(', ',$names).' di Ruethies Patisserie, Lippo Cikarang. Hubungi WhatsApp untuk pilihan dan pemesanan.',0,170);
  $html=seo_head($html,'Snack Box & Kue di Cikarang | Ruethies Patisserie',$description,'/',($snack[0]['img'] ?? $public[0]['img'] ?? 'images/hero-marble-cake.jpg'),[
    seo_business($template),seo_menu_schema($public),['@context'=>'https://schema.org','@type'=>'WebSite','name'=>'Ruethies Patisserie','url'=>CATALOG_ORIGIN.'/','inLanguage'=>'id'],seo_faq_schema($faq)
  ]);
  $html=str_replace('id="menugrid" aria-busy="true"','id="menugrid" aria-busy="false"',$html);
  $html=str_replace('id="signature" aria-busy="true"','id="signature" aria-busy="false"',$html);
  $snapshot=['menu'=>array_map('seo_interactive_product',$public),'faq'=>$faq];
  $html=str_replace('</body>','<script type="application/json" id="catalog-data">'.catalog_json($snapshot).'</script></body>',$html);
  return $html;
}
function seo_product(string $template,array $m,array $items): string {
  $snack=$m['slug']==='snackbox';$path=catalog_path($m);$name=$m['id']['n'];
  $heading=$snack?'Snack Box di Cikarang':$name.' di Cikarang';
  $title=$snack?'Pesan Snack Box Cikarang | Ruethies Patisserie':$heading.' | Ruethies Patisserie';
  $description=$snack?'Kenali snack box Ruethies Patisserie di Lippo Cikarang. Lihat foto dan hubungi WhatsApp untuk pilihan isi, harga, jumlah pesanan serta tanggal kebutuhan.':$name.' di Ruethies Patisserie, Lippo Cikarang. '.$m['id']['d'].' Tanyakan pilihan dan pemesanan melalui WhatsApp.';
  $description=mb_strlen($description)>170 ? rtrim(mb_substr($description,0,167)).'…' : $description;
  $photos=array_values(array_filter(array_merge([$m['img']],$m['detail_images'] ?? [])));
  $body='<main id="home" class="product-page container"><nav class="breadcrumbs" aria-label="Breadcrumb"><a href="/">Beranda</a><span aria-hidden="true"> / </span><a href="/#menu">Menu</a><span aria-hidden="true"> / </span><span>'.catalog_escape($name).'</span></nav><section class="product-intro"><div><p class="eyebrow">RUETHIES · LIPPO CIKARANG</p><h1 class="h2" id="product-title">'.catalog_escape($heading).'</h1><p class="product-summary" id="product-summary">'.catalog_escape($m['id']['d']).'</p><div id="product-detail">'.seo_paragraphs(catalog_detail($m,'id')).'</div><p class="product-context" data-copy="context">'.($snack?'Snack box merupakan pilihan paket kudapan dalam kotak. Hubungi Ruethies Patisserie di Lippo Cikarang untuk membicarakan kebutuhan Anda.':'Temukan '.catalog_escape($name).' dalam katalog Ruethies Patisserie di Lippo Cikarang. Hubungi kami untuk menanyakan pilihan dan ketersediaannya.').'</p><a class="btn primary" id="product-wa" href="'.seo_wa($name).'" target="_blank" rel="noopener">Tanyakan '.catalog_escape($name).' via WhatsApp</a><p class="muted product-note" data-copy="terms">Pilihan isi, ukuran, harga, minimum pesanan, tenggat pemesanan, serta pengambilan atau pengantaran dikonfirmasi melalui WhatsApp.</p></div><div class="product-photos">';
  foreach($photos as $i=>$photo) $body.='<figure class="product-photo-frame">'.seo_picture(array_replace($m,['img'=>$photo]),$i===0).'<figcaption>'.catalog_escape($name).($i ? ' · Detail '.$i : '').'</figcaption></figure>';
  $body.='</div></section><section class="product-information"><div><h2 class="h2" data-copy="order">Cara memesan</h2><ol id="order-steps"><li>Sebutkan produk yang Anda minati.</li><li>Sampaikan jumlah serta tanggal kebutuhan.</li><li>Konfirmasikan pilihan, harga, dan cara menerima pesanan melalui WhatsApp.</li></ol></div><div><h2 class="h2" data-copy="location">Lokasi Ruethies Patisserie</h2><p>Ruko The Neo Patio East No. 51, Lippo, Cikarang Selatan, Kabupaten Bekasi, Jawa Barat 17530.</p><p><a href="https://www.google.com/maps/dir/?api=1&amp;destination=-6.3443722,107.1490519" target="_blank" rel="noopener" data-copy="directions">Dapatkan rute →</a></p><p>Email: <a href="mailto:info@ruethiespatisserie.com">info@ruethiespatisserie.com</a></p></div></section>';
  $faq=[['q'=>'Bagaimana cara memesan '.$name.'?','a'=>'Hubungi WhatsApp 0851-3928-1257. Sebutkan produk, jumlah, dan tanggal kebutuhan untuk mengonfirmasi pilihan serta harga.'],['q'=>'Apakah harga dan ketentuan pesanan tersedia di website?','a'=>'Silakan konfirmasikan harga, pilihan isi, minimum pesanan, tenggat pemesanan, serta pengambilan atau pengantaran melalui WhatsApp.'],['q'=>'Di mana lokasi Ruethies Patisserie?','a'=>'Ruko The Neo Patio East No. 51, Lippo, Cikarang Selatan, Kabupaten Bekasi, Jawa Barat 17530.']];
  $body.='<section class="product-faq"><h2 class="h2" data-copy="faq">Pertanyaan tentang '.catalog_escape($name).'</h2><div id="product-faq">'.seo_faq_html($faq).'</div></section><section class="product-related"><h2 class="h2" data-copy="related">Pilihan lainnya</h2><div class="cards3">';
  $related=array_values(array_filter($items,fn($r)=>$r['uid']!==$m['uid'] && ($r['available'] ?? true)!==false));
  usort($related,fn($a,$b)=>(int)($b['cat']===$m['cat'])<=>(int)($a['cat']===$m['cat']));
  foreach(array_slice($related,0,3) as $r) $body.='<article class="card"><a class="ph product-photo" href="'.catalog_path($r).'">'.seo_picture($r).'</a><div class="bd"><h3><a href="'.catalog_path($r).'">'.catalog_escape($r['id']['n']).'</a></h3><p>'.catalog_escape($r['id']['d']).'</p></div></article>';
  $body.='</div></section></main>';
  $html=preg_replace_callback('~<main id="home">.*?</main>~s',fn()=>$body,$template);
  // Public links use root-relative paths; the same header/footer works on nested pages.
  $html=preg_replace('~href="#([^\"]*)"~','href="/#$1"',$html);
  $html=preg_replace('~<!-- LIGHTBOX -->.*?</body>~s','</body>',$html);
  $html=preg_replace('~<link rel="preload"[^>]*>~','',$html);
  $html=preg_replace('~<script src="app.js\?[^\"]+" defer></script>~','<script src="/product.js?v=20261109-seo" defer></script>',$html);
  $html=str_replace('</body>','<script type="application/json" id="product-data">'.catalog_json(['product'=>seo_interactive_product($m),'faq'=>$faq]).'</script></body>',$html);
  $crumb=['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'Beranda','item'=>CATALOG_ORIGIN.'/'],['@type'=>'ListItem','position'=>2,'name'=>'Menu','item'=>CATALOG_ORIGIN.'/#menu'],['@type'=>'ListItem','position'=>3,'name'=>$name,'item'=>CATALOG_ORIGIN.$path]]];
  $page=['@context'=>'https://schema.org','@type'=>'WebPage','@id'=>CATALOG_ORIGIN.$path,'url'=>CATALOG_ORIGIN.$path,'name'=>$title,'inLanguage'=>'id','description'=>$description,'mainEntity'=>['@type'=>'MenuItem','name'=>$name,'description'=>$m['id']['d'],'image'=>array_map(fn($p)=>CATALOG_ORIGIN.'/'.$p,$photos)]];
  return seo_head($html,$title,$description,$path,$m['img'],[seo_business($template),$page,$crumb,seo_faq_schema($faq)]);
}
function seo_paragraphs(string $text): string { return implode('',array_map(fn($p)=>'<p>'.nl2br(catalog_escape($p),false).'</p>',array_filter(preg_split('/\R\s*\R/u',$text)))); }
function seo_assets(string $html): string {
  return preg_replace_callback('~\b(src|href|srcset)="([^\"]*)"~',function($m){
    $value=preg_replace('~(^|,\s*)(?=(?:images/|logo-|styles\.css|app\.js|manifest\.json))~','$1/',$m[2]);
    return $m[1].'="'.$value.'"';
  },$html);
}
function seo_sitemap(array $items,int $homeModified): string {
  $xml='<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>'.CATALOG_ORIGIN.'/</loc><lastmod>'.gmdate('c',$homeModified).'</lastmod></url>';
  foreach($items as $m) if(($m['available'] ?? true)!==false) $xml.='<url><loc>'.CATALOG_ORIGIN.catalog_path($m).'</loc><lastmod>'.catalog_escape($m['updated_at']).'</lastmod></url>';
  return $xml.'</urlset>';
}
function seo_llms(string $template,array $items,array $faq): string {
  $business=seo_business($template);
  $text="# Ruethies Patisserie\n\nSnack box, kue klasik dan camilan gurih di Lippo Cikarang.\n\n";
  $text.='- Alamat: '.$business['address']['streetAddress'].', '.$business['address']['addressLocality']."\n- WhatsApp: https://wa.me/6285139281257\n- Email: info@ruethiespatisserie.com\n- Jam buka: 09.00–21.00 WIB setiap hari\n- Pilihan, harga, minimum pesanan, tenggat, pengambilan dan pengantaran dikonfirmasi melalui WhatsApp.\n\n## Menu\n";
  foreach($items as $m) if(($m['available'] ?? true)!==false)$text.='- ['.$m['id']['n'].']('.CATALOG_ORIGIN.catalog_path($m).'): '.$m['id']['d']."\n";
  $text.="\n## FAQ\n";foreach($faq as $f)$text.='- '.$f['q'].' '.$f['a']."\n";
  return $text;
}

function seo_interactive_product(array $item): array {
  foreach (['id','en'] as $language) {
    $item[$language] = $item[$language] ?? $item['id'];
    $item[$language]['detail']=catalog_detail($item,$language);
  }
  $item['url']=catalog_path($item);
  return $item;
}
