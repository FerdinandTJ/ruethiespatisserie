<?php
// Pure shared catalogue helpers. No session, account store, or private-data access.
if (!defined('RUETHIES_ADMIN') && !defined('RUETHIES_PUBLIC_RENDER')) { http_response_code(404); exit; }
const CATALOG_ORIGIN = 'https://ruethiespatisserie.com';
// Legacy coffee stays editable only inside the staging filesystem layout.
define('CATALOG_CATEGORIES', ['cakery'=>'Kue & Jajanan','savoury'=>'Camilan Gurih','packages'=>'Paket & Sajian'] +
  ((basename(dirname(__DIR__))==='staging' && basename(dirname(__DIR__,2))==='public_html') ? ['coffee'=>'Kopi & Minuman'] : []));
function catalog_escape(string $text): string { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function catalog_json($value): string { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR); }
function catalog_image($path): bool { return is_string($path) && (bool)preg_match('~\Aimages/menu/[a-z0-9]+(?:-[a-z0-9]+)*\.(?:jpg|png|webp)\z~D', $path); }
function catalog_slug(string $name): string {
  $ascii = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name);
  $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii === false ? $name : $ascii)), '-');
  return rtrim(substr($slug ?: 'menu', 0, 70), '-') ?: 'menu';
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
  if (!str_starts_with(ltrim($raw),'[') || !is_array($data) || !array_is_list($data)) throw new RuntimeException('Daftar konten tidak valid.');
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

// Copy describes the existing catalogue only; ordering terms stay on WhatsApp.
function catalog_detail(array $item,string $language): string {
  $custom=$item[$language]['detail'] ?? '';
  if ($custom!=='') return $custom;
  $copy=[
    'snackbox'=>['Paket kudapan dalam kotak menjadi bagian dari katalog Ruethies Patisserie di Lippo Cikarang. Untuk membahas kebutuhan acara, kirimkan jumlah kotak dan tanggal kebutuhan melalui WhatsApp. Pilihan isi dan kemasan dikonfirmasi bersama tim Ruethies.','Boxed treats are part of the Ruethies Patisserie catalogue in Lippo Cikarang. Share the number of boxes and your date on WhatsApp to discuss your gathering. Confirm contents and packaging with our team.'],
    'bolu-jadoel'=>['Bolu klasik dengan taburan cokelat yang menjadi salah satu pilihan Kue & Jajanan Ruethies. Lihat foto sebagai referensi tampilan produk, lalu tanyakan pilihan sajian dan kemasannya melalui WhatsApp.','A classic cake with chocolate sprinkles in the Ruethies Cakes & Desserts selection. Use the photos as a reference and ask about presentation and packaging on WhatsApp.'],
    'brownies-cokelat'=>['Brownies cokelat untuk dinikmati per potong atau bersama. Gunakan foto produk sebagai referensi tampilan brownies saat membicarakan kebutuhan Anda. Konfirmasikan pilihan serta ukuran brownies melalui WhatsApp.','Chocolate brownies to enjoy by the slice or share. Use the product photos as a reference when discussing the brownies you need. Confirm brownie selections and sizes on WhatsApp.'],
    'lapis-surabaya'=>['Kue berlapis dengan tampilan klasik dalam pilihan Kue & Jajanan Ruethies. Foto produk memperlihatkan sajian Lapis Surabaya sebagai referensi sebelum Anda menanyakan ukuran dan pilihan pemesanannya.','A layered cake with a classic presentation in our Cakes & Desserts selection. The product photos show Lapis Surabaya as a reference before you ask about sizes and ordering options.'],
    'marble-cake'=>['Pola marmer pada setiap irisan menjadi tampilan khas Marble Cake dalam katalog Ruethies. Bandingkan foto produk dengan pilihan bolu lainnya, lalu hubungi WhatsApp untuk memastikan ukuran dan sajian yang tersedia.','The marble pattern in each slice distinguishes Marble Cake in our catalogue. Compare the product photos with our other cakes, then ask on WhatsApp about available sizes and presentation.'],
    'jongkong'=>['Jongkong tampil dalam cup dengan lapisan hijau dan putih. Produk ini melengkapi pilihan kue dan jajanan di Ruethies Patisserie. Tanyakan ketersediaan serta kebutuhan jumlah cup melalui WhatsApp.','Jongkong is presented in cups with green and white layers. It complements the cakes and traditional treats at Ruethies Patisserie. Ask on WhatsApp about availability and the number of cups you need.'],
    'pastel'=>['Pastel dengan kulit berlipat berada dalam pilihan Camilan Gurih Ruethies, bersama kroket dan risol. Lihat foto untuk mengenali sajiannya dan tanyakan pilihan isi yang tersedia melalui WhatsApp.','Folded pastel pastries are part of our Savoury Snacks selection alongside croquettes and risol. Browse the photos and ask about available fillings on WhatsApp.'],
    'kroket'=>['Kroket berbalut tepung panir dengan tampilan keemasan merupakan salah satu pilihan camilan gurih. Jika ingin memadukannya dengan sajian lain, diskusikan pilihan kroket, pastel, dan risol melalui WhatsApp.','Golden, breadcrumb-coated croquettes are one of our savoury snack selections. Discuss croquettes, pastel and risol on WhatsApp if you would like to combine different treats.'],
    'risol'=>['Risol berbalut tepung panir melengkapi pilihan Camilan Gurih di Ruethies Patisserie. Gunakan nama produk saat menghubungi WhatsApp agar tim dapat membantu mengonfirmasi pilihan isi dan kebutuhan pesanan.','Breadcrumb-coated risol completes our savoury snack selection. Mention the product name on WhatsApp so our team can confirm fillings and your ordering needs.'],
    'platter'=>['Platter merupakan pilihan sajian campuran dalam katalog Paket & Sajian Ruethies. Foto dapat menjadi referensi untuk membicarakan pilihan sajian. Konfirmasikan isi serta kemasannya melalui WhatsApp.','Platters offer a mixed selection in our Boxes & Platters catalogue. Use the photos as a reference when discussing your selection. Confirm contents and packaging on WhatsApp.'],
  ];
  // A renamed product must never inherit a description about its previous identity.
  $original=['snackbox'=>'Snackbox','bolu-jadoel'=>'Bolu Jadoel','brownies-cokelat'=>'Brownies Cokelat','lapis-surabaya'=>'Lapis Surabaya','marble-cake'=>'Marble Cake','jongkong'=>'Jongkong','pastel'=>'Pastel','kroket'=>'Kroket','risol'=>'Risol','platter'=>'Platter'];
  if (($item['id']['n'] ?? '')!==($original[$item['slug']] ?? null)) return '';
  return $copy[$item['slug']][$language==='en'?1:0] ?? '';
}
