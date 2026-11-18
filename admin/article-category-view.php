<?php
defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.');
article_authorize($me,true);$rows=$articleState['categories'];$key=is_string($_GET['key']??null)?$_GET['key']:'';$editCategory=null;
foreach($rows as$row)if($row['id']===$key)$editCategory=$row;
if($key!==''&&!$editCategory){http_response_code(404);echo '<p class="err">Kategori tidak ditemukan.</p>';return;}
$form=$editCategory??['id'=>'','name_id'=>'','name_en'=>''];
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['category_action']??'')==='save')foreach(['name_id','name_en']as$field)if(is_string($_POST[$field]??null))$form[$field]=$_POST[$field];
$counts=[];$live=[];foreach($articleState['items']as$row){$counts[$row['category']]=($counts[$row['category']]??0)+1;if($row['status']==='published'&&$row['deletedAt']===null)$live[$row['category']]=($live[$row['category']]??0)+1;}
?>
<p><a class="backlink" href="?p=artikel">← Kembali ke Artikel</a></p>
<div class="card editor"><h2><?= $editCategory?'Ubah kategori artikel':'Tambah kategori artikel' ?></h2>
<form method="post">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>"><input type="hidden" name="aksi" value="article-category-write"><input type="hidden" name="category_action" value="save"><input type="hidden" name="category_id" value="<?= h($form['id']) ?>"><input type="hidden" name="revision" value="<?= h($revision) ?>">
<div class="article-admin-grid"><label>Nama Indonesia<input name="name_id" maxlength="60" value="<?= h($form['name_id']) ?>" required></label><label>Nama Inggris (opsional)<input name="name_en" maxlength="60" value="<?= h($form['name_en']) ?>"></label></div>
<p class="sub">Jika nama Inggris kosong, nama Indonesia digunakan. Mengganti nama tidak memindahkan atau mengubah isi artikel.</p>
<div class="article-admin-actions"><button>Simpan kategori</button><?php if($editCategory): ?><a href="?p=artikel-kategori">Batal</a><?php endif; ?></div>
</form></div>
<div class="article-admin-list">
<?php foreach($rows as$i=>$row): $used=$counts[$row['id']]??0; ?>
<article class="card article-category-row"><div><h2><?= h($row['name_id']) ?></h2><p class="sub"><?= h($row['name_en']) ?> · Urutan <?= $i+1 ?><br><?= $used ?> artikel seluruhnya · <?= $live[$row['id']]??0 ?> terbit</p></div>
<div class="article-admin-actions"><a class="linkbtn" href="?p=artikel-kategori&amp;key=<?= h($row['id']) ?>">Ubah</a><div class="article-category-order">
<?php foreach(['up'=>'↑ Naik','down'=>'↓ Turun']as$action=>$label): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>"><input type="hidden" name="aksi" value="article-category-write"><input type="hidden" name="category_action" value="<?= h($action) ?>"><input type="hidden" name="category_id" value="<?= h($row['id']) ?>"><input type="hidden" name="revision" value="<?= h($revision) ?>"><button class="ghost" <?= ($action==='up'&&$i===0||$action==='down'&&$i===count($rows)-1)?'disabled':'' ?>><?= h($label) ?></button></form>
<?php endforeach; ?></div>
<?php if(!$used&&count($rows)>1): ?><form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>"><input type="hidden" name="aksi" value="article-category-write"><input type="hidden" name="category_action" value="delete"><input type="hidden" name="category_id" value="<?= h($row['id']) ?>"><input type="hidden" name="revision" value="<?= h($revision) ?>"><label class="inline"><input type="checkbox" name="confirm" value="yes" required> Ya, hapus kategori ini</label><button class="danger">Hapus</button></form><?php endif; ?></div>
<?php if($used): ?><p class="sub">Pindahkan seluruh artikel sebelum menghapus kategori, termasuk artikel yang belum diterbitkan dan artikel di tempat sampah. <a href="?p=artikel&amp;status=all&amp;acat=<?= h($row['id']) ?>">Lihat artikelnya →</a></p><?php elseif(count($rows)===1): ?><p class="sub">Minimal satu kategori artikel harus dipertahankan.</p><?php endif; ?>
</article><?php endforeach; ?></div>
