# W06/W07 recurring tasks

Status: implementation and local MariaDB acceptance coverage complete; Plesk/production scheduler verification and final package sign-off remain open.

## Scope delivered

- Additive migrations create recurring rule snapshots, effective-month rule versions, a unique rule/month occurrence ledger and run summaries. Foreign keys prevent deleting a client still referenced by recurrence history; task deletion sets an occurrence's task ID to NULL without removing the ledger.
- Users can activate recurrence while creating a task or from an existing task, starting no earlier than next month. The initial task and rule save in one transaction; a failed rule validation leaves neither behind. Users can edit day/fee separately from a task, see the effective month/date, stop immediately, and restart as a new linked rule. Row versions reject stale edits. No scheduled end-date field exists.
- A CLI-only daily scheduler uses `Europe/Athens`, calendar-month arithmetic and day clamping. It catches up due months using the applicable historical version, creates independent tasks with status `Σε εξέλιξη`, zero collection and no payment method, and writes task plus occurrence in one transaction.
- Rule row locking and a unique `(rule_id, period_month)` constraint protect concurrent runs. Batch limit defaults to 100. Dry-run is read-only. Test-only clock override and failure injection are rejected outside `APP_ENV=test`.

## Evidence

- RED: calendar unit test initially failed because recurrence date helpers did not exist.
- GREEN: clean disposable Compose project full PHPUnit suite, 132 tests / 592 assertions. New-task recurrence HTTP regressions verify that task and rule are saved together and invalid recurrence settings do not leave a task behind. Other coverage includes Jan/Feb/Mar/Apr clamping, leap day, month arithmetic and UTC-to-Athens date conversion; independently due rules; rule activation/idempotency; multiple historical day/fee versions during batched catch-up; stop/restart and stale row versions; source/generated task deletion; client deletion denial; read-only dry run; injected transactional failure/retry while another rule succeeds; CLI/migration HTTP denial; and two simultaneous CLI processes against real MariaDB.
- Same guarded PHPUnit command recorded in [W05 evidence](w05-catalogs-payments.md). Test DB: `test-db / crm_w01_test`.
- The long-lived preview database already contained an unrelated active fictional rule, so its global scheduler count tests reported extra generated occurrences. The preview data was preserved. On that database the other 130 tests passed (565 assertions); the two affected scheduler-count tests passed in the clean disposable Compose project.
- Real concurrent process result: exactly one task and one occurrence for the tested rule/month.
- Injected failure result: task insert rolled back with no orphan occurrence, CLI returned nonzero, another rule still completed, and retry created the missing occurrence once.

## Acceptance mapping

- AT-29, AT-31 to AT-36, AT-40 to AT-42 and AT-53: `W06RecurringTasksTest` and `W06RecurrenceDateTest`. AT-29 includes same-submit task/rule creation with rollback on invalid recurrence settings.
- AT-30, AT-37 to AT-39: `W06RecurringTasksTest`, using the CLI and MariaDB rather than mocked locking.
- AT-43: local Athens clock behavior, CLI-only access, dry-run and deterministic test clock are covered. The actual Plesk Run Now action, server timezone/DST configuration and second production-like run remain unverified because deployment is out of scope.

## Scheduler operation

Run migrations once as the restricted CRM database user with `php bin/migrate.php`. Schedule `php bin/run-recurring-tasks.php` daily at 03:15 with the Plesk task timezone set to `Europe/Athens`. Before enabling a schedule, run `php bin/run-recurring-tasks.php --dry-run` and inspect its summary. Do not expose the script as an HTTP route. The real Plesk setup and backup/restore exercise are release operations, not completed here.

## Still open

- Actual Plesk scheduler invocation, production-like timezone/DST configuration, real backup/restore, responsive/browser review and independent QA remain open under AT-43, AT-49 to AT-52 and W08/W09/W10.
- G01 remains `NOT_EVALUATED`; W02/W03/W04/W08 are still deferred and not passed.
