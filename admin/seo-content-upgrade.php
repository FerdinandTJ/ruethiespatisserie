<?php
// CLI only. Add Indonesian editorial drafts; never publishes or overwrites an existing article.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('RUETHIES_ADMIN',true);require __DIR__.'/config.php';require_once dirname(__DIR__).'/public-lib/articles.php';
function seo_content_plan(array $state,array $seeds): array {
 $new=[];$skipped=[];$categories=article_categories($state);$now=gmdate('Y-m-d\TH:i:s\Z');
 foreach($seeds as$seed){
  $existing=null;$source=null;$translation=null;
  foreach($state['items']as$row){if($row['id']===$seed['id']||$row['slug']===$seed['slug'])$existing=$row;if($row['slug']===$seed['source_slug']&&$row['lang']==='en'&&$row['deletedAt']===null)$source=$row;}
  if($source)foreach($state['items']as$row)if($row['lang']==='id'&&($row['translationOf']===$source['id']||$source['translationOf']===$row['id']))$translation=$row;
  $reason=$existing?'already_exists':($translation?'translation_already_exists':(!isset($categories[$seed['category']])?'category_missing':(!$source?'english_source_missing':null)));
  if($reason){$skipped[]=['slug'=>$seed['slug'],'reason'=>$reason];continue;}
  $cover=$source['cover'];if($cover!==''&&!article_image_info($cover))$cover='';
  $row=['id'=>$seed['id'],'title'=>$seed['title'],'slug'=>$seed['slug'],'description'=>$seed['description'],'category'=>$seed['category'],'body'=>$seed['body'],'status'=>'draft','createdAt'=>$now,'updatedAt'=>$now,'publishedAt'=>null,'createdBy'=>'Persiapan artikel','updatedBy'=>'Persiapan artikel','deletedAt'=>null,'lang'=>'id','translationOf'=>$source['id'],'related'=>$seed['related'],'cover'=>$cover,'coverAlt'=>$cover!==''?$source['coverAlt']:''];
  article_validate($row,false,$categories);$new[]=$row;
 }
 return ['new'=>$new,'skipped'=>$skipped];
}
try{
 $mode=$argv[1]??'--check';if(count($argv)>2||!in_array($mode,['--check','--apply'],true))throw new DomainException('Gunakan --check atau --apply.');
 $seeds=require __DIR__.'/seo-local-article-seeds.php';
 $backup=null;$applied=false;$state=article_read();$plan=seo_content_plan($state,$seeds);
 if($mode==='--apply')article_lock(true,function()use($seeds,&$state,&$plan,&$backup,&$applied){
  $state=article_read_unlocked();$plan=seo_content_plan($state,$seeds);if(!$plan['new'])return;
  foreach($plan['new']as$row){$state['items'][]=$row;$state['audit'][]=['article'=>$row['id'],'actor'=>'SSH CLI','action'=>'seo-create-draft','at'=>$row['createdAt']];}
  $state['audit']=array_slice($state['audit'],-5000);$state['revision']=bin2hex(random_bytes(32));article_state_validate($state);$backup=article_backup();article_write($state);$applied=true;
 });
 echo json_encode(['mode'=>$mode,'drafts'=>array_column($plan['new'],'slug'),'skipped'=>$plan['skipped'],'applied'=>$applied,'enabled_unchanged'=>$state['enabled'],'backup'=>$backup],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,"Persiapan draft SEO gagal. Periksa data artikel, kategori dan izin penyimpanan. Artikel terbit, akun dan katalog tidak ditimpa.\n");exit(1);}
