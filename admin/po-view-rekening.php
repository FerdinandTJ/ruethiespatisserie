<?php defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.'); ?>
<section class="card editor" id="payment"><h2>Rekening pembayaran PO</h2>
<p class="sub">Atur rekening transfer untuk invoice PO manual. Gunakan rekening contoh pada staging; jangan meminta transfer sungguhan.</p>
<p class="<?= $payment['enabled'] ? 'okmsg' : 'warn' ?>"><?= $payment['enabled'] ? 'Rekening disiapkan untuk pembayaran manual.' : 'Rekening belum diaktifkan untuk pembayaran manual.' ?></p>
<form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="save-payment"><input type="hidden" name="version" value="<?= (int)$payment['version'] ?>">
<div class="fgrid"><label>Nama bank<input name="bank_name" maxlength="80" value="<?= h($payment['bank_name']) ?>" placeholder="Contoh: Bank Contoh"></label>
<label>Nomor rekening<input name="account_number" type="text" inputmode="numeric" maxlength="70" value="<?= h($payment['account_number']) ?>" placeholder="Contoh: 001234567890"></label></div>
<label>Nama pemilik rekening<input name="account_name" maxlength="120" value="<?= h($payment['account_name']) ?>"></label>
<label>Instruksi pembayaran (opsional)<textarea name="instructions" rows="3" maxlength="500"><?= h($payment['instructions']) ?></textarea></label>
<label><input type="checkbox" name="enabled" value="1" <?= $payment['enabled'] ? 'checked' : '' ?>>Rekening aktif untuk alur pembayaran manual</label>
<p class="sub">Untuk mengaktifkan, lengkapi nama bank, nomor rekening, dan nama pemilik. Perubahan rekening nantinya hanya berlaku untuk pesanan baru.</p>
<?php if ($payment['updated_at']): ?><p class="sub">Terakhir disimpan oleh <?= h($payment['updated_by']) ?> pada <?= h(po_date((int)$payment['updated_at'])) ?>.</p><?php endif; ?>
<button>Simpan rekening</button></form></section>