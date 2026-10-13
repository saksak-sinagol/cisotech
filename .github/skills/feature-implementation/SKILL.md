---
name: feature-implementation
description: "Use when implementing a feature or approved refactor from a persisted, user-approved requirements document and implementation plan."
---

# Feature implementation

## Inputs and context

Read current state/workflow/context, approved requirements and plan, scoped
instructions, relevant source/tests, and worktree status.

## Procedure

1. Verify both requirement and plan approvals and exact authorized scope. If
   either is absent/ambiguous, stop and ask.
2. Preserve unrelated changes; search before adding helpers or dependencies.
3. Implement one small plan task at a time with tests.
4. Run the smallest relevant checks; fix only in-scope failures.
5. Inspect diff and status; record exact commands/results, gaps, and handoff.

## Safety and completion

No self-approval, scope creep, secret access, or production/destructive
operations. Ask before dependency installation, schema/data changes, or
deployment unless explicitly authorized for this exact task. Completion
requires validation evidence, not code alone.
