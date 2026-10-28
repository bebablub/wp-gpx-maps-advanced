<?php
/**
 * [sgpx] and [sgpxf] shortcodes.
 *
 * @package WP-GPX-Maps
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Translated message for a track loading error code.
 */
function wpgpxmaps_error_message(string $code): string
{
    return match ($code) {
        'wpgpxmaps_no_source' => __('No GPX file specified.', 'wp-gpx-maps'),
        'wpgpxmaps_not_found' => __('The GPX file was not found.', 'wp-gpx-maps'),
        'wpgpxmaps_blocked' => __('The GPX address is not allowed.', 'wp-gpx-maps'),
        'wpgpxmaps_fetch' => __('The GPX file could not be downloaded.', 'wp-gpx-maps'),
        'wpgpxmaps_empty' => __('The GPX file contains no track data.', 'wp-gpx-maps'),
        default => __('The GPX file could not be read.', 'wp-gpx-maps'),
    };
}

function wpgpxmaps_error_html(string $code): string
{
    return '<div class="wpgpxmaps_error">' . esc_html(wpgpxmaps_error_message($code)) . '</div>';
}

/**
 * Icon URL (media library attachment named "map-marker-<sym>") for a waypoint symbol.
 */
function wpgpxmaps_waypoint_icon_url(string $sym): string
{
    static $cache = array();
    $slug = sanitize_title($sym);
    if ('' === $slug) {
        return '';
    }
    if (!isset($cache[$slug])) {
        global $wpdb;
        $id = (int) $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_name = %s LIMIT 1", 'map-marker-' . $slug));
        $cache[$slug] = $id > 0 ? (string) wp_get_attachment_url($id) : '';
    }
    return $cache[$slug];
}

function wpgpxmaps_icon_url(mixed $value): string
{
    return esc_url_raw(trim((string) $value), array('http', 'https'));
}

/**
 * Resolves every shortcode attribute (attribute, option, default) to a validated value.
 */
