<?php
declare(strict_types=1);

return static function (mysqli $db): void {
    $db->query('CREATE TABLE IF NOT EXISTS login_throttle (' .
        'scope_type ENUM("identity","source") NOT NULL, scope_hash CHAR(64) NOT NULL, ' .
        'window_started_at DATETIME NOT NULL, failure_count INT UNSIGNED NOT NULL DEFAULT 0, locked_until DATETIME NULL, ' .
        'updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ' .
        'PRIMARY KEY (scope_type, scope_hash), KEY idx_login_throttle_updated (updated_at)' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
    $db->query('CREATE TABLE IF NOT EXISTS security_events (' .
        'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, occurred_at DATETIME NOT NULL, event_type VARCHAR(40) NOT NULL, result VARCHAR(24) NOT NULL, ' .
        'identity_hash CHAR(64) NULL, source_hash CHAR(64) NULL, metadata JSON NULL, PRIMARY KEY (id), ' .
        'KEY idx_security_events_time (occurred_at), KEY idx_security_events_type_time (event_type, occurred_at)' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
};
