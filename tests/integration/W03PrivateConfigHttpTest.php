<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** CFG-01/02, AT-56: real /crm HTTP and CLI share an external private source. */
final class W03PrivateConfigHttpTest extends TestCase
{
    private string $directory;
    private string $file;
    private string $baseUrl;
    private array $environment;
    private $server = null;

    protected function setUp(): void
    {
        self::assertTrue(CRM_TEST_DB_SAFE);
        self::assertSame(CRM_TEST_DATABASE, getenv('DB_NAME'));
        $db = new mysqli((string)getenv('DB_HOST'), (string)getenv('DB_USER'), (string)getenv('DB_PASSWORD'), CRM_TEST_DATABASE);
        self::assertSame(CRM_TEST_DATABASE, $db->query('SELECT DATABASE()')->fetch_row()[0]);
        $db->close();
        $this->directory = (string)realpath(sys_get_temp_dir()) . '/crm-private-http-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        mkdir($this->directory . '/web', 0700);
        symlink(CRM_TEST_APP_ROOT, $this->directory . '/web/crm');
        $this->file = $this->directory . '/private.php';
        $this->writeConfig([
            'DB_HOST' => (string)getenv('DB_HOST'), 'DB_NAME' => CRM_TEST_DATABASE,
            'DB_USER' => (string)getenv('DB_USER'), 'DB_PASSWORD' => (string)getenv('DB_PASSWORD'),
            'CRM_SECURITY_HMAC_KEY' => 'W03-fictional-private-http-hmac-key-2026',
            'APP_BASE_PATH' => '/crm', 'APP_TIMEZONE' => 'Europe/Athens',
        ]);
        $this->environment = array_merge(getenv(), [
            'APP_ENV' => 'test', 'CRM_CONFIG_FILE' => $this->file,
            'DB_HOST' => 'invalid.example.invalid', 'DB_PASSWORD' => 'wrong-environment-password',
            'CRM_SECURITY_HMAC_KEY' => 'short', 'APP_BASE_PATH' => '/wrong-environment-path',
        ]);
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        if (!isset($this->directory)) return;
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            if (!is_dir($file)) unlink($file);
        }
        unlink($this->directory . '/web/crm');
        rmdir($this->directory . '/web');
        rmdir($this->directory);
    }

    private function writeConfig(array $config): void
    {
        file_put_contents($this->file, '<?php return ' . var_export($config, true) . ';');
        chmod($this->file, 0600);
    }

    private function startServer(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->baseUrl = 'http://' . $address . '/crm';
        $this->server = proc_open([
            PHP_BINARY, '-d', 'display_errors=1', '-d', 'log_errors=1',
            '-S', $address, '-t', $this->directory . '/web', __DIR__ . '/../support/native-router.php',
        ], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $this->directory . '/stdout.log', 'w'], 2 => ['file', $this->directory . '/stderr.log', 'w']], $pipes, null, $this->environment);
        self::assertIsResource($this->server);
        $deadline = microtime(true) + 5;
        do {
            $connection = @stream_socket_client('tcp://' . $address, $error, $message, 0.1);
            if (is_resource($connection)) {
                fclose($connection);
                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        self::fail('Private-config HTTP test server did not start.');
    }

    private function request(string $path, array $data = [], string $cookie = ''): array
    {
        $curl = curl_init($this->baseUrl . $path);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 5]);
        if ($data !== []) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($data)]);
        if ($cookie !== '') curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        $response = curl_exec($curl);
        self::assertIsString($response);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $headerSize = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);
        return ['status' => $status, 'headers' => substr($response, 0, $headerSize), 'body' => substr($response, $headerSize)];
    }

    private function cli(string $script): array
    {
        $process = proc_open([PHP_BINARY, $script], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $this->environment);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $output];
    }

    public function testWebLoginAndCliMigrationUsePrivateDatabaseAndHmacDespiteConflictingEnvironment(): void
    {
        $this->startServer();
        $login = $this->request('/login.php');
        self::assertSame(200, $login['status']);
        self::assertSame(1, preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $login['body'], $token));
        self::assertSame(1, preg_match('/Set-Cookie: (PHPSESSID=[^;]+)/i', $login['headers'], $cookie));
        $saved = $this->request('/login.php', [
            '_csrf_token' => $token[1], 'username' => 'w01-fake-user', 'password' => 'W01-Fictional-Password-2026',
        ], $cookie[1]);
        self::assertSame(302, $saved['status']);
        self::assertStringContainsString('Location: index.php', $saved['headers']);
        self::assertStringContainsString('path=/crm/', $saved['headers']);
        self::assertSame([0, "Database schema is up to date.\n"], $this->cli(CRM_TEST_APP_ROOT . '/bin/migrate.php'));

        $db = new mysqli((string)getenv('DB_HOST'), (string)getenv('DB_USER'), (string)getenv('DB_PASSWORD'), CRM_TEST_DATABASE);
        $hash = hash_hmac('sha256', 'w01-fake-user', 'W03-fictional-private-http-hmac-key-2026');
        $event = $db->prepare("SELECT COUNT(*) FROM security_events WHERE event_type='login_success' AND identity_hash=?");
        $event->bind_param('s', $hash);
        $event->execute();
        self::assertGreaterThan(0, (int)$event->get_result()->fetch_row()[0]);
        $db->close();
        $denied = $this->request('/../private.php');
        self::assertContains($denied['status'], [403, 404]);
        self::assertStringNotContainsString('W03-fictional-private-http-hmac-key-2026', $denied['body']);
    }

    public function testInvalidPrivateConfigFailsClosedInHttpAndCliWithoutLoggingDetails(): void
    {
        file_put_contents($this->file, '<?php echo "W03-FICTIONAL-SECRET-OUTPUT"; throw new RuntimeException(__FILE__);');
        $this->startServer();
        $response = $this->request('/login.php');
        self::assertSame(503, $response['status']);
        self::assertSame("CRM configuration unavailable.\n", $response['body']);
        self::assertSame([78, "CRM configuration unavailable.\n"], $this->cli(CRM_TEST_APP_ROOT . '/bin/migrate.php'));
        $logs = file_get_contents($this->directory . '/stdout.log') . file_get_contents($this->directory . '/stderr.log');
        self::assertStringNotContainsString('W03-FICTIONAL-SECRET-OUTPUT', $logs);
        self::assertStringNotContainsString($this->file, $logs);
    }
}
