<?php
// The only public exception to staging Basic Auth. No sessions or browser callbacks.
define('RUETHIES_ADMIN',true);
require __DIR__.'/admin/config.php';require __DIR__.'/admin/lib.php';require __DIR__.'/admin/po-lib.php';
po_require_staging();header('Content-Type: application/json; charset=UTF-8');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');http_response_code(405);echo '{"error":"method"}';exit;}
try{
  $raw=file_get_contents('php://input',false,null,0,16385);
  if(!is_string($raw)||strlen($raw)>16384){http_response_code(413);echo '{"error":"size"}';exit;}
  po_mt_notification(po_db(),po_mt_settings(po_config()),$raw);
  echo '{"received":true}';
}catch(DomainException $e){http_response_code(403);echo '{"error":"invalid_notification"}';}
catch(Throwable $e){http_response_code(503);echo '{"error":"retry_later"}';}
