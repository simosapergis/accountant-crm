<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** AT-18/47: actual authenticated nested exports and their list filters. */
final class W08ExportsTest extends TestCase
{
    private mysqli $db;
    private int $clientId;
    private int $partnerId;
    private string $prefix;
    private string $cookie;

    protected function setUp(): void
    {
        self::assertTrue(CRM_TEST_DB_SAFE);
        $this->db = new mysqli((string)getenv('DB_HOST'), (string)getenv('DB_USER'), (string)getenv('DB_PASSWORD'), (string)getenv('DB_NAME'));
        self::assertSame(CRM_TEST_DATABASE, $this->db->query('SELECT DATABASE()')->fetch_row()[0]);
        $this->db->set_charset('utf8mb4');
        $this->prefix = 'W08-' . bin2hex(random_bytes(5));
        $stmt = $this->db->prepare("INSERT INTO clients (name, afm, category, phone, email) VALUES (?, ?, 'Ιδιώτης', '', '')");
        $name = $this->prefix . ' Πελάτης';
        $afm = $this->prefix . '123';
        $stmt->execute([$name, $afm]);
        $this->clientId = $this->db->insert_id;
        $stmt = $this->db->prepare('INSERT INTO partners (fullname) VALUES (?)');
        $stmt->execute([$this->prefix . ' Συνεργάτης']);
        $this->partnerId = $this->db->insert_id;
        $stmt = $this->db->prepare("INSERT INTO tasks (task_date, title, client_id, partner_id, status, fee, collected) VALUES (?, ?, ?, ?, 'Σε εξέλιξη', '123.45', '23.40')");
        $stmt->execute(['2026-01-12', $this->prefix . ' Παλιά εργασία', $this->clientId, null]);
        $stmt->execute(['2026-02-12', $this->prefix . ' Νέα εργασία', $this->clientId, $this->partnerId]);
        $stmt = $this->db->prepare('INSERT INTO active_professionals (code, fullname, is_active, has_vat, has_payroll) VALUES (?, ?, ?, 0, ?)');
        foreach ([['A', 1, 1], ['B', 1, 0], ['C', 0, 1]] as [$suffix, $active, $payroll]) {
            $stmt->execute([$this->prefix . $suffix, $this->prefix . ' Επαγγελματίας ' . $suffix, $active, $payroll]);
        }
        [$headers, $body] = $this->request('/login.php');
        preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $body, $match);
        [$headers] = $this->request('/login.php', $this->sessionCookie($headers), ['username' => 'w01-fake-user', 'password' => 'W01-Fictional-Password-2026', '_csrf_token' => $match[1] ?? '']);
        self::assertStringContainsString('302', $headers[0]);
        $this->cookie = $this->sessionCookie($headers);
    }

    protected function tearDown(): void
    {
        if (isset($this->clientId)) {
            $stmt = $this->db->prepare('DELETE FROM tasks WHERE client_id = ?');
            $stmt->execute([$this->clientId]);
            $stmt = $this->db->prepare('DELETE FROM clients WHERE id = ?');
            $stmt->execute([$this->clientId]);
        }
        if (isset($this->partnerId)) {
            $stmt = $this->db->prepare('DELETE FROM partners WHERE id = ?');
            $stmt->execute([$this->partnerId]);
        }
        if (isset($this->prefix)) {
            $stmt = $this->db->prepare('DELETE FROM active_professionals WHERE code LIKE ?');
            $stmt->execute([$this->prefix . '%']);
        }
        if (isset($this->db)) $this->db->close();
    }

    public function testEveryExistingExportReturnsItsExpectedFormatWithoutDiagnostics(): void
    {
        foreach (['active_professionals', 'analysis_partners', 'clients', 'tasks'] as $kind) {
            foreach (['excel', 'pdf'] as $format) {
                [$headers, $body] = $this->request('/exports/export_' . $kind . '_' . $format . '.php', $this->cookie);
                self::assertStringContainsString('200', $headers[0], $kind . '/' . $format);
                self::assertDoesNotMatchRegularExpression('/(?:Fatal error|Warning:|Deprecated:|Notice:)/', strip_tags($body));
                self::assertStringContainsString('no-store', implode("\n", $headers));
                if ($kind === 'active_professionals' && $format === 'pdf') {
                    self::assertStringStartsWith('%PDF-', $body);
                } elseif ($kind === 'active_professionals') {
                    self::assertStringStartsWith('PK', $body);
                } else {
                    self::assertStringContainsString('<table', $body);
                }
            }
        }
    }

    public function testTaskPrintRespectsCustomDateFilterAndHandlesUnassignedPartner(): void
    {
        [, $body] = $this->request('/exports/export_tasks_pdf.php?' . http_build_query(['client' => $this->clientId, 'date_range_type' => 'custom', 'date_from' => '2026-01-01', 'date_to' => '2026-01-31']), $this->cookie);
        self::assertStringContainsString($this->prefix . ' Παλιά εργασία', $body);
        self::assertStringNotContainsString($this->prefix . ' Νέα εργασία', $body);
        self::assertStringNotContainsString('Deprecated:', strip_tags($body));
    }

    public function testClientPrintMatchesAfmSearchUsedByListAndExcel(): void
    {
        [, $body] = $this->request('/exports/export_clients_pdf.php?search=' . urlencode($this->prefix . '123'), $this->cookie);
        self::assertStringContainsString($this->prefix . ' Πελάτης', $body);
        self::assertStringContainsString('246,90', $body);
    }

    public function testProfessionalExportsMatchSearchPayrollAndDefaultActiveFilters(): void
    {
        require_once CRM_TEST_APP_ROOT . '/vendor/autoload.php';
        foreach (['excel', 'pdf'] as $format) {
            $query = http_build_query(['search' => $this->prefix, 'payroll' => '1']);
            [, $body] = $this->request('/exports/export_active_professionals_' . $format . '.php?' . $query, $this->cookie);
            $text = $format === 'pdf' ? $this->pdfText($body) : $this->xlsxText($body);
            self::assertStringContainsString($this->prefix . 'A', $text);
            self::assertStringNotContainsString($this->prefix . 'B', $text, 'Payroll=1 must exclude non-payroll rows.');
            self::assertStringNotContainsString($this->prefix . 'C', $text, 'Default active filter must exclude inactive rows.');
            [, $body] = $this->request('/exports/export_active_professionals_' . $format . '.php?search=NoMatch' . $this->prefix, $this->cookie);
            $text = $format === 'pdf' ? $this->pdfText($body) : $this->xlsxText($body);
            self::assertStringNotContainsString($this->prefix, $text, 'Search must constrain both export formats.');
            [, $body] = $this->request('/exports/export_active_professionals_' . $format . '.php?' . http_build_query(['search' => $this->prefix, 'payroll' => '0']), $this->cookie);
            $text = $format === 'pdf' ? $this->pdfText($body) : $this->xlsxText($body);
            self::assertStringContainsString($this->prefix . 'B', $text);
            self::assertStringNotContainsString($this->prefix . 'A', $text);
            [, $body] = $this->request('/exports/export_active_professionals_' . $format . '.php?' . http_build_query(['search' => $this->prefix, 'is_active' => '0']), $this->cookie);
            $text = $format === 'pdf' ? $this->pdfText($body) : $this->xlsxText($body);
            self::assertStringContainsString($this->prefix . 'C', $text);
            self::assertStringNotContainsString($this->prefix . 'A', $text);
        }
    }

    public function testTotalsStayUnderTheirFinancialColumns(): void
    {
        foreach (['tasks_pdf' => ['client' => $this->clientId], 'analysis_partners_pdf' => ['partner_id' => $this->partnerId], 'analysis_partners_excel' => ['partner_id' => $this->partnerId]] as $route => $query) {
            [, $body] = $this->request('/exports/export_' . $route . '.php?' . http_build_query($query), $this->cookie);
            $doc = new DOMDocument();
            @$doc->loadHTML('<?xml encoding="UTF-8">' . $body);
            $xpath = new DOMXPath($doc);
            $headers = $xpath->query('//tr[th]')->item(0);
            self::assertNotNull($headers);
            $names = [];
            foreach ($headers->childNodes as $cell) if ($cell instanceof DOMElement) $names[] = trim($cell->textContent);
            $totals = $xpath->query('//tr[td[contains(., "Σύνολα")]]')->item(0);
            self::assertNotNull($totals);
            $values = [];
            foreach ($totals->childNodes as $cell) {
                if (!$cell instanceof DOMElement) continue;
                for ($i = 0; $i < max(1, (int)$cell->getAttribute('colspan')); $i++) $values[] = trim($cell->textContent);
            }
            self::assertCount(count($names), $values, $route . ' totals must occupy exactly the report columns.');
            $expectedFee = str_starts_with($route, 'tasks') ? '246,90' : '123,45';
            $expectedCollected = str_starts_with($route, 'tasks') ? '46,80' : '23,40';
            self::assertStringContainsString($expectedFee, $values[array_search('Αμοιβή', $names, true)], $route);
            self::assertStringContainsString($expectedCollected, $values[array_search('Είσπραξη', $names, true)], $route);
        }
    }

    public function testNestedImportNavigationAndAssetsResolveUnderCrm(): void
    {
        [, $body] = $this->request('/imports/import_active_professionals.php', $this->cookie);
        self::assertStringContainsString('href="/crm/assets/style.css"', $body);
        self::assertStringContainsString('href="/crm/tasks.php"', $body);
        self::assertStringContainsString('action="/crm/logout.php"', $body);
        self::assertStringNotContainsString('session_start():', $body);
    }

    public function testGreekWorkbookImportsThroughNestedUploadAndPersistsItsValues(): void
    {
        require_once CRM_TEST_APP_ROOT . '/vendor/autoload.php';
        [, $form] = $this->request('/imports/import_active_professionals.php', $this->cookie);
        preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $form, $matches);
        self::assertNotEmpty($matches[1] ?? '');
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $book->getActiveSheet()->fromArray([
            ['Κωδικός', 'Ονοματεπώνυμο', 'Ενεργός', 'Ημερομηνία Έναρξης', 'Ημερομηνία Διακοπής', 'ΦΠΑ', 'Τύπος ΦΠΑ', 'Μισθοδοσία'],
            [$this->prefix . 'IMPORT', 'Δοκιμαστικός Επαγγελματίας Ωμέγα', 'ΝΑΙ', '12/01/2026', '', 'ΝΑΙ', 'Μηνιαίο', 'ΟΧΙ'],
            [$this->prefix . 'IMPORT2', 'Δοκιμαστικός Επαγγελματίας Άλφα', 'ΝΑΙ', '', '', 'ΟΧΙ', '', 'ΟΧΙ'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'crm-w08-upload-');
        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
            $boundary = 'W08Boundary' . bin2hex(random_bytes(10));
            $body = '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"_csrf_token\"\r\n\r\n" . $matches[1] . "\r\n";
            $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"excel_file\"; filename=\"fictional.xlsx\"\r\nContent-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet\r\n\r\n";
            $body .= file_get_contents($path) . "\r\n--" . $boundary . "--\r\n";
            $context = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Cookie: ' . $this->cookie . "\r\nContent-Type: multipart/form-data; boundary=" . $boundary, 'content' => $body, 'ignore_errors' => true, 'follow_location' => 0]]);
            $response = file_get_contents(rtrim((string)getenv('CRM_BASE_URL'), '/') . '/imports/import_active_professionals.php', false, $context);
            self::assertStringContainsString('200', $http_response_header[0]);
            self::assertStringContainsString('Επιτυχής εισαγωγή: 2 εγγραφές.', (string)$response);
            $stmt = $this->db->prepare('SELECT fullname, is_active, start_date, has_vat, vat_type, has_payroll FROM active_professionals WHERE code = ?');
            $stmt->execute([$this->prefix . 'IMPORT']);
            self::assertSame(['fullname' => 'Δοκιμαστικός Επαγγελματίας Ωμέγα', 'is_active' => 1, 'start_date' => '2026-01-12', 'has_vat' => 1, 'vat_type' => 'Μηνιαίο', 'has_payroll' => 0], $stmt->get_result()->fetch_assoc());
            $stmt->execute([$this->prefix . 'IMPORT2']);
            self::assertSame(['fullname' => 'Δοκιμαστικός Επαγγελματίας Άλφα', 'is_active' => 1, 'start_date' => null, 'has_vat' => 0, 'vat_type' => null, 'has_payroll' => 0], $stmt->get_result()->fetch_assoc());
        } finally { unlink($path); }
    }

    public function testTaskListDeleteFormTargetsWorkingHandlerAndDeletesOnlySelectedTask(): void
    {
        [, $body] = $this->request('/tasks.php?client=' . $this->clientId, $this->cookie);
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $body);
        $xpath = new DOMXPath($doc);
        $form = $xpath->query('//form[button[@name="delete_task"]]')->item(0);
        self::assertInstanceOf(DOMElement::class, $form);
        self::assertSame('post', strtolower($form->getAttribute('method')));
        $button = $xpath->query('.//button[@name="delete_task"]', $form)->item(0);
        $token = $xpath->query('.//input[@name="_csrf_token"]', $form)->item(0);
        self::assertInstanceOf(DOMElement::class, $button);
        self::assertInstanceOf(DOMElement::class, $token);
        $target = $form->getAttribute('action');
        self::assertSame('task_form.php', basename($target));
        [$headers] = $this->request('/task_form.php', $this->cookie, ['delete_task' => $button->getAttribute('value'), '_csrf_token' => $token->getAttribute('value')]);
        self::assertStringContainsString('302', $headers[0]);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM tasks WHERE client_id = ?');
        $stmt->execute([$this->clientId]);
        self::assertSame(1, $stmt->get_result()->fetch_row()[0]);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM tasks WHERE id = ?');
        $stmt->execute([$button->getAttribute('value')]);
        self::assertSame(0, $stmt->get_result()->fetch_row()[0]);
    }

    private function xlsxText(string $body): string
    {
        $path = tempnam(sys_get_temp_dir(), 'crm-w08-xlsx-');
        try {
            file_put_contents($path, $body);
            $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            return json_encode($book->getActiveSheet()->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } finally { unlink($path); }
    }

    private function pdfText(string $body): string
    {
        self::assertStringStartsWith('%PDF-', $body);
        $path = tempnam(sys_get_temp_dir(), 'crm-w08-pdf-');
        try {
            file_put_contents($path, $body);
            exec('pdftotext -layout ' . escapeshellarg($path) . ' -', $lines, $status);
            self::assertSame(0, $status);
            return implode("\n", $lines);
        } finally { unlink($path); }
    }

    private function sessionCookie(array $headers): string
    {
        foreach ($headers as $header) if (stripos($header, 'Set-Cookie: PHPSESSID=') === 0) return explode(';', substr($header, 12))[0];
        self::fail('Session cookie missing.');
    }

    private function request(string $path, string $cookie = '', ?array $data = null): array
    {
        $context = stream_context_create(['http' => ['method' => $data === null ? 'GET' : 'POST', 'header' => 'Cookie: ' . $cookie . "\r\nContent-Type: application/x-www-form-urlencoded", 'content' => $data === null ? '' : http_build_query($data), 'ignore_errors' => true, 'follow_location' => 0]]);
        $body = file_get_contents(rtrim((string)getenv('CRM_BASE_URL'), '/') . $path, false, $context);
        return [$http_response_header ?? [], (string)$body];
    }
}
