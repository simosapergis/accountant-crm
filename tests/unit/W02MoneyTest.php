<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once '/var/www/html/crm/includes/money.php';

final class W02MoneyTest extends TestCase
{
    public function testParsesSupportedDecimalAndGreekGroupedAmountsToCents(): void
    {
        self::assertSame(10000, crm_parse_money_cents('100'));
        self::assertSame(10050, crm_parse_money_cents('100,50'));
        self::assertSame(10050, crm_parse_money_cents('100.50'));
        self::assertSame(123456, crm_parse_money_cents('1.234,56'));
    }

    public function testRejectsAmbiguousMalformedNegativeAndOutOfRangeAmounts(): void
    {
        foreach (['1.234', '1,234.56', '-1', '+1', '1,', '1.234,567', '100000000'] as $value) {
            self::assertNull(crm_parse_money_cents($value), "Expected {$value} to be rejected.");
        }
    }

    public function testFormatsCentsAsExactDatabaseDecimal(): void
    {
        self::assertSame('1234.56', crm_money_decimal(123456));
        self::assertSame('0.00', crm_money_decimal(0));
    }
}
