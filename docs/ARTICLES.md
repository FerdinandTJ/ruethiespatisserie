# Artikel — pengelolaan dan akses publik

Implementasi pada `main`, 17 November 2026. Tahap 1–5 telah dirilis; penerbitan artikel dan aktivasi flag dilakukan pemilik melalui admin/SSH. Tahap 6 menambahkan akses publik atas persetujuan pemilik, tanpa mengubah data artikel hosting.

## Audit dan keputusan

- Situs memakai PHP native dengan front controller `index.php`, renderer bersama `public-lib/profile.php`, serta JSON untuk konten CMS. Tidak ada framework, proses build baru, dependency baru, atau migrasi SQL.
- `/admin` sudah memiliki autentikasi, sesi, CSRF, pembatasan percobaan, dan peran `owner`/`staff`. Atas persetujuan pemilik, **Staf menjadi Admin artikel**: membuat, mengubah, pratinjau, menerbitkan, dan menyembunyikan. Hanya Pemilik yang dapat menghapus dan memulihkan.
- Halaman artikel mengikuti **biru–cream main**. Admin tetap memakai komponen admin yang ada. PO, Midtrans, Fonnte, dan staging tidak diubah.
- Artikel tersimpan di `ruethies-data/articles.json`, di luar `public_html`. Berkas memuat artikel, flag `enabled`, revisi, dan audit perubahan. Lock bersama, validasi, backup sebelum perubahan, dan atomic rename melindungi penulisan. Sampul berada di `images/articles/`, diabaikan Git.
- Flag `enabled` merupakan konfigurasi sederhana setara `ARTICLES_ENABLED`, dikelola lewat CLI. Default **false**. Artikel baru selalu draft. Tidak ada perubahan akun atau konfigurasi keamanan.
- Kategori artikel dikelola Pemilik dari bagian Artikel, terpisah dari kategori menu. Data kategori tersimpan di berkas privat artikel yang sama agar artikel dan kategorinya memiliki satu lock/revisi.
- ID/EN tetap menggunakan URL yang sama seperti situs sekarang. UI diterjemahkan; isi artikel tetap dalam bahasa penulis. Tautan terjemahan hanya ditampilkan jika pasangan artikel benar-benar terbit. Tidak membuat URL Inggris baru atau terjemahan otomatis.

**Tahap 6:** footer memiliki tautan Artikel sebelum Cara Pesan & FAQ. Beranda menampilkan maksimal tiga artikel terbaru pada “Dari dapur Ruethies”, setelah perkenalan dan sebelum Lokasi. Halaman produk/Snack Box menampilkan maksimal tiga artikel “Baca juga” jika field halaman/produk terkait artikel mencantumkan URL produk tersebut. Semuanya hanya muncul jika flag aktif dan ada artikel published. Header tetap dipertahankan. Kartu memakai thumbnail jika ada sampul; jika tidak, kartu tetap berupa teks tanpa area gambar kosong. Tidak ada skrip artikel tambahan pada halaman lama. Saat flag mati atau publikasi terakhir disembunyikan, seluruh tambahan hilang.

## Penggunaan admin

1. Login seperti biasa, buka **Artikel** di navigasi admin (`/admin/index.php?p=artikel`).
2. Pilih tambah artikel. Isi judul, slug, kategori, ringkasan maksimal 155 karakter, bahasa, isi Markdown, dan maksimal tiga halaman/produk terkait. Slug otomatis tersedia; sesudah terbit sebaiknya dipertahankan agar tautan tidak putus.
3. **Simpan draft**. Judul dan slug harus valid; isi/ringkasan dapat dilengkapi kemudian. Gunakan H2 (`##`) dan H3 (`###`), paragraf, tebal/miring, tautan, daftar, kutipan, dan tabel Markdown. HTML mentah ditampilkan sebagai teks, bukan dijalankan.
4. Sampul opsional: JPG/PNG/WebP maksimal **2 MiB**, minimal 2 piksel, maksimal 8 megapiksel dan sisi 8.192 piksel. Alt wajib jika memakai sampul. Server memvalidasi dan mengubahnya ke WebP dengan nama acak. Sampul tetap opsional, dengan pengingat relevansi foto di editor. Unggahan menghasilkan thumbnail 480/800 piksel jika sumber lebih lebar; gambar kecil tidak diperbesar. Simpan dahulu untuk melihat sampul baru di pratinjau.
5. **Pratinjau perubahan** membuka tab privat dengan template publik, tidak menyimpan perubahan. Hanya akun login dengan peran yang sesuai dapat mengaksesnya; respons `no-store` dan `noindex`.
6. Setelah isi disetujui, simpan lalu konfirmasi **Terbitkan** pada daftar. Seluruh field wajib harus lengkap, slug unik, dan tidak boleh ada penanda `[PERLU KONFIRMASI`. H1 di isi dan H3 sebelum H2 pertama ditolak. Saat flag masih mati, status published tetap tidak terbuka ke publik.
7. **Sembunyikan** mengembalikan ke draft. **Hapus** hanya Pemilik, berupa soft delete. Buka daftar terhapus untuk **Pulihkan**; hasil pemulihan selalu draft, bukan langsung terbit. Slug artikel terhapus tetap dicadangkan.

