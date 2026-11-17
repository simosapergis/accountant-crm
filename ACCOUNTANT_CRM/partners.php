<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = "Συνεργάτες";
require_once "includes/db.php";
require_once "includes/functions.php";

/* Απλό search αν θέλεις μπορείς να το επεκτείνεις */
$searchInput = $_GET['search'] ?? '';
$search = is_string($searchInput) ? $searchInput : '';

$where = '';
$params = [];
if ($search !== '') {
    $where = ' WHERE (fullname LIKE ? OR phone LIKE ? OR email LIKE ?)';
    $searchPattern = '%' . $search . '%';
    $params = [$searchPattern, $searchPattern, $searchPattern];
}

$sql = "
    SELECT *
    FROM partners
    $where
    ORDER BY fullname
";
$stmt = $mysqli->prepare($sql);
$stmt->execute($params);
$res = $stmt->get_result();

$partners = [];
while ($p = $res->fetch_assoc()) {
    $partners[] = $p;
}

require "includes/header.php";
?>

<style>
@media (max-width:768px){
    .desktop-table{display:none;}
    .mobile-cards{display:block;}
}
@media (min-width:769px){
    .desktop-table{display:block;}
    .mobile-cards{display:none;}
}
.mobile-card{
    background:#fff;
    border-radius:12px;
    padding:10px 12px;
    margin-bottom:10px;
    box-shadow:0 1px 4px rgba(15,23,42,0.1);
    font-size:13px;
}
.mobile-card-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:4px;
}
.mobile-card-title{
    font-weight:600;
}
.mobile-card-row{
    display:flex;
    justify-content:space-between;
    margin-top:2px;
}
.mobile-card-label{
    color:#6b7280;
    margin-right:4px;
}
.mobile-card-actions{
    margin-top:8px;
    text-align:right;
}
</style>

<div class="page-container">

    <div class="new-task-top">
        <a href="partner_form.php" class="new-task-btn">+ Νέος Συνεργάτης</a>
    </div>

    <form method="get">
    <div class="filters-container">
        <div class="filters-row">
            <input type="text" name="search" placeholder="Αναζήτηση..."
                   value="<?= htmlspecialchars($search) ?>">
            <button class="filter-btn" type="submit">🔍</button>
        </div>
    </div>
    </form>

    <!-- ΠΙΝΑΚΑΣ DESKTOP -->
    <div class="table-wrapper desktop-table">
        <table>
            <thead>
                <tr>
                    <th>Ονοματεπώνυμο</th>
                    <th>Τηλέφωνο</th>
                    <th>Email</th>
                    <th style="text-align:center;">Ενέργειες</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($partners as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['fullname']) ?></td>
                    <td><?= htmlspecialchars((string)($p['phone'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($p['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td class="task-actions">
                        <a class="icon-btn gray" href="partner_form.php?id=<?= $p['id'] ?>">👁</a>
                        <a class="icon-btn blue" href="partner_form.php?id=<?= $p['id'] ?>">✏️</a>
                        <form method="post" action="partner_form.php" style="display:inline;">
                            <?= csrf_field() ?>
                            <button class="icon-btn red" type="submit" name="delete_partner" value="<?= (int)$p['id'] ?>"
                                    onclick="return confirm('Διαγραφή συνεργάτη;')">🗑</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- MOBILE CARDS -->
    <div class="mobile-cards">
        <?php foreach ($partners as $p): ?>
            <div class="mobile-card">
                <div class="mobile-card-header">
                    <div class="mobile-card-title"><?= htmlspecialchars($p['fullname']) ?></div>
                </div>

                <?php if ($p['phone']): ?>
                <div class="mobile-card-row">
                    <span class="mobile-card-label">Τηλέφωνο:</span>
                    <span><?= htmlspecialchars($p['phone']) ?></span>
                </div>
                <?php endif; ?>

                <?php if ($p['email']): ?>
                <div class="mobile-card-row">
                    <span class="mobile-card-label">Email:</span>
                    <span><?= htmlspecialchars($p['email']) ?></span>
                </div>
                <?php endif; ?>

                <div class="mobile-card-actions">
                    <a class="icon-btn gray" href="partner_form.php?id=<?= $p['id'] ?>">👁</a>
                    <a class="icon-btn blue" href="partner_form.php?id=<?= $p['id'] ?>">✏️</a>
                    <form method="post" action="partner_form.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <button class="icon-btn red" type="submit" name="delete_partner" value="<?= (int)$p['id'] ?>"
                                onclick="return confirm('Διαγραφή συνεργάτη;')">🗑</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<?php require "includes/footer.php"; ?>
