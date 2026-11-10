<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('RUETHIES_ADMIN',true);
require __DIR__.'/config.php';require __DIR__.'/lib.php';require __DIR__.'/po-lib.php';
if(!RUETHIES_STAGING){fwrite(STDERR,"Worker ini hanya untuk staging.\n");exit(1);}
date_default_timezone_set('Asia/Jakarta');
$lock=null;
try{
  $args=array_slice($argv,1);if(count($args)!==1||!in_array($args[0],['--check','--run'],true))throw new DomainException('Gunakan --check atau --run.');
  $config=po_config();$db=po_db();
  po_query($db,'SELECT id FROM po_whatsapp_outbox LIMIT 1');
  if($args[0]==='--check'){$settings=po_wa_settings($config);echo json_encode(['database'=>'ready','manual_checkout'=>po_manual_enabled(),'fonnte_enabled'=>$settings['enabled'],'midtrans_sandbox_enabled'=>po_mt_enabled()],JSON_THROW_ON_ERROR)."\n";exit;}
  $lock=fopen(ruethies_data_dir().'/po-wa-worker.lock','c');if(!$lock)throw new RuntimeException('Worker lock unavailable.');chmod(ruethies_data_dir().'/po-wa-worker.lock',0600);
  if(!flock($lock,LOCK_EX|LOCK_NB)){echo "Worker lain masih berjalan.\n";exit;}
  po_expire($db);
  try{$midtrans=po_mt_worker($db,$config);}catch(Throwable $e){$midtrans=['errors'=>1];}
  try { $summary=po_wa_run($db,$config); } catch(Throwable $e) { $summary=['processed'=>0,'notification_error'=>true]; }
  $summary['midtrans']=$midtrans;
  $path=ruethies_data_dir().'/po-wa-heartbeat.json';$tmp=$path.'.tmp';
  if(file_put_contents($tmp,json_encode(['last_success'=>time(),'summary'=>$summary],JSON_THROW_ON_ERROR),LOCK_EX)===false||!chmod($tmp,0600)||!rename($tmp,$path))throw new RuntimeException('Heartbeat unavailable.');
  echo json_encode($summary,JSON_THROW_ON_ERROR)."\n";
  if(!empty($summary['notification_error']))exit(1);
}catch(Throwable $e){fwrite(STDERR,"Worker gagal. Periksa database, migrasi, konfigurasi privat dan koneksi layanan. Tidak ada token/nomor/payload yang ditampilkan.\n");exit(1);}
finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}}