Form memakai revisi untuk menolak penyimpanan halaman yang kedaluwarsa. Muat ulang sebelum mengedit kembali. Aksi tulis dibatasi 60 per akun/15 menit, unggahan 20 per akun/15 menit. Audit privat menyimpan 5.000 aksi terbaru, disertai log aktivitas yang sudah ada. Penggantian sampul tidak menghapus berkas lama agar pemulihan tetap aman.

## URL, SEO, dan fallback

- Daftar `/artikel/`, detail `/artikel/{slug}/`; tanpa trailing slash diarahkan 301.
- Flag mati, tidak ada publikasi, draft, tersembunyi, atau terhapus: URL publik menghasilkan **404 + noindex**. JSON artikel rusak menghasilkan **503** pada halaman artikel; beranda lama tetap berfungsi. Sitemap tetap menyediakan URL lama bila penyimpanan artikel bermasalah.
- Saat aktif, hanya publikasi yang masuk daftar dan sitemap. Canonical, metadata sosial, Article dan BreadcrumbList dibentuk dari data aktual. Tidak ada rating atau harga rekaan.
- Daftar berisi 12 artikel per halaman. Filter kategori ditingkatkan JavaScript dan memakai parameter URL. Tanpa JavaScript, daftar tetap menampilkan semua kategori dengan paginasi biasa.
- Detail memuat TOC jika memiliki setidaknya tiga H2, estimasi baca, WhatsApp ke nomor situs, maksimal tiga halaman terkait, dan tiga artikel lain.
- Konten utama serta tautan WhatsApp tersedia tanpa JavaScript. Bahasa isi tidak berubah ketika bahasa UI ditukar.

## Tiga draft dan konfirmasi yang diperlukan

CLI hanya menambahkan draft yang belum ada berdasarkan ID permanen; tidak menimpa suntingan admin dan tidak menyalakan flag.

1. **Berapa jumlah snack box untuk 20, 50, dan 100 orang?** — Tim Ruethies perlu meninjau panduan jumlah dan menentukan informasi paket yang boleh diterbitkan. Contoh satu kotak per penerima dinyatakan sebagai panduan umum, bukan minimum pesanan.
2. **Ide isi snack box untuk rapat kantor, arisan, dan pengajian** — Konfirmasi produk yang benar-benar dapat menjadi isi snack box, kombinasi paket, dan informasi kemasan yang boleh diumumkan.
3. **Cara memesan di Ruethies: dari memilih menu hingga konfirmasi** — Konfirmasi kembali alur pemesanan dan jam buka. Data lokal yang diperiksa mencantumkan 07.00–21.00 WIB; tidak ditemukan pertentangan 09.00 pada FAQ lokal. Konten hosting tetap perlu ditinjau pemilik.

Ketiga penanda `[PERLU KONFIRMASI: ...]` berada dalam draft dan memblokir penerbitan. Tidak ada ketentuan harga, minimum, tenggat, pengiriman, sertifikasi, bahan, atau kesehatan yang dibuat sendiri. Tidak ada keputusan hak akses/tema yang masih menunggu jawaban; keduanya telah disetujui.

## Persiapan deployment — setelah persetujuan

Commit sebelum pekerjaan: **`3be5e0d522b0fc030fc9c29790582349e8efbd72`**. Ini baseline lokal; periksa commit hosting sebenarnya sebelum deployment. Jangan menganggap keduanya sama.

Hentikan sementara pengeditan admin. Dari SSH, sebelum pull, buat backup di luar web root:

