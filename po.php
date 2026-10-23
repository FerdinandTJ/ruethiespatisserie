<?php
define('RUETHIES_ADMIN', true);
require __DIR__ . '/admin/config.php';
require __DIR__ . '/admin/lib.php';
require __DIR__ . '/admin/po-lib.php';
po_require_staging();
session_name('RUETHIESPODEMO');
session_cache_limiter('');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','httponly'=>true,'samesite'=>'Lax']);
session_start();
if (empty($_SESSION['po_csrf'])) $_SESSION['po_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['po_csrf'];
$error = ''; $unavailable = false; $slots = []; $products = []; $slot = null; $review = null; $order = null;
$nonce = ''; $values = []; $db = null;
$token = is_string($_GET['t'] ?? null) ? $_GET['t'] : '';
$statusRequested = array_key_exists('t', $_GET);
// Draft hanya berada dalam sesi, bukan di Git atau URL.
foreach ($_SESSION['po_drafts'] ?? [] as $key => $draft) if ($draft['created'] < time()-3600) unset($_SESSION['po_drafts'][$key]);
if (is_string($_GET['edit'] ?? null) && isset($_SESSION['po_drafts'][$_GET['edit']])) $values = $_SESSION['po_drafts'][$_GET['edit']]['input'];
try {
  $db = po_db();
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($csrf, $_POST['csrf'])) throw new DomainException('Sesi formulir berakhir. Muat ulang halaman.');
    if (($_POST['website'] ?? '') !== '') throw new DomainException('Pengajuan tidak dapat diproses.');
    $key = hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', po_config()['secret']);
    po_rate($db, 'ip:' . $key, 20);
    if (($_POST['action'] ?? '') === 'review') {
      $values = $_POST;
      po_customer($values);
      $review = po_quote($db, $values);
      $nonce = bin2hex(random_bytes(32));
      $_SESSION['po_drafts'][$nonce] = ['input'=>$values,'hash'=>po_quote_hash($review),'created'=>time()];
      while (count($_SESSION['po_drafts']) > 5) array_shift($_SESSION['po_drafts']);
    } elseif (($_POST['action'] ?? '') === 'submit') {
      $nonce = is_string($_POST['nonce'] ?? null) ? $_POST['nonce'] : '';
      $draft = $_SESSION['po_drafts'][$nonce] ?? null;
      if (!$draft) throw new DomainException('Ringkasan kedaluwarsa. Isi formulir kembali.');
      $values = $draft['input'];
      $access = po_submit($db, $values, $nonce, $draft['hash'], po_config()['secret']);
      header('Location: po.php?t=' . $access, true, 303); exit;
    } else throw new DomainException('Tindakan tidak valid.');
  }
  if ($statusRequested) {
    $order = po_order($db, $token);
    if (!$order) { http_response_code(404); $error = 'Tautan status tidak valid atau pesanan tidak ditemukan.'; }
  }
} catch (DomainException $e) { $error = $e->getMessage(); }
catch (Throwable $e) { http_response_code(503); $unavailable = true; $error = 'Demo PO sedang disiapkan. Silakan coba lagi setelah admin mengaktifkan database.'; error_log('PO demo: storage or configuration unavailable'); }
if (!$unavailable && !$statusRequested && !$review) {
  try {
    $slots = po_query($db, 'SELECT * FROM po_slots WHERE is_open=1 AND opens_at<=? AND closes_at>? ORDER BY pickup_from', [time(),time()])->fetchAll();
    $sid = $values['slot_id'] ?? ($_GET['slot'] ?? 0);
    foreach ($slots as $s) if ((string)$s['id'] === (string)(is_scalar($sid) ? $sid : 0)) $slot = $s;
    if (!$slot && count($slots) === 1) $slot = $slots[0];
    if ($slot) $products = po_public_products($db, (int)$slot['id']);
  } catch (Throwable $e) { $unavailable = true; $error = 'Daftar PO belum dapat dimuat.'; }
}
function po_value(array $values, string $key): string { return is_string($values[$key] ?? null) ? $values[$key] : ''; }
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer">
<title>Pre-order — Ruethies Patisserie</title><link rel="stylesheet" href="po.css?v=2"><script src="po.js?v=1" defer></script></head>
<body>
<div class="demo-banner">DEMO STAGING · Gunakan data contoh · Tidak ada pembayaran atau pesanan nyata</div>
<header class="po-header"><a href="./" aria-label="Beranda Ruethies"><img src="logo-wine.png" alt="Ruethies Patisserie" width="130" height="52"></a><a href="./">Kembali ke website ↗</a></header>
<main class="po-wrap">
<p class="eyebrow">DIPERSIAPKAN DENGAN KELEMBUTAN</p>
<h1><?= $order ? 'Pengajuan Anda' : 'Rencanakan momen manis Anda.' ?></h1>
<p class="intro">Pilih sajian favorit, ajukan pre-order, lalu ambil di toko setelah dikonfirmasi oleh tim Ruethies.</p>
<?php if ($error): ?><div class="alert error" role="alert"><?= h($error) ?></div><?php endif; ?>

