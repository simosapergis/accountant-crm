<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class W02ExportSqlTest extends TestCase
{
    public function testTaskExportRejectsArraySearchFiltersWithoutServerError(): void
    {
        $response = $this->request('/exports/export_tasks_excel.php?search%5B%5D=fixture');

        self::assertSame(400, $response['status'], $response['body']);
    }

    public function testClientPdfExportRejectsArrayCategoryFilterWithoutServerError(): void
    {
        $response = $this->request('/exports/export_clients_pdf.php?category%5B%5D=fixture');

        self::assertSame(400, $response['status'], $response['body']);
    }

    public function testPartnerAnalysisExcelRejectsSqlLikePartnerIdentifier(): void
    {
        $response = $this->request('/exports/export_analysis_partners_excel.php?' . http_build_query([
            'partner_id' => '910001 OR 1=1',
        ]));

        self::assertSame(400, $response['status']);
        self::assertStringNotContainsString('W01 Fixture Task', $response['body']);
    }

    public function testPartnerAnalysisPdfRejectsSqlLikePartnerIdentifier(): void
    {
        $response = $this->request('/exports/export_analysis_partners_pdf.php?' . http_build_query([
            'partner_id' => '910001 OR 1=1',
        ]));

        self::assertSame(400, $response['status']);
        self::assertStringNotContainsString('W01 Fixture Task', $response['body']);
    }

    public function testPartnerAnalysisExportsUseTheSelectedNumericPartnerId(): void
    {
        $excel = $this->request('/exports/export_analysis_partners_excel.php?partner_id=910001');
        $pdf = $this->request('/exports/export_analysis_partners_pdf.php?partner_id=910001');

        self::assertSame(200, $excel['status']);
        self::assertStringContainsString('W01 Fixture Task', $excel['body']);
        self::assertSame(200, $pdf['status']);
        self::assertStringContainsString('W01 Fixture Task', $pdf['body']);
    }

    public function testTaskExportsTreatSqlLikeSearchAsFilterData(): void
    {
        $query = http_build_query(['search' => "' OR 1=1 --"]);
        $excel = $this->request('/exports/export_tasks_excel.php?' . $query);
        $pdf = $this->request('/exports/export_tasks_pdf.php?' . $query);

        self::assertSame(200, $excel['status']);
        self::assertStringNotContainsString('W01 Fixture Task', $excel['body']);
        self::assertSame(200, $pdf['status']);
        self::assertStringNotContainsString('W01 Fixture Task', $pdf['body']);
    }

    public function testClientExportsTreatSqlLikeCategoryAsFilterData(): void
    {
        $query = http_build_query(['category' => "Ιδιώτης' OR 1=1 --"]);
        $excel = $this->request('/exports/export_clients_excel.php?' . $query);
        $pdf = $this->request('/exports/export_clients_pdf.php?' . $query);

        self::assertSame(200, $excel['status']);
        self::assertStringNotContainsString('W01 Fixture Client', $excel['body']);
        self::assertSame(200, $pdf['status']);
        self::assertStringNotContainsString('W01 Fixture Client', $pdf['body']);
    }

    public function testClientExportsHonorListBalanceFilterValues(): void
    {
        $positiveExcel = $this->request('/exports/export_clients_excel.php?balance=positive');
        $zeroExcel = $this->request('/exports/export_clients_excel.php?balance=zero');
        $positivePdf = $this->request('/exports/export_clients_pdf.php?balance=positive');
        $zeroPdf = $this->request('/exports/export_clients_pdf.php?balance=zero');

        self::assertSame(200, $positiveExcel['status']);
        self::assertStringContainsString('W01 Fixture Client', $positiveExcel['body']);
        self::assertStringNotContainsString('W01 Fixture Client', $zeroExcel['body']);
        self::assertSame(200, $positivePdf['status']);
        self::assertStringContainsString('W01 Fixture Client', $positivePdf['body']);
        self::assertStringNotContainsString('W01 Fixture Client', $zeroPdf['body']);
    }

    public function testProfessionalExportsTreatSqlLikeVatTypeAsFilterData(): void
    {
        $query = http_build_query(['vat_type' => "Μηνιαίο' OR 1=1 --"]);
        $excel = $this->request('/exports/export_active_professionals_excel.php?' . $query);

        self::assertSame(200, $excel['status']);
        self::assertXlsxDoesNotContain($excel['body'], 'W02-XSS-');
    }

    public function testProfessionalExcelExportUsesTheListPageFilterNames(): void
    {
        $response = $this->request('/exports/export_active_professionals_excel.php?is_active=0&has_vat=1');

        self::assertSame(200, $response['status']);
        self::assertXlsxDoesNotContain($response['body'], 'W02-XSS-');
    }

    public function testProfessionalExportLinksEscapeQueryStringInHtmlAttribute(): void
    {
        $response = $this->request('/active_professionals.php?search=fictional&is_active=1');

        self::assertSame(200, $response['status']);
        self::assertStringContainsString('search=fictional&amp;is_active=1', $response['body']);
    }

    public function testActiveProfessionalExportsRejectArrayVatTypeFilter(): void
    {
        $excel = $this->request('/exports/export_active_professionals_excel.php?vat_type%5B%5D=fixture');
        $pdf = $this->request('/exports/export_active_professionals_pdf.php?vat_type%5B%5D=fixture');

        self::assertSame(400, $excel['status']);
        self::assertSame(400, $pdf['status']);
    }

    private function request(string $path): array
    {
        $cookie = $this->login();
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => 'Cookie: ' . $cookie,
            'ignore_errors' => true,
            'follow_location' => 0,
        ]]);
        $body = file_get_contents(rtrim((string) getenv('CRM_BASE_URL'), '/') . $path, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                $status = (int) $matches[1];
            }
        }

        return ['status' => $status, 'body' => $body ?: ''];
    }

    private function login(): string
    {
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0]]);
        file_get_contents(rtrim((string) getenv('CRM_BASE_URL'), '/') . '/login.php', false, $context);
        $cookie = '';
        $token = '';
        foreach ($http_response_header ?? [] as $header) {
            if (stripos($header, 'Set-Cookie: PHPSESSID=') === 0) {
                $cookie = explode(';', trim(substr($header, strlen('Set-Cookie: '))))[0];
            }
        }
        $pageContext = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => 'Cookie: ' . $cookie,
            'ignore_errors' => true,
            'follow_location' => 0,
        ]]);
        $page = file_get_contents(rtrim((string) getenv('CRM_BASE_URL'), '/') . '/login.php', false, $pageContext);
        if (preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $page ?: '', $matches)) {
            $token = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $post = http_build_query([
            'username' => 'w01-fake-user',
            'password' => 'W01-Fictional-Password-2026',
            '_csrf_token' => $token,
        ]);
        $loginContext = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Cookie: $cookie\r\nContent-Type: application/x-www-form-urlencoded",
            'content' => $post,
            'ignore_errors' => true,
            'follow_location' => 0,
        ]]);
        file_get_contents(rtrim((string) getenv('CRM_BASE_URL'), '/') . '/login.php', false, $loginContext);
        foreach ($http_response_header ?? [] as $header) {
            if (stripos($header, 'Set-Cookie: PHPSESSID=') === 0) {
                $cookie = explode(';', trim(substr($header, strlen('Set-Cookie: '))))[0];
            }
        }

        return $cookie;
    }

    private static function assertXlsxDoesNotContain(string $contents, string $needle): void
    {
        require_once '/var/www/html/crm/vendor/autoload.php';
        $path = tempnam(sys_get_temp_dir(), 'w02-export-');
        file_put_contents($path, $contents);
        try {
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
            $values = implode("\n", array_map(
                static fn(array $row): string => implode("\n", array_map('strval', $row)),
                $sheet->toArray()
            ));
            self::assertStringNotContainsString($needle, $values);
        } finally {
            unlink($path);
        }
    }
}