function wpgpxmaps_shortcode_options(array $attr): array
{
    $f = static fn(string $name, string $option, mixed $default): mixed => wpgpxmaps_find_value($attr, $name, $option, $default);
    $b = static fn(string $name, string $option, bool $default): bool => wpgpxmaps_find_bool($attr, $name, $option, $default);
    $n = static function (string $name, string $option, float $default, float $min, float $max) use ($f): float {
        $value = wpgpxmaps_num($f($name, $option, ''));
        return null === $value ? $default : max($min, min($max, $value));
    };
    $choice = static function (string $name, string $option, array $allowed, string $default) use ($f): string {
        $value = strtoupper((string) $f($name, $option, $default));
        return in_array($value, $allowed, true) ? $value : $default;
    };
    $optional_number = static function (string $name, string $option) use ($f): ?float {
        return wpgpxmaps_num($f($name, $option, ''));
    };

    $map_types = wpgpxmaps_map_types();

    // Direction arrows: the shortcode attribute wins, otherwise the setting (when enabled).
    $arrows_km = 0.0;
    $arrows_attr = wpgpxmaps_num($attr['arrowskm'] ?? '');
    if (null !== $arrows_attr) {
        $arrows_km = max(0.0, min(1000.0, $arrows_attr));
    } elseif (wpgpxmaps_bool(get_option('wpgpxmaps_arrows_enabled', ''))) {
        $arrows_km = $n('', 'wpgpxmaps_arrows_km', 10.0, 0.1, 1000.0);
    }

    return array(
        'gpx' => trim((string) ($attr['gpx'] ?? '')),
        'width' => wpgpxmaps_sanitize_css_size($f('width', 'wpgpxmaps_width', '100%'), '100%'),
        'mheight' => wpgpxmaps_sanitize_css_size($f('mheight', 'wpgpxmaps_height', '450px'), '450px'),
        'gheight' => wpgpxmaps_sanitize_css_size($f('gheight', 'wpgpxmaps_graph_height', '200px'), '200px'),
        'mtype' => $choice('mtype', 'wpgpxmaps_map_type', $map_types, 'HYBRID'),
        'uom' => $choice('uom', 'wpgpxmaps_unit_of_measure', array('0', '1', '2', '3', '4', '5'), '0'),
        'uomspeed' => $choice('uomspeed', 'wpgpxmaps_unit_of_measure_speed', array('0', '1', '2', '3', '4', '5', '6'), '0'),
        'distance_type' => (int) $choice('distancetype', 'wpgpxmaps_distance_type', array('0', '1', '2'), '0'),
        'pointsoffset' => $n('pointsoffset', 'wpgpxmaps_pointsoffset', 10.0, 0.0, 10000.0),
        'reduce' => !$b('donotreducegpx', 'wpgpxmaps_donotreducegpx', true),
        'skipcache' => $b('skipcache', 'wpgpxmaps_skipcache', false),
        'privacy' => $b('privacymode', 'wpgpxmaps_privacymode', false),
        'zoomwheel' => $b('zoomonscrollwheel', 'wpgpxmaps_zoomonscrollwheel', false),
        'waypoints' => $b('waypoints', 'wpgpxmaps_show_waypoint', false),
        'extreme' => $b('showextrememarkers', 'wpgpxmaps_show_extreme_markers', true),
        'download' => $b('download', 'wpgpxmaps_download', false),
        'gps' => $b('usegpsposition', 'wpgpxmaps_usegpsposition', false),
        'showele' => $b('showele', 'wpgpxmaps_show_elevation', true),
        'showspeed' => $b('showspeed', 'wpgpxmaps_show_speed', false),
        'showhr' => $b('showhr', 'wpgpxmaps_show_hr', false),
        'showatemp' => $b('showatemp', 'wpgpxmaps_show_atemp', false),
        'showcad' => $b('showcad', 'wpgpxmaps_show_cadence', false),
        'showgrade' => $b('showgrade', 'wpgpxmaps_show_grade', false),
        'summary' => $b('summary', 'wpgpxmaps_summary', false),
        'summary_len' => $b('summarytotlen', 'wpgpxmaps_summary_tot_len', false),
        'summary_max' => $b('summarymaxele', 'wpgpxmaps_summary_max_ele', false),
        'summary_min' => $b('summaryminele', 'wpgpxmaps_summary_min_ele', false),
        'summary_avg' => $b('summaryavgele', 'wpgpxmaps_summary_avg_ele', false),
        'summary_up' => $b('summaryeleup', 'wpgpxmaps_summary_total_ele_up', false),
        'summary_down' => $b('summaryeledown', 'wpgpxmaps_summary_total_ele_down', false),
        'summary_speed' => $b('summaryavgspeed', 'wpgpxmaps_summary_avg_speed', false),
        'summary_time' => $b('summarytotaltime', 'wpgpxmaps_summary_total_time', false),
        'colors_map' => wpgpxmaps_sanitize_color_list($f('mlinecolor', 'wpgpxmaps_map_line_color', '#3366cc'), '#3366cc'),
        'color_ele' => wpgpxmaps_sanitize_color($f('glinecolor', 'wpgpxmaps_graph_line_color', '#3366cc'), '#3366cc'),
        'color_speed' => wpgpxmaps_sanitize_color($f('glinecolorspeed', 'wpgpxmaps_graph_line_color_speed', '#ff0000'), '#ff0000'),
        'color_hr' => wpgpxmaps_sanitize_color($f('glinecolorhr', 'wpgpxmaps_graph_line_color_hr', '#ff77bd'), '#ff77bd'),
        'color_atemp' => wpgpxmaps_sanitize_color($f('glinecoloratemp', 'wpgpxmaps_graph_line_color_atemp', '#ff77bd'), '#ff77bd'),
        'color_cad' => wpgpxmaps_sanitize_color($f('glinecolorcad', 'wpgpxmaps_graph_line_color_cad', '#beecff'), '#beecff'),
        'color_grade' => wpgpxmaps_sanitize_color($f('glinecolorgrade', 'wpgpxmaps_graph_line_color_grade', '#beecff'), '#beecff'),
        'ele_from' => $optional_number('chartfrom1', 'wpgpxmaps_graph_offset_from1'),
        'ele_to' => $optional_number('chartto1', 'wpgpxmaps_graph_offset_to1'),
        'speed_from' => $optional_number('chartfrom2', 'wpgpxmaps_graph_offset_from2'),
        'speed_to' => $optional_number('chartto2', 'wpgpxmaps_graph_offset_to2'),
        'icon_start' => wpgpxmaps_icon_url($f('starticon', 'wpgpxmaps_map_start_icon', '')),
        'icon_end' => wpgpxmaps_icon_url($f('endicon', 'wpgpxmaps_map_end_icon', '')),
        'icon_current' => wpgpxmaps_icon_url($f('currenticon', 'wpgpxmaps_map_current_icon', '')),
        'icon_waypoint' => wpgpxmaps_icon_url($f('waypointicon', 'wpgpxmaps_map_waypoint_icon', '')),
        'icon_gps' => wpgpxmaps_icon_url($f('currentpositioncon', 'wpgpxmaps_currentpositioncon', '')),
        'elev_color' => $b('elevcolor', 'wpgpxmaps_elev_color_enabled', false),
        'elev_thresh' => $n('elevcolorthreshold', 'wpgpxmaps_elev_color_threshold', 5.0, 0.0, 100.0),
        'elev_max' => $n('elevcolormax', 'wpgpxmaps_elev_color_max', 12.0, 0.0, 100.0),
        'arrows_km' => $arrows_km,
        'dtoffset' => (int) $n('dtoffset', 'wpgpxmaps_dtoffset', 0.0, -86400.0, 86400.0),
        'ng_galleries' => preg_replace('/[^0-9,]/', '', (string) $f('nggalleries', 'wpgpxmaps_map_ngGalleries', '')),
        'ng_images' => preg_replace('/[^0-9,]/', '', (string) $f('ngimages', 'wpgpxmaps_map_ngImages', '')),
        'attachments' => $b('attachments', 'wpgpxmaps_map_attachments', true),
    );
}

