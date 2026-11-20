# ElMiCo CRM - Κατάσταση υλοποίησης

### CLT-05..08: ενιαία οθόνη πελατών - 20 Νοεμβρίου 2026

Καταγράφηκε η εγκεκριμένη συμπεριφορά CLT-05..08 / AT-63..64: οι ιδιώτες και οι επαγγελματίες διαχειρίζονται από την ενότητα Πελάτες, με κοινή εγγραφή, φίλτρα, imports και exports. Προστέθηκαν στη λίστα πελατών οι μετρητές ιδιωτών, επαγγελματιών, ενεργών και ανενεργών επαγγελματιών, τα επαγγελματικά φίλτρα και τα επαγγελματικά πεδία στην προβολή. Η κύρια φόρμα πελάτη αποθηκεύει τώρα και τα δύο σύνολα πεδίων. Τα Excel/PDF exports πελατών περιλαμβάνουν τα επαγγελματικά πεδία και τα ίδια φίλτρα. Το import επαγγελματιών είναι προσβάσιμο από τη λίστα πελατών, ενώ η παλιά λίστα επαγγελματιών ανακατευθύνει με συμβατό φίλτρο.
Δεν προστέθηκαν ούτε εκτελέστηκαν tests ή syntax checks, σύμφωνα με ρητή οδηγία χρήστη. AT-63..64 παραμένουν `NOT_RUN`. Δεν εκτελέστηκε migration ή deployment και δεν άλλαξαν δεδομένα βάσης.
Επόμενο: έλεγχος/εκτέλεση acceptance tests όταν ο χρήστης το ζητήσει ρητά, κατόπιν οπτική επιθεώρηση preprod και έλεγχος εισαγωγής/εξαγωγής και παλιών διαδρομών.

### CLT-02..04: unified clients and active-professional profile - 19 November 2026

The specification package is updated to version 1.8 (CLT-02..04, AT-59..62). The user chose to keep possible duplicate client/professional records separate; no name-based or other automatic identity merge is performed.
Added migration `20261119_002_unified_clients.php`: extends `clients`, copies legacy professional rows into new client records with their full professional fields, records legacy IDs for resumable copying, and renames the old table to `active_professionals_legacy` as a retained rollback source. Existing client IDs are unchanged, preserving task and recurrence references.
Active-professional forms, listing, PDF and XLSX now read/write the shared `clients` table. The form includes all client fields and professional fields. Removing a professional profile now deactivates the profile and preserves the client and linked tasks. XLSX import writes unified rows, exports all 12 fields, and retains the legacy 8-column import layout.
No tests or syntax checks were run, as requested. Feature acceptance AT-59..62 is therefore `NOT_RUN`; deployment/migration has not been executed against any database.
Next: after explicit authorization and backup, run the migration in an isolated database, then manually inspect the feature and exercise both Excel layouts. Do not run it against production as part of this local implementation.

### AT-54 local acceptance follow-up - 19 November 2026

Verified starting branch `codex/crm-remaining-tasks`, exact HEAD `7f83ed7525084efa318c1ff5ad80b49eb09647a5`, matching the requested remote HEAD; the worktree was clean before this follow-up.
The host is macOS with PHP 8.3.36, Composer 2.11.2 and MariaDB 10.11.19, so the Linux Compose harness and its reported 87 failures were not rerun and remain unresolved.
The prior isolated database `crm_test_w09_20261119` contained the identified `W01 Mobile Recurring Filing` browser rule, so it was preserved and not used for the full-suite run.
A new empty `crm_test_at54_20261119` database and restricted non-root user were created on the verified local test-owned MariaDB server; the root SQL dump contains no `INSERT` rows and imported schema only.
The database guard confirmed the exact target before schema setup, migration and each fixture-seeding step.
Applied migrations `20261118_001_catalogs`, `20261118_002_recurring_tasks`, `20261118_003_security_events` and `20261119_001_users_utf8mb4`, then seeded only the fictional W01 login/client fixtures.
The first full PHPUnit attempt ran without the loopback application server and failed 135 HTTP/session-dependent checks; no such failure is treated as a product regression.
After starting the loopback PHP server under `/crm/`, the recurrence tables were verified empty and the complete suite passed: **210 tests / 1,282 assertions**.
Only after PHPUnit passed, seeded 250 fictional AT-54 picker clients in IDs 911000-911249 and the documented `W01 Mobile Recurring Filing` browser fixture.
The browser suite passed **14/14 checks**; its new AT-54 check found a unique client by AFM without matching its name, restored all 252 results when cleared, scrolled the results container to its actual bottom, and selected the final returned client with the corresponding ID.
The observed list rendered in 59 ms and reached an inner scroll position of 15,711 px; the regression allows up to 8 seconds to avoid treating a minor local timing variation as a failure.
The first browser attempt failed only because the assertion assumed the last rendered client's name rather than the API's sorted final result; the test now compares against the returned final record and checks its selection.
Added the idempotent, guarded fictional fixture seeder [seed-at54-client-picker.php](../../tests/bin/seed-at54-client-picker.php) and the AFM/long-list browser regression in `tests/e2e/login-page.test.cjs`.
AT-54 is now PASS locally for the specified authenticated loading, AFM/name filtering, clear-to-all behavior, selected client ID/name, unauthenticated denial, long-list internal scrolling and measured render bound.
No fixture rows were deleted; the new test database retains its clearly identified fictional client fixtures and one documented mobile browser recurrence fixture.
AT-56 remains PARTIAL without authorized pre-production access; hosting-dependent AT-43/44/45/48/50/51 remain open or partial.
No new G01 or W09 PASS is granted, and the historical G01 PASS remains scoped to its recorded earlier commit.
Next: rerun the corrected Compose harness on Linux to investigate the 87 reported failures, then continue the remaining independent acceptance evidence without deployment or production access.
Exact commands, first-run failures, fixture ordering and scope limits are recorded in [W09 acceptance evidence](evidence/w09-acceptance.md).

### DASH-01/DASH-02: dashboard shortcut and task list filters - 19 November 2026

Updated the specification package to version 1.7 with DASH-01/AT-57 for the «Αναμονή εξόφλησης» card and DASH-02/AT-58 for exact task title/payment-method filtering.
The dashboard card now links to the existing positive-client-balance filter, ordered by balance. The existing dark balance card background is lighter (`#6c757d`) with explicit white text and visible keyboard focus.
The task list now filters by exact stored `tasks.title` and payment label snapshots; dropdown options come from existing task rows, preserve historical labels, and include a «Χωρίς μέσο» option. All conditions are prepared and combine with existing filters.
TDD RED: `testTaskListFiltersByStoredTitleAndPaymentMethodSnapshots` failed before the filter implementation because the unfiltered response still contained the nonmatching task. A dashboard browser check also found the pending card was not actionable before the link was added.
GREEN: focused PHPUnit class `W05TaskPaymentsTest` passed **11 tests / 105 assertions** in the isolated `crm-w01-dashboard` Compose project, including combined snapshot filters, missing-method filtering, and dashboard card destination/positive client filtering. PHP 8.3 syntax checks passed for `tasks.php` and `W05TaskPaymentsTest.php`.
The full guarded suite earlier in this turn completed 210 tests with 2 unrelated failures because `pdftotext` is absent (`W08ExportsTest` and `W08PdfTest`). The final Playwright suite was not completed; its pre-change run showed an unrelated existing AT-49 focus-indicator failure at 320px. AT-57 has HTTP integration coverage; browser E2E rerun remains NOT_RUN on the final image.
The local `accountant-crm-app-1` container had stale environment and omitted `APP_ENV`; recreating only that app container restored its Compose `development` environment. Local login returned HTTP 200 with no configuration error; the database container was not recreated.
W03/W09 and the historical G01 result are unaffected. No Plesk files or production database were changed.
Next: complete a final browser run for AT-57/58 when the permitted browser runner is available; review the combined local diff, including the earlier documentation edits.

### Plesk pre-production check: private config and HTTP access - 19 November 2026

