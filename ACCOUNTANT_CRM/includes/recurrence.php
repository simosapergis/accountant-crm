<?php
declare(strict_types=1);

function crm_recurrence_today(?DateTimeImmutable $now = null): string
{
    $zone = new DateTimeZone('Europe/Athens');
    return ($now ?? new DateTimeImmutable('now', $zone))->setTimezone($zone)->format('Y-m-d');
}

function crm_recurrence_month_add(string $month, int $offset): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $month);
    if (!$date || $date->format('d') !== '01') {
        throw new InvalidArgumentException('Month must be the first day of a calendar month.');
    }
    return $date->modify(($offset >= 0 ? '+' : '') . $offset . ' months')->format('Y-m-01');
}

function crm_recurrence_due_date(string $month, int $day): string
{
    if ($day < 1 || $day > 31) {
        throw new InvalidArgumentException('Day of month must be between 1 and 31.');
    }
    $first = DateTimeImmutable::createFromFormat('!Y-m-d', $month);
    if (!$first || $first->format('d') !== '01') {
        throw new InvalidArgumentException('Month must be the first day of a calendar month.');
    }
    $lastDay = (int)$first->format('t');
    return $first->setDate((int)$first->format('Y'), (int)$first->format('m'), min($day, $lastDay))->format('Y-m-d');
}

function crm_recurrence_parse_month(mixed $value): ?string
{
    if (!is_string($value) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $value)) {
        return null;
    }
    return $value . '-01';
}

function crm_recurrence_fee_decimal(mixed $input): ?string
{
    if (!is_string($input) || !preg_match('/^(?:0|[1-9]\d{0,7})(?:[.,]\d{1,2})?$/D', trim($input))) {
        return null;
    }
    return number_format((float)str_replace(',', '.', trim($input)), 2, '.', '');
}

function crm_recurrence_create_rule(mysqli $db, int $taskId, int $day, string $startMonth, string $fee, ?int $createdBy, bool $manageTransaction = true): int
{
    if ($day < 1 || $day > 31 || crm_recurrence_parse_month(substr($startMonth, 0, 7)) !== $startMonth) {
        throw new InvalidArgumentException('Invalid recurrence schedule.');
    }
    $today = crm_recurrence_today();
    $minimumMonth = crm_recurrence_month_add(substr($today, 0, 7) . '-01', 1);
    if ($startMonth < $minimumMonth) {
        throw new InvalidArgumentException('Η έναρξη πρέπει να είναι από τον επόμενο μήνα και μετά.');
    }

    if ($manageTransaction) {
        $db->begin_transaction();
    }
    try {
        $taskStmt = $db->prepare('SELECT id,client_id,title,partner_id,notes FROM tasks WHERE id=? FOR UPDATE');
        $taskStmt->bind_param('i', $taskId);
        $taskStmt->execute();
        $task = $taskStmt->get_result()->fetch_assoc();
        if ($task === null) {
            throw new RuntimeException('Η εργασία δεν βρέθηκε.');
        }
        $activeStmt = $db->prepare("SELECT id FROM recurring_task_rules WHERE source_task_id=? AND state='active' LIMIT 1 FOR UPDATE");
        $activeStmt->bind_param('i', $taskId);
        $activeStmt->execute();
        if ($activeStmt->get_result()->fetch_assoc() !== null) {
            throw new RuntimeException('Υπάρχει ήδη ενεργή επανάληψη για αυτή την εργασία.');
        }

        $clientId = (int)$task['client_id'];
        $partnerId = $task['partner_id'] === null ? null : (int)$task['partner_id'];
        $title = (string)$task['title'];
        $notes = (string)($task['notes'] ?? '');
        $stmt = $db->prepare('INSERT INTO recurring_task_rules (source_task_id,client_id,title,partner_id,notes,day_of_month,start_month,fee,state,created_by) VALUES (?,?,?,?,?,?,?,? ,\'active\',?)');
        $stmt->bind_param('iisisissi', $taskId, $clientId, $title, $partnerId, $notes, $day, $startMonth, $fee, $createdBy);
        $stmt->execute();
        $ruleId = (int)$stmt->insert_id;
        $version = $db->prepare('INSERT INTO recurring_task_rule_versions (rule_id,effective_month,day_of_month,client_id,title,partner_id,notes,fee) VALUES (?,?,?,?,?,?,?,?)');
        $version->bind_param('isiisiss', $ruleId, $startMonth, $day, $clientId, $title, $partnerId, $notes, $fee);
        $version->execute();
        if ($manageTransaction) {
            $db->commit();
        }
        return $ruleId;
    } catch (Throwable $exception) {
        if ($manageTransaction) {
            $db->rollback();
        }
        throw $exception;
    }
}

