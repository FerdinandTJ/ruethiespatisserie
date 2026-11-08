# PO transfer manual + Fonnte — staging

Perubahan 8 November 2026, hanya branch `staging`. Midtrans/Paper.id belum diintegrasikan. PHP native, database MySQL/MariaDB PO yang sudah ada, tanpa framework atau database baru.

## Perilaku yang tersedia

1. Pemilik mengatur rekening dan jadwal `manual_v1`, lalu produk/harga/kuota/batas beli.
2. Pelanggan memeriksa ringkasan dan checkout. Kuota ditahan 15 menit; invoice menggunakan snapshot item, harga, jadwal, dan rekening pada waktu checkout.
3. Website menampilkan invoice dan magic link. Entri notifikasi dibuat dalam transaksi yang sama, tanpa memanggil jaringan saat checkout. Token akses untuk notifikasi disimpan terenkripsi; hash akses pelanggan tetap dipertahankan.
4. Cron mengirim ringkasan, rekening, batas transfer, dan magic link melalui Fonnte ke nomor uji yang diizinkan. Halaman tetap dapat digunakan bila WhatsApp gagal.
5. Pelanggan mengirim **laporan transfer berupa nama, nominal, dan referensi/keterangan**, melalui magic link + CSRF. Status menjadi `under_review`, belum lunas. Reservasi tidak kedaluwarsa otomatis selama pemeriksaan.
6. Pemilik memeriksa mutasi rekening. Konfirmasi lunas wajib nominal sesuai total dan referensi pemeriksaan. Reservasi yang sudah ditahan dikomit sekali, tanpa pengurangan kuota kedua. Notifikasi pelanggan/pemilik masuk antrean.
7. Pemilik bisa meminta perbaikan laporan dengan alasan dan tenggat baru 15 menit, maksimal dua perbaikan. Setelah itu tetap ditahan untuk keputusan pemilik, tidak otomatis dilepas. Pemilik dapat menolak final setelah menyatakan mutasi sudah diperiksa dan tidak ada dana masuk; aksi ini mencatat alasan dan melepas kuota sekali. Staf/pemilik dapat melanjutkan pesanan lunas menjadi siap diambil dan selesai; pelanggan mendapat notifikasi.

Batas implementasi: belum ada unggah foto bukti, PDF yang dikirim sebagai lampiran, countdown/polling otomatis, maupun pencatatan refund/pembayaran terlambat. Invoice bisa dicetak/disimpan PDF melalui browser. Pembayaran terlambat diarahkan menghubungi toko; tidak menghidupkan kuota otomatis. Pembatalan biasa hanya untuk pesanan belum dibayar dan belum dilaporkan. Pesanan lunas tidak dapat dibatalkan melalui aksi tersebut.

Rencana lama `PO-strategy.md` tanpa pihak ketiga diubah **khusus untuk notifikasi Fonnte**. Tahap unggah bukti gambar tetap terpisah; laporan teks bukan pengganti pemeriksaan mutasi bank. Semua transaksi staging memakai contoh, tanpa transfer nyata.

## Sebelum pull

- Backup checkout staging, database staging, serta direktori privat `ruethies-data-staging` di luar web root. Jangan menyalin database/data produksi.
- Hentikan penyuntingan dan checkout uji selama migrasi. Pertahankan password protection serta `noindex` subdomain staging.
- Pastikan branch checkout hosting `staging`. Masuk ke `public_html/staging`, bukan `public_html` produksi.

```sh
git status --short
git pull --ff-only origin staging
php admin/po-install.php
php admin/po-cron.php --check
```

Installer menambah tabel `po_whatsapp_outbox` dan kolom pendukung laporan/notifikasi secara idempoten, tanpa menghapus pesanan, kuota, atau rekening lama. Jalankan tanpa `--seed-demo`. Tidak perlu mengimpor ulang seluruh SQL melalui phpMyAdmin. Database user perlu izin CREATE/ALTER/INDEX untuk migrasi. Bila gagal, pertahankan checkout tertutup dan tinjau penyebabnya; jangan DROP tabel.

## Konfigurasi privat

Edit **file yang sudah ada** `ruethies-data-staging/po-config.php` di luar `public_html`. Pertahankan `dsn`, `user`, `password`, dan terutama `secret` lama. Jangan mengganti file hosting dengan contoh dari laptop. Tambahkan:

```php
'manual_checkout_enabled' => true,
'fonnte' => [
    'enabled' => false,
    'token' => '',
    'owner_phone' => '6281234567890',
    'test_recipient' => '6281234567890',
    'allowed_recipients' => ['6281234567890'],
],
```

Ganti nomor contoh dengan nomor uji milik sendiri. Pada awal pengujian biarkan `enabled=false`; alur invoice/laporan/verifikasi tetap bisa diuji dari website. Ketika siap, hubungkan device di akun Fonnte, isi token device **di file privat**, lalu set `enabled=true`. Jangan membagikan token melalui chat, Git, screenshot, HTML, atau URL cron. Contoh konfigurasi ada di `admin/po-config.example.php`.

Semua pesan staging diberi tanda `[UJI STAGING]`. Bila `test_recipient` diisi, semua pesan pelanggan maupun pemilik dialihkan ke nomor tersebut. `allowed_recipients` wajib berisi 1–10 nomor saat Fonnte aktif; nomor tujuan di luar daftar tidak pernah dipanggil ke API. Jika override dikosongkan untuk uji beberapa nomor, masukkan semua nomor pelanggan/pemilik uji ke daftar tersebut. Tanpa override, nomor pemilik kosong berarti notifikasi pemilik dilewati.

