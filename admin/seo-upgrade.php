<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('RUETHIES_ADMIN',true);
require __DIR__.'/config.php';
require __DIR__.'/lib.php';
try {
  $args=array_slice($argv,1);
  if (count($args)!==1 || !in_array($args[0],['--check','--apply'],true)) throw new RuntimeException('Gunakan php admin/seo-upgrade.php --check atau --apply');
  if ($args[0]==='--apply') content_lock();
  $items=catalog_read_json(RUETHIES_MENU); catalog_validate($items,false);
  $new=catalog_metadata($items,(int)filemtime(RUETHIES_MENU)); catalog_validate($new);
  $changes=$new!==$items; $backup=null;
  if ($args[0]==='--apply' && $changes) {
    $directory=ruethies_data_dir().'/backups';
    if (!is_dir($directory) && !mkdir($directory,0700,true)) throw new RuntimeException('Backup tidak dapat disiapkan.');
    $backup=$directory.'/menu-before-seo-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
    if (!copy(RUETHIES_MENU,$backup) || !chmod($backup,0600)) throw new RuntimeException('Backup gagal; menu belum diubah.');
    if (!catalog_atomic_write(RUETHIES_MENU,$new)) throw new RuntimeException('Penyimpanan gagal; gunakan backup menu.');
  }
  echo json_encode(['mode'=>$args[0],'products'=>count($items),'changes_needed'=>$args[0]==='--check' && $changes,'applied'=>$args[0]==='--apply' && $changes,'backup'=>$backup],JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
