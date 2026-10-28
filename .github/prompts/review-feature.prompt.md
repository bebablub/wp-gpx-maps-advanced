---
description: Review an implemented feature against the plan and fix findings
argument-hint: Feature to review, e.g. GPX download button in the shortcode
agent: Reviewer
---

Review this feature: ${input:feature}

Follow `.github/copilot-instructions.md` and `.github/instructions/wp-gpx-maps.instructions.md`. Start from `git status` and `git diff`. Verify:

- **Complete:** fully implemented according to the plan, with nothing missing, unrelated, dead or over-engineered; no debug output or `error_log()`.
- **Style and compatibility:**
  - PHP is procedural, prefixed, and PHP 5.6 compatible.
  - JS is ES5 (`var`, no arrows, template literals or destructuring) inside the jQuery IIFE.
  - Existing shortcodes, saved options and published pages behave unchanged; new attributes and settings have defaults.
- **Secure:**
  - Nonce and capability checks on every admin form and endpoint.
  - Input sanitized (`sanitize_text_field`, `sanitize_file_name`, `wp_unslash`), output escaped, `$wpdb->prepare` used.
  - GPX parsed with `LIBXML_NONET`; untrusted GPX/EXIF values validated.
  - Remote requests and the tile proxy validate the scheme (http/https) and block private IP ranges; `is_wp_error()` checked.
  - No stack traces or internal paths exposed.
- **Robust:**
  - Handles missing elevation, time, heart rate, images, empty or invalid GPX.
  - Several shortcodes on one page work; Google Maps `AdvancedMarkerElement` is feature-detected with a classic `Marker` fallback; Highcharts v11 and v3.0.10 paths both work.
- **Performant:** large tracks handled without needless loops, no new per-request remote calls, assets enqueued only when the shortcode is present, tile cache expiry intact.
- **UI/UX:** easy to understand, responsive down to 320 px, accessible, consistent with existing settings pages. Strings use `wp-gpx-maps` and are in `languages/wp-gpx-maps.pot`.
- **Docs and validation:** `README.md` updated for user-visible changes; version bump only when requested (`wp-gpx-maps.php` header, the enqueue version strings and README stable tag together); `php -l`, `node --check` and `get_errors` are clean; manual verification steps are listed.

Keep a todo list: one item per check above, plus one per finding, and mark items done immediately. Fix every finding. Keep answers concise: list findings and fixes only.
