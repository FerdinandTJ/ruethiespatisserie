<?php defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.'); ?>
<?php if($orderId): ?>
<a class="backlink" href="<?= h(po_admin_url('pesanan')) ?>">← Semua pesanan</a>
<?php if($detailOrder): ?><section class="card po-order-detail">
<?php $o=$detailOrder; $lines = po_query($db,'SELECT * FROM po_lines WHERE order_id=? ORDER BY id',[$o['id']])->fetchAll(); $history = po_query($db,'SELECT * FROM po_history WHERE order_id=? ORDER BY id',[$o['id']])->fetchAll(); ?>
<article class="po-order"><div class="pagehead"><h3><?= h($o['code']) ?></h3><span class="badge"><?= h(PO_STATUSES[$o['status']]) ?></span></div>
<p><b><?= h($o['customer_name']) ?></b> · <?= h($o['phone']) ?><br><small><?= h($o['title']) ?> · Ambil <?= h(po_date((int)$o['pickup_from'])) ?><br>Diajukan <?= h(po_date((int)$o['created_at'])) ?></small></p>
<ul><?php foreach ($lines as $l): ?><li><?= (int)$l['qty'] ?> × <?= h($l['name']) ?> — <?= h(po_money((int)$l['price']*(int)$l['qty'])) ?></li><?php endforeach; ?></ul><p><b>Total: <?= h(po_money((int)$o['total'])) ?></b></p>
<?php if ($o['note'] !== ''): ?><p class="po-note">Catatan: <?= h($o['note']) ?></p><?php endif; ?>
<?php if(($o['flow_version']??'')==='midtrans_v1'): require __DIR__.'/po-midtrans-admin.php'; elseif(($o['flow_version']??'legacy')==='manual_v1'): require __DIR__.'/po-manual-admin.php'; endif; ?>
<div class="actions"><?php foreach (($o['flow_version'] ?? 'legacy') === 'legacy' ? (PO_TRANSITIONS[$o['status']] ?? []) : [] as $target): ?><form method="post" data-confirm="Ubah <?= h($o['code']) ?> menjadi <?= h(PO_STATUSES[$target]) ?>?"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="transition"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="expected" value="<?= h($o['status']) ?>"><input type="hidden" name="target" value="<?= h($target) ?>"><button class="<?= in_array($target,['rejected','cancelled'],true) ? 'danger' : '' ?>"><?= h(PO_STATUSES[$target]) ?></button></form><?php endforeach; ?></div>
<details><summary>Riwayat status</summary><ul><?php foreach ($history as $event): ?><li><?= h(po_date((int)$event['created_at'])) ?> — <?= h(PO_STATUSES[$event['status']]) ?> · <?= h($event['actor']) ?></li><?php endforeach; ?></ul></details></article>
</section><?php endif; ?>
<?php else: ?>
<div class="stats po-stats" aria-label="Ringkasan seluruh pesanan">
<?php foreach([['review','Perlu verifikasi',['payment'=>'under_review']],['pending','Menunggu konfirmasi',['status'=>'pending']],['ready','Siap diambil',['status'=>'ready']],['completed','Selesai',['status'=>'completed']]]as[$key,$label,$params]): ?>
<a class="stat po-stat" href="<?= h(po_admin_url('pesanan',$params)) ?>"><div><b><?= (int)$stats[$key] ?></b><span><?= h($label) ?></span></div></a>
<?php endforeach; ?></div>
<section class="card" id="orders"><h2>Daftar pesanan</h2>
<form method="get" class="po-filter" role="search"><input type="hidden" name="view" value="pesanan"><label>Cari pesanan<input name="q" maxlength="80" placeholder="Nomor, nama, atau WhatsApp" value="<?= h($search) ?>"></label><label>Jadwal<select name="slot"><option value="">Semua jadwal</option><?php foreach($slots as$s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$s['id']===$sid?'selected':'' ?>><?= h($s['title']) ?></option><?php endforeach; ?></select></label>
<label>Pembayaran<select name="payment"><option value="">Semua pembayaran</option><?php foreach(PO_PAYMENT_LABELS as$key=>$label): ?><option value="<?= h($key) ?>" <?= $paymentFilter===$key?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
<label>Status pesanan<select name="status"><option value="">Semua status</option><?php foreach(PO_STATUSES as$key=>$label): ?><option value="<?= h($key) ?>" <?= $status===$key?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label><button>Terapkan filter</button></form>
<div class="list-caption"><span><?= $total ?> pesanan ditemukan</span><?php if($sid||$status||$paymentFilter||$search!==''): ?><a href="<?= h(po_admin_url('pesanan')) ?>">Reset filter</a><?php endif; ?></div>
<?php if(!$orders): ?><div class="empty"><b>Belum ada pesanan yang sesuai</b><p>Coba jadwal atau filter lain. Pesanan baru akan muncul di sini.</p></div><?php endif; ?>
<?php foreach($orders as$o): ?><article class="po-order-row"><div class="po-order-summary"><div class="grow"><a class="po-order-code" href="<?= h(po_admin_url('pesanan',['order'=>(int)$o['id']])) ?>"><?= h($o['code']) ?></a><b><?= h($o['customer_name']) ?></b><small><?= h($o['title']) ?> · Ambil <?= h(po_date((int)$o['pickup_from'])) ?></small></div><div class="po-order-amount"><strong><?= h(po_money((int)$o['total'])) ?></strong><span class="badge"><?= h(PO_STATUSES[$o['status']]) ?></span></div></div>
<div class="po-order-bottom"><span class="po-payment-state <?= $o['payment_status']==='under_review'?'review':'' ?>"><?= h(PO_PAYMENT_LABELS[$o['payment_status']]??$o['payment_status']) ?></span><a class="btn ghost" href="<?= h(po_admin_url('pesanan',['order'=>(int)$o['id']])) ?>">Lihat detail →</a></div></article><?php endforeach; ?>
<div class="po-pagination"><?php if($page>1): ?><a class="btn ghost" href="<?= h(po_admin_url('pesanan',['slot'=>$sid,'status'=>$status,'payment'=>$paymentFilter,'q'=>$search,'page'=>$page-1])) ?>">← Sebelumnya</a><?php endif; ?><span>Halaman <?= $page ?></span><?php if($page*25<$total): ?><a class="btn ghost" href="<?= h(po_admin_url('pesanan',['slot'=>$sid,'status'=>$status,'payment'=>$paymentFilter,'q'=>$search,'page'=>$page+1])) ?>">Berikutnya →</a><?php endif; ?></div>
</section>
<?php endif; ?>
