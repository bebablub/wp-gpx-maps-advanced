<?php
/**
 * Admin "Settings" tab, generated from the settings schema.
 *
 * @package WP-GPX-Maps
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Labels of the options with a fixed set of choices.
 *
 * @return array<string, string>
 */
function wpgpxmaps_settings_choices(string $option): array
{
	switch ($option) {
		case 'wpgpxmaps_distance_type':
			return array(
				'0' => __('Normal (default)', 'wp-gpx-maps'),
				'1' => __('Flat (ignore altitude)', 'wp-gpx-maps'),
				'2' => __('Climb (only climb distance)', 'wp-gpx-maps'),
			);
		case 'wpgpxmaps_maps_channel':
			return array(
				'quarterly' => __('Quarterly (stable, default)', 'wp-gpx-maps'),
				'weekly' => __('Weekly (latest features)', 'wp-gpx-maps'),
			);
		case 'wpgpxmaps_map_type':
			return array(
				'HYBRID' => __('Hybrid: street names on satellite images', 'wp-gpx-maps'),
				'ROADMAP' => __('Roadmap: normal street map', 'wp-gpx-maps'),
				'SATELLITE' => __('Satellite: satellite images', 'wp-gpx-maps'),
				'TERRAIN' => __('Terrain: physical features such as terrain and vegetation', 'wp-gpx-maps'),
				'OSM1' => __('OpenStreetMap', 'wp-gpx-maps'),
				'OSM2' => __('Thunderforest Cycle (needs key)', 'wp-gpx-maps'),
				'OSM4' => __('Thunderforest Transport (needs key)', 'wp-gpx-maps'),
				'OSM5' => __('Thunderforest Landscape (needs key)', 'wp-gpx-maps'),
				'OSM6' => __('MapTiler Outdoor (needs key)', 'wp-gpx-maps'),
				'OSM7' => __('MapTiler Topo (needs key)', 'wp-gpx-maps'),
				'OSM8' => __('MapTiler Landscape (needs key)', 'wp-gpx-maps'),
			);
		case 'wpgpxmaps_unit_of_measure':
			return array(
				'0' => __('meters/meters', 'wp-gpx-maps'),
				'1' => __('feet/miles', 'wp-gpx-maps'),
				'2' => __('meters/kilometers', 'wp-gpx-maps'),
				'3' => __('meters/nautical miles', 'wp-gpx-maps'),
				'4' => __('meters/miles', 'wp-gpx-maps'),
				'5' => __('feet/nautical miles', 'wp-gpx-maps'),
			);
		case 'wpgpxmaps_unit_of_measure_speed':
			return array(
				'0' => 'm/s',
				'1' => 'km/h',
				'2' => 'miles/h',
				'3' => 'min/km',
				'4' => 'min/miles',
				'5' => __('Nautical Miles/Hour (Knots)', 'wp-gpx-maps'),
				'6' => 'min/100 meters',
			);
	}
	return array();
}

/**
 * Sections of the settings form: title => list of [option, label, hint].
 *
 * @return array<string, array<int, array{0: string, 1: string, 2?: string}>>
 */
