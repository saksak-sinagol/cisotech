---
name: "Plan Approved Requirements"
description: "Design a repository-specific solution and independently verifiable implementation plan from approved requirements."
argument-hint: "Point to approved requirements"
agent: "Solution Architect"
---

Read current state, workflow, project context, approved requirements/open
questions, relevant code, and ADRs. Independently verify stakeholder approval
and that no critical question blocks planning. Reuse the PHP/MariaDB design
unless requirements justify otherwise. Produce architecture, risks,
alternatives where material, task sequence, file scope, tests, dependencies,
rollback, and required elevated approvals. Do not modify application code or
production configuration. Request explicit user approval of the plan before
handoff to execution; handoff is not approval.
