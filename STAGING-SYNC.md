# Penyelarasan halaman main ke staging — 14 November 2026

Integrasi dari main `ed29df3` ke staging `c68f576`. Branch main tidak diubah.

## Hasil

- Halaman Beranda, Menu, produk, Snack Box, Tentang Kami, Lokasi/Kontak, serta Cara Pesan/FAQ mengikuti struktur dan renderer main saat ini.
- Galeri/carousel, form pesan WhatsApp, pencarian/filter GET, ID/EN, menu mobile, breadcrumb, metadata, sitemap, dan jam buka 07.00–21.00 WIB ikut diselaraskan.
- Warna staging tetap wine `#7A0000` dan cream `#FAF6F0`, termasuk tombol, logo, footer dan warna browser. Palet biru main tidak diterapkan. BLUE-CREAM-THEME.md mendeskripsikan main, bukan tema staging.
- Pre-order tersedia pada navigasi desktop, mobile, dan footer. Routing `/pre-order/`, `/pre-order/status/`, `/pre-order/return/` dan webhook tetap terpisah dari renderer publik, termasuk body POST dan query magic link.
- Seluruh controller/admin/schema PO, Midtrans, Fonnte, konfigurasi privat, pengecekan stok dan worker cron dipertahankan. Tidak ada migrasi database dalam sinkronisasi ini.
- Tidak ada perubahan berkas gambar atau kompresi. Optimasi foto Lighthouse yang pernah dibatalkan tidak diterapkan kembali.

## Konten hosting dan identitas produk

`menu.json`, `faq.json`, dan `atmosphere.json` hosting tetap dipertahankan oleh updater. Jangan menggantinya dengan salinan laptop: UID katalog dapat dirujuk oleh jadwal PO. Kategori coffee lama staging tetap didukung termasuk kategori, ID/EN, halaman produk dan editor. Host header tidak dapat mengaktifkan kategori ini di produksi.

Katalog repository mengikuti main, tetapi hal itu **tidak mengganti katalog hosting**. Memindahkan katalog PO ke produk baru merupakan pekerjaan terpisah yang perlu mempertahankan/pemetaan UID; tidak dilakukan otomatis.

CLI `admin/menu-copy-upgrade.php` yang dibawa dari main tersedia untuk penyelarasan deskripsi produk yang namanya cocok. `--check` tidak mengubah data; `--apply` bersifat opsional, membuat backup, mempertahankan UID/foto/status dan tidak menambahkan produk hilang. Ia juga mengganti nama/slug Lapis Surabaya yang cocok menjadi Lapis Marble. URL lama Lapis Surabaya menghasilkan 404, mengikuti main.

## Deployment di SSH hPanel

Hentikan sementara pengeditan admin. Jalankan **di public_html/staging**, bukan public_html:

```sh
cd /home/u857797475/domains/ruethiespatisserie.com/public_html/staging
git status --short
git fetch origin staging
STAGING_DEPLOY_SCRIPT=$(mktemp)
git show origin/staging:tools/update-staging.sh > "$STAGING_DEPLOY_SCRIPT" && bash "$STAGING_DEPLOY_SCRIPT"
rm -f "$STAGING_DEPLOY_SCRIPT"
```

Updater mem-backup staging dan data privat di luar web root, mempertahankan JSON hosting serta Basic Auth hPanel bila ada. Konfigurasi tanpa Basic Auth yang telah dipilih pemilik juga dipertahankan. Perubahan kode hosting yang tidak dikenali membuat updater berhenti; tinjau dahulu. Jangan `git stash pop`.

Setelah update:

```sh
php admin/seo-upgrade.php --check
php admin/po-cron.php --check
php admin/midtrans-upgrade.php --check
php admin/menu-copy-upgrade.php --check
```

Metadata SEO yang sudah siap tidak perlu di-upgrade lagi. Jika check menyebut metadata belum siap, jalankan `php admin/seo-upgrade.php --apply`, lalu check ulang. Tidak perlu menjalankan ulang security-upgrade, installer/seed PO, atau impor SQL untuk pembaruan ini. Jangan menampilkan isi po-config.php/token saat mengirim hasil pemeriksaan.

Jika ingin menyamakan deskripsi katalog yang cocok dengan main, setelah meninjau hasil check jalankan `php admin/menu-copy-upgrade.php --apply`. FAQ hosting tidak ditimpa; jam buka pada jawaban FAQ lama dapat diselaraskan ke 07.00–21.00 WIB melalui editor FAQ.

Bersihkan cache staging lalu cek halaman langsung/refresh, login admin, daftar PO, invoice, pembayaran sandbox, dan cron/Fonnte. Header `X-Robots-Tag: noindex, nofollow, noarchive` staging tetap berlaku. Canonical bisnis tetap domain produksi sebagaimana sebelumnya; staging tidak didaftarkan ke Search Console.

## Validasi lokal

- Regresi SEO/public HTTP, routing Apache, CMS, autentikasi/keamanan, upgrade, katalog, foto, form WhatsApp, carousel dan updater lulus pada fixture sementara.
- `tests/staging-legacy.php` menguji katalog lama/coffee, UID, halaman baru, ID/EN dan tautan Pre-order.
- `tests/po-midtrans.php` lulus pada MySQL lokal dengan provider tiruan: checkout, concurrency, webhook, pembayaran, fulfillment, kuota habis, antrean notifikasi pelanggan/pemilik, deduplikasi, dan override nomor uji. Tidak ada API/payment atau WhatsApp nyata dipanggil.
- Suite historis `po-flow.php`, `po-manual.php`, dan `po-whatsapp.php` masih memakai fixture pembuatan jadwal manual/legacy yang sudah ditolak staging sejak sebelum integrasi ini. Ketiganya bukan suite penerimaan flow aktif; kegagalan fixture tersebut tidak ditutup dengan mengaktifkan kembali flow yang telah dihentikan. Regresi flow aktif berada pada po-midtrans dan po-ui.
- Preview browser 320/390/1280/1440: tujuh tipe halaman tanpa overflow, wine–cream terjaga, menu mobile/ID–EN berfungsi, tidak ada exception JavaScript.

Pemulihan: gunakan backup deployment yang dicetak updater. Pemulihan kode/data publik harus cocok; jangan memulihkan akun atau metadata keamanan lama. Database PO dan konfigurasi privat tidak berubah oleh integrasi ini.
