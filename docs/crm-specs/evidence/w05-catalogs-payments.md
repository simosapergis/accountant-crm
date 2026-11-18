# W05 catalogs and payment snapshots

Status: local implementation and focused acceptance coverage complete; visual/mobile review and final package sign-off remain open.

## Scope delivered

- Versioned additive migration creates the payment method and task title catalogs and adds nullable payment method ID/label snapshot columns to existing tasks.
- Admin-only, CSRF-protected catalog pages support create, rename, soft-deactivate and reactivate. Names are trimmed and unique without regard to case. No catalog delete action is provided.
- Task title suggestions use an editable text field; tasks retain their own title string after catalog changes.
- Positive collection requires an active payment method. The server reads the label from the catalog and stores it with the ID. Existing ID/label snapshots survive unrelated edits, including catalog rename/deactivation. Zero collection clears both.
- The payment label appears in desktop/mobile task listings, task form, calendar day view, partner task views and task/partner-analysis exports. Existing fee/collection totals are unchanged.

## Evidence

- RED: initial `W05CatalogsTest::testAdminCanOpenBothCatalogManagementScreens` failed with HTTP 404 for both catalog routes.
- RED: `W05TaskPaymentsTest` initially failed because title options/payment fields did not exist and positive collection was accepted without method validation.
- GREEN: isolated full PHPUnit suite, 130 tests / 577 assertions. It includes catalog role/duplicate/reactivation/soft-deactivation cases, task label snapshot and zero-clearing cases, inactive and legacy payment behavior, title length/options and preservation of an old task title after a catalog rename, over-collection, list/export rendering, and existing W02 regressions.
- Focused command:

  ```sh
  docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 exec -T app sh -lc 'php /opt/crm-tests/bin/assert-test-db.php && php /var/www/html/crm/bin/migrate.php && php /opt/crm-tests/bin/seed-w01-fixtures.php && php /opt/crm-tests/vendor/bin/phpunit -c /opt/crm-tests/phpunit.xml'
  ```

  The guard reported `test-db / crm_w01_test`; migration runner reported schema up to date. All fixtures were fictional.

## Acceptance mapping

- AT-19: catalog authorization, names, duplicate rejection, deactivation and reactivation — `W05CatalogsTest`.
- AT-20, AT-22 to AT-25, AT-27, AT-28: payment snapshot lifecycle, old rows, server validation, exact amount handling and over-collection — `W05TaskPaymentsTest` plus existing money regressions.
- AT-21: active title options and server-side 255-character limit — `W05TaskPaymentsTest` and catalog tests.
- AT-26: task list, mobile card, task form and individual task/partner exports — `W05TaskPaymentsTest`.

## Still open

- Manual keyboard and browser review of catalog/title selection, small-screen layouts and the full AT-49 width matrix is deferred to W09.
- G01, W02 remainder, W03 migration-failure/install criteria, W08 dependency/export criteria and final package sign-off remain open by the user's sequencing instruction.
