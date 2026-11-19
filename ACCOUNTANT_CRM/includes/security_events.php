<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function crm_security_key(): string
{
    return crm_config('CRM_SECURITY_HMAC_KEY');
}

function crm_security_hash(string $value): string
{
    return hash_hmac('sha256', $value, crm_security_key());
}

function crm_record_security_event(mysqli $db, string $type, string $result, ?string $identityHash, ?string $sourceHash, array $metadata = []): void
{
    $json = $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR);
    $stmt = $db->prepare('INSERT INTO security_events (occurred_at,event_type,result,identity_hash,source_hash,metadata) VALUES (UTC_TIMESTAMP(),?,?,?,?,?)');
    $stmt->bind_param('sssss', $type, $result, $identityHash, $sourceHash, $json);
    $stmt->execute();
}

function crm_login_throttle_status(mysqli $db, string $identityHash, string $sourceHash): array
{
    foreach ([['identity', $identityHash, 5], ['source', $sourceHash, 30]] as [$scope, $hash, $limit]) {
        $stmt = $db->prepare('SELECT locked_until, window_started_at, failure_count FROM login_throttle WHERE scope_type=? AND scope_hash=?');
        $stmt->bind_param('ss', $scope, $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && $row['locked_until'] !== null && strtotime($row['locked_until'] . ' UTC') > time()) return ['limited'=>true,'scope'=>$scope];
    }
    return ['limited'=>false,'scope'=>null];
}

function crm_login_failure(mysqli $db, string $identityHash, string $sourceHash): bool
{
    $window = crm_security_setting('CRM_LOGIN_WINDOW_SECONDS', 900);
    $cooldown = crm_security_setting('CRM_LOGIN_COOLDOWN_SECONDS', 900);
    foreach ([['identity', $identityHash, crm_security_setting('CRM_LOGIN_IDENTITY_LIMIT', 5)], ['source', $sourceHash, crm_security_setting('CRM_LOGIN_SOURCE_LIMIT', 30)]] as [$scope, $hash, $limit]) {
        $stmt = $db->prepare("UPDATE login_throttle SET failure_count=IF(window_started_at <= UTC_TIMESTAMP() - INTERVAL $window SECOND, 1, failure_count+1), locked_until=IF(failure_count >= ?, UTC_TIMESTAMP() + INTERVAL $cooldown SECOND, NULL), window_started_at=IF(window_started_at <= UTC_TIMESTAMP() - INTERVAL $window SECOND, UTC_TIMESTAMP(), window_started_at) WHERE scope_type=? AND scope_hash=?");
        $stmt->bind_param('iss', $limit, $scope, $hash);
        $stmt->execute();
    }
    return crm_login_throttle_status($db, $identityHash, $sourceHash)['limited'];
}

function crm_security_setting(string $name, int $default): int
{
    $value = crm_config($name);
    if ($value === '') return $default;
    return (int)$value;
}

// Source first, then identity: all login attempts take locks in the same order.
function crm_login_begin(mysqli $db, string $identityHash, string $sourceHash): array
{
    $db->begin_transaction();
    foreach ([['source', $sourceHash], ['identity', $identityHash]] as [$scope, $hash]) {
        $stmt = $db->prepare('INSERT INTO login_throttle (scope_type,scope_hash,window_started_at,failure_count) VALUES (?,?,UTC_TIMESTAMP(),0) ON DUPLICATE KEY UPDATE scope_hash=VALUES(scope_hash)');
        $stmt->bind_param('ss', $scope, $hash); $stmt->execute();
        $stmt = $db->prepare('SELECT failure_count FROM login_throttle WHERE scope_type=? AND scope_hash=? FOR UPDATE');
        $stmt->bind_param('ss', $scope, $hash); $stmt->execute(); $stmt->get_result()->fetch_assoc();
    }
    return crm_login_throttle_status($db, $identityHash, $sourceHash);
}

function crm_login_success(mysqli $db, string $identityHash): void
{
    $stmt = $db->prepare('DELETE FROM login_throttle WHERE scope_type="identity" AND scope_hash=?');
    $stmt->bind_param('s', $identityHash); $stmt->execute();
}

function crm_security_cleanup(mysqli $db): void
{
    $days = crm_security_setting('CRM_SECURITY_RETENTION_DAYS', 30);
    $db->query("DELETE FROM security_events WHERE occurred_at < UTC_TIMESTAMP() - INTERVAL $days DAY");
    $db->query("DELETE FROM login_throttle WHERE updated_at < UTC_TIMESTAMP() - INTERVAL $days DAY AND (locked_until IS NULL OR locked_until <= UTC_TIMESTAMP())");
}
