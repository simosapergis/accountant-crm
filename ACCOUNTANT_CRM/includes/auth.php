<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_validate_request();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . crm_base_path() . '/login.php');
    exit;
}

header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

function require_admin_role(): void
{
    if (($_SESSION['role'] ?? null) !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
}
