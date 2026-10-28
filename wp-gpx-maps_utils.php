<?php
/**
 * Shared helpers: input validation, GPX parsing, path/URL resolution, cache and unit conversion.
 *
 * @package WP-GPX-Maps
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WPGPXMAPS_CACHE_VERSION')) {
    define('WPGPXMAPS_CACHE_VERSION', 3);
}

require_once __DIR__ . '/wp-gpx-maps_utils_nggallery.php';

/* -------------------------------------------------------------------------
 * Input helpers
 * ---------------------------------------------------------------------- */

/**
 * Looks up a shortcode attribute (case-insensitive), then the stored option, then the default.
 */
function wpgpxmaps_find_value(array $attr, string $attribute, string $option, mixed $default): mixed
{
    $val = $attr[strtolower($attribute)] ?? ($attr[$attribute] ?? '');
    if (is_string($val)) {
        $val = trim($val);
    }
    if ('' === $val || null === $val) {
        $val = '' !== $option ? get_option($option, '') : '';
    }
    if ('' === $val || false === $val || null === $val) {
        $val = $default;
    }
    return $val;
}

/**
 * Same as wpgpxmaps_find_value() but returns a boolean. A stored empty option means "off"
 * once the option exists; an absent option falls back to the default.
 */
function wpgpxmaps_find_bool(array $attr, string $attribute, string $option, bool $default): bool
{
    $val = $attr[strtolower($attribute)] ?? ($attr[$attribute] ?? '');
    if (is_string($val)) {
        $val = trim($val);
    }
    if ('' !== $val && null !== $val) {
        return wpgpxmaps_bool($val, $default);
    }
    if ('' === $option) {
        return $default;
    }
    $stored = get_option($option, null);
    if (null === $stored || false === $stored) {
        return $default;
    }
    return wpgpxmaps_bool($stored, false);
}

function wpgpxmaps_bool(mixed $value, bool $default = false): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (null === $value || '' === $value) {
        return $default;
    }
    if (is_string($value)) {
        return in_array(strtolower(trim($value)), array('true', '1', 'yes', 'on'), true);
    }
    return (bool) $value;
}

/**
 * Accepts a CSS length such as "100%", "450px" or "30em". A bare number is treated as pixels.
 */
function wpgpxmaps_sanitize_css_size(mixed $value, string $default): string
{
    $value = strtolower(trim((string) $value));
    if (preg_match('/^\d+(\.\d+)?$/', $value)) {
        $value .= 'px';
    }
    if (preg_match('/^\d+(\.\d+)?(px|%|em|rem|ex|ch|pt|cm|mm|in|vh|vw|vmin|vmax|svh|dvh|lvh)$/', $value)) {
        return $value;
    }
    return $default;
}

/**
 * Accepts #rgb, #rrggbb, #rrggbbaa and plain colour names.
 */
function wpgpxmaps_sanitize_color(mixed $value, string $default): string
{
    $value = trim((string) $value);
    if (preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value) || preg_match('/^[a-z]{3,20}$/i', $value)) {
        return $value;
    }
    return $default;
}

/**
 * Splits a space separated list of colours (map line colours) into validated values.
 *
 * @return string[]
 */
function wpgpxmaps_sanitize_color_list(mixed $value, string $default): array
{
    $list = array();
    foreach (preg_split('/\s+/', trim((string) $value)) as $color) {
        if ('' !== $color) {
            $list[] = wpgpxmaps_sanitize_color($color, $default);
        }
    }
    return $list ? array_slice($list, 0, 50) : array($default);
}

/**
 * Returns a float for a numeric value, otherwise null.
 */
function wpgpxmaps_num(mixed $value): ?float
{
    if (is_string($value)) {
        $value = trim($value);
    }
    if ('' === $value || null === $value || !is_numeric($value)) {
        return null;
    }
    $number = (float) $value;
    return is_finite($number) ? $number : null;
}

/* -------------------------------------------------------------------------
 * Paths and folders
 * ---------------------------------------------------------------------- */

function wpgpxmaps_max_gpx_size(): int
{
    return max(1024, (int) apply_filters('wpgpxmaps_max_gpx_size', 50 * 1024 * 1024));
}

function wpgpxmaps_max_points(): int
{
    return max(100, (int) apply_filters('wpgpxmaps_max_points', 100000));
}

/**
 * Directory that contains wp-content; relative GPX paths in shortcodes are resolved against it.
 */
function wpgpxmaps_site_root(): string
{
    if (defined('WP_CONTENT_DIR')) {
        return rtrim(str_replace('\\', '/', dirname(WP_CONTENT_DIR)), '/');
    }
    return rtrim(str_replace('\\', '/', ABSPATH), '/');
}

/**
 * Real paths of the folders a GPX file may live in.
 *
 * @return string[]
 */
function wpgpxmaps_allowed_roots(): array
{
    $roots = array();
    $uploads = wp_upload_dir(null, false);
    if (!empty($uploads['basedir'])) {
        $roots[] = $uploads['basedir'];
    }
    if (defined('WP_CONTENT_DIR')) {
        $roots[] = WP_CONTENT_DIR;
    }
    $roots = (array) apply_filters('wpgpxmaps_allowed_gpx_roots', $roots);

    $real = array();
    foreach ($roots as $root) {
        $resolved = is_string($root) ? realpath($root) : false;
        if (false !== $resolved) {
            $real[] = rtrim(str_replace('\\', '/', $resolved), '/');
        }
    }
    return array_values(array_unique($real));
}

