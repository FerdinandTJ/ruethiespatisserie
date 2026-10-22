<?php
// Jalankan: php tests/menu-photo-delete.php (fixture sementara, bukan data website).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('RUETHIES_ADMIN', true);
$tmp = sys_get_temp_dir() . '/ruethies-photo-test-' . bin2hex(random_bytes(8));
mkdir($tmp, 0700);
define('RUETHIES_PUBLIC', $tmp . '/staging');
define('RUETHIES_MENU', RUETHIES_PUBLIC . '/menu.json');
mkdir(RUETHIES_PUBLIC . '/images/menu', 0700, true);
mkdir($tmp . '/private', 0700);
function ruethies_data_dir(): string { global $tmp; return $tmp . '/private'; }
require dirname(__DIR__) . '/admin/lib.php';

function check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  print "PASS: $label\n";
}
function fixture(): void {
  foreach (['menu.json', 'atmosphere.json', 'faq.json'] as $file) file_put_contents(RUETHIES_PUBLIC . '/' . $file, '[]');
  foreach (['index.html', 'app.js', 'styles.css', 'llms.txt'] as $file) file_put_contents(RUETHIES_PUBLIC . '/' . $file, '');
  file_put_contents(RUETHIES_PUBLIC . '/images/menu/example.jpg', 'jpeg');
  file_put_contents(RUETHIES_PUBLIC . '/images/menu/example.webp', 'webp');
}
function exists_pair(): bool {
  clearstatcache();
  return is_file(RUETHIES_PUBLIC . '/images/menu/example.jpg') && is_file(RUETHIES_PUBLIC . '/images/menu/example.webp');
}
function cleanup(string $path): void {
  if (is_link($path) || !is_dir($path)) { unlink($path); return; }
  chmod($path, 0700);
  foreach (scandir($path) as $name) if ($name !== '.' && $name !== '..') cleanup($path . '/' . $name);
  rmdir($path);
}

try {
  fixture();
  check(menu_save([]), 'menu saved before cleanup');
  check(menu_photo_delete_unused('images/menu/example.jpg') === 'deleted' && !exists_pair(), 'unused JPEG and WebP deleted');
  check(!file_exists(RUETHIES_PUBLIC . '/images/menu/example.webp'), 'WebP companion removed');
  check(menu_photo_delete_unused('images/menu/example.jpg') === 'deleted', 'already missing files are harmless');
  check(menu_photo_delete_unused('') === 'none', 'menu without image');

  foreach (['menu.json', 'atmosphere.json'] as $file) {
    fixture();
    file_put_contents(RUETHIES_PUBLIC . '/' . $file, json_encode([['imgFull' => '/images/menu/example.webp?v=2']]));
    check(menu_photo_delete_unused('images/menu/example.jpg') === 'kept' && exists_pair(), "shared WebP reference in $file preserved");
  }
  fixture();
  file_put_contents(RUETHIES_MENU, json_encode([['img' => 'images/menu/example.jpg', 'available' => false]]));
  check(menu_photo_delete_unused('images/menu/example.jpg') === 'kept' && exists_pair(), 'hidden menu reference preserved');

  foreach (['index.html', 'app.js', 'styles.css', 'faq.json'] as $file) {
    fixture();
    $ref = 'https://example.test/images/menu/example.jpg';
    file_put_contents(RUETHIES_PUBLIC . '/' . $file, str_ends_with($file, '.json') ? json_encode([['a' => $ref]]) : $ref);
    check(menu_photo_delete_unused('images/menu/example.jpg') === 'kept' && exists_pair(), "public reference in $file preserved");
  }

  fixture();
  foreach (['../example.jpg', 'images/menu/../../example.jpg', '/images/menu/example.jpg', 'https://example.test/example.jpg', 'images/menu/example.php', 'images/menu/%2e%2e/example.jpg'] as $bad) {
    check(menu_photo_delete_unused($bad) === 'kept' && exists_pair(), "unsafe/unsupported path rejected: $bad");
  }
  file_put_contents(RUETHIES_PUBLIC . '/atmosphere.json', '{broken');
  check(menu_photo_delete_unused('images/menu/example.jpg') === 'failed' && exists_pair(), 'invalid reference data prevents deletion');
  fixture();
  unlink(RUETHIES_PUBLIC . '/app.js');
  check(menu_photo_delete_unused('images/menu/example.jpg') === 'failed' && exists_pair(), 'missing reference source prevents deletion');

  fixture();
  $outside = $tmp . '/production.jpg';
  file_put_contents($outside, 'production');
  unlink(RUETHIES_PUBLIC . '/images/menu/example.webp');
  symlink($outside, RUETHIES_PUBLIC . '/images/menu/example.webp');
  check(menu_photo_delete_unused('images/menu/example.jpg') === 'failed', 'symlink file rejected before either variant is removed');
  check(is_file(RUETHIES_PUBLIC . '/images/menu/example.jpg') && file_get_contents($outside) === 'production', 'production target untouched');
  unlink(RUETHIES_PUBLIC . '/images/menu/example.webp');
  fixture();
  rename(RUETHIES_PUBLIC . '/images/menu', RUETHIES_PUBLIC . '/images/original-menu');
  symlink(RUETHIES_PUBLIC . '/images/original-menu', RUETHIES_PUBLIC . '/images/menu');
  check(menu_photo_delete_unused('images/menu/example.jpg') === 'failed' && exists_pair(), 'symlink directory rejected');
  unlink(RUETHIES_PUBLIC . '/images/menu');
  rename(RUETHIES_PUBLIC . '/images/original-menu', RUETHIES_PUBLIC . '/images/menu');

  fixture();
  if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
    chmod(RUETHIES_PUBLIC . '/images/menu', 0500);
    check(menu_photo_delete_unused('images/menu/example.jpg') === 'failed' && exists_pair(), 'unlink failure reported without claiming success');
    chmod(RUETHIES_PUBLIC . '/images/menu', 0700);
  }
  check(file_get_contents($outside) === 'production', 'all tests leave production fixture unchanged');
} finally {
  cleanup($tmp);
}
