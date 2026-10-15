# Current state

Last updated: 2026-10-14

## Active phase and objective

- Phase: VALIDATION (approved login-bootstrap bugfix).
- Objective: validate the restored configuration bootstrap that prevented
  database-backed pages, including login, from loading.
- Scope approval: user authorized adding a versioned environment configuration
  loader and regression coverage in this checkout on 2026-10-14. Do not inspect
  `.env`, change database state, or deploy.

## Progress

- Confirmed reference `2fcc7a3660f56e6b9ec4dae7a49c24ea` in the Nginx error
  log: `index.php` fails while `includes/db.php` requires the absent
  `includes/config.php`. The clean Git tree has no tracked loader; `.gitignore`
  excludes files named `config.php`.
- Approved implementation plan: add a tracked `includes/app_config.php` that
  follows README configuration behavior, wire the DB and PHPUnit bootstraps to
  it, and run focused PHP/configuration checks. No schema, database, secret,
  or deployment changes.
- Completed: added `includes/app_config.php`, routed application/test
  bootstraps through it, and added a regression test for `.env` loading and
  process-environment precedence.
- Completed: repository assessment; confirmed PHP 8.1/MariaDB, Composer,
  PHPUnit, pytest/Playwright, existing working tree changes, and current
  customization files.
- Completed: workflow artifacts and native agent customizations.
- Completed: separated deployment service/actions/AJAX/page data/frontend;
  inventory service/page data/frontend; settings actions and tab views.
- Completed: consolidated activity tab counts into one grouped query and
  removed the per-candidate pending-disposal-flag lookup from the inventory
  autoflag scan.
- Completed: added query-aligned indexes to the fresh-install schema and an
  idempotent, superadmin-only existing-install migration
  (`migrate_v21_add_query_indexes.php`).
- Completed: applied the migration's ten index definitions to the explicitly
  authorized `cisotech_test_` database through its test-only connection, then
  verified all ten index definitions. The web migration entry point was not
  invoked because the test database has no active superadmin; `c1so_tech` was
  not accessed.
- Completed: reviewed shared UI chrome and CSS, login/work-log/messages,
  empty/error-state patterns, first-party image usage, and palette contrast.
- Completed: persisted UI requirements and plan; user approved both on
  2026-10-13.
- Completed: implemented the five approved UI tasks, preserving existing
  workflows and unrelated changes.
- Review follow-up: added a persistent polite live region for submit progress
  instead of relying on a dynamic button name to be announced.
- Completed: UI review passed after the live-region correction. User approved
  isolating the UI changes; commit `35dcc1d` was pushed to remote branch
  `ui-quality-accessibility`. No pull request was created.
- Completed: user authorized committing and pushing all pending changes.
  Commit `6a0aa4b` containing 46 files was pushed to `origin/main` over SSH.
  No application-server deployment was performed.
- Validation passed: Chromium checks at 320px, 375px, and 768px; keyboard menu
  operation; color contrast; PHPUnit; PHP syntax and style; whitespace.
- Pending: run the HTTP integration suite against its isolated test database,
  finish the proposed integration-test suite split, and review that diff.
  Representative EXPLAIN plans and timings also remain pending; no live or
  unverified database was accessed.

## Approved requirements and architecture

- Requirements: [initial workflow request](../requirements/idea.md) and
  [requirements](../requirements/requirements.md).
- Architecture: [workflow architecture](../architecture/architecture.md).
- Implementation plan:
  [phased plan](../planning/implementation-plan.md).

## Decisions, blockers, unresolved issues

- Keep the existing PHP/MariaDB stack unchanged.
- Use native workspace Copilot files under `.github/`.
- No external integrations, dependency changes, CI additions, or automation
  hooks are included in this bootstrap.
- Native custom-agent tool grants are static; approval checks are procedural
  and persisted, not a security sandbox.
- The HTTP integration test runner also requires a loopback app server; this
  session verified database connectivity and schema but did not configure or
  run the server.
- Material blocker: the isolated test database is available, but the loopback
  HTTP app server is not configured, so the integration suite split cannot yet
  be safely validated.
- The performance review is static rather than measured. Indexes were chosen
  from observed query predicates/orderings; EXPLAIN plans and timings remain
  necessary to assess actual performance impact.

## Verification

- Passed: Python structural validation parsed YAML frontmatter and required
  sections for 7 agents, 10 skills, 7 prompts, and 2 scoped instructions.
- Passed: every declared agent handoff and prompt agent target resolves to a
  declared custom agent.
