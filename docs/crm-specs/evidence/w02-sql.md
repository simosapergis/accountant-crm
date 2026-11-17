# W02 evidence: prepared query slice

Date: 17 November 2026.
Code baseline commit: `9dd265d` with follow-up W02 work in the working tree.
Requirement: SEC-06; acceptance: AT-07 (partial).

## Scope reviewed

The main list and autocomplete searches in `clients.php`, `partners.php`, `tasks.php`, `active_professionals.php`, and `ajax/search_clients.php` previously interpolated user values after `real_escape_string()` or integer casting. Client category, task status/date filters, professional filters and search patterns now use prepared statements. Optional conditions add placeholders and values separately. Client ordering remains selected from fixed SQL fragments; all other ordering in this slice is fixed.

`mydiary.php` also had escaped values inserted into note SQL and into event values before a prepared insert. Notes, event writes, month task/event reads, selected-day notes and selected-day event reads now bind values through prepared statements. The event insert no longer stores the extra backslash produced by escaping before binding.

Non-string query parameters for these paths are normalized to empty values before search/filter construction, avoiding array-to-string type errors.

## Regression coverage

Integration cases send SQL-like text (`' OR 1=1 --`) through client, partner, task, active-professional and autocomplete search paths and assert known fixture records are not returned. These tests passed against the original escaped queries before refactoring, so no behavioral RED was expected: the source audit identified a requirement violation in query construction, while existing escaping already blocked the sample exploit. The tests preserve the observable safety behavior as those queries move to placeholders.

The MyDiary regression first failed because an apostrophe in a fictional event title/description did not round-trip to the database. The latest full PHPUnit run passes 59 tests / 195 assertions against the guarded `test-db / crm_w01_test` environment.

## Follow-up

The remaining task/client/professional export filters and partner-analysis export identifiers were reviewed in [w02-exports.md](w02-exports.md). Those export query values are now bound, invalid partner IDs are rejected, and client sorting is allowlisted. SEC-06 / AT-07 remains partial until the broader route, ID, custom-date and sort matrix is complete. G01 remains `NOT_EVALUATED`.
