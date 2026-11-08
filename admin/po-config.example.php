<?php
// Salin ke ruethies-data-staging/po-config.php DI LUAR public_html.
// Gunakan database/user khusus staging, bukan database produksi.
// Buat secret sekali: php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
return [
  'dsn' => 'mysql:host=localhost;dbname=GANTI_DATABASE_STAGING;charset=utf8mb4',
  'user' => 'GANTI_USER_DATABASE',
  'password' => 'GANTI_PASSWORD_DATABASE',
  'secret' => 'GANTI_DENGAN_64_KARAKTER_HEKSADESIMAL_ACAK',
  // Aktifkan sesudah migrasi dan pemeriksaan staging. Jangan mengganti secret lama.
  'manual_checkout_enabled' => false,
  'fonnte' => [
    'enabled' => false,
    'token' => '', // Token device Fonnte; bukan Bearer. Hanya di file privat ini.
    'owner_phone' => '', // Nomor untuk laporan transfer / pemberitahuan pemilik.
    'test_recipient' => '', // Staging: arahkan semua pesan ke satu nomor uji milik sendiri.
    'allowed_recipients' => [], // Contoh ['6281234567890']; wajib saat Fonnte aktif.
  ],
];
