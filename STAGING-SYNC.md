# Penyelarasan main ke staging — 10 November 2026

Main 42eaa5b digabungkan ke staging acd5d55 melalui branch integrasi, dengan penyelesaian konflik yang mempertahankan PO. Tidak ada penggabungan staging ke main dan tidak ada perubahan token/API maupun database hosting.

Yang ikut: UI admin, katalog bawaan sepuluh produk, kontak WhatsApp/email, foto utama + dua foto detail, detail mobile, foto responsif on-demand, renderer PHP SEO, URL produk, sitemap dinamis, upgrade keamanan dan metadata SEO.

Yang dipertahankan: controller /pre-order/ dan /pre-order/status/, body POST serta query magic link, semua tabel/flow PO dan notifikasi Fonnte, navigasi PO pada CMS dan homepage, UID produk lama saat upgrade, proteksi file foto yang masih dirujuk PO, noindex dan password protection staging. Canonical dan sitemap tetap memakai domain produksi; staging tidak ditujukan untuk indeks pencarian.

## Sebelum pull di hosting

Kerjakan dari public_html/staging, bukan public_html. Hentikan sementara aktivitas admin selama backup/upgrade. Jangan menjalankan seed-demo atau mengganti database.

1. Catat git status --short dan git rev-parse HEAD. Jika ada perubahan CMS atau .htaccess, simpan dan tinjau dahulu; jangan reset --hard atau stash pop membabi buta. .htaccess hosting dapat berisi Basic Auth dari hPanel, yang harus tetap dipertahankan bersama aturan baru.
2. Backup folder staging serta ruethies-data-staging di luar public_html; gunakan umask 077 saat backup, lalu kembalikan umask 022 SEBELUM pull. Simpan snapshot database melalui fasilitas backup hosting bila diperlukan.
3. Jangan menimpa menu.json, faq.json, atmosphere.json, users.json atau po-config.php hosting dengan data laptop. Simpan perubahan konten hosting sebelum pull dan rekonsiliasi file terkait. Jika katalog lama masih memakai kategori drinks/menu lama, tentukan migrasi katalog dahulu; CLI SEO akan menolak kategori tidak didukung. Jangan menghapus data untuk memaksa lolos.
4. Setelah working tree siap: git pull --ff-only origin staging.
5. Jalankan dari staging:

```sh
php admin/security-upgrade.php --check
php admin/security-upgrade.php --apply
php admin/seo-upgrade.php --check
php admin/seo-upgrade.php --apply
php admin/seo-upgrade.php --check
php admin/po-cron.php --check
```

Jika salah satu langkah gagal, berhenti dan periksa output sebelum melanjutkan. Upgrade menambahkan metadata akun dan katalog; hash password, secret 2FA, UID katalog yang valid, rekening, pesanan serta token Fonnte dipertahankan. Login admin lama akan dicabut. Tidak perlu impor SQL atau menjalankan installer PO untuk sinkronisasi ini.

6. Bersihkan cache staging, lalu cek login pemilik dan OTP, akses admin PO, beranda/katalog/detail, noindex dan Basic Auth. Cek cron yang sudah ada masih menunjuk folder staging.
7. Verifikasi pembayaran, pembatalan dan retry notifikasi mengikuti verifikasi ulang 5 menit. Setelah verifikasi, kembali ke PO dan kirim ulang tindakan secara sadar; password/OTP/payload keputusan tidak disimpan otomatis.

Katalog repository yang baru tidak otomatis memetakan produk lama pada jadwal PO. Bila mengganti katalog hosting, lakukan pemetaan UID secara eksplisit dahulu. Pesanan lama tetap memakai snapshot, tetapi produk dengan UID yang tidak lagi ada tidak dapat menerima checkout baru.

## Validasi lokal

Suite keamanan, UI admin, SEO/public HTTP, routing Apache (termasuk Basic Auth dan path staging terenkode), foto, WhatsApp, dan PO UI dijalankan pada fixture lokal. Tiga suite PO database memakai instance MySQL terisolasi melalui socket, bukan database hosting. Tidak ada pesan WhatsApp nyata dikirim.

Integrasi tambahan mencakup homepage SEO + tautan PO, navigasi CMS ke PO, UID tetap setelah edit, foto yang dirujuk PO tidak terhapus, dan verifikasi ulang kedaluwarsa yang menolak pembayaran sampai pemilik memverifikasi ulang.
