<?php
declare(strict_types=1);

require_once '/var/www/html/crm/includes/recurrence.php';

use PHPUnit\Framework\TestCase;

final class W06RecurrenceDateTest extends TestCase
{
    public function testShortMonthsClampWithoutChangingNominalDay(): void
    {
        self::assertSame('2027-01-31', crm_recurrence_due_date('2027-01-01', 31));
        self::assertSame('2027-02-28', crm_recurrence_due_date('2027-02-01', 31));
        self::assertSame('2027-03-31', crm_recurrence_due_date('2027-03-01', 31));
        self::assertSame('2027-04-30', crm_recurrence_due_date('2027-04-01', 31));
    }

    public function testLeapFebruaryAndDay29And30ClampCorrectly(): void
    {
        self::assertSame('2028-02-29', crm_recurrence_due_date('2028-02-01', 31));
        self::assertSame('2027-02-28', crm_recurrence_due_date('2027-02-01', 29));
        self::assertSame('2027-02-28', crm_recurrence_due_date('2027-02-01', 30));
    }

    public function testMonthArithmeticUsesCalendarMonths(): void
    {
        self::assertSame('2027-01-01', crm_recurrence_month_add('2026-12-01', 1));
        self::assertSame('2026-12-01', crm_recurrence_month_add('2027-01-01', -1));
    }

    public function testBusinessClockAlwaysUsesAthensDate(): void
    {
        $utc = new DateTimeImmutable('2027-03-27 22:30:00', new DateTimeZone('UTC'));
        self::assertSame('2027-03-28', crm_recurrence_today($utc));
    }
}
