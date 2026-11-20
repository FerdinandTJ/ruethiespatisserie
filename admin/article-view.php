<?php
defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.');
article_authorize($me);
try{$articleState=article_read();}catch(Throwable $e){http_response_code(503);echo '<p class="err">Artikel belum dapat dibuka. Minta pengelola website memeriksa penyimpanannya. Tidak ada perubahan yang disimpan.</p>';return;}
$articleId=is_string($_GET['id']??null)?$_GET['id']:'';$edit=$articleId!==''?article_find($articleState,$articleId):null;
if($p==='artikel-ubah'&&!$edit){http_response_code(404);echo '<p class="err">Artikel tidak ditemukan.</p>';return;}
$editing=in_array($p,['artikel-tambah','artikel-ubah'],true);
$links=['/'=>'Beranda','/menu/'=>'Katalog Menu','/snack-box-cikarang/'=>'Snack Box','/tentang-kami/'=>'Tentang Kami','/hubungi-kami/'=>'Lokasi & Kontak','/cara-pesan/'=>'Cara Pesan & FAQ'];foreach($_menu as$product)if(($product['available']??true)!==false&&isset($product['slug']))$links[catalog_path($product)]=$product['id']['n'];
$displayTime=static function(string $value):string{try{return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d/m/Y, H.i').' WIB';}catch(Exception $e){return 'Waktu belum tersedia';}};
$displayAuthor=static fn(string $value):string=>$value==='CLI seed'?'Persiapan awal':$value;
$revision=$articleState['revision'];$articleCategories=article_categories($articleState);
if($p==='artikel-kategori'){require __DIR__.'/article-category-view.php';return;}
if(!$articleState['enabled']): ?><p class="warn">Artikel belum ditampilkan kepada pengunjung. Anda tetap bisa menulis, menyimpan, dan melihat contohnya. Minta pengelola website mengaktifkan halaman Artikel; tombol Terbitkan saja belum akan menampilkannya.</p><?php endif;
if($editing&&($edit['deletedAt']??null)===null):
 $values=$edit??['title'=>'','slug'=>'','description'=>'','category'=>array_key_first($articleCategories),'body'=>'','lang'=>'id','translationOf'=>'','related'=>[],'cover'=>'','coverAlt'=>''];
 if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['article_action']??'')==='save')foreach(['title','slug','description','category','body','lang','translationOf','coverAlt']as$key)if(is_string($_POST[$key]??null))$values[$key]=$_POST[$key];
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['article_action']??'')==='save'){try{$values['body']=article_input_body($_POST);}catch(DomainException $e){$visualRecovery=true;}}
?>
<p><a class="backlink" href="?p=artikel">← Kembali ke Artikel</a></p>
<div class="card editor"><h2><?= $edit?'Ubah artikel':'Tulis artikel baru' ?></h2>
<form method="post" enctype="multipart/form-data" class="article-editor" rel="noopener">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>"><input type="hidden" name="aksi" value="article-write"><input type="hidden" name="article_action" value="save"><input type="hidden" name="article_id" value="<?= h($edit['id']??'') ?>"><input type="hidden" name="revision" value="<?= h($revision) ?>">
<label>Judul<input id="article-title" name="title" maxlength="180" required value="<?= h($values['title']) ?>"></label>
<label>Alamat artikel<input id="article-slug" name="slug" maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= h($values['slug']) ?>"><small>Terisi otomatis dari judul. Gunakan huruf kecil dan tanda hubung, misalnya cara-memesan-snack-box. Setelah artikel diterbitkan, sebaiknya jangan diubah agar tautan yang sudah dibagikan tetap bisa dibuka.</small></label>
<label>Ringkasan artikel <span id="article-description-count" aria-live="polite"></span><textarea id="article-description" name="description" rows="3" maxlength="155"><?= h($values['description']) ?></textarea><small>Maksimal 155 karakter. Ringkasan tampil di daftar artikel dan membantu mesin pencari menjelaskan isi artikel.</small></label>
<div class="article-admin-grid"><label>Kategori<select name="category"><?php foreach($articleCategories as$key=>$names): ?><option value="<?= h($key) ?>" <?= $values['category']===$key?'selected':'' ?>><?= h($names[0]) ?></option><?php endforeach; ?></select></label>
<label>Bahasa konten<select name="lang"><option value="id" <?= $values['lang']==='id'?'selected':'' ?>>Indonesia</option><option value="en" <?= $values['lang']==='en'?'selected':'' ?>>Inggris</option></select></label></div>
<label>Artikel asal yang diterjemahkan (opsional)<select name="translationOf"><option value="">Bukan terjemahan artikel lain</option><?php foreach($articleState['items']as$other)if($other['id']!==($edit['id']??'')): ?><option value="<?= h($other['id']) ?>" <?= $values['translationOf']===$other['id']?'selected':'' ?>><?= h($other['title'].' · '.($other['lang']==='en'?'Inggris':'Indonesia')) ?></option><?php endif; ?></select></label>
<?php if(!empty($visualRecovery)): ?><details class="article-help" open><summary>Isi visual belum dapat disimpan</summary><p>Perbaiki format yang disebutkan pada pesan kesalahan. Salinan masukan tersedia di bawah agar tulisan tidak hilang. Editor menampilkan isi terakhir yang dapat dibaca.</p><textarea readonly rows="8" aria-label="Salinan isi visual yang belum tersimpan"><?= h(is_string($_POST['body_html']??null)?$_POST['body_html']:'') ?></textarea></details><?php endif; ?>
<div class="article-body-field">
<label for="article-body">Isi artikel</label>
<p class="sub" id="article-editor-help">Tulis seperti biasa. Blok teks yang ingin diubah, lalu pilih format. Gunakan Judul bagian untuk topik baru dan Subbagian untuk penjelasannya.</p>
<div id="article-visual-shell" hidden>
<div class="article-visual-toolbar" role="group" aria-label="Format isi artikel">
<label class="article-format-label">Jenis tulisan<select id="article-block-format"><option value="p">Paragraf</option><option value="h2">Judul bagian</option><option value="h3">Subbagian</option><option value="blockquote">Kutipan</option></select></label>
<?php foreach(['bold'=>'Tebal','italic'=>'Miring','insertUnorderedList'=>'Daftar poin','insertOrderedList'=>'Daftar angka','createLink'=>'Tautan','unlink'=>'Lepas tautan','undo'=>'Urungkan','redo'=>'Ulangi']as$command=>$label): ?><button type="button" class="ghost" data-editor-command="<?= h($command) ?>"><?= h($label) ?></button><?php endforeach; ?>
</div>
<div id="article-visual" class="article-visual-content" contenteditable="true" role="textbox" aria-label="Isi artikel" aria-multiline="true" aria-describedby="article-editor-help" spellcheck="true"><?= article_markdown($values['body'])['html'] ?></div>
<button type="button" class="ghost article-source-toggle" id="article-source-toggle">Buka mode teks</button>
</div>
<textarea id="article-body" name="body" rows="20" maxlength="60000" aria-label="Isi artikel dalam mode teks"><?= h($values['body']) ?></textarea>
<input type="hidden" name="body_mode" id="article-body-mode" value="markdown">
<textarea name="body_html" id="article-body-html" hidden><?php if(($_POST['body_mode']??'')==='visual'&&is_string($_POST['body_html']??null))echo h($_POST['body_html']); ?></textarea>
<p id="article-editor-status" class="sub" role="status" aria-live="polite"></p>
</div>
<details class="article-help"><summary>Bantuan menulis artikel</summary><p>Gunakan tombol format untuk judul bagian, tulisan tebal atau miring, daftar, dan tautan. Tautan dapat memakai alamat website lengkap atau alamat halaman seperti /menu/. Gunakan Lihat tampilan untuk memeriksa hasil sebelum menyimpan. Artikel lama dan tabel sederhana tetap dapat dibuka. Daftar bertingkat dan gabungan format pada teks yang sama belum didukung.</p><p>Mode teks adalah pilihan lanjutan untuk format Markdown. Tanpa JavaScript, isi tetap dapat disunting melalui mode teks.</p><p>Tulisan [PERLU KONFIRMASI] menandakan informasi belum pasti. Lengkapi dan hapus penanda tersebut sebelum menerbitkan artikel.</p></details>
<?php for($i=0;$i<3;$i++): ?><label>Produk / halaman terkait <?= $i+1 ?><select name="related<?= $i ?>"><option value="">Tidak ada</option><?php foreach($links as$url=>$name): ?><option value="<?= h($url) ?>" <?= ($values['related'][$i]??'')===$url?'selected':'' ?>><?= h($name) ?></option><?php endforeach; ?></select></label><?php endfor; ?>
<label>Sampul opsional (JPG/PNG/WebP, maksimal 2 MB)<input name="article_cover" type="file" accept="image/jpeg,image/png,image/webp"></label>
<p class="sub">Disarankan memakai foto yang sesuai isi artikel. Sampul tampil pada detail dan seluruh kartu artikel; tanpa sampul, kartu tetap berupa teks. Foto pada halaman artikel tampil utuh. Foto kecil di daftar artikel menyesuaikan bingkai dan dapat terpotong.</p>
<label>Deskripsi foto (wajib jika memakai sampul)<small>Jelaskan isi foto, misalnya: Snack box Ruethies dalam kemasan biru. Deskripsi ini membantu pembaca yang tidak dapat melihat foto.</small><input name="coverAlt" maxlength="180" value="<?= h($values['coverAlt']) ?>"></label>
<?php if($values['cover']!==''): ?><img class="article-admin-cover" src="/<?= h($values['cover']) ?>" alt="<?= h($values['coverAlt']) ?>"><label class="inline"><input type="checkbox" name="remove_cover" value="yes"> Lepas foto sampul</label><?php endif; ?>
<div class="stickybar article-admin-actions"><button>Simpan <?= ($edit['status']??'draft')==='published'?'perubahan pada artikel terbit':'artikel belum terbit' ?></button><button type="submit" class="ghost" formaction="article-preview.php" formenctype="application/x-www-form-urlencoded" formtarget="_blank">Lihat tampilan ↗</button></div>
<p class="sub">Lihat tampilan tidak menyimpan perubahan. Simpan dahulu untuk melihat foto baru. Artikel baru belum terlihat oleh pengunjung sampai Anda memilih Terbitkan di daftar artikel. Jika artikel sudah terbit, menyimpan perubahan langsung memperbarui isinya saat halaman Artikel aktif.</p>
</form></div>
<?php else:
 $search=is_string($_GET['aq']??null)?mb_substr(trim($_GET['aq']),0,100):'';$filter=is_string($_GET['status']??null)?$_GET['status']:'';$categoryFilter=is_string($_GET['acat']??null)?$_GET['acat']:'';
 $rows=array_values(array_filter($articleState['items'],fn($r)=>($search===''||mb_stripos($r['title'].' '.$r['description'],$search)!==false)&&($categoryFilter===''||$r['category']===$categoryFilter)&&($filter==='all'||($filter==='deleted'?$r['deletedAt']!==null:($r['deletedAt']===null&&($filter===''||$r['status']===$filter))))));usort($rows,fn($a,$b)=>strcmp($b['updatedAt'],$a['updatedAt']));
