# W01 baseline evidence

Date: 2026-11-17. Branch: `codex/crm-w01-bootstrap`.
Application baseline: `27a805fbafcab86d0673c1f7f4911a45c1fcc8f1`.
Only empty/fabricated test data was used. No production or personal database was accessed.

## Workspace and environment

- `docs/crm-specs/` was imported into this CRM workspace.
- The two instructed CRM `AGENTS.md` corrections were applied before product-code changes.
- Host PHP and Composer are absent. Docker 29.8 / Compose 5.5, Node 17.9.1, npm 8.11.0, and Chrome 131 are available.
- Existing `accountant-crm` and `taxoffice-crm-main` services and their volumes were left untouched.
- Isolated project `crm-w01-20261117` uses a separate MariaDB 10.11 service and volume, imports only the schema, and serves the app under `/crm/` on loopback HTTP 18080 and HTTPS 18443.
- The DB preflight reported: `Verified isolated MariaDB target: test-db / crm_w01_test.`

## Executed checks

| Requirement / AT | Command or test | Result |
|---|---|---|
| W01 harness smoke | `docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm tests` | PHP 8.3.36 and MariaDB 10.11 connected; nested `/crm/login.php` returned 200. |
| W01 test clock | `FrozenClockTest::testClockReturnsTheExplicitAthensInstantWithoutChangingProcessTime` | PASS; returns a supplied `Europe/Athens` instant without changing process time. |
| SEC-01 / AT-01 | `W01EnvironmentTest::testProtectedTaskListRedirectsUnauthenticatedRequestsToNestedLogin`, `testDataEntrypointsRequireAuthenticationAndUseNestedLogin`, and `testUnauthenticatedResponsesDoNotRenderFictionalCrmRecords` | **FAIL (expected RED):** unauthenticated `tasks.php`, `clients.php`, `partners.php`, `calendar.php`, and the professional import endpoint returned HTTP 200. The four data pages rendered their seeded fictional task/client/collaborator records. Other guarded endpoints returned 302 but pointed to `/login.php` instead of `/crm/login.php`. Response bodies are not printed. |
| SEC-11 / AT-13 | `CRM_BASE_URL=https://127.0.0.1:18443/crm npm --prefix tests/e2e test` | Login page and username/password fields loaded over HTTPS under `/crm/` (**PASS**). Cookie checks: `Secure=false`, `HttpOnly=false`, and path `/` instead of `/crm/` (**FAIL**); `SameSite=Lax` (**PASS**). |
| Harness definition | `docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 config -q` | PASS after escaping Compose `$` interpolation in the PHP healthcheck. |

The expanded PHPUnit run reported 5 passing checks and 29 failing regression cases (34 tests, 64 assertions). The failures cover fictional data rendered without login, a public import form, and guard redirects that lose the `/crm/` prefix. The browser run exited 1 because three cookie acceptance checks currently fail; the SameSite check passes. Chrome required execution outside the restricted sandbox; the headless run reached the isolated HTTPS service successfully.

## Endpoint inventory (source review)

Expected: all data pages, AJAX, imports, and exports require an authenticated session before reading CRM data; mutations also require method and CSRF controls. Login is public, and account creation is CLI-only.

| Entry point | Expected access | Source review / W01 status |
|---|---|---|
| `index.php` | Authenticated | Includes `includes/auth.php`; runtime not separately exercised. |
| `clients.php`, `tasks.php`, `partners.php`, `calendar.php` | Authenticated | No auth include found before data queries; all four returned 200 and rendered seeded fictional records to unauthenticated requests. |
| `active_professionals.php`, `analysis_partners.php`, `client_form.php`, `partner_form.php`, `partner_tasks.php`, `active_professional_form.php`, `task_form.php`, `mydiary.php`, `index.php` | Authenticated | Guard returned 302, but Location was `/login.php` instead of nested `/crm/login.php`. |
| `login.php` | Public | Nested HTTPS form smoke PASS. |
| `logout.php` | Authenticated POST with CSRF | Source starts and destroys session; method/CSRF behavior NOT_RUN. |
| `save_client_ajax.php`, `update_event.php`, `ajax/search_clients.php` | Authenticated; mutations POST + CSRF | Guard returned 302 to `/login.php`; role/method/CSRF tests NOT_RUN. |
| `imports/import_active_professionals.php` | Authenticated; validate upload | Unauthenticated GET returned 200 despite source auth include; nested include/auth failure is reproduced. Upload validation NOT_RUN. |
| All eight `exports/*.php` endpoints | Authenticated; private/no-store response | Guards returned 302 to `/login.php`; export contents/cache behavior NOT_RUN. |
| `create_user.php` | CLI only | Unauthenticated HTTP access returned 404; CLI flow NOT_RUN. |
| `includes/*.php`, `assets/*` | No direct data access / public static assets | Direct web-access policy NOT_RUN. |

Inventory source command: `rg --files ACCOUNTANT_CRM -g '*.php' -g '*.js' -g '*.css'` plus source header review of each PHP entry point.

## Remaining W01 work

W01 environment, unit/integration/browser runners, fictional fixtures, frozen clock, entrypoint inventory, and expected RED evidence are complete. Session/security fixes and untested mutations remain W02/G01 work; W01 and G01 are separate statuses.
