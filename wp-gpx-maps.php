<?php
/*
Plugin Name: WP-GPX-Maps
Plugin URI: http://www.devfarm.it/
Description: Draws a GPX track with altitude chart
Version: 2.1.0
Author: Bastianon Massimo, Benjamin Barinka
Author URI: http://www.pedemontanadelgrappa.it/
*/

//error_reporting (E_ALL);

include 'wp-gpx-maps_utils.php';
include 'wp-gpx-maps_admin.php';

add_shortcode('sgpx','handle_WP_GPX_Maps_Shortcodes');
add_shortcode('sgpxf','handle_WP_GPX_Maps_folder_Shortcodes');
register_activation_hook(__FILE__,'WP_GPX_Maps_install'); 
register_deactivation_hook( __FILE__, 'WP_GPX_Maps_remove');
add_filter('plugin_action_links', 'WP_GPX_Maps_action_links', 10, 2);
add_action('wp_print_styles', 'print_WP_GPX_Maps_styles' );
add_action('wp_enqueue_scripts', 'enqueue_WP_GPX_Maps_scripts');
add_action('plugins_loaded' ,'WP_GPX_Maps_lang_init');

// Performance: only enqueue scripts on pages that actually contain the shortcode
$GLOBALS['wpgpxmaps_should_enqueue'] = false;
add_filter('the_posts', 'wpgpxmaps_detect_shortcodes', 9);
function wpgpxmaps_detect_shortcodes($posts){
    if (empty($posts)) { return $posts; }
    foreach ($posts as $post) {
        $content = isset($post->post_content) ? $post->post_content : '';
        if ( (function_exists('has_shortcode') && (has_shortcode($content, 'sgpx') || has_shortcode($content, 'sgpxf'))) 
            || strpos($content, '[sgpx') !== false || strpos($content, '[sgpxf') !== false) {
            $GLOBALS['wpgpxmaps_should_enqueue'] = true;
            break;
        }
    }
    return $posts;
}

// Resource hints to speed up third-party connections
add_filter('wp_resource_hints', 'wpgpxmaps_resource_hints', 10, 2);
function wpgpxmaps_resource_hints($hints, $relation_type) {
    $hosts = array(
        'https://maps.googleapis.com',
        'https://maps.gstatic.com',
        // Highcharts is now self-hosted in wp-gpx-maps/js/highcharts/
        // 'https://code.highcharts.com' - removed for self-hosting
        'https://tile.openstreetmap.org',
        'https://a.tile.thunderforest.com',
        'https://a.tile.opencyclemap.org',
        'https://api.maptiler.com',
    );
    if ($relation_type === 'dns-prefetch') {
        foreach ($hosts as $h) { $hints[] = $h; }
    } elseif ($relation_type === 'preconnect') {
        foreach ($hosts as $h) { $hints[] = array('href' => $h, 'crossorigin' => true); }
    }
    return $hints;
}

function WP_GPX_Maps_lang_init() {
   if (function_exists('load_plugin_textdomain')) {
      load_plugin_textdomain('wp-gpx-maps', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
   }
}

function WP_GPX_Maps_action_links($links, $file) {
    static $this_plugin;
 
    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }
 
    // check to make sure we are on the correct plugin
    if ($file == $this_plugin) {
        // the anchor tag and href to the URL we want. For a "Settings" link, this needs to be the url of your settings page
        $settings_link = '<a href="' . esc_url( admin_url('options-general.php?page=WP-GPX-Maps') ) . '">Settings</a>';
        // add the link to the list
        array_unshift($links, $settings_link);
    }
 
    return $links;
}

function enqueue_WP_GPX_Maps_scripts()
{      
	$wpgpxmaps_googlemapsv3_apikey = get_option('wpgpxmaps_googlemapsv3_apikey');
	$wpgpxmaps_googlemaps_map_id = get_option('wpgpxmaps_googlemaps_map_id');
	wp_enqueue_script( 'jquery' );
	// Load only when shortcode detected
	if ( isset($GLOBALS['wpgpxmaps_should_enqueue']) && $GLOBALS['wpgpxmaps_should_enqueue'] !== true ) {
		return;
	}
	{
		$mapIdParam = $wpgpxmaps_googlemaps_map_id ? ('&map_ids='.urlencode($wpgpxmaps_googlemaps_map_id)) : '';
		if ($wpgpxmaps_googlemapsv3_apikey)	{     
			wp_enqueue_script('googlemaps', 'https://maps.googleapis.com/maps/api/js?key='.$wpgpxmaps_googlemapsv3_apikey.'&loading=async&libraries=marker&v=weekly'.$mapIdParam, array(), null, true);
		}	else	{     
			wp_enqueue_script('googlemaps', 'https://maps.googleapis.com/maps/api/js?loading=async&libraries=marker&v=weekly'.$mapIdParam, array(), null, true);
		}
        // Load Highcharts version based on setting
        $use_hc_v11 = get_option('wpgpxmaps_highcharts_v11');
        if ($use_hc_v11 === '' || $use_hc_v11 === false) { $use_hc_v11 = true; }
        if ($use_hc_v11) {
            wp_enqueue_script( 'highcharts', plugins_url('/js/highcharts/v11/highcharts.js', __FILE__), array('jquery'), null, true);
            // Include accessibility module to remove console warning and improve UX
            wp_enqueue_script( 'highcharts-accessibility', plugins_url('/js/highcharts/v11/modules/accessibility.js', __FILE__), array('highcharts'), null, true);
        } else {
            wp_enqueue_script( 'highcharts', plugins_url('/js/highcharts/v3.0.10/highcharts.js', __FILE__), array('jquery'), "3.0.10", true);
        }
        $wpgpx_deps = array('jquery','googlemaps','highcharts');
        if ($use_hc_v11) { $wpgpx_deps[] = 'highcharts-accessibility'; }
		// Align script version with plugin header for cache busting
     	wp_enqueue_script( 'WP-GPX-Maps', plugins_url('/WP-GPX-Maps.js', __FILE__), $wpgpx_deps, "2.1.0", true);
 	}	

 }