/**
 * Chart series (units converted, arrays aligned with the map points) for the frontend.
 */
function wpgpxmaps_chart_series(array $track, array $o): array
{
    $has = $track['has'];
    $series = array(
        'dist' => array(),
        'ele' => array(),
        'speed' => array(),
        'hr' => array(),
        'atemp' => array(),
        'cad' => array(),
        'grade' => array(),
    );

    $wanted = array(
        'ele' => $o['showele'] && $has['ele'],
        'speed' => $o['showspeed'] && $has['speed'],
        'hr' => $o['showhr'] && $has['hr'],
        'atemp' => $o['showatemp'] && $has['atemp'],
        'cad' => $o['showcad'] && $has['cad'],
        'grade' => $o['showgrade'] && $has['grade'],
    );
    if (!in_array(true, $wanted, true)) {
        return $series;
    }

    $count = count($track['dist']);
    for ($i = 0; $i < $count; $i++) {
        if (null === $track['dist'][$i]) {
            $series['dist'][] = null;
            foreach (array('ele', 'speed', 'hr', 'atemp', 'cad', 'grade') as $key) {
                if ($wanted[$key]) {
                    $series[$key][] = null;
                }
            }
            continue;
        }
        $series['dist'][] = round(wpgpxmaps_convert_distance((float) $track['dist'][$i], $o['uom']), 2);

        if ($wanted['ele']) {
            $ele = $track['ele'][$i];
            $series['ele'][] = null === $ele ? null : round(wpgpxmaps_convert_elevation((float) $ele, $o['uom']), 2);
        }
        if ($wanted['speed']) {
            $speed = wpgpxmaps_convert_speed((float) $track['speed'][$i], $o['uomspeed']);
            $series['speed'][] = null === $speed ? null : round($speed, 2);
        }
        foreach (array('hr', 'atemp', 'cad', 'grade') as $key) {
            if ($wanted[$key]) {
                $value = $track[$key][$i];
                if (null !== $value && ('atemp' === $key || 0.0 !== (float) $value)) {
                    $series[$key][] = round((float) $value, 2);
                } else {
                    $series[$key][] = null;
                }
            }
        }
    }
    return $series;
}

/**
 * Summary table below the chart.
 */
