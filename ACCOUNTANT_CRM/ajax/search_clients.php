<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once "../includes/db.php";

$qInput = $_GET['q'] ?? '';
$q = is_string($qInput) ? trim($qInput) : '';
$allInput = $_GET['all'] ?? '';
$showAll = is_string($allInput) && $allInput === '1';

if (!$showAll && mb_strlen($q) < 2) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([]);
    exit;
}

if ($showAll) {
    $res = $mysqli->query('SELECT id, name, afm FROM clients ORDER BY name ASC');
} else {
    $sql = "
        SELECT id, name, afm
        FROM clients
        WHERE name LIKE ?
           OR afm LIKE ?
        ORDER BY name ASC
        LIMIT 20
    ";

    $stmt = $mysqli->prepare($sql);
    $searchPattern = '%' . $q . '%';
    $stmt->execute([$searchPattern, $searchPattern]);
    $res = $stmt->get_result();
}

$clients = [];
while ($row = $res->fetch_assoc()) {
    $clients[] = $row;
}

header("Content-Type: application/json; charset=utf-8");
echo json_encode($clients, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
exit;
