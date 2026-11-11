<?php
define('RUETHIES_ADMIN', true);
require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';
require __DIR__ . '/po-lib.php';
po_require_staging();
session_cache_limiter('');
ruethies_session_start();
$me = current_user();
if (!$me) { header('Location: index.php?p=login'); exit; }
$error = ''; $ready = false; $slots = []; $products = []; $orders = []; $selected = null; $edit = null;
$notice = $_SESSION['po_notice'] ?? ''; unset($_SESSION['po_notice']);
$sid = max(0, (int)filter_var($_GET['slot'] ?? ($_POST['slot_id'] ?? 0), FILTER_VALIDATE_INT));
$status = is_string($_GET['status'] ?? null) && isset(PO_STATUSES[$_GET['status']]) ? $_GET['status'] : '';
$paymentFilter = is_string($_GET['payment']??null) && isset(PO_PAYMENT_LABELS[$_GET['payment']]) ? $_GET['payment'] : '';
$search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']),0,80) : '';
$page = max(1, min(10000, (int)filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT)));
$view=is_string($_GET['view']??null)?$_GET['view']:'';
if(!in_array($view,['pesanan','jadwal','produk','notifikasi'],true)) $view=$sid?(isset($_GET['edit'])?'jadwal':'produk'):'pesanan';
$action=is_string($_POST['action']??null)?$_POST['action']:'';
$actionViews=['save-slot'=>'jadwal','toggle-slot'=>'jadwal','save-product'=>'produk','wa-retry'=>'notifikasi','midtrans-check'=>'pesanan','manual-fulfill'=>'pesanan'];
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&isset($actionViews[$action]))$view=$actionViews[$action];
if(!is_owner($me)&&in_array($view,['rekening','notifikasi'],true)){http_response_code(403);$view='pesanan';$error='Bagian ini hanya tersedia untuk pemilik.';}
$orderId=max(0,(int)filter_var($_GET['order']??($_POST['order_id']??0),FILTER_VALIDATE_INT));
$productId=max(0,(int)filter_var($_GET['product']??($_POST['id']??0),FILTER_VALIDATE_INT));
$scheduleEditor=$view==='jadwal'&&is_owner($me)&&(isset($_GET['edit'])||$action==='save-slot');
$productEditor=$view==='produk'&&is_owner($me)&&(isset($_GET['product'])||$action==='save-product');
if($action==='save-slot'&&!$sid)$sid=max(0,(int)filter_var($_POST['id']??0,FILTER_VALIDATE_INT));
$total = 0; $counts = []; $stats=['pending'=>0,'review'=>0,'ready'=>0,'completed'=>0];
$catalog = []; $choices = []; $editingProduct=null; $detailOrder=null; $scheduleLocked=false;
$payment = po_payment_defaults();
$waCounts=[];$waJobs=[];$waEnabled=false;$waHeartbeat=0;$waConfigError=false;$waTestMode=false;
function po_admin_url(string $view,array $params=[]): string { return 'po.php?'.http_build_query(['view'=>$view]+$params); }
try {
  $db = po_db();
  if($view==='produk') $catalog = po_catalog();
  po_expire($db);
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!ruethies_csrf_ok()) throw new DomainException('Sesi formulir tidak valid. Muat ulang halaman.');
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['save-payment','transition','manual-payment','manual-cancel'], true)) throw new DomainException('Alur demo dan transfer manual sudah dinonaktifkan.');
    if ($action === 'save-product' && (po_slot($db, po_int($_POST['slot_id'] ?? 0,1,PHP_INT_MAX))['flow_version'] ?? 'legacy') !== 'midtrans_v1') throw new DomainException('Jadwal lama sudah dinonaktifkan.');
    if ($action === 'manual-fulfill' && po_query($db, 'SELECT flow_version FROM po_orders WHERE id=?', [po_int($_POST['order_id'] ?? 0,1,PHP_INT_MAX)])->fetchColumn() !== 'midtrans_v1') throw new DomainException('Pesanan lama sudah dinonaktifkan.');
    if($action==='midtrans-check'){
      if(!is_owner($me))throw new DomainException('Hanya pemilik yang dapat memeriksa pembayaran.');
      po_rate($db,'midtrans-admin:'.$me['name'],10);
      try{po_mt_reconcile($db,po_int($_POST['order_id']??0,1,PHP_INT_MAX),po_mt_settings(po_config()));}catch(RuntimeException $e){throw new DomainException('Midtrans belum merespons. Status dan kuota tetap menunggu verifikasi.');}
      $_SESSION['po_notice']='Status diperiksa melalui Midtrans Sandbox.';
    } elseif ($action==='manual-fulfill') {
      po_manual_fulfill($db,po_int($_POST['order_id']??0,1,PHP_INT_MAX),po_text($_POST['expected']??'','Status',1,16),po_text($_POST['target']??'','Tujuan',1,16),$me);
      $_SESSION['po_notice']='Status pemenuhan diperbarui; notifikasi masuk antrean.';
    } elseif ($action==='wa-retry') {
      po_rate($db,'manual-verification:'.$me['name'],5);
      po_manual_verify_identity($me,$_POST);
      po_wa_retry($db,po_int($_POST['job_id']??0,1,PHP_INT_MAX),po_int($_POST['version']??-1,0,PHP_INT_MAX),$me);
      $_SESSION['po_notice']='Tindakan disimpan. Notifikasi masuk antrean pengiriman.';
    } else {
      if (!is_owner($me)) throw new DomainException('Pengaturan jadwal dan produk hanya untuk pemilik.');
      if ($action === 'save-slot') {
        $sid = po_save_slot($db, $_POST);
        $_SESSION['po_notice'] = 'Jadwal Midtrans Sandbox disimpan. Lengkapi produk sebelum membuka jadwal.';
      }
      elseif ($action === 'toggle-slot') {
        $sid = po_int($_POST['slot_id'] ?? 0,1,PHP_INT_MAX);
        po_toggle_slot($db, $sid, po_int($_POST['is_open'] ?? 0,0,1));
        $_SESSION['po_notice'] = 'Pengaturan buka/tutup diperbarui.';
      } elseif ($action === 'save-product') {
        po_save_product($db, $_POST); $sid = (int)$_POST['slot_id']; $_SESSION['po_notice'] = 'Produk PO disimpan. Pesanan lama tetap memakai harga yang tercatat saat pengajuan.';
      } else throw new DomainException('Tindakan tidak valid.');
    }
    if($action==='save-slot')$view=empty($_POST['id'])?'produk':'jadwal';
    $returnParams=in_array($view,['produk','jadwal'],true)&&$sid?['slot'=>$sid]:[];
    if($view==='pesanan'&&$orderId)$returnParams['order']=$orderId;
    header('Location: '.po_admin_url($view,$returnParams),true,303);exit;
  }
  $ready = true;
} catch (RuethiesReauthRequired $e) { header('Location: index.php?p=verifikasi',true,303);exit; }
catch (DomainException $e) { $error = $e->getMessage(); $ready = true; }
catch (Throwable $e) { http_response_code(503); $error = 'Data pesanan belum tersedia. Silakan coba lagi atau hubungi pengelola website.'; error_log('PO admin: storage unavailable'); }
if ($ready) {
  try {
    if(is_owner($me)&&$view==='rekening') $payment=po_payment_settings($db);
    if(is_owner($me)&&$view==='notifikasi') {
      $waCounts=po_query($db,'SELECT status,COUNT(*) AS n FROM po_whatsapp_outbox GROUP BY status')->fetchAll();
      $waJobs=po_query($db,"SELECT w.*,o.code FROM po_whatsapp_outbox w JOIN po_orders o ON o.id=w.order_id WHERE w.status IN ('failed','uncertain') ORDER BY w.id DESC LIMIT 20")->fetchAll();
      try { $waSettings=po_wa_settings(po_config());$waEnabled=$waSettings['enabled'];$waTestMode=!empty($waSettings['test_recipient']); } catch(Throwable $e) { $waConfigError=true; }
      $waHeartbeat=(int)(jread(ruethies_data_dir().'/po-wa-heartbeat.json',[])['last_success']??0);
    }
    if(in_array($view,['pesanan','jadwal','produk'],true)) {
      $slots=po_query($db,"SELECT * FROM po_slots WHERE flow_version='midtrans_v1' ORDER BY pickup_from DESC LIMIT 100")->fetchAll();
      foreach($slots as $s)if((int)$s['id']===$sid)$selected=$s;
    }
    if($view==='jadwal'&&$scheduleEditor) {
      if($sid&&!$selected){http_response_code(404);$error='Jadwal tidak ditemukan.';$scheduleEditor=false;}
      elseif($selected){$edit=$selected;$scheduleLocked=(bool)po_query($db,'SELECT id FROM po_orders WHERE slot_id=? LIMIT 1',[$sid])->fetch();}
    }
    if($view==='produk'&&$selected) {
      $products=po_query($db,'SELECT * FROM po_products WHERE slot_id=? ORDER BY id',[$sid])->fetchAll();
      $used=array_column($products,'menu_uid');
      $choices=array_filter($catalog,fn($m)=>po_catalog_visible($m)&&!in_array($m['uid'],$used,true));
      foreach($products as $product)if((int)$product['id']===$productId)$editingProduct=$product;
      if($productEditor&&$productId&&!$editingProduct){http_response_code(404);$error='Produk tidak ditemukan pada jadwal ini.';}
    }
    if($view==='pesanan') {
      if($orderId) {
        $detailOrder=po_query($db,'SELECT o.*,s.title,s.pickup_from,s.pickup_until FROM po_orders o JOIN po_slots s ON s.id=o.slot_id WHERE o.id=? AND o.flow_version=\'midtrans_v1\'',[$orderId])->fetch()?:null;
        if(!$detailOrder){http_response_code(404);$error='Pesanan tidak ditemukan.';}
      } else {
        $where=["o.flow_version='midtrans_v1'"];$args=[];
        if($status){$where[]='o.status=?';$args[]=$status;}
        if($paymentFilter){$where[]='o.payment_status=?';$args[]=$paymentFilter;}
        if($sid){$where[]='o.slot_id=?';$args[]=$sid;}
        if($search!==''){$where[]='(o.code LIKE ? OR o.customer_name LIKE ? OR o.phone LIKE ?)';array_push($args,'%'.$search.'%','%'.$search.'%','%'.$search.'%');}
        $filter=$where?' WHERE '.implode(' AND ',$where):'';
        $total=(int)po_query($db,'SELECT COUNT(*) FROM po_orders o'.$filter,$args)->fetchColumn();
        $orders=po_query($db,'SELECT o.*,s.title,s.pickup_from,s.pickup_until FROM po_orders o JOIN po_slots s ON s.id=o.slot_id'.$filter.' ORDER BY o.id DESC LIMIT 25 OFFSET '.(($page-1)*25),$args)->fetchAll();
        $counts=po_query($db,'SELECT status,payment_status,COUNT(*) AS n FROM po_orders WHERE flow_version=\'midtrans_v1\' GROUP BY status,payment_status')->fetchAll();
        foreach($counts as $c){if(isset($stats[$c['status']]))$stats[$c['status']]+=(int)$c['n'];if($c['payment_status']==='paid_review')$stats['review']+=(int)$c['n'];}
      }
    }
  }catch(Throwable $e){$ready=false;$error='Data pesanan belum dapat dimuat. Silakan coba lagi.';}
}
$csrf = ruethies_csrf();
function po_datetime_value(?array $row,string $key): string { if(($_POST['action']??'')==='save-slot'&&is_string($_POST[$key]??null))return $_POST[$key];return $row?date('Y-m-d\TH:i',(int)$row[$key]):''; }
$poLabels=['pesanan'=>'Pesanan PO','jadwal'=>'Jadwal PO','produk'=>'Produk PO','notifikasi'=>'Notifikasi WhatsApp'];
$poIntros=['pesanan'=>'Pantau pesanan dan tindak lanjuti pembayaran pelanggan.','jadwal'=>'Atur periode pemesanan dan waktu pengambilan.','produk'=>'Kelola harga, kuota, dan batas pembelian setiap jadwal.','rekening'=>'Atur rekening yang tampil pada invoice pesanan baru.','notifikasi'=>'Pantau pengiriman pesan dan tindak lanjuti notifikasi yang perlu diperiksa.'];
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title><?= h($poLabels[$view]) ?> — Ruethies Admin</title><link rel="stylesheet" href="admin.css?v=20261110-sync"><link rel="stylesheet" href="po-admin.css?v=20261108-po-ui"><script src="admin.js?v=20261110-sync" defer></script></head>
<body><a class="skip-admin" href="#admin-main">Langsung ke konten</a><div class="shell">
<aside class="side" aria-label="Navigasi admin"><div class="mark"><span class="sealmini">N°51</span><div><b>Ruethies Admin</b><span>PATISSERIE · CIKARANG</span></div></div>
<nav class="nav"><p class="navcap">Kelola</p><a href="index.php"><?= icon('menu') ?> Menu</a><a href="index.php?p=galeri"><?= icon('img') ?> Galeri</a><a href="index.php?p=faq"><?= icon('check') ?> FAQ</a><a class="on" aria-current="page" href="po.php"><?= icon('tag') ?> Pre-order</a><?php if(is_owner($me)): ?><a href="index.php?p=pengaturan"><?= icon('gear') ?> Pengaturan</a><?php endif; ?><p class="navcap">Lainnya</p><a href="../" target="_blank" rel="noopener"><?= icon('web') ?> Lihat web</a></nav>
<div class="who"><div class="who-id"><?= icon('user',16) ?><div><b><?= h($me['name']) ?></b><small><?= is_owner($me)?'Pemilik':'Staf' ?></small></div></div><form method="post" action="index.php"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="aksi" value="logout"><button class="ghost w100 outbtn"><?= icon('out',16) ?> Keluar</button></form></div><p class="sidefoot">Ruethies Patisserie<br>Kelola pre-order</p></aside>
<div class="maincol"><header class="topbar"><span class="sealmini">N°51</span><b>Ruethies Admin</b><span class="sp"></span><a class="ticon" href="../" target="_blank" rel="noopener" aria-label="Lihat website"><?= icon('web',18) ?></a><form method="post" action="index.php"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="aksi" value="logout"><button class="ticon" aria-label="Keluar dari admin"><?= icon('out',18) ?></button></form></header>
<main class="main" id="admin-main"><nav class="mnav" aria-label="Navigasi admin mobile"><a href="index.php">Menu</a><a href="index.php?p=galeri">Galeri</a><a href="index.php?p=faq">FAQ</a><a class="on" href="po.php" aria-current="page">Pre-order</a><?php if(is_owner($me)): ?><a href="index.php?p=pengaturan">Pengaturan</a><?php endif; ?></nav>
<div class="pagehead"><div><p class="eyebrow">RUETHIES · PRE-ORDER</p><h1><?= h($poLabels[$view]) ?></h1><p class="sub"><?= h($poIntros[$view]) ?></p></div><a class="btn ghost" href="/pre-order/" target="_blank" rel="noopener"><?= icon('web',16) ?> Halaman pelanggan</a></div>
<nav class="po-tabs" aria-label="Bagian pre-order"><?php foreach($poLabels as$key=>$label): if(!is_owner($me)&&in_array($key,['rekening','notifikasi'],true))continue; ?><a class="<?= $view===$key?'on':'' ?>" href="<?= h(po_admin_url($key)) ?>" <?= $view===$key?'aria-current="page"':'' ?>><?= h(['pesanan'=>'Pesanan','jadwal'=>'Jadwal','produk'=>'Produk','rekening'=>'Rekening','notifikasi'=>'WhatsApp'][$key]) ?></a><?php endforeach; ?></nav>
<p class="po-environment">Mode Sandbox · Pembayaran simulasi.</p>
<?php if($error): ?><p class="err" role="alert"><?= h($error) ?></p><?php endif; ?><?php if($notice): ?><p class="okmsg" role="status"><?= h($notice) ?></p><?php endif; ?>
<?php if($ready): require __DIR__.'/po-view-'.$view.'.php'; endif; ?>
</main></div></div></body></html>
