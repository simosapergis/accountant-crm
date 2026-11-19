<?php
require_once __DIR__ . '/includes/auth.php';
if (isset($_GET['delete'])) {
    require_post_method();
}
$pageTitle = "Ενεργοί Επαγγελματίες";
require_once "includes/db.php";
require_once "includes/functions.php";

/* ============================================================
   ΣΤΑΤΙΣΤΙΚΑ ΠΛΗΘΟΥΣ
============================================================ */
$count_total    = $mysqli->query("SELECT COUNT(*) AS c FROM clients WHERE has_professional_profile = 1")->fetch_assoc()['c'];
$count_active   = $mysqli->query("SELECT COUNT(*) AS c FROM clients WHERE has_professional_profile = 1 AND professional_is_active = 1")->fetch_assoc()['c'];
$count_inactive = $mysqli->query("SELECT COUNT(*) AS c FROM clients WHERE has_professional_profile = 1 AND professional_is_active = 0")->fetch_assoc()['c'];

/* ============================================================
   ΦΙΛΤΡΑ
============================================================ */
$searchInput = $_GET['search'] ?? '';
$hasVatInput = $_GET['has_vat'] ?? '';
$vatTypeInput = $_GET['vat_type'] ?? '';
$payrollInput = $_GET['payroll'] ?? '';
$search = is_string($searchInput) ? $searchInput : '';
$has_vat = is_string($hasVatInput) ? $hasVatInput : '';
$vat_type = is_string($vatTypeInput) ? $vatTypeInput : '';
$payroll = is_string($payrollInput) ? $payrollInput : '';

/* Default: Εμφάνιση μόνο ΕΝΕΡΓΩΝ */
if (!array_key_exists('is_active', $_GET)) {
    $is_active = "1";
} else {
    $activeInput = $_GET['is_active'];
    $is_active = is_string($activeInput) ? $activeInput : '';
}

$conditions = ['c.has_professional_profile = 1'];
$params = [];

if ($search !== "") {
    $conditions[] = '(c.name LIKE ? OR c.professional_code LIKE ? OR c.afm LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
    $searchPattern = '%' . $search . '%';
    array_push($params, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern);
}

if ($is_active !== "") {
    $conditions[] = 'c.professional_is_active = ?';
    $params[] = (int)$is_active;
}

if ($has_vat !== "") {
    $conditions[] = 'c.professional_has_vat = ?';
    $params[] = (int)$has_vat;
}

if ($vat_type !== "") {
    $conditions[] = 'c.professional_vat_type = ?';
    $params[] = $vat_type;
}

