<?php
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'DB_USER';
$password = getenv('DB_PASSWORD') ?: 'DB_PASSWORD';
$database = getenv('DB_NAME') ?: 'DB_NAME';

$mysqli = new mysqli($host, $user, $password, $database);

if ($mysqli->connect_error) {
    die('Database connection failed');
}

$mysqli->set_charset('utf8mb4');