Ο χρήστης ολοκλήρωσε περιορισμένη δοκιμή σε pre-production CRM εγκατάσταση με ξεχωριστή, νέα βάση δεδομένων. Δεν καταγράφηκαν credentials, HMAC key ή προσωπικά δεδομένα.
Το private PHP config τοποθετήθηκε σε sibling ιδιωτικό φάκελο έξω από το document root. Ο χρήστης ρύθμισε owner-only permissions· ο κοινός loader το διάβασε επιτυχώς και αποδέχθηκε path/permissions τόσο από web όσο και από CLI.
Η επιτυχής εφαρμογή των `20261118_001_catalogs`, `20261118_002_recurring_tasks`, `20261118_003_security_events` και `20261119_001_users_utf8mb4` αποδεικνύει ότι το CLI συνδέθηκε στη νέα pre-production βάση. Το web login λειτούργησε μετά από ανανέωση του browser session σε ιδιωτικό παράθυρο, επιβεβαιώνοντας τη web διαδρομή config/DB/HMAC.
Πραγματικά HTTP requests επέστρεψαν `403` για `.htaccess`, `composer.json`, `composer.lock`, `vendor/autoload.php`, `includes/db.php`, `bin/migrate.php`, `bin/run-recurring-tasks.php`, migration PHP και `create_user.php`. Το `assets/style.css` και το `login.php` επέστρεψαν `200`.
Το υποσύνολο του AT-56 για θέση εκτός document root/repository, αποδεκτά permissions, ανάγνωση web/CLI, κοινή pre-production βάση/HMAC ρύθμιση και HTTP αποκλεισμό των δοκιμασμένων εσωτερικών διαδρομών καταγράφεται `PASS`. Το CLI migration χρησιμοποίησε το ίδιο private config και συνδέθηκε στη νέα βάση· ο loader επικύρωσε και το HMAC key. Η επιτυχής web σύνδεση επιβεβαίωσε ότι το web runtime φόρτωσε την ίδια ρύθμιση και χρησιμοποίησε το HMAC. Δεν υπάρχει δημόσιο URL προς το config επειδή βρίσκεται εκτός document root· δεν έγινε αίτημα που να στοχεύει απευθείας το φυσικό αρχείο.
Το συνολικό AT-56 παραμένει `PARTIAL`: δεν έχουν δοκιμαστεί encrypted backup/restore και πλήρης αποτυχία φόρτωσης με ελλιπές config. Οι ακριβείς απόλυτες διαδρομές και οι runtime UID/GID δεν καταγράφηκαν. Δεν προκύπτει από αυτό το checkpoint παραγωγικό sign-off ή νέο G01/W09 PASS.
Το `/crm/create_user.php` επέστρεψε `403`. Το `403` επιβεβαιώθηκε από τον χρήστη.
Ο χρήστης επιβεβαίωσε ότι τα προσωρινά probes και Scheduled Tasks αφαιρέθηκαν.
W03 παραμένει `IN_PROGRESS` για τα υπόλοιπα hosting acceptance checks και W09 παραμένει `IN_PROGRESS`.
Next: συνέχισε με τα εκκρεμή backup/restore και pre-production checks.

### W03/W09 continuation at config-loader HEAD - 19 November 2026

Verified branch `codex/crm-remaining-tasks` at `bf71c7119b5ea351069d955286dc8fe0ac7d8de8`; it matches `origin/codex/crm-remaining-tasks` and the worktree was clean before this documentation update.
The private config-loader evidence below corresponds to this exact HEAD; its local fictional-config PHPUnit, browser, lint, Compose YAML and Node results are recorded in the preceding W03 checkpoint and [W09 evidence](evidence/w09-acceptance.md).
Rechecked `tasks.md`, `acceptance.md`, the config-loader design and deployment runbook before evaluating the next hosting-dependent step.
At the time this local checkpoint was recorded, no authorized pre-production hosting target or access mechanism was available in the workspace, so hosting checks had not been run.
The user subsequently authorized and performed the limited Plesk pre-production checks recorded in the entry above; those results supersede the hosting status below but do not change this local evidence.
No production deployment or backup operation was performed, and no production or personal data was used.
AT-56 remains PARTIAL: private config placement/permissions, web/CLI loading of the same pre-production DB/HMAC config, and tested HTTP denials now have pre-production evidence; encrypted backup/restore, fail-closed behavior on invalid hosting config, and runtime identity details remain open.
The prior local full suite result is 210 tests / 1,282 assertions PASS, followed by 13 browser checks PASS; the required order is PHPUnit first, then seed the `W01 Mobile Recurring Filing` fixture before browser execution.
These local results do not close AT-43/44/45/48/50/51, do not establish new G01 or W09 PASS, and do not establish full AT-56 completion.
W03 and W09 remain `IN_PROGRESS`; the historical G01 PASS remains scoped to its recorded older commit.
Next: continue only independent local evidence work that does not depend on hosting; obtain an authorized non-production target before attempting AT-56 or other hosting-dependent criteria.

### W03 private config loader - 19 November 2026

Pulled documentation commit `52dbc91` and merged as `aa45987`, preserving prior W09 checkpoints.
Implemented common private PHP config loading for DB, session, security settings and recurring CLI through `includes/config.php`.
The config file is authoritative; the loader supports the PHP ini locator, `CRM_CONFIG_FILE`, an external default, and process-only test/development environment fallback.
Validation rejects incomplete or invalid values, public/repository paths, `wp-config.php`, unsafe permissions and output/errors; failures return a generic HTTP 503 or CLI exit 78.
The SET agent added subprocess unit coverage and independently reviewed the loader; CLI working-directory, `open_basedir` warning leakage and permitted-hosting-directory cases were reproduced and fixed.
Authenticated HTTP and CLI migration tests use the same private fictional config and guarded MariaDB target, with deliberately conflicting environment credentials.
Final private-file full suite: **210 tests / 1282 assertions PASS** on PHP 8.3.36 and MariaDB 10.11.19.
Browser suite then passed **13 checks**, including AT-49 viewport/keyboard coverage and the AT-54 exercised client-picker flow.
The browser mobile recurrence fixture is seeded only after PHPUnit; it can make W06 tests fail if the full suite is run afterwards because the scheduler processes shared rules globally.
The successful import response warning and nullable contact-field deprecations were reproduced with tests and fixed; the full private-file suite includes these regressions.
`git diff --check`, focused PHP lint, Compose YAML parsing and Node syntax checks pass.
AT-56 remains PARTIAL: the local test establishes config resolution, permissions checks, HTTP denial in the local `/crm/` harness and web/CLI parity, but actual Plesk identity/permissions and encrypted backup/restore remain unverified.
W03 remains IN_PROGRESS for hosting acceptance; W09 remains IN_PROGRESS and no G01/W09 PASS is granted.
Next: complete the independent review, then commit this checkpoint; keep Linux Compose rerun, AT-43/44/45/48/50/51 and Plesk AT-56 hosting checks open.

### W09 post-pull verification - 19 November 2026

Committed the preserved W09 diff as `9c912e5`, pulled remote `152021f`, and completed merge `d8ab822` while retaining both sides of the progress-document conflict.
Remote additions include users utf8mb4/AT-55, client picker CLT-01/AT-54, test Compose changes and deployment documentation.
Created and verified the new isolated native target `crm_test_w09_20261119`; the older fixture database was not cleaned or modified.
The merged W06 passed 21 tests / 158 assertions with the required test HMAC configuration.
Fixed two independently reviewed harness issues: missing shared test HMAC in Compose and W03 granting the wrong account host on native MariaDB.
W03 now verifies the actual authenticated application account and server identity before creating/removing its temporary schema.
Updated the remaining obsolete W05 catalog assertion and strengthened the title snapshot regression to submit the rendered custom input.
Final guarded PHPUnit passed **167 tests / 1095 assertions** on PHP 8.3.36 / MariaDB 10.11.19.
Browser regression passed **13 checks**, including client-picker loading/name filtering/clearing/keyboard selection and the seven-width AT-49 matrices.
All 81 non-vendor PHP files passed syntax checking; subsequently edited PHP tests, Node syntax, both Composer platform checks and whitespace checks also passed.
Exact commands, observed failures and final independent read-only SET findings are recorded in [W09 evidence](evidence/w09-acceptance.md).
The SET confirmed the reported source issues were corrected and identified no further material finding within its limited review scope; final human acceptance remains open.
AT-55 is PASS locally; AT-54 is PARTIAL pending AFM and actual long-list checks.
Linux Compose was not run on macOS, so the pulled report of 87 failures still requires a Linux rerun and is not certified resolved.
W09 remains `IN_PROGRESS`; the old G01 PASS applies only to its historical commit and is not a new sign-off for this merged tree.
AT-43/44/45/48/51 hosting checks and AT-50 final human inspection remain open without an authorized non-production hosting environment.
No hosting, production, deployment or backup changes were made.

### W09 independent review and snapshot regression - 19 November 2026

