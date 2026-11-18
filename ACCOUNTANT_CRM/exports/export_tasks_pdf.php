<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/export_filters.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';


// Φίλτρα (ίδια λογική με tasks.php)
$search     = crm_export_string_filter('search');
$status     = crm_export_string_filter('status');
$client_id  = crm_export_optional_id('client');
$partner_id = crm_export_optional_id('partner');

$date_range_type = crm_export_string_filter('date_range_type');
$date_from = crm_export_string_filter('date_from');
$date_to = crm_export_string_filter('date_to');
$conditions = [];
$params = [];

if ($search != "") {
    $conditions[] = 't.title LIKE ?';
    $params[] = '%' . $search . '%';
}

if ($status != "") {
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
    SELECT t.*, c.name AS client_name, p.fullname AS partner_name
    FROM tasks t
    JOIN clients c ON c.id = t.client_id
    LEFT JOIN partners p ON p.id = t.partner_id
    $where
    ORDER BY t.task_date DESC
";
$stmt = $mysqli->prepare($sql);
$stmt->execute($params);
$res = $stmt->get_result();
?>
<!doctype html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title>Αναφορά Εργασιών</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        h2 { margin-bottom: 10px; }
        table { width:100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border:1px solid #000; padding:4px 6px; }
        th { background:#f0f0f0; }
        .note { margin-top: 15px; font-size: 11px; color:#555; }
    </style>
</head>
<body>

<h2>Αναφορά Εργασιών</h2>

<table>
    <thead>
        <tr>
            <th>Ημερομηνία</th>
            <th>Τίτλος</th>
            <th>Πελάτης</th>
            <th>Συνεργάτης</th>
            <th>Κατάσταση</th>
            <th>Αμοιβή</th>
            <th>Είσπραξη</th>
            <th>Μέσο είσπραξης</th>
            <th>Υπόλοιπο</th>
        </tr>
    </thead>

    <tbody>
    <?php 
    $sum_fee = 0;
    $sum_collected = 0;

    while ($row = $res->fetch_assoc()):
        $balance = $row['fee'] - $row['collected'];
        $sum_fee += $row['fee'];
        $sum_collected += $row['collected'];
    ?>
        <tr>
            <td><?= greekDateFromDb($row['task_date']) ?></td>
            <td><?= htmlspecialchars((string)($row['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($row['client_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($row['partner_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($row['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= number_format($row['fee'], 2, ',', '.') ?> €</td>
            <td><?= number_format($row['collected'], 2, ',', '.') ?> €</td>
            <td><?= htmlspecialchars((string)($row['payment_method_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= number_format($balance, 2, ',', '.') ?> €</td>
        </tr>
    <?php endwhile; ?>
    </tbody>

    <tfoot>
        <tr>
            <td colspan="5" style="font-weight:bold;">Σύνολα</td>
            <td><?= number_format($sum_fee, 2, ',', '.') ?> €</td>
            <td><?= number_format($sum_collected, 2, ',', '.') ?> €</td>
            <td></td>
            <td><?= number_format($sum_fee - $sum_collected, 2, ',', '.') ?> €</td>
        </tr>
    </tfoot>

</table>

<p class="note">
    Για αποθήκευση σε PDF: <strong>Αρχείο → Εκτύπωση → Αποθήκευση ως PDF</strong>
</p>

</body>
</html>
