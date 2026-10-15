# Session handoff

Updated: 2026-10-14

## Objective

Complete the approved login-bootstrap bugfix. User authorized a tracked
configuration loader and regression coverage in this checkout; do not inspect
`.env`, change database state, or deploy.

## Current task

- Reference `2fcc7a3660f56e6b9ec4dae7a49c24ea` matches an Nginx error for
  `index.php`; `includes/db.php:11` requires missing `includes/config.php`.
- `.gitignore` excludes generic `config.php`, so add the versioned loader as
  `includes/app_config.php` and update DB/test bootstrap references.
- Approval is recorded in `docs/ai/CURRENT-STATE.md`.
- Implemented the loader and added a regression test for `.env` loading and
  process-environment precedence. PHP lint, PHP smoke checks, XML parsing, and
  `git diff --check` passed.
- User approved installing locked development dependencies. `composer install`
  passed; `composer test` passed (37 tests / 190 assertions); `composer lint:php`
  passed (75 files, no fixes required).
- No database-backed HTTP login was tested; no service restart or deployment
  was performed.
- Follow-up reference `5f090312e6dbf42a0946ff4f2831028e` is for `login.php` and
  points to `app_env_required()` rejecting an unset or empty required setting.
  Ask the user to verify required DB setting presence only; never request or
  inspect their values.
- User confirmed only `CISOTECH_TEST_DB_*` exists in SSH/CLI; `.env` is absent
  and PHP-FPM has no `DB_*` configuration. Login requires the actual app
  settings `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, and `DB_PORT`.
- No application deployment is authorized.

## Completed

- Inspected repository stack, structure, tests, existing customization, and
  uncommitted work.
- Confirmed project is legacy PHP 8.1/MariaDB with PHPUnit and
  pytest/Playwright.
- Confirmed `.github/copilot-instruction.md` is the only current project AI
  guidance and uses a noncanonical singular filename.
- Confirmed local `.env` and TLS private key are ignored by Git. Their contents
  were not inspected.
- Created the seven custom agents, ten skills, seven prompt entry points, two
  scoped instructions, project memory, phase artifacts, architecture, and
  implementation plan.
- Structural validation passed: 7 agents, 10 skills, 7 prompts, and 2
  instructions parsed; handoff/prompt targets resolved; 16 local Markdown
  links across 37 workflow documents resolved; Markdown whitespace validation
  and `git diff --check` passed.
- Extracted deployment service, request handlers, AJAX handlers, read-model
  preparation, and wizard frontend into `includes/deployment_*.php`.
- Extracted inventory service operations, read-model preparation, and
  frontend behavior into `includes/inventory_*.php`.
- Extracted settings POST handling and tab views into
  `includes/settings_actions.php` and `includes/settings_views/`.
- After each extraction, `composer test` passed (36 tests, 186 assertions)
  and changed PHP files passed `php -l`.
- Added PHP-CS-Fixer with `composer lint:php` and `composer format:php`, using a
  narrow style configuration that preserves existing template conventions.
- The formatter fixed four files; a second lint run passed for all 74
  first-party PHP files. No existing dependency versions changed.
- Added formatter usage to the README and validated Composer metadata.
- `composer test:integration` was attempted after each extraction but cannot
  start without `CISOTECH_TEST_BASE_URL` and `CISOTECH_TEST_DB_*`. No values
  were guessed; no development or production database was used.
- An attempted inventory view split crossed a PHP template-control-flow
  boundary; the original view was restored, leaving no partial view files.
- Replaced the per-item pending disposal-flag lookup in
  `run_disposal_autoflag()` with a set-based anti-join while retaining the
  existing PHP inactivity-date calculation and per-flag activity logging.
- Replaced seven separate activity-tab count queries with one grouped count
  query.
- Added indexes aligned with inventory batch/remark/disposal lookups,
  deployment aggregates and filters, reverse message-conversation lookups,
  work-log status, and activity module/date filters. Fresh installs receive
  them from `database.sql`; existing installs can use the idempotent,
  superadmin-only `migrate_v21_add_query_indexes.php`.
- `composer test` passed after these query changes (36 tests, 186 assertions);
  PHP syntax, `composer lint:php` (75 files), and `git diff --check` passed.
- Applied the ten additive index definitions from migration v21 to the
  explicitly authorized `cisotech_test_` database using its test-only
  connection. All ten definitions were verified afterward. The web migration
  entry point was not invoked because no active test superadmin exists;
  `c1so_tech` was not accessed.
- No EXPLAIN plan or timing was run. The HTTP integration server is not yet
  configured.
- For the UI-quality request, reviewed shared CSS/chrome and key
  form/error/empty-state templates. Findings and the approval-gated scope are
  recorded in `docs/requirements/ui-quality.md` and
  `docs/planning/ui-quality-plan.md`. The user approved both artifacts on
  2026-10-13; all five planned tasks are implemented and validated.
- UI updates: improved muted/accent/error contrast and focus rings; semantic
  error/success/loading announcements; accessible labels for login, work-log,
  message, and public filters; keyboard-operable mobile/account menus; mobile
  layout refinements and scrollable public work-log table regions.
- UI validation: `composer test` (36 tests / 186 assertions),
  `composer lint:php` (75 files), changed-file syntax checks, and
  `git diff --check` passed. Chromium layout/keyboard tests passed at 320px,
  375px, and 768px; static contrast calculations passed. Full authenticated
  E2E and assistive-technology checks remain unrun.
- Review follow-up: submit progress now updates a persistent polite live region;
  Chromium verified its text, role, and `aria-live` state.
- No first-party images render in application pages; bundled Adminer is
  third-party and was left unchanged.
- All pending staged, unstaged, and untracked changes were committed together
  as `6a0aa4b` and pushed over SSH to `origin/main`. The working tree is clean.
  The user clarified that this request was Git-only; no application server was
  deployed to.
- Final checks passed: `composer test` (36 tests / 186 assertions),
  `composer lint:php` (75 PHP files), `composer validate --no-check-publish`,
  and `git diff HEAD --check`. Remote `main` was verified at commit
  `6a0aa4ba34cec16847b8af77d90ce9b290548db4`.

## Decisions

- Preserve the PHP/MariaDB design and current unrelated working-tree changes.
- Use native `.github/agents`, `.github/skills`, `.github/instructions`, and
  `.github/prompts`.
- Persist phase status and approvals in `docs/ai/`; future execution requires
  explicit human approval.
- No hooks or other automation are added because supported tool-input
  enforcement and dynamic agent permissions are not confirmed for this
  workspace harness.
- Performance conclusions are based on static query/schema analysis only;
  actual plans and timing should be gathered using representative data in the
  isolated test environment before drawing conclusions about deployed
  performance.

## Remaining work

- Configure the isolated loopback HTTP server described in
  `tests/integration/README.md`.
- Run deployment, inventory, and settings integration tests, then collect
  representative EXPLAIN plans against the isolated database.
- Split `tests/integration/EndpointIntegrationTest.php` by domain only when the
  suite can be executed after each split.
- Review the final diff and document any accepted verification gap.

## Exact next action

Have the user configure the actual application `DB_*` settings for PHP-FPM or
create the root `.env` from `.env.example` with the correct application values,
then retry login. Do not inspect `.env`, make an HTTP login request against the
active application, or deploy without separate authorization.