```sh
php admin/po-cron.php --check
```

Perintah pemeriksaan tidak mengirim WhatsApp dan tidak menampilkan token/nomor/password.

## Cron hPanel

Buat Cron Job PHP setiap menit dari menu Cron Jobs hPanel. Pilih binary PHP versi yang sesuai dengan website; gunakan **path absolut** checkout staging:

```text
/path/php /home/USER/domains/ruethiespatisserie.com/public_html/staging/admin/po-cron.php --run
```

Ganti `/path/php` dan `/home/USER/...` sesuai hosting; jangan menyalin placeholder secara literal. Skrip hanya menerima CLI dan menolak checkout di luar layout staging. Setiap eksekusi menjalankan expiry, memproses maksimal lima pesan, lalu mencatat heartbeat privat. Lock proses mencegah cron tumpang tindih. Jangan membuat endpoint HTTP atau memasukkan token API ke perintah cron.

Worker memakai koneksi timeout 4 detik dan total timeout 12 detik per pesan. Kegagalan yang jelas belum diterima provider dicoba ulang dengan jeda meningkat, maksimal lima percobaan. Respons timeout, rusak, HTTP 5xx, atau worker terputus ditandai **perlu pemeriksaan**, bukan otomatis dikirim berulang. Tidak ada jaminan exactly-once dari API eksternal. Pemilik harus memeriksa riwayat Fonnte sebelum mencoba ulang manual (maksimal tiga kali); retry memakai versi agar dua tab tidak menggandakan antrean.

`accepted` berarti **diterima gateway**, bukan bukti WhatsApp terkirim, terbaca, atau uang diterima. Integrasi ini tidak menggunakan webhook delivered/read dari Fonnte. Invoice yang sudah kedaluwarsa/berubah status dilewati sebelum pemanggilan provider.

## Uji penerimaan staging

- Aktifkan rekening contoh; buat jadwal baru jenis transfer manual, tambahkan produk, dan buka setelah flag checkout aktif. Jadwal demo lama tetap memakai flow legacy.
- Checkout, simpan magic link dari website, periksa snapshot rekening/total dan cetak invoice. Refresh tidak memperpanjang batas bayar.
- Dengan Fonnte nonaktif, checkout dan laporan tetap bekerja. Aktifkan token + nomor uji, jalankan cron, dan periksa pesan contoh serta dashboard **Notifikasi WhatsApp** di admin PO.
- Laporan transfer tidak otomatis lunas. Hanya pemilik dapat menyetujui pembayaran setelah cek mutasi. Pada staging lama, keputusan uang/retry memerlukan password dan OTP bila aktif, dibatasi lima percobaan per 15 menit. Setelah hardening dari main digabungkan, mekanisme verifikasi ulang 5 menit digunakan.
- Coba perbaikan laporan, expiry tanpa laporan, klik ganda, dua admin memverifikasi bersamaan, serta alur siap/selesai. Pastikan kuota tidak ganda/negatif. Laporan under_review jangan dibiarkan tanpa pemeriksaan pemilik.
- Dashboard menampilkan antrean, hasil gagal/tidak pasti, dan peringatan bila heartbeat lebih dari tiga menit. Cek cron bila antrean menumpuk. Tautan staging tetap membutuhkan password akses subdomain.

Pengujian lokal otomatis memakai database sementara dan provider simulasi; tidak mengirim WhatsApp sungguhan:

```sh
PO_TEST_DSN='mysql:unix_socket=/path/test/mysql.sock;charset=utf8mb4' php tests/po-whatsapp.php
PO_TEST_DSN='mysql:unix_socket=/path/test/mysql.sock;charset=utf8mb4' php tests/po-manual.php
PO_TEST_DSN='mysql:unix_socket=/path/test/mysql.sock;charset=utf8mb4' php tests/po-flow.php
php tests/po-apache-routing.php
php tests/admin-ui.php
php tests/menu-photo-delete.php
node tests/po-ui.cjs
```

Jangan gunakan DSN produksi untuk tes: suite membuat dan menghapus database fixture sendiri. Sesuaikan `PO_TEST_USER`/`PO_TEST_PASSWORD` bila diperlukan.

## Pemulihan dan status keamanan

Matikan `fonnte.enabled` untuk menghentikan pengiriman tanpa menghapus pesanan. Matikan `manual_checkout_enabled` dan tutup jadwal untuk menghentikan checkout baru; invoice/order lama tetap bisa diakses dan laporan transfer yang masih sah tetap dapat dikirim. Cron expiry tetap diperlukan walaupun Fonnte nonaktif. Kesalahan konfigurasi notifikasi dilaporkan terpisah: expiry tetap dijalankan dan dashboard pesanan tetap dapat digunakan. Jangan mengubah `secret`: itu akan memutus dekripsi link notifikasi dan konsistensi checkout retry.

Setelah ada laporan/lunas, jangan restore database lama atau menghapus antrean demi mengulang pengujian; rekonsiliasi mutasi, status pesanan dan riwayat Fonnte terlebih dahulu. Backup/restore harus menyertakan database dan konfigurasi secret yang cocok.

Penggabungan hardening keamanan dari main masih menunggu validasi OTP produksi oleh pemilik. Perubahan ini tidak menggabungkan main dan tidak merilis PO ke produksi. Token Fonnte, nomor uji, migrasi database hosting, konfigurasi cron, dan satu pengiriman WhatsApp uji nyata tetap perlu disiapkan operator hosting sebelum dinyatakan aktif live.

Referensi API: [Sending API Messages — Fonnte](https://docs.fonnte.com/api-send-message/).
