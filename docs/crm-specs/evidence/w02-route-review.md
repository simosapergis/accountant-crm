# W02 route and security source review

Date: 18 November 2026.
Reviewed code: `c9652a7` on `codex/crm-remaining-tasks`.
Review type: source review against SEC-01 to SEC-06 and SEC-13, plus ROL-01 to ROL-03.

## Route inventory

The review covered 33 HTTP PHP entrypoints: the root pages and forms, client-search AJAX endpoint, eight exports and the active-professional import.
It also checked the CLI-only account creation and three `bin/` tools, shared includes and migration files for accidental web entry.

| Route group | Authentication and authorization | Methods and CSRF | Review result |
|---|---|---|---|
| Dashboard, clients, tasks, partners, professionals, calendar and partner analysis | Each page loads `includes/auth.php` before reading application data. | Mutations occur in POST handlers, and `auth.php` validates every POST token. GET views do not mutate rows. | No missing route guard found in source. Shared business records are visible to both authenticated roles as the current product model specifies. |
| Recurring task list and form | Both load `includes/auth.php`; both authenticated roles can manage recurrence, as specified by ROL-02. | Form updates, stop and restart operations are POST handlers covered by the shared CSRF guard. | No missing route guard found in source. |
| Title and payment catalogs | List and form routes all call `require_admin_role()` after authentication. | Catalog saves use POST and the shared CSRF guard. | Admin enforcement is server-side on all four routes. |
| Personal diary and event update | `mydiary.php` and `update_event.php` load `includes/auth.php`. Diary note and event reads/writes include the current session `user_id`; event update/delete predicates also include that owner ID. | Note/event writes use POST and the shared CSRF guard; `update_event.php` also explicitly requires POST. | No owner-filter omission found in the reviewed SQL. CRM tasks shown in the diary are shared tasks, not personal diary records. |
| Client save AJAX and client search | Both endpoints load `includes/auth.php`. | Save endpoint explicitly requires POST and receives the shared CSRF check. Search is read-only GET. | The save response encodes the inserted name for JavaScript; search JSON is rendered with DOM `textContent` in the task form. |
| Imports and exports | Import and all eight export scripts load `includes/auth.php`. | Import mutates only on POST and therefore receives the shared CSRF check. Exports are read-only. | Existing W02/W08 evidence covers import validation, private cache headers, export formula handling and current output routes. |
| Login and logout | Login is public with CSRF protection. Logout now runs the shared authenticated route guard after enforcing POST. | Both state-changing flows use POST and validate CSRF. | Anonymous logout is rejected by redirect without destroying its session; authenticated logout remains covered by existing tests. |
| CLI tools, includes, migrations, Composer and private files | CLI scripts check CLI execution; `.htaccess` denies `includes`, `vendor`, `logs`, backups, `bin`, migrations, dotfiles and sensitive extensions. | Not browser mutation routes. | Local HTTP denials are recorded in W02 closeout evidence. Actual hosting behavior remains unverified. |

## SQL, output and file-access review

The reviewed page, AJAX, import and export queries use prepared statements for request-derived values.
Dynamic `WHERE` fragments are assembled from fixed clauses, and client sort expressions use explicit allowlists.
The interpolated SQL interval values found are login-window, cooldown and retention settings.
`crm_security_setting()` accepts only digit strings in a bounded range before these values reach SQL.

The reviewed HTML views escape stored text for HTML text or attribute contexts.
The client autocomplete uses `textContent`, and the modal save response uses JSON hex flags before embedding the name in JavaScript.
Task status colors are selected from fixed constants rather than stored strings.
No new unescaped user-controlled text sink was identified in this review.

The application `.htaccess` denies its private paths and sensitive file extensions.
The existing local HTTP matrix tests those denials, but Apache/Plesk configuration and private backup/log locations on the actual host cannot be certified locally.

## Finding

**SEC-02 / AT-01: logout had no authenticated-session guard. RESOLVED LOCALLY.**
At the reviewed base code, `logout.php` accepted a valid-CSRF POST from an anonymous session and destroyed that session.
This did not expose or change CRM business data, but SEC-02 says only static resources and login are publicly accessible.
The loopback HTTP reproduction returned 302 to `/crm/login.php`, then a login POST using that same anonymous cookie and token returned 403 because logout had destroyed the session.

The regression `W02CsrfTest::testAnonymousLogoutPostIsRejectedWithoutInvalidatingItsSession` initially failed as expected: 1 test / 3 assertions, with the session token replaced.
The local fix now requires POST first, then includes the shared authentication guard before loading the database or destroying the session.
After the fix, anonymous POST redirects to login without invalidating its session, while authenticated logout still destroys the session.
The focused regression passed with 1 test / 3 assertions, and the full `W02CsrfTest` class passed with 40 tests / 230 assertions.
The full suite passed with 160 tests / 1,001 assertions against the guarded isolated MariaDB database `crm_test_w08_closeout_20261118`.

## Remaining limits

This source pass is not a complete runtime role/CSRF matrix.
The W02 evidence still marks route-level tests for all mutations and roles as partial, so each relevant route needs matching HTTP regression evidence before G01.
The output review used source searches and prior hostile-value tests; it does not replace a final output-context inventory and representative browser review.
The route source review began read-only; the focused logout fix and regression are the only code/test changes made in its follow-up.
