<?php
/**
 * CLI smoke tests: php tests/run.php
 *
 * Loads the plugin helpers with minimal WordPress stubs and checks parsing, units, input
 * sanitising, path containment and SSRF protection. No WordPress installation is needed.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

if ('cli' !== PHP_SAPI) {
    exit;
}

$base = sys_get_temp_dir() . '/wpgpxmaps-tests-' . bin2hex(random_bytes(4));
$content = $base . '/wp-content';
$uploads = $content . '/uploads';
mkdir($uploads . '/gpx', 0755, true);
mkdir($base . '/outside', 0755, true);

define('ABSPATH', $base . '/');
define('WP_CONTENT_DIR', $content);
define('WPGPXMAPS_VERSION', 'test');
define('HOUR_IN_SECONDS', 3600);

$GLOBALS['wpgpxmaps_test_options'] = array();

class WP_Error
{
    public function __construct(public string $code = '', public string $message = '')
    {
    }
}

function is_wp_error($thing): bool
{
    return $thing instanceof WP_Error;
}
function apply_filters(string $tag, $value)
{
    return $value;
}
function add_action(...$args): void
{
}
function __($text, $domain = '')
{
    return $text;
}
function get_option(string $name, $default = false)
{
    return $GLOBALS['wpgpxmaps_test_options'][$name] ?? $default;
}
function register_setting(string $group, string $option, array $args = array()): void
{
    $GLOBALS['wpgpxmaps_test_registered'][$option] = $args;
}
function wp_upload_dir($time = null, $create = true): array
{
    return array('basedir' => WP_CONTENT_DIR . '/uploads', 'baseurl' => 'http://example.test/wp-content/uploads');
}
function untrailingslashit(string $value): string
{
    return rtrim($value, '/\\');
}
function content_url(): string
{
    return 'http://example.test/wp-content';
}
function wp_json_encode($value, int $flags = 0)
{
    return json_encode($value, $flags);
}
function wp_parse_url(string $url, int $component = -1)
{
    return parse_url($url, $component);
}
function wp_mkdir_p(string $dir): bool
{
    return is_dir($dir) || mkdir($dir, 0755, true);
}
function wp_delete_file(string $file): void
{
    @unlink($file);
}
function esc_url_raw(string $url, array $protocols = array()): string
{
    $scheme = parse_url($url, PHP_URL_SCHEME);
    return false !== filter_var($url, FILTER_VALIDATE_URL) && in_array($scheme, $protocols ?: array('http', 'https'), true) ? $url : '';
}
function sanitize_text_field(string $text): string
{
    return trim(strip_tags($text));
}

require __DIR__ . '/../wp-gpx-maps_settings.php';
require __DIR__ . '/../wp-gpx-maps_utils.php';
require __DIR__ . '/../wp-gpx-maps_shortcode.php';

$passed = 0;
$failed = 0;

function check(string $label, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        ++$passed;
        return;
    }
    ++$failed;
    echo "FAIL: {$label}\n";
}

function near(float $a, float $b, float $tolerance = 0.01): bool
{
    return abs($a - $b) <= $tolerance;
}

function write_gpx(string $path, string $body, string $extra_ns = ''): string
{
    file_put_contents($path, '<?xml version="1.0" encoding="UTF-8"?><gpx version="1.1" creator="test" xmlns="http://www.topografix.com/GPX/1/1" xmlns:gpxtpx="http://www.garmin.com/xmlschemas/TrackPointExtension/v1" xmlns:gpxx="http://www.garmin.com/xmlschemas/GpxExtensions/v3"' . $extra_ns . '>' . $body . '</gpx>');
    return $path;
}

$dir = $uploads . '/gpx';

// ---------------------------------------------------------------- parsing

$points = '';
for ($i = 0; $i < 6; $i++) {
    $lat = 46.0 + $i * 0.001;
    $time = gmdate('Y-m-d\TH:i:s\Z', 1700000000 + $i * 30);
    $points .= sprintf('<trkpt lat="%.5f" lon="11.00000"><ele>%d</ele><time>%s</time><extensions><gpxtpx:TrackPointExtension><gpxtpx:hr>%d</gpxtpx:hr><gpxtpx:atemp>12.5</gpxtpx:atemp><gpxtpx:cad>80</gpxtpx:cad></gpxtpx:TrackPointExtension></extensions></trkpt>', $lat, 100 + $i * 10, $time, 120 + $i);
}
$full = write_gpx($dir . '/full.gpx', '<wpt lat="46.002" lon="11.0"><name>Hut &lt;b&gt;</name><desc>Rest</desc><sym>Flag</sym></wpt><trk><trkseg>' . $points . '</trkseg></trk>');

$parsed = wpgpxmaps_parse_gpx($full, 0.0, 0);
check('parse returns array', is_array($parsed));
check('six points parsed', is_array($parsed) && 6 === count($parsed['lat']));
check('elevation range', is_array($parsed) && 100.0 === $parsed['minEle'] && 150.0 === $parsed['maxEle']);
check('climbing is positive', is_array($parsed) && near($parsed['totalEleUp'], 50.0));
check('length plausible (about 556 m)', is_array($parsed) && $parsed['totalLength'] > 540 && $parsed['totalLength'] < 580);
check('heart rate read', is_array($parsed) && 120.0 === $parsed['hr'][0] && 125.0 === $parsed['hr'][5]);
check('temperature and cadence read', is_array($parsed) && 12.5 === $parsed['atemp'][2] && 80.0 === $parsed['cad'][2]);
check('waypoint read', is_array($parsed) && 1 === count($parsed['waypoints']) && 'Hut <b>' === $parsed['waypoints'][0]['name']);
check('average speed positive', is_array($parsed) && $parsed['avgSpeed'] > 0);

$offset = wpgpxmaps_parse_gpx($full, 300.0, 0);
check('offset skips close points', is_array($offset) && count($offset['lat']) < 6);

$flat = wpgpxmaps_parse_gpx($full, 0.0, 1);
check('flat distance ignores altitude', is_array($flat) && $flat['totalLength'] <= $parsed['totalLength']);

// Segments.
$segments = write_gpx($dir . '/segments.gpx', '<trk><trkseg><trkpt lat="46.0" lon="11.0"/><trkpt lat="46.001" lon="11.0"/></trkseg><trkseg><trkpt lat="46.01" lon="11.01"/><trkpt lat="46.011" lon="11.01"/></trkseg></trk>');
$seg = wpgpxmaps_parse_gpx($segments, 0.0, 0);
check('segments separated by null', is_array($seg) && 5 === count($seg['lat']) && null === $seg['lat'][2]);
check('all arrays stay aligned', is_array($seg) && count($seg['dist']) === count($seg['lat']) && count($seg['hr']) === count($seg['lat']));

// Routes and Garmin route points.
$route = write_gpx($dir . '/route.gpx', '<rte><rtept lat="46.0" lon="11.0"/><rtept lat="46.002" lon="11.0"/></rte>');
$rte = wpgpxmaps_parse_gpx($route, 0.0, 0);
check('rte used when there is no trk', is_array($rte) && 2 === count($rte['lat']));

$garmin = write_gpx($dir . '/garmin.gpx', '<rte><rtept lat="46.0" lon="11.0"><extensions><gpxx:RoutePointExtension><gpxx:rpt lat="46.0001" lon="11.0001"/><gpxx:rpt lat="46.0004" lon="11.0004"/></gpxx:RoutePointExtension></extensions></rtept></rte>');
$rpt = wpgpxmaps_parse_gpx($garmin, 0.0, 0);
check('gpxx:rpt preferred over rtept', is_array($rpt) && 2 === count($rpt['lat']) && near($rpt['lat'][0], 46.0001, 0.00001));

$both = write_gpx($dir . '/both.gpx', '<rte><rtept lat="10.0" lon="10.0"/></rte><trk><trkseg><trkpt lat="46.0" lon="11.0"/><trkpt lat="46.001" lon="11.0"/></trkseg></trk>');
$prefer = wpgpxmaps_parse_gpx($both, 0.0, 0);
check('trk wins over rte', is_array($prefer) && 2 === count($prefer['lat']) && near($prefer['lat'][0], 46.0));

// Invalid coordinates and broken files.
$bad = write_gpx($dir . '/bad.gpx', '<trk><trkseg><trkpt lat="91" lon="11"/><trkpt lat="0" lon="0"/><trkpt lat="abc" lon="1"/><trkpt lat="46.0" lon="11.0"/></trkseg></trk>');
$badParsed = wpgpxmaps_parse_gpx($bad, 0.0, 0);
check('invalid coordinates dropped', is_array($badParsed) && 1 === count($badParsed['lat']));

file_put_contents($dir . '/doctype.gpx', '<?xml version="1.0"?><!DOCTYPE gpx [<!ENTITY x SYSTEM "file:///etc/passwd">]><gpx><trk><trkseg><trkpt lat="1" lon="1"><name>&x;</name></trkpt></trkseg></trk></gpx>');
check('DOCTYPE rejected', false === wpgpxmaps_parse_gpx($dir . '/doctype.gpx'));

file_put_contents($dir . '/broken.gpx', '<gpx><trk><trkseg><trkpt lat="46.0" lon="11.0"><ele>1');
$broken = wpgpxmaps_parse_gpx($dir . '/broken.gpx');
check('truncated XML does not crash', false === $broken || is_array($broken));

file_put_contents($dir . '/empty.gpx', '');
check('empty file rejected', false === wpgpxmaps_parse_gpx($dir . '/empty.gpx'));
check('missing file rejected', false === wpgpxmaps_parse_gpx($dir . '/missing.gpx'));

file_put_contents($dir . '/notgpx.gpx', 'this is not xml');
check('plain text rejected', false === wpgpxmaps_parse_gpx($dir . '/notgpx.gpx'));

// ------------------------------------------------- reduce, privacy, build

$long = '';
for ($i = 0; $i < 400; $i++) {
    $long .= sprintf('<trkpt lat="%.6f" lon="11.0"><ele>%d</ele></trkpt>', 46.0 + $i * 0.0002, 100 + ($i % 20));
}
$longFile = write_gpx($dir . '/long.gpx', '<trk><trkseg>' . $long . '</trkseg></trk>');
$longParsed = wpgpxmaps_parse_gpx($longFile, 0.0, 0);
check('long track parsed', is_array($longParsed) && 400 === count($longParsed['lat']));

$reduced = wpgpxmaps_reduce_track($longParsed, 100);
check('reduce keeps arrays aligned', count($reduced['lat']) === count($reduced['ele']) && count($reduced['lat']) < 400 && count($reduced['lat']) >= 100);
check('reduce keeps the last point', near((float) end($reduced['lat']), (float) end($longParsed['lat']), 0.000001));

$trimmed = wpgpxmaps_privacy_trim($longParsed);
check('privacy trim shortens the track', count($trimmed['lat']) < 400 && count($trimmed['lat']) === count($trimmed['dist']));
check('privacy trim hides the first point', $trimmed['lat'][0] > $longParsed['lat'][0]);

$tiny = wpgpxmaps_privacy_trim(array_fill_keys(wpgpxmaps_soa_keys(), array()));
check('privacy trim copes with an empty track', array() === $tiny['lat']);

$built = wpgpxmaps_build_track($parsed, false, false);
check('build marks available series', $built['has']['ele'] && $built['has']['hr'] && $built['has']['atemp'] && $built['has']['cad']);
check('extreme indexes found', 5 === $built['extremes']['maxEleIndex']);
check('map points are [lat, lon]', 2 === count($built['map'][0]));

// --------------------------------------------------------- load + cache

$result = wpgpxmaps_load_track('/wp-content/uploads/gpx/full.gpx', array('offset' => 0, 'reduce' => false, 'distance_type' => 0, 'privacy' => false));
check('load_track by relative path', is_array($result) && 6 === count($result['map']));
check('cache file written', array() !== glob($uploads . '/gpx/~cache/*.json'));
check('cache folder is guarded', is_file($uploads . '/gpx/~cache/.htaccess') && is_file($uploads . '/gpx/~cache/index.php'));
$again = wpgpxmaps_load_track('/wp-content/uploads/gpx/full.gpx', array('offset' => 0, 'reduce' => false, 'distance_type' => 0, 'privacy' => false));
check('second load comes from the cache unchanged', $again == $result);
wpgpxmaps_clear_cache();
check('clear cache removes tracks but keeps guards', array() === glob($uploads . '/gpx/~cache/*.json') && is_file($uploads . '/gpx/~cache/index.php'));

$error = wpgpxmaps_load_track('/wp-content/uploads/gpx/nope.gpx', array());
check('missing track gives WP_Error', is_wp_error($error) && 'wpgpxmaps_not_found' === $error->code);
$error = wpgpxmaps_load_track('', array());
check('empty source gives WP_Error', is_wp_error($error));
$error = wpgpxmaps_load_track('http://127.0.0.1/track.gpx', array());
check('private remote address blocked', is_wp_error($error) && 'wpgpxmaps_blocked' === $error->code);
$error = wpgpxmaps_load_track('/wp-content/uploads/gpx/notgpx.gpx', array());
check('unparsable file gives WP_Error', is_wp_error($error));

// ------------------------------------------------------------ path safety

file_put_contents($base . '/outside/secret.gpx', '<gpx/>');
check('traversal out of the content folder refused', false === wpgpxmaps_resolve_local_path('../outside/secret.gpx'));
check('absolute path outside refused', false === wpgpxmaps_resolve_local_path($base . '/outside/secret.gpx'));
check('wrong extension refused', false === wpgpxmaps_resolve_local_path('/wp-content/uploads/gpx/full.php'));
check('null byte refused', false === wpgpxmaps_resolve_local_path("/wp-content/uploads/gpx/full.gpx\0.txt"));
check('inside path accepted', false !== wpgpxmaps_resolve_local_path('/wp-content/uploads/gpx/full.gpx'));
if (function_exists('symlink') && @symlink($base . '/outside', $content . '/linked')) {
    check('symlinked folder inside wp-content accepted', false !== wpgpxmaps_resolve_local_path('/wp-content/linked/secret.gpx'));
    check('traversal through a symlink refused', false === wpgpxmaps_resolve_local_path('/wp-content/linked/../uploads/../../outside/secret.gpx'));
}
check('local url maps to file', false !== wpgpxmaps_url_to_local_path('http://example.test/wp-content/uploads/gpx/full.gpx?x=1'));
check('local path maps back to url', 'http://example.test/wp-content/uploads/gpx/full.gpx' === wpgpxmaps_local_path_to_url((string) realpath($dir . '/full.gpx')));
check('relative path helper', '/wp-content/uploads/gpx/' === wpgpxmaps_relative_path($dir));

// ------------------------------------------------------------------- SSRF

foreach (array('127.0.0.1', '10.1.2.3', '172.16.0.1', '192.168.1.1', '169.254.169.254', '100.64.0.1', '0.0.0.0', '::1', 'fc00::1', 'fe80::1') as $ip) {
    check("{$ip} is not public", false === wpgpxmaps_is_public_ip($ip));
}
foreach (array('8.8.8.8', '1.1.1.1', '2606:4700:4700::1111') as $ip) {
    check("{$ip} is public", true === wpgpxmaps_is_public_ip($ip));
}
foreach (array(
    'file:///etc/passwd',
    'ftp://8.8.8.8/a.gpx',
    'gopher://8.8.8.8/',
    'http://127.0.0.1/a.gpx',
    'http://localhost/a.gpx',
    'http://[::1]/a.gpx',
    'http://user:pass@8.8.8.8/a.gpx',
    'http://8.8.8.8:22/a.gpx',
    'javascript:alert(1)',
    '//8.8.8.8/a.gpx',
    "http://8.8.8.8/a\n.gpx",
) as $url) {
    check("blocked: {$url}", false === wpgpxmaps_validate_remote_url($url));
}
check('public IP url allowed', 'http://8.8.8.8/a.gpx' === wpgpxmaps_validate_remote_url('http://8.8.8.8/a.gpx'));
check('cheap check skips DNS but keeps IP literals blocked', false !== wpgpxmaps_validate_remote_url('http://localhost/a.gpx', false) && false === wpgpxmaps_validate_remote_url('http://10.0.0.1/a.gpx', false));

// ---------------------------------------------------------- input helpers

check('css size px', '450px' === wpgpxmaps_sanitize_css_size('450', '100%'));
check('css size percent', '80%' === wpgpxmaps_sanitize_css_size('80%', '100%'));
check('css size rejects injection', '100%' === wpgpxmaps_sanitize_css_size('100%;position:fixed', '100%'));
check('css size rejects expression', '100%' === wpgpxmaps_sanitize_css_size('expression(alert(1))', '100%'));
check('css size viewport units', '60dvh' === wpgpxmaps_sanitize_css_size('60dvh', '450px'));
check('color hex', '#3366cc' === wpgpxmaps_sanitize_color('#3366cc', '#000'));
check('color name', 'red' === wpgpxmaps_sanitize_color('red', '#000'));
check('color rejects injection', '#000' === wpgpxmaps_sanitize_color('red;}</style>', '#000'));
check('color list', array('#fff', 'blue') === wpgpxmaps_sanitize_color_list('#fff  blue', '#000'));
check('color list default', array('#000') === wpgpxmaps_sanitize_color_list('', '#000'));
check('bool strings', wpgpxmaps_bool('true') && !wpgpxmaps_bool('false') && !wpgpxmaps_bool('0') && wpgpxmaps_bool('1'));
check('bool default for empty', wpgpxmaps_bool('', true) && !wpgpxmaps_bool('', false));
check('num parses', 12.5 === wpgpxmaps_num('12.5') && null === wpgpxmaps_num('abc') && null === wpgpxmaps_num('') && null === wpgpxmaps_num('INF'));

// -------------------------------------------------------------- settings

check('setting bool', 'true' === wpgpxmaps_sanitize_setting('true', 'wpgpxmaps_skipcache') && '' === wpgpxmaps_sanitize_setting(null, 'wpgpxmaps_skipcache'));
check('setting enum falls back', 'HYBRID' === wpgpxmaps_sanitize_setting('OSM3', 'wpgpxmaps_map_type'));
check('setting enum case', 'ROADMAP' === wpgpxmaps_sanitize_setting('roadmap', 'wpgpxmaps_map_type'));
check('setting number clamps', '10000' === wpgpxmaps_sanitize_setting('99999', 'wpgpxmaps_pointsoffset'));
check('setting url rejects javascript', '' === wpgpxmaps_sanitize_setting('javascript:alert(1)', 'wpgpxmaps_map_start_icon'));
check('setting unknown option', '' === wpgpxmaps_sanitize_setting('x', 'unknown_option'));
check('setting key is stripped of tags', 'abc' === wpgpxmaps_sanitize_setting('<b>abc</b>', 'wpgpxmaps_googlemapsv3_apikey'));

// WordPress calls the registered sanitize callback with the value only; the option name must already be bound.
wpgpxmaps_register_settings();
$registered = $GLOBALS['wpgpxmaps_test_registered'] ?? array();
check('settings registered', count($registered) === count(wpgpxmaps_settings_schema()));
check('registered callback keeps enum value', 'SATELLITE' === ($registered['wpgpxmaps_map_type']['sanitize_callback'])('SATELLITE'));
check('registered callback keeps color', '#ff0000' === ($registered['wpgpxmaps_graph_line_color_hr']['sanitize_callback'])('#ff0000'));

$GLOBALS['wpgpxmaps_test_options'] = array('wpgpxmaps_privacymode' => '', 'wpgpxmaps_show_elevation' => '');
$opts = wpgpxmaps_shortcode_options(array('privacymode' => 'true', 'showele' => 'false', 'mtype' => 'osm1', 'mheight' => '300'));
check('shortcode privacymode attribute', true === $opts['privacy']);
check('shortcode "false" string is false', false === $opts['showele']);
check('shortcode map type normalised', 'OSM1' === $opts['mtype']);
check('shortcode height gets unit', '300px' === $opts['mheight']);
$opts = wpgpxmaps_shortcode_options(array('mtype' => 'OSM3', 'uom' => '9'));
check('unknown map type falls back', 'HYBRID' === $opts['mtype']);
check('unknown unit falls back', '0' === $opts['uom']);
check('stored empty option means off', false === wpgpxmaps_shortcode_options(array())['showele']);

// ------------------------------------------------------------------ units

check('km conversion', near(wpgpxmaps_convert_distance(5000, '2'), 5.0));
check('mile conversion', near(wpgpxmaps_convert_distance(1609.344, '1'), 1.0, 0.001));
check('nautical mile conversion', near(wpgpxmaps_convert_distance(1852, '3'), 1.0, 0.001));
check('feet conversion', near(wpgpxmaps_convert_elevation(100, '1'), 328.08, 0.01));
check('meters stay meters', 100.0 === wpgpxmaps_convert_elevation(100, '0'));
check('km/h', near((float) wpgpxmaps_convert_speed(10, '1'), 36.0));
check('pace of a standing point is null', null === wpgpxmaps_convert_speed(0, '3'));
check('pace 5:00 min/km', near((float) wpgpxmaps_convert_speed(1000 / 300, '3'), 5.0));
check('speed unit label', 'knots' === wpgpxmaps_speed_unit('5'));
check('duration format', '01:01:01' === wpgpxmaps_format_duration(3661));
check('negative duration clamps', '00:00:00' === wpgpxmaps_format_duration(-5));

// ----------------------------------------------------------------- finish

function rrmdir(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    foreach ((array) scandir($path) as $entry) {
        if ('.' !== $entry && '..' !== $entry) {
            rrmdir($path . '/' . $entry);
        }
    }
    @rmdir($path);
}
rrmdir($base);

echo "{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
