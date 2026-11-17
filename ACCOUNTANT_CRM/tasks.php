<?php
require_once __DIR__ . '/includes/auth.php';
if (isset($_GET['delete'])) {
    require_post_method();
}
require_once "includes/db.php";
require_once "includes/functions.php";

$pageTitle = "Εργασίες";

/* ---------------- ΦΙΛΤΡΑ ---------------- */
$search     = $_GET['search'] ?? '';
$status     = $_GET['status'] ?? '';
$client_id  = $_GET['client'] ?? '';
$partner_id = $_GET['partner'] ?? '';

$date_range_type = $_GET['date_range_type'] ?? '';
$date_from       = $_GET['date_from'] ?? '';
$date_to         = $_GET['date_to'] ?? '';

$where = " WHERE 1=1 ";

if ($search != "") {
    $s = $mysqli->real_escape_string($search);
    $where .= " AND t.title LIKE '%$s%'";
}

if ($status != "") {
    $s = $mysqli->real_escape_string($status);
    $where .= " AND t.status = '$s'";
}

if ($client_id != "") {
    $cid = (int)$client_id;
    $where .= " AND t.client_id = $cid";
}

if ($partner_id != "") {
    $pid = (int)$partner_id;
    $where .= " AND t.partner_id = $pid";
}

/* ---------------- ΦΙΛΤΡΑ ΗΜΕΡΟΜΗΝΙΑΣ ---------------- */

if ($date_range_type == "today") {
    $where .= " AND t.task_date = CURDATE() ";
}

if ($date_range_type == "this_week") {
    $where .= " AND YEARWEEK(t.task_date, 1) = YEARWEEK(CURDATE(), 1) ";
}

if ($date_range_type == "this_month") {
    $where .= " AND MONTH(t.task_date) = MONTH(CURDATE())
                AND YEAR(t.task_date) = YEAR(CURDATE()) ";
}

if ($date_range_type == "prev_month") {
    $where .= "
        AND MONTH(t.task_date) = MONTH(CURDATE() - INTERVAL 1 MONTH)
        AND YEAR(t.task_date)  = YEAR(CURDATE() - INTERVAL 1 MONTH)
    ";
}

if ($date_range_type == "custom" && $date_from && $date_to) {
    $f = $mysqli->real_escape_string($date_from);
    $t = $mysqli->real_escape_string($date_to);
    $where .= " AND t.task_date BETWEEN '$f' AND '$t' ";
}

/* ---------------- QUERY ---------------- */
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

$result = $mysqli->query($sql);

/* Μεταφέρουμε αποτελέσματα σε array */
$tasks = [];
while ($row = $result->fetch_assoc()) {

    // Χρώμα κατάστασης
    $statusColor = "#fd7e14";
    if ($row['status'] == "Ολοκληρωμένη") $statusColor = "#28a745";
    elseif ($row['status'] == "Αναμονή εξόφλησης") $statusColor = "#dc3545";

    $row['statusColor'] = $statusColor;
    $row['balance']     = $row['fee'] - $row['collected'];

    $tasks[] = $row;
}

$clientsRes  = $mysqli->query("SELECT id,name FROM clients ORDER BY name");
$partnersRes = $mysqli->query("SELECT id,fullname FROM partners ORDER BY fullname");

require "includes/header.php";
?>

<style>
/* ----------------------------------------------
   RESPONSIVE FILTERS – SAFE VERSION
   Desktop = normal
   Mobile = horizontal scroll
---------------------------------------------- */

.filters-container {
    margin-bottom: 15px;
}

.filters-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

/* MOBILE */
@media (max-width: 768px) {

    .filters-row {
        flex-wrap: nowrap !important;
        overflow-x: auto;
        white-space: nowrap;
        padding-bottom: 8px;
        -webkit-overflow-scrolling: touch;
    }

    .filters-row input[type="text"],
    .filters-row select,
    .filters-row input[type="date"],
    .filters-row button {
        min-width: 180px;
        flex: 0 0 auto;
    }

    #date_from, #date_to {
        min-width: 150px !important;
        flex: 0 0 auto;
    }

    .filter-btn {
        min-width: 70px !important;
    }
}