function print_WP_GPX_Maps_styles()
{
?>
<style type="text/css">
	.wpgpxmaps { clear:both; }
	#content .wpgpxmaps img,
	.entry-content .wpgpxmaps img,
	.wpgpxmaps img { max-width: none; width: none; padding:0; background:none; margin:0; border:none; }
	.wpgpxmaps .ngimages { display:none; }
	.wpgpxmaps .myngimages { border:1px solid #fff;position:absolute;cursor:pointer;margin:0;z-index:1; }
	.wpgpxmaps_summary { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 16px; align-items: start; border-top: 1px solid #ddd; margin-top: 8px; padding-top: 8px; }
	.wpgpxmaps_summary > span:nth-child(odd) { justify-self: start; text-align: left; }
	.wpgpxmaps_summary > span:nth-child(even) { justify-self: end; text-align: right; }
	.wpgpxmaps_summary .summarylabel { }
	.wpgpxmaps_summary .summaryvalue { font-weight: bold; }
	.wpgpxmaps .report { line-height:120%; }
	.wpgpxmaps .gmnoprint div:first-child {  }
	/* Style Google Maps type dropdown: wider menu, smaller font */
	.wpgpxmaps .gm-style-mtc { font-size: 12px !important; white-space: nowrap !important; min-width: 220px !important; }
	.wpgpxmaps .gm-style-mtc ul { min-width: 220px !important; }
	.wpgpxmaps .gm-style-mtc ul li { font-size: 12px !important; }
	.wpgpxmaps .gm-style-mtc ul li label { display: inline !important; font-size: 12px !important; margin-left: 3px !important; }
	.wpgpxmaps .wpgpxmaps_osm_footer {
		position: absolute;
		left: 0;
		right: 0;
		bottom: 0;
		width: 100%;
		height: 13px;
		margin: 0;
		z-index: 999;
		background: WHITE;
		font-size: 12px;
	}
	
	.wpgpxmaps .wpgpxmaps_osm_footer span {
		background: WHITE;
		padding: 0 6px 6px 6px;
		vertical-align: baseline;
		position: absolute;
		bottom: 0;
	}	

	/* Spinner overlay while map initializes */
	.wpgpxmaps .wpgpxmaps_spinner {
		position: absolute;
		left: 0;
		right: 0;
		top: 0;
		bottom: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		background: rgba(255,255,255,0.6);
		z-index: 1000;
	}
	.wpgpxmaps .wpgpxmaps_spinner .spinner {
		width: 36px;
		height: 36px;
		border: 4px solid #bbb;
		border-top-color: #3366cc;
		border-radius: 50%;
		animation: wpgpxmaps_spin 1s linear infinite;
	}
	@keyframes wpgpxmaps_spin { to { transform: rotate(360deg); } }
	
</style>
<?php
}

function findValue($attr, $attributeName, $optionName, $defaultValue)
{
	$val = '';
	if ( isset($attr[$attributeName]) )	{
		$val = $attr[$attributeName];
	}
	if ($val == '')	{
		$val = get_option($optionName);
	}
	if ($val == '' && isset($_GET[$attributeName]) && $attributeName != "download")	{
		$val = sanitize_text_field( wp_unslash( $_GET[$attributeName] ) );
	}
	if ($val == '')	{
		$val = $defaultValue;
	}
	return $val;
}

function handle_WP_GPX_Maps_folder_Shortcodes($attr, $content=''){

	$folder =             findValue($attr, "folder",             "",                                 "");
	$pointsoffset =       findValue($attr, "pointsoffset",       "wpgpxmaps_pointsoffset",      		 10);
	$distanceType =       findValue($attr, "distanceType",        "wpgpxmaps_distance_type", 		 0);
	$donotreducegpx =     findValue($attr, "donotreducegpx",     "wpgpxmaps_donotreducegpx", 		 false);
	$uom =                findValue($attr, "uom",                "wpgpxmaps_unit_of_measure",        "0");
    $privacymode =        findValue($attr, "privacymode",          "wpgpxmaps_privacymode",                  "");
    	
	// fix folder path
	$sitePath = sitePath();	
	$folder = trim($folder);
	$folder = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $folder);
	// Prevent directory traversal
	if (strpos($folder, '..') !== false) {
		return "Invalid folder path";
	}
	$folder = $sitePath . $folder;	
	
	if (!is_dir($folder) || !is_readable($folder)) {
		return;
	}
	$files = @scandir($folder);
	
	foreach($files as $file) {
	
		if (substr($file, - 4) == ".gpx" ) {
		
			$gpx = $folder . DIRECTORY_SEPARATOR . $file;
			$points = getPoints($gpx, $pointsoffset, $donotreducegpx, $distanceType);
			// Align processing with primary shortcode
			$points_maps = '';
			$points_graph_dist = '';
			$points_graph_ele = '';

			$work_lat = $points->lat;
			$work_lon = $points->lon;
			$work_ele = $points->ele;
			$work_dist = $points->dist;

			if ($privacymode == true && is_array($work_lat)) {
				$N = count($work_lat);
				if ($N > 0) {
					$nb_points_to_remove = ceil($N * 0.05);
					if ($nb_points_to_remove < 100 && ($N * 0.5) > 100) { $nb_points_to_remove = 100; }
					else if ($nb_points_to_remove > 500) { $nb_points_to_remove = 500; }
					else if ($nb_points_to_remove > $N) { $nb_points_to_remove = $N; }
					else if ($nb_points_to_remove == 0) { $nb_points_to_remove = $N; }
					$trimStart = (int) floor($nb_points_to_remove / 2);
					$trimEnd   = (int) ($nb_points_to_remove - $trimStart);
					$newLen = max(0, $N - $trimStart - $trimEnd);
					$work_lat  = array_slice($work_lat,  $trimStart, $newLen);
					$work_lon  = array_slice($work_lon,  $trimStart, $newLen);
					$work_ele  = array_slice($work_ele,  $trimStart, $newLen);
					$work_dist = array_slice($work_dist, $trimStart, $newLen);
				}
			}

			if (is_array($work_lat)) {
				for ($i = 0; $i < count($work_lat); $i++) {
					$_lat = (float)$work_lat[$i];
					$_lon = (float)$work_lon[$i];
					if ($_lat == 0 && $_lon == 0) {
						$points_maps .= 'null,';
						$points_graph_dist .= 'null,';
						$points_graph_ele .= 'null,';
					} else {
						$points_maps .= '['.number_format($_lat, 7 , '.' , '' ).','.number_format($_lon, 7 , '.' , '' ).'],';
						$_ele = isset($work_ele[$i]) ? (float)$work_ele[$i] : 0;
						$_dist = isset($work_dist[$i]) ? (float)$work_dist[$i] : 0;
						if ($uom == '1') { $_dist *= 0.000621371192; $_ele *= 3.2808399; }
						else if ($uom == '2') { $_dist = ($_dist / 1000); }
						else if ($uom == '3') { $_dist = ($_dist / 1000 / 1.852); }
						else if ($uom == '4') { $_dist *= 0.000621371192; }
						else if ($uom == '5') { $_dist = ($_dist / 1000 / 1.852); $_ele *= 3.2808399; }
						$points_graph_dist .= number_format($_dist, 2, '.', '') . ',';
						$points_graph_ele .= number_format($_ele, 2, '.', '') . ',';
					}
				}
			}
			
		}
	}

	return $points_maps;
}


function handle_WP_GPX_Maps_Shortcodes($attr, $content='')
{

	$error = '';

	$gpx =                findValue($attr, "gpx",                "",                          		 "");
	$w =                  findValue($attr, "width",              "wpgpxmaps_width",           		 "100%");
	$mh =                 findValue($attr, "mheight",            "wpgpxmaps_height",          		 "450px");
	$mt =                 findValue($attr, "mtype",              "wpgpxmaps_map_type",        		 "HYBRID");
	$gh =                 findValue($attr, "gheight",            "wpgpxmaps_graph_height",    		 "200px");
	$showCad =            findValue($attr, "showcad",            "wpgpxmaps_show_cadence",   		 false);
	$showHr =             findValue($attr, "showhr",             "wpgpxmaps_show_hr",   			 false);
	$showAtemp =          findValue($attr, "showatemp",          "wpgpxmaps_show_atemp",   			 false);
	$showW =              findValue($attr, "waypoints",          "wpgpxmaps_show_waypoint",   		 false);
	$showEle =            findValue($attr, "showele",            "wpgpxmaps_show_elevation",   		 "true");
	$showSpeed =          findValue($attr, "showspeed",          "wpgpxmaps_show_speed",      		 false);
	$showGrade =          findValue($attr, "showgrade",          "wpgpxmaps_show_grade",      		 false);	
	$zoomOnScrollWheel =  findValue($attr, "zoomonscrollwheel",  "wpgpxmaps_zoomonscrollwheel",      false);
	$donotreducegpx =     findValue($attr, "donotreducegpx",     "wpgpxmaps_donotreducegpx", 		 false);
	$pointsoffset =       findValue($attr, "pointsoffset",       "wpgpxmaps_pointsoffset",     		 10);
	$uom =                findValue($attr, "uom",                "wpgpxmaps_unit_of_measure",        "0");
	$uomspeed =           findValue($attr, "uomspeed",           "wpgpxmaps_unit_of_measure_speed",  "0");
	$color_map =          findValue($attr, "mlinecolor",         "wpgpxmaps_map_line_color",         "#3366cc");
	$color_graph =        findValue($attr, "glinecolor",         "wpgpxmaps_graph_line_color",       "#3366cc");
	$color_graph_speed =  findValue($attr, "glinecolorspeed",    "wpgpxmaps_graph_line_color_speed", "#ff0000");
	$color_graph_hr =  	  findValue($attr, "glinecolorhr",       "wpgpxmaps_graph_line_color_hr",    "#ff77bd");
	$color_graph_atemp =  findValue($attr, "glinecoloratemp",    "wpgpxmaps_graph_line_color_atemp", "#ff77bd");
	$color_graph_cad =    findValue($attr, "glinecolorcad",      "wpgpxmaps_graph_line_color_cad",   "#beecff");
	$color_graph_grade =  findValue($attr, "glinecolorgrade",    "wpgpxmaps_graph_line_color_grade",  "#beecff");
	// arrows along track each N kilometers (shortcode: arrowskm="N")
	$arrows_km_attr = isset($attr['arrowskm']) ? trim($attr['arrowskm']) : "";
	$arrows_km = "";
	if ($arrows_km_attr !== "") {
		$arrows_km = $arrows_km_attr;
	} else if (get_option('wpgpxmaps_arrows_enabled')) {
		$arrows_km_opt = get_option('wpgpxmaps_arrows_km');
		if ($arrows_km_opt === false || $arrows_km_opt === '') { $arrows_km_opt = '10'; }
		$arrows_km = $arrows_km_opt;
	}
	
	$chartFrom1 =         findValue($attr, "chartfrom1",         "wpgpxmaps_graph_offset_from1",     "");
	$chartTo1 =           findValue($attr, "chartto1",           "wpgpxmaps_graph_offset_to1",       "");
	$chartFrom2 =         findValue($attr, "chartfrom2",         "wpgpxmaps_graph_offset_from2", 	 "");
	$chartTo2 =           findValue($attr, "chartto2",           "wpgpxmaps_graph_offset_to2", 		 "");
	$startIcon =          findValue($attr, "starticon",          "wpgpxmaps_map_start_icon", 		 "");
	$endIcon =            findValue($attr, "endicon",            "wpgpxmaps_map_end_icon", 			 "");
	$currentIcon =        findValue($attr, "currenticon",        "wpgpxmaps_map_current_icon", 		 "");
	$waypointIcon =       findValue($attr, "waypointicon",       "wpgpxmaps_map_waypoint_icon", 	 "");
	$ngGalleries =        findValue($attr, "nggalleries",        "wpgpxmaps_map_ngGalleries", 		 "");
	$ngImages =           findValue($attr, "ngimages",           "wpgpxmaps_map_ngImages", 		     "");
	$attachments =        findValue($attr, "attachments",        "wpgpxmaps_map_attachments", 	     true);
	$download =           findValue($attr, "download",           "wpgpxmaps_download", 		     "");
	$dtoffset =           findValue($attr, "dtoffset",           "wpgpxmaps_dtoffset", 		     0);
	$distanceType =       findValue($attr, "distanceType",       "wpgpxmaps_distance_type", 		 0);
	
	$skipcache =          findValue($attr, "skipcache",          "wpgpxmaps_skipcache", 	     "");
	$privacymode =        findValue($attr, "privacymode",          "wpgpxmaps_privacymode", 	     "");

	$summary =            findValue($attr, "summary",            "wpgpxmaps_summary", 		     "");
	$p_tot_len =          findValue($attr, "summarytotlen",      "wpgpxmaps_summary_tot_len",      	 false);
	$p_max_ele =          findValue($attr, "summarymaxele",      "wpgpxmaps_summary_max_ele",      	 false);
	$p_min_ele =          findValue($attr, "summaryminele",      "wpgpxmaps_summary_min_ele",      	 false);
	$p_total_ele_up =     findValue($attr, "summaryeleup",       "wpgpxmaps_summary_total_ele_up",   false);
	$p_total_ele_down =   findValue($attr, "summaryeledown",     "wpgpxmaps_summary_total_ele_down", false);
	$p_avg_ele =          findValue($attr, "summaryavgele",      "wpgpxmaps_summary_avg_ele",        false);
	$p_avg_speed =        findValue($attr, "summaryavgspeed",    "wpgpxmaps_summary_avg_speed",      false);
	$p_total_time =       findValue($attr, "summarytotaltime",   "wpgpxmaps_summary_total_time",     false);
	
	$usegpsposition =     findValue($attr, "usegpsposition",     "wpgpxmaps_usegpsposition",         false);
	$currentpositioncon = findValue($attr, "currentpositioncon", "wpgpxmaps_currentpositioncon", 	 "");
	
	$colors_map = "\"".implode("\",\"",(explode(" ",$color_map)))."\"";
	
	$gpxurl = $gpx;
		
	// Add file modification time to cache filename to catch new uploads with same file name
	$mtime = sitePath() . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, trim($gpx));
	if(file_exists($mtime)) {
		$mtime = filemtime($mtime);
	} else {
		$mtime = 0;
	}
	// include privacy mode and bump cache version to avoid stale misaligned data
	$cacheFileName = "$gpx,$mtime,$w,$mh,$mt,$gh,$showEle,$showW,$showHr,$showAtemp,$showCad,$donotreducegpx,$pointsoffset,$showSpeed,$showGrade,$uomspeed,$uom,$distanceType,priv=".($privacymode? '1':'0').",v2.1.0";

	$cacheFileName = md5($cacheFileName);
	
	$gpxcache = gpxCacheFolderPath();
	
	if(!(file_exists($gpxcache) && is_dir($gpxcache)))
		@mkdir($gpxcache,0755,true);
	
	$gpxcache.= DIRECTORY_SEPARATOR.$cacheFileName.".tmp";
	
	// Try to load cache
 	if (file_exists($gpxcache) && !($skipcache == true)) {
		try {
			$cache_str = file_get_contents($gpxcache);
			$cache_obj = json_decode($cache_str, true);
			if (is_array($cache_obj)) {
				$points_maps = isset($cache_obj["points_maps"]) ? $cache_obj["points_maps"] : '';
				$points_x_time = isset($cache_obj["points_x_time"]) ? $cache_obj["points_x_time"] : '';
				$points_x_lat = isset($cache_obj["points_x_lat"]) ? $cache_obj["points_x_lat"] : '';
				$points_x_lon = isset($cache_obj["points_x_lon"]) ? $cache_obj["points_x_lon"] : '';
				$points_graph_dist = isset($cache_obj["points_graph_dist"]) ? $cache_obj["points_graph_dist"] : '';
				$points_graph_ele = isset($cache_obj["points_graph_ele"]) ? $cache_obj["points_graph_ele"] : '';
				$points_graph_speed = isset($cache_obj["points_graph_speed"]) ? $cache_obj["points_graph_speed"] : '';
				$points_graph_hr = isset($cache_obj["points_graph_hr"]) ? $cache_obj["points_graph_hr"] : '';
				$points_graph_atemp = isset($cache_obj["points_graph_atemp"]) ? $cache_obj["points_graph_atemp"] : '';
				$points_graph_cad = isset($cache_obj["points_graph_cad"]) ? $cache_obj["points_graph_cad"] : '';
				$points_graph_grade = isset($cache_obj["points_graph_grade"]) ? $cache_obj["points_graph_grade"] : '';
				$points_map_grade = isset($cache_obj["points_map_grade"]) ? $cache_obj["points_map_grade"] : '';
				$waypoints = isset($cache_obj["waypoints"]) ? $cache_obj["waypoints"] : '[]';
				$max_ele = isset($cache_obj["max_ele"]) ? $cache_obj["max_ele"] : 0;
				$avg_ele_str = isset($cache_obj["avg_ele"]) ? $cache_obj["avg_ele"] : '';
				$min_ele = isset($cache_obj["min_ele"]) ? $cache_obj["min_ele"] : 0;
				$max_time = isset($cache_obj["max_time"]) ? $cache_obj["max_time"] : 0;
				$min_time = isset($cache_obj["min_time"]) ? $cache_obj["min_time"] : 0;
				$total_ele_up = isset($cache_obj["total_ele_up"]) ? $cache_obj["total_ele_up"] : 0;
				$total_ele_down = isset($cache_obj["total_ele_down"]) ? $cache_obj["total_ele_down"] : 0;
				$avg_speed = isset($cache_obj["avg_speed"]) ? $cache_obj["avg_speed"] : 0;
				$tot_len = isset($cache_obj["tot_len"]) ? $cache_obj["tot_len"] : 0;
			} else {
				$points_maps = '';
				$points_x_time = '';
				$points_x_lat = '';
				$points_x_lon = '';
				$points_graph_dist = '';
				$points_graph_ele = '';
				$points_graph_speed = '';
				$points_graph_hr = '';
				$points_graph_atemp = '';
				$points_graph_cad = '';
				$points_graph_grade = '';
				$points_map_grade = '';
				$waypoints= '[]';
				$max_ele = 0; $min_ele = 0; $max_time = 0; $min_time = 0; $total_ele_up = 0; $total_ele_down = 0; $avg_speed = 0; $tot_len = 0;
				$avg_ele_str = '';
			}
		} catch (Exception $e) {
			$points_maps = '';
			$points_x_time = '';
			$points_x_lat = '';
			$points_x_lon = '';
			$points_graph_dist = '';
			$points_graph_ele = '';
			$points_graph_speed = '';
			$points_graph_hr = '';
			$points_graph_atemp = '';
			$points_graph_cad = '';
			$points_graph_grade = '';
			$points_map_grade = '';
			$waypoints= '[]';
			$max_ele = 0; $min_ele = 0; $max_time = 0; $min_time = 0; $total_ele_up = 0; $total_ele_down = 0; $avg_speed = 0; $tot_len = 0;
			$avg_ele_str = '';
		}
	}
	
	$isGpxUrl = (preg_match('/^(http(s)?\:\/\/)/', trim($gpx)) == 1);

	if ((!isset($points_maps) || $points_maps == '') && $gpx != '')	{
	//if (true)	{
		
		$sitePath = sitePath();
		
		$gpx = trim($gpx);
		
		if ($isGpxUrl == true) {
			// SSRF mitigation: allow only http/https and reject private IP ranges
			if (!preg_match('#^https?://#i', $gpx)) { return "Invalid GPX url"; }
			$host = parse_url($gpx, PHP_URL_HOST);
			if ($host) {
				$ips = gethostbynamel($host);
				$blocked = false;
				if ($ips) {
					foreach ($ips as $ip) {
						if (preg_match('#^(10\.|127\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)#', $ip)) { $blocked = true; break; }
					}
				}
				if ($blocked) { return "Blocked host"; }
			}
			$gpx = downloadRemoteFile($gpx);
		}
		else {
			$gpx = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $gpx);
			$gpx = $sitePath . $gpx;
		}

		if ($gpx == '')	{
			return "No gpx found";
		}
		
		$points = getPoints( $gpx, $pointsoffset, $donotreducegpx, $distanceType);
		
		$points_maps = '';
		$points_graph_dist = '';
		$points_graph_ele = '';
		$points_graph_speed = '';
		$points_graph_hr = '';
		$points_graph_atemp = '';
		$points_graph_cad = '';
		$points_graph_grade = '';
		$points_map_grade = '';
		$waypoints = '';

		$points_x_time = $points->dt;
		$points_x_lat = $points->lat;
		$points_x_lon = $points->lon;
		$work_dt   = $points->dt;
		$work_lat  = $points->lat;
		$work_lon  = $points->lon;
		$work_ele  = $points->ele;
		$work_dist = $points->dist;
		$work_speed= $points->speed;
		$work_hr   = $points->hr;
		$work_atemp= $points->atemp;
		$work_cad  = $points->cad;
		$work_grade= $points->grade;
		
		//privacy: trim all arrays consistently to keep alignment
		if ($privacymode == true) {
			if (is_array($work_lat)) {
				$N = count($work_lat);
				if ($N > 0) {
					$nb_points_to_remove = ceil($N * 0.05);
					if ($nb_points_to_remove < 100 && ($N * 0.5) > 100) { $nb_points_to_remove = 100; }
					else if ($nb_points_to_remove > 500) { $nb_points_to_remove = 500; }
					else if ($nb_points_to_remove > $N) { $nb_points_to_remove = $N; }
					else if ($nb_points_to_remove == 0) { $nb_points_to_remove = $N; }
					$trimStart = (int) floor($nb_points_to_remove / 2);
					$trimEnd   = (int) ($nb_points_to_remove - $trimStart);
					$newLen = max(0, $N - $trimStart - $trimEnd);
					$work_dt    = array_slice($work_dt,    $trimStart, $newLen);
					$work_lat   = array_slice($work_lat,   $trimStart, $newLen);
					$work_lon   = array_slice($work_lon,   $trimStart, $newLen);
					$work_ele   = array_slice($work_ele,   $trimStart, $newLen);
					$work_dist  = array_slice($work_dist,  $trimStart, $newLen);
					$work_speed = array_slice($work_speed, $trimStart, $newLen);
					$work_hr    = array_slice($work_hr,    $trimStart, $newLen);
					$work_atemp = array_slice($work_atemp, $trimStart, $newLen);
					$work_cad   = array_slice($work_cad,   $trimStart, $newLen);
					$work_grade = array_slice($work_grade, $trimStart, $newLen);
				}
			}
		}

		$max_ele = $points->maxEle;
		$min_ele = $points->minEle;
		// Compute average elevation (meters) over available samples
		$avg_ele_value = 0;
		if (is_array($points->ele) && count($points->ele) > 0) {
			$sumEle = 0; $cntEle = 0;
			foreach ($points->ele as $_e) {
				if ($_e !== null && $_e !== false && is_numeric($_e)) { $sumEle += (float)$_e; $cntEle++; }
			}
			$avg_ele_value = ($cntEle > 0) ? ($sumEle / $cntEle) : 0;
		}
		$max_time = $points->maxTime;
		$min_time =  $points->minTime;
		$total_ele_up = $points->totalEleUp;
		$total_ele_down = $points->totalEleDown;
		$avg_speed = $points->avgSpeed;
		$tot_len = $points->totalLength;

		// Compute movement/climbing metrics
		$moving_time_seconds = 0;
		$time_climbing_seconds = 0;
		$climb_distance_m = 0.0;
		$avg_climb_speed_mps = 0.0;
		if (is_array($points->dt) && is_array($points->speed)) {
			$len_dt = count($points->dt);
			for ($i2 = 1; $i2 < $len_dt; $i2++) {
				$t_prev = $points->dt[$i2-1];
				$t_curr = $points->dt[$i2];
				if ($t_prev && $t_curr && is_numeric($t_prev) && is_numeric($t_curr)) {
					$delta = (int)$t_curr - (int)$t_prev;
					if ($delta > 0) {
						$_speed = isset($points->speed[$i2]) ? (float)$points->speed[$i2] : 0.0; // m/s
						if ($_speed > 0.5) { $moving_time_seconds += $delta; }
						$_grade = isset($points->grade[$i2]) ? (float)$points->grade[$i2] : 0.0;
						if ($_grade > 0.0) {
							$time_climbing_seconds += $delta;
							if (isset($points->dist[$i2]) && isset($points->dist[$i2-1])) {
								$dd = (float)$points->dist[$i2] - (float)$points->dist[$i2-1];
								if ($dd > 0) { $climb_distance_m += $dd; }
							}
						}
					}
				}
			}
			if ($time_climbing_seconds > 0) { $avg_climb_speed_mps = $climb_distance_m / $time_climbing_seconds; }
		}

		// compute arrow repeat percentage for google polyline symbols
		$arrowRepeat = "";
		if ($arrows_km !== "" && is_numeric($arrows_km) && $arrows_km > 0 && $tot_len > 0) {
			$repeatPercent = ($arrows_km * 1000.0) / $tot_len * 100.0;
			// clamp sane bounds
			if ($repeatPercent < 0.1) { $repeatPercent = 0.1; }
			if ($repeatPercent > 100) { $repeatPercent = 100; }
			$arrowRepeat = number_format($repeatPercent, 6, '.', '') . '%';
		}
	
                // Privacy trimming already applied to work_* arrays above via array_slice.
                // The points_x_lat/lon arrays are only used for NGG image geo-matching
                // and do not need separate trimming.

		if (is_array ($work_lat))
		for ($i = 0; $i < count($work_lat); $i++) {
			
			if (!isset($work_lat[$i], $work_lon[$i])) { continue; }
			$_lat = (float)$work_lat[$i];
			$_lon = (float)$work_lon[$i];
			
			if ( $_lat == 0 && $_lon == 0 )
			{
				$points_maps .= 'null,';
				$points_graph_dist .= 'null,';
				$points_graph_ele .= 'null,';
					
				if ($showSpeed == true) 
					$points_graph_speed .= 'null,';

				if ($showHr == true)
					$points_graph_hr .= 'null,';
					
				if ($showAtemp == true)
					$points_graph_atemp .= 'null,';
					
				if ($showCad == true)
					$points_graph_cad .= 'null,';
					
				if ($showGrade == true)
					$points_graph_grade .= 'null,';
					
			}
			else {
				$points_maps .= '['.number_format((float)$work_lat[$i], 7 , '.' , '' ).','.number_format((float)$work_lon[$i], 7 , '.' , '' ).'],';	

				$_ele = isset($work_ele[$i]) ? (float)$work_ele[$i] : 0;
				$_dist = isset($work_dist[$i]) ? (float)$work_dist[$i] : 0;
				
				if ($uom == '1')
				{
					// Miles and feet			
					$_dist *= 0.000621371192;
					$_ele *= 3.2808399;
				} else if ($uom == '2')
				{
					// meters / kilometers
					$_dist = (float)($_dist / 1000);
				} else if ($uom == '3')
				{
					// meters / kilometers / nautical miles
					$_dist = (float)($_dist / 1000 / 1.852);
					}
				 else if ($uom == '4')
				{
					// meters / miles
					$_dist *= 0.000621371192;
				} else if ($uom == '5')
				{
					// meters / kilometers / nautical miles and feet
					$_dist = (float)($_dist / 1000 / 1.852);
					$_ele *= 3.2808399;
				}
				
				$points_graph_dist .= number_format ( $_dist , 2 , '.' , '' ).',';
				$points_graph_ele .= number_format ( $_ele , 2 , '.' , '' ).',';
					
				if ($showSpeed == true) {
					
					$_speed = isset($work_speed[$i]) ? (float)$work_speed[$i] : 0;
					
					$points_graph_speed .= convertSpeed($_speed,$uomspeed).',';
				}
				
				if ($showHr == true) {
					$points_graph_hr .= number_format ( isset($work_hr[$i]) ? $work_hr[$i] : 0 , 2 , '.' , '' ).',';
				}
				
				if ($showAtemp == true) {
					$points_graph_atemp .= number_format ( isset($work_atemp[$i]) ? $work_atemp[$i] : 0 , 1 , '.' , '' ).',';
				}
				
				if ($showCad == true) {
					$points_graph_cad .= number_format ( isset($work_cad[$i]) ? $work_cad[$i] : 0 , 2 , '.' , '' ).',';
				}
				
				if ($showGrade == true) {
					$points_graph_grade .= number_format ( isset($work_grade[$i]) ? $work_grade[$i] : 0 , 2 , '.' , '' ).',';
					$points_map_grade .= number_format ( $work_grade[$i] , 2 , '.' , '' ).',';
				}
				// always collect map grade for coloring feature
				$points_map_grade .= number_format ( isset($work_grade[$i]) ? $work_grade[$i] : 0 , 2 , '.' , '' ).',';
			}
		}	

		if ($uom == '1') {
			// Miles and feet			
			$tot_len = round($tot_len * 0.000621371192, 2)." mi";
			$max_ele = round($max_ele * 3.2808399, 0)." ft";
			$min_ele = round($min_ele * 3.2808399, 0)." ft";
			$total_ele_up = round($total_ele_up * 3.2808399, 0)." ft";
			$total_ele_down = round($total_ele_down * 3.2808399, 0)." ft";
			$avg_ele_str = number_format($avg_ele_value * 3.2808399, 0, '.', '')." ft";
		} 
		else if ($uom == '2') {
			// meters / kilometers
			$tot_len = round($tot_len / 1000, 2)." km";
			$max_ele = round($max_ele, 0) ." m";
			$min_ele = round($min_ele, 0) ." m";
			$total_ele_up = round($total_ele_up, 0) ." m";
			$total_ele_down = round($total_ele_down, 0) ." m";
			$avg_ele_str = number_format($avg_ele_value, 0, '.', '')." m";
		} 
		else if ($uom == '3') {
			// meters / kilometers / nautical miles
			$tot_len = round($tot_len / 1000/1.852, 2)." NM";
			$max_ele = round($max_ele, 0) ." m";
			$min_ele = round($min_ele, 0) ." m";
			$total_ele_up = round($total_ele_up, 0) ." m";
			$total_ele_down = round($total_ele_down, 0) ." m";
			$avg_ele_str = number_format($avg_ele_value, 0, '.', '')." m";
		}
		else if ($uom == '4') {
			// meters / kilometers / nautical miles
			$tot_len = round($tot_len * 0.000621371192, 2)." mi";
			$max_ele = round($max_ele, 0) ." m";
			$min_ele = round($min_ele, 0) ." m";
			$total_ele_up = round($total_ele_up, 0) ." m";
			$total_ele_down = round($total_ele_down, 0) ." m";
			$avg_ele_str = number_format($avg_ele_value, 0, '.', '')." m";
		}
		else if ($uom == '5') {
			// meters / kilometers / nautical miles and feet
			$tot_len = round($tot_len / 1000/1.852, 2)." NM";
			$max_ele = round($max_ele * 3.2808399, 0)." ft";
			$min_ele = round($min_ele * 3.2808399, 0)." ft";
			$total_ele_up = round($total_ele_up * 3.2808399, 0)." ft";
			$total_ele_down = round($total_ele_down * 3.2808399, 0)." ft";
			$avg_ele_str = number_format($avg_ele_value * 3.2808399, 0, '.', '')." ft";
		}
		else {
			// meters / meters
			$tot_len = round($tot_len, 0) ." m";
			$max_ele = round($max_ele, 0) ." m";
			$min_ele = round($min_ele, 0) ." m";
			$total_ele_up = round($total_ele_up, 0) ." m";
			$total_ele_down = round($total_ele_down, 0) ." m";
			$avg_ele_str = number_format($avg_ele_value, 0, '.', '')." m";
		}

		$avg_speed = convertSpeed($avg_speed,$uomspeed,true);
		$waypoints = '[]';
		
		if ($showW == true) {
			$wpoints = getWayPoints($gpx);
			/*
			foreach ($wpoints as $p) {
				$waypoints .= '['.number_format ( (float)$p[0] , 7 , '.' , '' ).','.number_format ( (float)$p[1] , 7 , '.' , '' ).',\''.unescape($p[4]).'\',\''.unescape($p[5]).'\',\''.unescape($p[7]).'\'],';
			}
			*/
			$waypoints = json_encode($wpoints);
		}

		if ($showEle == "false")
		{
			$points_graph_ele = "";
		}

		$p="/(,|,null,)$/";

		$points_maps = preg_replace($p, "", $points_maps);

		$points_graph_dist = preg_replace($p, "", $points_graph_dist);
		$points_graph_ele = preg_replace($p, "", $points_graph_ele);
		$points_graph_speed = preg_replace($p, "", $points_graph_speed);
		$points_graph_hr = preg_replace($p, "", $points_graph_hr);
		$points_graph_atemp = preg_replace($p, "", $points_graph_atemp);
		$points_graph_cad = preg_replace($p, "", $points_graph_cad);
		$points_graph_grade = preg_replace($p, "", $points_graph_grade);
		$points_map_grade = preg_replace($p, "", $points_map_grade);
					
		if (preg_match("/^(0,?)+$/", $points_graph_dist)) 
			$points_graph_dist = "";
			
		if (preg_match("/^(0,?)+$/", $points_graph_ele)) 
			$points_graph_ele = "";
			
		if (preg_match("/^(0,?)+$/", $points_graph_speed)) 
			$points_graph_speed = "";
			
		if (preg_match("/^(0,?)+$/", $points_graph_hr)) 
			$points_graph_hr = "";
			
		if (preg_match("/^(0,?)+$/", $points_graph_hr)) 
			$points_graph_hr = "";
			
		if (preg_match("/^(0,?)+$/", $points_graph_atemp)) 
			$points_graph_atemp = "";
			
		if (preg_match("/^(0,?)+$/", $points_graph_grade)) 
			$points_graph_grade = "";
		
	}

	$ngimgs_data = '';
	// Skip NGG fetch if not configured
	if ( ($ngGalleries != '' || $ngImages != '') ) {
	
	//print_r($points);
	
		$ngimgs = getNGGalleryImages($ngGalleries, $ngImages, $points_x_time, $points_x_lat, $points_x_lon, $dtoffset, $error);
		$ngimgs_data ='';	
		foreach ($ngimgs as $img) {		
			$data = $img['data'];
			$data = str_replace("\n","",$data);
			$ngimgs_data .= '<span lat="'.$img['lat'].'" lon="'.$img['lon'].'">'.$data.'</span>';
		}
	}
