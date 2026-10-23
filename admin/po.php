<?php
define('RUETHIES_ADMIN', true);
require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';
require __DIR__ . '/po-lib.php';
po_require_staging();
session_cache_limiter('');
ruethies_session_start();
$me = current_user();
if (!$me) { header('Location: index.php?p=login'); exit; }
$error = ''; $ready = false; $slots = []; $products = []; $orders = []; $selected = null; $edit = null;
$notice = $_SESSION['po_notice'] ?? ''; unset($_SESSION['po_notice']);
$sid = max(0, (int)filter_var($_GET['slot'] ?? 0, FILTER_VALIDATE_INT));
$status = is_string($_GET['status'] ?? null) && isset(PO_STATUSES[$_GET['status']]) ? $_GET['status'] : '';
$search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']),0,80) : '';
$page = max(1, min(10000, (int)filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT)));
$total = 0; $counts = [];
$catalog = []; $choices = [];
try {
  $db = po_db();
  $catalog = po_catalog();
  po_expire($db);
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!ruethies_csrf_ok()) throw new DomainException('Sesi formulir tidak valid. Muat ulang halaman.');
    $action = $_POST['action'] ?? '';
    if ($action === 'transition') {
      po_transition($db, po_int($_POST['order_id'] ?? 0,1,PHP_INT_MAX), po_text($_POST['expected'] ?? '', 'Status',1,16), po_text($_POST['target'] ?? '', 'Status tujuan',1,16), $me['name']);
      $_SESSION['po_notice'] = 'Status pesanan diperbarui.';
    } else {
      if (!is_owner($me)) throw new DomainException('Pengaturan jadwal dan produk hanya untuk pemilik.');
      if ($action === 'save-slot') { $sid = po_save_slot($db, $_POST); $_SESSION['po_notice'] = 'Jadwal disimpan. Lengkapi produk, lalu buka PO.'; }
      elseif ($action === 'toggle-slot') {
        $sid = po_int($_POST['slot_id'] ?? 0,1,PHP_INT_MAX);
        po_toggle_slot($db, $sid, po_int($_POST['is_open'] ?? 0,0,1));
        $_SESSION['po_notice'] = 'Pengaturan buka/tutup diperbarui.';
      } elseif ($action === 'save-product') {
        po_save_product($db, $_POST); $sid = (int)$_POST['slot_id']; $_SESSION['po_notice'] = 'Produk PO disimpan. Pesanan lama tetap memakai harga yang tercatat saat pengajuan.';
      } else throw new DomainException('Tindakan tidak valid.');
    }
    header('Location: po.php' . ($sid ? '?slot=' . $sid : ''), true, 303); exit;
  }
  $ready = true;
} catch (DomainException $e) { $error = $e->getMessage(); $ready = true; }
catch (Throwable $e) { http_response_code(503); $error = 'Database PO belum siap atau sedang bermasalah. Periksa konfigurasi privat dan jalankan installer CLI sesuai panduan.'; error_log('PO admin: storage unavailable'); }
if ($ready) {
  try {
    $slots = po_query($db, 'SELECT * FROM po_slots ORDER BY pickup_from DESC LIMIT 100')->fetchAll();
    foreach ($slots as $s) if ((int)$s['id'] === $sid) $selected = $s;
    if ($selected) $products = po_query($db, 'SELECT * FROM po_products WHERE slot_id=? ORDER BY id', [$sid])->fetchAll();
    $used = array_column($products,'menu_uid');
    $choices = array_filter($catalog, fn($m)=>po_catalog_visible($m) && !in_array($m['uid'],$used,true));
    if (isset($_GET['edit']) && $selected) $edit = $selected;
    $where = []; $args = [];
    if ($status) { $where[] = 'o.status=?'; $args[] = $status; }
    if ($sid) { $where[] = 'o.slot_id=?'; $args[] = $sid; }
    if ($search !== '') { $where[] = '(o.code LIKE ? OR o.customer_name LIKE ? OR o.phone LIKE ?)'; array_push($args, '%'.$search.'%', '%'.$search.'%', '%'.$search.'%'); }
    $filter = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $total = (int)po_query($db, 'SELECT COUNT(*) FROM po_orders o' . $filter, $args)->fetchColumn();
    $orders = po_query($db, 'SELECT o.*,s.title,s.pickup_from,s.pickup_until FROM po_orders o JOIN po_slots s ON s.id=o.slot_id' . $filter . ' ORDER BY o.id DESC LIMIT 25 OFFSET ' . (($page-1)*25), $args)->fetchAll();
    $counts = po_query($db, 'SELECT status,COUNT(*) AS n FROM po_orders GROUP BY status')->fetchAll();
  } catch (Throwable $e) { $ready = false; $error = 'Daftar PO belum dapat dimuat. Periksa instalasi database.'; }
}
$csrf = ruethies_csrf();
function po_datetime_value(?array $row, string $key): string { return $row ? date('Y-m-d\TH:i',(int)$row[$key]) : ''; }
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>PO Demo — Ruethies Admin</title><link rel="stylesheet" href="admin.css?v=1"><link rel="stylesheet" href="po-admin.css?v=1"><script src="admin.js?v=1" defer></script></head>
<body><div class="shell"><aside class="side"><div class="mark"><span class="sealmini">N°51</span><div><b>Ruethies Admin</b><span>DEMO PRE-ORDER</span></div></div><nav class="nav"><a href="index.php">← Menu & galeri</a><a class="on" href="po.php">Pre-order</a><a href="../po.php" target="_blank" rel="noopener">Lihat halaman pelanggan ↗</a></nav><div class="who"><b><?= h($me['name']) ?></b><small><?= is_owner($me) ? 'Pemilik' : 'Staf' ?></small></div></aside>
<div class="maincol"><main class="main"><div class="mnav"><a href="index.php">Menu & galeri</a><a class="on" href="po.php">PO</a><a href="../po.php" target="_blank" rel="noopener">Lihat demo ↗</a></div>
<div class="pagehead"><div><h1>Pre-order · Demo</h1><p class="sub">Ambil di toko. Konfirmasi manual. Belum ada pencatatan pembayaran.</p></div><a class="btn" href="../po.php" target="_blank" rel="noopener">Halaman pelanggan ↗</a></div>
<p class="warn">Lingkungan presentasi: gunakan data contoh. Tidak ada notifikasi WhatsApp otomatis atau pembayaran nyata.</p>
<?php if ($error): ?><p class="err" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($notice): ?><p class="okmsg" role="status"><?= h($notice) ?></p><?php endif; ?>
<?php if ($ready): ?>
<div class="po-counts"><?php foreach ($counts as $count): ?><span class="badge"><?= h(PO_STATUSES[$count['status']] ?? $count['status']) ?>: <?= (int)$count['n'] ?></span><?php endforeach; ?></div>
<section class="card"><h2>Jadwal PO</h2><p class="sub">Admin menentukan kapan pengajuan dibuka, ditutup, dan waktu pengambilan. Pending belum mengurangi kuota.</p>
<?php if (!$slots): ?><p class="empty">Belum ada jadwal. Buat jadwal pertama di bawah.</p><?php endif; ?>
<?php foreach ($slots as $s): ?><div class="item"><div class="grow"><b><?= h($s['title']) ?></b><span class="badge"><?= po_slot_open($s) ? 'Sedang dibuka' : ((bool)$s['is_open'] ? 'Di luar waktu buka' : 'Ditutup admin') ?></span><br><small>Pengajuan <?= h(po_date((int)$s['opens_at'])) ?> – <?= h(po_date((int)$s['closes_at'])) ?><br>Ambil <?= h(po_date((int)$s['pickup_from'])) ?> – <?= h(po_date((int)$s['pickup_until'])) ?></small></div>
<div class="actions"><a class="btn ghost" href="?slot=<?= (int)$s['id'] ?>#products">Produk & pesanan</a>
<?php if (is_owner($me)): ?><a class="btn ghost" href="?slot=<?= (int)$s['id'] ?>&edit=1#schedule">Ubah jadwal</a><form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="toggle-slot"><input type="hidden" name="slot_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="is_open" value="<?= $s['is_open'] ? 0 : 1 ?>"><button class="ghost"><?= $s['is_open'] ? 'Tutup PO' : 'Buka PO' ?></button></form><?php endif; ?></div></div><?php endforeach; ?></section>

