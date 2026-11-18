<?php
declare(strict_types=1);

function crm_catalog_config(string $kind): array
{
    return match ($kind) {
        'payment_method' => [
            'table' => 'payment_methods',
            'plural_route' => 'payment_methods.php',
            'form_route' => 'payment_method_form.php',
            'label' => 'Μέσο είσπραξης',
            'plural_label' => 'Μέσα είσπραξης',
            'max_length' => 100,
        ],
        'task_title' => [
            'table' => 'task_title_templates',
            'plural_route' => 'task_titles.php',
            'form_route' => 'task_title_form.php',
            'label' => 'Τίτλος εργασίας',
            'plural_label' => 'Τίτλοι εργασιών',
            'max_length' => 255,
        ],
        default => throw new InvalidArgumentException('Unknown catalog kind.'),
    };
}

function crm_catalog_normalized_name(string $name): string
{
    return mb_strtolower(trim($name), 'UTF-8');
}

function crm_catalog_find(mysqli $db, string $kind, int $id): ?array
{
    $config = crm_catalog_config($kind);
    $stmt = $db->prepare('SELECT id, name, normalized_name, is_active FROM ' . $config['table'] . ' WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function crm_catalog_save(mysqli $db, string $kind, array $input): array
{
    $config = crm_catalog_config($kind);
    $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
    $normalized = crm_catalog_normalized_name($name);
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    $active = ($id === null || isset($input['is_active'])) ? 1 : 0;

    if ($name === '') {
        return ['ok' => false, 'error' => 'Η ονομασία είναι υποχρεωτική.'];
    }
    if (mb_strlen($name, 'UTF-8') > $config['max_length']) {
        return ['ok' => false, 'error' => 'Η ονομασία υπερβαίνει το επιτρεπόμενο όριο χαρακτήρων.'];
    }

    $existingItem = $id === null ? null : crm_catalog_find($db, $kind, $id);
    if ($id !== null && $existingItem === null) {
        return ['ok' => false, 'error' => 'Η επιλογή δεν βρέθηκε.'];
    }

    $reactivateId = filter_var($input['reactivate_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    if ($reactivateId !== null) {
        $duplicate = crm_catalog_find($db, $kind, $reactivateId);
        if ($duplicate === null || (int)$duplicate['is_active'] === 1 || $duplicate['normalized_name'] !== $normalized) {
            return ['ok' => false, 'error' => 'Η ανενεργή επιλογή δεν μπορεί να ενεργοποιηθεί με αυτά τα στοιχεία.'];
        }
        $stmt = $db->prepare('UPDATE ' . $config['table'] . ' SET name = ?, normalized_name = ?, is_active = 1 WHERE id = ?');
        $stmt->bind_param('ssi', $name, $normalized, $reactivateId);
        try {
            $stmt->execute();
            return ['ok' => true, 'id' => $reactivateId];
        } catch (mysqli_sql_exception $exception) {
            if ($exception->getCode() === 1062) {
                return ['ok' => false, 'error' => 'Υπάρχει ήδη επιλογή με αυτή την ονομασία.'];
            }
            throw $exception;
        }
    }

    if ($id === null) {
        $duplicateStmt = $db->prepare(
            'SELECT id, is_active FROM ' . $config['table'] . ' WHERE normalized_name = ? LIMIT 1'
        );
        $duplicateStmt->bind_param('s', $normalized);
    } else {
        $duplicateStmt = $db->prepare(
            'SELECT id, is_active FROM ' . $config['table'] . ' WHERE normalized_name = ? AND id <> ? LIMIT 1'
        );
        $duplicateStmt->bind_param('si', $normalized, $id);
    }
    $duplicateStmt->execute();
    $duplicate = $duplicateStmt->get_result()->fetch_assoc();
    if ($duplicate !== null) {
        if ((int)$duplicate['is_active'] === 0) {
            return [
                'ok' => false,
                'error' => 'Υπάρχει ανενεργή επιλογή με αυτή την ονομασία. Μπορείτε να την επανενεργοποιήσετε.',
                'inactive_duplicate_id' => (int)$duplicate['id'],
            ];
        }
        return ['ok' => false, 'error' => 'Υπάρχει ήδη ενεργή επιλογή με αυτή την ονομασία.'];
    }

    try {
        if ($id === null) {
            $stmt = $db->prepare('INSERT INTO ' . $config['table'] . ' (name, normalized_name, is_active) VALUES (?, ?, ?)');
            $stmt->bind_param('ssi', $name, $normalized, $active);
        } else {
            $stmt = $db->prepare('UPDATE ' . $config['table'] . ' SET name = ?, normalized_name = ?, is_active = ? WHERE id = ?');
            $stmt->bind_param('ssii', $name, $normalized, $active, $id);
        }
        $stmt->execute();
        return ['ok' => true, 'id' => $id ?? (int)$stmt->insert_id];
    } catch (mysqli_sql_exception $exception) {
        if ($exception->getCode() === 1062) {
            return ['ok' => false, 'error' => 'Υπάρχει ήδη επιλογή με αυτή την ονομασία.'];
        }
        throw $exception;
    }
}

function crm_catalog_render_list(string $kind, array $items): void
{
    $config = crm_catalog_config($kind);
    ?>
    <div class="page-container">
        <h2><?= htmlspecialchars($config['plural_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
        <p><a class="btn btn-primary" href="<?= htmlspecialchars($config['form_route'], ENT_QUOTES, 'UTF-8') ?>">+ Νέα επιλογή</a></p>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Ονομασία</th><th>Κατάσταση</th><th>Ενέργειες</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$item['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td><?= (int)$item['is_active'] === 1 ? 'Ενεργή' : 'Ανενεργή' ?></td>
                        <td><a class="btn btn-secondary" href="<?= htmlspecialchars($config['form_route'], ENT_QUOTES, 'UTF-8') ?>?id=<?= (int)$item['id'] ?>">Επεξεργασία</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($items === []): ?><tr><td colspan="3">Δεν υπάρχουν επιλογές.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

function crm_catalog_render_form(string $kind, ?array $item, string $error, ?int $inactiveDuplicateId, string $csrfField): void
{
    $config = crm_catalog_config($kind);
    $active = $item === null || (int)($item['is_active'] ?? 1) === 1;
    ?>
    <div class="page-container">
        <h2><?= $item === null ? 'Νέα επιλογή' : 'Επεξεργασία επιλογής' ?>: <?= htmlspecialchars($config['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
        <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
        <form method="post" action="<?= htmlspecialchars($config['form_route'], ENT_QUOTES, 'UTF-8') ?>">
            <?= $csrfField ?>
            <?php if ($item !== null): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
            <label for="catalog-name">Ονομασία</label>
            <input id="catalog-name" name="name" type="text" maxlength="<?= (int)$config['max_length'] ?>" required value="<?= htmlspecialchars((string)($item['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <label><input type="checkbox" name="is_active" value="1" <?= $active ? 'checked' : '' ?>> Ενεργή</label>
            <button class="btn btn-primary" type="submit" name="save_catalog" value="1">Αποθήκευση</button>
        </form>
        <?php if ($inactiveDuplicateId !== null): ?>
            <form method="post" action="<?= htmlspecialchars($config['form_route'], ENT_QUOTES, 'UTF-8') ?>">
                <?= $csrfField ?>
                <input type="hidden" name="name" value="<?= htmlspecialchars((string)($item['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <input type="hidden" name="reactivate_id" value="<?= $inactiveDuplicateId ?>">
                <button class="btn btn-warning" type="submit" name="save_catalog" value="1">Επανενεργοποίηση υπάρχουσας επιλογής</button>
            </form>
        <?php endif; ?>
        <p><a href="<?= htmlspecialchars($config['plural_route'], ENT_QUOTES, 'UTF-8') ?>">Επιστροφή στη λίστα</a></p>
    </div>
    <?php
}
