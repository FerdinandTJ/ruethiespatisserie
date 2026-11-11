<?php defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.'); ?>
<section class="card" id="whatsapp"><h2>Notifikasi WhatsApp</h2>
<?php if($waConfigError): ?><p class="warn">Layanan WhatsApp belum siap. Hubungi pengelola website; pesanan tetap dapat dikelola.</p><?php endif; ?>
<p><?= $waEnabled?'Notifikasi WhatsApp aktif.':'Notifikasi WhatsApp belum aktif. Status pesanan tetap dapat dilihat melalui website.' ?></p>
<p class="sub">Diterima layanan berarti pesan telah diterima penyedia WhatsApp, belum memastikan pesan sampai atau dibaca.</p>
<?php if($waTestMode): ?><p class="warn">Penerima uji aktif: semua notifikasi dialihkan ke nomor pengujian.</p><?php endif; ?>
<?php if(($waEnabled||po_mt_enabled())&&(!$waHeartbeat||time()-$waHeartbeat>180)): ?><p class="warn">Pengiriman otomatis belum diperbarui lebih dari 3 menit. Hubungi pengelola website.</p><?php endif; ?>
<p>Pemeriksaan terakhir: <?= $waHeartbeat?h(po_date($waHeartbeat)):'Belum tercatat' ?></p>
<ul class="po-wa-counts"><?php $waLabels=['pending'=>'Menunggu/dicoba ulang','processing'=>'Sedang diproses','accepted'=>'Diterima layanan','failed'=>'Gagal','uncertain'=>'Perlu pemeriksaan','skipped'=>'Tidak relevan lagi']; foreach($waCounts as$c): ?><li><?= h($waLabels[$c['status']]??$c['status']) ?>: <?= (int)$c['n'] ?></li><?php endforeach; ?></ul>
<?php foreach($waJobs as$job): ?><details><summary><?= h($job['code']) ?> · <?= h($job['kind']) ?> · <?= h($waLabels[$job['status']]??$job['status']) ?></summary>
<p class="sub">Kode pemeriksaan: <?= h($job['last_error']) ?>. Periksa riwayat pengiriman Fonnte sebelum mencoba ulang; hasil tidak pasti dapat berarti pesan sudah dikirim.</p>
<form method="post" data-confirm="Sudah memeriksa riwayat Fonnte? Mencoba ulang dapat menghasilkan pesan ganda."><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="wa-retry"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><input type="hidden" name="version" value="<?= (int)$job['version'] ?>"><?php require __DIR__.'/po-owner-confirm.php'; ?><button class="ghost">Coba ulang setelah diperiksa</button></form></details><?php endforeach; ?>
</section>
