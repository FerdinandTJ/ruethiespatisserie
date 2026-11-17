<?php
// Additive metadata only; no seed, account, publication, cover, or flag changes.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('RUETHIES_ADMIN',true);require __DIR__.'/config.php';require __DIR__.'/lib.php';require __DIR__.'/articles-lib.php';
try{
 $mode=$argv[1]??'--check';if(count($argv)>2||!in_array($mode,['--check','--apply'],true))throw new RuntimeException('Mode tidak valid.');
 if(!is_file(article_file()))throw new RuntimeException('Data artikel belum disiapkan.');
 $result=article_lock($mode==='--apply',static function()use($mode){
  $state=article_read_unlocked();$raw=json_decode(file_get_contents(article_file()),true,512,JSON_THROW_ON_ERROR);$needed=!array_key_exists('categories',$raw);$backup=null;
  if($mode==='--apply'&&$needed){$now=gmdate('Y-m-d\TH:i:s\Z');foreach($state['categories']as&$category)$category['updatedAt']=$now;unset($category);$state['revision']=bin2hex(random_bytes(32));$state['audit'][]=['article'=>'','actor'=>'SSH CLI','action'=>'category-upgrade','at'=>$now];$state['audit']=array_slice($state['audit'],-5000);article_state_validate($state);$backup=article_backup();article_write($state);}
  return ['mode'=>$mode,'categories'=>count($state['categories']),'changes_needed'=>$needed&&$mode==='--check','applied'=>$needed&&$mode==='--apply','backup'=>$backup];
 });echo json_encode($result,JSON_UNESCAPED_SLASHES),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,"Upgrade kategori artikel gagal. Periksa data artikel, referensi kategori dan izin penyimpanan. Tidak ada akun atau katalog yang diubah.\n");exit(1);}
