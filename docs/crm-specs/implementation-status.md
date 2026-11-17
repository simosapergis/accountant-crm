# ElMiCo CRM - Κατάσταση υλοποίησης

Τελευταία ενημέρωση: 18 Νοεμβρίου 2026.
Κατάσταση: W01 ολοκληρώθηκε· W02 σε εξέλιξη.
Έκδοση specs: 1.6.

Το αρχείο καταγράφει την πραγματική πρόοδο και τα αποδεικτικά. Δεν πιστοποιεί πρόσβαση ή εγκατάσταση στην παραγωγή.

## Σύνοψη τρέχουσας προόδου

- Τρέχον πακέτο: W02, σε εξέλιξη — κοινή authentication/session υποδομή και ευρύτεροι έλεγχοι ασφάλειας.
- Ολοκληρωμένα πακέτα υλοποίησης: 1 από 10 (W01). Το G01 παραμένει `NOT_EVALUATED`.
- W01: απομονωμένο Docker περιβάλλον, test DB guards/fixtures, PHPUnit και browser runners, frozen clock και baseline evidence.
- W02 μέχρι τώρα: κοινό session bootstrap, session cookie policy, session ID rotation στο login, idle timeout, auth guards και `/crm/` redirects· CSRF validation για POST, POST-only logout/AJAX mutations και POST deletion forms.
- W02 / SEC-04 / AT-05: PARTIAL — tests cover missing/invalid/foreign CSRF tokens, valid update/delete, login/logout and representative GET deletions. Δεν έχει ολοκληρωθεί ολόκληρο το route matrix ή το συνολικό W02 security audit.
- W02 / ROL-03 / AT-04: PARTIAL — private diary read filtering and foreign event update/delete rejection have tests and implementation.
- W02 / SEC-05 / AT-06: PARTIAL — existing header, diary, autocomplete, import feedback, AJAX handoff, partner Excel, active-professional PDF HTML and export-link attributes have targeted fixes. Full output inventory and Dompdf rendering remain unverified.
- W02 / SEC-06 / AT-07: PARTIAL — client, partner, task, active-professional, autocomplete, MyDiary and remaining export query families use prepared statements for values. Export identifiers are strictly validated; sort clauses are fixed or allowlisted. Export regressions cover query filters, partner IDs, cache policy and spreadsheet text handling.
- ROL-02 / AT-03: PARTIAL — a logged-in `user` can edit a shared task with valid CSRF; recurrence and catalog flows do not yet exist. AT-02 has no current catalog route to exercise and remains a W05 acceptance item.
- SEC-07/08 / AT-08/09: password length/algorithm limits are enforced by shared policy, login preserves surrounding spaces, and tests verify no plaintext hash storage.
- SEC-11/12 / AT-14/15: exact idle timeout boundary and cache headers on representative authenticated pages/exports are covered. Logout/old-cookie browser coverage remains in earlier evidence.
- SEC-14 / AT-17/18: import size/type/preflight/transaction behavior and literal spreadsheet text handling have regressions; corrupt workbook breadth and real spreadsheet application verification remain open.
- SEC-13 / AT-16: eight HTTP probes now deny direct access to app config, include/vendor internals, Composer manifests, CLI and installer paths. Deployment webroot behavior still requires verification.
- PAY-08 / AT-27: task fee/collection input now parses to integer cents and persists canonical decimal strings; valid forms and invalid no-write behavior are covered.
- Latest full PHPUnit run: PASS, 93 tests / 315 assertions in isolated `test-db / crm_w01_test`. Focused money/session tests: PASS, 5 tests / 28 assertions; sensitive-path matrix: PASS, 8 tests / 24 assertions. Browser/E2E was not rerun because these changes are server-side.
- Επόμενο βήμα: audit AT-16 sensitive file/routes, finish the AT-04/05/06/07 coverage and broader authorization/output review, and close remaining AT-17/18 manual breadth. Do not mark W02 or G01 complete while required criteria and evidence remain open.

## Καταγραφή τρέχουσας εργασίας