function wpgpxmaps_summary_html(array $track, array $o, array $series, string $r): string
{
    if (!$o['summary']) {
        return '';
    }
    $stats = $track['stats'];
    $rows = array();
    $add = static function (string $class, string $label, string $value) use (&$rows): void {
        $rows[] = '<span class="' . esc_attr($class) . '"><span class="summarylabel">' . esc_html($label) . ':</span><span class="summaryvalue"> ' . esc_html($value) . '</span></span>';
    };
    $has_ele = !empty($series['ele']);

    if ($o['summary_len'] && $stats['totalLength'] > 0) {
        $add('totlen', __('Total distance', 'wp-gpx-maps'), wpgpxmaps_format_distance((float) $stats['totalLength'], $o['uom']));
    }
    if ($o['summary_max'] && $has_ele) {
        $add('maxele', __('Max elevation', 'wp-gpx-maps'), wpgpxmaps_format_elevation((float) $stats['maxEle'], $o['uom']));
    }
    if ($o['summary_up'] && $has_ele) {
        $add('totaleleup', __('Total climbing', 'wp-gpx-maps'), wpgpxmaps_format_elevation((float) $stats['totalEleUp'], $o['uom']));
    }
    if ($o['summary_min'] && $has_ele) {
        $add('minele', __('Min elevation', 'wp-gpx-maps'), wpgpxmaps_format_elevation((float) $stats['minEle'], $o['uom']));
    }
    if ($o['summary_down'] && $has_ele) {
        $add('totaleledown', __('Total descent', 'wp-gpx-maps'), wpgpxmaps_format_elevation((float) $stats['totalEleDown'], $o['uom']));
    }
    if ($o['summary_avg'] && $has_ele) {
        $add('avgele', __('Avg elevation', 'wp-gpx-maps'), wpgpxmaps_format_elevation((float) $stats['avgEle'], $o['uom']));
    }
    if ($o['summary_speed'] && !empty($series['speed'])) {
        $add('avgspeed', __('Average speed', 'wp-gpx-maps'), wpgpxmaps_format_speed((float) $stats['avgSpeed'], $o['uomspeed']));
    }

    $total_time = (int) $stats['maxTime'] - (int) $stats['minTime'];
    if ($o['summary_time'] && $stats['maxTime'] > 0) {
        $add('totaltime', __('Total Time', 'wp-gpx-maps'), wpgpxmaps_format_duration($total_time));
    }
    if ($total_time > 0 && $stats['moving'] > 0) {
        $add('moveratio', __('Move ratio', 'wp-gpx-maps'), round($stats['moving'] / $total_time * 100) . '%');
    }
    if ($stats['climbing'] > 0) {
        $add('timeclimb', __('Time climbing', 'wp-gpx-maps'), wpgpxmaps_format_duration((int) $stats['climbing']));
    }
    if ($stats['climbSpeed'] > 0) {
        $add('avgclimbspeed', __('Avg climbing speed', 'wp-gpx-maps'), wpgpxmaps_format_speed((float) $stats['climbSpeed'], $o['uomspeed']));
    }

    return $rows ? '<div id="wpgpxmaps_summary_' . esc_attr($r) . '" class="wpgpxmaps_summary">' . implode('', $rows) . '</div>' : '';
}

/**
 * Shortcode [sgpx gpx="..."]: one map with charts.
 */
