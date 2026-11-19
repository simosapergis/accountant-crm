<?php
require_once __DIR__ . '/includes/auth.php';
if (isset($_GET['delete'])) {
    require_post_method();
}
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once __DIR__ . '/includes/money.php';
require_once __DIR__ . '/includes/catalog_admin.php';
require_once __DIR__ . '/includes/recurrence.php';

/* ----------------------------------------------------------
   ΔΙΑΓΡΑΦΗ
----------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task'])) {
    $id = (int)$_POST['delete_task'];
    $stmt = $mysqli->prepare("DELETE FROM tasks WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: tasks.php");
    exit;
}

/* ----------------------------------------------------------
   ΕΠΕΞΕΡΓΑΣΙΑ / ΝΕΑ ΕΡΓΑΣΙΑ
----------------------------------------------------------- */
$id = $_GET['id'] ?? null;
$formError = '';

$task = [
    'task_date'    => date('Y-m-d'),
    'title'        => '',
    'client_id'    => 0,
    'client_name'  => '',
    'partner_id'   => '',
    'status'       => 'Σε εξέλιξη',
    'fee'          => 0,
    'collected'    => 0,
    'payment_method_id' => null,
    'payment_method_label' => null,
    'notes'        => ''
];

if ($id) {
    $id = (int)$id;

    // Φέρνουμε και client_name για το input
    $stmt = $mysqli->prepare("
        SELECT t.*, c.name AS client_name
        FROM tasks t
        LEFT JOIN clients c ON c.id = t.client_id
        WHERE t.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $task = $row;
        if (!isset($task['client_name'])) $task['client_name'] = '';
    }
}
$titleTemplateResult = $mysqli->query("SELECT id, name FROM task_title_templates WHERE is_active = 1 ORDER BY name");
$titleTemplateOptions = [];
while ($titleTemplate = $titleTemplateResult->fetch_assoc()) {
    $titleTemplateOptions[] = $titleTemplate;
}
$originalPaymentMethodId = isset($task['payment_method_id']) ? (int)$task['payment_method_id'] : 0;
$originalPaymentMethodLabel = (string)($task['payment_method_label'] ?? '');
$minimumRecurrenceMonth = crm_recurrence_month_add(substr(crm_recurrence_today(), 0, 7) . '-01', 1);
$defaultRecurrenceDay = (int)substr((string)$task['task_date'], 8, 2);
$recurrenceDayInput = is_string($_POST['recurrence_day'] ?? null) ? $_POST['recurrence_day'] : (string)$defaultRecurrenceDay;
$recurrenceStartInput = is_string($_POST['recurrence_start_month'] ?? null) ? $_POST['recurrence_start_month'] : substr($minimumRecurrenceMonth, 0, 7);
$recurrenceFeeInput = is_string($_POST['recurrence_fee'] ?? null)
    ? $_POST['recurrence_fee']
    : (is_string($_POST['fee'] ?? null) ? $_POST['fee'] : crm_money_input_value((string)$task['fee']));
$enableRecurrence = !$id && ($_POST['enable_recurrence'] ?? '') === '1';
$recurrenceLinkId = null;
$canEnableRecurrence = false;
if ($id && isset($task['id'])) {
    $occurrenceRule = $mysqli->prepare('SELECT rule_id FROM recurrence_occurrences WHERE task_id=? LIMIT 1');
    $occurrenceRule->bind_param('i', $id);
    $occurrenceRule->execute();
    $occurrence = $occurrenceRule->get_result()->fetch_assoc();
    if ($occurrence !== null) {
        $recurrenceLinkId = (int)$occurrence['rule_id'];
    } else {
        $sourceRule = $mysqli->prepare('SELECT id FROM recurring_task_rules WHERE source_task_id=? AND state=\'active\' LIMIT 1');
        $sourceRule->bind_param('i', $id);
        $sourceRule->execute();
        $activeRule = $sourceRule->get_result()->fetch_assoc();
        if ($activeRule !== null) $recurrenceLinkId = (int)$activeRule['id'];
        else $canEnableRecurrence = true;
    }
}

