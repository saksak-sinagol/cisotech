# UI quality implementation plan

Status: APPROVED by user, 2026-10-13.
Date: 2026-10-13

Requirements: [UI quality requirements](../requirements/ui-quality.md).

## Ordered tasks

| # | Task | Main files/areas | Verification |
|---|---|---|---|
| 1 | Make focus and text contrast accessible | `assets/css/style.css` | **Complete.** Contrast matrix passed; Chromium confirmed 3px keyboard focus outline. |
| 2 | Improve announced validation, errors, success, and submit state | `includes/footer.php`, shared page templates, `login.php`, `worklog.php`, `messages.php`, `public_worklog.php` | **Complete.** Accessible-name/feedback checks, submit-state behavior, PHP syntax, and unit tests passed. |
| 3 | Make shared menus keyboard/screen-reader accessible | `includes/header.php`, `includes/footer.php` | **Complete.** Chromium verified expanded state, inert closed navigation, Escape dismissal, and focus return. |
| 4 | Tighten narrow-screen layout where controls currently compress or overflow | `assets/css/style.css`, `messages.php`, `public_worklog.php` | **Complete.** Chromium verified document width at 320px/375px/768px; data tables scroll within labeled regions. |
| 5 | Re-validate empty/error states and image alternative text | First-party templates | **Complete.** Existing empty states retained and made more legible through the palette; no first-party rendered images were found. |

## Risks and boundaries

- Global palette adjustments affect status badges and existing pages; verify
  contrast and distinguishability without changing status meanings.
- Preserve native browser validation and existing POST behavior.
- Do not add JavaScript dependencies or change data/business rules.
- No first-party rendered `<img>` elements were found during inspection.
- Browser/E2E validation depends on an available local app and isolated test
  setup; do not point tests at a development or production database.
- No application changes begin until this plan is explicitly approved.

## Approval

Approved by: user
Date: 2026-10-13
Scope: UI quality review and the ordered changes above.

## Execution and validation record

- Added visible keyboard focus rings; removed focus-outline suppression.
- Updated muted, accent, and error colors. Verified representative normal-text
  contrast against page/panel/raised-panel surfaces at 4.65:1 or higher,
  action text at 5.08:1, and focus indicator at 5.56:1. These are static color
  calculations for the palette; they do not replace a full WCAG audit.
- Added accessible names to login, work-log, message-composer, and public
  filter controls; made server-rendered error/success feedback and toast
  announcements semantic; exposed submit progress through a persistent polite
  live region; disabled spinner/transition motion for reduced-motion users.
- Added state announcements and Escape/focus behavior to the shared menus.
- Fixed public work-log table overflow: before the fix, the page expanded to
  723px at 320px and 375px; after the fix, document width matched viewport
  width and each table scrolls inside a labeled region.
- Local Playwright/Chromium checks passed for public and message layouts at
  320px, 375px, and 768px; navigation/account-menu keyboard behaviors; and
  accessible submit state/reduced motion.
- Review follow-up: moved submit progress announcements into the persistent
  `formSubmitStatus` live region because a changed button accessible name alone
  was not a reliable announcement mechanism. Verified the region text and
  semantics in Chromium.
- `composer test`: passed, 36 tests / 186 assertions.
- `composer lint:php`: passed, 0 files needing fixes across 75 PHP files.
- `php -l` passed for changed PHP files; `git diff --check` passed.
- The authenticated full-app E2E suite was not run; it was not needed for the
  isolated markup/layout checks. No production database was used.
- Review status: UI change review passed after the live-region correction.