if ($payroll !== "") {
    $conditions[] = 'c.professional_has_payroll = ?';
    $params[] = (int)$payroll;
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

/* ============================================================
   QUERY
============================================================ */
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
$pros = [];
while ($row = $result->fetch_assoc()) {
    $pros[] = $row;
}

require "includes/header.php";
?>

<style>
/* ===== DASHBOARD BOXES ===== */
.stats-grid {
    display:flex;
    gap:15px;
    margin-bottom:20px;
}
.stat-box {
    flex:1;
    padding:18px;
    border-radius:12px;
    color:white;
    text-align:center;
    font-size:15px;
    font-weight:600;
    box-shadow:0 2px 6px rgba(0,0,0,0.1);
}
.stat-num {
    font-size:26px;
    font-weight:700;
    margin-top:4px;
}
.stat-blue { background:#0d6efd; }
.stat-green { background:#28a745; }
.stat-red { background:#dc3545; }

/* ===== MOBILE CARDS ===== */
@media (max-width: 768px) {
    .desktop-table { display:none; }
    .mobile-cards { display:block; }
    .stats-grid { flex-direction:column; }
}
@media (min-width: 769px) {
    .desktop-table { display:block; }
    .mobile-cards { display:none; }
}

.mobile-card {
    background:#fff;
    border-radius:12px;
    padding:10px 12px;
    margin-bottom:10px;
    box-shadow:0 1px 4px rgba(15,23,42,0.10);
    font-size:13px;
}

.mobile-card-header {
    display:flex;
    justify-content:space-between;
    margin-bottom:4px;
}

.mobile-card-actions {
    text-align:right;
    margin-top:8px;
}

@media (max-width: 768px) {
    .mobile-card-actions .icon-btn {
        width:30px;
        height:30px;
        font-size:16px;
    }
}
</style>

<div class="page-container">

    <!-- ============================================================
         ΣΤΑΤΙΣΤΙΚΑ ΚΟΥΤΙΑ
    ============================================================= -->
    <div class="stats-grid">
        <div class="stat-box stat-blue">
            Σύνολο Επαγγελματιών
            <div class="stat-num"><?= $count_total ?></div>
        </div>

        <div class="stat-box stat-green">
            Ενεργοί
            <div class="stat-num"><?= $count_active ?></div>
        </div>

        <div class="stat-box stat-red">
            Ανενεργοί
            <div class="stat-num"><?= $count_inactive ?></div>
        </div>
    </div>

    <!-- ============================================================
         ΚΟΥΜΠΙΑ ΕΝΕΡΓΕΙΩΝ
    ============================================================= -->
    <div class="top-actions">

        <a href="active_professional_form.php"
           class="new-task-btn"
           style="background:#28a745;">
           + Νέος Επαγγελματίας
        </a>

        <a href="imports/import_active_professionals.php" class="btn btn-warning">
            ⬆ Εισαγωγή από Excel
        </a>

        <a href="exports/export_active_professionals_excel.php?<?= htmlspecialchars(http_build_query($_GET), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
           target="_blank"
           class="btn btn-primary">
           ⬇ Εξαγωγή Excel
        </a>

        <a href="exports/export_active_professionals_pdf.php?<?= htmlspecialchars(http_build_query($_GET), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
           target="_blank"
           class="btn btn-danger">
           ⬇ Εξαγωγή PDF
        </a>

    </div>

    <!-- ============================================================
         ΦΙΛΤΡΑ
    ============================================================= -->
    <form method="get">
        <div class="filters-container">
            <div class="filters-row">

                <input type="text" name="search" placeholder="Αναζήτηση..."
                       value="<?= htmlspecialchars($search) ?>">

                <select name="is_active">
                    <option value="">Ενεργός / Ανενεργός</option>
                    <option value="1" <?= $is_active==="1" ? "selected" : "" ?>>Ενεργός</option>
                    <option value="0" <?= $is_active==="0" ? "selected" : "" ?>>Ανενεργός</option>
                </select>

                <select name="has_vat">
                    <option value="">ΦΠΑ</option>
                    <option value="1" <?= $has_vat==="1" ? "selected" : "" ?>>ΝΑΙ</option>
                    <option value="0" <?= $has_vat==="0" ? "selected" : "" ?>>ΟΧΙ</option>
                </select>

                <select name="vat_type">
                    <option value="">Τύπος ΦΠΑ</option>
                    <option value="Μηνιαίο"    <?= $vat_type==="Μηνιαίο" ? "selected" : "" ?>>Μηνιαίο</option>
                    <option value="Τριμηνιαίο" <?= $vat_type==="Τριμηνιαίο" ? "selected" : "" ?>>Τριμηνιαίο</option>
                </select>

                <select name="payroll">
                    <option value="">Μισθοδοσία</option>
                    <option value="1" <?= $payroll==="1" ? "selected" : "" ?>>ΝΑΙ</option>
                    <option value="0" <?= $payroll==="0" ? "selected" : "" ?>>ΟΧΙ</option>
                </select>

                <button class="filter-btn" type="submit">🔍</button>

            </div>
        </div>
    </form>

    <!-- ============================================================
         DESKTOP TABLE
    ============================================================= -->
    <div class="table-wrapper desktop-table">
        <table>
            <thead>
                <tr>
                    <th>Κωδικός</th>
                    <th>Ονοματεπώνυμο</th>
                    <th>ΑΦΜ</th>
                    <th>Κατηγορία</th>
                    <th>Τηλέφωνο</th>
                    <th>Email</th>
                    <th>Ενεργός</th>
                    <th>Έναρξη</th>
                    <th>Διακοπή</th>
                    <th>ΦΠΑ</th>
                    <th>Τύπος ΦΠΑ</th>
                    <th>Μισθοδοσία</th>
                    <th style="text-align:center;">Ενέργειες</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($pros as $row): ?>
                <?php $statusColor = $row["is_active"] ? "#28a745" : "#dc3545"; ?>
                <tr>
                    <td><?= htmlspecialchars($row["code"]) ?></td>
                    <td><?= htmlspecialchars($row["fullname"]) ?></td>
                    <td><?= htmlspecialchars((string)($row['afm'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($row['category'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($row['phone'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($row['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>

                    <td>
                        <span class="task-status-square" style="background: <?= $statusColor ?>;"></span>
                        <?= $row["is_active"] ? "Ενεργός" : "Ανενεργός" ?>
                    </td>

                    <td><?= $row["start_date"] ? greekDateFromDb($row["start_date"]) : "" ?></td>
                    <td><?= $row["end_date"]   ? greekDateFromDb($row["end_date"])   : "" ?></td>

                    <td><?= $row["has_vat"] ? "ΝΑΙ" : "ΟΧΙ" ?></td>
                    <td><?= htmlspecialchars($row["vat_type"]) ?></td>

                    <td><?= $row["has_payroll"] ? "ΝΑΙ" : "ΟΧΙ" ?></td>

                    <td class="task-actions">
                        <a class="icon-btn blue" href="active_professional_form.php?id=<?= $row['id'] ?>">✏️</a>
                        <form method="post" action="active_professional_form.php" style="display:inline;">
                            <?= csrf_field() ?>
                            <button class="icon-btn red" type="submit" name="delete_professional" value="<?= (int)$row['id'] ?>"
                                    aria-label="Αφαίρεση επαγγελματικού προφίλ"
                                    onclick="return confirm('Αφαίρεση του επαγγελματικού προφίλ; Τα στοιχεία πελάτη και οι εργασίες θα διατηρηθούν.')">🗑</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ============================================================
         MOBILE CARD VIEW
    ============================================================= -->
    <div class="mobile-cards">
        <?php foreach ($pros as $row): ?>
            <?php $statusColor = $row["is_active"] ? "#22c55e" : "#ef4444"; ?>
            <div class="mobile-card">

                <div class="mobile-card-header">
                    <div class="mobile-card-title">
                        <?= htmlspecialchars($row["code"]) ?> – <?= htmlspecialchars($row["fullname"]) ?>
                    </div>
                    <div style="font-size:12px; color:<?= $statusColor ?>;">
                        <?= $row["is_active"] ? "Ενεργός" : "Ανενεργός" ?>
                    </div>
                </div>

                <?php foreach (['ΑΦΜ' => 'afm', 'Κατηγορία' => 'category', 'Τηλέφωνο' => 'phone', 'Email' => 'email'] as $label => $field): ?>
                    <?php if (!empty($row[$field])): ?>
                        <div class="mobile-card-row"><span class="mobile-card-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>:</span><span><?= htmlspecialchars((string)$row[$field], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="mobile-card-row">
                    <span class="mobile-card-label">Έναρξη:</span>
                    <span><?= $row["start_date"] ? greekDateFromDb($row["start_date"]) : "-" ?></span>
                </div>

                <?php if ($row["end_date"]): ?>
                <div class="mobile-card-row">
                    <span class="mobile-card-label">Διακοπή:</span>
                    <span><?= greekDateFromDb($row["end_date"]) ?></span>
                </div>
                <?php endif; ?>

                <div class="mobile-card-row">
                    <span class="mobile-card-label">ΦΠΑ:</span>
                    <span>
                        <?= $row["has_vat"] ? "ΝΑΙ" : "ΟΧΙ" ?>
                        <?php if ($row["vat_type"]): ?>
                            (<?= htmlspecialchars($row["vat_type"]) ?>)
                        <?php endif; ?>
                    </span>
                </div>

                <div class="mobile-card-row">
                    <span class="mobile-card-label">Μισθοδοσία:</span>
                    <span><?= $row["has_payroll"] ? "ΝΑΙ" : "ΟΧΙ" ?></span>
                </div>

                <div class="mobile-card-actions">
                    <a class="icon-btn blue" href="active_professional_form.php?id=<?= $row['id'] ?>">✏️</a>
                    <form method="post" action="active_professional_form.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <button class="icon-btn red" type="submit" name="delete_professional" value="<?= (int)$row['id'] ?>"
                                aria-label="Αφαίρεση επαγγελματικού προφίλ"
                                onclick="return confirm('Αφαίρεση του επαγγελματικού προφίλ; Τα στοιχεία πελάτη και οι εργασίες θα διατηρηθούν.')">🗑</button>
                    </form>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

</div>

<?php require "includes/footer.php"; ?>
