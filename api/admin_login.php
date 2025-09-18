<?php
// /api/admin_login.php — Login/Logout für Admin-Seite (Session)
//   POST ?login=1  {password}  -> setzt $_SESSION['admin_authenticated']=true
//   POST ?logout=1             -> zerstört Session

require_once __DIR__ . '/../functions.php';

session_name('TSID');
session_set_cookie_params([
  'lifetime'=>0, 'path'=>'/', 'domain'=>'', 'secure'=>true, 'httponly'=>true, 'samesite'=>'Lax'
]);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (isset($_GET['logout'])) {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
  echo json_encode(['ok'=>true]); exit;
}

if (isset($_GET['login'])) {
  $raw = file_get_contents('php://input');
  $in  = json_decode($raw, true);
  $pw  = trim((string)($in['password'] ?? ''));

  if (!defined('ADMIN_KEY')) {
    // Fallback: setze hier DEIN Passwort (oder definiere ADMIN_KEY in functions.php/.env)
    define('ADMIN_KEY', '12345678');
  }

  if ($pw !== '' && hash_equals(ADMIN_KEY, $pw)) {
    session_regenerate_id(true);
    $_SESSION['admin_authenticated'] = true;
    echo json_encode(['ok'=>true]); exit;
  }
  http_response_code(401);
  echo json_encode(['ok'=>false, 'error'=>'invalid']); exit;
}

http_response_code(400);
echo json_encode(['ok'=>false, 'error'=>'bad request']);
