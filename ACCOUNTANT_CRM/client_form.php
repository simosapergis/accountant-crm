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
    'email' => ''
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
        'email' => trim($_POST['email'] ?? '')
    ];

    try {
        if ($id) {
            $stmt = $mysqli->prepare(
                'UPDATE clients SET name=?, afm=?, category=?, phone=?, email=? WHERE id=?'
            );
            $stmt->bind_param(
                'sssssi', $client['name'], $client['afm'], $client['category'],
                $client['phone'], $client['email'], $id
            );
        } else {
            $stmt = $mysqli->prepare(
                'INSERT INTO clients (name, afm, category, phone, email) VALUES (?,?,?,?,?)'
            );
            $stmt->bind_param(
                'sssss', $client['name'], $client['afm'], $client['category'],
                $client['phone'], $client['email']
            );
        }

        if (!$stmt->execute()) {
            throw new mysqli_sql_exception($stmt->error, $stmt->errno);
        }

        header('Location: clients.php');
        exit;
    } catch (mysqli_sql_exception $exception) {
        error_log('Client save failed: ' . $exception->getMessage());
        $saveError = 'Η αποθήκευση απέτυχε. Ελέγξτε τα στοιχεία και δοκιμάστε ξανά.';
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
                <input type="text" name="afm" value="<?= htmlspecialchars($client['afm']) ?>">
            </div>
            <div class="form-group">
                <label>Κατηγορία</label>
                <select name="category">
                    <option <?= $client['category']=='Ιδιώτης' ? 'selected' : '' ?>>Ιδιώτης</option>
                    <option <?= $client['category']=='Επιχείρηση' ? 'selected' : '' ?>>Επιχείρηση</option>
                    <option <?= $client['category']=='Λοιπά' ? 'selected' : '' ?>>Λοιπά</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Τηλέφωνο</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($client['phone']) ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($client['email']) ?>">
            </div>
        </div>

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