function wpgpxmaps_path_is_allowed(string $real_path): bool
{
    $real_path = str_replace('\\', '/', $real_path);
    foreach (wpgpxmaps_allowed_roots() as $root) {
        if (str_starts_with($real_path, $root . '/')) {
            return true;
        }
    }
    return false;
}

/**
 * Resolves the "gpx" attribute of a local track to a real file path, or false when the file does not exist
 * or lives outside the allowed folders.
 */
function wpgpxmaps_resolve_local_path(string $src): string|false
{
    $src = trim(str_replace('\\', '/', $src));
    $src = strtok($src, '?#');
    if ('' === $src || str_contains($src, "\0")) {
        return false;
    }

    $candidates = array($src); // already an absolute filesystem path

    // URL path under wp-content (handles subfolder installs and moved wp-content)
    $content_url_path = untrailingslashit((string) wp_parse_url(content_url(), PHP_URL_PATH));
    if ('' !== $content_url_path && str_starts_with($src, $content_url_path . '/')) {
        $candidates[] = WP_CONTENT_DIR . substr($src, strlen($content_url_path));
    }
    // Plain "/wp-content/..." even if content_url differs
    if (str_starts_with($src, '/wp-content/')) {
        $candidates[] = WP_CONTENT_DIR . substr($src, strlen('/wp-content'));
    }
    // Relative to the site root / ABSPATH
    $candidates[] = untrailingslashit(ABSPATH) . '/' . ltrim($src, '/');

    // Full URL on this site
    if (preg_match('#^https?://#i', $src) && str_starts_with($src, content_url())) {
        $candidates[] = WP_CONTENT_DIR . substr($src, strlen(content_url()));
    }

    foreach ($candidates as $candidate) {
        $real = realpath($candidate);
        if (false !== $real && is_file($real) && is_readable($real)) {
            return $real;
        }
    }
    return false;
}

/**
 * Maps a URL on this site (uploads or content folder) to its file path so no HTTP request is needed.
 */
function wpgpxmaps_url_to_local_path(string $url): string|false
{
    $url = strtok($url, '?#');
    $uploads = wp_upload_dir(null, false);
    $pairs = array();
    if (!empty($uploads['baseurl']) && !empty($uploads['basedir'])) {
        $pairs[] = array($uploads['baseurl'], $uploads['basedir']);
    }
    if (defined('WP_CONTENT_DIR') && function_exists('content_url')) {
        $pairs[] = array(content_url(), WP_CONTENT_DIR);
    }
    foreach ($pairs as list($base_url, $base_dir)) {
        $base_url = preg_replace('#^https?:#i', '', rtrim($base_url, '/')) . '/';
        $check = preg_replace('#^https?:#i', '', $url);
        if (str_starts_with(strtolower($check), strtolower($base_url))) {
            $relative = rawurldecode(substr($check, strlen($base_url)));
            $real = realpath(rtrim($base_dir, '/\\') . '/' . $relative);
            if (false !== $real && 'gpx' === strtolower(pathinfo($real, PATHINFO_EXTENSION)) && is_file($real) && wpgpxmaps_path_is_allowed($real)) {
                return $real;
            }
        }
    }
    return false;
}

/**
 * Folder the current user may upload GPX files to ('' when not permitted).
 */
function wpgpxmaps_gpx_folder_path(): string
{
    $uploads = wp_upload_dir(null, false);
    if (empty($uploads['basedir'])) {
        return '';
    }
    $base = rtrim($uploads['basedir'], '/\\') . '/gpx';
    if (current_user_can('manage_options')) {
        return $base;
    }
    if (current_user_can('publish_posts')) {
        $login = preg_replace('#[/\\\\]|\.\.#', '', wp_get_current_user()->user_login);
        return '' !== $login ? $base . '/' . $login : '';
    }
    return '';
}

function wpgpxmaps_cache_dir(): string
{
    $uploads = wp_upload_dir(null, false);
    return rtrim($uploads['basedir'] ?? '', '/\\') . '/gpx/~cache';
}

/**
 * Path of a folder relative to the site root, with a trailing slash ("/wp-content/uploads/gpx/").
 */
function wpgpxmaps_relative_path(string $path): string
{
    $root = wpgpxmaps_site_root();
    $path = str_replace('\\', '/', $path);
    if (str_starts_with($path, $root)) {
        $path = substr($path, strlen($root));
    }
    return rtrim($path, '/') . '/';
}

/**
 * Public URL of a local GPX file, or '' when it cannot be mapped.
 */
function wpgpxmaps_local_path_to_url(string $real_path): string
{
    $real_path = str_replace('\\', '/', $real_path);
    $uploads = wp_upload_dir(null, false);
    $pairs = array();
    if (!empty($uploads['basedir'])) {
        $pairs[] = array($uploads['basedir'], $uploads['baseurl']);
    }
    if (defined('WP_CONTENT_DIR')) {
        $pairs[] = array(WP_CONTENT_DIR, content_url());
    }
    foreach ($pairs as list($dir, $url)) {
        $dir = rtrim(str_replace('\\', '/', realpath($dir) ?: $dir), '/');
        if (str_starts_with($real_path, $dir . '/')) {
            return rtrim($url, '/') . '/' . implode('/', array_map('rawurlencode', explode('/', substr($real_path, strlen($dir) + 1))));
        }
    }
    return '';
}

/* -------------------------------------------------------------------------
 * Cache
 * ---------------------------------------------------------------------- */

/**
 * Creates the cache folder with files that block direct access. Returns false when it cannot be created.
 */
