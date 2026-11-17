<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/FrozenClock.php';

final class FrozenClockTest extends TestCase
{
    public function testClockReturnsTheExplicitAthensInstantWithoutChangingProcessTime(): void
    {
        $instant = new DateTimeImmutable('2027-02-28 12:30:00', new DateTimeZone('Europe/Athens'));
        $clock = new FrozenClock($instant);

        self::assertSame($instant, $clock->now());
        self::assertSame('Europe/Athens', $clock->now()->getTimezone()->getName());
    }
}
