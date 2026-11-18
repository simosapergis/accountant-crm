<?php
declare(strict_types=1);

return static function (mysqli $db): void {
    $db->query(
        'CREATE TABLE IF NOT EXISTS payment_methods (' .
        'id INT NOT NULL AUTO_INCREMENT, ' .
        'name VARCHAR(100) NOT NULL, ' .
        'normalized_name VARCHAR(100) NOT NULL, ' .
        'is_active TINYINT(1) NOT NULL DEFAULT 1, ' .
        'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
        'updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ' .
        'PRIMARY KEY (id), UNIQUE KEY uq_payment_methods_normalized_name (normalized_name)' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $db->query(
        'CREATE TABLE IF NOT EXISTS task_title_templates (' .
        'id INT NOT NULL AUTO_INCREMENT, ' .
        'name VARCHAR(255) NOT NULL, ' .
        'normalized_name VARCHAR(255) NOT NULL, ' .
        'is_active TINYINT(1) NOT NULL DEFAULT 1, ' .
        'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
        'updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ' .
        'PRIMARY KEY (id), UNIQUE KEY uq_task_title_templates_normalized_name (normalized_name)' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $hasColumn = static function (string $table, string $column) use ($db): bool {
        $stmt = $db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS ' .
            'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        return $stmt->get_result()->num_rows === 1;
    };

    if (!$hasColumn('tasks', 'payment_method_id')) {
        $db->query('ALTER TABLE tasks ADD COLUMN payment_method_id INT NULL DEFAULT NULL AFTER collected');
    }
    if (!$hasColumn('tasks', 'payment_method_label')) {
        $db->query('ALTER TABLE tasks ADD COLUMN payment_method_label VARCHAR(100) NULL DEFAULT NULL AFTER payment_method_id');
    }

    $hasIndex = $db->prepare(
        'SELECT 1 FROM information_schema.STATISTICS ' .
        'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
    );
    $table = 'tasks';
    $index = 'idx_tasks_payment_method_id';
    $hasIndex->bind_param('ss', $table, $index);
    $hasIndex->execute();
    if ($hasIndex->get_result()->num_rows === 0) {
        $db->query('ALTER TABLE tasks ADD KEY idx_tasks_payment_method_id (payment_method_id)');
    }
};
