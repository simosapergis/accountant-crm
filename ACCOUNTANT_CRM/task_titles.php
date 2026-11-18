<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_role();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/catalog_admin.php';

$pageTitle = 'Τίτλοι εργασιών';
$result = $mysqli->query('SELECT id, name, is_active FROM task_title_templates ORDER BY is_active DESC, name');
$items = $result->fetch_all(MYSQLI_ASSOC);
require __DIR__ . '/includes/header.php';
crm_catalog_render_list('task_title', $items);
require __DIR__ . '/includes/footer.php';
