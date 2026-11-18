# W02 evidence: CSRF and state-changing methods

Date: 17 November 2026.
Code baseline commit: `9885c58` (`Add CRM security test harness and W02 auth foundation`).
Requirements: SEC-04, SEC-11 and SEC-12; acceptance: AT-05 (partial), AT-13 browser regression.

## RED observed

With the W02 HTTP integration tests added and before the CSRF implementation, the isolated test command reported 38 tests and 3 failures:

- A client update without a token returned `302` instead of `403`.
- The authenticated client form had no CSRF token field.
- GET `/client_form.php?delete=910098` returned `302` instead of a method rejection, and deleted its fictional fixture.

The suite used only the `test-db / crm_w01_test` database. The test restores/removes its fictional records in guarded setup/teardown.

## Implemented behavior

- Session-bound tokens are generated and rendered as hidden `_csrf_token` fields. Login rotates both the session ID and token.
- Every POST to an authenticated route is validated centrally by `includes/auth.php`; login and logout validate separately.
- Logout accepts POST only, destroys the server-side session, expires its cookie with the same path/security attributes, and redirects under the configured base path.
- GET deletion requests return `405`. Existing deletion controls now submit CSRF-protected POST forms to their actual handlers, including tasks, clients, partners, active professionals, and diary events.
- AJAX form posts and the active-professional import include the token; dedicated AJAX mutation endpoints require POST.

## GREEN and regression results

`docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 build app && docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 up -d app && docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm tests` — PASS, 44 tests / 113 assertions. Coverage includes missing, invalid and cross-session CSRF tokens; a valid client update; login CSRF rejection; GET deletion rejection for clients/tasks; valid POST task deletion; and logout invalidation.

`CRM_BASE_URL=https://127.0.0.1:18443/crm npm --prefix tests/e2e --cache /tmp/crm-w01-npm-cache test` — PASS. Login rotates the session ID, logout ends it, the old session cannot reopen `/tasks.php`, and cookie Secure/HttpOnly/SameSite/path checks pass. Host Chrome required execution outside the sandbox due to its socket restriction.

`docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm --entrypoint sh tests -lc 'find /var/www/html/crm -name "*.php" -print0 | xargs -0 -n1 php -l'` — PASS; PHP syntax checks completed with exit code 0.

## Limits and next work

AT-05 remains partial: remaining mutation paths and deletion flows need route-by-route coverage and manual UI review. SEC-04 is not declared complete. Other W02 work remains, including authorization/role checks, XSS-safe output across all contexts, prepared SQL audit, private diary ownership checks, password policy, login throttling/events, cache controls, and further endpoint/method validation. G01 remains `NOT_EVALUATED`.

## Status update - 2026-11-18

This earlier evidence recorded AT-05 as partial at that checkpoint.
The local W02 baseline is now `DONE_LOCAL`; expanded route and mutation coverage, exact commands and current suite results are recorded in [w02-closeout.md](w02-closeout.md).
G01 remains `NOT_EVALUATED`.
