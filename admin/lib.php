<?php
// Ruethies Patisserie — pustaka keamanan + penyimpanan admin (tanpa dependensi luar).
defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.');

const RUETHIES_CATS = ['cakery' => 'Cakery', 'coffee' => 'Coffee & Drinks', 'savoury' => 'Savoury Patisserie'];

/* ---------- util ---------- */
function h($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function ruethies_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? 'unknown'; }

function jread(string $f, $def) {
  if (!is_file($f)) return $def;
  $fh = @fopen($f, 'r'); if (!$fh) return $def;
  flock($fh, LOCK_SH); $raw = stream_get_contents($fh); flock($fh, LOCK_UN); fclose($fh);
  $d = json_decode($raw ?? '', true);
  return is_array($d) ? $d : $def;
}
function jwrite(string $f, $data): bool {
  $tmp = $f . '.tmp.' . getmypid();
  $ok = @file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
  if ($ok === false) return false;
  return @rename($tmp, $f);
}

/* ---------- log aktivitas ---------- */
function ruethies_log(string $actor, string $aksi, string $detail = ''): void {
  $f = ruethies_data_dir() . '/log.jsonl';
  $line = json_encode(['t' => date('c'), 'ip' => ruethies_ip(), 'oleh' => $actor, 'aksi' => $aksi, 'detail' => $detail], JSON_UNESCAPED_UNICODE);
  $fh = @fopen($f, 'a');
  if ($fh) { flock($fh, LOCK_EX); fwrite($fh, $line . "\n"); flock($fh, LOCK_UN); fclose($fh); }
}
function ruethies_logs(int $n = 100): array {
  $f = ruethies_data_dir() . '/log.jsonl';
  if (!is_file($f)) return [];
  $lines = array_filter(explode("\n", @file_get_contents($f) ?: ''));
  $out = [];
  foreach (array_slice($lines, -$n) as $l) { $d = json_decode($l, true); if ($d) $out[] = $d; }
  return array_reverse($out);
}

/* ---------- rate limit login: maks 5 gagal / 15 mnt / IP ---------- */
function ruethies_ratelimit_hit(): bool {
  $f = ruethies_data_dir() . '/ratelimit.json';
  $d = jread($f, []);
  $ip = ruethies_ip(); $now = time();
  $d[$ip] = array_values(array_filter($d[$ip] ?? [], fn($t) => $now - $t < 900));
  if (count($d[$ip]) >= 5) return true;
  $d[$ip][] = $now; jwrite($f, $d);
  return false;
}
function ruethies_ratelimit_ok(): bool {
  $f = ruethies_data_dir() . '/ratelimit.json';
  $d = jread($f, []);
  $ip = ruethies_ip(); $now = time();
  $hits = array_filter($d[$ip] ?? [], fn($t) => $now - $t < 900);
  return count($hits) < 5;
}
function ruethies_ratelimit_reset(): void {
  $f = ruethies_data_dir() . '/ratelimit.json';
  $d = jread($f, []); unset($d[ruethies_ip()]); jwrite($f, $d);
}

/* ---------- CSRF ---------- */
function ruethies_csrf(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function ruethies_csrf_ok(): bool {
  return isset($_POST['csrf']) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

/* ---------- pengguna ---------- */
function users_file(): string { return ruethies_data_dir() . '/users.json'; }
function users_all(): array { return jread(users_file(), []); }
function users_save(array $u): bool { return jwrite(users_file(), $u); }
function user_get(string $name): ?array {
  foreach (users_all() as $u) if (hash_equals(strtolower($u['name']), strtolower($name))) return $u;
  return null;
}

/* ---------- TOTP RFC 6238 (2FA tanpa layanan luar) ---------- */
function b32enc(string $bin): string {
  $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = '';
  for ($i = 0; $i < strlen($bin); $i++) $bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT);
  $out = '';
  for ($i = 0; $i < strlen($bits); $i += 5) $out .= $map[bindec(str_pad(substr($bits, $i, 5), 5, '0'))];
  return $out;
}
function b32dec(string $s) {
  $s = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $s));
  $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = '';
  for ($i = 0; $i < strlen($s); $i++) { $v = strpos($map, $s[$i]); if ($v === false) return false; $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT); }
  $out = '';
  for ($i = 0; $i + 8 <= strlen($bits); $i += 8) $out .= chr((int)bindec(substr($bits, $i, 8)));
  return $out;
}
function totp_at(string $secret, int $counter): string {
  $key = b32dec($secret); if ($key === false) return '------';
  $bin = pack('N*', 0, $counter);
  $hash = hash_hmac('sha1', $bin, $key, true);
  $off = ord($hash[19]) & 0x0f;
  $code = ((ord($hash[$off]) & 0x7f) << 24 | ord($hash[$off + 1]) << 16 | ord($hash[$off + 2]) << 8 | ord($hash[$off + 3])) % 1000000;
  return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}
