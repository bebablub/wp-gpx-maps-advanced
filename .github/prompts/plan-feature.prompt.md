---
description: Plan a new feature (questions first, no code)
argument-hint: Feature to plan, e.g. GPX download button in the shortcode
agent: Planner
---

Plan this feature: ${input:feature}

Follow `.github/copilot-instructions.md`. Do not write code.

1. Read the relevant code first: `wp-gpx-maps.php` (shortcode, attributes, enqueueing), `wp-gpx-maps_utils.php` (GPX parsing), the admin files and `WP-GPX-Maps.js` parts the feature touches. Reuse existing helpers and patterns.
2. Ask me about every non-obvious decision with options and a recommendation, in one batch. Wait for answers.
3. Then give a short plan:
   - Scope and non-goals.
   - Files and functions to change or add, with approximate size.
   - Data flow (shortcode attribute or setting, PHP parsing, JSON output to JS, map/chart rendering).
   - New shortcode attributes and settings with their defaults, and how they are read (`wpgpxmaps_find_value()`).
   - Backward compatibility: existing shortcodes, saved options and already published pages must behave unchanged; PHP 8.3, ES2017+ and WordPress 7.1+ constraints.
   - Security: nonces, capabilities, sanitizing, escaping, untrusted GPX/EXIF input, remote requests.
   - Performance on large tracks and with several shortcodes on one page; conditional asset loading.
   - Behaviour with missing data (no elevation, time, heart rate, images) and when a remote service or Google Maps feature is unavailable.
   - UI flow, responsive behaviour, accessibility, translations (`.pot`).
   - Manual verification steps and README updates (there is no automated test suite).

Keep a todo list of the planning steps and open questions, updated as answers arrive. Be concise.
