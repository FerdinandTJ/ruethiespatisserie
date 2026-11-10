<?php
// Derivatif logo untuk website; sumber PNG asli tetap dipertahankan.
// Jalankan lokal: php tools/build-brand-images.php (memerlukan GD + WebP).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!function_exists('imagewebp')) { fwrite(STDERR,"GD WebP diperlukan.\n"); exit(1); }
$root = dirname(__DIR__);
$out = $root . '/images/brand';
if (!is_dir($out) && !mkdir($out,0755,true)) throw new RuntimeException('Folder output gagal dibuat.');
foreach (['wine','white'] as $color) {
  $source = imagecreatefrompng($root . '/logo-' . $color . '.png');
  foreach ($color === 'wine' ? [64,320,640] : [320,640] as $width) {
    $height = (int)round(imagesy($source) * $width / imagesx($source));
    $image = imagecreatetruecolor($width,$height);
    imagealphablending($image,false); imagesavealpha($image,true);
    imagefill($image,0,0,imagecolorallocatealpha($image,0,0,0,127));
    imagecopyresampled($image,$source,0,0,0,0,$width,$height,imagesx($source),imagesy($source));
    if ($width >= 320) {
      $file = $out . '/logo-' . $color . '-' . $width . '.webp';
      if (!imagewebp($image,$file,90)) throw new RuntimeException('Konversi logo gagal.');
      echo basename($file), ' ', $width, 'x', $height, ' ', filesize($file), " bytes\n";
    }
    if ($width <= 320) {
      $file = $out . '/logo-' . $color . '-' . $width . '.png';
      if (!imagepng($image,$file,9)) throw new RuntimeException('Fallback PNG gagal.');
      echo basename($file), ' ', $width, 'x', $height, ' ', filesize($file), " bytes\n";
    }
    unset($image);
  }
  unset($source);
}