function crm_recurrence_first_affected_month(mysqli $db, array $rule, int $newDay, string $today): string
{
    $startMonth = (string)$rule['start_month'];
    $currentMonth = substr($today, 0, 7) . '-01';
    if ($currentMonth < $startMonth) {
        return $startMonth;
    }

    $period = $currentMonth;
    $exists = $db->prepare('SELECT 1 FROM recurrence_occurrences WHERE rule_id=? AND period_month=? LIMIT 1');
    $ruleId = (int)$rule['id'];
    $exists->bind_param('is', $ruleId, $period);
    $exists->execute();
    if ($exists->get_result()->num_rows > 0) {
        return crm_recurrence_month_add($currentMonth, 1);
    }
    $oldDue = crm_recurrence_due_date($currentMonth, (int)$rule['day_of_month']);
    $newDue = crm_recurrence_due_date($currentMonth, $newDay);
    if ($oldDue > $today && $newDue > $today) {
        return $currentMonth;
    }
    return crm_recurrence_month_add($currentMonth, 1);
}

function crm_recurrence_update_rule(mysqli $db, int $ruleId, int $rowVersion, int $day, string $fee, string $today): string
{
    if ($day < 1 || $day > 31) {
        throw new InvalidArgumentException('Η ημέρα πρέπει να είναι από 1 έως 31.');
    }
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT * FROM recurring_task_rules WHERE id=? AND state='active' FOR UPDATE");
        $stmt->bind_param('i', $ruleId);
        $stmt->execute();
        $rule = $stmt->get_result()->fetch_assoc();
        if ($rule === null) {
            throw new RuntimeException('Ο ενεργός κανόνας δεν βρέθηκε.');
        }
        if ((int)$rule['row_version'] !== $rowVersion) {
            throw new RuntimeException('Οι ρυθμίσεις άλλαξαν σε άλλο παράθυρο. Ανανεώστε τη σελίδα.');
        }
        $effectiveMonth = crm_recurrence_first_affected_month($db, $rule, $day, $today);
        $clientId = (int)$rule['client_id'];
        $title = (string)$rule['title'];
        $partnerId = $rule['partner_id'] === null ? null : (int)$rule['partner_id'];
        $notes = (string)($rule['notes'] ?? '');
        $version = $db->prepare('INSERT INTO recurring_task_rule_versions (rule_id,effective_month,day_of_month,client_id,title,partner_id,notes,fee) VALUES (?,?,?,?,?,?,?,?)');
        $version->bind_param('isiisiss', $ruleId, $effectiveMonth, $day, $clientId, $title, $partnerId, $notes, $fee);
        $version->execute();
        $update = $db->prepare('UPDATE recurring_task_rules SET day_of_month=?,fee=?,row_version=row_version+1 WHERE id=? AND row_version=?');
        $update->bind_param('isii', $day, $fee, $ruleId, $rowVersion);
        $update->execute();
        if ($update->affected_rows !== 1) {
            throw new RuntimeException('Οι ρυθμίσεις άλλαξαν σε άλλο παράθυρο. Ανανεώστε τη σελίδα.');
        }
        $db->commit();
        return $effectiveMonth;
    } catch (Throwable $exception) {
        $db->rollback();
        throw $exception;
    }
}

function crm_recurrence_stop_rule(mysqli $db, int $ruleId, int $rowVersion, string $today): void
{
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT row_version,state FROM recurring_task_rules WHERE id=? FOR UPDATE");
        $stmt->bind_param('i', $ruleId);
        $stmt->execute();
        $rule = $stmt->get_result()->fetch_assoc();
        if ($rule === null || $rule['state'] !== 'active') {
            throw new RuntimeException('Ο ενεργός κανόνας δεν βρέθηκε.');
        }
        if ((int)$rule['row_version'] !== $rowVersion) {
            throw new RuntimeException('Οι ρυθμίσεις άλλαξαν σε άλλο παράθυρο. Ανανεώστε τη σελίδα.');
        }
        $update = $db->prepare("UPDATE recurring_task_rules SET state='stopped',stop_before_date=?,row_version=row_version+1 WHERE id=? AND row_version=?");
        $update->bind_param('sii', $today, $ruleId, $rowVersion);
        $update->execute();
        $db->commit();
    } catch (Throwable $exception) {
        $db->rollback();
        throw $exception;
    }
}

