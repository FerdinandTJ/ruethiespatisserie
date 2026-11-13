<?php
// Targeted owner-approved copy only. Never replaces hosting photos, catalogue, or identities.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('RUETHIES_ADMIN',true);require __DIR__.'/config.php';require __DIR__.'/lib.php';
try{
 $mode=$argv[1]??'--check';if(count($argv)!==2||!in_array($mode,['--check','--apply'],true))throw new RuntimeException('Gunakan --check atau --apply.');
 if($mode==='--apply')content_lock();
 $items=catalog_read_json(RUETHIES_MENU);catalog_validate($items,false);catalog_validate(catalog_metadata($items,(int)filemtime(RUETHIES_MENU)));
 $patch=catalog_read_json(RUETHIES_PUBLIC.'/content/menu-copy-20261113.json');$new=$items;$changed=[];$missing=[];
 foreach($patch as$copy){
  if(!is_array($copy)||!is_string($copy['slug']??null)||!is_array($copy['aliases']??null))throw new RuntimeException('Patch tidak valid.');
  foreach(['id','en']as$lang)foreach(['d','detail']as$field)if(!is_string($copy[$lang][$field]??null)||mb_strlen($copy[$lang][$field])>4000)throw new RuntimeException('Teks patch tidak valid.');
  $matches=[];foreach($items as$i=>$item)if(in_array($item['id']['n'],$copy['aliases'],true))$matches[]=$i;
  if(!$matches){$missing[]=$copy['slug'];continue;}
  if(count($matches)!==1)throw new RuntimeException('Nama produk ambigu: '.$copy['slug']);
  $i=$matches[0];$before=$new[$i];
  foreach(['id','en']as$lang){$new[$i][$lang]=array_replace($new[$i][$lang]??[],$copy[$lang]);if(!isset($new[$i][$lang]['n']))$new[$i][$lang]['n']=$before['id']['n'];}
  // Seed only a missing old slug before renaming; an existing hosting slug is permanent.
  if($copy['slug']==='lapis-surabaya'&&!isset($new[$i]['slug']))$new[$i]['slug']='lapis-surabaya';
  if($new[$i]!==$before){if(isset($new[$i]['updated_at']))$new[$i]['updated_at']=gmdate('Y-m-d\TH:i:s\Z');$changed[]=['slug'=>$new[$i]['slug']??$copy['slug'],'before'=>$before['id']['n'],'after'=>$new[$i]['id']['n']];}
 }
 catalog_validate($new,false);catalog_validate(catalog_metadata($new,time()));$backup=null;
 if($mode==='--apply'&&$changed){
  $dir=ruethies_data_dir().'/backups';if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Direktori backup gagal.');
  $backup=$dir.'/menu-before-copy-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
  if(!copy(RUETHIES_MENU,$backup)||!chmod($backup,0600))throw new RuntimeException('Backup gagal; katalog belum diubah.');
  if(!catalog_atomic_write(RUETHIES_MENU,$new))throw new RuntimeException('Penyimpanan gagal; gunakan backup.');
 }
 echo json_encode(['mode'=>$mode,'changes_needed'=>$mode==='--check'?count($changed):0,'applied'=>$mode==='--apply'?count($changed):0,'products'=>$changed,'unmatched'=>$missing,'backup'=>$backup],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
