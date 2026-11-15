# SEO lokal Ruethies: deployment dan pemulihan

Rilis ini mempertahankan desain dan memakai PHP native + JSON. Beranda dirender dari template `index.html`, katalog hosting dan FAQ hosting. URL snack box adalah `/snack-box-cikarang/`; sembilan produk lain memakai `/menu/{slug}/`. Modal tetap tersedia sebagai peningkatan JavaScript. Tidak ada SQL, framework, API baru, perubahan akun, atau penggabungan staging.

## Sebelum deployment

- Jadwalkan jendela singkat tanpa aktivitas admin. Sebelum CLI upgrade selesai, renderer baru dapat mengembalikan 503; jangan menganggap ini sebagai kehilangan data.
- Pastikan perubahan produksi pada kode selain konten CMS sudah ditinjau. Jangan reset hard atau git clean.
- Catat kueri, halaman dan laporan pengindeksan Search Console. Screenshot awal menunjukkan 20 klik / 78 impressions / CTR 25,6% / posisi rata-rata 5,8. Belum ada laporan pengindeksan atau data lengkap per halaman; penyebabnya tidak diasumsikan.
- Tetap gunakan data JSON serta foto dari hosting. File `menu.json` dalam rilis ini tidak diganti dengan katalog laptop.

## Backup dari SSH

Dari direktori `public_html` produksi, simpan backup di luar web root. Simpan nilai `SEO_BACKUP_DIR` karena dibutuhkan untuk pemulihan.

```sh
umask 077
SEO_BACKUP_DIR="../deploy-backups/seo-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$SEO_BACKUP_DIR"
git status --short
git rev-parse HEAD > "$SEO_BACKUP_DIR/release-before.txt"
tar --exclude=./.git --exclude=./staging --exclude=./admin/data -czf "$SEO_BACKUP_DIR/public_html.tar.gz" .
cp menu.json faq.json atmosphere.json "$SEO_BACKUP_DIR/"
if [ -d ../ruethies-data ]; then tar -czf "$SEO_BACKUP_DIR/private-data.tar.gz" -C .. ruethies-data; fi
if [ -d admin/data ]; then tar -czf "$SEO_BACKUP_DIR/legacy-private-data.tar.gz" -C admin data; fi
```

Periksa bahwa semua backup berhasil sebelum melanjutkan. Arsip data privat mengandung data sensitif; jangan pindahkan ke folder publik.

## Pull dan upgrade

Jika CMS telah mengubah file tracked, simpan dahulu perubahan tersebut:

```sh
umask 022
git stash push -m "backup CMS sebelum SEO lokal" -- index.html menu.json faq.json atmosphere.json llms.txt
git pull --ff-only origin main
```

Lanjutkan hanya jika pull berhasil. Pulihkan **JSON hosting**, bukan template HTML lama:

```sh
cp "$SEO_BACKUP_DIR/menu.json" menu.json
cp "$SEO_BACKUP_DIR/faq.json" faq.json
cp "$SEO_BACKUP_DIR/atmosphere.json" atmosphere.json
chmod 644 .htaccess index.php index.html styles.css app.js product.js menu.json faq.json atmosphere.json llms.txt sitemap.xml public-lib/seo.php
chmod 755 public-lib
touch .htaccess
php admin/seo-upgrade.php --check
php admin/seo-upgrade.php --apply
php admin/seo-upgrade.php --check
```

`umask 077` hanya untuk backup privat. Sebelum operasi Git gunakan `umask 022`; file statis publik memerlukan izin 644 dan direktori publik 755. Git tidak mendeteksi perbedaan 600 vs 644 melalui `git status`. Jangan menggunakan chmod rekursif pada data privat, akun, token, atau direktori backup. Jika beranda PHP 200 tetapi CSS/JS 403 setelah pull, periksa `ls -l styles.css app.js product.js` dan jalankan perbaikan izin publik di atas.

`--check` tidak membuat file atau mengubah data. `--apply` menambahkan ID, slug dan timestamp yang belum ada, dengan backup `menu-before-seo-*` di direktori data privat. Hash password, secret 2FA dan versi autentikasi tidak disentuh. Pengulangan `--apply` tidak mengganti metadata yang sudah ada.

Jangan menjalankan `git stash pop` setelahnya: itu dapat mengembalikan template HTML lama. FAQ asli tetap dibaca dari `faq.json` dan ringkasan `llms.txt` dilayani secara dinamis. Tidak perlu menjalankan ulang upgrade keamanan untuk rilis SEO ini.

Jika beranda PHP sudah normal tetapi semua URL produk 404, `/index.html` masih 200, atau sitemap tetap berisi satu URL lama, aturan rewrite belum aktif. Jalankan `chmod 644 .htaccess` dan `touch .htaccess` untuk memperbaiki izin serta memicu pembacaan ulang konfigurasi. Jika tetap gagal, periksa `ls -l .htaccess` dan `sed -n '1,40p' .htaccess` melalui SSH; jangan menimpa konfigurasi hosting tanpa meninjau isinya.

Jika CLI menolak JSON rusak, metadata duplikat atau slug tidak valid, jangan menggantinya dengan menu laptop; periksa backup hosting dan hentikan deployment sampai datanya valid.

Bersihkan cache HTML pada hPanel/LiteSpeed/CDN jika aktif, lalu muat ulang browser. Aset CSS/JS memakai versi baru. Untuk HTML lama yang masih tertahan cache, JavaScript menyediakan jalur kompatibilitas yang membaca JSON hosting asli tanpa katalog contoh.

