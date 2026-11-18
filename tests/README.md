# CRM test harness

Select the test environment by operating system before running any command.

- macOS: use native PHP 8.3, Composer and MariaDB. Do not use Docker. Create a dedicated database whose name starts with `crm_test_` and a non-root MariaDB user with privileges only on that database. Import the schema only after checking the connected server and selected database. Never point the harness at the preview/development database.
- Linux: use the isolated Compose project below with PHP 8.3 and MariaDB 10.11 containers. It owns a separate `test_db_data` volume and does not use the normal `.env` or application database volume.

All integration tests use real MariaDB. SQL behavior, constraints, transactions and concurrency must not be replaced with SQLite or mocks. The application must be reachable under `/crm/` for HTTP tests.

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
```

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

For browser checks, install the pinned Playwright dependency and run against the local `/crm/` URL. Do not run `down -v`; retaining the project-owned test volume avoids accidental data loss. Do not use the test project with `.env` or a normal development database.