function crm_recurrence_restart_rule(mysqli $db, int $oldRuleId, string $startMonth, ?int $createdBy): int
{
    $minimumMonth = crm_recurrence_month_add(substr(crm_recurrence_today(), 0, 7) . '-01', 1);
    if (crm_recurrence_parse_month(substr($startMonth, 0, 7)) !== $startMonth || $startMonth < $minimumMonth) {
        throw new InvalidArgumentException('Η επανέναρξη πρέπει να είναι από τον επόμενο μήνα και μετά.');
    }
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT * FROM recurring_task_rules WHERE id=? AND state='stopped' FOR UPDATE");
        $stmt->bind_param('i', $oldRuleId);
        $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc();
        if ($old === null) {
            throw new RuntimeException('Ο σταματημένος κανόνας δεν βρέθηκε.');
        }
        $source = $old['source_task_id'] === null ? null : (int)$old['source_task_id'];
        $previous = $oldRuleId;
        $client = (int)$old['client_id'];
        $title = (string)$old['title'];
        $partner = $old['partner_id'] === null ? null : (int)$old['partner_id'];
        $notes = (string)($old['notes'] ?? '');
        $day = (int)$old['day_of_month'];
        $fee = (string)$old['fee'];
        $rule = $db->prepare('INSERT INTO recurring_task_rules (source_task_id,previous_rule_id,client_id,title,partner_id,notes,day_of_month,start_month,fee,state,created_by) VALUES (?,?,?,?,?,?,?,?,?,\'active\',?)');
        $rule->bind_param('iiisisissi', $source, $previous, $client, $title, $partner, $notes, $day, $startMonth, $fee, $createdBy);
        $rule->execute();
        $newId = (int)$rule->insert_id;
        $version = $db->prepare('INSERT INTO recurring_task_rule_versions (rule_id,effective_month,day_of_month,client_id,title,partner_id,notes,fee) VALUES (?,?,?,?,?,?,?,?)');
        $version->bind_param('isiisiss', $newId, $startMonth, $day, $client, $title, $partner, $notes, $fee);
        $version->execute();
        $db->commit();
        return $newId;
    } catch (Throwable $exception) {
        $db->rollback();
        throw $exception;
    }
}