<?php if (is_owner($me)): ?><section class="card" id="schedule"><h2><?= $edit ? 'Ubah jadwal' : 'Buat jadwal baru' ?></h2><p class="sub">Semua waktu WIB. Setelah ada pesanan, tanggal tidak bisa diubah; buka/tutup tetap tersedia.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="save-slot"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
<label>Nama periode<input name="title" required minlength="3" maxlength="100" placeholder="PO Weekend — Sabtu" value="<?= h($edit['title'] ?? '') ?>"></label>
<div class="fgrid"><label>Mulai menerima pengajuan<input type="datetime-local" name="opens_at" required value="<?= h(po_datetime_value($edit,'opens_at')) ?>"></label><label>Batas pengajuan<input type="datetime-local" name="closes_at" required value="<?= h(po_datetime_value($edit,'closes_at')) ?>"></label><label>Mulai pengambilan<input type="datetime-local" name="pickup_from" required value="<?= h(po_datetime_value($edit,'pickup_from')) ?>"></label><label>Selesai pengambilan<input type="datetime-local" name="pickup_until" required value="<?= h(po_datetime_value($edit,'pickup_until')) ?>"></label></div><button>Simpan jadwal</button></form></section><?php endif; ?>

<?php if ($selected): ?><section class="card" id="products"><h2>Produk — <?= h($selected['title']) ?></h2><p class="sub">Pilih dari menu website. Nama dan foto mengikuti katalog; harga dan kuota disimpan khusus jadwal ini. Perubahan harga tidak mengubah pesanan yang sudah masuk.</p>
<?php foreach ($products as $product): $source = $catalog[$product['menu_uid'] ?? ''] ?? null; $visible = $source && po_catalog_visible($source); ?>
<?php if (is_owner($me)): ?><form method="post" class="po-product-form"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="save-product"><input type="hidden" name="slot_id" value="<?= $sid ?>"><input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
<h3><?= h($source['id']['n'] ?? $product['name']) ?></h3>
<?php if ($product['menu_uid']): ?><input type="hidden" name="menu_uid" value="<?= h($product['menu_uid']) ?>"><p class="sub">Terhubung ke katalog. Ubah nama/deskripsi/foto melalui Kelola Menu.</p>
<?php if (!$visible): ?><p class="warn">Menu disembunyikan atau sudah dihapus dari katalog. Pengajuan baru dihentikan; pesanan lama tetap dapat diproses.</p><?php endif; ?>
<?php else: ?><p class="warn">Produk manual lama tidak menerima pengajuan baru. Hubungkan ke katalog hanya jika belum punya pesanan; jika sudah, nonaktifkan lalu tambahkan produk katalog baru.</p><label>Hubungkan ke menu<select name="menu_uid"><option value="">Tidak terhubung (nonaktif)</option><?php foreach ($choices as $uid=>$m): ?><option value="<?= h($uid) ?>"><?= h(($m['cat'] ?? '') . ' — ' . $m['id']['n']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
<div class="fgrid"><label>Harga PO (Rp)<input type="number" name="price" min="1000" max="10000000" required value="<?= (int)$product['price'] ?>"></label><label>Kuota total<input type="number" name="quota" min="<?= max(1,(int)$product['reserved']) ?>" max="10000" required value="<?= (int)$product['quota'] ?>"></label></div><label><input type="checkbox" name="active" value="1" <?= $product['active'] && $visible ? 'checked' : '' ?>>Aktif untuk PO</label><p class="sub">Kuota terpakai: <?= (int)$product['reserved'] ?> · Sisa: <?= (int)$product['quota']-(int)$product['reserved'] ?></p><button class="ghost">Simpan produk</button></form>
<?php else: ?><p><?= h($source['id']['n'] ?? $product['name']) ?> · <?= h(po_money((int)$product['price'])) ?> · <?= (int)$product['reserved'] ?>/<?= (int)$product['quota'] ?> kuota terpakai</p><?php endif; ?>
<?php endforeach; ?>
<?php if (is_owner($me)): ?><h3>Tambah dari katalog menu</h3><form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="save-product"><input type="hidden" name="slot_id" value="<?= $sid ?>"><label>Pilih menu<select name="menu_uid" required><option value="">Pilih produk yang ditampilkan di website</option><?php foreach ($choices as $uid=>$m): ?><option value="<?= h($uid) ?>"><?= h(($m['cat'] ?? '') . ' — ' . $m['id']['n']) ?></option><?php endforeach; ?></select></label><?php if (!$choices): ?><p class="sub">Semua menu yang tersedia sudah dipilih, atau belum ada menu yang ditampilkan. Kelola katalog melalui halaman Menu.</p><?php endif; ?><div class="fgrid"><label>Harga PO (Rp)<input type="number" name="price" min="1000" max="10000000" required></label><label>Kuota total<input type="number" name="quota" min="1" max="10000" required></label></div><input type="hidden" name="active" value="1"><button <?= !$choices ? 'disabled' : '' ?>>Tambah ke jadwal PO</button></form><?php endif; ?></section><?php endif; ?>

<section class="card" id="orders"><h2>Pengajuan masuk</h2><form method="get" class="po-filter"><input type="hidden" name="slot" value="<?= $sid ?>"><label>Status<select name="status"><option value="">Semua status</option><?php foreach (PO_STATUSES as $key=>$label): ?><option value="<?= h($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label><label>Cari nomor, nama, WhatsApp<input name="q" maxlength="80" value="<?= h($search) ?>"></label><button class="ghost">Cari</button></form>
<?php if ($sid): ?><p class="sub">Filter jadwal aktif. <a href="po.php#orders">Lihat semua jadwal</a></p><?php endif; ?>
<p class="sub"><?= $total ?> pengajuan ditemukan. Pengajuan pending kedaluwarsa saat waktu pengambilan dimulai.</p>
<?php if (!$orders): ?><p class="empty">Belum ada pengajuan yang sesuai filter.</p><?php endif; ?>
<?php foreach ($orders as $o): $lines = po_query($db,'SELECT * FROM po_lines WHERE order_id=? ORDER BY id',[$o['id']])->fetchAll(); $history = po_query($db,'SELECT * FROM po_history WHERE order_id=? ORDER BY id',[$o['id']])->fetchAll(); ?>
<article class="po-order"><div class="pagehead"><h3><?= h($o['code']) ?></h3><span class="badge"><?= h(PO_STATUSES[$o['status']]) ?></span></div>
<p><b><?= h($o['customer_name']) ?></b> · <?= h($o['phone']) ?><br><small><?= h($o['title']) ?> · Ambil <?= h(po_date((int)$o['pickup_from'])) ?><br>Diajukan <?= h(po_date((int)$o['created_at'])) ?></small></p>
<ul><?php foreach ($lines as $l): ?><li><?= (int)$l['qty'] ?> × <?= h($l['name']) ?> — <?= h(po_money((int)$l['price']*(int)$l['qty'])) ?></li><?php endforeach; ?></ul><p><b>Total: <?= h(po_money((int)$o['total'])) ?></b></p>
<?php if ($o['note'] !== ''): ?><p class="po-note">Catatan: <?= h($o['note']) ?></p><?php endif; ?>
<div class="actions"><?php foreach (PO_TRANSITIONS[$o['status']] ?? [] as $target): ?><form method="post" data-confirm="Ubah <?= h($o['code']) ?> menjadi <?= h(PO_STATUSES[$target]) ?>?"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="transition"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="expected" value="<?= h($o['status']) ?>"><input type="hidden" name="target" value="<?= h($target) ?>"><button class="<?= in_array($target,['rejected','cancelled'],true) ? 'danger' : '' ?>"><?= h(PO_STATUSES[$target]) ?></button></form><?php endforeach; ?></div>
<details><summary>Riwayat status</summary><ul><?php foreach ($history as $event): ?><li><?= h(po_date((int)$event['created_at'])) ?> — <?= h(PO_STATUSES[$event['status']]) ?> · <?= h($event['actor']) ?></li><?php endforeach; ?></ul></details></article>
<?php endforeach; ?>
<div class="actions"><?php if ($page > 1): ?><a class="btn ghost" href="?<?= h(http_build_query(['slot'=>$sid,'status'=>$status,'q'=>$search,'page'=>$page-1])) ?>#orders">← Sebelumnya</a><?php endif; ?><?php if ($page*25 < $total): ?><a class="btn ghost" href="?<?= h(http_build_query(['slot'=>$sid,'status'=>$status,'q'=>$search,'page'=>$page+1])) ?>#orders">Berikutnya →</a><?php endif; ?></div>
</section><?php endif; ?></main></div></div></body></html>
