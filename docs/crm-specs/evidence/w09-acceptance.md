# W09 acceptance execution

## Current-head hosting availability check - 19 November 2026

Current branch: `codex/crm-remaining-tasks`.
Current application HEAD: `bf71c7119b5ea351069d955286dc8fe0ac7d8de8`, matching `origin/codex/crm-remaining-tasks` at the start of this review.
The exact-HEAD local config-loader results are recorded in [implementation status](../implementation-status.md): 210 PHPUnit tests / 1,282 assertions and 13 browser checks passed with fictional configuration/data, with PHPUnit run before the browser recurrence fixture was seeded.

Availability result: no authorized pre-production host, credentials, or connected hosting access mechanism were available or identified for this task.
The deployment runbook's `elmico.gr` URL does not establish that the host is pre-production or authorize a request to it.
Therefore no remote HTTP, SSH, Plesk, filesystem, database, scheduler, or backup command was run.

AT-56 remains PARTIAL pending an authorized pre-production environment for verification of web/CLI runtime identities, private config location and permissions, HTTP denial, shared database/HMAC behavior, and encrypted backup/restore.
AT-43/44/45/48/50/51 remain open or partial as itemized in the acceptance disposition below.
No new G01 or W09 PASS is claimed, and the historical G01 result remains scoped to its earlier recorded commit.

## Private CRM configuration continuation - 19 November 2026

The private config loader work and local AT-56 evidence are summarized in the latest `implementation-status.md` entry.
Execution order matters because the browser viewport suite needs a recurring-rule display fixture and W06's global scheduler consumes all active rules.
Before PHPUnit, `crm_test_w09_20261119` was verified with non-root user `crm_test_w091119`; recurring-rule and occurrence tables were empty.
The full suite then passed in private-file mode: 210 tests / 1282 assertions on PHP 8.3.36 and MariaDB 10.11.19.
Only after PHPUnit passed were the documented W01 fixtures and `W01 Mobile Recurring Filing` browser rule seeded.
The browser suite then passed all 13 checks, including AT-49 and the exercised AT-54 picker journey.
The browser recurrence fixture remains in the disposable database after this final browser run.
Before rerunning PHPUnit, use a fresh schema or remove only positively identified fixture-owned rows; do not run W06 after seeding the browser rule in the same database.

Local tests verify loader resolution, validation, failure sanitization, permissions policy, web/CLI parity and private HMAC/database values despite conflicting environment values.
They do not verify actual Plesk process users, production filesystem permissions, public hosting HTTP rules or encrypted backup/restore.
AT-56 remains PARTIAL, and W03/W09 remain IN_PROGRESS.

## Post-pull isolated verification - 19 November 2026

The preserved W09 work was committed as `9c912e5` before pulling, as requested.
The pull fetched `433eacf` and merge `152021f`; local merge `d8ab822` preserved both progress records after resolving the sole conflict in `implementation-status.md`.
Remote changes added the users utf8mb4 migration, CLT-01/AT-54 client picker, AT-55 migration acceptance, Compose portability changes and a revised deployment runbook.
The results in this section supersede historical execution claims for the affected merged behavior.

A new disposable database `crm_test_w09_20261119` was created on the existing test-owned native MariaDB server, with a new non-root account `crm_test_w091119` restricted to that database.
Before creation, the administrative connection identified the local socket `/tmp/accountant-crm-preview-20261118/mysql.sock`, data directory `/tmp/accountant-crm-preview-20261118/data/`, hostname `MM25M0JH20T` and MariaDB `10.11.19`.
The import verified the exact database and zero existing tables before loading the schema.
Every subsequent setup/test command ran through `/tmp/accountant-crm-w09-20261119/run.sh`, which sets the explicit test target, reads private temporary credentials/HMAC key and executes `php tests/bin/assert-test-db.php` before the requested command.
The PHP server served the current repository at `http://127.0.0.1:18119/crm/` using the same test configuration and `tests/support/native-router.php`.
The older `crm_test_w09_20261118` database and its unknown fixture rows were left untouched.

