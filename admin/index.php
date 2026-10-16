<?php
// Ruethies Patisserie — halaman admin (login + dashboard + pengaturan). Tanpa framework, tanpa layanan luar.
define('RUETHIES_ADMIN', true);
require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';
ruethies_session_start();
header('X-Robots-Tag: noindex, nofollow', true);

$me = current_user();
$err = ''; $ok = $_GET['ok'] ?? '';

/* ---------- aksi POST ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $aksi = $_POST['aksi'] ?? '';

  // Login: username + password (rate-limit + CSRF tidak dipakai di form login pertama agar tidak bocor oracle;
  // proteksi = rate-limit per IP + sesi baru).
  if ($aksi === 'login') {
    $name = trim((string)($_POST['u'] ?? ''));
    $pass = (string)($_POST['p'] ?? '');
    if (!ruethies_ratelimit_ok()) { $err = 'Terlalu banyak percobaan. Tunggu 15 menit.'; }
    else {
      $u = user_get($name);
      if ($u && password_verify($pass, $u['hash'])) {
        if (password_needs_rehash($u['hash'], PASSWORD_DEFAULT)) {
          $all = users_all();
          foreach ($all as &$x) if ($x['name'] === $u['name']) $x['hash'] = password_hash($pass, PASSWORD_DEFAULT);
          users_save($all);
        }
        ruethies_ratelimit_reset();
        if (($u['role'] ?? '') === 'owner' && !empty($u['totp'])) {
          $_SESSION['pending'] = $u['name']; $_SESSION['pending_t'] = time();
          header('Location: ?p=kode'); exit;
        }
        ruethies_login($u);
        ruethies_log($u['name'], 'login', 'berhasil');
        header('Location: ?'); exit;
      }
      ruethies_ratelimit_hit();
      ruethies_log($name !== '' ? $name : '(kosong)', 'login-gagal', '');
      usleep(random_int(200000, 500000)); // samarkan timing
      $err = 'Nama atau kata sandi salah.';
    }
  }
  // Verifikasi kode 2FA pemilik.
  elseif ($aksi === 'kode') {
    $name = $_SESSION['pending'] ?? '';
    if ($name === '' || time() - ($_SESSION['pending_t'] ?? 0) > 300) { unset($_SESSION['pending']); $err = 'Sesi kode kedaluwarsa, login ulang.'; }
    else {
      $u = user_get($name);
      if ($u && !empty($u['totp']) && totp_verify($u['totp'], trim((string)($_POST['c'] ?? '')))) {
        unset($_SESSION['pending']);
        ruethies_login($u);
        ruethies_log($u['name'], 'login-2fa', 'berhasil');
        header('Location: ?'); exit;
      }
      ruethies_log($name, 'login-2fa-gagal', '');
      $err = 'Kode salah, coba lagi.';
    }
  }
  elseif ($aksi === 'logout') { $n = $me['name'] ?? '?'; ruethies_log($n, 'logout', ''); ruethies_logout(); header('Location: ?p=login'); exit; }

  // Semua aksi di bawah wajib login + CSRF.
  elseif (!$me || !ruethies_csrf_ok()) { http_response_code(403); die('Aksi ditolak.');
  }
  elseif ($aksi === 'toggle') {
    $items = menu_load(); $i = (int)($_POST['i'] ?? -1);
    if (isset($items[$i])) {
      $items[$i]['available'] = empty($items[$i]['available']) || $items[$i]['available'] === false ? true : false;
      if (menu_save($items)) { ruethies_log($me['name'], 'toggle', ($items[$i]['id']['n'] ?? '') . ' => ' . ($items[$i]['available'] ? 'tampil' : 'sembunyi')); header('Location: ?ok=tampil'); exit; }
      $err = 'Gagal menyimpan.';
    }
  }
  elseif ($aksi === 'save') {
    $items = menu_load(); $i = (int)($_POST['i'] ?? -1);
    $old = ($i >= 0 && isset($items[$i])) ? $items[$i] : null;
    $img = $old['img'] ?? '';
    if (!empty($_FILES['foto']['name'] ?? '')) {
      [$path, $perr] = photo_upload($_FILES['foto'], $_POST['n'] ?? 'menu');
      if ($perr) $err = $perr; else $img = $path;
    }
    if ($err === '') {
      $row = menu_validate(array_merge($_POST, ['img' => $img]));
      if (!$row) $err = 'Periksa isian: nama wajib, kategori valid.';
      else {
        if ($old) { $row['img'] = $img; $items[$i] = $row; }
        else $items[] = $row;
        if (menu_save($items)) { ruethies_log($me['name'], $old ? 'ubah' : 'tambah', $row['id']['n']); header('Location: ?ok=simpan'); exit; }
        $err = 'Gagal menyimpan.';
      }
    }
  }
  elseif ($aksi === 'hapus') {
    if (!is_owner($me)) { http_response_code(403); die('Khusus pemilik.'); }
    $items = menu_load(); $i = (int)($_POST['i'] ?? -1);
    if (isset($items[$i])) {
      $nm = $items[$i]['id']['n'] ?? '';
      array_splice($items, $i, 1);
      if (menu_save($items)) { ruethies_log($me['name'], 'hapus', $nm); header('Location: ?ok=hapus'); exit; }
      $err = 'Gagal menghapus.';
    }
  }
  elseif ($aksi === 'pw') {
    $all = users_all(); $p1 = (string)($_POST['p1'] ?? ''); $p2 = (string)($_POST['p2'] ?? '');
    if (mb_strlen($p1) < 12 || $p1 !== $p2) $err = 'Kata sandi minimal 12 karakter dan konfirmasi harus sama.';
    else {
      foreach ($all as &$x) if ($x['name'] === $me['name']) $x['hash'] = password_hash($p1, PASSWORD_DEFAULT);
      if (users_save($all)) { ruethies_log($me['name'], 'ganti-password', 'sendiri'); header('Location: ?p=pengaturan&ok=pw'); exit; }
      $err = 'Gagal menyimpan.';
    }
  }
  elseif ($aksi === '2fa-aktif') {
    if (!is_owner($me)) { http_response_code(403); die('Khusus pemilik.'); }
    $sec = $_SESSION['totp_baru'] ?? '';
    if ($sec !== '' && totp_verify($sec, trim((string)($_POST['c'] ?? '')))) {
      $all = users_all();
      foreach ($all as &$x) if ($x['name'] === $me['name']) $x['totp'] = $sec;
      unset($_SESSION['totp_baru']);
      if (users_save($all)) { ruethies_log($me['name'], '2fa-aktif', ''); header('Location: ?p=pengaturan&ok=2fa'); exit; }
      $err = 'Gagal menyimpan.';
    } else $err = 'Kode salah, coba lagi.';
  }
  elseif ($aksi === '2fa-mati') {
    if (!is_owner($me)) { http_response_code(403); die('Khusus pemilik.'); }
    $all = users_all();
    foreach ($all as &$x) if ($x['name'] === $me['name']) $x['totp'] = '';
    if (users_save($all)) { ruethies_log($me['name'], '2fa-mati', ''); header('Location: ?p=pengaturan&ok=2fa-mati'); exit; }
    $err = 'Gagal menyimpan.';
  }
  elseif ($aksi === 'user-tambah') {
    if (!is_owner($me)) { http_response_code(403); die('Khusus pemilik.'); }
    $nm = trim(preg_replace('/[^a-zA-Z0-9_.-]/', '', (string)($_POST['nu'] ?? '')));
    $pw = (string)($_POST['np'] ?? '');
    if ($nm === '' || user_get($nm) || mb_strlen($pw) < 12) $err = 'Nama sudah dipakai/kosong, atau sandi < 12 karakter.';
    else {
      $all = users_all(); $all[] = ['name' => $nm, 'hash' => password_hash($pw, PASSWORD_DEFAULT), 'role' => 'staff', 'totp' => ''];
      if (users_save($all)) { ruethies_log($me['name'], 'user-tambah', $nm); header('Location: ?p=pengaturan&ok=user'); exit; }
      $err = 'Gagal menyimpan.';
    }
  }
  elseif ($aksi === 'galeri-save') {
    $items = atmos_load(); $i = (int)($_POST['gi'] ?? -1);
    $old = ($i >= 0 && isset($items[$i])) ? $items[$i] : null;
    $img = $old['img'] ?? ''; $full = $old['imgFull'] ?? '';
    if (!empty($_FILES['gfoto']['name'] ?? '')) {
      [$p1, $p2, $perr] = galeri_upload($_FILES['gfoto'], $_POST['cap'] ?? 'suasana');
      if ($perr) $err = $perr; else { $img = $p1; $full = $p2; }
    }
    if ($err === '') {
      $cap = trim(strip_tags($_POST['cap'] ?? '')); $cape = trim(strip_tags($_POST['cape'] ?? ''));
      if ($cap === '' || mb_strlen($cap) > 80 || mb_strlen($cape) > 80) $err = 'Caption wajib diisi (maks 80).';
      elseif ($img === '') $err = 'Foto wajib diunggah untuk item baru.';
      else {
        $row = ['img' => $img, 'imgFull' => $full !== '' ? $full : $img,
          'id' => ['cap' => $cap], 'en' => ['cap' => $cape !== '' ? $cape : $cap]];
        if ($old) $items[$i] = $row; else $items[] = $row;
        if (atmos_save($items)) { ruethies_log($me['name'], $old ? 'galeri-ubah' : 'galeri-tambah', $cap); header('Location: ?ok=simpan#galeri'); exit; }
        $err = 'Gagal menyimpan.';
      }
    }
  }
  elseif ($aksi === 'galeri-gerak') {
    $items = atmos_load(); $i = (int)($_POST['gi'] ?? -1); $d = (int)($_POST['dir'] ?? 0);
    $j = $i + ($d > 0 ? 1 : -1);
    if (isset($items[$i], $items[$j])) {
      [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
      if (atmos_save($items)) { ruethies_log($me['name'], 'galeri-urut', (string)$i); header('Location: ?ok=simpan#galeri'); exit; }
      $err = 'Gagal menyimpan.';
    }
  }
  elseif ($aksi === 'galeri-hapus') {
    if (!is_owner($me)) { http_response_code(403); die('Khusus pemilik.'); }
    $items = atmos_load(); $i = (int)($_POST['gi'] ?? -1);
    if (isset($items[$i])) {
      $nm = $items[$i]['id']['cap'] ?? '';
      array_splice($items, $i, 1);
      if (atmos_save($items)) { ruethies_log($me['name'], 'galeri-hapus', $nm); header('Location: ?ok=hapus#galeri'); exit; }
      $err = 'Gagal menghapus.';
    }
  }
  elseif ($aksi === 'user-hapus') {
    if (!is_owner($me)) { http_response_code(403); die('Khusus pemilik.'); }
    $nm = (string)($_POST['nu'] ?? '');
    if ($nm === $me['name']) $err = 'Tidak bisa menghapus akun sendiri.';
    else {
      $all = array_values(array_filter(users_all(), fn($x) => $x['name'] !== $nm));
      if (users_save($all)) { ruethies_log($me['name'], 'user-hapus', $nm); header('Location: ?p=pengaturan&ok=user'); exit; }
      $err = 'Gagal menyimpan.';
    }
  }
  $me = current_user();
}

/* ---------- tampilan ---------- */
$p = $_GET['p'] ?? '';
if (!$me && $p !== 'kode') $p = 'login';
if (!$me && $p === 'kode' && empty($_SESSION['pending'])) $p = 'login';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="robots" content="noindex, nofollow" />
<title>Admin — Ruethies Patisserie</title>
<script src="admin.js" defer></script>
<style>
:root{--wine:#7A0000;--wine-deep:#4a0d0d;--bg:#FAF6F0;--card:#FFFDF9;--ink:#2B2320;--mut:#6F625B;--line:#E7DACA;--ok:#1E7A3C;--bad:#8f1d1d}
*{box-sizing:border-box}html{-webkit-text-size-adjust:100%}
body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:var(--bg);color:var(--ink);margin:0;padding:0 0 64px;font-size:16px;line-height:1.55}
.w{max-width:780px;margin:0 auto;padding:20px 16px}
.brandbar{background:var(--wine-deep);color:#F6E9E4;margin:0 -16px;padding:18px 20px;border-radius:0 0 22px 22px}
.brandbar .no{font-size:.72rem;letter-spacing:.3em;opacity:.75}
.brandbar h1{font-size:1.35rem;margin:2px 0;color:#fff}
.brandbar p{margin:0;font-size:.85rem;opacity:.85}
.card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:20px;margin:14px 0;box-shadow:0 6px 20px -14px rgba(74,13,13,.25)}
.card h2{font-size:1.1rem;color:var(--wine);margin:0 0 4px}
.sub{color:var(--mut);font-size:.9rem;margin:0 0 10px}
label{display:block;font-size:.9rem;font-weight:600;margin-top:14px}
input,select,textarea{width:100%;margin-top:6px;border:1.5px solid var(--line);border-radius:12px;padding:13px 14px;font:inherit;font-size:1rem;background:#fff;color:var(--ink);min-height:48px}
input:focus,select:focus,textarea:focus{outline:2px solid var(--wine);outline-offset:1px;border-color:var(--wine)}
input[type="checkbox"]{width:22px;height:22px;min-height:0;accent-color:var(--wine);vertical-align:-5px;margin-right:8px}
input[type="file"]{padding:10px}
button,.btn{border:0;background:var(--wine);color:#fff;border-radius:999px;padding:13px 26px;font:inherit;font-size:1rem;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block;margin-top:16px;min-height:48px;text-align:center}
button:active,.btn:active{transform:scale(.98)}
.ghost{background:#fff;color:var(--wine);border:1.5px solid var(--wine)}
.danger{background:#fff;color:var(--bad);border:1.5px solid var(--bad)}
.linkbtn{background:none;border:0;color:var(--wine);font-size:.9rem;font-weight:600;text-decoration:underline;padding:8px;min-height:0;margin:6px 0 0;cursor:pointer}
.err{background:#fdecec;border:1px solid #e8a0a0;color:var(--bad);border-radius:12px;padding:12px 16px}
.okmsg{background:#e9f6ee;border:1px solid #9ed3b2;color:var(--ok);border-radius:12px;padding:12px 16px}
.warn{background:#fff7e6;border:1px solid #e3c47f;color:#7a5b00;border-radius:12px;padding:12px 16px}
.tabs{position:sticky;top:0;z-index:5;display:flex;gap:8px;background:rgba(250,246,240,.96);padding:10px 0;flex-wrap:wrap}
.tabs .btn,.tabs button{margin:0;padding:10px 20px;min-height:44px;font-size:.92rem}
.tabs form{margin:0}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:14px 0}
.stat{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:12px 8px;text-align:center}
.stat b{display:block;font-size:1.4rem;color:var(--wine)}
.stat span{font-size:.72rem;color:var(--mut)}
.item{display:flex;gap:12px;align-items:center;padding:12px 0;border-top:1px solid var(--line);flex-wrap:wrap}
.item:first-of-type{border-top:0}
.item img.thumb{width:76px;height:58px;object-fit:cover;border-radius:10px;flex:none}
.item .grow{flex:1;min-width:150px}
.item .grow b{font-size:1rem}
.item .grow small{color:var(--mut)}
.badge{display:inline-block;font-size:.68rem;font-weight:700;background:#efe7dc;color:var(--wine);border-radius:999px;padding:3px 10px;margin-left:6px;vertical-align:2px}
.badge.hide{background:#f3d9d9;color:var(--bad)}
.actions{display:flex;gap:8px;flex-wrap:wrap;width:100%}
.actions button,.actions .btn{margin:0;padding:10px 18px;min-height:44px;font-size:.9rem}
.preview img{max-width:100%;max-height:220px;border-radius:12px;margin-top:10px;display:block}
.preview small{color:var(--mut)}
.count{font-size:.75rem;color:var(--mut);float:right;margin-top:14px}
.pwrow{display:flex;gap:8px}.pwrow input{flex:1}.pwrow button{flex:none;margin-top:6px;padding:13px 16px}
table{width:100%;font-size:.8rem;border-collapse:collapse}td{padding:7px 4px;border-bottom:1px solid var(--line);word-break:break-all;vertical-align:top}
.mono{font-family:ui-monospace,monospace;background:#f4efe8;border-radius:8px;padding:10px;word-break:break-all;font-size:.85rem}
small.hint{color:var(--mut);font-weight:400}
.stickybar{position:sticky;bottom:0;background:rgba(255,253,249,.97);border-top:1px solid var(--line);padding:10px 0;margin:16px -20px -20px;padding-left:20px;padding-right:20px;border-radius:0 0 18px 18px}
.stickybar button{width:100%;margin-top:0}
.item.off{opacity:.6}
@media(max-width:560px){.stats{grid-template-columns:repeat(2,1fr)}.w{padding:14px 12px}.actions button,.actions .btn{flex:1;text-align:center}}
@media(prefers-reduced-motion:reduce){*{transition:none!important;animation:none!important}}
</style>
</head>
<body>
<div class="w">
<header class="brandbar">
<div class="no">RUETHIES PATISSERIE</div>
<h1>Halaman Admin</h1>
<p>Kelola menu & galeri tanpa menyentuh kode. Perubahan tampil di web ±5 menit.</p>
</header>
<?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
<?php if ($ok === 'simpan'): ?><p class="okmsg">Tersimpan. Cek web ±5 menit lagi.</p><?php endif; ?>
<?php if ($ok === 'tampil'): ?><p class="okmsg">Status tampil/sembunyi diperbarui.</p><?php endif; ?>
<?php if ($ok === 'hapus'): ?><p class="okmsg">Item dihapus permanen.</p><?php endif; ?>
<?php if ($ok === 'pw'): ?><p class="okmsg">Kata sandi diganti.</p><?php endif; ?>
<?php if ($ok === '2fa'): ?><p class="okmsg">Kode OTP aktif. Setiap login pemilik wajib memasukkan kode.</p><?php endif; ?>
<?php if ($ok === '2fa-mati'): ?><p class="okmsg">Kode OTP dimatikan. Tidak disarankan.</p><?php endif; ?>
<?php if ($ok === 'user'): ?><p class="okmsg">Data staf diperbarui.</p><?php endif; ?>

<?php if ($p === 'login'): ?>
<div class="card">
<h2>Selamat datang 👋</h2>
<p class="sub">Masuk untuk mengelola menu dan galeri.</p>
<form method="post" autocomplete="off">
<input type="hidden" name="aksi" value="login" />
<label>Nama pengguna<input name="u" required autocomplete="username" /></label>
<label>Kata sandi
<span class="pwrow"><input id="pw-login" name="p" type="password" required autocomplete="current-password" /><button type="button" class="ghost" data-pw-toggle="pw-login">Lihat</button></span></label>
<button style="width:100%">Masuk</button>
</form>
</div>
<?php elseif ($p === 'kode'): ?>
<div class="card">
<h2>Kode keamanan</h2>
<p class="sub"><b>Pemilik:</b> buka aplikasi Authenticator di HP, masukkan 6 digit kode yang tampil.</p>
<form method="post" autocomplete="off">
<input type="hidden" name="aksi" value="kode" />
<label>Kode OTP<input name="c" inputmode="numeric" maxlength="6" required autocomplete="one-time-code" style="font-size:1.4rem;letter-spacing:.3em;text-align:center" /></label>
<button style="width:100%">Verifikasi</button>
</form>
</div>
<?php else: ?>
<div class="tabs">
<a class="btn ghost" href="?">🍰 Menu</a>
<a class="btn ghost" href="?#galeri">🖼️ Galeri</a>
<?php if (is_owner($me)): ?><a class="btn ghost" href="?p=pengaturan">⚙️ Pengaturan</a><?php endif; ?>
<form method="post"><input type="hidden" name="aksi" value="logout" /><button class="ghost">Keluar · <?= h($me['name']) ?></button></form>
</div>
<?php if (is_file(__DIR__ . '/setup.php')): ?><p class="warn"><b>Penting:</b> hapus file <span class="mono">admin/setup.php</span> dari hosting sekarang — akun awal sudah jadi.</p><?php endif; ?>
<?php if ($p !== 'pengaturan' && $p !== 'tambah' && $p !== 'ubah' && $p !== 'galeri-tambah' && $p !== 'galeri-ubah'):
  $_items = menu_load(); $_vis = array_filter($_items, fn($m) => !empty($m['available']));
  $_sig = array_filter($_items, fn($m) => !empty($m['signature']) && !empty($m['available']));
  $_gal = atmos_load(); ?>
<div class="stats">
<div class="stat"><b><?= count($_items) ?></b><span>Total menu</span></div>
<div class="stat"><b><?= count($_vis) ?></b><span>Tampil</span></div>
<div class="stat"><b><?= count($_sig) ?></b><span>Signature</span></div>
<div class="stat"><b><?= count($_gal) ?></b><span>Foto galeri</span></div>
</div>
<?php endif; ?>

<?php if ($p === 'pengaturan' && is_owner($me)): ?>
<div class="card">
<h2>Kode OTP pemilik (2FA)</h2>
<?php if (!empty($me['totp'])): ?>
<p class="sub">Status: <b>aktif</b>. Setiap login wajib kode 6 digit.</p>
<form method="post" data-confirm="Matikan OTP? Tidak disarankan."><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="2fa-mati" /><button class="danger">Matikan OTP</button></form>
<?php else:
  if (empty($_SESSION['totp_baru'])) $_SESSION['totp_baru'] = b32enc(random_bytes(20));
  $sec = $_SESSION['totp_baru'];
  $uri = 'otpauth://totp/Ruethies:' . rawurlencode($me['name']) . '?secret=' . $sec . '&issuer=Ruethies&digits=6&period=30';
?>
<p class="sub">1. Buka aplikasi Authenticator (Google/Microsoft/Apple) → tambah akun manual.<br />2. Masukkan kode rahasia ini:</p>
<p class="mono"><?= h(chunk_split($sec, 4, ' ')) ?></p>
<p class="sub">Atau URI: <span class="mono"><?= h($uri) ?></span></p>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="2fa-aktif" />
<label>Kode 6 digit dari aplikasi<input name="c" inputmode="numeric" maxlength="6" required /></label>
<button>Aktifkan OTP</button>
</form>
<?php endif; ?>
</div>
<div class="card">
<h2>Ganti kata sandi saya</h2>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="pw" />
<label>Sandi baru (min. 12 karakter)<input name="p1" type="password" required /></label>
<label>Ulangi sandi<input name="p2" type="password" required /></label>
<button>Simpan sandi</button>
</form>
</div>
<div class="card">
<h2>Akun staf</h2>
<?php foreach (users_all() as $x): if (($x['role'] ?? '') !== 'staff') continue; ?>
<form method="post" data-confirm="Cabut akses staf ini?"><div class="item"><span class="grow"><?= h($x['name']) ?></span>
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="user-hapus" /><input type="hidden" name="nu" value="<?= h($x['name']) ?>" />
<button class="danger">Cabut</button></div></form>
<?php endforeach; ?>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="user-tambah" />
<label>Nama pengguna staf<input name="nu" required /></label>
<label>Sandi awal (min. 12 karakter)<input name="np" type="password" required /></label>
<button>Tambah staf</button>
</form>
<small>Staf bisa tambah/ubah/tampilkan menu + upload foto. Hapus permanen & pengaturan khusus pemilik.</small>
</div>
<div class="card">
<h2>Log aktivitas (100 terakhir)</h2>
<table><?php foreach (ruethies_logs() as $l): ?>
<tr><td><?= h($l['t']) ?></td><td><?= h($l['oleh']) ?></td><td><?= h($l['aksi']) ?></td><td><?= h($l['detail']) ?></td></tr>
<?php endforeach; ?></table>
</div>
<?php endif; ?>

<?php
$edit = null;
if (($p === 'ubah' || $p === 'tambah')) {
  $items0 = menu_load(); $ei = (int)($_GET['i'] ?? -1);
  if ($p === 'ubah' && isset($items0[$ei])) $edit = $items0[$ei] + ['_i' => $ei];
}
if ($p === 'tambah' || $edit):
?>
<div class="card">
<h2><?= $edit ? 'Ubah menu' : 'Tambah menu' ?></h2>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="save" />
<input type="hidden" name="i" value="<?= $edit['_i'] ?? -1 ?>" />
<label>Kategori<select name="cat"><?php foreach (RUETHIES_CATS as $k => $v): ?>
<option value="<?= $k ?>" <?= (($edit['cat'] ?? '') === $k) ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
<label>Nama (Indonesia)<span class="count" id="c-n"></span><input name="n" maxlength="60" required data-count="c-n" value="<?= h($edit['id']['n'] ?? '') ?>" /></label>
<label>Deskripsi (Indonesia)<span class="count" id="c-d"></span><input name="d" maxlength="160" data-count="c-d" value="<?= h($edit['id']['d'] ?? '') ?>" /></label>
<label>Nama (Inggris, opsional)<span class="count" id="c-ne"></span><input name="ne" maxlength="60" data-count="c-ne" value="<?= h($edit['en']['n'] ?? '') ?>" /></label>
<label>Deskripsi (Inggris, opsional)<span class="count" id="c-de"></span><input name="de" maxlength="160" data-count="c-de" value="<?= h($edit['en']['d'] ?? '') ?>" /></label>
<label>Label <small class="hint">(mis. Best Seller — kosongkan bila tidak ada)</small><input name="badge" maxlength="24" value="<?= h($edit['badge'] ?? '') ?>" /></label>
<label>Tag <small class="hint">(mis. Manis • Lembut)</small><input name="tag" maxlength="40" value="<?= h($edit['tag']['id'] ?? '') ?>" /></label>
<label><input type="checkbox" name="signature" value="1" <?= !empty($edit['signature']) ? 'checked' : '' ?> /> Tampilkan di Signature <small class="hint">(maks 3)</small></label>
<label><input type="checkbox" name="available" value="1" <?= !isset($edit) || !empty($edit['available']) ? 'checked' : '' ?> /> Tampilkan di web</label>
<label>Foto <small class="hint">(JPG/PNG/WebP, maks 5 MB<?= $edit && !empty($edit['img']) ? ' — kosongkan untuk pakai foto lama' : '' ?>)</small><input type="file" name="foto" accept="image/jpeg,image/png,image/webp" data-preview="prev-foto" /></label>
<div class="preview" id="prev-foto"><?php if ($edit && !empty($edit['img'])): ?><img src="../<?= h($edit['img']) ?>" alt="Foto saat ini" style="width:120px;height:90px;object-fit:cover;border-radius:10px" /><?php endif; ?></div>
<div class="stickybar"><button>💾 Simpan menu</button></div>
<p style="text-align:center"><a class="linkbtn" href="?">← Kembali tanpa menyimpan</a></p>
</form>
</div>
<?php else: ?>
<div class="card"><a class="btn" href="?p=tambah">+ Tambah menu</a></div>
<?php foreach (RUETHIES_CATS as $ck => $cv):
  $items = menu_load(); ?>
<div class="card">
<h2><?= h($cv) ?></h2>
<?php $ada = false; foreach ($items as $i => $m): if (($m['cat'] ?? '') !== $ck) continue; $ada = true; ?>
<div class="item <?= empty($m['available']) || $m['available'] === false ? 'off' : '' ?>">
<?php if (!empty($m['img'])): ?><img class="thumb" src="../<?= h($m['img']) ?>" alt="" loading="lazy" /><?php endif; ?>
<span class="grow"><b><?= h($m['id']['n'] ?? '-') ?></b><br /><small><?= h($m['id']['d'] ?? '') ?></small>
<?php if (empty($m['available']) || $m['available'] === false): ?> <span class="badge hide">SEMBUNYI</span><?php endif; ?>
<?php if (!empty($m['signature'])): ?> <span class="badge">★ SIGNATURE</span><?php endif; ?></span>
<div class="actions">
<form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="toggle" /><input type="hidden" name="i" value="<?= $i ?>" /><button class="ghost"><?= empty($m['available']) || $m['available'] === false ? '👁️ Tampilkan' : '🚫 Sembunyikan' ?></button></form>
<a class="btn ghost" href="?p=ubah&i=<?= $i ?>">✏️ Ubah</a>
<?php if (is_owner($me)): ?>
<form method="post" data-confirm="Hapus permanen? Data tidak bisa dikembalikan."><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="hapus" /><input type="hidden" name="i" value="<?= $i ?>" /><button class="danger">🗑️ Hapus</button></form>
<?php endif; ?>
</div>
</div>
<?php endforeach; if (!$ada): ?><p class="sub">Belum ada item.</p><?php endif; ?>
</div>
<?php endforeach; ?>
<div class="card" id="galeri">
<h2>Galeri Suasana</h2>
<p class="sub">Foto tampil berurutan di web. Perubahan maksimal ±5 menit.</p>
<?php $gal = atmos_load(); foreach ($gal as $gi => $g): ?>
<div class="item">
<?php if (!empty($g['img'])): ?><img class="thumb" src="../<?= h($g['img']) ?>" alt="" loading="lazy" /><?php endif; ?>
<span class="grow"><b><?= h($g['id']['cap'] ?? '-') ?></b><br /><small><?= h($g['en']['cap'] ?? '') ?></small></span>
<div class="actions">
<form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="galeri-gerak" /><input type="hidden" name="gi" value="<?= $gi ?>" /><input type="hidden" name="dir" value="-1" /><button class="ghost" <?= $gi === 0 ? 'disabled' : '' ?>>↑</button></form>
<form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="galeri-gerak" /><input type="hidden" name="gi" value="<?= $gi ?>" /><input type="hidden" name="dir" value="1" /><button class="ghost" <?= $gi === count($gal) - 1 ? 'disabled' : '' ?>>↓</button></form>
<a class="btn ghost" href="?p=galeri-ubah&gi=<?= $gi ?>">✏️ Ubah</a>
<?php if (is_owner($me)): ?>
<form method="post" data-confirm="Hapus foto ini dari galeri?"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="galeri-hapus" /><input type="hidden" name="gi" value="<?= $gi ?>" /><button class="danger">🗑️</button></form>
<?php endif; ?>
</div>
</div>
<?php endforeach; if (!$gal): ?><p class="sub">Belum ada foto.</p><?php endif; ?>
<a class="btn" href="?p=galeri-tambah">+ Tambah foto</a>
</div>
<?php
$gedit = null;
if (($p === 'galeri-ubah' || $p === 'galeri-tambah')) {
  $g0 = atmos_load(); $gei = (int)($_GET['gi'] ?? -1);
  if ($p === 'galeri-ubah' && isset($g0[$gei])) $gedit = $g0[$gei] + ['_i' => $gei];
}
if ($p === 'galeri-tambah' || $gedit):
?>
<div class="card">
<h2><?= $gedit ? 'Ubah foto' : 'Tambah foto' ?></h2>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="galeri-save" />
<input type="hidden" name="gi" value="<?= $gedit['_i'] ?? -1 ?>" />
<label>Caption (Indonesia)<span class="count" id="c-cap"></span><input name="cap" maxlength="80" required data-count="c-cap" value="<?= h($gedit['id']['cap'] ?? '') ?>" /></label>
<label>Caption (Inggris, opsional)<span class="count" id="c-cape"></span><input name="cape" maxlength="80" data-count="c-cape" value="<?= h($gedit['en']['cap'] ?? '') ?>" /></label>
<label>Foto <small class="hint">(JPG/PNG/WebP, maks 8 MB<?= $gedit && !empty($gedit['img']) ? ' — kosongkan untuk pakai foto lama' : '' ?>)</small><input type="file" name="gfoto" accept="image/jpeg,image/png,image/webp" data-preview="prev-gfoto" /></label>
<div class="preview" id="prev-gfoto"><?php if ($gedit && !empty($gedit['img'])): ?><img src="../<?= h($gedit['img']) ?>" alt="Foto saat ini" style="width:120px;height:90px;object-fit:cover;border-radius:10px" /><?php endif; ?></div>
<div class="stickybar"><button>💾 Simpan foto</button></div>
<p style="text-align:center"><a class="linkbtn" href="?#galeri">← Kembali tanpa menyimpan</a></p>
</form>
</div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
