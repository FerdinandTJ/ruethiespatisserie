<?php
// Ruethies Patisserie — konfigurasi admin. File ini BOLEH dibaca browser? Tidak masalah:
// tidak berisi kredensial (kredensial hanya di RUETHIES_DATA/users.json yang dilindungi .htaccess).
defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.');

define('RUETHIES_PUBLIC', dirname(__DIR__)); // public_html (admin adalah subdirektori)
define('RUETHIES_MENU', RUETHIES_PUBLIC . '/menu.json');
define('RUETHIES_IMGDIR', RUETHIES_PUBLIC . '/images/menu');
// Berdasarkan lokasi di disk, bukan Host header yang bisa dipalsukan.
define('RUETHIES_STAGING', basename(RUETHIES_PUBLIC) === 'staging'
  && basename(dirname(RUETHIES_PUBLIC)) === 'public_html');

// Data sensitif: utamakan di LUAR web root (sejajar public_html), fallback ke admin/data/ (dilindungi .htaccess).
function ruethies_data_dir(): string {
  if (RUETHIES_STAGING) {
    $outside = dirname(RUETHIES_PUBLIC, 2) . '/ruethies-data-staging';
    if (!is_link($outside) && (is_dir($outside) || @mkdir($outside, 0700, true))) return $outside;
    // Jangan pernah fallback ke direktori publik atau data produksi.
    http_response_code(503);
    die('Direktori data privat staging belum tersedia. Hubungi pengelola hosting.');
  }
  $outside = dirname(RUETHIES_PUBLIC) . '/ruethies-data';
  if (is_dir($outside) || @mkdir($outside, 0700, true)) return $outside;
  $inside = __DIR__ . '/data';
  if (!is_dir($inside)) @mkdir($inside, 0700, true);
  return $inside;
}

// Session keras: HttpOnly + Secure (bila HTTPS) + SameSite Strict + nama non-standar.
function ruethies_session_start(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  session_name(RUETHIES_STAGING ? 'RUETHIESSTG' : 'RUETHIESADM');
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
  session_set_cookie_params([
    'lifetime' => 0, 'path' => '/admin',
    'secure' => $https, 'httponly' => true, 'samesite' => 'Strict',
  ]);
  session_start();
  // Idle timeout 30 menit, absolut 8 jam.
  $now = time();
  if (isset($_SESSION['last']) && $now - $_SESSION['last'] > 1800) ruethies_logout();
  if (isset($_SESSION['born']) && $now - $_SESSION['born'] > 28800) ruethies_logout();
  $_SESSION['last'] = $now;
  if (empty($_SESSION['born'])) { $_SESSION['born'] = $now; session_regenerate_id(true); }
}
