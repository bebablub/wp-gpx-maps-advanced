<?php
/**
 * Plugin Name: WP-GPX-Maps
 * Plugin URI: https://github.com/bebablub/wp-gpx-maps-advanced
 * Description: Draws a GPX track on a map with altitude, speed and heart rate charts.
 * Version: 3.0.1
 * Requires at least: 7.1
 * Requires PHP: 8.3
 * Author: Bastianon Massimo, Benjamin Barinka
 * Author URI: http://www.pedemontanadelgrappa.it/
 * Text Domain: wp-gpx-maps
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
	exit;
}

// Keep this file parseable on old PHP versions so the notice below can be shown instead of a fatal error.
if (version_compare(PHP_VERSION, '8.3', '<')) {
	add_action('admin_notices', 'wpgpxmaps_php_version_notice');
	function wpgpxmaps_php_version_notice()
	{
		echo '<div class="notice notice-error"><p>WP-GPX-Maps requires PHP 8.3 or newer.</p></div>';
	}
	return;
}

define('WPGPXMAPS_VERSION', '3.0.1');
define('WPGPXMAPS_FILE', __FILE__);
define('WPGPXMAPS_DIR', plugin_dir_path(__FILE__));

require_once WPGPXMAPS_DIR . 'wp-gpx-maps_settings.php';
require_once WPGPXMAPS_DIR . 'wp-gpx-maps_utils.php';
require_once WPGPXMAPS_DIR . 'wp-gpx-maps_shortcode.php';

if (is_admin()) {
	require_once WPGPXMAPS_DIR . 'wp-gpx-maps_admin.php';
}

add_shortcode('sgpx', 'handle_WP_GPX_Maps_Shortcodes');
add_shortcode('sgpxf', 'handle_WP_GPX_Maps_folder_Shortcodes');

register_activation_hook(__FILE__, 'wpgpxmaps_activate');
add_action('plugins_loaded', 'wpgpxmaps_maybe_upgrade');
add_action('init', 'wpgpxmaps_load_textdomain');
add_action('wp_enqueue_scripts', 'wpgpxmaps_register_assets');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wpgpxmaps_action_links');

function wpgpxmaps_load_textdomain(): void
{
	load_plugin_textdomain('wp-gpx-maps', false, dirname(plugin_basename(WPGPXMAPS_FILE)) . '/languages');
}

function wpgpxmaps_action_links(array $links): array
{
	array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=WP-GPX-Maps')) . '">' . esc_html__('Settings', 'wp-gpx-maps') . '</a>');
	return $links;
}

/**
 * Registers (but does not enqueue) all frontend assets. They are enqueued by the shortcode.
 */
function wpgpxmaps_register_assets(): void
{
	$defer = array('strategy' => 'defer', 'in_footer' => true);

	wp_register_style('wpgpxmaps', plugins_url('css/wp-gpx-maps.css', WPGPXMAPS_FILE), array(), WPGPXMAPS_VERSION);
	wp_register_script('wpgpxmaps-highcharts', plugins_url('js/highcharts/highcharts.js', WPGPXMAPS_FILE), array(), '13.1.1', $defer);
	wp_register_script('wpgpxmaps-highcharts-a11y', plugins_url('js/highcharts/modules/accessibility.js', WPGPXMAPS_FILE), array('wpgpxmaps-highcharts'), '13.1.1', $defer);
	wp_register_script('wpgpxmaps', plugins_url('WP-GPX-Maps.js', WPGPXMAPS_FILE), array('wpgpxmaps-highcharts', 'wpgpxmaps-highcharts-a11y'), WPGPXMAPS_VERSION, $defer);
}

/**
 * Runtime settings handed to the frontend script.
 *
 * @return array<string, mixed>
 */
function wpgpxmaps_frontend_settings(): array
{
	$channel = (string) get_option('wpgpxmaps_maps_channel', 'quarterly');

	return array(
		'apiKey' => (string) get_option('wpgpxmaps_googlemapsv3_apikey', ''),
		'mapId' => (string) get_option('wpgpxmaps_googlemaps_map_id', ''),
		'channel' => in_array($channel, array('quarterly', 'weekly'), true) ? $channel : 'quarterly',
		'tfKey' => (string) get_option('wpgpxmaps_openstreetmap_apikey', ''),
		'mtKey' => (string) get_option('wpgpxmaps_maptiler_apikey', ''),
		'language' => str_replace('_', '-', get_locale()),
		// Return false when a theme or another plugin already loads the Google Maps JS API.
		'loadMaps' => (bool) apply_filters('wpgpxmaps_load_google_maps', true),
		'i18n' => array(
			'altitude' => __('Altitude', 'wp-gpx-maps'),
			'currentPosition' => __('Current Position', 'wp-gpx-maps'),
			'start' => __('Start', 'wp-gpx-maps'),
			'end' => __('End', 'wp-gpx-maps'),
			'speed' => __('Speed', 'wp-gpx-maps'),
			'grade' => __('Grade', 'wp-gpx-maps'),
			'heartRate' => __('Heart rate', 'wp-gpx-maps'),
			'atemp' => __('Temperature', 'wp-gpx-maps'),
			'cadence' => __('Cadence', 'wp-gpx-maps'),
			'hideImages' => __('Hide Images', 'wp-gpx-maps'),
			'showImages' => __('Show Images', 'wp-gpx-maps'),
			'backToCenter' => __('Back to center', 'wp-gpx-maps'),
			'avgLabel' => __('avg', 'wp-gpx-maps'),
			'maxAltitude' => __('Max altitude', 'wp-gpx-maps'),
			'maxSpeed' => __('Max speed', 'wp-gpx-maps'),
			'zoom' => __('Zoom', 'wp-gpx-maps'),
			'resetZoom' => __('Reset zoom', 'wp-gpx-maps'),
			'directions' => __('Directions', 'wp-gpx-maps'),
			'mapError' => __('The map could not be loaded.', 'wp-gpx-maps'),
		),
	);
}

/**
 * Enqueues the frontend assets; called from the shortcode so pages without a track stay clean.
 */
function wpgpxmaps_enqueue_assets(): void
{
	static $done = false;
	if ($done) {
		return;
	}
	$done = true;

	if (!wp_script_is('wpgpxmaps', 'registered')) {
		wpgpxmaps_register_assets();
	}

	$json = wp_json_encode(wpgpxmaps_frontend_settings(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	wp_add_inline_script('wpgpxmaps', 'window.WPGPXMAPS_SETTINGS = ' . (false === $json ? '{}' : $json) . ';', 'before');
	wp_enqueue_style('wpgpxmaps');
	wp_enqueue_script('wpgpxmaps');
}
