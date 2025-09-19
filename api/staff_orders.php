<?php
// /api/staff_orders.php
// - POST ?login=1  {key}               -> $_SESSION['staff_authenticated']=true
// - GET             (auth)             -> offene/in_prep Bestellungen als JSON (IDs = STRING)

session_name('TSID');
session_set_cookie_params([
  'lifetime'=>0,'path'=>'/','domain'=>'','secure'=>true,'httponly'=>true,'samesite'=>'Lax'
]);
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Keys müssen aus config.php kommen
if (isset($_GET['login'])) {
  $in  = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
  $key = (string)($in['key'] ?? '');
  if (defined('STAFF_KEY') && $key !== '' && hash_equals((string)STAFF_KEY, $key)) {
    session_regenerate_id(true);
    $_SESSION['staff_authenticated'] = true;
    echo json_encode(['ok'=>true]); exit;
  }
  http_response_code(401); echo json_encode(['ok'=>false,'error'=>'invalid']); exit;
}

if (empty($_SESSION['staff_authenticated']) || $_SESSION['staff_authenticated'] !== true) {
  http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
}

// Konstanten sicherstellen
if (!defined('DATA_DIR'))        define('DATA_DIR',        __DIR__ . '/../data');
if (!defined('CSV_ORDERS'))      define('CSV_ORDERS',      DATA_DIR . '/orders.csv');
if (!defined('CSV_ORDER_ITEMS')) define('CSV_ORDER_ITEMS', DATA_DIR . '/order_items.csv');
if (!defined('CSV_MENU'))        define('CSV_MENU',        DATA_DIR . '/menu.csv');
if (!defined('CSV_TABLES'))      define('CSV_TABLES',      DATA_DIR . '/tables.csv');

if (!function_exists('csv_read_assoc')) {
  function csv_read_assoc(string $file): array {
    if (!is_file($file)) return [];
    $f = fopen($file, 'r'); if (!$f) return [];
    $rows = [];
    $headers = fgetcsv($f, 0, ';');
    if (!$headers) { fclose($f); return []; }
    while (($r = fgetcsv($f, 0, ';')) !== false) {
      $row = [];
      foreach ($headers as $i => $h) { $row[$h] = $r[$i] ?? ''; }
      $rows[] = $row;
    }
    fclose($f); return $rows;
  }
}

// Daten lesen
$orders = csv_read_assoc(CSV_ORDERS);
$items  = csv_read_assoc(CSV_ORDER_ITEMS);
$menu   = csv_read_assoc(CSV_MENU);
$tables = csv_read_assoc(CSV_TABLES);

// Maps
$menuById = [];
foreach ($menu as $m) { $menuById[(int)($m['id'] ?? 0)] = $m; }
$tblByCode = [];
foreach ($tables as $t) { $tblByCode[$t['code'] ?? ''] = $t['name'] ?? ''; }

// offene/in_prep
$validStatuses = ['open','in_prep'];
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
    'id'         => $oid, // STRING!
    'status'     => $st,
    'created_at' => $o['created_at'] ?? '',
    'table_name' => $tblByCode[$o['table_code'] ?? ''] ?: ($o['table_code'] ?? ''),
    'items'      => $arr
  ];
}

echo json_encode(['ok'=>true,'orders'=>$out], JSON_UNESCAPED_UNICODE);
