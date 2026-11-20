<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Πελάτες';
require_once 'includes/db.php';
require_once 'includes/functions.php';

/* ===================== ΦΙΛΤΡΑ ====================== */
$searchInput = $_GET['search'] ?? '';
$categoryInput = $_GET['category'] ?? '';
$balanceInput = $_GET['balance'] ?? '';
$sortInput = $_GET['sort'] ?? '';
$entityInput = $_GET['entity'] ?? '';
$activeInput = $_GET['is_active'] ?? '';
$vatInput = $_GET['vat'] ?? '';
$vatTypeInput = $_GET['vat_type'] ?? '';
$payrollInput = $_GET['payroll'] ?? '';
$search = is_string($searchInput) ? $searchInput : '';
$category = is_string($categoryInput) ? $categoryInput : '';
$balanceF = is_string($balanceInput) ? $balanceInput : ''; // positive / zero / all
$sort = is_string($sortInput) ? $sortInput : '';
$entity = is_string($entityInput) ? $entityInput : '';
$isActive = is_string($activeInput) ? $activeInput : '';
$vat = is_string($vatInput) ? $vatInput : '';
$vatType = is_string($vatTypeInput) ? $vatTypeInput : '';
$payroll = is_string($payrollInput) ? $payrollInput : '';

$clientCounts = $mysqli->query('SELECT COALESCE(SUM(has_professional_profile=0),0) AS private_count, COALESCE(SUM(has_professional_profile=1),0) AS professional_count, COALESCE(SUM(has_professional_profile=1 AND professional_is_active=1),0) AS active_count, COALESCE(SUM(has_professional_profile=1 AND professional_is_active=0),0) AS inactive_count FROM clients')->fetch_assoc();

$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = '(c.name LIKE ? OR c.professional_code LIKE ? OR c.afm LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
    $searchPattern = '%' . $search . '%';
    for ($i = 0; $i < 5; $i++) $params[] = $searchPattern;
}

if ($category !== '') {
    $conditions[] = 'c.category = ?';
    $params[] = $category;
}
if ($entity === 'private') {
    $conditions[] = 'c.has_professional_profile = 0';
} elseif ($entity === 'professional') {
    $conditions[] = 'c.has_professional_profile = 1';
}
$professionalFiltersApplied = $isActive !== '' || $vat !== '' || $vatType !== '' || $payroll !== '';
if ($professionalFiltersApplied) {
    $conditions[] = 'c.has_professional_profile = 1';
}
if ($isActive === '1' || $isActive === '0') { $conditions[] = 'c.professional_is_active = ?'; $params[] = (int)$isActive; }
if ($vat === 'yes' || $vat === 'no') { $conditions[] = 'c.professional_has_vat = ?'; $params[] = $vat === 'yes' ? 1 : 0; }
if ($vatType !== '' && in_array($vatType, ['Μηνιαίο', 'Τριμηνιαίο'], true)) { $conditions[] = 'c.professional_vat_type = ?'; $params[] = $vatType; }
if ($payroll === 'yes' || $payroll === 'no') { $conditions[] = 'c.professional_has_payroll = ?'; $params[] = $payroll === 'yes' ? 1 : 0; }

/* Βάση query */
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
$sql = "
    SELECT c.*,
           COALESCE(SUM(t.fee),0) AS total_fees,
           COALESCE(SUM(t.collected),0) AS total_collected,
           COUNT(t.id) AS task_count
    FROM clients c
    LEFT JOIN tasks t ON t.client_id = c.id
    $where
    GROUP BY c.id
";

/* Ταξινόμηση */
$orderBy = match ($sort) {
    'balance' => ' ORDER BY (COALESCE(SUM(t.fee),0) - COALESCE(SUM(t.collected),0)) DESC',
    'tasks' => ' ORDER BY COUNT(t.id) DESC',
    default => ' ORDER BY c.name ASC',
};

$sql .= $orderBy;

$stmt = $mysqli->prepare($sql);
$stmt->execute($params);
$res = $stmt->get_result();

