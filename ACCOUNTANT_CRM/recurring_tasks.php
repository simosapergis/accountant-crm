<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Επαναλαμβανόμενες εργασίες';
$result = $mysqli->query(
    'SELECT r.*, c.name AS client_name, p.fullname AS partner_name, ' .
    '(SELECT COUNT(*) FROM recurrence_occurrences o WHERE o.rule_id=r.id) AS occurrence_count ' .
    'FROM recurring_task_rules r JOIN clients c ON c.id=r.client_id LEFT JOIN partners p ON p.id=r.partner_id ' .
    'ORDER BY (r.state=\'active\') DESC, r.id DESC'
);
require __DIR__ . '/includes/header.php';
?>
<div class="page-container">
    <h2>Επαναλαμβανόμενες εργασίες</h2>
    <div class="table-wrapper"><table>
        <thead><tr><th>Πελάτης</th><th>Τίτλος</th><th>Ημέρα</th><th>Έναρξη</th><th>Αμοιβή</th><th>Κατάσταση</th><th>Εργασίες</th><th>Ρυθμίσεις</th></tr></thead>
        <tbody>
        <?php while ($rule = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars((string)$rule['client_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)$rule['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td><?= (int)$rule['day_of_month'] ?></td>
                <td><?= htmlspecialchars(substr((string)$rule['start_month'], 0, 7), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= formatMoney($rule['fee']) ?></td>
                <td><?= $rule['state'] === 'active' ? 'Ενεργός' : 'Σταματημένος' ?></td>
                <td><?= (int)$rule['occurrence_count'] ?></td>
                <td><a class="btn btn-secondary" href="recurring_task_form.php?<?= $rule['state'] === 'active' ? 'id' : 'restart_id' ?>=<?= (int)$rule['id'] ?>"><?= $rule['state'] === 'active' ? 'Ρυθμίσεις' : 'Επανέναρξη' ?></a></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
