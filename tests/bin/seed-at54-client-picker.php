<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

if (!CRM_TEST_DB_SAFE || getenv('DB_NAME') !== CRM_TEST_DATABASE || getenv('DB_USER') === 'root') {
    fwrite(STDERR, "Refusing to seed AT-54 fixtures outside the explicitly configured isolated test database.\n");
    exit(2);
}

$db = new mysqli(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASSWORD'), getenv('DB_NAME'));
if ($db->connect_error || $db->query('SELECT DATABASE()')->fetch_row()[0] !== CRM_TEST_DATABASE) {
    fwrite(STDERR, "Cannot seed AT-54 fixtures: configured MariaDB target could not be verified.\n");
    exit(2);
}

$lookup = $db->prepare('SELECT name, afm FROM clients WHERE id = ?');
$insert = $db->prepare("INSERT INTO clients (id, name, afm, category) VALUES (?, ?, ?, 'Ιδιώτης')");
for ($index = 0; $index < 250; $index++) {
    $id = 911000 + $index;
    $name = $index === 0
        ? 'AT54 AFM Search Target'
        : sprintf('AT54 Long List Client %03d', $index);
    $afm = sprintf('99000%04d', $index + 1);

    $lookup->bind_param('i', $id);
    $lookup->execute();
    $existing = $lookup->get_result()->fetch_assoc();
    if ($existing !== null) {
        if ($existing['name'] !== $name || $existing['afm'] !== $afm) {
            fwrite(STDERR, "Refusing to overwrite a client outside the AT-54 fixture set.\n");
            exit(2);
        }
        continue;
    }

    $insert->bind_param('iss', $id, $name, $afm);
    $insert->execute();
}

fwrite(STDOUT, "Seeded or verified 250 fictional AT-54 client-picker fixtures.\n");
