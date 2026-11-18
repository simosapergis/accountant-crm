<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once CRM_TEST_APP_ROOT . '/includes/password_policy.php';

final class W02PasswordPolicyTest extends TestCase
{
    public function testFourteenUnicodeCharactersAreRejected(): void
    {
        self::assertNotNull(crm_password_policy_error(str_repeat('π', 14)));
    }

    public function testFifteenUnicodeCharactersAreAcceptedWithoutTrimmingSpaces(): void
    {
        $password = ' ' . str_repeat('π', 15) . ' ';

        self::assertNull(crm_password_policy_error($password));
        $hash = password_hash($password, PASSWORD_DEFAULT);
        self::assertNotSame($password, $hash);
        self::assertTrue(password_verify($password, $hash));
        self::assertFalse(password_verify(trim($password), $hash));
    }

    public function testBcryptByteLimitRejectsInputsThatWouldBeTruncated(): void
    {
        if (PASSWORD_DEFAULT !== PASSWORD_BCRYPT) {
            self::markTestSkipped('The current default password algorithm has no 72-byte bcrypt limit.');
        }

        self::assertNull(crm_password_policy_error(str_repeat('🔐', 18)));
        self::assertNotNull(crm_password_policy_error(str_repeat('🔐', 19)));
    }
}
