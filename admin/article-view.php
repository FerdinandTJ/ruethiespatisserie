<?php
defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.');
article_authorize($me);
try{$articleState=article_read();}catch(Throwable $e){http_response_code(503);echo '<p class="err">Data artikel belum tersedia atau rusak. Perubahan tidak disimpan.</p>';return;}
$articleId=is_string($_GET['id']??null)?$_GET['id']:'';$edit=$articleId!==''?article_find($articleState,$articleId):null;
if($p==='artikel-ubah'&&!$edit){http_response_code(404);echo '<p class="err">Artikel tidak ditemukan.</p>';return;}
$editing=in_array($p,['artikel-tambah','artikel-ubah'],true);
$links=['/'=>'Beranda','/menu/'=>'Katalog Menu','/snack-box-cikarang/'=>'Snack Box','/tentang-kami/'=>'Tentang Kami','/hubungi-kami/'=>'Lokasi & Kontak','/cara-pesan/'=>'Cara Pesan & FAQ'];foreach($_menu as$product)if(($product['available']??true)!==false&&isset($product['slug']))$links[catalog_path($product)]=$product['id']['n'];
$revision=$articleState['revision'];
if(!$articleState['enabled']): ?><p class="warn">Fitur publik Artikel nonaktif. Draft dan pratinjau tetap tersedia. Pengaktifan melalui CLI setelah persetujuan rilis.</p><?php endif;
if($editing&&($edit['deletedAt']??null)===null):
 $values=$edit??['title'=>'','slug'=>'','description'=>'','category'=>'panduan','body'=>'','lang'=>'id','translationOf'=>'','related'=>[],'cover'=>'','coverAlt'=>''];
 if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['article_action']??'')==='save')foreach(['title','slug','description','category','body','lang','translationOf','coverAlt']as$key)if(is_string($_POST[$key]??null))$values[$key]=$_POST[$key];
?>
<p><a class="backlink" href="?p=artikel">← Kembali ke Artikel</a></p>
<div class="card editor"><h2><?= $edit?'Ubah artikel':'Buat draft artikel' ?></h2>
<form method="post" enctype="multipart/form-data" class="article-editor" rel="noopener">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>"><input type="hidden" name="aksi" value="article-write"><input type="hidden" name="article_action" value="save"><input type="hidden" name="article_id" value="<?= h($edit['id']??'') ?>"><input type="hidden" name="revision" value="<?= h($revision) ?>">
<label>Judul<input id="article-title" name="title" maxlength="180" required value="<?= h($values['title']) ?>"></label>
<label>Slug<input id="article-slug" name="slug" maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= h($values['slug']) ?>"><small>Otomatis dari judul jika kosong. Setelah dipublikasikan, pertahankan slug agar tautan lama tetap berfungsi.</small></label>
<label>Ringkasan / meta description <span id="article-description-count" aria-live="polite"></span><textarea id="article-description" name="description" rows="3" maxlength="155"><?= h($values['description']) ?></textarea></label>
<div class="article-admin-grid"><label>Kategori<select name="category"><?php foreach(ARTICLE_CATEGORIES as$key=>$names): ?><option value="<?= h($key) ?>" <?= $values['category']===$key?'selected':'' ?>><?= h($names[0]) ?></option><?php endforeach; ?></select></label>
<label>Bahasa konten<select name="lang"><option value="id" <?= $values['lang']==='id'?'selected':'' ?>>Indonesia</option><option value="en" <?= $values['lang']==='en'?'selected':'' ?>>English</option></select></label></div>
<label>Terjemahan dari (opsional)<select name="translationOf"><option value="">Tidak ditautkan</option><?php foreach($articleState['items']as$other)if($other['id']!==($edit['id']??'')): ?><option value="<?= h($other['id']) ?>" <?= $values['translationOf']===$other['id']?'selected':'' ?>><?= h($other['title'].' · '.$other['lang']) ?></option><?php endif; ?></select></label>
<label>Isi (Markdown)<textarea id="article-body" name="body" rows="20" maxlength="60000"><?= h($values['body']) ?></textarea></label>
<details class="article-help"><summary>Panduan Markdown</summary><p>Gunakan <code>## Judul bagian</code>, <code>### Subbagian</code>, paragraf kosong, <code>**tebal**</code>, <code>*miring*</code>, <code>[teks](/menu/)</code>, daftar <code>- isi</code> atau <code>1. isi</code>, dan kutipan <code>&gt; isi</code>. HTML mentah ditampilkan sebagai teks.</p><pre>| Kolom A | Kolom B |
| --- | --- |
| Isi A | Isi B |</pre><p>Draft dengan penanda [PERLU KONFIRMASI] tidak dapat diterbitkan.</p></details>
<?php for($i=0;$i<3;$i++): ?><label>Produk / halaman terkait <?= $i+1 ?><select name="related<?= $i ?>"><option value="">Tidak ada</option><?php foreach($links as$url=>$name): ?><option value="<?= h($url) ?>" <?= ($values['related'][$i]??'')===$url?'selected':'' ?>><?= h($name) ?></option><?php endforeach; ?></select></label><?php endfor; ?>
<label>Sampul opsional (JPG/PNG/WebP, maksimal 2 MB)<input name="article_cover" type="file" accept="image/jpeg,image/png,image/webp"></label>
<p class="sub">Disarankan memakai foto yang sesuai isi artikel. Sampul tampil pada detail dan seluruh kartu artikel; tanpa sampul, kartu tetap berupa teks. Gambar detail tetap utuh, sementara thumbnail kartu dapat terpotong mengikuti bingkai.</p>
<label>Alt foto sampul<input name="coverAlt" maxlength="180" value="<?= h($values['coverAlt']) ?>"></label>
<?php if($values['cover']!==''): ?><img class="article-admin-cover" src="/<?= h($values['cover']) ?>" alt="<?= h($values['coverAlt']) ?>"><label class="inline"><input type="checkbox" name="remove_cover" value="yes"> Lepas foto sampul</label><?php endif; ?>
<div class="stickybar article-admin-actions"><button>Simpan <?= ($edit['status']??'draft')==='published'?'perubahan artikel terbit':'draft' ?></button><button type="submit" class="ghost" formaction="article-preview.php" formenctype="application/x-www-form-urlencoded" formtarget="_blank">Pratinjau perubahan ↗</button></div>
<p class="sub">Pratinjau tidak menyimpan perubahan. Untuk melihat foto baru, simpan draft terlebih dahulu. Publikasi dilakukan lewat tombol terpisah setelah penyimpanan.</p>
</form></div>
<?php else:
 $search=is_string($_GET['aq']??null)?mb_substr(trim($_GET['aq']),0,100):'';$filter=is_string($_GET['status']??null)?$_GET['status']:'';$categoryFilter=is_string($_GET['acat']??null)?$_GET['acat']:'';
 $rows=array_values(array_filter($articleState['items'],fn($r)=>($search===''||mb_stripos($r['title'].' '.$r['description'],$search)!==false)&&($categoryFilter===''||$r['category']===$categoryFilter)&&($filter==='deleted'?$r['deletedAt']!==null:($r['deletedAt']===null&&($filter===''||$r['status']===$filter)))));usort($rows,fn($a,$b)=>strcmp($b['updatedAt'],$a['updatedAt']));
