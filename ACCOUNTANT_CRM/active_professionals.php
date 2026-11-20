<?php
require_once __DIR__ . '/includes/auth.php';
$filters = [];
foreach (['search' => 'search', 'vat_type' => 'vat_type'] as $old => $new) {
    if (isset($_GET[$old]) && is_string($_GET[$old])) $filters[$new] = $_GET[$old];
}
$oldVat = $_GET['has_vat'] ?? null;
if (is_string($oldVat) && in_array($oldVat, ['0','1'], true)) $filters['vat'] = $oldVat === '1' ? 'yes' : 'no';
$oldPayroll = $_GET['payroll'] ?? null;
if (is_string($oldPayroll) && in_array($oldPayroll, ['0','1'], true)) $filters['payroll'] = $oldPayroll === '1' ? 'yes' : 'no';
$filters['entity'] = 'professional';
$active = $_GET['is_active'] ?? '1';
if (is_string($active) && in_array($active, ['0','1'], true)) $filters['is_active'] = $active;
header('Location: clients.php?' . http_build_query($filters));
exit;
