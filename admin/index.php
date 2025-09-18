<?php
// /admin/index.php — Passwortgeschützte Admin-Konsole (Tische & Menü), rein serverseitig.
// - Login per ADMIN_KEY (unten setzen) -> Session-Guard
// - CSV: tables.csv (code;name), menu.csv (id;name;price_cents;category;active)
// - Aktionen: Tische anlegen/umbenennen/löschen; Menü anlegen/bearbeiten/löschen
// - Keine JS-Abhängigkeit, alles per POST + CSRF

// --------- KONFIG ---------
define('ADMIN_KEY', '12345678'); // <--- HIER sicheres Admin-Passwort setzen (nicht Staff-Key!)
define('DATA_DIR_DEFAULT', __DIR__ . '/../data');
define('CSV_TABLES_DEFAULT', DATA_DIR_DEFAULT . '/tables.csv');
define('CSV_MENU_DEFAULT',   DATA_DIR_DEFAULT . '/menu.csv');

// --------- BOOTSTRAP ---------
require_once __DIR__ . '/../functions.php'; // nutzt vorhandene CSV_* Defs wenn vorhanden

// Session einheitlich & sicher
session_name('TSID');
session_set_cookie_params([
  'lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax'
]);
session_start();

// Konstanten/Fallbacks
if (!defined('DATA_DIR'))   define('DATA_DIR',   DATA_DIR_DEFAULT);
if (!defined('CSV_TABLES')) define('CSV_TABLES', CSV_TABLES_DEFAULT);
if (!defined('CSV_MENU'))   define('CSV_MENU',   CSV_MENU_DEFAULT);

// Utils: CSV read/write (robust, headerbasiert)
if (!function_exists('csv_read_assoc')) {
  function csv_read_assoc($file) {
    if (!is_file($file)) return [];
    $f = fopen($file, 'r'); if(!$f) return [];
    $rows = [];
    $headers = fgetcsv($f, 0, ';');
    if (!$headers) { fclose($f); return []; }
    while(($r=fgetcsv($f, 0, ';'))!==false){
      $row=[]; foreach($headers as $i=>$h){ $row[$h] = $r[$i] ?? ''; }
      $rows[]=$row;
    }
    fclose($f); return $rows;
  }
}
if (!function_exists('csv_write_all')) {
  function csv_write_all($file, $headers, $rows) {
    @mkdir(dirname($file), 0775, true);
    $tmp=$file.'.tmp'; $f=fopen($tmp,'w'); if(!$f) return false;
    fputcsv($f, $headers, ';');
    foreach($rows as $row){
      $line=[]; foreach($headers as $h){ $line[] = $row[$h] ?? ''; }
      fputcsv($f, $line, ';');
    }
    fclose($f); return rename($tmp, $file);
  }
}

// Locks
function lock_file($name){
  $lf = DATA_DIR . '/lock_' . $name . '.lock';
  $h = fopen($lf,'c'); if(!$h) return false;
  flock($h, LOCK_EX); return $h;
}
function unlock_file($h){ if($h){ flock($h,LOCK_UN); fclose($h); } }

// Sanitizer
function txt($s){ $s=trim((string)$s); $s=preg_replace('/[\r\n]+/',' ',$s); return $s; }
function euro_to_cents($s){
  $s = str_replace(['.', ' '], ['',''], $s);
  $s = str_replace(',', '.', $s);
  if ($s==='') return 0;
  return (int)round((float)$s*100);
}
function cents_to_euro($c){
  return number_format(((int)$c)/100, 2, ',', '.');
}
function gen_code($len=8){
  $chars='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  $out=''; for($i=0;$i<$len;$i++) $out.=$chars[random_int(0,strlen($chars)-1)];
  return $out;
}
function next_menu_id($rows){
  $max=0; foreach($rows as $r){ $id=(int)($r['id']??0); if($id>$max)$max=$id; }
  return $max+1;
}