function handle_WP_GPX_Maps_Shortcodes($attr, $content = '', $tag = ''): string
{
    static $instance = 0;

    if (is_feed() || (function_exists('wp_is_serving_rest_request') && wp_is_serving_rest_request())) {
        return '';
    }
    $attr = is_array($attr) ? array_change_key_case($attr, CASE_LOWER) : array();
    $o = wpgpxmaps_shortcode_options($attr);

    $track = wpgpxmaps_load_track(
        $o['gpx'],
        array(
            'offset' => $o['pointsoffset'],
            'reduce' => $o['reduce'],
            'distance_type' => $o['distance_type'],
            'privacy' => $o['privacy'],
            'skipcache' => $o['skipcache'],
        )
    );
    if (is_wp_error($track)) {
        return wpgpxmaps_error_html($track->get_error_code());
    }

    $post = get_post();
    $post_id = $post ? (int) $post->ID : 0;
    ++$instance;
    $r = $post_id . '_' . $instance;

    $hide_graph = in_array($o['gheight'], array('0px', '0%', '0em', '0rem', '0vh', '0vw', '0vmin', '0vmax'), true);
    $series = wpgpxmaps_chart_series($track, $o);

    // Images placed along the track.
    $images = array();
    if ($o['attachments']) {
        $images = array_merge($images, wpgpxmaps_get_attached_images($post_id, $track['dt'], $track['map'], $o['dtoffset']));
    }
    if ('' !== $o['ng_galleries'] || '' !== $o['ng_images']) {
        $images = array_merge($images, wpgpxmaps_get_nggallery_images($o['ng_galleries'], $o['ng_images'], $track['dt'], $track['map'], $o['dtoffset']));
    }

    $waypoints = array();
    if ($o['waypoints']) {
        foreach ($track['waypoints'] as $waypoint) {
            $icon = wpgpxmaps_waypoint_icon_url((string) $waypoint['sym']);
            $waypoints[] = array(
                'lat' => $waypoint['lat'],
                'lon' => $waypoint['lon'],
                'name' => $waypoint['name'],
                'desc' => $waypoint['desc'],
                'type' => $waypoint['type'],
                'img' => '' !== $icon ? $icon : $o['icon_waypoint'],
            );
        }
    }

    $arrow_repeat = '';
    if ($o['arrows_km'] > 0 && $track['stats']['totalLength'] > 0) {
        $arrow_repeat = round(max(0.1, min(100.0, $o['arrows_km'] * 1000 / $track['stats']['totalLength'] * 100)), 2) . '%';
    }

    $extremes = array();
    if ($o['extreme']) {
        $ele_index = (int) $track['extremes']['maxEleIndex'];
        $speed_index = (int) $track['extremes']['maxSpeedIndex'];
        if ($ele_index >= 0 && $track['has']['ele'] && isset($track['map'][$ele_index])) {
            $extremes['maxEle'] = array(
                'index' => $ele_index,
                'value' => round(wpgpxmaps_convert_elevation((float) $track['ele'][$ele_index], $o['uom']), 2),
            );
        }
        if ($speed_index >= 0 && isset($track['map'][$speed_index])) {
            $speed_value = wpgpxmaps_convert_speed((float) $track['speed'][$speed_index], $o['uomspeed']);
            if (null !== $speed_value) {
                $extremes['maxSpeed'] = array(
                    'index' => $speed_index,
                    'value' => round($speed_value, 2),
                );
            }
        }
    }

    $config = array(
        'id' => $r,
        'mapType' => $o['mtype'],
        'map' => $track['map'],
        'chart' => $hide_graph ? null : $series,
        'mapGrade' => $o['elev_color'] && $track['has']['grade'] ? array_map(static fn($v) => null === $v ? null : round((float) $v, 2), $track['grade']) : array(),
        'colors' => $o['colors_map'],
        'colorsChart' => array(
            'ele' => $o['color_ele'],
            'speed' => $o['color_speed'],
            'hr' => $o['color_hr'],
            'atemp' => $o['color_atemp'],
            'cad' => $o['color_cad'],
            'grade' => $o['color_grade'],
        ),
        'range' => array(
            'ele' => array($o['ele_from'], $o['ele_to']),
            'speed' => array($o['speed_from'], $o['speed_to']),
        ),
        'units' => array(
            'distance' => match ($o['uom']) {
                '1', '4' => 'mi',
                '2' => 'km',
                '3', '5' => 'NM',
                default => 'm',
            },
            'elevation' => in_array($o['uom'], array('1', '5'), true) ? 'ft' : 'm',
            'speed' => wpgpxmaps_speed_unit($o['uomspeed']),
            'paceSpeed' => in_array($o['uomspeed'], array('3', '4', '6'), true),
        ),
        'elevColor' => array(
            'enabled' => $o['elev_color'],
            'threshold' => $o['elev_thresh'],
            'max' => $o['elev_max'],
        ),
        'icons' => array(
            'start' => $o['icon_start'],
            'end' => $o['icon_end'],
            'current' => $o['icon_current'],
            'gps' => $o['icon_gps'],
        ),
        'extremes' => $extremes,
        'waypoints' => $waypoints,
        'arrowRepeat' => $arrow_repeat,
        'scrollWheel' => $o['zoomwheel'],
        'usePosition' => $o['gps'],
        'hasImages' => (bool) $images,
    );

    $json = wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (false === $json) {
        return wpgpxmaps_error_html('wpgpxmaps_parse');
    }

    wpgpxmaps_enqueue_assets();

    $id_attr = esc_attr($r);
    $style_m = esc_attr('width:' . $o['width'] . ';height:' . $o['mheight']);
    $style_g = esc_attr('width:' . $o['width'] . ';height:' . $o['gheight']);

    $html = '<div id="wpgpxmaps_' . $id_attr . '" class="wpgpxmaps">';
    $html .= '<div id="map_' . $id_attr . '_cont" style="' . $style_m . ';position:relative">';
    $html .= '<div class="wpgpxmaps_spinner" id="spinner_' . $id_attr . '" role="status" aria-label="' . esc_attr__('Loading map', 'wp-gpx-maps') . '"><div class="spinner"></div></div>';
    $html .= '<div id="map_' . $id_attr . '" role="region" aria-label="' . esc_attr__('Track map', 'wp-gpx-maps') . '" style="' . $style_m . '"></div>';
    $html .= '<div id="wpgpxmaps_' . $id_attr . '_osm_footer" class="wpgpxmaps_osm_footer" style="display:none"><span>&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors</span></div>';
    $html .= '</div>';
    if (!$hide_graph) {
        $html .= '<div id="hchart_' . $id_attr . '" class="plot" style="' . $style_g . '"></div>';
    }
    $html .= '<div id="ngimages_' . $id_attr . '" class="ngimages" style="display:none">';
    foreach ($images as $image) {
        $html .= '<span class="ngimage" data-lat="' . esc_attr((string) round((float) $image['lat'], 7)) . '" data-lon="' . esc_attr((string) round((float) $image['lon'], 7)) . '">' . $image['data'] . '</span>';
    }
    $html .= '</div>';
    $html .= '<div id="report_' . $id_attr . '" class="report"></div>';
    $html .= '<script type="application/json" class="wpgpxmaps-config">' . $json . '</script>';
    $html .= '</div>';
    $html .= wpgpxmaps_summary_html($track, $o, $series, $r);

    if ($o['download']) {
        if (preg_match('#^https?://#i', $o['gpx'])) {
            $url = $o['gpx'];
        } else {
            $real = wpgpxmaps_resolve_local_path($o['gpx']);
            $url = false === $real ? '' : wpgpxmaps_local_path_to_url($real);
        }
        if ('' !== $url) {
            $html .= '<div class="wpgpxmaps_download"><a class="wpgpxmaps_download_link" href="' . esc_url($url) . '" target="_blank" rel="noopener" download>' . esc_html__('Download GPX', 'wp-gpx-maps') . '</a></div>';
        }
    }

    return $html;
}