/* Φιλτράρισμα υπολοίπου σε PHP, για απλότητα */
$clients = [];
while ($c = $res->fetch_assoc()) {
    $c['balance'] = $c['total_fees'] - $c['total_collected'];

    if ($balanceF === 'positive' && $c['balance'] <= 0) {
        continue;
    }
    if ($balanceF === 'zero' && $c['balance'] != 0) {
        continue;
    }
    // all -> όλα

    $clients[] = $c;
}

require 'includes/header.php';
?>

<style>
@media (min-width: 769px) {
    main > .page-container:has(.clients-page) {
        width: 99%;
        max-width: none;
        box-sizing: border-box;
    }
    .page-container.clients-page {
        max-width: none;
        padding: 0;
    }
}
.clients-page .clients-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin: 0 0 16px;
}
.clients-page .clients-action-downloads { display: flex; flex-wrap: wrap; gap: 10px; }
.clients-page .clients-actions > a {
    min-height: 42px;
    padding: 0 16px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
}
.clients-page .clients-actions > a:hover { filter: brightness(.95); }
.clients-page .clients-actions > a:focus-visible { outline: 3px solid #005fcc; outline-offset: 2px; }
@media (max-width: 600px) {
    .clients-page .clients-actions { display: grid; grid-template-columns: 1fr 1fr; }
    .clients-page .clients-actions > a { width: 100%; }
}
@media (min-width: 901px) {
    .clients-page .filters-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
        align-items: center;
    }
}
.clients-page .desktop-table table {
    table-layout: fixed;
    width: 100%;
    min-width: 0;
}
.clients-page .desktop-table th,
.clients-page .desktop-table td {
    width: 7%;
    max-width: 7%;
    white-space: normal;
    overflow-wrap: break-word;
    padding-left: 5px;
    padding-right: 5px;
}
.clients-page .desktop-table th:nth-child(1),
.clients-page .desktop-table td:nth-child(1) {
    width: 12%;
    max-width: 12%;
}
.clients-page .desktop-table th:nth-child(5),
.clients-page .desktop-table td:nth-child(5) {
    width: 14%;
    max-width: 14%;
    overflow-wrap: normal;
    word-break: normal;
}
.clients-page .desktop-table th:nth-child(6),
.clients-page .desktop-table td:nth-child(6),
.clients-page .desktop-table th:nth-child(9),
.clients-page .desktop-table td:nth-child(9) {
    width: 3.5%;
    max-width: 3.5%;
}
.clients-page .filters-row input,
.clients-page .filters-row select,
.clients-page .filters-row button {
    width: 100%;
    min-width: 0;
}
@media (max-width: 768px) {
    .desktop-table { display:none; }
    .mobile-cards { display:block; }
}
@media (min-width: 769px) {
    .desktop-table { display:block; }
    .mobile-cards { display:none; }
}

.mobile-card {
    background:#ffffff;
    border-radius:12px;
    padding:10px 12px;
    margin-bottom:10px;
    box-shadow:0 1px 4px rgba(15,23,42,0.10);
    font-size:13px;
}
.mobile-card-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:4px;
}
.mobile-card-title {
    font-weight:600;
}
.mobile-card-row {
    display:flex;
    justify-content:space-between;
    margin-top:2px;
}
.mobile-card-label {
    color:#6b7280;
    margin-right:4px;
}
.mobile-card-amounts {
    margin-top:6px;
    border-top:1px dashed #e5e7eb;
    padding-top:6px;
    display:flex;
    flex-wrap:wrap;
    row-gap:2px;
}
.mobile-card-amounts span {
    margin-right:10px;
}
.mobile-card-actions {
    margin-top:8px;
    text-align:right;
}
@media (max-width: 768px) {
    .mobile-card-actions .btn {
        padding:4px 8px;
        font-size:12px;
    }
}

