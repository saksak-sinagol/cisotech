# Architecture decision records

Record only decisions that materially affect architecture, security, data
integrity, compatibility, or long-term maintenance. Keep the record concise
and link it from the implementation plan.

Name new records `NNNN-short-title.md` and use:

```markdown
# ADR NNNN: Title

- Status: proposed | accepted | superseded | rejected
- Date: YYYY-MM-DD
- Decision owner:

## Context
## Decision
## Alternatives considered
## Consequences
## Approval
```

The initial workflow architecture is documented in
`docs/architecture/architecture.md`; no separate ADR was needed because this
bootstrap adds no application architecture or runtime dependency.