<?php if ($order): ?>
<section class="panel"><p class="eyebrow">NOMOR PENGAJUAN</p><h2><?= h($order['code']) ?></h2>
<p class="status"><?= h(PO_STATUSES[$order['status']]) ?></p>
<?php if ($order['status'] === 'pending'): ?><p>Pengajuan tercatat, tetapi <strong>belum diterima atau mengunci kuota</strong>. Tim kami akan memeriksa ketersediaan. Pengajuan yang belum dikonfirmasi sampai <?= h(po_date((int)$order['expires_at'])) ?> akan kedaluwarsa.</p>
<?php elseif ($order['status'] === 'confirmed'): ?><p>Pengajuan telah diterima. Pantau halaman ini sampai pesanan siap diambil.</p>
<?php elseif ($order['status'] === 'ready'): ?><p>Pesanan siap diambil. Tunjukkan nomor pengajuan saat datang ke toko.</p>
<?php elseif ($order['status'] === 'completed'): ?><p>Pengambilan telah selesai. Terima kasih sudah memilih Ruethies.</p>
<?php else: ?><p>Pengajuan ini sudah ditutup. Anda dapat membuat pengajuan baru pada jadwal yang tersedia.</p><?php endif; ?>
<h3><?= h($order['title']) ?></h3><p>Ambil di toko: <?= h(po_date((int)$order['pickup_from'])) ?> sampai <?= h(po_date((int)$order['pickup_until'])) ?></p>
<ul class="order-lines"><?php foreach ($order['lines'] as $line): ?><li><span><?= (int)$line['qty'] ?> × <?= h($line['name']) ?></span><strong><?= h(po_money((int)$line['price'] * (int)$line['qty'])) ?></strong></li><?php endforeach; ?></ul>
<p class="total"><span>Total pengajuan</span><strong><?= h(po_money((int)$order['total'])) ?></strong></p>
<p class="muted">Belum ada pembayaran yang diproses atau dicatat. Simpan tautan pribadi ini untuk melihat status. Jangan membagikannya kepada orang lain.</p>
<div class="actions"><a class="button" href="po.php?t=<?= h($token) ?>">Perbarui status</a><a class="button secondary" href="po.php">Lihat jadwal lain</a></div>
</section>

<?php elseif ($review): ?>
<section class="panel"><p class="eyebrow">LANGKAH 2 DARI 2</p><h2>Periksa pengajuan Anda</h2>
<h3><?= h($review['slot']['title']) ?></h3><p>Ambil di toko: <?= h(po_date((int)$review['slot']['pickup_from'])) ?> sampai <?= h(po_date((int)$review['slot']['pickup_until'])) ?></p>
<ul class="order-lines"><?php foreach ($review['lines'] as $line): ?><li><span><?= (int)$line['qty'] ?> × <?= h($line['name']) ?></span><strong><?= h(po_money($line['price'] * $line['qty'])) ?></strong></li><?php endforeach; ?></ul>
<p class="total"><span>Total pengajuan</span><strong><?= h(po_money($review['total'])) ?></strong></p>
<p><strong><?= h(po_value($values,'name')) ?></strong><br>WhatsApp: <?= h(po_value($values,'phone')) ?></p>
<?php if (po_value($values,'note') !== ''): ?><p class="customer-note">Catatan: <?= h(po_value($values,'note')) ?></p><?php endif; ?>
<div class="alert">Belum ada pembayaran. Pengajuan baru diterima setelah admin mengonfirmasi ketersediaan.</div>
<form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="submit"><input type="hidden" name="nonce" value="<?= h($nonce) ?>">
<button type="submit">Kirim pengajuan PO</button> <a class="text-link" href="po.php?edit=<?= h($nonce) ?>">Ubah pilihan</a></form>
</section>

