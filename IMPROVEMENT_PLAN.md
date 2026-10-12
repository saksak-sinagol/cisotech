# Improvement Plan

This plan is ordered by priority and designed so each task is small enough to finish and verify independently. It addresses the highest-risk issues identified during the audit without changing application behavior beyond the intended fixes.

## 1) Add CSRF protection to authenticated state-changing forms

Priority: Highest

Goal: Prevent cross-site request forgery against all POST actions that create, update, or delete records.

Tasks:
1. Add a CSRF token generator and validator helper in a shared include file, such as `includes/auth.php` or a new `includes/csrf.php`.
2. Generate a token on page render for authenticated pages that contain forms.
3. Store the token in the session and include it as a hidden field in each form.
4. Validate the token before processing POST requests in the main handlers.
5. Verify the change by logging in, submitting a form, and confirming the request passes with a valid token.
6. Confirm the app rejects requests with an invalid or missing token.

Files likely involved:
- `includes/auth.php`
- `worklog.php`
- `inventory.php`
- `deployments.php`
- `settings.php`
- `staff.php`
- `messages.php`
- `account.php`

Verification:
- Successful form submits still work normally for valid tokens.
- Invalid token requests fail with a clear error.
- No existing business flow is broken.

## 2) Remove hardcoded secrets from the repository and move config to environment variables

Priority: Highest

Goal: Eliminate credential leakage and make local/production setup configurable.

Tasks:
1. Replace hardcoded DB credentials in `includes/db.php` with environment-based configuration.
2. Add a small example config file or `.env.example` that documents required variables without committing real secrets.
3. Update `setup.php` so it reads default credentials from env or config, rather than embedding them directly in the source.
4. Ensure the app fails with a clear error if required DB or credential environment variables are missing.
5. Verify the app starts with a valid environment file and fails clearly when secrets are absent.

Files likely involved:
- `includes/db.php`
- `setup.php`
- `.gitignore`
- README or deployment docs

Verification:
- Secret values are no longer present in source files.
- The app can be configured by environment variables.
- Missing config triggers a clear message, not a broken connection attempt.

## 3) Harden file upload handling for work log photos

Priority: High

Goal: Reduce risk from untrusted uploads while keeping the photo feature working.

Tasks:
1. Define an allowlist for permitted upload types and reject anything not on the list.
2. Validate file size before saving to disk.
3. Generate a safe file name and ensure the upload directory is not directly served as executable content when possible.
4. Check for `UPLOAD_ERR_*` errors and report them clearly to the user.
5. Save only trusted files and record a message if the upload is rejected.
6. Verify a valid image uploads successfully and an invalid file is rejected with a user-friendly error.

Files likely involved:
- `worklog.php`
- `uploads/`

Verification:
- Valid JPG/PNG/WebP uploads still succeed.
- Oversized or invalid file uploads are rejected.
- The app does not break when upload errors occur.

## 4) Fix database configuration drift and make setup consistent

Priority: High

Goal: Ensure the app selects the correct database consistently across import docs and runtime configuration.

Tasks:
1. Decide on the canonical database name and use it consistently across the project.
2. Update `database.sql` to use the same database name as `includes/db.php`.
3. Check if `setup.php` or any migration scripts assume a different database and align them.
4. Add a short setup note in the project documentation explaining the exact database name and import steps.
5. Verify a clean local import works with the selected database name.

Files likely involved:
- `database.sql`
- `includes/db.php`
- `README.md`
- `setup.php`

Verification:
- Importing the schema creates and uses the correct database.
- The app connects successfully to that database without manual edits.

## 5) Wrap critical inventory and deployment writes in database transactions

Priority: High

Goal: Prevent partial writes that leave inventory counts and deployment history inconsistent.

Tasks:
1. Identify each critical multi-step operation: inventory add/restock and deployment creation.
2. Add `BEGIN`, `COMMIT`, and `ROLLBACK` handling around those operations.
3. Ensure all related inserts are executed before commit, and no partial state remains after DB errors.
4. Add explicit checks for failed statements and log the failure reason.
5. Verify a successful add/deploy still works end-to-end.
6. Verify a forced DB failure leaves the system consistent or clearly reports the error.

Files likely involved:
- `inventory.php`
- `deployments.php`

Verification:
- Successful operations persist all expected records.
- Failed operations do not leave half-written data behind.
- Inconsistent stock totals are prevented.

