# Filosofi dan deskripsi menu — 13 November 2026

Sumber teks: file pemilik `FIL RUETHIES PATISSERIE.csv` dari Downloads. CSV asli tidak ikut dipublikasikan. Payload teks yang diperlukan website ada di `content/menu-copy-20261113.json`.

Tujuh produk diselaraskan: Bolu Jadoel (baris Bolu Jadul Mocca), Brownies Cokelat (Brownies), Pastel (Pastel Ayam), Kroket (Kroket Ragout), Risol (Risoles), Marble Cake, serta Lapis Surabaya → **Lapis Marble**. Nama lain dipertahankan. Deskripsi singkat diringkas dari isi yang diberikan; teks detail berisi judul cerita dan paragraf filosofi ID/EN dari CSV, dengan perapian tanda kurung/jeda baris. Tidak menambahkan harga atau ketentuan pesanan.

Dadar Gulung Cokelat, Lemper Ayam dan Swiss Roll belum ditambahkan sebagai produk karena tidak ada dalam katalog saat ini. Jongkong, Snackbox dan Platter tidak mempunyai materi baru pada CSV sehingga teksnya tetap. Foto, kategori, tanda signature, visibilitas dan urutan produk tidak diubah. Tidak ada optimasi/kompresi gambar dalam revisi ini.

Nama **Lapis Marble** menggunakan URL baru `/menu/lapis-marble/`. Atas permintaan eksplisit pemilik, URL lama `/menu/lapis-surabaya/` dihapus dan menghasilkan 404, tanpa redirect. Tautan lama yang pernah dibagikan perlu diperbarui. Nama berkas foto lama tetap dipertahankan agar gambar tidak terganggu. Filosofi memakai field detail yang sudah dapat diedit melalui admin.

## Hosting setelah kode dirilis

Jangan mengganti menu.json hosting dengan salinan laptop karena hosting mempunyai UID/slug, foto detail, status dan produk tambahan sendiri. Backup kode/konten di luar web root dan simpan perubahan CMS sebelum pull; kembalikan JSON hosting asli, bukan template HTML lama.

Lalu jalankan saat admin tidak sedang mengedit:

```sh
php admin/menu-copy-upgrade.php --check
php admin/menu-copy-upgrade.php --apply
php admin/menu-copy-upgrade.php --check
```

`--check` hanya membaca dan melaporkan perubahan/materi yang tidak cocok. `--apply` menggunakan lock konten yang sama dengan admin, membuat backup menu hosting di direktori privat, lalu menulis secara atomik. Hanya nama Lapis, deskripsi singkat/detail ID/EN serta slug khusus Lapis dan updated_at produk yang berubah. UID, slug produk lain, foto utama/detail, kategori, status, signature dan field tambahan tetap dipertahankan. Produk hilang tidak dihidupkan kembali. Nama ambigu atau JSON rusak ditolak sebelum penyimpanan. Produk yang sudah berganti nama di luar alias yang dikenal dilaporkan dalam unmatched untuk ditinjau, bukan ditebak.

Tidak memerlukan SQL/phpMyAdmin, pengunggahan foto ulang, atau upgrade akun/2FA. Penerapan ulang tidak membuat perubahan maupun backup baru bila teks sudah sama.

Periksa Beranda, Menu, halaman baru `/menu/lapis-marble/` (200), URL lama dengan/tanpa slash (404 tanpa redirect), pertukaran ID/EN, filosofi halaman detail, WhatsApp, schema serta sitemap. Bersihkan cache HTML hosting bila diperlukan. Untuk membatalkan hanya pembaruan teks, simpan dulu perubahan admin terbaru dan tinjau backup menu-before-copy; jangan memulihkan metadata akun/security lama.
