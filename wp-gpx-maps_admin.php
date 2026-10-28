<?php
/**
 * Admin page: menu, tabs and help.
 *
 * @package WP-GPX-Maps
 */

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_menu', 'wpgpxmaps_admin_menu');
add_action('admin_notices', 'wpgpxmaps_admin_notices');

function wpgpxmaps_admin_menu(): void
{
	if (current_user_can('manage_options')) {
		add_options_page('WP GPX Maps', 'WP GPX Maps', 'manage_options', 'WP-GPX-Maps', 'wpgpxmaps_admin_page');
	} elseif (current_user_can('publish_posts')) {
		add_menu_page('WP GPX Maps', 'WP GPX Maps', 'publish_posts', 'WP-GPX-Maps', 'wpgpxmaps_admin_page');
	}
}

function wpgpxmaps_is_plugin_screen(): bool
{
	return isset($_GET['page']) && 'WP-GPX-Maps' === sanitize_text_field(wp_unslash($_GET['page'])); // phpcs:ignore WordPress.Security.NonceVerification
}

function wpgpxmaps_admin_notices(): void
{
	if (!current_user_can('manage_options') || !wpgpxmaps_is_plugin_screen()) {
		return;
	}
	if ('' === (string) get_option('wpgpxmaps_googlemapsv3_apikey', '')) {
		echo '<div class="notice notice-warning"><p>' . esc_html__('WP GPX Maps: no Google Maps API key is configured. Maps load with a development watermark until you set one in Settings.', 'wp-gpx-maps') . '</p></div>';
	}
	if ('' === (string) get_option('wpgpxmaps_googlemaps_map_id', '')) {
		echo '<div class="notice notice-info"><p>' . esc_html__('WP GPX Maps: no Google Maps Map ID is configured. Advanced Markers require a Map ID; classic markers are used without one.', 'wp-gpx-maps') . '</p></div>';
	}
}

/**
 * Tabs the current user may open (slug => label).
 *
 * @return array<string, string>
 */
function wpgpxmaps_admin_tabs(): array
{
	$tabs = array('tracks' => __('Tracks', 'wp-gpx-maps'));
	if (current_user_can('manage_options')) {
		$tabs['settings'] = __('Settings', 'wp-gpx-maps');
	}
	$tabs['help'] = __('Help', 'wp-gpx-maps');
	return $tabs;
}

function wpgpxmaps_admin_page(): void
{
	if (!current_user_can('publish_posts') && !current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have permission to access this page.', 'wp-gpx-maps'));
	}

	$tabs = wpgpxmaps_admin_tabs();
	$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'tracks'; // phpcs:ignore WordPress.Security.NonceVerification
	if (!isset($tabs[$tab])) {
		$tab = 'tracks';
	}

	$gpx_dir = wpgpxmaps_gpx_folder_path();

	echo '<div class="wrap"><h1>' . esc_html__('WP GPX Maps', 'wp-gpx-maps') . '</h1>';

	if ('' === $gpx_dir || (!is_dir($gpx_dir) && !wp_mkdir_p($gpx_dir))) {
		echo '<div class="notice notice-error"><p>' . esc_html__('The GPX folder could not be created. Create it and make it writable, or upload tracks manually.', 'wp-gpx-maps') . '</p></div>';
	}
	if (!wpgpxmaps_ensure_cache_dir()) {
		echo '<div class="notice notice-warning"><p>' . esc_html__('The cache folder could not be created. Tracks will not be cached and pages may be slower.', 'wp-gpx-maps') . '</p></div>';
	}

	echo '<h2 class="nav-tab-wrapper">';
	foreach ($tabs as $slug => $label) {
		$url = add_query_arg(array('page' => 'WP-GPX-Maps', 'tab' => $slug), admin_url(current_user_can('manage_options') ? 'options-general.php' : 'admin.php'));
		printf(
			'<a class="nav-tab%s" href="%s">%s</a>',
			$slug === $tab ? ' nav-tab-active' : '',
			esc_url($url),
			esc_html($label)
		);
	}
	echo '</h2>';

	if ('tracks' === $tab) {
		require_once WPGPXMAPS_DIR . 'wp-gpx-maps_admin_tracks.php';
		wpgpxmaps_admin_tracks_tab($gpx_dir);
	} elseif ('settings' === $tab && current_user_can('manage_options')) {
		require_once WPGPXMAPS_DIR . 'wp-gpx-maps_admin_settings.php';
		wpgpxmaps_admin_settings_tab();
	} else {
		wpgpxmaps_admin_help_tab($gpx_dir);
	}

	echo '</div>';
}

/**
 * Shortcode attributes shown on the help tab (attribute => description).
 *
 * @return array<string, string>
 */