Observed failures and corrections:

- The first fresh W06 run had 21 tests / 105 assertions and 9 login failures because the new temporary runner omitted `CRM_SECURITY_HMAC_KEY`.
  The server log confirmed the missing-key exception before authentication; supplying the same private test key to server and runner restored the HTTP flows.
- Independent source review confirmed that Compose also omitted this mandatory key in both services.
  `compose.w01.yaml` now supplies the same explicitly fictional test-only key to app and runner; this is not production configuration.
- Native W03 RED: 3 tests / 12 assertions, one grant error because the merged test hardcoded the account host `%`.
  W03 now discovers the authenticated non-root account with `CURRENT_USER()`, grants that existing account access to its own random temporary schema, and compares hostname/port/socket/data directory with the guarded server before CREATE and again before DROP.
  `CRM_TEST_ADMIN_HOST` falls back to `DB_HOST`; native instructions now describe the required administrator and shared HMAC configuration.
- The first full run had 167 tests / 1,088 assertions with one obsolete title-catalog assertion expecting the renamed title in an input value.
  The assertion now checks the saved custom input, selected custom mode and renamed template option through DOM queries.
  An intermediate rerun exposed option indentation whitespace; trimming the displayed option text corrected that test expectation without changing application behavior.
- The snapshot race regression now submits `custom_title` read from the actual GET input and verifies that the input is enabled.

Exact final commands and results:

```sh
sh /tmp/accountant-crm-w09-20261119/run.sh php tests/vendor/bin/phpunit -c tests/phpunit.xml tests/integration/W06RecurringTasksTest.php
# PASS: 21 tests / 158 assertions.
sh /tmp/accountant-crm-w09-20261119/run.sh env CRM_TEST_ADMIN_HOST=localhost CRM_TEST_ADMIN_USER=root CRM_TEST_ADMIN_PASSWORD= php tests/vendor/bin/phpunit -c tests/phpunit.xml --no-progress
# PASS: 167 tests / 1095 assertions, including W03, W02, W05, W06, scheduler and DST checks.
sh /tmp/accountant-crm-w09-20261119/run.sh env CHROME_BIN='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' npm --prefix tests/e2e --cache /tmp/crm-w01-native-npm-cache test
# PASS: 13 checks, including the new AT-54 exercised flow and AT-49 viewport/keyboard matrices.
composer check-platform-reqs --working-dir=ACCOUNTANT_CRM --no-dev
composer check-platform-reqs --working-dir=tests
# Both PASS on PHP 8.3.36.
node --check tests/e2e/login-page.test.cjs
git -c core.whitespace=cr-at-eol diff --check
# Both PASS.
```

PHP syntax checks passed for all 81 application/test PHP files excluding vendor directories; the three subsequently edited PHP tests were linted again successfully.
All four migrations applied successfully to the new target.
The documented W01 fixtures were restored before browser execution; the mobile recurrence fixture was seeded for browser checks and only that positively identified rule/version was removed before the final full PHPUnit run.
No unknown database rows were cleaned up.
The database and temporary runner remain available for follow-up; the mobile recurrence display fixture must be seeded again before another complete browser run.

The independent SET reviewer performed read-only source/evidence QA of the snapshot fix and, after the pull, of the changed harness/tests.
It identified the HMAC omission, native grant issue and insufficient server identity check, and confirmed the final source corrections.
It found no further material issue or high/critical finding in that limited scope.
The reviewer did not execute the tests; execution results above were observed by the implementing agent.
This is not a complete security audit or human visual acceptance of the final tree.

