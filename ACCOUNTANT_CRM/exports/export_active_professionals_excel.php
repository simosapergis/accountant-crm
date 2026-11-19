<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/export_filters.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // PhpSpreadsheet


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/* -------------------------------------------------------------
   ΔΙΑΒΑΣΗ ΦΙΛΤΡΩΝ ΑΠΟ ΤΟ index.php
-------------------------------------------------------------- */
$activeOnly = crm_export_string_filter('active', crm_export_string_filter('is_active', '1'));
$vatFilter  = crm_export_string_filter('vat', crm_export_string_filter('has_vat'));
$vatType    = crm_export_string_filter('vat_type');
$payroll    = crm_export_string_filter('payroll');

/* -------------------------------------------------------------
   ΧΤΙΖΟΥΜΕ ΤΟ WHERE
-------------------------------------------------------------- */
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

/* -------------------------------------------------------------
   QUERY
-------------------------------------------------------------- */
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
$result = $stmt->get_result();

/* -------------------------------------------------------------
   ΔΗΜΙΟΥΡΓΙΑ EXCEL
-------------------------------------------------------------- */
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Ενεργοί Επαγγελματίες");

/* -------------------------------------------------------------
   ΚΕΦΑΛΙΔΕΣ
-------------------------------------------------------------- */
$headers = [
    "A1" => "Κωδικός",
    "B1" => "Ονοματεπώνυμο",
    "C1" => "ΑΦΜ",
    "D1" => "Κατηγορία",
    "E1" => "Τηλέφωνο",
    "F1" => "Email",
    "G1" => "Ενεργός",
    "H1" => "Ημερομηνία Έναρξης",
    "I1" => "Ημερομηνία Διακοπής",
    "J1" => "ΦΠΑ",
    "K1" => "Τύπος ΦΠΑ",
    "L1" => "Μισθοδοσία"
];

foreach ($headers as $cell => $title) {
    $sheet->setCellValue($cell, $title);
}

/* Στυλ κεφαλίδων */
$sheet->getStyle("A1:L1")->getFont()->setBold(true);
$sheet->getStyle("A1:L1")->getFill()
    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
    ->getStartColor()->setARGB('FFE2E3E5');

/* -------------------------------------------------------------
   ΓΕΜΙΣΜΑ ΓΡΑΜΜΩΝ
-------------------------------------------------------------- */
$rowNum = 2;

while ($row = $result->fetch_assoc()) {

    $sheet->setCellValueExplicit("A$rowNum", (string)($row['code'] ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("B$rowNum", (string)$row['fullname'], DataType::TYPE_STRING);
    foreach (['C' => 'afm', 'D' => 'category', 'E' => 'phone', 'F' => 'email'] as $col => $field) {
        $sheet->setCellValueExplicit($col . $rowNum, (string)($row[$field] ?? ''), DataType::TYPE_STRING);
    }
    $sheet->setCellValue("G$rowNum", $row['is_active'] ? "ΝΑΙ" : "ΟΧΙ");
    $sheet->setCellValueExplicit("H$rowNum", (string)($row['start_date'] ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("I$rowNum", (string)($row['end_date'] ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValue("J$rowNum", $row['has_vat'] ? "ΝΑΙ" : "ΟΧΙ");
    $sheet->setCellValueExplicit("K$rowNum", (string)($row['vat_type'] ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValue("L$rowNum", $row['has_payroll'] ? "ΝΑΙ" : "ΟΧΙ");

    $rowNum++;
}

/* -------------------------------------------------------------
   AUTO SIZE ΣΤΙΣ ΣΤΗΛΕΣ
-------------------------------------------------------------- */
foreach (range('A','L') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

/* -------------------------------------------------------------
   DOWNLOAD
-------------------------------------------------------------- */
$filename = "Ενεργοί_Επαγγελματίες.xlsx";

header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$filename\"");
$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;


