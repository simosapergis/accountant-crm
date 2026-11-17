<?php
declare(strict_types=1);

$expected = [
    'DB_HOST' => 'test-db',
    'DB_NAME' => 'crm_w01_test',
    'DB_USER' => 'crm_w01_user',
];

foreach ($expected as $key => $value) {
    if (getenv($key) !== $value) {
        fwrite(STDERR, "Refusing database test operation: {$key} does not match the isolated W01 target.\n");
        exit(2);
    }
}

$mysqli = new mysqli(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASSWORD'), getenv('DB_NAME'));
if ($mysqli->connect_error) {
    fwrite(STDERR, "Cannot connect to the isolated W01 database.\n");
    exit(2);
}

$actual = $mysqli->query('SELECT DATABASE() AS database_name')->fetch_assoc()['database_name'] ?? '';
if ($actual !== 'crm_w01_test') {
    fwrite(STDERR, "Refusing database test operation: connected database is not the isolated W01 target.\n");
    exit(2);
}

fwrite(STDOUT, "Verified isolated MariaDB target: test-db / crm_w01_test.\n");
