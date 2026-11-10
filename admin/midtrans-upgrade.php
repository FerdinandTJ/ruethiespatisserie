<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('RUETHIES_ADMIN',true);
require __DIR__.'/config.php';require __DIR__.'/lib.php';require __DIR__.'/po-lib.php';
if(!RUETHIES_STAGING){fwrite(STDERR,"Hanya untuk staging.\n");exit(1);}
try{
  if(count($argv)!==2||!in_array($argv[1],['--check','--apply'],true))throw new RuntimeException('Gunakan --check atau --apply.');
  $config=po_mt_settings(po_config());$db=po_db();
  po_query($db,'SELECT id FROM po_orders LIMIT 1');
  if($argv[1]==='--apply')po_mt_migrate($db);
  $ready=(bool)po_query($db,"SHOW TABLES LIKE 'po_midtrans_payments'")->fetch();
  echo json_encode(['environment'=>'sandbox','enabled'=>$config['enabled'],'schema_ready'=>$ready,'applied'=>$argv[1]==='--apply'],JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,"Periksa konfigurasi Sandbox dan database PO. Tidak ada kunci yang ditampilkan.\n");exit(1);}