function totp_verify(string $secret, string $code): bool {
  if (!ctype_digit($code) || strlen($code) !== 6) return false;
  $c = (int)floor(time() / 30);
  foreach ([-1, 0, 1] as $w) if (hash_equals(totp_at($secret, $c + $w), $code)) return true;
  return false;
}

/* ---------- session auth ---------- */
function current_user(): ?array {
  if (empty($_SESSION['uid'])) return null;
  $u = user_get($_SESSION['uid']);
  if (!$u) { ruethies_logout(); return null; }
  // UA binding ringan: sesi tidak bisa dibajak mentah ke browser lain.
  if (($_SESSION['ua'] ?? '') !== md5($_SERVER['HTTP_USER_AGENT'] ?? '')) { ruethies_logout(); return null; }
  return $u;
}
function is_owner(?array $u): bool { return $u && ($u['role'] ?? '') === 'owner'; }
function ruethies_login(array $u): void {
  session_regenerate_id(true);
  $_SESSION['uid'] = $u['name'];
  $_SESSION['ua'] = md5($_SERVER['HTTP_USER_AGENT'] ?? '');
  $_SESSION['born'] = time(); $_SESSION['last'] = time();
}
function ruethies_logout(): void {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], '', $p['secure'], $p['httponly']);
  }
  if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}

/* ---------- menu ---------- */
function menu_load(): array {
  $d = jread(RUETHIES_MENU, []);
  return is_array($d) ? array_values(array_filter($d, fn($m) => is_array($m))) : [];
}
function menu_save(array $items): bool {
  // Backup 5 versi terakhir sebelum tulis.
  $dir = ruethies_data_dir() . '/backups';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);
  if (is_file(RUETHIES_MENU)) @copy(RUETHIES_MENU, $dir . '/menu-' . date('Ymd-His') . '.json');
  foreach (glob($dir . '/menu-*.json') ?: [] as $i => $f) if ($i >= 4) @unlink($f);
  $fh = @fopen(RUETHIES_MENU, 'c+');
  if (!$fh) return false;
  flock($fh, LOCK_EX); ftruncate($fh, 0);
  $ok = fwrite($fh, json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
  fflush($fh); flock($fh, LOCK_UN); fclose($fh);
  return $ok !== false;
}
function menu_validate(array $in): ?array {
  $cat = $in['cat'] ?? '';
  if (!isset(RUETHIES_CATS[$cat])) return null;
  $n = trim(strip_tags($in['n'] ?? '')); if ($n === '' || mb_strlen($n) > 60) return null;
  $d = trim(strip_tags($in['d'] ?? '')); if (mb_strlen($d) > 160) return null;
  $ne = trim(strip_tags($in['ne'] ?? '')); if (mb_strlen($ne) > 60) return null;
  $de = trim(strip_tags($in['de'] ?? '')); if (mb_strlen($de) > 160) return null;
  $badge = trim(strip_tags($in['badge'] ?? '')); if (mb_strlen($badge) > 24) return null;
  return [
    'cat' => $cat, 'badge' => $badge, 'signature' => !empty($in['signature']),
    'available' => !isset($in['available']) || !empty($in['available']),
    'tag' => ['id' => trim(strip_tags($in['tag'] ?? '')), 'en' => ''],
    'img' => $in['img'] ?? '',
    'id' => ['n' => $n, 'd' => $d],
    'en' => ['n' => $ne !== '' ? $ne : $n, 'd' => $de !== '' ? $de : $d],
  ];
}

