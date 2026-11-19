<?php
declare(strict_types=1);

/** Shared, process-local configuration for web entrypoints and CLI commands. */
function crm_config_fail(): never
{
    $message = "CRM configuration unavailable.\n";
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message);
        exit(78);
    }
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    echo $message;
    exit;
}

function crm_config_defaults(): array
{
    return [
        'APP_BASE_PATH' => '/crm', 'APP_TIMEZONE' => 'Europe/Athens',
        'CRM_LOGIN_IDENTITY_LIMIT' => '', 'CRM_LOGIN_SOURCE_LIMIT' => '',
        'CRM_LOGIN_WINDOW_SECONDS' => '', 'CRM_LOGIN_COOLDOWN_SECONDS' => '',
        'CRM_SECURITY_RETENTION_DAYS' => '',
    ];
}

function crm_config_validate(string $name, mixed $value): string
{
    if (str_starts_with($name, 'CRM_LOGIN_') || $name === 'CRM_SECURITY_RETENTION_DAYS') {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]{0,7}$/D', (string)$value)
            || (int)$value > 31536000) {
            throw new RuntimeException('Invalid configuration.');
        }
        return (string)$value;
    }
    if (!is_string($value) || $value === '' || str_contains($value, "\0")) {
        throw new RuntimeException('Invalid configuration.');
    }
    if ($name === 'CRM_SECURITY_HMAC_KEY' && strlen($value) < 32) {
        throw new RuntimeException('Invalid configuration.');
    }
    if ($name === 'APP_TIMEZONE' && $value !== 'Europe/Athens') {
        throw new RuntimeException('Invalid configuration.');
    }
    if ($name === 'APP_BASE_PATH' && !preg_match('~^/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]*$~D', $value)) {
        throw new RuntimeException('Invalid configuration.');
    }
    return $value;
}

function crm_config_path_is_within(string $path, string $root): bool
{
    $root = rtrim($root, DIRECTORY_SEPARATOR);
    return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
}

/** Only trusted installation-owned PHP files are supported, never user uploads. */
function crm_config_read_private(string $path): array
{
    if (!str_starts_with($path, '/') || str_contains($path, "\0")) {
        throw new RuntimeException('Invalid configuration.');
    }
    $resolved = realpath($path);
    if ($resolved === false || !is_file($resolved) || !is_readable($resolved) || strtolower(basename($resolved)) === 'wp-config.php') {
        throw new RuntimeException('Invalid configuration.');
    }
    $appRoot = dirname(__DIR__);
    $publicPath = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    $documentRoot = $publicPath !== '' ? (realpath($publicPath) ?: dirname($appRoot)) : dirname($appRoot);
    if (crm_config_path_is_within($resolved, $appRoot) || crm_config_path_is_within($resolved, $documentRoot)) {
        throw new RuntimeException('Invalid configuration.');
    }
    // Source checkouts place ACCOUNTANT_CRM directly under the repository root.
    // Do not probe unrelated ancestors, which can be outside hosting open_basedir.
    $sourceRoot = dirname($appRoot);
    if (file_exists($sourceRoot . '/.git') && crm_config_path_is_within($resolved, $sourceRoot)) {
        throw new RuntimeException('Invalid configuration.');
    }
    $permissions = fileperms($resolved);
    if ($permissions === false || ($permissions & 0027) !== 0) {
        throw new RuntimeException('Invalid configuration.');
    }

    $bufferLevel = ob_get_level();
    ob_start();
    set_error_handler(static function (): never { throw new RuntimeException('Invalid configuration.'); });
    try {
        $values = (static function (string $file): mixed { return require $file; })($resolved);
        if (ob_get_contents() !== '' || !is_array($values)) {
            throw new RuntimeException('Invalid configuration.');
        }
    } finally {
        restore_error_handler();
        while (ob_get_level() > $bufferLevel) ob_end_clean();
    }

    $required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'CRM_SECURITY_HMAC_KEY'];
    $allowed = array_merge(array_keys(crm_config_defaults()), $required);
    foreach ($required as $name) {
        if (!array_key_exists($name, $values)) throw new RuntimeException('Invalid configuration.');
    }
    foreach ($values as $name => $value) {
        if (!is_string($name) || !in_array($name, $allowed, true)) throw new RuntimeException('Invalid configuration.');
        $values[$name] = crm_config_validate($name, $value);
    }
    return array_merge(crm_config_defaults(), $values);
}

function crm_config(string $name): string
{
    static $initialized = false;
    static $private = null;
    set_error_handler(static function (): never { throw new RuntimeException('Invalid configuration.'); });
    try {
        if (!$initialized) {
            $iniPath = get_cfg_var('crm.config_file');
            $environmentPath = getenv('CRM_CONFIG_FILE');
            $explicit = $iniPath !== false && $iniPath !== '' ? $iniPath : $environmentPath;
            $path = $explicit !== false && $explicit !== '' ? (string)$explicit : dirname(__DIR__, 3) . '/private/accountant-crm.php';
            if (($explicit !== false && $explicit !== '') || file_exists($path) || is_link($path)) {
                $private = crm_config_read_private($path);
            } elseif (!in_array(getenv('APP_ENV'), ['test', 'development'], true)) {
                throw new RuntimeException('Invalid configuration.');
            }
            $initialized = true;
        }
        if ($name === 'APP_ENV') return (string)(getenv('APP_ENV') ?: 'production');
        if ($private !== null) {
            if (!array_key_exists($name, $private)) throw new RuntimeException('Invalid configuration.');
            return $private[$name];
        }
        $defaults = crm_config_defaults();
        $allowed = array_merge(array_keys($defaults), ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'CRM_SECURITY_HMAC_KEY']);
        if (!in_array($name, $allowed, true)) throw new RuntimeException('Invalid configuration.');
        $value = getenv($name);
        if ($value === false || $value === '') {
            if (array_key_exists($name, $defaults)) return $defaults[$name];
            throw new RuntimeException('Invalid configuration.');
        }
        return crm_config_validate($name, $value);
    } catch (Throwable) {
        crm_config_fail();
    } finally {
        restore_error_handler();
    }
}
