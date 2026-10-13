# Requirements: repository AI engineering workflow

Status: APPROVED for workflow bootstrap by explicit user request, 2026-10-13.

## Functional requirements

1. Provide IDEA, QUESTIONING, PLANNING, EXECUTION, and VALIDATION states with
   explicit entry/exit criteria and back-transition on failed validation.
2. Provide specialized workspace agents for product strategy, requirements,
   architecture, implementation, QA, security review, and documentation.
3. Give discovery/requirements/planning roles edit capability only to persist
   their assigned phase artifacts; prohibit application-code edits and
   terminal execution. Grant execute only to roles that need it.
4. Provide reusable skills and single-task prompt entry points for the
   requested recurring workflows.
5. Persist stable project context, current phase, approvals, and concise
   cross-session handoff separately.
6. Require explicit human approval for requirements-to-planning and
   planning-to-execution transitions; handoff alone is not approval.
7. Use verified CisoTech stack, architecture, test commands, and security
   boundaries rather than generic assumptions.
8. Preserve existing application behavior, dependencies, local secrets, and
   unrelated uncommitted changes.
9. Report only validation that was actually run, with exact results and gaps.

## Nonfunctional requirements

- Native VS Code/GitHub Copilot workspace conventions; Git-friendly Markdown
  and YAML frontmatter.
- No new dependency or external integration.
- Progressive context loading; no whole-repository reads by default.
- Clear ownership of each memory artifact; no duplicated conversation logs.
- Least-privilege static tool grants, with host limitations stated honestly.

## Acceptance criteria

- Each of the seven agents has purpose, responsibilities, required context,
  tool boundary, prohibitions, clarification conditions, entry/exit criteria,
  handoff, and memory rules.
- Each requested reusable skill has activation description, inputs,
  procedure, safety, outputs, and completion checks.
- Workspace prompts cover idea, requirements clarification, planning,
  execution, bug investigation, validation, and context resumption.
- Project-wide and relevant scoped instructions link to authoritative context.
- `PROJECT-CONTEXT.md`, `CURRENT-STATE.md`, `WORKFLOW.md`, and
  `SESSION-HANDOFF.md` have distinct roles and required information.
- Requirements, architecture, implementation-plan, and ADR directories exist
  with an initial task record/template.
- A missing approval is a stop condition in phase agents/prompts and persisted
  workflow guidance. Static tool limits and inability to enforce dynamic
  authorization are disclosed.
- Phase agents can edit only their assigned artifacts by procedure; the
  Copilot host does not enforce per-path edit scopes.
- Customization metadata and local references are checked; no application
  source or dependencies are modified by this workflow bootstrap.

## Dependencies, constraints, and risks

- Depends on VS Code's documented workspace Copilot discovery of `.github/`
  agents, skills, prompts, and instructions.
- Existing singular `.github/copilot-instruction.md` is not the documented
  canonical filename; preserve its content while moving to
  `.github/copilot-instructions.md`.
- Static agent tools cannot guarantee that a model obeys approval text; native
  user tool-approval controls remain the hard boundary.
- Existing working-tree application changes are outside scope and must remain
  untouched.

## Approval record

Approved by: user (explicit master prompt)
Date: 2026-10-13
Scope: workflow/configuration/documentation scaffolding only.
