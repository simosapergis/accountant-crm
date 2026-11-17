# Repository guidance for coding agents

## Project at a glance

- This is a procedural PHP CRM for an accounting office. PHP pages, shared includes, imports, and exports live in `ACCOUNTANT_CRM/`; the application directory is mounted as the web root by Docker Compose.
- The root contains `compose.yaml`, `Dockerfile`, `accountant_crm.sql`, and project documentation. The app uses MySQLi and MariaDB. Composer dependencies for the app are declared in `ACCOUNTANT_CRM/composer.json`; PhpSpreadsheet is used for Excel exports.
- The documented local setup is `docker compose up --build` after copying `.env.example` to `.env`. The compose file currently uses PHP 8.2+ and MariaDB 10.11. Treat these as the local development environment, not as proof of the production server's exact configuration.
- Read `README.md` for setup, `COMMANDS.md` for local database commands, and `BUGS.md` for known issues. The README includes legacy setup guidance; prefer the Docker setup when working with this repository unless the task concerns the Otenet deployment.
- The current application has no established automated test suite. Do not assume a test command or test framework exists. The project SET agent definition is `.codex/agents/set.toml`.

## Product context and client brief

The detailed client explanation is `accountant-crm-hours-client-explanation.docx`. Treat it as the source for the requested work summarized below. Before implementing a behavior whose details are not specified there, in existing application behavior, or in a written feature spec, ask for or document the product decision rather than inventing one.

The brief covers:

1. **Security:** protect all relevant pages and actions so only authenticated and authorized users can view or change CRM data; secure session handling and prevent unwanted state changes and unsafe output. Audit the application broadly, not only the login page.
2. **Otenet deployment under `/crm/`:** make paths, links, imports, exports, and required programs work at the subdirectory URL without affecting the existing site. Verify the existing features still work in that deployment context.
3. **Monthly recurring tasks:** users can configure which tasks recur, their start date, fee, and end date. Each generated task is a new independent record and must not inherit earlier collections. Handle short months without invalid dates and prevent duplicate generation on repeated scheduler runs.
4. **Payment methods:** provide a user-managed list (examples in the brief are cash, bank, and POS); save the selected method on each task and show it in relevant CRM views and exports.
5. **Task title catalog:** allow users to add, edit, and deactivate reusable task titles. Existing tasks keep the title value they had when created, even if the catalog title is later edited or deactivated.
6. **Production readiness:** test the completed flows with test data; install and verify the application and database on the actual site; configure and verify scheduling for recurring work; and check access, backups, and restore procedures.

These are client-requested requirements, not confirmation that the features are implemented. Inspect the code and schema before reporting status. The brief's time estimates and price are not technical acceptance criteria.

## Specification and TDD workflow

Use a lightweight Spec Driven Development process for new features, cross-cutting security work, schema changes, scheduled jobs, and deployment changes. Small isolated bug fixes can use concise acceptance criteria in the task or PR without a standalone spec.

For substantial work:

1. Read the client brief, relevant current behavior, schema, and related bug notes. Separate confirmed requirements from assumptions and unresolved decisions.
2. Before implementation, add or update `specs/<feature>/spec.md`. Include the user goal, scope and non-goals, actors and authorization, behavior and validation, failure/edge cases, data model and migration expectations, security considerations, operational behavior where relevant, and observable acceptance criteria. Keep the spec proportional to the feature.
3. Resolve decisions that materially affect users or stored data before coding. In particular, recurring tasks need explicit rules for schedule timing, month-end dates, timezone, missed runs, retries/idempotency, and stopping; payment methods need rules for edit/deactivate/delete and historical task records; title catalog behavior must preserve the title on existing tasks.
4. Use TDD against the acceptance criteria: write the smallest meaningful test first, confirm it fails for the expected reason, implement the behavior, then refactor with the suite green. Add regression tests for existing behavior when changing it. Do not encode a known bug as desired behavior.
5. Review the spec against the tests and implementation. Update the spec when an approved requirement changes. Report any acceptance criterion that remains untested or unresolved.

For test strategy or test implementation, use the SET agent in `.codex/agents/set.toml` when custom agents are available. Otherwise follow its test guidance directly.

## Engineering and safety expectations

- Keep changes focused and consistent with the existing PHP/MySQLi application. Avoid broad rewrites or new infrastructure unless the task requires them.
- Treat authentication and authorization as separate checks. Protect every state-changing route, verify record ownership where applicable, use prepared SQL statements, validate request data, and escape output at the rendering boundary.
- Database changes must be reproducible and safe for existing data. Include migration/upgrade and rollback considerations; do not rely only on editing the initial-import SQL file for a database that may already exist.
- Never use production or personal data in tests. Use a dedicated disposable test database/container, and verify its target before any destructive reset or fixture cleanup.
- Do not run destructive database commands, deploy to Otenet, modify production configuration, or change backups without explicit task authorization. Prepare and review the required deployment steps first.
- Preserve unrelated user changes. Inspect `git status` before editing or cleanup; do not revert, overwrite, or delete files that were already changed by the user.
- For feature, bug-fix, or test work covered by the TDD workflow above, add and run the relevant tests as part of the task. Avoid running unrelated suites without a reason. Report exact commands and results, including checks not run and why.
