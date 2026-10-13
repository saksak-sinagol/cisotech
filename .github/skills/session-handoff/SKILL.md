---
name: session-handoff
description: "Use when ending or resuming a chat, after context compaction, or when transferring work between agents; persist a concise, actionable handoff without copying the conversation."
---

# Session handoff and context recovery

## Inputs and context

Read `docs/ai/CURRENT-STATE.md`, `docs/ai/SESSION-HANDOFF.md`, and only the
referenced approved task artifacts.

## Procedure

1. On resume, compare phase, objective, approval, worktree, and verification
   state with current files; flag conflicts/staleness.
2. Before handoff, record objective, completed work, decisions, relevant
   references, remaining tasks/blockers, actual test evidence, and exact next
   action.
3. Name receiver and artifacts for agent handoffs; list gate conditions not
   satisfied.
4. Update state and handoff only with verified facts; omit secrets and
   unnecessary personal details.
5. Keep the note short enough to load without replaying prior sessions.

## Safety and completion

Do not infer approval or completion from a previous message summary alone.
Confirm persisted records. Never store credentials, tokens, raw logs with
sensitive values, or long transcripts.
