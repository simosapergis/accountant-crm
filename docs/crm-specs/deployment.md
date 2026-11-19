# ElMiCo CRM - Δοκιμαστική εγκατάσταση στο Otenet/Plesk

Το παρόν είναι runbook για περιορισμένη δοκιμαστική εγκατάσταση στο υπάρχον hosting. Δεν αποτελεί επιβεβαίωση ότι η εγκατάσταση ή η παραγωγική έγκριση έχουν ολοκληρωθεί.

Η εγκατάσταση γίνεται στο ίδιο domain με το static site, στη διεύθυνση `https://elmico.gr/crm/`. Δεν χρησιμοποιείται subdomain και δεν εγκαθίσταται Docker στο Otenet/Plesk. Η PHP και η MariaDB του hosting εκτελούν την εφαρμογή.

Μην καταγράψεις σε αυτό το αρχείο passwords, tokens, database credentials ή το `CRM_SECURITY_HMAC_KEY`.

## Κατάσταση και όρια

- Ο χρήστης επιβεβαίωσε ότι το αρχικό τοπικό βήμα δοκιμής έχει ολοκληρωθεί. Δεν επαναλαμβάνεται ως μέρος της εγκατάστασης στο Plesk. Η τοπική δοκιμή δεν επιβεβαιώνει τη λειτουργία στο hosting.
- Το repository βρίσκεται στο branch `codex/crm-remaining-tasks`. Το commit αλλάζει με κάθε νέα έκδοση· καταγράφεται ξανά κατά τη δημιουργία κάθε release.
- Το ιστορικό G01 PASS αφορά μόνο το καταγεγραμμένο παλιότερο commit και δεν αποτελεί έγκριση του τρέχοντος merged κώδικα.
  Το W09 παραμένει `IN_PROGRESS`, όπως καταγράφεται στο `implementation-status.md`.
  Η περιορισμένη δοκιμή Plesk στο `/crm/` ολοκληρώθηκε στις 19 Νοεμβρίου 2026 με νέα, ξεχωριστή pre-production βάση. Τα ευρήματα και τα όριά τους καταγράφονται στο `implementation-status.md`.
  Δεν έχει εγκριθεί παραγωγική λειτουργία και η δοκιμή δεν αποτελεί production sign-off.

## Γνωστά στοιχεία hosting

| Στοιχείο | Καταγεγραμμένη πληροφορία |
|---|---|
| Provider / panel | Otenet με Plesk, panel `w6113.controlpanel.ote.gr`. |
| Domain | `elmico.gr`. |
| Ενεργό document root | Εμφανίζεται ως `Home/elmico.gr` στο Hosting Settings. Επιβεβαίωσε το πραγματικό απόλυτο filesystem path πριν από οποιαδήποτε μεταφορά. |
| Ξεχωριστός φάκελος | Το `httpdocs` είναι διπλανός φάκελος, όχι ο ενεργός document root. Μην τον θεωρήσεις ενεργό root. |
| CRM URL | `https://elmico.gr/crm/`. |
| PHP | Έχει καταγραφεί PHP 8.3.36, CGI/FastCGI, Linux 64-bit. Επιβεβαίωσέ το ξανά για το συγκεκριμένο domain και CLI. |
| PHP όρια | Έχουν καταγραφεί `memory_limit=128M`, `upload_max_filesize=2M`, `post_max_size=8M`, `max_execution_time=300`. Επανέλεγξέ τα στο domain και στο CLI. |
| Extensions | Έχουν επιβεβαιωθεί MySQLi/mysqlnd, GD και Zip. Τα υπόλοιπα απαιτούν τελικό platform check. |
| Εργαλεία Plesk | Έχουν εμφανιστεί PHP Composer, Git, Scheduled Tasks και Logs. Η ορατότητα εργαλείου δεν αποδεικνύει πρόσβαση ή λειτουργία. |
| Βάσεις | Υπάρχει ξεχωριστή παλιά βάση WordPress. Το CRM παίρνει δική του βάση και database user. |
| HTTPS | Έχουν καταγραφεί ενεργό Let’s Encrypt και ανακατεύθυνση HTTP σε HTTPS. Επαλήθευσέ τα στο `/crm/`. |
| Backups | Έχουν εμφανιστεί καθημερινά scheduled backups. Δεν έχει επιβεβαιωθεί ακόμη ότι περιλαμβάνουν τη νέα βάση ούτε ότι έχει δοκιμαστεί restore. |
| Deployment runtime | Native PHP και MariaDB του Otenet/Plesk. Δεν χρησιμοποιείται Docker στον server. |

Η ίδια-origin διεύθυνση `/crm/` είναι επιλεγμένη αρχιτεκτονική. Το cookie περιορίζεται στο path `/crm/`, αλλά το CRM και το static site παραμένουν στην ίδια browser origin. Μην προσθέτεις μη ελεγμένα scripts στο static site που θα μπορούσαν να επηρεάσουν αυτή την origin.

**Αποτέλεσμα αρχικού ελέγχου MariaDB από τον χρήστη:** MariaDB `10.11.5-MariaDB`, InnoDB `DEFAULT`, και `utf8mb4`/`utf8mb4_general_ci` διαθέσιμα. Τα server defaults εμφανίστηκαν ως `latin1`/`latin1_swedish_ci`. Αυτό δεν εμποδίζει την εφαρμογή να χρησιμοποιεί `utf8mb4`, εφόσον τα tables το ορίζουν ρητά. Το αρχικό `accountant_crm.sql` πλέον ορίζει και τον πίνακα `users` ως `utf8mb4`, ενώ η migration `20261119_001_users_utf8mb4` μετατρέπει παλιό `users` table σε `utf8mb4` διατηρώντας τα δεδομένα. Η αλλαγή θα θεωρηθεί έτοιμη για pre-prod αφού περάσουν τα migration regression tests· η βάση προτείνεται να δημιουργείται με default `utf8mb4_general_ci` όπου το Plesk το επιτρέπει.

