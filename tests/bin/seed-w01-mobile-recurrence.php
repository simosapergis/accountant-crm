<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

if (!CRM_TEST_DB_SAFE || getenv('DB_NAME') !== CRM_TEST_DATABASE || getenv('DB_USER') === 'root') {
    fwrite(STDERR, "Refusing to seed the mobile recurrence fixture outside the isolated test database.\n");
    exit(2);
}

$db = new mysqli(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASSWORD'), getenv('DB_NAME'));
if ($db->connect_error || $db->query('SELECT DATABASE()')->fetch_row()[0] !== CRM_TEST_DATABASE) {
    fwrite(STDERR, "Cannot seed fixture: configured MariaDB target could not be verified.\n");
    exit(2);
}

$clientId = 910001;
$client = $db->prepare('SELECT id FROM clients WHERE id=?');
$client->bind_param('i', $clientId);
$client->execute();
if (!$client->get_result()->fetch_row()) {
    fwrite(STDERR, "Seed the standard W01 fixtures before the mobile recurrence fixture.\n");
    exit(2);
}

$title = 'W01 Mobile Recurring Filing';
$existing = $db->prepare('SELECT id FROM recurring_task_rules WHERE client_id=? AND title=? LIMIT 1');
$existing->bind_param('is', $clientId, $title);
$existing->execute();
$row = $existing->get_result()->fetch_assoc();
if ($row) {
    fwrite(STDOUT, "Mobile recurrence fixture already exists.\n");
    exit(0);
}

$partnerId = null;
$notes = 'Fictional mobile layout fixture';
$day = 15;
$startMonth = '2026-12-01';
$fee = '125.50';
$state = 'active';
$rule = $db->prepare(
    'INSERT INTO recurring_task_rules (source_task_id,client_id,title,partner_id,notes,day_of_month,start_month,fee,state) ' .
    'VALUES (NULL,?,?,?,?,?,?,?,?)'
);
$rule->bind_param('isisisss', $clientId, $title, $partnerId, $notes, $day, $startMonth, $fee, $state);
$rule->execute();
$ruleId = $db->insert_id;

$version = $db->prepare(
    'INSERT INTO recurring_task_rule_versions (rule_id,effective_month,day_of_month,client_id,title,partner_id,notes,fee) ' .
    'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$version->bind_param('isiisiss', $ruleId, $startMonth, $day, $clientId, $title, $partnerId, $notes, $fee);
$version->execute();

fwrite(STDOUT, "Seeded fictional W01 mobile recurrence fixture.\n");