```sh
cd /home/u857797475/domains/ruethiespatisserie.com
(
  set -e
  umask 077
  BACKUP_DIR="deploy-backups/articles-before-$(date +%Y%m%d-%H%M%S)"
  mkdir -p "$BACKUP_DIR"
  git -C public_html rev-parse HEAD > "$BACKUP_DIR/commit.txt"
  git -C public_html status --short > "$BACKUP_DIR/status.txt"
  tar --exclude=public_html/staging --exclude=public_html/.git -czf "$BACKUP_DIR/public_html.tar.gz" public_html
  tar -czf "$BACKUP_DIR/private-data.tar.gz" ruethies-data
  echo "$BACKUP_DIR"
)
```

Backup mengandung data privat; jangan pindahkan ke `public_html`. Setelah persetujuan push, pull memakai `umask 022` dan prosedur yang mempertahankan perubahan CMS hosting. Jika `git status` menunjukkan perubahan JSON/konten, arsipkan dan selaraskan dahulu; jangan menimpanya dengan salinan laptop atau memakai `reset --hard`.

Sesudah kode tersedia di hosting, dari `public_html`:

```sh
php admin/articles-upgrade.php --check
php admin/articles-upgrade.php --apply
php admin/articles-upgrade.php --check
```

`--check` hanya membaca. `--apply` aditif/idempoten, membuat tiga draft, mempertahankan flag yang ada, dan membuat backup jika data lama diubah. Pada pemasangan pertama flag tetap false. Tidak perlu phpMyAdmin.

Aktivasi dan penerbitan tetap merupakan tindakan pemilik. Setelah isi disetujui, `php admin/articles-upgrade.php --enable` mengaktifkan URL publik untuk artikel yang berstatus published. `--disable` mematikan keterbukaan publik tanpa menghapus artikel. Setelah kode tahap 6 di-pull, tautan serta blok artikel muncul otomatis sesuai publikasi. Tidak perlu mengulang seed atau migrasi untuk tahap 6; flag dan artikel hosting dipertahankan.

Uji di hosting: login Pemilik/Staf, lihat tiga draft, pratinjau privat, pastikan URL draft 404 dan sitemap tidak memuatnya. Penerbitan/penghapusan agresif dilakukan pada fixture lokal, bukan konten produksi.

## Rollback

Untuk menghentikan akses artikel segera dari `public_html`:

```sh
php admin/articles-upgrade.php --disable
```

Untuk membatalkan kode, lakukan di checkout pengembangan bersih, setelah meninjau diff. Rentang berikut hanya mencakup implementasi artikel yang sekarang:

```sh
git revert --no-commit 3be5e0d..6bcb291
git commit -m "revert: disable article feature and restore previous public behavior"
```

Tinjau hasil dan tes sebelum push/pull melalui prosedur deployment yang disetujui. Jangan memasukkan perubahan logo atau catatan pribadi. Jangan menjalankan revert tersebut langsung pada checkout hosting yang memiliki perubahan CMS.

Rollback kode tidak perlu menghapus `articles.json`, backup artikel, atau sampul. **Jangan memulihkan users.json, sesi, auth_version, atau direktori privat keseluruhan dari backup lama.** Jika data artikel rusak, flag CLI dapat gagal; rollback kode tetap menghilangkan rute artikel. Pemulihan data dilakukan khusus dari backup artikel yang valid, saat admin tidak digunakan, dengan lock artikel, dan flag tetap false. Akun maupun metadata keamanan terbaru dipertahankan.

## Pemeriksaan yang telah dilakukan

- `php tests/articles.php`: fixture terisolasi; seed idempoten, flag, autentikasi/role, CSRF, pratinjau, publikasi/sembunyi/hapus/pulih, penanda, slug, XSS, unggahan native/base64, audit, race revisi, serta JSON rusak.
- Regresi lulus: `tests/seo-public.php`, `tests/categories-http.php`, `tests/admin-ui.php`, `tests/security-auth.php`, `tests/seo-routing.php`, dan `node tests/admin-upload.cjs`. Routing memakai Apache lokal, termasuk isolasi staging.
- Browser terisolasi: 360, 390, 768, dan 1440 px; tanpa overflow, filter/paginasi, ID/EN, WhatsApp, tabel/TOC, pratinjau, unggahan nyata, serta fallback tanpa JavaScript. Tidak mengirim pesan WhatsApp.
- Lighthouse lokal pada konfigurasi yang sama: artikel Performance/Accessibility/SEO **100/100/100**, LCP sekitar 1,35 dtk, CLS 0,017, TBT 0. Referensi halaman Cara Pesan lama: **100/98/100**, LCP 1,20 dtk, CLS 0,037, TBT 0. Ini pembanding laboratorium lokal, bukan jaminan hasil produksi atau peringkat Google. Tidak mengubah halaman referensi untuk mengejar skor.
- Sintaks PHP/JS dan `git diff --check` lulus. Tidak ada dependency repo baru, perubahan foto lama, atau publikasi hosting.

