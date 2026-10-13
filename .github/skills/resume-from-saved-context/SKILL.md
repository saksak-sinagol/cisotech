---
name: resume-from-saved-context
description: Resume work after a new chat or compaction by reconciling current state, handoff, approvals, worktree, and relevant task artifacts.
disable-model-invocation: true
argument-hint: "Optional objective or task reference"
---
Read `docs/ai/CURRENT-STATE.md`, `docs/ai/SESSION-HANDOFF.md`, and
`docs/ai/WORKFLOW.md`; then load only referenced relevant project context,
requirements, plan, and source. Check actual Git status and reconcile stale or
conflicting notes explicitly. Confirm the active phase and persisted approval
before acting; a summary/handoff never grants approval. State the exact next
action and ask if a gate or material decision is unclear. After meaningful
progress, update the current state and concise handoff without copying chat.
