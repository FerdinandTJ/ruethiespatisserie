<?php
// Uses the existing /admin cookie/session; drafts never get public preview tokens.
define('RUETHIES_ADMIN',true);require __DIR__.'/config.php';require __DIR__.'/lib.php';require __DIR__.'/articles-lib.php';
header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');header('Pragma: no-cache');
try{
 ruethies_session_start();$actor=current_user();if(!$actor){header('Location: /admin/?p=login',true,302);exit;}article_authorize($actor);
 if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');exit;}
 $state=article_read();$id=article_text($_SERVER['REQUEST_METHOD']==='POST'?$_POST:$_GET,'article_id',80);if($id===''&&$_SERVER['REQUEST_METHOD']==='GET')$id=article_text($_GET,'id',80);$row=$id!==''?article_find($state,$id):null;
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!ruethies_csrf_ok()){http_response_code(403);throw new DomainException('Aksi ditolak. Muat ulang formulir.');}security_rate_take('article-preview',$actor['name'],60,false);
  if($id!==''&&!$row)throw new DomainException('Artikel tidak ditemukan.');$now=gmdate('Y-m-d\TH:i:s\Z');$row=$row??['id'=>'preview','createdAt'=>$now,'updatedAt'=>$now,'createdBy'=>$actor['name'],'updatedBy'=>$actor['name'],'publishedAt'=>null,'deletedAt'=>null,'cover'=>''];
  foreach(['title'=>180,'slug'=>100,'description'=>155,'category'=>40,'body'=>60000,'lang'=>2,'translationOf'=>80,'coverAlt'=>180]as$key=>$max)$row[$key]=article_text($_POST,$key,$max);$row['body']=article_input_body($_POST);if($row['slug']==='')$row['slug']=article_slug($row['title']);$row['status']='draft';$row['related']=[];for($i=0;$i<3;$i++){if($path=article_text($_POST,'related'.$i,180))$row['related'][]=$path;}article_validate($row,false,article_categories($state));
 }
 if(!$row||$row['deletedAt']!==null){http_response_code(404);echo 'Artikel tidak ditemukan.';exit;}
 define('RUETHIES_PUBLIC_RENDER',true);require dirname(__DIR__).'/public-lib/seo.php';require dirname(__DIR__).'/public-lib/profile.php';require dirname(__DIR__).'/public-lib/articles-public.php';$template=file_get_contents(RUETHIES_PUBLIC.'/index.html');echo article_detail($template,$row,catalog_snapshot()['menu'],article_published($state),true);
}catch(RuethiesRateLimit $e){http_response_code(429);header('Retry-After: 900');echo 'Terlalu banyak percobaan. Coba lagi nanti.';}catch(DomainException $e){if(http_response_code()<400)http_response_code(400);echo '<!doctype html><meta name="robots" content="noindex"><h1>Pratinjau belum tersedia</h1><p>'.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8').'</p>';}
catch(Throwable $e){http_response_code(503);echo 'Pratinjau sementara tidak tersedia. Coba lagi.';}
