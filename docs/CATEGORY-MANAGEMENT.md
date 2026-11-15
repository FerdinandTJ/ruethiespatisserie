# Kategori menu — main dan staging

Kategori kini dikelola pemilik melalui **Admin → Kategori**. Staf tetap dapat memilih kategori dalam editor menu. Nama ID wajib, EN opsional (fallback ID), maksimal 60 karakter. Nama ID duplikat ditolak. Urutan memakai tombol Naik/Turun. Penghapusan membutuhkan konfirmasi dan hanya boleh setelah semua produk dipindahkan, termasuk produk tersembunyi. Kategori kosong tidak ditampilkan di publik.

## Data dan kompatibilitas

`categories.json` adalah data runtime **milik hosting**, diabaikan Git seperti `.catalog.lock`; keduanya dibuat saat upgrade. Jangan upload salinan kategori laptop. Main dan staging menyimpan file masing-masing di document root yang berbeda. Kategori bukan data sensitif; tidak memerlukan database baru.

Format berupa daftar terurut: setiap baris memiliki `key` permanen, `id` (nama Indonesia), `en` (opsional), dan `updated_at` UTC. Kategori baru mendapat key acak `category-…`; pemilik tidak mengedit key. Key lama cakery/savoury/packages dan coffee staging dipertahankan. `menu.json.cat` merujuk key tersebut; perubahan nama tidak mengganti UID/slug/foto/tag produk.

Sebelum upgrade pertama, helper tetap memahami kategori bawaan agar deployment kode tidak langsung memutus katalog lama. Pengelolaan kategori baru dinonaktifkan sampai upgrade selesai. Data kategori yang rusak atau cat yang tidak dikenal tetap ditolak, bukan di-reset atau dipindahkan diam-diam. Gunakan admin untuk perubahan; pengeditan manual file tidak mendapat perlindungan lock/revisi.

Semua mutasi kategori/menu memakai lock eksklusif `.catalog.lock`; pembacaan pasangan kategori/menu memakai shared lock yang sama. Lock privat konten tetap diambil lebih dahulu. Form kategori dan menu menolak revisi kedaluwarsa ketika kategori telah diinisialisasi. Kategori tidak dapat dihapus bersamaan dengan produk masuk tanpa salah satu request ditolak.

Snapshot kategori/menu sebelum mutasi kategori disimpan di data privat `backups/catalog-pair-*`. Penulisan memakai temporary file dan rename atomik; backup/validasi/lock gagal berarti perubahan ditolak. Nama ID/EN tidak dipercaya sebagai HTML. Mutasi kategori memerlukan pemilik, CSRF dan dicatat dalam log; tidak menambah reauth password untuk konten.

## Upgrade

Hentikan sementara pengeditan admin. Backup kode, JSON hosting termasuk `categories.json` bila sudah ada, serta data privat. Jangan menyalin lock saat backup/pemulihan. Setelah pull, jalankan di document root yang sedang di-upgrade:

```sh
php admin/categories-upgrade.php --check
php admin/categories-upgrade.php --apply
php admin/categories-upgrade.php --check
```

Check pertama tidak menulis file, bahkan lock. Apply awal mem-backup katalog, membuat kategori bawaan, mempertahankan coffee khusus layout staging, dan tidak mengubah menu/akun/pesanan/config. Apply berulang tidak membuat ulang kategori atau backup. Kategori tidak dikenal dilaporkan dan proses dihentikan; tinjau sebelum apply, jangan menghapus produk untuk melewati validasi.

Staging: gunakan `tools/update-staging.sh` dari SSH seperti biasa. Updater mempertahankan file kategori hosting jika ada dan mengecualikan `.catalog.lock` dari arsip. Main: `categories.json` tidak dilacak Git sehingga tidak ditimpa pull; tetap sertakan dalam backup deployment. Jangan menjalankan installer PO, seed demo atau impor SQL.

Untuk rilis pertama, uji di staging dahulu lalu bawa kode bersama ke main tanpa berkas PO. Tema masing-masing tidak berubah. Snapshot pesanan, harga PO, kuota, Midtrans dan Fonnte tidak dimigrasi. Pemilih produk PO memakai nama kategori dinamis, dengan hubungan produk tetap melalui UID.

## Verifikasi dan pemulihan

- Tambah/edit ID–EN/urut/hapus kosong oleh pemilik; staf dan CSRF salah ditolak.
- Pindah kategori produk tidak mengubah UID/slug/foto. Produk tersembunyi menghalangi hapus.
- Form lama dan penulisan bersamaan tidak menimpa perubahan terbaru; JSON/backup gagal tidak merusak data sebelumnya.
- Kategori tampil di beranda/filter/produk/schema/ringkasan hanya jika dipakai produk terlihat. ETag dan waktu perubahan mengikuti data kategori; canonical/filter tetap seperti sebelumnya.
- Cek kategori panjang/banyak pada 320/390/1280/1440 piksel dan alur PO aktif pada fixture lokal. Tidak ada pesan WA nyata dalam tes.
- Tes inti: `php tests/categories.php`, `php tests/categories-http.php`; lanjutkan regresi CMS, SEO, keamanan dan PO staging.

Rollback harus memakai kode kompatibel beserta pasangan backup kategori/menu yang cocok, saat admin dihentikan. Pulihkan kedua JSON, bukan `.catalog.lock`; jangan memulihkan akun atau metadata keamanan lama. Rollback ke kode sebelum fitur ini memerlukan menu yang hanya memakai kategori bawaan, sehingga tidak boleh sekadar git checkout setelah kategori baru digunakan.
