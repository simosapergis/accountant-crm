<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** OPS-03/04, AT-47: exercise the actual nested export after login. */
final class W08PdfTest extends TestCase
{
    public function testProfessionalPdfContainsReadableGreekText(): void
    {
        [$headers, $body] = $this->request('/login.php');
        preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $body, $matches);
        self::assertNotEmpty($matches[1] ?? '');
        [$headers] = $this->request('/login.php', $this->cookie($headers), [
            'username' => 'w01-fake-user',
            'password' => 'W01-Fictional-Password-2026',
            '_csrf_token' => $matches[1],
        ]);
        self::assertStringContainsString('302', $headers[0]);
        [$headers, $body] = $this->request('/exports/export_active_professionals_pdf.php', $this->cookie($headers));
        self::assertStringStartsWith('%PDF-', $body, 'The export must return PDF bytes, without PHP diagnostics.');
        self::assertStringContainsString('application/pdf', implode("\n", $headers));
        self::assertStringContainsString('no-store', implode("\n", $headers));
        $path = tempnam(sys_get_temp_dir(), 'crm-w08-pdf-');
        try {
            file_put_contents($path, $body);
            $command = 'pdftotext -layout ' . escapeshellarg($path) . ' -';
            exec($command, $lines, $status);
            self::assertSame(0, $status, 'Poppler pdftotext is required for Greek PDF acceptance.');
            self::assertStringContainsString('ΑΝΑΦΟΡΑ ΕΝΕΡΓΩΝ ΕΠΑΓΓΕΛΜΑΤΙΩΝ', implode("\n", $lines));
        } finally {
            unlink($path);
        }
    }

    private function cookie(array $headers): string
    {
        foreach ($headers as $header) {
            if (stripos($header, 'Set-Cookie: PHPSESSID=') === 0) {
                return explode(';', substr($header, strlen('Set-Cookie: ')))[0];
            }
        }
        self::fail('Expected a session cookie.');
    }

    private function request(string $path, string $cookie = '', ?array $data = null): array
    {
        $context = stream_context_create(['http' => [
            'method' => $data === null ? 'GET' : 'POST',
            'header' => 'Cookie: ' . $cookie . "\r\nContent-Type: application/x-www-form-urlencoded",
            'content' => $data === null ? '' : http_build_query($data),
            'ignore_errors' => true,
            'follow_location' => 0,
        ]]);
        $body = file_get_contents(rtrim((string)getenv('CRM_BASE_URL'), '/') . $path, false, $context);
        return [$http_response_header ?? [], (string)$body];
    }
}
