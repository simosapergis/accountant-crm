<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

$pageTitle = "Εισαγωγή Επαγγελματιών και Πελατών";

$message = "";
$errors = [];
$inserted = 0;

function importIniSizeBytes(string $value): int
{
    if (!preg_match('/^\s*(\d+)\s*([KMG]?)\s*$/i', $value, $matches)) {
        return PHP_INT_MAX;
    }

    $size = (int)$matches[1];
    $multiplier = match (strtoupper($matches[2])) {
        'G' => 1024 ** 3,
        'M' => 1024 ** 2,
        'K' => 1024,
        default => 1,
    };

    return $size > intdiv(PHP_INT_MAX, $multiplier) ? PHP_INT_MAX : $size * $multiplier;
}

/* ================================================================
   ΒΟΗΘΗΤΙΚΗ ΣΥΝΑΡΤΗΣΗ: Μετατροπή ημερομηνιών Excel → YYYY-MM-DD
================================================================ */
function convertDateExcelToDb($value)
{
    // Κενό → επιστροφή κενό
    if (trim($value) === "") return null;

    // Αν είναι ήδη YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    // Αν είναι DD/MM/YYYY
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
        [$d, $m, $y] = explode("/", $value);
        return "$y-$m-$d";
    }

    // Numeric Excel date (π.χ. 45123)
    if (is_numeric($value)) {
        $unix = ((int)$value - 25569) * 86400;
        return date("Y-m-d", $unix);
    }

    return ""; // fallback
}

