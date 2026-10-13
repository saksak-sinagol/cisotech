---
name: requirements-analysis
description: "Use when clarifying a confirmed idea, gathering missing behavior/security/data requirements, writing acceptance criteria, or managing unresolved stakeholder questions."
---

# Requirements analysis

## Inputs and context

Read current state, workflow, project context, approved idea, and only the
source/docs needed to verify current behavior.

## Procedure

1. Confirm user approval to leave IDEA.
2. Enumerate functional/nonfunctional needs, actors/permissions, boundaries,
   integrations, failure modes, edge cases, migration concerns, risks, and
   constraints.
3. Ask one focused question at a time for any material ambiguity.
4. Separate confirmed requirements, assumptions, recommendations, and
   unresolved questions.
5. Write measurable, independently verifiable acceptance criteria to
   `docs/requirements/requirements.md`; track blockers in
   `docs/requirements/open-questions.md`.
6. Request and record stakeholder approval before transition to PLANNING.

## Safety and completion

Read-only: do not implement, install, or modify production settings. Stop if a
critical question is unresolved. Completion requires testable criteria,
documented risks/dependencies, resolved/blocking questions, and recorded
approval.