Untuk meninjau sendiri tanpa menyentuh akun/data nyata:

```sh
php tools/preview-articles.php --port=9011
```

Buka `http://127.0.0.1:9011/artikel/`. Akun **khusus fixture lokal**: `owner` atau `staff`, kata sandi `Local-articles-preview-2026`. Preview membuat publikasi demonstrasi terpisah untuk menguji daftar/paginasi; tiga seed tetap draft. Ctrl+C mematikan server dan membersihkan data sementara. Jangan mengunggah fixture ini ke hosting.

## Commit dan berkas

- `6e91d0b`: `public-lib/articles.php`, `admin/articles-lib.php` — model, lock/atomic write, validasi dan izin.
- `02271e6`: `articles.css`, `articles.js`, `public-lib/articles-public.php`, `public-lib/profile.php`, `admin/articles-lib.php` — template, Markdown aman, UI ID/EN.
- `f17ddc4`: `admin/article-preview.php`, `admin/article-view.php`, `admin/articles.css`, `admin/articles.js`, `admin/index.php` — pengelolaan dan pratinjau.
- `3540452`: `.htaccess`, `index.php` — routing dan sitemap.
- `3037b6e`: `.gitignore`, `admin/articles-upgrade.php`, seed — CLI aditif. Seed akhirnya dipindahkan ke `admin/article-seeds.php` dengan penjagaan akses; tidak ada JSON draft yang dapat diunduh publik.
- `6bcb291`: `public-lib/profile.php`, `profile.css`, `index.php`, `tests/articles.php` — akses footer/beranda, relevansi produk, revalidasi cache dan pengujian conditional. Untuk membatalkan hanya akses tambahan, revert commit ini; pengelolaan artikel tetap tersedia.
- `8c7db9c`: penguatan unggahan/pratinjau, seed privat, metadata/i18n, `tests/articles.php`, adaptasi fixture regresi, dan `tools/preview-articles.php`. `admin/admin.js` hanya mengecualikan editor artikel dari konverter unggahan umum dan penguncian tombol pratinjau.

Dokumen ini dan tautan indeks dokumentasi disimpan dalam commit dokumentasi terpisah. Lihat `git show --stat <commit>` untuk daftar berkas lengkap setiap commit.

## Validasi akses publik tahap 6

Pengujian fixture memastikan posisi section, maksimal tiga kartu, footer tanpa tautan header baru, relevansi URL produk, serta penghapusan tambahan saat flag mati/semua draft. Regresi artikel, kategori HTTP, dan SEO publik lulus. Browser diperiksa pada 360/390/768/1440 piksel, UI Inggris dan tanpa JavaScript: tautan dapat diakses, tidak overflow, dan halaman lama tidak memuat articles.css/articles.js tambahan. Data artikel rusak menyembunyikan tambahan tanpa membuat katalog/beranda gagal. Tidak ada perubahan akun, artikel hosting, atau foto lama.


## Sampul responsif pada seluruh kartu

Sampul tampil pada detail, daftar Artikel, beranda, Baca juga, dan artikel lainnya. Detail mempertahankan proporsi sumber (`object-fit: contain`); thumbnail kartu mengikuti bingkai 4:3 (`object-fit: cover`). Area gambar tetap menjadi bagian dari tautan kartu. Tanpa sampul, artikel tetap dapat diterbitkan dan ditampilkan sebagai kartu teks. Tidak ada gambar pengganti otomatis atau foto tambahan di tengah tulisan.

Gambar detail memakai pemrosesan unggahan yang sama seperti sebelumnya (WebP kualitas 86, dimensi tidak dikecilkan). Dua thumbnail dibuat dari gambar yang sudah didekode, dengan kualitas 86 dan proporsi sumber utuh; pemotongan hanya pada tampilan CSS kartu. Varian diturunkan dari lebar sumber, tidak di-upscale, dan memakai `srcset`/`sizes` serta lazy loading. Detail tidak memakai thumbnail. File foto dan konten lain tidak dikompres ulang.