// CSRF
if(empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_ok($t){ return hash_equals($_SESSION['csrf'] ?? '', $t ?? ''); }

// Dateien sicherstellen (Header)
if(!is_dir(DATA_DIR)) @mkdir(DATA_DIR,0775,true);
if(!is_file(CSV_TABLES)) csv_write_all(CSV_TABLES, ['code','name'], []);
if(!is_file(CSV_MENU))   csv_write_all(CSV_MENU,   ['id','name','price_cents','category','active'], []);

// --------- LOGIN/LOGOUT ---------
$logged = !empty($_SESSION['admin']) && $_SESSION['admin']===true;

if(($_POST['action'] ?? '')==='login'){
  if(!csrf_ok($_POST['csrf'] ?? '')){ http_response_code(400); $login_err='Sicherheitsfehler. Bitte erneut versuchen.'; }
  else {
    $pw = $_POST['password'] ?? '';
    if(hash_equals(ADMIN_KEY, $pw)){
      session_regenerate_id(true);
      $_SESSION['admin']=true;
      header('Location: ./'); exit;
    } else {
      $login_err='Falsches Passwort.';
    }
  }
}

if(($_POST['action'] ?? '')==='logout'){
  if(csrf_ok($_POST['csrf'] ?? '')){
    $_SESSION=[]; if(ini_get('session.use_cookies')){
      $p=session_get_cookie_params();
      setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
    }
    session_destroy();
  }
  header('Location: ./'); exit;
}

// --------- AKTIONEN (nur eingeloggt) ---------
$msg = '';
if($logged && !empty($_POST['action'])){
  if(!csrf_ok($_POST['csrf'] ?? '')) { $msg='Sicherheitsfehler (CSRF).'; }
  else {
    switch($_POST['action']){
      // Tische
      case 'table_create': {
        $name = txt($_POST['name'] ?? '');
        if($name===''){ $msg='Name fehlt.'; break; }
        $lk = lock_file('tables');
        $rows = csv_read_assoc(CSV_TABLES);
        $codes = array_column($rows,'code');
        for($i=0;$i<500;$i++){ $code=gen_code(8); if(!in_array($code,$codes,true)) break; }
        $rows[]=['code'=>$code,'name'=>$name];
        csv_write_all(CSV_TABLES, ['code','name'], $rows);
        unlock_file($lk);
        $msg='Tisch angelegt: '.$code;
        break;
      }
      case 'table_rename': {
        $code = txt($_POST['code'] ?? '');
        $name = txt($_POST['name'] ?? '');
        $lk = lock_file('tables');
        $rows = csv_read_assoc(CSV_TABLES);
        $found=false;
        foreach($rows as &$r){ if(($r['code']??'')===$code){ $r['name']=$name; $found=true; break; } }
        csv_write_all(CSV_TABLES, ['code','name'], $rows);
        unlock_file($lk);
        $msg = $found ? 'Tisch umbenannt.' : 'Tisch nicht gefunden.';
        break;
      }
      case 'table_delete': {
        $code = txt($_POST['code'] ?? '');
        $lk = lock_file('tables');
        $rows = csv_read_assoc(CSV_TABLES);
        $new=[]; $found=false;
        foreach($rows as $r){ if(($r['code']??'')===$code){ $found=true; continue; } $new[]=$r; }
        csv_write_all(CSV_TABLES, ['code','name'], $new);
        unlock_file($lk);
        $msg = $found ? 'Tisch gelöscht.' : 'Tisch nicht gefunden.';
        break;
      }

      // Menü
      case 'menu_create': {
        $name = txt($_POST['name'] ?? '');
        $price_cents = euro_to_cents($_POST['price'] ?? '');
        $cat = txt($_POST['category'] ?? '');
        $active = isset($_POST['active']) ? '1' : '0';
        if($name==='' || $cat===''){ $msg='Name/Kategorie fehlen.'; break; }
        $lk = lock_file('menu');
        $rows = csv_read_assoc(CSV_MENU);
        $id = next_menu_id($rows);
        $rows[] = ['id'=>$id,'name'=>$name,'price_cents'=>$price_cents,'category'=>$cat,'active'=>$active];
        csv_write_all(CSV_MENU, ['id','name','price_cents','category','active'], $rows);
        unlock_file($lk);
        $msg='Menüeintrag angelegt (#'.$id.').';
        break;
      }
      case 'menu_update': {
        $id = (int)($_POST['id'] ?? 0);
        $name = txt($_POST['name'] ?? '');
        $price_cents = euro_to_cents($_POST['price'] ?? '');
        $cat = txt($_POST['category'] ?? '');
        $active = isset($_POST['active']) ? '1' : '0';
        $lk = lock_file('menu');
        $rows = csv_read_assoc(CSV_MENU);
        $found=false;
        foreach($rows as &$r){
          if((int)($r['id']??0)===$id){
            $r['name']=$name; $r['price_cents']=$price_cents; $r['category']=$cat; $r['active']=$active;
            $found=true; break;
          }
        }
        csv_write_all(CSV_MENU, ['id','name','price_cents','category','active'], $rows);
        unlock_file($lk);
        $msg = $found ? 'Menüeintrag aktualisiert.' : 'Eintrag nicht gefunden.';
        break;
      }
      case 'menu_delete': {
        $id = (int)($_POST['id'] ?? 0);
        $lk = lock_file('menu');
        $rows = csv_read_assoc(CSV_MENU);
        $new=[]; $found=false;
        foreach($rows as $r){ if((int)($r['id']??0)===$id){ $found=true; continue; } $new[]=$r; }
        csv_write_all(CSV_MENU, ['id','name','price_cents','category','active'], $new);
        unlock_file($lk);
        $msg = $found ? 'Menüeintrag gelöscht.' : 'Eintrag nicht gefunden.';
        break;
      }
    }
  }
}

// --------- VIEW ---------
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }

