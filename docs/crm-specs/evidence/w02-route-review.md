# W02 route and security source review

Date: 18 November 2026.
Reviewed code: `c9652a7` on `codex/crm-remaining-tasks`.
Review type: source review against SEC-01 to SEC-06 and SEC-13, plus ROL-01 to ROL-03.

## Route inventory

The review covered 33 HTTP PHP entrypoints: the root pages and forms, client-search and client-save AJAX endpoints, eight exports and the active-professional import.
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

The final entrypoint reconciliation below enumerates each web route from the current application tree.
All are included in the anonymous entrypoint inventory in `W01EnvironmentTest`, except public `login.php`; CLI `create_user.php` is excluded from the HTTP inventory.

| Route(s) | Guard or operation | Runtime evidence |
|---|---|---|
| `/login.php` | Public login only; CSRF required for POST. | `W02CsrfTest` missing-token and password behavior; W04 throttle suite. |
| `/logout.php` | Authenticated POST only; CSRF required. | `W02CsrfTest::testLogoutRequiresCsrfProtectedPostAndEndsSession` and `testAnonymousLogoutPostIsRejectedWithoutInvalidatingItsSession`. |
| `/index.php` | Authenticated shared dashboard. | `W01EnvironmentTest`; browser inspection; shared-header escaping test in `W02CsrfTest`. |
| `/clients.php`, `/partners.php`, `/tasks.php`, `/active_professionals.php`, `/calendar.php`, `/analysis_partners.php`, `/partner_tasks.php` | Authenticated shared-data views; request filters use prepared values/allowlists. | `W02CsrfTest` SQL-like search tests, XSS list/calendar test and W08 export/integration coverage; anonymous inventory. |
| `/client_form.php`, `/partner_form.php`, `/active_professional_form.php`, `/task_form.php` | Authenticated forms; mutations require POST and shared CSRF; deletion cannot use GET. | `W02CsrfTest` POST/CSRF/delete/task tests; `W08CollaboratorTest`; `W05TaskPaymentsTest`; anonymous inventory. |
| `/mydiary.php`, `/update_event.php` | Authenticated personal diary; owner ID is part of diary reads and writes; POST mutations require CSRF. | `W02CsrfTest` foreign note/event read, update, delete, and mutation tests. |
| `/recurring_tasks.php`, `/recurring_task_form.php` | Authenticated shared recurrence view and management; POST mutations require CSRF. | `W06RecurringTasksTest` user create, update, stop, restart, idempotency, retry and scheduler cases; anonymous inventory. |
| `/payment_methods.php`, `/payment_method_form.php`, `/task_titles.php`, `/task_title_form.php` | Authenticated admin-only catalog access, including direct form URLs. | `W05CatalogsTest::testUserCannotViewOrPostToCatalogManagement` checks user GET denial for all four screens and denied writes; admin create/edit/deactivate cases. |
| `/ajax/search_clients.php` | Authenticated read-only JSON search; request values bound. | `W02CsrfTest::testPartnerAndAutocompleteSearchesDoNotBroadenForSqlLikeInput`; autocomplete XSS/browser checks. |
| `/save_client_ajax.php` | Authenticated POST, shared CSRF, prepared insert, JSON-safe response. | `W02CsrfTest::testAjaxClientCreationDoesNotEmbedSubmittedNameAsExecutableScript` and all-POST missing-token sweep. |
| `/imports/import_active_professionals.php` | Authenticated POST, shared CSRF, upload format/size validation and transactional row validation. | `W02CsrfTest` hostile feedback, invalid/oversized/corrupt/truncated/invalid-row cases; `W08ExportsTest` valid import flow. |
| `/exports/export_active_professionals_excel.php`, `/exports/export_active_professionals_pdf.php`, `/exports/export_analysis_partners_excel.php`, `/exports/export_analysis_partners_pdf.php`, `/exports/export_clients_excel.php`, `/exports/export_clients_pdf.php`, `/exports/export_tasks_excel.php`, `/exports/export_tasks_pdf.php` | Authenticated read-only exports; private cache headers; spreadsheet formula handling and context-safe output. | `W08ExportsTest`, `W08PdfTest`, `W02ExportSqlTest`, `W02CsrfTest::testPartnerExcelExportEscapesUntrustedPartnerName`; anonymous inventory. |

This is route-by-route source and test reconciliation, not a statement that each route has a separate test method.
Shared guard behavior is covered by the common HTTP regressions, while route-specific business mutations are covered by their acceptance suites.
The local suite does not prove the Apache/Plesk configuration on the production host.

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

## Follow-up verification

On the current follow-up checkout, `W05CatalogsTest::testUserCannotViewOrPostToCatalogManagement` also checks direct GET access to both catalog form routes and confirms denied writes leave no catalog rows.
The focused suite passed against the guarded isolated database: 7 tests / 62 assertions.