<?php elseif (!$unavailable && !$statusRequested): ?>
<section class="panel"><p class="eyebrow">01 · PILIH JADWAL</p><h2>PO yang sedang dibuka</h2>
<?php if (!$slots): ?><div class="empty">Belum ada jadwal PO yang dibuka. Silakan kembali setelah admin membuka periode baru.</div><?php endif; ?>
<div class="slot-grid"><?php foreach ($slots as $s): ?><a class="slot <?= $slot && $slot['id'] == $s['id'] ? 'selected' : '' ?>" href="po.php?slot=<?= (int)$s['id'] ?>#pilihan" <?= $slot && $slot['id'] == $s['id'] ? 'aria-current="true"' : '' ?>>
<strong><?= h($s['title']) ?></strong><span>Ambil <?= h(po_date((int)$s['pickup_from'])) ?></span><small>PO ditutup <?= h(po_date((int)$s['closes_at'])) ?></small></a><?php endforeach; ?></div></section>
<?php if ($slot): ?>
<form method="post" id="po-form"><input type="hidden" name="action" value="review"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="slot_id" value="<?= (int)$slot['id'] ?>">
<section class="panel" id="pilihan"><p class="eyebrow">02 · PILIH SAJIAN</p><h2><?= h($slot['title']) ?></h2><p class="muted">Jumlah 0 berarti tidak dipilih. Ketersediaan akhir dikonfirmasi oleh admin.</p>
<?php if (!$products): ?><div class="empty">Produk untuk jadwal ini sedang disiapkan.</div><?php endif; ?>
<?php foreach ($products as $p): $left = max(0, (int)$p['quota'] - (int)$p['reserved']); $qty = is_array($values['qty'] ?? null) ? ($values['qty'][$p['id']] ?? 0) : 0; ?>
<div class="product"><div class="product-info"><?php if ($p['image']): ?><img class="product-photo" src="<?= h($p['image']) ?>" alt="" width="88" height="66" loading="lazy"><?php endif; ?><div><h3><?= h($p['name']) ?></h3><p class="muted"><?= h($p['description']) ?></p><p><?= h(po_money((int)$p['price'])) ?> <span class="muted">/ item</span></p><small><?= $left ? 'Kuota tersedia: ' . $left : 'Kuota habis' ?></small></div></div>
<label>Jumlah<input type="number" name="qty[<?= (int)$p['id'] ?>]" min="0" max="<?= min(20,$left) ?>" step="1" value="<?= h(is_scalar($qty) ? (string)$qty : '0') ?>" data-price="<?= (int)$p['price'] ?>" <?= !$left ? 'disabled' : '' ?> aria-label="Jumlah <?= h($p['name']) ?>"></label></div>
<?php endforeach; ?>
<p class="total"><span>Perkiraan total</span><strong id="po-total" aria-live="polite">Rp0</strong></p></section>
<section class="panel"><p class="eyebrow">03 · DETAIL PENGAMBILAN</p><h2>Siapa yang akan mengambil?</h2>
<div class="form-grid"><label>Nama<input name="name" required minlength="2" maxlength="80" autocomplete="name" value="<?= h(po_value($values,'name')) ?>"></label>
<label>Nomor WhatsApp<input name="phone" type="tel" required maxlength="24" autocomplete="tel" placeholder="081234567890" value="<?= h(po_value($values,'phone')) ?>"></label></div>
<label>Catatan (opsional)<textarea name="note" rows="3" maxlength="500"><?= h(po_value($values,'note')) ?></textarea></label>
<div class="trap" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
<div class="pickup"><strong>Ambil di toko Ruethies Patisserie</strong><br>Ruko The Neo Patio East No. 51, Lippo Cikarang.<br><?= h(po_date((int)$slot['pickup_from'])) ?> sampai <?= h(po_date((int)$slot['pickup_until'])) ?></div>
<label class="check"><input type="checkbox" name="consent" value="yes" required <?= ($values['consent'] ?? '') === 'yes' ? 'checked' : '' ?>>Saya setuju kontak digunakan untuk menindaklanjuti pengajuan ini dan memahami pesanan perlu konfirmasi admin.</label>
<button type="submit" <?= !$products ? 'disabled' : '' ?>>Periksa ringkasan →</button><p class="muted">Demo presentasi: gunakan nama dan nomor contoh. Tidak ada pesan WhatsApp otomatis.</p></section></form>
<?php endif; endif; ?>
</main><footer class="po-footer">Ruethies Patisserie · Made With Delicacy · Demo pre-order</footer>
</body></html>
