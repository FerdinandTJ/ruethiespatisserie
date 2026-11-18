# Sinkronisasi Artikel dan tema — 18 November 2026

Main dan staging memakai fitur Artikel serta tema biru #8ACEF1, krem #FFF3E1 dan teks biru gelap #143E55 yang sama. Login, CMS, dan admin PO mengikuti palet ini. Warna status kesalahan/peringatan tetap dibedakan.

## Data dan keamanan

Artikel, kategori artikel, flag, dan backup staging tersimpan di `ruethies-data-staging`, di luar `public_html`; produksi memakai `ruethies-data`. Data tidak disalin antarlingkungan. Sampul masing-masing checkout tetap di `images/articles/`. UID katalog, akun, sesi, konfigurasi pembayaran dan notifikasi tidak dimigrasi. PO/Midtrans/Fonnte tetap khusus staging.

Updater staging mencadangkan kode, konten, sampul dan data privat tanpa file lock, lalu mempertahankan CMS dan proteksi hPanel yang tersedia. Jangan `stash pop` setelah update. Hentikan sementara pengeditan admin saat deployment.

## Setelah pull staging

Jalankan dari `public_html/staging`:

```sh
php admin/articles-upgrade.php --check
php admin/articles-upgrade.php --apply
php admin/article-categories-upgrade.php --check
php admin/article-categories-upgrade.php --apply
php admin/article-images.php --check
php admin/article-images.php --apply
php admin/po-cron.php --check
```

Upgrade idempoten: artikel hosting yang sudah ada dipertahankan, seed baru berupa draft, dan flag awal nonaktif. Tidak ada publikasi atau pengaktifan otomatis. Untuk membuka artikel staging setelah meninjau konten, gunakan `php admin/articles-upgrade.php --enable`. Ini tidak mengaktifkan produksi. Tidak perlu impor SQL atau mengulang upgrade akun.

Main yang sudah memiliki fitur artikel tidak memerlukan seed ulang untuk perubahan warna. Data dan flag produksi yang ada tetap dipertahankan.

## Pemeriksaan dan pemulihan

Periksa login pemilik/staf, daftar/editor/kategori/pratinjau artikel, unggahan sampul, ID/EN dan tampilan mobile/desktop. Periksa halaman PO, stok, pembayaran sandbox dan cron pada staging. Tes penyedia pembayaran/notifikasi memakai fixture dan transport mock; bukan transaksi produksi.

Catat commit sebelum deployment dan simpan arsip updater. Bila perlu rollback, pulihkan kode kompatibel dengan data saat ini. Jika data artikel perlu dipulihkan, gunakan backup artikel yang sesuai; jangan memulihkan akun/auth_version lama, konfigurasi pembayaran, atau database PO. Lihat [ARTICLES.md](ARTICLES.md) untuk pengelolaan dan rollback artikel.
