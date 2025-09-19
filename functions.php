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
// erzeugt/liest pro Tisch eine Sequenz: data/seq/orders_<code>.seq
function next_order_id_for_table($table_code) {
    $seqDir = DATA_DIR . '/seq';
    if (!is_dir($seqDir)) { mkdir($seqDir, 0775, true); }
    $seqFile = $seqDir . '/orders_' . preg_replace('/[^A-Za-z0-9_-]/','',$table_code) . '.seq';

    $fh = fopen($seqFile, 'c+');          // anlegen, wenn fehlt
    if (!$fh) return $table_code . '-' . time(); // Fallback
    flock($fh, LOCK_EX);                  // **per Tisch** exklusiv
    $raw = stream_get_contents($fh);
    $n   = intval(trim($raw)) ?: 0;
    $n++;
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, (string)$n);
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    return $table_code . '-' . $n;
}

// Optional: allgemeiner Mutex, falls du Kopf+Positionen zusammen schützen willst
function ts_lock_table($table_code) {
    $lockFile = DATA_DIR . '/lock_' . preg_replace('/[^A-Za-z0-9_-]/','',$table_code) . '.lock';
    $fh = fopen($lockFile, 'c');
    if (!$fh) return false;
    flock($fh, LOCK_EX);
    return $fh;
}
function ts_unlock($fh) {
    if ($fh) { flock($fh, LOCK_UN); fclose($fh); }
}
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
