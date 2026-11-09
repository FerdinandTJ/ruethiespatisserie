<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('RUETHIES_ADMIN',true);define('RUETHIES_PUBLIC',dirname(__DIR__));require RUETHIES_PUBLIC.'/admin/lib.php';
function catalog_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
$menu=json_decode(file_get_contents(RUETHIES_PUBLIC.'/menu.json'),true,512,JSON_THROW_ON_ERROR);
$expected=['Bolu Jadoel','Brownies Cokelat','Lapis Surabaya','Marble Cake','Jongkong','Pastel','Kroket','Risol','Platter','Snackbox'];
catalog_check(array_column(array_column($menu,'id'),'n')===$expected,'catalog contains exactly the ten approved products');
catalog_check(array_keys(RUETHIES_CATS)===['cakery','savoury','packages'],'CMS categories match cakes, savouries and packages');
foreach($menu as$item){catalog_check(isset(RUETHIES_CATS[$item['cat']])&&menu_validate(['cat'=>$item['cat'],'n'=>$item['id']['n'],'d'=>$item['id']['d'],'tag'=>$item['tag']['id']])!==null,'CMS accepts category and content: '.$item['id']['n']);foreach([$item['img'],substr($item['img'],0,-4).'.webp']as$path){$size=getimagesize(RUETHIES_PUBLIC.'/'.$path);catalog_check($size[0]===960&&$size[1]===720&&filesize(RUETHIES_PUBLIC.'/'.$path)<250000,'optimized menu photo: '.$path);}}
$html=file_get_contents(RUETHIES_PUBLIC.'/index.html');$doc=new DOMDocument();libxml_use_internal_errors(true);$doc->loadHTML($html);libxml_clear_errors();$xp=new DOMXPath($doc);$schemas=$xp->query('//script[@type="application/ld+json"]');$schemaMenu=json_decode($schemas->item(1)->textContent,true,512,JSON_THROW_ON_ERROR);$names=[];foreach($schemaMenu['hasMenuSection']as$section)foreach($section['hasMenuItem']as$item)$names[]=$item['name'];sort($names);sort($expected);catalog_check($names===$expected,'structured menu matches all visible products');
catalog_check($xp->query('//button[@data-cat="packages"]')->length===1&&$xp->query('//button[@data-cat="coffee"]')->length===0,'public category navigation includes packages and removes drinks');
$js=file_get_contents(RUETHIES_PUBLIC.'/app.js');catalog_check(str_contains($js,'catalogueSnapshot.menu') && !str_contains($js,'let MENU = ['),'public interactivity uses server snapshot without a sample catalogue fallback');
foreach(['Croissant','Opera Cake','Fraisier','Espresso','Coffee & Drinks','Smoked Beef']as$old)catalog_check(!str_contains($html,$old)&&!str_contains($js,$old),'removed product claims absent from page and fallback: '.$old);
$hero=getimagesize(RUETHIES_PUBLIC.'/images/hero-marble-cake.webp');catalog_check($hero[0]===880&&$hero[1]===1100,'new hero retains expected dimensions');
echo "All catalog checks passed.\n";
