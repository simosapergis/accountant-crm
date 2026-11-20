<?php
require_once __DIR__ . '/includes/auth.php';
if (isset($_GET['delete'])) {
    require_once __DIR__ . '/includes/csrf.php';
    require_post_method();
}
require_once 'includes/db.php';

$id = $_GET['id'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_client'])) {
    $deleteId = (int)$_POST['delete_client'];
    $recurrenceUse = $mysqli->prepare('SELECT 1 FROM recurring_task_rules WHERE client_id=? LIMIT 1');
    $recurrenceUse->bind_param('i', $deleteId);
    $recurrenceUse->execute();
    if ($recurrenceUse->get_result()->num_rows > 0) {
        header('Location: client_form.php?id=' . $deleteId . '&delete_error=recurrence');
        exit;
    }
    $stmt = $mysqli->prepare('DELETE FROM clients WHERE id = ?');
    $stmt->bind_param('i', $deleteId);
    $stmt->execute();
    header('Location: clients.php');
    exit;
}

$client = [
    'name' => '',
    'afm' => '',
    'category' => 'Ιδιώτης',
    'phone' => '',
    'email' => '',
    'has_professional_profile' => 0, 'professional_code' => '', 'professional_is_active' => 1,
    'professional_start_date' => '', 'professional_end_date' => '', 'professional_has_vat' => 0,
    'professional_vat_type' => '', 'professional_has_payroll' => 0
];
$saveError = ($_GET['delete_error'] ?? '') === 'recurrence'
    ? 'Ο πελάτης δεν μπορεί να διαγραφεί επειδή συνδέεται με ενεργό ή ιστορικό κανόνα επανάληψης.'
    : '';

if ($id) {
    $stmt = $mysqli->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $client = $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $client = [
        'name' => trim($_POST['name'] ?? ''),
        'afm' => trim($_POST['afm'] ?? ''),
        'category' => $_POST['category'] ?? 'Ιδιώτης',
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'has_professional_profile' => isset($_POST['has_professional_profile']) ? 1 : 0,
        'professional_code' => trim((string)($_POST['professional_code'] ?? '')) ?: null,
        'professional_is_active' => isset($_POST['professional_is_active']) ? 1 : 0,
        'professional_start_date' => ($_POST['professional_start_date'] ?? '') ?: null,
        'professional_end_date' => ($_POST['professional_end_date'] ?? '') ?: null,
        'professional_has_vat' => isset($_POST['professional_has_vat']) ? 1 : 0,
        'professional_vat_type' => (string)($_POST['professional_vat_type'] ?? '') ?: null,
        'professional_has_payroll' => isset($_POST['professional_has_payroll']) ? 1 : 0
    ];

    if ($client['has_professional_profile'] && (empty($client['professional_code']) || !in_array($client['professional_vat_type'] ?? '', ['', 'Μηνιαίο', 'Τριμηνιαίο'], true))) {
        $saveError = 'Για επαγγελματικό προφίλ απαιτείται κωδικός και έγκυρος τύπος ΦΠΑ.';
    }

    try {
        if ($saveError !== '') {
            throw new RuntimeException('Invalid professional profile');
        }
        if ($id) {
            $stmt = $mysqli->prepare(
                'UPDATE clients SET name=?, afm=?, category=?, phone=?, email=?, has_professional_profile=?, professional_code=?, professional_is_active=?, professional_start_date=?, professional_end_date=?, professional_has_vat=?, professional_vat_type=?, professional_has_payroll=? WHERE id=?'
            );
            $stmt->bind_param(
                'sssssisissisii', $client['name'], $client['afm'], $client['category'],
                $client['phone'], $client['email'], $client['has_professional_profile'], $client['professional_code'],
                $client['professional_is_active'], $client['professional_start_date'], $client['professional_end_date'],
                $client['professional_has_vat'], $client['professional_vat_type'], $client['professional_has_payroll'], $id
            );
        } else {
            $stmt = $mysqli->prepare(
                'INSERT INTO clients (name, afm, category, phone, email, has_professional_profile, professional_code, professional_is_active, professional_start_date, professional_end_date, professional_has_vat, professional_vat_type, professional_has_payroll) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->bind_param(
                'sssssisissisi', $client['name'], $client['afm'], $client['category'], $client['phone'], $client['email'],
                $client['has_professional_profile'], $client['professional_code'], $client['professional_is_active'],
                $client['professional_start_date'], $client['professional_end_date'], $client['professional_has_vat'],
                $client['professional_vat_type'], $client['professional_has_payroll']
            );
        }

        if (!$stmt->execute()) {
            throw new mysqli_sql_exception($stmt->error, $stmt->errno);
        }

        header('Location: clients.php');
        exit;
    } catch (Throwable $exception) {
        if ($saveError === '') {
            error_log('Client save failed: ' . $exception->getMessage());
            $saveError = 'Η αποθήκευση απέτυχε. Ελέγξτε αν ο επαγγελματικός κωδικός χρησιμοποιείται ήδη.';
        }
    }
}