function wpgpxmaps_settings_sections(): array
{
	return array(
		__('General', 'wp-gpx-maps') => array(
			array('wpgpxmaps_width', __('Width', 'wp-gpx-maps'), __('CSS size, e.g. 100% or 600px.', 'wp-gpx-maps')),
			array('wpgpxmaps_height', __('Map height', 'wp-gpx-maps')),
			array('wpgpxmaps_graph_height', __('Graph height', 'wp-gpx-maps'), __('Use 0 to hide the chart.', 'wp-gpx-maps')),
			array('wpgpxmaps_distance_type', __('Distance type', 'wp-gpx-maps')),
			array('wpgpxmaps_skipcache', __('Cache', 'wp-gpx-maps'), __('Do not use cache', 'wp-gpx-maps')),
			array('wpgpxmaps_privacymode', __('Privacy mode', 'wp-gpx-maps'), __('Remove points at the start and end of the track to be more anonymous', 'wp-gpx-maps')),
			array('wpgpxmaps_arrows_enabled', __('Direction arrows', 'wp-gpx-maps'), __('Draw direction arrows along the track when not set via shortcode', 'wp-gpx-maps')),
			array('wpgpxmaps_arrows_km', __('Arrow spacing (km)', 'wp-gpx-maps')),
			array('wpgpxmaps_download', __('GPX download', 'wp-gpx-maps'), __('Allow users to download your GPX file', 'wp-gpx-maps')),
			array('wpgpxmaps_usegpsposition', __('Use browser GPS position', 'wp-gpx-maps'), __('Show the visitor position on the map', 'wp-gpx-maps')),
		),
		__('Map services', 'wp-gpx-maps') => array(
			array('wpgpxmaps_googlemapsv3_apikey', __('Google Maps API key', 'wp-gpx-maps'), __('Create a key in the Google Cloud Console (Maps JavaScript API) and restrict it to your domain.', 'wp-gpx-maps')),
			array('wpgpxmaps_googlemaps_map_id', __('Google Maps Map ID', 'wp-gpx-maps'), __('Optional. Enables Advanced Markers; without a Map ID classic markers are used.', 'wp-gpx-maps')),
			array('wpgpxmaps_maps_channel', __('Google Maps release channel', 'wp-gpx-maps')),
			array('wpgpxmaps_openstreetmap_apikey', __('Thunderforest API key', 'wp-gpx-maps'), __('Needed for the Thunderforest map types.', 'wp-gpx-maps')),
			array('wpgpxmaps_maptiler_apikey', __('MapTiler API key', 'wp-gpx-maps'), __('Needed for the MapTiler map types.', 'wp-gpx-maps')),
		),
		__('Summary table', 'wp-gpx-maps') => array(
			array('wpgpxmaps_summary', __('Summary table', 'wp-gpx-maps'), __('Print summary table', 'wp-gpx-maps')),
			array('wpgpxmaps_summary_tot_len', __('Total distance', 'wp-gpx-maps')),
			array('wpgpxmaps_summary_max_ele', __('Max elevation', 'wp-gpx-maps')),
			array('wpgpxmaps_summary_min_ele', __('Min elevation', 'wp-gpx-maps')),
			array('wpgpxmaps_summary_avg_ele', __('Avg elevation', 'wp-gpx-maps')),
			array('wpgpxmaps_summary_total_ele_up', __('Total climbing', 'wp-gpx-maps')),
			array('wpgpxmaps_summary_total_ele_down', __('Total descent', 'wp-gpx-maps')),
			array('wpgpxmaps_summary_avg_speed', __('Average speed', 'wp-gpx-maps')),
			array('wpgpxmaps_summary_total_time', __('Total time', 'wp-gpx-maps')),
		),
		__('Map', 'wp-gpx-maps') => array(
			array('wpgpxmaps_zoomonscrollwheel', __('On mouse scroll wheel', 'wp-gpx-maps'), __('Zoom the map without holding Ctrl', 'wp-gpx-maps')),
			array('wpgpxmaps_show_waypoint', __('Waypoints support', 'wp-gpx-maps'), __('Show waypoints', 'wp-gpx-maps')),
			array('wpgpxmaps_map_line_color', __('Map line color', 'wp-gpx-maps'), __('One or more colors separated by spaces.', 'wp-gpx-maps')),
			array('wpgpxmaps_map_type', __('Default map type', 'wp-gpx-maps')),
			array('wpgpxmaps_map_start_icon', __('Start icon', 'wp-gpx-maps'), __('URL of an image. Leave empty to hide.', 'wp-gpx-maps')),
			array('wpgpxmaps_map_end_icon', __('End icon', 'wp-gpx-maps'), __('URL of an image. Leave empty to hide.', 'wp-gpx-maps')),
			array('wpgpxmaps_map_current_icon', __('Current position icon', 'wp-gpx-maps'), __('URL of an image. Leave empty for the default.', 'wp-gpx-maps')),
			array('wpgpxmaps_currentpositioncon', __('Current GPS position icon', 'wp-gpx-maps'), __('URL of an image. Leave empty for the default.', 'wp-gpx-maps')),
			array('wpgpxmaps_map_waypoint_icon', __('Custom waypoint icon', 'wp-gpx-maps'), __('URL of an image. Leave empty for the default.', 'wp-gpx-maps')),
			array('wpgpxmaps_show_extreme_markers', __('Max altitude/speed markers', 'wp-gpx-maps'), __('Show markers with a hover tooltip', 'wp-gpx-maps')),
			array('wpgpxmaps_elev_color_enabled', __('Elevation highlight', 'wp-gpx-maps'), __('Color the track red where the grade exceeds the threshold', 'wp-gpx-maps')),
			array('wpgpxmaps_elev_color_threshold', __('Grade threshold (%)', 'wp-gpx-maps')),
			array('wpgpxmaps_elev_color_max', __('Max grade for full red (%)', 'wp-gpx-maps')),
		),
		__('Chart', 'wp-gpx-maps') => array(
			array('wpgpxmaps_show_elevation', __('Show altitude', 'wp-gpx-maps')),
			array('wpgpxmaps_graph_line_color', __('Altitude line color', 'wp-gpx-maps')),
			array('wpgpxmaps_unit_of_measure', __('Unit of measure', 'wp-gpx-maps')),
			array('wpgpxmaps_graph_offset_from1', __('Altitude minimum', 'wp-gpx-maps'), __('Leave empty for auto scale.', 'wp-gpx-maps')),
			array('wpgpxmaps_graph_offset_to1', __('Altitude maximum', 'wp-gpx-maps'), __('Leave empty for auto scale.', 'wp-gpx-maps')),
			array('wpgpxmaps_show_speed', __('Show speed', 'wp-gpx-maps')),
			array('wpgpxmaps_graph_line_color_speed', __('Speed line color', 'wp-gpx-maps')),
			array('wpgpxmaps_unit_of_measure_speed', __('Speed unit of measure', 'wp-gpx-maps')),
			array('wpgpxmaps_graph_offset_from2', __('Speed minimum', 'wp-gpx-maps'), __('Leave empty for auto scale.', 'wp-gpx-maps')),
			array('wpgpxmaps_graph_offset_to2', __('Speed maximum', 'wp-gpx-maps'), __('Leave empty for auto scale.', 'wp-gpx-maps')),
			array('wpgpxmaps_show_hr', __('Show heart rate (where available)', 'wp-gpx-maps')),
			array('wpgpxmaps_graph_line_color_hr', __('Heart rate line color', 'wp-gpx-maps')),
			array('wpgpxmaps_show_atemp', __('Show temperature (where available)', 'wp-gpx-maps')),
			array('wpgpxmaps_graph_line_color_atemp', __('Temperature line color', 'wp-gpx-maps')),
			array('wpgpxmaps_show_cadence', __('Show cadence (where available)', 'wp-gpx-maps')),
			array('wpgpxmaps_graph_line_color_cad', __('Cadence line color', 'wp-gpx-maps')),
			array('wpgpxmaps_show_grade', __('Show grade', 'wp-gpx-maps'), __('Grade values depend on your GPS accuracy and can be wrong with a poor signal.', 'wp-gpx-maps')),
			array('wpgpxmaps_graph_line_color_grade', __('Grade line color', 'wp-gpx-maps')),
		),
		__('Advanced options', 'wp-gpx-maps') => array(
			array('wpgpxmaps_pointsoffset', __('Skip points closer than (meters)', 'wp-gpx-maps')),
			array('wpgpxmaps_donotreducegpx', __('Do not reduce GPX', 'wp-gpx-maps'), __('Keep every point of long tracks (slower).', 'wp-gpx-maps')),
		),
	);
}

