<?php
defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.');

const PO_STATUSES = [
  'pending' => 'Menunggu konfirmasi', 'confirmed' => 'Dikonfirmasi',
  'ready' => 'Siap diambil', 'completed' => 'Selesai',
  'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', 'expired' => 'Kedaluwarsa',
];
const PO_TRANSITIONS = [
  'pending' => ['confirmed', 'rejected', 'cancelled'],
  'confirmed' => ['ready', 'cancelled'], 'ready' => ['completed', 'cancelled'],
];

function po_require_staging(): void {
  // Fail closed: fitur demo belum boleh diakses di domain produksi.
  if (!RUETHIES_STAGING) { http_response_code(404); exit('Halaman tidak tersedia.'); }
  date_default_timezone_set('Asia/Jakarta');
  header('Cache-Control: private, no-store, max-age=0');
  header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
  header('X-Robots-Tag: noindex, nofollow');
}

function po_config(): array {
  static $config;
  if ($config !== null) return $config;
  $path = ruethies_data_dir() . '/po-config.php';
  if (!is_file($path)) throw new RuntimeException('PO belum dikonfigurasi.');
  $c = require $path;
  if (!is_array($c) || !str_starts_with($c['dsn'] ?? '', 'mysql:') || strlen($c['secret'] ?? '') < 64) {
    throw new RuntimeException('Konfigurasi PO tidak valid.');
  }
  return $config = $c;
}