function crm_recurrence_run(mysqli $db, DateTimeImmutable $asOf, int $batchLimit = 100, bool $dryRun = false, ?string $failAfterTask = null): array
{
    if ($batchLimit < 1) {
        throw new InvalidArgumentException('Batch limit must be positive.');
    }
    $asOf = $asOf->setTimezone(new DateTimeZone('Europe/Athens'));
    $today = $asOf->format('Y-m-d');
    $throughMonth = substr($today, 0, 7) . '-01';
    $result = ['created' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
    $runId = null;
    if (!$dryRun) {
        $db->query('INSERT INTO recurrence_run_log (dry_run) VALUES (0)');
        $runId = (int)$db->insert_id;
    }
    $rules = $db->query("SELECT id,start_month,stop_before_date FROM recurring_task_rules WHERE state IN ('active','stopped') ORDER BY id");
    while ($rule = $rules->fetch_assoc()) {
        $period = (string)$rule['start_month'];
        while ($period <= $throughMonth) {
            try {
                $ruleId = (int)$rule['id'];
                if ($dryRun) {
                    $versionStmt = $db->prepare('SELECT id,day_of_month FROM recurring_task_rule_versions WHERE rule_id=? AND effective_month<=? ORDER BY effective_month DESC,id DESC LIMIT 1');
                    $versionStmt->bind_param('is', $ruleId, $period);
                    $versionStmt->execute();
                    $version = $versionStmt->get_result()->fetch_assoc();
                    if ($version === null) {
                        $result['failed']++;
                        $result['errors'][] = "rule {$ruleId}: missing version";
                        break;
                    }
                    $dueDate = crm_recurrence_due_date($period, (int)$version['day_of_month']);
                    if ($dueDate > $today || ($rule['stop_before_date'] !== null && $dueDate >= (string)$rule['stop_before_date'])) break;
                    $exists = $db->prepare('SELECT id FROM recurrence_occurrences WHERE rule_id=? AND period_month=? LIMIT 1');
                    $exists->bind_param('is', $ruleId, $period);
                    $exists->execute();
                    if ($exists->get_result()->fetch_assoc() !== null) {
                        $result['skipped']++;
                        $period = crm_recurrence_month_add($period, 1);
                        continue;
                    }
                    $result['created']++;
                    if ($result['created'] >= $batchLimit) break 2;
                    $period = crm_recurrence_month_add($period, 1);
                    continue;
                }
                if ($result['created'] >= $batchLimit) {
                    break 2;
                }

                $db->begin_transaction();
                $lock = $db->prepare('SELECT state,stop_before_date FROM recurring_task_rules WHERE id=? FOR UPDATE');
                $lock->bind_param('i', $ruleId);
                $lock->execute();
                $locked = $lock->get_result()->fetch_assoc();
                if ($locked === null) {
                    $db->commit();
                    $result['skipped']++;
                    break;
                }
                $versionStmt = $db->prepare('SELECT id,day_of_month,client_id,title,partner_id,notes,fee FROM recurring_task_rule_versions WHERE rule_id=? AND effective_month<=? ORDER BY effective_month DESC,id DESC LIMIT 1');
                $versionStmt->bind_param('is', $ruleId, $period);
                $versionStmt->execute();
                $version = $versionStmt->get_result()->fetch_assoc();
                if ($version === null) {
                    $db->commit();
                    $result['failed']++;
                    $result['errors'][] = "rule {$ruleId}: missing version";
                    break;
                }
                $dueDate = crm_recurrence_due_date($period, (int)$version['day_of_month']);
                if ($dueDate > $today || ($locked['stop_before_date'] !== null && $dueDate >= (string)$locked['stop_before_date'])) {
                    $db->commit();
                    break;
                }
                $exists = $db->prepare('SELECT id FROM recurrence_occurrences WHERE rule_id=? AND period_month=? LIMIT 1');
                $exists->bind_param('is', $ruleId, $period);
                $exists->execute();
                if ($exists->get_result()->fetch_assoc() !== null) {
                    $db->commit();
                    $result['skipped']++;
                    $period = crm_recurrence_month_add($period, 1);
                    continue;
                }
                $client = (int)$version['client_id'];
                $title = (string)$version['title'];
                $partner = $version['partner_id'] === null ? null : (int)$version['partner_id'];
                $status = 'Σε εξέλιξη';
                $fee = (string)$version['fee'];
                $collected = '0.00';
                $notes = (string)($version['notes'] ?? '');
                $task = $db->prepare('INSERT INTO tasks (task_date,title,client_id,partner_id,status,fee,collected,payment_method_id,payment_method_label,notes) VALUES (?,?,?,?,?,?,?,NULL,NULL,?)');
                $task->bind_param('ssiissss', $dueDate, $title, $client, $partner, $status, $fee, $collected, $notes);
                $task->execute();
                $taskId = (int)$task->insert_id;
                if ($failAfterTask === $ruleId . ':' . $period) {
                    throw new RuntimeException('Injected transactional scheduler failure.');
                }
                $occurrence = $db->prepare('INSERT INTO recurrence_occurrences (rule_id,period_month,due_date,version_id,task_id) VALUES (?,?,?,?,?)');
                $versionId = (int)$version['id'];
                $occurrence->bind_param('issii', $ruleId, $period, $dueDate, $versionId, $taskId);
                $occurrence->execute();
                $db->commit();
                $result['created']++;
            } catch (Throwable $exception) {
                try { $db->rollback(); } catch (Throwable) { }
                $result['failed']++;
                $result['errors'][] = 'rule ' . (int)$rule['id'] . ' period ' . $period . ': ' . substr($exception->getMessage(), 0, 180);
            }
            $period = crm_recurrence_month_add($period, 1);
        }
    }
    if ($runId !== null) {
        $summary = implode("\n", $result['errors']);
        $update = $db->prepare('UPDATE recurrence_run_log SET completed_at=CURRENT_TIMESTAMP,created_count=?,skipped_count=?,failed_count=?,summary=? WHERE id=?');
        $update->bind_param('iiisi', $result['created'], $result['skipped'], $result['failed'], $summary, $runId);
        $update->execute();
    }
    return $result;
}
