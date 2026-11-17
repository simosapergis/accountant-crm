<?php
declare(strict_types=1);

function crm_export_string_filter(string $key, string $default = ''): string
{
    if (!array_key_exists($key, $_GET)) {
        return $default;
    }

    if (!is_string($_GET[$key])) {
        http_response_code(400);
        exit('Invalid export filter.');
    }

    return $_GET[$key];
}

function crm_export_optional_id(string $key): ?int
{
    $value = crm_export_string_filter($key);
    if ($value === '') {
        return null;
    }

    if (!preg_match('/^[1-9][0-9]*$/D', $value) || (string)(int)$value !== $value) {
        http_response_code(400);
        exit('Invalid export identifier.');
    }

    return (int)$value;
}

function crm_excel_html_text(mixed $value): string
{
    $text = (string)($value ?? '');
    if (preg_match('/^[\x00-\x20]*[=+\-@]/', $text)) {
        $text = "'" . $text;
    }

    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
