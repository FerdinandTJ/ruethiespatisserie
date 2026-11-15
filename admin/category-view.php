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
<h2>Urutan kategori</h2><p class="sub">Atur urutan dengan panah. Kategori terpakai tidak bisa dihapus; kategori kosong tidak muncul di website.</p>
<?php foreach($_categorySnapshot['categories']as$position=>$row):
 $products=array_filter($_menu,fn($m)=>$m['cat']===$row['key']);$visible=array_filter($products,fn($m)=>($m['available']??true)!==false);$filter='?cat='.rawurlencode($row['key']); ?>
<article class="category-admin-row">
<div class="category-admin-info"><span class="category-position" aria-label="Urutan <?= $position+1 ?>"><?= str_pad((string)($position+1),2,'0',STR_PAD_LEFT) ?></span><div class="category-admin-copy"><h3><?= h($row['id']) ?></h3><p class="category-translation"><?= h($row['en']!==''?$row['en']:'Terjemahan mengikuti nama Indonesia') ?></p><a class="category-product-count" href="<?= h($filter) ?>"><?= count($products) ?> produk · <?= count($visible) ?> tampil</a></div></div>
<div class="category-admin-actions">
<a class="category-edit" href="?p=kategori&amp;edit=<?= h($row['key']) ?>"><?= icon('edit',15) ?> Ubah</a>
<div class="category-order" role="group" aria-label="<?= h('Urutan '.$row['id']) ?>">
<?php foreach(['up'=>'Naik','down'=>'Turun']as$direction=>$label): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>"><input type="hidden" name="aksi" value="category-move"><input type="hidden" name="revision" value="<?= h($categoryRevision) ?>"><input type="hidden" name="key" value="<?= h($row['key']) ?>"><input type="hidden" name="direction" value="<?= $direction ?>"><button type="submit" aria-label="<?= h($label.' '.$row['id']) ?>" title="<?= $label ?>" <?= !$categoryReady||($direction==='up'?$position===0:$position===count($_categorySnapshot['categories'])-1)?'disabled':'' ?>><?= icon($direction,16) ?></button></form>
<?php endforeach; ?>
</div>
<?php if(!$products&&$categoryReady): ?>
<details class="category-delete"><summary aria-label="<?= h('Hapus '.$row['id']) ?>" title="Hapus kategori kosong"><?= icon('trash',16) ?></summary>
<div class="category-delete-panel"><p><b>Hapus kategori ini?</b></p><p class="sub"><?= h($row['id']) ?> belum memiliki produk.</p><form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>"><input type="hidden" name="aksi" value="category-delete"><input type="hidden" name="revision" value="<?= h($categoryRevision) ?>"><input type="hidden" name="key" value="<?= h($row['key']) ?>"><label class="category-confirm"><input type="checkbox" name="confirm" value="yes" required> Ya, hapus kategori ini</label><div class="category-delete-buttons"><button type="button" class="ghost" data-category-cancel hidden>Batal</button><button class="danger">Hapus kategori</button></div></form></div>
</details>
<?php else: ?><span class="category-in-use" role="img" aria-label="<?= $products?'Kategori terpakai; pindahkan semua produk sebelum menghapus.':'Pengelolaan kategori belum diaktifkan.' ?>" title="<?= $products?'Kategori masih dipakai produk':'Jalankan upgrade kategori dahulu' ?>"><?= icon('lock',15) ?></span><?php endif; ?>
</div></article>
<?php endforeach; ?>
</section>
