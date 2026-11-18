<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/migration_runner.php';

try {
    $applied = crm_apply_migrations($mysqli);
    if ($applied === []) {
        fwrite(STDOUT, "Database schema is up to date.\n");
    } else {
        fwrite(STDOUT, 'Applied migrations: ' . implode(', ', $applied) . "\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
