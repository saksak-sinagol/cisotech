---
name: security-review
description: "Use when performing an explicit security review or reviewing security-sensitive changes involving authentication, authorization, CSRF, SQL, XSS, secrets, uploads, sessions, CORS, or logging."
---

# Security review

## Inputs and context

Read workflow/current state, authorized review scope/diff, project context,
relevant security instructions, and only affected trust boundaries.

## Procedure

1. Confirm review scope and authorization.
2. Trace attacker-controlled input through validation, authorization, storage,
   logging, and output.
3. Check authentication/session/CSRF, prepared SQL, output encoding, access
   checks, secret handling, upload rules, and error disclosure as relevant.
4. Report only evidence-backed issues with file/line, preconditions, impact,
   severity, confidence, and limitations.
5. Separate confirmed vulnerabilities from hardening suggestions; offer fixes
   only after the user authorizes implementation.

## Safety and completion

Read-only. Do not inspect secrets/private keys or production data, run
destructive/exploit operations, or claim speculation as a finding. Complete
with a bounded evidence-based report or explicit no-findings statement.
