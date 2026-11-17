<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/export_filters.php';
require_once "../includes/db.php";
require_once "../vendor/autoload.php"; // Dompdf


use Dompdf\Dompdf;

// -------------------------------------------------------------
// ΦΙΛΤΡΑ
// -------------------------------------------------------------
$activeOnly = crm_export_string_filter('active', crm_export_string_filter('is_active'));
$vatFilter  = crm_export_string_filter('vat', crm_export_string_filter('has_vat'));
$vatType    = crm_export_string_filter('vat_type');
$payroll    = crm_export_string_filter('payroll');

// -------------------------------------------------------------
// WHERE
// -------------------------------------------------------------
$conditions = [];
$params = [];

if ($activeOnly === "1") {
    $conditions[] = 'is_active = 1';
} elseif ($activeOnly === "0") {
    $conditions[] = 'is_active = 0';
}

if ($vatFilter === "yes" || $vatFilter === "1") {
    $conditions[] = 'has_vat = 1';
} elseif ($vatFilter === "no" || $vatFilter === "0") {
    $conditions[] = 'has_vat = 0';
}

if ($vatType !== "") {
    $conditions[] = 'vat_type = ?';
    $params[] = $vatType;
}

if ($payroll === "yes") {
    $conditions[] = 'has_payroll = 1';
} elseif ($payroll === "no") {
    $conditions[] = 'has_payroll = 0';
}
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

// -------------------------------------------------------------
// QUERY
// -------------------------------------------------------------
$sql = "
    SELECT *
    FROM active_professionals
    $where
    ORDER BY code ASC
";
$stmt = $mysqli->prepare($sql);
$stmt->execute($params);
$res = $stmt->get_result();

// -------------------------------------------------------------
// HTML TEMPLATE ΓΙΑ PDF
// -------------------------------------------------------------
$html = '
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<style>
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 11px;
        color: #111;
    }
    h2 {
        text-align: center;
        margin-bottom: 15px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 5px;
    }
    th {
        background: #e2e3e5;
        padding: 8px;
        border: 1px solid #999;
        font-size: 12px;
    }
    td {
        padding: 6px;
        border: 1px solid #ccc;
    }
</style>
</head>
<body>

<h2>ΑΝΑΦΟΡΑ ΕΝΕΡΓΩΝ ΕΠΑΓΓΕΛΜΑΤΙΩΝ</h2>

<table>
<thead>
<tr>
    <th>Κωδ.</th>
    <th>Ονοματεπώνυμο</th>
    <th>Ενεργός</th>
    <th>Έναρξη</th>
    <th>Διακοπή</th>
    <th>ΦΠΑ</th>
    <th>Τύπος ΦΠΑ</th>
    <th>Μισθ.</th>
</tr>
</thead>
<tbody>
';

// -------------------------------------------------------------
// ΓΕΜΙΣΜΑ ΠΙΝΑΚΑ
// -------------------------------------------------------------
while ($row = $res->fetch_assoc()) {

    $code = htmlspecialchars((string)($row['code'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $fullname = htmlspecialchars((string)$row['fullname'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $startDate = htmlspecialchars((string)($row['start_date'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $endDate = htmlspecialchars((string)($row['end_date'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $vatTypeOutput = htmlspecialchars((string)($row['vat_type'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $html .= "
    <tr>
        <td>{$code}</td>
        <td>{$fullname}</td>
        <td>".($row['is_active'] ? "ΝΑΙ" : "ΟΧΙ")."</td>
        <td>{$startDate}</td>
        <td>{$endDate}</td>
        <td>".($row['has_vat'] ? 'ΝΑΙ' : 'ΟΧΙ')."</td>
        <td>{$vatTypeOutput}</td>
        <td>".($row['has_payroll'] ? 'ΝΑΙ' : 'ΟΧΙ')."</td>
    </tr>";
}

$html .= "</tbody></table></body></html>";


// -------------------------------------------------------------
// DOMPDF
// -------------------------------------------------------------
$dompdf = new Dompdf([
    "defaultFont" => "DejaVu Sans"
]);

$dompdf->loadHtml($html, "UTF-8");
$dompdf->setPaper("A4", "portrait");
$dompdf->render();

$dompdf->stream("Ενεργοί_Επαγγελματίες.pdf", [
    "Attachment" => true
]);
exit;