function wpgpxmaps_shortcode_attributes(): array
{
	return array(
		'gpx' => 'Path to the GPX file relative to the site root, or a http(s) URL.',
		'width' => 'Width of the map and the chart (CSS size, e.g. 100% or 600px).',
		'mheight' => 'Map height.',
		'gheight' => 'Chart height; 0 hides the chart.',
		'mtype' => 'Map type: HYBRID, ROADMAP, SATELLITE, TERRAIN, OSM1 (OpenStreetMap), OSM2 (Thunderforest Cycle), OSM4 (Thunderforest Transport), OSM5 (Thunderforest Landscape), OSM6 (MapTiler Outdoor), OSM7 (MapTiler Topo), OSM8 (MapTiler Landscape). OSM2/4/5 need a Thunderforest key and OSM6-8 a MapTiler key.',
		'waypoints' => 'Show the GPX waypoints on the map (default false).',
		'donotreducegpx' => 'Keep every point instead of reducing long tracks (set in Settings; on after a fresh install).',
		'pointsoffset' => 'Skip points closer than N meters (default 10).',
		'distancetype' => '0 = normal, 1 = flat (ignore altitude), 2 = climb only.',
		'uom' => 'Distance and altitude unit: 0 = m/m, 1 = ft/mi, 2 = m/km, 3 = m/NM, 4 = m/mi, 5 = ft/NM.',
		'uomspeed' => 'Speed unit: 0 = m/s, 1 = km/h, 2 = mi/h, 3 = min/km, 4 = min/mi, 5 = knots, 6 = min/100m.',
		'mlinecolor' => 'Map line color, or several colors separated by spaces (one per track segment).',
		'glinecolor' => 'Altitude line color (default #3366cc).',
		'glinecolorspeed' => 'Speed line color (default #ff0000).',
		'glinecolorhr' => 'Heart rate line color (default #ff77bd).',
		'glinecoloratemp' => 'Temperature line color (default #ff77bd).',
		'glinecolorcad' => 'Cadence line color (default #beecff).',
		'glinecolorgrade' => 'Grade line color (default #beecff).',
		'showele' => 'Show altitude in the chart (default true).',
		'showspeed' => 'Show speed in the chart (default false).',
		'showhr' => 'Show heart rate in the chart (default false).',
		'showatemp' => 'Show temperature in the chart (default false).',
		'showcad' => 'Show cadence in the chart (default false).',
		'showgrade' => 'Show grade in the chart (default false).',
		'chartfrom1' => 'Minimum value of the altitude axis.',
		'chartto1' => 'Maximum value of the altitude axis.',
		'chartfrom2' => 'Minimum value of the speed axis.',
		'chartto2' => 'Maximum value of the speed axis.',
		'arrowskm' => 'Draw direction arrows along the track every N kilometers (decimals allowed).',
		'elevcolor' => 'Color the track red where the grade is steep (default false).',
		'elevcolorthreshold' => 'Grade in % where the red coloring starts (default 5).',
		'elevcolormax' => 'Grade in % for full red (default 12).',
		'showextrememarkers' => 'Show markers for the highest point and the top speed (default true).',
		'starticon' => 'URL of the start marker image.',
		'endicon' => 'URL of the end marker image.',
		'currenticon' => 'URL of the marker that follows the mouse over the chart.',
		'waypointicon' => 'URL of the default waypoint image.',
		'currentpositioncon' => 'URL of the marker image for the visitor GPS position.',
		'attachments' => 'Show images attached to the post along the track (default true).',
		'nggalleries' => 'NextGEN gallery id(s), comma separated.',
		'ngimages' => 'NextGEN image id(s), comma separated.',
		'dtoffset' => 'Seconds between the GPX clock and the camera clock.',
		'zoomonscrollwheel' => 'Zoom the map with the mouse wheel without Ctrl (default false).',
		'usegpsposition' => 'Show the visitor position from the browser GPS (default false).',
		'download' => 'Show a link to download the GPX file (default false).',
		'skipcache' => 'Do not use the cache; slow for big files (default false).',
		'privacymode' => 'Cut the start and end of the track to hide exact locations (default false).',
		'summary' => 'Print a summary table (default false).',
		'summarytotlen' => 'Summary: total distance.',
		'summarymaxele' => 'Summary: maximum elevation.',
		'summaryminele' => 'Summary: minimum elevation.',
		'summaryavgele' => 'Summary: average elevation.',
		'summaryeleup' => 'Summary: total climbing.',
		'summaryeledown' => 'Summary: total descent.',
		'summaryavgspeed' => 'Summary: average speed.',
		'summarytotaltime' => 'Summary: total time.',
	);
}

function wpgpxmaps_admin_help_tab(string $gpx_dir): void
{
	$relative = wpgpxmaps_relative_path($gpx_dir);

	echo '<div style="padding:10px;max-width:960px">';
	echo '<p><b>' . esc_html__('The fastest way to use this plugin:', 'wp-gpx-maps') . '</b> ';
	echo esc_html__('upload a file in the Tracks tab, then copy its shortcode into a post or page.', 'wp-gpx-maps') . '</p>';
	echo '<p>' . esc_html__('Relative path example:', 'wp-gpx-maps') . ' <code>' . esc_html('[sgpx gpx="' . $relative . 'track.gpx"]') . '</code></p>';
	echo '<p>' . esc_html__('Remote file example:', 'wp-gpx-maps') . ' <code>[sgpx gpx="https://example.com/track.gpx"]</code></p>';
	echo '<p>' . esc_html__('All GPX files of a folder:', 'wp-gpx-maps') . ' <code>' . esc_html('[sgpxf folder="' . rtrim($relative, '/') . '"]') . '</code></p>';
	echo '<hr /><h3>' . esc_html__('Attributes', 'wp-gpx-maps') . '</h3><ul style="list-style:disc;margin-left:20px">';
	foreach (wpgpxmaps_shortcode_attributes() as $name => $description) {
		echo '<li><b>' . esc_html($name) . '</b>: ' . esc_html($description) . '</li>';
	}
	echo '</ul></div>';
}
