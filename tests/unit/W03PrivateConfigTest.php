<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** CFG-01/02, AT-56: each process starts with an independent configuration cache. */
final class W03PrivateConfigTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = (string)realpath(sys_get_temp_dir()) . '/crm-config-test-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    private function validConfig(): array
    {
        return [
            'DB_HOST' => 'fictional-config-host', 'DB_NAME' => 'crm_test_private_config',
            'DB_USER' => 'fictional-config-user', 'DB_PASSWORD' => '  Fictional-Private-Password  ',
            'CRM_SECURITY_HMAC_KEY' => 'Fictional-private-HMAC-key-at-least-32-characters',
        ];
    }

    private function fixture(array $values, string $name = 'accountant-crm.php'): string
    {
        return $this->source('<?php return ' . var_export($values, true) . ';', $name);
    }

    private function source(string $source, string $name = 'accountant-crm.php'): string
    {
        $file = $this->directory . '/' . $name;
        file_put_contents($file, $source);
        chmod($file, 0600);
        return $file;
    }

    /** @return array{status:int,stdout:string,stderr:string} */
    private function runConfig(string $code, array $environment = [], ?string $iniFile = null): array
    {
        $env = getenv();
        foreach (array_keys($env) as $key) {
            if (str_starts_with($key, 'DB_') || str_starts_with($key, 'CRM_') || str_starts_with($key, 'APP_')) {
                unset($env[$key]);
            }
        }
        $env = array_merge($env, ['APP_ENV' => 'production'], $environment);
        $command = [PHP_BINARY, '-d', 'display_errors=1', '-d', 'log_errors=1', '-d', 'error_log=/dev/stderr'];
        array_push($command, '-d', 'crm.config_file=' . ($iniFile ?? ''));
        array_push($command, '-r', 'require ' . var_export(CRM_TEST_APP_ROOT . '/includes/config.php', true) . '; ' . $code);
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return ['status' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private function assertUnavailable(array $result): void
    {
        self::assertSame(78, $result['status']);
        self::assertSame('CRM configuration unavailable.', trim($result['stdout'] . $result['stderr']));
    }

    public function testPrivateFileIsAuthoritativeAndProvidesSafeDefaults(): void
    {
        $values = $this->validConfig();
        $file = $this->fixture($values);
        $result = $this->runConfig('echo json_encode([crm_config("DB_HOST"), crm_config("DB_PASSWORD"), crm_config("CRM_SECURITY_HMAC_KEY"), crm_config("APP_BASE_PATH"), crm_config("APP_TIMEZONE"), crm_config("APP_ENV")]);', [
            'CRM_CONFIG_FILE' => $file, 'DB_HOST' => 'wrong-env-host', 'DB_PASSWORD' => 'wrong-env-password',
            'CRM_SECURITY_HMAC_KEY' => 'wrong-env-hmac-key-long-enough-for-validation',
        ]);
        self::assertSame(0, $result['status']);
        self::assertSame('', $result['stderr']);
        self::assertSame([$values['DB_HOST'], $values['DB_PASSWORD'], $values['CRM_SECURITY_HMAC_KEY'], '/crm', 'Europe/Athens', 'production'], json_decode($result['stdout'], true));
    }

    public function testCustomIniSelectionTakesPrecedenceOverEnvironmentPath(): void
    {
        $iniFile = $this->fixture($this->validConfig(), 'ini-selected.php');
        $result = $this->runConfig('echo crm_config("DB_HOST");', ['CRM_CONFIG_FILE' => $this->directory . '/missing.php'], $iniFile);
        self::assertSame(0, $result['status']);
        self::assertSame('fictional-config-host', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    public function testCliWithoutDocumentRootDoesNotTreatItsWorkingDirectoryAsPublic(): void
    {
        $file = $this->fixture($this->validConfig());
        $result = $this->runConfig('$_SERVER["DOCUMENT_ROOT"]=""; chdir(' . var_export($this->directory, true) . '); echo crm_config("DB_HOST");', ['CRM_CONFIG_FILE' => $file]);
        self::assertSame(0, $result['status']);
        self::assertSame('fictional-config-host', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    public function testExplicitMissingFileDoesNotFallBackToTestEnvironment(): void
    {
        $this->assertUnavailable($this->runConfig('echo crm_config("DB_HOST");', array_merge($this->validConfig(), [
            'APP_ENV' => 'test', 'CRM_CONFIG_FILE' => $this->directory . '/missing.php',
        ])));
    }

    public function testProductionCannotUseEnvironmentCredentialsWithoutPrivateFile(): void
    {
        $this->assertUnavailable($this->runConfig('echo crm_config("DB_HOST");', $this->validConfig()));
    }

    public function testTestEnvironmentReadsFreshValuesAndRemainsProcessOnly(): void
    {
        $result = $this->runConfig('echo crm_config("DB_HOST"), "|"; putenv("DB_HOST=second-fictional-host"); echo crm_config("DB_HOST"), "|", crm_config("APP_ENV");', [
            'APP_ENV' => 'test', 'DB_HOST' => 'first-fictional-host',
        ]);
        self::assertSame(0, $result['status']);
        self::assertSame('first-fictional-host|second-fictional-host|test', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    public function testPrivateFileCacheIsStableWithinOneProcess(): void
    {
        $file = $this->fixture($this->validConfig());
        $replacement = '<?php return ' . var_export(array_merge($this->validConfig(), ['DB_HOST' => 'changed-file-host']), true) . ';';
        $result = $this->runConfig('echo crm_config("DB_HOST"), "|"; file_put_contents(' . var_export($file, true) . ', ' . var_export($replacement, true) . '); echo crm_config("DB_HOST");', ['CRM_CONFIG_FILE' => $file]);
        self::assertSame(0, $result['status']);
        self::assertSame('fictional-config-host|fictional-config-host', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    public function testDevelopmentEnvironmentCanUseEnvironmentValuesAndOptionalNumericDefaultIsEmpty(): void
    {
        $result = $this->runConfig('echo crm_config("DB_USER"), "|", crm_config("CRM_LOGIN_WINDOW_SECONDS");', [
            'APP_ENV' => 'development', 'DB_USER' => 'fictional-development-user',
        ]);
        self::assertSame(0, $result['status']);
        self::assertSame('fictional-development-user|', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    public function testMissingEnvironmentModeDoesNotEnableEnvironmentFallback(): void
    {
        $this->assertUnavailable($this->runConfig('echo crm_config("DB_HOST");', array_merge($this->validConfig(), ['APP_ENV' => ''])));
    }

    public function testPrivateFileKeepsProcessTestModeAndDoesNotMergeOptionalEnvironmentSettings(): void
    {
        $file = $this->fixture($this->validConfig());
        $result = $this->runConfig('echo crm_config("APP_ENV"), "|", crm_config("CRM_LOGIN_WINDOW_SECONDS");', [
            'CRM_CONFIG_FILE' => $file, 'APP_ENV' => 'test', 'CRM_LOGIN_WINDOW_SECONDS' => '123',
        ]);
        self::assertSame(0, $result['status']);
        self::assertSame('test|', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    public function testEnvironmentModeValidatesRequestedMandatoryAndNumericValues(): void
    {
        foreach (['DB_PASSWORD', 'CRM_SECURITY_HMAC_KEY', 'CRM_LOGIN_WINDOW_SECONDS'] as $key) {
            $this->assertUnavailable($this->runConfig('echo crm_config(' . var_export($key, true) . ');', [
                'APP_ENV' => 'test', 'DB_HOST' => 'fictional-test-host', $key => $key === 'CRM_LOGIN_WINDOW_SECONDS' ? '0' : '',
            ]));
        }
    }

    public function testDirectoryAndSymlinkIntoDocumentRootAreRejected(): void
    {
        $this->assertUnavailable($this->runConfig('echo crm_config("DB_HOST");', ['CRM_CONFIG_FILE' => $this->directory]));
        $file = $this->fixture($this->validConfig());
        $link = $this->directory . '/linked.php';
        symlink($file, $link);
        $this->assertUnavailable($this->runConfig('$_SERVER["DOCUMENT_ROOT"]=' . var_export($this->directory, true) . '; echo crm_config("DB_HOST");', ['CRM_CONFIG_FILE' => $link]));
    }

    public static function invalidValues(): array
    {
        return [
            'missing database password' => ['DB_PASSWORD', null],
            'empty database host' => ['DB_HOST', ''],
            'non-string database name' => ['DB_NAME', 12],
            'short HMAC key' => ['CRM_SECURITY_HMAC_KEY', 'short-fictional-key'],
            'non-string HMAC key' => ['CRM_SECURITY_HMAC_KEY', ['fictional']],
            'unknown key' => ['UNKNOWN_CONFIG_KEY', 'fictional'],
            'file cannot spoof test mode' => ['APP_ENV', 'test'],
            'unsupported timezone' => ['APP_TIMEZONE', 'UTC'],
            'base path cannot be URL' => ['APP_BASE_PATH', 'https://example.invalid/crm'],
            'base path cannot contain traversal' => ['APP_BASE_PATH', '/crm/../private'],
            'base path cannot contain query' => ['APP_BASE_PATH', '/crm?debug=1'],
            'numeric zero' => ['CRM_LOGIN_IDENTITY_LIMIT', 0],
            'numeric negative' => ['CRM_LOGIN_WINDOW_SECONDS', '-1'],
            'numeric over maximum' => ['CRM_SECURITY_RETENTION_DAYS', 31536001],
            'numeric float' => ['CRM_LOGIN_SOURCE_LIMIT', 1.5],
            'numeric noncanonical' => ['CRM_LOGIN_COOLDOWN_SECONDS', '01'],
            'numeric explicitly empty' => ['CRM_LOGIN_COOLDOWN_SECONDS', ''],
        ];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidPrivateSettingsFailWithoutDetails(string $key, mixed $value): void
    {
        $values = $this->validConfig();
        if ($value === null) unset($values[$key]);
        else $values[$key] = $value;
        $this->assertUnavailable($this->runConfig('echo crm_config("DB_HOST");', ['CRM_CONFIG_FILE' => $this->fixture($values)]));
    }

    public function testIntegerAndCanonicalStringNumericSettingsAreAccepted(): void
    {
        $file = $this->fixture(array_merge($this->validConfig(), ['CRM_LOGIN_IDENTITY_LIMIT' => 1, 'CRM_LOGIN_WINDOW_SECONDS' => '31536000']));
        $result = $this->runConfig('echo crm_config("CRM_LOGIN_IDENTITY_LIMIT"), "|", crm_config("CRM_LOGIN_WINDOW_SECONDS");', ['CRM_CONFIG_FILE' => $file]);
        self::assertSame(0, $result['status']);
        self::assertSame('1|31536000', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    public static function unsafeSources(): array
    {
        return [
            'wrong return type' => ['<?php return "Fictional-secret-invalid-type";'],
            'output is rejected' => ['<?php echo "Fictional-secret-output"; return [];'],
            'parse error is sanitized' => ['<?php return ["Fictional-secret-parse" => ;'],
            'exception is sanitized' => ['<?php throw new RuntimeException("Fictional-secret-exception");'],
            'warning is sanitized' => ['<?php trigger_error("Fictional-secret-warning", E_USER_WARNING); return [];'],
        ];
    }

    #[DataProvider('unsafeSources')]
    public function testLoadFailuresSuppressOutputWarningsAndExceptionDetails(string $source): void
    {
        $this->assertUnavailable($this->runConfig('echo crm_config("DB_HOST");', ['CRM_CONFIG_FILE' => $this->source($source)]));
    }

    public function testFileInsideDocumentRootIsRejected(): void
    {
        $file = $this->fixture($this->validConfig());
        $this->assertUnavailable($this->runConfig('$_SERVER["DOCUMENT_ROOT"]=' . var_export($this->directory, true) . '; echo crm_config("DB_HOST");', ['CRM_CONFIG_FILE' => $file]));
    }

    public function testOpenBasedirFailureDoesNotExposePrivateFilesystemPaths(): void
    {
        $file = $this->fixture($this->validConfig());
        $this->assertUnavailable($this->runConfig('ini_set("open_basedir", ' . var_export(CRM_TEST_APP_ROOT, true) . '); echo crm_config("DB_HOST");', ['CRM_CONFIG_FILE' => $file]));
    }

    public function testValidPrivateConfigWorksWithinRestrictedHostingDirectories(): void
    {
        $file = $this->fixture($this->validConfig());
        $allowedDirectories = dirname(CRM_TEST_APP_ROOT) . PATH_SEPARATOR . $this->directory;
        $result = $this->runConfig('ini_set("open_basedir", ' . var_export($allowedDirectories, true) . '); echo crm_config("DB_HOST");', ['CRM_CONFIG_FILE' => $file]);
        self::assertSame(0, $result['status']);
        self::assertSame('fictional-config-host', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    public function testWordpressConfigFilenameIsRejected(): void
    {
        $this->assertUnavailable($this->runConfig('echo crm_config("DB_HOST");', ['CRM_CONFIG_FILE' => $this->fixture($this->validConfig(), 'wp-config.php')]));
    }

    public function testWorldReadableAndGroupWritablePrivateFilesAreRejected(): void
    {
        $file = $this->fixture($this->validConfig());
        foreach ([0644, 0620] as $mode) {
            chmod($file, $mode);
            $this->assertUnavailable($this->runConfig('echo crm_config("DB_HOST");', ['CRM_CONFIG_FILE' => $file]));
        }
    }

    public function testRelativeAndStreamPathsAreRejected(): void
    {
        foreach (['relative/private.php', 'php://memory'] as $path) {
            $this->assertUnavailable($this->runConfig('echo crm_config("DB_HOST");', ['CRM_CONFIG_FILE' => $path]));
        }
    }
}
