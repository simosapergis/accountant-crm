# W08 local closeout

Date: 18 November 2026.
Code: `948b24d` plus uncommitted W08 fixes on `codex/crm-remaining-tasks`.
Environment: native macOS, PHP 8.3.36, MariaDB 10.11.19.
G01 remains `NOT_EVALUATED`.

## Acceptance and fixes

- AT-05 / SEC-04: existing W02 rejection coverage is reused; W08 submits the rendered task-list POST deletion form and confirms only the selected task disappears.
- AT-18 / SEC-14: existing W02 literal-text regressions cover all spreadsheet routes, including formula-leading strings and numeric fields.
  W02 office-suite evidence remains valid; current task, client, analysis XLS and professional XLSX downloads additionally opened and converted successfully with LibreOffice.
- AT-47 / OPS-03/04: all eight export routes return their intended format without PHP diagnostics.
  The professional PDF uses Dompdf 3.1.6 with readable Greek text; the other three PDF-named routes intentionally remain HTML print reports.
  Renderer restrictions reject application-file and remote access, disable PHP/JavaScript and allow bundled Dompdf resources.
- Task print date filters now match the Excel export; client print searches include AFM.
  Both professional export formats respect search, default/explicit activity and numeric payroll selection.
- Nullable collaborator/client fields no longer emit PHP warnings in print reports.
  Task and partner-analysis footer totals align with fee/collection columns, leaving the payment-method column empty.
- Valid Greek XLSX imports preserve blank dates and VAT type as SQL NULL instead of invalid empty DATE/ENUM values.
  Existing invalid/corrupt/oversized import and transaction regressions pass.
- Nested import includes use filesystem-relative absolute paths; shared stylesheet, menu and logout URLs use the configured base path.
  Shared session startup is guarded and the authenticated home/import responses have no duplicate-session notice.
- Collaborator creation RED/GREEN is recorded in implementation-status.md and W08CollaboratorTest.

## RED and GREEN

Before fixes, W08ExportsTest: 8 tests / 66 assertions / 7 failures.
Failures reproduced actual authenticated HTTP form/export behavior: diagnostics, custom date filtering, AFM search, professional filters, financial footer alignment, nested header URLs and valid Greek upload.
After fixes, all eight cases pass in the full suite.
The remaining PDF code-wrap failure was corrected by keeping the code column together; a rendered fictional Greek report was visually inspected with Poppler.

The first full run in the manual testing database had four W06 failures because its active manual recurrence was processed by globally scoped scheduler tests.
No manual data was reset or removed.
The final run used newly created `crm_test_w08_closeout_20261118`, schema only plus fictional W01 fixtures, with a separate loopback server on port 18095.
W04's hard-coded historical database-name assertion was replaced by the existing isolated-database guard; the actual connected database equality assertion remains.

## Commands and observed results

All database commands used `DB_HOST=localhost`, `DB_USER=crm_test_native`, `DB_NAME=CRM_TEST_DB_NAME=crm_test_w08_closeout_20261118`, `CRM_TEST_ADMIN_USER=root`, `APP_ENV=test`, `PHP_INI_SCAN_DIR=/tmp/accountant-crm-preview-20261118/php-ini`, and `CRM_BASE_URL=http://127.0.0.1:18095/crm`.
DB_PASSWORD was read privately from the existing test-secret file.
The separate web process also used `APP_BASE_PATH=/crm` and a private randomly generated HMAC key.

- `php tests/bin/assert-test-db.php && tests/vendor/bin/phpunit -c tests/phpunit.xml`: PASS, 159 tests / 998 assertions.
- Final rerun after the nested-include cleanup: `php tests/bin/assert-test-db.php && tests/vendor/bin/phpunit -c tests/phpunit.xml tests/integration/W08ExportsTest.php`: PASS, 8 tests / 117 assertions.
- `CHROME_BIN='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' CRM_BASE_URL=http://127.0.0.1:18095/crm npm --prefix tests/e2e test`: PASS, 7 checks.
  The historical test label says HTTPS, but this run used HTTP loopback; it does not certify production TLS.
- `composer audit --working-dir=ACCOUNTANT_CRM --locked --no-dev`: no security advisories.
- `composer check-platform-reqs --working-dir=ACCOUNTANT_CRM --no-dev`: all requirements pass.
- `find ACCOUNTANT_CRM tests -path '*/vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l`: PASS, 79 files.
- `git -c core.whitespace=cr-at-eol diff --check`: PASS.
- `soffice --headless --convert-to pdf --outdir /tmp /tmp/w08-professionals.xlsx /tmp/w08-clients.xls /tmp/w08-tasks.xls /tmp/w08-analysis.xls`: all four conversions completed.
- `pdftoppm -f 1 -singlefile -scale-to 1200 -png /tmp/w08-greek-report.pdf /tmp/w08-greek-report`: rendered and inspected readable Greek report.

## Remaining gates

W08 has no known remaining local acceptance blocker.
Actual hosting Composer/extensions, private paths, PHP/webserver behavior and production exports remain deployment verification.
Independent G01 review and W05-W07 acceptance sign-off remain separate work.