$pageTitle = $id ? 'Επεξεργασία Πελάτη' : 'Νέος Πελάτης';
require 'includes/header.php';
?>

<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') ?>">
    <div class="form-card">
        <?php if ($saveError): ?>
            <div role="alert" style="margin-bottom:16px;padding:12px;border-radius:6px;background:#f8d7da;color:#842029;">
                <?= htmlspecialchars($saveError) ?>
            </div>
        <?php endif; ?>
        <div class="form-row">
            <div class="form-group">
                <label>Επωνυμία</label>
                <input type="text" name="name" value="<?= htmlspecialchars($client['name']) ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>ΑΦΜ</label>
                <input type="text" name="afm" value="<?= htmlspecialchars($client['afm'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Κατηγορία</label>
                <select name="category">
                    <option <?= $client['category']=='Ιδιώτης' ? 'selected' : '' ?>>Ιδιώτης</option>
                    <option <?= $client['category']=='Επιχείρηση' ? 'selected' : '' ?>>Επιχείρηση</option>
                    <option <?= $client['category']=='Εταιρεία' ? 'selected' : '' ?>>Εταιρεία</option>
                    <option <?= $client['category']=='Λοιπά' ? 'selected' : '' ?>>Λοιπά</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Τηλέφωνο</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($client['email'] ?? '') ?>">
            </div>
        </div>

        <fieldset class="form-card" style="margin-top:16px">
            <legend>Επαγγελματικά στοιχεία</legend>
            <label><input type="checkbox" name="has_professional_profile" value="1" <?= !empty($client['has_professional_profile']) ? 'checked' : '' ?>> Επαγγελματικό προφίλ</label>
            <div class="form-row"><div class="form-group"><label for="professional-code">Κωδικός</label><input id="professional-code" name="professional_code" value="<?= htmlspecialchars((string)($client['professional_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="form-group"><label><input type="checkbox" name="professional_is_active" value="1" <?= !empty($client['professional_is_active']) ? 'checked' : '' ?>> Ενεργός</label></div></div>
            <div class="form-row"><div class="form-group"><label for="professional-start">Έναρξη</label><input id="professional-start" type="date" name="professional_start_date" value="<?= htmlspecialchars((string)($client['professional_start_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="form-group"><label for="professional-end">Διακοπή</label><input id="professional-end" type="date" name="professional_end_date" value="<?= htmlspecialchars((string)($client['professional_end_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div></div>
            <div class="form-row"><div class="form-group"><label><input type="checkbox" name="professional_has_vat" value="1" <?= !empty($client['professional_has_vat']) ? 'checked' : '' ?>> ΦΠΑ</label></div>
            <div class="form-group"><label for="professional-vat-type">Τύπος ΦΠΑ</label><select id="professional-vat-type" name="professional_vat_type"><option value="">—</option><option value="Μηνιαίο" <?= ($client['professional_vat_type'] ?? '') === 'Μηνιαίο' ? 'selected' : '' ?>>Μηνιαίο</option><option value="Τριμηνιαίο" <?= ($client['professional_vat_type'] ?? '') === 'Τριμηνιαίο' ? 'selected' : '' ?>>Τριμηνιαίο</option></select></div>
            <div class="form-group"><label><input type="checkbox" name="professional_has_payroll" value="1" <?= !empty($client['professional_has_payroll']) ? 'checked' : '' ?>> Μισθοδοσία</label></div></div>
        </fieldset>

        <div class="form-actions">
            <a href="clients.php" class="btn btn-secondary">Άκυρο</a>
            <?php if ($id): ?>
                <button type="submit" name="delete_client" value="<?= (int)$id ?>" class="btn btn-danger"
                        onclick="return confirm('Διαγραφή πελάτη;')">Διαγραφή</button>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Αποθήκευση</button>
        </div>
    </div>
</form>

<?php require 'includes/footer.php'; ?>