function wpgpxmaps_ensure_cache_dir(): bool
{
    $dir = wpgpxmaps_cache_dir();
    if (!is_dir($dir) && !wp_mkdir_p($dir)) {
        return false;
    }
    $guards = array(
        'index.php' => "<?php\n// Silence is golden.\n",
        '.htaccess' => "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n",
        'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
    );
    foreach ($guards as $name => $content) {
        if (!file_exists($dir . '/' . $name)) {
            @file_put_contents($dir . '/' . $name, $content); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }
    }
    return is_writable($dir);
}

function wpgpxmaps_cache_key(array $parts): string
{
    return md5(wp_json_encode($parts));
}

function wpgpxmaps_cache_read(string $key, int $ttl = 0): ?array
{
    $file = wpgpxmaps_cache_dir() . '/' . $key . '.json';
    if (!is_file($file)) {
        return null;
    }
    if ($ttl > 0 && (time() - (int) filemtime($file)) > $ttl) {
        return null;
    }
    $size = filesize($file);
    if (false === $size || $size > 256 * 1024 * 1024) {
        return null;
    }
    $json = file_get_contents($file);
    $data = false === $json ? null : json_decode($json, true);
    if (!is_array($data) || ($data['v'] ?? null) !== WPGPXMAPS_CACHE_VERSION) {
        return null;
    }
    return $data;
}

function wpgpxmaps_cache_write(string $key, array $data): void
{
    if (!wpgpxmaps_ensure_cache_dir()) {
        return;
    }
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if (false === $json) {
        return;
    }
    $file = wpgpxmaps_cache_dir() . '/' . $key . '.json';
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (false !== @file_put_contents($tmp, $json, LOCK_EX)) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
        if (!@rename($tmp, $file)) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
            @unlink($tmp); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }
    }
}

/**
 * Deletes cached tracks (the guard files stay in place).
 */
function wpgpxmaps_clear_cache(): void
{
    $dir = wpgpxmaps_cache_dir();
    if (!is_dir($dir)) {
        return;
    }
    foreach (array('*.json', '*.tmp') as $pattern) {
        foreach ((array) glob($dir . '/' . $pattern) as $file) {
            if (is_file($file)) {
                @unlink($file); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            }
        }
    }
}

/* -------------------------------------------------------------------------
 * Remote GPX
 * ---------------------------------------------------------------------- */

function wpgpxmaps_is_public_ip(string $ip): bool
{
    if (false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return false;
    }
    if (false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $long = ip2long($ip);
        // Carrier-grade NAT range 100.64.0.0/10 is not covered by the PHP flags.
        if (false !== $long && $long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255')) {
            return false;
        }
    }
    return true;
}

/**
 * Returns the cleaned URL when it is an http(s) URL pointing to public hosts only, otherwise false.
 * With $resolve false host names are not looked up (cheap check for cache keys); IP literals are always checked.
 */
function wpgpxmaps_validate_remote_url(string $url, bool $resolve = true): string|false
{
    $url = str_replace(' ', '%20', trim($url));
    if ('' === $url || strlen($url) > 2048 || preg_match('/[\x00-\x1f\x7f]/', $url)) {
        return false;
    }
    $parts = wp_parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }
    if (!in_array(strtolower($parts['scheme']), array('http', 'https'), true) || isset($parts['user']) || isset($parts['pass'])) {
        return false;
    }
    if (isset($parts['port']) && !in_array((int) $parts['port'], array(80, 443, 8080, 8443), true)) {
        return false;
    }

    $host = trim($parts['host'], '[]');
    if (false !== filter_var($host, FILTER_VALIDATE_IP)) {
        return wpgpxmaps_is_public_ip($host) ? $url : false;
    }
    if (!$resolve) {
        return $url;
    }

    $ips = array();
    $ipv4 = gethostbynamel($host);
    if (is_array($ipv4)) {
        $ips = $ipv4;
    }
    if (function_exists('dns_get_record')) {
        $ipv6 = @dns_get_record($host, DNS_AAAA); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        if (is_array($ipv6)) {
            foreach ($ipv6 as $record) {
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }
    }
    if (!$ips) {
        return false;
    }
    foreach ($ips as $ip) {
        if (!wpgpxmaps_is_public_ip($ip)) {
            return false;
        }
    }
    return $url;
}

/**
 * Downloads a remote GPX into a temporary file and returns its path. The caller must delete it.
 */
function wpgpxmaps_fetch_remote(string $url): string|false
{
    $url = wpgpxmaps_validate_remote_url($url);
    if (false === $url) {
        return false;
    }
    if (!function_exists('wp_tempnam')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    $tmp = wp_tempnam('wpgpxmaps.gpx');
    if (!$tmp) {
        return false;
    }
    $max = wpgpxmaps_max_gpx_size();
    $response = wp_safe_remote_get(
        $url,
        array(
            'timeout' => 10,
            'redirection' => 3,
            'stream' => true,
            'filename' => $tmp,
            'limit_response_size' => $max + 1,
            'user-agent' => 'WP-GPX-Maps/' . (defined('WPGPXMAPS_VERSION') ? WPGPXMAPS_VERSION : '3'),
        )
    );
    if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
        wp_delete_file($tmp);
        return false;
    }
    clearstatcache(true, $tmp);
    $size = filesize($tmp);
    if (false === $size || 0 === $size || $size > $max) {
        wp_delete_file($tmp);
        return false;
    }
    return $tmp;
}

