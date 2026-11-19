# W09 acceptance execution

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

## AT-01 to AT-53 disposition

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
| AT-21 | PASS locally — editable title suggestions and 255-character validation. |
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
| AT-43 | PARTIAL — local Athens date/CLI/dry-run behavior passes; this run exercised a December 2026 due rule and confirmed `created=1` in dry-run output with no occurrence written. DST-boundary and real Plesk Run Now/second run were not performed. |
| AT-44 | NOT RUN — actual static document-root coexistence and independent site deployment are hosting checks. |
| AT-45 | PARTIAL — synthetic legacy-row migration preservation and idempotency pass; no real legacy database copy or WordPress-isolation check was used. |
| AT-46 | PASS locally — migration idempotency and failed-DDL recording/activation behavior. |
| AT-47 | PASS locally — PHP 8.3.36 platform, imports, eight export routes, Greek PDF and duplicate-session checks. |
| AT-48 | PARTIAL — CRM HTTP responses now carry `X-Robots-Tag: noindex, nofollow`; public static menu/sitemap was not inspected. |
| AT-49 | PASS locally — all seven requested widths cover task list/form, payment and title catalog lists/forms, recurring list and recurrence settings; every visible focusable control is traversed, checked for visible focus and on-screen placement, and visible text is scanned for hidden/clip overflow. |
| AT-50 | PARTIAL — human acceptance of the listed layouts is recorded; final independent W09 QA cannot PASS while AT-43–45, AT-48 and AT-51 remain incomplete. |
| AT-51 | NOT RUN — real backup and restore require a separate deployment environment and are reserved for W10. |
| AT-52 | PASS locally — newly created fictional admin `w09-onboarding-admin` logged into `/crm/`, opened the payment-method and task-title catalogs, then created a December 2026 recurrence rule for fictional task 910001 through the recurrence form. Scheduler dry-run reported one due occurrence without persisting it; the exact temporary rule/version were removed from the isolated test DB afterward. Password was not recorded in evidence. |
| AT-53 | PASS locally — source-task deletion preserves the rule, and client deletion with recurrence history is denied clearly. |

## Remaining work and next step

W09 remains `IN_PROGRESS`; do not record W09 PASS while partial/not-run criteria above remain.
AT-49 and AT-52 are complete for local acceptance.
AT-43 DST/Plesk checks, AT-44 static-site coexistence, AT-45 real legacy/WordPress isolation, AT-48 public static-site sitemap/menu and cache policy, and AT-51 backup/restore require the relevant non-production deployment environment or W10 execution.
AT-50 remains partial pending independent QA and any required human layout acceptance.