// Folgende Zeilen hinzugefügt
	// Skip attachments fetch unless enabled
	if ($attachments == true) {
		$attimgs = getAttachedImages($points_x_time, $points_x_lat, $points_x_lon, $dtoffset, $error);
		foreach ($attimgs as $img) {		
			$data = $img['data'];
			$data = str_replace("\n","",$data);
			$ngimgs_data .= '<span lat="'.$img['lat'].'" lon="'.$img['lon'].'">'.$data.'</span>';
		}
	}
	
	if (!($skipcache == true)) {
		
		@file_put_contents(
			$gpxcache,
			json_encode(array(
				"points_maps"       => $points_maps,
				"points_x_time"     => $points_x_time,
				"points_x_lat"      => $points_x_lat,
				"points_x_lon"      => $points_x_lon,
				"points_graph_dist" => $points_graph_dist,
				"points_graph_ele"  => $points_graph_ele,
				"points_graph_speed"=> $points_graph_speed,
				"points_graph_hr"   => $points_graph_hr,
				"points_graph_atemp"=> $points_graph_atemp,
				"points_graph_cad"  => $points_graph_cad,
				"points_graph_grade"=> $points_graph_grade,
				"points_map_grade"  => $points_map_grade,
				"waypoints"         => $waypoints,
				"max_ele"           => $max_ele,
				"avg_ele"           => isset($avg_ele_str) ? $avg_ele_str : '',
				"min_ele"           => $min_ele,
				"total_ele_up"      => $total_ele_up,
				"total_ele_down"    => $total_ele_down,
				"avg_speed"         => $avg_speed,
				"tot_len"           => $tot_len,
				"max_time"          => $max_time,
				"min_time"          => $min_time
			), JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
			LOCK_EX
		);
		@chmod($gpxcache,0644);		
	}
	
	$hideGraph = ($gh == "0" || $gh == "0px");
	
	global $post;
	$post_id = ($post && isset($post->ID)) ? $post->ID : mt_rand(1, 99999);
	$r = $post_id."_".rand(1,5000000);	

	// Recompute arrowRepeat now that data is available (also works when cache was used)
	$arrowRepeat = "";
	if ($arrows_km !== "" && is_numeric($arrows_km) && $arrows_km > 0) {
		$tot_len_meters = 0;
		if (is_numeric($tot_len)) {
			$tot_len_meters = (float)$tot_len; // in meters
		} else {
			// Try to derive total length from the last distance value in points_graph_dist
			if (!empty($points_graph_dist)) {
				$distParts = explode(',', $points_graph_dist);
				$lastVal = 0;
				for ($idx = count($distParts)-1; $idx >= 0; $idx--) {
					$val = trim($distParts[$idx]);
					if ($val !== '' && is_numeric($val)) { $lastVal = (float)$val; break; }
				}
				if ($lastVal > 0) {
					// Convert based on unit of measure (uom)
					if ($uom == '2') { // km
						$tot_len_meters = $lastVal * 1000.0;
					} else if ($uom == '1' || $uom == '4') { // miles
						$tot_len_meters = $lastVal * 1609.344;
					} else if ($uom == '3' || $uom == '5') { // nautical miles
						$tot_len_meters = $lastVal * 1852.0;
					} else { // meters
						$tot_len_meters = $lastVal;
					}
				}
			}
		}
		if ($tot_len_meters > 0) {
			$repeatPercent = ($arrows_km * 1000.0) / $tot_len_meters * 100.0;
			if ($repeatPercent < 0.1) { $repeatPercent = 0.1; }
			if ($repeatPercent > 100) { $repeatPercent = 100; }
			$arrowRepeat = number_format($repeatPercent, 6, '.', '') . '%';
		}
	}

	$output = '
		<div id="wpgpxmaps_'.$r.'" class="wpgpxmaps">
			<div id="map_'.$r.'_cont" style="width:'.$w.'; height:'.$mh.';position:relative" >
				<div class="wpgpxmaps_spinner" id="spinner_'.$r.'"><div class="spinner"></div></div>
				<div id="map_'.$r.'" style="width:'.$w.'; height:'.$mh.'"></div>
				<div id="wpgpxmaps_'.$r.'_osm_footer" class="wpgpxmaps_osm_footer" style="display:none;"><span> &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors</span></div>			
			</div>
			<div id="hchart_'.$r.'" class="plot" style="width:'.$w.'; height:'.$gh.'"></div>
			<div id="ngimages_'.$r.'" class="ngimages" style="display:none">'.$ngimgs_data.'</div>
			<div id="report_'.$r.'" class="report"></div>
		</div>
		'. $error .'
		<script type="text/javascript">
			window.WPGPXMAPS_MAP_ID = "'.esc_js(get_option('wpgpxmaps_googlemaps_map_id')).'";
			(function initWPGPX(){
				if (window.jQuery && jQuery.fn && jQuery.fn.wpgpxmaps) {
					jQuery(function(){
						jQuery("#wpgpxmaps_'.$r.'").wpgpxmaps({ 
					targetId    : "'.$r.'",
					mapType     : "'.esc_js($mt).'",
					mapData     : ['.$points_maps.'],
					graphDist   : ['.($hideGraph ? '' : $points_graph_dist).'],
					graphEle    : ['.($hideGraph ? '' : $points_graph_ele).'],
					graphSpeed  : ['.($hideGraph ? '' : $points_graph_speed).'],
					graphHr     : ['.($hideGraph ? '' : $points_graph_hr).'],
					graphAtemp  : ['.($hideGraph ? '' : $points_graph_atemp).'],
					graphCad    : ['.($hideGraph ? '' : $points_graph_cad).'],
					graphGrade  : ['.($hideGraph ? '' : $points_graph_grade).'],
					mapGrade    : ['.$points_map_grade.'],
					waypoints   : '.$waypoints.',
					unit        : "'.esc_js($uom).'",
					unitspeed   : "'.esc_js($uomspeed).'",
					color1      : ['.$colors_map.'],
					color2      : "'.esc_js($color_graph).'",
					color3      : "'.esc_js($color_graph_speed).'",
					color4      : "'.esc_js($color_graph_hr).'",
					color5      : "'.esc_js($color_graph_cad).'",
					color6      : "'.esc_js($color_graph_grade).'",
					color7      : "'.esc_js($color_graph_atemp).'",
					arrowRepeat : "'.esc_js($arrowRepeat).'",
					chartFrom1  : "'.esc_js($chartFrom1).'",
					chartTo1    : "'.esc_js($chartTo1).'",
					chartFrom2  : "'.esc_js($chartFrom2).'",
					chartTo2    : "'.esc_js($chartTo2).'",
					startIcon   : "'.esc_js($startIcon).'",
					endIcon     : "'.esc_js($endIcon).'",
					currentIcon : "'.esc_js($currentIcon).'",
					waypointIcon : "'.esc_js($waypointIcon).'",
					currentpositioncon : "'.esc_js($currentpositioncon).'",
					usegpsposition : "'.esc_js($usegpsposition).'",
					zoomOnScrollWheel : "'.esc_js($zoomOnScrollWheel).'", 
					ngGalleries : ['.$ngGalleries.'],
					ngImages : ['.$ngImages.'],
					pluginUrl : "'.plugins_url().'",
					elevColoringEnabled : '.(get_option('wpgpxmaps_elev_color_enabled') ? 'true' : 'false').',
					elevColorThreshold : "'.esc_js(get_option('wpgpxmaps_elev_color_threshold', '5')).'",
					elevColorMax : "'.esc_js(get_option('wpgpxmaps_elev_color_max', '12')).'",
				TFApiKey : "'.esc_js(get_option('wpgpxmaps_openstreetmap_apikey')).'",
				MTApiKey : "'.esc_js(get_option('wpgpxmaps_maptiler_apikey')).'",
					langs : { altitude              : "'.__("Altitude", "wp-gpx-maps").'",
							  currentPosition       : "'.__("Current Position", "wp-gpx-maps").'",
							  speed                 : "'.__("Speed", "wp-gpx-maps").'", 
							  grade                 : "'.__("Grade", "wp-gpx-maps").'", 
							  heartRate             : "'.__("Heart rate", "wp-gpx-maps").'", 
							  atemp             	: "'.__("Temperature", "wp-gpx-maps").'", 
							  cadence               : "'.__("Cadence", "wp-gpx-maps").'",
							  goFullScreen          : "'.__("Go Full Screen", "wp-gpx-maps").'",
							  exitFullFcreen        : "'.__("Exit Full Screen", "wp-gpx-maps").'",
							  hideImages            : "'.__("Hide Images", "wp-gpx-maps").'",
							  showImages            : "'.__("Show Images", "wp-gpx-maps").'",
							  backToCenter		    : "'.__("Back to center", "wp-gpx-maps").'",
							  avgLabel              : "'.__("avg", "wp-gpx-maps").'",
							  avgAltitude           : "'.__("Avg altitude", "wp-gpx-maps").'"
						}
				});
					});
				} else {
					setTimeout(initWPGPX, 60);
				}
			})();
			</script>';	

	// print summary
	if ($summary=='true' && ( $points_graph_speed != '' || $points_graph_ele != '' || $points_graph_dist != '') ) {
		
		$output .= "<div id='wpgpxmaps_summary_".$r."' class='wpgpxmaps_summary'>";
		// Row 1: Total distance | Max elevation
		if ($points_graph_dist != '' && $p_tot_len == 'true')
		{
			$output .= "<span class='totlen'><span class='summarylabel'>".__("Total distance", "wp-gpx-maps").":</span><span class='summaryvalue'> $tot_len</span></span>";
		}
		if ($points_graph_ele != '' && $p_max_ele == 'true')
			$output .= "<span class='maxele'><span class='summarylabel'>".__("Max elevation", "wp-gpx-maps").":</span><span class='summaryvalue'> $max_ele</span></span>";
		// Row 2: Total climbing | Min elevation
		if ($points_graph_ele != '' && $p_total_ele_up == 'true')
			$output .= "<span class='totaleleup'><span class='summarylabel'>".__("Total climbing", "wp-gpx-maps").":</span><span class='summaryvalue'> $total_ele_up</span></span>";
		if ($points_graph_ele != '' && $p_min_ele == 'true')
			$output .= "<span class='minele'><span class='summarylabel'>".__("Min elevation", "wp-gpx-maps").":</span><span class='summaryvalue'> $min_ele</span></span>";
		// Row 3: Total descent | Avg elevation
		if ($points_graph_ele != '' && $p_total_ele_down == 'true')
			$output .= "<span class='totaleledown'><span class='summarylabel'>".__("Total descent", "wp-gpx-maps").":</span><span class='summaryvalue'> $total_ele_down</span></span>";
		if ($points_graph_ele != '' && $p_avg_ele == 'true' && !empty($avg_ele_str))
			$output .= "<span class='avgele'><span class='summarylabel'>".__("Avg elevation", "wp-gpx-maps").":</span><span class='summaryvalue'> $avg_ele_str</span></span>";
		// Row 4: Total Time | Average speed
		if ($points_graph_speed != '' && $p_avg_speed == 'true')
		{
			$output .= "<span class='avgspeed'><span class='summarylabel'>".__("Average speed", "wp-gpx-maps").":</span><span class='summaryvalue'> $avg_speed</span></span>";
		}
		if ($p_total_time == 'true' && $max_time > 0)
		{		
			$time_diff = date("H:i:s", ($max_time - $min_time));
			$output .= "<span class='totaltime'><span class='summarylabel'>".__("Total Time", "wp-gpx-maps").":</span><span class='summaryvalue'> $time_diff</span></span>";
		}
		// New extra metrics
		$__tot_seconds = ($max_time > 0 && $min_time > 0 && ($max_time - $min_time) > 0) ? ($max_time - $min_time) : 0;
		if (isset($moving_time_seconds) && $__tot_seconds > 0 && $moving_time_seconds > 0) {
			$move_ratio = round($moving_time_seconds / $__tot_seconds * 100);
			$output .= "<span class='moveratio'><span class='summarylabel'>".__("Move ratio", "wp-gpx-maps").":</span><span class='summaryvalue'> $move_ratio%</span></span>";
		}
		if (isset($time_climbing_seconds) && $time_climbing_seconds > 0) {
			$time_climb_str = date("H:i:s", $time_climbing_seconds);
			$output .= "<span class='timeclimb'><span class='summarylabel'>".__("Time climbing", "wp-gpx-maps").":</span><span class='summaryvalue'> $time_climb_str</span></span>";
		}
		if (isset($avg_climb_speed_mps) && $avg_climb_speed_mps > 0) {
			$avg_climb_speed_disp = convertSpeed($avg_climb_speed_mps, $uomspeed, true);
			$output .= "<span class='avgclimbspeed'><span class='summarylabel'>".__("Avg climbing speed", "wp-gpx-maps").":</span><span class='summaryvalue'> $avg_climb_speed_disp</span></span>";
		}
		$output .= "</div>";
	}
	
	// print download link
	if ($download=='true' && $gpxurl != '') {
		if ($isGpxUrl == true) {

		}
		else {
			// wpml fix
			$dummy = ( defined('WP_SITEURL') ) ? WP_SITEURL : get_bloginfo('url');
			$gpxurl = $dummy.$gpxurl;
		}
		$output.="<div class='wpgpxmaps_download'><a class='wpgpxmaps_download_link' href='".esc_url($gpxurl)."' target='_blank' rel='noopener' download>".__("Download GPX", "wp-gpx-maps")."</a></div>";
	}

	return $output;
}

