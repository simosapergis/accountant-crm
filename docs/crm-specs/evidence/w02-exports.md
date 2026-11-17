# W02 evidence: export filters, identifiers and output review

Date: 18 November 2026.
Branch base: `48209a8` (`codex/crm-w02-security-audit`).
Requirements: SEC-05, SEC-06; acceptance: AT-06 and AT-07 (partial).

## RED / GREEN

Added `tests/integration/W02ExportSqlTest.php` before changing export query construction. The first focused run against `test-db / crm_w01_test` failed all 5 cases (5 assertions): malformed array filters and SQL-like partner identifiers were accepted with HTTP 200. After adding validation and prepared statements, two cases still failed because the XLS endpoints had already emitted output before setting HTTP 400. Moving headers/body output until after input validation and successful query execution produced GREEN.

Final focused command:

```sh
docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 build app
docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm tests "php /opt/crm-tests/bin/assert-test-db.php && php /opt/crm-tests/bin/seed-w01-fixtures.php && /opt/crm-tests/vendor/bin/phpunit -c /opt/crm-tests/phpunit.xml --filter W02ExportSqlTest"
```

Result: PASS, 12 tests / 32 assertions. Coverage exercises task/client/professional export SQL-like filters, malformed array inputs, strict numeric partner IDs in both analysis exports, valid partner IDs, active-professional list filter names, client balance filter parity, and HTML escaping in export-link attributes. The active-professional XLSX response is parsed with PhpSpreadsheet to inspect returned cell values. Client export sorting is selected from fixed SQL fragments; all other sort clauses in these exports are fixed.

The export filters now bind values in task Excel/PDF, client Excel/PDF, active-professional Excel/PDF and partner-analysis Excel/PDF. Partner IDs reject non-decimal or out-of-range values with HTTP 400. The active-professional exports accept the parameter names used by their list page and support both active and inactive selection. Client exports now use the list's `positive` / `zero` balance values and filter after aggregation. No dynamic SQL identifier comes from a request value.

## SEC-05 review

The active-professional PDF builder now escapes code, fullname, dates and VAT type before inserting values into Dompdf's HTML. The list page escapes its generated export query string at the `href` attribute boundary. Existing targeted output fixes remain covered in `w02-xss.md`.

The active-professional PDF export could not be exercised end to end: the isolated app image does not contain Dompdf, and the route fails with `Class "Dompdf\\Dompdf" not found`. This is the known W08 dependency item; no missing dependency was added as part of this security slice. That route's prepared filter SQL is source-reviewed and PHP-parsed, but AT-06/AT-07 output behavior for that export remains unverified.

## Access review and remaining criteria

Added a regression proving a logged-in `user` can edit a shared task with a valid CSRF token (ROL-02 / AT-03, existing-task slice). No existing catalog management route exists to test an admin-only boundary; AT-02 remains not applicable until the W05 catalog screens are introduced. New catalog role enforcement remains required in W05. Diary ownership regressions are recorded in `w02-diary-ownership.md`.

AT-07 remains PARTIAL: these export families are covered, but the complete route/ID/date/sort matrix has not been executed. AT-06 remains PARTIAL because the entire output surface and the PDF route have not been verified. Existing W02 gaps include password policy/hash coverage (AT-08/09), idle-timeout boundary and cache headers (AT-14/15), sensitive file/route exposure (AT-16), import bounds/format (AT-17), formula-safe exports (AT-18), and amount parsing (AT-27). W04/W08 and later QA dependencies remain as specified; G01 is not evaluated.
