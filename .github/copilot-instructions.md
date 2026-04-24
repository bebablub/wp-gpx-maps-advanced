# WP-GPX-Maps Project Guidelines

## Architecture

WordPress plugin that renders GPX tracks as interactive maps + charts via the `[sgpx]` shortcode.

**Key files:**
- `wp-gpx-maps.php` — entry point; shortcode registration, asset enqueueing (`wpgpxmaps_detect_shortcodes`), activation hook
- `wp-gpx-maps_utils.php` — GPX parsing (`simplexml_load_file` + `LIBXML_NONET`), coordinate/elevation helpers
- `wp-gpx-maps_admin.php` / `wp-gpx-maps_admin_settings.php` / `wp-gpx-maps_admin_tracks.php` — admin UI
- `WP-GPX-Maps.js` — frontend map + chart rendering (ES5, jQuery IIFE)
- `wp-gpx-maps_tileproxy.php` — proxied map tile cache (7-day expiry)

**Data flow:** shortcode → PHP parses GPX → outputs JSON to JS → Google Maps renders track + Highcharts renders charts.

## Code Style

**PHP:** Procedural only. No classes, namespaces, or OOP. Prefix functions with `wpgpxmaps_` or `handle_WP_GPX_Maps_`. PHP 5.6+ compatible (no nullable types, no void returns).

**JS:** ES5 only — `var` only, no `let`/`const`, no arrow functions, no template literals. jQuery IIFE: `(function ($) { ... })(jQuery);`. Private members prefixed with `_`.

## Build & Test

No build tools. Source files served directly—edit and reload. No automated test suite.

## Conventions

- **Settings:** stored via `get_option()` / `update_option()` with `wpgpxmaps_` prefix. Read via `findValue()` which falls back to stored option.
- **Assets:** only enqueued when shortcode is detected on the current page.
- **Google Maps markers:** feature-detect `AdvancedMarkerElement`, fall back to classic `Marker`.
- **Highcharts:** v11 primary; v3.0.10 legacy fallback; both self-hosted under `js/highcharts/`.
- **Translations:** `__()` / `_e()` with text domain `wp-gpx-maps`.

## Security

- Nonces for all form submissions (`wp_nonce_field` / `wp_verify_nonce`).
- Sanitize all input: `sanitize_text_field`, `sanitize_file_name`, `wp_unslash`.
- SQL via `$wpdb->prepare()` — never concatenate user input.
- GPX parsed with `LIBXML_NONET` to block external entity requests.
- Tile proxy: validate URL scheme (http/https only), block private IP ranges (SSRF prevention).
- Escape all output: `esc_html`, `esc_attr`, `esc_url`.
- Never expose stack traces or internal paths.
