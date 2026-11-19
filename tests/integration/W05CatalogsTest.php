<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class W05CatalogsTest extends TestCase
{
    private mysqli $db;
    private const ADMIN_ID = 910010;
    private const ADMIN_USERNAME = 'w05-fictional-admin';
    private const ADMIN_PASSWORD = 'W05-Fictional-Admin-Password';

    protected function setUp(): void
    {
        $this->db = new mysqli(
            (string)getenv('DB_HOST'),
            (string)getenv('DB_USER'),
            (string)getenv('DB_PASSWORD'),
            (string)getenv('DB_NAME')
        );
        self::assertSame(CRM_TEST_DATABASE, $this->db->query('SELECT DATABASE()')->fetch_row()[0]);

        $username = self::ADMIN_USERNAME;
        $hash = password_hash(self::ADMIN_PASSWORD, PASSWORD_DEFAULT);
        $fullname = 'W05 Fictional Administrator';
        $role = 'admin';
        $stmt = $this->db->prepare(
            'INSERT INTO users (id, username, password, fullname, role) VALUES (?, ?, ?, ?, ?) ' .
            'ON DUPLICATE KEY UPDATE password = VALUES(password), fullname = VALUES(fullname), role = VALUES(role)'
        );
        $id = self::ADMIN_ID;
        $stmt->bind_param('issss', $id, $username, $hash, $fullname, $role);
        $stmt->execute();
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->connect_errno === 0) {
            $this->db->query("DELETE FROM payment_methods WHERE normalized_name IN ('bank transfer', 'cash', 'card')");
            $this->db->query("DELETE FROM task_title_templates WHERE normalized_name LIKE 'w05%'");
            $this->db->query('DELETE FROM tasks WHERE id = 910021');
            $stmt = $this->db->prepare('DELETE FROM users WHERE id = ? AND username = ?');
            $id = self::ADMIN_ID;
            $username = self::ADMIN_USERNAME;
            $stmt->bind_param('is', $id, $username);
            $stmt->execute();
            $this->db->close();
        }
    }

    public function testAdminCanOpenBothCatalogManagementScreens(): void
    {
        $admin = $this->login(self::ADMIN_USERNAME, self::ADMIN_PASSWORD);

        self::assertSame(200, $this->request('/payment_methods.php', 'GET', [], $admin['cookie'])['status']);
        self::assertSame(200, $this->request('/task_titles.php', 'GET', [], $admin['cookie'])['status']);
    }

    public function testHostileCatalogNamesRemainEscapedInCatalogAndTaskForm(): void
    {
        $name = '<img src=x onerror="window.crmXss=1">';
        $normalized = mb_strtolower($name, 'UTF-8');
        foreach ([['payment_methods', 'name'], ['task_title_templates', 'name']] as [$table, $column]) {
            $stmt = $this->db->prepare("INSERT INTO {$table} (name, normalized_name, is_active) VALUES (?, ?, 1)");
            $stmt->bind_param('ss', $name, $normalized);
            $stmt->execute();
        }

        try {
            $admin = $this->login(self::ADMIN_USERNAME, self::ADMIN_PASSWORD);
            $methods = $this->request('/payment_methods.php', 'GET', [], $admin['cookie']);
            $titles = $this->request('/task_titles.php', 'GET', [], $admin['cookie']);
            $task = $this->request('/task_form.php', 'GET', [], $admin['cookie']);

            foreach ([$methods['body'], $titles['body'], $task['body']] as $body) {
                self::assertStringNotContainsString('<img src=x', $body);
                self::assertStringContainsString('&lt;img', $body);
            }
            self::assertStringNotContainsString('onerror="window.crmXss=1"', $task['body']);
        } finally {
            foreach (['payment_methods', 'task_title_templates'] as $table) {
                $stmt = $this->db->prepare("DELETE FROM {$table} WHERE normalized_name = ?");
                $stmt->bind_param('s', $normalized);
                $stmt->execute();
            }
        }
    }

    public function testUserCannotViewOrPostToCatalogManagement(): void
    {
        $user = $this->login('w01-fake-user', 'W01-Fictional-Password-2026');
        $page = $this->request('/tasks.php', 'GET', [], $user['cookie']);
        $token = $this->csrfToken($page['body']);

        self::assertSame(403, $this->request('/payment_methods.php', 'GET', [], $user['cookie'])['status']);
        self::assertSame(403, $this->request('/task_titles.php', 'GET', [], $user['cookie'])['status']);
        self::assertSame(403, $this->request('/payment_method_form.php', 'GET', [], $user['cookie'])['status']);
        self::assertSame(403, $this->request('/task_title_form.php', 'GET', [], $user['cookie'])['status']);
        self::assertSame(403, $this->request('/payment_method_form.php', 'POST', [
            'name' => 'W05 Unauthorized Method', 'save_catalog' => '1', '_csrf_token' => $token,
        ], $user['cookie'])['status']);
        self::assertSame(403, $this->request('/task_title_form.php', 'POST', [
            'name' => 'W05 Unauthorized Title', 'save_catalog' => '1', '_csrf_token' => $token,
        ], $user['cookie'])['status']);
        self::assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM payment_methods WHERE normalized_name = 'w05 unauthorized method'")->fetch_row()[0]);
        self::assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM task_title_templates WHERE normalized_name = 'w05 unauthorized title'")->fetch_row()[0]);
    }

    public function testAdminCanCreateAndDeactivatePaymentMethodWithoutDeletingIt(): void
    {
        $admin = $this->login(self::ADMIN_USERNAME, self::ADMIN_PASSWORD);
        $form = $this->request('/payment_method_form.php', 'GET', [], $admin['cookie']);
        $token = $this->csrfToken($form['body']);
        $created = $this->request('/payment_method_form.php', 'POST', [
            'name' => '  Bank Transfer  ', 'is_active' => '1', 'save_catalog' => '1', '_csrf_token' => $token,
        ], $admin['cookie']);
        $method = $this->db->query("SELECT id, name, normalized_name, is_active FROM payment_methods WHERE normalized_name = 'bank transfer'")->fetch_assoc();

        self::assertSame(302, $created['status']);
        self::assertSame('Bank Transfer', $method['name'] ?? null);
        self::assertSame(1, (int)($method['is_active'] ?? 0));

        $edit = $this->request('/payment_method_form.php?id=' . (int)$method['id'], 'GET', [], $admin['cookie']);
        $editToken = $this->csrfToken($edit['body']);
        $deactivated = $this->request('/payment_method_form.php', 'POST', [
            'id' => (int)$method['id'], 'name' => 'Bank Transfer', 'save_catalog' => '1',
            '_csrf_token' => $editToken,
        ], $admin['cookie']);
        $after = $this->db->query('SELECT name, is_active FROM payment_methods WHERE id = ' . (int)$method['id'])->fetch_assoc();

        self::assertSame(302, $deactivated['status']);
        self::assertSame('Bank Transfer', $after['name'] ?? null);
        self::assertSame(0, (int)($after['is_active'] ?? 1));
    }

    public function testInactiveDuplicateOffersReactivationInsteadOfCreatingAnotherRow(): void
    {
        $admin = $this->login(self::ADMIN_USERNAME, self::ADMIN_PASSWORD);
        $id = $this->insertInactivePaymentMethod('Cash');
        $form = $this->request('/payment_method_form.php', 'GET', [], $admin['cookie']);
        $token = $this->csrfToken($form['body']);
        $duplicate = $this->request('/payment_method_form.php', 'POST', [
            'name' => ' cash ', 'save_catalog' => '1', '_csrf_token' => $token,
        ], $admin['cookie']);

        self::assertSame(200, $duplicate['status']);
        self::assertStringContainsString('Υπάρχει ανενεργή επιλογή', $duplicate['body']);
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM payment_methods WHERE normalized_name="cash"')->fetch_row()[0]);
        $reactivateToken = $this->csrfToken($duplicate['body']);
        $reactivated = $this->request('/payment_method_form.php', 'POST', [
            'name' => 'Cash', 'reactivate_id' => (string)$id, 'save_catalog' => '1', '_csrf_token' => $reactivateToken,
        ], $admin['cookie']);
        self::assertSame(302, $reactivated['status']);
        self::assertSame(1, (int)$this->db->query('SELECT is_active FROM payment_methods WHERE id=' . $id)->fetch_row()[0]);
        $this->db->query('DELETE FROM payment_methods WHERE id = ' . $id);
    }

    public function testActiveDuplicateAndBlankCatalogNamesAreRejected(): void
    {
        $admin = $this->login(self::ADMIN_USERNAME, self::ADMIN_PASSWORD);
        $form = $this->request('/payment_method_form.php', 'GET', [], $admin['cookie']);
        $token = $this->csrfToken($form['body']);
        $first = $this->request('/payment_method_form.php', 'POST', [
            'name' => 'Card', 'save_catalog' => '1', '_csrf_token' => $token,
        ], $admin['cookie']);
        $duplicate = $this->request('/payment_method_form.php', 'POST', [
            'name' => ' card ', 'save_catalog' => '1', '_csrf_token' => $token,
        ], $admin['cookie']);
        $blank = $this->request('/payment_method_form.php', 'POST', [
            'name' => '  ', 'save_catalog' => '1', '_csrf_token' => $token,
        ], $admin['cookie']);

        self::assertSame(302, $first['status']);
        self::assertSame(200, $duplicate['status']);
        self::assertStringContainsString('Υπάρχει ήδη ενεργή επιλογή', $duplicate['body']);
        self::assertSame(200, $blank['status']);
        self::assertStringContainsString('υποχρεωτική', $blank['body']);
    }

    public function testTitleCatalogCanCreateRenameAndDeactivate(): void
    {
        $admin = $this->login(self::ADMIN_USERNAME, self::ADMIN_PASSWORD);
        $form = $this->request('/task_title_form.php', 'GET', [], $admin['cookie']);
        $token = $this->csrfToken($form['body']);
        $created = $this->request('/task_title_form.php', 'POST', [
            'name' => 'W05 Quarterly Return', 'is_active' => '1', 'save_catalog' => '1', '_csrf_token' => $token,
        ], $admin['cookie']);
        self::assertSame(302, $created['status']);
        $item = $this->db->query("SELECT id FROM task_title_templates WHERE normalized_name='w05 quarterly return'")->fetch_assoc();
        $id = (int)$item['id'];
        $taskId = 910021;
        $taskDate = '2026-11-18';
        $taskTitle = 'W05 Quarterly Return';
        $clientId = 910001;
        $status = 'Σε εξέλιξη';
        $fee = '25.00';
        $collected = '0.00';
        $task = $this->db->prepare('INSERT INTO tasks (id,task_date,title,client_id,status,fee,collected) VALUES (?,?,?,?,?,?,?)');
        $task->bind_param('ississs', $taskId, $taskDate, $taskTitle, $clientId, $status, $fee, $collected);
        $task->execute();
        $edit = $this->request('/task_title_form.php?id=' . $id, 'GET', [], $admin['cookie']);
        $edited = $this->request('/task_title_form.php', 'POST', [
            'id' => (string)$id, 'name' => 'W05 Annual Return', 'is_active' => '1', 'save_catalog' => '1', '_csrf_token' => $this->csrfToken($edit['body']),
        ], $admin['cookie']);
        self::assertSame(302, $edited['status']);
        self::assertSame('W05 Quarterly Return', $this->db->query('SELECT title FROM tasks WHERE id=910021')->fetch_row()[0]);
        $taskEdit = $this->request('/task_form.php?id=910021', 'GET', [], $admin['cookie']);
        self::assertStringContainsString('value="W05 Quarterly Return"', $taskEdit['body']);
        self::assertStringContainsString('value="W05 Annual Return"', $taskEdit['body']);
        $deactivateForm = $this->request('/task_title_form.php?id=' . $id, 'GET', [], $admin['cookie']);
        $deactivated = $this->request('/task_title_form.php', 'POST', [
            'id' => (string)$id, 'name' => 'W05 Annual Return', 'save_catalog' => '1', '_csrf_token' => $this->csrfToken($deactivateForm['body']),
        ], $admin['cookie']);
        $row = $this->db->query('SELECT name,is_active FROM task_title_templates WHERE id=' . $id)->fetch_assoc();

        self::assertSame(302, $deactivated['status']);
        self::assertSame('W05 Annual Return', $row['name']);
        self::assertSame(0, (int)$row['is_active']);
    }

    private function insertInactivePaymentMethod(string $name): int
    {
        $normalized = mb_strtolower(trim($name), 'UTF-8');
        $stmt = $this->db->prepare('INSERT INTO payment_methods (name, normalized_name, is_active) VALUES (?, ?, 0)');
        $stmt->bind_param('ss', $name, $normalized);
        $stmt->execute();
        return (int)$stmt->insert_id;
    }

    private function login(string $username, string $password): array
    {
        $page = $this->request('/login.php');
        $token = $this->csrfToken($page['body']);
        $response = $this->request('/login.php', 'POST', [
            'username' => $username,
            'password' => $password,
            '_csrf_token' => $token,
        ], $page['cookie']);

        self::assertSame(302, $response['status']);
        self::assertNotSame('', $response['cookie']);
        return ['cookie' => $response['cookie']];
    }

    private function request(string $path, string $method = 'GET', array $data = [], string $cookie = ''): array
    {
        $headers = [];
        if ($cookie !== '') {
            $headers[] = 'Cookie: ' . $cookie;
        }
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $method === 'POST' ? http_build_query($data) : '',
            'ignore_errors' => true,
            'follow_location' => 0,
        ]]);
        $body = file_get_contents(rtrim((string)getenv('CRM_BASE_URL'), '/') . $path, false, $context);
        $status = 0;
        $responseCookie = '';
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                $status = (int)$matches[1];
            }
            if (stripos($header, 'Set-Cookie: PHPSESSID=') === 0) {
                $responseCookie = explode(';', trim(substr($header, strlen('Set-Cookie: '))))[0];
            }
        }
        return ['status' => $status, 'body' => $body ?: '', 'cookie' => $responseCookie];
    }

    private function csrfToken(string $body): string
    {
        if (!preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $body, $matches)) {
            self::fail('The page did not render a session CSRF token.');
        }
        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
