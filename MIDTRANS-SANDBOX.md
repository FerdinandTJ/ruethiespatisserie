# Midtrans Sandbox untuk PO staging

Implementasi menggunakan Snap Redirect melalui PHP/cURL native, MariaDB yang sudah ada, dan antrean Fonnte. Tidak ada SDK/snap.js pada beranda. CSP staging mengizinkan redirect formulir ke app.sandbox.midtrans.com; tujuan lain tetap dibatasi. Rilis ini menolak environment production dan memakai endpoint Sandbox tetap. Pesanan manual/legacy tidak diubah.

## 1. Update kode hosting

Jalankan dari **public_html/staging**. Script membuat backup privat di luar public_html, menyimpan perubahan CMS, dan mengembalikan menu.json/faq.json/atmosphere.json serta blok Basic Auth hPanel. Script berhenti bila menemukan perubahan kode lain. Jangan menjalankan pada produksi.

```bash
git fetch origin staging
STAGING_DEPLOY_SCRIPT=$(mktemp)
git show origin/staging:tools/update-staging.sh > "$STAGING_DEPLOY_SCRIPT" && bash "$STAGING_DEPLOY_SCRIPT"
rm -f "$STAGING_DEPLOY_SCRIPT"
```

Jika ada error, berhenti dan kirim output tanpa isi token. Jangan reset --hard atau stash pop. Script tidak memetakan/mengganti katalog dan tidak menghapus snapshot PO. Basic Auth dikembalikan setelah checkout kode; lakukan rangkaian update saat staging tidak sedang dipakai penguji.

## 2. Ambil Server Key Sandbox

Pada dashboard Midtrans, pastikan mode **Sandbox**, lalu buka Settings → Access Keys. Salin **Server Key** Sandbox (berawalan `SB-Mid-server-`). Integrasi redirect ini tidak membutuhkan Client Key di browser.

Edit file yang sudah ada melalui SSH:

```bash
nano ../../ruethies-data-staging/po-config.php
```

Tambahkan satu entri ini di dalam array `return [...]`. Jangan mengganti seluruh file; pertahankan dsn/user/password/secret dan konfigurasi Fonnte lama. Jangan mengirim kunci melalui chat atau memasukkannya ke Git.

```php
'midtrans' => [
    'enabled' => true,
    'environment' => 'sandbox',
    'server_key' => 'SB-Mid-server-ISI_DARI_DASHBOARD_SANDBOX',
],
```

Flag manual_checkout_enabled boleh tetap aktif: setiap jadwal mempunyai jenis pembayaran tersendiri.

## 3. Jalankan migrasi

Backup database staging melalui fasilitas hosting/phpMyAdmin sebelum migrasi. Tidak perlu mengimpor SQL secara manual. CLI berikut hanya menambahkan tabel pembayaran Midtrans dan penanda migrasi, idempoten, tanpa mengubah rekening, pesanan, akun, UID katalog atau data Fonnte.

```bash
php admin/midtrans-upgrade.php --check
php admin/midtrans-upgrade.php --apply
php admin/midtrans-upgrade.php --check
php admin/po-cron.php --check
```

Hasil check harus menunjukkan `environment: sandbox`, `enabled: true`, `schema_ready: true`; cron menambahkan `midtrans_sandbox_enabled: true`. Hentikan jika ada error. Tidak perlu mengulang security-upgrade atau seed-demo untuk rilis ini.

## 4. Atur URL di dashboard Midtrans Sandbox

Settings → Configuration:

- **Payment Notification URL:** `https://staging.ruethiespatisserie.com/midtrans-notify.php`
- **Finish Redirect URL:** `https://staging.ruethiespatisserie.com/pre-order/return/`
- **Unfinished Redirect URL:** `https://staging.ruethiespatisserie.com/pre-order/return/`
- **Error Redirect URL:** `https://staging.ruethiespatisserie.com/pre-order/return/`

