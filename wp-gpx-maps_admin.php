<?php

if ( is_admin() ){
	add_action('admin_menu', 'wpgpxmaps_admin_menu');
    // Enqueue admin assets on our pages
    add_action('admin_enqueue_scripts','wpgpxmaps_admin_assets');
    // Show admin notices
    add_action('admin_notices','wpgpxmaps_admin_notices');
}

function wpgpxmaps_admin_menu() {
	if ( current_user_can('manage_options') ){
		add_options_page('WP GPX Maps', 'WP GPX Maps', 'manage_options', 'WP-GPX-Maps', 'WP_GPX_Maps_html_page');
	} 
	else if ( current_user_can('publish_posts') ) {
		add_menu_page('WP GPX Maps', 'WP GPX Maps', 'publish_posts', 'WP-GPX-Maps', 'WP_GPX_Maps_html_page');
	}
}

function wpgpxmaps_admin_assets($hook){
    // Load only on our plugin pages
    $is_plugin_page = isset($_GET['page']) && $_GET['page'] === 'WP-GPX-Maps';
    if (!$is_plugin_page) { return; }
    // bootstrap-table 1.13.2 (admin only)
    wp_register_script( 'bootstrap-table', 'https://unpkg.com/bootstrap-table@1.13.2/dist/bootstrap-table.min.js', array('jquery'), '1.13.2', true );
    wp_enqueue_script( 'bootstrap-table' );
    wp_register_style( 'bootstrap-table', 'https://unpkg.com/bootstrap-table@1.13.2/dist/bootstrap-table.min.css', array(), '1.13.2' );
    wp_enqueue_style( 'bootstrap-table' );
}

function wpgpxmaps_admin_notices(){
    if (!current_user_can('manage_options')) { return; }
    $is_plugin_page = isset($_GET['page']) && $_GET['page'] === 'WP-GPX-Maps';
    if (!$is_plugin_page) { return; }
	$map_engine = get_option('wpgpxmaps_map_engine');
	if ($map_engine !== 'google') { return; }
    $gm_key = get_option('wpgpxmaps_googlemapsv3_apikey');
    $map_id = get_option('wpgpxmaps_googlemaps_map_id');
    if (empty($gm_key)) {
        echo '<div class="notice notice-warning"><p>WP GPX Maps: No Google Maps API Key configured. Set it in Settings to enable Google Maps and avoid console warnings.</p></div>';
    }
    if (empty($map_id)) {
        echo '<div class="notice notice-info"><p>WP GPX Maps: No Google Maps Map ID configured. Advanced Markers and styled maps require a Map ID. This is optional.</p></div>';
    }
}

function ilc_admin_tabs( $current  ) {

	if (current_user_can('manage_options'))
	{
		$tabs = array( 'tracks' => 'Tracks', 'settings' => 'Settings', 'help' => "help" );	
	}
	else if ( current_user_can('publish_posts') ) {
		$tabs = array( 'tracks' => 'Tracks', 'help' => "help" );	
	}

    echo '<h2 class="nav-tab-wrapper">';
    foreach( $tabs as $tab => $name ){
        $class = ( $tab == $current ) ? ' nav-tab-active' : '';
        echo "<a class='nav-tab$class' href='?page=WP-GPX-Maps&tab=$tab'>$name</a>";
    }
    echo '</h2>';
}

