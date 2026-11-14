<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=sys_get_temp_dir().'/ruethies-legacy-'.bin2hex(random_bytes(5));$public=$root.'/public_html/staging';
mkdir($public.'/admin',0700,true);mkdir($public.'/public-lib',0700);mkdir($root.'/ruethies-data-staging',0700);
function legacy_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
function legacy_remove(string $p):void{if(!is_dir($p)){unlink($p);return;}foreach(scandir($p)as$n)if($n!=='.'&&$n!=='..')legacy_remove($p.'/'.$n);rmdir($p);}
try{
foreach(['admin/catalog.php','admin/config.php','admin/lib.php','admin/security.php','admin/security-resources.php','admin/seo-upgrade.php','public-lib/seo.php','public-lib/profile.php','index.html']as$f)copy(dirname(__DIR__).'/'.$f,$public.'/'.$f);
$items=[['uid'=>str_repeat('a',32),'cat'=>'coffee','img'=>'images/menu/kopi-susu.jpg','id'=>['n'=>'Kopi Susu','d'=>'Menu lama'],'available'=>true]];
file_put_contents($public.'/menu.json',json_encode($items));
$run=static function(array $args):array{$p=proc_open(array_merge([PHP_BINARY],$args),[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return[proc_close($p),$out,$err];};
[$exit,$out,$err]=$run([$public.'/admin/seo-upgrade.php','--check']);legacy_check($exit===0&&json_decode($out,true)['changes_needed'],'legacy staging catalogue passes read-only preflight');
[$exit]=$run([$public.'/admin/seo-upgrade.php','--apply']);$after=json_decode(file_get_contents($public.'/menu.json'),true);legacy_check($exit===0&&$after[0]['uid']===$items[0]['uid']&&$after[0]['cat']==='coffee','upgrade preserves legacy UID, category and content');
[$exit,$out]=$run([$public.'/admin/seo-upgrade.php','--check']);legacy_check($exit===0&&!json_decode($out,true)['changes_needed'],'legacy upgrade is idempotent');
$code='define("RUETHIES_PUBLIC_RENDER",true);require '.var_export($public.'/public-lib/seo.php',true).';require '.var_export($public.'/public-lib/profile.php',true).';$items=catalog_read_json('.var_export($public.'/menu.json',true).');catalog_validate($items);echo profile_catalog(file_get_contents('.var_export($public.'/index.html',true).'),$items,[]);';
[$exit,$html,$err]=$run(['-r',$code]);legacy_check($exit===0&&$err===''&&str_contains($html,'/menu/?kategori=coffee')&&str_contains($html,'Kopi Susu')&&str_contains($html,'/menu/kopi-susu/'),'redesigned catalogue keeps legacy coffee filter, crawlable HTML and product link');

legacy_check(str_contains($html,'href="/pre-order/"')&&str_contains($html,'/tentang-kami/')&&str_contains($html,'/hubungi-kami/'),'new desktop/mobile navigation and footer keep Pre-order alongside profile pages');
$code=str_replace('echo profile_catalog','echo profile_home',$code);
[$exit,$home,$err]=$run(['-r',$code]);legacy_check($exit===0&&$err===''&&str_contains($home,'Kopi Susu')&&str_contains($home,'href="/pre-order/"'),'home supports old staging products without resetting catalogue or PO identity');
$code='define("RUETHIES_PUBLIC_RENDER",true);require '.var_export($public.'/public-lib/seo.php',true).';require '.var_export($public.'/public-lib/profile.php',true).';$items=catalog_read_json('.var_export($public.'/menu.json',true).');echo profile_product(file_get_contents('.var_export($public.'/index.html',true).'),$items[0],$items);';
[$exit,$product,$err]=$run(['-r',$code]);legacy_check($exit===0&&$err===''&&str_contains($product,'Coffee &amp; drinks')&&str_contains($product,'order-quantity'),'legacy product detail supports ID/EN and WhatsApp form');
$code='define("RUETHIES_PUBLIC_RENDER",true);$_SERVER["HTTP_HOST"]="staging.ruethiespatisserie.com";require '.var_export(dirname(__DIR__).'/admin/catalog.php',true).';echo isset(CATALOG_CATEGORIES["coffee"])?"enabled":"disabled";';
[$exit,$out]=$run(['-r',$code]);legacy_check($exit===0&&$out==='disabled','Host header cannot enable legacy categories outside staging filesystem');
}finally{legacy_remove($root);}
