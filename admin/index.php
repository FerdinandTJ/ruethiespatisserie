<?php
// Ruethies Patisserie — halaman admin (login + dashboard + pengaturan). Tanpa framework, tanpa layanan luar.
define('RUETHIES_ADMIN', true);
require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';
foreach ($_GET as $value) if (!is_string($value) || str_contains($value,"\0") || !mb_check_encoding($value,'UTF-8')) { http_response_code(400); die('Format parameter tidak valid.'); }
ruethies_session_start();
header('X-Robots-Tag: noindex, nofollow', true);

$err = ''; $ok = is_string($_GET['ok'] ?? null) ? $_GET['ok'] : '';
try { $me = current_user(); } catch (RuntimeException $e) { $me=null; http_response_code(503); $err='Penyimpanan keamanan belum siap. Hubungi pengelola.'; }

/* ---------- aksi POST ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
 try {
  foreach ($_POST as $value) if (!is_string($value) || str_contains($value,"\0") || !mb_check_encoding($value,'UTF-8')) throw new DomainException('Format isian tidak valid.');
  $aksi = security_input($_POST, 'aksi', 40);
  if ($aksi !== '' && !ruethies_csrf_ok()) { http_response_code(403); die('Aksi ditolak. Muat ulang formulir.'); }
  if (in_array($aksi,['pw','2fa-aktif','2fa-mati','user-tambah','user-hapus'],true)) {
    if (!$me) { http_response_code(403); die('Aksi ditolak.'); }
    security_require_reauth();
  }
  security_upload_input($_FILES);
  if ($me && in_array($aksi,['save','galeri-save'],true)) {
    foreach ($aksi==='save' ? ['foto','detail1','detail2'] : ['gfoto'] as $field) {
      if (!empty($_FILES[$field]['name']) || !empty($_POST[$field.'_b64'])) security_rate_take('upload',$me['name'],20,false);
    }
  }
  if ($me && ruethies_csrf_ok() && in_array($aksi, [
    'save', 'toggle', 'hapus', 'galeri-save', 'galeri-gerak', 'galeri-hapus',
    'faq-save', 'faq-gerak', 'faq-hapus',
  ], true)) content_lock();
  // POST kosong padahal ada isi terkirim = ukuran melewati batas server (post_max_size).
  // Terjadi SEBELUM kode kita jalan — beri pesan yang bisa ditindaklanjuti.
  if ($aksi === '' && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $err = 'Foto terlalu besar untuk server (batas ±8 MB). Kecilkan dulu (mis. screenshot/kompres di HP), lalu coba lagi.';
  } else
  if ($aksi === 'login') {
    $name=trim(security_input($_POST,'u',256));$pass=security_input($_POST,'p',1024);
    if (security_password_login($name,$pass)) {
      if (isset($_SESSION['pending'])) { header('Location: ?p=kode'); exit; }
      ruethies_log($_SESSION['uid'],'login','berhasil'); header('Location: ?'); exit;
    }
    ruethies_log($name!=='' ? $name : '(kosong)','login-gagal','');
    $err='Nama atau kata sandi salah.';
  }
  elseif ($aksi === 'kode') {
    $name=$_SESSION['pending']['name'] ?? '(tanpa challenge)';
    if (security_challenge_login(trim(security_input($_POST,'c',6)))) {
      ruethies_log($_SESSION['uid'],'login-2fa','berhasil');header('Location: ?');exit;
    }
    ruethies_log($name,'login-2fa-gagal','');$err='Kode salah atau sudah digunakan. Gunakan kode terbaru.';
  }
  elseif ($aksi === 'reauth') {
    if (!$me) { http_response_code(403);die('Aksi ditolak.'); }
    if (security_reauth($me,security_input($_POST,'current_password',1024),trim(security_input($_POST,'c',6)))) {
      header('Location: ?p=pengaturan&ok=verified');exit;
    }
    ruethies_log($me['name'],'verifikasi-gagal','');$err='Verifikasi gagal. Periksa kata sandi dan kode terbaru.';
  }
  elseif ($aksi === 'logout') {
    if ($me) ruethies_log($me['name'],'logout','');
    ruethies_logout();header('Location: ?p=login');exit;
  }

  // Semua aksi di bawah wajib login + CSRF.
  elseif (!$me || !ruethies_csrf_ok()) { http_response_code(403); die('Aksi ditolak.');
  }
  elseif ($aksi === 'toggle') {
    $items = menu_load(); $i = (int)($_POST['i'] ?? -1);
    if (isset($items[$i])) {
      $items[$i]['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
      $items[$i]['available'] = empty($items[$i]['available']) || $items[$i]['available'] === false ? true : false;
      if (menu_save($items)) { ruethies_log($me['name'], 'toggle', ($items[$i]['id']['n'] ?? '') . ' => ' . ($items[$i]['available'] ? 'tampil' : 'sembunyi')); header('Location: ?ok=tampil#item-' . $i); exit; }
      $err = 'Gagal menyimpan.';
    }
  }
  elseif ($aksi === 'save') {
    $items = menu_load(); $i = (int)($_POST['i'] ?? -1);
    $old = ($i >= 0 && isset($items[$i])) ? $items[$i] : null;
    $img = $old['img'] ?? '';
    $details = array_pad(array_slice($old['detail_images'] ?? [], 0, 2), 2, '');
    $created = [];
    $ulock = upload_lock();
    try {
      foreach (['foto','detail1','detail2'] as $slot => $field) {
        if ($slot > 0 && !empty($_POST[$field.'_remove'])) $details[$slot-1] = '';
        $path = null; $perr = null;
        if (!empty($_FILES[$field]['name'] ?? '')) {
          [$path, $perr] = photo_upload($_FILES[$field], ($_POST['n'] ?? 'menu').($slot ? '-detail-'.$slot : ''), $slot > 0);
        } elseif (!empty($_POST[$field.'_b64'] ?? '')) {
          [$raw, $perr] = b64_image($field.'_b64', 3 * 1024 * 1024);
          if (!$perr) [$path, $perr] = photo_upload_raw($raw, ($_POST['n'] ?? 'menu').($slot ? '-detail-'.$slot : ''), $slot > 0);
        }
        if ($perr) { $err = $perr; break; }
        if ($path) { $created[] = $path; if ($slot === 0) $img = $path; else $details[$slot-1] = $path; }
      }
    } finally { upload_unlock($ulock); }
    if ($err === '' && $img === '') $err = 'Foto utama wajib diunggah.';
    if ($err === '') {
      $row = menu_validate(array_merge(['detail'=>$old['id']['detail'] ?? '', 'detaile'=>$old['en']['detail'] ?? ''], $_POST, ['img' => $img, 'detail_images' => $details, 'available'=>!empty($_POST['available'])]));
      if (!$row) $err = 'Periksa isian: nama wajib, kategori valid.';
      else {
        if ($old) {
          $row = array_replace($old, $row); // Preserve IDs, stable slug and unknown future metadata.
          $row['id'] = array_replace($old['id'], $row['id']);
          $row['en'] = array_replace($old['en'] ?? [], $row['en']);
        }
        $row['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
        if ($old) $items[$i] = $row; else $items[] = $row;
        if (menu_save($items)) {
          foreach (array_merge([$old['img'] ?? ''], $old['detail_images'] ?? []) as $previous) menu_photo_delete_unused($previous);
          ruethies_log($me['name'], $old ? 'ubah' : 'tambah', $row['id']['n']); header('Location: ?ok=simpan'); exit;
        }
        $err = 'Gagal menyimpan.';
      }
    }
    foreach ($created as $unused) menu_photo_delete_unused($unused);
  }
  elseif ($aksi === 'hapus') {
    if (!is_owner($me)) { http_response_code(403); die('Khusus pemilik.'); }
    $items = menu_load(); $i = (int)($_POST['i'] ?? -1);
    if (isset($items[$i])) {
      $nm = $items[$i]['id']['n'] ?? '';
      $images = array_merge([(string)($items[$i]['img'] ?? '')], $items[$i]['detail_images'] ?? []);
      array_splice($items, $i, 1);
      if (menu_save($items)) {
        $results = array_map('menu_photo_delete_unused', $images);
        $photo = in_array('failed',$results,true) ? 'failed' : (in_array('kept',$results,true) ? 'kept' : 'deleted');
        ruethies_log($me['name'], 'hapus', $nm . ' | foto: ' . $photo);
        header('Location: ?ok=hapus&photo=' . $photo); exit;
      }
      $err = 'Gagal menghapus.';
    }
  }
  elseif ($aksi === 'pw') {
    security_change_password($me,security_input($_POST,'p1',1024),security_input($_POST,'p2',1024));
    ruethies_log($me['name'],'ganti-password','Semua sesi dicabut.');ruethies_logout();header('Location: ?p=login&ok=security');exit;
  }
  elseif ($aksi === '2fa-aktif') {
    if (!is_owner($me)) { http_response_code(403);die('Khusus pemilik.'); }
    security_rate_take('enrollment',$me['name']);
    $sec=security_input($_SESSION,'totp_baru',64);
    if ($sec==='')throw new DomainException('Sesi pengaturan OTP kedaluwarsa.');
    security_set_totp($me,$sec,trim(security_input($_POST,'c',6)));
    ruethies_log($me['name'],'2fa-aktif','Semua sesi dicabut.');ruethies_logout();header('Location: ?p=login&ok=security');exit;
  }
  elseif ($aksi === '2fa-mati') {
    security_disable_totp($me);ruethies_log($me['name'],'2fa-mati','Semua sesi dicabut.');ruethies_logout();header('Location: ?p=login&ok=security');exit;
  }
  elseif ($aksi === 'user-tambah') {
    security_add_staff($me,trim(security_input($_POST,'nu',80)),security_input($_POST,'np',1024));
    ruethies_log($me['name'],'user-tambah',security_input($_POST,'nu',80));header('Location: ?p=pengaturan&ok=user');exit;
  }
  elseif ($aksi === 'galeri-save') {
    $items = atmos_load(); $i = (int)($_POST['gi'] ?? -1);
    $old = ($i >= 0 && isset($items[$i])) ? $items[$i] : null;
    $img = $old['img'] ?? ''; $full = $old['imgFull'] ?? '';
    $ulock = upload_lock();
    if (!empty($_FILES['gfoto']['name'] ?? '')) {
      [$p1, $p2, $perr] = galeri_upload($_FILES['gfoto'], $_POST['cap'] ?? 'suasana');
      if ($perr) $err = $perr; else { $img = $p1; $full = $p2; }
    } elseif (!empty($_POST['gfoto_b64'] ?? '')) {
      [$braw, $berr] = b64_image('gfoto_b64', 3 * 1024 * 1024);
      if ($berr) $err = $berr;
      else { [$p1, $p2, $perr] = galeri_upload_raw($braw, $_POST['cap'] ?? 'suasana'); if ($perr) $err = $perr; else { $img = $p1; $full = $p2; } }
    }
    upload_unlock($ulock);
    if ($err === '') {
      $cap = trim(strip_tags($_POST['cap'] ?? '')); $cape = trim(strip_tags($_POST['cape'] ?? ''));
      if ($cap === '' || mb_strlen($cap) > 80 || mb_strlen($cape) > 80) $err = 'Caption wajib diisi (maks 80).';
      elseif ($img === '') $err = 'Foto wajib diunggah untuk item baru.';
      else {
        $row = ['img' => $img, 'imgFull' => $full !== '' ? $full : $img,
          'id' => ['cap' => $cap], 'en' => ['cap' => $cape !== '' ? $cape : $cap]];
        if ($old) {
          $row = array_replace($old, $row); // Preserve IDs, stable slug and unknown future metadata.
          $row['id'] = array_replace($old['id'], $row['id']);
          $row['en'] = array_replace($old['en'] ?? [], $row['en']);
        }
        $row['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
        if ($old) $items[$i] = $row; else $items[] = $row;
        if (atmos_save($items)) { ruethies_log($me['name'], $old ? 'galeri-ubah' : 'galeri-tambah', $cap); header('Location: ?p=galeri&ok=simpan#galeri'); exit; }
        $err = 'Gagal menyimpan.';
      }
    }
  }
  elseif ($aksi === 'galeri-gerak') {
    $items = atmos_load(); $i = (int)($_POST['gi'] ?? -1); $d = (int)($_POST['dir'] ?? 0);
    $j = $i + ($d > 0 ? 1 : -1);
    if (isset($items[$i], $items[$j])) {
      [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
      if (atmos_save($items)) { ruethies_log($me['name'], 'galeri-urut', (string)$i); header('Location: ?p=galeri&ok=simpan#gitem-' . $j); exit; }
      $err = 'Gagal menyimpan.';
    }
  }
  elseif ($aksi === 'galeri-hapus') {
    if (!is_owner($me)) { http_response_code(403); die('Khusus pemilik.'); }
    $items = atmos_load(); $i = (int)($_POST['gi'] ?? -1);
    if (isset($items[$i])) {
      $nm = $items[$i]['id']['cap'] ?? '';
      array_splice($items, $i, 1);
      if (atmos_save($items)) { ruethies_log($me['name'], 'galeri-hapus', $nm); header('Location: ?p=galeri&ok=hapus#galeri'); exit; }
      $err = 'Gagal menghapus.';
    }
  }
  elseif ($aksi === 'faq-save') {
    $items = faq_load(); $i = (int)($_POST['fi'] ?? -1);
    $old = ($i >= 0 && isset($items[$i])) ? $items[$i] : null;
    $row = faq_validate($_POST);
    if (!$row) $err = 'Periksa isian: pertanyaan wajib (maks 120), jawaban wajib (maks 400). Tautan format [teks](https://...).';
    else {
      if ($old) $items[$i] = $row; else $items[] = $row;
      [$okf, $msg] = faq_apply($items);
      if ($okf) { ruethies_log($me['name'], $old ? 'faq-ubah' : 'faq-tambah', $row['q']); header('Location: ?p=faq&ok=simpan#faqman'); exit; }
      $err = $msg;
    }
  }
  elseif ($aksi === 'faq-gerak') {
    $items = faq_load(); $i = (int)($_POST['fi'] ?? -1); $d = (int)($_POST['dir'] ?? 0);
    $j = $i + ($d > 0 ? 1 : -1);
    if (isset($items[$i], $items[$j])) {
      [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
      [$okf, $msg] = faq_apply($items);
      if ($okf) { ruethies_log($me['name'], 'faq-urut', (string)$i); header('Location: ?p=faq&ok=simpan#faqman'); exit; }
      $err = $msg;
    }
  }
  elseif ($aksi === 'faq-hapus') {
    if (!is_owner($me)) { http_response_code(403); die('Khusus pemilik.'); }
    $items = faq_load(); $i = (int)($_POST['fi'] ?? -1);
    if (isset($items[$i])) {
      if (count($items) <= 1) $err = 'Minimal 1 FAQ harus ada.';
      else {
        $nm = $items[$i]['q'] ?? '';
        array_splice($items, $i, 1);
        [$okf, $msg] = faq_apply($items);
        if ($okf) { ruethies_log($me['name'], 'faq-hapus', $nm); header('Location: ?p=faq&ok=hapus#faqman'); exit; }
        $err = $msg;
      }
    }
  }
  elseif ($aksi === 'user-hapus') {
    security_remove_staff($me,security_input($_POST,'nu',256),security_input($_POST,'target_version',64));
    ruethies_log($me['name'],'user-hapus',security_input($_POST,'nu',256));header('Location: ?p=pengaturan&ok=user');exit;
  }
  $me=current_user();
 } catch (RuethiesReauthRequired $e) { header('Location: ?p=verifikasi',true,303);exit; }
 catch (RuethiesRateLimit $e) { http_response_code(429);header('Retry-After: 900');$err=$e->getMessage(); }
 catch (DomainException $e) { $err=$e->getMessage(); }
 catch (RuntimeException $e) { http_response_code(503);$err='Penyimpanan keamanan belum siap atau sedang sibuk. Hubungi pengelola.';error_log('Ruethies admin: security storage unavailable'); }
}

/* ---------- tampilan ---------- */
$p = is_string($_GET['p'] ?? null) ? $_GET['p'] : '';
if (!$me && $p !== 'kode') $p = 'login';
if (!$me && $p === 'kode' && empty($_SESSION['pending'])) $p = 'login';
$area = str_starts_with($p, 'galeri') ? 'galeri' : (str_starts_with($p, 'faq') ? 'faq' : (in_array($p,['pengaturan','verifikasi'],true) ? 'pengaturan' : 'menu'));
$labels = ['menu'=>'Kelola Menu', 'galeri'=>'Galeri Suasana', 'faq'=>'Tanya-Jawab', 'pengaturan'=>'Pengaturan'];
$intro = ['menu'=>'Atur sajian, foto, dan ketersediaan menu.', 'galeri'=>'Susun foto yang menyambut pengunjung website.', 'faq'=>'Bantu pengunjung menemukan jawaban dengan mudah.', 'pengaturan'=>'Kelola keamanan akun dan akses tim.'];
// Baca setiap sumber sekali untuk tampilan. Indeks asli dipertahankan saat memfilter.
try { $_menu = $me ? menu_load() : []; } catch (RuntimeException $e) { http_response_code(503);$_menu=[];$err='Data menu tidak dapat dibaca. Pulihkan file menu sebelum menyimpan perubahan.'; }
$_gallery = $me ? atmos_load() : [];
$_faqs = $me ? faq_load() : [];
$_logs=[];
if ($p==='pengaturan' && is_owner($me)) {
  try { $_logs=ruethies_logs(); } catch (RuntimeException $e) { http_response_code(503); $err='Log aktivitas belum dapat dibaca. Coba lagi.'; }
}
$q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']),0,80) : '';
$category = is_string($_GET['cat'] ?? null) && isset(RUETHIES_CATS[$_GET['cat']]) ? $_GET['cat'] : '';
$availability = in_array($_GET['show'] ?? '', ['visible','hidden'], true) ? $_GET['show'] : '';
$filtered = array_filter($_menu, static function ($m) use ($q,$category,$availability) {
  $visible = ($m['available'] ?? true) !== false;
  return (!$category || ($m['cat'] ?? '') === $category)
    && (!$availability || ($availability === 'visible' ? $visible : !$visible))
    && ($q === '' || mb_stripos(($m['id']['n'] ?? '').' '.($m['id']['d'] ?? '').' '.($m['en']['n'] ?? ''),$q) !== false);
});

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="robots" content="noindex, nofollow" />
<title><?= h($me ? $labels[$area] : 'Masuk') ?> — Ruethies Admin</title>
<!-- ADMIN_BUILD=2026-11-05-ui -->
<script src="admin.js?v=20261109-upload-csp" defer></script>
<link rel="stylesheet" href="admin.css?v=20261109-details" />
</head>
<body>
<a class="skip-admin" href="#admin-main">Langsung ke konten</a>
<?php $isGate = ($p === 'login' || $p === 'kode'); ?>
<?php if ($isGate): ?>
<div class="gate" id="admin-main"><div class="gatecard">
<div class="gateseal">N°51</div>
<p class="gateno">RUETHIES PATISSERIE · ADMIN</p>
<?php else: ?>
<div class="shell">
<aside class="side" aria-label="Navigasi admin">
<div class="mark"><span class="sealmini">N°51</span><div><b>Ruethies Admin</b><span>PATISSERIE · CIKARANG</span></div></div>
<?php $_nm = count($_menu); $_ng = count($_gallery); ?>
<nav class="nav">
<p class="navcap">Kelola</p>
<a href="?" class="<?= $area === 'menu' ? 'on' : '' ?>" aria-current="<?= $area === 'menu' ? 'page' : 'false' ?>"><?= icon('menu') ?> Menu <span class="cnt"><?= $_nm ?></span></a>
<a href="?p=galeri#galeri" class="<?= $area === 'galeri' ? 'on' : '' ?>" aria-current="<?= $area === 'galeri' ? 'page' : 'false' ?>"><?= icon('img') ?> Galeri <span class="cnt"><?= $_ng ?></span></a>
<a href="?p=faq#faqman" class="<?= $area === 'faq' ? 'on' : '' ?>" aria-current="<?= $area === 'faq' ? 'page' : 'false' ?>"><?= icon('check') ?> FAQ <span class="cnt"><?= count($_faqs) ?></span></a>
<?php if (is_owner($me)): ?><a href="?p=pengaturan" class="<?= $p === 'pengaturan' ? 'on' : '' ?>" aria-current="<?= $area === 'pengaturan' ? 'page' : 'false' ?>"><?= icon('gear') ?> Pengaturan</a><?php endif; ?>
<p class="navcap">Lainnya</p>
<a href="../" target="_blank" rel="noopener"><?= icon('web') ?> Lihat web</a>
</nav>
<div class="who"><div class="who-id"><?= icon('user', 16) ?><div><b><?= h($me['name']) ?></b><small><?= is_owner($me) ? 'Pemilik' : 'Staf' ?></small></div></div>
<form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>"><input type="hidden" name="aksi" value="logout" /><button class="ghost w100 outbtn"><?= icon('out', 16) ?> Keluar</button></form>
</div>
<p class="sidefoot">Perubahan langsung tampil di web.<br />Ruethies Patisserie</p>
</aside>
<div class="maincol">
<header class="topbar"><span class="sealmini">N°51</span><b>Ruethies Admin</b><span class="sp"></span><a class="ticon" href="../" target="_blank" rel="noopener" aria-label="Lihat website"><?= icon('web',18) ?></a><form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>"><input type="hidden" name="aksi" value="logout"><button class="ticon" aria-label="Keluar dari admin"><?= icon('out',18) ?></button></form></header>
<main class="main" id="admin-main">
<div class="mnav">
<a href="?" class="<?= $area === 'menu' ? 'on' : '' ?>" aria-current="<?= $area === 'menu' ? 'page' : 'false' ?>"><?= icon('menu', 16) ?> Menu</a>
<a href="?p=galeri#galeri" class="<?= $area === 'galeri' ? 'on' : '' ?>" aria-current="<?= $area === 'galeri' ? 'page' : 'false' ?>"><?= icon('img', 16) ?> Galeri</a>
<a href="?p=faq#faqman" class="<?= $area === 'faq' ? 'on' : '' ?>" aria-current="<?= $area === 'faq' ? 'page' : 'false' ?>"><?= icon('check', 16) ?> FAQ</a>
<?php if (is_owner($me)): ?><a href="?p=pengaturan" class="<?= $p === 'pengaturan' ? 'on' : '' ?>" aria-current="<?= $area === 'pengaturan' ? 'page' : 'false' ?>"><?= icon('gear', 16) ?> Pengaturan</a><?php endif; ?>
</div>
<?php endif; ?>
<?php if ($err): ?><p class="err" role="alert"><?= h($err) ?></p><?php endif; ?>
<?php if ($ok === 'security'): ?><p class="okmsg" role="status">Keamanan akun diperbarui. Semua sesi sudah dicabut. Silakan masuk kembali.</p><?php endif; ?>
<?php if ($ok === 'verified'): ?><p class="okmsg" role="status">Verifikasi berhasil. Tindakan keamanan tersedia selama 5 menit.</p><?php endif; ?>
<?php if ($ok === 'simpan'): ?><p class="okmsg" role="status">Tersimpan dan langsung tampil di web.</p><?php endif; ?>
<?php if ($ok === 'tampil'): ?><p class="okmsg" role="status">Status tampil/sembunyi diperbarui.</p><?php endif; ?>
<?php if ($ok === 'hapus'): ?><p class="okmsg" role="status">Item dihapus permanen.</p><?php endif; ?>
<?php if ($ok === 'hapus' && ($_GET['photo'] ?? '') === 'deleted'): ?><p class="okmsg" role="status">Foto menu JPG/WebP sudah dibersihkan (atau sebelumnya sudah tidak ada).</p><?php endif; ?>
<?php if ($ok === 'hapus' && ($_GET['photo'] ?? '') === 'kept'): ?><p class="okmsg" role="status">Foto dipertahankan karena masih digunakan atau berada di luar folder upload menu yang diizinkan.</p><?php endif; ?>
<?php if ($ok === 'hapus' && ($_GET['photo'] ?? '') === 'failed'): ?><p class="warn">Menu sudah dihapus, tetapi pembersihan foto belum tuntas. Periksa referensi dan izin file melalui File Manager; jangan hapus foto yang masih digunakan.</p><?php endif; ?>
<?php if ($ok === 'pw'): ?><p class="okmsg" role="status">Kata sandi diganti.</p><?php endif; ?>
<?php if ($ok === '2fa'): ?><p class="okmsg" role="status">Kode OTP aktif. Setiap login pemilik wajib memasukkan kode.</p><?php endif; ?>
<?php if ($ok === '2fa-mati'): ?><p class="okmsg" role="status">Kode OTP dimatikan. Tidak disarankan.</p><?php endif; ?>
<?php if ($ok === 'user'): ?><p class="okmsg" role="status">Data staf diperbarui.</p><?php endif; ?>

