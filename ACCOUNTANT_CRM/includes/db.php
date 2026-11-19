<?php
require_once __DIR__ . '/config.php';
$host = crm_config('DB_HOST');
$user = crm_config('DB_USER');
$password = crm_config('DB_PASSWORD');
$database = crm_config('DB_NAME');

try {
    $mysqli = new mysqli($host, $user, $password, $database);
    $mysqli->set_charset('utf8mb4');
} catch (Throwable) {
    crm_config_fail();
}