The broader HTTP behavior is distributed across the acceptance-specific integration suites rather than a single route matrix class:

- Anonymous access and web exposure: `W01EnvironmentTest`.
- Missing, invalid, cross-session and valid CSRF; all POST entrypoints; GET deletion; logout; diary ownership: `W02CsrfTest`.
- Admin/user catalog permissions: `W05CatalogsTest`.
- Shared task and recurrence access for both roles: `W02CsrfTest` and `W06RecurringTasksTest::testAdminCanEditSharedTaskAndCreateMonthlyRule`.
- Catalog/task/payment mutations and server validation: `W05CatalogsTest` and `W05TaskPaymentsTest`.
- Recurrence create, update, stop, restart, retries and idempotency: `W06RecurringTasksTest`.
- Collaborator, professional, import and export flows: `W08CollaboratorTest` and `W08ExportsTest`, with import rejection cases in `W02CsrfTest`.

The first focused test attempt failed at setup because the loopback server lacked `CRM_SECURITY_HMAC_KEY`; this was a harness configuration issue. After starting the loopback server with a fictional test-only key, the guarded focused suite passed.
The full suite subsequently passed on the established isolated closeout schema: 161 tests / 1,014 assertions after adding the admin-role route regression.
The route-by-route source/test reconciliation is recorded above.

The source output review was extended through HTML/JavaScript sinks, SQL query sites and file access sites across application PHP files.
The search confirms remaining direct `query()` calls are fixed-value reads, schema/migration operations, scheduler bookkeeping or bounded validated retention intervals; request-derived filters are prepared and sort expressions are allowlisted.
Output sinks were reviewed with context-aware escaping or fixed markup, with client autocomplete data inserted via `textContent` and fixed literal `innerHTML` used only for static status markup.
Existing hostile-value regressions cover shared headers, list/calendar pages, task forms, diary JavaScript, import feedback, autocomplete and partner spreadsheet HTML.
The representative rendered browser inspection is recorded below.

The route review began from `c9652a7`; the logout correction was committed as `393ac41`.
The catalog-form authorization test extension is included in commit `df84d7b`.

## Browser inspection

On 18 November 2026, the app was opened at `http://127.0.0.1:18096/crm/` in the local in-app browser and signed in with the fictional `w01-fake-user` test account.
At the browser's 495-pixel mobile viewport, the dashboard rendered its task totals and both filter groups, and the hamburger menu exposed the expected navigation links.
The task list showed the fixture task and collection method column; the new task form showed title, client search, partner, status, fee, collection, payment method, balance and recurrence controls.
The client list displayed the hostile XSS fixture as literal text, with no image or script execution observed.
The personal diary rendered the November 2026 month grid, selected-day notes and appointment form.
These were read-only inspections; no records were submitted or changed.

The recurring-task screen rendered its heading followed by a blank rounded white panel when there were no rules.
The panel is visually unexplained and looks like an empty list container without empty-state text or action.
This should be reviewed as a UI defect; no change was made during the security audit.

The full PHPUnit suite passed on the established isolated database after setting `APP_ENV=test` and `CRM_TEST_ADMIN_USER=root`: 161 tests / 1,014 assertions.
An earlier run on `crm_test_native_20261118` was invalid as a full-suite baseline because it omitted those test-only runner settings and contained unrelated recurrence rows; it failed 6 tests / 979 assertions.
No cleanup was performed on that database.

## Independent review of the reconciled state

The independent read-only review checked exact commit `df84d7bd56b1b039be62f73a7861985e9bbc780f` and found no new critical or high security issue in the reviewed changes.
It confirmed the 33-route inventory, the admin-role task/recurrence regression, and the recorded focused and full-suite results.
The reviewer did not approve G01 because the recurring-task empty screen has an unexplained blank panel and remains an open UI issue.
G01 remains `NOT_EVALUATED` until that issue is resolved and the exact final state is reviewed.
On 18 November 2026, the user asked to leave the UI unchanged and keep the issue documented.

## Recurring-task empty-state regression and fix

The user later authorized fixing this issue.
The empty state was reproduced in the authenticated browser with zero recurring rules in the guarded isolated database; before the fix, the screen displayed only the table header inside a blank panel.
The new regression `W06RecurringTasksTest::testEmptyRecurringTaskListExplainsHowToCreateTheFirstRule` failed before the fix because the response had no empty-state guidance or create action.
The page now replaces the empty table with a labeled empty-state section that explains how to create the first recurring rule and links to the task form, where monthly recurrence can be enabled.
The existing recurring-rule creation test now also verifies that the non-empty list still displays its rule and does not show the empty-state message.
Focused `W06RecurringTasksTest` passed with 19 tests / 138 assertions; the full guarded PHPUnit suite passed with 162 tests / 1,024 assertions.
Browser verification at a 1280-pixel viewport showed the guidance and action in a centered panel; activating the action opened `/crm/task_form.php` with the recurrence checkbox visible.
No data was created or changed during the browser inspection.
The same desktop screenshot shows the authenticated username clipped at the right edge of the top navigation.
This is a separate visual issue and was not changed as part of the authorized empty-state fix.
The earlier independent review applies to commit `df84d7b`, before this fix; an independent re-review of the final fix commit is required before recording any G01 result.

