# ElMiCo CRM - Κατάσταση υλοποίησης

Τελευταία ενημέρωση: 17 Νοεμβρίου 2026.
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
- W02 / SEC-05 / AT-06: PARTIAL — header fullname/title, MyDiary inline event data, client autocomplete, active-professional form attributes, import feedback, AJAX script handoff, and partner Excel export have targeted escaping/rendering regressions.
- W02 / SEC-06 / AT-07: PARTIAL — client, partner, task, active-professional, autocomplete and MyDiary queries now bind variable values; ordering remains fixed or allowlisted. SQL-like search and diary apostrophe regressions cover changed paths.
- Τελευταίοι έλεγχοι: PHPUnit 59 tests / 195 assertions PASS, including null contact rendering, partner title and Excel export regressions, SQL-like searches and MyDiary apostrophe round trip; browser login/logout/autocomplete checks PASS from the previous E2E run· focused PHP syntax lint PASS.
- Επόμενο βήμα: continue SEC-06 / AT-07 audit for diary queries and export filters/IDs; broader SEC-05 output review remains incomplete.

## Καταγραφή τρέχουσας εργασίας

| Καταγραφή | Αποτέλεσμα |
|---|---|
| Αρχικό σημείο αναφοράς | `27a805fbafcab86d0673c1f7f4911a45c1fcc8f1`; branch `codex/crm-w01-bootstrap`. |
| Τρέχον Git σημείο | Checked-in HEAD `9885c58`; υπάρχουν μη καταχωρισμένες αλλαγές W02 για CSRF, POST mutations, diary ownership, XSS output, tests και evidence. |
| Specs και οδηγίες | Το `docs/crm-specs/` εισήχθη ως canonical αντίγραφο. Ενημερώθηκε το `AGENTS.md` για το canonical spec path και την επιβεβαιωμένη συμπεριφορά recurring tasks χωρίς scheduled end date. |
| W01 baseline | [w01-baseline.md](evidence/w01-baseline.md): 34 tests, 5 PASS / 29 RED πριν από τις W02 διορθώσεις. Καταγράφηκαν δημόσια routes/data exposure, redirects εκτός `/crm/` και cookie policy gaps. |
| W02 RED | [w02-csrf.md](evidence/w02-csrf.md): πριν από την υλοποίηση, 38 tests / 3 failures για POST χωρίς CSRF, token field που έλειπε και GET διαγραφή πελάτη που εκτελούνταν. |
| W02 ROL-03 RED/GREEN | [w02-diary-ownership.md](evidence/w02-diary-ownership.md): 47 tests / 2 RED failures for foreign update/delete returning success; latest suite is GREEN at 47 tests / 126 assertions. |
| W02 SEC-05 RED/GREEN | [w02-xss.md](evidence/w02-xss.md): raw header fullname, diary inline JavaScript and active-professional attribute values reproduced as RED; current suite GREEN at 50 tests / 144 assertions and autocomplete E2E PASS. The pre-fix autocomplete browser attempt was interrupted, so its RED was not observed. |
| W02 SEC-05 import follow-up | [w02-xss.md](evidence/w02-xss.md): duplicate-code import feedback reproduced as raw text containing an attribute payload; output is now escaped and parser exceptions return a generic message. Focused regression PASS, 1 test / 5 assertions. |
| W02 SEC-05 AJAX follow-up | [w02-xss.md](evidence/w02-xss.md): a submitted `</script>` payload was reproduced in the `save_client_ajax.php` response before the fix; JSON_HEX encoding now protects the handoff. Full PHPUnit suite PASS, 52 tests / 155 assertions. |
| W02 SEC-05 document title and export | [w02-xss.md](evidence/w02-xss.md): partner fullname broke out of the shared `<title>` and appeared as raw HTML in the partner Excel export. Both sinks now escape the name. Full PHPUnit suite PASS, 54 tests / 165 assertions. |
| W02 SEC-06 query slice | [w02-sql.md](evidence/w02-sql.md): client, partner, task, active-professional and autocomplete searches use prepared statements; MyDiary note/event writes and reads also bind values. SQL-like input and apostrophe round-trip regressions pass. Full PHPUnit suite PASS, 59 tests / 195 assertions. |
| W02 PHPUnit | `docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 build app && docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 up -d app && docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm tests` — latest full PASS: 59 tests / 195 assertions. Runner verified `test-db / crm_w01_test` and used fictional fixtures. |
| W02 browser | `CRM_BASE_URL=https://127.0.0.1:18443/crm npm --prefix tests/e2e --cache /tmp/crm-w01-npm-cache test` — PASS: login/session ID rotation, POST logout, blocked re-entry with old session, hostile autocomplete name rendered as text, Secure, HttpOnly, SameSite=Lax και cookie path `/crm/`. Το Chrome εκτελέστηκε εκτός sandbox εξαιτίας socket restriction. |
| W02 syntax | `php -l` passed for the modified header, diary, update-event and CSRF files; PHPUnit parsed and exercised the latest active-professional form change. A broader application-wide PHP lint passed before these latest output changes. |
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