<?php if ($p === 'login'): ?>
<div class="card">
<h2><?= icon('lock') ?> Selamat datang</h2>
<p class="sub">Masuk untuk mengelola menu dan galeri.</p>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>"><input type="hidden" name="aksi" value="login" />
<label>Nama pengguna<input name="u" required autocomplete="username" /></label>
<label>Kata sandi
<span class="pwrow"><input id="pw-login" name="p" type="password" required autocomplete="current-password" /><button type="button" class="ghost" data-pw-toggle="pw-login">Lihat</button></span></label>
<button class="w100">Masuk</button>
</form>
</div>
<?php elseif ($p === 'kode'): ?>
<div class="card">
<h2><?= icon("shield") ?> Kode keamanan</h2>
<p class="sub"><b>Pemilik:</b> buka aplikasi Authenticator di HP, masukkan 6 digit kode yang tampil.</p>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>"><input type="hidden" name="aksi" value="kode" />
<label>Kode OTP<input name="c" class="otp" inputmode="numeric" maxlength="6" required autocomplete="one-time-code" /></label>
<button class="w100">Verifikasi</button>
</form>
</div>
<?php else: ?>
<div class="pagehead"><div><p class="eyebrow">RUETHIES · KONTEN WEBSITE</p><h1><?= h($labels[$area]) ?></h1><p class="sub"><?= h($intro[$area]) ?></p></div><span class="account-chip"><?= icon('user',16) ?> <?= h($me['name']) ?> · <?= is_owner($me) ? 'Pemilik' : 'Staf' ?></span></div>
<?php if (in_array($p,['tambah','ubah','galeri-tambah','galeri-ubah','faq-tambah','faq-ubah'],true)): ?><a class="backlink" href="<?= $area === 'menu' ? '?' : '?p='.$area ?>"><?= icon('back',16) ?> Kembali ke <?= h($labels[$area]) ?></a><?php endif; ?>
<?php if (is_owner($me) && is_file(__DIR__ . '/setup.php')): ?><p class="warn"><b>Satu langkah terakhir:</b> buka File Manager hosting, hapus file <span class="mono">admin/setup.php</span>, lalu muat ulang halaman ini. Peringatan ini hilang sendiri setelah file dihapus.</p><?php endif; ?>
<?php if ($area === 'menu' && !in_array($p,['tambah','ubah'],true)):
  $_vis = array_filter($_menu, fn($m) => ($m['available'] ?? true) !== false);
  $_sig = array_filter($_vis, fn($m) => !empty($m['signature'])); ?>
