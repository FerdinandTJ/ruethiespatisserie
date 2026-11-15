# Penyelarasan halaman main ke staging — 14 November 2026

Integrasi dari main `ed29df3` ke staging `c68f576`. Branch main tidak diubah.

## Hasil

- Halaman Beranda, Menu, produk, Snack Box, Tentang Kami, Lokasi/Kontak, serta Cara Pesan/FAQ mengikuti struktur dan renderer main saat ini.
- Galeri/carousel, form pesan WhatsApp, pencarian/filter GET, ID/EN, menu mobile, breadcrumb, metadata, sitemap, dan jam buka 07.00–21.00 WIB ikut diselaraskan.
- Warna staging tetap wine `#7A0000` dan cream `#FAF6F0`, termasuk tombol, logo, footer dan warna browser. Palet biru main tidak diterapkan. [BLUE-CREAM-THEME.md](BLUE-CREAM-THEME.md) mendeskripsikan main, bukan tema staging.
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

## Pembaruan kategori — 15 November 2026

Admin pemilik mendapat halaman Kategori. Setelah backup/pull lewat updater, hentikan sementara pengeditan lalu jalankan `php admin/categories-upgrade.php --check`, `php admin/categories-upgrade.php --apply`, dan check ulang. Tidak ada impor database atau perubahan pesanan. `categories.json` hosting merupakan data runtime yang tidak ditimpa Git; updater mem-backup/memulihkannya jika sudah ada dan tidak menyalin `.catalog.lock`. Pemilih produk PO memakai nama kategori, UID dan snapshot pesanan dipertahankan. Kategori coffee staging ikut migrasi. Panduan fitur, validasi dan rollback pasangan kategori/menu tersedia pada [CATEGORY-MANAGEMENT.md](CATEGORY-MANAGEMENT.md).

## Foto lama yang telah dihapus lewat CMS

Updater menerima penghapusan file JPG/WebP terlacak hanya di `images/menu/`, mencatat path pada `deleted-menu-images.txt` dalam backup, dan mempertahankan status penghapusan setelah pull maupun saat pull gagal. Foto yang dimodifikasi/ditambahkan sebagai perubahan Git tetap perlu ditinjau; upload CMS yang tidak dilacak Git tetap berada di tempatnya. Jangan stash pop: dapat memunculkan kembali konflik JSON atau penghapusan foto. Ini mempertahankan kondisi hosting yang sudah ada, bukan pembersihan otomatis foto lainnya.


## Shared UI synchronization — 16 November 2026

Merged the current main history into staging. Common ordering-story typography/card sizing and the centered SVG gallery close button now match main. Staging retains its wine/cream tokens, existing logo, PO navigation/routes, security rules, Midtrans and Fonnte code. Main retains its blue/cream theme and has no PO functionality.

The ordering card's sticky surface uses staging's own beige section background. Main-only blue surface compositing is not applied to staging. Hosting JSON, account/security metadata, product identities, photo binaries and payment settings are unchanged; no upgrade CLI or database migration is required.

Deploy staging using the existing updater, which preserves hosting content and authentication rules. Check the three ordering states (including reverse scroll), ID/EN and short-screen fallback; open and close gallery photos on desktop/mobile. Verify the existing pre-order navigation remains available. Do not apply staging database or payment configuration to main.

Validation for this synchronization: public/CMS HTTP regression, legacy staging catalogue and coffee UID preservation, observer/i18n/typing tests, WhatsApp form and carousel tests, Apache PO routing/access protection, PO UI and hosting updater tests passed. Desktop/mobile browser checks verified wine colours, PO navigation, reversible ordering states, fixed card dimensions, short-screen fallback, reduced motion, no-JavaScript content, and the gallery button's exact SVG centering/equal insets/click-to-close behavior. No live payment or WhatsApp request was made. Compared with the preceding staging commit, PO/admin backend files, content JSON, images and access rules are unchanged.
