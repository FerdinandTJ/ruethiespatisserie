<?php defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.');
$mtInvoice=po_query($db,'SELECT create_state,snap_url,last_error FROM po_midtrans_payments WHERE order_id=?',[$order['id']])->fetch();
$canPay=$mtInvoice&&($mtInvoice['snap_url']||($mtInvoice['create_state']==='new'&&(int)$order['expires_at']>=time()+300)); ?>
<section class="panel"><p class="eyebrow">INVOICE · MIDTRANS SANDBOX</p><h2><?= h($order['code']) ?></h2>
<p><?= h($order['customer_name']) ?> · <?= h($order['title']) ?></p>
<p class="status"><?= h(PO_PAYMENT_LABELS[$order['payment_status']]??$order['payment_status']) ?></p>
<ul class="order-lines"><?php foreach($order['lines']as$line): ?><li><span><?= (int)$line['qty'] ?> × <?= h($line['name']) ?></span><strong><?= h(po_money((int)$line['price']*(int)$line['qty'])) ?></strong></li><?php endforeach; ?></ul>
<p class="total"><span>Total simulasi</span><strong><?= h(po_money((int)$order['total'])) ?></strong></p>
<?php if($order['payment_status']==='paid'): ?><div class="alert">Pembayaran Sandbox terverifikasi. <?= h(PO_STATUSES[$order['status']]) ?>.</div>
<?php elseif($order['payment_status']==='paid_review'): ?><div class="alert error">Pembayaran memerlukan pemeriksaan pemilik. Pesanan belum otomatis diproses. Hubungi toko dengan kode pesanan dan jangan membayar ulang.</div>
<?php elseif($order['reservation_status']==='held'): ?><div class="alert">Gunakan simulator Sandbox, jangan transfer uang sungguhan. Selesaikan pembayaran sebelum <?= h(po_date((int)$order['expires_at'])) ?>. Status pesanan diperbarui setelah pembayaran terverifikasi.</div>
<?php if($canPay&&(int)$order['expires_at']>time()): ?><form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="midtrans-pay"><button>Bayar di Midtrans Sandbox →</button></form><?php endif; ?>
<?php if(!$canPay): ?><p class="muted">Halaman pembayaran belum tersedia atau sedang diperiksa. Jangan membayar di luar invoice ini. Status akan diperiksa kembali secara otomatis.</p><?php endif; ?>
<?php else: ?><div class="alert error">Invoice sudah ditutup. Jangan membayar lagi. Jika pembayaran sudah dilakukan, hubungi pemilik dengan kode pesanan.</div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="midtrans-check"><button class="secondary">Periksa status pembayaran</button></form>
<p>Pengambilan <?= h(po_date((int)$order['pickup_from'])) ?> – <?= h(po_date((int)$order['pickup_until'])) ?></p>
<label for="po-status-link">Tautan pribadi pesanan</label><input id="po-status-link" readonly value="https://staging.ruethiespatisserie.com/pre-order/status/?t=<?= h($token) ?>">
<button type="button" class="secondary" id="po-copy-link" hidden>Salin tautan pesanan</button><p id="po-copy-feedback" role="status"></p>
<p class="muted">Simpan tautan ini untuk memantau pesanan. Jangan membagikannya kepada orang lain. Informasi pesanan juga dikirim melalui WhatsApp.</p></section>