| Καταγραφή | Αποτέλεσμα |
|---|---|
| Αρχικό σημείο αναφοράς | `27a805fbafcab86d0673c1f7f4911a45c1fcc8f1`; branch `codex/crm-w01-bootstrap`. |
| Τρέχον Git σημείο | Branch `codex/crm-w02-security-audit`; commits `1a1e4e2` and `237d795` contain the first export slice/evidence. Follow-up security changes are being verified before commit. W02 remains in progress. |
| Specs και οδηγίες | Το `docs/crm-specs/` εισήχθη ως canonical αντίγραφο. Ενημερώθηκε το `AGENTS.md` για το canonical spec path και την επιβεβαιωμένη συμπεριφορά recurring tasks χωρίς scheduled end date. |
| W01 baseline | [w01-baseline.md](evidence/w01-baseline.md): 34 tests, 5 PASS / 29 RED πριν από τις W02 διορθώσεις. Καταγράφηκαν δημόσια routes/data exposure, redirects εκτός `/crm/` και cookie policy gaps. |
| W02 RED | [w02-csrf.md](evidence/w02-csrf.md): πριν από την υλοποίηση, 38 tests / 3 failures για POST χωρίς CSRF, token field που έλειπε και GET διαγραφή πελάτη που εκτελούνταν. |
| W02 ROL-03 RED/GREEN | [w02-diary-ownership.md](evidence/w02-diary-ownership.md): 47 tests / 2 RED failures for foreign update/delete returning success; suite was GREEN at that checkpoint at 47 tests / 126 assertions. |
| W02 SEC-05 RED/GREEN | [w02-xss.md](evidence/w02-xss.md): raw header fullname, diary inline JavaScript and active-professional attribute values reproduced as RED at that checkpoint; the latest suite is GREEN at 59 tests / 195 assertions and autocomplete E2E PASS. The pre-fix autocomplete browser attempt was interrupted, so its RED was not observed. |
| W02 SEC-05 import follow-up | [w02-xss.md](evidence/w02-xss.md): duplicate-code import feedback reproduced as raw text containing an attribute payload; output is now escaped and parser exceptions return a generic message. Focused regression PASS, 1 test / 5 assertions. |
| W02 SEC-05 AJAX follow-up | [w02-xss.md](evidence/w02-xss.md): a submitted `</script>` payload was reproduced in the `save_client_ajax.php` response before the fix; JSON_HEX encoding now protects the handoff. Full PHPUnit suite PASS, 52 tests / 155 assertions. |
| W02 SEC-05 document title and export | [w02-xss.md](evidence/w02-xss.md): partner fullname broke out of the shared `<title>` and appeared as raw HTML in the partner Excel export. Both sinks now escape the name. Full PHPUnit suite PASS, 54 tests / 165 assertions. |
| W02 SEC-06 query slice | [w02-sql.md](evidence/w02-sql.md): client, partner, task, active-professional and autocomplete searches use prepared statements; MyDiary note/event writes and reads also bind values. SQL-like input and apostrophe round-trip regressions pass. Full PHPUnit suite PASS, 59 tests / 195 assertions. |
| W02 security baseline follow-up | [w02-exports.md](evidence/w02-exports.md): export SQL/output, cache policy, spreadsheet formula handling, imports, password whitespace/policy, exact idle expiry, AT-16 HTTP denials and task money parsing. Latest full suite PASS, 93 tests / 315 assertions. Dompdf output remains blocked by the known missing dependency. |
| W02 PHPUnit | `docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 build app && docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 up -d app && docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm tests` — latest full PASS: 59 tests / 195 assertions. Runner verified `test-db / crm_w01_test` and used fictional fixtures. |
| W02 PHPUnit latest | `docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 build app`; `docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm tests` — PASS, 72 tests / 231 assertions. Runner verified `test-db / crm_w01_test`; fixtures are fictional. |
| W02 PHPUnit current | `docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 build app`; `docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm tests` — PASS, 93 tests / 315 assertions. Runner verified `test-db / crm_w01_test`; fixtures are fictional. |
| W02 browser | `CRM_BASE_URL=https://127.0.0.1:18443/crm npm --prefix tests/e2e --cache /tmp/crm-w01-npm-cache test` — PASS: login/session ID rotation, POST logout, blocked re-entry with old session, hostile autocomplete name rendered as text, Secure, HttpOnly, SameSite=Lax και cookie path `/crm/`. Το Chrome εκτελέστηκε εκτός sandbox εξαιτίας socket restriction. |
| W02 syntax | `php -l` passed for every touched app PHP file; PHPUnit parsed the changed test files. `git diff --check` passed with `core.whitespace=cr-at-eol` because touched legacy PHP files use CRLF. |
| Περιβάλλον | Host PHP/Composer δεν είναι διαθέσιμα· οι PHP έλεγχοι εκτελούνται στο απομονωμένο Compose project. Chrome browser check απαιτεί εκτέλεση εκτός sandbox. |
| Παραγωγή | Δεν έγινε σύνδεση, εγκατάσταση, αλλαγή δεδομένων ή deployment στο Otenet. |

## Πακέτα εργασίας

| Πακέτο | Κατάσταση | Αποδεικτικό / εκκρεμότητα |
|---|---|---|
| W01 | DONE | [W01 baseline](evidence/w01-baseline.md), test harness, fixtures, clock και runners. |
| W02 | IN_PROGRESS | Auth/session, selected CSRF/method, diary ownership and XSS output paths verified; [CSRF](evidence/w02-csrf.md), [diary ownership](evidence/w02-diary-ownership.md), [XSS](evidence/w02-xss.md). Εκκρεμούν πλήρης AT-04/AT-05/AT-06 coverage, role checks, SQL, password policy, throttling/events και cache controls. |
| W03 | NOT_STARTED | — |
| W04 | NOT_STARTED | — |
| W08 | NOT_STARTED | — |
| G01 | NOT_EVALUATED | Απαιτούνται τα συνολικά checks που ορίζει το `tasks.md`. |
| W05 | NOT_STARTED | Προϋπόθεση G01 PASS. |
| W06 | NOT_STARTED | Προϋπόθεση G01 PASS. |
| W07 | NOT_STARTED | Προϋποθέσεις G01 PASS και W06. |
| W09 | NOT_STARTED | Προϋπόθεση ολοκλήρωση προηγούμενων πακέτων. |
| W10 | NOT_STARTED | Προϋποθέσεις W09 PASS, preflight και ανάθεση deployment. |