<div class="stats" aria-label="Ringkasan menu">
<div class="stat"><span class="ic"><?= icon('menu',20) ?></span><div><b><?= count($_menu) ?></b><span>Total menu</span></div></div>
<div class="stat"><span class="ic"><?= icon('eye',20) ?></span><div><b><?= count($_vis) ?></b><span>Tampil di web</span></div></div>
<div class="stat"><span class="ic"><?= icon('eyeoff',20) ?></span><div><b><?= count($_menu)-count($_vis) ?></b><span>Disembunyikan</span></div></div>
<div class="stat"><span class="ic"><?= icon('star',20) ?></span><div><b><?= count($_sig) ?></b><span>Signature</span></div></div>
</div>
<div class="card menu-toolbar"><form method="get" class="menu-filter" role="search" aria-label="Cari dan filter menu">
<label>Cari menu<input type="search" name="q" maxlength="80" placeholder="Nama atau deskripsi menu…" value="<?= h($q) ?>"></label>
<label>Kategori<select name="cat"><option value="">Semua kategori</option><?php foreach (RUETHIES_CATS as $key=>$label): ?><option value="<?= h($key) ?>" <?= $category === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
<label>Status<select name="show"><option value="">Semua status</option><option value="visible" <?= $availability === 'visible' ? 'selected' : '' ?>>Tampil</option><option value="hidden" <?= $availability === 'hidden' ? 'selected' : '' ?>>Disembunyikan</option></select></label>
<button type="submit">Terapkan</button></form>
<div class="list-caption"><span><?= count($filtered) ?> dari <?= count($_menu) ?> menu</span><?php if ($q !== '' || $category || $availability): ?><a href="?">Reset filter</a><?php endif; ?><a class="btn" href="?p=tambah#formulir"><?= icon('plus',16) ?> Tambah menu</a></div></div>
<?php if (!$filtered): ?><div class="empty"><b><?= $_menu ? 'Tidak ada menu yang cocok.' : 'Belum ada menu.' ?></b><p><?= $_menu ? 'Coba kata kunci lain atau reset filter.' : 'Tambahkan sajian pertama untuk ditampilkan di website.' ?></p></div><?php endif; ?>
<?php endif; ?>

