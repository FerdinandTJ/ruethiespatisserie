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
<style>body{font-family:system-ui,sans-serif;background:#FAF6F0;color:#2B2320;padding:24px 16px}.w{max-width:480px;margin:0 auto;background:#fff;border:1px solid #E7DACA;border-radius:16px;padding:22px}label{display:block;font-weight:600;font-size:.85rem;margin-top:12px}input{width:100%;margin-top:6px;border:1px solid #E7DACA;border-radius:12px;padding:11px 13px;font:inherit}button{border:0;background:#7A0000;color:#fff;border-radius:999px;padding:11px 22px;font:inherit;font-weight:600;cursor:pointer;margin-top:14px}.err{background:#fdecec;border:1px solid #e8a0a0;color:#8f1d1d;border-radius:12px;padding:10px 14px}</style>
</head>
<body>
<div class="w">
<h1>Setup akun pemilik</h1>
<p>Jalankan sekali saja, lalu <b>hapus file ini</b>.</p>
<?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
<form method="post" autocomplete="off">
<label>Nama pengguna<input name="u" required autocomplete="username" /></label>
<label>Kata sandi (min. 12 karakter)<input name="p1" type="password" required autocomplete="new-password" /></label>
<label>Ulangi kata sandi<input name="p2" type="password" required autocomplete="new-password" /></label>
<button>Buat akun pemilik</button>
</form>
</div>
</body>
</html>
