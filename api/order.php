<?php
// api/order.php — nimmt Bestellungen entgegen und schreibt sie in CSV
// ID-Strategie: <TISCHCODE>-<laufende Nummer pro Tisch>
// Thread-Safety: per-Tisch Lock, damit ID-Vergabe + Schreiben atomar sind.

require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');

/* ----------------------------------------------------------------------
   Fallback-Helfer (werden nur definiert, falls nicht bereits vorhanden)
---------------------------------------------------------------------- */
if (!function_exists('ts_lock_table')) {
    function ts_lock_table($table_code) {
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$table_code);
        $lockFile = DATA_DIR . '/lock_' . $safe . '.lock';
        $fh = fopen($lockFile, 'c'); // create if not exists
        if (!$fh) return false;
        flock($fh, LOCK_EX);
        return $fh; // wichtig: Handle halten bis ts_unlock
    }
}
if (!function_exists('ts_unlock')) {
    function ts_unlock($fh) {
        if ($fh) { flock($fh, LOCK_UN); fclose($fh); }
    }
}
if (!function_exists('next_order_id_for_table')) {
    function next_order_id_for_table($table_code) {
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$table_code);
        $seqDir = DATA_DIR . '/seq';
        if (!is_dir($seqDir)) { @mkdir($seqDir, 0775, true); }
        $seqFile = $seqDir . '/orders_' . $safe . '.seq';

        $fh = fopen($seqFile, 'c+'); // create + read/write
        if (!$fh) {
            // Fallback auf Zeitstempel, wenn Sequenz nicht schreibbar
            return $safe . '-' . time();
        }
        flock($fh, LOCK_EX);
        $raw = stream_get_contents($fh);
        $n   = intval(trim((string)$raw)) ?: 0;
        $n++;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, (string)$n);
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        return $safe . '-' . $n;
    }
}

/* ----------------------------------------------------------------------
   Request einlesen & validieren
---------------------------------------------------------------------- */
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) $input = [];

$table_code = $input['table_code'] ?? '';
$items      = $input['items'] ?? [];

if (!$table_code || !is_array($items) || count($items) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'bad request: table_code and items required']);
    exit;
}

/* ----------------------------------------------------------------------
   Tisch & Menü prüfen
---------------------------------------------------------------------- */
$tables = csv_read_assoc(CSV_TABLES);
$tbl = null;
foreach ($tables as $t) {
    if (($t['code'] ?? '') === $table_code) { $tbl = $t; break; }
}
if (!$tbl) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid table']);
    exit;
}

$menu = csv_read_assoc(CSV_MENU);
$menuMap = [];
foreach ($menu as $m) {
    if (($m['active'] ?? '') === '1') {
        // Map nach ID (als String!)
        $menuMap[(string)$m['id']] = $m;
    }
}

/* ----------------------------------------------------------------------
   Atomare Operation pro Tisch: Lock -> ID -> Kopf & Positionen schreiben
---------------------------------------------------------------------- */
$lock = ts_lock_table($table_code);
if (!$lock) {
    http_response_code(500);
    echo json_encode(['error' => 'lock failed']);
    exit;
}

try {
    // Fortlaufende ID nur für diesen Tisch
    $order_id = next_order_id_for_table($table_code);

    // Bestellkopf
    $ok1 = csv_append_assoc(CSV_ORDERS, ['id','table_code','status','created_at'], [
        'id'         => $order_id,                 // z.B. AB12CD34-17
        'table_code' => $table_code,
        'status'     => 'open',
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    // Positionen
    $ok2 = true;
    foreach ($items as $it) {
        $item_id = (string)($it['item_id'] ?? '');
        $qty     = max(1, intval($it['qty'] ?? 1));
        $notes   = sanitize_text($it['notes'] ?? '');

        // unbekannte/ inaktive Items überspringen
        if (!isset($menuMap[$item_id])) continue;

        $ok2 = $ok2 && csv_append_assoc(
            CSV_ORDER_ITEMS,
            ['order_id','item_id','qty','notes'],
            ['order_id' => $order_id, 'item_id' => $item_id, 'qty' => $qty, 'notes' => $notes]
        );
    }

    if (!$ok1 || !$ok2) {
        throw new Exception('write failed');
    }

    echo json_encode(['ok' => true, 'order_id' => $order_id]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server error']);
} finally {
    ts_unlock($lock);
}