function WP_GPX_Maps_html_page() {
	$realGpxPath = gpxFolderPath();
	$cacheGpxPath = gpxCacheFolderPath();
	$relativeGpxPath = relativeGpxFolderPath();
	$relativeGpxPath = str_replace("\\","/", $relativeGpxPath);
	
	$tab = $_GET['tab'];
	
	if ($tab == '')
		$tab = 'tracks';
	

?>
	<div id="icon-themes" class="icon32"><br></div>
		<h2>WP GPX Settings</h2>	
<?php

	if(file_exists($realGpxPath) && is_dir($realGpxPath))
	{
		//dir exsist!
	}
	else
	{
		if (!@mkdir($realGpxPath,0755,true)) {
			echo '<div class="error" style="padding:10px">
					Can\'t create <b>'.$realGpxPath.'</b> folder. Please create it and make it writable!<br />
					If not, you will must update the file manually!
				  </div>';
		}
	}
	
	if(file_exists($cacheGpxPath) && is_dir($cacheGpxPath))
	{
		//dir exsist!
	}
	else
	{
		if (!@mkdir($cacheGpxPath,0755,true)) {
			echo '<div class="error" style="padding:10px">
					Can\'t create <b>'.$cacheGpxPath.'</b> folder. Please create it and make it writable!<br />
					If not, cache will not created and your site could be slower!
				  </div>';
		}
	}

	ilc_admin_tabs($tab);	
	
	if ($tab == "tracks")
	{
		include 'wp-gpx-maps_admin_tracks.php';
	}
	else if ($tab == "settings")
	{
		include 'wp-gpx-maps_admin_settings.php';
	}
	else if ($tab == "help")
	{
?>

	<div style="padding:10px;">
		<b>The fastest way to use this plugin:</b><br /> upload the file using the uploader in the first tab, than copy the shortcode from the list and paste it in the pages/posts.
		<p>You can manually set the relative path to your gpx: <b>[sgpx gpx="<?php echo $relativeGpxPath; ?>&lt gpx file name &gt"]</b>.</p>
		<p>You can also use gpx from other sites: <b>[sgpx gpx="http://www.someone.com/somewhere/somefile.gpx"]</b></p>
		<hr />
		<p>
			<i>Full set of attributes:</i> <b>[sgpx gpx="<?php echo $relativeGpxPath; ?>&lt gpx file name &gt" </b>
													&nbsp;&nbsp;&nbsp;<em>&gt&gt read below all the optional attributes &lt&lt</em>&nbsp;&nbsp;&nbsp;
											<b>]</b>

			<ul>
<li><b>gpx</b>: relative path to gpx
</li><li><b>width</b>: width in pixels
</li><li><b>mheight</b>: map height
</li><li><b>gheight</b>: graph height
</li><li><b>mtype</b>: map available types are: HYBRID, ROADMAP, SATELLITE, TERRAIN, OSM1 (Open Street Map), OSM2 (Open Cycle Map), OSM3 (Hike &amp; Bike), OSM4 (Open Cycle Map - Transport), OSM5 (Open Cycle Map - Landscape), OSM6 (MapTiler - Outdoor), OSM7 (MapTiler - Topo), OSM8 (MapTiler - Landscape)
</li><li><b>waypoints</b>: print the gpx waypoints inside the map (default is FALSE)
</li><li><b>donotreducegpx</b>: print all the point without reduce it (default is FALSE)
</li><li><b>pointsoffset</b>: skip points closer than XX meters(default is 10)
</li><li><b>uom</b>: distance/altitude possible unit of measure are: 0, 1, 2, 3, 4, 5 (0 = meters, 1 = feet/miles, 2 = meters/kilometers, 3 = meters/nautical miles, 4 = meters/miles, 5 = feet/nautical miles)
</li><li><b>mlinecolor</b>: map line color (default is #3366cc)
</li><li><b>glinecolor</b>: altitude line color (default is #3366cc)
</li><li><b>showspeed</b>: show speed inside the chart (default is FALSE)
</li><li><b>showhr</b>: show heart rate inside the chart (default is FALSE)
</li><li><b>showele</b>: show elevation data inside the chart (default is TRUE)
</li><li><b>showcad</b>: show cadence inside the chart (default is FALSE)
</li><li><b>showgrade</b>: show grade inside the chart (default is FALSE)
</li><li><b>glinecolorspeed</b>: speed line color (default is #ff0000)
</li><li><b>glinecolorhr</b>: heart rate line color (default is #ff77bd)
</li><li><b>glinecolorcad</b>: cadence line color (default is #beecff)
</li><li><b>glinecolorgrade</b>: grade line color (default is #beecff)
</li><li><b>uomspeed</b>: unit of measure for speed are: 0, 1, 2, 3, 4, 5 (0 = m/s, 1 = km/h, 2 = miles/h, 3 = min/km, 4 = min/miles, 5 = Nautical Miles/Hour (Knots), 6 = min/100 meters)
</li><li><b>chartFrom1</b>: minimun value for altitude chart
</li><li><b>chartTo1</b>: maxumin value for altitude chart
</li><li><b>chartFrom2</b>: minimun value for speed chart
</li><li><b>chartTo2</b>: maxumin value for speed chart
</li><li><b>arrowskm</b>: draw direction arrows along the track every N kilometers (decimals allowed, e.g. 0.5)
</li><li><b>startIcon</b>: Start track icon
</li><li><b>endIcon</b>: End track icon
</li><li><b>currentIcon</b>: Current position icon (when mouse hover)
</li><li><b>waypointicon</b>: waypoint custom icon
</li><li><b>nggalleries</b>: NextGen Gallery id or a list of Galleries id separated by a comma
</li><li><b>ngimages</b>: NextGen Image id or a list of Images id separated by a comma
</li><li><b>dtoffset</b>: the difference (in seconds) between your gpx tool date and your camera date
</li><li><b>zoomonscrollwheel</b>: zoom on map when mouse scroll wheel
</li><li><b>download</b>: Allow users to download your GPX file
</li><li><b>skipcache</b>: Do not use cache. If TRUE might be very slow (default is FALSE)
</li><li><b>privacymode</b>: Remove points at start and end of track to hide exact location (default is FALSE)
</li><li><b>summary</b>: Print summary details of your GPX (default is FALSE)
</li><li><b>summarytotlen</b>: Print Total distance in summary table (default is FALSE)
</li><li><b>summarymaxele</b>: Print Max Elevation in summary table (default is FALSE)
</li><li><b>summaryminele</b>: Print Min Elevation in summary table (default is FALSE)
</li><li><b>summaryeleup</b>: Print Total climbing in summary table (default is FALSE)
</li><li><b>summaryeledown</b>: Print Total descent in summary table (default is FALSE)
</li><li><b>summaryavgele</b>: Print Avg elevation in summary table (default is FALSE)
</li><li><b>summaryavgspeed</b>: Print Average Speed in summary table (default is FALSE)
</li><li><b>summarytotaltime</b>: Print Total time in summary table (default is FALSE)  </li>
			</ul>
		
			<p>
				<a href="http://devfarm.it/forums/forum/wp-gpx-maps/">Bugs, problems, thanks and anything else here!</a>
			</p>
			
		</p>
	</div>

<?php
	}

}
?>