function wpgpxmaps_render_setting_field(string $option, string $label, string $hint = ''): void
{
	$schema = wpgpxmaps_settings_schema();
	if (!isset($schema[$option])) {
		return;
	}
	$definition = $schema[$option];
	$value = (string) get_option($option, $definition['default']);
	$id = esc_attr($option);
	$name = esc_attr($option);

	echo '<tr><th scope="row"><label for="' . $id . '">' . esc_html($label) . '</label></th><td>';

	switch ($definition['type']) {
		case 'bool':
			printf('<input type="checkbox" id="%1$s" name="%2$s" value="true"%3$s />', $id, $name, checked(wpgpxmaps_bool($value), true, false)); // phpcs:ignore WordPress.Security.EscapeOutput
			if ('' !== $hint) {
				echo ' <span class="description">' . esc_html($hint) . '</span>';
				$hint = '';
			}
			break;
		case 'enum':
			echo '<select id="' . $id . '" name="' . $name . '">';
			$choices = wpgpxmaps_settings_choices($option);
			foreach ($definition['choices'] as $choice) {
				$text = $choices[$choice] ?? $choice;
				echo '<option value="' . esc_attr($choice) . '"' . selected($value, $choice, false) . '>' . esc_html($text) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</select>';
			break;
		case 'color':
			// The color input only accepts #rrggbb.
			if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $value, $m)) {
				$value = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
			} elseif (!preg_match('/^#[0-9a-f]{6}$/i', $value)) {
				$value = (string) $definition['default'];
			}
			echo '<input type="color" id="' . $id . '" name="' . $name . '" value="' . esc_attr($value) . '" />';
			break;
		case 'number':
			printf(
				'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" step="any" class="small-text" />',
				$id, // phpcs:ignore WordPress.Security.EscapeOutput
				$name, // phpcs:ignore WordPress.Security.EscapeOutput
				esc_attr($value),
				esc_attr((string) $definition['min']),
				esc_attr((string) $definition['max'])
			);
			break;
		case 'number_or_empty':
			echo '<input type="number" id="' . $id . '" name="' . $name . '" value="' . esc_attr($value) . '" step="any" class="small-text" />';
			break;
		case 'size':
			echo '<input type="text" id="' . $id . '" name="' . $name . '" value="' . esc_attr($value) . '" class="small-text" />';
			break;
		case 'url':
			// Plain text input: relative URLs such as /bike-marker.png are valid and must not be blocked by the browser.
			echo '<input type="text" inputmode="url" id="' . $id . '" name="' . $name . '" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off" />';
			break;
		default:
			echo '<input type="text" id="' . $id . '" name="' . $name . '" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off" />';
	}

	if ('' !== $hint) {
		echo '<p class="description">' . esc_html($hint) . '</p>';
	}
	echo '</td></tr>';
}

function wpgpxmaps_admin_settings_tab(): void
{
	if (!current_user_can('manage_options')) {
		return;
	}

	echo '<form method="post" action="' . esc_url(admin_url('options.php')) . '">';
	settings_fields('wpgpxmaps_settings');

	foreach (wpgpxmaps_settings_sections() as $title => $fields) {
		echo '<h2 class="title">' . esc_html($title) . '</h2><table class="form-table" role="presentation">';
		foreach ($fields as $field) {
			wpgpxmaps_render_setting_field($field[0], $field[1], $field[2] ?? '');
		}
		echo '</table>';
	}

	submit_button();
	echo '</form>';
}
