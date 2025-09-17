<?php
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json');

if (($_COOKIE['staff_key'] ?? '') !== STAFF_KEY) { http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit; }
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (string)($input['id'] ?? '');
$status = $input['status'] ?? '';

if (!$id || !in_array($status, ['open','in_prep','served','cancelled'])) { http_response_code(400); echo json_encode(['error'=>'bad request']); exit; }

$rows = csv_read_assoc(CSV_ORDERS);
$changed = false;
foreach ($rows as &$r) {
  if ($r['id'] === $id) { $r['status'] = $status; $changed = true; break; }
}
if (!$changed) { http_response_code(404); echo json_encode(['error'=>'not found']); exit; }

csv_update_assoc(CSV_ORDERS, ['id','table_code','status','created_at'], $rows);
echo json_encode(['ok'=>true]);
