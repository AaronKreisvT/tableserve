<?php
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
$code = trim((string)($in['code'] ?? ''));

if ($code === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>'missing code']); exit;
}

$tables = csv_read_assoc(CSV_TABLES);
$found = null;
foreach ($tables as $t) {
  if (($t['code'] ?? '') === $code) { $found = $t; break; }
}

if (!$found) {
  http_response_code(404);
  echo json_encode(['ok'=>false, 'error'=>'invalid table']); exit;
}

// success
echo json_encode(['ok'=>true, 'code'=>$found['code'], 'name'=>$found['name'] ?? $found['code']]);
