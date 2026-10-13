# AI engineering workflow

## Operating principles

- The repository is the durable context source; read only the relevant files.
- Check `CURRENT-STATE.md` before substantive work. Resolve stale/conflicting
  state explicitly instead of silently selecting one version.
- Use one agent for a continuous trace. Delegate only bounded, independent
  investigations that save meaningful context.
- Never expose secrets or place them in context documents, logs, tests, or
  prompts.
- Preserve unrelated worktree changes. Do not claim checks passed unless they
  were executed and observed.
- A direct user request is approval only for its stated scope. It does not
  approve adjacent application, schema, deployment, or destructive changes.

## Phases and gates

### 1. IDEA

Owner: Product Strategist. Capture problem, users, outcomes, initial scope,
exclusions, assumptions, constraints, dependencies, and discovery questions
in `docs/requirements/idea.md`. Do not change application code or claim the
solution is approved.

**Exit:** problem and desired outcome are clear; user explicitly confirms the
idea is ready for requirements analysis. Record confirmation and date.

### 2. QUESTIONING

Owner: Requirements Analyst. Resolve material ambiguity; specify functional
and nonfunctional behavior, permissions, edge cases, failures, migration
concerns, risks, and measurable acceptance criteria. Keep unresolved items in
`docs/requirements/open-questions.md`; record only confirmed answers as
requirements.

**Exit:** acceptance criteria are testable; dependencies and risks are
documented; critical questions are resolved or explicitly block planning;
stakeholder approval is recorded in `requirements.md`. Do not implement.

### 3. PLANNING

Owner: Solution Architect. Describe the existing-state fit, design, interfaces,
data flow, security/reliability impacts, alternatives when material, small
ordered tasks, tests, rollback, and approvals. Use `docs/architecture/` and
`docs/planning/`; record significant decisions in `docs/decisions/`.

**Exit:** approved requirements are satisfied by the plan, task acceptance
criteria/dependencies/risks/rollback are stated, and user approval is recorded
in the plan. Do not modify application source or production configuration.

### 4. EXECUTION

Owner: Implementation Agent. Verify approvals in persisted requirements and
plan before code changes. Implement only approved scope in reviewable
increments. Report blockers and material deviations before expanding scope.
Update state/handoff after meaningful milestones.

**Exit to VALIDATION:** increment is implemented and ready for targeted checks.
Do not mark complete based only on code being written.

### 5. VALIDATION

Owner: QA Engineer; invoke Security Reviewer for security-sensitive changes
and Documentation Agent for documentation changes when useful. Compare
results against the acceptance criteria; run proportionate relevant tests and
review the diff.

- A defect or unmet criterion returns the task to EXECUTION.
- Completion requires all required checks to pass, or a named authorized user
  to explicitly accept each verification gap. Record exact executed commands,
  results, skipped checks, and environment limitations.

## Transitions and handoffs

`IDEA -> QUESTIONING -> PLANNING -> EXECUTION -> VALIDATION -> COMPLETE`.
Validation defects transition back to EXECUTION. Approval is not implied by
handoff or by an agent's recommendation.

Every handoff names the receiving role, task, artifacts, satisfied exit
criteria, unresolved issues, and next action. The receiving agent rereads
`CURRENT-STATE.md` and independently checks the gate before acting.

The initial workflow-bootstrap task is explicitly approved by the user's
request and is limited to workflow artifacts/configuration. For later work,
approval must be written in the relevant artifact; absence or ambiguity means
stop and ask.

## Proportionate workflow

Small, reversible, low-risk fixes may use concise IDEA/QUESTIONING/PLANNING
notes in the task or current state rather than full new documents, but still
need testable expected behavior, scope, approval before implementation when
material, and truthful validation. Security, data integrity, credentials,
destructive operations, schema changes, and deployment retain explicit gates.

## Tool and enforcement limits

IDEA, QUESTIONING, and PLANNING agents receive edit capability only because
they must persist their phase artifacts; their instructions limit edits to
those docs and they have no terminal capability. The Copilot host does not
enforce per-path edit scopes. Execution agents have static edit/execute
capability; approval checks are procedural and persisted, not a security
sandbox. The user and VS Code's native approval controls remain authoritative
for tool execution.

No model, token, or delegation limits are claimed. Prefer the smallest
sufficient context and available model; tool/model selection depends on the
active Copilot host.

## Memory maintenance

- `PROJECT-CONTEXT.md`: stable verified project facts and authoritative links.
- `CURRENT-STATE.md`: phase, active task, approvals, progress, blockers, actual
  verification, and next action.
- `SESSION-HANDOFF.md`: concise restart instructions for the next chat.
- Requirements, architecture, plans, and ADRs: durable task-specific decisions
  in their respective directories.
- Never duplicate long conversations or store credentials/sensitive personal
  data.
