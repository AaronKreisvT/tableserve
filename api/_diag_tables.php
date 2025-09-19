<?php
// /api/_diag_tables.php — Diagnose für Admin/Staff + CSV/Pfade/Dateirechte

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$errors = [];
set_error_handler(function($no,$str,$file,$line) use (&$errors){
  $errors[] = "PHP$no: $str @ $file:$line";
});
register_shutdown_function(function() use (&$errors){
  $e = error_get_last();
  if ($e) { $errors[] = "FATAL: {$e['message']} @ {$e['file']}:{$e['line']}"; }
});

session_name('TSID');
session_set_cookie_params([
  'lifetime'=>0,'path'=>'/','domain'=>'','secure'=>true,'httponly'=>true,'samesite'=>'Lax'
]);
session_start();

$info = [
  'php_version' => PHP_VERSION,
  'sapi'        => PHP_SAPI,
  'session_id'  => session_id() ? 'present' : 'missing',
];

$okStaff = !empty($_SESSION['staff_authenticated']) && $_SESSION['staff_authenticated']===true;
$okAdmin = !empty($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated']===true;

$info['auth'] = ['staff'=>$okStaff, 'admin'=>$okAdmin];

$cfgLoaded = false;
$fnLoaded  = false;
try {
  require_once __DIR__ . '/../config.php';
  $cfgLoaded = true;
} catch(Throwable $e){ $errors[] = 'config.php load failed: '.$e->getMessage(); }

try {
  require_once __DIR__ . '/../functions.php';
  $fnLoaded = true;
} catch(Throwable $e){ $errors[] = 'functions.php load failed: '.$e->getMessage(); }

$info['files_loaded'] = ['config'=>$cfgLoaded, 'functions'=>$fnLoaded];

if (!defined('DATA_DIR'))   define('DATA_DIR',   __DIR__ . '/../data');
if (!defined('CSV_TABLES')) define('CSV_TABLES', DATA_DIR . '/tables.csv');

$info['const'] = [
  'DATA_DIR'   => DATA_DIR,
  'CSV_TABLES' => CSV_TABLES,
  'exists(DATA_DIR)'   => is_dir(DATA_DIR),
  'exists(CSV_TABLES)' => is_file(CSV_TABLES),
  'readable(DATA_DIR)' => is_readable(DATA_DIR),
  'writable(DATA_DIR)' => is_writable(DATA_DIR),
  'readable(CSV_TABLES)' => is_file(CSV_TABLES) ? is_readable(CSV_TABLES) : null,
  'filesize(CSV_TABLES)' => is_file(CSV_TABLES) ? filesize(CSV_TABLES) : null,
];

$info['functions'] = [
  'csv_read_assoc_exists'  => function_exists('csv_read_assoc'),
  'csv_write_all_exists'   => function_exists('csv_write_all'),
];

$rowsSample = null;
try {
  if (is_file(CSV_TABLES)) {
    $fh = fopen(CSV_TABLES, 'r');
    $hdr = fgetcsv($fh, 0, ';');
    $first = fgetcsv($fh, 0, ';');
    fclose($fh);
    $rowsSample = ['header'=>$hdr, 'first'=>$first];
  }
} catch(Throwable $e){
  $errors[] = 'CSV read failed: '.$e->getMessage();
}

echo json_encode([
  'ok'      => true,
  'info'    => $info,
  'sample'  => $rowsSample,
  'errors'  => $errors,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
