# W02 evidence: selected XSS output contexts

Date: 17 November 2026.
Code baseline commit: `9885c58` with local W02 changes in the working tree.
Requirement: SEC-05; acceptance: AT-06 (partial).

## RED observed

Two new HTTP integration tests produced 49 total tests with 2 failures before the output changes:

- The authenticated header rendered a fictional username containing `<script>window.w02Xss=1</script>` as raw HTML.
- Diary event text containing quote-based attribute content and `</script><script>...` appeared unescaped in an inline JavaScript handler assembled with `addslashes()`.

## Implemented behavior

- Both shared header copies escape the session fullname for HTML text context.
- Diary edit controls place event values in HTML-escaped `data-*` attributes and pass the clicked element to a static handler. No event title or description is interpolated into inline JavaScript.
- The modal now also restores the event date when editing.
- Client autocomplete now creates DOM nodes and assigns client names and AFM values through `textContent` instead of `innerHTML`.

## GREEN result

`docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 build app && docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 up -d app && docker compose --env-file /dev/null -f compose.w01.yaml -p crm-w01-20261117 run --rm tests` — PASS, 49 tests / 137 assertions. The new tests verify the malicious fullname is text and unsafe event strings are absent from executable/attribute contexts.

`CRM_BASE_URL=https://127.0.0.1:18443/crm npm --prefix tests/e2e --cache /tmp/crm-w01-npm-cache test` — PASS: the browser autocomplete check confirms the hostile client fixture creates no `<img>` element or script effect. The attempted pre-fix browser run was interrupted before producing a result, so no browser RED is claimed for autocomplete.

A follow-up fake active-professional fixture also exposed raw code/name values in quoted form attributes. Before the fix, the PHPUnit suite reported 50 tests with 1 expected failure for the raw attribute payload. The code, fullname, start date and end date inputs now escape for HTML attribute context. The latest PHPUnit run passes 50 tests / 144 assertions. The E2E script was not rerun for this server-rendered attribute-only change.

The import follow-up adds an XLSX integration fixture with a duplicate active-professional code containing quotes and an attribute payload. Before the fix, the focused test failed because the raw payload appeared in the response. Import success/error feedback is now HTML-escaped, and parser failures return a generic message instead of exposing exception details or server paths. The focused regression passes 1 test / 5 assertions; the full suite passes 51 tests / 149 assertions against `test-db / crm_w01_test`. Browser E2E was not rerun because this only changes server-rendered feedback.

Source review then found that `save_client_ajax.php` used `addslashes()` to insert a submitted client name into an inline script. A new HTTP test reproduced a `</script><script>...` breakout as RED. The response now uses `json_encode()` with JSON_HEX flags (and invalid UTF-8 substitution) for the JavaScript argument. The first post-fix run exposed a brittle test expectation around JSON's optional escaped slash; after normalizing that representation in the assertion, the full suite passes 52 tests / 155 assertions. The endpoint is exercised through HTTP; browser E2E was not rerun because the UI handoff code is unchanged and the source test asserts script-safe encoding.

## Remaining scope

AT-06 remains partial: broader outputs such as task/client/partner fields, autocomplete responses, export documents and other dynamic JavaScript sinks need their own context-specific tests and review. G01 remains `NOT_EVALUATED`.
