# Accountant CRM

## Run with Docker

From the project root, create a local environment file and start the app:

```bash
cp .env.example .env
docker compose up --build
```

Then open <http://localhost:8080>. MariaDB imports `accountant_crm.sql` on its first
startup, and the app connects to it using the values in `.env`. Change the example
passwords in `.env` before using this setup beyond local development.

To stop the containers, press `Ctrl+C` and run `docker compose down`. To also
delete the database volume and start with an empty database next time, run
`docker compose down -v` (this permanently deletes the local database data).

The included SQL file contains the schema but no login users. Create your own
administrator through the local command line:

```bash
docker compose exec -it app php create_user.php
```

The script prompts for the account details and password, hides password input,
and is restricted to CLI use. It accepts `admin` or `user` as the role; choose
`admin` for your own account. Do not expose this command through a web page.

Το **Accountant CRM** είναι μια δωρεάν web εφαρμογή CRM, σχεδιασμένη ειδικά για λογιστικά – φοροτεχνικά γραφεία.  
Στόχος της είναι η οργάνωση εργασιών, πελατών, επαγγελματιών και ραντεβού, με έμφαση στην απλότητα και την καθημερινή χρήση.

Η εφαρμογή διατίθεται **δωρεάν σε συναδέλφους** για προσωπική ή επαγγελματική χρήση.

---

## 🚀 Χαρακτηριστικά

- Διαχείριση Εργασιών (CRM)
- Διαχείριση Πελατών
- Διαχείριση Ενεργών / Ανενεργών Επαγγελματιών
- MyDiary – Προσωπικό ημερολόγιο ανά χρήστη
- Ραντεβού & σημειώσεις ημέρας
- Οικονομική παρακολούθηση (αμοιβές – εισπράξεις – υπόλοιπα)
- Εξαγωγές σε Excel & PDF
- Responsive σχεδίαση (Desktop & Mobile)
- Ασφαλές Login με hashed passwords

---

## 🛠️ Τεχνολογίες

- PHP 8.2+
- MySQL / MariaDB
- HTML / CSS / JavaScript
- Bootstrap 5
- Composer (προαιρετικά – για Excel exports)
- PhpSpreadsheet

---

## 📁 Δομή Project (ενδεικτικά)

/
├── includes/
│ ├── db.sample.php
│ ├── auth.php
│ └── functions.php
├── ajax/
│ └── search_clients.php
├── database/
│ └── accountant_crm.sql
├── exports/
├── imports/
├── vendor/
├── login.php
├── dashboard.php
├── tasks.php
├── mydiary.php
└── README.md

yaml
Αντιγραφή κώδικα

---

## 📦 Εγκατάσταση

### 🔹 Βήμα 1 – Κατέβασμα Κώδικα

```bash
git clone git@github.com:simosapergis/accountant-crm.git
ή κατεβάστε το ZIP από το GitHub και αποσυμπιέστε το.

🔹 Βήμα 2 – Βάση Δεδομένων
Δημιουργήστε νέα βάση MySQL (π.χ. accountant_crm)

Εισάγετε το αρχείο:

pgsql
Αντιγραφή κώδικα
accountant_crm.sql
ℹ️ Το αρχείο περιλαμβάνει μόνο τη δομή, χωρίς δεδομένα.

🔹 Βήμα 3 – Ρύθμιση Σύνδεσης Βάσης
Αντιγράψτε το αρχείο:

bash
Αντιγραφή κώδικα
includes/db.sample.php
Μετονομάστε το σε:

bash
Αντιγραφή κώδικα
includes/db.php
Συμπληρώστε τα στοιχεία σύνδεσης:

php
Αντιγραφή κώδικα
<?php
$mysqli = new mysqli(
    "localhost",
    "db_user",
    "db_password",
    "db_name"
);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8mb4");
🔹 Βήμα 4 – Composer (προαιρετικό)
Απαραίτητο μόνο αν χρησιμοποιείτε εξαγωγές Excel:

bash
Αντιγραφή κώδικα
composer install
Αν δεν χρειάζεστε Excel exports, μπορείτε να παραλείψετε αυτό το βήμα.

🔹 Βήμα 5 – Δημιουργία Πρώτου Χρήστη
Με Docker, δημιουργήστε τον δικό σας χρήστη από το terminal:

```bash
docker compose exec -it app php create_user.php
```
🔹 Βήμα 6 – Πρόσβαση στην Εφαρμογή
Ανοίξτε στον browser:

pgsql
Αντιγραφή κώδικα
http://your-domain/login.php
Συνδεθείτε με τον χρήστη που δημιουργήσατε.

📅 MyDiary
Το MyDiary είναι προσωπικό ημερολόγιο ανά χρήστη και περιλαμβάνει:

Προβολή εργασιών CRM ανά ημέρα

Προσωπικά ραντεβού

Σημειώσεις ημέρας

Μηνιαία προβολή (calendar ή list view)

🔐 Ασφάλεια
Password hashing (password_hash)

Sessions

SQL prepared statements

Έλεγχος πρόσβασης χρηστών

📄 Άδεια Χρήσης
MIT License

Η εφαρμογή διατίθεται δωρεάν, χωρίς καμία εγγύηση.
Μπορείτε να τη χρησιμοποιήσετε, να τη τροποποιήσετε και να τη διανείμετε ελεύθερα.





---


