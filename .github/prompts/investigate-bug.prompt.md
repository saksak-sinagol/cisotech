---
name: "Investigate a Bug"
description: "Reproduce and trace a PHP application bug or failing test, distinguish evidence from hypotheses, and recommend a bounded fix."
argument-hint: "Describe expected and observed behavior"
agent: "Implementation Agent"
---

Read current state/workflow/context and inspect worktree status. Define expected
versus observed behavior and reproduce with a focused safe test. Trace only the
relevant route/domain helper/data flow. Report confirmed root cause separately
from hypotheses. Do not use production data or expose secrets. Do not fix
unrelated issues. Before implementing, verify approved requirements/plan; if
this is an unplanned bugfix, ask for approval proportional to its risk and
scope. Record evidence and exact next action.