A separate SET reviewer completed the initial independent read-only AT-50 review of `88efb87` and found that historical green runs did not certify the merged title-selection UI.
The authenticated W05 regression reproduced a snapshot race: renaming the catalog title while a task form was open changed the existing task title on an unrelated fee edit.
The task form now defaults to its saved custom title; explicit catalog selection remains available.
W05/W06/W02 payloads and the browser task-title selector were updated to the current form contract.
Guarded W05 passed 9 tests / 78 assertions after the observed snapshot RED; guarded W02 passed 40 tests / 230 assertions.
All 12 browser checks passed on the modified tree after correcting the obsolete title-field selector.
The expanded date unit checks passed 5 tests / 22 assertions, including both Athens DST transitions and local-midnight boundaries.
W06 stopped before the edit assertion because pre-existing fictional rules caused 5 scheduler creations instead of 1; no unknown fixture records were removed.
See [W09 evidence](evidence/w09-acceptance.md) for commands, review scope and outstanding verification.
The user could not confirm an authorized non-production hosting environment.
W09 remains `IN_PROGRESS` and AT-50 remains `PARTIAL`; the historical G01 result is not a new PASS for this modified tree.

### AT-50 read-only QA - 19 November 2026

Reviewed the current committed snapshot `88efb87` on `codex/crm-remaining-tasks`; `git status --short --branch` was clean and there was no uncommitted W09 diff in this checkout.
This was a self-review by the active agent, not an independent review; no separate reviewer was available, so AT-50 remains `PARTIAL`.
The review reconciled `acceptance.md`, `tasks.md`, `evidence/w09-acceptance.md`, and `evidence/g01-evaluation.md`, then inspected the AT-49 browser assertions and the changed task-form focus CSS.
The browser evidence is specific and reproducible: the recorded run covered 12 checks, seven viewport widths, the listed task/catalog/recurrence routes, keyboard traversal, focus indicators, overflow, and clipped visible text.
Limits: those checks do not establish human visual acceptance of every listed layout, real-host behavior, DST/Plesk scheduling, static-site coexistence, a real legacy database/WordPress isolation, public-site sitemap/menu/cache behavior, or backup/restore.
No critical/high finding was established within this narrow review scope; this is not a complete security audit or evidence that no such findings exist elsewhere.
No tests or other guarded checks were run because this was read-only review and the worktree was clean.
W09 remains `IN_PROGRESS`; AT-43, AT-44, AT-45, AT-48, AT-50 and AT-51 remain partial or not run as recorded in [W09 acceptance evidence](evidence/w09-acceptance.md).

### `users` σε utf8mb4 και upgrade migration - 19 Νοεμβρίου 2026

Το αρχικό `accountant_crm.sql` πλέον δημιουργεί τον πίνακα `users` σε `utf8mb4_general_ci`, όπως και τα υπόλοιπα application tables. Η migration `20261119_001_users_utf8mb4` μετατρέπει legacy `users` columns σε `utf8mb4` και αφήνει το migration runner να καταγράψει/ελέγξει την εφαρμογή της. Το W03 regression καλύπτει legacy ID, username, fullname, role, password hash, ελληνικά username/fullname και δεύτερη εκτέλεση.
TDD RED: το αρχικό schema assertion απέτυχε με `users.fullname`=`latin1`. Μετά τη διόρθωση, `W03MigrationTest.php` PASS — 3 tests / 27 assertions σε απομονωμένο MariaDB 10.11 Compose database.
Η πλήρης guarded σουίτα εκτελέστηκε σε Linux Compose, αλλά απέτυχε με 87/163 tests. Τα περισσότερα αποτυχημένα checks αναφέρουν HTTP 200 αντί για αναμενόμενη redirect μετά από login ή “The fictional W01 account should authenticate”. Η συγκεκριμένη σουίτα δεν είναι πράσινη· απαιτείται ξεχωριστή διερεύνηση των W01/W02/W05/W06/W08 fixture/authentication failures. Το focused W03 αποτέλεσμα δεν επηρεάζεται.
Για να εκτελεστεί το W03 harness στο Compose, το test image περιλαμβάνει πλέον το root `accountant_crm.sql`, οι test admin μεταβλητές περνούν από το Compose και το test project χρησιμοποιεί τοπικά ports `18081/18444` ώστε να συνυπάρχει με άλλα projects. Το test MariaDB root επιτρέπεται από το δίκτυο Compose μόνο στο disposable W01 project.
Δεν έγινε καμία αλλαγή στο Otenet/Plesk. Το G01 και τα υπόλοιπα εκκρεμή acceptance criteria παραμένουν ως έχουν.

### G01 exact-commit re-evaluation - 18 November 2026

Current branch `codex/crm-remaining-tasks` has `HEAD` at `bc1406c064e1fa8829dee74da5aa86409c6f3793`, matching `origin/codex/crm-remaining-tasks`; the worktree was clean before this review, and only the three review-evidence documents are now modified.
The commit adds responsive recurrence cards and a post-PHPUnit layout fixture.
Read-only review found the mobile and desktop markup preserves the existing escaping, fixed status classes and integer rule IDs.
The fixture verifies the isolated test database and refuses the root database account, uses prepared statements, creates fictional data only, and is idempotent; it does not delete data.
The commit's focused W06, full guarded PHPUnit and 9 browser-check results are recorded below and in [G01 evaluation evidence](evidence/g01-evaluation.md).

The G01 checklist in `tasks.md` was reconciled against the later W02 closeout, route review and W08 closeout.
Earlier checkpoint notes that said the role/CSRF route matrices, broad SQL/output review, CLI account flow, Dompdf and spreadsheet opening were still outstanding are superseded by those later evidence records.
The user confirmed human acceptance of the recurring-task empty state and compact navigation/header for this exact commit.
The [G01 evaluation](evidence/g01-evaluation.md) now records `PASS` for the local corrected baseline; no production or hosting checks were performed.

### W09 acceptance execution - 19 November 2026

Started W09 against HEAD `a2e805e2581542652ff7a007f9f980ad1f3c00c3` plus local changes; those implementation changes are now present in the committed branch history.
The complete AT disposition and command results are in [W09 acceptance evidence](evidence/w09-acceptance.md).
Added the specified `X-Robots-Tag: noindex, nofollow` response header after observing the AT-48 HTTP regression fail on `/login.php`.
Added explicit AT-26 mobile payment snapshot assertions and an AT-32 authenticated generated-task independence regression.
AT-49 now passes locally across task list/form, both catalog lists/forms, recurring list and recurrence settings at 320, 375, 390, 768, 1024, 1280 and 1440 pixels.
Browser checks cover overflow, responsive breakpoints, every visible focusable control, visible focus indicators and clipped visible text.
The keyboard audit found and fixed missing focus indicators on task form fields when Bootstrap is loaded after the global stylesheet.
Final guarded full suite passed with 165 tests / 1,056 assertions; the extended browser suite passed 12 checks; all 80 PHP files passed syntax checks; Node syntax, Composer platform, Composer audit and `git diff --check` passed.
The new fictional admin completed the local AT-52 walkthrough through `/crm/`, opening both catalogs and creating a recurrence rule from a fictional task.
A guarded December 2026 scheduler dry-run reported one due occurrence and wrote no occurrence row; the exact temporary rule/version were removed from the isolated DB.
W09 remains `IN_PROGRESS`: AT-43, AT-44, AT-45, AT-48, AT-50 and AT-51 have partial or unrun work as itemized in the evidence.

### Recurring-task mobile cards - 18 November 2026

At the user's request, the recurring-task list now renders cards only at widths up to 768 pixels and retains its desktop table.
Cards include the existing rule details and settings/restart action.
The regression first failed without mobile markup, then the focused W06 suite passed with 20 tests / 149 assertions and the full isolated PHPUnit suite passed with 163 tests / 1,035 assertions.
All 9 browser checks passed, including 390/320-pixel card layouts, 1024-pixel desktop table visibility and horizontal overflow checks.
The browser fixture is seeded only after PHPUnit to preserve the clean recurrence-table precondition.
The user visually approved the recurring-rule cards on 18 November 2026; the exact-commit read-only review is recorded above and G01 remains `NOT_EVALUATED` pending other human layout acceptance.

### Mobile catalog card redesign - 18 November 2026

The user rejected the narrow table layout on mobile and requested a card redesign for task titles and payment methods.
At widths up to 768 pixels, each option now appears as a card with its name, active status and a full-width edit action; empty catalogs display a short empty-state message.
The desktop table remains in place above the mobile breakpoint.
The browser regression initially failed because the card list was absent, then passed all 8 checks at 390/320 pixels and 1024 pixels.
The user visually approved both redesigned catalog pages.
PHP lint, Node syntax validation and CRLF-aware `git diff --check` passed.
The full guarded PHPUnit suite passed with 162 tests / 1,024 assertions on clean isolated database `crm_test_g01_mobile_catalog_20261118`, including the W05 catalog tests.
The browser suite passed all 8 checks against the clean test server, including both mobile catalog pages at 390 and 320 pixels and desktop behavior at 1024 pixels.
Independent review of exact commit `7f640e0596c1e53d18e7fc7e84e287e8d9067f7e` supports the catalog change and found no security or functional regression.
That review was scoped to the catalog implementation; the full G01 checklist review and any remaining required human layout acceptance are still open.
G01 remains `NOT_EVALUATED`.

