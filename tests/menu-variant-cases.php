<?php
// Included in categories-http.php: real requests against disposable owner/staff fixture.
[$code,$editor]=$http('/admin/?p=ubah&i=0');
ch_check($code===200&&str_contains($editor,'Pilihan rasa / varian'),'product editor offers optional variants');
$variantPost=['aksi'=>'save','i'=>'0','cat'=>$key,'n'=>'Kue Uji','d'=>'Sajian','available'=>'1','variants_present'=>'1','variants_enabled'=>'1','variant_0_n'=>'Vanilla & Strawberry','variant_0_ne'=>'Vanilla Strawberry','variant_0_d'=>'Keterangan rasa','variant_0_order'=>'1','variant_1_n'=>'Cokelat Mocca','variant_1_order'=>'0'];
$saveVariant=static function(array $values)use($http,$variantPost){
  [, $editor]=$http('/admin/?p=ubah&i=0');
  return $http('/admin/',array_replace($variantPost,['csrf'=>ch_field($editor,'csrf'),'revision'=>ch_field($editor,'revision')],$values));
};
$before=file_get_contents($public.'/menu.json');
[$code]=$saveVariant(['csrf'=>'wrong']);ch_check($code===403&&file_get_contents($public.'/menu.json')===$before,'variant writes require CSRF');
[$code,$error]=$saveVariant(['variant_8_n'=>'Kesembilan']);ch_check(str_contains($error,'Maksimal 8 varian')&&file_get_contents($public.'/menu.json')===$before,'more than eight input slots rejected without truncation');
[$code]=$saveVariant([]);$items=json_decode(file_get_contents($public.'/menu.json'),true);$v=$items[0]['variants'];
ch_check($code===302&&count($v)===2&&$v[0]['id']['n']==='Cokelat Mocca','variant names and requested order save');
ch_check($items[0]['uid']===$menu[0]['uid']&&$items[0]['slug']===$menu[0]['slug']&&$items[0]['img']===$menu[0]['img'],'variants preserve catalogue and PO product identities');
ch_check($v[0]['en']['n']===$v[0]['id']['n'],'missing English variant falls back to Indonesian');
[$code,$page,$headers]=$http('/menu/kue-uji/');
ch_check($code===200&&str_contains($page,'name="product_variant"')&&str_contains($page,'Vanilla &amp; Strawberry')&&str_contains($page,'Tanyakan varian ini'),'HTML contains variants and no-JS WhatsApp links');
$oldEtag=$headers['etag'];
$doc=new DOMDocument();libxml_use_internal_errors(true);$doc->loadHTML($page);libxml_clear_errors();$xp=new DOMXPath($doc);
$link=$xp->query('//a[contains(@class,"rp-variant-fallback")]')->item(0)->getAttribute('href');parse_str(parse_url($link,PHP_URL_QUERY),$query);
ch_check(str_contains($query['text'],"\nVarian: Cokelat Mocca")&&str_contains($query['text'],'https://ruethiespatisserie.com/menu/kue-uji/'),'no-JS variant link encodes real newlines, variant and canonical');
$roundtrip=['variant_0_uid'=>$v[0]['uid'],'variant_0_n'=>$v[0]['id']['n'],'variant_0_order'=>'0','variant_1_uid'=>$v[1]['uid'],'variant_1_n'=>$v[1]['id']['n'],'variant_1_order'=>'1'];
[$code]=$saveVariant($roundtrip+['variants_enabled'=>'']);$disabled=json_decode(file_get_contents($public.'/menu.json'),true)[0];
ch_check($code===302&&!$disabled['variants_enabled']&&array_column($disabled['variants'],'uid')===array_column($v,'uid'),'disabling retains stable variant IDs and rows');
[$code,$page,$headers]=$http('/menu/kue-uji/');ch_check(!str_contains($page,'id="product-variants"')&&$headers['etag']!==$oldEtag,'disabled variants vanish publicly and invalidate ETag');
[$code,$error]=$saveVariant(array_replace($roundtrip,['variant_1_n'=>' COKELAT MOCCA ']));ch_check($code===200&&str_contains($error,'Nama varian wajib dan tidak boleh sama')&&json_decode(file_get_contents($public.'/menu.json'),true)[0]===$disabled,'duplicate names are rejected without changing data');
[$code,$error]=$saveVariant(array_replace($roundtrip,['variant_1_n'=>'<script>alert(1)</script>']));ch_check(str_contains($error,'teks biasa')&&json_decode(file_get_contents($public.'/menu.json'),true)[0]===$disabled,'HTML input is rejected and old data preserved');
[$code,$error]=$saveVariant(array_replace($roundtrip,['variant_0_uid'=>str_repeat('f',32)]));ch_check(str_contains($error,'Varian sudah berubah'),'foreign variant identity rejected');
$login('staff');[$code]=$saveVariant($roundtrip);ch_check($code===302,'staff keeps existing permission to edit variants');
$login('owner');[, $editor]=$http('/admin/?p=ubah&i=0');$staleRevision=ch_field($editor,'revision');
[$code]=$saveVariant(array_replace($roundtrip,['variant_0_d'=>'Keterangan baru']));
[$code,$error]=$http('/admin/',array_replace($variantPost,$roundtrip,['csrf'=>ch_field($editor,'csrf'),'revision'=>$staleRevision]));ch_check(str_contains($error,'Data sudah berubah'),'stale variant form rejected by catalogue revision');
[$code]=$saveVariant(array_replace($roundtrip,['variant_0_delete'=>'1','variant_1_delete'=>'1']));ch_check($code===200,'active product requires at least one variant');
[$code]=$saveVariant(array_replace($roundtrip,['variant_1_delete'=>'1']));$one=json_decode(file_get_contents($public.'/menu.json'),true)[0];ch_check($code===302&&count($one['variants'])===1&&$one['variants'][0]['uid']===$v[0]['uid'],'explicit delete removes only chosen variant');
$corrupt=$one;$corrupt['variants'][0]['img']='images/menu/../../private.php';$items[0]=$corrupt;file_put_contents($public.'/menu.json',json_encode($items));[$code]=$http('/menu/kue-uji/');ch_check($code===503,'traversal variant image in corrupt data fails closed');
$items[0]=$one;file_put_contents($public.'/menu.json',json_encode($items));
