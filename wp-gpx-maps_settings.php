<?php
/**
 * Settings schema, sanitising, install and upgrade routines.
 *
 * @package WP-GPX-Maps
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', 'wpgpxmaps_register_settings');

/**
 * Map type codes: Google types plus tile layers that are drawn on top of Google Maps.
 *
 * @return string[]
 */
function wpgpxmaps_map_types(): array
{
    return array('HYBRID', 'ROADMAP', 'SATELLITE', 'TERRAIN', 'OSM1', 'OSM2', 'OSM4', 'OSM5', 'OSM6', 'OSM7', 'OSM8');
}

/**
 * Every option the plugin stores: type, default and limits. Admin form, sanitising, defaults on
 * activation and upgrade are all driven by this list.
 *
 * Types: bool ('true' or ''), size, color, color_list, url, text, number, number_or_empty, enum.
 *
 * @return array<string, array<string, mixed>>
 */
function wpgpxmaps_settings_schema(): array
{
    static $schema = null;
    if (null !== $schema) {
        return $schema;
    }

    $bool = static fn(string $default = ''): array => array('type' => 'bool', 'default' => $default);
    $color = static fn(string $default): array => array('type' => 'color', 'default' => $default);
    $url = array('type' => 'url', 'default' => '');
    $text = array('type' => 'text', 'default' => '');
    $offset = array('type' => 'number_or_empty', 'default' => '');

    $schema = array(
        // General.
        'wpgpxmaps_width' => array('type' => 'size', 'default' => '100%'),
        'wpgpxmaps_height' => array('type' => 'size', 'default' => '450px'),
        'wpgpxmaps_graph_height' => array('type' => 'size', 'default' => '200px'),
        'wpgpxmaps_distance_type' => array('type' => 'enum', 'default' => '0', 'choices' => array('0', '1', '2')),
        'wpgpxmaps_pointsoffset' => array('type' => 'number', 'default' => '10', 'min' => 0, 'max' => 10000),
        'wpgpxmaps_donotreducegpx' => $bool('true'),
        'wpgpxmaps_skipcache' => $bool(),
        'wpgpxmaps_privacymode' => $bool(),
        'wpgpxmaps_download' => $bool(),
        'wpgpxmaps_usegpsposition' => $bool(),
        'wpgpxmaps_arrows_enabled' => $bool(),
        'wpgpxmaps_arrows_km' => array('type' => 'number', 'default' => '10', 'min' => 0.1, 'max' => 1000),
        // API keys.
        'wpgpxmaps_googlemapsv3_apikey' => $text,
        'wpgpxmaps_googlemaps_map_id' => $text,
        'wpgpxmaps_maps_channel' => array('type' => 'enum', 'default' => 'quarterly', 'choices' => array('quarterly', 'weekly')),
        'wpgpxmaps_openstreetmap_apikey' => $text,
        'wpgpxmaps_maptiler_apikey' => $text,
        // Summary.
        'wpgpxmaps_summary' => $bool(),
        'wpgpxmaps_summary_tot_len' => $bool(),
        'wpgpxmaps_summary_max_ele' => $bool(),
        'wpgpxmaps_summary_min_ele' => $bool(),
        'wpgpxmaps_summary_avg_ele' => $bool(),
        'wpgpxmaps_summary_total_ele_up' => $bool(),
        'wpgpxmaps_summary_total_ele_down' => $bool(),
        'wpgpxmaps_summary_avg_speed' => $bool(),
        'wpgpxmaps_summary_total_time' => $bool(),
        // Map.
        'wpgpxmaps_map_type' => array('type' => 'enum', 'default' => 'HYBRID', 'choices' => wpgpxmaps_map_types()),
        'wpgpxmaps_map_line_color' => array('type' => 'color_list', 'default' => '#3366cc'),
        'wpgpxmaps_show_waypoint' => $bool(),
        'wpgpxmaps_zoomonscrollwheel' => $bool(),
        'wpgpxmaps_show_extreme_markers' => $bool('true'),
        'wpgpxmaps_elev_color_enabled' => $bool(),
        'wpgpxmaps_elev_color_threshold' => array('type' => 'number', 'default' => '5', 'min' => 0, 'max' => 100),
        'wpgpxmaps_elev_color_max' => array('type' => 'number', 'default' => '12', 'min' => 0, 'max' => 100),
        'wpgpxmaps_map_start_icon' => $url,
        'wpgpxmaps_map_end_icon' => $url,
        'wpgpxmaps_map_current_icon' => $url,
        'wpgpxmaps_map_waypoint_icon' => $url,
        'wpgpxmaps_currentpositioncon' => $url,
        // Charts.
        'wpgpxmaps_unit_of_measure' => array('type' => 'enum', 'default' => '0', 'choices' => array('0', '1', '2', '3', '4', '5')),
        'wpgpxmaps_unit_of_measure_speed' => array('type' => 'enum', 'default' => '0', 'choices' => array('0', '1', '2', '3', '4', '5', '6')),
        'wpgpxmaps_show_elevation' => $bool('true'),
        'wpgpxmaps_graph_line_color' => $color('#3366cc'),
        'wpgpxmaps_show_speed' => $bool(),
        'wpgpxmaps_graph_line_color_speed' => $color('#ff0000'),
        'wpgpxmaps_show_hr' => $bool(),
        'wpgpxmaps_graph_line_color_hr' => $color('#ff77bd'),
        'wpgpxmaps_show_atemp' => $bool(),
        'wpgpxmaps_graph_line_color_atemp' => $color('#ff77bd'),
        'wpgpxmaps_show_cadence' => $bool(),
        'wpgpxmaps_graph_line_color_cad' => $color('#beecff'),
        'wpgpxmaps_show_grade' => $bool(),
        'wpgpxmaps_graph_line_color_grade' => $color('#beecff'),
        'wpgpxmaps_graph_offset_from1' => $offset,
        'wpgpxmaps_graph_offset_to1' => $offset,
        'wpgpxmaps_graph_offset_from2' => $offset,
        'wpgpxmaps_graph_offset_to2' => $offset,
    );
    return $schema;
}

