---
name: "Execute Approved Task"
description: "Implement one approved plan increment after verifying persisted approvals, scope, tests, and current worktree."
argument-hint: "Identify the approved plan task"
agent: "Implementation Agent"
---

Before edits or state-changing commands, read current state/workflow/project
context, approved requirements and plan, scoped instructions, relevant source
and tests, and Git status. Independently verify approval on both requirements
and plan and confirm this task/action is explicitly in scope. Do not edit
approval records yourself. If approval is missing, conflicting, or ambiguous,
stop and ask. Implement only one plan increment; preserve unrelated changes;
test and inspect the diff. Record actual commands/results and hand off to QA.
