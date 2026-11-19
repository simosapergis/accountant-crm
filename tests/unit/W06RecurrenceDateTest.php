<?php
declare(strict_types=1);

require_once CRM_TEST_APP_ROOT . '/includes/recurrence.php';

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

    public function testAthensDateAcrossBothDstTransitionsAndLocalMidnight(): void
    {
        $cases = [
            '2027-03-27 21:59:59' => '2027-03-27',
            '2027-03-27 22:00:00' => '2027-03-28',
            '2027-03-28 00:59:59' => '2027-03-28',
            '2027-03-28 01:00:00' => '2027-03-28',
            '2027-03-28 20:59:59' => '2027-03-28',
            '2027-03-28 21:00:00' => '2027-03-29',
            '2027-10-30 20:59:59' => '2027-10-30',
            '2027-10-30 21:00:00' => '2027-10-31',
            '2027-10-31 00:59:59' => '2027-10-31',
            '2027-10-31 01:00:00' => '2027-10-31',
            '2027-10-31 21:59:59' => '2027-10-31',
            '2027-10-31 22:00:00' => '2027-11-01',
        ];
        foreach ($cases as $utc => $expected) {
            self::assertSame($expected, crm_recurrence_today(new DateTimeImmutable($utc, new DateTimeZone('UTC'))), $utc);
        }
    }
}