The independent G01 review checked exact HEAD `d7476c500ead6e5802228a229b789eea2e381ca8`, found no open critical/high issue and found the local route/security and existing-flow evidence sufficient.
It identified AT-50 human inspection of the recurring-task empty state and compact navigation/header as the remaining gate item.
The user has approved the catalog layouts only; G01 remains `NOT_EVALUATED` until the other key layouts are accepted and the exact-commit PASS is recorded.

### Compact navigation drawer alignment - 18 November 2026

The user reported that the compact navigation list container was wider than the header.
At 390 pixels, the browser regression reproduced a 20-pixel mismatch: the header spanned x=10..380 and the drawer x=0..390.
The drawer now uses the header as its positioning container, so its width and horizontal edges match the header.
The E2E suite passed all 8 checks, including drawer/header alignment at 390 and 1280 pixels, no mobile horizontal overflow, and desktop navigation geometry at 1600 pixels.
The earlier guarded PHPUnit run passed with 162 tests / 1,024 assertions.
The final repeat encountered a MariaDB connection refusal during W08 export tests: 162 tests ran with 4 connection errors and 2 downstream failures after the database stopped accepting connections.
The E2E result and syntax checks passed; the interrupted database run needs a healthy isolated MariaDB repeat for a fresh full-suite result.
PHP lint, Node syntax validation and CRLF-aware `git diff --check` passed.
The independent code review is recorded below; G01 remains `NOT_EVALUATED` pending a fresh green full-suite run and the required human layout inspection.

Independent reviewer checked exact code commit `63fc9a7a603c90c54f7c00ac989327b715e7b50d`, confirmed the drawer bounds and E2E coverage, and found no security or functional regression.
The latest full PHPUnit repeat lost its MariaDB connection in W08 exports, so the earlier 162-test pass is not a fresh green result for this follow-up.
A fresh full-suite run and human visual acceptance remain open; G01 is still `NOT_EVALUATED`.

### Authenticated desktop header fix - 18 November 2026

The user authorized fixing the separately observed clipping of the signed-in username in the desktop navigation.
The new browser regression reproduced the issue before the CSS update: at a 1280-pixel viewport, the desktop navigation overflowed instead of switching to the compact menu.
The header now accounts for its horizontal padding, uses a compact hamburger navigation through 1400 pixels, and allows the desktop account block to wrap without clipping.
The browser regression verifies no horizontal overflow, account/logout access through the compact menu, and the full desktop account block within a 1600-pixel viewport.
The E2E suite passed all 8 checks, and the full guarded PHP suite passed with 162 tests / 1,024 assertions.
Manual browser inspection at 1280 pixels showed the hamburger menu and full account name in its drawer; the 1600-pixel geometry is checked by Playwright.
Independent review of exact commit `251a35c1251c4307f81afe6b6a9300fd9db5c1e7` found no new critical/high security issue and confirmed the responsive header and recurrence test setup.
G01 remains `NOT_EVALUATED` pending human inspection of the key layouts.

### G01 re-review follow-up - 18 November 2026

The independent review of `51f2100ad85a4166c145a2cb5b989d2ebc87c955` confirmed the empty-state fix and found no new critical/high security issue.
It flagged that recurrence tests require a clean schema; that precondition is now explicit in the test and `tests/README.md`.
After this clarification, focused `W06RecurringTasksTest` passes with 19 tests / 138 assertions and the full guarded suite passes with 162 tests / 1,024 assertions.
The reviewer also noted the authenticated username was clipped in the desktop header; this was subsequently fixed with user authorization as recorded above.
The final review of that fix is recorded in [W02 route review](evidence/w02-route-review.md).
G01 remains `NOT_EVALUATED` pending human inspection of the key layouts.

### Recurring-task empty-state fix - 18 November 2026

The user authorized fixing the documented blank panel after previously choosing to leave it unchanged.
Reproduced the empty recurring list in the authenticated browser at `/crm/recurring_tasks.php` with zero rules in the guarded isolated DB.
Added `W06RecurringTasksTest::testEmptyRecurringTaskListExplainsHowToCreateTheFirstRule`; its pre-fix HTTP run failed because the empty page had no guidance or create action.
The page now shows an accessible empty-state message explaining that a new task can enable monthly recurrence, plus a `Δημιουργία εργασίας` action leading to the existing task form.
The non-empty list continues to render rule rows, covered by the existing recurrence HTTP test.
Focused `W06RecurringTasksTest`: PASS, 19 tests / 138 assertions; full guarded PHPUnit suite: PASS, 162 tests / 1,024 assertions.
Browser verification confirmed the empty-state layout and that its action opens the new-task form with the recurrence control.
The desktop screenshot also showed the authenticated username clipped at the right edge of the navigation; this was fixed with user authorization in commit `251a35c`.
G01 remains `NOT_EVALUATED` pending human inspection of the key layouts; no production or hosting access occurred.

### G01 follow-up review - 18 November 2026

Continued from commit `393ac41` on `codex/crm-remaining-tasks`.
Extended `W05CatalogsTest::testUserCannotViewOrPostToCatalogManagement` to cover unauthorized GET access to both catalog form routes and to assert rejected catalog writes create no rows.
Added `W06RecurringTasksTest::testAdminCanEditSharedTaskAndCreateMonthlyRule` to verify ROL-02 for the admin role over real authenticated HTTP.
The first test attempt was blocked by the absent loopback server; a second attempt exposed the missing test-only HMAC key in the temporary server setup.
After starting the loopback server on `127.0.0.1:18096` with the verified isolated MariaDB target and a fictional HMAC key, the focused suite passed: `W05CatalogsTest` — 7 tests / 62 assertions.
The route evidence now maps anonymous, role, CSRF, diary ownership, catalog, recurrence, import/export and deletion regressions to their integration suites.
Extended source review across application PHP query, output and file access sites found no new concrete security defect; its limits and exact coverage are recorded in [W02 route review](evidence/w02-route-review.md).
The full suite passed on `crm_test_w08_closeout_20261118` after setting the required test-only environment: 161 tests / 1,014 assertions.
Representative mobile browser inspection at `/crm/` covered login, dashboard/navigation, task list/form, client list and diary; the hostile client fixture rendered as text.
The recurring-task empty screen has an unexplained blank rounded panel; recorded as a visible UI issue for review, with no change made.
Independent read-only review of exact commit `df84d7bd56b1b039be62f73a7861985e9bbc780f` found no new critical or high security issue and confirmed the route count, role regression and test evidence.
The reviewer did not approve G01 because the recurring-task empty screen still has an unexplained blank panel.
The user chose to leave the UI unchanged and keep the issue documented.
G01 remains `NOT_EVALUATED`; the observed issue prevents a gate pass under the independent review criteria.
No deployment or production access occurred.

### G01 baseline evaluation - 18 November 2026

G01 was evaluated at `c9652a7` with an independent read-only review and the full isolated native MariaDB suite.
The suite passed with 159 tests / 998 assertions after the test database guard verified `crm_test_w08_closeout_20261118`.
The first W02 source route review is recorded in [w02-route-review.md](evidence/w02-route-review.md).
The anonymous logout issue found there is fixed locally with a RED/GREEN HTTP regression; the focused W02 CSRF class passes with 40 tests / 230 assertions and the full suite with 160 tests / 1,001 assertions.
Full route-level role/CSRF regression coverage and final output/file reviews remain incomplete.
The independent review confirmed that G01 cannot pass while these checks and human review remain open.
See [G01 evaluation evidence](evidence/g01-evaluation.md).
G01 remains `NOT_EVALUATED`; no production or hosting checks were performed.
Next: close the documented W02 baseline review gaps, then repeat independent G01 review.

### W08 local closeout - 18 November 2026

