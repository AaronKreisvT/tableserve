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

// Zugriff: Admin ODER Staff
$okStaff = !empty($_SESSION['staff_authenticated']) && $_SESSION['staff_authenticated'] === true;
$okAdmin = !empty($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
if (!$okStaff && !$okAdmin) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }

// Helpers
function tables_lock() {
  $lf = DATA_DIR . '/lock_tables.lock';
  $h = fopen($lf,'c'); if(!$h) return false;
  flock($h, LOCK_EX); return $h;
}
function tables_unlock($h){ if($h){ flock($h,LOCK_UN); fclose($h);} }
function sanitize_text($s){ $s=trim((string)$s); return preg_replace('/[\r\n]+/',' ',$s); }
function random_code($len=8){ $a='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $o=''; for($i=0;$i<$len;$i++) $o.=$a[random_int(0,strlen($a)-1)]; return $o; }
function unique_code($rows){ $set=[]; foreach($rows as $r){ $set[$r['code']??'']=true; } for($i=0;$i<500;$i++){ $c=random_code(8); if(empty($set[$c])) return $c; } return 'T'.dechex(time()); }

// Datei/Header sicherstellen
if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0775, true);
if (!is_file(CSV_TABLES)) csv_write_all(CSV_TABLES, ['code','name'], []);

// Request
$m  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$in = json_decode(file_get_contents('php://input'), true) ?: [];

try {
  if ($m === 'GET') {
    $lk = tables_lock();
    $rows = csv_read_assoc(CSV_TABLES);
    tables_unlock($lk);

    $out = [];
    foreach ($rows as $r) {
      $out[] = ['code'=>$r['code'] ?? ($r['Code'] ?? ''), 'name'=>$r['name'] ?? ($r['Name'] ?? '')];
    }
    echo json_encode(['ok'=>true,'tables'=>$out], JSON_UNESCAPED_UNICODE); exit;
  }

  if ($m === 'POST') { // anlegen
    $name = sanitize_text($in['name'] ?? '');
    if ($name===''){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'name required']); exit; }

    $lk = tables_lock();
    $rows = csv_read_assoc(CSV_TABLES);
    $norm = [];
    foreach ($rows as $r) { $norm[] = ['code'=>$r['code'] ?? '', 'name'=>$r['name'] ?? '']; }
    $code = unique_code($norm);
    $norm[] = ['code'=>$code,'name'=>$name];
    $ok = csv_write_all(CSV_TABLES, ['code','name'], $norm);
    tables_unlock($lk);

    if(!$ok) throw new Exception('write failed');
    echo json_encode(['ok'=>true,'table'=>['code'=>$code,'name'=>$name]]); exit;
  }

  if ($m === 'PATCH') { // umbenennen
    $code = sanitize_text($in['code'] ?? '');
    $name = sanitize_text($in['name'] ?? '');
    if ($code==='' || $name===''){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'code and name required']); exit; }

    $lk = tables_lock();
    $rows = csv_read_assoc(CSV_TABLES);
    $changed=false; $norm=[];
    foreach ($rows as $r) {
      $c=$r['code'] ?? ($r['Code'] ?? ''); $n=$r['name'] ?? ($r['Name'] ?? '');
      if ($c===$code){ $n=$name; $changed=true; }
      $norm[]=['code'=>$c,'name'=>$n];
    }
    $ok = $changed ? csv_write_all(CSV_TABLES, ['code','name'], $norm) : false;
    tables_unlock($lk);

    if(!$changed){ http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not found']); exit; }
    if(!$ok) throw new Exception('write failed');
    echo json_encode(['ok'=>true]); exit;
  }

  if ($m === 'DELETE') { // löschen
    $code = sanitize_text($in['code'] ?? '');
    if ($code===''){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'code required']); exit; }

    $lk = tables_lock();
    $rows = csv_read_assoc(CSV_TABLES);
    $found=false; $norm=[];
    foreach ($rows as $r) {
      $c=$r['code'] ?? ($r['Code'] ?? ''); $n=$r['name'] ?? ($r['Name'] ?? '');
      if ($c===$code){ $found=true; continue; }
      $norm[]=['code'=>$c,'name'=>$n];
    }
    $ok = csv_write_all(CSV_TABLES, ['code','name'], $norm);
    tables_unlock($lk);

    if(!$found){ http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not found']); exit; }
    if(!$ok) throw new Exception('write failed');
    echo json_encode(['ok'=>true]); exit;
  }

  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'method not allowed']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server error']); // keine Details nach außen
}
