<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$host = (string) getenv('DB_HOST');
$name = (string) getenv('DB_NAME');
$user = (string) getenv('DB_USER');
if (!CRM_TEST_DB_SAFE || $name !== CRM_TEST_DATABASE || $name !== (string) (getenv('CRM_TEST_DB_NAME') ?: '')) {
    fwrite(STDERR, "Refusing database test operation: set CRM_TEST_DB_NAME to an explicitly isolated test database (crm_test_* or crm_w01_test).\n");
    exit(2);
}
if ($host === '' || $user === '' || $user === 'root' || str_contains(strtolower($name), 'prod') || str_contains(strtolower($name), 'production')) {
    fwrite(STDERR, "Refusing database test operation: unsafe connection identity.\n");
    exit(2);
}
$mysqli = new mysqli($host, $user, (string) getenv('DB_PASSWORD'), $name);
if ($mysqli->connect_error) {
    fwrite(STDERR, "Cannot connect to the configured isolated test database.\n");
    exit(2);
}
$actual = (string) ($mysqli->query('SELECT DATABASE() AS database_name')->fetch_assoc()['database_name'] ?? '');
$server = $mysqli->query('SELECT @@hostname AS hostname, @@datadir AS datadir')->fetch_assoc();
if ($actual !== CRM_TEST_DATABASE) {
    fwrite(STDERR, "Refusing database test operation: connected database differs from the explicit test target.\n");
    exit(2);
}
fwrite(STDOUT, 'Verified isolated MariaDB target: ' . $host . ' / ' . $actual . ' (' . (string) $server['hostname'] . ").\n");
