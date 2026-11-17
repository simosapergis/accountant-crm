<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once '/var/www/html/crm/includes/session_lifecycle.php';
require_once __DIR__ . '/../support/FrozenClock.php';

final class W01RuntimeTest extends TestCase
{
    public function testPhpRuntimeHasTheVersionAndExtensionsRequiredByTheHarness(): void
    {
        self::assertGreaterThanOrEqual(80300, PHP_VERSION_ID);

        foreach (['mysqli', 'gd', 'mbstring', 'zip', 'dom', 'xml'] as $extension) {
            self::assertTrue(extension_loaded($extension), "Required PHP extension {$extension} is loaded.");
        }
    }

    public function testSessionIdleExpiryUsesTheThirtyMinuteBoundaryWithAFrozenClock(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-11-17 12:30:01', new DateTimeZone('Europe/Athens')));
        $now = $clock->now()->getTimestamp();

        self::assertFalse(SessionLifecycle::isExpired($now - SessionLifecycle::IDLE_TIMEOUT_SECONDS + 1, $now));
        self::assertTrue(SessionLifecycle::isExpired($now - SessionLifecycle::IDLE_TIMEOUT_SECONDS, $now));
    }
}
