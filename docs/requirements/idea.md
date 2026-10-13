# Idea: repository AI engineering workflow

Status: APPROVED FOR REQUIREMENTS ANALYSIS (bootstrap scope approved by user,
2026-10-13).

## Problem

AI-assisted changes need consistent discovery, requirement clarification,
planning, implementation, validation, and handoff. Without durable phase
state and project-specific context, agents may repeat repository scans, lose
decisions across chats, implement unresolved assumptions, or overstate
verification.

## Users and desired outcome

- Primary users: maintainers and contributors using VS Code with GitHub Copilot.
- Desired outcome: a maintainable, team-shared workflow adapted to this PHP
  application, with clear phase roles, persisted context, approval gates, and
  evidence-based validation.

## Initial scope

- Native repository-level Copilot instructions, custom agents, skills, and
  prompt entry points.
- Distinct project memory, phase state, handoff, requirements, architecture,
  plan, and decision-record locations.
- Documented resource, safety, approval, and validation rules.
- Validation of discoverability metadata and repository references.

## Exclusions

- No PHP application behavior changes, new runtime/development dependencies,
  schema changes, deployment, CI, or live database operations.
- No claim of hard dynamic tool/model/token enforcement unsupported by the
  active host.

## Assumptions and constraints

- Workspace customizations should be committed and shared with the team.
- Existing PHP/MariaDB and test conventions remain authoritative.
- Existing uncommitted work must be preserved.
- Local `.env` and private TLS keys stay ignored and out of project memory.

## Discovery questions

No material unanswered question blocks this bootstrap: the user selected a
repository-based Copilot workflow and supplied the phases and required
artifacts.

## Approval

The user explicitly requested implementation of this workflow. Approval is
limited to the scaffolding described above; future product changes need their
own gates.
