<?php
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json');

// einfacher Cookie-Login
if (($_GET['login'] ?? '') === '1') {
  $input = json_decode(file_get_contents('php://input'), true) ?? [];
  if (($input['key'] ?? '') !== STAFF_KEY) { http_response_code(401); echo json_encode(['error'=>'bad key']); exit; }
  setcookie('staff_key', STAFF_KEY, [
    'httponly'=>true,'samesite'=>'Lax','secure'=>false,'path'=>'/','expires'=>time()+7*24*3600
  ]);
  echo json_encode(['ok'=>true]); exit;
}
if (($_COOKIE['staff_key'] ?? '') !== STAFF_KEY) { http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit; }

$orders = csv_read_assoc(CSV_ORDERS);
$orders = array_values(array_filter($orders, fn($o)=> in_array($o['status'], ['open','in_prep'])));
usort($orders, fn($a,$b)=>strcmp($a['created_at'],$b['created_at']));

$tables = csv_read_assoc(CSV_TABLES);
$tMap = []; foreach ($tables as $t) $tMap[$t['code']] = $t['name'];

$menu = csv_read_assoc(CSV_MENU);
$mMap = []; foreach ($menu as $m) $mMap[$m['id']] = $m;

$items = csv_read_assoc(CSV_ORDER_ITEMS);
$byOrder = [];
foreach ($items as $it) $byOrder[$it['order_id']][] = $it;

$out = [];
foreach ($orders as $o) {
  $arr = [];
  foreach ($byOrder[$o['id']] ?? [] as $line) {
    $def = $mMap[$line['item_id']] ?? null;
    $arr[] = [
      'qty' => (int)$line['qty'],
      'name'=> $def ? $def['name'] : ('#'.$line['item_id']),
      'notes'=> $line['notes'] ?? ''
    ];
  }
  $out[] = [
    'id'=>(int)$o['id'],
    'status'=>$o['status'],
    'created_at'=>$o['created_at'],
    'table_name'=>$tMap[$o['table_code']] ?? $o['table_code'],
    'items'=>$arr
  ];
}
echo json_encode(['orders'=>$out]);
Y
