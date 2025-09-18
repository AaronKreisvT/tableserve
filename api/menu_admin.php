<?php
// /api/menu_admin.php — CRUD für Menü (nur Admin-Session)
// Methoden:
//   GET    -> Liste items
//   POST   -> {name, price, category, active}   -> anlegen
//   PATCH  -> {id, name, price, category, active} -> ändern
//   DELETE -> {id} -> löschen
// CSV: id;name;price_cents;category;active

require_once __DIR__ . '/../functions.php';

session_name('TSID');
session_set_cookie_params([
  'lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax'
]);
session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
}

// Fallback-Konstanten
if (!defined('DATA_DIR'))  define('DATA_DIR',  __DIR__ . '/../data');
if (!defined('CSV_MENU'))  define('CSV_MENU',  DATA_DIR . '/menu.csv');

// CSV Helpers
if (!function_exists('csv_read_assoc')) {
  function csv_read_assoc($file) {
    if (!is_file($file)) return [];
    $f=fopen($file,'r'); if(!$f) return [];
    $rows=[]; $headers=fgetcsv($f,0,';'); if(!$headers){ fclose($f); return []; }
    while(($r=fgetcsv($f,0,';'))!==false){
      $row=[]; foreach($headers as $i=>$h){ $row[$h]=$r[$i]??''; } $rows[]=$row;
    }
    fclose($f); return $rows;
  }
}
if (!function_exists('csv_write_all')) {
  function csv_write_all($file,$headers,$rows){
    @mkdir(dirname($file),0775,true);
    $tmp=$file.'.tmp'; $f=fopen($tmp,'w'); if(!$f) return false;
    fputcsv($f,$headers,';');
    foreach($rows as $row){
      $line=[]; foreach($headers as $h){ $line[]=$row[$h]??''; }
      fputcsv($f,$line,';');
    }
    fclose($f); return rename($tmp,$file);
  }
}
function lock_menu(){ $h=fopen(DATA_DIR.'/lock_menu.lock','c'); if(!$h)return false; flock($h,LOCK_EX); return $h; }
function unlock($h){ if($h){ flock($h,LOCK_UN); fclose($h);} }

function euro_to_cents($s){
  $s=str_replace(['.', ' '], ['',''], $s);
  $s=str_replace(',', '.', $s);
  if ($s==='') return 0;
  return (int)round((float)$s*100);
}
function sanitize($s){ $s=trim((string)$s); return preg_replace('/[\r\n]+/', ' ', $s); }
function next_id($rows){ $m=0; foreach($rows as $r){ $id=(int)($r['id']??0); if($id>$m)$m=$id; } return $m+1; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw = file_get_contents('php://input');
$in  = json_decode($raw,true);
if(!is_array($in)) $in=[];

// Datei sicherstellen
if(!is_file(CSV_MENU)) csv_write_all(CSV_MENU, ['id','name','price_cents','category','active'], []);

try{
  if($method==='GET'){
    $lk = lock_menu();
    $rows = csv_read_assoc(CSV_MENU);
    unlock($lk);
    $out=[];
    foreach($rows as $r){
      $out[] = [
        'id' => (int)($r['id']??0),
        'name' => $r['name']??'',
        'price_cents' => (int)($r['price_cents']??0),
        'category' => $r['category']??'',
        'active' => (($r['active']??'')==='1')
      ];
    }
    echo json_encode(['ok'=>true,'items'=>$out], JSON_UNESCAPED_UNICODE); exit;
  }

  if($method==='POST'){
    $name = sanitize($in['name']??'');
    $price_cents = euro_to_cents($in['price']??'');
    $cat  = sanitize($in['category']??'');
    $act  = !empty($in['active']) ? '1':'0';
    if($name==='' || $cat===''){ http_response_code(400); echo json_encode(['ok'=>false]); exit; }

    $lk = lock_menu();
    $rows = csv_read_assoc(CSV_MENU);
    $id = next_id($rows);
    $rows[] = ['id'=>$id,'name'=>$name,'price_cents'=>$price_cents,'category'=>$cat,'active'=>$act];
    $ok = csv_write_all(CSV_MENU, ['id','name','price_cents','category','active'], $rows);
    unlock($lk);

    if(!$ok) throw new Exception('write failed');
    echo json_encode(['ok'=>true,'id'=>$id]); exit;
  }

  if($method==='PATCH'){
    $id   = (int)($in['id']??0);
    $name = sanitize($in['name']??'');
    $price_cents = euro_to_cents($in['price']??'');
    $cat  = sanitize($in['category']??'');
    $act  = !empty($in['active']) ? '1':'0';

    $lk = lock_menu();
    $rows = csv_read_assoc(CSV_MENU);
    $found=false;
    foreach($rows as &$r){
      if((int)($r['id']??0)===$id){
        $r['name']=$name; $r['price_cents']=$price_cents; $r['category']=$cat; $r['active']=$act;
        $found=true; break;
      }
    }
    $ok = $found ? csv_write_all(CSV_MENU, ['id','name','price_cents','category','active'], $rows) : false;
    unlock($lk);

    if(!$found){ http_response_code(404); echo json_encode(['ok'=>false]); exit; }
    if(!$ok) throw new Exception('write failed');
    echo json_encode(['ok'=>true]); exit;
  }

  if($method==='DELETE'){
    $id = (int)($in['id']??0);
    $lk = lock_menu();
    $rows = csv_read_assoc(CSV_MENU);
    $new=[]; $found=false;
    foreach($rows as $r){ if((int)($r['id']??0)===$id){ $found=true; continue; } $new[]=$r; }
    $ok = csv_write_all(CSV_MENU, ['id','name','price_cents','category','active'], $new);
    unlock($lk);

    if(!$found){ http_response_code(404); echo json_encode(['ok'=>false]); exit; }
    if(!$ok) throw new Exception('write failed');
    echo json_encode(['ok'=>true]); exit;
  }

  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'method not allowed']);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server error']);
}
