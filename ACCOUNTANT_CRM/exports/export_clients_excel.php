<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/export_filters.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;


$search   = crm_export_string_filter('search');
$category = crm_export_string_filter('category');
$balance  = crm_export_string_filter('balance');
$sort     = crm_export_string_filter('sort');
$entity = crm_export_string_filter('entity');
$isActive = crm_export_string_filter('is_active');
$vat = crm_export_string_filter('vat');
$vatType = crm_export_string_filter('vat_type');
$payroll = crm_export_string_filter('payroll');

$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = '(c.name LIKE ? OR c.professional_code LIKE ? OR c.afm LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
    $pattern = '%' . $search . '%';
    for ($i = 0; $i < 5; $i++) $params[] = $pattern;
}
if ($category !== '') {
    $conditions[] = 'c.category = ?';
    $params[] = $category;
}
if ($entity === 'private') $conditions[] = 'c.has_professional_profile = 0';
elseif ($entity === 'professional') $conditions[] = 'c.has_professional_profile = 1';
if ($isActive === '1' || $isActive === '0') { $conditions[] = 'c.has_professional_profile = 1 AND c.professional_is_active = ?'; $params[] = (int)$isActive; }
if ($vat === 'yes' || $vat === 'no') { $conditions[] = 'c.has_professional_profile = 1 AND c.professional_has_vat = ?'; $params[] = $vat === 'yes' ? 1 : 0; }
if ($vatType !== '' && in_array($vatType, ['Μηνιαίο','Τριμηνιαίο'], true)) { $conditions[] = 'c.has_professional_profile = 1 AND c.professional_vat_type = ?'; $params[] = $vatType; }
if ($payroll === 'yes' || $payroll === 'no') { $conditions[] = 'c.has_professional_profile = 1 AND c.professional_has_payroll = ?'; $params[] = $payroll === 'yes' ? 1 : 0; }
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
$having = match ($balance) {
    'positive', 'with' => ' HAVING balance > 0',
    'zero' => ' HAVING balance = 0',
    default => '',
};
$sql = "
    SELECT c.*,
           COALESCE(SUM(t.fee),0) AS total_fees,
           COALESCE(SUM(t.collected),0) AS total_collected,
           (COALESCE(SUM(t.fee),0) - COALESCE(SUM(t.collected),0)) AS balance,
           COUNT(t.id) AS task_count
    FROM clients c
    LEFT JOIN tasks t ON t.client_id = c.id
    $where
    GROUP BY c.id
    $having
";

$orderBy = match ($sort) {
    'name_desc' => ' ORDER BY c.name DESC',
    'balance' => ' ORDER BY balance DESC',
    'tasks' => ' ORDER BY task_count DESC',
    default => ' ORDER BY c.name ASC',
};
$sql .= $orderBy;

$stmt = $mysqli->prepare($sql);
$stmt->execute($params);
$res = $stmt->get_result();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Πελάτες');
$headers = ['Επωνυμία', 'ΑΦΜ', 'Κατηγορία', 'Τηλέφωνο', 'Email', 'Ενεργός', 'Έναρξη', 'Διακοπή', 'Μισθοδοσία', 'Αμοιβές', 'Εισπράξεις', 'Υπόλοιπο'];
$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:L1')->getFont()->setBold(true);
$sheet->getStyle('A1:L1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E3E5');
$rowNumber = 2;
while ($c = $res->fetch_assoc()) {
    $values = [
        $c['name'], $c['afm'] ?? '', $c['category'] ?? '', $c['phone'] ?? '', $c['email'] ?? '',
        !empty($c['has_professional_profile']) ? (!empty($c['professional_is_active']) ? 'Ναι' : 'Όχι') : '—',
        $c['professional_start_date'] ?? '', $c['professional_end_date'] ?? '',
        !empty($c['has_professional_profile']) ? (!empty($c['professional_has_payroll']) ? 'Ναι' : 'Όχι') : '—',
        (float)$c['total_fees'], (float)$c['total_collected'], (float)$c['balance'],
    ];
    foreach ($values as $index => $value) {
        $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
        if ($index <= 8) $sheet->setCellValueExplicit($column . $rowNumber, (string)$value, DataType::TYPE_STRING);
        else $sheet->setCellValue($column . $rowNumber, $value);
    }
    $rowNumber++;
}
$lastRow = max(1, $rowNumber - 1);
$sheet->getStyle("J2:L$lastRow")->getNumberFormat()->setFormatCode('#,##0.00');
foreach (range('A', 'L') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
$sheet->setAutoFilter("A1:L$lastRow");
$sheet->freezePane('A2');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="clients_' . date('Ymd_His') . '.xlsx"');
(new Xlsx($spreadsheet))->save('php://output');
exit;
