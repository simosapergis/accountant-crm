# ElMiCo CRM - Κατάσταση υλοποίησης

Τελευταία ενημέρωση: 17 Νοεμβρίου 2026.
Κατάσταση: W01 ολοκληρώθηκε· W02 σε εξέλιξη.
Έκδοση specs: 1.6.

Το αρχείο καταγράφει την πραγματική πρόοδο και τα αποδεικτικά. Δεν πιστοποιεί πρόσβαση ή εγκατάσταση στην παραγωγή.

## Σύνοψη τρέχουσας προόδου

- Τρέχον πακέτο: W02, σε εξέλιξη — κοινή authentication/session υποδομή και ευρύτεροι έλεγχοι ασφάλειας.
- Ολοκληρωμένα πακέτα υλοποίησης: 1 από 10 (W01). Το G01 παραμένει `NOT_EVALUATED`.
- W01: απομονωμένο Docker περιβάλλον, test DB guards/fixtures, PHPUnit και browser runners, frozen clock και baseline evidence.
- W02 μέχρι τώρα: κοινό session bootstrap, session cookie policy, session ID rotation στο login, idle timeout, auth guards στα page/AJAX/import/export entrypoints και redirects συμβατά με `/crm/`.
- Τελευταίοι έλεγχοι: PHPUnit 35 tests / 76 assertions PASS· browser login/session/cookie checks PASS.
- Επόμενο βήμα: TDD για CSRF στα state-changing requests, POST-only mutations και κατάργηση GET deletion actions· κατόπιν συνέχιση του W02 security audit.

## Καταγραφή τρέχουσας εργασίας

| Καταγραφή | Αποτέλεσμα |
|---|---|
| Αρχικό σημείο αναφοράς | `27a805fbafcab86d0673c1f7f4911a45c1fcc8f1`; branch `codex/crm-w01-bootstrap`. |
| Specs και οδηγίες | Το `docs/crm-specs/` εισήχθη ως canonical αντίγραφο. Ενημερώθηκε το `AGENTS.md` για το canonical spec path και την επιβεβαιωμένη συμπεριφορά recurring tasks χωρίς scheduled end date. |
| W01 baseline | [w01-baseline.md](evidence/w01-baseline.md): 34 tests, 5 PASS / 29 RED πριν από τις W02 διορθώσεις. Καταγράφηκαν δημόσια routes/data exposure, redirects εκτός `/crm/` και cookie policy gaps. |
| W02 PHPUnit | `docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 build app && docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 up -d app && docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm tests` — PASS, 35 tests / 76 assertions. Το test runner επιβεβαίωσε target `test-db / crm_w01_test` και χρησιμοποίησε πλασματικά fixtures. |
| W02 browser | `CRM_BASE_URL=https://127.0.0.1:18443/crm npm --prefix tests/e2e --cache /tmp/crm-w01-npm-cache test` — PASS: login page, session ID rotation, protected dashboard, Secure, HttpOnly, SameSite=Lax και cookie path `/crm/`. Το sandboxed Chrome launch απέτυχε με socket permission· η επανάληψη με εγκεκριμένη εκτέλεση εκτός sandbox πέρασε. |
| Περιβάλλον | Host PHP/Composer δεν είναι διαθέσιμα· οι PHP έλεγχοι εκτελούνται στο απομονωμένο Compose project. Chrome browser check απαιτεί εκτέλεση εκτός sandbox. |
| Παραγωγή | Δεν έγινε σύνδεση, εγκατάσταση, αλλαγή δεδομένων ή deployment στο Otenet. |

## Πακέτα εργασίας

| Πακέτο | Κατάσταση | Αποδεικτικό / εκκρεμότητα |
|---|---|---|
| W01 | DONE | [W01 baseline](evidence/w01-baseline.md), test harness, fixtures, clock και runners. |
| W02 | IN_PROGRESS | Auth/session foundation επαληθεύτηκε. Εκκρεμούν CSRF, POST-only mutations, ασφαλείς διαγραφές, έλεγχοι authorization/validation/SQL/output και τα acceptance tests του πακέτου. |
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

Πρόσθεσε πρώτα failing integration/browser tests για CSRF σε αλλαγές κατάστασης και απόρριψη GET-based mutations. Έπειτα εφάρμοσε token validation στα αντίστοιχα handlers/forms, μετέτρεψε διαγραφές και logout σε POST, και επανέλεγξε ότι όλες οι μεταβολές απαιτούν authenticated/authorized χρήστη. Συνέχισε με escaping, validation και prepared-statement audit βάσει W02 spec. Μην αλλάξεις συμπεριφορές που δεν ορίζονται στα specs ή στην υπάρχουσα εφαρμογή χωρίς να καταγράψεις την απαιτούμενη απόφαση.

## Τελευταίο βήμα TDD

- SEC-01 / AT-01: τα αρχικά unauthenticated route/data regressions ήταν RED· οι προστατευμένες διαδρομές τώρα ανακατευθύνουν στο `/crm/login.php` και δεν εμφανίζουν fixtures.
- SEC-11 / AT-13: τα αρχικά Secure/HttpOnly/path checks ήταν RED· τώρα περνούν μαζί με SameSite=Lax και επιτυχής session ID rotation.
- Test evidence: `tests/integration/W01EnvironmentTest.php`, `tests/unit/W01RuntimeTest.php`, `tests/e2e/login-page.test.cjs`· τελευταία εκτέλεση 35/35 PHPUnit tests και όλα τα browser checks PASS.
- Μη καλυμμένα ακόμη: CSRF και προστασία των state-changing routes από GET· παραμένει W02 εργασία.

## Bugs, αποφάσεις και περιορισμοί

Τα αρχικά W01 ευρήματα καταγράφονται στο baseline evidence και αναπαράχθηκαν με πλασματικά δεδομένα. Τα συγκεκριμένα entrypoint/session regressions που καλύπτει το τρέχον suite είναι πράσινα μετά τις W02 αλλαγές. Άλλα source-review findings δεν έχουν ακόμη ελεγχθεί πλήρως. Τα W02 acceptance criteria δεν έχουν ολοκληρωθεί, άρα δεν δηλώνεται συνολικό security pass.

Η συμφωνημένη συμπεριφορά recurring tasks παραμένει χειροκίνητη άμεση διακοπή, χωρίς scheduled end date. Οι προϋποθέσεις Otenet και backup/restore βρίσκονται στο `deployment.md` και δεν έχουν επαληθευτεί σε παραγωγή.

Μη χρησιμοποιείς πραγματικά στοιχεία πελατών ή production database. Μην εκτελείς `down -v` και μην επαναχρησιμοποιείς το test setup με το κανονικό `.env`.
