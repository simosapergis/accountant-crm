# CRM test harness

Select the test environment by operating system before running any command.

- macOS: use native PHP 8.3, Composer and MariaDB. Do not use Docker. Create a dedicated database whose name starts with `crm_test_` and a non-root MariaDB user with privileges only on that database. Import the schema only after checking the connected server and selected database. Never point the harness at the preview/development database.
- Linux: use the isolated Compose project below with PHP 8.3 and MariaDB 10.11 containers. It owns a separate `test_db_data` volume and does not use the normal `.env` or application database volume.

All integration tests use real MariaDB. SQL behavior, constraints, transactions and concurrency must not be replaced with SQLite or mocks. The application must be reachable under `/crm/` for HTTP tests.

The W06 recurrence HTTP suite requires a clean isolated schema with no pre-existing rows in the recurring-rule tables because recurrence rules are shared application data and the scheduler processes them globally.
Use the schema-only test database plus the fictional fixtures described below; do not point the suite at a development database or a test database containing manually created recurrence rules.
The browser regression for recurring-task mobile cards needs one display fixture, so run the full PHPUnit suite first and then seed it with `php tests/bin/seed-w01-mobile-recurrence.php` before the browser suite.

## macOS - native

Install application and test dependencies:

```sh
composer install --working-dir=ACCOUNTANT_CRM --no-dev --prefer-dist
composer install --working-dir=tests --prefer-dist
```

Set connection variables in the current shell, using the dedicated test-only user and database. Do not put passwords in command history or logs:

```sh
export DB_HOST=127.0.0.1 DB_NAME=crm_test_accountant_crm DB_USER=crm_test_user
export DB_PASSWORD='(read from your local secret store)'
export CRM_TEST_DB_NAME=crm_test_accountant_crm CRM_APP_ROOT="$PWD/ACCOUNTANT_CRM"
export APP_BASE_PATH=/crm APP_ENV=test CRM_BASE_URL=http://127.0.0.1:18080/crm
export CRM_SECURITY_HMAC_KEY='w01-fictional-test-only-hmac-key-2026'
export CRM_TEST_ADMIN_HOST=127.0.0.1 CRM_TEST_ADMIN_USER=root
export CRM_TEST_ADMIN_PASSWORD='(read from your local test-only secret store)'
```

The HTTP server and test runner must share the same test-only HMAC key; without it login POST fails before checking credentials.
The W03 migration fixture uses the test administrator only to create and remove its own random `crm_test_w03_*` database on the verified server.
It grants the existing application account returned by `CURRENT_USER()` access to that temporary database, supporting both native `@localhost` and Compose `@%` accounts.
For a native socket connection set both `DB_HOST` and `CRM_TEST_ADMIN_HOST` to `localhost` and configure the test-only PHP socket ini for all child processes.

Start PHP with the application served under `/crm/`, the test database environment, and the test-only ini directory that configures the MariaDB socket for child processes. For HTTP integration tests use `tests/support/native-router.php` as the PHP built-in server router; it blocks internal paths as the Compose Apache configuration does. Bind only to loopback. For Secure-cookie browser checks, use a temporary HTTPS loopback proxy or a local Apache HTTPS vhost.

Run the preflight before migrations, fixtures or PHPUnit:

```sh
php tests/bin/assert-test-db.php
```

Only continue if it reports the expected host and `crm_test_*` database. Then run the migration, seed and suite:

```sh
php ACCOUNTANT_CRM/bin/migrate.php
php tests/bin/assert-test-db.php && php tests/bin/seed-w01-fixtures.php
tests/vendor/bin/phpunit -c tests/phpunit.xml
```

The web server must run with the same DB and `/crm` settings. `CRM_BASE_URL` should resolve to its local `/crm/` path.

## Linux - isolated Docker Compose

From the repository root:

```sh
docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 up -d app
docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 ps
docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm tests
```

This uses only the dedicated `test-db / crm_w01_test` target. Test dependencies are installed in the W01 test image; the production Dockerfile remains `--no-dev`.

The test DB guard requires `CRM_TEST_DB_NAME` to match `DB_NAME`, a non-root user, a nonempty host and a clearly isolated database name. It also queries MariaDB to verify the actual selected database before destructive/setup operations. Credentials are never printed. The fixtures are fictional and idempotent.
The W01 fixture seeder creates a fictional admin account used by the mobile catalog browser regression.

For browser checks, install the pinned Playwright dependency and run against the local `/crm/` URL. Do not run `down -v`; retaining the project-owned test volume avoids accidental data loss. Do not use the test project with `.env` or a normal development database.

## Private configuration regression (CFG-01/02, AT-56)

`tests/unit/W03PrivateConfigTest.php` launches isolated PHP processes with temporary external files and fictional values.
`tests/integration/W03PrivateConfigHttpTest.php` starts its own loopback `/crm/` server against the guarded test database and checks real login/HMAC persistence, CLI migration parity, HTTP denial and safe failures.
Run the normal database guard first, then either focused file or the full suite.
These tests do not validate production Plesk accounts, permissions or encrypted backup/restore.
Run the complete PHPUnit suite before seeding `seed-w01-mobile-recurrence.php` and running the browser suite.
The mobile browser fixture is a real shared recurring rule and the scheduler can generate its occurrences during later integration tests.

The app prefers the PHP ini locator `crm.config_file`, then the environment locator `CRM_CONFIG_FILE`, then its documented external default.
A private file is authoritative and cached for the request/process; it must not include `APP_ENV`.
Existing environment-based native/Compose tests require explicit process `APP_ENV=test` and no selected/default private file.
For file-mode regression, create a 0600 file outside the repository/public root using only the same guarded fictional DB/HMAC values, then start the HTTP server with `php -d crm.config_file=/absolute/private/test.php ...` and pass `CRM_CONFIG_FILE=/absolute/private/test.php` to PHPUnit so child CLI processes use that same source.
Never point this harness at a hosting config or production database.
