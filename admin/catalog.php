<?php
// Shared catalogue helpers. Public snapshots read only catalogue content; writes run through owner admin/CLI.
if (!defined('RUETHIES_ADMIN') && !defined('RUETHIES_PUBLIC_RENDER')) { http_response_code(404); exit; }
const CATALOG_ORIGIN = 'https://ruethiespatisserie.com';
// Category names are hosting content; keys remain stable when labels change.
function catalog_root(): string { return defined('RUETHIES_PUBLIC') ? RUETHIES_PUBLIC : dirname(__DIR__); }
function catalog_defaults(): array {
  $names=['cakery'=>['Kue & Jajanan','Cakes & desserts'],'savoury'=>['Camilan Gurih','Savoury bites'],'packages'=>['Paket & Sajian','Boxes & platters']];
  $root=catalog_root();
  if(basename($root)==='staging' && basename(dirname($root))==='public_html')$names['coffee']=['Kopi & Minuman','Coffee & drinks'];
  $out=[];foreach($names as$key=>$label)$out[]=['key'=>$key,'id'=>$label[0],'en'=>$label[1],'updated_at'=>'1970-01-01T00:00:00Z'];return $out;
}
function catalog_lock_run(bool $exclusive,callable $work) {
  $root=catalog_root();$held=$GLOBALS['catalog_locks'][$root]??null;
  if($held){if($exclusive&&!$held['exclusive'])throw new RuntimeException('Urutan lock katalog tidak valid.');return $work();}
  $path=$root.'/.catalog.lock';if(is_link($path))throw new RuntimeException('Lock katalog tidak valid.');
  $fh=@fopen($path,'c+');if(!$fh)throw new RuntimeException('Lock katalog tidak tersedia.');
  try{if(!flock($fh,$exclusive?LOCK_EX:LOCK_SH))throw new RuntimeException('Katalog sedang tidak dapat dikunci.');$GLOBALS['catalog_locks'][$root]=['handle'=>$fh,'exclusive'=>$exclusive];return $work();}
  finally{unset($GLOBALS['catalog_locks'][$root]);flock($fh,LOCK_UN);fclose($fh);}
}
function catalog_lock_hold(): void {
  $root=catalog_root();if(isset($GLOBALS['catalog_locks'][$root])){if(!$GLOBALS['catalog_locks'][$root]['exclusive'])throw new RuntimeException('Lock katalog belum eksklusif.');return;}
  $path=$root.'/.catalog.lock';if(is_link($path))throw new RuntimeException('Lock katalog tidak valid.');
  $fh=@fopen($path,'c+');if(!$fh||!flock($fh,LOCK_EX)){if($fh)fclose($fh);throw new RuntimeException('Katalog sedang tidak dapat dikunci.');}
  $GLOBALS['catalog_locks'][$root]=['handle'=>$fh,'exclusive'=>true];register_shutdown_function(static function()use($root,$fh){unset($GLOBALS['catalog_locks'][$root]);flock($fh,LOCK_UN);fclose($fh);});
}
function categories_validate(array $rows): void {
  $keys=[];$names=[];
  foreach($rows as$row){
    if(!is_array($row)||!is_string($row['key']??null)||!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D',$row['key'])||strlen($row['key'])>80||isset($keys[$row['key']]))throw new RuntimeException('ID kategori tidak valid atau duplikat.');
    foreach(['id','en']as$lang)if(!is_string($row[$lang]??null)||!mb_check_encoding($row[$lang],'UTF-8')||preg_match('/[\x00-\x1F\x7F]/u',$row[$lang])||mb_strlen($row[$lang])>60||strip_tags($row[$lang])!==$row[$lang]||trim($row[$lang])!==$row[$lang])throw new RuntimeException('Nama kategori harus teks biasa, maksimal 60 karakter.');
    $name=mb_strtolower(trim($row['id']),'UTF-8');if($name===''||isset($names[$name]))throw new RuntimeException('Nama kategori Indonesia wajib dan tidak boleh duplikat.');
    if(!is_string($row['updated_at']??null)||!preg_match('/\A\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ\z/D',$row['updated_at'])||strtotime($row['updated_at'])===false)throw new RuntimeException('Waktu kategori tidak valid.');
    $keys[$row['key']]=true;$names[$name]=true;
  }
}
function categories_read(): array {
  $path=catalog_root().'/categories.json';if(is_link($path)||(file_exists($path)&&!is_file($path)))throw new RuntimeException('File kategori tidak valid.');
  $rows=is_file($path)?catalog_read_json($path):catalog_defaults();categories_validate($rows);clearstatcache(true,$path);$GLOBALS['catalog_category_mtime'][catalog_root()]=is_file($path)?filemtime($path):0;return $rows;
}
function catalog_categories(string $language='id',?array $publicItems=null): array {
  $root=catalog_root();if(!isset($GLOBALS['catalog_category_rows'][$root]))$GLOBALS['catalog_category_rows'][$root]=catalog_lock_run(false,fn()=>categories_read());
  $used=$publicItems===null?null:array_fill_keys(array_column(array_filter($publicItems,fn($m)=>($m['available']??true)!==false),'cat'),true);
  $out=[];foreach($GLOBALS['catalog_category_rows'][$root]as$row)if($used===null||isset($used[$row['key']]))$out[$row['key']]=($language==='en'&&$row['en']!=='')?$row['en']:$row['id'];return $out;
}
function catalog_category_label(string $key,string $language='id'): string {
  $names=catalog_categories($language);if(!isset($names[$key]))throw new RuntimeException('Kategori produk tidak dikenal.');return $names[$key];
}
function catalog_snapshot(bool $metadata=true): array {
  return catalog_lock_run(false,static function()use($metadata){
    $rows=categories_read();$GLOBALS['catalog_category_rows'][catalog_root()]=$rows;
    $items=catalog_read_json(catalog_root().'/menu.json');catalog_validate($items,$metadata);
    return ['menu'=>$items,'categories'=>$rows,'revision'=>hash('sha256',catalog_json([$rows,$items]))];
  });
}
function catalog_revision_assert(string $revision,array $snapshot): void {
  if(!preg_match('/\A[a-f0-9]{64}\z/D',$revision)||!hash_equals($snapshot['revision'],$revision))throw new DomainException('Data sudah berubah. Muat ulang halaman sebelum menyimpan.');
}
function catalog_category_modified(?string $key=null): int {
  $root=catalog_root();catalog_categories();$time=0;foreach($GLOBALS['catalog_category_rows'][$root]as$row)if($key===null||$row['key']===$key)$time=max($time,strtotime($row['updated_at']));
  // Reordering/deleting changes the file even if individual labels are unchanged.
  if($key===null)$time=max($time,$GLOBALS['catalog_category_mtime'][$root]??0);return $time;
}
function catalog_backup_pair(): string {
  $dir=ruethies_data_dir().'/backups/catalog-pair-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(5));
  if(!is_dir(dirname($dir))&&!@mkdir(dirname($dir),0700,true))throw new RuntimeException('Backup gagal; data belum diubah.');
  if(!@mkdir($dir,0700))throw new RuntimeException('Backup gagal; data belum diubah.');
  foreach(['menu.json','categories.json']as$name){$path=catalog_root().'/'.$name;if(!file_exists($path))continue;if(!@copy($path,$dir.'/'.$name)||!@chmod($dir.'/'.$name,0600))throw new RuntimeException('Backup gagal; data belum diubah.');}return $dir;
}
function categories_mutate(array $input): void {
  content_lock(true);if(!is_file(catalog_root().'/categories.json'))throw new DomainException('Jalankan upgrade kategori terlebih dahulu.');
  $snapshot=catalog_snapshot(false);catalog_revision_assert($input['revision']??'',$snapshot);$rows=$snapshot['categories'];$key=$input['key']??'';$index=null;foreach($rows as$i=>$row)if($row['key']===$key)$index=$i;
  $action=$input['aksi']??'';
  if($action==='category-save'){
    $name=trim($input['name_id']??'');$english=trim($input['name_en']??'');
    if($key!==''&&$index===null)throw new DomainException('Kategori sudah tidak ada.');
    $row=['key'=>$key!==''?$key:'category-'.bin2hex(random_bytes(8)),'id'=>$name,'en'=>$english,'updated_at'=>gmdate('Y-m-d\TH:i:s\Z')];
    if($index===null)$rows[]=$row;else $rows[$index]=$row;
  }elseif($action==='category-delete'){
    if($index===null)throw new DomainException('Kategori sudah tidak ada.');
    foreach($snapshot['menu']as$item)if($item['cat']===$key)throw new DomainException('Kategori masih dipakai produk, termasuk yang disembunyikan. Pindahkan semua produknya dahulu.');
    if(($input['confirm']??'')!=='yes')throw new DomainException('Konfirmasi penghapusan diperlukan.');array_splice($rows,$index,1);
  }elseif($action==='category-move'){
    if($index===null||!in_array($input['direction']??'',['up','down'],true))throw new DomainException('Urutan kategori tidak valid.');
    $target=$index+($input['direction']==='up'?-1:1);if(!isset($rows[$target]))throw new DomainException('Kategori sudah berada di batas urutan.');
    [$rows[$index],$rows[$target]]=[$rows[$target],$rows[$index]];
  }else throw new DomainException('Aksi kategori tidak dikenal.');
  try{categories_validate($rows);}catch(RuntimeException $e){throw new DomainException($e->getMessage());}
  if($rows===$snapshot['categories'])return;catalog_backup_pair();
  if(!catalog_atomic_write(catalog_root().'/categories.json',$rows))throw new RuntimeException('Penyimpanan kategori gagal. Data sebelumnya dipertahankan.');
  $GLOBALS['catalog_category_rows'][catalog_root()]=$rows;
}

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
    if (!is_array($item) || (!is_string($item['cat']??null)||!isset(catalog_categories()[$item['cat']])) || !is_array($item['id'] ?? null)
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
    menu_variants_validate($item);
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
  return catalog_lock_run(true,static function()use($path,$items){
    if(basename($path)==='categories.json')categories_validate($items);
    if(basename($path)==='menu.json'){$GLOBALS['catalog_category_rows'][catalog_root()]=categories_read();catalog_validate($items,false);}
    return catalog_atomic_text($path,json_encode(array_values($items),JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
  });
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

const MENU_VARIANT_LIMIT = 8;
function menu_variants_validate(array $item): void {
  if (isset($item['variants_enabled']) && !is_bool($item['variants_enabled'])) throw new RuntimeException('Status varian tidak valid.');
  $rows=$item['variants']??[];
  if(!is_array($rows)||!array_is_list($rows)||count($rows)>MENU_VARIANT_LIMIT)throw new RuntimeException('Maksimal 8 varian per produk.');
  $ids=[];$names=[];
  foreach($rows as$row){
    if(!is_array($row)||!is_string($row['uid']??null)||!preg_match('/\A[a-f0-9]{32}\z/D',$row['uid'])||isset($ids[$row['uid']]))throw new RuntimeException('ID varian tidak valid.');
    foreach(['id','en']as$lang)foreach(['n'=>60,'d'=>300]as$key=>$limit){
      $text=$row[$lang][$key]??null;
      if(!is_string($text)||!mb_check_encoding($text,'UTF-8')||mb_strlen($text)>$limit||preg_match('/[\x00-\x1F\x7F]/u',$text)||strip_tags($text)!==$text)throw new RuntimeException('Isian varian harus teks biasa: nama maksimal 60, keterangan 300 karakter.');
    }
    $name=mb_strtolower(trim($row['id']['n']));
    if($name===''||isset($names[$name]))throw new RuntimeException('Nama varian wajib dan tidak boleh sama.');
    if(!is_string($row['img']??null)||($row['img']!==''&&!catalog_image($row['img'])))throw new RuntimeException('Foto varian tidak valid.');
    $ids[$row['uid']]=true;$names[$name]=true;
  }
  if(!empty($item['variants_enabled'])&&!$rows)throw new RuntimeException('Tambahkan minimal satu varian atau matikan pilihan varian.');
}
function menu_variants_input(array $input,?array $old): array {
  if(($input['variants_present']??'')!=='1')return ['variants_enabled'=>$old['variants_enabled']??false,'variants'=>$old['variants']??[]];
  foreach(array_keys($input)as$field)if(preg_match('/\Avariant_([0-9]+)_/D',(string)$field,$match)&&(int)$match[1]>=MENU_VARIANT_LIMIT)throw new DomainException('Maksimal 8 varian per produk.');
  $existing=array_column($old['variants']??[],null,'uid');$out=[];$seen=[];
  for($i=0;$i<MENU_VARIANT_LIMIT;$i++){
    $prefix='variant_'.$i.'_';$uid=$input[$prefix.'uid']??'';
    if($uid!==''&&(!isset($existing[$uid])||isset($seen[$uid])))throw new DomainException('Varian sudah berubah. Muat ulang halaman.');
    $seen[$uid]=true;
    if(($input[$prefix.'delete']??'')==='1')continue;
    $n=trim($input[$prefix.'n']??'');$ne=trim($input[$prefix.'ne']??'');$d=trim($input[$prefix.'d']??'');$de=trim($input[$prefix.'de']??'');
    if($uid===''&&$n===''&&$ne===''&&$d===''&&$de==='')continue;
    $out[]=['uid'=>$uid?:bin2hex(random_bytes(16)),'id'=>['n'=>$n,'d'=>$d],'en'=>['n'=>$ne?:$n,'d'=>$de?:$d],'img'=>!empty($input[$prefix.'photo_remove'])?'':($existing[$uid]['img']??''),'_slot'=>$i,'_order'=>(int)($input[$prefix.'order']??$i)];
  }
  usort($out,fn($a,$b)=>[$a['_order'],$a['_slot']]<=>[$b['_order'],$b['_slot']]);
  $result=['variants_enabled'=>!empty($input['variants_enabled']),'variants'=>$out];
  try{menu_variants_validate($result);}catch(RuntimeException $e){throw new DomainException($e->getMessage());}
  return $result;
}
function menu_variant_images(array $item): array {return array_values(array_filter(array_column($item['variants']??[],'img')));}