$tables = $logged ? csv_read_assoc(CSV_TABLES) : [];
$menu   = $logged ? csv_read_assoc(CSV_MENU)   : [];
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TableServe – Admin</title>
  <link rel="stylesheet" href="/assets/css/styles.css">
  <link rel="stylesheet" href="/assets/css/tableserve.css">
  <style>
    /* etwas kompakter im Admin */
    .admin-wrap{max-width:1100px;margin:20px auto;padding:0 16px}
    form.inline{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    table{width:100%;border-collapse:collapse;margin:10px 0}
    th,td{border:1px solid var(--border);padding:.5em .6em}
    thead th{background: color-mix(in srgb, var(--border) 55%, transparent)}
    .right{text-align:right}
    .muted{color:var(--muted)}
    .msg{margin:10px 0}
  </style>
</head>
<body>
  <header>
    <nav class="nav">
      <a class="brand" href="/admin/">Admin</a>
      <div class="spacer"></div>
      <?php if($logged): ?>
      <form method="post" class="inline">
        <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
        <button type="submit" name="action" value="logout">Logout</button>
      </form>
      <?php endif; ?>
    </nav>
  </header>
  <main class="admin-wrap">
    <?php if(!$logged): ?>
      <h1>Anmeldung</h1>
      <p class="muted">Bitte Admin-Passwort eingeben.</p>
      <?php if(!empty($login_err)): ?><p class="msg" style="color:#b00"><?=h($login_err)?></p><?php endif; ?>
      <form method="post" class="inline" style="gap:10px">
        <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
        <input type="password" name="password" placeholder="Admin-Passwort" required style="min-width:260px">
        <button type="submit" name="action" value="login">Einloggen</button>
      </form>

    <?php else: ?>
      <?php if($msg): ?><p class="msg"><?=h($msg)?></p><?php endif; ?>

      <h1>Tische</h1>
      <form method="post" class="inline" style="margin-bottom:8px">
        <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
        <input type="text" name="name" placeholder="Neuer Tischnamen (z. B. Tisch 7)" required style="flex:1;min-width:260px">
        <button type="submit" name="action" value="table_create">Tisch anlegen</button>
      </form>

      <?php if(empty($tables)): ?>
        <p class="muted">Noch keine Tische angelegt.</p>
      <?php else: ?>
        <table>
          <thead><tr><th style="width:50%">Name</th><th>Code</th><th class="right">Aktionen</th></tr></thead>
          <tbody>
          <?php foreach($tables as $t): ?>
            <tr>
              <td>
                <form method="post" class="inline">
                  <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
                  <input type="hidden" name="code" value="<?=h($t['code'] ?? '')?>">
                  <input type="text"   name="name" value="<?=h($t['name'] ?? '')?>" required style="min-width:220px">
                  <button type="submit" name="action" value="table_rename">Umbenennen</button>
                </form>
              </td>
              <td><code><?=h($t['code'] ?? '')?></code></td>
              <td class="right">
                <form method="post" onsubmit="return confirm('Tisch wirklich löschen?')" style="display:inline">
                  <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
                  <input type="hidden" name="code" value="<?=h($t['code'] ?? '')?>">
                  <button type="submit" name="action" value="table_delete">Löschen</button>
                </form>
                <a href="/order.html?code=<?=urlencode($t['code'] ?? '')?>" style="margin-left:6px">Öffnen</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <h1>Menü</h1>
      <form method="post" class="inline" style="gap:8px;margin-bottom:8px">
        <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
        <input type="text"   name="name" placeholder="Name" required>
        <input type="text"   name="price" placeholder="Preis (€ z. B. 2,50)" required style="width:140px">
        <input type="text"   name="category" placeholder="Kategorie (z. B. Getränke)" required>
        <label><input type="checkbox" name="active" checked> aktiv</label>
        <button type="submit" name="action" value="menu_create">Hinzufügen</button>
      </form>

      <?php if(empty($menu)): ?>
        <p class="muted">Noch keine Menüeinträge.</p>
      <?php else: ?>
        <table>
          <thead><tr><th>ID</th><th style="width:40%">Name</th><th>Preis</th><th>Kategorie</th><th>Aktiv</th><th class="right">Aktionen</th></tr></thead>
          <tbody>
          <?php foreach($menu as $m):
            $id=(int)($m['id']??0); $nm=$m['name']??''; $pc=(int)($m['price_cents']??0); $cat=$m['category']??''; $act=($m['active']??'')==='1';
          ?>
            <tr>
              <td><?=h($id)?></td>
              <td>
                <form method="post" class="inline" style="gap:8px">
                  <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
                  <input type="hidden" name="id"   value="<?=h($id)?>">
                  <input type="text"   name="name" value="<?=h($nm)?>" required style="min-width:220px">
                  <input type="text"   name="price" value="<?=h(cents_to_euro($pc))?>" required style="width:120px">
                  <input type="text"   name="category" value="<?=h($cat)?>" required style="width:160px">
                  <label><input type="checkbox" name="active" <?= $act?'checked':''; ?>> aktiv</label>
                  <button type="submit" name="action" value="menu_update">Speichern</button>
                </form>
              </td>
              <td><?=h(cents_to_euro($pc))?> €</td>
              <td><?=h($cat)?></td>
              <td><?= $act?'ja':'nein' ?></td>
              <td class="right">
                <form method="post" onsubmit="return confirm('Eintrag wirklich löschen?')" style="display:inline">
                  <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
                  <input type="hidden" name="id" value="<?=h($id)?>">
                  <button type="submit" name="action" value="menu_delete">Löschen</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

    <?php endif; ?>
  </main>
  <footer><div class="foot"><span>© <?=date('Y')?> TableServe Admin</span><span class="spacer"></span><a href="/">Zur Startseite</a></div></footer>
</body>
</html>