W08 is `DONE_LOCAL` on `codex/crm-remaining-tasks`, based on `948b24d` plus the current uncommitted fixes.
All eight export routes, Greek PDF, valid and invalid imports, nested navigation, session startup and rendered deletion flow pass their local checks.
Full fresh native suite: PASS, 159 tests / 998 assertions.
Browser checks: PASS, 7 checks on loopback HTTP under `/crm/`; this is not production HTTPS verification.
PHP lint: PASS, 79 application/test files; whitespace check: PASS with the existing CRLF convention.
Composer audit: no advisories; PHP 8.3.36 platform requirements pass.
See [W08 closeout evidence](evidence/w08-closeout.md) for commands, RED/GREEN and acceptance mapping.
The earlier manual test database was preserved after its active recurrence caused global scheduler count interference; the final suite used a fresh disposable schema and fictional fixtures.
Next: separately evaluate G01 with independent review; G01 remains `NOT_EVALUATED`.
No new commit, push or deployment was performed.


### Collaborator creation regression - 18 November 2026

Fixed new collaborator creation in `partner_form.php`.
Actual authenticated `/crm/` form submission reproduced a PHP 8.3 null-escaping warning inside the hidden ID, which selected the update branch and inserted no row.
The hidden ID now renders as an empty string for a new collaborator.
`W08CollaboratorTest::testNewCollaboratorFromRenderedFormIsSavedAndListed` submits the rendered inputs and verifies persistence and the list row.
Guarded command: `php tests/bin/assert-test-db.php && tests/vendor/bin/phpunit -c tests/phpunit.xml tests/integration/W08CollaboratorTest.php`.
RED: 1 test / 5 assertions, expected one row, actual zero.
GREEN: 1 test / 7 assertions.
PHP syntax checks passed for both changed PHP files.
Only uniquely named fictional test records were created and cleaned up in `crm_test_native_20261118`.
W08 remains `IN_PROGRESS`, with the earlier export/import regressions unresolved.
G01 remains `NOT_EVALUATED`; all changes are uncommitted.


### W08 local work in progress - 18 November 2026

Branch `codex/crm-remaining-tasks`, starting commit `a7c8dbb`, initially clean.
W08 is `IN_PROGRESS`; G01 stays `NOT_EVALUATED`.
Native PHP 8.3.36 and the dedicated `/crm/` server were verified against `crm_test_native_20261118`.
The stopped temporary MariaDB process was restarted using its existing isolated data directory without reset.
Authenticated HTTP reproduction confirmed missing Dompdf and the nullable collaborator deprecation in the task print report.
`W08PdfTest` RED: 1 test / 3 assertions, expected PDF bytes but received the missing Dompdf fatal error.
Composer audit found 12 advisories affecting the locked PhpSpreadsheet 5.3.0; a compatible patched update and direct Dompdf dependency are in progress.
Remaining local work: export and nested-path regressions, dependency/resource restrictions, import/delete rechecks, spreadsheet/PDF inspection and full regression.
No commit, push, deployment or G01 approval has occurred.


### W04 local completion - 18 November 2026

W04 is `DONE_LOCAL` on `codex/crm-remaining-tasks`, based on `1441217` plus the current uncommitted changes.
AT-10, AT-11 and AT-12 HTTP checks, real MariaDB concurrent counters and retention checks pass.
Full native regression command: `php tests/bin/assert-test-db.php && tests/vendor/bin/phpunit -c tests/phpunit.xml`, using the isolated test environment and `CRM_BASE_URL=http://127.0.0.1:18094/crm`.
Result: PASS, 148 tests / 859 assertions.
See [W04 evidence](evidence/w04-throttle.md) for scope and deployment configuration.
G01 remains `NOT_EVALUATED`; W08 is next.


### W03 local completion evidence - 18 Νοεμβρίου 2026

Το native CLI είναι PHP 8.3.36 και η τοπική MariaDB είναι 10.11.19.
Το `php -m` επιβεβαίωσε τα απαιτούμενα extensions `ctype`, `dom`, `fileinfo`, `gd`, `iconv`, `libxml`, `mbstring`, `mysqli`, `mysqlnd`, `session`, `SimpleXML`, `xml`, `xmlreader`, `xmlwriter`, `zip` και `zlib`.
Το `composer check-platform-reqs --working-dir=ACCOUNTANT_CRM --no-dev` πέρασε για PHP 8.3.36, 64-bit και όλα τα απαιτούμενα extensions του PhpSpreadsheet.
Το αρχικό-user CLI `ACCOUNTANT_CRM/create_user.php` έχει ήδη εκτελεστεί και επαληθευτεί στο απομονωμένο περιβάλλον, όπως καταγράφεται στο [W02 closeout](evidence/w02-closeout.md). Η επανάληψή του δεν απαιτείται για το W03.
Δεν επιβεβαιώθηκε production PHP/MariaDB/config ούτε πραγματική συμπεριφορά Plesk· αυτά παραμένουν deployment checks.
CLI failure probe: με επιβεβαιωμένο `crm_test_native_20261118`, προσωρινή migration που εκτοξεύει εξαίρεση μέσω του ίδιου runner επέστρεψε exit status 1 από PHP CLI. Δεν ενεργοποιήθηκε κανένα release.
Τελικός focused έλεγχος: `php tests/bin/assert-test-db.php && tests/vendor/bin/phpunit -c tests/phpunit.xml tests/integration/W03MigrationTest.php` — PASS, 3 tests / 23 assertions. `php -l` για migration runner και W03 test — PASS· `git diff --check` — PASS.
Τοπικά εφικτά acceptance checks AT-45/46 καλύπτονται για legacy upgrade, schema additions, διατήρηση δεδομένων, idempotency, failure recording και CLI exit. Δεν εκτελέστηκε πραγματική ενεργοποίηση release.
Παραμένουν μόνο hosting-dependent επιβεβαιώσεις: πραγματική Plesk release activation/stop procedure και επιθεώρηση ότι η χωριστή WordPress βάση δεν επηρεάζεται στο hosting. Δεν γίνεται τοπικός ισχυρισμός για αυτές.

### Ολοκλήρωση W02 σε τοπικό baseline - 18 Νοεμβρίου 2026

Το W02 ολοκληρώθηκε για τα τοπικά baseline acceptance checks AT-01 έως AT-09, AT-13 έως AT-18 και AT-27.
Το νέο evidence [w02-closeout.md](evidence/w02-closeout.md) καταγράφει αντιστοίχιση acceptance, πραγματικές εντολές και όρια της επιθεώρησης.
Πλήρης native MariaDB σουίτα: PASS, 142 tests / 750 assertions.
Browser E2E στο `/crm/`: PASS, 7 checks για login, session rotation/logout, autocomplete escaping και Secure/HttpOnly/SameSite/path cookie.
AT-16 επιβεβαιώθηκε στο απομονωμένο local HTTP test setup και σε `.htaccess`· πραγματικό hosting/webroot και private backup/log locations μένουν για deployment verification, όχι για το τοπικό W02 baseline.
AT-18: τα πραγματικά application task `.xls` και professional `.xlsx` exports ανοίχτηκαν από LibreOffice και μετατράπηκαν επιτυχώς.
AT-17 προστέθηκε truncated XLSX regression, AT-04/05 πρόσθεσαν diary privacy και πλήρες POST mutation CSRF sweep, AT-06 πρόσθεσε hostile catalog output και AT-27 ακριβή client aggregate cents.
Η CLI δημιουργία χρήστη εκτελέστηκε σε pseudo-terminal και επαληθεύτηκε αποθήκευση password hash χωρίς εκτύπωση password/hash.
W02 καταγράφεται `DONE_LOCAL`; αυτό δεν αποτελεί G01 PASS, επειδή W03, W04 και W08 και η ανεξάρτητη συνολική QA παραμένουν ανοιχτά.

## Earlier checkpoints (chronological, superseded by W02 closeout above)

## Τοπική προεπισκόπηση macOS - 18 Νοεμβρίου 2026
### Συνέχεια W02 - native regressions και CLI account creation - 18 Νοεμβρίου 2026

Προστέθηκε αυτόματο AT-01 inventory που εντοπίζει όλα τα PHP αρχεία του app και επιβεβαιώνει ότι, χωρίς session, αποκρίνονται μόνο με redirect ή άρνηση (εξαιρούνται login και CLI account tool).
Η πρώτη εκτέλεση βρήκε ότι το logout ορθά απαντά 405 σε GET· το assertion δέχεται πλέον 405 ως ασφαλή method rejection.
Focused `php tests/vendor/bin/phpunit -c tests/phpunit.xml --filter W01EnvironmentTest` — PASS, 44 tests / 200 assertions.
Πλήρης native suite μετά την αλλαγή — PASS, 136 tests / 704 assertions.
Ο SQL source review σε δυναμικά queries δεν εντόπισε μεταβλητές τιμές ενσωματωμένες απευθείας σε `query()`· τα δυναμικά filters περνούν από prepared statements και τα sort clauses είναι σταθερά allowlisted.