/* ----------------------------------------------------------
   ΑΠΟΘΗΚΕΥΣΗ
----------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_task'])) {

    $task_date  = $_POST['task_date'] ?? date('Y-m-d');
    $titleSelectionInput = $_POST['title_selection'] ?? '';
    $titleSelection = is_string($titleSelectionInput) ? $titleSelectionInput : '';
    $titleInput = '';
    $title = '';
    if ($titleSelection === 'custom') {
        $customTitleInput = $_POST['custom_title'] ?? '';
        $titleInput = is_string($customTitleInput) ? $customTitleInput : null;
        $title = is_string($titleInput) ? trim($titleInput) : '';
    } elseif ($titleSelection !== '') {
        if (preg_match('/^template:(\d+)$/D', $titleSelection, $templateMatch) !== 1) {
            $formError = 'Επιλέξτε έναν έγκυρο τίτλο εργασίας.';
        } else {
            $templateId = (int)$templateMatch[1];
            $templateStmt = $mysqli->prepare('SELECT name, is_active FROM task_title_templates WHERE id = ?');
            $templateStmt->bind_param('i', $templateId);
            $templateStmt->execute();
            $selectedTemplate = $templateStmt->get_result()->fetch_assoc();
            if ($selectedTemplate === null) {
                $titleInput = null;
                $formError = 'Ο επιλεγμένος τίτλος δεν είναι πλέον διαθέσιμος.';
            } else {
                $titleInput = (string)$selectedTemplate['name'];
                $title = trim($titleInput);
                if ((int)$selectedTemplate['is_active'] !== 1) {
                    $formError = 'Ο επιλεγμένος τίτλος έχει απενεργοποιηθεί. Επιλέξτε άλλον τίτλο ή χρησιμοποιήστε «Άλλος τίτλος».';
                }
            }
        }
    }
    $client_id  = (int)($_POST['client_id'] ?? 0);
    $partner_id = ($_POST['partner_id'] ?? '') !== '' ? (int)$_POST['partner_id'] : null;
    $status     = $_POST['status'] ?? 'Σε εξέλιξη';
    $feeInput = is_string($_POST['fee'] ?? null) ? $_POST['fee'] : '';
    $collectedInput = is_string($_POST['collected'] ?? null) ? $_POST['collected'] : '';
    $feeCents = crm_parse_money_cents($feeInput);
    $collectedCents = crm_parse_money_cents($collectedInput);
    $notes      = $_POST['notes'] ?? '';
    $paymentMethodInput = is_string($_POST['payment_method_id'] ?? null) ? trim($_POST['payment_method_id']) : '';
    $paymentMethodId = $paymentMethodInput !== '' && ctype_digit($paymentMethodInput) && (int)$paymentMethodInput > 0
        ? (int)$paymentMethodInput : null;
    $paymentMethodLabel = null;

    $task = array_merge($task, [
        'task_date' => $task_date, 'title' => $title, 'client_id' => $client_id,
        'partner_id' => $partner_id, 'status' => $status, 'fee' => $feeInput,
        'collected' => $collectedInput, 'notes' => $notes,
        'payment_method_id' => $paymentMethodInput,
    ]);
    if ($feeCents === null || $collectedCents === null) {
        $formError = 'Παρακαλώ εισάγετε μη αρνητικά ποσά έως 99.999.999,99. Επιτρέπονται μορφές 100, 100,50, 100.50 και 1.234,56.';
    }
    if ($formError === '' && (!is_string($titleInput) || mb_strlen($title, 'UTF-8') > 255)) {
        $formError = 'Ο τίτλος εργασίας πρέπει να έχει έως 255 χαρακτήρες.';
    }

    $recurrenceDay = filter_var($recurrenceDayInput, FILTER_VALIDATE_INT);
    $recurrenceStartMonth = crm_recurrence_parse_month($recurrenceStartInput);
    $recurrenceFeeCents = crm_parse_money_cents($recurrenceFeeInput);
    if ($formError === '' && $enableRecurrence && (
        $recurrenceDay === false || $recurrenceDay < 1 || $recurrenceDay > 31 ||
        $recurrenceStartMonth === null || $recurrenceStartMonth < $minimumRecurrenceMonth ||
        $recurrenceFeeCents === null
    )) {
        $formError = 'Ελέγξτε την ημέρα (1-31), τον μήνα έναρξης και την επαναλαμβανόμενη αμοιβή.';
    }

    if ($formError === '' && $collectedCents > 0) {
        if ($id && $originalPaymentMethodId > 0 && $paymentMethodId === $originalPaymentMethodId) {
            // Keep the original label snapshot even if the catalog entry has since changed or been deactivated.
            $paymentMethodLabel = $originalPaymentMethodLabel;
            if ($paymentMethodLabel === '') {
                $formError = 'Η παλιά είσπραξη χρειάζεται ενεργό μέσο είσπραξης.';
            }
        } elseif ($paymentMethodId === null) {
            $formError = 'Για θετική είσπραξη απαιτείται ενεργό μέσο είσπραξης.';
        } else {
            $methodStmt = $mysqli->prepare('SELECT name FROM payment_methods WHERE id = ? AND is_active = 1');
            $methodStmt->bind_param('i', $paymentMethodId);
            $methodStmt->execute();
            $method = $methodStmt->get_result()->fetch_assoc();
            if ($method === null) {
                $formError = 'Για θετική είσπραξη απαιτείται ενεργό μέσο είσπραξης.';
            } else {
                $paymentMethodLabel = (string)$method['name'];
            }
        }
    }

    if ($formError === '' && $client_id <= 0) {
        // Αν θες μπορείς να βάλεις validation μήνυμα. Προς το παρόν απλά δεν σώζουμε.
        header("Location: task_form.php" . ($id ? "?id=".(int)$id : ""));
        exit;
    }

    if ($formError === '' && $id) {
        $fee = crm_money_decimal($feeCents);
        $collected = crm_money_decimal($collectedCents);
        $stmt = $mysqli->prepare("
            UPDATE tasks
            SET task_date=?, title=?, client_id=?, partner_id=?, status=?, fee=?, collected=?, payment_method_id=?, payment_method_label=?, notes=?, updated_at=NOW()
            WHERE id=?
        ");

        $stmt->bind_param(
            "ssiisssissi",
            $task_date,
            $title,
            $client_id,
            $partner_id,
            $status,
            $fee,
            $collected,
            $paymentMethodId,
            $paymentMethodLabel,
            $notes,
            $id
        );

    } elseif ($formError === '') {
        $fee = crm_money_decimal($feeCents);
        $collected = crm_money_decimal($collectedCents);
        $stmt = $mysqli->prepare("
            INSERT INTO tasks (task_date, title, client_id, partner_id, status, fee, collected, payment_method_id, payment_method_label, notes)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ");

        $stmt->bind_param(
            "ssiisssiss",
            $task_date,
            $title,
            $client_id,
            $partner_id,
            $status,
            $fee,
            $collected,
            $paymentMethodId,
            $paymentMethodLabel,
            $notes
        );
    }

    if ($formError === '') {
        try {
            if ($enableRecurrence) {
                $mysqli->begin_transaction();
            }
            $stmt->execute();
            if ($enableRecurrence) {
                $newTaskId = (int)$stmt->insert_id;
                $recurrenceFee = crm_money_decimal($recurrenceFeeCents);
                crm_recurrence_create_rule(
                    $mysqli,
                    $newTaskId,
                    (int)$recurrenceDay,
                    $recurrenceStartMonth,
                    $recurrenceFee,
                    isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                    false
                );
                $mysqli->commit();
            }
            if ($collectedCents === 0 && $originalPaymentMethodId > 0) {
                $_SESSION['flash_message'] = 'Η είσπραξη μηδενίστηκε και το μέσο είσπραξης καθαρίστηκε.';
            }
            header("Location: tasks.php");
            exit;
        } catch (Throwable $exception) {
            if ($enableRecurrence) {
                $mysqli->rollback();
            }
            $formError = 'Δεν ήταν δυνατή η αποθήκευση της εργασίας και της μηνιαίας επανάληψης. Ελέγξτε τα στοιχεία και δοκιμάστε ξανά.';
        }
    }
}

/* ----------------------------------------------------------
   ΛΙΣΤΑ ΣΥΝΕΡΓΑΤΩΝ
----------------------------------------------------------- */
$partners = $mysqli->query("SELECT id, fullname FROM partners ORDER BY fullname");
$paymentMethods = $mysqli->query("SELECT id, name FROM payment_methods WHERE is_active = 1 ORDER BY name");
$activePaymentMethods = [];
while ($method = $paymentMethods->fetch_assoc()) {
    $activePaymentMethods[] = $method;
}