## Independent review after the empty-state fix

The independent reviewer checked commit `51f2100ad85a4166c145a2cb5b989d2ebc87c955`, confirmed the recurring empty-state fix and found no new critical/high security issue.
The reviewer identified the clean recurrence schema as a test precondition because rules are shared and processed globally.
That precondition is now asserted by the empty-state HTTP test and documented in `tests/README.md`; focused W06 and full-suite runs passed again afterward.
The reviewer also reported that the signed-in username is clipped at the right edge of the desktop header.
The user authorized a fix.
The browser regression failed before the fix at 1280 pixels because the desktop menu was still displayed and overflowed the viewport.
The shared header now uses border-box sizing, tighter flexible desktop navigation, and switches to the compact drawer through 1400 pixels.
The E2E regression verifies no horizontal overflow at 1280 pixels, that the user name and logout remain available in the compact drawer, and that the desktop account block stays inside a 1600-pixel viewport.
All 8 browser E2E checks passed after the fix, and the full guarded PHP suite passed with 162 tests / 1,024 assertions.
Manual browser inspection confirmed the compact menu and account name display correctly at 1280 pixels.
The independent reviewer checked exact commit `251a35c1251c4307f81afe6b6a9300fd9db5c1e7`, confirmed the empty-state and header fixes and recurrence test precondition, and found no new critical/high security issue.
The reviewer confirmed the corresponding E2E and PHPUnit results.
G01 remains `NOT_EVALUATED` because its checklist still requires a human inspection of the key layouts.

## Navigation drawer width alignment follow-up

After the user requested a closer layout review, the open compact navigation drawer was found to be 20 pixels wider than the header at a 390-pixel viewport.
The E2E geometry check reproduced the mismatch: the header occupied x=10..380 while the drawer occupied x=0..390.
The drawer is now positioned relative to the header with its left and right edges set to the header bounds, and its top edge aligned to the bottom of the header.
The browser regression checks matching drawer/header edges at 390 and 1280 pixels, no horizontal page overflow at 390 pixels, and the desktop navigation bounds at 1600 pixels.
After the fix, all 8 browser E2E checks passed.
The earlier full guarded PHPUnit suite passed with 162 tests / 1,024 assertions on `crm_test_w08_closeout_20261118`.
On the final repeat, MariaDB stopped accepting connections during W08 export tests: 162 tests ran with 4 connection errors and 2 downstream failures after 64 seconds.
The repeat did not establish a code regression; those failing cases could not connect to the verified database.
PHP lint, Node syntax validation and CRLF-aware `git diff --check` passed.
This follow-up requires an independent review of its exact code commit.
G01 remains `NOT_EVALUATED` pending that review and the required human inspection of the key layouts.

## Independent review of navigation drawer alignment

The independent reviewer checked exact code commit `63fc9a7a603c90c54f7c00ac989327b715e7b50d`.
The reviewer confirmed the header-relative bounds match the compact body padding and that the E2E assertions cover 390- and 1280-pixel alignment, mobile overflow and 1600-pixel desktop geometry.
No security or functional regression was found, and the reviewer supports the code change.
This is not a G01 PASS: a fresh green full-suite rerun and the required human layout inspection remain outstanding.

## Catalog mobile-card redesign and visual acceptance

The user reported that the task-title and payment-method tables disappeared on small screens and rejected the dense narrow table layout.
The browser regression first failed because the mobile card list was missing.
At widths up to 768 pixels, both catalog pages now show individual cards with the option name, active status and a full-width edit action; an empty catalog shows an explanatory message.
The desktop table remains visible above the mobile breakpoint.
The browser suite passed all 8 checks, covering both catalogs at 390 and 320 pixels and the desktop table at 1024 pixels.
PHP lint, Node syntax validation and CRLF-aware `git diff --check` passed.
The user visually approved the redesigned catalog pages on 18 November 2026.
The full guarded PHPUnit suite passed with 162 tests / 1,024 assertions on the new clean isolated database `crm_test_g01_mobile_catalog_20261118`; this includes the W05 catalog integration tests.
The browser suite passed all 8 checks against the same database after the full suite, confirming that the seeded admin fixture remains available.
An independent review of this latest code state and any remaining human layout acceptance remain outstanding; G01 remains `NOT_EVALUATED`.
