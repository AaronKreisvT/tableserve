<?php
require_once __DIR__ . '/config.php';

// CSV lesen -> Array assoziativ (Header aus erster Zeile)
function csv_read_assoc($file) {
    if (!file_exists($file)) return [];
    $f = fopen($file, 'r');
    if (!$f) return [];
    flock($f, LOCK_SH);
    $rows = [];
    $headers = fgetcsv($f, 0, ';');
    if ($headers === false) { flock($f, LOCK_UN); fclose($f); return []; }
    while (($row = fgetcsv($f, 0, ';')) !== false) {
        $rows[] = array_combine($headers, $row);
    }
    flock($f, LOCK_UN);
    fclose($f);
    return $rows;
}

// CSV anhängen (assoziativ, gleiche Header-Reihenfolge nötig)
function csv_append_assoc($file, $headers, $row) {
    $exists = file_exists($file);
    $f = fopen($file, 'a+');
    if (!$f) return false;
    flock($f, LOCK_EX);
    if (!$exists) fputcsv($f, $headers, ';');
    $ordered = [];
    foreach ($headers as $h) { $ordered[] = $row[$h] ?? ''; }
    fputcsv($f, $ordered, ';');
    fflush($f);
    flock($f, LOCK_UN);
    fclose($f);
    return true;
}

// CSV "update": ganze Datei neu schreiben (klein, daher ok)
function csv_update_assoc($file, $headers, $rows) {
    $tmp = $file . '.tmp';
    $f = fopen($tmp, 'w');
    if (!$f) return false;
    flock($f, LOCK_EX);
    fputcsv($f, $headers, ';');
    foreach ($rows as $r) {
        $ordered = [];
        foreach ($headers as $h) $ordered[] = $r[$h] ?? '';
        fputcsv($f, $ordered, ';');
    }
    fflush($f);
    flock($f, LOCK_UN);
    fclose($f);
    rename($tmp, $file);
    return true;
}

function next_order_id() {
    $rows = csv_read_assoc(CSV_ORDERS);
    if (empty($rows)) return 1;
    $last = end($rows);
    return intval($last['id']) + 1;
}

function sanitize_text($s) {
    return trim(preg_replace('/\s+/', ' ', (string)$s));
}
