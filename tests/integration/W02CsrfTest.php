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
        if ($this->db->connect_errno === 0 && $this->databaseName() === CRM_TEST_DATABASE) {
            $ajaxFixtureName = 'W02 XSS </script><script>window.crmXss=1</script>';
            $deleteAjaxFixture = $this->db->prepare('DELETE FROM clients WHERE name = ?');
            $deleteAjaxFixture->bind_param('s', $ajaxFixtureName);
            $deleteAjaxFixture->execute();
            $this->db->query("DELETE FROM diary_events WHERE user_id = 910001 AND event_date = '2026-11-18' AND title LIKE 'W02 Apostrophe%'");
            $this->putTaskFixture();
            $this->db->query('DELETE FROM diary_events WHERE id = 910099');
            $this->db->query('DELETE FROM diary_events WHERE id = 910098');
            $this->db->query("DELETE FROM diary_notes WHERE user_id = 910002 AND note_date = '2026-11-17'");
            $this->db->query("DELETE FROM diary_notes WHERE user_id = 910001 AND note_date = '2026-11-17'");
            $this->db->query('DELETE FROM users WHERE id = 910002');
            $this->db->query("UPDATE users SET fullname = 'W01 Fictional User' WHERE id = 910001");
            $this->db->query("UPDATE partners SET fullname = 'W01 Fixture Collaborator' WHERE id = 910001");
            $this->db->query('DELETE FROM active_professionals WHERE id = 910099');
            $this->db->query('DELETE FROM clients WHERE id IN (910098, 910099)');
            $this->db->query('DELETE FROM tasks WHERE id IN (910096, 910097)');
            $this->db->query('DELETE FROM clients WHERE id = 910096');
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

    public function testLoginPreservesLeadingAndTrailingPasswordSpaces(): void
    {
        $id = 910003;
        $username = 'w02-space-password';
        $password = '  W02-Fictional-Password-With-Spaces  ';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $fullname = 'W02 Fictional Space Password User';
        $role = 'user';
        $user = $this->db->prepare(
            'INSERT INTO users (id, username, password, fullname, role) VALUES (?, ?, ?, ?, ?) ' .
            'ON DUPLICATE KEY UPDATE password = VALUES(password), fullname = VALUES(fullname), role = VALUES(role)'
        );
        $user->bind_param('issss', $id, $username, $hash, $fullname, $role);
        $user->execute();

        try {
            $page = $this->request('/login.php');
            $token = $this->csrfToken($page['body']);
            $login = $this->request('/login.php', 'POST', [
                'username' => $username,
                'password' => $password,
                '_csrf_token' => $token,
            ], $page['cookie']);

            self::assertSame(302, $login['status']);
            self::assertSame(200, $this->request('/tasks.php', 'GET', [], $login['cookie'])['status']);
        } finally {
            $delete = $this->db->prepare('DELETE FROM users WHERE id = ?');
            $delete->bind_param('i', $id);
            $delete->execute();
        }
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

    public function testAnonymousLogoutPostIsRejectedWithoutInvalidatingItsSession(): void
    {
        $loginPage = $this->request('/login.php');
        $anonymousCookie = $loginPage['cookie'];
        $token = $this->csrfToken($loginPage['body']);

        $logout = $this->request('/logout.php', 'POST', ['_csrf_token' => $token], $anonymousCookie);

        self::assertSame(302, $logout['status']);
        self::assertSame('/crm/login.php', $logout['location']);
        $loginPageAfter = $this->request('/login.php', 'GET', [], $anonymousCookie);
        self::assertSame($token, $this->csrfToken($loginPageAfter['body']), 'An anonymous request must not execute the authenticated logout action.');
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
            'title_selection' => 'custom', 'custom_title' => 'W02 User Role Task Edit',
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

    public function testTaskMoneyInputsStoreExactCentsAndRejectAmbiguousValues(): void
    {
        $session = $this->login();
        $form = $this->request('/task_form.php?id=910001', 'GET', [], $session['cookie']);
        $token = $this->csrfToken($form['body']);
        $base = [
            'id' => 910001, 'task_date' => '2026-11-17', 'title_selection' => 'custom', 'custom_title' => 'W02 Exact Money Fixture',
            'client_id' => 910001, 'partner_id' => 910001, 'status' => 'Σε εξέλιξη',
            'notes' => 'fictional money regression', 'save_task' => '1', '_csrf_token' => $token,
        ];

        foreach ([['100', '0', '100.00'], ['100,50', '0', '100.50'], ['100.50', '0', '100.50'], ['1.234,56', '0', '1234.56']] as [$fee, $collected, $expected]) {
            $response = $this->request('/task_form.php?id=910001', 'POST', $base + ['fee' => $fee, 'collected' => $collected], $session['cookie']);
            self::assertSame(302, $response['status']);
            $row = $this->db->query('SELECT fee FROM tasks WHERE id = 910001')->fetch_assoc();
            self::assertSame($expected, (string)($row['fee'] ?? ''));
        }

        $before = $this->db->query('SELECT fee FROM tasks WHERE id = 910001')->fetch_assoc()['fee'];
        $invalid = $this->request('/task_form.php?id=910001', 'POST', $base + ['fee' => '1.234', 'collected' => '0'], $session['cookie']);
        $after = $this->db->query('SELECT fee FROM tasks WHERE id = 910001')->fetch_assoc()['fee'];

        self::assertSame(200, $invalid['status']);
        self::assertStringContainsString('Παρακαλώ εισάγετε', $invalid['body']);
        self::assertSame((string)$before, (string)$after);
    }

    public function testClientFinancialTotalsDisplayExactCents(): void
    {
        $this->putClient(910096, 'W02 Exact Total Client');
        foreach ([[910096, '100.05', '0.01'], [910097, '0.05', '0.02']] as [$id, $fee, $collected]) {
            $date = '2026-11-18';
            $title = 'W02 Exact Total Task ' . $id;
            $clientId = 910096;
            $status = 'Σε εξέλιξη';
            $stmt = $this->db->prepare(
                'INSERT INTO tasks (id,task_date,title,client_id,status,fee,collected) VALUES (?,?,?,?,?,?,?)'
            );
            $stmt->bind_param('ississs', $id, $date, $title, $clientId, $status, $fee, $collected);
            $stmt->execute();
        }

        $session = $this->login();
        $response = $this->request('/clients.php?search=' . rawurlencode('W02 Exact Total Client'), 'GET', [], $session['cookie']);

        self::assertSame(200, $response['status']);
        self::assertSame(2, substr_count($response['body'], '100,10 €'));
        self::assertSame(2, substr_count($response['body'], '0,03 €'));
        self::assertSame(2, substr_count($response['body'], '100,07 €'));
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

    public function testGetDeletionIsRejectedAcrossPartnerProfessionalAndDiaryRoutes(): void
    {
        $session = $this->login();
        $requests = [
            '/partner_form.php?delete=910001',
            '/active_professional_form.php?delete=910099',
            '/active_professionals.php?delete=910099',
            '/mydiary.php?delete_event=910099',
        ];

        foreach ($requests as $path) {
            self::assertSame(405, $this->request($path, 'GET', [], $session['cookie'])['status'], $path);
        }

        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM partners WHERE id = 910001')->fetch_row()[0]);
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM active_professionals WHERE id = 910099')->fetch_row()[0]);
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM diary_events WHERE id = 910099')->fetch_row()[0]);
    }

    public function testDeletePostsWithoutCsrfAreRejectedAcrossAllExistingDeleteRoutes(): void
    {
        $session = $this->login();
        $requests = [
            ['/client_form.php', ['delete_client' => 910099]],
            ['/partner_form.php', ['delete_partner' => 910001]],
            ['/task_form.php', ['delete_task' => 910001]],
            ['/active_professional_form.php', ['delete_professional' => 910099]],
            ['/mydiary.php', ['delete_event' => 910099]],
        ];

        foreach ($requests as [$path, $payload]) {
            self::assertSame(403, $this->request($path, 'POST', $payload, $session['cookie'])['status'], $path);
        }

        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM clients WHERE id = 910099')->fetch_row()[0]);
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM partners WHERE id = 910001')->fetch_row()[0]);
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM tasks WHERE id = 910001')->fetch_row()[0]);
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM active_professionals WHERE id = 910099')->fetch_row()[0]);
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM diary_events WHERE id = 910099')->fetch_row()[0]);
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

    public function testSavingDiaryNoteCannotChangeAnotherUsersNote(): void
    {
        $session = $this->login();
        $page = $this->request('/mydiary.php?date=2026-11-17', 'GET', [], $session['cookie']);
        $token = $this->csrfToken($page['body']);
        $response = $this->request('/mydiary.php', 'POST', [
            'note_date' => '2026-11-17',
            'note_content' => 'W02 Attempted note change',
            'save_note' => '1',
            '_csrf_token' => $token,
        ], $session['cookie']);

        $foreign = $this->db->query('SELECT content FROM diary_notes WHERE user_id = 910002 AND note_date = \'2026-11-17\'')->fetch_assoc();
        $own = $this->db->query('SELECT content FROM diary_notes WHERE user_id = 910001 AND note_date = \'2026-11-17\'')->fetch_assoc();

        self::assertSame(302, $response['status']);
        self::assertSame('W02 Private Diary Note', $foreign['content'] ?? null);
        self::assertSame('W02 Attempted note change', $own['content'] ?? null);
    }

    public function testDiaryMutationsWithoutCsrfAreRejectedWithoutWrites(): void
    {
        $session = $this->login();
        $beforeEvents = (int)$this->db->query('SELECT COUNT(*) FROM diary_events WHERE user_id = 910001')->fetch_row()[0];
        $beforeNotes = (int)$this->db->query('SELECT COUNT(*) FROM diary_notes WHERE user_id = 910001')->fetch_row()[0];

        $requests = [
            ['/mydiary.php', ['save_note' => '1', 'note_date' => '2026-11-18', 'note_content' => 'forged note']],
            ['/mydiary.php', ['save_event' => '1', 'event_date' => '2026-11-18', 'event_title' => 'forged event']],
            ['/update_event.php', ['id' => 910098, 'event_date' => '2026-11-18', 'title' => 'forged update']],
        ];

        foreach ($requests as [$path, $payload]) {
            $response = $this->request($path, 'POST', $payload, $session['cookie']);
            self::assertSame(403, $response['status'], $path . ' mutation must require CSRF.');
        }

        self::assertSame($beforeEvents, (int)$this->db->query('SELECT COUNT(*) FROM diary_events WHERE user_id = 910001')->fetch_row()[0]);
        self::assertSame($beforeNotes, (int)$this->db->query('SELECT COUNT(*) FROM diary_notes WHERE user_id = 910001')->fetch_row()[0]);
    }

    public function testEveryPostMutationEntrypointRejectsMissingCsrfToken(): void
    {
        $session = $this->login();
        $routes = [
            '/client_form.php', '/partner_form.php', '/active_professional_form.php',
            '/task_form.php', '/mydiary.php', '/update_event.php', '/save_client_ajax.php',
            '/imports/import_active_professionals.php', '/payment_method_form.php',
            '/task_title_form.php', '/recurring_task_form.php',
        ];

        foreach ($routes as $route) {
            $response = $this->request($route, 'POST', ['name' => 'forged W02 mutation'], $session['cookie']);
            self::assertSame(403, $response['status'], $route . ' must reject POST without CSRF.');
        }
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

    public function testStoredNamesAndTaskTitlesAreEscapedAcrossListAndCalendarViews(): void
    {
        $payload = 'W02 XSS "><script>window.crmXss=1</script>';
        $client = $this->db->prepare('UPDATE clients SET name = ? WHERE id = 910099');
        $client->bind_param('s', $payload);
        $client->execute();
        $partner = $this->db->prepare('UPDATE partners SET fullname = ? WHERE id = 910001');
        $partner->bind_param('s', $payload);
        $partner->execute();
        $task = $this->db->prepare('UPDATE tasks SET title = ? WHERE id = 910001');
        $task->bind_param('s', $payload);
        $task->execute();

        $session = $this->login();
        $responses = [
            $this->request('/clients.php?search=W02', 'GET', [], $session['cookie']),
            $this->request('/partners.php?search=W02', 'GET', [], $session['cookie']),
            $this->request('/tasks.php?search=W02', 'GET', [], $session['cookie']),
            $this->request('/calendar.php?view=day&year=2026&month=11&day=17', 'GET', [], $session['cookie']),
        ];

        foreach ($responses as $response) {
            self::assertSame(200, $response['status']);
            self::assertStringNotContainsString('<script>window.crmXss=1</script>', $response['body']);
        }
        self::assertStringContainsString('&lt;script&gt;window.crmXss=1&lt;/script&gt;', $responses[2]['body']);
        self::assertStringContainsString('&lt;script&gt;window.crmXss=1&lt;/script&gt;', $responses[3]['body']);
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
        require_once CRM_TEST_APP_ROOT . '/vendor/autoload.php';

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

        self::assertSame(400, $response['status']);
        self::assertStringNotContainsString($code, $response['body']);
        self::assertStringContainsString(htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $response['body']);
    }

    public function testImportRejectsRenamedNonXlsxFileWithoutDatabaseChanges(): void
    {
        $session = $this->login();
        $form = $this->request('/imports/import_active_professionals.php', 'GET', [], $session['cookie']);
        $token = $this->csrfToken($form['body']);
        $before = (int)$this->db->query('SELECT COUNT(*) FROM active_professionals')->fetch_row()[0];

        $response = $this->requestMultipart(
            '/imports/import_active_professionals.php',
            ['_csrf_token' => $token],
            'excel_file',
            'renamed.csv.xlsx',
            "Κωδικός,Ονοματεπώνυμο\nW02-INVALID-FORMAT,No Import\n",
            $session['cookie']
        );

        $after = (int)$this->db->query('SELECT COUNT(*) FROM active_professionals')->fetch_row()[0];
        self::assertSame(400, $response['status']);
        self::assertStringNotContainsString('/tmp/', $response['body']);
        self::assertSame($before, $after);
    }

    public function testImportRejectsOversizedUploadWithoutDatabaseChanges(): void
    {
        $session = $this->login();
        $form = $this->request('/imports/import_active_professionals.php', 'GET', [], $session['cookie']);
        $token = $this->csrfToken($form['body']);
        $before = (int)$this->db->query('SELECT COUNT(*) FROM active_professionals')->fetch_row()[0];

        $response = $this->requestMultipart(
            '/imports/import_active_professionals.php',
            ['_csrf_token' => $token],
            'excel_file',
            'oversized.xlsx',
            str_repeat('x', 2 * 1024 * 1024 + 1),
            $session['cookie']
        );

        $after = (int)$this->db->query('SELECT COUNT(*) FROM active_professionals')->fetch_row()[0];
        self::assertSame(413, $response['status']);
        self::assertStringNotContainsString('/tmp/', $response['body']);
        self::assertSame($before, $after);
    }

    public function testImportRejectsCorruptXlsxWithoutExposingParserDetails(): void
    {
        $session = $this->login();
        $form = $this->request('/imports/import_active_professionals.php', 'GET', [], $session['cookie']);
        $token = $this->csrfToken($form['body']);
        $before = (int)$this->db->query('SELECT COUNT(*) FROM active_professionals')->fetch_row()[0];

        $response = $this->requestMultipart(
            '/imports/import_active_professionals.php',
            ['_csrf_token' => $token],
            'excel_file',
            'corrupt.xlsx',
            "PK\x03\x04W02-CORRUPT-XLSX-FIXTURE",
            $session['cookie']
        );

        $after = (int)$this->db->query('SELECT COUNT(*) FROM active_professionals')->fetch_row()[0];
        self::assertSame(400, $response['status']);
        self::assertStringContainsString('Δεν ήταν δυνατή η ανάγνωση', $response['body']);
        self::assertStringNotContainsString('/tmp/', $response['body']);
        self::assertSame($before, $after);
    }

    public function testImportRejectsTruncatedXlsxArchiveWithoutPartialWrites(): void
    {
        require_once CRM_TEST_APP_ROOT . '/vendor/autoload.php';
        $session = $this->login();
        $form = $this->request('/imports/import_active_professionals.php', 'GET', [], $session['cookie']);
        $token = $this->csrfToken($form['body']);
        $before = (int)$this->db->query('SELECT COUNT(*) FROM active_professionals')->fetch_row()[0];

        $workbook = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $workbook->getActiveSheet()->setCellValue('A1', 'truncated archive fixture');
        $path = tempnam(sys_get_temp_dir(), 'crm-w02-truncated-');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($workbook))->save($path);
        $xlsx = file_get_contents($path);
        unlink($path);
        $truncated = substr((string)$xlsx, 0, max(0, strlen((string)$xlsx) - 22));

        $response = $this->requestMultipart(
            '/imports/import_active_professionals.php',
            ['_csrf_token' => $token],
            'excel_file',
            'truncated.xlsx',
            $truncated,
            $session['cookie']
        );
        $after = (int)$this->db->query('SELECT COUNT(*) FROM active_professionals')->fetch_row()[0];

        self::assertSame(400, $response['status']);
        self::assertStringNotContainsString('/tmp/', $response['body']);
        self::assertSame($before, $after);
    }

    public function testImportRejectsWorkbookWithInvalidRowWithoutPartialInsert(): void
    {
        require_once CRM_TEST_APP_ROOT . '/vendor/autoload.php';
        $session = $this->login();
        $form = $this->request('/imports/import_active_professionals.php', 'GET', [], $session['cookie']);
        $token = $this->csrfToken($form['body']);
        $before = (int)$this->db->query('SELECT COUNT(*) FROM active_professionals')->fetch_row()[0];

        $workbook = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $workbook->getActiveSheet();
        $sheet->fromArray([[
            'Κωδικός', 'Ονοματεπώνυμο', 'Ενεργός', 'Ημερομηνία Έναρξης',
            'Ημερομηνία Διακοπής', 'ΦΠΑ', 'Τύπος ΦΠΑ', 'Μισθοδοσία',
        ]], null, 'A1');
        $sheet->fromArray([[
            'W02-IMPORT-AT17-GOOD', 'Fictional Imported Professional', 'ΝΑΙ', '', '', 'ΟΧΙ', '', 'ΟΧΙ',
        ]], null, 'A2');
        $sheet->fromArray([['', 'Missing Code', 'ΝΑΙ', '', '', 'ΟΧΙ', '', 'ΟΧΙ']], null, 'A3');
        $xlsxPath = tempnam(sys_get_temp_dir(), 'crm-w02-import-');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($workbook))->save($xlsxPath);

        try {
            $response = $this->requestMultipart(
                '/imports/import_active_professionals.php',
                ['_csrf_token' => $token],
                'excel_file',
                'partial-import.xlsx',
                file_get_contents($xlsxPath),
                $session['cookie']
            );
        } finally {
            unlink($xlsxPath);
            $cleanup = $this->db->prepare('DELETE FROM active_professionals WHERE code = ?');
            $cleanupCode = 'W02-IMPORT-AT17-GOOD';
            $cleanup->bind_param('s', $cleanupCode);
            $cleanup->execute();
        }

        $after = (int)$this->db->query('SELECT COUNT(*) FROM active_professionals')->fetch_row()[0];
        self::assertSame(400, $response['status']);
        self::assertSame($before, $after);
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
        $location = '';

        foreach ($responseHeaders as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                $status = (int) $matches[1];
            }
            if (stripos($header, 'Set-Cookie: PHPSESSID=') === 0) {
                $responseCookie = explode(';', trim(substr($header, strlen('Set-Cookie: '))))[0];
            }
            if (stripos($header, 'Location:') === 0) {
                $location = trim(substr($header, strlen('Location:')));
            }
        }

        return ['status' => $status, 'body' => $body ?: '', 'cookie' => $responseCookie, 'location' => $location];
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
        if ($this->databaseName() !== CRM_TEST_DATABASE) {
            throw new RuntimeException('Refusing W02 CSRF fixtures outside the verified test database.');
        }
    }

    private function databaseName(): ?string
    {
        $result = $this->db->query('SELECT DATABASE()');
        return $result ? ($result->fetch_row()[0] ?? null) : null;
    }
}
