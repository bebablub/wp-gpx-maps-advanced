---
applyTo: "**"
---
# WP-GPX-Maps Plugin Guidelines

WordPress plugin that displays GPX tracks on maps with interactive altitude/speed/HR charts.

## PHP

- Procedural style only — no classes, no namespaces, no OOP
- Use WordPress hook system: `add_action()`, `add_filter()`, `add_shortcode()`
- Function naming: snake_case with plugin prefix (`wpgpxmaps_`, `handle_WP_GPX_Maps_`)
- Settings via `get_option()` / `update_option()` with sensible defaults
- Translations via `__()` and `_e()` with text domain `wp-gpx-maps`

## JavaScript

- ES5 only — no `let`, `const`, arrow functions, template literals, or destructuring
- jQuery IIFE pattern: `(function ($) { ... })(jQuery);`
- Use `var` for all declarations
- camelCase for function names, underscore-prefix for private members (`latlng_`, `src_`)
- Google Maps: feature-detect `AdvancedMarkerElement`, fall back to classic `Marker`

## Security

- Always use `wp_nonce_field()` / `wp_verify_nonce()` for form submissions
- Sanitize all input: `sanitize_file_name()`, `sanitize_text_field()`, `wp_unslash()`
- Use `$wpdb->prepare()` for all SQL queries — never concatenate user input
- Tile proxy: validate URL scheme (http/https only), block private IP ranges (SSRF)
- Escape output: `esc_html()`, `esc_attr()`, `esc_url()`

## Error Handling

- Use try/catch for operations that can fail (file I/O, XML parsing, remote requests)
- Check remote responses with `is_wp_error()` before using them
- Do not use `error_log()` — fail silently or return safe defaults
- Never expose stack traces or internal paths to the user

## Architecture

- Target PHP 5.6+ — no features requiring PHP 7.1+ (nullable types, void return, etc.)
- No build tools — source files are served directly
- Keep backward compatibility with WordPress 2.0.0+
- Conditional asset loading: only enqueue scripts/styles when shortcode is present
- External libraries are self-hosted in `/js/` (Highcharts, bootstrap-table, Chart.js)
- Tile caching: 7-day expiry for proxied map tiles

## File Organization

- `wp-gpx-maps.php` — entry point, hooks registration
- `wp-gpx-maps_admin*.php` — admin UI (settings, tracks)
- `wp-gpx-maps_utils*.php` — shared utilities
- `wp-gpx-maps_tileproxy.php` — map tile proxy with caching
- `WP-GPX-Maps.js` — frontend map/chart logic
