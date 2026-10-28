---
applyTo: "**"
---

# WP-GPX-Maps Plugin Guidelines

WordPress plugin that displays GPX tracks on maps with interactive altitude/speed/HR charts.

## PHP

- Procedural style only — no classes, no namespaces, no OOP
- Use WordPress hook system: `add_action()`, `add_filter()`, `add_shortcode()`
- Function naming: snake*case with plugin prefix (`wpgpxmaps*`, `handle*WP_GPX_Maps*`)
- Settings via `get_option()` / `update_option()` with sensible defaults; registered with `register_setting()`
- Translations via `__()` and `_e()` with text domain `wp-gpx-maps`
- PHP 8.3+: typed parameters/returns, `str_contains()`, `match` are fine

## JavaScript

- ES2017+ — `const`/`let`, arrow functions, template literals, `async`/`await`, destructuring are fine
- Vanilla JS in an IIFE with `'use strict'`; do not depend on jQuery
- Never put GPX/setting data into `innerHTML`; use `textContent` / DOM APIs
- Private members prefixed with underscore (`_latlng`, `_src`)
- Google Maps: load via `google.maps.importLibrary`; use `AdvancedMarkerElement` when a Map ID is set, fall back to classic `Marker`

## Security

- Always use nonces (`settings_fields()` / `wp_nonce_field()` / `wp_verify_nonce()`) and `current_user_can()` checks
- Sanitize all input: `sanitize_file_name()`, `sanitize_text_field()`, `wp_unslash()`
- Use `$wpdb->prepare()` for all SQL queries — never concatenate user input
- Remote GPX: validate URL scheme (http/https only), block private/reserved IP ranges (SSRF), size limit
- Local GPX: `realpath()` containment in uploads or `WP_CONTENT_DIR`, `.gpx` extension only
- Escape output: `esc_html()`, `esc_attr()`, `esc_url()`; JSON via `wp_json_encode()` with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`

## Error Handling

- Use try/catch for operations that can fail (file I/O, XML parsing, remote requests)
- Check remote responses with `is_wp_error()` before using them
- Do not use `error_log()` — fail silently or return safe defaults
- Never expose stack traces or internal paths to the user

## Architecture

- Target PHP 8.3+ and WordPress 7.1+
- No build tools — source files are served directly
- Conditional asset loading: only enqueue scripts/styles when a shortcode renders
- External libraries are self-hosted in `/js/` (Highcharts); Google Maps JS API is loaded from Google
- GPX parse results are cached as JSON in `uploads/gpx/~cache` (guarded against direct access)

## File Organization

- `wp-gpx-maps.php` — entry point, hooks registration, assets, install/upgrade
- `wp-gpx-maps_settings.php` — settings schema, sanitizing, activation/upgrade
- `wp-gpx-maps_shortcode.php` — shortcode handling and output
- `wp-gpx-maps_admin*.php` — admin UI (settings, tracks)
- `wp-gpx-maps_utils*.php` — shared utilities
- `WP-GPX-Maps.js` — frontend map/chart logic
- `css/wp-gpx-maps.css` — frontend styles
- `tests/run.php` — CLI smoke tests
