<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/password_policy.php';

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    return trim((string) fgets(STDIN));
}

function prompt_password(string $label): string
{
    fwrite(STDOUT, $label);
    $terminal_state = function_exists('shell_exec') ? shell_exec('stty -g 2>/dev/null') : null;
    $can_hide = $terminal_state !== null && trim($terminal_state) !== '' && function_exists('system');
    if (!$can_hide) {
        fwrite(STDERR, "Password entry requires an interactive terminal.\n");
        exit(1);
    }
    system('stty -echo');
    $password = rtrim((string)fgets(STDIN), "\r\n");
    system('stty echo');
    fwrite(STDOUT, PHP_EOL);
    return $password;
}

$username = prompt('Username: ');
$fullname = prompt('Full name: ');
$role = prompt('Role (admin/user) [user]: ') ?: 'user';

if ($username === '' || $fullname === '') {
    fwrite(STDERR, "Username and full name are required.\n");
    exit(1);
}
if (!in_array($role, ['admin', 'user'], true)) {
    fwrite(STDERR, "Role must be admin or user.\n");
    exit(1);
}

$password = prompt_password('Password: ');
$confirmation = prompt_password('Confirm password: ');
$passwordError = crm_password_policy_error($password);
if ($passwordError !== null) {
    fwrite(STDERR, $passwordError . "\n");
    exit(1);
}
if (!hash_equals($password, $confirmation)) {
    fwrite(STDERR, "Passwords do not match.\n");
    exit(1);
}

$stmt = $mysqli->prepare('INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, ?)');
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt->bind_param('ssss', $username, $hash, $fullname, $role);
if (!$stmt->execute()) {
    fwrite(STDERR, "Could not create user: {$stmt->error}\n");
    exit(1);
}

fwrite(STDOUT, "Created user '{$username}'.\n");
