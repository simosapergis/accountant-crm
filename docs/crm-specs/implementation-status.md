# ElMiCo CRM - Κατάσταση υλοποίησης

Τελευταία ενημέρωση: 18 Νοεμβρίου 2026.
Κατάσταση: W01 ολοκληρώθηκε· W02 παραμένει ανοιχτό και αναβλημένο· W05/W06/W07 έχουν υλοποιηθεί τοπικά, με ορισμένα acceptance checks ακόμη ανοιχτά.
Έκδοση specs: 1.6.

## Εξαίρεση σειράς εργασιών

Στις 18 Νοεμβρίου 2026 ο χρήστης ζήτησε να αναβληθεί η ολοκλήρωση των υπολοίπων W02/W03/W04/W08 και του G01, να υλοποιηθούν πρώτα τα νέα features W05/W06/W07 σε ξεχωριστό branch από το W02 και να επιστρέψει η εργασία στα υπολειπόμενα πακέτα μετά την υλοποίηση των features. Αυτό αποτελεί ρητή εξαίρεση από την κανονική πύλη `G01` στο `tasks.md`. Το `codex/crm-w05-w07-features` δημιουργήθηκε από το W02 checkpoint `466eb18`. Η τοπική υλοποίηση W05/W06/W07 έχει ολοκληρωθεί και checkpointed· ορισμένα browser/hosting acceptance checks παραμένουν ανοιχτά. Τα W02/W03/W04/W08 παραμένουν ανοιχτά, το G01 δεν έχει PASS και δεν γίνεται production sign-off ή deployment.

Το αρχείο καταγράφει την πραγματική πρόοδο και τα αποδεικτικά. Δεν πιστοποιεί πρόσβαση ή εγκατάσταση στην παραγωγή.

## Σύνοψη τρέχουσας προόδου

- Τρέχον branch: `codex/crm-w05-w07-features`, based on W02 commit `466eb18`; local feature implementation is complete. W02 security remainder is deferred by explicit user direction.
- Formally accepted packages: 1 από 10 (W01). W05/W06/W07 are implemented locally but not signed off; G01 remains `NOT_EVALUATED`.
- W01: απομονωμένο Docker περιβάλλον, test DB guards/fixtures, PHPUnit και browser runners, frozen clock και baseline evidence.
- W02 μέχρι τώρα: κοινό session bootstrap, session cookie policy, session ID rotation στο login, idle timeout, auth guards και `/crm/` redirects· CSRF validation για POST, POST-only logout/AJAX mutations και POST deletion forms.
- W02 / SEC-04 / AT-05: PARTIAL — tests cover missing/invalid/foreign CSRF tokens, valid update/delete, login/logout and representative GET deletions. Δεν έχει ολοκληρωθεί ολόκληρο το route matrix ή το συνολικό W02 security audit.
- W02 / ROL-03 / AT-04: PARTIAL — private diary read filtering and foreign event update/delete rejection have tests and implementation.
- W02 / SEC-05 / AT-06: PARTIAL — existing header, diary, autocomplete, import feedback, AJAX handoff, partner Excel, active-professional PDF HTML and export-link attributes have targeted fixes. Full output inventory and Dompdf rendering remain unverified.
- W02 / SEC-06 / AT-07: PARTIAL — client, partner, task, active-professional, autocomplete, MyDiary and remaining export query families use prepared statements for values. Export identifiers are strictly validated; sort clauses are fixed or allowlisted. Export regressions cover query filters, partner IDs, cache policy and spreadsheet text handling.
- ROL-02 / AT-03: PARTIAL — a logged-in `user` can edit shared tasks and use recurrence flows with valid CSRF; catalog routes are restricted to admin and role tests cover their read/write denial. Remaining W02 role/route matrix is deferred. W05 catalog routes now exist and have focused acceptance tests.
- SEC-07/08 / AT-08/09: password length/algorithm limits are enforced by shared policy, login preserves surrounding spaces, and tests verify no plaintext hash storage.
- SEC-11/12 / AT-14/15: exact idle timeout boundary and cache headers on representative authenticated pages/exports are covered. Logout/old-cookie browser coverage remains in earlier evidence.
- SEC-14 / AT-17/18: import size/type/preflight/transaction behavior and literal spreadsheet text handling have regressions; corrupt workbook breadth and real spreadsheet application verification remain open.
- SEC-13 / AT-16: existing probes deny direct access to app config, include/vendor internals, Composer manifests and installer paths. W05/W06 also deny HTTP access to the new CLI/migration directories. Actual hosting/webroot behavior still requires verification.
- PAY-08 / AT-27: task fee/collection input parses to integer cents and persists canonical decimal strings. W05 verifies the task payment snapshot, zero-clear behavior, legacy rows, exports and over-collection.
- W05 catalogs/payments: see [W05 evidence](evidence/w05-catalogs-payments.md) for admin-only catalogs, task title suggestions, server-generated payment snapshots and views/exports.
- W06/W07 recurrence: see [recurrence evidence](evidence/w06-w07-recurring.md) for temporal rule versions, stop/restart, transactional CLI scheduling, batch catch-up and MariaDB concurrency coverage.
- REC-01 / AT-29: New task form offers monthly recurrence settings; task and rule persist in one transaction. Invalid recurrence settings do not leave an unlinked task. See [W06/W07 evidence](evidence/w06-w07-recurring.md).
- Latest full PHPUnit run: PASS, 132 tests / 592 assertions in a fresh disposable Compose project targeting isolated `test-db / crm_w01_test`. In the long-lived preview DB, 130 tests / 565 assertions pass when excluding two global scheduler count checks affected by an existing active fictional rule; the two checks pass in the clean project. Browser/E2E has not been rerun; final keyboard/mobile review remains open.
- Deferred W02 items: complete AT-04/05 route matrices; finish broader AT-06/07 output and query coverage; exercise CLI account creation for AT-08/09; verify AT-16 against actual hosting/webroot and backup/log paths; broaden corrupt import cases for AT-17; open real spreadsheet exports in a compatible application for AT-18; audit broader money displays/totals under AT-27.
- Next sequencing: when work resumes, return to deferred W02, W03, W04 and W08, then evaluate G01. W05/W06/W07 do not receive a full PASS until their remaining acceptance checks are completed. Do not merge to `main`, deploy, or claim a security pass while required criteria remain open.