/**
 * Shortcode [sgpxf folder="..."]: one map per GPX file in a folder (at most 20).
 */
function handle_WP_GPX_Maps_folder_Shortcodes($attr, $content = '', $tag = ''): string
{
    $attr = is_array($attr) ? array_change_key_case($attr, CASE_LOWER) : array();
    $folder = trim(str_replace('\\', '/', (string) ($attr['folder'] ?? '')));
    if ('' === $folder || str_contains($folder, "\0")) {
        return wpgpxmaps_error_html('wpgpxmaps_not_found');
    }

    $real = realpath(wpgpxmaps_site_root() . '/' . ltrim($folder, '/'));
    // A dummy file name lets the folder be checked with the same containment rule as files.
    if (false === $real || !is_dir($real) || !wpgpxmaps_path_is_allowed($real . '/x.gpx')) {
        return wpgpxmaps_error_html('wpgpxmaps_not_found');
    }

    $files = glob($real . '/*.gpx');
    if (!is_array($files) || !$files) {
        return wpgpxmaps_error_html('wpgpxmaps_not_found');
    }
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    $html = '';
    foreach (array_slice($files, 0, 20) as $file) {
        $html .= handle_WP_GPX_Maps_Shortcodes(array('gpx' => $file) + $attr);
    }
    return $html;
}
