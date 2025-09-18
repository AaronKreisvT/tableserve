<?php
// /api/tables.php — CRUD für Tische (nur für eingeloggtes Personal)
// Methoden:
//   GET    /api/tables.php                -> Liste der Tische
//   POST   /api/tables.php                -> {name}           -> neuen Tisch anlegen (auto code)
//   PATCH  /api/tables.php                -> {code, name}     -> Tisch umbenennen
//   DELETE /api/tables.php                -> {code}           -> Tisch löschen

require_once __DIR__ . '/../functions.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

// --- Zugriffsschutz: nur, wenn Staff eingeloggt (wie bei staff_orders.php)
if (empty($_SESSION['staff_authenticated']) || $_SESSION['staff_authenticated'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

// --- Fallback-Helfer (falls nicht in functions.php vorhanden)
if (!defined('DATA_DIR')) { define('DATA_DIR', __DIR__ . '/../data'); }
if (!defined('CSV_TABLES')) { define('CSV_TABLES', DATA_DIR . '/tables.csv'); }

if (!function_exists('csv_read_assoc')) {
    function csv_read_assoc($file) {
        if (!is_file($file)) return [];
        $f = fopen($file, 'r');
        if (!$f) return [];
        $rows = [];
        $headers = fgetcsv($f, 0, ';');
        if (!$headers) { fclose($f); return []; }
        while (($r = fgetcsv($f, 0, ';')) !== false) {
            $rows[] = array_combine($headers, $r);
        }
        fclose($f);
        return $rows;
    }
}
if (!function_exists('csv_write_all')) {
    function csv_write_all($file, $headers, $rows) {
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
if (!function_exists('sanitize_text')) {
    function sanitize_text($s) {
        $s = trim((string)$s);
        // einfache Sanitization: keine Zeilenumbrüche, beschränkter Zeichensatz
        $s = preg_replace('/[\r\n]+/',' ', $s);
        return $s;
    }
}
function tables_lock() {
    $lf = DATA_DIR . '/lock_tables.lock';
    $h = fopen($lf, 'c');
    if (!$h) return false;
    flock($h, LOCK_EX);
    return $h;
}
function tables_unlock($h) {
    if ($h) { flock($h, LOCK_UN); fclose($h); }
}
function random_code($len = 8) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // ohne I, O, 1, 0
    $out = '';
    for ($i=0; $i<$len; $i++) { $out .= $alphabet[random_int(0, strlen($alphabet)-1)]; }
    return $out;
}
function unique_code($existing, $tries = 500) {
    $set = [];
    foreach ($existing as $t) { $set[$t['code']] = true; }
    while ($tries-- > 0) {
        $c = random_code(8);
        if (empty($set[$c])) return $c;
    }
    // Fallback mit Zeitstempel
    return 'T' . dechex(time());
}

// --- Request einlesen
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) $payload = [];

// --- Datei sicherstellen
if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0775, true);
if (!is_file(CSV_TABLES)) {
    // Header schreiben, falls Datei fehlt
    csv_write_all(CSV_TABLES, ['name','code'], []);
}

// --- Routing
try {
    if ($method === 'GET') {
        $lock = tables_lock();
        $rows = csv_read_assoc(CSV_TABLES);
        tables_unlock($lock);
        echo json_encode(['ok'=>true, 'tables'=>$rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST') {
        $name = sanitize_text($payload['name'] ?? '');
        if ($name === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'name required']); exit; }

        $lock = tables_lock();
        $rows = csv_read_assoc(CSV_TABLES);
        $code = unique_code($rows);
        $rows[] = ['name'=>$name, 'code'=>$code];
        $ok = csv_write_all(CSV_TABLES, ['name','code'], $rows);
        tables_unlock($lock);

        if (!$ok) { throw new Exception('write failed'); }
        echo json_encode(['ok'=>true, 'table'=>['name'=>$name,'code'=>$code]], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'PATCH') {
        $code = sanitize_text($payload['code'] ?? '');
        $name = sanitize_text($payload['name'] ?? '');
        if ($code==='' || $name==='') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'code and name required']); exit; }

        $lock = tables_lock();
        $rows = csv_read_assoc(CSV_TABLES);
        $found = false;
        foreach ($rows as &$r) {
            if (($r['code'] ?? '') === $code) { $r['name'] = $name; $found = true; break; }
        }
        $ok = $found ? csv_write_all(CSV_TABLES, ['name','code'], $rows) : false;
        tables_unlock($lock);

        if (!$found) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not found']); exit; }
        if (!$ok) { throw new Exception('write failed'); }
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($method === 'DELETE') {
        $code = sanitize_text($payload['code'] ?? '');
        if ($code==='') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'code required']); exit; }

        $lock = tables_lock();
        $rows = csv_read_assoc(CSV_TABLES);
        $new = [];
        $found = false;
        foreach ($rows as $r) {
            if (($r['code'] ?? '') === $code) { $found = true; continue; }
            $new[] = $r;
        }
        $ok = csv_write_all(CSV_TABLES, ['name','code'], $new);
        tables_unlock($lock);

        if (!$found) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not found']); exit; }
        if (!$ok) { throw new Exception('write failed'); }
        echo json_encode(['ok'=>true]);
        exit;
    }

    // Unsupported
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method not allowed']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server error']);
}
