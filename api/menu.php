<?php
// /api/menu.php — öffentlicher Menü-Endpoint für die Bestellseite
// Response: { ok:true, items:[{id,name,price_cents,category,active}] }  (nur active==1)

session_name('TSID');
session_set_cookie_params([
  'lifetime'=>0,'path'=>'/','domain'=>'','secure'=>true,'httponly'=>true,'samesite'=>'Lax'
]);
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Fallback-Konstanten
if (!defined('DATA_DIR'))   define('DATA_DIR',   __DIR__ . '/../data');
if (!defined('CSV_MENU'))   define('CSV_MENU',   DATA_DIR . '/menu.csv');

// CSV-Fallbacks (nur falls in functions.php nicht vorhanden)
if (!function_exists('csv_read_assoc')) {
  function csv_read_assoc(string $file): array {
    if (!is_file($file)) return [];
    $f = fopen($file, 'r'); if(!$f) return [];
    $rows = [];
    $headers = fgetcsv($f, 0, ';');
    if (!$headers) { fclose($f); return []; }
    while(($r=fgetcsv($f,0,';'))!==false){
      $row=[]; foreach($headers as $i=>$h){ $row[$h]=$r[$i]??''; }
      $rows[]=$row;
    }
    fclose($f); return $rows;
  }
}

// Datei sicherstellen
if (!is_dir(DATA_DIR)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'no data dir']); exit; }
if (!is_file(CSV_MENU)) { echo json_encode(['ok'=>true,'items'=>[]]); exit; }

// Menü laden & aufbereitete Liste (nur aktive)
$rows = csv_read_assoc(CSV_MENU);
$out  = [];
foreach ($rows as $r) {
  $active = (string)($r['active'] ?? '') === '1';
  if (!$active) continue;
  $out[] = [
    'id'          => (int)($r['id'] ?? 0),
    'name'        => (string)($r['name'] ?? ''),
    'price_cents' => (int)($r['price_cents'] ?? 0),
    'category'    => (string)($r['category'] ?? ''),
    'active'      => $active ? 1 : 0,
  ];
}

// Optional: sortieren (Kategorie, dann Name)
usort($out, function($a,$b){
  $c = strcmp($a['category'] ?? '', $b['category'] ?? '');
  if ($c !== 0) return $c;
  return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

echo json_encode(['ok'=>true,'items'=>$out], JSON_UNESCAPED_UNICODE);
