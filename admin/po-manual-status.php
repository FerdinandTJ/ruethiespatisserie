<?php defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.');
$bank=json_decode($order['bank_snapshot'],true,512,JSON_THROW_ON_ERROR);
$waiting=$order['payment_status']==='unpaid'&&$order['fulfillment_status']==='awaiting_transfer';
?>
<section class="panel"><p class="eyebrow">INVOICE PRE-ORDER · TRANSFER MANUAL</p><h2><?= h($order['code']) ?></h2>
<p><?= h($order['customer_name']) ?> · Dibuat <?= h(po_date((int)$order['created_at'])) ?></p>
<p class="status"><?= h(in_array($order['status'],['expired','cancelled'],true)?PO_STATUSES[$order['status']]:(PO_PAYMENT_LABELS[$order['payment_status']]??$order['payment_status'])) ?></p><p>Status pesanan: <?= h(PO_STATUSES[$order['status']]) ?></p>
<h3><?= h($order['title']) ?></h3><p>Ambil di toko: <?= h(po_date((int)$order['pickup_from'])) ?> sampai <?= h(po_date((int)$order['pickup_until'])) ?></p>
<ul class="order-lines"><?php foreach($order['lines']as$line): ?><li><span><?= (int)$line['qty'] ?> × <?= h($line['name']) ?></span><strong><?= h(po_money((int)$line['price']*(int)$line['qty'])) ?></strong></li><?php endforeach; ?></ul>
<p class="total"><span>Total</span><strong><?= h(po_money((int)$order['total'])) ?></strong></p>
<h3>Rekening pada invoice ini</h3>
<dl><dt>Bank</dt><dd><?= h($bank['bank_name']) ?></dd><dt>Nomor rekening</dt><dd><input readonly aria-label="Nomor rekening" value="<?= h($bank['account_number']) ?>"></dd><dt>Atas nama</dt><dd><?= h($bank['account_name']) ?></dd></dl><p><?= nl2br(h($bank['instructions'])) ?></p>
<?php if($waiting): ?>
<div class="alert">Kuota ditahan sampai <?= h(po_date((int)$order['expires_at'])) ?>. Transfer dan laporkan sebelum tenggat. Jika belum transfer sampai waktu habis, jangan membayar invoice ini.</div>

<?php if($order['payment_admin_reference']): ?><p class="alert error">Periksa kembali laporan transfer: <?= h($order['payment_admin_reference']) ?></p><?php endif; ?>
<h3>Sudah melakukan transfer?</h3><p>Laporan ini belum menyatakan lunas. Pemilik akan memeriksa mutasi rekening. Pada staging, gunakan data contoh tanpa transfer sungguhan.</p>
<form method="post" action="/pre-order/status/?t=<?= h($token) ?>"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="report-transfer">
<label>Nama pengirim transfer<input name="sender_name" required minlength="2" maxlength="80"></label>
<label>Nominal transfer (rupiah)<input type="number" name="transfer_amount" min="1" max="2000000000" step="1" required value="<?= (int)$order['total'] ?>"></label>
<label>Referensi atau keterangan transfer<input name="transfer_reference" required minlength="3" maxlength="160" placeholder="Contoh: transfer 8 November pukul 14.30"></label>
<button>Kirim laporan transfer</button></form>
<?php elseif($order['payment_status']==='under_review'): ?><div class="alert">Laporan transfer diterima dan sedang diperiksa. Kuota tetap ditahan selama pemeriksaan. Jangan transfer ulang.</div>
<?php elseif($order['payment_status']==='paid'): ?><div class="alert"><?= $order['status']==='ready'?'Pesanan siap diambil. Tunjukkan kode pesanan kepada tim toko.':($order['status']==='completed'?'Pesanan selesai. Terima kasih.':'Pembayaran telah diverifikasi pemilik. Pesanan sedang diproses.') ?></div>
<?php else: ?><div class="alert error">Invoice sudah ditutup dan tidak berlaku untuk transfer baru. Kuota sudah dilepas. Jika sudah transfer, hubungi toko dengan kode pesanan. Jangan transfer ulang; pembayaran terlambat harus diperiksa pemilik.</div><?php endif; ?>
<label for="po-status-link">Tautan pribadi pesanan</label><input id="po-status-link" type="text" readonly spellcheck="false" value="https://staging.ruethiespatisserie.com/pre-order/status/?t=<?= h($token) ?>">
<button type="button" class="secondary" id="po-copy-link" hidden>Salin tautan pesanan</button><p id="po-copy-feedback" role="status"></p>
<p class="muted">Simpan tautan pribadi ini. Pesanan tetap dapat diakses melalui website meskipun notifikasi WhatsApp terlambat atau gagal. Tautan staging memerlukan password akses staging.</p>
<div class="actions"><a class="button" href="/pre-order/status/?t=<?= h($token) ?>">Perbarui status</a><button type="button" class="secondary" id="po-print-invoice" hidden>Cetak / simpan PDF</button><a class="button secondary" href="/pre-order/">Lihat jadwal lain</a></div>
</section>
