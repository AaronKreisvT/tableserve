<?php
// /api/set_status.php — Bestellstatus setzen (nur Staff/Admin), ID = STRING

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
if (!$okStaff && !$okAdmin) {
  http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
}

if (!defined('DATA_DIR'))   define('DATA_DIR',   __DIR__ . '/../data');
if (!defined('CSV_ORDERS')) define('CSV_ORDERS', DATA_DIR . '/orders.csv');

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
    fclose($f); return @rename($tmp, $file);
  }
}

$in = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
$id = (string)($in['id'] ?? '');
$st = (string)($in['status'] ?? '');

$allowed = ['open','in_prep','served','cancelled'];
if ($id === '' || !in_array($st, $allowed, true)) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad request']); exit;
}

// Update
$rows = csv_read_assoc(CSV_ORDERS);
$changed = false;
foreach ($rows as &$r) {
  if ((string)($r['id'] ?? '') === $id) {
    $r['status'] = $st;
    $changed = true;
    break;
  }
}
if (!$changed) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not found']); exit; }

if (!csv_write_all(CSV_ORDERS, ['id','table_code','status','created_at'], $rows)) {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'server error']); exit;
}
echo json_encode(['ok'=>true]);
