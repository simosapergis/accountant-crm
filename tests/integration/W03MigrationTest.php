<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once CRM_TEST_APP_ROOT . '/includes/migration_runner.php';

final class W03MigrationTest extends TestCase
{
    public function testLegacyRecordsAndSchemaSurviveMigrationAndSecondRunIsEmpty(): void
    {
        self::assertTrue(CRM_TEST_DB_SAFE);
        self::assertSame(CRM_TEST_DATABASE, getenv('DB_NAME'));
        $target = new mysqli((string)getenv('DB_HOST'), (string)getenv('DB_USER'), (string)getenv('DB_PASSWORD'), CRM_TEST_DATABASE);
        self::assertSame(CRM_TEST_DATABASE, $target->query('SELECT DATABASE()')->fetch_row()[0]);
        $account = (string)$target->query('SELECT CURRENT_USER()')->fetch_row()[0];
        $accountSeparator = strrpos($account, '@');
        self::assertNotFalse($accountSeparator);
        $accountUser = substr($account, 0, $accountSeparator);
        $accountHost = substr($account, $accountSeparator + 1);
        self::assertSame((string)getenv('DB_USER'), $accountUser);
        self::assertNotSame('root', $accountUser);
        $serverIdentityQuery = 'SELECT @@hostname, @@port, @@socket, @@datadir';
        $targetServer = $target->query($serverIdentityQuery)->fetch_assoc();
        $target->close();
        self::assertSame('root', (string)getenv('CRM_TEST_ADMIN_USER'));
        $adminHost = (string)(getenv('CRM_TEST_ADMIN_HOST') ?: getenv('DB_HOST'));
        $admin = new mysqli($adminHost, (string)getenv('CRM_TEST_ADMIN_USER'), (string)getenv('CRM_TEST_ADMIN_PASSWORD'));
        self::assertSame($targetServer, $admin->query($serverIdentityQuery)->fetch_assoc());
        $database = 'crm_test_w03_' . bin2hex(random_bytes(6));
        $admin->query("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $testUser = (string)getenv('DB_USER');
        $admin->query("GRANT ALL PRIVILEGES ON `$database`.* TO '" . $admin->real_escape_string($accountUser) . "'@'" . $admin->real_escape_string($accountHost) . "'");
        $admin->close();
        $db = new mysqli((string)getenv('DB_HOST'), $testUser, (string)getenv('DB_PASSWORD'), $database);
        self::assertSame($database, $db->query('SELECT DATABASE()')->fetch_row()[0]);
        $legacySql = file_get_contents(dirname(__DIR__, 2) . '/accountant_crm.sql');
        self::assertIsString($legacySql);
        self::assertStringNotContainsString('DROP DATABASE', strtoupper($legacySql));
        if ($db->multi_query($legacySql)) {
            do { if ($result = $db->store_result()) { $result->free(); } } while ($db->more_results() && $db->next_result());
        }
        $freshUsersCharset = $db->query("SELECT CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'fullname'")->fetch_row()[0];
        self::assertSame('utf8mb4', $freshUsersCharset, 'Fresh schema must store account names as Unicode.');

        // Recreate the pre-migration latin1 definition to exercise upgrades from existing installations.
        $db->query('ALTER TABLE users CONVERT TO CHARACTER SET latin1 COLLATE latin1_swedish_ci');
        $db->query('ALTER TABLE users MODIFY password VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL');
        $legacyUserId = 919903;
        $legacyUsername = 'legacy_admin';
        $legacyPasswordHash = password_hash('W03-fixture-password', PASSWORD_DEFAULT);
        $legacyFullname = 'Legacy Admin';
        $legacyRole = 'admin';
        $stmt = $db->prepare('INSERT INTO users (id, username, password, fullname, role) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('issss', $legacyUserId, $legacyUsername, $legacyPasswordHash, $legacyFullname, $legacyRole);
        $stmt->execute();

        $clientId = 919901;
        $taskId = 919902;
        $db->query("DELETE FROM tasks WHERE id = $taskId");
        $db->query("DELETE FROM clients WHERE id = $clientId");
        $name = 'W03 Δοκιμαστικός Πελάτης';
        $title = 'W03 Δοκιμαστική εργασία';
        $stmt = $db->prepare('INSERT INTO clients (id, name) VALUES (?, ?)');
        $stmt->bind_param('is', $clientId, $name); $stmt->execute();
        $stmt = $db->prepare('INSERT INTO tasks (id, task_date, title, client_id, fee, collected) VALUES (?, "2026-01-15", ?, ?, 1234.56, 78.90)');
        $stmt->bind_param('isi', $taskId, $title, $clientId); $stmt->execute();
        $beforeTables = array_column($db->query('SHOW TABLES')->fetch_all(), 0);
        $beforeSchema = $this->schemaSnapshot($db);
        $applied = crm_apply_migrations($db);
        $again = crm_apply_migrations($db);
        $row = $db->query("SELECT c.id,c.name,t.id AS task_id,t.title,t.fee,t.collected FROM clients c JOIN tasks t ON t.client_id=c.id WHERE c.id=$clientId")->fetch_assoc();
        $afterTables = array_column($db->query('SHOW TABLES')->fetch_all(), 0);
        $afterSchema = $this->schemaSnapshot($db);
        $migratedUser = $db->query("SELECT id, username, password, fullname, role FROM users WHERE id = $legacyUserId")->fetch_assoc();
        $userCharsets = array_column($db->query("SELECT COLUMN_NAME, CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME IN ('username', 'password', 'fullname') ORDER BY COLUMN_NAME")->fetch_all(MYSQLI_ASSOC), 'CHARACTER_SET_NAME', 'COLUMN_NAME');
        $db->set_charset('utf8mb4');
        $unicodeUserId = 919904;
        $unicodeUsername = 'χρήστης_δοκιμής';
        $unicodeFullname = 'Μαρία Δοκιμή';
        $unicodePasswordHash = password_hash('W03-unicode-fixture-password', PASSWORD_DEFAULT);
        $unicodeRole = 'user';
        $stmt = $db->prepare('INSERT INTO users (id, username, password, fullname, role) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('issss', $unicodeUserId, $unicodeUsername, $unicodePasswordHash, $unicodeFullname, $unicodeRole);
        $stmt->execute();
        $unicodeUser = $db->query("SELECT username, fullname FROM users WHERE id = $unicodeUserId")->fetch_assoc();
        self::assertSame(['id'=>(string)$clientId,'name'=>$name,'task_id'=>(string)$taskId,'title'=>$title,'fee'=>'1234.56','collected'=>'78.90'], $row);
        self::assertSame(['id'=>(string)$legacyUserId,'username'=>$legacyUsername,'password'=>$legacyPasswordHash,'fullname'=>$legacyFullname,'role'=>$legacyRole], $migratedUser);
        self::assertSame(['fullname'=>'utf8mb4','password'=>'utf8mb4','username'=>'utf8mb4'], $userCharsets);
        self::assertSame(['username'=>$unicodeUsername,'fullname'=>$unicodeFullname], $unicodeUser);
        self::assertSame([], $again);
        self::assertSame(['20261118_001_catalogs','20261118_002_recurring_tasks','20261118_003_security_events','20261119_001_users_utf8mb4'], $applied);
        self::assertSame(['login_throttle','payment_methods','recurrence_occurrences','recurrence_run_log','recurring_task_rule_versions','recurring_task_rules','schema_migrations','security_events','task_title_templates'], array_values(array_diff($afterTables,$beforeTables)));
        self::assertSame(['payment_method_id INT(11) NULL','payment_method_label VARCHAR(100) NULL'], array_values(array_diff($afterSchema['columns']['tasks'], $beforeSchema['columns']['tasks'])));
        self::assertContains('idx_tasks_payment_method_id', array_diff($afterSchema['indexes']['tasks'], $beforeSchema['indexes']['tasks']));
        self::assertNotEmpty(array_diff(array_keys($afterSchema['foreign_keys']), array_keys($beforeSchema['foreign_keys'])));
        self::assertSame([], array_diff($beforeSchema['tables'], $afterSchema['tables']));
        $db->close();
        $admin = new mysqli($adminHost, (string)getenv('CRM_TEST_ADMIN_USER'), (string)getenv('CRM_TEST_ADMIN_PASSWORD'));
        self::assertSame($targetServer, $admin->query($serverIdentityQuery)->fetch_assoc());
        $admin->query("DROP DATABASE `$database`");
        $admin->close();
    }

    private function schemaSnapshot(mysqli $db): array
    {
        $tables = array_column($db->query('SHOW TABLES')->fetch_all(), 0);
        $columns = [];
        $indexes = [];
        $foreignKeys = [];
        foreach ($tables as $table) {
            $safe = str_replace('`', '``', (string)$table);
            $columns[$table] = array_map(static fn(array $row): string => $row['Field'] . ' ' . strtoupper($row['Type']) . ($row['Null'] === 'YES' ? ' NULL' : ' NOT NULL'), $db->query("SHOW COLUMNS FROM `$safe`")->fetch_all(MYSQLI_ASSOC));
            $indexes[$table] = array_values(array_unique(array_column($db->query("SHOW INDEX FROM `$safe`")->fetch_all(MYSQLI_ASSOC), 'Key_name')));
            $stmt = $db->prepare('SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION');
            $stmt->bind_param('s', $table); $stmt->execute();
            $foreignKeys[$table] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        return ['tables'=>$tables,'columns'=>$columns,'indexes'=>$indexes,'foreign_keys'=>$foreignKeys];
    }

    public function testFailedMigrationIsNotRecordedEvenWhenDdlAlreadyApplied(): void
    {
        self::assertTrue(CRM_TEST_DB_SAFE);
        self::assertSame(CRM_TEST_DATABASE, getenv('DB_NAME'));
        $db = new mysqli((string)getenv('DB_HOST'), (string)getenv('DB_USER'), (string)getenv('DB_PASSWORD'), CRM_TEST_DATABASE);
        self::assertSame(CRM_TEST_DATABASE, $db->query('SELECT DATABASE()')->fetch_row()[0]);
        $suffix = bin2hex(random_bytes(8));
        $table = 'w03_test_' . $suffix;
        $version = '20991231_999_failure_' . $suffix;
        $directory = sys_get_temp_dir() . '/crm-w03-' . $suffix;
        mkdir($directory, 0700);
        $file = $directory . '/' . $version . '.php';
        file_put_contents($file, '<?php return static function (mysqli $db): void { $db->query("CREATE TABLE ' . $table . ' (id INT PRIMARY KEY)"); throw new RuntimeException("W03 expected failure"); };');

        try {
            try {
                crm_apply_migrations($db, $directory);
                self::fail('A failed migration must stop the runner.');
            } catch (RuntimeException $error) {
                self::assertSame('W03 expected failure', $error->getMessage());
            }
            $stmt = $db->prepare('SELECT COUNT(*) FROM schema_migrations WHERE version = ?');
            $stmt->bind_param('s', $version);
            $stmt->execute();
            self::assertSame(0, (int)$stmt->get_result()->fetch_row()[0]);
            $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->bind_param('s', $table);
            $stmt->execute();
            self::assertSame(1, (int)$stmt->get_result()->fetch_row()[0], 'MariaDB DDL may persist after failure.');
        } finally {
            $db->query('DROP TABLE IF EXISTS `' . $table . '`');
            unlink($file);
            rmdir($directory);
            $db->close();
        }
    }

    public function testMissingMigrationDirectoryDoesNotReportAnUpToDateSchema(): void
    {
        self::assertTrue(CRM_TEST_DB_SAFE);
        self::assertSame(CRM_TEST_DATABASE, getenv('DB_NAME'));
        $db = new mysqli((string)getenv('DB_HOST'), (string)getenv('DB_USER'), (string)getenv('DB_PASSWORD'), CRM_TEST_DATABASE);
        self::assertSame(CRM_TEST_DATABASE, $db->query('SELECT DATABASE()')->fetch_row()[0]);

        $this->expectException(RuntimeException::class);
        crm_apply_migrations($db, sys_get_temp_dir() . '/crm-missing-migrations-' . bin2hex(random_bytes(12)));
    }
}
