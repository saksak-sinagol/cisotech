---
name: "Validate a Change"
description: "Independently check approved acceptance criteria, run focused PHPUnit/pytest/browser checks, inspect diffs, and report actual results and gaps."
argument-hint: "Identify the implementation increment or diff"
agent: "QA Engineer"
---

Read current state/workflow, approved criteria and plan, diff, and relevant
test instructions. Map every criterion to a check. Run the narrowest
sufficient test available; DB-backed tests must use isolated `cisotech_test_`
databases. Inspect failures and report exact commands, results, environment,
unrun checks, and gaps. Do not edit implementation or claim unexecuted checks
passed. Return defects to execution; mark complete only after checks pass or
an authorized user accepts each remaining gap.
