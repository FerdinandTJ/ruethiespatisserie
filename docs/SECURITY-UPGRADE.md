# Upgrade keamanan admin

Perubahan ini berasal dari `main`, tanpa fitur PO, framework, API, atau database baru. Tidak memerlukan impor phpMyAdmin. PHP 8.3 dan ekstensi yang dipakai sebelumnya (GD, mbstring, fileinfo) tetap diperlukan.

## Sebelum pull pada produksi

1. Batasi sementara akses `/admin` melalui hPanel (misalnya password protection). Halaman publik tetap dapat dibuka. Hentikan aktivitas penyuntingan admin selama backup dan upgrade.
2. Masuk melalui SSH ke document root produksi yang benar. Periksa `pwd`, `git branch --show-current`, `git status --short`, dan `php -v`. Branch harus `main`; jangan menjalankan upgrade dari direktori staging untuk data produksi.
3. Buat backup di luar `public_html`, dengan izin privat. Backup seluruh file website, termasuk gambar/konten hosting, serta direktori privat `../ruethies-data`. Jika instalasi menggunakan fallback `admin/data`, direktori tersebut juga wajib ikut backup. Jangan menimpa JSON hosting memakai JSON laptop, `git reset --hard`, atau `git checkout .`.

Contoh setelah memastikan lokasi direktori:

```sh
umask 077
release_backup="../security-backup-$(date +%Y%m%d-%H%M%S)"
mkdir "$release_backup"
tar --exclude=./.git --exclude=./staging -czf "$release_backup/public_html.tar.gz" .
# Jalankan bila penyimpanan privat berada di ../ruethies-data:
tar -C .. -czf "$release_backup/ruethies-data.tar.gz" ruethies-data
```

Simpan backup di lokasi aman; backup berisi hash kata sandi dan secret 2FA. Jangan menaruhnya di direktori yang dapat diunduh lewat web.

## Pull dan penerapan

```sh
git pull --ff-only origin main
php admin/security-upgrade.php --check
php admin/security-upgrade.php --apply
php admin/security-upgrade.php --check
```

Jika Git melaporkan konflik/perubahan lokal, berhenti dan tinjau file yang bersangkutan. Jangan memaksa overwrite konten. Bila pemeriksaan atau apply gagal, pertahankan pembatasan akses admin dan perbaiki sebabnya terlebih dahulu.

`--check` hanya memeriksa, tanpa mengubah file. `--apply` membuat backup privat di direktori data `/backups/security-*`, menambahkan `auth_version` dan counter TOTP yang belum ada, meregenerasi blok FAQ homepage serta `llms.txt` dari `faq.json` hosting, dan mengarsipkan log lama sekali. Penerapan ulang mempertahankan versi, hash, secret 2FA, dan counter yang sudah ada. Berkas akun rusak tidak diganti dengan daftar kosong. Lokasi fallback lama yang sudah berisi akun tetap dipakai.

Upgrade awal membuat seluruh sesi admin lama tidak berlaku. Akun, password dan Authenticator yang sudah terdaftar tetap digunakan. Tidak perlu membuat ulang akun. Jam hosting harus sinkron untuk OTP.

## Pemeriksaan setelah upgrade

Buka kembali akses admin hanya setelah apply berhasil. Periksa dengan akun pemilik sendiri:

- Login password + OTP; gunakan kode baru setelah counter sebelumnya sudah dipakai. Kode yang sama tidak bisa dipakai lagi, termasuk untuk verifikasi ulang.
- Cookie/sesi lama tidak dapat membuka dashboard. Mengubah password atau 2FA mencabut semua sesi akun, termasuk sesi pelaksana.
- Verifikasi ulang password + OTP (bila aktif) berlaku kurang dari 300 detik untuk password, 2FA, dan tambah/cabut staf. Setelah kedaluwarsa, isi ulang tindakan; password/OTP tidak disimpan sebagai payload tertunda.
- Navigasi/filter, edit menu, galeri, FAQ, role staf dan halaman publik/SEO bekerja. Gunakan konten uji hanya pada fixture lokal atau staging.
- Respons admin `noindex`, cookie HttpOnly/SameSite Strict/Secure melalui HTTPS, CSP dan header keamanan tetap tersedia.
- Melalui domain produksi, `/staging/` dan `/%73taging/` harus 403 (404 bila ditolak server lebih awal). Subdomain staging tetap meminta password hPanel dan mengirim `noindex`.

Pemeriksaan HTTP live dibatasi: satu pembukaan homepage/admin dan beberapa path proteksi. Jangan mencoba brute force OTP/login produksi atau mengubah konten untuk membuktikan proteksi. Uji agresif hanya memakai fixture/akun uji staging.

## Retensi dan batas keamanan

