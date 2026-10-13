# AI workflow architecture

Status: approved for the workflow bootstrap by explicit user request,
2026-10-13.

## Environment fit

The repository is a server-rendered PHP 8.1/MariaDB app with Composer,
PHPUnit, pytest, and Playwright. The installed editor context is VS Code with
GitHub Copilot. Use documented workspace customizations rather than adding an
agent runtime or application framework.

## Components

- `.github/copilot-instructions.md`: concise project-wide rules and links.
- `.github/instructions/`: scoped PHP/application and database/migration rules.
- `.github/agents/`: seven specialized roles; phase agents have `read` and
  `search` only, while implementation/QA/documentation receive only the tools
  their work needs.
- `.github/skills/`: on-demand procedures for repeatable work; agent personas
  remain separate from skills.
- `.github/prompts/`: focused slash-command entry points that reload current
  state and verify gates.
- `docs/ai/`: stable project context, active state, process rules, and concise
  session handoff.
- `docs/requirements/`, `docs/architecture/`, `docs/planning/`, and
  `docs/decisions/`: phased task artifacts and significant decisions.

## Phase flow and data

The user request is captured as an idea, clarified into testable requirements,
designed into a plan, then implemented and validated. Approval records are
stored alongside requirements and the plan; `CURRENT-STATE.md` points to the
authoritative records. Handoffs link to artifacts rather than copying their
full content. Test commands and results are recorded as evidence, not inferred
from code inspection.

## Safety and permissions

- IDEA/QUESTIONING/PLANNING agents: read/search plus edit access needed for
  their assigned phase artifacts only; no terminal or application-code edits.
- Security Reviewer: read/search only.
- Implementation Agent: read/search/edit/execute, restricted procedurally to
  approved scope.
- QA Engineer: read/search/execute, no edit by default.
- Documentation Agent: read/search/edit, no execute by default.
- No new MCP server, external research integration, runtime dependency, or
  hook is needed for the bootstrap.
- Approval checks are not a dynamic sandbox: Copilot tool sets are static.
  Agent instructions require checking persisted user approvals, but native
  user confirmation remains the security boundary.

## Alternatives and tradeoffs

- **Custom agents vs one general prompt:** selected agents provide role
  separation and least-privilege tools for phase boundaries.
- **Skills vs agents:** recurring procedures are on-demand skills; roles are
  dedicated custom agents. Avoid copying whole role definitions into skills.
- **Lifecycle hooks:** not selected because the current task does not require
  deterministic automation, and a hook cannot safely bind persisted approval
  to human identity without additional supported host data.
- **All context always loaded:** rejected to reduce repeated token/tool
  consumption; state and skills are loaded progressively.
