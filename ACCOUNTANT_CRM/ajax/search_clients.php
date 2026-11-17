<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once "../includes/db.php";

$qInput = $_GET['q'] ?? '';
$q = is_string($qInput) ? trim($qInput) : '';

if (mb_strlen($q) < 2) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([]);
    exit;
}

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

$clients = [];
while ($row = $res->fetch_assoc()) {
    $clients[] = $row;
}

header("Content-Type: application/json; charset=utf-8");
echo json_encode($clients);
exit;
