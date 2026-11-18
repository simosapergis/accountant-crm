<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/money.php';
require_once __DIR__ . '/includes/recurrence.php';

$taskIdInput = $_GET['task_id'] ?? null;
$ruleIdInput = $_GET['id'] ?? ($_POST['id'] ?? null);
$restartIdInput = $_GET['restart_id'] ?? null;
foreach ([$taskIdInput, $ruleIdInput, $restartIdInput] as $candidate) {
    if ($candidate !== null && (!is_string($candidate) || filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false)) {
        http_response_code(400);
        exit('Invalid recurrence identifier.');
    }
}
$taskId = $taskIdInput === null ? null : (int)$taskIdInput;
$ruleId = $ruleIdInput === null ? null : (int)$ruleIdInput;
$restartId = $restartIdInput === null ? null : (int)$restartIdInput;
$today = crm_recurrence_today();
$minimumMonth = crm_recurrence_month_add(substr($today, 0, 7) . '-01', 1);
$notice = '';
$error = '';
$effectiveMonth = null;
$rule = null;
$task = null;

if ($taskId !== null && $ruleId === null && $restartId === null) {
    $stmt = $mysqli->prepare('SELECT id,task_date,title,client_id,partner_id,fee,notes FROM tasks WHERE id=?');
    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    if ($task === null) { http_response_code(404); exit('Task not found.'); }
    $active = $mysqli->prepare("SELECT id FROM recurring_task_rules WHERE source_task_id=? AND state='active' LIMIT 1");
    $active->bind_param('i', $taskId);
    $active->execute();
    $activeRule = $active->get_result()->fetch_assoc();
    if ($activeRule !== null) { header('Location: recurring_task_form.php?id=' . (int)$activeRule['id']); exit; }
} elseif ($ruleId !== null) {
    $stmt = $mysqli->prepare('SELECT * FROM recurring_task_rules WHERE id=?');
    $stmt->bind_param('i', $ruleId);
    $stmt->execute();
    $rule = $stmt->get_result()->fetch_assoc();
    if ($rule === null) { http_response_code(404); exit('Rule not found.'); }
} elseif ($restartId !== null) {
    $stmt = $mysqli->prepare("SELECT * FROM recurring_task_rules WHERE id=? AND state='stopped'");
    $stmt->bind_param('i', $restartId);
    $stmt->execute();
    $rule = $stmt->get_result()->fetch_assoc();
    if ($rule === null) { http_response_code(404); exit('Stopped rule not found.'); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_new_rule']) && $taskId !== null && $task !== null) {
            $day = filter_var($_POST['day_of_month'] ?? null, FILTER_VALIDATE_INT);
            $start = crm_recurrence_parse_month($_POST['start_month'] ?? null);
            $feeCents = is_string($_POST['fee'] ?? null) ? crm_parse_money_cents($_POST['fee']) : null;
            if ($day === false || $day === null || $day < 1 || $day > 31 || $start === null || $feeCents === null) {
                throw new InvalidArgumentException('Ελέγξτε την ημέρα (1-31), τον μήνα έναρξης και την αμοιβή.');
            }
            $fee = crm_money_decimal($feeCents);
            $ruleId = crm_recurrence_create_rule($mysqli, $taskId, $day, $start, $fee, isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
            header('Location: recurring_task_form.php?id=' . $ruleId);
            exit;
        }
        if (isset($_POST['save_rule']) && $ruleId !== null && $rule !== null) {
            $day = filter_var($_POST['day_of_month'] ?? null, FILTER_VALIDATE_INT);
            $feeCents = is_string($_POST['fee'] ?? null) ? crm_parse_money_cents($_POST['fee']) : null;
            if ($day === false || $day === null || $day < 1 || $day > 31 || $feeCents === null) {
                throw new InvalidArgumentException('Ελέγξτε την ημέρα (1-31) και την αμοιβή.');
            }
            $effectiveMonth = crm_recurrence_update_rule($mysqli, $ruleId, (int)($_POST['row_version'] ?? 0), $day, crm_money_decimal($feeCents), $today);
            $notice = 'Οι αλλαγές ισχύουν από ' . substr($effectiveMonth, 0, 7) . ' (' . crm_recurrence_due_date($effectiveMonth, $day) . ').';
        } elseif (isset($_POST['stop_rule']) && $ruleId !== null && $rule !== null) {
            crm_recurrence_stop_rule($mysqli, $ruleId, (int)($_POST['row_version'] ?? 0), $today);
            header('Location: recurring_tasks.php');
            exit;
        } elseif (isset($_POST['restart_rule']) && $restartId !== null && $rule !== null) {
            $start = crm_recurrence_parse_month($_POST['start_month'] ?? null);
            if ($start === null) { throw new InvalidArgumentException('Επιλέξτε μήνα έναρξης.'); }
            $ruleId = crm_recurrence_restart_rule($mysqli, $restartId, $start, isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
            header('Location: recurring_task_form.php?id=' . $ruleId);
            exit;
        }
    } catch (Throwable $exception) {
        if ($restartId !== null && $exception instanceof mysqli_sql_exception && $exception->getCode() === 1062) {
            $active = $mysqli->prepare("SELECT id FROM recurring_task_rules WHERE source_task_id=? AND state='active' LIMIT 1");
            $sourceTaskId = $rule['source_task_id'] === null ? null : (int)$rule['source_task_id'];
            if ($sourceTaskId !== null) {
                $active->bind_param('i', $sourceTaskId);
                $active->execute();
                $activeRule = $active->get_result()->fetch_assoc();
            }
            if (!empty($activeRule['id'])) {
                $error = 'Υπάρχει ήδη ενεργή επανάληψη για αυτή την εργασία. Μπορείτε να ανοίξετε τις ρυθμίσεις της.';
                $existingActiveRuleId = (int)$activeRule['id'];
            } else {
                $error = 'Δεν ήταν δυνατή η επανέναρξη επειδή υπάρχει ήδη ενεργός κανόνας. Ανανεώστε τη σελίδα και ελέγξτε τις επαναλαμβανόμενες εργασίες.';
            }
        } else {
            $error = $exception->getMessage();
        }
    }
    if ($ruleId !== null) {
        $refresh = $mysqli->prepare('SELECT * FROM recurring_task_rules WHERE id=?');
        $refresh->bind_param('i', $ruleId);
        $refresh->execute();
        $rule = $refresh->get_result()->fetch_assoc() ?: $rule;
    }
}

$pageTitle = 'Ρυθμίσεις επανάληψης';
require __DIR__ . '/includes/header.php';
?>
<div class="page-container">
    <h2><?= $taskId !== null ? 'Νέα μηνιαία επανάληψη' : ($restartId !== null ? 'Επανέναρξη επανάληψης' : 'Ρυθμίσεις επανάληψης') ?></h2>
    <?php if ($error !== ''): ?><div class="alert alert-danger recurrence-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?php if (!empty($existingActiveRuleId)): ?> <a class="recurrence-alert-link" href="recurring_task_form.php?id=<?= $existingActiveRuleId ?>">Άνοιγμα ενεργών ρυθμίσεων</a><?php endif; ?></div><?php endif; ?>
    <?php if ($notice !== ''): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($taskId !== null && $task !== null): ?>
        <section class="recurrence-card recurrence-editor">
        <p class="recurrence-context"><?= htmlspecialchars((string)$task['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <form method="post" class="recurrence-form">
            <?= csrf_field() ?>
            <div class="recurrence-fields">
                <div class="form-group"><label for="day-of-month">Ημέρα μήνα</label><input id="day-of-month" type="number" name="day_of_month" min="1" max="31" required value="<?= (int)substr((string)$task['task_date'], 8, 2) ?>"><small>Αν λείπει από τον μήνα, επιλέγεται η τελευταία ημέρα.</small></div>
                <div class="form-group"><label for="start-month">Μήνας έναρξης</label><input id="start-month" type="month" name="start_month" min="<?= htmlspecialchars(substr($minimumMonth, 0, 7), ENT_QUOTES, 'UTF-8') ?>" required value="<?= htmlspecialchars(substr($minimumMonth, 0, 7), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="form-group"><label for="recurring-fee">Επαναλαμβανόμενη αμοιβή (€)</label><input id="recurring-fee" type="text" name="fee" required value="<?= htmlspecialchars(crm_money_input_value((string)$task['fee']), ENT_QUOTES, 'UTF-8') ?>"></div>
            </div>
            <div class="recurrence-form-actions"><a href="task_form.php?id=<?= (int)$taskId ?>" class="btn btn-secondary">Άκυρο</a><button class="btn btn-primary" type="submit" name="save_new_rule" value="1">Ενεργοποίηση επανάληψης</button></div>
        </form>
        </section>
    <?php elseif ($restartId !== null && $rule !== null): ?>
        <section class="recurrence-card recurrence-editor"><p class="recurrence-context">Νέος κανόνας από <strong><?= htmlspecialchars((string)$rule['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>. Το διάστημα διακοπής δεν αναπληρώνεται.</p>
        <form method="post" class="recurrence-form">
            <?= csrf_field() ?>
            <div class="recurrence-fields recurrence-fields-single"><div class="form-group"><label for="start-month">Μήνας έναρξης</label><input id="start-month" type="month" name="start_month" min="<?= htmlspecialchars(substr($minimumMonth, 0, 7), ENT_QUOTES, 'UTF-8') ?>" required value="<?= htmlspecialchars(substr($minimumMonth, 0, 7), ENT_QUOTES, 'UTF-8') ?>"></div></div>
            <div class="recurrence-form-actions"><a href="recurring_tasks.php" class="btn btn-secondary">Άκυρο</a><button class="btn btn-primary" type="submit" name="restart_rule" value="1">Επανέναρξη ως νέος κανόνας</button></div>
        </form></section>
    <?php elseif ($rule !== null && $rule['state'] === 'active'): ?>
        <section class="recurrence-card recurrence-editor">
        <header class="recurrence-rule-header"><div><h3><?= htmlspecialchars((string)$rule['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3><p>Ενεργή μηνιαία επανάληψη</p></div><span class="recurrence-badge">Έναρξη <?= htmlspecialchars(substr((string)$rule['start_month'], 0, 7), ENT_QUOTES, 'UTF-8') ?></span></header>
        <form method="post" class="recurrence-form">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$rule['id'] ?>"><input type="hidden" name="row_version" value="<?= (int)$rule['row_version'] ?>">
            <div class="recurrence-fields recurrence-fields-active"><div class="form-group"><label for="day-of-month">Ημέρα μήνα</label><input id="day-of-month" type="number" name="day_of_month" min="1" max="31" required value="<?= (int)$rule['day_of_month'] ?>"><small>Σε μικρότερο μήνα, χρησιμοποιείται η τελευταία ημέρα.</small></div><div class="form-group"><label for="recurring-fee">Επαναλαμβανόμενη αμοιβή (€)</label><input id="recurring-fee" type="text" name="fee" required value="<?= htmlspecialchars(crm_money_input_value((string)$rule['fee']), ENT_QUOTES, 'UTF-8') ?>"></div></div>
            <div class="recurrence-form-actions"><button class="btn btn-primary" type="submit" name="save_rule" value="1">Αποθήκευση ρυθμίσεων</button></div>
        </form>
        <form method="post" class="recurrence-stop-form">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$rule['id'] ?>"><input type="hidden" name="row_version" value="<?= (int)$rule['row_version'] ?>">
            <span>Η διακοπή ακυρώνει τις μελλοντικές εργασίες που δεν έχουν δημιουργηθεί.</span><button class="btn btn-danger" type="submit" name="stop_rule" value="1">Διακοπή επανάληψης</button>
        </form></section>
    <?php endif; ?>
    <nav class="recurrence-back-links" aria-label="Σχετικές σελίδες"><a href="recurring_tasks.php"><span aria-hidden="true">←</span> Επαναλαμβανόμενες εργασίες</a><a href="tasks.php"><span aria-hidden="true">↗</span> Εργασίες</a></nav>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
