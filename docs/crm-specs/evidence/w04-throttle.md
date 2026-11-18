# W04 local evidence

The HTTP baseline returned the same unsuccessful response after six failures and after 30 different usernames from one source.
Those acceptance assertions failed before implementation.

AT-10 checks five failures for the fictional known account, rejection from a fresh session, and successful authentication after expiry of the stored cooldown and window.
AT-11 checks 30 different fictional usernames from loopback and rejection of the following attempt.
AT-12 checks failure, throttle, success and logout records and excludes the supplied password and session-cookie name from stored metadata.
The response uses the same generic credential error for known and unknown identities; blocked requests return 429.

The implementation serializes login attempts through source and identity row locks in a fixed order, with both counters and security events in the login transaction.
Ten concurrent PHP processes exercise the same identity and source; exactly five failures are counted before the remaining requests are blocked.
Retention testing preserves a current event and removes an event older than 30 days.

Tests use native PHP 8.3.36, real MariaDB 10.11.19 and the guarded fictional database `crm_test_native_20261118`.
The dedicated web server serves this checkout at `http://127.0.0.1:18094/crm/` with a test-only HMAC key and the same database.
No production or hosting configuration was changed.

Full native suite: `php tests/bin/assert-test-db.php && tests/vendor/bin/phpunit -c tests/phpunit.xml` passed with 148 tests and 859 assertions.
The environment includes `APP_ENV=test`, the native socket ini, the guarded test DB identity, `CRM_TEST_ADMIN_USER=root` for W03 fixture creation only, and the dedicated `/crm/` base URL.
W03 schema expectations were extended for the additive W04 tables and migration version.
