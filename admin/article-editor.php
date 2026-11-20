<?php
// Conversion only; same session, role, CSRF and limiter as article preview. Never saves.
define('RUETHIES_ADMIN',true);require __DIR__.'/config.php';require __DIR__.'/lib.php';require __DIR__.'/articles-lib.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');
try{
 ruethies_session_start();$actor=current_user();article_authorize($actor);
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);header('Allow: POST');throw new DomainException('Gunakan formulir artikel.');}
 if(!ruethies_csrf_ok()){http_response_code(403);throw new DomainException('Muat ulang halaman sebelum melanjutkan.');}
 security_rate_take('article-editor',$actor['name'],120,false);
 $body=article_input_body($_POST);echo json_encode(['body'=>$body,'html'=>article_markdown($body)['html']],JSON_THROW_ON_ERROR);
}catch(RuethiesRateLimit $e){http_response_code(429);echo json_encode(['error'=>'Terlalu banyak percobaan. Tunggu sebentar.']);}
catch(DomainException $e){if(http_response_code()<400)http_response_code(400);echo json_encode(['error'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(503);echo json_encode(['error'=>'Tulisan belum bisa diproses. Belum ada perubahan yang disimpan.']);}
