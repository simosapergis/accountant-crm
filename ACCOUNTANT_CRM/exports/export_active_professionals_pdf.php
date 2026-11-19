<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/export_filters.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/pdf.php';



// -------------------------------------------------------------
// ΦΙΛΤΡΑ
// -------------------------------------------------------------
$activeOnly = crm_export_string_filter('active', crm_export_string_filter('is_active', '1'));
$vatFilter  = crm_export_string_filter('vat', crm_export_string_filter('has_vat'));
$vatType    = crm_export_string_filter('vat_type');
$payroll    = crm_export_string_filter('payroll');

// -------------------------------------------------------------
// WHERE
// -------------------------------------------------------------
$search = crm_export_string_filter('search');
$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(c.name LIKE ? OR c.professional_code LIKE ? OR c.afm LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
    for ($i = 0; $i < 5; $i++) $params[] = '%' . $search . '%';
}

if ($activeOnly === "1") {
    $conditions[] = 'c.professional_is_active = 1';
} elseif ($activeOnly === "0") {
    $conditions[] = 'c.professional_is_active = 0';
}

if ($vatFilter === "yes" || $vatFilter === "1") {
    $conditions[] = 'c.professional_has_vat = 1';
} elseif ($vatFilter === "no" || $vatFilter === "0") {
    $conditions[] = 'c.professional_has_vat = 0';
}

if ($vatType !== "") {
    $conditions[] = 'c.professional_vat_type = ?';
    $params[] = $vatType;
}

if ($payroll === "yes" || $payroll === "1") {
    $conditions[] = 'c.professional_has_payroll = 1';
} elseif ($payroll === "no" || $payroll === "0") {
    $conditions[] = 'c.professional_has_payroll = 0';
}
$conditions[] = 'c.has_professional_profile = 1';
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

// -------------------------------------------------------------
// QUERY
// -------------------------------------------------------------
$sql = "
    SELECT c.id, c.professional_code AS code, c.name AS fullname, c.afm, c.category, c.phone, c.email,
           c.professional_is_active AS is_active, c.professional_start_date AS start_date,
           c.professional_end_date AS end_date, c.professional_has_vat AS has_vat,
           c.professional_vat_type AS vat_type, c.professional_has_payroll AS has_payroll
    FROM clients c
    $where
    ORDER BY c.professional_code ASC
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
    td:first-child { white-space: nowrap; }
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
    <th>Επωνυμία / Ονοματεπώνυμο</th>
    <th>ΑΦΜ</th>
    <th>Κατηγορία</th>
    <th>Τηλέφωνο</th>
    <th>Email</th>
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
    $afm = htmlspecialchars((string)($row['afm'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $category = htmlspecialchars((string)($row['category'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $phone = htmlspecialchars((string)($row['phone'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $email = htmlspecialchars((string)($row['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $startDate = htmlspecialchars((string)($row['start_date'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $endDate = htmlspecialchars((string)($row['end_date'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $vatTypeOutput = htmlspecialchars((string)($row['vat_type'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $html .= "
    <tr>
        <td>{$code}</td>
        <td>{$fullname}</td>
        <td>{$afm}</td>
        <td>{$category}</td>
        <td>{$phone}</td>
        <td>{$email}</td>
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
$dompdf = crm_pdf_renderer();

$dompdf->loadHtml($html, "UTF-8");
$dompdf->setPaper("A4", "portrait");
$dompdf->render();

$dompdf->stream("Ενεργοί_Επαγγελματίες.pdf", [
    "Attachment" => true
]);
exit;


