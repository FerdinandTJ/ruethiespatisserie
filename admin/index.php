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
<style>
:root{--wine:#7A0000;--bg:#FAF6F0;--ink:#2B2320;--mut:#6F625B;--line:#E7DACA}
*{box-sizing:border-box}body{font-family:system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);margin:0;padding:24px 16px}
.w{max-width:760px;margin:0 auto}.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px;margin:14px 0}
h1{font-size:1.4rem;color:var(--wine);margin:0 0 4px}.sub{color:var(--mut);font-size:.9rem;margin:0 0 12px}
label{display:block;font-size:.85rem;font-weight:600;margin-top:12px}
input,select,textarea{width:100%;margin-top:6px;border:1px solid var(--line);border-radius:12px;padding:11px 13px;font:inherit;background:#fff}
button,.btn{border:0;background:var(--wine);color:#fff;border-radius:999px;padding:11px 22px;font:inherit;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;margin-top:14px}
.ghost{background:#fff;color:var(--wine);border:1px solid var(--wine)}
.danger{background:#fff;color:#8f1d1d;border:1px solid #8f1d1d}
.err{background:#fdecec;border:1px solid #e8a0a0;color:#8f1d1d;border-radius:12px;padding:10px 14px}
.okmsg{background:#e9f6ee;border:1px solid #9ed3b2;color:#1E7A3C;border-radius:12px;padding:10px 14px}
.warn{background:#fff7e6;border:1px solid #e3c47f;color:#7a5b00;border-radius:12px;padding:10px 14px}
.row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.row img{width:72px;height:54px;object-fit:cover;border-radius:10px}
.badge{font-size:.7rem;background:#eee;border-radius:999px;padding:2px 10px}
.off{opacity:.55}.grow{flex:1;min-width:140px}
.top{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
.top form{margin:0}.top button{margin:0;padding:8px 16px;font-size:.85rem}
table{width:100%;font-size:.85rem;border-collapse:collapse}td{padding:6px 4px;border-bottom:1px solid var(--line);word-break:break-all}
.mono{font-family:ui-monospace,monospace;background:#f4efe8;border-radius:8px;padding:10px;word-break:break-all}
small{color:var(--mut)}
</style>
</head>
<body>
<div class="w">
<h1>Admin Ruethies Patisserie</h1>
<p class="sub">Kelola menu tanpa menyentuh kode. Perubahan tampil di web maksimal ±5 menit.</p>
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
<form method="post" autocomplete="off">
<input type="hidden" name="aksi" value="login" />
<label>Nama pengguna<input name="u" required autocomplete="username" /></label>
<label>Kata sandi<input name="p" type="password" required autocomplete="current-password" /></label>
<button>Masuk</button>
</form>
</div>
<?php elseif ($p === 'kode'): ?>
<div class="card">
<p><b>Pemilik:</b> masukkan 6 digit kode dari aplikasi Authenticator.</p>
<form method="post" autocomplete="off">
<input type="hidden" name="aksi" value="kode" />
<label>Kode OTP<input name="c" inputmode="numeric" maxlength="6" required autocomplete="one-time-code" /></label>
<button>Verifikasi</button>
</form>
</div>
<?php else: ?>
<div class="top">
<div class="row">
<a class="btn ghost" href="?">Menu</a>
<?php if (is_owner($me)): ?><a class="btn ghost" href="?p=pengaturan">Pengaturan</a><?php endif; ?>
</div>
<form method="post"><input type="hidden" name="aksi" value="logout" /><button class="ghost">Keluar (<?= h($me['name']) ?>)</button></form>
</div>
<?php if (is_file(__DIR__ . '/setup.php')): ?><p class="warn"><b>Penting:</b> hapus file <span class="mono">admin/setup.php</span> dari hosting sekarang — akun awal sudah jadi.</p><?php endif; ?>

<?php if ($p === 'pengaturan' && is_owner($me)): ?>
<div class="card">
<h1 style="font-size:1.1rem">Kode OTP pemilik (2FA)</h1>
<?php if (!empty($me['totp'])): ?>
<p class="sub">Status: <b>aktif</b>. Setiap login wajib kode 6 digit.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="2fa-mati" /><button class="danger" onclick="return confirm('Matikan OTP? Tidak disarankan.')">Matikan OTP</button></form>
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
<h1 style="font-size:1.1rem">Ganti kata sandi saya</h1>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="pw" />
<label>Sandi baru (min. 12 karakter)<input name="p1" type="password" required /></label>
<label>Ulangi sandi<input name="p2" type="password" required /></label>
<button>Simpan sandi</button>
</form>
</div>
<div class="card">
<h1 style="font-size:1.1rem">Akun staf</h1>
<?php foreach (users_all() as $x): if (($x['role'] ?? '') !== 'staff') continue; ?>
<form method="post"><div class="row"><span class="grow"><?= h($x['name']) ?></span>
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="user-hapus" /><input type="hidden" name="nu" value="<?= h($x['name']) ?>" />
<button class="danger" onclick="return confirm('Cabut akses <?= h($x['name']) ?>?')">Cabut</button></div></form>
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
<h1 style="font-size:1.1rem">Log aktivitas (100 terakhir)</h1>
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
<h1 style="font-size:1.1rem"><?= $edit ? 'Ubah menu' : 'Tambah menu' ?></h1>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="save" />
<input type="hidden" name="i" value="<?= $edit['_i'] ?? -1 ?>" />
<label>Kategori<select name="cat"><?php foreach (RUETHIES_CATS as $k => $v): ?>
<option value="<?= $k ?>" <?= (($edit['cat'] ?? '') === $k) ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
<label>Nama (Indonesia)<input name="n" maxlength="60" required value="<?= h($edit['id']['n'] ?? '') ?>" /></label>
<label>Deskripsi (Indonesia, maks 160)<input name="d" maxlength="160" value="<?= h($edit['id']['d'] ?? '') ?>" /></label>
<label>Nama (Inggris, opsional)<input name="ne" maxlength="60" value="<?= h($edit['en']['n'] ?? '') ?>" /></label>
<label>Deskripsi (Inggris, opsional)<input name="de" maxlength="160" value="<?= h($edit['en']['d'] ?? '') ?>" /></label>
<label>Label (mis. Best Seller, kosongkan bila tidak ada)<input name="badge" maxlength="24" value="<?= h($edit['badge'] ?? '') ?>" /></label>
<label>Tag (mis. Manis • Lembut)<input name="tag" maxlength="40" value="<?= h($edit['tag']['id'] ?? '') ?>" /></label>
<label><input type="checkbox" name="signature" value="1" style="width:auto" <?= !empty($edit['signature']) ? 'checked' : '' ?> /> Tampilkan di Signature (maks 3)</label>
<label><input type="checkbox" name="available" value="1" style="width:auto" <?= !isset($edit) || !empty($edit['available']) ? 'checked' : '' ?> /> Tampilkan di web</label>
<label>Foto (JPG/PNG/WebP, maks 5 MB)<?= $edit && !empty($edit['img']) ? ' — kosongkan untuk pakai foto lama' : '' ?><input type="file" name="foto" accept="image/jpeg,image/png,image/webp" /></label>
<?php if ($edit && !empty($edit['img'])): ?><p><img src="../<?= h($edit['img']) ?>" alt="" style="width:120px;height:90px;object-fit:cover;border-radius:10px" /></p><?php endif; ?>
<button>Simpan</button> <a class="btn ghost" href="?">Batal</a>
</form>
</div>
<?php else: ?>
<div class="card"><a class="btn" href="?p=tambah">+ Tambah menu</a></div>
<?php foreach (RUETHIES_CATS as $ck => $cv):
  $items = menu_load(); ?>
<div class="card">
<h1 style="font-size:1.1rem"><?= h($cv) ?></h1>
<?php $ada = false; foreach ($items as $i => $m): if (($m['cat'] ?? '') !== $ck) continue; $ada = true; ?>
<div class="row <?= empty($m['available']) || $m['available'] === false ? 'off' : '' ?>">
<?php if (!empty($m['img'])): ?><img src="../<?= h($m['img']) ?>" alt="" loading="lazy" /><?php endif; ?>
<span class="grow"><b><?= h($m['id']['n'] ?? '-') ?></b><br /><small><?= h($m['id']['d'] ?? '') ?></small>
<?php if (empty($m['available']) || $m['available'] === false): ?> <span class="badge">SEMBUNYI</span><?php endif; ?>
<?php if (!empty($m['signature'])): ?> <span class="badge">SIGNATURE</span><?php endif; ?></span>
<form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="toggle" /><input type="hidden" name="i" value="<?= $i ?>" /><button class="ghost"><?= empty($m['available']) || $m['available'] === false ? 'Tampilkan' : 'Sembunyikan' ?></button></form>
<a class="btn ghost" href="?p=ubah&i=<?= $i ?>">Ubah</a>
<?php if (is_owner($me)): ?>
<form method="post" onsubmit="return confirm('Hapus permanen?')"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="hapus" /><input type="hidden" name="i" value="<?= $i ?>" /><button class="danger">Hapus</button></form>
<?php endif; ?>
</div>
<?php endforeach; if (!$ada): ?><p class="sub">Belum ada item.</p><?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