## Σημαντικά τεχνικά προαπαιτούμενα

Πριν γίνει το app διαθέσιμο μέσω HTTP, πρέπει να επιβεβαιωθούν τα παρακάτω:

1. Η PHP του web handler και η PHP CLI είναι 8.3 και διαθέτουν τις απαιτούμενες επεκτάσεις.
2. Η MariaDB είναι συμβατή με InnoDB, foreign keys, `utf8mb4` και τα migrations του app.
3. Το CRM χρησιμοποιεί ξεχωριστό ιδιωτικό αρχείο ρυθμίσεων και κοινό loader για web PHP και CLI tasks. Δεν εξαρτάται από το να περάσει το Plesk αυθαίρετες environment variables στον CGI/FastCGI handler, και δεν διαβάζει το `wp-config.php`.
4. Ο κοινός loader `includes/config.php` υποστηρίζει ιδιωτικό PHP config για web και CLI.
   Πριν από deployment, επαλήθευσε τη φόρτωση στο πραγματικό Plesk και τα AT-56 permissions/HTTP/backup σκέλη· οι τοπικοί έλεγχοι δεν πιστοποιούν το hosting.
5. Το app σήμερα φορτώνει Composer dependencies από `ACCOUNTANT_CRM/vendor/autoload.php`. Το `vendor/`, τα `bin/`, `migrations/`, `includes/` και τα Composer metadata βρίσκονται κάτω από το app tree. Το `.htaccess` περιέχει απαγορεύσεις, αλλά πρέπει να αποδειχθεί με HTTP requests ότι ισχύουν και στο πραγματικό Plesk web stack. Αν αρχεία είναι προσβάσιμα, σταμάτησε και ρύθμισε απαγορεύσεις Apache/nginx ή μετακίνησε dependencies/CLI έξω από το public root με αντίστοιχη υποστήριξη από τον κώδικα.
6. Το Plesk Scheduled Task μπορεί να εκτελέσει τα CLI scripts με σωστή PHP, filesystem permissions και τις ίδιες database/key ρυθμίσεις.
7. Το logging δεν αποκαλύπτει σφάλματα, paths, SQL ή credentials στον browser και τα αρχεία καταγραφής βρίσκονται εκτός public root.