/* -------------------------------------------------------------------------
 * GPX parsing
 * ---------------------------------------------------------------------- */

/**
 * Keys of the parallel per-point arrays. A null entry in every array marks a track segment break.
 *
 * @return string[]
 */
function wpgpxmaps_soa_keys(): array
{
    return array('dt', 'lat', 'lon', 'ele', 'dist', 'speed', 'hr', 'atemp', 'cad', 'grade');
}

function wpgpxmaps_to_radians(float $degrees): float
{
    return $degrees * M_PI / 180;
}

/**
 * Distance between two points in meters. $distance_type: 0 = 3D, 1 = flat, 2 = elevation difference only.
 */
function wpgpxmaps_calculate_distance(float $lat1, float $lon1, float $ele1, float $lat2, float $lon2, float $ele2, int $distance_type): float
{
    if (2 === $distance_type) {
        return $ele1 - $ele2;
    }
    $alpha = sin(wpgpxmaps_to_radians($lat2 - $lat1) / 2);
    $beta = sin(wpgpxmaps_to_radians($lon2 - $lon1) / 2);
    $a = $alpha * $alpha + cos(wpgpxmaps_to_radians($lat1)) * cos(wpgpxmaps_to_radians($lat2)) * $beta * $beta;
    $flat = 2 * 6369628.75 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    if (1 === $distance_type) {
        return $flat;
    }
    return sqrt(($ele1 - $ele2) ** 2 + $flat ** 2);
}

/**
 * Parses an ISO 8601 time (fractional seconds supported) to a float timestamp.
 */
function wpgpxmaps_parse_time(string $value): ?float
{
    $value = trim($value);
    if ('' === $value) {
        return null;
    }
    $timestamp = strtotime($value);
    if (false === $timestamp) {
        return null;
    }
    $fraction = 0.0;
    if (preg_match('/:\d{2}\.(\d+)/', $value, $m)) {
        $fraction = (float) ('0.' . $m[1]);
    }
    return $timestamp + $fraction;
}

function wpgpxmaps_collector_new(): array
{
    $c = array(
        'open' => false,
        'first' => true,
        'segPoints' => 0,
        'count' => 0,
        'lastLat' => 0.0,
        'lastLon' => 0.0,
        'lastEle' => null,
        'lastTime' => null,
        'lastOffset' => 0.0,
        'speedSum' => 0.0,
        'speedCount' => 0,
        'runDist' => 0.0,
        'up' => 0.0,
        'down' => 0.0,
    );
    foreach (wpgpxmaps_soa_keys() as $key) {
        $c[$key] = array();
    }
    return $c;
}

function wpgpxmaps_collector_end(array &$c): void
{
    if (!$c['open']) {
        return;
    }
    $c['open'] = false;
    if ($c['segPoints'] > 0) {
        foreach (wpgpxmaps_soa_keys() as $key) {
            $c[$key][] = null;
        }
    }
}

function wpgpxmaps_collector_begin(array &$c): void
{
    wpgpxmaps_collector_end($c);
    $c['open'] = true;
    $c['first'] = true;
    $c['segPoints'] = 0;
    $c['lastLat'] = 0.0;
    $c['lastLon'] = 0.0;
    $c['lastEle'] = null;
    $c['lastTime'] = null;
    $c['lastOffset'] = 0.0;
    $c['speedSum'] = 0.0;
    $c['speedCount'] = 0;
}

function wpgpxmaps_collector_push(array &$c, ?int $dt, float $lat, float $lon, ?float $ele, float $dist, float $speed, ?float $hr, ?float $atemp, ?float $cad, float $grade): void
{
    $c['dt'][] = $dt;
    $c['lat'][] = $lat;
    $c['lon'][] = $lon;
    $c['ele'][] = null === $ele ? null : round($ele, 2);
    $c['dist'][] = round($dist, 2);
    $c['speed'][] = round($speed, 1);
    $c['hr'][] = $hr;
    $c['atemp'][] = $atemp;
    $c['cad'][] = $cad;
    $c['grade'][] = round($grade, 2);
    ++$c['segPoints'];
    ++$c['count'];
}

/**
 * Adds one raw GPX point. Points closer than $min_offset meters to the last stored point are skipped
 * (their speed is averaged into the next stored point).
 */
function wpgpxmaps_collector_add(array &$c, float $lat, float $lon, ?float $ele, ?float $time, float $speed, ?float $hr, ?float $atemp, ?float $cad, float $min_offset, int $distance_type): void
{
    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180 || (0.0 === $lat && 0.0 === $lon)) {
        return;
    }
    if (!$c['open']) {
        wpgpxmaps_collector_begin($c);
    }
    $dt = null === $time ? null : (int) floor($time);

    if ($c['first']) {
        $c['first'] = false;
        wpgpxmaps_collector_push($c, $dt, $lat, $lon, $ele, $c['runDist'], 0.0, $hr, $atemp, $cad, 0.0);
    } else {
        $offset = wpgpxmaps_calculate_distance(
            $lat,
            $lon,
            $ele ?? $c['lastEle'] ?? 0.0,
            $c['lastLat'],
            $c['lastLon'],
            $c['lastEle'] ?? $ele ?? 0.0,
            $distance_type
        );
        $c['runDist'] += $offset;

        if (0.0 === $speed && null !== $time && null !== $c['lastTime']) {
            $elapsed = $time - $c['lastTime'];
            if ($elapsed > 0) {
                $speed = $offset / $elapsed;
            }
        }

        $grade = 0.0;
        if (null !== $ele && null !== $c['lastEle']) {
            $delta = $ele - $c['lastEle'];
            if ($delta > 0) {
                $c['up'] += $delta;
            } else {
                $c['down'] += $delta;
            }
            $grade = 0.0 === $offset ? 0.0 : max(-100.0, min(100.0, $delta / $offset * 100));
        }

        $c['speedSum'] += $speed;
        ++$c['speedCount'];

        if ($offset + $c['lastOffset'] > $min_offset) {
            wpgpxmaps_collector_push($c, $dt, $lat, $lon, $ele, $c['runDist'], $c['speedSum'] / $c['speedCount'], $hr, $atemp, $cad, $grade);
            $c['speedSum'] = 0.0;
            $c['speedCount'] = 0;
            $c['lastOffset'] = 0.0;
        } else {
            $c['lastOffset'] += $offset;
        }
    }

    $c['lastLat'] = $lat;
    $c['lastLon'] = $lon;
    $c['lastEle'] = $ele;
    $c['lastTime'] = $time;
}