Συνέχισε το W02 με SEC-06 / AT-07 source audit for remaining export filters and partner-analysis export IDs; replace escaped SQL interpolation with prepared statements and preserve allowlists for dynamic sort columns. Broader SEC-05 / AT-06 output review remains open. Add targeted coverage for each changed query family and role/access boundary checks for existing routes. Μην εφαρμόσεις role rules σε μελλοντικούς catalog routes που δεν υπάρχουν ακόμη· κατέγραψε τα ως μελλοντική απαίτηση του σχετικού feature.

## Τελευταίο βήμα TDD

- SEC-01 / AT-01: τα αρχικά unauthenticated route/data regressions ήταν RED· οι προστατευμένες διαδρομές τώρα ανακατευθύνουν στο `/crm/login.php` και δεν εμφανίζουν fixtures.
- SEC-11 / AT-13: τα αρχικά Secure/HttpOnly/path checks ήταν RED· τώρα περνούν μαζί με SameSite=Lax και επιτυχής session ID rotation.
- SEC-04 / AT-05: RED για απουσία token και GET deletion· GREEN για missing/invalid/foreign token rejection, έγκυρο POST update/delete και μη εκτέλεση GET delete σε αντιπροσωπευτικές διαδρομές. Κατάσταση PARTIAL.
- ROL-03 / AT-04: read filtering already passed; RED for foreign diary event update/delete returning success responses, then GREEN with owner check and 404 rejection. Κατάσταση PARTIAL λόγω μη ολοκληρωμένου συνολικού route matrix.
- SEC-05 / AT-06: RED for raw header fullname, malicious diary values in inline JS and active-professional attribute values; GREEN with context-specific escaping, `data-*` handoff and text-only autocomplete rendering. Autocomplete pre-fix RED was not captured after its browser run was interrupted. Κατάσταση PARTIAL.
- SEC-12: browser regression επιβεβαίωσε POST logout και ακύρωση του session· οι cache headers σε προσωπικές σελίδες/exports δεν έχουν ελεγχθεί.
- Test evidence: `tests/integration/W01EnvironmentTest.php`, `tests/integration/W02CsrfTest.php`, `tests/unit/W01RuntimeTest.php`, `tests/e2e/login-page.test.cjs`· τελευταία εκτέλεση 50/50 PHPUnit tests PASS; latest relevant browser suite passed autocomplete and login/session checks.
- Μη καλυμμένα ακόμη: πλήρης role/authorization και diary ownership matrix, συνολικό XSS/SQL review, password policy, throttling/security events και cache behavior.

## Bugs, αποφάσεις και περιορισμοί

Τα αρχικά W01 ευρήματα καταγράφονται στο baseline evidence και αναπαράχθηκαν με πλασματικά δεδομένα. Τα entrypoint/session και τα επιλεγμένα CSRF regressions είναι πράσινα μετά τις W02 αλλαγές. Άλλα source-review findings δεν έχουν ακόμη ελεγχθεί πλήρως. Τα W02 acceptance criteria δεν έχουν ολοκληρωθεί, άρα δεν δηλώνεται συνολικό security pass.

Η συμφωνημένη συμπεριφορά recurring tasks παραμένει χειροκίνητη άμεση διακοπή, χωρίς scheduled end date. Οι προϋποθέσεις Otenet και backup/restore βρίσκονται στο `deployment.md` και δεν έχουν επαληθευτεί σε παραγωγή.

Μη χρησιμοποιείς πραγματικά στοιχεία πελατών ή production database. Μην εκτελείς `down -v` και μην επαναχρησιμοποιείς το test setup με το κανονικό `.env`.
