---
name: "Database and Migration Safety"
description: "Use when changing database.sql, creating or reviewing migrate_v*.php scripts, modifying persistence, or planning schema rollout and rollback."
applyTo: "database.sql,migrate_v*.php"
---

# Database and migration safety

- MariaDB/MySQL with `mysqli` is the application persistence boundary.
- State the schema impact, deployment order, compatibility window, backup
  needs, and rollback or forward-recovery procedure before implementation.
- Do not run migrations or alter a live database without explicit user
  authorization.
- Preserve existing rows and foreign-key behavior. Prefer additive,
  repeatable migrations where compatible with the repository's established
  patterns.
- Update fresh-install schema and existing-install migration paths together.
- Verify schema changes only against a clearly isolated test database; do not
  read or copy local database credentials.