function convertSeconds($s){
	if ($s ==0)		return 0;
	$s =  1.0 / $s;
	$_sSecT = $s * 60; //sec/km
	$_sMin = floor ( $_sSecT / 60 );
	$_sSec = $_sSecT - $_sMin * 60;
	return $_sMin + $_sSec / 100;
}

function convertSpeed($speed,$uomspeed, $addUom = false){
	$uom = '';
	if ($uomspeed == '6') /* min/100 meters */	{		
		$speed = 1 / $speed * 100 / 60 ;		
		$uom = " min/100m";	
	} 	else if ($uomspeed == '5') /* knots */	{
		$speed *= 1.94384449;		
		$uom = " knots";
	} 	else if ($uomspeed == '4') /* min/mi */	{
		$speed = convertSeconds($speed * 0.037282272);			
		$uom = " min/mi";
	} 	else if ($uomspeed == '3') /* min/km */	{
		$speed = convertSeconds($speed * 0.06);		
		$uom = " min/km";
	} 	else if ($uomspeed == '2') /* miles/h */	{
		$speed *= 2.2369362920544025;		
		$uom = " mi/h";
	} 	else if ($uomspeed == '1') /* km/h */	{
		$speed *= 3.6;		
		$uom = " km/h";
	}	else	/* dafault m/s */	{				
		$uom = " m/s";			
	}	
	
	if ($addUom == true)	{		
		return number_format ( $speed , 2 , '.' , '' ) . $uom;	
	}	else	{		
		return number_format ( $speed , 2 , '.' , '' );	
	}
}

