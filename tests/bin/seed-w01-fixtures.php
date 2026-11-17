<?php
declare(strict_types=1);

if (getenv('DB_HOST') !== 'test-db' || getenv('DB_NAME') !== 'crm_w01_test' || getenv('DB_USER') !== 'crm_w01_user') {
    fwrite(STDERR, "Refusing to seed fixtures outside the isolated W01 database.\n");
    exit(2);
}

$db = new mysqli(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASSWORD'), getenv('DB_NAME'));
if ($db->connect_error) {
    fwrite(STDERR, "Cannot seed W01 fixtures: isolated MariaDB is unavailable.\n");
    exit(2);
}

$clientId = 910001;
$client = $db->prepare(
    "INSERT INTO clients (id, name, category) VALUES (?, ?, 'Ιδιώτης') " .
    'ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category)'
);
$clientName = 'W01 Fixture Client';
$client->bind_param('is', $clientId, $clientName);
$client->execute();

$partnerId = 910001;
$partner = $db->prepare(
    'INSERT INTO partners (id, fullname) VALUES (?, ?) ' .
    'ON DUPLICATE KEY UPDATE fullname = VALUES(fullname)'
);
$partnerName = 'W01 Fixture Collaborator';
$partner->bind_param('is', $partnerId, $partnerName);
$partner->execute();

$taskId = 910001;
$taskDate = '2026-11-17';
$taskTitle = 'W01 Fixture Task';
$status = 'Σε εξέλιξη';
$fee = '100.00';
$collected = '0.00';
$task = $db->prepare(
    'INSERT INTO tasks (id, task_date, title, client_id, partner_id, status, fee, collected) ' .
    'VALUES (?, ?, ?, ?, ?, ?, ?, ?) ' .
    'ON DUPLICATE KEY UPDATE task_date = VALUES(task_date), title = VALUES(title), ' .
    'client_id = VALUES(client_id), partner_id = VALUES(partner_id), status = VALUES(status), ' .
    'fee = VALUES(fee), collected = VALUES(collected)'
);
$task->bind_param('issiisss', $taskId, $taskDate, $taskTitle, $clientId, $partnerId, $status, $fee, $collected);
$task->execute();

$userId = 910001;
$username = 'w01-fake-user';
$passwordHash = password_hash('W01-Fictional-Password-2026', PASSWORD_DEFAULT);
$fullname = 'W01 Fictional User';
$role = 'user';
$user = $db->prepare(
    'INSERT INTO users (id, username, password, fullname, role) VALUES (?, ?, ?, ?, ?) ' .
    'ON DUPLICATE KEY UPDATE password = VALUES(password), fullname = VALUES(fullname), role = VALUES(role)'
);
$user->bind_param('issss', $userId, $username, $passwordHash, $fullname, $role);
$user->execute();

fwrite(STDOUT, "Seeded idempotent fictional W01 user, client, collaborator, and task fixtures.\n");
