<?php
// Isolated HTTP checks: publication storage and sitemap retain both URLs; discovery groups pairs.
$translationSaved=file_get_contents($private.'/articles.json');$translationState=json_decode($translationSaved,true);$translationState['enabled']=true;$translationState['items']=[];$translationState['audit']=[];$translationState['revision']=str_repeat('c',64);
$makeTranslation=static function(string $id,string $lang,string $parent='',string $status='published',string $date='2026-11-19T00:00:00Z'):array{return ['id'=>$id,'slug'=>$id,'title'=>'Title '.$id,'description'=>'Description '.$id,'category'=>'panduan','body'=>"## Guide\n\nA useful article.",'status'=>$status,'createdAt'=>$date,'updatedAt'=>$date,'publishedAt'=>$status==='published'?$date:null,'deletedAt'=>null,'createdBy'=>'owner','updatedBy'=>'owner','cover'=>'','coverAlt'=>'','lang'=>$lang,'translationOf'=>$parent,'related'=>[]];};
$translationState['items'][]=$makeTranslation('paired-en','en','','published','2026-11-20T03:00:00Z');$translationState['items'][0]['related']=['/snack-box-cikarang/'];
$translationState['items'][]=$makeTranslation('paired-id','id','paired-en','published','2026-11-20T03:01:00Z');
$translationState['items'][]=$makeTranslation('english-only','en','','published','2026-11-20T02:00:00Z');
$translationState['items'][]=$makeTranslation('hidden-id','id','english-only','draft');
$translationState['items'][]=$makeTranslation('indonesian-only','id','','published','2026-11-20T01:00:00Z');
$translationState['items'][]=$makeTranslation('hidden-en','en','indonesian-only','draft');
for($i=0;$i<9;$i++)$translationState['items'][]=$makeTranslation('filler-'.$i,'id');
file_put_contents($private.'/articles.json',json_encode($translationState));
try{
 [$code,$list]=$http('anon','/artikel/');$dom=ar_xpath($list);$cards=$dom->query('//article[@data-article-group]');
 at($code===200&&$cards->length===12&&$dom->query('//nav[contains(@class,"article-pagination")]')->length===0,'pagination counts twelve translation families instead of thirteen published records');
 at($dom->query('//article[@data-article-group="paired-en" and @lang="id"]/h2/a[@href="/artikel/paired-id/"]')->length===1,'one Indonesian card is rendered for a published pair without JavaScript');
 at($dom->query('//article[@data-article-group="paired-en"]/template[@data-article-version="en"]//a[@href="/artikel/paired-en/"]')->length===1,'published English variant is available in an inert card template');
 at($dom->query('//article[@data-article-group="english-only" and @lang="en"]')->length===1&&!str_contains($list,'Title hidden-id')&&!str_contains($list,'Title hidden-en'),'missing or draft translations fall back without exposing draft text');
 [$code]=$http('anon','/artikel/?page=2');at($code===404,'grouped pagination does not leave an empty duplicate second page');
 [$code,$filtered]=$http('anon','/artikel/?kategori=panduan&enhanced=1');at($code===200&&ar_xpath($filtered)->query('//article[@data-article-group]')->length===12,'AJAX category responses also group translation pairs');
 [$code,$home]=$http('anon','/');$homeDom=ar_xpath($home);at($code===200&&$homeDom->query('//article[@data-article-group]')->length===3&&$homeDom->query('//article[@data-article-group="paired-en"]')->length===1&&str_contains($home,'article-cards.js'),'home displays three distinct families and loads only the small shared card enhancement');
 [$code,$product]=$http('anon','/snack-box-cikarang/');$productDom=ar_xpath($product);at($code===200&&$productDom->query('//article[@data-article-group="paired-en"]/div/h3/a[@href="/artikel/paired-id/"]')->length===1,'read-also selects a whole translation family even if only its English record marks the product related');
 foreach(['paired-id','paired-en']as$id){[$code,$detail]=$http('anon','/artikel/'.$id.'/');$detailDom=ar_xpath($detail);at($code===200&&$detailDom->query('//link[@rel="canonical"]')->item(0)->getAttribute('href')==='https://ruethiespatisserie.com/artikel/'.$id.'/','each published translation keeps its own canonical URL: '.$id);at($detailDom->query('//section//article[@data-article-group="paired-en"]')->length===0,'more articles excludes the entire current translation family: '.$id);}
 foreach(['paired-id'=>'id','paired-en'=>'en','english-only'=>'en','indonesian-only'=>'id']as$id=>$lang){
  [$code,$detail]=$http('anon','/artikel/'.$id.'/');$node=ar_xpath($detail)->query('//script[@id="profile-data"]')->item(0);$snapshot=json_decode($node->textContent,true);
  at($snapshot['articleLang']===$lang&&($snapshot['articleTranslations'][$lang]??'')==='/artikel/'.$id.'/','detail snapshot keeps URL language as toggle source: '.$id);
  $expected=str_starts_with($id,'paired-')?2:1;
  at(count($snapshot['articleTranslations'])===$expected,'toggle exposes only published paired destinations: '.$id);
  if($expected===2)at(str_contains($detail,'data-article-translation="'.($lang==='id'?'en':'id').'"'),'native counterpart link remains usable without JavaScript: '.$id);
 }
 foreach(['paired-id'=>'id','paired-en'=>'en']as$id=>$lang){
  [$code,$detail]=$http('anon','/artikel/'.$id.'/');$x=ar_xpath($detail);
  at($x->query('//html')->item(0)->getAttribute('lang')===$lang,'initial HTML language matches article without JavaScript: '.$id);
  at($x->query('//meta[@property="og:type"]')->item(0)->getAttribute('content')==='article','article Open Graph uses article type: '.$id);
  foreach(['id'=>'paired-id','en'=>'paired-en']as$alternate=>$slug)at($x->query('//link[@rel="alternate" and @hreflang="'.$alternate.'" and @href="https://ruethiespatisserie.com/artikel/'.$slug.'/"]')->length===1,'reciprocal self and counterpart hreflang: '.$id.' '.$alternate);
  at($x->query('//link[@rel="canonical"]')->item(0)->getAttribute('href')==='https://ruethiespatisserie.com/artikel/'.$id.'/','localized canonical remains self-referencing: '.$id);
  if($lang==='en')at(str_contains($detail,'>Published</span>')&&str_contains($detail,'>Order now</span>'),'English article labels are server rendered, not waiting for JavaScript');
 }
 [$code,$single]=$http('anon','/artikel/english-only/');at(ar_xpath($single)->query('//link[@hreflang]')->length===0,'no alternate URL is fabricated for untranslated article');
 [$code,$xml]=$http('anon','/sitemap.xml');at(str_contains($xml,'/artikel/paired-id/')&&str_contains($xml,'/artikel/paired-en/')&&!str_contains($xml,'/artikel/hidden-id/'),'sitemap retains both published URLs and excludes draft translations');
 [$code,$admin]=$http('owner','/admin/?p=artikel&status=all');at(str_contains($admin,'Title paired-id')&&str_contains($admin,'Title paired-en'),'admin retains separate editable entries for both languages');
 at(file_get_contents($private.'/articles.json')===json_encode($translationState),'public grouping never rewrites the article store');
}finally{file_put_contents($private.'/articles.json',$translationSaved);}