AT-54 is PARTIAL: the browser exercised authenticated all-client loading, name filtering, clearing, keyboard selection, text-safe hostile names and unauthenticated denial.
It checked bounded scrolling styles; the available fictional clients did not establish AFM filtering or actual long-list scrolling/performance.
AT-55 passes locally through the merged W03 migration regression in the final full suite.
Compose YAML was parsed successfully with Ruby YAML and the shared fictional key length/equality was verified.
Linux Compose execution is NOT_RUN on this macOS session; the remote report of 87 failures is retained as an unresolved Linux verification item, not declared fixed by native results.
AT-43/44/45/48/51 hosting work and final AT-50 human inspection remain open.
W09 remains `IN_PROGRESS`; no new G01 or W09 PASS is granted.

## Independent QA follow-up - 19 November 2026

Reviewed application baseline: `88efb87`, followed by the uncommitted snapshot fix and test updates in this session.
The earlier commands below describe historical runs and do not certify this merged HEAD.
A separate SET reviewer completed read-only source/evidence review and identified obsolete title POST payloads and a task-title snapshot race.
The reviewer subsequently reviewed the fix read-only; its own intermediate test-editing work is distinct from the independent initial review.
An authenticated HTTP regression reproduced the race: a catalog rename between opening and saving a task changed the task title when only the fee was edited.
The task form now defaults existing titles to their saved custom text, preserving the snapshot until the user explicitly selects a catalog title.
W05 and W02/W06 task POST tests now use the current `title_selection` / `custom_title` contract.

- Guarded W05 RED: 9 tests, 78 assertions, one expected snapshot-title failure.
- Guarded W05 GREEN: `php tests/vendor/bin/phpunit -c tests/phpunit.xml tests/integration/W05TaskPaymentsTest.php`, 9 tests / 78 assertions PASS.
- Guarded W02: `php tests/vendor/bin/phpunit -c tests/phpunit.xml tests/integration/W02CsrfTest.php`, 40 tests / 230 assertions PASS.
- DST unit: `php tests/vendor/bin/phpunit -c tests/phpunit.xml tests/unit/W06RecurrenceDateTest.php`, 5 tests / 22 assertions PASS, including UTC instants around both 2027 Athens DST transitions and local midnight.
- Browser rerun: `CHROME_BIN='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' CRM_BASE_URL=http://127.0.0.1:18098/crm npm --prefix tests/e2e --cache /tmp/crm-w01-native-npm-cache test`, all 12 checks PASS after replacing the obsolete `input[name="title"]` route selector with `#title_selection`.
- W06 focused attempt: `--filter testEditingOneGeneratedTaskDoesNotChangeItsRecurrenceRuleOrFutureTasks`, 1 test / 2 assertions, failed before the HTTP edit because the scheduler created 5 tasks instead of the expected 1 in the existing fictional fixture database.

Database commands used `crm_test_w09_20261118` with non-root user `crm_test_w09`, verified by `tests/bin/assert-test-db.php` before each invocation.
The sandbox initially denied the MariaDB connection; the approved retry reached the guarded local target.
W06 requires a fresh guarded test database or positively identified fixture cleanup before its current-code result can be certified.
The unit DST result establishes date conversion, not real Plesk execution or calendar/list agreement.
The user could not confirm an authorized non-production hosting environment in this session.
AT-43/44/45/48/51 hosting checks remain open; AT-50 remains PARTIAL and W09 remains IN_PROGRESS.

Date: 19 November 2026.
Branch: `codex/crm-remaining-tasks`.
Application baseline: `a2e805e2581542652ff7a007f9f980ad1f3c00c3` plus the uncommitted W09 changes listed in this record.
Status: `IN_PROGRESS`; local automated checks pass, but several acceptance scenarios require further local/manual evidence or deployment access.
No production data, hosting configuration, production scheduler or production backup was accessed or changed.

## AT-48 noindex RED/GREEN

The new HTTP regression `W01EnvironmentTest::testCrmResponsesCarryNoIndexDirective` checks the login page, an unauthenticated protected page and an export route under `/crm/`.
Before the fix, it failed on `/login.php` because the response had no `X-Robots-Tag` header (1 test / 2 assertions; expected failure).
The shared web session bootstrap now sends `X-Robots-Tag: noindex, nofollow` for web requests, covering login, protected pages, redirects and exports.
Focused GREEN run including the new regression, nested login and protected redirect: 3 tests / 11 assertions.
The public sitemap and navigation belong to the external static site and were not inspected in this local CRM checkout, so AT-48 is still partial.

