<?php
// Ruethies Patisserie — pembuatan akun pemilik pertama. HANYA jalan bila belum ada akun.
// Setelah dipakai: HAPUS file ini dari hosting.
define('RUETHIES_ADMIN', true);
require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';
ruethies_session_start();
header('X-Robots-Tag: noindex, nofollow', true);

if (users_all() !== []) die('Akun sudah ada. Hapus file setup.php ini dari hosting SEKARANG.');
$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $nm = trim(preg_replace('/[^a-zA-Z0-9_.-]/', '', (string)($_POST['u'] ?? '')));
  $p1 = (string)($_POST['p1'] ?? ''); $p2 = (string)($_POST['p2'] ?? '');
  if ($nm === '') $err = 'Nama pengguna wajib diisi.';
  elseif (mb_strlen($p1) < 12) $err = 'Kata sandi minimal 12 karakter (campur huruf, angka, simbol).';
  elseif ($p1 !== $p2) $err = 'Konfirmasi sandi tidak sama.';
  else {
    users_save([['name' => $nm, 'hash' => password_hash($p1, PASSWORD_DEFAULT), 'role' => 'owner', 'totp' => '']]);
    ruethies_log($nm, 'setup-owner', 'akun pemilik dibuat');
    die('Akun pemilik <b>' . h($nm) . '</b> dibuat. <b>Hapus setup.php dari hosting</b>, lalu login di <a href="./">admin</a> dan aktifkan kode OTP di Pengaturan.');
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="robots" content="noindex, nofollow" />
<title>Setup Admin — Ruethies Patisserie</title>
<link rel="stylesheet" href="admin.css" />
</head>
<body>
<div class="gate"><div class="gatecard">
<div class="gateseal">N°51</div>
<p class="gateno">RUETHIES PATISSERIE · ADMIN</p>
<div class="card">
<h2>Setup akun pemilik</h2>
<p class="sub">Jalankan sekali saja, lalu <b>hapus file ini</b>.</p>
<?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
<form method="post" autocomplete="off">
<label>Nama pengguna<input name="u" required autocomplete="username" /></label>
<label>Kata sandi (min. 12 karakter)<input name="p1" type="password" required autocomplete="new-password" /></label>
<label>Ulangi kata sandi<input name="p2" type="password" required autocomplete="new-password" /></label>
<button class="w100">Buat akun pemilik</button>
</form>
</div>
</div></div>
</body>
</html>