/* === Mobile Cards === */
@media (max-width: 768px) {
    .desktop-table { display: none; }
    .mobile-cards { display: block; }
}

@media (min-width: 769px) {
    .desktop-table { display: block; }
    .mobile-cards { display: none; }
}

.mobile-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 10px 12px;
    margin-bottom: 10px;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.10);
    font-size: 13px;
}

.mobile-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mobile-card-title {
    font-weight: 600;
    margin-left: 6px;
    flex: 1;
}

.mobile-card-row {
    display: flex;
    justify-content: space-between;
    margin-top: 3px;
}

.mobile-card-label {
    color: #6b7280;
}

.mobile-card-amounts {
    margin-top: 6px;
    border-top: 1px dashed #e5e7eb;
    padding-top: 6px;
    display: flex;
    justify-content: space-between;
    row-gap: 2px;
}

.mobile-card-actions {
    margin-top: 8px;
    text-align: right;
}

@media (max-width: 768px) {
    .mobile-card-actions .icon-btn {
        width: 30px;
        height: 30px;
        font-size: 16px;
    }
}
</style>

<div class="page-container">

    <!-- Νέα εργασία -->
    <div class="new-task-top">
        <a href="task_form.php" class="new-task-btn">+ Νέα Εργασία</a>
    </div>

    <!-- ΦΙΛΤΡΑ -->
    <form method="get">
    <div class="filters-container">
        <div class="filters-row">

            <input type="text" name="search" placeholder="Αναζήτηση..."
                   value="<?= htmlspecialchars($search) ?>">

            <select name="status">
                <option value="">Κατάσταση</option>
                <option <?= $status=="Σε εξέλιξη" ? "selected":"" ?>>Σε εξέλιξη</option>
                <option <?= $status=="Ολοκληρωμένη" ? "selected":"" ?>>Ολοκληρωμένη</option>
                <option <?= $status=="Αναμονή εξόφλησης" ? "selected":"" ?>>Αναμονή εξόφλησης</option>
            </select>

            <select name="client">
                <option value="">Πελάτης</option>
                <?php while ($c = $clientsRes->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>" <?= $client_id==$c['id'] ? "selected":"" ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <?php $partnersRes2 = $mysqli->query("SELECT id,fullname FROM partners ORDER BY fullname"); ?>
            <select name="partner">
                <option value="">Συνεργάτης</option>
                <?php while ($p = $partnersRes2->fetch_assoc()): ?>
                    <option value="<?= $p['id'] ?>" <?= $partner_id==$p['id'] ? "selected":"" ?>>
                        <?= htmlspecialchars($p['fullname']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <select name="date_range_type" id="date_range_type">
                <option value="">Ημερομηνίες</option>
                <option value="today"      <?= ($date_range_type=="today" ? "selected":"") ?>>Σήμερα</option>
                <option value="this_week"  <?= ($date_range_type=="this_week" ? "selected":"") ?>>Αυτή την εβδομάδα</option>
                <option value="this_month" <?= ($date_range_type=="this_month" ? "selected":"") ?>>Αυτόν τον μήνα</option>
                <option value="prev_month" <?= ($date_range_type=="prev_month" ? "selected":"") ?>>Προηγούμενος μήνας</option>
                <option value="custom"     <?= ($date_range_type=="custom" ? "selected":"") ?>>Προσαρμοσμένο εύρος</option>
            </select>

            <input type="date" name="date_from" id="date_from"
                   value="<?= htmlspecialchars($date_from) ?>" style="display:none;">

            <input type="date" name="date_to" id="date_to"
                   value="<?= htmlspecialchars($date_to) ?>" style="display:none;">

            <button class="filter-btn" type="submit">🔍</button>

        </div>
    </div>
    </form>

    <script>
    function toggleDateInputs() {
        const type = document.getElementById("date_range_type").value;
        const df = document.getElementById("date_from");
        const dt = document.getElementById("date_to");

        if (type === "custom") {
            df.style.display = "block";
            dt.style.display = "block";
        } else {
            df.style.display = "none";
            dt.style.display = "none";
        }
    }
    document.getElementById("date_range_type").addEventListener("change", toggleDateInputs);
    toggleDateInputs();
    </script>

    <!-- ΠΙΝΑΚΑΣ DESKTOP -->
    <div class="table-wrapper desktop-table">
        <table>
            <thead>
                <tr>
                    <th>Ημ/νία</th>
                    <th>Τίτλος</th>
                    <th>Πελάτης</th>
                    <th>Συνεργάτης</th>
                    <th>Αμοιβή</th>
                    <th>Είσπραξη</th>
                    <th>Υπόλοιπο</th>
                    <th style="text-align:center;">Ενέργειες</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($tasks as $row): ?>
                <tr>
                    <td><?= greekDateFromDb($row['task_date']) ?></td>

                    <td class="tasks-table-title">
                        <span class="task-status-square" style="background: <?= $row['statusColor'] ?>;"></span>
                        <?= htmlspecialchars($row['title']) ?>
                    </td>

                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                    <td><?= htmlspecialchars($row['partner_name'] ?? '') ?></td>

                    <td><?= number_format($row['fee'],2,',','.') ?> €</td>
                    <td><?= number_format($row['collected'],2,',','.') ?> €</td>
                    <td><?= number_format($row['balance'],2,',','.') ?> €</td>

                    <td class="task-actions">
                        <a class="icon-btn blue" href="task_form.php?id=<?= $row['id'] ?>">✏️</a>
                        <form method="post" action="task_form.php" style="display:inline;">
                            <?= csrf_field() ?>
                            <button class="icon-btn red" type="submit" name="delete_task" value="<?= (int)$row['id'] ?>"
                                    onclick="return confirm('Διαγραφή εργασίας;')">🗑</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>

        </table>
    </div>

    <!-- MOBILE CARDS -->
    <div class="mobile-cards">
        <?php foreach ($tasks as $row): ?>
            <div class="mobile-card">

                <div class="mobile-card-header">
                    <div style="display:flex; align-items:center;">
                        <span class="task-status-square" style="background: <?= $row['statusColor'] ?>;"></span>
                        <div class="mobile-card-title"><?= htmlspecialchars($row['title']) ?></div>
                    </div>
                    <div style="font-size:12px; color:#6b7280;">
                        <?= greekDateFromDb($row['task_date']) ?>
                    </div>
                </div>

                <div class="mobile-card-row">
                    <span class="mobile-card-label">Πελάτης:</span>
                    <span><?= htmlspecialchars($row['client_name']) ?></span>
                </div>

                <?php if (!empty($row['partner_name'])): ?>
                <div class="mobile-card-row">
                    <span class="mobile-card-label">Συνεργάτης:</span>
                    <span><?= htmlspecialchars($row['partner_name']) ?></span>
                </div>
                <?php endif; ?>

                <div class="mobile-card-row">
                    <span class="mobile-card-label">Κατάσταση:</span>
                    <span><?= htmlspecialchars($row['status']) ?></span>
                </div>

                <div class="mobile-card-amounts">
                    <span><strong>Αμοιβή:</strong> <?= number_format($row['fee'],2,',','.') ?> €</span>
                    <span><strong>Είσπραξη:</strong> <?= number_format($row['collected'],2,',','.') ?> €</span>
                    <span><strong>Υπόλοιπο:</strong> <?= number_format($row['balance'],2,',','.') ?> €</span>
                </div>

                <div class="mobile-card-actions">
                    <a class="icon-btn blue" href="task_form.php?id=<?= $row['id'] ?>">✏️</a>
                    <form method="post" action="task_form.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <button class="icon-btn red" type="submit" name="delete_task" value="<?= (int)$row['id'] ?>"
                                onclick="return confirm('Διαγραφή εργασίας;')">🗑</button>
                    </form>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

</div>

<?php require "includes/footer.php"; ?>
