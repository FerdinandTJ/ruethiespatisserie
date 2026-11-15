<?php
// Hosting-only metadata upgrade; never rewrites products, accounts or PO tables.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('RUETHIES_ADMIN',true);require __DIR__.'/config.php';require __DIR__.'/lib.php';
try{
 $mode=$argv[1]??'--check';if(!in_array($mode,['--check','--apply'],true)||count($argv)>2)throw new RuntimeException('Gunakan --check atau --apply.');
 if($mode==='--apply')content_lock(true);
 $read=static function(){
  $rows=categories_read();$GLOBALS['catalog_category_rows'][catalog_root()]=$rows;$items=catalog_read_json(RUETHIES_MENU);
  $names=catalog_categories();$unknown=[];foreach($items as$item){$key=$item['cat']??null;if(!is_string($key)||!isset($names[$key]))$unknown[]=is_string($key)?$key:'(format tidak valid)';}
  if($unknown)throw new RuntimeException('Kategori tidak dikenal: '.implode(', ',array_unique($unknown)).'. Tinjau menu hosting; tidak ada data yang diubah.');
  catalog_validate($items,false);return $rows;
 };
 // A first read-only preflight must not create even the lock file.
 $rows=is_file(catalog_root().'/.catalog.lock')?catalog_lock_run(false,$read):$read();
 $needed=!is_file(catalog_root().'/categories.json');$backup=null;
 if($mode==='--apply'&&$needed){
  $stamp=gmdate('Y-m-d\TH:i:s\Z');foreach($rows as&$row)$row['updated_at']=$stamp;unset($row);
  $backup=catalog_backup_pair();if(!catalog_atomic_write(catalog_root().'/categories.json',$rows))throw new RuntimeException('Gagal menyimpan kategori. Katalog tidak diubah.');
 }
 echo json_encode(['mode'=>$mode,'categories'=>count($rows),'changes_needed'=>$mode==='--check'&&$needed,'applied'=>$mode==='--apply'&&$needed,'backup'=>$backup],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
