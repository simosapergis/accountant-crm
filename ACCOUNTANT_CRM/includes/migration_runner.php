<?php
declare(strict_types=1);

function crm_apply_migrations(mysqli $db, ?string $directory = null): array
{
    $directory ??= dirname(__DIR__) . '/migrations';
    if (!is_dir($directory) || !is_readable($directory)) {
        throw new RuntimeException('Migration directory is missing or unreadable: ' . $directory);
    }
    $db->query(
        'CREATE TABLE IF NOT EXISTS schema_migrations (' .
        'version VARCHAR(100) NOT NULL PRIMARY KEY, ' .
        'checksum CHAR(64) NOT NULL, ' .
        'applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $lockName = 'crm_schema_migrations';
    $lock = $db->prepare('SELECT GET_LOCK(?, 10) AS acquired');
    $lock->bind_param('s', $lockName);
    $lock->execute();
    if ((int)($lock->get_result()->fetch_assoc()['acquired'] ?? 0) !== 1) {
        throw new RuntimeException('Could not acquire schema migration lock.');
    }

    $applied = [];
    try {
        $files = glob(rtrim($directory, '/') . '/*.php') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $version = basename($file, '.php');
            if (!preg_match('/^\d{8}_\d{3}_[a-z0-9_]+$/D', $version)) {
                throw new RuntimeException('Invalid migration filename: ' . $version);
            }
            $checksum = hash_file('sha256', $file);
            $lookup = $db->prepare('SELECT checksum FROM schema_migrations WHERE version = ?');
            $lookup->bind_param('s', $version);
            $lookup->execute();
            $record = $lookup->get_result()->fetch_assoc();
            if ($record !== null) {
                if (!hash_equals((string)$record['checksum'], $checksum)) {
                    throw new RuntimeException('Applied migration checksum changed: ' . $version);
                }
                continue;
            }

            $migration = require $file;
            if (!is_callable($migration)) {
                throw new RuntimeException('Migration must return a callable: ' . $version);
            }
            $migration($db);

            $recordMigration = $db->prepare('INSERT INTO schema_migrations (version, checksum) VALUES (?, ?)');
            $recordMigration->bind_param('ss', $version, $checksum);
            $recordMigration->execute();
            $applied[] = $version;
        }
    } finally {
        $release = $db->prepare('SELECT RELEASE_LOCK(?)');
        $release->bind_param('s', $lockName);
        $release->execute();
    }

    return $applied;
}
