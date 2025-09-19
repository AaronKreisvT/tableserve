<?php
// /api/admin_login.php
//   POST ?login=1  {password}  -> $_SESSION['admin_authenticated']=true
//   POST ?logout=1             -> Session invalidieren

session_name('TSID');
session_set_cookie_params([
  'lifetime'=>0,'path'=>'/','domain'=>'','secure'=>true,'httponly'=>true,'samesite'=>'Lax'
]);
session_start();

require_once __DIR__ . '/../config.php'; // ADMIN_KEY kommt HIERHER
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
  if (!defined('ADMIN_KEY')) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'ADMIN_KEY not defined']); exit; }
  $in = json_decode(file_get_contents('php://input'), true) ?: [];
  $pw = (string)($in['password'] ?? '');
  if ($pw !== '' && hash_equals((string)ADMIN_KEY, $pw)) {
    session_regenerate_id(true);
    $_SESSION['admin_authenticated'] = true;
    echo json_encode(['ok'=>true]); exit;
  }
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'invalid']); exit;
}

http_response_code(400);
echo json_encode(['ok'=>false,'error'=>'bad request']);
