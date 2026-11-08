<?php defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.'); ?>
<p><b>Pembayaran:</b> <?= h(PO_PAYMENT_LABELS[$o['payment_status']]??$o['payment_status']) ?></p>
<?php if(is_owner($me)): $invoiceBank=json_decode($o['bank_snapshot'],true,512,JSON_THROW_ON_ERROR); ?>
<p class="sub">Rekening saat checkout: <?= h($invoiceBank['bank_name']) ?> · <?= h($invoiceBank['account_number']) ?> · a.n. <?= h($invoiceBank['account_name']) ?></p>
<?php endif; ?>
<?php if($o['payment_reported_at']): ?><p>Laporan <?= h(po_date((int)$o['payment_reported_at'])) ?> · <?= h($o['payment_sender']) ?> · <?= h(po_money((int)$o['payment_report_amount'])) ?><br><?= h($o['payment_report_reference']) ?></p><?php endif; ?>
<?php if($o['payment_status']==='under_review'&&is_owner($me)): ?>
<details><summary>Periksa dan putuskan pembayaran</summary><p class="warn">Periksa mutasi bank. Laporan pelanggan bukan bukti otomatis bahwa uang masuk. Kuota tetap ditahan selama pemeriksaan.</p>
<form method="post" data-confirm="Sudah memeriksa mutasi bank dan keputusan pembayaran ini?"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="manual-payment"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
<input type="hidden" name="expected_report" value="<?= (int)$o['payment_report_count'] ?>"><label>Keputusan<select name="decision"><option value="paid">Lunas setelah cek mutasi</option><option value="reject">Minta perbaikan laporan (maksimal 2 kali)</option><option value="reject-final">Tolak final setelah memastikan tidak ada dana masuk</option></select></label>
<label>Nominal yang benar-benar masuk (rupiah)<input type="number" min="1" max="2000000000" name="verified_amount" value="<?= (int)$o['total'] ?>"></label>
<label>Referensi pemeriksaan mutasi / alasan perbaikan<input name="admin_reference" required minlength="3" maxlength="160"></label>
<label><input type="checkbox" name="verified_no_transfer" value="yes">Khusus penolakan final: mutasi telah diperiksa dan tidak ada dana masuk untuk pesanan ini.</label>
<?php require __DIR__.'/po-owner-confirm.php'; ?><button>Simpan keputusan pembayaran</button></form></details>
<?php elseif($o['payment_status']==='unpaid'&&$o['reservation_status']==='held'&&is_owner($me)): ?>
<details><summary>Batalkan pesanan belum dibayar</summary><form method="post" data-confirm="Pastikan belum ada transfer sebelum melepas kuota."><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="manual-cancel"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><label>Alasan<input name="reason" required minlength="3" maxlength="160"></label><?php require __DIR__.'/po-owner-confirm.php'; ?><button class="danger">Batalkan dan lepas kuota</button></form></details>
<?php endif; ?>
<?php if($o['payment_status']==='paid'&&in_array($o['status'],['confirmed','ready'],true)): $next=$o['status']==='confirmed'?'ready':'completed'; ?>
<form method="post" data-confirm="Ubah status pemenuhan pesanan?"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="manual-fulfill"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="expected" value="<?= h($o['status']) ?>"><input type="hidden" name="target" value="<?= h($next) ?>"><button><?= h(PO_STATUSES[$next]) ?></button></form>
<?php endif; ?>
