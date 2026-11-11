# Validasi SEO lokal — 9 November 2026

## Kondisi awal dan batas akses

- Baseline Search Console dari pemilik: 20 klik, 78 impressions, CTR 25,6%, posisi rata-rata 5,8. Kueri yang terlihat: `ruethies patisserie` (4 klik/17 impressions), `patisserie` (0/2), `macarons near me` (0/1). Tidak disimpulkan bahwa kueri yang tidak terlihat sama sekali belum memiliki impressions.
- Dua GET read-only ke produksi mengonfirmasi title lama, canonical `/`, sitemap satu URL, respons 200 dan cache HTML 600 detik. Tidak ada konten, akun atau pesan WhatsApp produksi yang diubah.
- Search Console/Business Profile tidak diakses sebagai pemilik. Laporan pengindeksan, canonical pilihan Google dan pengiriman sitemap tetap perlu dilakukan pemilik setelah deployment.
- Rich Results Test resmi Google berhasil dibuka dalam browser terisolasi. Pengujian mode kode meminta login (`Something went wrong — Log in and try again`); tidak diklaim sebagai kelulusan Google. JSON-LD lokal seluruh halaman diperiksa agar valid dan aman terhadap penutup script.

## Pengujian otomatis

Semua data dan akun uji berada di fixture sementara, terpisah dari hosting dan data admin lokal.

```sh
php tests/seo-public.php
php tests/seo-routing.php
node tests/seo-client.cjs
php tests/security-auth.php
php tests/security-resources.php
php tests/security-upgrade.php
php tests/security-routing.php
php tests/admin-ui.php
php tests/menu-photo-delete.php
php tests/catalog.php
php tests/seo-check.php
php tests/whatsapp.php
node tests/admin-upload.cjs
node tests/menu-gallery.cjs
node tests/whatsapp.cjs
```

Tes routing menggunakan Apache HTTPS dan PHP CGI nyata, bukan hanya router PHP pengganti. Di Linux, sesuaikan `APACHE_BINARY`, `APACHE_MODULE_DIR`, dan `PHP_CGI_BINARY` jika diperlukan.

Cakupan utama: 10 produk pada HTML awal, canonical dan alias, sitemap, ID/slug stabil, tambah/edit/sembunyi/hapus, foto admin, deskripsi ID/EN, upgrade read-only/idempoten, backup pra-SEO tetap tersimpan, katalog kosong, JSON rusak, metadata duplikat, ETag berubah pada edit di detik yang sama, bodyless 304, HTML-safe JSON-LD, path traversal, isolasi staging, autentikasi/OTP dan upload. Tes klien memeriksa klik biasa vs Ctrl/Meta/Shift/Alt serta kompatibilitas HTML lama yang masih berada di cache.

## Pemeriksaan browser

Browser lokal memakai profil uji terisolasi dan header CSP hosting. Diperiksa pada 1440, 390 dan 320 piksel:

- Nama/foto/tautan detail menuju modal produk yang sesuai; tautan memiliki URL yang dapat dibuka langsung.
- Filter kategori, pencarian, ID/EN, X, galeri suasana serta tujuan WhatsApp tetap berfungsi.
- Halaman snack box memiliki konten dan foto aktual, terjemahan antarmuka, breadcrumb, produk terkait dan tautan WhatsApp.
- Tidak ada overflow horizontal, pelanggaran CSP atau exception JavaScript pada skenario tersebut.
- Tanpa JavaScript, sepuluh produk dan tautan canonical tersedia; halaman produk tetap memiliki konten lengkap.
- Deskripsi admin panjang tetap dapat ditutup pada mobile; konten dialog dapat digulir.
- HTML main lama dengan aset JavaScript baru tetap membaca katalog hosting dan membuka modal selama transisi cache.

## Pengukuran respons HTML lokal

PHP, mesin dan CSP sama; lima permintaan pemanasan diikuti 30 permintaan per versi. Ini waktu respons HTML lengkap pada loopback tanpa kompresi, **bukan Core Web Vitals atau kecepatan hosting**.

| Ukuran | Sebelum (main `9b45d41`) | Sesudah SSR |
|---|---:|---:|
| Median respons HTML | 0,23 ms | 0,75 ms |
| P95 respons HTML | 0,34 ms | 2,21 ms |
| Ukuran HTML tanpa kompresi | 24.506 byte | 46.406 byte |

HTML bertambah karena katalog, schema dan snapshot interaksi sudah berada dalam respons pertama. Ada tambahan pekerjaan PHP; tidak diklaim lebih cepat dari HTML statis. Pada jalur normal, JavaScript tidak lagi memerlukan fetch `menu.json` untuk menampilkan katalog. Gambar tetap WebP/JPEG, detail lazy-loaded, peta menunggu interaksi, dan tidak ditambah skrip pihak ketiga.

Core Web Vitals nyata perlu diperiksa di Search Console/PageSpeed setelah deployment dan setelah data lapangan tersedia. Hasil lokal tidak digunakan untuk menjanjikan skor atau posisi Google.

## Revisi Snackbox — 11 November 2026

- Breadcrumb Snackbox: Beranda → Snack Box di Cikarang. Structured data mengikuti dua tingkat ini; tautan katalog /#menu tersedia terpisah. Produk lain mempertahankan hierarki Beranda → Menu → Produk.
- Jika admin menambahkan foto detail Snackbox, maksimal tiga foto menempati satu carousel. Berganti setiap 6 detik saat terlihat; kontrol jeda/putar, sebelumnya/berikutnya dan keyboard tersedia. Fokus, hover, tab latar, posisi di luar layar, dan reduced motion ditangani. Tanpa JavaScript foto pertama, konten, serta tautan foto tambahan tetap tersedia.
- Foto tersembunyi tidak mendapat src/srcset sebelum dipilih; HTML tetap memuat caption dan schema gambar yang sesuai. Tidak ada preload seluruh galeri atau script pihak ketiga baru.
- Entitas halaman, bakery, breadcrumb dan item menu memiliki relasi URL/ID yang konsisten. Lokasi Lippo Cikarang/Cikarang Selatan dan cara menghubungi WhatsApp tersedia di HTML awal, tanpa harga, minimum pesanan, jangkauan antar, atau ulasan rekaan.
- robots.txt menggunakan satu kelompok aturan untuk semua crawler. Halaman publik tetap dapat dirayapi; /admin/, /staging/ dan jalur transaksi privat dikecualikan. Aturan ini bukan kontrol akses server.
- Lulus tes carousel state/timer, HTTP/CMS/SEO, JSON-LD, FAQ, navigasi katalog dan galeri. Browser lokal diuji pada 320, 390 dan 1440 px: satu bingkai, tanpa overflow, unduhan foto sesuai slide, ID/EN, autoplay nyata, reduced motion, dan tanpa JavaScript. Safari perangkat nyata serta pengindeksan/ranking setelah rilis belum diverifikasi.
- Rujukan: https://developers.google.com/search/docs/appearance/ai-features dan https://www.w3.org/WAI/ARIA/apg/patterns/carousel/. GEO tidak memerlukan schema khusus dan tidak menjamin website dikutip layanan AI.
- Deployment memakai backup dan pull main; tidak ada migrasi database atau perubahan JSON hosting. Pengujian browser memakai dua foto detail fixture, bukan menambahkan foto baru ke menu.json hosting. Jika foto detail belum diisi admin, halaman tetap menampilkan satu foto tanpa kontrol carousel.