/**
 * Streams a GPX file with XMLReader (no DOM in memory, no DTD, no external entities).
 *
 * Track points (trk/trkpt) win over Garmin route points (gpxx:rpt), which win over route points (rtept).
 * Returns false when the file is missing, too large, not XML or has a DOCTYPE.
 */
function wpgpxmaps_parse_gpx(string $path, float $min_offset = 10.0, int $distance_type = 0): array|false
{
    $size = is_file($path) ? filesize($path) : false;
    if (false === $size || 0 === $size || $size > wpgpxmaps_max_gpx_size()) {
        return false;
    }

    $previous_errors = libxml_use_internal_errors(true);
    $reader = new XMLReader();
    $result = false;

    try {
        if (!$reader->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) {
            return false;
        }

        $max_points = wpgpxmaps_max_points();
        $trk = wpgpxmaps_collector_new();
        $rpt = wpgpxmaps_collector_new();
        $rte = wpgpxmaps_collector_new();
        $waypoints = array();
        $truncated = false;
        $cur = null;
        $has_root = false;

        while ($reader->read()) {
            $node_type = $reader->nodeType;

            if (XMLReader::DOC_TYPE === $node_type) {
                return false;
            }

            if (XMLReader::ELEMENT === $node_type) {
                $name = $reader->localName;
                $has_root = true;

                switch ($name) {
                    case 'trk':
                    case 'trkseg':
                        wpgpxmaps_collector_begin($trk);
                        break;
                    case 'rte':
                        wpgpxmaps_collector_begin($rte);
                        break;
                    case 'RoutePointExtension':
                        if (!$rpt['open']) {
                            wpgpxmaps_collector_begin($rpt);
                        }
                        break;
                    case 'rpt':
                        $rlat = wpgpxmaps_num($reader->getAttribute('lat'));
                        $rlon = wpgpxmaps_num($reader->getAttribute('lon'));
                        if (null !== $rlat && null !== $rlon) {
                            wpgpxmaps_collector_add($rpt, $rlat, $rlon, null, null, 0.0, null, null, null, $min_offset, $distance_type);
                        }
                        break;
                    case 'trkpt':
                    case 'rtept':
                    case 'wpt':
                        $cur = array(
                            'kind' => $name,
                            'lat' => wpgpxmaps_num($reader->getAttribute('lat')),
                            'lon' => wpgpxmaps_num($reader->getAttribute('lon')),
                            'ele' => null,
                            'time' => null,
                            'speed' => 0.0,
                            'hr' => null,
                            'atemp' => null,
                            'cad' => null,
                            'name' => '',
                            'desc' => '',
                            'sym' => '',
                            'type' => '',
                        );
                        if ($reader->isEmptyElement) {
                            wpgpxmaps_finish_point($cur, $trk, $rte, $waypoints, $min_offset, $distance_type);
                            $cur = null;
                        }
                        break;
                    default:
                        if (null === $cur || $reader->isEmptyElement) {
                            break;
                        }
                        switch ($name) {
                            case 'ele':
                                $cur['ele'] = wpgpxmaps_num($reader->readString());
                                break;
                            case 'time':
                                $cur['time'] = wpgpxmaps_parse_time($reader->readString());
                                break;
                            case 'speed':
                                $cur['speed'] = max(0.0, wpgpxmaps_num($reader->readString()) ?? 0.0);
                                break;
                            case 'hr':
                                $cur['hr'] = wpgpxmaps_num($reader->readString());
                                break;
                            case 'atemp':
                                $cur['atemp'] = wpgpxmaps_num($reader->readString());
                                break;
                            case 'cad':
                                $cur['cad'] = wpgpxmaps_num($reader->readString());
                                break;
                            case 'name':
                            case 'desc':
                            case 'sym':
                            case 'type':
                                if ('wpt' === $cur['kind']) {
                                    $cur[$name] = mb_substr(trim($reader->readString()), 0, 2000);
                                }
                                break;
                        }
                }
            } elseif (XMLReader::END_ELEMENT === $node_type) {
                switch ($reader->localName) {
                    case 'trk':
                    case 'trkseg':
                        wpgpxmaps_collector_end($trk);
                        break;
                    case 'rte':
                        wpgpxmaps_collector_end($rte);
                        wpgpxmaps_collector_end($rpt);
                        break;
                    case 'trkpt':
                    case 'rtept':
                    case 'wpt':
                        if (null !== $cur) {
                            wpgpxmaps_finish_point($cur, $trk, $rte, $waypoints, $min_offset, $distance_type);
                            $cur = null;
                        }
                        break;
                }
                if ($trk['count'] + $rpt['count'] + $rte['count'] >= $max_points) {
                    $truncated = true;
                    break;
                }
            }
        }

        if (!$has_root) {
            return false;
        }

        wpgpxmaps_collector_end($trk);
        wpgpxmaps_collector_end($rpt);
        wpgpxmaps_collector_end($rte);

        if ($trk['count'] > 0) {
            $chosen = $trk;
        } elseif ($rpt['count'] > 0) {
            $chosen = $rpt;
        } else {
            $chosen = $rte;
        }
        $result = wpgpxmaps_collector_to_track($chosen, $waypoints, $truncated);
    } catch (Throwable $e) {
        $result = false;
    } finally {
        $reader->close();
        libxml_clear_errors();
        libxml_use_internal_errors($previous_errors);
    }

    return $result;
}