## Καταγραφή τρέχουσας εργασίας

| Καταγραφή | Αποτέλεσμα |
|---|---|
| Αρχικό σημείο αναφοράς | `27a805fbafcab86d0673c1f7f4911a45c1fcc8f1`; branch `codex/crm-w01-bootstrap`. |
| Τρέχον Git σημείο | W02 branch `codex/crm-w02-security-audit` includes progress-sequence commit `466eb18`; feature branch `codex/crm-w05-w07-features` was created from that commit. W05/W06/W07 local implementation and evidence are checkpointed on the feature branch; W02 and G01 remain open. |
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
| W02 | IN_PROGRESS | Auth/session, selected CSRF/method, diary ownership, export filters and selected XSS paths verified; [CSRF](evidence/w02-csrf.md), [diary ownership](evidence/w02-diary-ownership.md), [XSS](evidence/w02-xss.md), [export/security follow-up](evidence/w02-exports.md). Deferred under the user's sequencing exception: remainder of AT-04/05/06/07, CLI password flow, hosting path checks, broader import/formula safety and money review. G01 remains NOT_EVALUATED. |
| W03 | NOT_STARTED | Feature-specific additive migrations and a version/checksum runner are used by W05/W06; the W03 baseline install/upgrade/failure criteria remain deferred. |
| W04 | NOT_STARTED | — |
| W08 | NOT_STARTED | — |
| G01 | NOT_EVALUATED | Απαιτούνται τα συνολικά checks που ορίζει το `tasks.md`. |
| W05 | IMPLEMENTED_LOCAL | Local implementation and focused tests cover AT-19 to AT-28; see [W05 evidence](evidence/w05-catalogs-payments.md). Keyboard/mobile visual review and final sign-off remain open. |
| W06 | IMPLEMENTED_LOCAL | Rule activation, effective versions, short-month dates, stop/restart, catch-up and stale-version rejection have MariaDB tests; see [recurrence evidence](evidence/w06-w07-recurring.md). |
| W07 | IMPLEMENTED_LOCAL | CLI scheduler, transactions, batch catch-up, dry-run, idempotency and concurrent MariaDB processes are covered locally. Actual Plesk Run Now and production-like timezone configuration remain open. |
| W09 | NOT_STARTED | Προϋπόθεση ολοκλήρωση προηγούμενων πακέτων. |
| W10 | NOT_STARTED | Προϋποθέσεις W09 PASS, preflight και ανάθεση deployment. |

## Ακριβές επόμενο βήμα

When resumed, return to the deferred W02/W03/W04/W08 criteria and then evaluate G01. Keep the feature branch separate; G01 stays `NOT_EVALUATED` until its full criteria are verified and documented.

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
