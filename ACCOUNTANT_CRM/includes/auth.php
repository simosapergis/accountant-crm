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