Στο Plesk, το nginx μπορεί να σερβίρει ορισμένα αιτήματα χωρίς να εφαρμόζεται το `.htaccess`. Έλεγξε το **Websites & Domains → Apache & nginx Settings** και επιβεβαίωσε την πραγματική συμπεριφορά με HTTP requests. [Plesk: Apache και nginx](https://docs.plesk.com/en-US/obsidian/administrator-guide/website-management/websites-and-domains/hosting-settings/web-server-settings/apache-and-nginx-settings.72320/)

Αν κάποια δυνατότητα είναι κλειδωμένη από το hosting plan, επικοινώνησε με Otenet με συγκεκριμένο αίτημα: έκδοση PHP CLI, κοινές ιδιωτικές web/CLI ρυθμίσεις, προστασία paths ή δικαιώματα ιδιωτικού φακέλου. Μην ζητήσεις Docker ή αλλαγή hosting χωρίς συγκεκριμένη τεχνική ανάγκη.

## Σειρά ενεργειών

### 1. Επιβεβαίωσε το Plesk περιβάλλον

1. Άνοιξε το Hosting Settings του `elmico.gr` και επιβεβαίωσε το ενεργό document root και τον handler PHP.
2. Κατέγραψε το πραγματικό απόλυτο filesystem path που αντιστοιχεί στο `Home/elmico.gr`. Μη χρησιμοποιήσεις ως υπόθεση το `/httpdocs`.
3. Επιβεβαίωσε PHP web version, MariaDB version, διαθέσιμο χώρο και δικαιώματα hosting user από Plesk. Η SSH πρόσβαση έχει αναφερθεί ως `Forbidden`, οπότε μην βασιστείς σε `php --version` από SSH.
4. Το web PHP έχει ελεγχθεί από Plesk PHP Info ως `CGI/FastCGI`, PHP `8.3.36 r13`. Η λίστα που έστειλε ο χρήστης περιλαμβάνει `mysqli`, `mysqlnd`, `gd`, `zip` και όλες τις απαιτούμενες extensions που απαριθμούνται παρακάτω. Αυτό επιβεβαιώνει μόνο το web PHP.
5. Για το CLI PHP, ακολούθησε την υποενότητα **Έλεγχος CLI PHP χωρίς SSH** παρακάτω και επίλεξε `8.3.36 r13` στο προσωρινό Plesk task.
6. Μετά την εγκατάσταση των production dependencies μέσω Plesk Composer, εκτέλεσε `composer check-platform-reqs --no-dev` αν η Plesk UI ή η Otenet παρέχει ασφαλή Composer CLI εκτέλεση στο `/crm/`. Αν το UI προσφέρει μόνο **Install Dependencies**, κράτησε το επιτυχές αποτέλεσμα και τα logs ως platform check ισοδύναμο· αν αποτύχει, μην συνεχίσεις. Μην μαντέψεις διαδρομή Composer ούτε μην περάσεις credentials σε command arguments.
7. Επιβεβαίωσε ότι web PHP και CLI βλέπουν την ίδια ιδιωτική βάση/κλειδί ρυθμίσεων, χωρίς να τυπώσεις τις τιμές σε output ή logs.
8. Επιβεβαίωσε Plesk timezone. Τα scheduled tasks εκτελούνται στη ζώνη ώρας server εξ ορισμού και σε Linux μπορεί να λειτουργούν σε chrooted filesystem. [Plesk: Scheduled Tasks](https://docs.plesk.com/en-US/obsidian/customer-guide/scheduling-tasks.65207/)

Μην αλλάξεις τα καταγεγραμμένα PHP limits χωρίς λόγο. Δοκίμασε imports με τα όρια του hosting και αύξησε τα μόνο αν τεκμηριωμένα απαιτείται, με συμφωνημένες τιμές `upload_max_filesize` και `post_max_size`.

#### Έλεγχος CLI PHP χωρίς SSH

Εκτέλεσε αυτόν τον έλεγχο αφού ανέβουν τα app files στο `/crm/`, ώστε το προσωρινό script να μπορεί να φυλαχθεί στο προστατευμένο `bin/` directory:

1. Έλεγξε πρώτα ότι `https://elmico.gr/crm/bin/migrate.php` και `https://elmico.gr/crm/bin/run-recurring-tasks.php` επιστρέφουν `403` ή `404`. Αν οποιοδήποτε είναι προσβάσιμο, μην αποθηκεύσεις προσωρινό script στο `bin/`· ζήτησε από Otenet ιδιωτικό path για Plesk PHP tasks.
2. Στο Plesk File Manager δημιούργησε προσωρινό αρχείο `/crm/bin/cli-extension-check.php` με το παρακάτω περιεχόμενο. Δεν περιλαμβάνει database credentials ή άλλο secret:

   ```php
   <?php
   $required = [
       'ctype', 'dom', 'fileinfo', 'gd', 'iconv', 'libxml', 'mbstring',
       'mysqli', 'mysqlnd', 'session', 'SimpleXML', 'xml', 'xmlreader',
       'xmlwriter', 'zip', 'zlib',
   ];
   $missing = [];
   echo 'PHP_VERSION=' . PHP_VERSION . PHP_EOL;
   echo 'PHP_SAPI=' . PHP_SAPI . PHP_EOL;
   foreach ($required as $extension) {
       $loaded = extension_loaded($extension);
       echo $extension . '=' . ($loaded ? 'OK' : 'MISSING') . PHP_EOL;
       if (!$loaded) {
           $missing[] = $extension;
       }
   }
   exit($missing === [] ? 0 : 1);
   ```

3. Έλεγξε ξανά ότι `https://elmico.gr/crm/bin/cli-extension-check.php` δεν επιστρέφει `200` ή περιεχόμενο. Μην εκτελέσεις το script από browser.
4. Άνοιξε **Websites & Domains → Scheduled Tasks → Add Task**. Επίλεξε **Run a PHP script**, βρες το `crm/bin/cli-extension-check.php` από το file picker και επίλεξε PHP `8.3.36 r13`.
5. Για τον προσωρινό έλεγχο επίλεξε ειδοποίηση με το task output σε email που ελέγχεις. Εκτέλεσε **Run Now** μία φορά και έλεγξε ότι αναφέρει `PHP_VERSION=8.3.36`, `PHP_SAPI=cli` και `OK` σε κάθε extension. Αν το output δείξει άλλη PHP έκδοση/SAPI ή missing extension, σταμάτησε και επικοινώνησε με Otenet.
6. Διέγραψε το προσωρινό task και το `cli-extension-check.php` από File Manager αμέσως μετά τον έλεγχο. Μην αφήσεις διαγνωστικό script στο server.

Αν το Plesk δεν εμφανίζει script output ή δεν μπορεί να εκτελέσει το αρχείο, ζήτησε από Otenet να τρέξει το check μέσω PHP 8.3.36 r13 CLI και να σου επιστρέψει μόνο PHP version, SAPI και το αποτέλεσμα των extensions. Μην ενεργοποιήσεις SSH χωρίς να επιβεβαιωθεί πρώτα η πολιτική hosting.

### 2. Έλεγξε και προστάτεψε τον υπάρχοντα ιστότοπο

1. Επιβεβαίωσε ότι η θέση `/crm/` δεν περιέχει ήδη αρχεία που χρειάζονται. Αν περιέχει, πάρε αντίγραφο πριν την αντικατάσταση και μην το καθαρίσεις.
2. Πάρε αντίγραφο των αρχείων που θα αλλάξουν. Δεν χρειάζεται και δεν πρέπει να αλλάξει το document root του `elmico.gr` για αυτή τη δοκιμή.
3. Βεβαιώσου ότι το deployment του static site δεν κάνει καθολικό καθαρισμό του document root και διατηρεί `/crm/`, `.well-known` και τις ρυθμίσεις hosting.
4. Μην τροποποιήσεις ή διαγράψεις τον διπλανό φάκελο `httpdocs`, τη βάση WordPress ή τους φακέλους `.wp-cli`, `.wp-toolkit`, `wordpress-backups`, `logs`.
5. Αν το Plesk παρέχει IP restriction ή άλλη προσωρινή προστασία, ενεργοποίησέ την για το CRM όσο εκτελούνται οι δοκιμές. Η προστασία του CRM δεν πρέπει να κρύβει ή να αλλάζει το δημόσιο static site.

### 3. Δημιούργησε ξεχωριστή pre-prod βάση

1. Στο Plesk δημιούργησε νέα, κενή βάση με σαφές όνομα, π.χ. `crm_preprod`.
2. Δημιούργησε νέο database user με δικαιώματα μόνο στη `crm_preprod`.
3. Επιβεβαίωσε το πραγματικό database hostname. Μην υποθέσεις ότι είναι `localhost`.
4. Άνοιξε phpMyAdmin από τη βάση `crm_preprod` και επιβεβαίωσε ότι είναι κενή.
5. Εισήγαγε το root `accountant_crm.sql` μόνο σε αυτή την κενή βάση. Το SQL περιέχει `DROP TABLE IF EXISTS` και δεν πρέπει να χρησιμοποιηθεί σε βάση που περιέχει δεδομένα.
6. Επιβεβαίωσε ότι δημιουργήθηκαν οι αρχικοί πίνακες. Το SQL είναι αρχικό schema και δεν περιλαμβάνει λογαριασμό CRM.

Μην εισαγάγεις πραγματικά δεδομένα πελατών. Μην επιλέξεις ή επαναχρησιμοποιήσεις τη WordPress βάση.

### 4. Φτιάξε release από συγκεκριμένο commit

Πριν από την εξαγωγή, βεβαιώσου ότι τα αρχεία που θα παραδοθούν είναι committed και το working tree είναι καθαρό:

```bash
git status --short
git rev-parse HEAD
```

Από Linux μπορεί να εξαχθεί μόνο το tracked `ACCOUNTANT_CRM/` χωρίς Docker. Τα production dependencies εγκαθίστανται αργότερα στο Plesk:

```bash
release_sha="$(git rev-parse --short HEAD)"
git archive --format=tar HEAD:ACCOUNTANT_CRM | gzip > "accountant-crm-${release_sha}.tar.gz"
sha256sum "accountant-crm-${release_sha}.tar.gz"
tar -tzf "accountant-crm-${release_sha}.tar.gz" | head -40
```

Έλεγξε το περιεχόμενο του archive πριν το upload. Περιλαμβάνει τις PHP σελίδες, `assets/`, `includes/`, `bin/`, `migrations/`, `.htaccess`, `composer.json` και `composer.lock`. Δεν περιλαμβάνει `.git`, tests, `vendor/`, `.env`, backups, SQL dump ή docs.

Κατέγραψε σε ασφαλές deployment σημείωμα το commit ID, το όνομα archive και το SHA-256. Μην καταγράψεις secrets.

### 5. Ανέβασε το app στο `/crm/`

1. Δημιούργησε τον φάκελο `/crm/` κάτω από το επιβεβαιωμένο document root του `elmico.gr`, αν δεν υπάρχει.
2. Ανέβασε το `accountant-crm-<commit>.tar.gz` με Plesk File Manager ή SFTP.
3. Αποσυμπίεσέ το έτσι ώστε το `index.php` και το `login.php` να βρίσκονται στο `<document-root>/crm/` και όχι σε nested διαδρομή όπως `/crm/ACCOUNTANT_CRM/`.
4. Μην αποσυμπιέσεις το archive πάνω από το document root. Μην αντικαταστήσεις το parent `.htaccess` ή αρχεία του static site.
5. Ρύθμισε read permissions για τον PHP hosting user. Μη χρησιμοποιήσεις `777`.
6. Εγκατάστησε dependencies από την Plesk UI:
   - Άνοιξε **Websites & Domains → Applications → Scan** για να εντοπιστεί το `composer.json` κάτω από `/crm/`.
   - Άνοιξε την εφαρμογή από **Applications → Manage My Applications**.
   - Επίλεξε **Install Dependencies** και περίμενε επιτυχή ολοκλήρωση. Το Plesk τεκμηριώνει αυτή τη ροή για project που έχει `composer.json`/`composer.lock`. [Plesk: Composer dependencies](https://docs.plesk.com/en-US/obsidian/reseller-guide/website-management/websites-and-domains/web-applications.68973/)
   - Έλεγξε το αποτέλεσμα/log για platform errors και επιβεβαίωσε ότι το Composer χρησιμοποίησε PHP 8.3.36 r13. Μην επιλέξεις **Update Dependencies**: η εγκατάσταση πρέπει να ακολουθεί το committed `composer.lock`.
   - Αν η Plesk UI προσφέρει ασφαλή Composer command execution, τρέξε στο project root `/crm/`:

     ```bash
     composer check-platform-reqs --no-dev
     ```

   Το Plesk Composer **Install Dependencies** εκτελεί Composer install και ελέγχει platform requirements κατά την εγκατάσταση. Αν η UI δεν προσφέρει αυθαίρετες Composer εντολές, κράτησε το επιτυχές install output ως ισοδύναμο platform check. Για την ακριβή `check-platform-reqs` εντολή χωρίς SSH, ζήτησε από Otenet να την εκτελέσει σε normal (όχι chrooted) Composer environment με PHP 8.3.36 r13.

   Μην ανεβάσεις `vendor/` από διαφορετικό PHP platform χωρίς platform check.

Το app αναμένει το `vendor/autoload.php` κάτω από το `/crm/`. Επομένως το `vendor/` βρίσκεται φυσικά κάτω από το public tree και πρέπει να αποκλειστεί από HTTP με πραγματικούς κανόνες του ενεργού Apache/nginx setup. Αν το hosting δεν μπορεί να το εγγυηθεί, σταμάτησε πριν από το login και προχώρησε σε αλλαγή κώδικα/διάταξης ώστε τα dependencies να φορτώνονται από ιδιωτικό path.

### 6. Δημιούργησε και επαλήθευσε το ιδιωτικό αρχείο ρυθμίσεων

Το production config είναι ξεχωριστό αρχείο PHP, σε ιδιωτικό κατάλογο έξω από το document root, το `/crm/` και το repository. Μην το βάλεις στο WordPress `wp-config.php`, σε `.env` κάτω από το public tree, ή σε φάκελο που σερβίρεται από HTTP. Για την τρέχουσα δοκιμή, ο χρήστης το τοποθέτησε σε ιδιωτικό sibling φάκελο έξω από το document root, σύμφωνα με την προεπιλεγμένη διαδρομή του loader. Η απόλυτη διαδρομή και τα runtime UID/GID δεν καταγράφηκαν.

Το αρχείο επιστρέφει τις παρακάτω ρυθμίσεις ως PHP array και δεν κάνει `echo`, `print_r`, `var_dump` ή logging τιμών:

```php
<?php
return [
    'APP_BASE_PATH' => '/crm',
    'APP_TIMEZONE' => 'Europe/Athens',
    'DB_HOST' => '<database hostname του Plesk>',
    'DB_NAME' => 'crm_preprod',
    'DB_USER' => '<pre-prod database user>',
    'DB_PASSWORD' => '<secret>',
    'CRM_SECURITY_HMAC_KEY' => '<ιδιωτικό τυχαίο κλειδί, τουλάχιστον 32 χαρακτήρες>',
];
```

Περιέλαβε τις προαιρετικές ρυθμίσεις `CRM_LOGIN_IDENTITY_LIMIT`, `CRM_LOGIN_SOURCE_LIMIT`, `CRM_LOGIN_WINDOW_SECONDS`, `CRM_LOGIN_COOLDOWN_SECONDS` και `CRM_SECURITY_RETENTION_DAYS` μόνο αν χρειάζεται να αλλάξουν από τις τεκμηριωμένες προεπιλογές. Ο loader ελέγχει τύπο και εγκυρότητα τιμών, ιδίως ότι το `CRM_SECURITY_HMAC_KEY` έχει τουλάχιστον 32 χαρακτήρες, και αποτυγχάνει με ασφαλές γενικό σφάλμα όταν απαιτούμενες τιμές λείπουν ή είναι άκυρες.

Δημιούργησε το ιδιωτικό αρχείο με δικαιώματα περιορισμένα από την αρχή. Ο ιδιοκτήτης και τα permissions πρέπει να επιτρέπουν ανάγνωση από τον λογαριασμό PHP και CLI, αλλά όχι από άλλους λογαριασμούς ή HTTP. Μην υποθέσεις ότι `0600` ή `0640` είναι σωστό πριν επιβεβαιώσεις ποιος λογαριασμός εκτελεί την PHP και ποια δυνατότητα αλλαγής δικαιωμάτων παρέχει το Plesk. Ποτέ μην το κάνεις world-readable. Αν αλλάξεις το αρχείο, χρησιμοποίησε ασφαλή αντικατάσταση ώστε να μην μείνει προσωρινά μερικώς γραμμένο.

Για να παραχθεί τυχαίο κλειδί, εκτέλεσε τοπικά:

```bash
openssl rand -hex 32
```

Αντέγραψε την τιμή απευθείας στο ιδιωτικό αρχείο χωρίς να τη στείλεις στη συνομιλία ή να τη βάλεις σε Git, URL, δημόσιο PHP source ή command arguments. Χρησιμοποίησε μόνο τα credentials της ξεχωριστής pre-prod βάσης CRM· μην αντιγράψεις credentials του WordPress.

Ο κοινός loader `includes/config.php` χρησιμοποιείται από DB, session, security settings και CLI scheduler. Στη δοκιμή Plesk της 19ης Νοεμβρίου, το web login και το CLI migration ολοκληρώθηκαν επιτυχώς με το private config· ο loader αποδέχθηκε τη θέση και τα permissions.
Η προτεραιότητα επιλογής είναι PHP ini `crm.config_file`, environment `CRM_CONFIG_FILE`, και έπειτα το συμβατικό εξωτερικό `<parent του app parent>/private/accountant-crm.php`.
Στη συνήθη εγκατάσταση `<document-root>/crm` η προεπιλογή αντιστοιχεί σε `<parent του document-root>/private/accountant-crm.php`.
Το συγκεκριμένο production path δεν έχει ακόμη επιβεβαιωθεί.

Αφού επιβεβαιωθεί η πραγματική ιδιωτική διαδρομή, μπορεί να οριστεί η μη μυστική ini τιμή και για web και CLI:

```ini
crm.config_file = "/confirmed/private/accountant-crm.php"
```

Στο CLI η ίδια μη μυστική επιλογή μπορεί να περάσει με `php -d crm.config_file=/confirmed/private/accountant-crm.php <script>`.
Το custom ini διαβάζεται με `get_cfg_var()`· επιβεβαίωσε ότι ο πραγματικός FastCGI handler και η CLI διαβάζουν την ίδια τιμή.
Μην περάσεις credentials ή HMAC μέσα σε command arguments.
Αν το hosting δεν επιτρέπει αυτή την ini ρύθμιση, επιβεβαίωσε ότι η συμβατική εξωτερική διαδρομή είναι διαθέσιμη και ιδιωτική ή ότι το `CRM_CONFIG_FILE` δίνεται με ασφάλεια και στα δύο runtimes.

Το επιλεγμένο αρχείο είναι αποκλειστική πηγή ρυθμίσεων και δεν συγχωνεύεται με environment DB/HMAC.
Απουσία ή λάθος ρητά επιλεγμένου αρχείου, άκυρες τιμές, απαγορευμένο path/permissions, output ή PHP load error δίνουν γενικό HTTP 503 ή CLI exit 78.
Ο loader απορρίπτει world-access και group-write στο αρχείο και επιτρέπει μόνο έγκυρο 0600/0640 που διαβάζουν οι αναγκαίοι λογαριασμοί.
Το `APP_ENV` δεν επιτρέπεται μέσα στο αρχείο· η παραγωγή αφήνει το process mode unset ή `production`, ώστε να μη γίνουν διαθέσιμα τα test clock/fault overrides.
Μόνο ρητό process `APP_ENV=test` ή `development` επιτρέπει environment-only λειτουργία όταν δεν υπάρχει επιλεγμένο/default ιδιωτικό αρχείο.
Το υπάρχον `.env.example` αφορά μόνο το τοπικό Docker setup και δεν φορτώνεται αυτόματα.
Αυτή η τοπική υποστήριξη δεν υποκαθιστά το πραγματικό pre-prod AT-56.

Για την τρέχουσα δοκιμή, τοποθέτηση έξω από document root/repository, owner-only permissions και ανάγνωση από web/CLI επιβεβαιώθηκαν από τον loader και την επιτυχή σύνδεση/login και migration. Το CLI migration και το web login χρησιμοποίησαν την ίδια νέα pre-production βάση και HMAC ρύθμιση. Δεν υπάρχει URL προς το config επειδή βρίσκεται έξω από το document root. Τα εσωτερικά URLs που ελέγχθηκαν επέστρεψαν `403`. Η διαδρομή του φυσικού αρχείου δεν δοκιμάζεται με URL που χαρτογραφείται σε άλλο αρχείο μέσα στο document root.

Πριν από παραγωγική χρήση, επιβεβαίωσε επιπλέον ότι αποτυχία φόρτωσης δίνει ασφαλές γενικό σφάλμα χωρίς credentials, key ή filesystem path. Μην αφήσεις δημόσιο diagnostic script· οι έλεγχοι γίνονται με CLI ή σε προσωρινό προστατευμένο έλεγχο που αφαιρείται αμέσως.

Συμπερίλαβε το ιδιωτικό αρχείο στη διαδικασία backup/restore μόνο σε κρυπτογραφημένο αποθηκευτικό χώρο με ελεγχόμενη πρόσβαση. Έλεγξε ότι τα backups δεν σερβίρονται από το site, δεν μπαίνουν σε Git και ότι η επαναφορά αποκαθιστά τα περιορισμένα filesystem permissions. Φύλαξε το κλειδί κρυπτογράφησης backup ξεχωριστά από το backup. Μην αντιγράψεις το config σε email, ticket ή μη κρυπτογραφημένο backup.

Στο PHP configuration του domain:

- `display_errors=Off`
- `log_errors=On`
- `error_log` σε ιδιωτικό path έξω από το document root, με permissions μόνο για τον hosting user.

Το scheduler υπολογίζει recurrence dates σε `Europe/Athens`. Η διαμόρφωση Plesk task πρέπει επίσης να έχει επιβεβαιωμένη ζώνη ώρας. Τα credentials δεν εμφανίζονται σε output ή logs.

### 7. Εφάρμοσε migrations και δημιούργησε λογαριασμό

1. Πριν από το migration, επιβεβαίωσε ότι το web και το CLI config δείχνουν στη `crm_preprod`.
2. Εκτέλεσε migration runner από CLI και όχι με browser:

   ```bash
   php /<absolute-document-root>/crm/bin/migrate.php
   ```

   Χρησιμοποίησε την επιβεβαιωμένη PHP 8.3 CLI αν το `php` δείχνει άλλη έκδοση.

3. Συνέχισε μόνο με exit code `0`. Έλεγξε στον `schema_migrations` τις αναμενόμενες εκδόσεις/checksums και δειγματοληπτικά τα νέα tables, columns, indexes και foreign keys.
4. Σε αποτυχία, κράτησε τα logs, μην ενεργοποιήσεις το app και μην επαναλάβεις τη migration πριν ελεγχθεί η μερικώς εφαρμοσμένη DDL. Τα MariaDB DDL statements δεν αναιρούνται όλα από transaction rollback.
5. Με interactive SSH terminal, δημιούργησε admin από CLI:

   ```bash
   php /<absolute-document-root>/crm/create_user.php
   ```

   Το script ζητά username, όνομα, ρόλο και κωδικό με κρυφή εισαγωγή. Μην το ανοίξεις ως web page.
6. Αν δεν υπάρχει διαδραστικό terminal, μην αλλάξεις το `create_user.php` σε web installer. Χρησιμοποίησε μόνο προσωρινή, ιδιωτική CLI διαδικασία που έχει ελεγχθεί, διαβάζει hash από ιδιωτικό αρχείο και αφαιρείται αμέσως μετά.
7. Παράδωσε credentials μέσω password manager ή άλλου ασφαλούς διαύλου.

### 8. Έλεγξε πρόσβαση HTTP πριν από χρήση

Πριν επιτρέψεις login, έλεγξε με browser και πραγματικά HTTP requests το `https://elmico.gr/crm/`.

Οι δημόσιες διαδρομές πρέπει να είναι μόνο οι απαιτούμενες PHP σελίδες και assets. Τα ακόλουθα δεν πρέπει να επιστρέφουν περιεχόμενο (`403` ή `404` είναι αναμενόμενα):

- `/crm/includes/db.php`
- `/crm/vendor/autoload.php`
- `/crm/bin/migrate.php`
- `/crm/bin/run-recurring-tasks.php`
- `/crm/migrations/20261118_001_catalogs.php`
- `/crm/composer.json`
- `/crm/composer.lock`
- `/crm/create_user.php`

Παράδειγμα ελέγχου χωρίς εμφάνιση response body:

```bash
curl -sS -o /dev/null -w '%{http_code}\n' https://elmico.gr/crm/includes/db.php
curl -sS -o /dev/null -w '%{http_code}\n' https://elmico.gr/crm/vendor/autoload.php
curl -sS -o /dev/null -w '%{http_code}\n' https://elmico.gr/crm/bin/migrate.php
curl -sS -o /dev/null -w '%{http_code}\n' https://elmico.gr/crm/composer.lock
curl -sS -o /dev/null -w '%{http_code}\n' https://elmico.gr/crm/create_user.php
```

Αν κάποια απαγορευμένη διαδρομή επιστρέψει `200`, περιεχόμενο αρχείου, PHP source ή directory listing, σταμάτησε και ρύθμισε το Plesk web stack πριν συνεχίσεις. Η ύπαρξη `.htaccess` μόνη της δεν είναι απόδειξη προστασίας.

Έλεγξε επίσης:

- Το HTTP ανακατευθύνεται στο HTTPS και το πιστοποιητικό είναι έγκυρο.
- Το login εμφανίζεται χωρίς PHP warnings/deprecations ή SQL/path details.
- Μη συνδεδεμένος χρήστης δεν βλέπει CRM δεδομένα και οι έλεγχοι ρόλων λειτουργούν.
- Τα session cookies έχουν `Secure`, `HttpOnly`, `SameSite=Lax` και path `/crm/`.
- Το static site εξακολουθεί να λειτουργεί και deployment του δεν σβήνει ή αντικαθιστά το `/crm/`.

### 9. Έλεγξε βασικές λειτουργίες με πλασματικά δεδομένα

Κράτησε την πρόσβαση περιορισμένη κατά τη διάρκεια των δοκιμών. Χρησιμοποίησε μόνο ψεύτικα ονόματα/ΑΦΜ και δοκιμαστικούς λογαριασμούς.

1. Login/logout, αλλαγή session ID, session expiry και πρόσβαση χωρίς login.
2. Admin και user permissions, CSRF προστασία και άρνηση μη εξουσιοδοτημένων αλλαγών.
3. Πελάτες, συνεργάτες, ενεργοί επαγγελματίες, εργασίες, ημερολόγιο και MyDiary.
4. Κατάλογοι τίτλων και μέσων είσπραξης, snapshot παλιάς εργασίας και εμφάνιση στις σχετικές λίστες.
5. Import με έγκυρο και άκυρο αρχείο, χωρίς μερική αποθήκευση σε αποτυχία.
6. Excel/PDF exports, ελληνικά κείμενα, ποσά και φίλτρα.
7. Δημιουργία recurring rule, σύντομος μήνας, catch-up, αλλαγή όρων, manual stop/restart.
8. Επιβεβαίωση ότι δεν εισάγονται/κληρονομούνται παλιές εισπράξεις σε generated tasks.
9. Links, redirects, CSS, imports, exports και cookies λειτουργούν κάτω από `/crm/`.

Μην καθαρίσεις τα δοκιμαστικά records αυτόματα. Πριν από οποιαδήποτε διαγραφή, κράτησε backup, εντόπισε ακριβώς τα fictional test records και έλεγξε foreign key relationships.

### 10. Ρύθμισε και επαλήθευσε Plesk Scheduled Tasks

Το recurring scheduler παραμένει ανενεργό μέχρι να περάσουν τα παραπάνω checks και να επιβεβαιωθεί η CLI σύνδεση στην pre-prod βάση.

1. Επιβεβαίωσε την ακριβή PHP 8.3 CLI executable και την πρόσβαση στο app path. Σε Linux Plesk Scheduled Tasks μπορεί να τρέχουν σε chroot· χρησιμοποίησε paths που λειτουργούν από το task context.
2. Επιβεβαίωσε ότι το task παίρνει την ίδια `DB_*` και `CRM_SECURITY_HMAC_KEY` ρύθμιση με το web app. Μην τοποθετείς secrets στη γραμμή εντολής.
3. Βεβαιώσου ότι το migration runner έχει ήδη περάσει επιτυχώς. Το recurring script καλεί επίσης migration runner, οπότε κάθε task χρειάζεται δικαιώματα schema migration· επιβεβαίωσε αυτά τα δικαιώματα και μην αλλάξεις DB grants χωρίς απόφαση.
4. Εκτέλεσε dry-run από CLI:

   ```bash
   php /<absolute-document-root>/crm/bin/run-recurring-tasks.php --dry-run
   ```

5. Δημιούργησε μέσω UI έναν αναγνωρίσιμο fictional κανόνα με επιλέξιμη περίοδο. Κάνε ελεγχόμενο πραγματικό run, επιβεβαίωσε μία εργασία και ξανατρέξε το script για να αποδείξεις idempotency.
6. Στο Plesk πρόσθεσε task με **Run a PHP script** ή **Run a command**, καθημερινά στις `03:15 Europe/Athens`, μετά την επιβεβαίωση της timezone του server. Χρησιμοποίησε το πλήρες path του `bin/run-recurring-tasks.php` και το PHP 8.3 CLI.
7. Πάτησε **Run Now**, έλεγξε αποτέλεσμα/exit status και ρύθμισε ειδοποίηση αποτυχίας.
8. Πρόσθεσε ξεχωριστό καθημερινό task για cleanup:

   ```bash
   php /<absolute-document-root>/crm/bin/prune-security-events.php
   ```

9. Επιβεβαίωσε την επόμενη αυτόματη εκτέλεση από τα Plesk logs. Στην pre-prod εγκατάσταση διατήρησε τα δεδομένα fictional scheduler test μέχρι να ολοκληρωθεί το αποδεικτικό idempotency.

Το `--dry-run` δεν δημιουργεί recurring task records, αλλά το script εκκινεί migration runner. Πέρασε migrations ρητά πριν από το dry-run ώστε το schema να είναι ήδη ενημερωμένο.

### 11. Backup, δοκιμή επαναφοράς και rollback

1. Επιβεβαίωσε στο Plesk ότι το backup περιλαμβάνει το `/crm/`, τη νέα βάση `crm_preprod` και την ιδιωτική ρύθμιση. Κατέγραψε retention, διαθέσιμο χώρο και αν το backup υπάρχει και εκτός του ίδιου server.
2. Πάρε backup αφού έχουν ολοκληρωθεί migrations και βασικές δοκιμές.
3. Κάνε test restore σε ξεχωριστή προσωρινή βάση και θέση αρχείων. Κράτησε απενεργοποιημένα όλα τα scheduler tasks της restored copy.
4. Επαλήθευσε σύνδεση, λογαριασμούς, πλήθος βασικών εγγραφών, επιλεγμένα ποσά, payment snapshots και recurrence versions.
5. Βεβαιώσου ότι η restored copy δεν μπορεί να συνδεθεί στην αρχική pre-prod ή σε οποιαδήποτε production βάση.
6. Σε αποτυχία release, απενεργοποίησε πρώτα recurring και cleanup tasks και περιόρισε τις μεταβολές χρηστών. Διατήρησε αντίγραφα αρχείων/βάσης και logs πριν από rollback.
7. Επανέφερε προηγούμενο κώδικα μόνο αφού ελεγχθεί η συμβατότητά του με το υπάρχον schema. Μην επαναφέρεις παλιό dump πάνω σε νεότερες εγγραφές χωρίς αξιολόγηση και έγκριση.
8. Μετά το rollback, επαλήθευσε HTTPS, login, βασικές εγγραφές και dry-run πριν επανενεργοποιήσεις tasks.

Η καθημερινή λήψη backup δεν εγγυάται μηδενική απώλεια αλλαγών από την τελευταία επιτυχημένη λήψη.

## Συμβίωση με το static site

- Το CRM εγκαθίσταται μόνο στο child path `/crm/` του ενεργού `Home/elmico.gr` document root.
- Το static site συνεχίζει να δημοσιεύεται στο υπάρχον root. Κάθε static deployment πρέπει να αντιγράφει μόνο τα δικά του build artifacts και να διατηρεί `/crm/`, `.well-known` και τις ρυθμίσεις του hosting.
- Δεν προστίθεται άδειο `/crm/` στο Astro source/`public/`, δεν γίνεται import του PHP app στο Astro build και το static build/deploy δεν εκτελεί migrations.
- Parent `.htaccess`, Plesk nginx directives, root redirects, permissions, DNS, HTTPS και cache rules εξετάζονται πριν αλλάξουν. Οι αλλαγές στο CRM δεν πρέπει να ανακατευθύνουν ή να αποκλείουν το δημόσιο site.
- Η παλιά WordPress βάση και τα υπάρχοντα backups της δεν τροποποιούνται ως μέρος αυτού του deployment.

## Κριτήρια ολοκλήρωσης pre-prod

- [ ] Επιβεβαιώθηκε το ακριβές commit και το checksum του release archive.
- [ ] Ενεργό URL είναι `https://elmico.gr/crm/` και το δημόσιο static site συνεχίζει να λειτουργεί.
- [ ] PHP 8.3 web/CLI, MariaDB και Composer platform requirements επαληθεύτηκαν στο hosting.
- [x] Το CRM config βρίσκεται έξω από document root/repository, τα owner-only permissions έγιναν αποδεκτά από τον loader, και web/CLI το διαβάζουν. Το config είναι εκτός document root· εσωτερικά paths που δοκιμάστηκαν δεν εκθέτουν περιεχόμενο.
- [x] Web και CLI χρησιμοποίησαν το ίδιο private config και συνδέθηκαν στη νέα pre-prod βάση. Ο CLI loader επικύρωσε το HMAC key και το web login το χρησιμοποίησε επιτυχώς, χωρίς εμφάνιση credentials.
- [x] Οι migrations ολοκληρώθηκαν: `20261118_001_catalogs`, `20261118_002_recurring_tasks`, `20261118_003_security_events`, `20261119_001_users_utf8mb4`.
- [x] Πραγματικά HTTP checks επέστρεψαν `403` για `includes/db.php`, `vendor/autoload.php`, `bin/migrate.php`, `bin/run-recurring-tasks.php`, migration PHP, Composer metadata και `create_user.php`.
- [ ] Ελέγχθηκαν migration checksums στον `schema_migrations`.
- [ ] Login, roles, CSRF, βασικές CRM ροές, imports/exports και ελληνικά δεδομένα δοκιμάστηκαν με fictional fixtures.
- [ ] Recurrence dry-run, πραγματικό run, επαναληπτικό run και Plesk **Run Now** επιβεβαίωσαν idempotency.
- [ ] Security cleanup task εκτελέστηκε και έχει αναφορά σφάλματος.
- [ ] Έγινε backup της βάσης/αρχείων/config και δοκιμή restore σε απομονωμένο προορισμό.
- [x] Ο χρήστης επιβεβαίωσε ότι αφαιρέθηκαν τα προσωρινά diagnostic scripts και Scheduled Tasks.
- [ ] Επιβεβαιώθηκε ότι δεν υπάρχουν δοκιμαστικοί λογαριασμοί με κοινά/γνωστά passwords.
- [ ] Καταγράφηκαν ημερομηνία, commit, PHP/MariaDB versions, αποτελέσματα, logs και εκκρεμότητες.

Η pre-prod ολοκλήρωση δεν ισοδυναμεί με παραγωγικό sign-off. Η παραγωγή προϋποθέτει τα G01/W09 acceptance checks, ανεξάρτητη QA και επιτυχημένο hosting restore.

## Ρυθμίσεις ασφαλείας W04

Πριν ενεργοποιηθεί login, πρέπει να έχει εφαρμοστεί η migration `20261118_003_security_events` μέσω CLI και να υπάρχει σταθερό ιδιωτικό `CRM_SECURITY_HMAC_KEY` τουλάχιστον 32 χαρακτήρων, κοινό για web και CLI. Αν λείπει ή δεν είναι έγκυρο, το login αποτυγχάνει.

Προεπιλογές περιορισμού σύνδεσης:

- `CRM_LOGIN_IDENTITY_LIMIT=5`
- `CRM_LOGIN_SOURCE_LIMIT=30`
- `CRM_LOGIN_WINDOW_SECONDS=900`
- `CRM_LOGIN_COOLDOWN_SECONDS=900`
- `CRM_SECURITY_RETENTION_DAYS=30`

Οι αριθμητικές ρυθμίσεις, αν οριστούν, πρέπει να είναι θετικοί ακέραιοι. Η προέλευση υπολογίζεται από `REMOTE_ADDR`· forwarded headers δεν θεωρούνται έμπιστα χωρίς τεκμηριωμένη proxy λίστα. Security events και counters βρίσκονται στη MariaDB και χρησιμοποιούν HMAC identifiers, όχι plaintext username/IP.

Σε rollback κράτησε τους additive security tables και έλεγξε συμβατότητα πριν από αλλαγή κώδικα. Μην επαναφέρεις παλιό dump πάνω σε νεότερες εγγραφές.
