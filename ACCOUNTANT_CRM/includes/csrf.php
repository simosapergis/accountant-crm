<?php

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' .
        htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" />';
}

function csrf_validate_request(): void
{
    $expected = $_SESSION['_csrf_token'] ?? '';
    $submitted = $_POST['_csrf_token'] ?? '';

    if (!is_string($expected) || $expected === '' || !is_string($submitted) || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

function require_post_method(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        exit('Method Not Allowed');
    }
}
