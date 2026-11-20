<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/export_filters.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';


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

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=clients_" . date('Ymd_His') . ".xls");
echo "<meta charset='UTF-8'>";

echo "<table border='1'>";
echo "<tr>
        <th>Επωνυμία</th>
        <th>ΑΦΜ</th>
        <th>Κατηγορία</th>
        <th>Τηλέφωνο</th>
        <th>Email</th><th>Κωδικός</th><th>Ενεργός</th><th>Έναρξη</th><th>Διακοπή</th><th>ΦΠΑ</th><th>Τύπος ΦΠΑ</th><th>Μισθοδοσία</th>
        <th>Αμοιβές</th>
        <th>Εισπράξεις</th>
        <th>Υπόλοιπο</th>
        <th># Εργασιών</th>
      </tr>";

while ($c = $res->fetch_assoc()) {
    echo "<tr>";
    echo "<td>".crm_excel_html_text($c['name'])."</td>";
    echo "<td>".crm_excel_html_text($c['afm'])."</td>";
    echo "<td>".crm_excel_html_text($c['category'])."</td>";
    echo "<td>".crm_excel_html_text($c['phone'])."</td>";
    echo "<td>".crm_excel_html_text($c['email'])."</td>";
    echo "<td>".crm_excel_html_text($c['professional_code'])."</td><td>".(!empty($c['has_professional_profile']) ? (!empty($c['professional_is_active']) ? 'Ναι' : 'Όχι') : '')."</td>";
    echo "<td>".crm_excel_html_text($c['professional_start_date'])."</td><td>".crm_excel_html_text($c['professional_end_date'])."</td>";
    echo "<td>".(!empty($c['has_professional_profile']) ? (!empty($c['professional_has_vat']) ? 'Ναι' : 'Όχι') : '')."</td><td>".crm_excel_html_text($c['professional_vat_type'])."</td><td>".(!empty($c['has_professional_profile']) ? (!empty($c['professional_has_payroll']) ? 'Ναι' : 'Όχι') : '')."</td>";
    echo "<td>".formatMoney($c['total_fees'])."</td>";
    echo "<td>".formatMoney($c['total_collected'])."</td>";
    echo "<td>".formatMoney($c['balance'])."</td>";
    echo "<td>".(int)$c['task_count']."</td>";
    echo "</tr>";
}
echo "</table>";
