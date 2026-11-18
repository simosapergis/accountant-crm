<?php
declare(strict_types=1);

require_once CRM_TEST_APP_ROOT . '/includes/recurrence.php';

use PHPUnit\Framework\TestCase;

final class W06RecurringTasksTest extends TestCase
{
    private mysqli $db;
    private array $ruleIds = [];
    private array $taskIds = [];
    private const CLIENT_ID = 910030;
    private const SOURCE_TASK_ID = 910030;
    private const ADMIN_ID = 910034;
    private const ADMIN_USERNAME = 'w06-fictional-admin';
    private const ADMIN_PASSWORD = 'W06-Fictional-Admin-Password';

    protected function setUp(): void
    {
        $this->db = new mysqli((string)getenv('DB_HOST'), (string)getenv('DB_USER'), (string)getenv('DB_PASSWORD'), (string)getenv('DB_NAME'));
        self::assertSame(CRM_TEST_DATABASE, $this->db->query('SELECT DATABASE()')->fetch_row()[0]);
        $id = self::CLIENT_ID;
        $name = 'W06 Fictional Recurrence Client';
        $stmt = $this->db->prepare("INSERT INTO clients (id,name,category) VALUES (?,?,'Ιδιώτης') ON DUPLICATE KEY UPDATE name=VALUES(name)");
        $stmt->bind_param('is', $id, $name);
        $stmt->execute();
        $taskId = self::SOURCE_TASK_ID;
        $date = '2026-11-18'; $title = 'W06 Monthly Filing'; $status = 'Σε εξέλιξη'; $fee = '100.00'; $collected = '0.00';
        $task = $this->db->prepare('INSERT INTO tasks (id,task_date,title,client_id,status,fee,collected) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE task_date=VALUES(task_date),title=VALUES(title),client_id=VALUES(client_id),status=VALUES(status),fee=VALUES(fee),collected=VALUES(collected)');
        $task->bind_param('ississs', $taskId, $date, $title, $id, $status, $fee, $collected);
        $task->execute();

        $adminId = self::ADMIN_ID;
        $adminUsername = self::ADMIN_USERNAME;
        $adminPassword = password_hash(self::ADMIN_PASSWORD, PASSWORD_DEFAULT);
        $adminFullname = 'W06 Fictional Administrator';
        $adminRole = 'admin';
        $admin = $this->db->prepare(
            'INSERT INTO users (id,username,password,fullname,role) VALUES (?,?,?,?,?) ' .
            'ON DUPLICATE KEY UPDATE password=VALUES(password),fullname=VALUES(fullname),role=VALUES(role)'
        );
        $admin->bind_param('issss', $adminId, $adminUsername, $adminPassword, $adminFullname, $adminRole);
        $admin->execute();
    }

    protected function tearDown(): void
    {
        if (!isset($this->db) || $this->db->connect_errno !== 0 || $this->db->query('SELECT DATABASE()')->fetch_row()[0] !== CRM_TEST_DATABASE) return;
        foreach ($this->ruleIds as $id) {
            $this->db->query('DELETE FROM recurrence_occurrences WHERE rule_id=' . (int)$id);
            $this->db->query('DELETE FROM recurring_task_rule_versions WHERE rule_id=' . (int)$id);
            $this->db->query('DELETE FROM recurring_task_rules WHERE id=' . (int)$id);
        }
        foreach ($this->taskIds as $id) $this->db->query('DELETE FROM tasks WHERE id=' . (int)$id);
        $this->db->query("DELETE FROM tasks WHERE client_id=910030 AND title LIKE 'W06 %'");
        $this->db->query('DELETE FROM tasks WHERE id=910030');
        $this->db->query('DELETE FROM clients WHERE id=910030');
        $this->db->query('DELETE FROM users WHERE id=' . self::ADMIN_ID . ' AND username="' . self::ADMIN_USERNAME . '"');
        $this->db->close();
    }

