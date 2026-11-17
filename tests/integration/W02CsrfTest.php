<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class W02CsrfTest extends TestCase
{
    private mysqli $db;

    protected function setUp(): void
    {
        $this->db = new mysqli(
            (string) getenv('DB_HOST'),
            (string) getenv('DB_USER'),
            (string) getenv('DB_PASSWORD'),
            (string) getenv('DB_NAME')
        );
        $this->assertTestDatabase();
        $this->putClient(910099, 'W02 CSRF Fixture');
        $this->putClient(910098, 'W02 GET Delete Fixture');
        $this->putTaskFixture();
        $this->putPrivateDiaryFixtures();
        $this->putOwnDiaryXssFixture();
        $this->putActiveProfessionalXssFixture();
    }

    protected function tearDown(): void
    {
        if ($this->db->connect_errno === 0 && $this->databaseName() === 'crm_w01_test') {
            $ajaxFixtureName = 'W02 XSS </script><script>window.crmXss=1</script>';
            $deleteAjaxFixture = $this->db->prepare('DELETE FROM clients WHERE name = ?');
            $deleteAjaxFixture->bind_param('s', $ajaxFixtureName);
            $deleteAjaxFixture->execute();
            $this->db->query("DELETE FROM diary_events WHERE user_id = 910001 AND event_date = '2026-11-18' AND title LIKE 'W02 Apostrophe%'");
            $this->putTaskFixture();
            $this->db->query('DELETE FROM diary_events WHERE id = 910099');
            $this->db->query('DELETE FROM diary_events WHERE id = 910098');
            $this->db->query("DELETE FROM diary_notes WHERE user_id = 910002 AND note_date = '2026-11-17'");
            $this->db->query('DELETE FROM users WHERE id = 910002');
            $this->db->query("UPDATE users SET fullname = 'W01 Fictional User' WHERE id = 910001");
            $this->db->query("UPDATE partners SET fullname = 'W01 Fixture Collaborator' WHERE id = 910001");
            $this->db->query('DELETE FROM active_professionals WHERE id = 910099');
            $this->db->query('DELETE FROM clients WHERE id IN (910098, 910099)');
        }
        $this->db->close();
    }

    public function testClientUpdateWithoutCsrfTokenIsRejectedAndDoesNotChangeData(): void
    {
        $session = $this->login();
        $response = $this->request('/client_form.php', 'POST', [
            'id' => 910099,
            'name' => 'W02 Forged Client Update',
            'afm' => '',
            'category' => 'Ιδιώτης',
            'phone' => '',
            'email' => '',
        ], $session['cookie']);

        self::assertSame(403, $response['status']);
        self::assertSame('W02 CSRF Fixture', $this->clientName(910099));
    }

    public function testClientUpdateWithInvalidCsrfTokenIsRejectedAndDoesNotChangeData(): void
    {
        $session = $this->login();
        $response = $this->request('/client_form.php', 'POST', [
            'id' => 910099,
            'name' => 'W02 Forged Client Update',
            'afm' => '',
            'category' => 'Ιδιώτης',
            'phone' => '',
            'email' => '',
            '_csrf_token' => 'not-a-valid-session-token',
        ], $session['cookie']);

        self::assertSame(403, $response['status']);
        self::assertSame('W02 CSRF Fixture', $this->clientName(910099));
    }

    public function testClientUpdateWithAnotherSessionsCsrfTokenIsRejected(): void
    {
        $sessionA = $this->login();
        $sessionB = $this->login();
        $pageB = $this->request('/client_form.php?id=910099', 'GET', [], $sessionB['cookie']);
        $tokenFromB = $this->csrfToken($pageB['body']);

        $response = $this->request('/client_form.php', 'POST', [
            'id' => 910099,
            'name' => 'W02 Cross Session Forgery',
            'afm' => '',
            'category' => 'Ιδιώτης',
            'phone' => '',
            'email' => '',
            '_csrf_token' => $tokenFromB,
        ], $sessionA['cookie']);

        self::assertSame(403, $response['status']);
        self::assertSame('W02 CSRF Fixture', $this->clientName(910099));
    }

    public function testLoginPostWithoutCsrfTokenIsRejected(): void
    {
        $page = $this->request('/login.php');
        $response = $this->request('/login.php', 'POST', [
            'username' => 'w01-fake-user',
            'password' => 'W01-Fictional-Password-2026',
        ], $page['cookie']);

        self::assertSame(403, $response['status']);
        self::assertSame(302, $this->request('/tasks.php', 'GET', [], $page['cookie'])['status']);
    }

    public function testLogoutRequiresCsrfProtectedPostAndEndsSession(): void
    {
        $session = $this->login();
        self::assertSame(405, $this->request('/logout.php', 'GET', [], $session['cookie'])['status']);

        $page = $this->request('/tasks.php', 'GET', [], $session['cookie']);
        $token = $this->csrfToken($page['body']);
        $logout = $this->request('/logout.php', 'POST', ['_csrf_token' => $token], $session['cookie']);

        self::assertSame(302, $logout['status']);
        self::assertSame(302, $this->request('/tasks.php', 'GET', [], $session['cookie'])['status']);
    }

    public function testClientUpdateWithSessionCsrfTokenIsAccepted(): void
    {
        $session = $this->login();
        $form = $this->request('/client_form.php?id=910099', 'GET', [], $session['cookie']);
        $token = $this->csrfToken($form['body']);

        $response = $this->request('/client_form.php', 'POST', [
            'id' => 910099,
            'name' => 'W02 Authorized Client Update',
            'afm' => '',
            'category' => 'Ιδιώτης',
            'phone' => '',
            'email' => '',
            '_csrf_token' => $token,
        ], $session['cookie']);

        self::assertSame(302, $response['status']);
        self::assertSame('W02 Authorized Client Update', $this->clientName(910099));
    }

    public function testUserRoleCanEditSharedTaskWithValidCsrfToken(): void
    {
        $session = $this->login();
        $form = $this->request('/task_form.php?id=910001', 'GET', [], $session['cookie']);
        $token = $this->csrfToken($form['body']);
        $response = $this->request('/task_form.php?id=910001', 'POST', [
            'id' => 910001,
            'task_date' => '2026-11-17',
            'title' => 'W02 User Role Task Edit',
            'client_id' => 910001,
            'partner_id' => 910001,
            'status' => 'Σε εξέλιξη',
            'fee' => '100.00',
            'collected' => '0.00',
            'notes' => 'fictional role regression',
            'save_task' => '1',
            '_csrf_token' => $token,
        ], $session['cookie']);
        $task = $this->db->query('SELECT title FROM tasks WHERE id = 910001')->fetch_assoc();

        self::assertSame(302, $response['status']);
        self::assertSame('W02 User Role Task Edit', $task['title'] ?? null);
    }

    public function testClientSearchDoesNotBroadenForSqlLikeInput(): void
    {
        $session = $this->login();
        $path = '/clients.php?' . http_build_query(['search' => "' OR 1=1 -- "]);
        $response = $this->request($path, 'GET', [], $session['cookie']);

        self::assertSame(200, $response['status']);
        self::assertStringNotContainsString('W01 Fixture Client', $response['body']);
        self::assertStringNotContainsString('W02 CSRF Fixture', $response['body']);
    }

    public function testClientAndPartnerListsHandleNullableContactValues(): void
    {
        $session = $this->login();
        $clientResponse = $this->request(
            '/clients.php?' . http_build_query(['search' => 'W02 CSRF Fixture']),
            'GET',
            [],
            $session['cookie']
        );
        $partnerResponse = $this->request(
            '/partners.php?' . http_build_query(['search' => 'W01 Fixture Collaborator']),
            'GET',
            [],
            $session['cookie']
        );

        self::assertSame(200, $clientResponse['status']);
        self::assertSame(200, $partnerResponse['status']);
        self::assertStringContainsString('W02 CSRF Fixture', $clientResponse['body']);
        self::assertStringContainsString('W01 Fixture Collaborator', $partnerResponse['body']);
        self::assertStringNotContainsString('Deprecated: htmlspecialchars()', $clientResponse['body']);
        self::assertStringNotContainsString('Deprecated: htmlspecialchars()', $partnerResponse['body']);
    }

    public function testPartnerAndAutocompleteSearchesDoNotBroadenForSqlLikeInput(): void
    {
        $session = $this->login();
        $sqlLikeInput = "' OR 1=1 -- ";

        $partnerPath = '/partners.php?' . http_build_query(['search' => $sqlLikeInput]);
        $partnerResponse = $this->request($partnerPath, 'GET', [], $session['cookie']);
        self::assertSame(200, $partnerResponse['status']);
        self::assertStringNotContainsString('W01 Fixture Collaborator', $partnerResponse['body']);

        $autocompletePath = '/ajax/search_clients.php?' . http_build_query(['q' => $sqlLikeInput]);
        $autocompleteResponse = $this->request($autocompletePath, 'GET', [], $session['cookie']);
        self::assertSame(200, $autocompleteResponse['status']);
        self::assertSame([], json_decode($autocompleteResponse['body'], true));
    }

    public function testTaskAndProfessionalSearchesDoNotBroadenForSqlLikeInput(): void
    {
        $session = $this->login();
        $sqlLikeInput = "' OR 1=1 -- ";

        $taskPath = '/tasks.php?' . http_build_query(['search' => $sqlLikeInput]);
        $taskResponse = $this->request($taskPath, 'GET', [], $session['cookie']);
        self::assertSame(200, $taskResponse['status']);
        self::assertStringNotContainsString('W01 Fixture Task', $taskResponse['body']);

        $professionalPath = '/active_professionals.php?' . http_build_query(['search' => $sqlLikeInput]);
        $professionalResponse = $this->request($professionalPath, 'GET', [], $session['cookie']);
        self::assertSame(200, $professionalResponse['status']);
        self::assertStringNotContainsString('W02-XSS-', $professionalResponse['body']);
    }

    public function testGetClientDeletionDoesNotChangeData(): void
    {
        $session = $this->login();
        $response = $this->request('/client_form.php?delete=910098', 'GET', [], $session['cookie']);

        self::assertSame(405, $response['status']);
        self::assertSame('W02 GET Delete Fixture', $this->clientName(910098));
    }

    public function testGetTaskDeletionDoesNotChangeData(): void
    {
        $session = $this->login();
        $response = $this->request('/task_form.php?delete=910001', 'GET', [], $session['cookie']);
        $task = $this->db->query('SELECT title FROM tasks WHERE id = 910001')->fetch_assoc();

        self::assertSame(405, $response['status']);
        self::assertSame('W01 Fixture Task', $task['title'] ?? null);
    }

    public function testValidCsrfPostCanDeleteTask(): void
    {
        $session = $this->login();
        $form = $this->request('/task_form.php?id=910001', 'GET', [], $session['cookie']);
        $token = $this->csrfToken($form['body']);
        $response = $this->request('/task_form.php', 'POST', [
            'delete_task' => 910001,
            '_csrf_token' => $token,
        ], $session['cookie']);

        self::assertSame(302, $response['status']);
        self::assertFalse((bool)$this->db->query('SELECT id FROM tasks WHERE id = 910001')->num_rows);
    }

    public function testAnotherUsersDiaryEventAndNoteAreNotRendered(): void
    {
        $session = $this->login();
        $response = $this->request('/mydiary.php?date=2026-11-17', 'GET', [], $session['cookie']);

        self::assertSame(200, $response['status']);
        self::assertStringNotContainsString('W02 Private Diary Event', $response['body']);
        self::assertStringNotContainsString('W02 Private Diary Note', $response['body']);
    }

    public function testAnotherUsersDiaryEventCannotBeUpdated(): void
    {
        $session = $this->login();
        $page = $this->request('/mydiary.php?date=2026-11-17', 'GET', [], $session['cookie']);
        $token = $this->csrfToken($page['body']);
        $response = $this->request('/update_event.php', 'POST', [
            'id' => 910099,
            'title' => 'W02 Attempted Private Event Change',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'description' => 'unauthorized update attempt',
            'event_date' => '2026-11-17',
            '_csrf_token' => $token,
        ], $session['cookie']);
        $event = $this->db->query('SELECT title FROM diary_events WHERE id = 910099')->fetch_assoc();

        self::assertSame(404, $response['status']);
        self::assertSame('W02 Private Diary Event', $event['title'] ?? null);
    }

    public function testAnotherUsersDiaryEventCannotBeDeleted(): void
    {
        $session = $this->login();
        $page = $this->request('/mydiary.php?date=2026-11-17', 'GET', [], $session['cookie']);
        $token = $this->csrfToken($page['body']);
        $response = $this->request('/mydiary.php?date=2026-11-17', 'POST', [
            'selected_date' => '2026-11-17',
            'delete_event' => 910099,
            '_csrf_token' => $token,
        ], $session['cookie']);
        $event = $this->db->query('SELECT title FROM diary_events WHERE id = 910099')->fetch_assoc();

        self::assertSame(404, $response['status']);
        self::assertSame('W02 Private Diary Event', $event['title'] ?? null);
    }

    public function testDiaryEventPreservesApostrophesWhenStored(): void
    {
        $session = $this->login();
        $date = '2026-11-18';
        $title = "W02 Apostrophe's Event";
        $description = "W02 description's apostrophe";
        $page = $this->request('/mydiary.php?date=' . $date, 'GET', [], $session['cookie']);
        $token = $this->csrfToken($page['body']);
        $response = $this->request('/mydiary.php', 'POST', [
            'event_date' => $date,
            'event_title' => $title,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'event_desc' => $description,
            'save_event' => '1',
            '_csrf_token' => $token,
        ], $session['cookie']);

        $stmt = $this->db->prepare('SELECT title, description FROM diary_events WHERE user_id = 910001 AND event_date = ? AND title = ?');
        $stmt->bind_param('ss', $date, $title);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();

        self::assertSame(302, $response['status']);
        self::assertSame($title, $event['title'] ?? null);
        self::assertSame($description, $event['description'] ?? null);
    }

    public function testAuthenticatedHeaderEscapesTheUsersFullname(): void
    {
        $fullname = '<script>window.w02Xss=1</script>';
        $stmt = $this->db->prepare('UPDATE users SET fullname = ? WHERE id = 910001');
        $stmt->bind_param('s', $fullname);
        $stmt->execute();

        $session = $this->login();
        $response = $this->request('/tasks.php', 'GET', [], $session['cookie']);

        self::assertSame(200, $response['status']);
        self::assertStringNotContainsString($fullname, $response['body']);
        self::assertStringContainsString('&lt;script&gt;window.w02Xss=1&lt;/script&gt;', $response['body']);
    }

    public function testSharedHeaderEscapesPartnerNameInDocumentTitle(): void
    {
        $name = 'W02 Title </title><script>window.crmXss=1</script>';
        $stmt = $this->db->prepare('UPDATE partners SET fullname = ? WHERE id = 910001');
        $stmt->bind_param('s', $name);
        $stmt->execute();

        $session = $this->login();
        $response = $this->request('/partner_tasks.php?id=910001', 'GET', [], $session['cookie']);

        self::assertSame(200, $response['status']);
        self::assertStringNotContainsString($name, $response['body']);
        self::assertStringContainsString(
            'W02 Title &lt;/title&gt;&lt;script&gt;window.crmXss=1&lt;/script&gt;',
            $response['body']
        );
    }

    public function testPartnerExcelExportEscapesUntrustedPartnerName(): void
    {
        $name = 'W02 Excel <img src=x onerror="window.crmXss=1">';
        $stmt = $this->db->prepare('UPDATE partners SET fullname = ? WHERE id = 910001');
        $stmt->bind_param('s', $name);
        $stmt->execute();

        $session = $this->login();
        $response = $this->request('/exports/export_analysis_partners_excel.php?partner_id=910001', 'GET', [], $session['cookie']);

        self::assertSame(200, $response['status']);
        self::assertStringNotContainsString($name, $response['body']);
        self::assertStringContainsString(
            'W02 Excel &lt;img src=x onerror=&quot;window.crmXss=1&quot;&gt;',
            $response['body']
        );
    }

    public function testDiaryValuesAreEscapedBeforeEmbeddingInInlineJavaScript(): void
    {
        $session = $this->login();
        $response = $this->request('/mydiary.php?date=2026-11-17', 'GET', [], $session['cookie']);

        self::assertSame(200, $response['status']);
        self::assertStringNotContainsString('W02 XSS " onclick="window.crmXss=1', $response['body']);
        self::assertStringContainsString('W02 XSS &quot; onclick=&quot;window.crmXss=1', $response['body']);
        self::assertStringNotContainsString('</script><script>window.crmXss=1</script>', $response['body']);
    }

    public function testActiveProfessionalFormEscapesUserValuesInAttributes(): void
    {
        $session = $this->login();
        $response = $this->request('/active_professional_form.php?id=910099', 'GET', [], $session['cookie']);
        $code = 'W02-XSS-" onfocus="window.crmXss=1';
        $fullname = 'W02 XSS <img src=x onerror="window.crmXss=1">';

        self::assertSame(200, $response['status']);
        self::assertStringNotContainsString($code, $response['body']);
        self::assertStringNotContainsString($fullname, $response['body']);
        self::assertStringContainsString('value="W02-XSS-&quot; onfocus=&quot;window.crmXss=1"', $response['body']);
        self::assertStringContainsString('value="W02 XSS &lt;img src=x onerror=&quot;window.crmXss=1&quot;&gt;"', $response['body']);
    }

    public function testImportDuplicateCodeErrorEscapesUntrustedCode(): void
    {
        require_once '/var/www/html/crm/vendor/autoload.php';

        $session = $this->login();
        $form = $this->request('/imports/import_active_professionals.php', 'GET', [], $session['cookie']);
        $token = $this->csrfToken($form['body']);
        $code = 'W02-XSS-" onfocus="window.crmXss=1';

        $workbook = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $workbook->getActiveSheet();
        $sheet->fromArray([[
            'Κωδικός', 'Ονοματεπώνυμο', 'Ενεργός', 'Ημερομηνία Έναρξης',
            'Ημερομηνία Διακοπής', 'ΦΠΑ', 'Τύπος ΦΠΑ', 'Μισθοδοσία',
        ]], null, 'A1');
        $sheet->fromArray([[$code, 'W02 Import Duplicate', 'ΝΑΙ', 'N/A', 'N/A', 'ΝΑΙ', 'Μηνιαίο', 'ΟΧΙ']], null, 'A2');
        $xlsxPath = tempnam(sys_get_temp_dir(), 'crm-w02-import-');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($workbook))->save($xlsxPath);
        $xlsx = file_get_contents($xlsxPath);
        unlink($xlsxPath);

        $response = $this->requestMultipart(
            '/imports/import_active_professionals.php',
            ['_csrf_token' => $token],
            'excel_file',
            'fixture.xlsx',
            $xlsx,
            $session['cookie']
        );

        self::assertSame(200, $response['status']);
        self::assertStringNotContainsString($code, $response['body']);
        self::assertStringContainsString(htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $response['body']);
    }

    public function testAjaxClientCreationDoesNotEmbedSubmittedNameAsExecutableScript(): void
    {
        $session = $this->login();
        $form = $this->request('/task_form.php', 'GET', [], $session['cookie']);
        $token = $this->csrfToken($form['body']);
        $name = 'W02 XSS </script><script>window.crmXss=1</script>';
        $response = $this->request('/save_client_ajax.php', 'POST', [
            'name' => $name,
            'afm' => '',
            'category' => 'Ιδιώτης',
            'phone' => '',
            'email' => '',
            '_csrf_token' => $token,
        ], $session['cookie']);

        self::assertSame(200, $response['status']);
        self::assertStringNotContainsString('</script><script>window.crmXss=1</script>', $response['body']);
        self::assertStringContainsString('window.parent.addClientToDropdown(', $response['body']);
        self::assertStringContainsString(
            '\\u003C/script\\u003E\\u003Cscript\\u003Ewindow.crmXss=1\\u003C/script\\u003E',
            str_replace('\\/', '/', $response['body'])
        );
    }

    private function login(): array
    {
        $page = $this->request('/login.php');
        $cookie = $page['cookie'];
        $token = $this->csrfToken($page['body']);
        $response = $this->request('/login.php', 'POST', [
            'username' => 'w01-fake-user',
            'password' => 'W01-Fictional-Password-2026',
            '_csrf_token' => $token,
        ], $cookie);

        self::assertSame(302, $response['status'], 'The fictional W01 account should authenticate.');
        self::assertNotSame('', $response['cookie'], 'Login should set or rotate the session cookie.');

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
        $body = file_get_contents(rtrim((string) getenv('CRM_BASE_URL'), '/') . $path, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $status = 0;
        $responseCookie = '';

        foreach ($responseHeaders as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                $status = (int) $matches[1];
            }
            if (stripos($header, 'Set-Cookie: PHPSESSID=') === 0) {
                $responseCookie = explode(';', trim(substr($header, strlen('Set-Cookie: '))))[0];
            }
        }

        return ['status' => $status, 'body' => $body ?: '', 'cookie' => $responseCookie];
    }

    private function requestMultipart(string $path, array $fields, string $fileField, string $filename, string $fileContents, string $cookie): array
    {
        $boundary = '----W02Boundary' . bin2hex(random_bytes(12));
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
            $body .= $value . "\r\n";
        }
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="' . $fileField . '"; filename="' . $filename . '"' . "\r\n";
        $body .= "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet\r\n\r\n";
        $body .= $fileContents . "\r\n";
        $body .= '--' . $boundary . "--\r\n";

        $headers = [
            'Content-Type: multipart/form-data; boundary=' . $boundary,
            'Content-Length: ' . strlen($body),
            'Cookie: ' . $cookie,
        ];
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'ignore_errors' => true,
            'follow_location' => 0,
        ]]);
        $responseBody = file_get_contents(rtrim((string) getenv('CRM_BASE_URL'), '/') . $path, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $status = 0;
        foreach ($responseHeaders as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                $status = (int) $matches[1];
            }
        }

        return ['status' => $status, 'body' => $responseBody ?: ''];
    }

    private function csrfToken(string $body, bool $required = true): string
    {
        if (preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $body, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if ($required) {
            self::fail('The page did not render its session CSRF token.');
        }

        return '';
    }

    private function putClient(int $id, string $name): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO clients (id, name, category) VALUES (?, ?, 'Ιδιώτης') " .
            'ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category)'
        );
        $stmt->bind_param('is', $id, $name);
        $stmt->execute();
    }

    private function putTaskFixture(): void
    {
        $id = 910001;
        $date = '2026-11-17';
        $title = 'W01 Fixture Task';
        $clientId = 910001;
        $partnerId = 910001;
        $status = 'Σε εξέλιξη';
        $fee = '100.00';
        $collected = '0.00';
        $stmt = $this->db->prepare(
            'INSERT INTO tasks (id, task_date, title, client_id, partner_id, status, fee, collected) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?) ' .
            'ON DUPLICATE KEY UPDATE task_date = VALUES(task_date), title = VALUES(title), ' .
            'client_id = VALUES(client_id), partner_id = VALUES(partner_id), status = VALUES(status), ' .
            'fee = VALUES(fee), collected = VALUES(collected)'
        );
        $stmt->bind_param('issiisss', $id, $date, $title, $clientId, $partnerId, $status, $fee, $collected);
        $stmt->execute();
    }

    private function putPrivateDiaryFixtures(): void
    {
        $userId = 910002;
        $username = 'w02-private-user';
        $passwordHash = password_hash('W02-Fictional-Password-2026', PASSWORD_DEFAULT);
        $fullname = 'W02 Fictional Private User';
        $role = 'user';
        $user = $this->db->prepare(
            'INSERT INTO users (id, username, password, fullname, role) VALUES (?, ?, ?, ?, ?) ' .
            'ON DUPLICATE KEY UPDATE password = VALUES(password), fullname = VALUES(fullname), role = VALUES(role)'
        );
        $user->bind_param('issss', $userId, $username, $passwordHash, $fullname, $role);
        $user->execute();

        $eventId = 910099;
        $date = '2026-11-17';
        $start = '09:00:00';
        $end = '10:00:00';
        $title = 'W02 Private Diary Event';
        $description = 'fictional private event content';
        $event = $this->db->prepare(
            'INSERT INTO diary_events (id, user_id, event_date, start_time, end_time, title, description) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), ' .
            'event_date = VALUES(event_date), start_time = VALUES(start_time), end_time = VALUES(end_time), ' .
            'title = VALUES(title), description = VALUES(description)'
        );
        $event->bind_param('iisssss', $eventId, $userId, $date, $start, $end, $title, $description);
        $event->execute();

        $note = 'W02 Private Diary Note';
        $noteStmt = $this->db->prepare(
            'INSERT INTO diary_notes (user_id, note_date, content) VALUES (?, ?, ?) ' .
            'ON DUPLICATE KEY UPDATE content = VALUES(content)'
        );
        $noteStmt->bind_param('iss', $userId, $date, $note);
        $noteStmt->execute();
    }

    private function putOwnDiaryXssFixture(): void
    {
        $id = 910098;
        $userId = 910001;
        $date = '2026-11-17';
        $start = '11:00:00';
        $end = '12:00:00';
        $title = 'W02 XSS " onclick="window.crmXss=1';
        $description = 'W02 XSS Description </script><script>window.crmXss=1</script>';
        $stmt = $this->db->prepare(
            'INSERT INTO diary_events (id, user_id, event_date, start_time, end_time, title, description) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), ' .
            'event_date = VALUES(event_date), start_time = VALUES(start_time), end_time = VALUES(end_time), ' .
            'title = VALUES(title), description = VALUES(description)'
        );
        $stmt->bind_param('iisssss', $id, $userId, $date, $start, $end, $title, $description);
        $stmt->execute();
    }

    private function putActiveProfessionalXssFixture(): void
    {
        $id = 910099;
        $code = 'W02-XSS-" onfocus="window.crmXss=1';
        $fullname = 'W02 XSS <img src=x onerror="window.crmXss=1">';
        $isActive = 1;
        $startDate = '2026-01-01';
        $endDate = null;
        $hasVat = 1;
        $vatType = 'Μηνιαίο';
        $hasPayroll = 0;
        $stmt = $this->db->prepare(
            'INSERT INTO active_professionals ' .
            '(id, code, fullname, is_active, start_date, end_date, has_vat, vat_type, has_payroll) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE code = VALUES(code), ' .
            'fullname = VALUES(fullname), is_active = VALUES(is_active), start_date = VALUES(start_date), ' .
            'end_date = VALUES(end_date), has_vat = VALUES(has_vat), vat_type = VALUES(vat_type), ' .
            'has_payroll = VALUES(has_payroll)'
        );
        $stmt->bind_param('ississisi', $id, $code, $fullname, $isActive, $startDate, $endDate, $hasVat, $vatType, $hasPayroll);
        $stmt->execute();
    }

    private function clientName(int $id): ?string
    {
        $stmt = $this->db->prepare('SELECT name FROM clients WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return $result['name'] ?? null;
    }

    private function assertTestDatabase(): void
    {
        if ($this->databaseName() !== 'crm_w01_test') {
            throw new RuntimeException('Refusing W02 CSRF fixtures outside crm_w01_test.');
        }
    }

    private function databaseName(): ?string
    {
        $result = $this->db->query('SELECT DATABASE()');
        return $result ? ($result->fetch_row()[0] ?? null) : null;
    }
}
