<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/export_filters.php';
require_once "../includes/db.php";
require_once "../vendor/autoload.php"; // PhpSpreadsheet


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/* -------------------------------------------------------------
   ΔΙΑΒΑΣΗ ΦΙΛΤΡΩΝ ΑΠΟ ΤΟ index.php
-------------------------------------------------------------- */
$activeOnly = crm_export_string_filter('active', crm_export_string_filter('is_active'));
$vatFilter  = crm_export_string_filter('vat', crm_export_string_filter('has_vat'));
$vatType    = crm_export_string_filter('vat_type');
$payroll    = crm_export_string_filter('payroll');

/* -------------------------------------------------------------
   ΧΤΙΖΟΥΜΕ ΤΟ WHERE
-------------------------------------------------------------- */
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

/* -------------------------------------------------------------
   QUERY
-------------------------------------------------------------- */
$sql = "
    SELECT *
    FROM active_professionals
    $where
    ORDER BY code ASC
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
    "C1" => "Ενεργός",
    "D1" => "Ημερομηνία Έναρξης",
    "E1" => "Ημερομηνία Διακοπής",
    "F1" => "ΦΠΑ",
    "G1" => "Τύπος ΦΠΑ",
    "H1" => "Μισθοδοσία"
];

foreach ($headers as $cell => $title) {
    $sheet->setCellValue($cell, $title);
}

/* Στυλ κεφαλίδων */
$sheet->getStyle("A1:H1")->getFont()->setBold(true);
$sheet->getStyle("A1:H1")->getFill()
    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
    ->getStartColor()->setARGB('FFE2E3E5');

/* -------------------------------------------------------------
   ΓΕΜΙΣΜΑ ΓΡΑΜΜΩΝ
-------------------------------------------------------------- */
$rowNum = 2;

while ($row = $result->fetch_assoc()) {

    $sheet->setCellValue("A$rowNum", $row['code']);
    $sheet->setCellValue("B$rowNum", $row['fullname']);
    $sheet->setCellValue("C$rowNum", $row['is_active'] ? "ΝΑΙ" : "ΟΧΙ");
    $sheet->setCellValue("D$rowNum", $row['start_date']);
    $sheet->setCellValue("E$rowNum", $row['end_date']);
    $sheet->setCellValue("F$rowNum", $row['has_vat'] ? "ΝΑΙ" : "ΟΧΙ");
    $sheet->setCellValue("G$rowNum", $row['vat_type']);
    $sheet->setCellValue("H$rowNum", $row['has_payroll'] ? "ΝΑΙ" : "ΟΧΙ");

    $rowNum++;
}

/* -------------------------------------------------------------
   AUTO SIZE ΣΤΙΣ ΣΤΗΛΕΣ
-------------------------------------------------------------- */
foreach (range('A','H') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

/* -------------------------------------------------------------
   DOWNLOAD
-------------------------------------------------------------- */
$filename = "Ενεργοί_Επαγγελματίες.xlsx";

header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;


