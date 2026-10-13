---
name: documentation-updates
description: "Use when updating README, project context, requirements, plans, architecture, ADRs, test guides, or session memory after approved behavior or decisions change."
---

# Documentation updates

## Inputs and context

Read current state/workflow, requested artifact, approved requirements, and
source/test evidence for claims.

## Procedure

1. Identify authoritative document and intended readers.
2. Verify commands, settings, paths, and behavior against repository sources.
3. Keep stable project facts in PROJECT-CONTEXT; active work in CURRENT-STATE;
   concise restart actions in SESSION-HANDOFF.
4. Put task-specific requirements, architecture, plans, and decisions in
   their respective directories.
5. Link instead of copying long content; exclude credentials and chat dumps.
6. Check links, formatting, stale claims, and approval status.

## Safety and completion

Do not change application behavior or assert unverified behavior. Preserve
existing guidance; surface conflicts rather than silently rewriting them.
