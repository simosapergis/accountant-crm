<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class W01EnvironmentTest extends TestCase
{
    public function testMariaDbIsAvailable(): void
    {
        $db = new mysqli(
            (string) getenv('DB_HOST'),
            (string) getenv('DB_USER'),
            (string) getenv('DB_PASSWORD'),
            (string) getenv('DB_NAME')
        );

        self::assertSame('crm_w01_test', $db->query('SELECT DATABASE()')->fetch_row()[0]);
        self::assertStringStartsWith('10.11', $db->server_info);
    }

    public function testLoginPageIsServedFromTheNestedCrmPath(): void
    {
        $response = $this->get('/login.php');

        self::assertSame(200, $response['status']);
        self::assertStringContainsString('name="username"', $response['body']);
    }

    public function testProtectedTaskListRedirectsUnauthenticatedRequestsToNestedLogin(): void
    {
        $response = $this->get('/tasks.php');

        self::assertSame(302, $response['status']);
        self::assertSame('/crm/login.php', $response['headers']['location'] ?? null);
        self::assertStringNotContainsString('Εργασίες', $response['body']);
    }

    #[DataProvider('protectedDataEntrypoints')]
    public function testDataEntrypointsRequireAuthenticationAndUseNestedLogin(string $path): void
    {
        $response = $this->get($path);

        self::assertSame(302, $response['status'], $path . ' must redirect before exposing data.');
        self::assertSame('/crm/login.php', $response['headers']['location'] ?? null, $path . ' must retain the /crm/ base path.');
    }

    public static function protectedDataEntrypoints(): array
    {
        $routes = [
            'clients.php',
            'partners.php',
            'calendar.php',
            'active_professionals.php',
            'analysis_partners.php',
            'client_form.php',
            'partner_form.php',
            'partner_tasks.php',
            'active_professional_form.php',
            'task_form.php',
            'mydiary.php',
            'index.php',
            'save_client_ajax.php',
            'update_event.php',
            'ajax/search_clients.php',
            'imports/import_active_professionals.php',
            'exports/export_active_professionals_excel.php',
            'exports/export_active_professionals_pdf.php',
            'exports/export_analysis_partners_excel.php',
            'exports/export_analysis_partners_pdf.php',
            'exports/export_clients_excel.php',
            'exports/export_clients_pdf.php',
            'exports/export_tasks_excel.php',
            'exports/export_tasks_pdf.php',
        ];

        $cases = [];
        foreach ($routes as $route) {
            $cases[$route] = [$route];
        }

        return $cases;
    }

    #[DataProvider('fixtureBearingEntrypoints')]
    public function testUnauthenticatedResponsesDoNotRenderFictionalCrmRecords(string $path, string $fixture): void
    {
        $response = $this->get($path);

        self::assertFalse(
            str_contains($response['body'], $fixture),
            $path . ' rendered a fictional CRM fixture without authentication.'
        );
        self::assertSame(302, $response['status'], $path . ' must reject unauthenticated access.');
    }

    public static function fixtureBearingEntrypoints(): array
    {
        return [
            'tasks list' => ['tasks.php', 'W01 Fixture Task'],
            'clients list' => ['clients.php', 'W01 Fixture Client'],
            'partners list' => ['partners.php', 'W01 Fixture Collaborator'],
            'calendar' => ['calendar.php', 'W01 Fixture Task'],
        ];
    }

    public function testAccountCreationEntrypointIsNotAvailableOverHttp(): void
    {
        self::assertSame(404, $this->get('/create_user.php')['status']);
    }

    private function get(string $path): array
    {
        $path = '/' . ltrim($path, '/');
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
        ]);
        $body = file_get_contents(rtrim((string) getenv('CRM_BASE_URL'), '/') . $path, false, $context);
        $headers = $http_response_header ?? [];
        $status = 0;
        $parsedHeaders = [];

        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                $status = (int) $matches[1];
            } elseif (str_contains($header, ':')) {
                [$name, $value] = explode(':', $header, 2);
                $parsedHeaders[strtolower(trim($name))] = trim($value);
            }
        }

        return ['status' => $status, 'headers' => $parsedHeaders, 'body' => $body ?: ''];
    }
}
