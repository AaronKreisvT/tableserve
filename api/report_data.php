<?php
// /api/report_data.php — Aggregierte Auswertung je Getränk
// GET-Parameter (optional): from=YYYY-MM-DD, to=YYYY-MM-DD
// Antwort: { ok:true, period:"YYYY-MM-DD bis YYYY-MM-DD", items:[{item_id,name,category,total_qty}] }

session_name('TSID');
session_set_cookie_params([
  'lifetime'=>0,'path'=>'/','domain'=>'','secure'=>true,'httponly'=>true,'samesite'=>'Lax'
]);
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Zugriff: Staff ODER Admin
$okStaff = !empty($_SESSION['staff_authenticated']) && $_SESSION['staff_authenticated'] === true;
$okAdmin = !empty($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
if (!$okStaff && !$okAdmin) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }

// Konstanten (Fallbacks)
if (!defined('DATA_DIR'))        define('DATA_DIR',        __DIR__ . '/../data');
if (!defined('CSV_ORDERS'))      define('CSV_ORDERS',      DATA_DIR . '/orders.csv');
if (!defined('CSV_ORDER_ITEMS')) define('CSV_ORDER_ITEMS', DATA_DIR . '/order_items.csv');
if (!defined('CSV_MENU'))        define('CSV_MENU',        DATA_DIR . '/menu.csv');

// CSV-Reader (nur falls nicht vorhanden)
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

// Zeitraum ermitteln (default = heute)
$from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$to   = isset($_GET['to'])   ? trim((string)$_GET['to'])   : '';

$now = new DateTime('now');
if ($from === '' && $to === '') {
  $from = $now->format('Y-m-d');
  $to   = $from;
}
if ($from !== '' && $to === '') $to = $from;
if ($to   !== '' && $from === '') $from = $to;

// Start/Ende Timestamps bauen
$start = strtotime($from . ' 00:00:00');
$end   = strtotime($to   . ' 23:59:59');
if ($start === false || $end === false) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'bad period']); exit;
}

// created_at tolerant parsen
function ts_parse_created_at(?string $s): ?int {
  if (!$s) return null;
  $s = trim($s);
  // 1) d-m-Y H:i (neues Format)
  $dt = DateTime::createFromFormat('d-m-Y H:i', $s);
  if ($dt instanceof DateTime) return $dt->getTimestamp();
  // 2) Y-m-d H:i:s (altes Format)
  $dt = DateTime::createFromFormat('Y-m-d H:i:s', $s);
  if ($dt instanceof DateTime) return $dt->getTimestamp();
  // 3) Y-m-d H:i (falls Sekunden fehlten)
  $dt = DateTime::createFromFormat('Y-m-d H:i', $s);
  if ($dt instanceof DateTime) return $dt->getTimestamp();
  // 4) Fallback: strtotime (kann schätzen)
  $ts = strtotime($s);
  return $ts === false ? null : $ts;
}

// Daten holen
$orders = csv_read_assoc(CSV_ORDERS);
$items  = csv_read_assoc(CSV_ORDER_ITEMS);
$menu   = csv_read_assoc(CSV_MENU);

// Map Menü
$menuMap = []; // id => ['name','category']
foreach ($menu as $m) {
  $id  = (int)($m['id'] ?? 0);
  if ($id <= 0) continue;
  $menuMap[$id] = [
    'name'     => $m['name'] ?? ('#'.$id),
    'category' => $m['category'] ?? ''
  ];
}

// Erlaubte Stati (alles außer "cancelled")
$ignore = ['cancelled'];

// Filter passende Order-IDs im Zeitraum
$allowedOrderIds = [];
foreach ($orders as $o) {
  $st = (string)($o['status'] ?? 'open');
  if (in_array($st, $ignore, true)) continue;

  $ts = ts_parse_created_at($o['created_at'] ?? '');
  if ($ts === null) continue;
  if ($ts < $start || $ts > $end) continue;

  $allowedOrderIds[(string)($o['id'] ?? '')] = true;
}

// Aggregation
$agg = []; // item_id => qty
foreach ($items as $it) {
  $oid = (string)($it['order_id'] ?? '');
  if ($oid === '' || empty($allowedOrderIds[$oid])) continue;

  $iid = (int)($it['item_id'] ?? 0);
  $qty = (int)($it['qty'] ?? 0);
  if ($iid <= 0 || $qty <= 0) continue;

  if (!isset($agg[$iid])) $agg[$iid] = 0;
  $agg[$iid] += $qty;
}

// Ausgabe vorbereiten
$out = [];
foreach ($agg as $iid => $qty) {
  $def = $menuMap[$iid] ?? ['name'=>'#'.$iid, 'category'=>''];
  $out[] = [
    'item_id'   => $iid,
    'name'      => $def['name'],
    'category'  => $def['category'],
    'total_qty' => $qty
  ];
}

// sortiere im Backend (Kategorie, dann Name)
usort($out, function($a,$b){
  $c = strcmp($a['category'] ?? '', $b['category'] ?? '');
  if ($c !== 0) return $c;
  return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

echo json_encode([
  'ok'     => true,
  'from'   => date('Y-m-d', $start),
  'to'     => date('Y-m-d', $end),
  'period' => date('d.m.Y', $start) . ' bis ' . date('d.m.Y', $end),
  'items'  => $out
], JSON_UNESCAPED_UNICODE);
