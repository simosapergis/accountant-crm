<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = "Επαγγελματίας";
require_once "includes/db.php";
require_once "includes/functions.php";

/* ============================================================
   ΦΟΡΤΩΣΗ ΔΕΔΟΜΕΝΩΝ (ΑΝ ΕΙΝΑΙ EDIT)
============================================================ */
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$pro = null;
$saveError = '';

if ($id) {
    $stmt = $mysqli->prepare("SELECT c.id, c.name AS fullname, c.afm, c.category, c.phone, c.email,
        c.professional_code AS code, c.professional_is_active AS is_active,
        c.professional_start_date AS start_date, c.professional_end_date AS end_date,
        c.professional_has_vat AS has_vat, c.professional_vat_type AS vat_type,
        c.professional_has_payroll AS has_payroll
        FROM clients c WHERE c.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $pro = $stmt->get_result()->fetch_assoc();
    if (!$pro) {
        http_response_code(404);
        exit('Not found');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_professional'])) {
    $deleteId = (int)$_POST['delete_professional'];
    $stmt = $mysqli->prepare('UPDATE clients SET has_professional_profile = 0, professional_is_active = 0 WHERE id = ? AND has_professional_profile = 1');
    $stmt->bind_param('i', $deleteId);
    $stmt->execute();
    header('Location: active_professionals.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    $fullname = trim((string)($_POST['fullname'] ?? ''));
    $afm = trim((string)($_POST['afm'] ?? ''));
    $category = (string)($_POST['category'] ?? 'Επιχείρηση');
    $phone = trim((string)($_POST['phone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $code = trim((string)($_POST['code'] ?? ''));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $start_date = ($_POST['start_date'] ?? '') ?: null;
    $end_date = ($_POST['end_date'] ?? '') ?: null;
    $has_vat = isset($_POST['has_vat']) ? 1 : 0;
    $vat_type = (string)($_POST['vat_type'] ?? '');
    $has_payroll = isset($_POST['has_payroll']) ? 1 : 0;
    $pro = compact('fullname', 'afm', 'category', 'phone', 'email', 'code', 'is_active', 'start_date', 'end_date', 'has_vat', 'vat_type', 'has_payroll');

    if ($fullname === '' || $code === '' || !in_array($category, ['Ιδιώτης', 'Επιχείρηση', 'Λοιπά'], true)
        || !in_array($vat_type, ['', 'Μηνιαίο', 'Τριμηνιαίο'], true)
        || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        $saveError = 'Συμπληρώστε επωνυμία και κωδικό και ελέγξτε τα στοιχεία.';
    } else {
        try {
            if ($id) {
                $stmt = $mysqli->prepare("UPDATE clients SET name=?, afm=?, category=?, phone=?, email=?,
                    professional_code=?, professional_is_active=?, professional_start_date=?, professional_end_date=?,
                    professional_has_vat=?, professional_vat_type=?, professional_has_payroll=?,
                    has_professional_profile=1 WHERE id=?");
                $stmt->bind_param('ssssssissisii', $fullname, $afm, $category, $phone, $email,
                    $code, $is_active, $start_date, $end_date, $has_vat, $vat_type, $has_payroll, $id);
            } else {
                $stmt = $mysqli->prepare("INSERT INTO clients
                    (name, afm, category, phone, email, has_professional_profile, professional_code,
                     professional_is_active, professional_start_date, professional_end_date,
                     professional_has_vat, professional_vat_type, professional_has_payroll)
                    VALUES (?,?,?,?,?,1,?,?,?,?,?,?,?)");
                $stmt->bind_param('ssssssissisi', $fullname, $afm, $category, $phone, $email,
                    $code, $is_active, $start_date, $end_date, $has_vat, $vat_type, $has_payroll);
            }
            $stmt->execute();
            header('Location: active_professionals.php');
            exit;
        } catch (mysqli_sql_exception $exception) {
            error_log('Professional profile save failed: ' . $exception->getMessage());
            $saveError = 'Η αποθήκευση απέτυχε. Ελέγξτε αν ο κωδικός υπάρχει ήδη.';
        }
    }
}

require "includes/header.php";
?>

<style>
/* MOBILE SUMMARY CARD */
.summary-card {
    background:white;
    padding:14px;
    border-radius:12px;
    box-shadow:0 1px 4px rgba(0,0,0,0.12);
    margin-bottom:18px;
}
.summary-row {
    display:flex;
    justify-content:space-between;
    margin:4px 0;
    font-size:14px;
}
.summary-label { color:#6b7280; }

/* RESPONSIVE FORM */
.form-box {
    background:white;
    padding:22px;
    border-radius:12px;
    box-shadow:0 1px 4px rgba(0,0,0,0.12);
}
.form-row {
    margin-bottom:15px;
}
.form-row label {
    display:block;
    font-weight:600;
    margin-bottom:4px;
}
.form-row input,
.form-row select {
    width:100%;
    padding:10px;
    border-radius:6px;
    border:1px solid #ccc;
}
.save-btn {
    background:#28a745;
    color:white;
    padding:12px 20px;
    border:none;
    border-radius:6px;
    cursor:pointer;
    font-size:16px;
}
.save-btn:hover { background:#1f8a39; }

.delete-btn {
    background:#dc3545;
    color:white;
    padding:12px 20px;
    border:none;
    border-radius:6px;
    cursor:pointer;
}

@media(min-width:900px){
    .form-row-half { display:flex; gap:20px; }
    .form-row-half .form-row { flex:1; }
}
</style>

<div class="page-container">

    <h2 style="margin-bottom:20px;">
        <?= $id ? "Επεξεργασία Πελάτη / Επαγγελματία" : "Νέος Πελάτης / Επαγγελματίας" ?>
    </h2>

    <!-- ============================================================
         MOBILE SUMMARY CARD (ΜΟΝΟ ΑΝ ΕΙΝΑΙ EDIT)
    ============================================================= -->
    <?php if ($pro): ?>
    <div class="summary-card">

        <div class="summary-row">
            <span class="summary-label">Κωδικός:</span>
            <span><?= htmlspecialchars($pro["code"]) ?></span>
        </div>

        <div class="summary-row">
            <span class="summary-label">Ονοματεπώνυμο:</span>
            <span><?= htmlspecialchars($pro["fullname"]) ?></span>
        </div>

        <div class="summary-row">
            <span class="summary-label">Κατάσταση:</span>
            <span style="color:<?= $pro["is_active"] ? '#28a745':'#dc3545' ?>;">
                <?= $pro["is_active"] ? "Ενεργός" : "Ανενεργός" ?>
            </span>
        </div>

        <div class="summary-row">
            <span class="summary-label">ΦΠΑ:</span>
            <span>
                <?= $pro["has_vat"] ? "ΝΑΙ" : "ΟΧΙ" ?>
                <?= $pro["vat_type"] ? "(" . htmlspecialchars($pro["vat_type"]) . ")" : "" ?>
            </span>
        </div>

        <div class="summary-row">
            <span class="summary-label">Μισθοδοσία:</span>
            <span><?= $pro["has_payroll"] ? "ΝΑΙ" : "ΟΧΙ" ?></span>
        </div>

    </div>
    <?php endif; ?>

    <!-- ============================================================
         ΦΟΡΜΑ
    ============================================================= -->
    <form method="post" class="form-box">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)($id ?? 0) ?>">
        <?php if ($saveError): ?>
            <div role="alert" style="margin-bottom:16px;padding:12px;border-radius:6px;background:#f8d7da;color:#842029;">
                <?= htmlspecialchars($saveError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <h3>Στοιχεία πελάτη</h3>

        <div class="form-row">
            <label>Επωνυμία / Ονοματεπώνυμο</label>
            <input type="text" name="fullname" value="<?= htmlspecialchars((string)($pro['fullname'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
        </div>

        <div class="form-row-half">
            <div class="form-row">
                <label>ΑΦΜ</label>
                <input type="text" name="afm" value="<?= htmlspecialchars((string)($pro['afm'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            </div>
            <div class="form-row">
                <label>Κατηγορία</label>
                <select name="category">
                    <?php foreach (['Ιδιώτης', 'Επιχείρηση', 'Λοιπά'] as $categoryOption): ?>
                        <option value="<?= htmlspecialchars($categoryOption, ENT_QUOTES, 'UTF-8') ?>" <?= ($pro['category'] ?? 'Επιχείρηση') === $categoryOption ? 'selected' : '' ?>><?= htmlspecialchars($categoryOption, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row-half">
            <div class="form-row">
                <label>Τηλέφωνο</label>
                <input type="text" name="phone" value="<?= htmlspecialchars((string)($pro['phone'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            </div>
            <div class="form-row">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars((string)($pro['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            </div>
        </div>

        <h3>Στοιχεία επαγγελματία</h3>
        <div class="form-row">
            <label>Κωδικός</label>
            <input type="text" name="code" value="<?= htmlspecialchars((string)($pro['code'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
        </div>

        <div class="form-row-half">

            <div class="form-row">
                <label>Έναρξη</label>
                <input type="date" name="start_date"
                       value="<?= htmlspecialchars((string)($pro["start_date"] ?? ""), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            </div>

            <div class="form-row">
                <label>Διακοπή</label>
                <input type="date" name="end_date"
                       value="<?= htmlspecialchars((string)($pro["end_date"] ?? ""), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            </div>

        </div>

        <div class="form-row">
            <label><input type="checkbox" name="is_active"
                   <?= isset($pro["is_active"]) && $pro["is_active"] ? "checked" : "" ?>>
                   Ενεργός</label>
        </div>

        <div class="form-row">
            <label><input type="checkbox" name="has_vat"
                   <?= isset($pro["has_vat"]) && $pro["has_vat"] ? "checked" : "" ?>>
                   Υπόκειται σε ΦΠΑ</label>
        </div>

        <div class="form-row">
            <label>Τύπος ΦΠΑ</label>
            <select name="vat_type">
                <option value="">—</option>
                <option value="Μηνιαίο"
                    <?= ($pro["vat_type"] ?? "") === "Μηνιαίο" ? "selected" : "" ?>>Μηνιαίο</option>
                <option value="Τριμηνιαίο"
                    <?= ($pro["vat_type"] ?? "") === "Τριμηνιαίο" ? "selected" : "" ?>>Τριμηνιαίο</option>
            </select>
        </div>

        <div class="form-row">
            <label>
                <input type="checkbox" name="has_payroll"
                    <?= isset($pro["has_payroll"]) && $pro["has_payroll"] ? "checked" : "" ?>>
                Μισθοδοσία
            </label>
        </div>

        <br>

        <button type="submit" class="save-btn">💾 Αποθήκευση</button>

        <?php if ($pro): ?>
            <button type="submit" name="delete_professional" value="<?= (int)$pro['id'] ?>"
                    class="delete-btn" onclick="return confirm('Σίγουρα διαγραφή;')">🗑 Διαγραφή</button>
        <?php endif; ?>

    </form>

</div>

<?php require "includes/footer.php"; ?>