Διορθώθηκε η native test εκτέλεση των προηγούμενων οκτώ αποτυχιών με test router για απαγορευμένα εσωτερικά paths και test-only PHP ini που περνά το MariaDB socket στις scheduler child processes.
Η native πλήρης σουίτα πέρασε αρχικά 132 tests / 592 assertions και μετά την επέκταση του AT-16 πέρασε 135 tests / 601 assertions.
Το `W01EnvironmentTest` ελέγχει πλέον επιπλέον HTTP paths για backup και log αρχεία.
Η διαδραστική εντολή `php ACCOUNTANT_CRM/create_user.php` εκτελέστηκε με pseudo-terminal απέναντι μόνο στη βάση `crm_test_native_20261118` και δημιούργησε τον πλασματικό admin `w02-cli-admin`· η εισαγωγή password έγινε με απενεργοποιημένη εμφάνιση τερματικού.
Το E2E HTTPS run του native `/crm/` ολοκλήρωσε επιτυχώς 7 checks όπως καταγράφεται παραπάνω.
W02 παραμένει `IN_PROGRESS`: το AT-06/07 output/query inventory δεν έχει πλήρη route-by-route ανεξάρτητη επιθεώρηση, το AT-16 δεν έχει επιβεβαιωθεί στο πραγματικό hosting και τα backup/log paths εκεί, το AT-17 χρειάζεται ευρύτερο corrupt-file corpus, το AT-18 δεν έχει χειροκίνητο άνοιγμα των CRM exports σε spreadsheet εφαρμογή, και το AT-27 χρειάζεται συνολική επαλήθευση ποσών/συνόλων στις αναφορές.
Το PDF export και άλλες επιβεβαιωμένες λειτουργικές εκκρεμότητες παραμένουν στο W08 λόγω Dompdf/dependency και δεν χαρακτηρίζονται ως ολοκληρωμένες μέσω unit checks.
Επόμενο W02 βήμα: ολοκλήρωση του source/output και SQL route inventory, προσθήκη regressions όπου εντοπιστούν μη καλυμμένοι sinks, και εκτέλεση της διαθέσιμης spreadsheet επιθεώρησης· hosting-only checks θα παραμείνουν ρητά `NOT_RUN` μέχρι το πραγματικό περιβάλλον να είναι διαθέσιμο.

### Συνέχεια native test harness - 18 Νοεμβρίου 2026

Εγκαταστάθηκαν οι PHPUnit dependencies με `composer install --working-dir=tests --prefer-dist --no-interaction` και οι Playwright dependencies με `npm install --prefix tests/e2e --ignore-scripts --cache /tmp/crm-w01-native-npm-cache`.
Επιβεβαιώθηκε ξανά το προσωρινό MariaDB instance `@@datadir=/tmp/accountant-crm-preview-20261118/data/` και δημιουργήθηκε η ξεχωριστή βάση `crm_test_native_20261118` με χρήστη περιορισμένο μόνο σε αυτή.
Έγινε import schema χωρίς `DROP TABLE`, έλεγχος preflight, migrations και seed μόνο πλασματικών fixtures.
Το native PHP server εξυπηρέτησε την εφαρμογή κάτω από `/crm/`, με test router για προστασία εσωτερικών αρχείων και test-only PHP ini που περνά το socket στις CLI child processes.
Focused suite `php tests/vendor/bin/phpunit -c tests/phpunit.xml --filter 'W01EnvironmentTest|W06RecurringTasksTest'` — PASS, 57 tests / 207 assertions.
Πλήρης σουίτα `php tests/vendor/bin/phpunit -c tests/phpunit.xml` — PASS, 132 tests / 592 assertions.
Browser E2E `CHROME_BIN='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' CRM_BASE_URL=https://127.0.0.1:18443/crm npm --prefix tests/e2e --cache /tmp/crm-w01-native-npm-cache test` — PASS, 7 checks: `/crm/` login, session rotation/logout, autocomplete escaping και cookie Secure/HttpOnly/SameSite/path.
Το HTTPS E2E έγινε μέσω προσωρινού loopback TLS proxy και self-signed certificate· δεν αποτελεί έλεγχο production TLS.
Οι αποτυχίες της πρώτης native εκτέλεσης οφείλονταν στην έλλειψη test router και ini ρύθμισης στις child processes· μετά την προετοιμασία τους όλα τα 132 PHPUnit tests και τα 7 browser checks πέρασαν.
Τα αποτελέσματα αυτά επαληθεύουν το τρέχον native harness, δεν αλλάζουν το G01 (`NOT_EVALUATED`) ούτε κλείνουν τα εκκρεμή W02/W03/W04/W08 acceptance criteria.

Ξεκίνησε native PHP 8.3.36 και προσωρινό, απομονωμένο instance MariaDB 10.11.19 με data directory `/tmp/accountant-crm-preview-20261118/data` και socket στο ίδιο directory.
Η σύνδεση επαλήθευσε το data directory και τη χωριστή βάση `crm_preview_20261118` πριν από την εισαγωγή μόνο του schema, χωρίς τις εντολές `DROP TABLE`.
Εφαρμόστηκαν τα migrations και δημιουργήθηκε πλασματικός χρήστης προεπισκόπησης.
Η εντολή `composer install --working-dir=ACCOUNTANT_CRM --no-dev --prefer-dist --no-interaction` ολοκληρώθηκε επιτυχώς με πρόσβαση δικτύου.
Η εντολή `sh /tmp/accountant-crm-preview-20261118/start.sh` ξεκίνησε την εφαρμογή στο `http://127.0.0.1:18080/crm/`.
Το `curl -sS -o /tmp/accountant-crm-preview-20261118/login.html -w 'HTTP %{http_code}\n' http://127.0.0.1:18080/crm/login.php` επέστρεψε HTTP 200.
Το `python3 /tmp/accountant-crm-preview-20261118/smoke.py` επιβεβαίωσε σύνδεση και ανακατεύθυνση στο `/crm/index.php` με HTTP 200.
Η πρώτη προσπάθεια δημιουργίας χρήστη με ελληνικό fullname απέτυχε λόγω της κωδικοποίησης της στήλης `users.fullname` και η προεπισκόπηση χρησιμοποιεί λατινικό fullname.
Δεν εκτελέστηκε πλήρης σουίτα ή browser QA και τα G01 και τα ανοιχτά κριτήρια παραμένουν ως έχουν.
Επόμενο βήμα είναι ο έλεγχος των ροών από τον χρήστη και η διερεύνηση του προβλήματος κωδικοποίησης πριν θεωρηθεί επιλυμένο.

## Ενημέρωση περιβάλλοντος ανά λειτουργικό σύστημα - 18 Νοεμβρίου 2026

Η ανάθεση ενημερώνει τις οδηγίες για native PHP και MariaDB σε macOS και Docker Compose σε Linux, χωρίς αλλαγή των λειτουργικών απαιτήσεων ή του G01.
Η εντολή `uname -s && php -v && mariadb --version && composer --version` εκτελέστηκε στο παρόν μηχάνημα και επέστρεψε Darwin, PHP 8.3.36, MariaDB 10.11.19 και Composer 2.11.2.
Η εντολή `git rev-parse --short HEAD` επέστρεψε `292a2f8` και το branch είναι `codex/crm-w05-w07-features`.
Οι προηγούμενες αποδείξεις W01 αφορούν το τότε Docker περιβάλλον και διατηρούνται ως ιστορικές.
Δεν επαληθεύτηκαν σε αυτή την αλλαγή extensions, σύνδεση βάσης, native test runner ή πραγματική ροή `/crm/` και δεν εκτελέστηκαν tests εφαρμογής.
Η εγκατάσταση των runtimes δεν αποτελεί νέο PASS της εφαρμογής ούτε από μόνη της ολοκλήρωση του W01.
Η υπάρχουσα ιστορική αποδοχή W01 δεν αποδεικνύει ότι η native υποδομή macOS είναι έτοιμη.
Επόμενο βήμα για δοκιμές στο macOS είναι η προσαρμογή και επαλήθευση απομονωμένου native test περιβάλλοντος, με έλεγχο του πραγματικού στόχου πριν από κάθε import ή cleanup.
Το G01 παραμένει `NOT_EVALUATED` και οι ήδη καταγεγραμμένες εκκρεμότητες και η ιστορική εξαίρεση σειράς παραμένουν ως έχουν.

Τελευταία ενημέρωση: 18 Νοεμβρίου 2026.
Κατάσταση: W01 ολοκληρώθηκε· W02 ολοκληρώθηκε για τοπικό baseline· W03/W04/W08 και G01 παραμένουν ανοιχτά· W05/W06/W07 έχουν υλοποιηθεί τοπικά με ορισμένα acceptance checks ακόμη ανοιχτά.
Έκδοση specs: 1.6.