<?php if ($p === 'verifikasi'): ?>
<div class="card editor"><h2><?= icon('shield') ?> Verifikasi keamanan</h2><p class="sub">Konfirmasi identitas Anda untuk mengubah pengaturan keamanan. Verifikasi berlaku 5 menit.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>"><input type="hidden" name="aksi" value="reauth">
<label>Kata sandi saat ini<input name="current_password" type="password" autocomplete="current-password" required></label>
<?php if (is_owner($me) && !empty($me['totp'])): ?><label>Kode terbaru dari Authenticator<input name="c" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label><?php endif; ?>
<button>Verifikasi</button> <a class="linkbtn" href="?p=pengaturan">Kembali</a></form></div>
<?php endif; ?>
<?php if ($p === 'pengaturan' && is_owner($me)):
  $fresh=is_int($_SESSION['reauth_at'] ?? null) && $_SESSION['reauth_at']<=time() && time()-$_SESSION['reauth_at']<300; ?>
<?php if (!$fresh): ?><p class="warn">Untuk mengubah keamanan atau akun staf, lakukan verifikasi ulang terlebih dahulu.</p><a class="btn" href="?p=verifikasi">Verifikasi keamanan</a><?php endif; ?>
<fieldset class="security-fields" <?= $fresh ? '' : 'disabled' ?>>
<div class="card">
<h2><?= icon("shield") ?> Kode OTP pemilik</h2>
<?php if (!empty($me['totp'])): ?>
<p class="sub">Status: <b>aktif</b>. Setiap login wajib kode 6 digit.</p>
<form method="post" data-confirm="Matikan OTP? Tidak disarankan."><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="2fa-mati" /><button class="danger">Matikan OTP</button></form>
<?php else:
  if (empty($_SESSION['totp_baru'])) $_SESSION['totp_baru'] = b32enc(random_bytes(20));
  $sec = $_SESSION['totp_baru'];
  $uri = 'otpauth://totp/Ruethies:' . rawurlencode($me['name']) . '?secret=' . $sec . '&issuer=Ruethies&digits=6&period=30';
