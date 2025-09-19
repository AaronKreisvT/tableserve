<?php
// /api/tables.php — DIAGNOSTIK-VERSION (vorübergehend)
// Fängt alle PHP-Fehler ab und gibt sie als JSON aus, damit 500 nicht stumm bleibt.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

error_reporting(E_ALL);
ini_set('display_errors','0');

$errors = [];
set_error_handler(function($no,$str,$file,$line) use (&$errors){
  $errors[] = "PHP$no: $str @ $file:$line";
});
register_shutdown_function(function() use (&$errors){
  $e = error_get_last();
  if ($e) {
    // Wenn es einen Fatal gab und noch nichts gesendet wurde: JSON ausgeben
    header_remove('Content-Type'); // setze erneut
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
      'ok'=>false,
      'fatal'=>[
        'type'=>$e['type'],
        'message'=>$e['message'],
        'file'=>$e['file'],
        'line'=>$e['line'],
      ],
      'errors'=>$errors,
      'where'=>'shutdown'
    ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
  }
});

// ---- Session + Auth (minimal, ohne Includes) ----
session_name('TSID');
session_set_cookie_params([
  'lifetime'=>0,'path'=>'/','domain'=>'','secure'=>true,'httponly'=>true,'samesite'=>'Lax'
]);
session_start();

$okStaff = !empty($_SESSION['staff_authenticated']) && $_SESSION['staff_authenticated'] === true;
$okAdmin = !empty($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;

// Wenn du hier 403 bekommst, stimmt die Session/Domäne/HTTPS nicht.
if (!$okStaff && !$okAdmin) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'forbidden','session'=>$_SESSION], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
  exit;
}

// ---- Konstanten lokal bestimmen (keine Abhängigkeit zu functions.php) ----
$DATA_DIR   = realpath(__DIR__ . '/../data') ?: (__DIR__ . '/../data');
$CSV_TABLES = $DATA_DIR . '/tables.csv';

// ---- Nur GET diagnostizieren (POST/PATCH/DELETE weglassen, um Fehler einzugrenzen) ----
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
  echo json_encode(['ok'=>true,'note'=>'diagnostic build only supports GET right now'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  // Vorab-Infos
  $diag = [
    'php_version'=>PHP_VERSION,
    'sapi'=>PHP_SAPI,
    'data_dir'=>$DATA_DIR,
    'csv_tables'=>$CSV_TABLES,
    'is_dir(DATA_DIR)'=>is_dir($DATA_DIR),
    'is_file(CSV_TABLES)'=>is_file($CSV_TABLES),
    'is_readable(CSV_TABLES)'=>is_file($CSV_TABLES) ? is_readable($CSV_TABLES) : null,
    'filesize(CSV_TABLES)'=>is_file($CSV_TABLES) ? filesize($CSV_TABLES) : null,
    'auth'=>['staff'=>$okStaff,'admin'=>$okAdmin],
  ];

  if (!is_file($CSV_TABLES)) {
    echo json_encode(['ok'=>true,'tables'=>[], 'diag'=>$diag, 'errors'=>$errors], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    exit;
  }

  $fh = fopen($CSV_TABLES, 'r');
  if (!$fh) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'open failed','diag'=>$diag,'errors'=>$errors], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    exit;
  }

  $hdr = fgetcsv($fh, 0, ';');
  if ($hdr === false) {
    fclose($fh);
    echo json_encode(['ok'=>true,'tables'=>[], 'diag'=>$diag, 'errors'=>$errors], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    exit;
  }

  $idx = array_change_key_case(array_flip($hdr), CASE_LOWER);
  $iCode = $idx['code'] ?? null;
  $iName = $idx['name'] ?? null;

  $out = [];
  while (($r = fgetcsv($fh, 0, ';')) !== false) {
    $out[] = [
      'code' => $iCode !== null ? ($r[$iCode] ?? '') : ($r[0] ?? ''),
      'name' => $iName !== null ? ($r[$iName] ?? '') : ($r[1] ?? ''),
    ];
  }
  fclose($fh);

  echo json_encode([
    'ok'=>true,
    'tables'=>$out,
    'diag'=>$diag,
    'errors'=>$errors
  ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok'=>false,
    'thrown'=>[
      'class'=>get_class($e),
      'message'=>$e->getMessage(),
      'file'=>$e->getFile(),
      'line'=>$e->getLine(),
    ],
    'errors'=>$errors
  ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
  exit;
}