## Εξαίρεση σειράς εργασιών

Στις 18 Νοεμβρίου 2026 ο χρήστης ζήτησε να ολοκληρωθούν πρώτα τα W05/W06/W07 σε branch ξεχωριστό από το W02 και μετά να επιστρέψει η εργασία στα υπόλοιπα πακέτα. Η εξαίρεση σειράς υλοποιήθηκε: το `codex/crm-w05-w07-features` βασίστηκε στο W02 checkpoint `466eb18`, και τώρα το W02 ολοκληρώθηκε τοπικά. Το G01 δεν έχει PASS· W03/W04/W08, W05/W06/W07 sign-off, ανεξάρτητη QA και production checks παραμένουν ανοιχτά.

Το αρχείο καταγράφει την πραγματική πρόοδο και τα αποδεικτικά. Δεν πιστοποιεί πρόσβαση ή εγκατάσταση στην παραγωγή.

## Σύνοψη τρέχουσας προόδου

- Τρέχον branch: `codex/crm-w05-w07-features`, based on W02 commit `466eb18`; local feature implementation is complete. W02 local baseline is now `DONE_LOCAL`; G01 and remaining package sign-offs are still open.
- Formally accepted packages: W01 and W02 local baseline. W05/W06/W07 are implemented locally but not signed off; G01 remains `NOT_EVALUATED`.
- W01: απομονωμένο Docker περιβάλλον, test DB guards/fixtures, PHPUnit και browser runners, frozen clock και baseline evidence.
- W02: DONE_LOCAL για AT-01..09, AT-13..18 και AT-27· πλήρες scope και evidence στο [W02 closeout](evidence/w02-closeout.md).
- W02 baseline: `DONE_LOCAL`; detailed AT mapping and actual command output are in [W02 closeout](evidence/w02-closeout.md). Hosting verification remains a deployment check and W08 PDF dependency/rendering remains separate.
- SEC-07/08 / AT-08/09: password length/algorithm limits are enforced by shared policy; the CLI-created test account was verified to contain a supported hash without exposing credential material.
- SEC-11/12 / AT-13/14/15: idle timeout and cache controls have integration tests; native browser E2E verifies rotation, cookie attributes, logout and rejection of the old session.
- W02 output/query/money scope: prepared-query review, hostile catalog/output tests, spreadsheet-open checks and exact aggregate cents are summarized in the W02 closeout evidence.
- W05 catalogs/payments: see [W05 evidence](evidence/w05-catalogs-payments.md) for admin-only catalogs, task title suggestions, server-generated payment snapshots and views/exports.
- W06/W07 recurrence: see [recurrence evidence](evidence/w06-w07-recurring.md) for temporal rule versions, stop/restart, transactional CLI scheduling, batch catch-up and MariaDB concurrency coverage.
- REC-01 / AT-29: New task form offers monthly recurrence settings; task and rule persist in one transaction. Invalid recurrence settings do not leave an unlinked task. See [W06/W07 evidence](evidence/w06-w07-recurring.md).
- Latest full PHPUnit run: PASS, 142 tests / 750 assertions in the native macOS test environment; prior clean Compose run: 132 tests / 592 assertions targeting isolated `test-db / crm_w01_test`. In the long-lived preview DB, 130 tests / 565 assertions pass when excluding two global scheduler count checks affected by an existing active fictional rule; the two checks pass in the clean project. Browser/E2E: PASS, 7 checks in the native `/crm/` environment; final keyboard/mobile product review remains open.
- W02 residual external check: verify private paths against actual hosting/webroot and backup/log configuration during deployment. This does not change the local W02 result. PDF rendering and Dompdf remain W08 items.
- Next sequencing: proceed with W03, then W04/W08, then evaluate G01. W05/W06/W07 do not receive full sign-off until their remaining acceptance checks are completed. G01 is not a PASS and no production sign-off or deployment is recorded.

## Καταγραφή τρέχουσας εργασίας

| Καταγραφή | Αποτέλεσμα |
|---|---|
| Αρχικό σημείο αναφοράς | `27a805fbafcab86d0673c1f7f4911a45c1fcc8f1`; branch `codex/crm-w01-bootstrap`. |
| Τρέχον Git σημείο | W02 branch `codex/crm-w02-security-audit` includes progress-sequence commit `466eb18`; feature branch `codex/crm-w05-w07-features` was created from that commit. W05/W06/W07 local implementation and evidence are checkpointed on the feature branch; W02 is now `DONE_LOCAL` and G01 remains open. |
| Specs και οδηγίες | Το `docs/crm-specs/` εισήχθη ως canonical αντίγραφο. Ενημερώθηκε το `AGENTS.md` για το canonical spec path και την επιβεβαιωμένη συμπεριφορά recurring tasks χωρίς scheduled end date. |
| W01 baseline | [w01-baseline.md](evidence/w01-baseline.md): 34 tests, 5 PASS / 29 RED πριν από τις W02 διορθώσεις. Καταγράφηκαν δημόσια routes/data exposure, redirects εκτός `/crm/` και cookie policy gaps. |
| W02 RED | [w02-csrf.md](evidence/w02-csrf.md): πριν από την υλοποίηση, 38 tests / 3 failures για POST χωρίς CSRF, token field που έλειπε και GET διαγραφή πελάτη που εκτελούνταν. |
| W02 ROL-03 RED/GREEN | [w02-diary-ownership.md](evidence/w02-diary-ownership.md): 47 tests / 2 RED failures for foreign update/delete returning success; suite was GREEN at that checkpoint at 47 tests / 126 assertions. |
| W02 SEC-05 RED/GREEN | [w02-xss.md](evidence/w02-xss.md): raw header fullname, diary inline JavaScript and active-professional attribute values reproduced as RED at that checkpoint; the latest suite is GREEN at 59 tests / 195 assertions and autocomplete E2E PASS. The pre-fix autocomplete browser attempt was interrupted, so its RED was not observed. |
| W02 SEC-05 import follow-up | [w02-xss.md](evidence/w02-xss.md): duplicate-code import feedback reproduced as raw text containing an attribute payload; output is now escaped and parser exceptions return a generic message. Focused regression PASS, 1 test / 5 assertions. |
| W02 SEC-05 AJAX follow-up | [w02-xss.md](evidence/w02-xss.md): a submitted `</script>` payload was reproduced in the `save_client_ajax.php` response before the fix; JSON_HEX encoding now protects the handoff. Full PHPUnit suite PASS, 52 tests / 155 assertions. |
| W02 SEC-05 document title and export | [w02-xss.md](evidence/w02-xss.md): partner fullname broke out of the shared `<title>` and appeared as raw HTML in the partner Excel export. Both sinks now escape the name. Full PHPUnit suite PASS, 54 tests / 165 assertions. |
| W02 SEC-06 query slice | [w02-sql.md](evidence/w02-sql.md): client, partner, task, active-professional and autocomplete searches use prepared statements; MyDiary note/event writes and reads also bind values. SQL-like input and apostrophe round-trip regressions pass. Full PHPUnit suite PASS, 59 tests / 195 assertions. |
| W02 security baseline follow-up | [w02-exports.md](evidence/w02-exports.md): export SQL/output, cache policy, spreadsheet formula handling, imports, password whitespace/policy, exact idle expiry, AT-16 HTTP denials and task money parsing. Latest full suite PASS, 97 tests / 354 assertions. Dompdf output remains blocked by the known missing dependency. |
| W02 PHPUnit | `docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 build app && docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 up -d app && docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm tests` — latest full PASS: 59 tests / 195 assertions. Runner verified `test-db / crm_w01_test` and used fictional fixtures. |
| W02 PHPUnit latest | `docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 build app`; `docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm tests` — PASS, 72 tests / 231 assertions. Runner verified `test-db / crm_w01_test`; fixtures are fictional. |
| W02 PHPUnit current | `docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 build app`; `docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm tests` — PASS, 97 tests / 354 assertions. Runner verified `test-db / crm_w01_test`; fixtures are fictional. |
| W05/W06/W07 PHPUnit | Fresh disposable Compose project full suite — PASS, 132 tests / 592 assertions. The long-lived preview data was preserved; its two scheduler count checks were verified in the clean project. Migrations and fictional fixtures were applied only to isolated `test-db / crm_w01_test`. |
| W02 browser | `CRM_BASE_URL=https://127.0.0.1:18443/crm npm --prefix tests/e2e --cache /tmp/crm-w01-npm-cache test` — PASS: login/session ID rotation, POST logout, blocked re-entry with old session, hostile autocomplete name rendered as text, Secure, HttpOnly, SameSite=Lax και cookie path `/crm/`. Το Chrome εκτελέστηκε εκτός sandbox εξαιτίας socket restriction. |
| W02 syntax | `php -l` passed for every touched app PHP file; PHPUnit parsed the changed test files. `git diff --check` passed with `core.whitespace=cr-at-eol` because touched legacy PHP files use CRLF. |
| Περιβάλλον | Host PHP/Composer δεν είναι διαθέσιμα· οι PHP έλεγχοι εκτελούνται στο απομονωμένο Compose project. Chrome browser check απαιτεί εκτέλεση εκτός sandbox. |
| Παραγωγή | Δεν έγινε σύνδεση, εγκατάσταση, αλλαγή δεδομένων ή deployment στο Otenet. |

