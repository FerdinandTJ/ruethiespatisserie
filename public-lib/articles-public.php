<?php
if(!defined('RUETHIES_PUBLIC_RENDER')){http_response_code(404);exit;}
require_once __DIR__.'/articles.php';
function article_path(array $row): string{return '/artikel/'.$row['slug'].'/';}
function article_date(string $date): string{return gmdate('d M Y',strtotime($date));}
function article_card_image(array $row,string $sizes): string {
 if($row['cover']===''||!($info=article_image_info($row['cover'])))return '';
 $sources=[];foreach([480,800]as$width){$path=article_thumbnail_path($row['cover'],$width);$thumb=article_image_info($path);if($thumb&&$thumb[0]===$width)$sources[$width]='/'.$path;}
 // Retain the original for small photos or an incomplete backfill: never stretch a tiny variant.
 if(!$sources||max(array_keys($sources))<min(800,$info[0]))$sources[$info[0]]='/'.$row['cover'];
 ksort($sources);$srcset=[];foreach($sources as$width=>$path)$srcset[]=$path.' '.$width.'w';
 return '<img class="article-card-image" src="'.article_escape(reset($sources)).'" srcset="'.article_escape(implode(', ',$srcset)).'" sizes="'.article_escape($sizes).'" alt="" width="'.$info[0].'" height="'.$info[1].'" loading="lazy" decoding="async">';
}
function article_card(array $row,string $heading='h2'): string{
 $heading=$heading==='h3'?'h3':'h2';$url=article_path($row);$html='<article class="article-card" lang="'.$row['lang'].'">';
 $html.=article_card_image($row,'(max-width: 600px) calc(100vw - 80px), (max-width: 900px) calc((100vw - 120px) / 2), 330px');
 return $html.'<p class="rp-kicker">'.profile_text(...ARTICLE_CATEGORIES[$row['category']]).'</p><'.$heading.'><a href="'.$url.'">'.article_escape($row['title']).'</a></'.$heading.'><p class="article-summary">'.article_escape($row['description']).'</p><p class="article-meta"><time datetime="'.$row['publishedAt'].'">'.article_date($row['publishedAt']).'</time> · '.article_minutes($row['body']).' '.profile_text('menit baca','min read').'</p></article>';
}
function article_related_choices(array $paths,array $items): string {
 $products=[];$pages=[];
 $names=['/'=>['Kembali ke beranda →','Back to home →'],'/menu/'=>['Jelajahi menu →','Explore the menu →'],'/tentang-kami/'=>['Kenali Ruethies →','Get to know Ruethies →'],'/cara-pesan/'=>['Cara pesan & FAQ →','Ordering & FAQ →'],'/hubungi-kami/'=>['Lihat lokasi & kontak →','Location & contact →']];
 foreach($paths as$path){
  $found=false;foreach($items as$product)if(catalog_path($product)===$path&&($product['available']??true)!==false){$products[]=profile_card($product);$found=true;break;}
  if(!$found&&isset($names[$path]))$pages[]=profile_link($path,...$names[$path]);
 }
 if(!$products&&!$pages)return '';
 return '<section class="rp-section article-related"><h2>'.profile_text('Pilihan terkait','Related choices').'</h2>'.($products?'<div class="rp-grid">'.implode('',$products).'</div>':'').($pages?'<div class="article-related-links">'.implode('',$pages).'</div>':'').'</section>';
}
function article_listing(string $template,array $state,array $query): ?string{
 $all=article_published($state);if(!$all)return null;$cat=is_string($query['kategori']??null)&&isset(ARTICLE_CATEGORIES[$query['kategori']])?$query['kategori']:'';
 $enhanced=($query['enhanced']??'')==='1';$items=$enhanced&&$cat!==''?array_values(array_filter($all,fn($r)=>$r['category']===$cat)):$all;
 $page=is_string($query['page']??null)&&ctype_digit($query['page'])?max(1,(int)$query['page']):1;$pages=max(1,(int)ceil(count($items)/12));if($page>$pages)return null;
 $body='<main id="home" class="container rp-page articles-page">'.profile_breadcrumb('Artikel','Articles').'<header class="rp-page-title"><h1>'.profile_text('Artikel Ruethies','Ruethies Articles').'</h1>'.profile_text('Panduan memesan, inspirasi acara, dan tips menikmati sajian Ruethies di Cikarang.','Ordering guides, occasion ideas and tips for enjoying Ruethies treats in Cikarang.','p',' class="rp-lead"').'</header><nav class="rp-filter article-filters" aria-label="Kategori artikel" data-article-label-id="Kategori artikel" data-article-label-en="Article categories" hidden>';
 foreach([''=>['Semua','All']]+ARTICLE_CATEGORIES as$key=>$names)$body.='<a href="/artikel/'.($key!==''?'?kategori='.$key:'').'"'.($cat===$key?' aria-current="true"':'').'>'.profile_text(...$names).'</a>';
 $body.='</nav><div id="article-list-region"><div class="article-grid">';foreach(array_slice($items,($page-1)*12,12)as$row)$body.=article_card($row);$body.='</div>';
 if(!$items)$body.=profile_text('Belum ada artikel pada kategori ini.','There are no articles in this category yet.','p');
 if($pages>1){$body.='<nav class="article-pagination" aria-label="Halaman artikel" data-article-label-id="Halaman artikel" data-article-label-en="Article pages">';for($i=1;$i<=$pages;$i++){$params=array_filter(['kategori'=>$cat,'page'=>$i>1?(string)$i:''],fn($v)=>$v!=='');$body.='<a href="/artikel/'.($params?'?'.article_escape(http_build_query($params)):'').'"'.($i===$page?' aria-current="page"':'').'>'.$i.'</a>';}$body.='</nav>';}
 $body.='</div><p id="article-list-status" role="status" aria-live="polite"></p></main>';
 $schema=['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'Beranda','item'=>CATALOG_ORIGIN.'/'],['@type'=>'ListItem','position'=>2,'name'=>'Artikel','item'=>CATALOG_ORIGIN.'/artikel/']]];
 return profile_shell($template,$body,'/artikel/','Artikel Ruethies'.($page>1?' — Halaman '.$page:'').' | Ruethies Patisserie',($page>1?'Halaman '.$page.'. ':'').'Panduan pemesanan, inspirasi acara dan tips sajian Ruethies Patisserie di Lippo Cikarang.','images/hero-marble-cake.jpg',[$schema],['articlePage'=>true],$cat!==''||$page>1);
}
function article_detail(string $template,array $row,array $items,array $published=[],bool $preview=false): string{
 $rendered=article_markdown($row['body']);$url=article_path($row);$body='<main id="home" class="container rp-page articles-page">';
 if($preview)$body.='<p class="article-preview">Pratinjau privat · '.article_escape($row['status']).' · <a href="/admin/?p=artikel-ubah&amp;id='.$row['id'].'">Kembali ke editor</a></p>';
 $body.='<nav class="rp-breadcrumb" aria-label="Breadcrumb">'.profile_link('/','Beranda','Home','').'<span aria-hidden="true">›</span>'.profile_link('/artikel/','Artikel','Articles','').'<span aria-hidden="true">›</span><span>'.article_escape($row['title']).'</span></nav><article class="article-reading" lang="'.$row['lang'].'"><header><p class="rp-kicker">'.profile_text(...ARTICLE_CATEGORIES[$row['category']]).'</p><h1>'.article_escape($row['title']).'</h1><p class="article-meta">';
 if($row['publishedAt']!==null)$body.=profile_text('Terbit','Published').' <time datetime="'.$row['publishedAt'].'">'.article_date($row['publishedAt']).'</time> · ';
 if($row['publishedAt']!==null&&$row['updatedAt']!==$row['publishedAt'])$body.=profile_text('Diperbarui','Updated').' <time datetime="'.$row['updatedAt'].'">'.article_date($row['updatedAt']).'</time> · ';
 $body.=article_minutes($row['body']).' '.profile_text('menit baca','min read').'</p><p class="rp-lead">'.article_escape($row['description']).'</p></header>';
 if($row['cover']!==''&&($dimensions=article_image_info($row['cover']))){$body.='<img class="article-cover" src="/'.$row['cover'].'" alt="'.article_escape($row['coverAlt']).'" width="'.$dimensions[0].'" height="'.$dimensions[1].'" decoding="async">';}
 if(count($rendered['toc'])>=3){$body.='<nav class="article-toc" aria-label="Daftar isi" data-article-label-id="Daftar isi" data-article-label-en="Table of contents"><h2>'.profile_text('Dalam artikel ini','In this article').'</h2><ol>';foreach($rendered['toc']as$heading)$body.='<li><a href="#'.article_escape($heading['id']).'">'.article_escape($heading['text']).'</a></li>';$body.='</ol></nav>';}
 foreach($published as$other)if($other['lang']!==$row['lang']&&($other['translationOf']===$row['id']||$row['translationOf']===$other['id']))$body.='<p><a href="'.article_path($other).'" hreflang="'.$other['lang'].'">'.($other['lang']==='en'?'Read the English version':'Baca versi bahasa Indonesia').'</a></p>';
 $message="Halo Ruethies Patisserie! Saya baru membaca artikel '".$row['title']."' dan ingin bertanya.";$wa='https://wa.me/6285139281257?text='.rawurlencode($message);
 $body.='<div class="article-body">'.$rendered['html'].'</div><aside class="article-cta"><h2>'.profile_text('Bicarakan kebutuhan Anda','Tell us what you have in mind').'</h2><a class="rp-button" href="'.article_escape($wa).'" target="_blank" rel="noopener" data-article-wa>'.profile_text('Tanyakan melalui WhatsApp','Ask us on WhatsApp').'</a></aside></article>';
 $body.=article_related_choices($row['related'],$items);
 $other=array_slice(array_values(array_filter($published,fn($r)=>$r['id']!==$row['id'])),0,3);if($other){$body.='<section class="rp-section"><h2>'.profile_text('Artikel lain','More articles').'</h2><div class="article-grid">';foreach($other as$r)$body.=article_card($r,'h3');$body.='</div></section>';}$body.='</main>';
 $crumbs=[['@type'=>'ListItem','position'=>1,'name'=>'Beranda','item'=>CATALOG_ORIGIN.'/'],['@type'=>'ListItem','position'=>2,'name'=>'Artikel','item'=>CATALOG_ORIGIN.'/artikel/'],['@type'=>'ListItem','position'=>3,'name'=>$row['title'],'item'=>CATALOG_ORIGIN.$url]];
 $schema=['@context'=>'https://schema.org','@type'=>'Article','headline'=>$row['title'],'description'=>$row['description'],'dateModified'=>$row['updatedAt'],'author'=>['@type'=>'Organization','name'=>'Ruethies Patisserie'],'publisher'=>['@type'=>'Organization','name'=>'Ruethies Patisserie'],'inLanguage'=>$row['lang'],'mainEntityOfPage'=>CATALOG_ORIGIN.$url];if($row['publishedAt']!==null)$schema['datePublished']=$row['publishedAt'];$image=$row['cover']?:'images/hero-marble-cake.jpg';$schema['image']=CATALOG_ORIGIN.'/'.$image;
 $html=profile_shell($template,$body,$url,$row['title'].' | Ruethies Patisserie',$row['description'],$image,[$schema,['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>$crumbs]],['articlePage'=>true,'articleTitle'=>$row['title'],'articleLang'=>$row['lang']],$preview);
 return $preview?str_replace('content="noindex, follow"','content="noindex, nofollow"',$html):$html;
}
