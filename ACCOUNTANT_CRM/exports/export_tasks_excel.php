<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/export_filters.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';


$search     = crm_export_string_filter('search');
$status     = crm_export_string_filter('status');
$client_id  = crm_export_optional_id('client');
$partner_id = crm_export_optional_id('partner');

$date_range_type = crm_export_string_filter('date_range_type');
$date_from       = crm_export_string_filter('date_from');
$date_to         = crm_export_string_filter('date_to');

$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = 't.title LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($status !== '') {
    $conditions[] = 't.status = ?';
    $params[] = $status;
}
if ($client_id !== null) {
    $conditions[] = 't.client_id = ?';
    $params[] = $client_id;
}
if ($partner_id !== null) {
    $conditions[] = 't.partner_id = ?';
    $params[] = $partner_id;
}

// Ημερομηνίες
if ($date_range_type == "today") {
    $conditions[] = 't.task_date = CURDATE()';
} elseif ($date_range_type == "this_week") {
    $conditions[] = 'YEARWEEK(t.task_date, 1) = YEARWEEK(CURDATE(), 1)';
} elseif ($date_range_type == "this_month") {
    $conditions[] = 'MONTH(t.task_date)=MONTH(CURDATE()) AND YEAR(t.task_date)=YEAR(CURDATE())';
} elseif ($date_range_type == "prev_month") {
    $conditions[] = 'MONTH(t.task_date)=MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(t.task_date)=YEAR(CURDATE() - INTERVAL 1 MONTH)';
} elseif ($date_range_type == "custom" && $date_from && $date_to) {
    $conditions[] = 't.task_date BETWEEN ? AND ?';
    $params[] = $date_from;
    $params[] = $date_to;
}
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$sql = "
    SELECT t.*,
           c.name AS client_name,
           p.fullname AS partner_name
    FROM tasks t
    JOIN clients c ON c.id = t.client_id
    LEFT JOIN partners p ON p.id = t.partner_id
    $where
    ORDER BY t.task_date DESC, t.id DESC
";

$stmt = $mysqli->prepare($sql);
$stmt->execute($params);
$res = $stmt->get_result();

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=tasks_" . date('Ymd_His') . ".xls");
echo "<meta charset='UTF-8'>";

echo "<table border='1'>";
echo "<tr>
        <th>Ημ/νία</th>
        <th>Τίτλος</th>
        <th>Πελάτης</th>
        <th>Συνεργάτης</th>
        <th>Κατάσταση</th>
        <th>Αμοιβή</th>
        <th>Είσπραξη</th>
        <th>Υπόλοιπο</th>
      </tr>";

while ($row = $res->fetch_assoc()) {
    $balance = $row['fee'] - $row['collected'];
    echo "<tr>";
    echo "<td>" . greekDateFromDb($row['task_date']) . "</td>";
    echo "<td>" . htmlspecialchars($row['title']) . "</td>";
    echo "<td>" . htmlspecialchars($row['client_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['partner_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "<td>" . number_format($row['fee'],2,',','.') . "</td>";
    echo "<td>" . number_format($row['collected'],2,',','.') . "</td>";
    echo "<td>" . number_format($balance,2,',','.') . "</td>";
    echo "</tr>";
}
echo "</table>";
