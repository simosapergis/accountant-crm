# G01 baseline evaluation

Current-tree limitation, 19 November 2026: the PASS below belongs only to historical commit `bc1406c064e1fa8829dee74da5aa86409c6f3793`.
Post-pull W09 verification is recorded in [W09 evidence](w09-acceptance.md); its native green runs do not grant a new G01/W09 PASS, resolve the unverified Linux failures or close outstanding hosting/human acceptance criteria.

## Exact-commit re-evaluation - 18 November 2026

Reviewed code: `bc1406c064e1fa8829dee74da5aa86409c6f3793` on `codex/crm-remaining-tasks`.
Review type: read-only source and evidence reconciliation against every G01 bullet in `tasks.md`.
Result: `PASS` for the local corrected baseline at this exact application commit.

The responsive recurrence list was reviewed in `recurring_tasks.php` and `assets/style.css`.
The mobile cards escape client and title text, render status using a two-value fixed class/label mapping, cast rule IDs and numeric fields, and retain the existing form action; the desktop table is still present and reset with `data_seek(0)` after the card loop.
The browser evidence reports cards at 390/320 pixels, desktop table at 1024 pixels and no horizontal overflow.
The post-PHPUnit fixture verifies both the configured test database name and non-root DB user, rechecks the connected schema, uses prepared statements and fictional records, and is idempotent without cleanup/deletion.
No new critical/high security issue was identified in these changed files or fixture.

### G01 checklist reconciliation

| `tasks.md` criterion | Current evidence and assessment |
|---|---|
| Known bugs, W01 findings and entrypoints recorded | Evidenced by [W01 baseline](w01-baseline.md), `BUGS.md` and the 33-route reconciliation in [W02 route review](w02-route-review.md). `BUGS.md` has no open entries. |
| Authentication/authorization, sessions, logout, passwords and throttling fixed | Local evidence: [W02 closeout](w02-closeout.md), [W04 throttle](w04-throttle.md), and route review. This covers local `/crm/` behavior; hosting is not certified. |
| CSRF, GET deletes, XSS, SQL and private-file handling | Local route/test coverage and source review are recorded in W02 closeout, [CSRF](w02-csrf.md), [XSS](w02-xss.md), [SQL](w02-sql.md), [exports/security](w02-exports.md), and route review. The actual host's Apache/webroot and private backup/log paths remain unverified deployment concerns. |
| Known operational bugs including duplicate session, nested import, Dompdf, delete paths and money parsing | W08 local closeout records the fixes and regressions, including PDF rendering with Dompdf 3.1.6, nested `/crm/` imports/links, deletion form behavior and exact amount handling. |
| Every confirmed bug has observed RED and GREEN regression | The known W01/W08 defects have individual RED/GREEN records across the evidence and implementation history. The repo has no open bug entries; the evidence is distributed, not a single exhaustive bug-to-test ledger. No new bug was found in this review. |
| Existing client/task/partner/calendar/import/export flows pass without depending on new features | W08 closeout and the current 163-test suite provide broad local regression evidence; the 9 browser checks additionally cover responsive recurrence presentation. Production hosting is outside this result. |
| Baseline tests, syntax/lint and dependencies pass without unresolved instability | Current reported full guarded PHPUnit: 163 tests / 1,035 assertions; browser: 9 checks; PHP lint, Node syntax and CRLF-aware diff checks pass. Earlier database connection loss is documented at its historical checkpoint and was followed by a green full run on the isolated database. |
| No open confirmed functional bug or critical/high security finding | `BUGS.md` is empty under Open; W02 route review and earlier independent review report no established critical/high finding. This is a local evidence-based result, not a hosting assessment. |
| Independent QA and human inspection of key flows | Prior independent reviews and browser inspections are documented. User acceptance covers catalog pages and recurring cards. On 18 November 2026, the user confirmed acceptance of the recurring empty state and compact navigation/header for this exact commit, closing the remaining AT-50 item. |
| Exact-commit PASS recorded with commands/results/scope | Met for local G01: this review records commit, review scope, the guarded PHPUnit/browser results and syntax/dependency evidence. |

Historical W02 statements that route/CSRF matrices, complete SQL/output review, interactive account creation, spreadsheet opening or Dompdf were outstanding describe earlier checkpoints.
Later W02/W08 closeout evidence supersedes those statements for local baseline work.
The remaining production hosting checks belong to deployment and do not replace the AT-50 blocker.

### Remaining blocker and next action

G01 is now PASS for the local corrected baseline at this exact commit.
Next: proceed to W09, execute the full acceptance matrix and document findings; production hosting, scheduler and backup/restore checks remain W10 deployment gates.

## Previous evaluation at `d7476c500ead6e5802228a229b789eea2e381ca8` - 18 November 2026

At this earlier checkpoint, the recurring-task list had just gained mobile cards after a RED/GREEN regression cycle; focused W06 passed with 20 tests / 149 assertions, full PHPUnit passed with 163 tests / 1,035 assertions, and all 9 browser checks passed.
That checkpoint recorded human visual acceptance for the recurring-rule cards and awaited review; the exact-commit review and checklist reconciliation are now recorded above for `bc1406c064e1fa8829dee74da5aa86409c6f3793`.

Reviewed code: `d7476c500ead6e5802228a229b789eea2e381ca8` on `codex/crm-remaining-tasks`.
Result: `NOT_EVALUATED` pending human inspection and acceptance of the remaining key application layouts.

The full guarded PHPUnit suite passed with 162 tests / 1,024 assertions on clean isolated database `crm_test_g01_mobile_catalog_20261118`.
The browser E2E suite passed all 8 checks, including both catalog cards at 390 and 320 pixels and desktop table behavior at 1024 pixels.
PHP lint, Node syntax validation and CRLF-aware `git diff --check` passed.
The independent G01 reviewer checked the full checklist against this exact commit, found no open critical/high issue, and found the local route/security and existing-flow evidence sufficient.
The user's visual approval covers the task-title and payment-method catalogs and the newly added recurring-rule cards.
Human acceptance of the recurring-task empty state and compact navigation/header is still required.
The reviewer did not grant G01 PASS because AT-50 human inspection remains incomplete and the final PASS record is not yet present.

The catalog-specific code review also supports the card change and its fixture handling; details are in [W02 route review](w02-route-review.md).
No production or hosting checks were performed.
Do not mark G01 PASS until the remaining human layout inspection is recorded against this exact code commit.

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
