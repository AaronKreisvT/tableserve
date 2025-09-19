<?php
// /api/tables.php — CRUD Tische (CSV: code;name), konfliktfrei mit functions.php

session_name('TSID');
session_set_cookie_params([
  'lifetime'=>0,'path'=>'/','domain'=>'','secure'=>true,'httponly'=>true,'samesite'=>'Lax'
]);
session_start();

// Zentrale Config/Funktionen zuerst laden
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Fallback-Konstanten (nur falls nicht in config/functions gesetzt)
if (!defined('DATA_DIR'))   define('DATA_DIR',   __DIR__ . '/../data');
if (!defined('CSV_TABLES')) define('CSV_TABLES', DATA_DIR . '/tables.csv');

// Fallback-CSV-Utils (nur definieren, wenn sie fehlen)
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
    fclose($f);
    return $rows;
  }
}
if (!function_exists('csv_write_all')) {
  function csv_write_all(string $file, array $headers, array $rows): bool {
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
    return @rename($tmp, $file);
  }
}

// WICHTIG: HIER KEIN sanitize_text() DEFINIEREN! (kommt aus functions.php)

// Zugriff erlauben: Admin ODER Staff Session
$okStaff = !empty($_SESSION['staff_authenticated']) && $_SESSION['staff_authenticated'] === true;
$okAdmin = !empty($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
if (!$okStaff && !$okAdmin) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'forbidden']);
  exit;
}

// Kleine lokale Helfer ohne Namenskollisionen
if (!function_exists('ts_random_code')) {
  function ts_random_code(int $len=8): string {
    $a='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $o=''; for($i=0;$i<$len;$i++) $o.=$a[random_int(0,strlen($a)-1)]; return $o;
  }
}
if (!function_exists('ts_unique_code')) {
  function ts_unique_code(array $rows): string {
    $set = [];
    foreach ($rows as $r) { $set[$r['code'] ?? ''] = true; }
    for ($i=0;$i<500;$i++) { $c = ts_random_code(8); if (empty($set[$c])) return $c; }
    return 'T'.dechex(time());
  }
}

// Datei/Headers sicherstellen
if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0775, true);
if (!is_file(CSV_TABLES)) {
  csv_write_all(CSV_TABLES, ['code','name'], []);
}

// Request einlesen
$method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) $payload = [];

try {
  if ($method === 'GET') {
    $rows = csv_read_assoc(CSV_TABLES);
    $out  = [];
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
    // sanitize_text() kommt aus functions.php
    $name = function_exists('sanitize_text') ? sanitize_text((string)($payload['name'] ?? ''))
                                             : trim((string)($payload['name'] ?? ''));
    if ($name===''){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'name required']); exit; }

    $rows = csv_read_assoc(CSV_TABLES);
    $norm = [];
    foreach ($rows as $r) { $norm[] = ['code'=>$r['code'] ?? '', 'name'=>$r['name'] ?? '']; }
    $code = ts_unique_code($norm);
    $norm[] = ['code'=>$code,'name'=>$name];

    if (!csv_write_all(CSV_TABLES, ['code','name'], $norm)) { throw new Exception('write failed'); }
    echo json_encode(['ok'=>true,'table'=>['code'=>$code,'name'=>$name]]);
    exit;
  }

  if ($method === 'PATCH') {
    $code = function_exists('sanitize_text') ? sanitize_text((string)($payload['code'] ?? ''))
                                             : trim((string)($payload['code'] ?? ''));
    $name = function_exists('sanitize_text') ? sanitize_text((string)($payload['name'] ?? ''))
                                             : trim((string)($payload['name'] ?? ''));
    if ($code==='' || $name===''){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'code and name required']); exit; }

    $rows = csv_read_assoc(CSV_TABLES);
    $changed=false; $norm=[];
    foreach ($rows as $r) {
      $c=$r['code'] ?? ($r['Code'] ?? ''); $n=$r['name'] ?? ($r['Name'] ?? '');
      if ($c===$code){ $n=$name; $changed=true; }
      $norm[]=['code'=>$c,'name'=>$n];
    }
    if (!$changed){ http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not found']); exit; }
    if (!csv_write_all(CSV_TABLES, ['code','name'], $norm)) { throw new Exception('write failed'); }
    echo json_encode(['ok'=>true]);
    exit;
  }

  if ($method === 'DELETE') {
    $code = function_exists('sanitize_text') ? sanitize_text((string)($payload['code'] ?? ''))
                                             : trim((string)($payload['code'] ?? ''));
    if ($code===''){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'code required']); exit; }

    $rows = csv_read_assoc(CSV_TABLES);
    $found=false; $norm=[];
    foreach ($rows as $r) {
      $c=$r['code'] ?? ($r['Code'] ?? ''); $n=$r['name'] ?? ($r['Name'] ?? '');
      if ($c===$code){ $found=true; continue; }
      $norm[]=['code'=>$c,'name'=>$n];
    }
    if (!$found){ http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not found']); exit; }
    if (!csv_write_all(CSV_TABLES, ['code','name'], $norm)) { throw new Exception('write failed'); }
    echo json_encode(['ok'=>true]);
    exit;
  }

  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'method not allowed']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server error']);
}
