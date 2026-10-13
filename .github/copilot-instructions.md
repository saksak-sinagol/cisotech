# Rules
- Make small, focused changes. One task at a time.
- Do not change behavior unless the task says so.
- Follow the existing code style and structure.
- Never hardcode secrets; use environment variables.
- Ask before installing dependencies, deleting files, or changing the database schema.
- After changes, run build, lint, and tests, and report the results.
- Summarize every change: files touched and why.

## Workflow and project context

- Follow [docs/ai/WORKFLOW.md](../docs/ai/WORKFLOW.md) and check
  [docs/ai/CURRENT-STATE.md](../docs/ai/CURRENT-STATE.md) before substantive work.
- Load only context relevant to the task; do not scan the full repository by default.
- Do not edit application code during IDEA, QUESTIONING, or PLANNING.
- Do not start execution until requirements and the implementation plan record
  explicit user approval. If approval is absent or ambiguous, stop and ask.
- Preserve existing uncommitted changes. Never inspect or disclose `.env`,
  credentials, or local TLS private keys.
- Base verification claims on commands actually run. Record failures, skipped
  checks, and known limitations explicitly.
- Update current state and the session handoff after meaningful milestones.

See [PROJECT-CONTEXT.md](../docs/ai/PROJECT-CONTEXT.md) for verified stack and
architecture facts. See the scoped PHP and database instructions under
`.github/instructions/`.