# G01 baseline evaluation

## Current re-evaluation - 18 November 2026

Reviewed code: `251a35c1251c4307f81afe6b6a9300fd9db5c1e7` on `codex/crm-remaining-tasks`.
Result: `NOT_EVALUATED` pending the required human inspection of key application layouts.

Latest code follow-up: `63fc9a7a603c90c54f7c00ac989327b715e7b50d` aligns the compact drawer with the header.
The independent reviewer supports this change and found no security or functional regression.
Its browser E2E suite passed 8 checks.
An earlier full guarded PHP run passed with 162 tests / 1,024 assertions, but a repeat during this follow-up lost its MariaDB connection during W08 exports and is not a passing rerun.
The full suite must pass again on a healthy isolated database, and a human must inspect and accept the key layouts before G01 can be evaluated as PASS.

The user has now visually approved the responsive card redesign for the task-title and payment-method catalogs.
The browser suite passed all 8 checks, including both catalog cards at 390 and 320 pixels and desktop table behavior at 1024 pixels.
The full guarded PHPUnit suite passed with 162 tests / 1,024 assertions on the clean isolated database `crm_test_g01_mobile_catalog_20261118`.
That approval covers those two screens only; the latest card implementation still needs an independent code review, and any other required human layout acceptance must be recorded.
G01 remains `NOT_EVALUATED` while those checks remain open.

- The current source review reconciles all 33 HTTP entrypoints with authentication, role, CSRF, ownership, SQL, output and private-file evidence in [W02 route review](w02-route-review.md).
- The logout issue, recurrence empty state and desktop header overflow are fixed with HTTP/browser regression coverage.
- Full guarded PHPUnit suite: PASS, 162 tests / 1,024 assertions on `crm_test_w08_closeout_20261118`.
- Browser E2E suite: PASS, 8 checks, including compact and desktop header layouts and protected login/logout flows.
- PHP syntax checks and CRLF-aware `git diff --check` passed.
- Independent read-only review of the exact commit found no new critical/high security issue and confirmed the empty-state, header and test-isolation changes.
- Agent browser inspection covered the recurring empty state and the compact header at `/crm/`; the 1600-pixel header geometry is checked by Playwright.
  A human has not yet inspected and accepted these resulting layouts, as required by AT-50 and G01.

No production or hosting checks were performed.
Do not mark G01 PASS until the human layout inspection is recorded against this exact commit.

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