    public function testSchedulerCreatesClampedIndependentTasksAndIsIdempotent(): void
    {
        $ruleId = $this->insertRule('2027-01-01', 31, '100.00');
        $first = crm_recurrence_run($this->db, new DateTimeImmutable('2027-03-31 12:00:00', new DateTimeZone('Europe/Athens')));
        self::assertSame(3, $first['created']);
        $rows = $this->db->query('SELECT t.task_date,t.status,t.fee,t.collected,t.payment_method_id,t.payment_method_label,o.period_month FROM recurrence_occurrences o JOIN tasks t ON t.id=o.task_id WHERE o.rule_id=' . $ruleId . ' ORDER BY o.period_month')->fetch_all(MYSQLI_ASSOC);
        self::assertSame(['2027-01-31', '2027-02-28', '2027-03-31'], array_column($rows, 'task_date'));
        foreach ($rows as $row) {
            self::assertSame('Σε εξέλιξη', $row['status']);
            self::assertSame('100.00', $row['fee']);
            self::assertSame('0.00', $row['collected']);
            self::assertNull($row['payment_method_id']);
            self::assertNull($row['payment_method_label']);
        }
        $second = crm_recurrence_run($this->db, new DateTimeImmutable('2027-03-31 13:00:00', new DateTimeZone('Europe/Athens')));
        self::assertSame(0, $second['created']);
        self::assertSame(3, $second['skipped']);
        self::assertSame(3, (int)$this->db->query('SELECT COUNT(*) FROM recurrence_occurrences WHERE rule_id=' . $ruleId)->fetch_row()[0]);
    }

    public function testDifferentRulesBecomeDueOnTheirOwnDays(): void
    {
        $dayOne = $this->insertRule('2027-03-01', 1, '10.00', 910041, 'W06 Day One');
        $dayFifteen = $this->insertRule('2027-03-01', 15, '15.00', 910042, 'W06 Day Fifteen');
        $dayThirty = $this->insertRule('2027-03-01', 30, '30.00', 910043, 'W06 Day Thirty');
        $beforeLaterDueDates = crm_recurrence_run($this->db, new DateTimeImmutable('2027-03-14', new DateTimeZone('Europe/Athens')));
        self::assertSame(1, $beforeLaterDueDates['created']);
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM recurrence_occurrences WHERE rule_id=' . $dayOne)->fetch_row()[0]);
        self::assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM recurrence_occurrences WHERE rule_id IN (' . $dayFifteen . ',' . $dayThirty . ')')->fetch_row()[0]);

        $afterAllDueDates = crm_recurrence_run($this->db, new DateTimeImmutable('2027-03-31', new DateTimeZone('Europe/Athens')));
        self::assertSame(2, $afterAllDueDates['created']);
        self::assertSame('2027-03-15', $this->db->query('SELECT due_date FROM recurrence_occurrences WHERE rule_id=' . $dayFifteen)->fetch_row()[0]);
        self::assertSame('2027-03-30', $this->db->query('SELECT due_date FROM recurrence_occurrences WHERE rule_id=' . $dayThirty)->fetch_row()[0]);
    }