## Additional acceptance coverage

`W05TaskPaymentsTest::testSnapshotIsRenderedInTaskListAndTaskExports` now verifies the payment snapshot in the task mobile-card markup, desktop list, task form, partner views and the task/partner-analysis exports.
It also asserts the fixture remains one task with fee `25.00` and collection `5.00`.
Focused W05 payment integration suite: 8 tests / 67 assertions.

`W06RecurringTasksTest::testEditingOneGeneratedTaskDoesNotChangeItsRecurrenceRuleOrFutureTasks` edits a generated task over authenticated HTTP and verifies that only that task changes, while the rule version/fee and next generated task remain unchanged.
Focused W06 recurrence suite: 21 tests / 158 assertions.

The AT-49 browser matrix checks task list/form, both catalog lists and create forms, recurring list and recurrence settings at 320, 375, 390, 768, 1024, 1280 and 1440 pixels.
It checks page overflow, card/table breakpoints, and representative control visibility at every width.
A second matrix traverses every visible focusable control on all eight screens at every requested width, verifies the focused control and focus indicator are visible, and scans visible text for clipping by hidden/clip overflow.
The first full keyboard pass exposed a missing focus indicator on task form fields when Bootstrap was loaded after the global stylesheet.
The global focus treatment now overrides that reset for header, forms and page content.
After restoring the documented W01 browser fixtures in the isolated test DB, the extended browser suite passed all 12 checks.
AT-49 is PASS locally; this is browser automation evidence, not human visual acceptance under AT-50.

## Commands and results

All database commands used the guarded isolated MariaDB database `crm_test_w09_20261118`, with a non-root application user restricted to that database.
The target was verified before migrations, seeding, fixture cleanup and each test run.
Only schema and fictional W01/W09 fixtures were used.
The W01 mobile recurrence fixture was seeded after recurrence PHPUnit runs for browser checks, then the same single fixture was removed under the database guard before the final full suite so W06's clean-rule-table precondition remained true.

- Guard, additive migrations and fictional fixture setup — PASS on `crm_test_w09_20261118`.
- AT-48 RED — `php tests/vendor/bin/phpunit -c tests/phpunit.xml --filter testCrmResponsesCarryNoIndexDirective`: 1 test / 2 assertions failed for the expected missing response header.
- AT-48 GREEN and nested-route regression — guarded PHPUnit filter: 3 tests / 11 assertions PASS.
- AT-26 — guarded `W05TaskPaymentsTest.php`: 8 tests / 67 assertions PASS.
- AT-32 — guarded `W06RecurringTasksTest.php`: 21 tests / 158 assertions PASS.
- Final full suite — `php tests/bin/assert-test-db.php && php tests/vendor/bin/phpunit -c tests/phpunit.xml --no-progress`: 165 tests / 1,056 assertions PASS.
- Browser suite — `CHROME_BIN='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' CRM_BASE_URL=http://127.0.0.1:18098/crm npm --prefix tests/e2e --cache /tmp/crm-w01-native-npm-cache test`: 12 checks PASS, including viewport, keyboard traversal and clipped-text scans for AT-49.
- AT-52 browser walkthrough — new fictional admin logged in at `/crm/`, opened both catalog screens, and created a recurrence from task 910001; all navigations and save completed successfully.
- AT-43 local dry-run follow-up — guarded `php ACCOUNTANT_CRM/bin/run-recurring-tasks.php --dry-run --as-of=2026-12-31`: `created=1 skipped=0 failed=0`; read-only verification found zero occurrence rows for the temporary rule.
- PHP syntax — all 80 application/test PHP files passed `php -l` (excluding vendor directories).
- Node syntax — `node --check tests/e2e/login-page.test.cjs` PASS.
- Production dependency platform requirements — `composer check-platform-reqs --working-dir=ACCOUNTANT_CRM --no-dev` PASS on PHP 8.3.36.
- Dependency security audit — `composer audit --working-dir=ACCOUNTANT_CRM --locked --no-dev` PASS, no advisories; the first sandboxed attempt could not resolve Packagist, and the approved network-enabled retry completed.
- `git -c core.whitespace=cr-at-eol diff --check` — PASS.

