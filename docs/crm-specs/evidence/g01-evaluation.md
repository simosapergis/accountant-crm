# G01 baseline evaluation

Date: 18 November 2026.
Reviewed code: `c9652a7` on `codex/crm-remaining-tasks`.
Result: `NOT_PASS`.

## Completed checks

- W01 bug and entrypoint inventory is recorded in [w01-baseline.md](w01-baseline.md) and `BUGS.md`.
- W02, W03, W04 and W08 have local closeout evidence in their respective evidence files.
- The current isolated native MariaDB run verified the target `crm_test_w08_closeout_20261118` before tests.
- Full PHPUnit suite: `php tests/bin/assert-test-db.php && tests/vendor/bin/phpunit -c tests/phpunit.xml --no-progress` — PASS, 159 tests / 998 assertions, PHP 8.3.36.
- The follow-up logout guard and regression pass the focused W02 CSRF class and full suite: 40 tests / 230 assertions and 160 tests / 1,001 assertions.
- The anonymous logout mismatch recorded in the initial evaluation is resolved locally and documented in [W02 route review](w02-route-review.md).
- W08 closeout records PHP syntax checks for 79 files, Composer audit/platform checks, seven `/crm/` browser checks and spreadsheet/PDF rendering checks.
- An independent read-only G01 review was assigned and completed.
  It found no new critical/high issue established by the available evidence, but could not rule out such findings while the security inventories remain incomplete.

The full test run used `APP_ENV=test`, `/crm/` base path settings and the isolated database credentials from the existing private test setup.
No production or hosting database was accessed.

## Blocking criteria

- The W02 route-by-route authentication/authorization, diary ownership and CSRF matrices are still marked partial.
- The XSS/output, SQL and sensitive-file reviews are not documented as complete across every existing entrypoint.
- Manual review of key application flows is not fully evidenced for G01.
- The independent reviewer found no current evidence that certifies the absence of open critical/high security findings while those reviews remain partial.
- The checklist item requiring a recorded PASS for an exact reviewed code state therefore cannot be satisfied.

Hosting/webroot configuration and production checks were not performed and are outside this local G01 result.

## Next step

Close the remaining W02 baseline route and output review criteria with regression evidence, then request another independent G01 review.
Keep G01 `NOT_EVALUATED` until every mandatory checklist item has verified evidence.
