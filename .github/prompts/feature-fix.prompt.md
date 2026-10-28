---
description: Fix a bug or small defect with minimal edits, validate, then self-review
argument-hint: Problem to fix, e.g. elevation chart ignores privacy mode
agent: Bugfixer
---

Fix this problem: ${input:problem}

Follow `.github/copilot-instructions.md` and `.github/instructions/wp-gpx-maps.instructions.md`. The result must be robust, secure and backward compatible, with minimal edits and no behaviour change beyond the fix.

- Find and fix the root cause; no workarounds.
- Locate code by area:
  - Shortcode, asset enqueueing, hooks: `wp-gpx-maps.php`.
  - GPX parsing, distance/elevation/speed helpers: `wp-gpx-maps_utils.php`; NextGEN images: `wp-gpx-maps_utils_nggallery.php`.
  - Admin: `wp-gpx-maps_admin.php`, `wp-gpx-maps_admin_settings.php`, `wp-gpx-maps_admin_tracks.php`.
  - Remote fetch and cache: `wp-gpx-maps_utils.php`.
  - Frontend map and charts: `WP-GPX-Maps.js` (the file enqueued by `wp-gpx-maps.php`); check whether `js/WP-GPX-Maps.js` needs the same change.
- Check how the setting or attribute reaches the code (`wpgpxmaps_find_value()`, shortcode attribute, stored option) before changing it.
- For production issues, check whether the bug already existed in the previous release (`git log -p`, `git show <rev>:<file>`) before blaming recent changes.
- Validate: `php -l` on edited PHP files, `node --check` on edited JS, and `get_errors`. There is no automated test suite, so state any manual check still needed (shortcode on a page, browser console, chart interaction).
- Keep a todo list: one item in progress, mark items done immediately, and add items as findings appear.
- Briefly review your own diff:
  - The root cause is addressed.
  - No regressions for existing shortcodes and saved settings.
  - Still works with missing data (no elevation, time, heart rate, images), empty or invalid GPX, and several shortcodes on one page.
  - PHP 8.3 and ES2017+ compatible; escaping, sanitizing and nonces intact; nothing reformatted or unrelated.

  Fix any findings.

- Keep answers concise: cause and fix only.
