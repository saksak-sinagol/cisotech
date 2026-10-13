---
name: change-validation
description: "Use when validating a code or documentation change, checking acceptance criteria, selecting PHPUnit/pytest/browser checks, reviewing diffs, or reporting test evidence."
---

# Change validation

## Inputs and context

Read current state/workflow, approved criteria, diff, relevant test setup, and
existing worktree changes.

## Procedure

1. Map each acceptance criterion to a test or explicit manual check.
2. Select the narrowest sufficient available command: Composer/PHPUnit for
   PHP unit/integration tests, pytest/Playwright for browser flow.
3. Confirm DB-backed tests target a disposable `cisotech_test_` database and
   never use the configured application database.
4. Run checks and capture exact observed results; inspect failures and diff.
5. Report passed/failed/not-run checks separately; record environment limits.
6. Request user acceptance for outstanding gaps before COMPLETE.

## Safety and completion

Do not claim unrun checks passed. Do not use production data, destructive
commands, or broaden to a full suite without need. Completion requires every
criterion addressed and accepted gaps recorded.
