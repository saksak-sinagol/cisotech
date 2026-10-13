# Project context

Verified 2026-10-13 from the repository's Composer manifest, SQL schema,
application structure, tests, and local project documentation.

## Purpose and users

CisoTech is a staff-facing work-log and inventory application. It records work
logs, inventory stock and item details, deployments/returns to departments,
staff accounts, reports, and internal messages. Roles include staff, admin,
and superadmin. `public_worklog.php` exposes the public work-log view.

## Technology stack

- PHP 8.1, server-rendered pages; no application framework.
- MariaDB/MySQL accessed through PHP `mysqli`.
- Composer manages PHP development dependencies; PHPUnit 10 is used for unit
  and HTTP integration tests.
- Python/pytest and Playwright provide browser end-to-end coverage.
- VS Code workspace configuration currently includes a browser launch
  configuration.
- No repository CI workflow was present at the time of inspection.

## Boundaries

- Root-level PHP files are page/controller entry points.
- `includes/` contains shared authentication, configuration, database,
  validation/domain rules, security, page chrome, and error handling.
- `database.sql` is the fresh-install schema; `migrate_v*.php` files are
  existing-install migrations.
- `assets/` contains frontend assets; `uploads/` stores work-log uploads.
- `tests/` contains unit, isolated HTTP integration, and Playwright tests.
- `adminer.php` is a bundled third-party database administration tool; avoid
  unrelated edits to it.

## Domain terminology

- **Work log**: dated task record, optionally categorized and associated with
  uploaded photos.
- **Inventory item/type/category**: stock model, kind, and higher-level
  grouping; stock quantity is supported by inventory batches and per-unit
  identifiers.
- **Deployment**: assignment of stock to a department/person; can be new or a
  replacement and later marked returned.
- **Superadmin/admin/staff**: application roles with different management
  permissions.

## Constraints and security boundaries

- Preserve the existing PHP/MariaDB architecture unless approved requirements
  authorize a change.
- `.env`, local TLS private keys, and local runtime/test data are sensitive;
  never read them into project context or commit them.
- Never log passwords, session identifiers, CSRF tokens, or raw request bodies.
- Destructive database work, deployment, dependency installation, and live
  schema changes require explicit approval.
- Use isolated databases whose names begin `cisotech_test_` for DB-backed
  automated tests.

## Authoritative references

- [README](../../README.md): local configuration, security defaults, tests.
- [Database schema](../../database.sql): fresh-install relational model.
- [Composer manifest](../../composer.json): PHP runtime and test commands.
- [Integration-test guide](../../tests/integration/README.md): isolated HTTP
  test setup.
- [E2E-test guide](../../tests/e2e/README.md): isolated browser test setup.
- [Workflow](./WORKFLOW.md): phase and approval rules.