?>
<p class="sub">1. Buka aplikasi Authenticator di HP (Google/Microsoft/Apple) → tambah akun manual.<br />2. Ketik kode rahasia di bawah ini:</p>
<p class="mono secret"><?= h(chunk_split($sec, 4, ' ')) ?></p>
<details><summary>Butuh cara lain? (opsional)</summary><p class="sub">Tempel tautan ini di aplikasi yang mendukung impor URI:</p><p class="mono"><?= h($uri) ?></p></details>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="2fa-aktif" />
<label>Kode 6 digit dari aplikasi<input name="c" inputmode="numeric" maxlength="6" required /></label>
<button>Aktifkan OTP</button>
</form>
<?php endif; ?>
</div>
<div class="card">
<h2><?= icon("lock") ?> Ganti kata sandi</h2>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="pw" />
<label>Sandi baru (min. 12 karakter)<input name="p1" type="password" required /></label>
<label>Ulangi sandi<input name="p2" type="password" required /></label>
<button>Simpan sandi</button>
</form>
</div>
<div class="card">
<h2><?= icon("user") ?> Akun staf</h2>
<?php foreach (users_all() as $x): if (($x['role'] ?? '') !== 'staff') continue; ?>
<form method="post" data-confirm="Cabut akses staf ini?"><div class="item"><span class="grow"><?= h($x['name']) ?></span>
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="user-hapus" /><input type="hidden" name="nu" value="<?= h($x['name']) ?>" /><input type="hidden" name="target_version" value="<?= h($x['auth_version'] ?? '') ?>">
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
</fieldset>
<div class="card">
<h2><?= icon("clock") ?> Log aktivitas</h2>
<div class="table-scroll" tabindex="0" role="region" aria-label="Log aktivitas"><table><tr><th>Waktu</th><th>Oleh</th><th>Aksi</th><th>Detail</th></tr><?php foreach ($_logs as $l): ?>
<tr><td><?= h(date('d M Y, H:i', strtotime($l['t']) ?: time())) ?></td><td><?= h($l['oleh']) ?></td><td><?= h($l['aksi']) ?></td><td><?= h($l['detail']) ?></td></tr>
<?php endforeach; ?></table></div>
</div>
<?php endif; ?>