Setelah kode baru di-pull, unggahan baru langsung mendapat varian. Untuk sampul lama, dari root `public_html`, saat admin sementara tidak mengedit:

```sh
php admin/article-images.php --check
php admin/article-images.php --apply
php admin/article-images.php --check
```

CLI hanya membaca sampul yang direferensikan data artikel, termasuk draft/soft-delete agar dapat dipulihkan. `--check` melaporkan varian yang belum ada. `--apply` membuat varian tersebut secara atomik, di bawah lock artikel, dan tidak menimpa thumbnail yang sudah ada. Gambar asli, JSON artikel, status publikasi, flag, dan akun tidak berubah. Tidak perlu migrasi database, mengulang seed, atau mengunggah ulang sampul. Backup deployment normal tetap disarankan sebelum pull; backfill sendiri bersifat aditif dan tidak memerlukan pemulihan data lama.

Jika sampul hilang/tidak valid atau thumbnail yang sudah ada rusak, CLI melaporkan `errors` dan exit nonzero untuk ditinjau melalui admin; tidak mencoba mengganti berkas tersebut. Symlink dan path di luar direktori sampul ditolak. Thumbnail belum tersedia tidak membuat artikel hilang: kartu memakai foto asli yang valid sampai backfill selesai. Foto kecil tetap memakai aslinya. Foto yang dilepas tidak otomatis dihapus dari disk, mengikuti perilaku pemulihan yang sudah ada.

Pengujian: `php tests/article-images.php` mencakup backfill read-only/idempoten, proporsi, ukuran sumber, byte asli tetap, foto kecil, cleanup, symlink, fallback lama, serta renderer seluruh kartu. `php tests/articles.php` dan `php tests/seo-public.php` lulus. Browser fixture pada 360/390/768/1440 px memverifikasi rasio 4:3, thumbnail terpilih, foto detail asli, klik penuh kartu, tanpa overflow, dan dukungan tanpa JavaScript. Contoh foto uji: asli 127.968 byte, thumbnail 480 px 52.428 byte, thumbnail 800 px 106.626 byte; penghematan foto lain bergantung sumber dan ukuran layar.

Commit gambar: `d83d0f1` (pemrosesan/CLI) dan `80892ea` (tampilan/admin). Jika perlu membatalkan khusus fitur gambar, lakukan di checkout pengembangan bersih dan uji hasilnya:

```sh
git revert --no-commit 80892ea d83d0f1
git commit -m "revert: restore previous optional article cover behavior"
```

Gambar asli dan thumbnail boleh dipertahankan; tidak perlu memulihkan JSON atau metadata keamanan. Push/deployment tetap dilakukan setelah hasil ditinjau.

## Kategori Artikel melalui admin

Buka **Artikel → Kategori Artikel** (khusus Pemilik), atau `/admin/?p=artikel-kategori`. Staf tetap dapat memilih kategori dalam editor dan filter artikel, tetapi akses langsung serta endpoint perubahan kategori ditolak server untuk Staf. Autentikasi/CSRF yang ada dipakai kembali, tanpa verifikasi ulang password untuk pengeditan konten.

- Pemilik dapat menambah, mengubah nama Indonesia/Inggris, mengurutkan dengan Naik/Turun, dan menghapus kategori kosong dengan konfirmasi.
- Nama Indonesia wajib, teks biasa maksimal 60 karakter. Nama Indonesia duplikat ditolak tanpa membedakan kapital dan spasi. Inggris opsional; kosong memakai nama Indonesia. Perubahan nama tidak mengganti ID kategori atau URL filter.
- Kategori baru mendapat ID acak permanen. ID awal `panduan`, `ide-acara`, dan `tips-kue` tetap dipertahankan. Daftar dibatasi 100 kategori dan minimal satu kategori harus tersedia.
- Jumlah seluruh artikel mencakup draft, terbit, tersembunyi, dan soft-delete. Penghapusan ditolak selama masih ada artikel yang memakai kategori. Tautan **Lihat artikelnya** membuka filter termasuk artikel terhapus. Untuk memindahkan artikel terhapus, Pemilik perlu memulihkannya sebagai draft, mengubah kategori, lalu menghapusnya kembali bila diperlukan.
- Kategori kosong tetap dapat dipilih admin tetapi tidak muncul pada chip publik. Filter publik mengikuti urutan admin dan hanya memuat kategori dengan artikel published yang tidak dihapus. Artikel baru akan muncul sesuai status terbit serta flag, seperti sebelumnya.
- Label ID/EN pada detail, kartu, beranda, Baca juga, serta `articleSection` pada schema berasal dari kategori yang sama. Metadata waktu berkas dan ETag yang ada memastikan perubahan admin direvalidasi.

