<?php
require_once __DIR__ . '/includes/auth.php';
require_post_method();
require_once "includes/db.php";

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    http_response_code(403);
    exit("Not logged in");
}

$id          = $_POST["id"] ?? 0;
$title       = $_POST["title"] ?? "";
$start_time  = $_POST["start_time"] ?? null;
$end_time    = $_POST["end_time"] ?? null;
$description = $_POST["description"] ?? "";
$event_date  = $_POST["event_date"] ?? null;

$id = (int)$id;

if (!$id || !$event_date) {
    http_response_code(400);
    exit("Missing fields");
}

$ownerCheck = $mysqli->prepare('SELECT id FROM diary_events WHERE id = ? AND user_id = ? LIMIT 1');
$ownerCheck->bind_param('ii', $id, $userId);
$ownerCheck->execute();
if ($ownerCheck->get_result()->num_rows !== 1) {
    http_response_code(404);
    exit('Not Found');
}

$stmt = $mysqli->prepare("
    UPDATE diary_events
    SET title=?, start_time=?, end_time=?, description=?, event_date=?
    WHERE id=? AND user_id=?
");

$stmt->bind_param(
    "sssssis",
    $title,
    $start_time,
    $end_time,
    $description,
    $event_date,
    $id,
    $userId
);

$stmt->execute();

echo "OK";
