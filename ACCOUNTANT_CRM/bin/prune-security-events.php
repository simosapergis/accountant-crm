<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try {
    require_once dirname(__DIR__) . '/includes/db.php';
    require_once dirname(__DIR__) . '/includes/security_events.php';
    crm_security_cleanup($mysqli);
    fwrite(STDOUT, "Security retention cleanup complete.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Security retention cleanup failed.\n");
    exit(1);
}