?>
<div class="card"><p><a class="linkbtn" href="?p=artikel-tambah">+ Buat artikel</a></p><form method="get" class="article-admin-filters"><input type="hidden" name="p" value="artikel"><label>Cari artikel<input name="aq" value="<?= h($search) ?>" maxlength="100"></label><label>Status<select name="status"><?php foreach([''=>'Semua aktif','draft'=>'Draft','published'=>'Terbit','deleted'=>'Dihapus']as$key=>$name): ?><option value="<?= h($key) ?>" <?= $filter===$key?'selected':'' ?>><?= h($name) ?></option><?php endforeach; ?></select></label><label>Kategori<select name="acat"><option value="">Semua</option><?php foreach(ARTICLE_CATEGORIES as$key=>$names): ?><option value="<?= h($key) ?>" <?= $categoryFilter===$key?'selected':'' ?>><?= h($names[0]) ?></option><?php endforeach; ?></select></label><button>Terapkan</button></form></div>
<div class="article-admin-list">
<?php foreach($rows as$row): ?><article class="card article-admin-row"><h2><?= h($row['title']) ?></h2><p class="sub"><?= h(ARTICLE_CATEGORIES[$row['category']][0]) ?> · <?= h($row['deletedAt']?'Dihapus':$row['status']) ?> · <?= h($row['lang']) ?><br>Dibuat: <?= h($row['createdBy']) ?> · Pengubah: <?= h($row['updatedBy']) ?> · <?= h($row['updatedAt']) ?><?php if($row['publishedAt']): ?><br>Terbit: <?= h($row['publishedAt']) ?><?php endif; ?></p><div class="article-admin-actions">
<?php if($row['deletedAt']===null): ?><a class="linkbtn" href="?p=artikel-ubah&amp;id=<?= h($row['id']) ?>">Ubah</a><a class="linkbtn" href="article-preview.php?id=<?= h($row['id']) ?>" target="_blank" rel="noopener">Pratinjau ↗</a><?php endif; ?>
<?php $actions=$row['deletedAt']!==null?['restore'=>'Pulihkan sebagai draft']:[$row['status']==='published'?'unpublish':'publish'=>$row['status']==='published'?'Sembunyikan':'Terbitkan'];if(is_owner($me)&&$row['deletedAt']===null)$actions['delete']='Hapus (dapat dipulihkan)';foreach($actions as$action=>$name): if(in_array($action,['delete','restore'],true)&&!is_owner($me))continue; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>"><input type="hidden" name="aksi" value="article-write"><input type="hidden" name="article_action" value="<?= h($action) ?>"><input type="hidden" name="article_id" value="<?= h($row['id']) ?>"><input type="hidden" name="revision" value="<?= h($revision) ?>"><label class="inline"><input type="checkbox" name="confirm" value="yes" required> Konfirmasi</label><button class="<?= $action==='delete'?'danger':'ghost' ?>"><?= h($name) ?></button></form>
<?php endforeach; ?></div></article><?php endforeach; ?>
<?php if(!$rows): ?><p class="sub">Belum ada artikel yang sesuai. Draft awal tersedia setelah CLI upgrade diterapkan.</p><?php endif; ?></div>
<?php endif; ?>
