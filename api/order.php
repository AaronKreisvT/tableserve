<?php
// /api/order.php — Bestellung vom Gast anlegen (ohne Login)
// Request-JSON: { table_code: "AB12CD34", items: [{item_id, qty, notes|null}, ...] }
// Response: { ok:true, order_id:"<TABLE>-<SUFFIX>" }

session_name('TSID');
session_set_cookie_params([
  'lifetime'=>0,'path'=>'/','domain'=>'','secure'=>true,'httponly'=>true,'samesite'=>'Lax'
]);
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Fallback-Konstanten (falls in functions.php nicht gesetzt)
if (!defined('DATA_DIR'))           define('DATA_DIR',           __DIR__ . '/../data');
if (!defined('CSV_TABLES'))         define('CSV_TABLES',         DATA_DIR . '/tables.csv');
if (!defined('CSV_MENU'))           define('CSV_MENU',           DATA_DIR . '/menu.csv');
if (!defined('CSV_ORDERS'))         define('CSV_ORDERS',         DATA_DIR . '/orders.csv');
if (!defined('CSV_ORDER_ITEMS'))    define('CSV_ORDER_ITEMS',    DATA_DIR . '/order_items.csv');

// CSV-Helfer, nur falls nicht vorhanden
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
if (!function_exists('csv_write_all')) {
  function csv_write_all(string $file, array $headers, array $rows): bool {
    @mkdir(dirname($file), 0775, true);
    $tmp = $file . '.tmp';
    $f = fopen($tmp, 'w'); if (!$f) return false;
    fputcsv($f, $headers, ';');
    foreach ($rows as $row) {
      $line = [];
      foreach ($headers as $h) { $line[] = $row[$h] ?? ''; }
      fputcsv($f, $line, ';');
    }
    fclose($f);
    return @rename($tmp, $file);
  }
}

// Lock für Bestellungen (eine gemeinsame Sperre für beide CSVs)
function orders_lock() {
  $lf = DATA_DIR . '/lock_orders.lock';
  @mkdir(dirname($lf), 0775, true);
  $h = fopen($lf, 'c'); if (!$h) return false;
  flock($h, LOCK_EX);
  return $h;
}
function orders_unlock($h) { if ($h) { flock($h, LOCK_UN); fclose($h); } }

// ID-Generator: Tischcode + sicherer Suffix; garantiert einzigartig innerhalb der CSV
function generate_order_id(string $tableCode, array $existingIds): string {
  // Kompakter sicherer Suffix: 6–8 Hex-Zeichen aus random_bytes
  for ($i=0; $i<500; $i++) {
    $suffix = bin2hex(random_bytes(3)); // 6 Hex-Zeichen
    $id = $tableCode . '-' . $suffix;
    if (empty($existingIds[$id])) return $id;
  }
  // Fallback, extrem unwahrscheinlich
  return $tableCode . '-' . bin2hex(random_bytes(6));
}

// Request einlesen
$in = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($in)) $in = [];

$table_code = isset($in['table_code']) ? trim((string)$in['table_code']) : '';
$items      = isset($in['items']) && is_array($in['items']) ? $in['items'] : [];

if ($table_code === '' || empty($items)) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>'invalid request']); exit;
}

// Dateien & Header sicherstellen
if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0775, true);
if (!is_file(CSV_ORDERS))       csv_write_all(CSV_ORDERS,       ['id','table_code','status','created_at'], []);
if (!is_file(CSV_ORDER_ITEMS))  csv_write_all(CSV_ORDER_ITEMS,  ['order_id','item_id','qty','notes'],      []);
if (!is_file(CSV_TABLES))       csv_write_all(CSV_TABLES,       ['code','name'],                            []);
if (!is_file(CSV_MENU))         csv_write_all(CSV_MENU,         ['id','name','price_cents','category','active'], []);

// Validierungen: Tisch & Menü
$tables = csv_read_assoc(CSV_TABLES);
$knownTable = false;
foreach ($tables as $t) { if (($t['code'] ?? '') === $table_code) { $knownTable = true; break; } }
if (!$knownTable) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>'unknown_table']); exit;
}

$menu = csv_read_assoc(CSV_MENU);
$menuActive = [];
foreach ($menu as $m) {
  if (($m['active'] ?? '') === '1') {
    $menuActive[(int)($m['id'] ?? 0)] = true;
  }
}

// Items säubern/prüfen
$clean = [];
foreach ($items as $it) {
  $iid  = (int)($it['item_id'] ?? 0);
  $qty  = (int)($it['qty'] ?? 0);
  $note = isset($it['notes']) ? (string)$it['notes'] : '';
  if ($iid <= 0 || $qty <= 0) continue;
  if (empty($menuActive[$iid])) continue; // nur aktive Artikel
  $clean[] = ['item_id'=>$iid, 'qty'=>$qty, 'notes'=>$note];
}
if (empty($clean)) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>'no_valid_items']); exit;
}

try {
  $lock = orders_lock();

  // IDs einlesen
  $existing = csv_read_assoc(CSV_ORDERS);
  $idSet = [];
  foreach ($existing as $o) { if (!empty($o['id'])) $idSet[$o['id']] = true; }

  // NEUE ID ERZEUGEN: <TABLE>-<HEX>
  $order_id = generate_order_id($table_code, $idSet);

  // Bestellung in orders.csv anfügen
  $now = date('d-m-Y H:i');
  $existing[] = [
    'id'         => $order_id,
    'table_code' => $table_code,
    'status'     => 'open',
    'created_at' => $now,
  ];
  if (!csv_write_all(CSV_ORDERS, ['id','table_code','status','created_at'], $existing)) {
    throw new Exception('write_orders_failed');
  }

  // Items anfügen (wir lesen, hängen dran, schreiben neu)
  $existingItems = csv_read_assoc(CSV_ORDER_ITEMS);
  foreach ($clean as $c) {
    $existingItems[] = [
      'order_id' => $order_id,
      'item_id'  => (string)$c['item_id'],
      'qty'      => (string)$c['qty'],
      'notes'    => $c['notes'],
    ];
  }
  if (!csv_write_all(CSV_ORDER_ITEMS, ['order_id','item_id','qty','notes'], $existingItems)) {
    throw new Exception('write_items_failed');
  }

  orders_unlock($lock);

  echo json_encode(['ok'=>true, 'order_id'=>$order_id], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  orders_unlock($lock ?? null);
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error']); // keine Details nach außen
}