/* ---------- galeri suasana (atmosphere.json) ---------- */
const RUETHIES_ATMOS = RUETHIES_PUBLIC . '/atmosphere.json';
function atmos_load(): array {
  $d = jread(RUETHIES_ATMOS, []);
  return is_array($d) ? array_values(array_filter($d, fn($a) => is_array($a))) : [];
}
function atmos_save(array $items): bool {
  $dir = ruethies_data_dir() . '/backups';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);
  if (is_file(RUETHIES_ATMOS)) @copy(RUETHIES_ATMOS, $dir . '/atmos-' . date('Ymd-His') . '.json');
  $all = glob($dir . '/atmos-*.json') ?: [];
  usort($all, fn($a, $b) => strcmp($b, $a));
  foreach (array_slice($all, 5) as $f) @unlink($f);
  $fh = @fopen(RUETHIES_ATMOS, 'c+');
  if (!$fh) return false;
  flock($fh, LOCK_EX); ftruncate($fh, 0);
  $ok = fwrite($fh, json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
  fflush($fh); flock($fh, LOCK_UN); fclose($fh);
  return $ok !== false;
}
// Upload galeri: satu sumber → thumb 640x480 + versi lightbox 1200x900.
function galeri_upload(array $file, string $caption): array {
  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null, null];
  if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) return [null, null, 'Upload gagal, coba lagi.'];
  if (($file['size'] ?? 0) > 8 * 1024 * 1024) return [null, null, 'Foto maksimal 8 MB.'];
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($file['tmp_name']);
  if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) return [null, null, 'File harus JPG, PNG, atau WebP.'];
  $raw = @file_get_contents($file['tmp_name']);
  $src = @imagecreatefromstring($raw ?: '');
  if (!$src) return [null, null, 'File gambar tidak valid.'];
  if (!is_dir(RUETHIES_PUBLIC . '/images')) @mkdir(RUETHIES_PUBLIC . '/images', 0755, true);
  $base = 'suasana-' . photo_slug($caption); $try = $base; $i = 2;
  while (is_file(RUETHIES_PUBLIC . "/images/$try.jpg")) { $try = "$base-$i"; $i++; }
  $mk = function ($tw, $th) use ($src) {
    $w = imagesx($src); $hh = imagesy($src); $tr = $tw / $th; $sr = $w / $hh;
    if ($sr > $tr) { $nw = (int)($hh * $tr); $x = (int)(($w - $nw) / 2); $c = imagecrop($src, ['x' => $x, 'y' => 0, 'width' => $nw, 'height' => $hh]); }
    else { $nh = (int)($w / $tr); $y = (int)(($hh - $nh) / 2); $c = imagecrop($src, ['x' => 0, 'y' => $y, 'width' => $w, 'height' => $nh]); }
    if (!$c) return null;
    $dst = imagecreatetruecolor($tw, $th);
    imagecopyresampled($dst, $c, 0, 0, 0, 0, $tw, $th, imagesx($c), imagesy($c));
    imagedestroy($c);
    return $dst;
  };
  $t1 = $mk(640, 480); $t2 = $mk(1200, 900);
  if (!$t1 || !$t2) { imagedestroy($src); return [null, null, 'Gagal memproses foto.']; }
  imagedestroy($src);
  $j1 = RUETHIES_PUBLIC . "/images/$try.jpg"; $j2 = RUETHIES_PUBLIC . "/images/$try-xl.jpg";
  $ok = @imagejpeg($t1, $j1, 68) && @imagejpeg($t2, $j2, 70);
  if (function_exists('imagewebp')) { @imagewebp($t1, RUETHIES_PUBLIC . "/images/$try.webp", 66); @imagewebp($t2, RUETHIES_PUBLIC . "/images/$try-xl.webp", 68); }
  imagedestroy($t1); imagedestroy($t2);
  if (!$ok) return [null, null, 'Gagal menyimpan foto.'];
  return ["images/$try.jpg", "images/$try-xl.jpg", null];
}
function photo_slug(string $name): string {
  $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($name));
  $t = preg_replace('/[^a-z0-9]+/', '-', $t ?? '');
  return trim(preg_replace('/-+/', '-', $t ?? ''), '-') ?: 'menu';
}
/* ---------- upload foto menu: re-encode (buang EXIF) + normalkan 640x480 ---------- */
function photo_upload(array $file, string $name): array {
  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null];
  if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) return [null, 'Upload gagal, coba lagi.'];
  if (($file['size'] ?? 0) > 5 * 1024 * 1024) return [null, 'Foto maksimal 5 MB.'];
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($file['tmp_name']);
  if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) return [null, 'File harus JPG, PNG, atau WebP.'];
  $raw = @file_get_contents($file['tmp_name']);
  $im = @imagecreatefromstring($raw ?: '');
  if (!$im) return [null, 'File gambar tidak valid.'];
  // Center-crop 4:3 lalu resize 640x480.
  $w = imagesx($im); $hh = imagesy($im); $tr = 640 / 480; $sr = $w / $hh;
  if ($sr > $tr) { $nw = (int)($hh * $tr); $x = (int)(($w - $nw) / 2); $crop = imagecrop($im, ['x' => $x, 'y' => 0, 'width' => $nw, 'height' => $hh]); }
  else { $nh = (int)($w / $tr); $y = (int)(($hh - $nh) / 2); $crop = imagecrop($im, ['x' => 0, 'y' => $y, 'width' => $w, 'height' => $nh]); }
  if ($crop) { imagedestroy($im); $im = $crop; }
  $dst = imagecreatetruecolor(640, 480);
  imagecopyresampled($dst, $im, 0, 0, 0, 0, 640, 480, imagesx($im), imagesy($im));
  imagedestroy($im);
  if (!is_dir(RUETHIES_IMGDIR)) @mkdir(RUETHIES_IMGDIR, 0755, true);
  $base = photo_slug($name); $try = $base; $i = 2;
  while (is_file(RUETHIES_IMGDIR . "/$try.jpg")) { $try = "$base-$i"; $i++; }
  $jpg = RUETHIES_IMGDIR . "/$try.jpg";
  if (!@imagejpeg($dst, $jpg, 68)) { imagedestroy($dst); return [null, 'Gagal menyimpan foto.']; }
  if (function_exists('imagewebp')) @imagewebp($dst, RUETHIES_IMGDIR . "/$try.webp", 66);
  imagedestroy($dst);
  return ["images/menu/$try.jpg", null];
}
