<?php
// Pure rendering checks: no hosting account, database, or provider calls.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('RUETHIES_PUBLIC_RENDER',true);require dirname(__DIR__).'/public-lib/seo.php';
function sm_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
$template=file_get_contents(dirname(__DIR__).'/index.html');$bakery=seo_business($template);
sm_check($bakery['@type']==='Bakery'&&!isset($bakery['areaServed'])&&$bakery['menu']===CATALOG_ORIGIN.'/menu/','business has catalogue URL and no unconfirmed delivery coverage');
$schemas=seo_entity_schemas([$bakery,['@type'=>'Article','headline'=>'English guide']],'/artikel/english-guide/','English guide','en');$byType=array_column($schemas,null,'@type');
sm_check($byType['WebSite']['publisher']['@id']===$bakery['@id']&&$byType['Article']['publisher']['@id']===$bakery['@id'],'website and article share the existing business identity');
sm_check($byType['Article']['mainEntityOfPage']['@id']===$byType['WebPage']['@id']&&$byType['WebPage']['inLanguage']==='en','article main entity resolves to its localized WebPage');
[$title,$desc]=seo_page_metadata('/artikel/english-guide/','CMS title','CMS description',['articlePage'=>true]);sm_check($title==='CMS title'&&$desc==='CMS description','article metadata preserves CMS editorial content');
$items=json_decode(file_get_contents(dirname(__DIR__).'/menu.json'),true);$titles=[];$descriptions=[];
foreach($items as$m){$m['slug']=$m['slug']??strtolower(preg_replace('/[^a-z0-9]+/i','-',$m['id']['n']));[$t,$d]=seo_page_metadata('/menu/'.$m['slug'].'/','old','old',['product'=>$m]);$titles[]=$t;$descriptions[]=$d;sm_check(mb_strlen($d)<=160&&str_contains($t,'Cikarang'),'product has bounded local metadata: '.$m['id']['n']);}
sm_check(count($titles)===count(array_unique($titles))&&count($descriptions)===count(array_unique($descriptions)),'catalogue titles and descriptions are unique');
[$title,$description]=seo_page_metadata('/menu/test/','old','old',['product'=>['slug'=>'test','id'=>['n'=>'Kue dari CMS','d'=>'Deskripsi baru dari editor.']]]);sm_check(str_contains($title,'Kue dari CMS')&&str_contains($description,'Deskripsi baru dari editor.'),'product metadata reflects CMS changes');
$html='<p data-id="Pesan" data-en="Order &amp; ask">Pesan</p><p data-id="Aman" data-en="&lt;script&gt;">Aman</p>';$en=seo_localized_labels($html,'en');sm_check(str_contains($en,'>Order &amp; ask</p>')&&str_contains($en,'>&lt;script&gt;</p>')&&!str_contains($en,'<script>'),'server language labels remain escaped');
sm_check(seo_localized_labels($html,'id')===$html,'Indonesian initial markup remains unchanged');