function po_db(): PDO {
  static $db;
  if ($db) return $db;
  $c = po_config();
  $db = new PDO($c['dsn'], $c['user'], $c['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $db->exec('SET NAMES utf8mb4');
  return $db;
}

function po_query(PDO $db, string $sql, array $args = []): PDOStatement {
  $s = $db->prepare($sql); $s->execute($args); return $s;
}
function po_money(int $amount): string { return 'Rp' . number_format($amount, 0, ',', '.'); }
function po_date(int $time): string { return date('d/m/Y H:i', $time) . ' WIB'; }
function po_text($value, string $label, int $min, int $max): string {
  if (!is_string($value)) throw new DomainException("$label tidak valid.");
  $value = trim($value);
  if (mb_strlen($value) < $min || mb_strlen($value) > $max) throw new DomainException("$label harus {$min}–{$max} karakter.");
  return $value;
}
function po_int($value, int $min, int $max): int {
  $n = filter_var($value, FILTER_VALIDATE_INT);
  if ($n === false || $n < $min || $n > $max) throw new DomainException('Angka atau jumlah tidak valid.');
  return $n;
}
function po_phone($value): string {
  $raw = po_text($value, 'Nomor WhatsApp', 10, 24);
  if (!preg_match('/\A\+?[0-9 ()-]+\z/', $raw)) throw new DomainException('Nomor WhatsApp tidak valid.');
  $phone = preg_replace('/[^0-9]/', '', $raw);
  if (str_starts_with($phone, '0')) $phone = '62' . substr($phone, 1);
  if (!preg_match('/\A62[0-9]{8,13}\z/', $phone)) throw new DomainException('Gunakan nomor Indonesia, misalnya 081234567890.');
  return $phone;
}
function po_time($value): int {
  if (!is_string($value)) throw new DomainException('Tanggal tidak valid.');
  $d = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone('Asia/Jakarta'));
  if (!$d || $d->format('Y-m-d\TH:i') !== $value) throw new DomainException('Tanggal tidak valid.');
  return $d->getTimestamp();
}

function po_slot(PDO $db, int $id, bool $lock = false): array {
  $slot = po_query($db, 'SELECT * FROM po_slots WHERE id=?' . ($lock ? ' FOR UPDATE' : ''), [$id])->fetch();
  if (!$slot) throw new DomainException('Jadwal PO tidak ditemukan.');
  return $slot;
}
function po_slot_open(array $slot): bool {
  return (bool)$slot['is_open'] && (int)$slot['opens_at'] <= time() && (int)$slot['closes_at'] > time();
}
function po_save_slot(PDO $db, array $input): int {
  $id = po_int($input['id'] ?? 0, 0, PHP_INT_MAX);
  $title = po_text($input['title'] ?? '', 'Nama jadwal', 3, 100);
  $opens = po_time($input['opens_at'] ?? ''); $closes = po_time($input['closes_at'] ?? '');
  $from = po_time($input['pickup_from'] ?? ''); $until = po_time($input['pickup_until'] ?? '');
  if (!($opens < $closes && $closes <= $from && $from < $until) || $until <= time()) {
    throw new DomainException('Urutan waktu: buka PO < tutup PO ≤ mulai pengambilan < selesai pengambilan.');
  }
  $db->beginTransaction();
  try {
    if ($id) {
      po_slot($db, $id, true);
      if (po_query($db, 'SELECT id FROM po_orders WHERE slot_id=? LIMIT 1', [$id])->fetch()) {
        throw new DomainException('Jadwal sudah memiliki pesanan. Buat jadwal baru untuk mengubah tanggal; buka/tutup masih bisa diubah.');
      }
      po_query($db, 'UPDATE po_slots SET title=?,opens_at=?,closes_at=?,pickup_from=?,pickup_until=? WHERE id=?', [$title,$opens,$closes,$from,$until,$id]);
    } else {
      po_query($db, 'INSERT INTO po_slots(title,opens_at,closes_at,pickup_from,pickup_until) VALUES(?,?,?,?,?)', [$title,$opens,$closes,$from,$until]);
      $id = (int)$db->lastInsertId();
    }
    $db->commit(); return $id;
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
}
function po_toggle_slot(PDO $db, int $id, int $open): void {
  po_int($open, 0, 1);
  $db->beginTransaction();
  try {
    po_slot($db, $id, true);
    po_query($db, 'UPDATE po_slots SET is_open=? WHERE id=?', [$open,$id]);
    $db->commit();
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
}
function po_save_product(PDO $db, array $input): int {
  $id = po_int($input['id'] ?? 0, 0, PHP_INT_MAX);
  $sid = po_int($input['slot_id'] ?? 0, 1, PHP_INT_MAX);
  $name = po_text($input['name'] ?? '', 'Nama produk', 2, 100);
  $price = po_int($input['price'] ?? '', 1000, 10000000);
  $quota = po_int($input['quota'] ?? '', 1, 10000);
  $active = isset($input['active']) ? 1 : 0;
  $db->beginTransaction();
  try {
    po_slot($db, $sid, true);
    if ($id) {
      $row = po_query($db, 'SELECT * FROM po_products WHERE id=? AND slot_id=? FOR UPDATE', [$id,$sid])->fetch();
      if (!$row || $quota < (int)$row['reserved']) throw new DomainException('Kuota tidak boleh di bawah jumlah yang sudah dikonfirmasi.');
      po_query($db, 'UPDATE po_products SET name=?,price=?,quota=?,active=? WHERE id=?', [$name,$price,$quota,$active,$id]);
    } else {
      if ((int)po_query($db,'SELECT COUNT(*) FROM po_products WHERE slot_id=?',[$sid])->fetchColumn() >= 100) throw new DomainException('Maksimal 100 produk per jadwal.');
      po_query($db, 'INSERT INTO po_products(slot_id,name,price,quota,active) VALUES(?,?,?,?,?)', [$sid,$name,$price,$quota,$active]);
      $id = (int)$db->lastInsertId();
    }
    $db->commit(); return $id;
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
}

// Harga/nama selalu berasal dari database, bukan input browser.
function po_quote(PDO $db, array $input): array {
  $slot = po_slot($db, po_int($input['slot_id'] ?? 0, 1, PHP_INT_MAX));
  if (!po_slot_open($slot)) throw new DomainException('Jadwal PO belum dibuka atau sudah ditutup.');
  $qty = $input['qty'] ?? [];
  if (!is_array($qty) || count($qty) > 100) throw new DomainException('Pilihan produk tidak valid.');
  $products = po_query($db, 'SELECT * FROM po_products WHERE slot_id=? AND active=1 ORDER BY id', [$slot['id']])->fetchAll();
  $selected = [];
  foreach ($qty as $id => $n) {
    $n = po_int($n === '' ? 0 : $n, 0, 20);
    if ($n) $selected[po_int($id, 1, PHP_INT_MAX)] = $n;
  }
  if (!$selected || count($selected) > 12 || array_sum($selected) > 100) throw new DomainException('Pilih 1–12 produk, maksimal 20 per produk dan 100 item total.');
  $lines = []; $total = 0;
  foreach ($products as $p) {
    if (!isset($selected[$p['id']])) continue;
    $n = $selected[$p['id']];
    if ($n > (int)$p['quota'] - (int)$p['reserved']) throw new DomainException('Kuota ' . $p['name'] . ' tidak mencukupi.');
    $lines[] = ['product_id' => (int)$p['id'], 'name' => $p['name'], 'price' => (int)$p['price'], 'qty' => $n];
    $total += (int)$p['price'] * $n;
  }
  if (count($lines) !== count($selected)) throw new DomainException('Ada produk yang tidak tersedia pada jadwal ini.');
  return ['slot' => $slot, 'lines' => $lines, 'total' => $total];
}
function po_quote_hash(array $quote): string {
  return hash('sha256', json_encode([$quote['slot']['id'], $quote['slot']['title'], $quote['slot']['pickup_from'], $quote['slot']['pickup_until'], $quote['lines']], JSON_THROW_ON_ERROR));
}
function po_customer(array $input): array {
  if (($input['consent'] ?? '') !== 'yes') throw new DomainException('Setujui penggunaan kontak untuk pengajuan ini.');
  return [po_text($input['name'] ?? '', 'Nama', 2, 80), po_phone($input['phone'] ?? ''), po_text($input['note'] ?? '', 'Catatan', 0, 500)];
}
function po_rate(PDO $db, string $key, int $limit): void {
  $now = time(); $bucket = hash('sha256', $key . ':' . intdiv($now, 900));
  po_query($db, 'DELETE FROM po_rate WHERE expires_at<? LIMIT 100', [$now]);
  $db->beginTransaction();
  try {
    po_query($db, 'INSERT INTO po_rate(bucket_key,attempts,expires_at) VALUES(?,1,?) ON DUPLICATE KEY UPDATE attempts=attempts+1', [$bucket,$now+1800]);
    $count = (int)po_query($db, 'SELECT attempts FROM po_rate WHERE bucket_key=?', [$bucket])->fetchColumn();
    $db->commit();
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
  if ($count > $limit) throw new DomainException('Terlalu banyak percobaan. Silakan coba lagi dalam 15 menit.');
}
function po_submit(PDO $db, array $input, string $nonce, string $quoteHash, string $secret): string {
  if (!preg_match('/\A[a-f0-9]{64}\z/', $nonce) || strlen($secret) < 64) throw new DomainException('Sesi pengajuan tidak valid.');
  $requestHash = hash('sha256', $nonce);
  $token = hash_hmac('sha256', 'status:' . $nonce, $secret);
  if (po_query($db, 'SELECT id FROM po_orders WHERE request_hash=?', [$requestHash])->fetch()) return $token;
  [$name,$phone,$note] = po_customer($input);
  po_rate($db, 'phone:' . hash_hmac('sha256', $phone, $secret), 5);
  $db->beginTransaction();
  try {
    // Semua submit/konfirmasi/edit kuota untuk jadwal yang sama memakai row lock ini.
    // Serialisasi nomor yang sama juga berlaku lintas jadwal (batas pending).
    po_query($db, 'INSERT INTO po_rate(bucket_key,attempts,expires_at) VALUES(?,0,?) ON DUPLICATE KEY UPDATE expires_at=?',
      [hash_hmac('sha256','submit-lock:' . $phone,$secret),time()+1800,time()+1800]);
    po_slot($db, po_int($input['slot_id'] ?? 0, 1, PHP_INT_MAX), true);
    if (po_query($db, 'SELECT id FROM po_orders WHERE request_hash=?', [$requestHash])->fetch()) { $db->commit(); return $token; }
    $q = po_quote($db, $input);
    if (!hash_equals(po_quote_hash($q), $quoteHash)) throw new DomainException('Produk, harga, atau jadwal berubah. Kembali dan periksa ringkasan baru.');
    $pending = (int)po_query($db, "SELECT COUNT(*) FROM po_orders WHERE phone=? AND status='pending' AND expires_at>?", [$phone,time()])->fetchColumn();
    if ($pending >= 3) throw new DomainException('Nomor ini sudah memiliki 3 pengajuan yang menunggu konfirmasi.');
    $code = 'PO-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    po_query($db, 'INSERT INTO po_orders(slot_id,code,token_hash,request_hash,customer_name,phone,note,total,created_at,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?)',
      [$q['slot']['id'],$code,hash('sha256',$token),$requestHash,$name,$phone,$note,$q['total'],time(),$q['slot']['pickup_from']]);
    $id = (int)$db->lastInsertId();
    foreach ($q['lines'] as $line) po_query($db, 'INSERT INTO po_lines(order_id,product_id,name,price,qty) VALUES(?,?,?,?,?)', [$id,$line['product_id'],$line['name'],$line['price'],$line['qty']]);
    po_query($db, 'INSERT INTO po_history(order_id,status,actor,created_at) VALUES(?,?,?,?)', [$id,'pending','Pelanggan demo',time()]);
    $db->commit(); return $token;
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
}

function po_expire(PDO $db): void {
  $slots = po_query($db, "SELECT DISTINCT slot_id FROM po_orders WHERE status='pending' AND expires_at<=?", [time()])->fetchAll();
  foreach ($slots as $row) {
    $db->beginTransaction();
    try {
      po_slot($db, (int)$row['slot_id'], true);
      $expired = po_query($db, "SELECT id FROM po_orders WHERE slot_id=? AND status='pending' AND expires_at<=? FOR UPDATE", [$row['slot_id'],time()])->fetchAll();
      foreach ($expired as $order) {
        po_query($db, "UPDATE po_orders SET status='expired' WHERE id=?", [$order['id']]);
        po_query($db, 'INSERT INTO po_history(order_id,status,actor,created_at) VALUES(?,?,?,?)', [$order['id'],'expired','Sistem',time()]);
      }
      $db->commit();
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
  }
}
function po_transition(PDO $db, int $id, string $expected, string $target, string $actor): void {
  $sid = po_query($db, 'SELECT slot_id FROM po_orders WHERE id=?', [$id])->fetchColumn();
  if (!$sid) throw new DomainException('Pesanan tidak ditemukan.');
  po_expire($db);
  $db->beginTransaction();
  try {
    po_slot($db, (int)$sid, true);
    $order = po_query($db, 'SELECT * FROM po_orders WHERE id=? FOR UPDATE', [$id])->fetch();
    if ($order['status'] !== $expected || !in_array($target, PO_TRANSITIONS[$order['status']] ?? [], true)) throw new DomainException('Status sudah berubah atau tindakan tidak diizinkan. Muat ulang daftar.');
    if ($order['status'] === 'pending' && (int)$order['expires_at'] <= time()) throw new DomainException('Pengajuan sudah kedaluwarsa.');
    $lines = po_query($db, 'SELECT * FROM po_lines WHERE order_id=? ORDER BY product_id', [$id])->fetchAll();
    if ($target === 'confirmed') {
      foreach ($lines as $line) {
        $s = po_query($db, 'UPDATE po_products SET reserved=reserved+? WHERE id=? AND active=1 AND reserved+?<=quota', [$line['qty'],$line['product_id'],$line['qty']]);
        if ($s->rowCount() !== 1) throw new DomainException('Kuota ' . $line['name'] . ' tidak cukup atau produk dinonaktifkan.');
      }
    }
    if ($target === 'cancelled' && in_array($order['status'], ['confirmed','ready'], true)) {
      foreach ($lines as $line) {
        if (po_query($db, 'UPDATE po_products SET reserved=reserved-? WHERE id=? AND reserved>=?', [$line['qty'],$line['product_id'],$line['qty']])->rowCount() !== 1) throw new RuntimeException('Kuota tidak konsisten.');
      }
    }
    po_query($db, 'UPDATE po_orders SET status=? WHERE id=?', [$target,$id]);
    po_query($db, 'INSERT INTO po_history(order_id,status,actor,created_at) VALUES(?,?,?,?)', [$id,$target,mb_substr($actor,0,80),time()]);
    $db->commit();
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
}
function po_order(PDO $db, string $token): ?array {
  if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) return null;
  po_expire($db);
  $row = po_query($db, 'SELECT o.*,s.title,s.pickup_from,s.pickup_until FROM po_orders o JOIN po_slots s ON s.id=o.slot_id WHERE token_hash=?', [hash('sha256',$token)])->fetch();
  if (!$row) return null;
  $row['lines'] = po_query($db, 'SELECT name,price,qty FROM po_lines WHERE order_id=? ORDER BY id', [$row['id']])->fetchAll();
  return $row;
}