$pageTitle = ($id ? "Επεξεργασία Εργασίας" : "Νέα Εργασία");
require "includes/header.php";
?>

<!-- Bootstrap CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<style>
/* Dropdown αποτελεσμάτων πελάτη */
#client_results .client-item {
    display: block;
    width: 100%;
    padding: 10px 10px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    border-top: 0;
    border-left: 0;
    border-right: 0;
    background: #fff;
    color: inherit;
    font-size: 14px;
    text-align: left;
}
#client_results .client-item:hover {
    background: #f8fafc;
}
#client_results .client-item:focus-visible {
    outline: 2px solid #007bff;
    outline-offset: -2px;
}
</style>

<script>
// Καλείται από save_client_ajax.php μέσα από iframe (όπως το έχεις)
function addClientToDropdown(id, name) {
    document.getElementById("client_search").value = name;
    document.getElementById("client_id").value = id;
    if (Array.isArray(clientListCache) && !clientListCache.some(client => String(client.id) === String(id))) {
        clientListCache.push({ id: Number(id), name: name, afm: "" });
        clientListCache.sort((a, b) => a.name.localeCompare(b.name));
    }

    const box = document.getElementById("client_results");
    box.innerHTML = "";
    hideResults();

    const modal = bootstrap.Modal.getInstance(document.getElementById("addClientModal"));
    if (modal) modal.hide();
}