## Ακριβές επόμενο βήμα

Συνέχισε το W02 με AT-16, την υπόλοιπη AT-04/05/06/07 route/output/access κάλυψη και remaining AT-17/18 breadth. Πρόσθεσε role tests μόνο για υπάρχοντα όρια εξουσιοδότησης· εφάρμοσε admin checks όταν δημιουργηθούν οι κατάλογοι στο W05.

## Τελευταίο βήμα TDD

- SEC-01 / AT-01: τα αρχικά unauthenticated route/data regressions ήταν RED· οι προστατευμένες διαδρομές τώρα ανακατευθύνουν στο `/crm/login.php` και δεν εμφανίζουν fixtures.
- SEC-11 / AT-13: τα αρχικά Secure/HttpOnly/path checks ήταν RED· τώρα περνούν μαζί με SameSite=Lax και επιτυχής session ID rotation.
- SEC-04 / AT-05: RED για απουσία token και GET deletion· GREEN για missing/invalid/foreign token rejection, έγκυρο POST update/delete και μη εκτέλεση GET delete σε αντιπροσωπευτικές διαδρομές. Κατάσταση PARTIAL.
- ROL-03 / AT-04: read filtering already passed; RED for foreign diary event update/delete returning success responses, then GREEN with owner check and 404 rejection. Κατάσταση PARTIAL λόγω μη ολοκληρωμένου συνολικού route matrix.
- SEC-05 / AT-06: RED for raw header fullname, malicious diary values in inline JS and active-professional attribute values; GREEN with context-specific escaping, `data-*` handoff and text-only autocomplete rendering. Autocomplete pre-fix RED was not captured after its browser run was interrupted. Κατάσταση PARTIAL.
- SEC-12 / AT-15: authenticated cache headers are covered for representative pages and exports; prior browser checks cover POST logout and old-session rejection.
- SEC-07/08 / AT-08/09: policy tests cover 14/15 Unicode character boundary and bcrypt byte limit; login regression proves surrounding spaces are preserved. Interactive CLI acceptance remains open.
- SEC-11 / AT-14: RED at exactly 1,800 seconds, then GREEN after changing the expiry comparison to include the boundary.
- SEC-13 / AT-16: RED for 200 responses from internal include, Composer manifest/lock and vendor metadata; GREEN after Apache access rules, with eight paths rejected (403/404) and no content markers.
- SEC-14 / AT-17 and AT-18: RED/GREEN import tests reject renamed CSV/oversized files and prevent partial insert on invalid rows; spreadsheet tests confirm formula-leading text is stored as literal text in HTML/XLSX export paths.
- PAY-08 / AT-27: RED when parser helper was absent; GREEN for accepted formats, rejection cases and exact task fee persistence. Full isolated suite: 93 tests / 315 assertions PASS.
- Μη καλυμμένα ακόμη: complete route-role/diary/CSRF matrices, full XSS/SQL and sensitive-file inventory, throttling/security events, corrupt import corpus, manual spreadsheet opening, CLI interaction and PDF output.
- SEC-06 / AT-07 export follow-up: prepared filters and strict partner IDs pass focused tests; client sorting uses an explicit allowlist. See [w02-exports.md](evidence/w02-exports.md).
- SEC-05 / AT-06 remains PARTIAL: active-professional PDF HTML and list export-link attributes were additionally reviewed and escaped; Dompdf is absent in the test image, and further output review is required.
- ROL-02 / AT-03 existing-flow check: `user` role can edit a shared task with a valid CSRF token. Catalog permissions are not testable until W05 creates those routes.

## Bugs, αποφάσεις και περιορισμοί

Τα αρχικά W01 ευρήματα καταγράφονται στο baseline evidence και αναπαράχθηκαν με πλασματικά δεδομένα. Τα entrypoint/session και τα επιλεγμένα CSRF regressions είναι πράσινα μετά τις W02 αλλαγές. Άλλα source-review findings δεν έχουν ακόμη ελεγχθεί πλήρως. Τα W02 acceptance criteria δεν έχουν ολοκληρωθεί, άρα δεν δηλώνεται συνολικό security pass.

Η συμφωνημένη συμπεριφορά recurring tasks παραμένει χειροκίνητη άμεση διακοπή, χωρίς scheduled end date. Οι προϋποθέσεις Otenet και backup/restore βρίσκονται στο `deployment.md` και δεν έχουν επαληθευτεί σε παραγωγή.

Μη χρησιμοποιείς πραγματικά στοιχεία πελατών ή production database. Μην εκτελείς `down -v` και μην επαναχρησιμοποιείς το test setup με το κανονικό `.env`.