/* ================================================================
   ΟΤΑΝ ΓΙΝΕΤΑΙ UPLOAD ΑΡΧΕΙΟΥ EXCEL
================================================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_FILES['excel_file'])) {
        http_response_code(400);
        $errors[] = 'Δεν ήταν δυνατή η ανάγνωση του αρχείου.';
    } elseif ($_FILES['excel_file']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['excel_file']['error'] === UPLOAD_ERR_FORM_SIZE) {
        http_response_code(413);
        $errors[] = 'Το αρχείο υπερβαίνει το επιτρεπόμενο μέγεθος.';
    } elseif ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        $errors[] = 'Δεν ήταν δυνατή η ανάγνωση του αρχείου.';
    } else {
        $file = (string)$_FILES['excel_file']['tmp_name'];
        $fileSize = (int)$_FILES['excel_file']['size'];
        $maxUploadBytes = importIniSizeBytes((string)ini_get('upload_max_filesize'));

        if (!is_uploaded_file($file) || !is_file($file) || $fileSize <= 0) {
            http_response_code(400);
            $errors[] = 'Δεν ήταν δυνατή η ανάγνωση του αρχείου.';
        } elseif ($fileSize > $maxUploadBytes) {
            http_response_code(413);
            $errors[] = 'Το αρχείο υπερβαίνει το επιτρεπόμενο μέγεθος.';
        } else {
            try {
                if (IOFactory::identify($file) !== 'Xlsx') {
                    http_response_code(400);
                    $errors[] = 'Το αρχείο πρέπει να είναι έγκυρο Excel XLSX.';
                } else {
                    $spreadsheet = IOFactory::load($file);
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = $sheet->toArray(null, true, true, true);

                    $legacyHeaders = [
                        'A' => 'Κωδικός',
                        'B' => 'Ονοματεπώνυμο',
                        'C' => 'Ενεργός',
                        'D' => 'Ημερομηνία Έναρξης',
                        'E' => 'Ημερομηνία Διακοπής',
                        'F' => 'ΦΠΑ',
                        'G' => 'Τύπος ΦΠΑ',
                        'H' => 'Μισθοδοσία',
                    ];
                    $unifiedHeaders = [
                        'A' => 'Κωδικός',
                        'B' => 'Ονοματεπώνυμο',
                        'C' => 'ΑΦΜ',
                        'D' => 'Κατηγορία',
                        'E' => 'Τηλέφωνο',
                        'F' => 'Email',
                        'G' => 'Ενεργός',
                        'H' => 'Ημερομηνία Έναρξης',
                        'I' => 'Ημερομηνία Διακοπής',
                        'J' => 'ΦΠΑ',
                        'K' => 'Τύπος ΦΠΑ',
                        'L' => 'Μισθοδοσία',
                    ];
                    $legacyLayout = trim((string)($rows[1]['C'] ?? '')) === 'Ενεργός';
                    $requiredHeaders = $legacyLayout ? $legacyHeaders : $unifiedHeaders;

                    foreach ($requiredHeaders as $col => $title) {
                        if (trim((string)($rows[1][$col] ?? '')) !== $title) {
                            $errors[] = "Η στήλη $col πρέπει να είναι: $title";
                        }
                    }

                    $pendingRows = [];
                    if (empty($errors)) {
                        for ($i = 2; $i <= count($rows); $i++) {
                            $code = trim((string)($rows[$i]['A'] ?? ''));
                            $fullname = trim((string)($rows[$i]['B'] ?? ''));
                            if ($code === '' || $fullname === '') {
                                $errors[] = "Γραμμή $i: λείπει υποχρεωτικό πεδίο.";
                                continue;
                            }

                            $check = $mysqli->prepare('SELECT id FROM clients WHERE professional_code = ?');
                            $check->bind_param('s', $code);
                            $check->execute();
                            if ($check->get_result()->num_rows > 0) {
                                $errors[] = "Γραμμή $i: ο κωδικός '$code' υπάρχει ήδη.";
                                continue;
                            }

                            $pendingRows[] = [
                                'code' => $code,
                                'fullname' => $fullname,
                                'afm' => $legacyLayout ? '' : trim((string)($rows[$i]['C'] ?? '')),
                                'category' => $legacyLayout ? 'Επιχείρηση' : (trim((string)($rows[$i]['D'] ?? '')) ?: 'Επιχείρηση'),
                                'phone' => $legacyLayout ? '' : trim((string)($rows[$i]['E'] ?? '')),
                                'email' => $legacyLayout ? '' : trim((string)($rows[$i]['F'] ?? '')),
                                'is_active' => strtoupper(trim((string)($rows[$i][$legacyLayout ? 'C' : 'G'] ?? ''))) === 'ΝΑΙ' ? 1 : 0,
                                'start_date' => convertDateExcelToDb(trim((string)($rows[$i][$legacyLayout ? 'D' : 'H'] ?? ''))),
                                'end_date' => convertDateExcelToDb(trim((string)($rows[$i][$legacyLayout ? 'E' : 'I'] ?? ''))),
                                'has_vat' => strtoupper(trim((string)($rows[$i][$legacyLayout ? 'F' : 'J'] ?? ''))) === 'ΝΑΙ' ? 1 : 0,
                                'vat_type' => trim((string)($rows[$i][$legacyLayout ? 'G' : 'K'] ?? '')) ?: null,
                                'has_payroll' => strtoupper(trim((string)($rows[$i][$legacyLayout ? 'H' : 'L'] ?? ''))) === 'ΝΑΙ' ? 1 : 0,
                            ];
                        }
                    }

                    if (empty($errors) && $pendingRows) {
                        $mysqli->begin_transaction();
                        try {
                            $stmt = $mysqli->prepare(
                                'INSERT INTO clients ' .
                                '(name, afm, category, phone, email, has_professional_profile, professional_code, ' .
                                'professional_is_active, professional_start_date, professional_end_date, ' .
                                'professional_has_vat, professional_vat_type, professional_has_payroll) ' .
                                'VALUES (?,?,?,?,?,1,?,?,?,?,?,?,?)'
                            );
                            foreach ($pendingRows as $row) {
                                $stmt->bind_param(
                                    'ssssssissisi',
                                    $row['fullname'], $row['afm'], $row['category'], $row['phone'], $row['email'],
                                    $row['code'], $row['is_active'], $row['start_date'], $row['end_date'],
                                    $row['has_vat'], $row['vat_type'], $row['has_payroll']
                                );
                                $stmt->execute();
                            }
                            $mysqli->commit();
                            $inserted = count($pendingRows);
                        } catch (Throwable $e) {
                            $mysqli->rollback();
                            throw $e;
                        }
                    }
                }
            } catch (Throwable $e) {
                // Keep file paths and parser/database details out of the response.
                if (empty($errors)) {
                    http_response_code(400);
                    $errors[] = 'Δεν ήταν δυνατή η ανάγνωση του αρχείου.';
                }
            }
        }
    }

    if ($errors && http_response_code() === 200) {
        http_response_code(400);
    }
    if ($inserted > 0) {
        $message = "Επιτυχής εισαγωγή: $inserted εγγραφές.";
    }
}

if ($inserted > 0 && empty($errors)) {
    header("Refresh: 2; URL=../active_professionals.php");
}

require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-container">

    <h2>Εισαγωγή Επαγγελματιών και Πελατών</h2>
    <p>Το νέο αρχείο Excel περιλαμβάνει τα στοιχεία πελάτη και επαγγελματία. Υποστηρίζεται επίσης η παλιά διάταξη 8 στηλών.</p>

    <?php if ($message): ?>
        <div class="card card-green" style="margin-bottom:15px;">
            <?= htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="card card-yellow" style="margin-bottom:15px;">
            <b>Παρουσιάστηκαν σφάλματα:</b><br>
            <?php foreach ($errors as $err): ?>
                • <?= htmlspecialchars($err, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-row">
                <div class="form-group">
                    <label>Επιλογή αρχείου Excel</label>
                    <input type="file" name="excel_file" accept=".xlsx" required>
                </div>
            </div>

            <div class="form-actions">
                <a href="../active_professionals.php" class="btn btn-secondary">Άκυρο</a>

                <button class="btn btn-primary">Εισαγωγή</button>
            </div>

        </form>
    </div>

</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
