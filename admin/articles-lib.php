<?php
defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.');
require_once dirname(__DIR__).'/public-lib/articles.php';
function article_authorize(?array $actor,bool $owner=false): void{if(!$actor||!in_array($actor['role']??null,['owner','staff'],true)||$owner&&$actor['role']!=='owner'){http_response_code(403);throw new DomainException('Anda tidak memiliki izin untuk aksi artikel ini.');}}
function article_remove_new_cover(string $cover): void {
 foreach([$cover,article_thumbnail_path($cover,480),article_thumbnail_path($cover,800)]as$path)if(!is_link(RUETHIES_PUBLIC.'/'.$path))@unlink(RUETHIES_PUBLIC.'/'.$path);
}
// The decoded upload is reused; detail images are never resized or recompressed here.
function article_make_thumbnails(GdImage $image,string $cover): int {
 $created=[];$width=imagesx($image);$height=imagesy($image);
 try{foreach([480,800]as$targetWidth){
  if($width<=$targetWidth)continue;$path=article_thumbnail_path($cover,$targetWidth);$file=RUETHIES_PUBLIC.'/'.$path;
  if(is_link($file))throw new RuntimeException('Thumbnail tidak dapat disimpan.');
  if(is_file($file)){if(!article_image_info($path))throw new RuntimeException('Thumbnail tidak valid.');continue;}
  $targetHeight=max(2,(int)round($height*$targetWidth/$width));$thumb=imagecreatetruecolor($targetWidth,$targetHeight);
  imagealphablending($thumb,false);imagesavealpha($thumb,true);$tmp=$file.'.tmp.'.bin2hex(random_bytes(8));
  try{if(!imagecopyresampled($thumb,$image,0,0,0,0,$targetWidth,$targetHeight,$width,$height)||!imagewebp($thumb,$tmp,86)||!chmod($tmp,0644)||!rename($tmp,$file))throw new RuntimeException('Thumbnail tidak dapat disimpan.');$created[]=$file;}
  finally{unset($thumb);if(is_file($tmp))@unlink($tmp);}
 }return count($created);}catch(Throwable $e){foreach($created as$file)@unlink($file);throw $e;}
}
function article_cover(array $file,array $actor,string $base64=''): string{
 if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE&&$base64==='')return '';
 security_rate_take('article-upload',$actor['name'],20,false);
 if($base64!==''){
  if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE||strlen($base64)>2796400||!preg_match('~\Adata:image/(?:jpeg|png|webp);base64,([A-Za-z0-9+/=]+)\z~D',$base64,$match)||($raw=base64_decode($match[1],true))===false)throw new DomainException('Data foto sampul tidak valid.');
 }else{
  security_upload_input([$file]);if($file['error']!==UPLOAD_ERR_OK||$file['size']>2*1024*1024||!is_uploaded_file($file['tmp_name']))throw new DomainException('Sampul harus JPG/PNG/WebP, maksimal 2 MB.');
  $raw=@file_get_contents($file['tmp_name'],false,null,0,2*1024*1024+1);if($raw===false)throw new DomainException('Foto sampul tidak dapat dibaca.');
 }
 [$image,$error]=security_image_decode($raw,2*1024*1024);if($error)throw new DomainException($error);
 $dir=RUETHIES_PUBLIC.'/images/articles';if(is_link(RUETHIES_PUBLIC.'/images')||is_link($dir)||!is_dir($dir)&&!@mkdir($dir,0755,true)){unset($image);throw new RuntimeException('Direktori sampul tidak tersedia.');}
 $path='images/articles/'.bin2hex(random_bytes(16)).'.webp';try{if(!function_exists('imagewebp')||!imagewebp($image,RUETHIES_PUBLIC.'/'.$path,86)||!chmod(RUETHIES_PUBLIC.'/'.$path,0644))throw new RuntimeException('Sampul tidak dapat disimpan.');article_make_thumbnails($image,$path);}catch(Throwable $e){article_remove_new_cover($path);throw $e;}finally{unset($image);}return $path;
}
function article_mutate(array $actor,array $input,array $files=[]): string{
 article_authorize($actor);if(!ruethies_csrf_ok()){http_response_code(403);throw new DomainException('Aksi ditolak. Muat ulang formulir.');}
 $action=article_text($input,'article_action',20);if(!in_array($action,['save','publish','unpublish','delete','restore'],true))throw new DomainException('Aksi artikel tidak dikenal.');
 article_authorize($actor,in_array($action,['delete','restore'],true));security_rate_take('article-write',$actor['name'],60,false);
 $createdCover='';$id='';
 try{$id=security_lock('users',function()use($actor,$input,$files,$action,&$createdCover){security_actor(users_all(),$actor,in_array($action,['delete','restore'],true));return article_lock(true,function()use($actor,$input,$files,$action,&$createdCover){
  $state=article_read_unlocked();$revision=article_text($input,'revision',64);if(!hash_equals($state['revision'],$revision))throw new DomainException('Artikel sudah berubah. Muat ulang halaman sebelum menyimpan.');
  $id=article_text($input,'article_id',80);$row=$id!==''?article_find($state,$id):null;if($id!==''&&!$row)throw new DomainException('Artikel tidak ditemukan.');
  $now=gmdate('Y-m-d\TH:i:s\Z');
  if($action==='save'){
   if($row&&$row['deletedAt']!==null)throw new DomainException('Pulihkan artikel terlebih dahulu.');
   $row=$row??['id'=>'article-'.bin2hex(random_bytes(12)),'status'=>'draft','createdAt'=>$now,'updatedAt'=>$now,'updatedBy'=>$actor['name'],'publishedAt'=>null,'createdBy'=>$actor['name'],'cover'=>'','deletedAt'=>null];
   $title=article_text($input,'title',180);$slug=article_text($input,'slug',100);$related=[];for($i=0;$i<3;$i++){$url=article_text($input,'related'.$i,180);if($url!=='')$related[]=$url;}
   $row=array_merge($row,['title'=>$title,'slug'=>$slug!==''?$slug:article_slug($title),'description'=>article_text($input,'description',155),'category'=>article_text($input,'category',40),'body'=>article_text($input,'body',60000),'lang'=>article_text($input,'lang',2),'translationOf'=>article_text($input,'translationOf',80),'coverAlt'=>article_text($input,'coverAlt',180),'related'=>array_values(array_unique($related))]);
   if(($input['remove_cover']??'')==='yes')$row['cover']='';
   $validPaths=['/','/menu/','/snack-box-cikarang/','/tentang-kami/','/hubungi-kami/','/cara-pesan/'];foreach(catalog_snapshot()['menu']as$product)if(($product['available']??true)!==false)$validPaths[]=catalog_path($product);foreach($related as$url)if(!in_array($url,$validPaths,true))throw new DomainException('Produk/halaman terkait sudah tidak tersedia.');
   // Validate before decoding a new cover; preserve the existing path on errors.
   $upload=$files['article_cover']??['error'=>UPLOAD_ERR_NO_FILE];$base64=security_input($input,'article_cover_b64',2796400);if((($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE||$base64!=='')&&$row['coverAlt']==='')throw new DomainException('Alt foto sampul wajib diisi.');
   article_validate($row);$path=article_cover($upload,$actor,$base64);if($path!==''){$row['cover']=$path;$createdCover=$path;}
  }else{
   if(!$row)throw new DomainException('Simpan draft terlebih dahulu.');if(($input['confirm']??'')!=='yes')throw new DomainException('Konfirmasi aksi diperlukan.');
   if($action==='restore'){if($row['deletedAt']===null)throw new DomainException('Artikel tidak dihapus.');$row['deletedAt']=null;$row['status']='draft';}
   elseif($row['deletedAt']!==null)throw new DomainException('Pulihkan artikel terlebih dahulu.');
   elseif($action==='publish'){$row['status']='published';$row['publishedAt']=$row['publishedAt']??$now;}
   elseif($action==='unpublish')$row['status']='draft';
   elseif($action==='delete'){$row['status']='draft';$row['deletedAt']=$now;}
  }
  $row['updatedAt']=$now;$row['updatedBy']=$actor['name'];article_validate($row);
  $index=null;foreach($state['items']as$i=>$old)if($old['id']===$row['id'])$index=$i;if($index===null)$state['items'][]=$row;else $state['items'][$index]=$row;
  try{article_state_validate($state);}catch(RuntimeException $e){throw new DomainException('Slug harus unik dan referensi terjemahan harus valid. Slug artikel terhapus tetap dicadangkan.');}
  $state['audit'][]=['article'=>$row['id'],'actor'=>$actor['name'],'action'=>$action,'at'=>$now];$state['audit']=array_slice($state['audit'],-5000);$state['revision']=bin2hex(random_bytes(32));article_backup();article_write($state);return $row['id'];
 });});}catch(Throwable $e){if($createdCover!=='')article_remove_new_cover($createdCover);throw $e;}
 try{ruethies_log($actor['name'],'article-'.$action,$id);}catch(RuntimeException $e){error_log('Ruethies: log artikel tersimpan pada audit privat.');}return $id;
}
