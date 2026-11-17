<?php
declare(strict_types=1);

function crm_password_policy_error(string $password): ?string
{
    if (mb_strlen($password, 'UTF-8') < 15) {
        return 'Password must contain at least 15 Unicode characters.';
    }

    if (PASSWORD_DEFAULT === PASSWORD_BCRYPT && strlen($password) > 72) {
        return 'Password exceeds the current hashing algorithm input limit.';
    }

    return null;
}
