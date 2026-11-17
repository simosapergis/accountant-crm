<?php
declare(strict_types=1);

/** Parse an unambiguous non-negative amount into integer cents. */
function crm_parse_money_cents(string $value): ?int
{
    $value = trim($value);
    if (preg_match('/^\d+$/D', $value) === 1) {
        $whole = $value;
        $fraction = '00';
    } elseif (preg_match('/^(\d+)[,.](\d{1,2})$/D', $value, $matches) === 1) {
        $whole = $matches[1];
        $fraction = str_pad($matches[2], 2, '0');
    } elseif (preg_match('/^(\d{1,3}(?:\.\d{3})+),(\d{1,2})$/D', $value, $matches) === 1) {
        $whole = str_replace('.', '', $matches[1]);
        $fraction = str_pad($matches[2], 2, '0');
    } else {
        return null;
    }

    $whole = ltrim($whole, '0');
    $whole = $whole === '' ? '0' : $whole;
    if (strlen($whole) > 8 || (strlen($whole) === 8 && $whole > '99999999')) {
        return null;
    }

    return ((int)$whole * 100) + (int)$fraction;
}

function crm_money_decimal(int $cents): string
{
    if ($cents < 0 || $cents > 9_999_999_999) {
        throw new InvalidArgumentException('Money cents are outside DECIMAL(10,2).');
    }
    return intdiv($cents, 100) . '.' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
}

function crm_money_input_value(string $decimal): string
{
    $cents = crm_parse_money_cents($decimal);
    if ($cents === null) {
        return '0,00';
    }
    $parts = explode('.', crm_money_decimal($cents));
    return number_format((int)$parts[0], 0, '', '.') . ',' . $parts[1];
}
