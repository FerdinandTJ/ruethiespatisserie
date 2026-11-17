<?php
// Additive, idempotent draft seed. Never publishes or edits accounts/CMS data.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('RUETHIES_ADMIN',true);require __DIR__.'/config.php';require __DIR__.'/lib.php';require_once dirname(__DIR__).'/public-lib/articles.php';
try{
 $mode=$argv[1]??'--check';if(count($argv)>2||!in_array($mode,['--check','--apply','--enable','--disable'],true))throw new RuntimeException('Gunakan --check, --apply, --enable, atau --disable.');
 $seeds=json_decode(file_get_contents(dirname(__DIR__).'/content/article-seeds.json'),true,512,JSON_THROW_ON_ERROR);$state=article_read();$missing=array_values(array_filter($seeds,fn($seed)=>article_find($state,$seed['id'])===null));$backup=null;$applied=false;
 if($mode!=='--check')article_lock(true,function()use($mode,$seeds,&$state,&$missing,&$backup,&$applied){$state=article_read_unlocked();$missing=array_values(array_filter($seeds,fn($seed)=>article_find($state,$seed['id'])===null));$before=$state;
  if($mode==='--apply')foreach($missing as$seed){$now=gmdate('Y-m-d\TH:i:s\Z');$state['items'][]=$seed+['status'=>'draft','createdAt'=>$now,'updatedAt'=>$now,'publishedAt'=>null,'createdBy'=>'CLI seed','updatedBy'=>'CLI seed','cover'=>'','coverAlt'=>'','lang'=>'id','translationOf'=>'','deletedAt'=>null];}
  elseif($mode==='--enable'){if(!is_file(article_file()))throw new RuntimeException('Jalankan --apply terlebih dahulu.');$state['enabled']=true;}
  elseif($mode==='--disable')$state['enabled']=false;
  if($state!==$before||!is_file(article_file())&&$mode==='--apply'){$state['revision']=bin2hex(random_bytes(32));$state['audit'][]=['article'=>'','actor'=>'SSH CLI','action'=>$mode,'at'=>gmdate('Y-m-d\TH:i:s\Z')];article_state_validate($state);$backup=article_backup();article_write($state);$applied=true;}
 });
 echo json_encode(['mode'=>$mode,'enabled'=>$state['enabled'],'articles'=>count($state['items']),'seed_drafts_missing'=>$mode==='--check'?count($missing):0,'applied'=>$applied,'backup'=>$backup],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Upgrade artikel gagal. Periksa data, slug duplikat, dan izin direktori privat. Tidak ada akun atau katalog yang diubah.'.PHP_EOL);exit(1);}
