<?php defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.'); ?>
<section class="card" id="whatsapp"><h2>Notifikasi WhatsApp</h2>
<?php if($waConfigError): ?><p class="warn">Layanan WhatsApp belum siap. Hubungi pengelola website; pesanan tetap dapat dikelola.</p><?php endif; ?>
<p><?= $waEnabled?'Notifikasi WhatsApp aktif.':'Notifikasi WhatsApp belum aktif. Status pesanan tetap dapat dilihat melalui website.' ?></p>
<p class="sub">Diterima layanan berarti pesan sudah diserahkan ke layanan pengiriman WhatsApp. Ini belum berarti pesan sudah sampai atau dibaca penerima.</p>
<?php if($waTestMode): ?><p class="warn">Pengiriman uji coba aktif. Pesan dialihkan ke nomor pengujian sesuai pengaturan, bukan langsung ke nomor pelanggan.</p><?php endif; ?>
<?php if(($waEnabled||po_mt_enabled())&&(!$waHeartbeat||time()-$waHeartbeat>180)): ?><p class="warn">Pengiriman otomatis belum diperbarui lebih dari 3 menit. Hubungi pengelola website.</p><?php endif; ?>
<p>Pemeriksaan terakhir: <?= $waHeartbeat?h(po_date($waHeartbeat)):'Belum tercatat' ?></p>
<ul class="po-wa-counts"><?php $waLabels=['pending'=>'Menunggu pengiriman atau percobaan ulang','processing'=>'Sedang diproses','accepted'=>'Diterima layanan','failed'=>'Gagal','uncertain'=>'Perlu pemeriksaan','skipped'=>'Tidak relevan lagi']; foreach($waCounts as$c): ?><li><?= h($waLabels[$c['status']]??$c['status']) ?>: <?= (int)$c['n'] ?></li><?php endforeach; ?></ul>
<?php foreach($waJobs as$job): ?><details><summary><?= h($job['code']) ?> · <?= h(['invoice'=>'Tagihan pesanan','paid'=>'Pembayaran berhasil','ready'=>'Pesanan siap diambil','completed'=>'Pesanan selesai'][$job['kind']]??'Pembaruan pesanan') ?> · <?= h($waLabels[$job['status']]??$job['status']) ?></summary>
<p class="sub">Kode pemeriksaan: <?= h($job['last_error']) ?>. Periksa riwayat pengiriman di Fonnte (layanan WhatsApp) sebelum mencoba ulang; hasil tidak pasti dapat berarti pesan sudah dikirim.</p>
<form method="post" data-confirm="Sudah memeriksa riwayat Fonnte? Mencoba ulang dapat menghasilkan pesan ganda."><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="wa-retry"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><input type="hidden" name="version" value="<?= (int)$job['version'] ?>"><?php require __DIR__.'/po-owner-confirm.php'; ?><button class="ghost">Coba ulang setelah diperiksa</button></form></details><?php endforeach; ?>
</section>
