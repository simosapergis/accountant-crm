<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/csrf.php';
require_post_method();
csrf_validate_request();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security_events.php';
if (isset($_SESSION['user_id'])) {
    crm_record_security_event($mysqli, 'logout', 'success', crm_security_hash(mb_strtolower((string)($_SESSION['username'] ?? ''), 'UTF-8')), crm_security_hash((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')));
}
$cookie = session_get_cookie_params();
$_SESSION = [];
session_destroy();
if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'],
        'samesite' => $cookie['samesite'],
    ]);
}
header('Location: ' . crm_base_path() . '/login.php');
exit;