?>
<details class="article-help"><summary>Cara menulis dan menerbitkan artikel</summary><p class="sub">Cara mengelola artikel: <b>1.</b> Buat dan simpan tulisan. <b>2.</b> Periksa lewat Lihat tampilan. <b>3.</b> Centang persetujuan lalu pilih Terbitkan. Artikel yang dihapus hanya dapat dikembalikan oleh pemilik.</p></details>
<div class="card"><div class="article-admin-actions"><a class="btn" href="?p=artikel-tambah">+ Buat artikel</a><?php if(is_owner($me)): ?><a class="btn ghost" href="?p=artikel-kategori">Kategori Artikel →</a><?php endif; ?></div><form method="get" class="article-admin-filters"><input type="hidden" name="p" value="artikel"><label>Cari artikel<input name="aq" value="<?= h($search) ?>" maxlength="100"></label><label>Status<select name="status"><?php foreach([''=>'Belum / sudah terbit','all'=>'Semua, termasuk dihapus','draft'=>'Belum diterbitkan','published'=>'Sudah diterbitkan','deleted'=>'Dihapus']as$key=>$name): ?><option value="<?= h($key) ?>" <?= $filter===$key?'selected':'' ?>><?= h($name) ?></option><?php endforeach; ?></select></label><label>Kategori<select name="acat"><option value="">Semua</option><?php foreach($articleCategories as$key=>$names): ?><option value="<?= h($key) ?>" <?= $categoryFilter===$key?'selected':'' ?>><?= h($names[0]) ?></option><?php endforeach; ?></select></label><button>Tampilkan hasil</button></form></div>
<div class="article-admin-list">
<?php foreach($rows as$row): ?><article class="card article-admin-row"><h2><?= h($row['title']) ?></h2><p class="sub"><?= h($articleCategories[$row['category']][0]) ?> · <?= h($row['deletedAt']?'Dihapus':($row['status']==='published'?'Sudah diterbitkan':'Belum diterbitkan')) ?> · <?= h($row['lang']==='en'?'Inggris':'Indonesia') ?><br>Dibuat: <?= h($displayAuthor($row['createdBy'])) ?> · Terakhir diubah oleh: <?= h($displayAuthor($row['updatedBy'])) ?> · <?= h($displayTime($row['updatedAt'])) ?><?php if($row['publishedAt']): ?><br>Terbit: <?= h($displayTime($row['publishedAt'])) ?><?php endif; ?></p><div class="article-admin-actions">
<?php if($row['deletedAt']===null): ?><a class="btn ghost" href="?p=artikel-ubah&amp;id=<?= h($row['id']) ?>">Ubah</a><a class="btn ghost" href="article-preview.php?id=<?= h($row['id']) ?>" target="_blank" rel="noopener">Lihat tampilan ↗</a><?php endif; ?>
<?php $actions=$row['deletedAt']!==null?['restore'=>'Kembalikan']:[$row['status']==='published'?'unpublish':'publish'=>$row['status']==='published'?'Sembunyikan':'Terbitkan'];if(is_owner($me)&&$row['deletedAt']===null)$actions['delete']='Hapus';foreach($actions as$action=>$name): if(in_array($action,['delete','restore'],true)&&!is_owner($me))continue; ?>
<?php if($action==='delete'): ?>
<details class="category-delete article-delete"><summary aria-label="<?= h('Hapus artikel '.$row['title']) ?>"><?= icon('trash',16) ?> Hapus</summary>
<div class="category-delete-panel"><p><b>Hapus artikel ini?</b></p><p class="sub">Artikel tidak akan tampil di website. Pemilik bisa mengembalikannya nanti.</p>
<?php endif; ?>
<form method="post" class="<?= $action==='delete'?'article-delete-form':'article-state-form' ?>">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>"><input type="hidden" name="aksi" value="article-write"><input type="hidden" name="article_action" value="<?= h($action) ?>"><input type="hidden" name="article_id" value="<?= h($row['id']) ?>"><input type="hidden" name="revision" value="<?= h($revision) ?>">
<label class="inline"><input type="checkbox" name="confirm" value="yes" required> <?= h(['publish'=>'Ya, terbitkan','unpublish'=>'Ya, sembunyikan','delete'=>'Ya, hapus artikel ini','restore'=>'Ya, kembalikan tanpa menerbitkan'][$action]) ?></label>
<?php if($action==='delete'): ?><div class="category-delete-buttons"><button type="button" class="ghost" data-category-cancel hidden>Batal</button><button class="danger">Hapus</button></div><?php else: ?><button class="<?= $action==='unpublish'?'ghost':'' ?>"><?= h($name) ?></button><?php endif; ?>
</form>
<?php if($action==='delete'): ?></div></details><?php endif; ?>
<?php endforeach; ?></div></article><?php endforeach; ?>
<?php if(!$rows): ?><p class="sub">Belum ada artikel yang sesuai. Pilih Buat artikel untuk mulai menulis, atau coba pencarian dan pilihan status lain.</p><?php endif; ?></div>
<?php endif; ?>
