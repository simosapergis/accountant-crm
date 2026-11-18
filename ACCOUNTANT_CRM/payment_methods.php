<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_role();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/catalog_admin.php';

$pageTitle = 'Μέσα είσπραξης';
$result = $mysqli->query('SELECT id, name, is_active FROM payment_methods ORDER BY is_active DESC, name');
$items = $result->fetch_all(MYSQLI_ASSOC);
require __DIR__ . '/includes/header.php';
crm_catalog_render_list('payment_method', $items);
require __DIR__ . '/includes/footer.php';
