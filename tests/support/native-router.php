<?php
declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$blocked = [
    '~(?:^|/)\.env(?:\.|$)~i',
    '~(?:^|/)accountant_crm\.sql$~i',
    '~^/crm/(?:includes|migrations|vendor)(?:/|$)~i',
    '~^/crm/(?:logs|backups)(?:/|$)~i',
    '~^/crm/[^/]+\.(?:log|sql|bak|backup|zip)$~i',
    '~^/crm/(?:composer\.(?:json|lock)|create_user\.php)$~i',
    '~^/crm/bin/(?:run-recurring-tasks|migrate)\.php$~i',
];
foreach ($blocked as $pattern) {
    if (preg_match($pattern, $path) === 1) {
        http_response_code(403);
        exit;
    }
}

return false;
