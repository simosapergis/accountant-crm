# W01 test harness

The application and database run in the separate Compose project
`crm-w01-20261117`. It uses a fresh `crm_w01_test` MariaDB volume, serves the app
under `/crm/`, and binds HTTP/HTTPS only to loopback ports 18080 and 18443. The
root `.env`, normal Compose project, and its persistent volumes are not used.

From the repository root:

```sh
docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 up -d app
docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 ps
docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm tests
npm install --prefix tests/e2e --ignore-scripts
npm install --prefix tests/e2e --ignore-scripts --cache /tmp/crm-w01-npm-cache
CRM_BASE_URL=https://127.0.0.1:18443/crm npm --prefix tests/e2e --cache /tmp/crm-w01-npm-cache test
```

The PHP runner checks DB host, user, and name before connecting. It refuses to
run against any target except `test-db` / `crm_w01_test`, then seeds only
idempotent fictional W01 client, collaborator and task records plus a hostile
client name used by the W02 autocomplete XSS browser regression. PHPUnit
dependencies are pinned in `tests/composer.lock`; the application Composer
install still uses `--no-dev`. Browser checks use pinned `playwright-core` with
host Chrome and ignore only the self-signed test certificate.

The guarded PHPUnit service applies versioned additive migrations before
seeding fixtures. To inspect or apply schema changes manually in this isolated
environment, run `php /var/www/html/crm/bin/migrate.php` in the app container.
The recurring scheduler is CLI-only: `php /var/www/html/crm/bin/run-recurring-tasks.php`;
use `--dry-run` to inspect due work without writes. Test clock/failure options
are refused unless `APP_ENV=test`. Do not expose the scheduler as a web route.

Choose another Compose project name and unused loopback ports for a parallel
test environment. Do not run `down -v`: retaining the project-owned test volume
avoids accidental deletion and supports repeat runs. Before any future test DB
reset, use the DB guard and confirm the connected host/name; never reuse this
setup with a normal `.env`.
