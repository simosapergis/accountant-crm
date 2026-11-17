# W02 evidence: private diary ownership

Date: 17 November 2026.
Code baseline commit: `9885c58` with local W02 changes in the working tree.
Requirements: ROL-03; acceptance: AT-04 (partial).

## RED observed

After adding isolated MariaDB fixtures for a second fictional user, diary event and note, the targeted test additions produced 47 tests with 2 failures:

- Updating another user's event returned `200 OK` even though the database row remained unchanged.
- Deleting another user's event returned a redirect (`302`) instead of rejecting the request.

The read test already confirmed that another user's event and note were not rendered by the current diary page.

## Implemented behavior

- `update_event.php` verifies `(event id, session user id)` ownership before update and returns `404` for an event owned by another user.
- Diary deletion reports `404` when the owner-scoped delete finds no matching row.
- Existing diary list and note queries continue to scope records to the session user.

## GREEN result

`docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 build app && docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 up -d app && docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm tests` — PASS, 47 tests / 126 assertions. The suite verified private event/note read filtering, foreign event update/delete rejection, CSRF behavior, and existing W01 access/session regressions. Fixtures are fake and guarded to `test-db / crm_w01_test`.

## Remaining scope

AT-04 remains partial pending review of every personal diary read/write path and broader authorization tests. The broader SEC-03 role checks and other W02 security acceptance criteria are still open; G01 remains `NOT_EVALUATED`.
