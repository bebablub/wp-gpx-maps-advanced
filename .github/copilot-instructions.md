# WP-GPX-Maps Project Guidelines

## Architecture

WordPress plugin that renders GPX tracks as interactive maps + charts via the `[sgpx]` / `[sgpxf]` shortcodes.

**Key files:**

- `wp-gpx-maps.php` — entry point; constants, hooks, asset registration/enqueueing, install/upgrade
- `wp-gpx-maps_settings.php` — settings schema, sanitizing, activation/upgrade
- `wp-gpx-maps_shortcode.php` — `[sgpx]` / `[sgpxf]` handling, config array, HTML output
- `wp-gpx-maps_utils.php` — GPX parsing (`XMLReader`), path resolution, remote fetch, cache, unit conversion
- `wp-gpx-maps_utils_nggallery.php` — NextGEN gallery + EXIF/image-to-track matching helpers
- `wp-gpx-maps_admin.php` / `wp-gpx-maps_admin_settings.php` / `wp-gpx-maps_admin_tracks.php` — admin UI (Settings API)
- `WP-GPX-Maps.js` — frontend map + chart rendering (ES2017+, no jQuery, no build step)
- `css/wp-gpx-maps.css` — frontend styles
- `uninstall.php` — removes options and cache on plugin deletion
- `tests/run.php` — CLI smoke tests (`php tests/run.php`)

**Data flow:** shortcode → PHP parses GPX (cached as JSON) → config JSON in a `<script type="application/json">` block → JS loads Google Maps via `importLibrary` and renders the track; Highcharts renders charts.

## Requirements

PHP 8.3+, WordPress 7.1+. Latest stable Google Maps JS API (`quarterly` channel by default, configurable).

## Code Style

**PHP:** Procedural only. No classes, namespaces, or OOP. Prefix functions with `wpgpxmaps_` or `handle_WP_GPX_Maps_`. Use PHP 8.3 features where useful (typed signatures, `str_contains`, `match`).

**JS:** ES2017+ (`const`/`let`, arrow functions, template literals, `async`/`await`). Vanilla JS inside an IIFE with `'use strict'`; no jQuery dependency. Build DOM with `textContent`/`createElement`, never `innerHTML` with data from GPX files or settings.

## Build & Test

No build tools. Source files are served directly — edit and reload. Run `php tests/run.php` for the CLI smoke tests and `php -l` / `node --check` for syntax.

## Conventions

- **Settings:** stored via `get_option()` / `update_option()` with `wpgpxmaps_` prefix; registered through the Settings API. Read via `wpgpxmaps_find_value()` (shortcode attribute → option → default).
- **Assets:** registered early, enqueued only when a shortcode renders.
- **Google Maps markers:** `AdvancedMarkerElement` when a Map ID is configured, classic `Marker` otherwise.
- **Highcharts:** latest stable, self-hosted under `js/highcharts/`.
- **Translations:** `__()` / `_e()` with text domain `wp-gpx-maps`.

## Security

- Nonces and capability checks for all form submissions.
- Sanitize all input: `sanitize_text_field`, `sanitize_file_name`, `wp_unslash`.
- SQL via `$wpdb->prepare()` — never concatenate user input.
- GPX parsed with `XMLReader` (`LIBXML_NONET`, DOCTYPE rejected, size/point caps).
- Local GPX paths must resolve (`realpath`) inside the uploads folder or `WP_CONTENT_DIR` and end in `.gpx`.
- Remote GPX: `wp_safe_remote_get`, http/https only, private/reserved IP ranges blocked (SSRF), size limit.
- Escape all output: `esc_html`, `esc_attr`, `esc_url`; emit JSON with `wp_json_encode` + `JSON_HEX_*` flags.
- Never expose stack traces or internal paths. No `error_log()`.