Simpan pengaturan. Nama/posisi menu dashboard dapat berubah; gunakan field yang sama. Endpoint notification harus dapat diakses Midtrans tanpa password. Aturan Files yang baru mengecualikan **hanya** midtrans-notify.php dari Basic Auth; halaman staging lainnya tetap dilindungi. Jangan menghapus seluruh blok PWPROTECTID hPanel.

Periksa dari Terminal Mac tanpa kredensial Basic Auth:

```bash
curl -sS -o /dev/null -w '%{http_code}\n' https://staging.ruethiespatisserie.com/midtrans-notify.php
curl -sS -o /dev/null -w '%{http_code}\n' https://staging.ruethiespatisserie.com/pre-order/
```

Hasil yang diharapkan: webhook **405** (GET ditolak), halaman PO **401** (password protection). Jika webhook 401, jangan mulai pengujian pembayaran; aturan Basic Auth hosting perlu ditinjau. Mengirim notifikasi contoh yang tidak cocok dengan transaksi aplikasi dapat menghasilkan 403; gunakan transaksi uji sesungguhnya untuk menilai perubahan status.

## 5. Buat jadwal uji baru

1. Login admin staging → Pre-order → Jadwal → Tambah jadwal.
2. Pilih **Midtrans Sandbox (simulasi pembayaran)**. Jangan mengubah jenis jadwal manual yang sudah berisi pesanan.
3. Atur buka sekarang, tutup kemudian, dan pengambilan sesudah tutup. Semua waktu WIB.
4. Simpan, tambahkan produk dari katalog lama yang dipertahankan, harga uji misalnya Rp25.000, kuota dan batas beli.
5. Buka PO. Aplikasi menolak membuka jadwal gateway jika konfigurasi Sandbox belum aktif.

## 6. Uji alur pelanggan

1. Buka `/pre-order/`, pilih jadwal Sandbox, isi nama dan nomor uji dalam allowlist Fonnte, lalu kirim pesanan.
2. Simpan tautan pribadi invoice. Cron mengirim notifikasi invoice melalui Fonnte sesuai konfigurasi test_recipient/allowed_recipients yang sudah ada.
3. Klik **Bayar di Midtrans Sandbox**. Website mengarahkan ke domain `app.sandbox.midtrans.com`.
4. Untuk pengujian awal pilih **BCA Virtual Account** atau BNI bila tersedia. Salin nomor VA simulasi.
5. Buka simulator resmi BCA: https://simulator.sandbox.midtrans.com/bca/va/index (atau BNI https://simulator.sandbox.midtrans.com/bni/va/index). Masukkan VA dan selesaikan simulasi bayar. **Jangan memakai aplikasi bank/uang sungguhan.**
6. Kembali ke invoice. Gunakan **Periksa status pembayaran** jika webhook belum tiba. Redirect saja tidak menandai lunas.
7. Pastikan invoice menjadi lunas, admin melihat pembayaran terverifikasi, dan Fonnte mengirim status ke pelanggan serta pemilik. Pengiriman WhatsApp tetap tergantung cron/perangkat/allowlist.
8. Admin dapat menandai siap diambil lalu selesai; status tersebut juga memakai antrean Fonnte.

Metode yang ditawarkan pada sesi ini dibatasi VA/e-wallet/QRIS; kartu kredit tidak diaktifkan pada permintaan Snap. Gunakan metode yang tersedia pada akun Sandbox. Untuk QRIS/GoPay gunakan simulator dari panduan resmi, bukan aplikasi pembayaran nyata.

Cron yang sudah ada tetap `php .../public_html/staging/admin/po-cron.php --run` setiap menit. Cron yang sama melakukan maksimal 3 pemeriksaan pembayaran per run dan antrean Fonnte yang dibatasi. Jangan menjalankan dua konfigurasi cron berbeda tanpa kebutuhan. `--run` dapat mengirim WhatsApp ke nomor uji yang diizinkan; `--check` tidak mengirim.

## Batas waktu dan kasus khusus