The first full PHPUnit attempt stopped in `W03MigrationTest` because `CRM_TEST_ADMIN_USER=root` was not set for its separate migration fixture.
The full run passed after applying the documented test-only setting; the database guard continued to verify the non-root W09 application connection.

## Acceptance disposition (including merged AT-54/55)

| AT | W09 disposition and evidence |
|---|---|
| AT-01 | PASS locally — `W01EnvironmentTest`, 33-route inventory, W02 closeout and route review. |
| AT-02 | PASS locally — `W05CatalogsTest` denies user access to catalog list and form routes. |
| AT-03 | PASS locally — shared task/rule user and admin HTTP regressions in W05/W06 tests. |
| AT-04 | PASS locally — diary read/write ownership and foreign-event rejection in W02 evidence/tests. |
| AT-05 | PASS locally — CSRF and GET-delete HTTP regressions across existing mutation routes. |
| AT-06 | PASS locally — hostile-value regressions plus route-by-route output source review; coverage is distributed across W02 tests/evidence. |
| AT-07 | PASS locally — prepared query/source review and SQL-like filter regressions. |
| AT-08 | PASS locally — password boundary/policy tests and login whitespace handling. |
| AT-09 | PASS locally — existing hash verification and password/log safety evidence in W02 closeout. |
| AT-10 | PASS locally — five-failure throttle, fresh-session behavior and cooldown tests in W04. |
| AT-11 | PASS locally — source-wide throttle and generic unknown-user behavior in W04. |
| AT-12 | PASS locally — security-event persistence and sensitive-field exclusion in W04. |
| AT-13 | PASS locally in loopback HTTPS harness — session rotation and cookie flags/path. |
| AT-14 | PASS locally — frozen-clock idle boundary at 1,799/1,800 seconds. |
| AT-15 | PASS locally — logout invalidation and no-store responses. |
| AT-16 | PASS for app-local paths — HTTP denial matrix and `.htaccess` review; actual host/webroot remains unverified. |
| AT-17 | PASS for the exercised corpus — oversized, renamed, corrupt, truncated and invalid-row import tests; not an exhaustive malformed-file fuzz campaign. |
| AT-18 | PASS locally — formula-leading output tests and LibreOffice opening/conversion of exported files. |
| AT-19 | PASS locally — catalog create, duplicate/blank rejection, deactivate/reactivate and retained associations. |
| AT-20 | PASS locally — inactive selection rejection and preserved legacy payment snapshots. |
| AT-21 | PASS locally — catalog dropdown/custom title selection and 255-character validation. |
| AT-22 | PASS locally — catalog rename preserves existing task/rule title snapshots. |
| AT-23 | PASS locally — positive collection requires an active method and persists server label. |
| AT-24 | PASS locally — zero collection clears method ID/label with user feedback. |
| AT-25 | PASS locally — legacy collection without a payment method remains readable and is validated on edit. |
| AT-26 | PASS locally — W05 regression covers mobile card, desktop list, task form, task and analytical exports; fee/collection row totals remain unchanged. |
| AT-27 | PASS locally — accepted/rejected amount parsing and exact persistence/aggregate tests. |
| AT-28 | PASS locally — over-collection balance and single-amount update behavior. |
| AT-29 | PASS locally — same-submit task/rule transaction, next-month minimum and duplicate activation. |
| AT-30 | PASS locally — independently due rules and per-rule dates. |
| AT-31 | PASS locally — month-end clamping and leap-year date tests. |
| AT-32 | PASS locally — new authenticated HTTP regression edits one generated task and verifies the rule and next occurrence are unchanged. |
| AT-33 | PASS locally — same-month future change behavior and effective date. |
| AT-34 | PASS locally — missed-month catch-up uses historical fee then new fee. |
| AT-35 | PASS locally — past-day change takes effect in the next month. |
| AT-36 | PASS locally — generated/deleted period ledger is retained and not recreated. |
| AT-37 | PASS locally — multi-version, multi-month batched catch-up. |
| AT-38 | PASS locally — two real concurrent MariaDB CLI processes create one occurrence. |
| AT-39 | PASS locally — injected task/occurrence failure rolls back and retry succeeds. |
| AT-40 | PASS locally — immediate stop retains eligible past due work and excludes future due dates. |
| AT-41 | PASS locally — restart creates a linked rule and does not catch up stopped months. |
| AT-42 | PASS locally — stale `row_version` is rejected. |
| AT-43 | PARTIAL — local Athens date/CLI/dry-run behavior passes; this run exercised a December 2026 due rule and confirmed `created=1` in dry-run output with no occurrence written. DST unit boundaries now pass for both Athens clock changes and local midnight; scheduler/calendar agreement at those boundaries and real Plesk Run Now/second run remain unverified. |
| AT-44 | NOT RUN — actual static document-root coexistence and independent site deployment are hosting checks. |
| AT-45 | PARTIAL — synthetic legacy-row migration preservation and idempotency pass; no real legacy database copy or WordPress-isolation check was used. |
| AT-46 | PASS locally — migration idempotency and failed-DDL recording/activation behavior. |
| AT-47 | PASS locally — PHP 8.3.36 platform, imports, eight export routes, Greek PDF and duplicate-session checks. |
| AT-48 | PARTIAL — CRM HTTP responses now carry `X-Robots-Tag: noindex, nofollow`; public static menu/sitemap was not inspected. |
| AT-49 | PASS locally — all seven requested widths cover task list/form, payment and title catalog lists/forms, recurring list and recurrence settings; every visible focusable control is traversed, checked for visible focus and on-screen placement, and visible text is scanned for hidden/clip overflow. |
| AT-50 | PARTIAL — earlier human layout acceptance is historical; independent source QA of this follow-up found no remaining material finding in scope, but final human inspection of the merged tree and AT-43/44/45/48/51 evidence remain outstanding. |
| AT-51 | NOT RUN — real backup and restore require a separate deployment environment and are reserved for W10. |
| AT-52 | PASS locally — newly created fictional admin `w09-onboarding-admin` logged into `/crm/`, opened the payment-method and task-title catalogs, then created a December 2026 recurrence rule for fictional task 910001 through the recurrence form. Scheduler dry-run reported one due occurrence without persisting it; the exact temporary rule/version were removed from the isolated test DB afterward. Password was not recorded in evidence. |
| AT-53 | PASS locally — source-task deletion preserves the rule, and client deletion with recurrence history is denied clearly. |

| AT-54 | PARTIAL - exercised browser client-picker flow passes; AFM and actual long-list scrolling remain unverified. |
| AT-55 | PASS locally - W03 verifies fresh utf8mb4, legacy account/hash preservation, Greek account text and migration idempotency in the final full suite. |

## Remaining work and next step

W09 remains `IN_PROGRESS`; do not record W09 PASS while partial/not-run criteria above remain.
AT-49 and AT-52 are complete for local acceptance.
AT-43 scheduler/calendar boundary and Plesk checks, AT-44 static-site coexistence, AT-45 real legacy/WordPress isolation, AT-48 public static-site sitemap/menu and cache policy, and AT-51 backup/restore require the relevant non-production deployment environment or W10 execution.
AT-50 remains partial pending required human inspection and complete acceptance evidence.
Complete AT-54 AFM/long-list coverage locally, and rerun the corrected Compose harness on an authorized Linux environment before resolving the remote failure report.
