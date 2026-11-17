# W02 evidence: export filters, identifiers and output review

Date: 18 November 2026.
Branch base: `48209a8` (`codex/crm-w02-security-audit`).
Requirements: SEC-05, SEC-06, SEC-12, SEC-13, SEC-14, SEC-07, SEC-08, SEC-11 and PAY-08; acceptance: AT-06/07 (partial), AT-08/09, AT-14/15, AT-17/18 and AT-27 (partial).

## RED / GREEN

Added `tests/integration/W02ExportSqlTest.php` before changing export query construction. The first focused run against `test-db / crm_w01_test` failed all 5 cases (5 assertions): malformed array filters and SQL-like partner identifiers were accepted with HTTP 200. After adding validation and prepared statements, two cases still failed because the XLS endpoints had already emitted output before setting HTTP 400. Moving headers/body output until after input validation and successful query execution produced GREEN.

Final focused command:

```sh
docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 build app
docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm tests "php /opt/crm-tests/bin/assert-test-db.php && php /opt/crm-tests/bin/seed-w01-fixtures.php && /opt/crm-tests/vendor/bin/phpunit -c /opt/crm-tests/phpunit.xml --filter W02ExportSqlTest"
```

Result at the export-query checkpoint: PASS, 12 tests / 32 assertions. Coverage exercises task/client/professional export SQL-like filters, malformed array inputs, strict numeric partner IDs in both analysis exports, valid partner IDs, active-professional list filter names, client balance filter parity, and HTML escaping in export-link attributes. The active-professional XLSX response is parsed with PhpSpreadsheet to inspect returned cell values. Client export sorting is selected from fixed SQL fragments; all other sort clauses in these exports are fixed.

The export filters now bind values in task Excel/PDF, client Excel/PDF, active-professional Excel/PDF and partner-analysis Excel/PDF. Partner IDs reject non-decimal or out-of-range values with HTTP 400. The active-professional exports accept the parameter names used by their list page and support both active and inactive selection. Client exports now use the list's `positive` / `zero` balance values and filter after aggregation. No dynamic SQL identifier comes from a request value.

## SEC-05 review

The active-professional PDF builder now escapes code, fullname, dates and VAT type before inserting values into Dompdf's HTML. The list page escapes its generated export query string at the `href` attribute boundary. Existing targeted output fixes remain covered in `w02-xss.md`.

The active-professional PDF export could not be exercised end to end: the isolated app image does not contain Dompdf, and the route fails with `Class "Dompdf\\Dompdf" not found`. This is the known W08 dependency item; no missing dependency was added as part of this security slice. That route's prepared filter SQL is source-reviewed and PHP-parsed, but AT-06/AT-07 output behavior for that export remains unverified.

## Additional security baseline follow-up

- SEC-12 / AT-15: authenticated responses set `private, no-store, no-cache, must-revalidate`, `Pragma: no-cache` and `Expires: 0`. Regressions check representative authenticated pages and spreadsheet exports. Logout/old-cookie coverage is in `w02-csrf.md`.
- SEC-14 / AT-18: text-only HTML spreadsheet exports prefix formula-like user text before HTML escaping; the active-professional XLSX writes untrusted text cells with explicit string type. Focused export regression: PASS, 14 tests / 43 assertions. Actual office-suite opening is not part of the isolated automated check.
- SEC-14 / AT-17: the active-professional import validates upload status, size and XLSX file type, validates all rows before writing, and uses a transaction for inserts. Regressions reject renamed CSV, oversized input and a corrupt XLSX fixture, and prove a later invalid row leaves no partial insert. A broader malformed workbook corpus has not been run.
- SEC-07/08 / AT-08/09: login preserves leading/trailing password spaces. Shared password policy requires 15 Unicode characters and enforces bcrypt’s 72-byte limit when bcrypt is the default; unit tests verify hashes rather than plaintext and existing hashes remain verified by the login suite. Interactive CLI `create_user.php` was not exercised end to end.
- SEC-11 / AT-14: frozen-clock regression verifies acceptance at 1,799 seconds and expiry at exactly 1,800 seconds.
- SEC-13 / AT-16: the app `.htaccess` disables directory listings and denies hidden/config/SQL/log/backup files, Composer manifests, `includes/`, `vendor/`, CLI user creation and installer paths. HTTP matrix covers eight sensitive paths; 8 tests / 24 assertions PASS. Parent-site files outside the `/crm/` app directory are not copied into this isolated image, so production webroot configuration still needs deployment verification.
- PAY-08 / AT-27: `task_form.php` parses supported forms into integer cents, binds canonical decimal strings to DECIMAL columns, displays validation errors without writing, and avoids float conversion for fee/collection/balance handling. Focused tests cover `100`, `100,50`, `100.50`, `1.234,56`, ambiguous input rejection and exact stored values.

Additional AT-04/05 and AT-06 regressions exercise missing-CSRF deletion across every existing delete route, GET deletion on partner/professional/diary routes, and hostile stored names/task titles on list and calendar views. Focused expanded coverage passed; details are recorded in `w02-csrf.md` and `w02-xss.md`.

Latest complete isolated suite after these changes: PASS, 97 tests / 354 assertions. Command: `docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm tests`. The runner verified `test-db / crm_w01_test` and used fictional fixtures.

## Access review and remaining criteria

Added a regression proving a logged-in `user` can edit a shared task with a valid CSRF token (ROL-02 / AT-03, existing-task slice). No existing catalog management route exists to test an admin-only boundary; AT-02 remains not applicable until the W05 catalog screens are introduced. New catalog role enforcement remains required in W05. Diary ownership regressions are recorded in `w02-diary-ownership.md`.

AT-06 and AT-07 remain PARTIAL: the full output and export route/filter matrix has not been executed, and active-professional PDF rendering remains blocked by missing Dompdf. AT-16's app-local routes are denied in the isolated HTTP matrix; hosting webroot and backup/log path verification is deferred. AT-17 needs broader malformed/corrupt workbook cases; AT-18 needs opening the exported files in a compatible spreadsheet application. `create_user.php` still needs an interactive CLI acceptance run. AT-27 covers task form inputs; broader money displays and related export totals have not received a full audit. ROL-03/AT-04 and SEC-04/AT-05 route matrices remain partial. Per the user's sequencing exception documented in `implementation-status.md`, these remaining W02 checks are deferred until after the planned feature work; W02/G01 remain incomplete and no security pass is claimed.
