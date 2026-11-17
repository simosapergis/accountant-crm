<?php
declare(strict_types=1);

require_once __DIR__ . '/session_lifecycle.php';

function crm_base_path(): string
{
    $configured = trim((string) (getenv('APP_BASE_PATH') ?: '/'));
    $path = '/' . trim($configured, '/');

    return $path === '/' ? '' : $path;
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string) SessionLifecycle::IDLE_TIMEOUT_SECONDS);

    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $secureCookie = $isHttps || getenv('APP_ENV') === 'test';
    $basePath = crm_base_path();
    $cookiePath = $basePath === '' ? '/' : $basePath . '/';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'domain' => '',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $lastActivity = (int) ($_SESSION['_last_activity'] ?? 0);
    if (SessionLifecycle::isExpired($lastActivity, time())) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['_last_activity'] = time();
}
