# W02 local closeout evidence

Date: 2026-11-18. Environment: macOS native PHP 8.3.36 and MariaDB 10.11.19.
Database: isolated `crm_test_native_20261118` on a temporary MariaDB data directory; fixtures are fictional.

## Coverage and results

- AT-01: an automatic inventory discovers PHP entrypoints and confirms unauthenticated requests redirect or are denied; only the public login and CLI account creator are excluded.
- AT-02/03: admin-only catalog actions, shared task use by regular users, and recurrence behavior have integration coverage in W05/W06 tests.
- AT-04/05: private diary event/note reads, foreign event update/delete, note write isolation, missing CSRF across mutation entrypoints, invalid/foreign CSRF and GET deletion are covered by HTTP integration tests.
- AT-06: source review covered HTML text/attribute contexts, JavaScript/JSON handoff, autocomplete, diary values, imports, exports, catalog names and task/calendar views; regression tests include hostile values in the principal persisted contexts.
- AT-07: dynamic query review found no request values interpolated into direct `query()` calls. Variable values use prepared statements; query fragments are fixed filters or allowlisted sorting. SQL-like input cases are covered by integration tests.
- AT-08/09: password policy/unit and login tests pass. `create_user.php` was run interactively against the isolated test database; the resulting row was confirmed to contain a supported password hash. No password or credential was emitted in verification output.
- AT-13/14/15: browser E2E verifies session ID rotation, cookie flags/path, logout invalidation and protected-route rejection; idle-boundary and no-cache behavior have integration tests.
- AT-16: native HTTP checks deny app internals, credentials/config, SQL, backup/log paths and CLI routes. `.htaccess` explicitly blocks includes, vendor, logs/backups, bin and migrations. Production hosting/webroot is outside this local result and remains a deployment check.
- AT-17: integration tests reject oversized, renamed, corrupt and truncated XLSX inputs, prevent partial writes and avoid parser path disclosure.
- AT-18: authenticated application exports were downloaded with fictional data and opened by LibreOffice headless. The task `.xls` and professional `.xlsx` exports converted successfully to PDF. Formula-leading text is separately verified as literal text in export tests.
- AT-27: integer-cent parsing and persistence tests cover accepted/rejected formats; an HTTP test verifies exact aggregate values `100,10`, `0,03` and `100,07` in the client summary.

## Commands and results

`php tests/vendor/bin/phpunit -c tests/phpunit.xml` with the guarded native environment — PASS, 142 tests / 750 assertions.

`CHROME_BIN='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' CRM_BASE_URL=https://127.0.0.1:18443/crm npm --prefix tests/e2e --cache /tmp/crm-w01-native-npm-cache test` — PASS, 7 browser checks under `/crm/`.

`soffice --headless --convert-to pdf --outdir /tmp/accountant-crm-preview-20261118 /tmp/accountant-crm-preview-20261118/active-professionals.xlsx /tmp/accountant-crm-preview-20261118/tasks.xls` — PASS; Calc opened both application exports and converted them.

`find ACCOUNTANT_CRM -path '*/vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l` — PASS, 53 application PHP files.

`find tests -path '*/vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l` — PASS, 16 test PHP files.

`git diff --check` — PASS.

The temporary HTTPS proxy used for E2E had a self-signed certificate and was bound to loopback; it verifies application cookie behavior, not production TLS. No production database or hosting was accessed. G01 remains `NOT_EVALUATED` because W03, W04 and W08, plus independent baseline review, remain outstanding.