<?php if ($area === 'menu'): ?>
<?php
$edit = null;
if (($p === 'ubah' || $p === 'tambah')) {
  $items0 = $_menu; $ei = (int)($_GET['i'] ?? -1);
  if ($p === 'ubah' && isset($items0[$ei])) $edit = $items0[$ei] + ['_i' => $ei];
}
if ($p === 'tambah' || $edit):
?>
<div class="card editor" id="formulir">
<h2><?= icon("edit") ?> <?= $edit ? "Ubah menu" : "Tambah menu" ?></h2>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="save" />
<input type="hidden" name="i" value="<?= $edit['_i'] ?? -1 ?>" />
<label>Kategori<select name="cat"><?php foreach (RUETHIES_CATS as $k => $v): ?>
<option value="<?= $k ?>" <?= (($edit['cat'] ?? '') === $k) ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
<label>Nama (Indonesia)<span class="count" id="c-n"></span><input name="n" maxlength="60" required data-count="c-n" value="<?= h($edit['id']['n'] ?? '') ?>" /></label>
<label>Deskripsi (Indonesia)<span class="count" id="c-d"></span><input name="d" maxlength="160" data-count="c-d" value="<?= h($edit['id']['d'] ?? '') ?>" /></label>
<label>Deskripsi detail (opsional)<small class="hint">Informasi produk yang sudah pasti, bukan harga atau ketentuan yang belum dikonfirmasi. Tampil pada halaman detail.</small><textarea name="detail" maxlength="4000" rows="5"><?= h($edit['id']['detail'] ?? '') ?></textarea></label>
<details class="optional-fields"><summary>Terjemahan Inggris <span>Opsional</span></summary><label>Nama (Inggris, opsional)<span class="count" id="c-ne"></span><input name="ne" maxlength="60" data-count="c-ne" value="<?= h($edit['en']['n'] ?? '') ?>" /></label>
<label>Deskripsi (Inggris, opsional)<span class="count" id="c-de"></span><input name="de" maxlength="160" data-count="c-de" value="<?= h($edit['en']['d'] ?? '') ?>" /></label>
<label>Deskripsi detail Inggris (opsional)<textarea name="detaile" maxlength="4000" rows="5"><?= h($edit['en']['detail'] ?? '') ?></textarea></label>
</details>
<label>Label <small class="hint">(mis. Best Seller — kosongkan bila tidak ada)</small><input name="badge" maxlength="24" value="<?= h($edit['badge'] ?? '') ?>" /></label>
<label>Tag <small class="hint">(mis. Manis • Lembut)</small><input name="tag" maxlength="40" value="<?= h($edit['tag']['id'] ?? '') ?>" /></label>
<label><input type="checkbox" name="signature" value="1" <?= !empty($edit['signature']) ? 'checked' : '' ?> /> Tampilkan di Signature <small class="hint">(maks 3)</small></label>
<label><input type="checkbox" name="available" value="1" <?= !isset($edit) || !empty($edit['available']) ? 'checked' : '' ?> /> Tampilkan di web</label>
<label>Foto display utama <small class="hint">(JPG/PNG/WebP, maks 5 MB<?= $edit && !empty($edit['img']) ? ' — kosongkan untuk pakai foto lama' : '' ?>)</small><input type="file" name="foto" accept="image/jpeg,image/png,image/webp" data-preview="prev-foto" /></label>
<div class="preview" id="prev-foto"><?php if ($edit && !empty($edit['img'])): ?><img class="thumb-sm" src="../<?= h($edit['img']) ?>" alt="Foto saat ini" /><?php endif; ?></div>
<fieldset class="photo-details"><legend>Foto detail (opsional, maksimal 2)</legend>
<p class="sub">Ditampilkan saat pengunjung mengeklik foto menu. Kosongkan untuk mempertahankan foto lama. Foto pengganti akan menggantikan slot yang sama.</p>
<?php for ($slot=1; $slot<=2; $slot++): $detail=$edit['detail_images'][$slot-1] ?? ''; ?>
<label>Foto detail <?= $slot ?> <small class="hint">(JPG/PNG/WebP, maks 5 MB)</small><input type="file" name="detail<?= $slot ?>" accept="image/jpeg,image/png,image/webp" data-preview="prev-detail<?= $slot ?>"></label>
<div class="preview" id="prev-detail<?= $slot ?>"><?php if ($detail): ?><img class="thumb-sm" src="../<?= h($detail) ?>" alt="Foto detail <?= $slot ?> saat ini"><?php endif; ?></div>
<?php if ($detail): ?><label><input type="checkbox" name="detail<?= $slot ?>_remove" value="1"> Hapus foto detail <?= $slot ?></label><?php endif; ?>
<?php endfor; ?>
</fieldset>
<div class="stickybar"><button><?= icon("save", 18) ?> Simpan menu</button></div>
<p class="center"><a class="linkbtn" href="?"><?= icon("back", 15) ?> Kembali tanpa menyimpan</a></p>
</form>
</div>
<?php else: ?>
<?php foreach (RUETHIES_CATS as $ck => $cv):
  $items = $filtered; if (!array_filter($items, fn($m)=>($m['cat'] ?? '') === $ck)) continue; ?>
