# Pembaruan katalog — 9 November 2026

Seluruh katalog lama diganti dengan sepuluh produk dari folder pilihan pemilik: Bolu Jadoel, Brownies Cokelat, Lapis Surabaya, Marble Cake, Jongkong, Pastel, Kroket, Risol, Platter, dan Snackbox. Minuman lama tidak ditampilkan lagi.

Kategori publik dan admin adalah Kue & Jajanan (`cakery`), Camilan Gurih (`savoury`), dan Paket & Sajian (`packages`). Brownies Cokelat, Pastel, dan Snackbox ditampilkan di bagian pilihan homepage; ini bukan klaim penjualan terlaris.

Foto memakai `01_Utama` dari masing-masing subfolder Hasil Sortir. Salinan website dioptimalkan menjadi JPEG/WebP 960×720 untuk menu dan 880×1100 untuk hero Marble Cake. Sumber pilihan di luar repo tidak diubah. Foto lama tetap disimpan agar referensi lama tidak rusak; foto tersebut tidak lagi muncul sebagai menu aktif.

Deskripsi tidak menetapkan isian daging, ukuran, harga, komposisi snackbox, atau minimum pemesanan yang belum diberikan pemilik. Pelanggan diarahkan menanyakan pilihan dan harga lewat WhatsApp. FAQ kisaran harga lama diperbarui supaya tidak menjanjikan harga yang belum dikonfirmasi untuk katalog baru.

## Penerapan melalui SSH

1. Dari document root produksi `public_html`, pastikan branch `main` dan buat backup file website serta direktori data privat. Simpan backup di luar web root.
2. Periksa `git status --short` dan `git diff --stat`. Menu/FAQ/homepage dapat memiliki perubahan lokal dari CMS hosting.
3. Jalankan `git pull --ff-only origin main`. Jika Git menolak karena file lokal telah berubah, jangan menggunakan `reset --hard` atau membuang seluruh perubahan. Simpan output dan tinjau hanya file yang konflik. Katalog baru memang menggantikan menu lama, tetapi konfigurasi privat, akun, galeri, dan perubahan hosting lain harus tetap dipertahankan. Jangan menerapkan kembali snapshot menu lama di atas katalog yang baru.
4. Tidak ada migrasi SQL atau perubahan konfigurasi Fonnte untuk pembaruan katalog ini. Tidak perlu menjalankan `security-upgrade.php --apply` hanya untuk katalog apabila upgrade keamanan sudah selesai sebelumnya. Jika upgrade keamanan sebelumnya belum dilakukan, selesaikan sesuai SECURITY-UPGRADE.md sebelum membuka admin.
5. Muat ulang halaman dan verifikasi kategori 5 kue/jajanan, 3 camilan gurih, 2 paket; foto; pencarian; ID/EN; tombol WhatsApp; serta editor kategori Paket & Sajian di admin. Aset JavaScript memakai versi URL baru.

JSON katalog di repo ini memang merupakan katalog pengganti yang disetujui pemilik. File privat akun, konfigurasi hosting, dan data staging tidak ikut diubah. Perubahan ini tidak menggabungkan fitur PO ke produksi atau membawa katalog baru ke staging; integrasi staging dilakukan terpisah agar hubungan menu/produk PO dapat ditinjau.

## Pemeriksaan lokal

- `php tests/catalog.php`: sepuluh produk, kategori CMS, dimensi/ukuran foto, fallback, schema menu.
- `php tests/seo-check.php`: structured data, FAQ aman, header markup dan aset.
- `php tests/admin-ui.php`: filter/editor kategori paket, unggahan, autentikasi dan sesi.
- `php tests/menu-photo-delete.php`: referensi foto dan pembersihan aman.
- `node --check app.js`: sintaks JavaScript.
- Pemeriksaan Chrome lokal desktop/mobile: kategori 5/3/2, pencarian Snackbox, pergantian ID/EN, tiga kartu pilihan dan tidak ada overflow horizontal/runtime error.