## 6) Strengthen database error handling and stop swallowing failed operations

Priority: High

Goal: Make data failures visible to users and developers instead of silently continuing.

Tasks:
1. Review all `prepare()`, `execute()`, and `query()` calls for missing result checks.
2. Add explicit error handling around DB operations that matter for user-visible actions.
3. Replace `@new mysqli(...)` with a safer connection pattern that surfaces errors clearly.
4. Log or display meaningful error messages when inserts/updates fail.
5. Verify at least one failed DB operation is visible and not silently accepted as success.

Files likely involved:
- `includes/db.php`
- `inventory.php`
- `worklog.php`
- `deployments.php`
- `settings.php`

Verification:
- The app returns clear errors when SQL fails.
- Users are not shown false success messages after failed writes.

## 7) Add a focused test suite for login, inventory, and deployment flows

Priority: Medium

Goal: Create a minimal regression safety net without needing a full rewrite.

Tasks:
1. Choose a lightweight PHP test runner (for example PHPUnit if available or another simple compatible setup).
2. Add a small test harness for authentication: valid login, invalid password, disabled user.
3. Add a test for inventory add/restock logic with a known item and qty.
4. Add a test for deployment availability checks and the insufficient-stock path.
5. Run the test suite locally and keep it small but meaningful.
6. Document how to run the tests in the project setup notes.

Files likely involved:
- New `tests/` directory
- `phpunit.xml` or equivalent config
- `README.md`

Verification:
- The tests pass reliably.
- Core app behaviors are covered and can be checked after code changes.

## 8) Reduce repeated DB work and optimize slow dashboard queries

Priority: Medium

Goal: Improve responsiveness on dashboard and search-heavy pages as the data grows.

Tasks:
1. Review the dashboard query set in `index.php` and identify repeated expensive aggregate calculations.
2. Replace the worst subqueries with simpler aggregated queries or indexes where appropriate.
3. Add necessary indexes to columns used in filters and joins, such as `work_logs.log_date`, `deployments.item_id`, `inventory_items.item_type_id`, and `department_id` lookups.
4. Keep the dashboard search focused and avoid broad `LIKE` searches on large tables when possible.
5. Verify the main dashboard still returns results correctly after optimization.

Files likely involved:
- `index.php`
- `database.sql`

Verification:
- Query count and response time are improved for common dashboard loads.
- Search and aggregate pages still return correct data.

## 9) Refactor duplicated admin validation and CRUD logic into helpers

Priority: Medium

Goal: Reduce maintenance risk and keep behavior consistent across settings pages.

Tasks:
1. Identify the repeated patterns in `settings.php`: duplicate-name checks, validation, delete checks, and success/error message handling.
2. Extract reusable validation helpers for duplicate names and delete restrictions.
3. Keep each helper focused on a single responsibility and use them consistently.
4. Verify one “add” flow and one “delete” flow before and after the refactor.
5. Confirm no behavior changes beyond cleanup and consistency.

Files likely involved:
- `settings.php`

Verification:
- Settings flows still work as before.
- Duplicate checks remain consistent.
- The code is easier to maintain and review.

## 10) Establish dependency and update hygiene

Priority: Low

Goal: Put the project onto a maintainable footing for future security and compatibility updates.

Tasks:
1. Decide whether the project will remain a pure PHP app or adopt a package manager in the future.
2. If PHP dependencies are introduced, add a manifest and lock file.
3. If no external dependencies are used, document that clearly and keep dependencies out of the repo.
4. Add a basic upgrade checklist for PHP/MySQL versions and security patches.
5. Verify the app can be run with the supported local environment and note any compatibility constraints.

Files likely involved:
- `README.md`
- project root configuration files

Verification:
- Teams know the supported runtime versions.
- There is a documented path for future upgrades.

## Suggested execution order

1. Add CSRF protection.
2. Remove hardcoded secrets and externalize config.
3. Harden file uploads.
4. Fix DB configuration drift.
5. Add transactions around critical writes.
6. Strengthen DB error handling.
7. Add a minimal test suite.
8. Optimize dashboard and search queries.
9. Refactor duplicated logic.
10. Document dependency and runtime hygiene.

## Definition of done for each task

Each task should be considered complete only when:
- the code change is isolated and easy to review,
- the affected flow is manually verified,
- the expected result is documented,
- and no secondary page is broken by the change.

This staged plan keeps the work incremental and safe while addressing the most damaging risk areas first.
