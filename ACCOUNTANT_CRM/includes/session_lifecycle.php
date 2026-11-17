<?php
declare(strict_types=1);

final class SessionLifecycle
{
    public const IDLE_TIMEOUT_SECONDS = 1800;

    public static function isExpired(int $lastActivity, int $now): bool
    {
        return $lastActivity > 0 && ($now - $lastActivity) >= self::IDLE_TIMEOUT_SECONDS;
    }
}
