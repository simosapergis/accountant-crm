<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_role();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/catalog_admin.php';

$idInput = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['id'] ?? null) : ($_GET['id'] ?? null);
if ($idInput !== null && (!is_string($idInput) || filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false)) {
    http_response_code(400);
    exit('Invalid catalog identifier.');
}
$id = $idInput === null ? null : (int)$idInput;
$item = $id === null ? null : crm_catalog_find($mysqli, 'task_title', $id);
if ($id !== null && $item === null) {
    http_response_code(404);
    exit('Not Found');
}
$error = '';
$inactiveDuplicateId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_catalog'])) {
    $result = crm_catalog_save($mysqli, 'task_title', $_POST);
    if ($result['ok']) {
        header('Location: task_titles.php');
        exit;
    }
    $error = $result['error'];
    $inactiveDuplicateId = $result['inactive_duplicate_id'] ?? null;
    $item = [
        'id' => $id,
        'name' => is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '',
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
}

$pageTitle = $id === null ? 'Νέος τίτλος εργασίας' : 'Επεξεργασία τίτλου εργασίας';
require __DIR__ . '/includes/header.php';
crm_catalog_render_form('task_title', $item, $error, $inactiveDuplicateId, csrf_field());
require __DIR__ . '/includes/footer.php';
