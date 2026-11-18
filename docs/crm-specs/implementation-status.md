# ElMiCo CRM - Κατάσταση υλοποίησης

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
| W03 | DONE_LOCAL | AT-45/46 local checks pass for isolated legacy upgrade, schema/data preservation, idempotency and CLI failure handling. Plesk activation and WordPress database isolation remain hosting verification. |
| W04 | DONE_LOCAL | AT-10/11/12 pass locally; see [W04 evidence](evidence/w04-throttle.md). Private key and cleanup scheduling require deployment configuration. |
| W08 | NOT_STARTED | — |
| G01 | NOT_EVALUATED | Απαιτούνται τα συνολικά checks που ορίζει το `tasks.md`. |
| W05 | IMPLEMENTED_LOCAL | Local implementation and focused tests cover AT-19 to AT-28; see [W05 evidence](evidence/w05-catalogs-payments.md). Keyboard/mobile visual review and final sign-off remain open. |
| W06 | IMPLEMENTED_LOCAL | Rule activation, effective versions, short-month dates, stop/restart, catch-up and stale-version rejection have MariaDB tests; see [recurrence evidence](evidence/w06-w07-recurring.md). |
| W07 | IMPLEMENTED_LOCAL | CLI scheduler, transactions, batch catch-up, dry-run, idempotency and concurrent MariaDB processes are covered locally. Actual Plesk Run Now and production-like timezone configuration remain open. |
| W09 | NOT_STARTED | Προϋπόθεση ολοκλήρωση προηγούμενων πακέτων. |
| W10 | NOT_STARTED | Προϋποθέσεις W09 PASS, preflight και ανάθεση deployment. |

## Ακριβές επόμενο βήμα

W03 and W04 local work is complete. Continue with W08, then evaluate G01. Keep G01 `NOT_EVALUATED` until all gate criteria are independently verified and documented.

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
