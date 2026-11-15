<?php
if(!defined('RUETHIES_ADMIN')){http_response_code(404);exit;}
if(!is_owner($me)){http_response_code(403);echo '<p class="err">Khusus pemilik.</p>';return;}
$categoryReady=is_file(RUETHIES_PUBLIC.'/categories.json');$editing=null;
foreach($_categorySnapshot['categories']as$row)if($row['key']===($_GET['edit']??''))$editing=$row;
$categoryRevision=$_categorySnapshot['revision'];
?>
<?php if(!$categoryReady): ?><p class="warn">Jalankan <span class="mono">php admin/categories-upgrade.php --apply</span> melalui SSH sebelum mengelola kategori.</p><?php endif; ?>
<section class="card">
<h2><?= $editing?'Ubah kategori':'Tambah kategori' ?></h2>
<p class="sub">Nama dapat diganti tanpa mengubah ID kategori atau alamat produk. Terjemahan Inggris opsional.</p>
<form method="post">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>"><input type="hidden" name="aksi" value="category-save"><input type="hidden" name="revision" value="<?= h($categoryRevision) ?>"><input type="hidden" name="key" value="<?= h($editing['key']??'') ?>">
<label>Nama Indonesia<input name="name_id" maxlength="60" required value="<?= h(($_POST['aksi']??'')==='category-save'?($_POST['name_id']??''):($editing['id']??'')) ?>"></label>
<label>Nama Inggris (opsional)<input name="name_en" maxlength="60" value="<?= h(($_POST['aksi']??'')==='category-save'?($_POST['name_en']??''):($editing['en']??'')) ?>"></label>
<div class="row"><button <?= !$categoryReady?'disabled':'' ?>>Simpan kategori</button><?php if($editing): ?><a class="ghost" href="?p=kategori">Batal</a><?php endif; ?></div>
</form>
</section>
<section class="card">
<h2>Urutan kategori</h2><p class="sub">Kategori kosong tetap tersedia di admin, tetapi tidak ditampilkan di website.</p>
<?php foreach($_categorySnapshot['categories']as$position=>$row):
 $products=array_filter($_menu,fn($m)=>$m['cat']===$row['key']);$visible=array_filter($products,fn($m)=>($m['available']??true)!==false);$filter='?cat='.rawurlencode($row['key']); ?>
<article class="category-admin-row">
<div><span class="hint">Urutan <?= $position+1 ?></span><h3><?= h($row['id']) ?></h3><p class="sub">Inggris: <?= h($row['en']!==''?$row['en']:$row['id']) ?></p><a href="<?= h($filter) ?>"><?= count($products) ?> produk · <?= count($visible) ?> tampil</a></div>
<div class="category-admin-actions">
<a class="ghost" href="?p=kategori&amp;edit=<?= h($row['key']) ?>">Ubah</a>
<?php foreach(['up'=>'Naik','down'=>'Turun']as$direction=>$label): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>"><input type="hidden" name="aksi" value="category-move"><input type="hidden" name="revision" value="<?= h($categoryRevision) ?>"><input type="hidden" name="key" value="<?= h($row['key']) ?>"><input type="hidden" name="direction" value="<?= $direction ?>"><button class="ghost" aria-label="<?= h($label.' '.$row['id']) ?>" <?= !$categoryReady||($direction==='up'?$position===0:$position===count($_categorySnapshot['categories'])-1)?'disabled':'' ?>><?= $label ?></button></form>
<?php endforeach; ?>
<?php if(!$products): ?>
<form method="post" data-confirm="Hapus kategori kosong ini?"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>"><input type="hidden" name="aksi" value="category-delete"><input type="hidden" name="revision" value="<?= h($categoryRevision) ?>"><input type="hidden" name="key" value="<?= h($row['key']) ?>"><label class="category-confirm"><input type="checkbox" name="confirm" value="yes" required> Konfirmasi hapus</label><button class="danger" <?= !$categoryReady?'disabled':'' ?>>Hapus</button></form>
<?php else: ?><p class="hint">Pindahkan semua produk terlebih dahulu untuk menghapus kategori.</p><?php endif; ?>
</div></article>
<?php endforeach; ?>
</section>