function downloadRemoteFile($remoteFile)
{
	try
	{
		$args = array(
			'timeout'      => 12,
			'redirection'  => 3,
			'headers'      => array( 'User-Agent' => 'WP-GPX-Maps/2.1.0' ),
			'reject_unsafe_urls' => true,
		);
		$response = wp_remote_get( $remoteFile, $args );
		if ( is_wp_error( $response ) ) {
			return '';
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return '';
		}
		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return '';
		}
		if ( function_exists( 'wp_tempnam' ) ) {
			$newfname = wp_tempnam( $remoteFile );
		} else {
			$newfname = tempnam( get_temp_dir(), 'gpx' );
		}
		if ( ! $newfname ) {
			return '';
		}
		file_put_contents( $newfname, $body );
		return $newfname;
	}
	catch (Exception $e) {
		return '';
	}
}

function unescape($value)
{
	$value = str_replace("'", "\'", $value);
	$value = str_replace(array("\n","\r"), "", $value);
	return $value;
}

function WP_GPX_Maps_install() {
	add_option("wpgpxmaps_width", '100%', '', 'yes');
	add_option("wpgpxmaps_graph_height", '200px', '', 'yes');
	add_option("wpgpxmaps_height", '450px', '', 'yes');
	add_option('wpgpxmaps_map_type','HYBRID','','yes');
	add_option('wpgpxmaps_show_waypoint','','','yes');
	add_option('wpgpxmaps_show_speed','','','yes');
	add_option('wpgpxmaps_pointsoffset','10','','yes');
	add_option('wpgpxmaps_donotreducegpx','true','','yes');
	add_option("wpgpxmaps_unit_of_measure", '0', '', 'yes');
	add_option("wpgpxmaps_unit_of_measure_speed", '0', '', 'yes');
	add_option("wpgpxmaps_graph_line_color", '#3366cc', '', 'yes');
	add_option("wpgpxmaps_graph_line_color_speed", '#ff0000', '', 'yes');
	add_option("wpgpxmaps_map_line_color", '#3366cc', '', 'yes');
	add_option("wpgpxmaps_graph_line_color_cad", '#beecff', '', 'yes');
	add_option("wpgpxmaps_graph_offset_from1", '', '', 'yes');
	add_option("wpgpxmaps_graph_offset_to1", '', '', 'yes');
	add_option("wpgpxmaps_graph_offset_from2", '', '', 'yes');
	add_option("wpgpxmaps_graph_offset_to2", '', '', 'yes');
	add_option("wpgpxmaps_map_start_icon", '', '', 'yes');
	add_option("wpgpxmaps_map_end_icon", '', '', 'yes');
	add_option("wpgpxmaps_map_current_icon", '', '', 'yes');
	add_option("wpgpxmaps_map_waypoint_icon", '', '', 'yes');
	add_option("wpgpxmaps_map_nggallery", '', '', 'yes');
	add_option("wpgpxmaps_show_hr", '', '', 'yes');
	add_option("wpgpxmaps_show_atemp", '', '', 'yes');
	add_option("wpgpxmaps_graph_line_color_hr", '#ff77bd', '', 'yes');
	add_option("wpgpxmaps_graph_line_color_atemp", '#ff77bd', '', 'yes');
	add_option('wpgpxmaps_show_cadence','','','yes');
	add_option('wpgpxmaps_zoomonscrollwheel','','','yes');
	add_option('wpgpxmaps_download','','','yes');
	add_option('wpgpxmaps_summary','','','yes');	
	add_option('wpgpxmaps_skipcache','','','yes');
	// default Highcharts to v11 enabled for performance on fresh installs
	add_option('wpgpxmaps_highcharts_v11','true','','yes');
	// new defaults for arrows
	add_option('wpgpxmaps_arrows_enabled', '', '', 'yes');
	add_option('wpgpxmaps_arrows_km', '10', '', 'yes');
	// elevation coloring defaults
	add_option('wpgpxmaps_elev_color_enabled', '', '', 'yes');
	add_option('wpgpxmaps_elev_color_threshold', '5', '', 'yes');
	add_option('wpgpxmaps_elev_color_max', '12', '', 'yes');
	// MapTiler API key
	add_option('wpgpxmaps_maptiler_apikey', '', '', 'yes');
	add_option('wpgpxmaps_summary_avg_ele', '', '', 'yes');
	add_option('wpgpxmaps_privacymode', '', '', 'yes');
}