<div class="card">
<h2><?= icon("tag") ?> <?= h($cv) ?></h2>
<?php $ada = false; foreach ($items as $i => $m): if (($m['cat'] ?? '') !== $ck) continue; $ada = true; ?>
<div class="item <?= empty($m['available']) || $m['available'] === false ? 'off' : '' ?>" id="item-<?= $i ?>">
<?php if (!empty($m['img'])): ?><img class="thumb" src="../<?= h($m['img']) ?>" alt="" loading="lazy" /><?php endif; ?>
<span class="grow"><b><?= h($m['id']['n'] ?? '-') ?></b><br /><small><?= h($m['id']['d'] ?? '') ?></small>
<?php if (empty($m['available']) || $m['available'] === false): ?> <span class="badge hide">SEMBUNYI</span><?php endif; ?>
<?php if (!empty($m['signature'])): ?> <span class="badge"><?= icon("star", 12) ?> SIGNATURE</span><?php endif; ?></span>
<div class="actions">
<form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="toggle" /><input type="hidden" name="i" value="<?= $i ?>" /><button class="ghost"><?= empty($m["available"]) || $m["available"] === false ? icon("eye", 16) . " Tampilkan" : icon("eyeoff", 16) . " Sembunyikan" ?></button></form>
<a class="btn ghost" href="?p=ubah&i=<?= $i ?>#formulir"><?= icon("edit", 16) ?> Ubah</a>
<?php if (is_owner($me)): ?>
<form method="post" data-confirm="Hapus menu permanen? Foto JPG/WebP yang tidak digunakan lagi juga dihapus. Backup JSON tidak menyimpan foto."><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="hapus" /><input type="hidden" name="i" value="<?= $i ?>" /><button class="danger"><?= icon("trash", 16) ?> Hapus</button></form>
<?php endif; ?>
</div>
</div>
<?php endforeach; if (!$ada): ?><p class="sub">Belum ada item.</p><?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
<nav class="section-shortcuts" aria-label="Konten lainnya"><a id="galeri" href="?p=galeri">Kelola galeri →</a><a id="faqman" href="?p=faq">Kelola FAQ →</a></nav>
<?php endif; ?>
<?php if ($area === 'galeri'): ?>
<?php if ($p === 'galeri'): ?>
<div class="card" id="galeri">
<h2><?= icon("img") ?> Galeri Suasana</h2>
<p class="sub">Foto tampil berurutan di web dan langsung tayang.</p>
<?php $gal = $_gallery; foreach ($gal as $gi => $g): ?>
<div class="item" id="gitem-<?= $gi ?>">
<?php if (!empty($g['img'])): ?><img class="thumb" src="../<?= h($g['img']) ?>" alt="" loading="lazy" /><?php endif; ?>
<span class="grow"><b><?= h($g['id']['cap'] ?? '-') ?></b><br /><small><?= h($g['en']['cap'] ?? '') ?></small></span>
<div class="actions">
<form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="galeri-gerak" /><input type="hidden" name="gi" value="<?= $gi ?>" /><input type="hidden" name="dir" value="-1" /><button class="ghost iconbtn" aria-label="Geser ke atas" <?= $gi === 0 ? 'disabled' : '' ?>><?= icon("up", 16) ?></button></form>
<form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="galeri-gerak" /><input type="hidden" name="gi" value="<?= $gi ?>" /><input type="hidden" name="dir" value="1" /><button class="ghost iconbtn" aria-label="Geser ke bawah" <?= $gi === count($gal) - 1 ? 'disabled' : '' ?>><?= icon("down", 16) ?></button></form>
<a class="btn ghost" href="?p=galeri-ubah&gi=<?= $gi ?>#formulir-g"><?= icon("edit", 16) ?> Ubah</a>
<?php if (is_owner($me)): ?>
<form method="post" data-confirm="Hapus foto ini dari galeri?"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="galeri-hapus" /><input type="hidden" name="gi" value="<?= $gi ?>" /><button class="danger iconbtn" aria-label="Hapus foto"><?= icon("trash", 16) ?></button></form>
<?php endif; ?>
</div>
</div>
<?php endforeach; if (!$gal): ?><p class="sub">Belum ada foto.</p><?php endif; ?>
<a class="btn" href="?p=galeri-tambah#formulir-g"><?= icon("plus", 16) ?> Tambah foto</a>
</div>
<?php endif; ?>
<?php
$gedit = null;
if (($p === 'galeri-ubah' || $p === 'galeri-tambah')) {
  $g0 = $_gallery; $gei = (int)($_GET['gi'] ?? -1);
  if ($p === 'galeri-ubah' && isset($g0[$gei])) $gedit = $g0[$gei] + ['_i' => $gei];
}
if ($p === 'galeri-tambah' || $gedit):
?>
<div class="card editor" id="formulir-g">
<h2><?= icon("img") ?> <?= $gedit ? "Ubah foto" : "Tambah foto" ?></h2>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="galeri-save" />
<input type="hidden" name="gi" value="<?= $gedit['_i'] ?? -1 ?>" />
<label>Caption (Indonesia)<span class="count" id="c-cap"></span><input name="cap" maxlength="80" required data-count="c-cap" value="<?= h($gedit['id']['cap'] ?? '') ?>" /></label>
<label>Caption (Inggris, opsional)<span class="count" id="c-cape"></span><input name="cape" maxlength="80" data-count="c-cape" value="<?= h($gedit['en']['cap'] ?? '') ?>" /></label>
<label>Foto <small class="hint">(JPG/PNG/WebP, maks 8 MB<?= $gedit && !empty($gedit['img']) ? ' — kosongkan untuk pakai foto lama' : '' ?>)</small><input type="file" name="gfoto" accept="image/jpeg,image/png,image/webp" data-preview="prev-gfoto" /></label>
<div class="preview" id="prev-gfoto"><?php if ($gedit && !empty($gedit['img'])): ?><img class="thumb-sm" src="../<?= h($gedit['img']) ?>" alt="Foto saat ini" /><?php endif; ?></div>
<div class="stickybar"><button><?= icon("save", 18) ?> Simpan foto</button></div>
<p class="center"><a class="linkbtn" href="?p=galeri#galeri"><?= icon("back", 15) ?> Kembali tanpa menyimpan</a></p>
</form>
</div>
<?php endif; ?>
<?php endif; ?>
<?php if ($area === 'faq'): ?>
<?php if ($p === 'faq'): ?>
<div class="card" id="faqman">
<h2><?= icon("check") ?> Tanya-Jawab (FAQ)</h2>
<p class="sub">Tampil di web sebagai daftar buka-tutup + dibaca Google & AI. Tautan format <span class="mono">[teks](https://...)</span>.</p>
<?php $faqs = $_faqs; foreach ($faqs as $fi => $f): ?>
<div class="item" id="fitem-<?= $fi ?>">
<span class="grow"><b><?= h($f['q']) ?></b><br /><small><?= h(mb_strimwidth(faq_plain($f['a']), 0, 90, '…')) ?></small></span>
<div class="actions">
<form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="faq-gerak" /><input type="hidden" name="fi" value="<?= $fi ?>" /><input type="hidden" name="dir" value="-1" /><button class="ghost iconbtn" aria-label="Geser ke atas" <?= $fi === 0 ? 'disabled' : '' ?>><?= icon("up", 16) ?></button></form>
<form method="post"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="faq-gerak" /><input type="hidden" name="fi" value="<?= $fi ?>" /><input type="hidden" name="dir" value="1" /><button class="ghost iconbtn" aria-label="Geser ke bawah" <?= $fi === count($faqs) - 1 ? 'disabled' : '' ?>><?= icon("down", 16) ?></button></form>
<a class="btn ghost" href="?p=faq-ubah&fi=<?= $fi ?>#formulir-f"><?= icon("edit", 16) ?> Ubah</a>
<?php if (is_owner($me)): ?>
<form method="post" data-confirm="Hapus pertanyaan ini?"><input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="faq-hapus" /><input type="hidden" name="fi" value="<?= $fi ?>" /><button class="danger iconbtn" aria-label="Hapus FAQ"><?= icon("trash", 16) ?></button></form>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<a class="btn" href="?p=faq-tambah#formulir-f"><?= icon("plus", 16) ?> Tambah pertanyaan</a>
</div>
<?php endif; ?>
<?php
$fedit = null;
if (($p === 'faq-ubah' || $p === 'faq-tambah')) {
  $f0 = $_faqs; $fei = (int)($_GET['fi'] ?? -1);
  if ($p === 'faq-ubah' && isset($f0[$fei])) $fedit = $f0[$fei] + ['_i' => $fei];
}
if ($p === 'faq-tambah' || $fedit):
?>
<div class="card editor" id="formulir-f">
<h2><?= icon("check") ?> <?= $fedit ? "Ubah pertanyaan" : "Tambah pertanyaan" ?></h2>
<form method="post">
<input type="hidden" name="csrf" value="<?= h(ruethies_csrf()) ?>" /><input type="hidden" name="aksi" value="faq-save" />
<input type="hidden" name="fi" value="<?= $fedit['_i'] ?? -1 ?>" />
<label>Pertanyaan (Indonesia)<span class="count" id="c-fq"></span><input name="q" maxlength="120" required data-count="c-fq" value="<?= h($fedit['q'] ?? '') ?>" /></label>
<label>Jawaban (Indonesia)<span class="count" id="c-fa"></span><textarea name="a" rows="3" maxlength="400" required data-count="c-fa"><?= h($fedit['a'] ?? '') ?></textarea></label>
<label>Pertanyaan (Inggris, opsional)<input name="qe" maxlength="120" value="<?= h($fedit['qe'] ?? '') ?>" /></label>
<label>Jawaban (Inggris, opsional)<textarea name="ae" rows="3" maxlength="400"><?= h($fedit['ae'] ?? '') ?></textarea></label>
<p class="sub">Tautan ditulis <span class="mono">[teks](https://...)</span> — contoh: [Dapatkan rute](https://www.google.com/maps/dir/?api=1&amp;destination=-6.3443722,107.1490519)</p>
<div class="stickybar"><button><?= icon("save", 18) ?> Simpan FAQ</button></div>
<p class="center"><a class="linkbtn" href="?p=faq#faqman"><?= icon("back", 15) ?> Kembali tanpa menyimpan</a></p>
</form>
</div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php if ($isGate): ?></div></div><?php else: ?></main></div></div><?php endif; ?>
</body>
</html>