function updateTaskTitleInput() {
    const selector = document.getElementById("title_selection");
    const customGroup = document.getElementById("custom_title_group");
    const customInput = document.getElementById("custom_title");
    const useCustomTitle = selector.value === "custom";
    customGroup.hidden = !useCustomTitle;
    customInput.disabled = !useCustomTitle;
}
</script>

<!-- ============================================
     ΦΟΡΜΑ ΕΡΓΑΣΙΑΣ
============================================ -->
<form method="post">
    <?= csrf_field() ?>
    <?php if ($formError !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($formError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($id): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>
    <input type="hidden" name="save_task" value="1">

    <div class="form-card">

        <div class="form-row">
            <div class="form-group">
                <label>Ημερομηνία</label>
                <input type="date" name="task_date" style="height:48px;"
                       value="<?= htmlspecialchars($task['task_date']) ?>">
            </div>

            <div class="form-group">
                <label for="title_selection">Τίτλος</label>
                <?php
                    $currentTaskTitle = (string)($task['title'] ?? '');
                    $selectedTitleTemplateId = null;
                    // Existing titles are snapshots, independent of later catalog changes.
                    $customTitleSelected = $currentTaskTitle !== '' && $selectedTitleTemplateId === null;
                ?>
                <select id="title_selection" name="title_selection" style="height:48px;" onchange="updateTaskTitleInput()">
                    <option value="" <?= $currentTaskTitle === '' ? 'selected' : '' ?>>— Επιλέξτε τίτλο —</option>
                    <?php foreach ($titleTemplateOptions as $titleTemplate): ?>
                        <option value="template:<?= (int)$titleTemplate['id'] ?>" <?= $selectedTitleTemplateId === (int)$titleTemplate['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$titleTemplate['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="custom" <?= $customTitleSelected ? 'selected' : '' ?>>Άλλος τίτλος…</option>
                </select>
                <div id="custom_title_group" <?= $customTitleSelected ? '' : 'hidden' ?>>
                    <label for="custom_title">Ελεύθερος τίτλος</label>
                    <input type="text" id="custom_title" name="custom_title" maxlength="255"
                           value="<?= $customTitleSelected ? htmlspecialchars($currentTaskTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '' ?>"
                           <?= $customTitleSelected ? '' : 'disabled' ?> style="height:48px;">
                </div>
            </div>
        </div>

        <!-- Πελάτης + Προσθήκη + AJAX Search -->
        <div class="form-row">

            <div class="form-group" style="position:relative;">
                <label for="client_search">Πελάτης</label>

                <div style="display:flex; gap:8px;">
                    <input type="text"
                           id="client_search"
                           aria-controls="client_results"
                           aria-expanded="false"
                           aria-autocomplete="list"
                           placeholder="Αναζήτηση πελάτη..."
                           autocomplete="off"
                           style="height:48px;"
                           value="<?= htmlspecialchars($task['client_name'] ?? '') ?>">

                    <input type="hidden"
                           name="client_id"
                           id="client_id"
                           value="<?= (int)($task['client_id'] ?? 0) ?>">

                    <button type="button"
                            id="client_list_button"
                            class="btn btn-secondary"
                            aria-label="Άνοιγμα λίστας πελατών"
                            aria-controls="client_results"
                            aria-expanded="false"
                            title="Εμφάνιση όλων των πελατών"
                            style="height:48px;">🔍</button>

                    <button type="button"
                            class="btn btn-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#addClientModal"
                            style="height:48px;">+</button>
                </div>

                <div id="client_results"
                     role="listbox"
                     aria-label="Λίστα πελατών"
                     style="
                        position:absolute;
                        top:100%;
                        left:0;
                        right:0;
                        background:#fff;
                        border:1px solid #ddd;
                        border-radius:6px;
                        z-index:9999;
                        display:none;
                        max-height:240px;
                        overflow-y:auto;">
                </div>
            </div>

            <div class="form-group">
                <label>Συνεργάτης</label>
                <select name="partner_id" style="height:48px;">
                    <option value="">—</option>
                    <?php while ($p = $partners->fetch_assoc()): ?>
                        <option value="<?= $p['id'] ?>" <?= ((string)$p['id'] === (string)($task['partner_id'] ?? '')) ? "selected":"" ?>>
                            <?= htmlspecialchars($p['fullname']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Κατάσταση</label>
                <select name="status" style="height:48px;">
                    <option <?= ($task['status']=="Σε εξέλιξη" ? "selected":"") ?>>Σε εξέλιξη</option>
                    <option <?= ($task['status']=="Ολοκληρωμένη" ? "selected":"") ?>>Ολοκληρωμένη</option>
                    <option <?= ($task['status']=="Αναμονή εξόφλησης" ? "selected":"") ?>>Αναμονή εξόφλησης</option>
                </select>
            </div>

        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Αμοιβή (€)</label>
                <input type="text" name="fee" style="height:48px;"
                       value="<?= htmlspecialchars(isset($_POST['fee']) ? (string)$task['fee'] : crm_money_input_value((string)$task['fee']), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group">
                <label>Είσπραξη (€)</label>
                <input type="text" name="collected" style="height:48px;"
                       value="<?= htmlspecialchars(isset($_POST['collected']) ? (string)$task['collected'] : crm_money_input_value((string)$task['collected']), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group">
                <label for="payment-method">Μέσο είσπραξης</label>
                <?php $currentMethodId = (string)($task['payment_method_id'] ?? ''); ?>
                <select id="payment-method" name="payment_method_id" style="height:48px;">
                    <option value="">— Χωρίς μέσο —</option>
                    <?php foreach ($activePaymentMethods as $method): ?>
                        <option value="<?= (int)$method['id'] ?>" <?= $currentMethodId === (string)$method['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$method['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                    <?php if ($currentMethodId !== '' && !array_filter($activePaymentMethods, static fn(array $method): bool => (string)$method['id'] === $currentMethodId)): ?>
                        <option value="<?= (int)$currentMethodId ?>" selected><?= htmlspecialchars((string)($task['payment_method_label'] ?? 'Ανενεργό μέσο'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (ανενεργό)</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Υπόλοιπο (€)</label>
                <?php $balanceCents = (crm_parse_money_cents((string)$task['fee']) ?? 0) - (crm_parse_money_cents((string)$task['collected']) ?? 0); ?>
                <input type="text" disabled style="height:48px; background:#eee;"
                       value="<?= htmlspecialchars(($balanceCents < 0 ? '-' : '') . crm_money_input_value(crm_money_decimal(abs($balanceCents))), ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Παρατηρήσεις</label>
                <textarea name="notes" rows="4"><?= htmlspecialchars($task['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <?php if (!$id): ?>
        <section class="recurrence-card" aria-labelledby="recurrence-heading">
            <div class="recurrence-toggle-row">
                <label class="recurrence-toggle" for="enable-recurrence">
                    <input id="enable-recurrence" type="checkbox" name="enable_recurrence" value="1" <?= $enableRecurrence ? 'checked' : '' ?>>
                    <span class="recurrence-toggle-copy">
                        <strong id="recurrence-heading">Επανάληψη εργασίας κάθε μήνα</strong>
                        <span>Δημιουργεί ξεχωριστή εργασία κάθε μήνα από την ημερομηνία έναρξης.</span>
                    </span>
                </label>
                <span class="recurrence-badge">Προαιρετικό</span>
            </div>
            <div id="recurrence-settings" class="recurrence-settings" style="display:<?= $enableRecurrence ? 'grid' : 'none' ?>;">
                <div class="form-group">
                    <label for="recurrence-day">Ημέρα μήνα (1-31)</label>
                    <input id="recurrence-day" type="number" name="recurrence_day" min="1" max="31" value="<?= htmlspecialchars($recurrenceDayInput, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label for="recurrence-start-month">Μήνας έναρξης</label>
                    <input id="recurrence-start-month" type="month" name="recurrence_start_month" min="<?= htmlspecialchars(substr($minimumRecurrenceMonth, 0, 7), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($recurrenceStartInput, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label for="recurrence-fee">Επαναλαμβανόμενη αμοιβή (€)</label>
                    <input id="recurrence-fee" type="text" name="recurrence_fee" value="<?= htmlspecialchars($recurrenceFeeInput, ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
        </section>
        <script>
            const recurrenceToggle = document.getElementById('enable-recurrence');
            const recurrenceSettings = document.getElementById('recurrence-settings');
            const taskFeeInput = document.querySelector('input[name="fee"]');
            const recurringFeeInput = document.getElementById('recurrence-fee');
            let recurringFeeWasEdited = <?= isset($_POST['recurrence_fee']) ? 'true' : 'false' ?>;
            recurrenceToggle.addEventListener('change', () => {
                recurrenceSettings.style.display = recurrenceToggle.checked ? 'grid' : 'none';
            });
            recurringFeeInput.addEventListener('input', () => { recurringFeeWasEdited = true; });
            taskFeeInput.addEventListener('input', () => {
                if (!recurringFeeWasEdited) recurringFeeInput.value = taskFeeInput.value;
            });
        </script>
        <?php endif; ?>

        <div class="form-actions">
            <a href="tasks.php" class="btn btn-secondary">Άκυρο</a>
            <?php if ($recurrenceLinkId !== null): ?><a href="recurring_task_form.php?id=<?= $recurrenceLinkId ?>" class="btn btn-secondary">Ρυθμίσεις επανάληψης</a>
            <?php elseif ($canEnableRecurrence): ?><a href="recurring_task_form.php?task_id=<?= (int)$id ?>" class="btn btn-secondary">Μηνιαία επανάληψη</a><?php endif; ?>
            <?php if ($id): ?>
                <button class="btn btn-danger" type="submit" name="delete_task" value="<?= (int)$id ?>"
                        onclick="return confirm('Διαγραφή εργασίας;')">Διαγραφή</button>
            <?php endif; ?>
            <button class="btn btn-primary" type="submit">Αποθήκευση</button>
        </div>

    </div>
</form>

<!-- ============================================
     BOOTSTRAP MODAL — ΝΕΟΣ ΠΕΛΑΤΗΣ
============================================ -->
<div class="modal fade" id="addClientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <form id="clientModalForm"
                  method="post"
                  action="save_client_ajax.php"
                  target="clientSaveFrame">
                <?= csrf_field() ?>

                <div class="modal-header">
                    <h5 class="modal-title">Νέος Πελάτης</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <label>Επωνυμία</label>
                    <input type="text" name="name" class="form-control" required>

                    <label class="mt-2">ΑΦΜ</label>
                    <input type="text" name="afm" class="form-control">

                    <label class="mt-2">Κατηγορία</label>
                    <select name="category" class="form-control" required>
                        <option value="Ιδιώτης">Ιδιώτης</option>
                        <option value="Επιχείρηση">Επιχείρηση</option>
                    </select>

                    <label class="mt-2">Τηλέφωνο</label>
                    <input type="text" name="phone" class="form-control">

                    <label class="mt-2">Email</label>
                    <input type="email" name="email" class="form-control">

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Άκυρο</button>
                    <button class="btn btn-primary" type="submit">Αποθήκευση</button>
                </div>

            </form>

            <iframe name="clientSaveFrame" style="display:none;"></iframe>

        </div>
    </div>
</div>

<script>
let timer = null;
let clientListCache = null;
let clientListRequest = null;
let showingAllClients = false;
let allClientsFilter = '';

const input = document.getElementById("client_search");
const box = document.getElementById("client_results");
const listButton = document.getElementById("client_list_button");

function setClientResultsOpen(isOpen) {
    box.style.display = isOpen ? "block" : "none";
    input.setAttribute("aria-expanded", isOpen ? "true" : "false");
    listButton.setAttribute("aria-expanded", isOpen ? "true" : "false");
}

function hideResults() {
    showingAllClients = false;
    setClientResultsOpen(false);
}

function renderMessage(message, color = "#6b7280") {
    box.innerHTML = "";
    const item = document.createElement("div");
    item.style.cssText = "padding:10px;color:" + color + ";";
    item.textContent = message;
    box.appendChild(item);
    setClientResultsOpen(true);
}

function renderResults(data) {
    box.innerHTML = "";

    if (!Array.isArray(data) || data.length === 0) {
        renderMessage("Δεν βρέθηκαν αποτελέσματα");
        return;
    }

    data.forEach(c => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "client-item";
        button.setAttribute("role", "option");
        button.setAttribute("aria-selected", "false");
        const name = document.createElement("strong");
        name.textContent = c.name;
        button.appendChild(name);

        if (c.afm) {
            button.appendChild(document.createElement("br"));
            const afm = document.createElement("small");
            afm.textContent = c.afm;
            button.appendChild(afm);
        }
        button.addEventListener("click", () => {
            input.value = c.name;
            document.getElementById("client_id").value = c.id;
            hideResults();
        });
        box.appendChild(button);
    });

    setClientResultsOpen(true);
}

function filterAllClients(query) {
    if (!Array.isArray(clientListCache)) return;
    const normalizedQuery = query.trim().toLocaleLowerCase();
    const filteredClients = normalizedQuery === ""
        ? clientListCache
        : clientListCache.filter(c => `${c.name} ${c.afm || ""}`.toLocaleLowerCase().includes(normalizedQuery));
    renderResults(filteredClients);
}

function loadAllClients() {
    if (clientListRequest) return clientListRequest;

    clientListRequest = fetch("ajax/search_clients.php?all=1")
        .then(response => {
            if (!response.ok) throw new Error("Client list request failed");
            return response.json();
        })
        .then(data => {
            if (!Array.isArray(data)) throw new Error("Invalid client list response");
            clientListCache = data;
            return data;
        })
        .finally(() => {
            clientListRequest = null;
        });
    return clientListRequest;
}

listButton.addEventListener("click", () => {
    if (showingAllClients && box.style.display === "block") {
        hideResults();
        return;
    }

    clearTimeout(timer);
    showingAllClients = true;
    allClientsFilter = "";
    setClientResultsOpen(true);
    renderMessage("Φόρτωση πελατών...");
    loadAllClients()
        .then(() => {
            if (showingAllClients) filterAllClients(allClientsFilter);
        })
        .catch(() => {
            if (showingAllClients) renderMessage("Σφάλμα φόρτωσης πελατών", "#dc3545");
        });
});

input.addEventListener("input", function () {
    const q = this.value.trim();

    // Αν αλλάξει το κείμενο, καθάρισε το client_id για να μη μείνει λάθος παλιός πελάτης.
    document.getElementById("client_id").value = "";
    clearTimeout(timer);

    if (showingAllClients) {
        allClientsFilter = q;
        if (Array.isArray(clientListCache)) filterAllClients(q);
        return;
    }

    if (q.length < 2) {
        hideResults();
        return;
    }

    timer = setTimeout(() => {
        fetch("ajax/search_clients.php?q=" + encodeURIComponent(q))
            .then(response => {
                if (!response.ok) throw new Error("Client search failed");
                return response.json();
            })
            .then(renderResults)
            .catch(() => renderMessage("Σφάλμα αναζήτησης", "#dc3545"));
    }, 250);
});

// Κλείσιμο dropdown όταν κάνεις click έξω.
document.addEventListener("click", function(e) {
    if (!box.contains(e.target) && e.target !== input && e.target !== listButton) {
        hideResults();
    }
});
</script>

<?php require "includes/footer.php"; ?>
