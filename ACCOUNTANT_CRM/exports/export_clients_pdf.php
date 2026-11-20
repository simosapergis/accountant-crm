<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/export_filters.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';


$search = crm_export_string_filter('search');
$category = crm_export_string_filter('category');
$balanceFilter = crm_export_string_filter('balance');
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
$having = match ($balanceFilter) {
    'positive', 'with' => ' HAVING total_fees > total_collected',
    'zero' => ' HAVING total_fees = total_collected',
    default => '',
};

$sql = "
    SELECT c.*,
           COALESCE(SUM(t.fee),0) AS total_fees,
           COALESCE(SUM(t.collected),0) AS total_collected
    FROM clients c
    LEFT JOIN tasks t ON t.client_id = c.id
    $where
    GROUP BY c.id
    $having
    ORDER BY c.name
";
$stmt = $mysqli->prepare($sql);
$stmt->execute($params);
$res = $stmt->get_result();
ob_start();
?>
<!doctype html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title>Αναφορά Πελατών</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; margin: 0; }
        h2 { margin-bottom: 10px; }
        table { width:100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border:1px solid #000; padding:4px 6px; }
        th { background:#f0f0f0; }
        .note { margin-top: 15px; font-size: 11px; color:#555; }
    </style>
</head>
<body>

<h2>Αναφορά Πελατών</h2>

<table>
    <thead>
        <tr>
            <th>Επωνυμία</th>
            <th>ΑΦΜ</th>
            <th>Κατηγορία</th>
            <th>Τηλέφωνο</th>
            <th>Email</th><th>Ενεργός</th><th>Έναρξη</th><th>Διακοπή</th><th>Μισθοδοσία</th>
            <th>Αμοιβές</th>
            <th>Εισπράξεις</th>
            <th>Υπόλοιπο</th>
        </tr>
    </thead>

    <tbody>
    <?php 
    $total_fee = 0;
    $total_col = 0;

    while ($c = $res->fetch_assoc()):
        $balance = $c['total_fees'] - $c['total_collected'];

        $total_fee += $c['total_fees'];
        $total_col += $c['total_collected'];
    ?>
        <tr>
            <td><?= htmlspecialchars((string)($c['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($c['afm'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($c['category'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($c['phone'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($c['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= !empty($c['has_professional_profile']) ? (!empty($c['professional_is_active']) ? 'Ναι' : 'Όχι') : '' ?></td>
            <td><?= htmlspecialchars((string)($c['professional_start_date'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($c['professional_end_date'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= !empty($c['has_professional_profile']) ? (!empty($c['professional_has_payroll']) ? 'Ναι' : 'Όχι') : '' ?></td>
            <td><?= formatMoney($c['total_fees']) ?></td>
            <td><?= formatMoney($c['total_collected']) ?></td>
            <td><?= formatMoney($balance) ?></td>
        </tr>
    <?php endwhile; ?>
    </tbody>

    <tfoot>
        <tr>
            <td colspan="9" style="font-weight:bold;">Σύνολα</td>
            <td><?= formatMoney($total_fee) ?></td>
            <td><?= formatMoney($total_col) ?></td>
            <td><?= formatMoney($total_fee - $total_col) ?></td>
        </tr>
    </tfoot>

</table>

</body>
</html>
<?php
$html = ob_get_clean();
require_once dirname(__DIR__) . '/vendor/autoload.php';
$dompdf = new \Dompdf\Dompdf();
$dompdf->setPaper('A4', 'landscape');
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->render();
$dompdf->stream('clients_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