- Kuota ditahan 15 menit sejak checkout. Pengaturan expiry pembayaran memakai start_time checkout, bukan waktu pengguna memilih metode. Halaman Snap memakai sisa waktu yang tersedia.
- Jika halaman pembayaran belum pernah dibuat dan sisa waktu kurang dari 5 menit, aplikasi tidak membuka sesi baru; tunggu invoice ditutup dan buat pesanan uji baru.
- Status 404 dari API dapat berarti pengguna belum memilih metode. Aplikasi tidak langsung melepas kuota: tunggu batas pembayaran + toleransi 2 menit dan hasil pemeriksaan API. Gangguan jaringan selalu menahan kuota sampai status bisa dipastikan.
- Jika pembuatan Snap timeout, jangan otomatis mengirim create ulang. Invoice menampilkan bahwa pembayaran sedang diperiksa; gunakan cron/status. Ini mencegah transaksi ganda ketika respons penyedia tidak sampai.
- Pembayaran yang masuk setelah kuota dilepas menjadi **Pembayaran perlu ditinjau pemilik**, tidak mengambil kuota kembali atau otomatis diproses. Refund/partial refund juga ditandai untuk tinjauan tanpa mengubah stok otomatis. Penyelesaian/refund otomatis belum termasuk rilis Sandbox ini.
- Menonaktifkan `midtrans.enabled` menghentikan checkout gateway baru. Pertahankan Server Key sampai seluruh transaksi uji selesai agar webhook dan rekonsiliasi tetap berjalan. Fingerprint key pada invoice mencegah pemeriksaan memakai key akun lain; jika key berubah, kuota tetap ditahan untuk peninjauan.
- Return URL membaca invoice terakhir dari sesi browser sendiri. Jika cookie/sesi tidak tersedia atau kembali melalui perangkat lain, halaman kembali ke katalog; gunakan magic link invoice dari website/WhatsApp.

## Keamanan dan hasil pengujian

Signature SHA-512 diperiksa sebelum status dicocokkan melalui GET Status API dengan Server Key. Nominal, mata uang dan ID transaksi diverifikasi. Webhook ganda, status terlambat, retry checkout dan tombol bayar paralel tidak menggandakan reservasi. Kunci, isi notifikasi lengkap, nomor VA dan token Snap tidak ditulis ke log aplikasi.

Pengujian lokal memakai database terisolasi dan mock provider, termasuk paralel create/paid, CSRF HTTP, callback palsu, timeout, expiry, late payment, refund dan Basic Auth Apache. Alur demo/manual/Fonnte serta keamanan/SEO turut diuji (23 suite lokal). Browser Chrome terisolasi juga mengonfirmasi bahwa CSP self-only memblokir redirect form, sedangkan allowlist Sandbox mengizinkannya; request eksternal dicegat sebelum keluar jaringan. Belum diuji langsung pada Safari perangkat nyata. **Belum ada transaksi Sandbox nyata dari akun pemilik yang dijalankan**; pengujian tersebut dilakukan setelah konfigurasi hosting di atas.

## Pemulihan

Tutup jadwal Sandbox dan set `midtrans.enabled` ke false untuk menghentikan pengajuan baru. Jangan menghapus tabel baru atau memulihkan database lama ketika masih ada transaksi tertunda. Biarkan key/cron/webhook tersedia untuk menyelesaikan pemeriksaan transaksi yang sudah tercatat. Jika kode perlu dikembalikan ke commit sebelumnya, catat bahwa kode lama tidak memahami pesanan midtrans_v1: jadwal harus tetap ditutup dan rekonsiliasi memerlukan rilis ini. Backup tidak dipulihkan secara otomatis ke akun/metadata keamanan lama.

Referensi resmi: [Snap Redirect](https://docs.midtrans.com/docs/snap-snap-integration-guide), [webhook](https://docs.midtrans.com/docs/https-notification-webhooks), [expiry Snap](https://docs.midtrans.com/reference/expire-a-snap-session), [pengujian Sandbox](https://docs.midtrans.com/docs/testing-payment-on-sandbox).