function WP_GPX_Maps_remove() {
	delete_option('wpgpxmaps_width');
	delete_option('wpgpxmaps_graph_height');
	delete_option('wpgpxmaps_height');
	delete_option('wpgpxmaps_map_type');
	delete_option('wpgpxmaps_show_waypoint');
	delete_option('wpgpxmaps_show_speed');
	delete_option('wpgpxmaps_pointsoffset');
	delete_option('wpgpxmaps_donotreducegpx');
	delete_option('wpgpxmaps_unit_of_measure');
	delete_option('wpgpxmaps_unit_of_measure_speed');
	delete_option('wpgpxmaps_graph_line_color');
	delete_option('wpgpxmaps_map_line_color');
	delete_option('wpgpxmaps_graph_line_color_speed');
	delete_option('wpgpxmaps_graph_offset_from1');
	delete_option('wpgpxmaps_graph_offset_to1');
	delete_option('wpgpxmaps_graph_offset_from2');
	delete_option('wpgpxmaps_graph_offset_to2');
	delete_option('wpgpxmaps_map_start_icon');
	delete_option('wpgpxmaps_map_end_icon');
	delete_option('wpgpxmaps_map_current_icon');
	delete_option('wpgpxmaps_map_waypoint_icon');
	delete_option('wpgpxmaps_map_nggallery');
	delete_option('wpgpxmaps_show_hr');
	delete_option('wpgpxmaps_show_atemp');
	delete_option('wpgpxmaps_graph_line_color_hr');
	delete_option('wpgpxmaps_graph_line_color_atemp');
	delete_option('wpgpxmaps_show_cadence');
	delete_option('wpgpxmaps_graph_line_color_cad');
	delete_option('wpgpxmaps_zoomonscrollwheel');
	delete_option('wpgpxmaps_download');
	delete_option('wpgpxmaps_summary');
	delete_option('wpgpxmaps_skipcache');
	// new options cleanup
	delete_option('wpgpxmaps_arrows_enabled');
	delete_option('wpgpxmaps_arrows_km');
	delete_option('wpgpxmaps_elev_color_enabled');
	delete_option('wpgpxmaps_elev_color_threshold');
	delete_option('wpgpxmaps_elev_color_max');
	delete_option('wpgpxmaps_summary_avg_ele');
	delete_option('wpgpxmaps_privacymode');
}

?>