/**
 * Stores a completed trkpt / rtept / wpt element.
 */
function wpgpxmaps_finish_point(array $cur, array &$trk, array &$rte, array &$waypoints, float $min_offset, int $distance_type): void
{
    if (null === $cur['lat'] || null === $cur['lon']) {
        return;
    }
    if ('wpt' === $cur['kind']) {
        if (count($waypoints) < 1000 && abs($cur['lat']) <= 90 && abs($cur['lon']) <= 180) {
            $waypoints[] = array(
                'lat' => $cur['lat'],
                'lon' => $cur['lon'],
                'ele' => $cur['ele'] ?? 0.0,
                'name' => $cur['name'],
                'desc' => $cur['desc'],
                'sym' => $cur['sym'],
                'type' => $cur['type'],
            );
        }
        return;
    }
    if ('trkpt' === $cur['kind']) {
        wpgpxmaps_collector_add($trk, $cur['lat'], $cur['lon'], $cur['ele'], $cur['time'], $cur['speed'], $cur['hr'], $cur['atemp'], $cur['cad'], $min_offset, $distance_type);
    } else {
        wpgpxmaps_collector_add($rte, $cur['lat'], $cur['lon'], $cur['ele'], $cur['time'], $cur['speed'], $cur['hr'], $cur['atemp'], $cur['cad'], $min_offset, $distance_type);
    }
}

/**
 * Converts the chosen collector into the parse result and computes the totals.
 */
function wpgpxmaps_collector_to_track(array $c, array $waypoints, bool $truncated): array
{
    $keys = wpgpxmaps_soa_keys();
    $track = array();
    foreach ($keys as $key) {
        $track[$key] = $c[$key];
    }
    // Drop the trailing segment separator.
    while ($track['lat'] && null === $track['lat'][array_key_last($track['lat'])]) {
        foreach ($keys as $key) {
            array_pop($track[$key]);
        }
    }

    $ele = array_filter($track['ele'], static fn($v) => null !== $v);
    $time = array_filter($track['dt'], static fn($v) => null !== $v);
    $dist = array_filter($track['dist'], static fn($v) => null !== $v);
    $speed = array_filter($track['speed'], static fn($v) => null !== $v && $v > 0);

    $track['maxEle'] = $ele ? (float) max($ele) : 0.0;
    $track['minEle'] = $ele ? (float) min($ele) : 0.0;
    $track['avgEle'] = $ele ? array_sum($ele) / count($ele) : 0.0;
    $track['totalEleUp'] = $c['up'];
    $track['totalEleDown'] = $c['down'];
    $track['totalLength'] = $dist ? (float) max($dist) : 0.0;
    $track['maxTime'] = $time ? (int) max($time) : 0;
    $track['minTime'] = $time ? (int) min($time) : 0;
    $track['avgSpeed'] = $speed ? array_sum($speed) / count($speed) : 0.0;
    $track['waypoints'] = $waypoints;
    $track['truncated'] = $truncated;
    return $track;
}

/**
 * Reduces a track to roughly $target points (segment separators and the last point of a segment are kept).
 */
function wpgpxmaps_reduce_track(array $track, int $target = 3200): array
{
    $count = count($track['lat']);
    if ($count <= $target) {
        return $track;
    }
    $step = (int) round($count / $target);
    if ($step <= 1) {
        return $track;
    }
    $keep = array();
    for ($i = 0; $i < $count; $i++) {
        if (null === $track['lat'][$i] || 0 === $i % $step || null === ($track['lat'][$i + 1] ?? null)) {
            $keep[] = $i;
        }
    }
    foreach (wpgpxmaps_soa_keys() as $key) {
        $reduced = array();
        foreach ($keep as $i) {
            $reduced[] = $track[$key][$i];
        }
        $track[$key] = $reduced;
    }
    return $track;
}

/**
 * Removes the first and last few percent of the points so start and end of the track stay private.
 */
function wpgpxmaps_privacy_trim(array $track): array
{
    $n = count($track['lat']);
    if ($n < 1) {
        return $track;
    }
    $remove = (int) ceil($n * 0.05);
    if ($remove < 100 && $n * 0.5 > 100) {
        $remove = 100;
    } elseif ($remove > 500) {
        $remove = 500;
    }
    $remove = min($remove, $n);
    $start = intdiv($remove, 2);
    $length = max(0, $n - $remove);

    $keys = wpgpxmaps_soa_keys();
    foreach ($keys as $key) {
        $track[$key] = array_slice($track[$key], $start, $length);
    }
    while ($track['lat'] && null === $track['lat'][0]) {
        foreach ($keys as $key) {
            array_shift($track[$key]);
        }
    }
    while ($track['lat'] && null === $track['lat'][array_key_last($track['lat'])]) {
        foreach ($keys as $key) {
            array_pop($track[$key]);
        }
    }
    return $track;
}

