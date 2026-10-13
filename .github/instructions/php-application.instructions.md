---
name: "PHP Application Practices"
description: "Use when investigating or changing CisoTech PHP pages, shared includes, authentication, forms, or server-rendered behavior."
applyTo: "{account,activity,departments,deployments,index,inventory,login,logout,messages,public_worklog,reports,settings,setup,staff,worklog}.php,includes/**/*.php"
---

# PHP application practices

- This is a PHP 8.1 server-rendered application using `mysqli`; do not add a
  framework or runtime dependency without approved requirements.
- Reuse the shared code in `includes/`, especially authentication, CSRF,
  validation, HTTP security, and error handling. Preserve prepared SQL.
- Escape values at HTML output boundaries with the existing `h()` helper.
- Keep authorization and CSRF checks on every state-changing route.
- Never log or render passwords, session identifiers, CSRF tokens, or raw
  request bodies. Use the shared structured error handler for unexpected
  failures.
- Keep valid existing form and JSON response behavior unless approved
  requirements explicitly change it.
- Add or update PHPUnit unit/HTTP integration tests for changed behavior. Use
  only isolated databases with the `cisotech_test_` prefix for integration
  tests; do not point tests at `.env`'s application database.
- See [PROJECT-CONTEXT.md](../../docs/ai/PROJECT-CONTEXT.md) and
  [WORKFLOW.md](../../docs/ai/WORKFLOW.md) for phase gates.