    public function testFailureBetweenTaskAndOccurrenceRollsBackAndCanRetry(): void
    {
        $ruleId = $this->insertRule('2027-03-01', 15, '75.00');
        $otherRuleId = $this->insertRule('2027-03-01', 15, '60.00', 910031, 'W06 Other Rule');
        $process = proc_open([PHP_BINARY, CRM_TEST_APP_ROOT . '/bin/run-recurring-tasks.php', '--as-of=2027-03-31', '--fail-after-task=' . $ruleId . ':2027-03-01'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(1, proc_close($process), $out . $err);
        self::assertStringContainsString('failed=1', $out);
        self::assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM tasks WHERE title="W06 Monthly Filing" AND task_date="2027-03-15"')->fetch_row()[0]);
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM tasks WHERE title="W06 Other Rule" AND task_date="2027-03-15"')->fetch_row()[0]);
        self::assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM recurrence_occurrences WHERE rule_id=' . $ruleId)->fetch_row()[0]);
        $retried = crm_recurrence_run($this->db, new DateTimeImmutable('2027-03-31', new DateTimeZone('Europe/Athens')));
        self::assertSame(1, $retried['created']);
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM recurrence_occurrences WHERE rule_id=' . $otherRuleId)->fetch_row()[0]);
    }

    public function testLateSettingsChangeTakesEffectNextMonthAndPreservesDuePeriod(): void
    {
        $ruleId = $this->insertRule('2027-01-01', 15, '100.00');
        $rule = $this->db->query('SELECT * FROM recurring_task_rules WHERE id=' . $ruleId)->fetch_assoc();
        $effective = crm_recurrence_update_rule($this->db, $ruleId, (int)$rule['row_version'], 15, '120.00', '2027-03-20');
        self::assertSame('2027-04-01', $effective);
        $result = crm_recurrence_run($this->db, new DateTimeImmutable('2027-04-30', new DateTimeZone('Europe/Athens')));
        self::assertSame(4, $result['created']);
        $marchApril = $this->db->query('SELECT t.task_date,t.fee FROM recurrence_occurrences o JOIN tasks t ON t.id=o.task_id WHERE o.rule_id=' . $ruleId . ' AND o.period_month >= "2027-03-01" ORDER BY o.period_month')->fetch_all(MYSQLI_ASSOC);
        self::assertSame([['task_date' => '2027-03-15', 'fee' => '100.00'], ['task_date' => '2027-04-15', 'fee' => '120.00']], $marchApril);
    }

    public function testMultipleHistoricalVersionsAreUsedDuringBatchedCatchUp(): void
    {
        $ruleId = $this->insertRule('2027-01-01', 30, '100.00');
        $rule = $this->db->query('SELECT * FROM recurring_task_rules WHERE id=' . $ruleId)->fetch_assoc();
        self::assertSame('2027-04-01', crm_recurrence_update_rule($this->db, $ruleId, (int)$rule['row_version'], 1, '100.00', '2027-03-10'));
        $rule = $this->db->query('SELECT * FROM recurring_task_rules WHERE id=' . $ruleId)->fetch_assoc();
        self::assertSame('2027-05-01', crm_recurrence_update_rule($this->db, $ruleId, (int)$rule['row_version'], 15, '120.00', '2027-04-10'));

        $created = 0;
        for ($run = 0; $run < 3; $run++) {
            $created += crm_recurrence_run($this->db, new DateTimeImmutable('2027-06-30', new DateTimeZone('Europe/Athens')), 2)['created'];
        }
        self::assertSame(6, $created);
        $rows = $this->db->query('SELECT period_month,due_date,fee FROM recurrence_occurrences o JOIN tasks t ON t.id=o.task_id WHERE o.rule_id=' . $ruleId . ' ORDER BY period_month')->fetch_all(MYSQLI_ASSOC);
        self::assertSame([
            ['period_month' => '2027-01-01', 'due_date' => '2027-01-30', 'fee' => '100.00'],
            ['period_month' => '2027-02-01', 'due_date' => '2027-02-28', 'fee' => '100.00'],
            ['period_month' => '2027-03-01', 'due_date' => '2027-03-30', 'fee' => '100.00'],
            ['period_month' => '2027-04-01', 'due_date' => '2027-04-01', 'fee' => '100.00'],
            ['period_month' => '2027-05-01', 'due_date' => '2027-05-15', 'fee' => '120.00'],
            ['period_month' => '2027-06-01', 'due_date' => '2027-06-15', 'fee' => '120.00'],
        ], $rows);
    }

    public function testStoppingKeepsPastDuePeriodsAndExcludesFutureDueDates(): void
    {
        $ruleId = $this->insertRule('2027-01-01', 15, '50.00');
        $dayThirtyRuleId = $this->insertRule('2027-01-01', 30, '60.00', 910044, 'W06 Day Thirty Stop');
        crm_recurrence_stop_rule($this->db, $ruleId, 1, '2027-03-20');
        crm_recurrence_stop_rule($this->db, $dayThirtyRuleId, 1, '2027-03-20');
        $result = crm_recurrence_run($this->db, new DateTimeImmutable('2027-04-30', new DateTimeZone('Europe/Athens')));
        self::assertSame(5, $result['created']);
        $periods = $this->db->query('SELECT period_month FROM recurrence_occurrences WHERE rule_id=' . $ruleId . ' ORDER BY period_month')->fetch_all(MYSQLI_NUM);
        self::assertSame([['2027-01-01'], ['2027-02-01'], ['2027-03-01']], $periods);
        self::assertSame([['2027-01-01'], ['2027-02-01']], $this->db->query('SELECT period_month FROM recurrence_occurrences WHERE rule_id=' . $dayThirtyRuleId . ' ORDER BY period_month')->fetch_all(MYSQLI_NUM));
    }

    public function testDeletingGeneratedTaskRetainsOccurrenceAndDoesNotRecreateIt(): void
    {
        $ruleId = $this->insertRule('2027-03-01', 10, '80.00');
        crm_recurrence_run($this->db, new DateTimeImmutable('2027-03-31', new DateTimeZone('Europe/Athens')));
        $taskId = (int)$this->db->query('SELECT task_id FROM recurrence_occurrences WHERE rule_id=' . $ruleId)->fetch_row()[0];
        $rule = $this->db->query('SELECT * FROM recurring_task_rules WHERE id=' . $ruleId)->fetch_assoc();
        self::assertSame('2027-04-01', crm_recurrence_update_rule($this->db, $ruleId, (int)$rule['row_version'], 10, '90.00', '2027-03-31'));
        $this->db->query('DELETE FROM tasks WHERE id=' . $taskId);
        $result = crm_recurrence_run($this->db, new DateTimeImmutable('2027-03-31', new DateTimeZone('Europe/Athens')));

        self::assertSame(0, $result['created']);
        self::assertSame(1, $result['skipped']);
        self::assertNull($this->db->query('SELECT task_id FROM recurrence_occurrences WHERE rule_id=' . $ruleId)->fetch_row()[0]);
        self::assertSame(1, crm_recurrence_run($this->db, new DateTimeImmutable('2027-04-30', new DateTimeZone('Europe/Athens')))['created']);
    }

    public function testDeletingSourceTaskDoesNotDeleteRuleOrItsFutureWork(): void
    {
        $ruleId = $this->insertRule('2027-03-01', 20, '80.00');
        $this->db->query('DELETE FROM tasks WHERE id=910030');
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM recurring_task_rules WHERE id=' . $ruleId)->fetch_row()[0]);
        $result = crm_recurrence_run($this->db, new DateTimeImmutable('2027-03-31', new DateTimeZone('Europe/Athens')));
        self::assertSame(1, $result['created']);
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM recurrence_occurrences WHERE rule_id=' . $ruleId)->fetch_row()[0]);
    }

    public function testUserCanActivateMonthlyRuleOnlyFromNextMonthAndDuplicatePostIsIdempotent(): void
    {
        $session = $this->login();
        $form = $this->request('/recurring_task_form.php?task_id=910030', 'GET', [], $session['cookie']);
        self::assertSame(200, $form['status']);
        $nextMonth = crm_recurrence_month_add(substr(crm_recurrence_today(), 0, 7) . '-01', 1);
        self::assertStringContainsString('min="' . substr($nextMonth, 0, 7) . '"', $form['body']);
        $token = $this->csrfToken($form['body']);
        $payload = [
            'day_of_month' => '15', 'start_month' => substr($nextMonth, 0, 7), 'fee' => '100,50',
            'save_new_rule' => '1', '_csrf_token' => $token,
        ];
        self::assertSame(302, $this->request('/recurring_task_form.php?task_id=910030', 'POST', $payload, $session['cookie'])['status']);
        $rule = $this->db->query('SELECT id,fee FROM recurring_task_rules WHERE source_task_id=910030 AND state="active"')->fetch_assoc();
        $this->ruleIds[] = (int)$rule['id'];
        self::assertSame('100.50', $rule['fee']);

        self::assertSame(302, $this->request('/recurring_task_form.php?task_id=910030', 'POST', $payload, $session['cookie'])['status']);
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM recurring_task_rules WHERE source_task_id=910030 AND state="active"')->fetch_row()[0]);
    }

    public function testAdminCanEditSharedTaskAndCreateMonthlyRule(): void
    {
        $session = $this->login(self::ADMIN_USERNAME, self::ADMIN_PASSWORD);
        $taskForm = $this->request('/task_form.php?id=910030', 'GET', [], $session['cookie']);
        self::assertSame(200, $taskForm['status']);
        $taskSaved = $this->request('/task_form.php?id=910030', 'POST', [
            'id' => '910030', 'task_date' => '2026-11-18', 'title' => 'W06 Admin Edited Shared Task',
            'client_id' => (string)self::CLIENT_ID, 'partner_id' => '', 'status' => 'Σε εξέλιξη',
            'fee' => '100.00', 'collected' => '0', 'payment_method_id' => '', 'notes' => 'fictional admin role regression',
            'save_task' => '1', '_csrf_token' => $this->csrfToken($taskForm['body']),
        ], $session['cookie']);
        self::assertSame(302, $taskSaved['status']);
        self::assertSame('W06 Admin Edited Shared Task', $this->db->query('SELECT title FROM tasks WHERE id=910030')->fetch_row()[0]);

        $ruleForm = $this->request('/recurring_task_form.php?task_id=910030', 'GET', [], $session['cookie']);
        self::assertSame(200, $ruleForm['status']);
        $nextMonth = crm_recurrence_month_add(substr(crm_recurrence_today(), 0, 7) . '-01', 1);
        $ruleSaved = $this->request('/recurring_task_form.php?task_id=910030', 'POST', [
            'day_of_month' => '15', 'start_month' => substr($nextMonth, 0, 7), 'fee' => '101,25',
            'save_new_rule' => '1', '_csrf_token' => $this->csrfToken($ruleForm['body']),
        ], $session['cookie']);
        self::assertSame(302, $ruleSaved['status']);
        $rule = $this->db->query('SELECT id,fee FROM recurring_task_rules WHERE source_task_id=910030 AND state="active"')->fetch_assoc();
        self::assertNotNull($rule);
        $this->ruleIds[] = (int)$rule['id'];
        self::assertSame('101.25', $rule['fee']);
    }

    public function testNewTaskCanStartMonthlyRecurrenceInTheSameSubmission(): void
    {
        $session = $this->login();
        $form = $this->request('/task_form.php', 'GET', [], $session['cookie']);
        self::assertSame(200, $form['status']);
        self::assertStringContainsString('name="enable_recurrence"', $form['body']);

        $nextMonth = crm_recurrence_month_add(substr(crm_recurrence_today(), 0, 7) . '-01', 1);
        $payload = [
            'task_date' => '2026-11-18', 'title' => 'W06 Initial Recurrence Task',
            'client_id' => (string)self::CLIENT_ID, 'partner_id' => '', 'status' => 'Σε εξέλιξη',
            'fee' => '125,50', 'collected' => '0', 'payment_method_id' => '', 'notes' => 'Fictional initial recurring task',
            'enable_recurrence' => '1', 'recurrence_day' => '12',
            'recurrence_start_month' => substr($nextMonth, 0, 7), 'recurrence_fee' => '130,75',
            'save_task' => '1', '_csrf_token' => $this->csrfToken($form['body']),
        ];
        $saved = $this->request('/task_form.php', 'POST', $payload, $session['cookie']);
        self::assertSame(302, $saved['status']);

        $task = $this->db->query("SELECT id FROM tasks WHERE title='W06 Initial Recurrence Task' AND client_id=" . self::CLIENT_ID)->fetch_assoc();
        self::assertNotNull($task);
        $taskId = (int)$task['id'];
        $this->taskIds[] = $taskId;
        $rule = $this->db->query('SELECT id,day_of_month,start_month,fee FROM recurring_task_rules WHERE source_task_id=' . $taskId . ' AND state="active"')->fetch_assoc();
        self::assertNotNull($rule);
        $this->ruleIds[] = (int)$rule['id'];
        self::assertSame('12', (string)$rule['day_of_month']);
        self::assertSame($nextMonth, $rule['start_month']);
        self::assertSame('130.75', $rule['fee']);
    }

    public function testInvalidInitialRecurrenceDoesNotLeaveTheNewTaskBehind(): void
    {
        $session = $this->login();
        $form = $this->request('/task_form.php', 'GET', [], $session['cookie']);
        $payload = [
            'task_date' => '2026-11-18', 'title' => 'W06 Invalid Initial Recurrence',
            'client_id' => (string)self::CLIENT_ID, 'partner_id' => '', 'status' => 'Σε εξέλιξη',
            'fee' => '125,50', 'collected' => '0', 'payment_method_id' => '', 'notes' => '',
            'enable_recurrence' => '1', 'recurrence_day' => '12',
            'recurrence_start_month' => substr(crm_recurrence_today(), 0, 7), 'recurrence_fee' => '130,75',
            'save_task' => '1', '_csrf_token' => $this->csrfToken($form['body']),
        ];
        $response = $this->request('/task_form.php', 'POST', $payload, $session['cookie']);
        self::assertSame(200, $response['status']);
        self::assertStringContainsString('μήνα έναρξης', mb_strtolower($response['body'], 'UTF-8'));
        self::assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM tasks WHERE title='W06 Invalid Initial Recurrence'")->fetch_row()[0]);
    }

    public function testSchedulerHasNoPublicHttpEndpoint(): void
    {
        $session = $this->login();
        self::assertSame(403, $this->request('/bin/run-recurring-tasks.php', 'GET', [], $session['cookie'])['status']);
        self::assertSame(403, $this->request('/bin/migrate.php', 'GET', [], $session['cookie'])['status']);
        self::assertSame(403, $this->request('/migrations/20261118_002_recurring_tasks.php', 'GET', [], $session['cookie'])['status']);
    }

    public function testTwoRealSchedulerProcessesCreateOnlyOneOccurrence(): void
    {
        $ruleId = $this->insertRule('2027-03-01', 15, '90.00');
        $command = [PHP_BINARY, CRM_TEST_APP_ROOT . '/bin/run-recurring-tasks.php', '--as-of=2027-03-31'];
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $first = proc_open($command, $descriptors, $firstPipes);
        $second = proc_open($command, $descriptors, $secondPipes);
        self::assertIsResource($first);
        self::assertIsResource($second);
        fclose($firstPipes[0]); fclose($secondPipes[0]);
        $firstOut = stream_get_contents($firstPipes[1]); $firstErr = stream_get_contents($firstPipes[2]);
        $secondOut = stream_get_contents($secondPipes[1]); $secondErr = stream_get_contents($secondPipes[2]);
        fclose($firstPipes[1]); fclose($firstPipes[2]); fclose($secondPipes[1]); fclose($secondPipes[2]);
        $firstExit = proc_close($first); $secondExit = proc_close($second);

        self::assertSame(0, $firstExit, $firstOut . $firstErr);
        self::assertSame(0, $secondExit, $secondOut . $secondErr);
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM recurrence_occurrences WHERE rule_id=' . $ruleId . ' AND period_month="2027-03-01"')->fetch_row()[0]);
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM tasks WHERE title="W06 Monthly Filing" AND task_date="2027-03-15"')->fetch_row()[0]);
    }

    public function testBatchLimitContinuesRemainingCatchUpPeriods(): void
    {
        $ruleId = $this->insertRule('2027-01-01', 1, '90.00');
        $first = crm_recurrence_run($this->db, new DateTimeImmutable('2027-03-31', new DateTimeZone('Europe/Athens')), 2);
        self::assertSame(2, $first['created']);
        self::assertSame(2, (int)$this->db->query('SELECT COUNT(*) FROM recurrence_occurrences WHERE rule_id=' . $ruleId)->fetch_row()[0]);
        $second = crm_recurrence_run($this->db, new DateTimeImmutable('2027-03-31', new DateTimeZone('Europe/Athens')), 2);
        self::assertSame(1, $second['created']);
        self::assertSame(3, (int)$this->db->query('SELECT COUNT(*) FROM recurrence_occurrences WHERE rule_id=' . $ruleId)->fetch_row()[0]);
    }

    public function testRestartCreatesNewRuleAndOldRowVersionIsRejected(): void
    {
        $oldId = $this->insertRule('2027-01-01', 15, '50.00');
        crm_recurrence_stop_rule($this->db, $oldId, 1, '2027-03-20');
        $newId = crm_recurrence_restart_rule($this->db, $oldId, '2027-05-01', 910001);
        $this->ruleIds[] = $newId;
        $new = $this->db->query('SELECT previous_rule_id,start_month,state FROM recurring_task_rules WHERE id=' . $newId)->fetch_assoc();
        self::assertSame((string)$oldId, (string)$new['previous_rule_id']);
        self::assertSame('2027-05-01', $new['start_month']);
        self::assertSame('active', $new['state']);
        $run = crm_recurrence_run($this->db, new DateTimeImmutable('2027-06-30', new DateTimeZone('Europe/Athens')));
        self::assertSame(5, $run['created']);
        self::assertSame([['2027-05-01'], ['2027-06-01']], $this->db->query('SELECT period_month FROM recurrence_occurrences WHERE rule_id=' . $newId . ' ORDER BY period_month')->fetch_all(MYSQLI_NUM));
        try {
            crm_recurrence_update_rule($this->db, $newId, 0, 20, '60.00', '2027-04-01');
            self::fail('A stale row version should be rejected.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Ανανεώστε', $exception->getMessage());
        }
    }

    public function testClientUsedByRuleCannotBeDeletedAndGetsClearNotice(): void
    {
        $this->insertRule('2027-01-01', 15, '50.00');
        $session = $this->login();
        $page = $this->request('/client_form.php?id=910030', 'GET', [], $session['cookie']);
        $response = $this->request('/client_form.php', 'POST', [
            'delete_client' => '910030', '_csrf_token' => $this->csrfToken($page['body']),
        ], $session['cookie']);
        self::assertSame(302, $response['status']);
        $notice = $this->request('/client_form.php?id=910030&delete_error=recurrence', 'GET', [], $session['cookie']);
        self::assertSame(200, $notice['status']);
        self::assertStringContainsString('δεν μπορεί να διαγραφεί', mb_strtolower($notice['body'], 'UTF-8'));
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM clients WHERE id=910030')->fetch_row()[0]);
    }

    public function testCliDryRunUsesTestClockAndDoesNotWrite(): void
    {
        $ruleId = $this->insertRule('2027-02-01', 31, '50.00');
        $process = proc_open([PHP_BINARY, CRM_TEST_APP_ROOT . '/bin/run-recurring-tasks.php', '--dry-run', '--as-of=2027-02-28'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $out . $err);
        self::assertStringContainsString('dry-run: created=1', $out);
        self::assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM recurrence_occurrences WHERE rule_id=' . $ruleId)->fetch_row()[0]);
        self::assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM tasks WHERE client_id=910030 AND task_date="2027-02-28"')->fetch_row()[0]);
    }

    private function insertRule(string $startMonth, int $day, string $fee, int $sourceTaskId = self::SOURCE_TASK_ID, string $ruleTitle = 'W06 Monthly Filing'): int
    {
        $client = self::CLIENT_ID; $source = $sourceTaskId; $title = $ruleTitle; $status = 'active';
        $partner = null; $notes = 'W06 fictional recurrence'; $version = 1;
        $rule = $this->db->prepare('INSERT INTO recurring_task_rules (source_task_id,client_id,title,partner_id,notes,day_of_month,start_month,fee,state) VALUES (?,?,?,?,?,?,?, ?,?)');
        $rule->bind_param('iisisisss', $source, $client, $title, $partner, $notes, $day, $startMonth, $fee, $status);
        $rule->execute();
        $id = (int)$rule->insert_id;
        $versionStmt = $this->db->prepare('INSERT INTO recurring_task_rule_versions (rule_id,effective_month,day_of_month,client_id,title,partner_id,notes,fee) VALUES (?,?,?,?,?,?,?,?)');
        $versionStmt->bind_param('isiisiss', $id, $startMonth, $day, $client, $title, $partner, $notes, $fee);
        $versionStmt->execute();
        $this->ruleIds[] = $id;
        return $id;
    }

    private function login(string $username = 'w01-fake-user', string $password = 'W01-Fictional-Password-2026'): array
    {
        $page = $this->request('/login.php');
        $token = $this->csrfToken($page['body']);
        $response = $this->request('/login.php', 'POST', ['username' => $username, 'password' => $password, '_csrf_token' => $token], $page['cookie']);
        self::assertSame(302, $response['status']);
        return ['cookie' => $response['cookie']];
    }

    private function request(string $path, string $method = 'GET', array $data = [], string $cookie = ''): array
    {
        $headers = $cookie === '' ? [] : ['Cookie: ' . $cookie];
        if ($method === 'POST') $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $context = stream_context_create(['http' => [
            'method' => $method, 'header' => implode("\r\n", $headers),
            'content' => $method === 'POST' ? http_build_query($data) : '', 'ignore_errors' => true, 'follow_location' => 0,
        ]]);
        $body = file_get_contents(rtrim((string)getenv('CRM_BASE_URL'), '/') . $path, false, $context);
        $status = 0; $responseCookie = '';
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) $status = (int)$matches[1];
            if (stripos($header, 'Set-Cookie: PHPSESSID=') === 0) $responseCookie = explode(';', trim(substr($header, strlen('Set-Cookie: '))))[0];
        }
        return ['status' => $status, 'body' => $body ?: '', 'cookie' => $responseCookie];
    }

    private function csrfToken(string $body): string
    {
        if (!preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $body, $matches)) self::fail('The form did not render a CSRF token.');
        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
