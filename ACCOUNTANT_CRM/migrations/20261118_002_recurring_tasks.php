<?php
declare(strict_types=1);

return static function (mysqli $db): void {
    $db->query(
        'CREATE TABLE IF NOT EXISTS recurring_task_rules (' .
        'id INT NOT NULL AUTO_INCREMENT, source_task_id INT NULL, state ENUM(\'active\',\'stopped\') NOT NULL DEFAULT \'active\', ' .
        'active_source_task_id INT GENERATED ALWAYS AS (CASE WHEN state = \'active\' THEN source_task_id ELSE NULL END) PERSISTENT, ' .
        'previous_rule_id INT NULL, client_id INT NOT NULL, title VARCHAR(255) NOT NULL, partner_id INT NULL, notes TEXT NULL, ' .
        'day_of_month TINYINT UNSIGNED NOT NULL, start_month DATE NOT NULL, fee DECIMAL(10,2) NOT NULL, ' .
        'stop_before_date DATE NULL, row_version INT UNSIGNED NOT NULL DEFAULT 1, ' .
        'created_by INT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ' .
        'PRIMARY KEY (id), UNIQUE KEY uq_recurring_active_source (active_source_task_id), KEY idx_recurring_state_month (state,start_month), KEY idx_recurring_previous (previous_rule_id), ' .
        'CONSTRAINT fk_recurring_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT, ' .
        'CONSTRAINT fk_recurring_partner FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE RESTRICT, ' .
        'CONSTRAINT fk_recurring_previous FOREIGN KEY (previous_rule_id) REFERENCES recurring_task_rules(id) ON DELETE SET NULL, ' .
        'CONSTRAINT chk_recurring_day CHECK (day_of_month BETWEEN 1 AND 31)' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $db->query(
        'CREATE TABLE IF NOT EXISTS recurring_task_rule_versions (' .
        'id INT NOT NULL AUTO_INCREMENT, rule_id INT NOT NULL, effective_month DATE NOT NULL, day_of_month TINYINT UNSIGNED NOT NULL, ' .
        'client_id INT NOT NULL, title VARCHAR(255) NOT NULL, partner_id INT NULL, notes TEXT NULL, fee DECIMAL(10,2) NOT NULL, ' .
        'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_recurrence_version_lookup (rule_id,effective_month,id), ' .
        'CONSTRAINT fk_recurring_version_rule FOREIGN KEY (rule_id) REFERENCES recurring_task_rules(id) ON DELETE RESTRICT, ' .
        'CONSTRAINT fk_recurring_version_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT, ' .
        'CONSTRAINT fk_recurring_version_partner FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE RESTRICT, ' .
        'CONSTRAINT chk_recurring_version_day CHECK (day_of_month BETWEEN 1 AND 31)' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $db->query(
        'CREATE TABLE IF NOT EXISTS recurrence_occurrences (' .
        'id BIGINT NOT NULL AUTO_INCREMENT, rule_id INT NOT NULL, period_month DATE NOT NULL, due_date DATE NOT NULL, version_id INT NOT NULL, task_id INT NULL, ' .
        'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_recurrence_rule_period (rule_id,period_month), KEY idx_recurrence_task (task_id), ' .
        'CONSTRAINT fk_recurrence_occurrence_rule FOREIGN KEY (rule_id) REFERENCES recurring_task_rules(id) ON DELETE RESTRICT, ' .
        'CONSTRAINT fk_recurrence_occurrence_version FOREIGN KEY (version_id) REFERENCES recurring_task_rule_versions(id) ON DELETE RESTRICT, ' .
        'CONSTRAINT fk_recurrence_occurrence_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $db->query(
        'CREATE TABLE IF NOT EXISTS recurrence_run_log (' .
        'id BIGINT NOT NULL AUTO_INCREMENT, started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, completed_at TIMESTAMP NULL, ' .
        'dry_run TINYINT(1) NOT NULL DEFAULT 0, created_count INT UNSIGNED NOT NULL DEFAULT 0, skipped_count INT UNSIGNED NOT NULL DEFAULT 0, failed_count INT UNSIGNED NOT NULL DEFAULT 0, summary TEXT NULL, ' .
        'PRIMARY KEY (id), KEY idx_recurrence_run_started (started_at)' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );
};
