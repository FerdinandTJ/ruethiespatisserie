<?php
// Tidak dapat dijalankan lewat web. Membuat tabel saja; tidak menghapus data.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('RUETHIES_ADMIN', true);
require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';
require __DIR__ . '/po-lib.php';
if (!RUETHIES_STAGING) { fwrite(STDERR,"Installer PO demo hanya untuk public_html/staging.\n"); exit(1); }
date_default_timezone_set('Asia/Jakarta');
try {
  content_lock();
  $db = po_db();
  foreach (explode(';', file_get_contents(__DIR__ . '/po-schema.sql')) as $sql) {
    if (trim($sql) !== '') $db->exec($sql);
  }
  po_migrate_catalog($db);
  $menu = jread(RUETHIES_MENU, null);
  if (!is_array($menu) || !array_is_list($menu) || array_filter($menu, fn($m)=>!is_array($m))) throw new RuntimeException('Katalog menu tidak valid.');
  $withIds = menu_with_ids($menu);
  if ($menu !== $withIds && !menu_save($withIds)) throw new RuntimeException('Gagal menyimpan UID katalog.');
  print "Migrasi katalog selesai. ID menu dan referensi PO siap.\n";
  print "Tabel PO siap. Data yang sudah ada dipertahankan.\n";
  if (in_array('--seed-demo', $argv, true)) {
    if ((int)$db->query('SELECT COUNT(*) FROM po_slots')->fetchColumn() !== 0) {
      print "Seed dilewati: jadwal sudah ada.\n";
    } else {
      $slot = po_save_slot($db, [
        'title'=>'PO Presentasi — Data Contoh',
        'opens_at'=>date('Y-m-d\TH:i',time()-3600),
        'closes_at'=>date('Y-m-d\TH:i',strtotime('+1 day 20:00')),
        'pickup_from'=>date('Y-m-d\TH:i',strtotime('+2 days 09:00')),
        'pickup_until'=>date('Y-m-d\TH:i',strtotime('+2 days 17:00')),
      ]);
      foreach (array_slice(array_filter(po_catalog(), 'po_catalog_visible'),0,3,true) as $uid=>$menu) {
        po_save_product($db,['slot_id'=>$slot,'menu_uid'=>$uid,'price'=>25000,'quota'=>10,'active'=>1]);
      }
      po_toggle_slot($db,$slot,1);
      print "Jadwal contoh dibuat dari maksimal tiga menu katalog. Harga Rp25.000 hanya untuk demo.\n";
    }
  }
  po_expire($db);
  print "Buka /admin/po.php untuk pengaturan dan /pre-order/ untuk pelanggan.\n";
} catch (Throwable $e) {
  fwrite(STDERR,"Instalasi gagal. Periksa konfigurasi, izin database, dan menu.json.\n");
  if ($e instanceof PDOException) fwrite(STDERR,'SQLSTATE: '.$e->getCode().' | MySQL: '.($e->errorInfo[1] ?? '-')."\n");
  else fwrite(STDERR, get_class($e) . "\n");
  exit(1);
}