## Πακέτα εργασίας

| Πακέτο | Κατάσταση | Αποδεικτικό / εκκρεμότητα |
|---|---|---|
| W01 | DONE | [W01 baseline](evidence/w01-baseline.md), test harness, fixtures, clock και runners. |
| W02 | DONE_LOCAL | AT-01..09, AT-13..18 and AT-27 pass locally; see [W02 closeout](evidence/w02-closeout.md). [CSRF](evidence/w02-csrf.md), [diary ownership](evidence/w02-diary-ownership.md), [XSS](evidence/w02-xss.md), [export/security follow-up](evidence/w02-exports.md). G01 remains NOT_EVALUATED. |
| W03 | IN_PROGRESS | AT-45/46 local checks pass for isolated legacy upgrade, schema/data preservation, idempotency and CLI failure handling. Plesk activation and WordPress database isolation remain hosting verification. |
| W04 | DONE_LOCAL | AT-10/11/12 pass locally; see [W04 evidence](evidence/w04-throttle.md). Private key and cleanup scheduling require deployment configuration. |
| W08 | DONE_LOCAL | AT-05/18/47 local checks pass; see [W08 evidence](evidence/w08-closeout.md). |
| G01 | HISTORICAL_PASS_LOCAL | Exact commit `bc1406c064e1fa8829dee74da5aa86409c6f3793`; see [G01 evaluation](evidence/g01-evaluation.md). This is not a current merged-tree or production sign-off. |
| W05 | IMPLEMENTED_LOCAL | Local implementation and focused tests cover AT-19 to AT-28; see [W05 evidence](evidence/w05-catalogs-payments.md). Keyboard/mobile visual review and final sign-off remain open. |
| W06 | IMPLEMENTED_LOCAL | Rule activation, effective versions, short-month dates, stop/restart, catch-up and stale-version rejection have MariaDB tests; see [recurrence evidence](evidence/w06-w07-recurring.md). |
| W07 | IMPLEMENTED_LOCAL | CLI scheduler, transactions, batch catch-up, dry-run, idempotency and concurrent MariaDB processes are covered locally. Actual Plesk Run Now and production-like timezone configuration remain open. |
| W09 | IN_PROGRESS | Merged-tree native PHPUnit 167/1095 and 13 browser checks pass; AT-54 is partial and Linux Compose rerun remains open. Hosting and final human acceptance gaps remain. See [W09 evidence](evidence/w09-acceptance.md). |
| W10 | NOT_STARTED | Προϋποθέσεις W09 PASS, preflight και ανάθεση deployment. |

## Ακριβές επόμενο βήμα

Complete AT-54 AFM/actual long-list checks, obtain a Linux Compose rerun of the corrected harness, and obtain final human inspection of the merged tree.
The fresh guarded W06/full-suite reruns and final independent source QA are complete; no new G01/W09 PASS is granted.
Keep AT-43/44/45 hosting-related checks, the public-site and cache-policy portions of AT-48, and AT-51 backup/restore for W10 in an authorized non-production deployment environment.

## Τελευταίο βήμα TDD

- SEC-01 / AT-01: τα αρχικά unauthenticated route/data regressions ήταν RED· οι προστατευμένες διαδρομές τώρα ανακατευθύνουν στο `/crm/login.php` και δεν εμφανίζουν fixtures.
- SEC-11 / AT-13: τα αρχικά Secure/HttpOnly/path checks ήταν RED· τώρα περνούν μαζί με SameSite=Lax και επιτυχής session ID rotation.
- SEC-04 / AT-05: RED για απουσία token και GET deletion· GREEN για missing/invalid/foreign token rejection, έγκυρο POST update/delete και μη εκτέλεση GET delete σε αντιπροσωπευτικές διαδρομές. Κατάσταση PARTIAL.
- ROL-03 / AT-04: read filtering already passed; RED for foreign diary event update/delete returning success responses, then GREEN with owner check and 404 rejection. Κατάσταση PARTIAL λόγω μη ολοκληρωμένου συνολικού route matrix.
- SEC-05 / AT-06: RED for raw header fullname, malicious diary values in inline JS and active-professional attribute values; GREEN with context-specific escaping, `data-*` handoff and text-only autocomplete rendering. Autocomplete pre-fix RED was not captured after its browser run was interrupted. Κατάσταση PARTIAL.
- SEC-12 / AT-15: authenticated cache headers are covered for representative pages and exports; prior browser checks cover POST logout and old-session rejection.
- SEC-07/08 / AT-08/09: policy tests cover 14/15 Unicode character boundary and bcrypt byte limit; login regression proves surrounding spaces are preserved. Interactive CLI acceptance remains open.
- SEC-11 / AT-14: RED at exactly 1,800 seconds, then GREEN after changing the expiry comparison to include the boundary.
- SEC-13 / AT-16: RED for 200 responses from internal include, Composer manifest/lock and vendor metadata; GREEN after Apache access rules, with eight paths rejected (403/404) and no content markers. Hosting/webroot verification is deferred.
- SEC-14 / AT-17 and AT-18: RED/GREEN import tests reject renamed CSV/oversized/corrupt input and prevent partial insert on invalid rows; spreadsheet tests confirm formula-leading text is stored as literal text in HTML/XLSX export paths. Broad malformed-file corpus and manual spreadsheet opening are deferred.
- SEC-04 / AT-05 follow-up: unauthenticated-CSRF deletion requests across client, partner, task, professional and diary routes all return 403 without deleting fixture rows; GET deletion attempts across partner/professional/diary routes return 405.
- SEC-05 / AT-06 follow-up: stored hostile client/partner names and task title render as escaped text on client/partner/task lists and calendar; full output inventory remains deferred.
- PAY-08 / AT-27: RED when parser helper was absent; GREEN for accepted formats, rejection cases and exact task fee persistence. Broader money displays/totals are deferred. Full isolated suite: 97 tests / 354 assertions PASS.
- Μη καλυμμένα ακόμη: complete route-role/diary/CSRF matrices, full XSS/SQL and sensitive-file inventory, throttling/security events, corrupt import corpus, manual spreadsheet opening, CLI interaction and PDF output.
- SEC-06 / AT-07 export follow-up: prepared filters and strict partner IDs pass focused tests; client sorting uses an explicit allowlist. See [w02-exports.md](evidence/w02-exports.md).
- SEC-05 / AT-06 remains PARTIAL: active-professional PDF HTML and list export-link attributes were additionally reviewed and escaped; Dompdf is absent in the test image, and further output review is required.
- ROL-02 / AT-03 existing-flow check: `user` role can edit a shared task with a valid CSRF token. Catalog permissions are not testable until W05 creates those routes.

## Bugs, αποφάσεις και περιορισμοί

Τα αρχικά W01 ευρήματα καταγράφονται στο baseline evidence και αναπαράχθηκαν με πλασματικά δεδομένα. Τα entrypoint/session και τα επιλεγμένα CSRF regressions είναι πράσινα μετά τις W02 αλλαγές. Άλλα source-review findings δεν έχουν ακόμη ελεγχθεί πλήρως. Τα W02 acceptance criteria δεν έχουν ολοκληρωθεί, άρα δεν δηλώνεται συνολικό security pass.

Η συμφωνημένη συμπεριφορά recurring tasks παραμένει χειροκίνητη άμεση διακοπή, χωρίς scheduled end date. Ο χρήστης διευκρίνισε ότι η βάση του μελλοντικού deployment θα είναι κενή. Δεν έγινε σύνδεση ή αλλαγή παραγωγής· οι προϋποθέσεις Otenet και backup/restore βρίσκονται στο `deployment.md` και δεν έχουν επαληθευτεί.

Μη χρησιμοποιείς πραγματικά στοιχεία πελατών ή production database. Μην εκτελείς `down -v` και μην επαναχρησιμοποιείς το test setup με το κανονικό `.env`.
