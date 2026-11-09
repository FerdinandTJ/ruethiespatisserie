<?php
// Pure shared catalogue helpers. No session, account store, or private-data access.
if (!defined('RUETHIES_ADMIN') && !defined('RUETHIES_PUBLIC_RENDER')) { http_response_code(404); exit; }
const CATALOG_ORIGIN = 'https://ruethiespatisserie.com';
const CATALOG_CATEGORIES = ['cakery'=>'Kue & Jajanan','savoury'=>'Camilan Gurih','packages'=>'Paket & Sajian'];
function catalog_escape(string $text): string { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function catalog_json($value): string { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR); }
function catalog_image($path): bool { return is_string($path) && (bool)preg_match('~\Aimages/menu/[a-z0-9]+(?:-[a-z0-9]+)*\.(?:jpg|png|webp)\z~D', $path); }
function catalog_slug(string $name): string {
  $ascii = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name);
  $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii === false ? $name : $ascii)), '-');
  return substr($slug ?: 'menu', 0, 70);
}
function catalog_path(array $item): string { return ($item['slug'] === 'snackbox') ? '/snack-box-cikarang/' : '/menu/'.$item['slug'].'/'; }
function catalog_read_json(string $path): array {
  $fh = @fopen($path, 'rb');
  if (!$fh) throw new RuntimeException('Konten tidak tersedia.');
  try {
    if (!flock($fh, LOCK_SH)) throw new RuntimeException('Konten tidak dapat dibaca.');
    $raw = stream_get_contents($fh); flock($fh, LOCK_UN);
  } finally { fclose($fh); }
  try { $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); } catch (JsonException $e) { throw new RuntimeException('Konten tidak valid.', 0, $e); }
  if (!is_array($data) || !array_is_list($data)) throw new RuntimeException('Daftar konten tidak valid.');
  return $data;
}
function catalog_validate(array $items, bool $metadata = true): void {
  $ids = []; $slugs = [];
  foreach ($items as $item) {
    if (!is_array($item) || !isset(CATALOG_CATEGORIES[$item['cat'] ?? '']) || !is_array($item['id'] ?? null)
      || !is_string($item['id']['n'] ?? null) || trim($item['id']['n']) === '' || !is_string($item['id']['d'] ?? null)
      || !catalog_image($item['img'] ?? null) || (isset($item['available']) && !is_bool($item['available']))) throw new RuntimeException('Produk tidak valid.');
    foreach (['id','en'] as $language) {
      if (isset($item[$language]) && (!is_array($item[$language]) || !is_string($item[$language]['n'] ?? null) || !is_string($item[$language]['d'] ?? null))) throw new RuntimeException('Terjemahan tidak valid.');
      if (isset($item[$language]['detail']) && !is_string($item[$language]['detail'])) throw new RuntimeException('Deskripsi detail tidak valid.');
    }
    if (isset($item['detail_images'])) {
      if (!is_array($item['detail_images']) || !array_is_list($item['detail_images']) || count($item['detail_images']) > 2) throw new RuntimeException('Foto detail tidak valid.');
      foreach ($item['detail_images'] as $image) if ($image !== '' && !catalog_image($image)) throw new RuntimeException('Foto detail tidak valid.');
    }
    if ($metadata) {
      if (!is_string($item['uid'] ?? null) || !preg_match('/\A[a-f0-9]{32}\z/D',$item['uid'])
        || !is_string($item['slug'] ?? null) || !preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D',$item['slug']) || strlen($item['slug']) > 80
        || !is_string($item['updated_at'] ?? null) || !preg_match('/\A\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ\z/D',$item['updated_at']) || strtotime($item['updated_at']) === false
        || isset($ids[$item['uid']]) || isset($slugs[$item['slug']])) throw new RuntimeException('Metadata SEO belum siap atau tidak valid.');
      $ids[$item['uid']] = true; $slugs[$item['slug']] = true;
    }
  }
}
function catalog_metadata(array $items, int $modified): array {
  $slugs = []; $ids = [];
  // Reserve existing identities first, including rows later in the file.
  foreach ($items as $item) {
    if (isset($item['uid'])) { if (!is_string($item['uid']) || !preg_match('/\A[a-f0-9]{32}\z/D',$item['uid']) || isset($ids[$item['uid']])) throw new RuntimeException('ID produk rusak atau duplikat.'); $ids[$item['uid']] = true; }
    if (isset($item['slug'])) { if (!is_string($item['slug']) || !preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D',$item['slug']) || strlen($item['slug']) > 80 || isset($slugs[$item['slug']])) throw new RuntimeException('Slug produk rusak atau duplikat.'); $slugs[$item['slug']] = true; }
  }
  foreach ($items as &$item) {
    if (!isset($item['uid'])) { do { $id = bin2hex(random_bytes(16)); } while(isset($ids[$id])); $item['uid']=$id; $ids[$id]=true; }
    if (!isset($item['slug'])) {
      $base = catalog_slug($item['id']['n'] ?? 'menu');
      if (in_array($base,['snack-box','snackbox'],true)) $base='snackbox';
      $slug=$base; $n=2; while(isset($slugs[$slug])) $slug=$base.'-'.$n++;
      $item['slug']=$slug; $slugs[$slug]=true;
    }
    if (!isset($item['updated_at'])) $item['updated_at']=gmdate('Y-m-d\TH:i:s\Z',$modified);
  } unset($item);
  return $items;
}
function catalog_atomic_text(string $path, string $encoded): bool {
  $tmp = @tempnam(dirname($path), '.catalog-');
  if ($tmp === false) return false;
  try {
    if (@file_put_contents($tmp,$encoded,LOCK_EX) === false) return false;
    if (!@chmod($tmp,0644)) return false;
    return @rename($tmp,$path);
  } finally { if (is_file($tmp)) @unlink($tmp); }
}

function catalog_atomic_write(string $path, array $items): bool {
  return catalog_atomic_text($path,json_encode(array_values($items),JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
}
