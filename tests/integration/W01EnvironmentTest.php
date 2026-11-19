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

        self::assertSame(CRM_TEST_DATABASE, $db->query('SELECT DATABASE()')->fetch_row()[0]);
        self::assertStringStartsWith('10.11', $db->server_info);
    }

    public function testLoginPageIsServedFromTheNestedCrmPath(): void
    {
        $response = $this->get('/login.php');

        self::assertSame(200, $response['status']);
        self::assertStringContainsString('name="username"', $response['body']);
    }

    /** OPS-06, AT-48: discourage indexing on public and protected CRM responses. */
    public function testCrmResponsesCarryNoIndexDirective(): void
    {
        foreach (['/login.php', '/tasks.php', '/exports/export_tasks_excel.php'] as $path) {
            $response = $this->get($path);

            self::assertContains($response['status'], [200, 302], $path . ' should be a normal page or auth redirect.');
            self::assertStringContainsString(
                'noindex',
                strtolower($response['headers']['x-robots-tag'] ?? ''),
                $path . ' must tell crawlers not to index CRM responses.'
            );
        }
    }

    public function testEveryPhpEntrypointRequiresAuthenticationOrIsInternal(): void
    {
        $root = CRM_TEST_APP_ROOT;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        $checked = 0;

        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root) + 1);
            if ($relative === 'login.php' || $relative === 'create_user.php' || str_starts_with($relative, 'vendor/')) {
                continue;
            }

            $response = $this->get('/' . $relative);
            self::assertContains($response['status'], [302, 403, 404, 405], $relative . ' must not be publicly callable.');
            self::assertStringNotContainsString('W01 Fixture', $response['body']);
            $checked++;
        }

        self::assertGreaterThan(20, $checked, 'The unauthenticated entrypoint inventory should be broad.');
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
        self::assertContains($this->get('/create_user.php')['status'], [403, 404]);
    }

    #[DataProvider('sensitivePaths')]
    public function testSensitiveFilesAndInternalDirectoriesAreNotServed(string $path): void
    {
        $response = $this->get($path);

        self::assertContains($response['status'], [403, 404], $path . ' must not be served over HTTP.');
        self::assertStringNotContainsString('DB_PASSWORD=', $response['body']);
        self::assertStringNotContainsString('W02-SECRET-MARKER', $response['body']);
    }

    public static function sensitivePaths(): array
    {
        return [
            'dotenv above app root' => ['/../.env'],
            'database dump above app root' => ['/../accountant_crm.sql'],
            'database backups' => ['/backups/accountant_crm.sql'],
            'application logs' => ['/logs/application.log'],
            'root backup artifact' => ['/accountant_crm.backup'],
            'database include' => ['/includes/db.php'],
            'composer manifest' => ['/composer.json'],
            'composer lock' => ['/composer.lock'],
            'vendor metadata' => ['/vendor/composer/installed.json'],
            'CLI account tool' => ['/create_user.php'],
            'installer route' => ['/install.php'],
        ];
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
