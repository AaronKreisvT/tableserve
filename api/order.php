<?php
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$table_code = $input['table_code'] ?? '';
$items = $input['items'] ?? [];

if (!$table_code || !is_array($items) || count($items)===0) { http_response_code(400); echo json_encode(['error'=>'bad request']); exit; }

// Tisch prüfen
$tables = csv_read_assoc(CSV_TABLES);
$tbl = null; foreach ($tables as $t) if ($t['code'] === $table_code) { $tbl = $t; break; }
if (!$tbl) { http_response_code(400); echo json_encode(['error'=>'invalid table']); exit; }

// Menü in Map für Prüfung
$menu = csv_read_assoc(CSV_MENU);
$menuMap = []; foreach ($menu as $m) if ($m['active']==='1') $menuMap[$m['id']] = $m;

// Bestellung anlegen
$id = next_order_id();
csv_append_assoc(CSV_ORDERS, ['id','table_code','status','created_at'], [
  'id'=>$id,
  'table_code'=>$table_code,
  'status'=>'open',
  'created_at'=>date('Y-m-d H:i:s'),
]);

foreach ($items as $it) {
  $item_id = (string)($it['item_id'] ?? '');
  $qty = max(1, intval($it['qty'] ?? 1));
  $notes = sanitize_text($it['notes'] ?? '');
  if (!isset($menuMap[$item_id])) continue;
  csv_append_assoc(CSV_ORDER_ITEMS, ['order_id','item_id','qty','notes'], [
    'order_id'=>$id, 'item_id'=>$item_id, 'qty'=>$qty, 'notes'=>$notes
  ]);
}

echo json_encode(['ok'=>true,'order_id'=>$id]);