function wpgpxmaps_register_settings(): void
{
    foreach (wpgpxmaps_settings_schema() as $option => $definition) {
        register_setting(
            'wpgpxmaps_settings',
            $option,
            array(
                'type' => 'string',
                'default' => $definition['default'],
                // WordPress passes only the value to this callback, so the option name is bound here.
                'sanitize_callback' => static fn(mixed $value): string => wpgpxmaps_sanitize_setting($value, $option),
                'show_in_rest' => false,
            )
        );
    }
}

/**
 * Sanitize callback shared by all options (the option name is passed as second argument).
 */
function wpgpxmaps_sanitize_setting(mixed $value, string $option = ''): string
{
    $schema = wpgpxmaps_settings_schema();
    if (!isset($schema[$option])) {
        return '';
    }
    $definition = $schema[$option];
    $default = (string) $definition['default'];
    $value = is_scalar($value) ? trim((string) $value) : '';

    switch ($definition['type']) {
        case 'bool':
            return wpgpxmaps_bool($value) ? 'true' : '';
        case 'size':
            return wpgpxmaps_sanitize_css_size($value, $default);
        case 'color':
            return wpgpxmaps_sanitize_color($value, $default);
        case 'color_list':
            return implode(' ', wpgpxmaps_sanitize_color_list($value, $default));
        case 'url':
            return esc_url_raw($value, array('http', 'https'));
        case 'number':
            $number = wpgpxmaps_num($value);
            return null === $number ? $default : (string) max($definition['min'], min($definition['max'], $number));
        case 'number_or_empty':
            $number = wpgpxmaps_num($value);
            return null === $number ? '' : (string) $number;
        case 'enum':
            foreach (array($value, strtoupper($value), strtolower($value)) as $candidate) {
                if (in_array($candidate, $definition['choices'], true)) {
                    return $candidate;
                }
            }
            return $default;
        default:
            return sanitize_text_field($value);
    }
}

function wpgpxmaps_activate(): void
{
    wpgpxmaps_maybe_upgrade();
}

/**
 * Runs once after the plugin is installed or updated: adds missing options, repairs old values,
 * drops obsolete options and cached tracks of older versions.
 */
function wpgpxmaps_maybe_upgrade(): void
{
    $stored = get_option('wpgpxmaps_version', '');
    if (WPGPXMAPS_VERSION === $stored) {
        return;
    }

    foreach (wpgpxmaps_settings_schema() as $option => $definition) {
        add_option($option, $definition['default']);
    }

    if ('' !== $stored) {
        // These were on by default while an empty value meant "on"; the settings form now saves an explicit state.
        foreach (array('wpgpxmaps_show_elevation', 'wpgpxmaps_show_extreme_markers') as $option) {
            if ('' === get_option($option, '')) {
                update_option($option, 'true');
            }
        }
    }

    delete_option('wpgpxmaps_highcharts_v11');
    delete_option('wpgpxmaps_map_nggallery');

    wpgpxmaps_clear_cache();
    wpgpxmaps_ensure_cache_dir();

    update_option('wpgpxmaps_version', WPGPXMAPS_VERSION);
}
