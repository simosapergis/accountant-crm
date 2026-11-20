<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class W05TaskPaymentsTest extends TestCase
{
    private mysqli $db;
    private const TASK_ID = 910020;
    private const CLIENT_ID = 910020;
    private const METHOD_ID = 910020;
    private const OTHER_TASK_ID = 910082;
    private const NO_METHOD_TASK_ID = 910083;
    private const ZERO_BALANCE_CLIENT_ID = 910081;

    protected function setUp(): void
    {
        $this->db = new mysqli((string)getenv('DB_HOST'), (string)getenv('DB_USER'), (string)getenv('DB_PASSWORD'), (string)getenv('DB_NAME'));
        self::assertSame(CRM_TEST_DATABASE, $this->db->query('SELECT DATABASE()')->fetch_row()[0]);
        $clientId = self::CLIENT_ID;
        $clientName = 'W05 Fictional Payment Client';
        $client = $this->db->prepare("INSERT INTO clients (id, name, category) VALUES (?, ?, 'Ιδιώτης') ON DUPLICATE KEY UPDATE name=VALUES(name)");
        $client->bind_param('is', $clientId, $clientName);
        $client->execute();
        $methodId = self::METHOD_ID;
        $methodName = 'W05 Card';
        $normalized = 'w05 card';
        $method = $this->db->prepare('INSERT INTO payment_methods (id,name,normalized_name,is_active) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name),normalized_name=VALUES(normalized_name),is_active=1');
        $method->bind_param('iss', $methodId, $methodName, $normalized);
        $method->execute();
        $this->putTask('W05 Payment fixture', '25.00', '0.00', null, null);
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->connect_errno === 0 && $this->db->query('SELECT DATABASE()')->fetch_row()[0] === CRM_TEST_DATABASE) {
            $this->db->query('DELETE FROM tasks WHERE id = 910020');
            $this->db->query('DELETE FROM tasks WHERE id IN (910082, 910083)');
            $this->db->query("DELETE FROM task_title_templates WHERE normalized_name LIKE 'w05 %'");
            $this->db->query('DELETE FROM payment_methods WHERE id = 910020');
            $this->db->query('DELETE FROM clients WHERE id = 910020');
            $this->db->query('DELETE FROM clients WHERE id = 910081');
            $this->db->close();
        }
    }

    public function testActiveTitleTemplateAndCustomTitleCanBeSaved(): void
    {
        $session = $this->login();
        $name = 'W05 Quarterly Return';
        $normalized = mb_strtolower($name, 'UTF-8');
        $stmt = $this->db->prepare('INSERT INTO task_title_templates (name, normalized_name, is_active) VALUES (?, ?, 1)');
        $stmt->bind_param('ss', $name, $normalized);
        $stmt->execute();
        $templateId = (int)$this->db->insert_id;

        $form = $this->request('/task_form.php?id=910020', 'GET', [], $session['cookie']);
        self::assertSame(200, $form['status']);
        self::assertStringContainsString('W05 Quarterly Return', $form['body']);
        self::assertStringContainsString('name="title_selection"', $form['body']);
        self::assertStringContainsString('value="template:' . $templateId . '"', $form['body']);
        $saved = $this->request('/task_form.php?id=910020', 'POST', $this->taskPayload($this->csrfToken($form['body']), [
            'title_selection' => 'template:' . $templateId,
        ]), $session['cookie']);
        self::assertSame(302, $saved['status']);
        self::assertSame($name, $this->db->query('SELECT title FROM tasks WHERE id=910020')->fetch_row()[0]);

        $form = $this->request('/task_form.php?id=910020', 'GET', [], $session['cookie']);
        $saved = $this->request('/task_form.php?id=910020', 'POST', $this->taskPayload($this->csrfToken($form['body']), [
            'title_selection' => 'custom', 'custom_title' => 'W05 Ελεύθερος τίτλος',
        ]), $session['cookie']);
        self::assertSame(302, $saved['status']);
        self::assertSame('W05 Ελεύθερος τίτλος', $this->db->query('SELECT title FROM tasks WHERE id=910020')->fetch_row()[0]);
    }

    public function testTaskListFiltersByStoredTitleAndPaymentMethodSnapshots(): void
    {
        $this->db->query("UPDATE payment_methods SET name='W05 Renamed Card',is_active=0 WHERE id=910020");
        $this->db->query("UPDATE tasks SET payment_method_id=910020,payment_method_label='W05 Card' WHERE id=910020");
        $this->putAdditionalTask(self::OTHER_TASK_ID, 'W05 Other Snapshot', 'W05 Cash');
        $this->putAdditionalTask(self::NO_METHOD_TASK_ID, 'W05 No Method Snapshot', null);
        $session = $this->login();

        $filtered = $this->request('/tasks.php?' . http_build_query([
            'task_title' => 'W05 Payment fixture',
            'payment_method' => 'label:' . bin2hex('W05 Card'),
        ]), 'GET', [], $session['cookie']);

        self::assertSame(200, $filtered['status']);
        self::assertStringContainsString('href="task_form.php?id=910020"', $filtered['body']);
        self::assertStringNotContainsString('href="task_form.php?id=910082"', $filtered['body']);
        self::assertStringNotContainsString('href="task_form.php?id=910083"', $filtered['body']);
        self::assertStringContainsString('value="W05 Payment fixture" selected', $filtered['body']);
        self::assertStringContainsString('value="label:' . bin2hex('W05 Card') . '" selected', $filtered['body']);
        self::assertStringContainsString('value="W05 Other Snapshot"', $filtered['body'], 'Title options include stored values outside the active catalog.');
        self::assertStringContainsString('value="label:' . bin2hex('W05 Cash') . '"', $filtered['body'], 'Payment options include stored snapshots outside the current catalog.');

        $withoutMethod = $this->request('/tasks.php?payment_method=none%3A', 'GET', [], $session['cookie']);
        self::assertSame(200, $withoutMethod['status']);
        self::assertStringContainsString('href="task_form.php?id=910083"', $withoutMethod['body']);
        self::assertStringNotContainsString('href="task_form.php?id=910020"', $withoutMethod['body']);
        self::assertStringNotContainsString('href="task_form.php?id=910082"', $withoutMethod['body']);
        self::assertStringContainsString('value="none:" selected', $withoutMethod['body']);
    }

    public function testDashboardPendingSettlementCardOpensClientsWithPositiveBalances(): void
    {
        $clientId = self::ZERO_BALANCE_CLIENT_ID;
        $clientName = 'W05 Zero Balance Client';
        $stmt = $this->db->prepare("INSERT INTO clients (id,name,category) VALUES (?,?,'Ιδιώτης')");
        $stmt->bind_param('is', $clientId, $clientName);
        $stmt->execute();
        $session = $this->login();

        $home = $this->request('/index.php', 'GET', [], $session['cookie']);
        self::assertSame(200, $home['status']);
        self::assertStringContainsString('href="clients.php?balance=positive&amp;sort=balance"', $home['body']);
        self::assertStringContainsString('dashboard-pending-link', $home['body']);

        $clients = $this->request('/clients.php?balance=positive&sort=balance', 'GET', [], $session['cookie']);
        self::assertSame(200, $clients['status']);
        self::assertStringContainsString('W05 Fictional Payment Client', $clients['body']);
        self::assertStringNotContainsString('W05 Zero Balance Client', $clients['body']);
        self::assertStringContainsString('value="positive" selected', $clients['body']);
        self::assertStringContainsString('value="balance" selected', $clients['body']);
    }

    public function testUnchangedTitleSelectionPreservesSnapshotWhenTemplateIsRenamedWhileFormIsOpen(): void
    {
        $name = 'W05 Payment fixture';
        $normalized = mb_strtolower($name, 'UTF-8');
        $stmt = $this->db->prepare('INSERT INTO task_title_templates (name,normalized_name,is_active) VALUES (?,?,1)');
        $stmt->bind_param('ss', $name, $normalized);
        $stmt->execute();
        $templateId = (int)$this->db->insert_id;
        $session = $this->login();
        $form = $this->request('/task_form.php?id=910020', 'GET', [], $session['cookie']);
        self::assertSame(200, $form['status']);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $form['body']);
        $xpath = new DOMXPath($dom);
        $selected = $xpath->query('//select[@name="title_selection"]/option[@selected]');
        self::assertSame(1, $selected->length);
        $selection = $selected->item(0)->getAttribute('value');
        $customTitle = $xpath->query('//input[@name="custom_title"]')->item(0);
        self::assertNotNull($customTitle);
        self::assertFalse($customTitle->hasAttribute('disabled'));

        $renamed = $this->db->prepare("UPDATE task_title_templates SET name='W05 Renamed title',normalized_name='w05 renamed title' WHERE id=?");
        $renamed->bind_param('i', $templateId);
        $renamed->execute();
        $saved = $this->request('/task_form.php?id=910020', 'POST', $this->taskPayload($this->csrfToken($form['body']), [
            'title_selection' => $selection, 'custom_title' => $customTitle->getAttribute('value'), 'fee' => '30.00',
        ]), $session['cookie']);
        self::assertSame(302, $saved['status']);
        self::assertSame(['title' => $name, 'fee' => '30.00'], $this->db->query('SELECT title,fee FROM tasks WHERE id=910020')->fetch_assoc());
    }

    public function testPositiveCollectionRequiresAnActivePaymentMethod(): void
    {
        $session = $this->login();
        $form = $this->request('/task_form.php?id=910020', 'GET', [], $session['cookie']);
        $payload = $this->taskPayload($this->csrfToken($form['body']), ['collected' => '5.00']);
        $response = $this->request('/task_form.php?id=910020', 'POST', $payload, $session['cookie']);
        $row = $this->taskRow();

        self::assertSame(200, $response['status']);
        self::assertStringContainsString('μέσο είσπραξης', mb_strtolower($response['body'], 'UTF-8'));
        self::assertSame('0.00', $row['collected']);
        self::assertNull($row['payment_method_id']);
        self::assertNull($row['payment_method_label']);
    }

    public function testLegacyPositiveCollectionRequiresMethodOnFirstEdit(): void
    {
        $this->db->query("UPDATE tasks SET collected='5.00',payment_method_id=NULL,payment_method_label=NULL WHERE id=910020");
        $session = $this->login();
        $form = $this->request('/task_form.php?id=910020', 'GET', [], $session['cookie']);
        $response = $this->request('/task_form.php?id=910020', 'POST', $this->taskPayload($this->csrfToken($form['body']), ['collected' => '5.00']), $session['cookie']);
        self::assertSame(200, $response['status']);
        self::assertStringContainsString('απαιτείται ενεργό μέσο είσπραξης', $response['body']);
        self::assertSame('5.00', $this->taskRow()['collected']);
        self::assertNull($this->taskRow()['payment_method_id']);
    }

    public function testOverCollectionShowsNegativeBalanceAsOneCollectedAmount(): void
    {
        $session = $this->login();
        $form = $this->request('/task_form.php?id=910020', 'GET', [], $session['cookie']);
        $payload = $this->taskPayload($this->csrfToken($form['body']), [
            'fee' => '100', 'collected' => '150', 'payment_method_id' => '910020',
        ]);
        self::assertSame(302, $this->request('/task_form.php?id=910020', 'POST', $payload, $session['cookie'])['status']);
        $display = $this->request('/task_form.php?id=910020', 'GET', [], $session['cookie']);
        self::assertSame('150.00', $this->taskRow()['collected']);
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM tasks WHERE id=910020')->fetch_row()[0]);
        self::assertStringContainsString('-50,00', $display['body']);
    }

    public function testTaskTitleLengthIsEnforcedOnTheServer(): void
    {
        $session = $this->login();
        $form = $this->request('/task_form.php?id=910020', 'GET', [], $session['cookie']);
        $payload = $this->taskPayload($this->csrfToken($form['body']), ['custom_title' => str_repeat('x', 256)]);
        $response = $this->request('/task_form.php?id=910020', 'POST', $payload, $session['cookie']);
        self::assertSame(200, $response['status']);
        self::assertStringContainsString('έως 255 χαρακτήρες', $response['body']);
        self::assertSame('W05 Payment fixture', $this->db->query('SELECT title FROM tasks WHERE id=910020')->fetch_row()[0]);
    }

    public function testCollectionStoresServerLabelThenPreservesSnapshotAndClearsAtZero(): void
    {
        $session = $this->login();
        $form = $this->request('/task_form.php?id=910020', 'GET', [], $session['cookie']);
        $token = $this->csrfToken($form['body']);
        $payload = $this->taskPayload($token, ['collected' => '5.00', 'payment_method_id' => '910020']);
        $saved = $this->request('/task_form.php?id=910020', 'POST', $payload, $session['cookie']);
        preg_match('/<div class="alert alert-danger"[^>]*>(.*?)<\/div>/s', $saved['body'], $saveError);
        self::assertSame(302, $saved['status'], trim(strip_tags($saveError[1] ?? 'no visible validation error')));
        self::assertSame('W05 Card', $this->taskRow()['payment_method_label']);

        $this->db->query("UPDATE payment_methods SET name='Renamed Card', normalized_name='renamed card',is_active=0 WHERE id=910020");
        $edit = $this->request('/task_form.php?id=910020', 'GET', [], $session['cookie']);
        $editToken = $this->csrfToken($edit['body']);
        $noteOnly = $this->taskPayload($editToken, ['collected' => '5.00', 'payment_method_id' => '910020', 'notes' => 'Unrelated edit']);
        self::assertSame(302, $this->request('/task_form.php?id=910020', 'POST', $noteOnly, $session['cookie'])['status']);
        $preserved = $this->taskRow();
        self::assertSame('910020', (string)$preserved['payment_method_id']);
        self::assertSame('W05 Card', $preserved['payment_method_label']);

        $edit = $this->request('/task_form.php?id=910020', 'GET', [], $session['cookie']);
        $zero = $this->taskPayload($this->csrfToken($edit['body']), ['collected' => '0']);
        self::assertSame(302, $this->request('/task_form.php?id=910020', 'POST', $zero, $session['cookie'])['status']);
        $cleared = $this->taskRow();
        self::assertSame('0.00', $cleared['collected']);
        self::assertNull($cleared['payment_method_id']);
        self::assertNull($cleared['payment_method_label']);
        self::assertStringContainsString('Η είσπραξη μηδενίστηκε', $this->request('/tasks.php', 'GET', [], $session['cookie'])['body']);
    }

    public function testInactiveMethodCannotBeSelectedForNewCollection(): void
    {
        $session = $this->login();
        $this->db->query('UPDATE payment_methods SET is_active=0 WHERE id=910020');
        $form = $this->request('/task_form.php?id=910020', 'GET', [], $session['cookie']);
        $payload = $this->taskPayload($this->csrfToken($form['body']), ['collected' => '5', 'payment_method_id' => '910020']);
        $response = $this->request('/task_form.php?id=910020', 'POST', $payload, $session['cookie']);

        self::assertSame(200, $response['status']);
        self::assertSame('0.00', $this->taskRow()['collected']);
    }

    public function testSnapshotIsRenderedInTaskListAndTaskExports(): void
    {
        $this->db->query("UPDATE tasks SET collected='5.00',payment_method_id=910020,payment_method_label='W05 Card',partner_id=910001 WHERE id=910020");
        $session = $this->login();
        $list = $this->request('/tasks.php', 'GET', [], $session['cookie']);
        $form = $this->request('/task_form.php?id=910020', 'GET', [], $session['cookie']);
        $excel = $this->request('/exports/export_tasks_excel.php', 'GET', [], $session['cookie']);
        $pdf = $this->request('/exports/export_tasks_pdf.php', 'GET', [], $session['cookie']);
        $partner = $this->request('/analysis_partners.php?id=910001', 'GET', [], $session['cookie']);
        $partnerTasks = $this->request('/partner_tasks.php?id=910001', 'GET', [], $session['cookie']);
        $partnerExcel = $this->request('/exports/export_analysis_partners_excel.php?partner_id=910001', 'GET', [], $session['cookie']);
        $partnerPdf = $this->request('/exports/export_analysis_partners_pdf.php?partner_id=910001', 'GET', [], $session['cookie']);

        foreach ([$list, $form, $excel, $pdf, $partner, $partnerTasks, $partnerExcel, $partnerPdf] as $response) {
            self::assertSame(200, $response['status']);
            self::assertStringContainsString('W05 Card', $response['body']);
        }
        self::assertStringContainsString('<span><strong>Μέσο είσπραξης:</strong> W05 Card</span>', $list['body'], 'The task mobile card must render the stored payment-method snapshot.');
        $stored = $this->db->query('SELECT COUNT(*) AS task_count, SUM(fee) AS fees, SUM(collected) AS collections FROM tasks WHERE id=910020')->fetch_assoc();
        self::assertSame('1', (string)$stored['task_count']);
        self::assertSame('25.00', (string)$stored['fees']);
        self::assertSame('5.00', (string)$stored['collections']);
    }

    private function putTask(string $title, string $fee, string $collected, ?int $methodId, ?string $label): void
    {
        $id = self::TASK_ID;
        $date = '2026-11-18';
        $client = self::CLIENT_ID;
        $status = 'Σε εξέλιξη';
        $notes = '';
        $stmt = $this->db->prepare('INSERT INTO tasks (id,task_date,title,client_id,status,fee,collected,payment_method_id,payment_method_label,notes) VALUES (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE task_date=VALUES(task_date),title=VALUES(title),client_id=VALUES(client_id),status=VALUES(status),fee=VALUES(fee),collected=VALUES(collected),payment_method_id=VALUES(payment_method_id),payment_method_label=VALUES(payment_method_label),notes=VALUES(notes)');
        $stmt->bind_param('ississsiss', $id, $date, $title, $client, $status, $fee, $collected, $methodId, $label, $notes);
        $stmt->execute();
    }

    private function putAdditionalTask(int $id, string $title, ?string $paymentMethodLabel): void
    {
        $date = '2026-11-19';
        $client = self::CLIENT_ID;
        $status = 'Ολοκληρωμένη';
        $fee = '40.00';
        $collected = '40.00';
        $stmt = $this->db->prepare('INSERT INTO tasks (id,task_date,title,client_id,status,fee,collected,payment_method_label) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->bind_param('ississss', $id, $date, $title, $client, $status, $fee, $collected, $paymentMethodLabel);
        $stmt->execute();
    }

    private function taskRow(): array
    {
        return $this->db->query('SELECT collected,payment_method_id,payment_method_label FROM tasks WHERE id=910020')->fetch_assoc();
    }

    private function taskPayload(string $token, array $overrides = []): array
    {
        return array_merge([
            'id' => '910020', 'task_date' => '2026-11-18', 'title_selection' => 'custom', 'custom_title' => 'W05 Payment fixture',
            'client_id' => '910020', 'partner_id' => '', 'status' => 'Σε εξέλιξη',
            'fee' => '25.00', 'collected' => '0.00', 'notes' => '', 'save_task' => '1', '_csrf_token' => $token,
        ], $overrides);
    }

    private function login(): array
    {
        $page = $this->request('/login.php');
        $token = $this->csrfToken($page['body']);
        $response = $this->request('/login.php', 'POST', [
            'username' => 'w01-fake-user', 'password' => 'W01-Fictional-Password-2026', '_csrf_token' => $token,
        ], $page['cookie']);
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
        if (!preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $body, $matches)) self::fail('The form did not render CSRF.');
        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