## Smoke test setelah upgrade

```sh
curl -I https://ruethiespatisserie.com/
curl -I https://ruethiespatisserie.com/snack-box-cikarang/
curl -I https://ruethiespatisserie.com/menu/brownies-cokelat/
curl -I https://ruethiespatisserie.com/index.html
curl -fsS https://ruethiespatisserie.com/sitemap.xml
```

- Beranda, snack box dan produk yang tampil harus 200. Canonical harus memakai HTTPS tanpa www.
- `/index.html` harus 301 langsung ke `/`. Alias snackbox diarahkan ke satu URL snack box.
- HTML memakai `Cache-Control: public, max-age=0, must-revalidate`, ETag dan Last-Modified. Sitemap berisi URL produk yang tampil, tanpa parameter pencarian atau URL Inggris.
- Periksa HTML awal/view source: nama produk, tautan, FAQ dan JSON-LD sudah ada tanpa JavaScript. Matikan JavaScript untuk memastikan katalog tetap terbaca.
- Login owner; periksa OTP jika aktif. Lakukan uji tambah/edit/toggle/hapus hanya pada produk uji yang disepakati, jangan mengubah produk asli untuk smoke test.
- Periksa filter, pencarian, ID/EN, modal, X, WhatsApp dan galeri di desktop/mobile.
- `/staging/` serta `/%73taging/` dari domain produksi harus 403/404; staging tetap password-protected dan noindex. Jangan menggabungkan branch staging untuk deployment ini.

## Search Console dan pengukuran oleh pemilik

1. Inspeksi beranda, halaman snack box, dan satu halaman produk; periksa canonical yang dipilih dan HTML hasil render.
2. Kirim ulang `https://ruethiespatisserie.com/sitemap.xml`, lalu minta pengindeksan URL prioritas.
3. Jalankan Rich Results Test dengan URL produksi setelah deployment. Percobaan mode kode dari lingkungan pengembangan meminta login Google (`Log in and try again`); ini belum dinyatakan lulus oleh alat Google.
4. Cocokkan nama, alamat, telepon, jam buka dan kategori usaha dengan Google Business Profile. Akses dan pengubahan profil hanya oleh pemilik yang berwenang; nama usaha tidak perlu ditambahi kata kunci.
5. Bandingkan periode 28 hari yang setara dan evaluasi ulang setelah 8–12 minggu. Pisahkan pencarian merek dari nonmerek, terutama snack box/snackbox Cikarang dan produk katalog. Catat impressions, klik, CTR dan halaman masuk. Jangan mengartikan posisi rata-rata gabungan sebagai posisi kata kunci snack box.

## Pemulihan melalui SSH

Gunakan langkah ini hanya jika smoke test gagal dan tidak dapat segera diperbaiki. Pertahankan versi terbaru akun/2FA/sesi. Jangan mengekstrak arsip privat ke direktori aktif untuk rollback SEO.

1. Hentikan sementara aktivitas admin. Buat snapshot JSON terbaru sebelum rollback, termasuk perubahan CMS setelah rilis:

```sh
SEO_ROLLBACK_DIR="../deploy-backups/seo-rollback-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$SEO_ROLLBACK_DIR"
cp menu.json faq.json atmosphere.json "$SEO_ROLLBACK_DIR/"
git stash push -m "snapshot CMS sebelum rollback SEO" -- index.html menu.json faq.json atmosphere.json llms.txt
```

2. Kembali ke commit yang dicatat **sebelum deployment**; checkout harus berhasil sebelum melanjutkan. Variabel `SEO_BACKUP_DIR` menunjuk folder backup deployment awal:

```sh
SEO_PREVIOUS_RELEASE="$(cat "$SEO_BACKUP_DIR/release-before.txt")"
umask 022
git switch --detach "$SEO_PREVIOUS_RELEASE"
cp "$SEO_ROLLBACK_DIR/menu.json" menu.json
cp "$SEO_ROLLBACK_DIR/faq.json" faq.json
cp "$SEO_ROLLBACK_DIR/atmosphere.json" atmosphere.json
```

Perbaiki izin hanya file publik yang ada pada release lama:

```sh
chmod 644 .htaccess index.html app.js styles.css menu.json faq.json atmosphere.json llms.txt sitemap.xml
```

3. Kode lama mengabaikan metadata SEO tambahan. Regenerasi FAQ statis dari FAQ terbaru memakai fungsi yang sudah tersedia pada release sebelumnya:

```sh
php -r 'define("RUETHIES_ADMIN",true); require "admin/config.php"; require "admin/lib.php"; $faq=json_decode(file_get_contents(RUETHIES_PUBLIC."/faq.json"),true,512,JSON_THROW_ON_ERROR); [$ok,$err]=faq_apply($faq); if(!$ok){fwrite(STDERR,$err.PHP_EOL);exit(1);}'
```

4. Purge cache, periksa beranda/admin kembali. URL produk baru dapat 404 selama rollback; jangan membuat redirect massal ke beranda. Akun dan metadata keamanan tetap memakai data aktif terbaru. Foto unggahan tidak dihapus karena tidak ada `git clean` atau ekstraksi seluruh arsip.

Checkout rollback berada pada detached HEAD. Untuk deployment perbaikan berikutnya, backup/stash perubahan CMS lagi sebelum kembali ke `main`, pull, pulihkan JSON hosting terbaru, dan ulangi upgrade/smoke test.