Data `categories` berupa daftar berurutan dengan ID, nama Indonesia, nama Inggris, dan waktu perubahan, dalam `ruethies-data/articles.json`. Tidak ada JSON publik baru atau database baru. Artikel tetap menyimpan ID kategori pada field `category`. Seluruh perubahan kategori dan artikel memakai exclusive lock dan revisi yang sama; penulis lama ditolak agar tidak membuat referensi putus. Keberadaan kategori dan penggunaannya diperiksa ulang di dalam lock. Backup privat dibuat sebelum penulisan atomik, aksi tercatat pada audit privat dan log aktivitas. Kegagalan backup/validasi menolak perubahan.

### Upgrade hosting

Sesudah persetujuan push/deployment, hentikan sementara pengeditan admin dan buat backup sesuai prosedur deployment di atas. Pull kode tanpa menimpa konten hosting, lalu dari `public_html` jalankan:

```sh
php admin/article-categories-upgrade.php --check
php admin/article-categories-upgrade.php --apply
php admin/article-categories-upgrade.php --check
```

CLI hanya menambahkan metadata kategori pada data artikel lama, membuat backup privat dan revisi baru. Ia tidak mengubah isi/slug/sampul/terjemahan/status/tanggal artikel, akun, flag, kategori menu, atau data pembayaran. Idempoten; data baru yang sudah memiliki kategori tidak ditimpa. Tidak perlu phpMyAdmin, mengulang seed, atau mengaktifkan flag kembali. Pembacaan data lama tetap kompatibel dan menggunakan tiga kategori awal di memori sampai upgrade atau penyimpanan pertama. Referensi kategori tak dikenal maupun metadata rusak ditolak, bukan dipindahkan atau ditebak.

Uji setelah deployment: login Pemilik, buka Kategori Artikel dan editor; pastikan jumlah serta label lama tetap benar. Perubahan konten baru dilakukan setelah pemeriksaan. Login Staf harus tetap bisa menulis artikel dan tidak bisa mengelola kategori. Semua pengujian publikasi/penghapusan bersamaan dilakukan pada fixture lokal, bukan akun produksi.

### Pengujian dan pemulihan

`php tests/articles.php` kini mencakup `tests/article-category-cases.php`: migrasi read-only/idempoten, Pemilik/Staf dan CSRF, nama duplikat/tidak valid, referensi kategori baru, label ID/EN/schema, filter hanya kategori terbit, penghapusan dengan draft/soft-delete, formulir kedaluwarsa, race hapus kategori vs simpan artikel, kegagalan backup, dan metadata rusak. Regresi `tests/article-images.php`, `tests/seo-public.php`, `tests/categories-http.php`, serta `tests/admin-ui.php` lulus. Browser fixture diperiksa pada 320/390/768/1440 piksel, nama 60 karakter, bahasa ID/EN, dan filter; tanpa overflow atau error console.

Commit model/CLI: `39ba441`. Commit admin/publik dan pengujian: `3e4753f`. Baseline sebelum fitur ini: `cb6196c`.

**Jangan langsung rollback ke kode kategori tetap setelah kategori baru sudah dipakai artikel.** Kode lama tidak mengenali ID baru. Jika ingin kembali sebelum ada kategori baru dipakai, revert dua commit fitur di checkout pengembangan bersih, uji, lalu deploy dengan backup. Jika kategori baru sudah dipakai, pertahankan kode yang dapat membaca kategori dinamis; lakukan perbaikan kompatibel, atau pindahkan semua artikel (termasuk soft-delete) ke ID awal melalui admin sebelum rollback. Jangan memulihkan direktori privat atau akun lama untuk membatalkan kategori. Backup JSON artikel yang cocok dipulihkan hanya sebagai pemulihan data terencana, saat admin tidak mengedit, dengan lock yang sama dan memperhitungkan suntingan artikel setelah backup.
