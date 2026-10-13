---
name: bug-investigation
description: "Use when diagnosing a reported PHP application defect, failing test, unexpected database/API behavior, or regression; reproduce and trace before proposing a fix."
---

# Bug investigation

## Inputs and context

Read current state, project context, report/reproduction, relevant source and
tests. Check worktree for unrelated edits before changing anything.

## Procedure

1. Clarify expected vs observed behavior and identify minimal reproduction.
2. Run a focused test or safe reproduction; record environment and evidence.
3. Trace the responsible route/domain helper/data flow and compare existing
   acceptance criteria.
4. Separate confirmed root cause from hypotheses; consider adjacent paths.
5. Recommend the narrowest fix and regression test. For implementation, verify
   requirements/plan approvals or request approval for a proportionate bugfix.
6. Record exact validation and handoff.

## Safety and completion

Do not run against production, expose secrets, make destructive changes, or
silently fix unrelated issues. Stop with evidence and a bounded next action if
reproduction is unsafe or impossible.