- Passed: 16 local Markdown links across 37 workflow Markdown files resolve.
- Passed: Markdown trailing-whitespace check across 37 workflow files and
  `git diff --check`.
- Passed after every extraction: `composer test` (36 tests, 186 assertions).
- Passed after every extraction: `php -l` on the changed pages and new modules.
- Passed: `composer lint:php` on 74 first-party PHP files after applying
  configured formatting fixes.
- Passed: `composer test` after query optimizations (36 tests, 186 assertions).
- Passed: PHP syntax checks for changed PHP files and `git diff --check`.
- Passed: `composer lint:php` after query optimizations (75 PHP files).
- Passed: `composer validate --no-check-publish`.
- Formatting fixes removed trailing whitespace/excess blank lines from four
  files; no existing dependency versions changed when adding PHP-CS-Fixer.
- Not passed: `composer test:integration`; each attempt stopped before running
  tests because `CISOTECH_TEST_BASE_URL` and `CISOTECH_TEST_DB_*` are absent.
- Not run: Playwright end-to-end tests.
- Passed: applied the ten v21 additive index definitions on the approved
  `cisotech_test_` database and verified all ten definitions. No other
  database was accessed.
- Passed: `composer test` after UI changes (36 tests, 186 assertions).
- Passed: `composer lint:php` (75 PHP files), `php -l` on changed PHP files,
  and `git diff --check`.
- Passed: focused Playwright/Chromium checks for public and messaging layouts
  at 320px, 375px, and 768px; focus visibility; mobile/account menu keyboard
  behavior; submit progress live-region announcement and reduced motion.
- Passed: palette contrast calculations for representative text/focus
  combinations (minimum measured normal text 4.65:1; focus indicator 5.56:1).
- Not run: full authenticated E2E suite or assistive-technology testing.
- Passed: isolated UI-only commit pushed to `origin/ui-quality-accessibility`;
  verified remote branch points to `35dcc1dd0ee85a053ae42253556a05864a42e7c9`.
- Passed: after final commit, `composer test` (36 tests / 186 assertions),
  `composer lint:php` (75 files), `composer validate --no-check-publish`,
  `git diff HEAD --check`, and remote `main` SHA verification.
- No first-party rendered `<img>` elements were found; bundled Adminer is
  third-party and was not modified.
- Not run: the authenticated web migration page, EXPLAIN plans, or query
  timings. The test database contained no active superadmin.
- Login-bootstrap bugfix: changed PHP files pass `php -l`; a PHP smoke check
  passed for environment-file loading, process-variable precedence, and font
  URL escaping; both PHPUnit XML files parse; `git diff --check` passes.
- Login-bootstrap bugfix: user authorized installing locked Composer
  development dependencies. `composer install --no-interaction --prefer-dist
  --no-progress` passed; `composer test` passed (37 tests, 190 assertions);
  `composer lint:php` passed (75 files, none fixable).
- Login-bootstrap bugfix: no database-backed HTTP login request was tested,
  and no deployment or service restart was performed.
- Follow-up reference `5f090312e6dbf42a0946ff4f2831028e` is for `login.php` and
  points to `app_env_required()` rejecting an unset or empty required setting.
  The application logger intentionally omits the setting name/value; verify
  required DB settings in PHP's environment without disclosing their values.
- User confirmed only `CISOTECH_TEST_DB_*` variables are set in the SSH/CLI
  environment; the root `.env` is absent and PHP-FPM has no `DB_*` settings.
  The login bootstrap requires `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, and
  `DB_PORT`; test-database CLI variables are not a substitute.
- Inventory tab partial extraction was abandoned after a PHP parse check exposed
  a cross-file template-control-flow boundary; the original template was
  restored and the unit suite plus PHP lint passed afterward.
- Not run: interactive VS Code discovery check; it requires opening/reloading
  the repository in the target VS Code/Copilot host.
- All previously staged, unstaged, and untracked work was committed and pushed
  in `6a0aa4b`; the original worktree is clean.

## Approval status

- Workflow scaffolding: APPROVED by explicit user request, 2026-10-13.
- UI quality requirements and implementation plan:
  APPROVED by user, 2026-10-13.
- Login-bootstrap bugfix: user authorized the focused configuration-loader
  repair and regression coverage in this checkout on 2026-10-14.
- Future feature requirements and execution plans: NOT APPROVED until a
  stakeholder explicitly records approval in the relevant artifacts.

## Next action

Configure the actual application `DB_*` settings for PHP-FPM (or create the
root `.env` from `.env.example` with the application's values and readable
permissions), without sharing values. Then retry login. Do not inspect `.env`,
deploy, or restart services without separate authorization.