- Login: 5 percobaan/15 menit per akun dan IP server (`REMOTE_ADDR`). Header IP dari klien tidak dipakai.
- Challenge OTP: 5 menit, 5 percobaan; batas akun OTP 5/15 menit juga berlaku pada verifikasi ulang. Challenge baru tidak menghapus batas akun. Reservasi dilakukan sebelum verifikasi; autentikasi penuh yang berhasil baru mereset bucket login/OTP.
- Idle sesi 30 menit, batas absolut 8 jam. OTP yang diterima menyimpan counter secara atomik untuk mencegah replay.
- Unggahan: 20 percobaan/akun/15 menit sebelum decode; login ulang tidak mereset batas unggahan. Multipart menu 5 MiB, galeri 8 MiB; jalur base64 3 MiB. Metadata harus 2 piksel atau lebih, maksimal 8 juta piksel dan 8.192 per sisi; JPG/PNG/WebP selalu di-encode ulang.
- Log: maksimal 4 KiB/entri, 5 MiB/file aktif, 3 file rotasi. Dashboard membaca maksimal 512 KiB dari bagian akhir dan menampilkan maksimal 100 entri. Arsip log lama dan backup upgrade berada di luar retensi otomatis: simpan/pindahkan sesuai kebutuhan pemilik.

## Pemulihan melalui SSH

Tetap batasi akses admin selama pemulihan. Periksa izin direktori privat dan jalankan `--check`. Jangan menghapus `users.json`, seluruh direktori data, atau metadata keamanan untuk memulai ulang. Jangan memulihkan salinan akun lama di atas data terbaru: itu dapat mengaktifkan kembali sesi/counter lama dan mengembalikan staf yang sudah dicabut.

Untuk mencabut semua sesi/challenge sambil mempertahankan password, 2FA dan counter terbaru:

```sh
php admin/security-upgrade.php --revoke-sessions
```

Untuk mereset password **pemilik yang masih ada**, tanpa mengubah staf, secret 2FA, atau counter terakhir, isi password lewat STDIN (tidak masuk argumen/proses/history shell):

```sh
read -r -s 'recovery_password?Password baru pemilik: '
printf '\n'
printf '%s\n' "$recovery_password" | php admin/security-upgrade.php --recover-owner NAMA_PEMILIK
unset recovery_password
```

Contoh `read` di atas untuk zsh; pada Bash gunakan `read -r -s -p 'Password baru pemilik: ' recovery_password`. Password baru minimal 12 karakter, maksimal 72 byte. Versi autentikasi akun diganti, sehingga sesi lama tetap tidak berlaku. CLI membuat backup privat sebelum pemulihan.

Hanya jika perangkat Authenticator benar-benar hilang, operator SSH dapat menambahkan `--disable-2fa` pada perintah pemulihan pemilik. Ini sengaja menonaktifkan 2FA pada akun tersebut; setelah login, segera daftarkan Authenticator kembali dengan pembuktian kode baru. Akun lain tidak berubah, counter/metadata terbaru tidak dipulihkan dari backup lama.

Untuk masalah konten, pulihkan hanya file konten yang terdampak, kemudian regenerasi FAQ dengan `--apply` bila perlu. Untuk masalah kode, utamakan perbaikan maju atau rollback sebagian yang **tetap mempertahankan pengamanan autentikasi ini**. Jangan kembali ke kode autentikasi lama yang tidak memeriksa `auth_version`; mengganti versi akun saja tidak mengamankan kode lama tersebut. Jika akun JSON rusak, simpan berkas rusak untuk analisis dan perbaiki record terdampak secara terarah sambil mempertahankan metadata terbaru; jangan membuat ulang seluruh daftar akun dari snapshot lama.

## Validasi lokal dan staging

```sh
php tests/security-auth.php
php tests/security-resources.php
php tests/security-upgrade.php
php tests/admin-ui.php
php tests/menu-photo-delete.php
php tests/seo-check.php
php tests/security-routing.php
```

Fixture memakai direktori temporer terisolasi, bukan akun/konten hosting. Tes HTTP memerlukan izin membuka port lokal. Tes routing menjalankan Apache HTTPS lokal dengan ekstensi POSIX/PCNTL; di Linux sesuaikan `APACHE_BINARY` dan `APACHE_MODULE_DIR` bila berbeda.

Setelah produksi selesai di-upgrade dan tervalidasi, gabungkan commit keamanan yang sama ke staging secara terkontrol. Tinjau konflik autentikasi/CMS dengan fitur PO, jalankan kembali tes staging, backup **ruethies-data-staging**, dan jalankan CLI dari checkout staging. Jangan memakai direktori data produksi untuk staging. Penggabungan staging menunggu validasi produksi oleh operator hosting.
