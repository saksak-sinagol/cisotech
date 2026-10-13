---
name: architecture-planning
description: "Use when converting approved requirements into a solution design, interfaces/data flow, security/reliability choices, ADR, or small implementation plan."
---

# Architecture and implementation planning

## Inputs and context

Read current state, workflow, project context, approved requirements/open
questions, relevant code, and existing ADRs.

## Procedure

1. Verify requirements approval and absence of unresolved critical blockers.
2. Reuse existing PHP, `mysqli`, shared includes, and test patterns where
   appropriate.
3. Define components, responsibilities, boundaries, interfaces, and data flow.
4. Assess security, performance, reliability, maintainability, migration,
   compatibility, and rollback implications.
5. Compare alternatives for material design choices; ask the user when
   requirements do not determine a consequential choice.
6. Break work into ordered, independently verifiable tasks with files,
   dependencies, acceptance criteria, tests, and required approvals.
7. Record design/plan and significant ADRs; request explicit plan approval.

## Safety and completion

Read/search only. Do not change application code, dependencies, schema, or
production configuration. Planning exits only with approved design/plan and
documented risks/rollback.
