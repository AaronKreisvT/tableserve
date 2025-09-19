<?php
// /api/tables.php — CRUD Tische (CSV: code;name)

session_name('TSID');
session_set_cookie_params([
  'lifetime'=>0,'path'=>'/','domain'=>'','secure'=>true,'httponly'=>true,'samesite'=>'Lax'
]);
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');

// ---- Fallback-Konstanten, falls functions.php sie nicht setzt
if (!defined('DATA_DIR'))   define('DATA_DIR',   __DIR__ . '/../data');
if (!defined('CSV_TABLES')) define('CSV_TABLES', DATA_DIR . '/tables.csv');

// ---- CSV-Fallbacks (nur falls in functions.php nicht vorhanden)
if (!function_exists('csv_read_assoc')) {
  function csv_read_assoc($file) {
    if (!is_file($file)) return [];
    $f = fopen($file, 'r'); if(!$f) return [];
    $rows = [];
    $headers = fgetcsv($f, 0, ';');
    if (!$headers) { fclose($f); return []; }
    while(($r = fgetcsv($f, 0, ';')) !== false){
      $row = [];
      foreach ($headers as $i => $h) $row[$h] = $r[$i] ?? '';
      $rows[] = $row;
    }
    fclose($f);
    return $rows;
  }
}
if (!function_exists('csv_write_all')) {
  function csv_write_all($file, $headers, $rows) {
    @mkdir(dirname($file), 0775, true);
    $tmp = $file . '.tmp';
    $f = fopen($tmp, 'w');
    if (!$f) return false;
    fputcsv($f, $headers, ';');
    foreach ($rows as $row) {
      $line = [];
      foreach ($headers as $h) { $line[] = $row[$h] ?? ''; }
      fputcsv($f, $line, ';');
    }
    fclose($f);
    return rename($tmp, $file);
  }
}

// ---- Zugriff: Admin ODER Staff
$okStaff = !empty($_SESSION['staff_authenticated']) && $_SESSION['staff_authenticated'] === true;
$okAdmin = !empty($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
if (!$okStaff && !$okAdmin) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
}

// ---- Helpers
function tables_lock() {
  $lf = DATA_DIR . '/lock_tables.lock';
  @mkdir(dirname($lf), 0775, true);
  $h = fopen($lf, 'c'); if(!$h) return false;
  flock($h, LOCK_EX);
  return $h;
}
function tables_unlock($h){ if($h){ flock($h, LOCK_UN); fclose($h); } }
function sanitize_text($s){ $s = trim((string)$s); return preg_replace('/[\r\n]+/',' ', $s); }
function random_code($len=8){ $a='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $o=''; for($i=0;$i<$len;$i++) $o.=$a[random_int(0,strlen($a)-1)]; return $o; }
function unique_code($rows){
  $set=[]; foreach($rows as $r){ $set[$r['code']??'']=true; }
  for($i=0;$i<500;$i++){ $c=random_code(8); if(empty($set[$c])) return $c; }
  return 'T'.dechex(time());
}

// ---- Datei/Headers sicherstellen (code;name)
if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0775, true);
if (!is_file(CSV_TABLES)) csv_write_all(CSV_TABLES, ['code','name'], []);

// ---- Request
$method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) $payload = [];

try {
  if ($method === 'GET') {
    $lk   = tables_lock();
    $rows = csv_read_assoc(CSV_TABLES);
    tables_unlock($lk);

    $out = [];
    foreach ($rows as $r) {
      $out[] = [
        'code' => $r['code'] ?? ($r['Code'] ?? ''),
        'name' => $r['name'] ?? ($r['Name'] ?? ''),
      ];
    }
    echo json_encode(['ok'=>true,'tables'=>$out], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($method === 'POST') {
    $name = sanitize_text($payload['name'] ?? '');
    if ($name===''){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'name required']); exit; }

    $lk   = tables_lock();
    $rows = csv_read_assoc(CSV_TABLES);
    $norm = [];
    foreach ($rows as $r) { $norm[] = ['code'=>$r['code'] ?? '', 'name'=>$r['name'] ?? '']; }
    $code = unique_code($norm);
    $norm[] = ['code'=>$code, 'name'=>$name];
    $ok = csv_write_all(CSV_TABLES, ['code','name'], $norm);
    tables_unlock($lk);

    if(!$ok) throw new Exception('write failed');
    echo json_encode(['ok'=>true,'table'=>['code'=>$code,'name'=>$name]]);
    exit;
  }

  if ($method === 'PATCH') {
    $code = sanitize_text($payload['code'] ?? '');
    $name = sanitize_text($payload['name'] ?? '');
    if ($code==='' || $name===''){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'code and name required']); exit; }

    $lk   = tables_lock();
    $rows = csv_read_assoc(CSV_TABLES);
    $changed=false; $norm=[];
    foreach ($rows as $r) {
      $c = $r['code'] ?? ($r['Code'] ?? '');
      $n = $r['name'] ?? ($r['Name'] ?? '');
      if ($c === $code) { $n = $name; $changed = true; }
      $norm[] = ['code'=>$c,'name'=>$n];
    }
    $ok = $changed ? csv_write_all(CSV_TABLES, ['code','name'], $norm) : false;
    tables_unlock($lk);

    if(!$changed){ http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not found']); exit; }
    if(!$ok) throw new Exception('write failed');
    echo json_encode(['ok'=>true]);
    exit;
  }

  if ($method === 'DELETE') {
    $code = sanitize_text($payload['code'] ?? '');
    if ($code===''){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'code required']); exit; }

    $lk   = tables_lock();
    $rows = csv_read_assoc(CSV_TABLES);
    $found=false; $norm=[];
    foreach ($rows as $r) {
      $c = $r['code'] ?? ($r['Code'] ?? '');
      $n = $r['name'] ?? ($r['Name'] ?? '');
      if ($c === $code) { $found = true; continue; }
      $norm[] = ['code'=>$c,'name'=>$n];
    }
    $ok = csv_write_all(CSV_TABLES, ['code','name'], $norm);
    tables_unlock($lk);

    if(!$found){ http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not found']); exit; }
    if(!$ok) throw new Exception('write failed');
    echo json_encode(['ok'=>true]);
    exit;
  }

  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'method not allowed']);
} catch (Throwable $e) {
  // Keine Fehlerdetails nach außen
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server error']);
}
