<?php
if(!defined('RUETHIES_ADMIN')){http_response_code(404);exit;}
$variantPosted=($_POST['aksi']??'')==='save'&&($_POST['variants_present']??'')==='1';
$variantValue=static function(int $i,string $key,string $fallback='')use($variantPosted):string{
  $value=$variantPosted?($_POST['variant_'.$i.'_'.$key]??''):$fallback;return is_string($value)?$value:'';
};
?>
<section class="variant-editor" id="variant-editor">
<input type="hidden" name="variants_present" value="1">
<label><input type="checkbox" name="variants_enabled" value="1" id="variants-enabled" <?= ($variantPosted?!empty($_POST['variants_enabled']):!empty($edit['variants_enabled']))?'checked':'' ?>> Pilihan rasa / varian</label>
<p class="sub">Aktifkan untuk produk dengan beberapa pilihan. Jika dimatikan, varian tetap tersimpan tetapi tidak tampil di website. Pilihan ini untuk pesan lewat WhatsApp; tidak mengubah produk atau stok PO.</p>
<div id="variant-rows">
<?php for($vi=0;$vi<MENU_VARIANT_LIMIT;$vi++):
$v=$edit['variants'][$vi]??[];$vp='variant_'.$vi.'_';$vn=$variantValue($vi,'n',$v['id']['n']??'');
?>
<details class="variant-row" data-variant-slot="<?= $vi ?>">
<summary><span data-variant-title><?= h($vn?:'Varian baru') ?></span> <span class="hint">Ubah</span></summary>
<input type="hidden" name="<?= $vp ?>uid" value="<?= h($variantValue($vi,'uid',$v['uid']??'')) ?>">
<input type="hidden" name="<?= $vp ?>order" value="<?= h($variantValue($vi,'order',(string)$vi)) ?>" data-variant-order>
<label>Nama varian<input name="<?= $vp ?>n" maxlength="60" value="<?= h($vn) ?>" data-variant-name></label>
<label>Keterangan (opsional)<input name="<?= $vp ?>d" maxlength="300" value="<?= h($variantValue($vi,'d',$v['id']['d']??'')) ?>"></label>
<details class="optional-fields"><summary>Terjemahan Inggris <span>Opsional</span></summary>
<label>Nama Inggris<input name="<?= $vp ?>ne" maxlength="60" value="<?= h($variantValue($vi,'ne',$v['en']['n']??'')) ?>"></label>
<label>Keterangan Inggris<input name="<?= $vp ?>de" maxlength="300" value="<?= h($variantValue($vi,'de',$v['en']['d']??'')) ?>"></label></details>
<label>Foto varian (opsional, JPG/PNG/WebP, maks 5 MB)<input type="file" name="<?= $vp ?>photo" accept="image/jpeg,image/png,image/webp" data-preview="prev-variant-<?= $vi ?>"></label>
<div class="preview" id="prev-variant-<?= $vi ?>"><?php if(!empty($v['img'])): ?><img class="thumb-sm" src="../<?= h($v['img']) ?>" alt="Foto varian saat ini"><?php endif; ?></div>
<p class="hint">Tanpa foto varian, halaman memakai foto utama produk.</p>
<?php if(!empty($v['img'])): ?><label><input type="checkbox" name="<?= $vp ?>photo_remove" value="1"> Hapus foto varian</label><?php endif; ?>
<div class="variant-actions"><button type="button" data-variant-move="up" hidden>↑ Naik</button><button type="button" data-variant-move="down" hidden>↓ Turun</button></div>
<label><input type="checkbox" name="<?= $vp ?>delete" value="1" data-variant-delete <?= $variantValue($vi,'delete')==='1'?'checked':'' ?>> Hapus varian ini saat disimpan</label>
</details>
<?php endfor; ?>
</div>
<button type="button" id="variant-add" hidden>+ Tambah varian</button>
<p class="hint">Maksimal <?= MENU_VARIANT_LIMIT ?> varian. Klik Simpan menu untuk menerapkan semua perubahan.</p>
</section>