/**
 * Moving time, climbing time and climbing distance.
 */
function wpgpxmaps_movement_metrics(array $track): array
{
    $moving = 0;
    $climbing = 0;
    $climb_len = 0.0;
    $n = count($track['dt']);
    for ($i = 1; $i < $n; $i++) {
        $t_prev = $track['dt'][$i - 1];
        $t_curr = $track['dt'][$i];
        if (null === $t_prev || null === $t_curr || $t_curr <= $t_prev) {
            continue;
        }
        $delta = $t_curr - $t_prev;
        if (($track['speed'][$i] ?? 0) > 0.5) {
            $moving += $delta;
        }
        if (($track['grade'][$i] ?? 0) > 0) {
            $climbing += $delta;
            $step = ($track['dist'][$i] ?? 0) - ($track['dist'][$i - 1] ?? 0);
            if ($step > 0) {
                $climb_len += $step;
            }
        }
    }
    return array(
        'moving' => $moving,
        'climbing' => $climbing,
        'climbSpeed' => $climbing > 0 ? $climb_len / $climbing : 0.0,
    );
}

/**
 * Turns the parse result into the structure that is cached and rendered: SI units, aligned arrays,
 * null entries at segment breaks.
 */
function wpgpxmaps_build_track(array $parsed, bool $reduce, bool $privacy): array
{
    if ($reduce) {
        $parsed = wpgpxmaps_reduce_track($parsed);
    }
    $metrics = wpgpxmaps_movement_metrics($parsed);
    $work = $privacy ? wpgpxmaps_privacy_trim($parsed) : $parsed;

    $map = array();
    $has = array('ele' => false, 'speed' => false, 'hr' => false, 'atemp' => false, 'cad' => false, 'grade' => false);
    $max_ele_idx = -1;
    $max_speed_idx = -1;
    $max_ele_val = null;
    $max_speed_val = null;

    $n = count($work['lat']);
    for ($i = 0; $i < $n; $i++) {
        if (null === $work['lat'][$i]) {
            $map[] = null;
            continue;
        }
        $map[] = array(round($work['lat'][$i], 7), round($work['lon'][$i], 7));

        $ele = $work['ele'][$i];
        if (null !== $ele) {
            if (0.0 !== (float) $ele) {
                $has['ele'] = true;
            }
            if (null === $max_ele_val || $ele > $max_ele_val) {
                $max_ele_val = $ele;
                $max_ele_idx = $i;
            }
        }
        $speed = $work['speed'][$i];
        if (null !== $speed && $speed > 0) {
            $has['speed'] = true;
            if (null === $max_speed_val || $speed > $max_speed_val) {
                $max_speed_val = $speed;
                $max_speed_idx = $i;
            }
        }
        foreach (array('hr', 'atemp', 'cad', 'grade') as $key) {
            $value = $work[$key][$i];
            if (null !== $value && 0.0 !== (float) $value) {
                $has[$key] = true;
            }
        }
    }

    return array(
        'v' => WPGPXMAPS_CACHE_VERSION,
        'map' => $map,
        'dt' => $work['dt'],
        'dist' => $work['dist'],
        'ele' => $work['ele'],
        'speed' => $work['speed'],
        'hr' => $work['hr'],
        'atemp' => $work['atemp'],
        'cad' => $work['cad'],
        'grade' => $work['grade'],
        'has' => $has,
        'extremes' => array(
            'maxEleIndex' => $max_ele_idx,
            'maxSpeedIndex' => $max_speed_idx,
        ),
        'stats' => array(
            'maxEle' => $parsed['maxEle'],
            'minEle' => $parsed['minEle'],
            'avgEle' => $parsed['avgEle'],
            'totalEleUp' => $parsed['totalEleUp'],
            'totalEleDown' => $parsed['totalEleDown'],
            'totalLength' => $parsed['totalLength'],
            'avgSpeed' => $parsed['avgSpeed'],
            'maxTime' => $parsed['maxTime'],
            'minTime' => $parsed['minTime'],
            'moving' => $metrics['moving'],
            'climbing' => $metrics['climbing'],
            'climbSpeed' => $metrics['climbSpeed'],
        ),
        'waypoints' => $parsed['waypoints'],
        'truncated' => $parsed['truncated'],
    );
}

/**
 * Loads a track from a local path, a URL on this site or a remote URL, using the cache.
 *
 * @param array $opts offset (float), reduce (bool), distance_type (int), privacy (bool), skipcache (bool)
 */