/* Client cards share the same readable layout on mobile and wide screens. */
.clients-page .mobile-card {
    border: 1px solid #e2e6ec;
    padding: 20px;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
    font-size: 14px;
}
.clients-page .mobile-card-header { display: block; margin: 0 0 6px; }
.clients-page .mobile-card-title { font-size: 18px; line-height: 1.4; overflow-wrap: anywhere; margin-bottom: 6px; }
.clients-page .mobile-card-row { gap: 12px; margin: 0; line-height: 1.5; }
.clients-page .mobile-card-row > :last-child { min-width: 0; text-align: right; overflow-wrap: anywhere; }
.clients-page .mobile-card-label { flex-shrink: 0; }
.clients-page .mobile-card-amounts {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    background: #f3f6fa;
    border: 0;
    border-radius: 8px;
    padding: 12px;
    margin-top: auto;
}
.clients-page .mobile-card-amounts span { margin: 0; overflow-wrap: anywhere; }
.clients-page .mobile-card-amounts span:nth-child(1) { text-align: left; }
.clients-page .mobile-card-amounts span:nth-child(2) { text-align: center; }
.clients-page .mobile-card-amounts span:nth-child(3) { text-align: right; }
.clients-page .mobile-card-amounts strong { display: block; font-size: 12px; color: #536174; margin-bottom: 5px; }
.clients-page .mobile-card-actions { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: 4px; }
.clients-page .mobile-card-actions form { min-width: 0; margin: 0; }
.clients-page .mobile-card-actions .btn { width: 100%; min-width: 0; min-height: 44px; height: 100%; display: inline-flex; align-items: center; justify-content: center; padding: 8px 4px; font-size: 14px; white-space: normal; overflow-wrap: anywhere; }
@media (max-width: 768px) {
    .clients-page .mobile-cards { display: grid; gap: 16px; }
}
@media (min-width: 769px) and (max-width: 1299.98px) {
    .clients-page .desktop-table { display: none; }
    .clients-page .mobile-cards { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
}
</style>

<div class="page-container clients-page">

    <div class="new-task-top clients-actions" aria-label="Ενέργειες πελατών">
        <div class="clients-action-downloads">
        <a href="exports/export_clients_pdf.php?<?= htmlspecialchars(http_build_query($_GET), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn btn-secondary" title="Λήψη λίστας πελατών σε PDF">Λήψη PDF</a>
        <a href="exports/export_clients_excel.php?<?= htmlspecialchars(http_build_query($_GET), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn btn-success" title="Λήψη λίστας πελατών σε Excel">Λήψη Excel</a>
        <a href="imports/import_active_professionals.php" class="btn btn-warning">Εισαγωγή από Excel</a>
        </div>
        <a href="client_form.php" class="new-task-btn">+ Νέος Πελάτης</a>
    </div>

    <div class="stats-grid" style="display:flex;flex-wrap:wrap;gap:12px;margin:16px 0">
        <div style="flex:1;padding:14px;border-radius:10px;color:white;text-align:center;background:#0d6efd"><strong>Σύνολο Ιδιωτών</strong><div style="font-size:26px;font-weight:700;margin-top:4px"><?= (int)$clientCounts['private_count'] ?></div></div>
        <div style="flex:1;padding:14px;border-radius:10px;color:white;text-align:center;background:#28a745"><strong>Σύνολο Επαγγελματιών</strong><div style="font-size:26px;font-weight:700;margin-top:4px"><?= (int)$clientCounts['professional_count'] ?></div></div>
        <div style="flex:1;padding:14px;border-radius:10px;color:white;text-align:center;background:#198754"><strong>Ενεργοί</strong><div style="font-size:26px;font-weight:700;margin-top:4px"><?= (int)$clientCounts['active_count'] ?></div></div>
        <div style="flex:1;padding:14px;border-radius:10px;color:white;text-align:center;background:#dc3545"><strong>Ανενεργοί</strong><div style="font-size:26px;font-weight:700;margin-top:4px"><?= (int)$clientCounts['inactive_count'] ?></div></div>
    </div>

    <!-- ΦΙΛΤΡΑ ΠΕΛΑΤΩΝ ΚΑΙ ΕΠΑΓΓΕΛΜΑΤΙΩΝ -->
    <form method="get">
    <div class="filters-container">
        <div class="filters-row">

            <input type="text" name="search" placeholder="Αναζήτηση..."
                   value="<?= htmlspecialchars($search) ?>">

            <select name="category">
                <option value="">Κατηγορία</option>
                <option value="Ιδιώτης"   <?= $category==='Ιδιώτης'   ? 'selected' : '' ?>>Ιδιώτης</option>
                <option value="Επιχείρηση" <?= $category==='Επιχείρηση' ? 'selected' : '' ?>>Επιχείρηση</option>
                <option value="Εταιρεία"   <?= $category==='Εταιρεία'   ? 'selected' : '' ?>>Εταιρεία</option>
            </select>

            <select name="entity"><option value="">Τύπος πελάτη</option><option value="private" <?= $entity==='private' ? 'selected' : '' ?>>Ιδιώτης</option><option value="professional" <?= $entity==='professional' ? 'selected' : '' ?>>Επαγγελματίας</option></select>

            <select name="is_active"><option value="">Κατάσταση πελάτη</option><option value="1" <?= $isActive==='1' ? 'selected' : '' ?>>Ενεργός</option><option value="0" <?= $isActive==='0' ? 'selected' : '' ?>>Ανενεργός</option></select>
            <select name="vat"><option value="">ΦΠΑ</option><option value="yes" <?= $vat==='yes' ? 'selected' : '' ?>>Με ΦΠΑ</option><option value="no" <?= $vat==='no' ? 'selected' : '' ?>>Χωρίς ΦΠΑ</option></select>
            <select name="vat_type"><option value="">Τύπος ΦΠΑ</option><option value="Μηνιαίο" <?= $vatType==='Μηνιαίο' ? 'selected' : '' ?>>Μηνιαίο</option><option value="Τριμηνιαίο" <?= $vatType==='Τριμηνιαίο' ? 'selected' : '' ?>>Τριμηνιαίο</option></select>
            <select name="payroll"><option value="">Μισθοδοσία</option><option value="yes" <?= $payroll==='yes' ? 'selected' : '' ?>>Με μισθοδοσία</option><option value="no" <?= $payroll==='no' ? 'selected' : '' ?>>Χωρίς μισθοδοσία</option></select>

            <select name="balance">
                <option value="">Υπόλοιπο</option>
                <option value="positive" <?= $balanceF==='positive' ? 'selected' : '' ?>>Με Υπόλοιπο</option>
                <option value="zero"     <?= $balanceF==='zero'     ? 'selected' : '' ?>>Μηδενικό</option>
                <option value="all"      <?= $balanceF==='all'      ? 'selected' : '' ?>>Όλοι</option>
            </select>

            <select name="sort">
                <option value="">Ταξινόμηση</option>
                <option value="name"    <?= $sort==='name'    ? 'selected' : '' ?>>A–Ω</option>
                <option value="balance" <?= $sort==='balance' ? 'selected' : '' ?>>Υπόλοιπο</option>
                <option value="tasks"   <?= $sort==='tasks'   ? 'selected' : '' ?>>Αριθμός εργασιών</option>
            </select>

            <button class="filter-btn" type="submit">🔍</button>
        </div>
    </div>
    </form>

    <!-- ΠΙΝΑΚΑΣ DESKTOP -->
    <div class="table-wrapper desktop-table">
        <table>
            <thead>
                <tr>
                    <th>Επωνυμία</th>
                    <th>ΑΦΜ</th>
                    <th>Κατηγορία</th>
                    <th>Τηλέφωνο</th>
                    <th>Email</th>
                    <th>Ενεργός</th><th>Έναρξη</th><th>Διακοπή</th><th>Μισθοδοσία</th>
                    <th>Αμοιβές</th>
                    <th>Εισπράξεις</th>
                    <th>Υπόλοιπο</th>
                    <th>Ενέργειες</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($clients as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['name']) ?></td>
                    <td><?= htmlspecialchars((string)($c['afm'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($c['category'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($c['phone'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($c['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td><?= !empty($c['has_professional_profile']) ? (!empty($c['professional_is_active']) ? 'Ναι' : 'Όχι') : '—' ?></td>
                    <td><?= htmlspecialchars((string)($c['professional_start_date'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($c['professional_end_date'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td><?= !empty($c['has_professional_profile']) ? (!empty($c['professional_has_payroll']) ? 'Ναι' : 'Όχι') : '—' ?></td>
                    <td><?= formatMoney($c['total_fees']) ?></td>
                    <td><?= formatMoney($c['total_collected']) ?></td>
                    <td><?= formatMoney($c['balance']) ?></td>
                    <td class="task-actions">
                <a class="icon-btn blue" href="client_form.php?id=<?= $c['id'] ?>">✏️</a>
                
                <a class="icon-btn gray" href="tasks.php?client=<?= $c['id'] ?>">📄</a>
                <form method="post" action="client_form.php" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="icon-btn red" type="submit" name="delete_client" value="<?= (int)$c['id'] ?>"
                            onclick="return confirm('Διαγραφή πελάτη;');">🗑</button>
                </form>
            </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- MOBILE CARDS -->
    <div class="mobile-cards">
        <?php foreach ($clients as $c): ?>
            <div class="mobile-card">
                <div class="mobile-card-header">
                    <div class="mobile-card-title"><?= htmlspecialchars($c['name']) ?></div>
                    <?php if ($c['afm']): ?>
                        <div style="font-size:12px;color:#6b7280;">ΑΦΜ: <?= htmlspecialchars($c['afm']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mobile-card-row">
                    <span class="mobile-card-label">Κατηγορία:</span>
                    <span><?= htmlspecialchars($c['category']) ?></span>
                </div>

                <?php if ($c['phone']): ?>
                <div class="mobile-card-row">
                    <span class="mobile-card-label">Τηλέφωνο:</span>
                    <span><?= htmlspecialchars($c['phone']) ?></span>
                </div>
                <?php endif; ?>

                <?php if ($c['email']): ?>
                <div class="mobile-card-row">
                    <span class="mobile-card-label">Email:</span>
                    <span><?= htmlspecialchars($c['email']) ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($c['has_professional_profile'])): ?>
                <div class="mobile-card-row"><span class="mobile-card-label">Κατάσταση:</span><strong><?= !empty($c['professional_is_active']) ? 'Ενεργός' : 'Ανενεργός' ?></strong></div>
                <div class="mobile-card-row"><span class="mobile-card-label">Έναρξη:</span><span><?= htmlspecialchars((string)($c['professional_start_date'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                <div class="mobile-card-row"><span class="mobile-card-label">Διακοπή:</span><span><?= htmlspecialchars((string)($c['professional_end_date'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                <div class="mobile-card-row"><span class="mobile-card-label">Μισθοδοσία:</span><span><?= !empty($c['professional_has_payroll']) ? 'Ναι' : 'Όχι' ?></span></div>
                <?php endif; ?>

                <div class="mobile-card-amounts">
                    <span><strong>Αμοιβές:</strong> <?= formatMoney($c['total_fees']) ?></span>
                    <span><strong>Εισπράξεις:</strong> <?= formatMoney($c['total_collected']) ?></span>
                    <span><strong>Υπόλοιπο:</strong> <?= formatMoney($c['balance']) ?></span>
                </div>

                <div class="mobile-card-actions">
                    <a href="client_form.php?id=<?= $c['id'] ?>" class="btn btn-secondary">Επεξεργασία</a>
                    <a href="tasks.php?client=<?= $c['id'] ?>" class="btn btn-warning">Εργασίες</a>
                    <form method="post" action="client_form.php">
                        <?= csrf_field() ?>
                        <button class="btn btn-secondary" type="submit" name="delete_client" value="<?= (int)$c['id'] ?>" onclick="return confirm('Διαγραφή πελάτη;');">Διαγραφή</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<?php require 'includes/footer.php'; ?>
