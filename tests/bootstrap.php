<?php
declare(strict_types=1);

define('CRM_TEST_APP_ROOT', rtrim((string) (getenv('CRM_APP_ROOT') ?: dirname(__DIR__) . '/ACCOUNTANT_CRM'), '/'));
define('CRM_TEST_DATABASE', (string) (getenv('CRM_TEST_DB_NAME') ?: ''));

if (!defined('CRM_TEST_DB_SAFE')) {
    define('CRM_TEST_DB_SAFE', CRM_TEST_DATABASE !== '' && (CRM_TEST_DATABASE === 'crm_w01_test' || str_starts_with(CRM_TEST_DATABASE, 'crm_test_')));
}
