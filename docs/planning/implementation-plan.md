# Implementation plan: AI workflow bootstrap

Status: APPROVED for this bootstrap by explicit user request, 2026-10-13.

Scope excludes PHP behavior, dependencies, CI, schema, deployment, and live
data. Preserve existing dirty work.

| # | Task | Files/area | Acceptance and verification |
|---|---|---|---|
| 1 | Install concise project guidance and scoped language/data rules | `.github/copilot-instructions.md`, `.github/instructions/` | Canonical filename discoverable; preserves existing rules; scoped metadata parses. |
| 2 | Establish persistent project and phase context | `docs/ai/`, `docs/requirements/` | Distinct state/context/handoff roles; bootstrap scope and approval recorded; no secrets. |
| 3 | Document project-specific architecture and ordered plan | `docs/architecture/`, `docs/planning/`, `docs/decisions/` | Boundaries, alternatives, risks, test commands, rollback/approval expectations recorded. |
| 4 | Define phase agents and handoffs | `.github/agents/` | Seven agents; discovery/planning can persist assigned docs only and cannot execute; execution gates and handoff targets consistent. |
| 5 | Add reusable engineering skills | `.github/skills/` | Ten focused skills each include trigger, input, procedure, safety, output, completion criteria. |
| 6 | Add focused entry prompts | `.github/prompts/` | Seven prompts reload current state, verify phase/approval, and preserve handoff context. |
| 7 | Validate the customization package | All new/changed workflow files | Check YAML/frontmatter, links/targets, required content, no secrets, and Git diff; report checks actually run. |

## Risks and rollback

- Canonical instruction filename correction could affect existing host
  discovery; keep its existing guidance and add rather than duplicate.
- Copilot capability discovery varies by editor version; manual discovery check
  is required in VS Code.
- Static tool assignments cannot technically revoke edit capability based on
  Markdown approval. Do not claim a hard execution sandbox.
- Rollback consists of reverting only workflow-specific changes after review;
  never revert existing application changes.

## Verification commands

- `git diff --check`
- YAML frontmatter parse with an available local parser, if present; otherwise
  structural/manual review and disclose the limitation.
- Deterministic script or targeted checks for required files, agent links,
  prompt targets, and approval content.
- Application PHPUnit/integration/E2E suites are not required because no
  application code, database, or dependency changes are in scope.

## Approval

Approved by: user (explicit master prompt)
Date: 2026-10-13
Scope: workflow scaffolding only.
