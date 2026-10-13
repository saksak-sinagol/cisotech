---
name: codebase-investigation
description: "Use when investigating how an existing PHP page, API-like route, database flow, test, or configuration behaves; trace a concrete question without scanning the whole repository."
---

# Codebase investigation

## Inputs and context

Read current state, project context, and the target/question. Search names and
call sites before opening files; inspect authoritative code/tests/config only.

## Procedure

1. Define the precise behavior or symbol to trace and bounded surfaces.
2. Search via repository/code intelligence; follow includes, callers, data
   writes, responses, and relevant tests.
3. Verify conclusions against implementation and test evidence.
4. Record file references, line locations, edge cases, and uncertainty.
5. Update context only if a stable verified project fact is newly established.

## Safety and completion

Do not read `.env`, secrets, private keys, production data, or unrelated
directories. Do not edit code during discovery/planning. Stop when the concrete
trace is answered; do not delegate a continuous trace or repeat completed
searches without new evidence.
