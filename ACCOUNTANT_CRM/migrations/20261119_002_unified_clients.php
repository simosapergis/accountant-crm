<?php
declare(strict_types=1);

return static function (mysqli $db): void {
    $columnExists = static function (string $column) use ($db): bool {
        $stmt = $db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $table = 'clients';
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    };
    $tableExists = static function (string $table) use ($db): bool {
        $stmt = $db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND TABLE_TYPE = ?'
        );
        $tableType = 'BASE TABLE';
        $stmt->bind_param('ss', $table, $tableType);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    };

    $columns = [
        'has_professional_profile' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'professional_code' => 'VARCHAR(50) DEFAULT NULL',
        'professional_is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'professional_start_date' => 'DATE DEFAULT NULL',
        'professional_end_date' => 'DATE DEFAULT NULL',
        'professional_has_vat' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'professional_vat_type' => "ENUM('Μηνιαίο','Τριμηνιαίο') DEFAULT NULL",
        'professional_has_payroll' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'legacy_professional_id' => 'INT(11) DEFAULT NULL',
    ];
    foreach ($columns as $name => $definition) {
        if (!$columnExists($name)) {
            $db->query("ALTER TABLE `clients` ADD COLUMN `$name` $definition");
        }
    }

    $indexes = [];
    $indexResult = $db->query("SHOW INDEX FROM `clients`");
    while ($index = $indexResult->fetch_assoc()) {
        $indexes[(string)$index['Key_name']] = true;
    }
    if (!isset($indexes['uq_clients_professional_code'])) {
        $db->query('ALTER TABLE `clients` ADD UNIQUE KEY `uq_clients_professional_code` (`professional_code`)');
    }
    if (!isset($indexes['uq_clients_legacy_professional_id'])) {
        $db->query('ALTER TABLE `clients` ADD UNIQUE KEY `uq_clients_legacy_professional_id` (`legacy_professional_id`)');
    }

    $sourceTable = $tableExists('active_professionals')
        ? 'active_professionals'
        : ($tableExists('active_professionals_legacy') ? 'active_professionals_legacy' : null);

    if ($sourceTable !== null) {
        $db->query(
            "INSERT INTO `clients` (
                `name`, `category`, `has_professional_profile`, `professional_code`,
                `professional_is_active`, `professional_start_date`, `professional_end_date`,
                `professional_has_vat`, `professional_vat_type`, `professional_has_payroll`,
                `legacy_professional_id`
            )
            SELECT p.`fullname`, 'Επιχείρηση', 1, p.`code`, p.`is_active`, p.`start_date`, p.`end_date`,
                   p.`has_vat`, p.`vat_type`, p.`has_payroll`, p.`id`
            FROM `$sourceTable` p
            LEFT JOIN `clients` existing ON existing.`legacy_professional_id` = p.`id`
            WHERE existing.`id` IS NULL"
        );
    }

    if ($tableExists('active_professionals') && !$tableExists('active_professionals_legacy')) {
        $db->query('RENAME TABLE `active_professionals` TO `active_professionals_legacy`');
    }
};