function wpgpxmaps_load_track(string $source, array $opts): array|WP_Error
{
    $source = trim($source);
    if ('' === $source) {
        return new WP_Error('wpgpxmaps_no_source', 'No gpx found');
    }

    $offset = (float) ($opts['offset'] ?? 10);
    $reduce = (bool) ($opts['reduce'] ?? false);
    $distance_type = (int) ($opts['distance_type'] ?? 0);
    $privacy = (bool) ($opts['privacy'] ?? false);
    $skip_cache = (bool) ($opts['skipcache'] ?? false);

    $path = false;
    $remote = false;
    if (preg_match('#^https?://#i', $source)) {
        $path = wpgpxmaps_url_to_local_path($source);
        if (false === $path) {
            $remote = wpgpxmaps_validate_remote_url($source, false);
            if (false === $remote) {
                return new WP_Error('wpgpxmaps_blocked', 'Invalid or blocked GPX url');
            }
        }
    } else {
        $path = wpgpxmaps_resolve_local_path($source);
        if (false === $path) {
            return new WP_Error('wpgpxmaps_not_found', 'GPX file not found');
        }
    }

    $ttl = 0;
    if (false !== $remote) {
        $identity = array($remote);
        $ttl = max(60, (int) apply_filters('wpgpxmaps_remote_cache_ttl', 12 * HOUR_IN_SECONDS));
    } else {
        $identity = array($path, (int) filemtime($path), (int) filesize($path));
    }
    $key = wpgpxmaps_cache_key(array_merge($identity, array(WPGPXMAPS_CACHE_VERSION, $offset, $reduce, $distance_type, $privacy)));

    if (!$skip_cache) {
        $cached = wpgpxmaps_cache_read($key, $ttl);
        if (null !== $cached) {
            return $cached;
        }
    }

    $temp = false;
    if (false !== $remote) {
        $temp = wpgpxmaps_fetch_remote($remote);
        if (false === $temp) {
            return new WP_Error('wpgpxmaps_fetch', 'GPX file could not be loaded');
        }
        $path = $temp;
    }

    $parsed = wpgpxmaps_parse_gpx($path, $offset, $distance_type);
    if (false !== $temp) {
        wp_delete_file($temp);
    }
    if (false === $parsed) {
        return new WP_Error('wpgpxmaps_parse', 'GPX file could not be read');
    }
    if (!$parsed['lat'] && !$parsed['waypoints']) {
        return new WP_Error('wpgpxmaps_empty', 'GPX file contains no track data');
    }

    $track = wpgpxmaps_build_track($parsed, $reduce, $privacy);
    if (!$skip_cache) {
        wpgpxmaps_cache_write($key, $track);
    }
    return $track;
}

/* -------------------------------------------------------------------------
 * Units
 * ---------------------------------------------------------------------- */

const WPGPXMAPS_M_TO_MILES = 0.000621371192;
const WPGPXMAPS_M_TO_FEET = 3.2808399;
const WPGPXMAPS_M_TO_NM = 1 / 1852;

/**
 * Distance in the chart unit: 0 m, 1 mi, 2 km, 3 NM, 4 mi, 5 NM.
 */
function wpgpxmaps_convert_distance(float $meters, string $uom): float
{
    return match ($uom) {
        '1', '4' => $meters * WPGPXMAPS_M_TO_MILES,
        '2' => $meters / 1000,
        '3', '5' => $meters * WPGPXMAPS_M_TO_NM,
        default => $meters,
    };
}

/**
 * Elevation in the chart unit: feet for 1 and 5, meters otherwise.
 */
function wpgpxmaps_convert_elevation(float $meters, string $uom): float
{
    return in_array($uom, array('1', '5'), true) ? $meters * WPGPXMAPS_M_TO_FEET : $meters;
}

function wpgpxmaps_format_distance(float $meters, string $uom): string
{
    $unit = match ($uom) {
        '1', '4' => 'mi',
        '2' => 'km',
        '3', '5' => 'NM',
        default => 'm',
    };
    $decimals = in_array($uom, array('1', '2', '3', '4', '5'), true) ? 2 : 0;
    return round(wpgpxmaps_convert_distance($meters, $uom), $decimals) . ' ' . $unit;
}

function wpgpxmaps_format_elevation(float $meters, string $uom): string
{
    $feet = in_array($uom, array('1', '5'), true);
    return round(wpgpxmaps_convert_elevation($meters, $uom), 0) . ($feet ? ' ft' : ' m');
}

/**
 * Pace as minutes.seconds (5.30 = 5 min 30 s).
 */
function wpgpxmaps_pace(float $minutes): float
{
    $total = (int) round($minutes * 60);
    return intdiv($total, 60) + ($total % 60) / 100;
}

/**
 * Speed in the chosen unit, or null when the speed is zero and the unit is a pace.
 * 0 m/s, 1 km/h, 2 mi/h, 3 min/km, 4 min/mi, 5 knots, 6 min/100m.
 */
function wpgpxmaps_convert_speed(float $mps, string $uomspeed): ?float
{
    switch ($uomspeed) {
        case '1':
            return $mps * 3.6;
        case '2':
            return $mps * 2.2369362920544025;
        case '5':
            return $mps * 1.94384449;
        case '3':
        case '4':
        case '6':
            if ($mps <= 0) {
                return null;
            }
            return match ($uomspeed) {
                '3' => wpgpxmaps_pace(1000 / $mps / 60),
                '4' => wpgpxmaps_pace(1609.344 / $mps / 60),
                default => 100 / $mps / 60,
            };
        default:
            return $mps;
    }
}

function wpgpxmaps_speed_unit(string $uomspeed): string
{
    return match ($uomspeed) {
        '1' => 'km/h',
        '2' => 'mi/h',
        '3' => 'min/km',
        '4' => 'min/mi',
        '5' => 'knots',
        '6' => 'min/100m',
        default => 'm/s',
    };
}

function wpgpxmaps_format_speed(float $mps, string $uomspeed): string
{
    $value = wpgpxmaps_convert_speed($mps, $uomspeed);
    return (null === $value ? '-' : number_format($value, 2, '.', '')) . ' ' . wpgpxmaps_speed_unit($uomspeed);
}

function wpgpxmaps_format_duration(int $seconds): string
{
    $seconds = max(0, $seconds);
    return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
}
