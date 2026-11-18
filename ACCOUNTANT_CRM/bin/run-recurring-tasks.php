<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/migration_runner.php';
require_once dirname(__DIR__) . '/includes/recurrence.php';

$dryRun = in_array('--dry-run', $argv, true);
$asOf = null;
$failAfterTask = null;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--as-of=')) $asOf = substr($argument, 8);
    if (str_starts_with($argument, '--fail-after-task=')) $failAfterTask = substr($argument, 18);
}
if (($asOf !== null || $failAfterTask !== null) && getenv('APP_ENV') !== 'test') {
    fwrite(STDERR, "Clock overrides and fault injection are test-only.\n");
    exit(2);
}
if ($asOf !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $asOf)) {
    fwrite(STDERR, "Invalid --as-of date.\n");
    exit(2);
}

try {
    crm_apply_migrations($mysqli);
    $timezone = new DateTimeZone('Europe/Athens');
    $clock = $asOf === null ? new DateTimeImmutable('now', $timezone) : new DateTimeImmutable($asOf . ' 23:59:59', $timezone);
    $result = crm_recurrence_run($mysqli, $clock, 100, $dryRun, $failAfterTask);
    fwrite(STDOUT, sprintf("Recurring scheduler %s: created=%d skipped=%d failed=%d\n", $dryRun ? 'dry-run' : 'run', $result['created'], $result['skipped'], $result['failed']));
    foreach ($result['errors'] as $error) fwrite(STDERR, $error . "\n");
    exit($result['failed'] > 0 ? 1 : 0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Recurring scheduler failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
