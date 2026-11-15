# Bug and Error Tracker

Use this file to record issues found during development, their impact, and their eventual resolution.

## Open

### Session is started twice on the home page

- **Status:** Open
- **Observed:** 2026-11-15
- **Page:** Home page (`/` / `index.php`)
- **Error:** `Notice: session_start(): Ignoring session_start() because a session is already active in /var/www/html/includes/header.php on line 3`
- **Cause:** `index.php` loads `includes/auth.php`, which starts the session; it then loads `includes/header.php`, which calls `session_start()` again.
- **Affected files:** `ACCOUNTANT_CRM/includes/auth.php`, `ACCOUNTANT_CRM/includes/header.php`
- **Proposed resolution:** Start the session in one shared authentication/bootstrap location only. Remove session startup from the header, or guard it with `session_status() === PHP_SESSION_NONE`.
- **Verification:** Load the home page while authenticated and confirm the notice is absent.

## Resolved

### Null collaborator name caused an `htmlspecialchars()` deprecation warning

- **Status:** Resolved
- **Observed:** 2026-11-15
- **Page:** Tasks list (`tasks.php`)
- **Error:** `Deprecated: htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in /var/www/html/tasks.php on line 320`
- **Cause:** Tasks without an assigned collaborator have a `NULL` `partner_name`, which was passed directly to `htmlspecialchars()`.
- **Resolution:** Render a blank collaborator cell when `partner_name` is `NULL` using `$row['partner_name'] ?? ''`.
- **Affected file:** `ACCOUNTANT_CRM/tasks.php`
- **Verification:** PHP syntax check passed in the running app container.
