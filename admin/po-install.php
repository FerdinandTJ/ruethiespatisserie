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
  $db = po_db();
  foreach (preg_split('/;\s*(?:\n|$)/', file_get_contents(__DIR__ . '/po-schema.sql')) as $sql) {
    if (trim($sql) !== '') $db->exec($sql);
  }
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
      foreach ([['Croissant Butter — Demo',25000,10],['Pain au Chocolat — Demo',32000,8],['Box Pastry — Demo',120000,5]] as [$name,$price,$quota]) {
        po_save_product($db,['slot_id'=>$slot,'name'=>$name,'price'=>$price,'quota'=>$quota,'active'=>1]);
      }
      po_toggle_slot($db,$slot,1);
      print "Jadwal dan tiga produk contoh dibuat. Harga hanya untuk demo.\n";
    }
  }
  po_expire($db);
  print "Buka /admin/po.php untuk pengaturan dan /po.php untuk pelanggan.\n";
} catch (Throwable $e) {
  fwrite(STDERR,"Instalasi gagal. Periksa po-config.php, izin database, dan dukungan PDO MySQL.\n"); exit(1);
}
