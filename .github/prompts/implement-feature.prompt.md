---
description: Implement the agreed feature plan and self-review
argument-hint: Feature to implement, e.g. GPX download button in the shortcode
agent: Implementer
---

Implement this feature according to the agreed plan: ${input:feature}

Follow `.github/copilot-instructions.md` and `.github/instructions/wp-gpx-maps.instructions.md`. The result must be product ready: robust, performant, secure, edge cases covered, backward compatible, and a UI that is easy to understand and consistent with the existing map, charts and settings pages.

- Follow the plan. If a larger change than planned turns out to be necessary, ask first.
- Reuse existing helpers and patterns (`findValue()`, `get_option()`/`update_option()` with the `wpgpxmaps_` prefix, existing shortcode attribute handling, GPX parsing in `wp-gpx-maps_utils.php`). Don't over-engineer: no speculative options, abstractions, or error handling for impossible states.
- New shortcode attributes and settings: add a default, read them via `findValue()`, add the admin field with sanitizing in the settings save handler, and keep existing shortcodes unchanged without the new attribute.
- PHP: procedural, PHP 5.6 compatible, `wpgpxmaps_` prefix. JS: ES5, `var`, jQuery IIFE. No `error_log()`.
- New admin forms use a nonce and capability check; new strings use `__()`/`_e()` with `wp-gpx-maps` and are added to `languages/wp-gpx-maps.pot`.
- New scripts and styles load only when the shortcode is present.
- Keep a todo list from the start: one item in progress, mark items done immediately, and add new items as work appears.
- Validate: `php -l` on edited PHP, `node --check` on edited JS, `get_errors`. There is no test suite; list what must be verified manually (shortcode on a page, several shortcodes on one page, admin save, browser console).
- Review your own diff against the plan and fix any findings. Remove anything over-engineered, dead or unrelated. Update `README.md` for user-visible changes.
- Keep answers concise.
