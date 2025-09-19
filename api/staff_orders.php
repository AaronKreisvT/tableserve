<?php
// /api/staff_orders.php
// - POST ?login=1  {key}      -> setzt $_SESSION['staff_authenticated']=true
// - GET             (auth)    -> liefert offene/aktive Bestellungen als JSON

// Einheitliche Session
session_name('TSID');
session_set_cookie_params([
  'lifetime'=>0,'path'=>'/','domain'=>'','secure'=>true,'httponly'=>true,'samesite'=>'Lax'
]);
session_start();

// ZUERST zentrale Config+Funktionen laden (kein lokales define von STAFF_KEY!)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Safety: STAFF_KEY muss aus config.php kommen
if (!defined('STAFF_KEY')) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'STAFF_KEY not defined']); exit;
}

// ---- LOGIN ----
if (isset($_GET['login'])) {
  $in  = json_decode(file_get_contents('php://input'), true) ?: [];
  $key = (string)($in['key'] ?? '');
  if ($key !== '' && hash_equals((string)STAFF_KEY, $key)) {
    session_regenerate_id(true);
    $_SESSION['staff_authenticated'] = true;
    echo json_encode(['ok'=>true]); exit;
  }
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'invalid']); exit;
}

// ---- AUTH GUARD für die Liste ----
if (empty($_SESSION['staff_authenticated']) || $_SESSION['staff_authenticated'] !== true) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
}

// ---- LISTE DER BESTELLUNGEN (vereinfachte, robuste Variante) ----
// Erwartete CSVs (kommen aus functions.php/ config.php):
// CSV_ORDERS: id;table_code;status;created_at
// CSV_ORDER_ITEMS: order_id;item_id;qty;notes
// CSV_MENU: id;name;price_cents;category;active
// CSV_TABLES: code;name

$orders = csv_read_assoc(CSV_ORDERS);
$items  = csv_read_assoc(CSV_ORDER_ITEMS);
$menu   = csv_read_assoc(CSV_MENU);
$tables = csv_read_assoc(CSV_TABLES);

$menuById = [];
foreach ($menu as $m) { $menuById[(int)($m['id']??0)] = $m; }
$tblByCode = [];
foreach ($tables as $t) { $tblByCode[$t['code'] ?? ''] = $t['name'] ?? ''; }

// nur relevante Stati anzeigen
$validStatuses = ['open','in_prep']; // „served“ & „cancelled“ sind erledigt
$out = [];
foreach ($orders as $o) {
  $st = $o['status'] ?? 'open';
  if (!in_array($st, $validStatuses, true)) continue;

  $oid = (string)($o['id'] ?? '');
  $arr = [];
  foreach ($items as $it) {
    if ((string)($it['order_id'] ?? '') !== $oid) continue;
    $def = $menuById[(int)($it['item_id'] ?? 0)] ?? null;
    $arr[] = [
      'qty'   => (int)($it['qty'] ?? 0),
      'name'  => $def ? ($def['name'] ?? ('#'.($it['item_id'] ?? ''))) : ('#'.($it['item_id'] ?? '')),
      'notes' => (string)($it['notes'] ?? '')
    ];
  }
  $out[] = [
    'id'         => (int)$oid,
    'status'     => $st,
    'created_at' => $o['created_at'] ?? '',
    'table_name' => $tblByCode[$o['table_code'] ?? ''] ?: ($o['table_code'] ?? ''),
    'items'      => $arr
  ];
}

echo json_encode(['ok'=>true,'orders'=>$out], JSON_UNESCAPED_UNICODE);
