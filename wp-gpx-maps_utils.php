<?php

	require_once("wp-gpx-maps_utils_nggallery.php");

	function getAttachedImages($dt, $lat, $lon, $dtoffset, &$error)
	{
		$result = array();
			
		try {
			$attachments = get_children( array(
				 'post_parent'    => get_the_ID(),
				 'post_type'      => 'attachment',
				 'numberposts'    => -1, 			// show all -1
				 'post_status'    => 'inherit',
				 'post_mime_type' => 'image',
				 'order'          => 'ASC',
				 'orderby'        => 'menu_order ASC')
			);

			foreach ($attachments as $attachment_id => $attachment) {

				$img_src      = wp_get_attachment_image_src($attachment_id,'full');
				$img_thmb     = wp_get_attachment_image_src($attachment_id,'thumbnail');
				$img_metadata = wp_get_attachment_metadata( $attachment_id);

				$item = array();
				$item["data"] = wp_get_attachment_link( $attachment_id, array(105,105) );

				// Prefer filesystem path via WordPress API to handle CDN/offloaded URLs
				$path_image = get_attached_file($attachment_id);
				$path_parts = $path_image ? pathinfo($path_image) : array();
				$hasExif = false;
				if ($path_image && file_exists($path_image) && isset($path_parts['extension']) && in_array(strtolower($path_parts['extension']), array('jpg','jpeg'))) {
					if (is_callable('exif_read_data')) {
						$fp = fopen($path_image, 'rb');
						if ($fp !== false) {
							$exif = @exif_read_data($fp);
							fclose($fp);
							if ($exif !== false) {
								$hasExif = true;
								if (isset($exif['GPSLongitude']) && isset($exif['GPSLongitudeRef']) && isset($exif['GPSLatitude']) && isset($exif['GPSLatitudeRef'])) {
									$item['lon'] = getExifGps($exif['GPSLongitude'], $exif['GPSLongitudeRef']);
									$item['lat'] = getExifGps($exif['GPSLatitude'], $exif['GPSLatitudeRef']);
									if (($item['lat'] != 0) || ($item['lon'] != 0)) {
										$result[] = $item;
										continue;
									}
								}
								if (isset($exif['DateTimeOriginal']) || isset($exif['DateTime'])) {
									$imagedate = isset($exif['DateTimeOriginal']) ? $exif['DateTimeOriginal'] : $exif['DateTime'];
									$_dt = strtotime($imagedate) + $dtoffset;
									$_item = findItemCoordinate($_dt, $dt, $lat, $lon);
									if ($_item != null) {
										$item['lat'] = $_item['lat'];
										$item['lon'] = $_item['lon'];
										$result[] = $item;
										continue;
									}
								}
							}
						}
					} else {
						$error .= "Sorry, <a href='http://php.net/manual/en/function.exif-read-data.php' target='_blank' >exif_read_data</a> function not found! check your hosting..<br />";
					}
				}

				// Fallback using attachment metadata/timestamps
				if (!$hasExif) {
					$created_ts = 0;
					if (isset($img_metadata['image_meta']) && isset($img_metadata['image_meta']['created_timestamp'])) {
						$created_ts = intval($img_metadata['image_meta']['created_timestamp']);
					}
					if ($created_ts <= 0) {
						$attachment_post = get_post($attachment_id);
						if ($attachment_post && $attachment_post->post_date_gmt) {
							$created_ts = strtotime($attachment_post->post_date_gmt);
						} elseif ($attachment_post && $attachment_post->post_date) {
							$created_ts = strtotime(get_gmt_from_date($attachment_post->post_date));
						}
					}
					if ($created_ts > 0) {
						$_dt = $created_ts + $dtoffset;
						$_item = findItemCoordinate($_dt, $dt, $lat, $lon);
						if ($_item != null) {
							$item['lat'] = $_item['lat'];
							$item['lon'] = $_item['lon'];
							$result[] = $item;
						}
					}
				}
			}
			
		} catch (Exception $e) {
			$error .= 'Error When Retrieving attached images: $e <br />';
		}

		return $result;
	}

	function sitePath()
	{
		return substr(substr(__FILE__, 0, strrpos(__FILE__,'wp-content')), 0, -1);
		//		$uploadsPath = 	substr($uploadsPath, 0, -1);
	}

	function gpxFolderPath()
	{
		$upload_dir = wp_upload_dir();
		$uploadsPath = $upload_dir['basedir'];	
		
		
		if ( current_user_can('manage_options') ){
			$ret = $uploadsPath.DIRECTORY_SEPARATOR."gpx";
		} 
		else if ( current_user_can('publish_posts') ) {	
			$current_user = wp_get_current_user();
			$ret = $uploadsPath.DIRECTORY_SEPARATOR."gpx".DIRECTORY_SEPARATOR.$current_user->user_login;
		}		
		
		return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $ret);
	}
	
	function gpxCacheFolderPath()
	{
		$upload_dir = wp_upload_dir();
		$uploadsPath = $upload_dir['basedir'];	
		$ret = $uploadsPath.DIRECTORY_SEPARATOR."gpx".DIRECTORY_SEPARATOR."~cache";
		return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $ret);
	}
	
	function relativeGpxFolderPath()
	{
		$sitePath = sitePath();
		$realGpxPath = gpxFolderPath();
		$ret = str_replace($sitePath,'',$realGpxPath).DIRECTORY_SEPARATOR;
		return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $ret);
	}

	function recursive_remove_directory($directory, $empty=FALSE)
	{
		if(substr($directory,-1) == '/')
		{
			$directory = substr($directory,0,-1);
		}
		if(!file_exists($directory) || !is_dir($directory))
		{
			return FALSE;
		}elseif(is_readable($directory))
		{
			$handle = opendir($directory);
			while (FALSE !== ($item = readdir($handle)))
			{
				if($item != '.' && $item != '..')
				{
					$path = $directory.'/'.$item;
					if(is_dir($path)) 
					{
						recursive_remove_directory($path);
					}else{
						unlink($path);
					}
				}
			}
			closedir($handle);
			if($empty == FALSE)
			{
				if(!rmdir($directory))
				{
					return FALSE;
				}
			}
		}
		return TRUE;
	}

	function getPoints($gpxPath, $gpxOffset = 10, $donotreducegpx, $distancetype)
	{

		$points = array();
		$dist=0;
		
		$lastLat=0;
		$lastLon=0;
		$lastEle=0;
		$lastOffset=0;
				
		if (file_exists($gpxPath))
		{
			$max_gpx_size = 50 * 1024 * 1024; // 50MB limit
			if (filesize($gpxPath) > $max_gpx_size) {
				// File too large, return empty points
			} else {
				$points = @parseXml($gpxPath, $gpxOffset, $distancetype);
			}
		}
		else
		{
			echo "WP GPX Maps Error: File $gpxPath not found!";
		}
		
		// Guard against parse failures
		if (!is_object($points) || !isset($points->lat)) {
			$empty = new stdClass;
			$empty->dt=[]; $empty->lat=[]; $empty->lon=[]; $empty->ele=[]; $empty->dist=[]; $empty->speed=[]; $empty->hr=[]; $empty->atemp=[]; $empty->cad=[]; $empty->grade=[];
			$empty->maxTime=0; $empty->minTime=0; $empty->maxEle=0; $empty->minEle=0; $empty->totalEleUp=0; $empty->totalEleDown=0; $empty->avgSpeed=0; $empty->totalLength=0;
			$points = $empty;
		}

		// reduce the points to around 3200 to speedup
		if ( $donotreducegpx != true)
		{
			$count=sizeof($points->lat);
			if ($count>3200)
			{
				$f = round($count/3200);
				if ($f>1) {
					for($i=$count-1;$i>=0;$i--)
						if ($i % $f != 0 && isset($points->lat[$i]) && $points->lat[$i] !== null)
						{
							unset($points->dt[$i]);
							unset($points->lat[$i]);						
							unset($points->lon[$i]);						
							unset($points->ele[$i]);						
							unset($points->dist[$i]);						
							unset($points->speed[$i]);						
							unset($points->hr[$i]);
							unset($points->atemp[$i]);
							unset($points->cad[$i]);
							unset($points->grade[$i]);
						}

					$points->dt = array_values($points->dt);
					$points->lat = array_values($points->lat);
					$points->lon = array_values($points->lon);
					$points->ele = array_values($points->ele);
					$points->dist = array_values($points->dist);
					$points->speed = array_values($points->speed);
					$points->hr = array_values($points->hr);
					$points->atemp = array_values($points->atemp);
					$points->cad = array_values($points->cad);
					$points->grade = array_values($points->grade);
				}
			}		
		}
		return $points;
	}

	function parseXml($filePath, $gpxOffset, $distancetype)
	{
		//fix null pointer exception in php8
		//$points = null;
		$points = new stdClass;
		
		$points->dt = array();
		$points->lat = array();
		$points->lon = array();
		$points->ele = array();
		$points->dist = array();
		$points->speed = array();
		$points->hr = array();
		$points->atemp = array();	
		$points->cad = array();
		$points->grade = array();
		
		$points->maxTime = 0;
		$points->minTime = 0;
		$points->maxEle = 0;
		$points->minEle = 0;
		$points->totalEleUp = 0;
		$points->totalEleDown = 0;
		$points->avgSpeed = 0;
		$points->totalLength = 0;
		
		$gpx = simplexml_load_file($filePath, 'SimpleXMLElement', LIBXML_NONET);	
		
		if($gpx === FALSE) 
			return;
		
		$gpx->registerXPathNamespace('a', 'http://www.topografix.com/GPX/1/0');
		$gpx->registerXPathNamespace('b', 'http://www.topografix.com/GPX/1/1');
		$gpx->registerXPathNamespace('gpxtpx', 'http://www.garmin.com/xmlschemas/TrackPointExtension/v1');
		
		$nodes = $gpx->xpath('//trk | //a:trk | //b:trk');
		//normal gpx
		
		if ( count($nodes) > 0 )	
		{
		
			foreach($nodes as $_trk)
			{
			
				$trk = simplexml_load_string($_trk->asXML(), 'SimpleXMLElement', LIBXML_NONET); 
				
				$trk->registerXPathNamespace('a', 'http://www.topografix.com/GPX/1/0');
				$trk->registerXPathNamespace('b', 'http://www.topografix.com/GPX/1/1');
				$trk->registerXPathNamespace('gpxtpx', 'http://www.garmin.com/xmlschemas/TrackPointExtension/v1');

				$trkpts = $trk->xpath('//trkpt | //a:trkpt | //b:trkpt');
				
				$lastLat = 0;
				$lastLon = 0;
				$lastEle = 0;
				$lastTime = 0;
				//$dist = 0;
				$lastOffset = 0;
				$speedBuffer = array();
			
				foreach($trkpts as $trkpt)
				{

					$lat = $trkpt['lat'];
					$lon = $trkpt['lon'];
					$ele = $trkpt->ele;
					$time = $trkpt->time;
					$speed = (float)$trkpt->speed;
					$hr = 0;
					$atemp = 0;
					$cad = 0;
					$grade = 0;

					if (isset($trkpt->extensions))
					{				
						$arr = json_decode( json_encode($trkpt->extensions) , 1);
						// find TrackPointExtension key regardless of prefix
						$tpe = null;
						foreach ($arr as $k => $v) {
							$lk = strtolower($k);
							if (strpos($lk, 'trackpointextension') !== false) { $tpe = $v; break; }
						}
						if ($tpe === null && isset($arr['gpxtpx:TrackPointExtension'])) { $tpe = $arr['gpxtpx:TrackPointExtension']; }
						if ($tpe === null && isset($arr['TrackPointExtension'])) { $tpe = $arr['TrackPointExtension']; }
						// extract numeric values, handling arrays/strings
						$fetchNum = function($node, $keys){
							foreach ($keys as $kk){
								if (isset($node[$kk])) {
									$val = $node[$kk];
									if (is_array($val)) { $val = reset($val); }
									if (is_array($val)) { $val = reset($val); }
									return (float)$val;
								}
							}
							return 0.0;
						};
						if (is_array($tpe)) {
							$hr    = $fetchNum($tpe, array('gpxtpx:hr','hr'));
							$atemp = $fetchNum($tpe, array('gpxtpx:atemp','atemp'));
							$cad   = $fetchNum($tpe, array('gpxtpx:cad','cad'));
						}
						
					}

					if ($lastLat == 0 && $lastLon == 0)
					{
						//Base Case

						array_push($points->dt,   		strtotime($time));
						array_push($points->lat,  		(float)$lat);
						array_push($points->lon,  		(float)$lon);
						array_push($points->ele,  		(float)round((float)$ele,2));
						array_push($points->dist, 		(float)round((float)$dist,2));
						array_push($points->speed, 		0);
						array_push($points->hr,    		(float)$hr);
						array_push($points->atemp,    	(float)$atemp);
						array_push($points->cad,   		(float)$cad);
						array_push($points->grade,   	$grade);
						
						$lastLat=$lat;
						$lastLon=$lon;
						$lastEle=$ele;				
						$lastTime=$time;
					}
					else
					{
						//Normal Case
						$offset = calculateDistance((float)$lat, (float)$lon, (float)$ele, (float)$lastLat, (float)$lastLon, (float)$lastEle, $distancetype);
						$dist = $dist + $offset;
						
						$points->totalLength = $dist;
						
						if ($speed == 0)
						{
							$datediff = (float)my_date_diff($lastTime,$time);
							if ($datediff>0)
							{
								$speed = $offset / $datediff;
							}
						}
						
						if ($ele != 0 && $lastEle != 0)
						{
						
							$deltaEle = (float)($ele - $lastEle);
						
							if ((float)$ele > (float)$lastEle)
							{
								$points->totalEleUp += $deltaEle;
							}
							else
							{
								$points->totalEleDown += $deltaEle;
							}
							
							if ($offset == 0) {
								$grade = 0;
							} else {
								$grade = $deltaEle / $offset * 100;
							}
							
						}
						
						array_push($speedBuffer, $speed);
						
						if (((float) $offset + (float) $lastOffset) > $gpxOffset)
						{
							//Bigger Offset -> write coordinate
							$avgSpeed = 0;
							
							foreach($speedBuffer as $s)
							{ 
								$avgSpeed += $s;
							}
							
							$avgSpeed = $avgSpeed / count($speedBuffer);
							$speedBuffer = array();
							
							$lastOffset=0;
							
							array_push($points->dt,    strtotime($time));
							array_push($points->lat,   (float)$lat );
							array_push($points->lon,   (float)$lon );
							array_push($points->ele,   (float)round((float)$ele, 2) );
							array_push($points->dist,  (float)round((float)$dist, 2) );
							array_push($points->speed, (float)round((float)$avgSpeed, 1) );
							array_push($points->hr, 	$hr);
							array_push($points->atemp,	$atemp);
							
							
							array_push($points->cad, $cad);
							array_push($points->grade, (float)round($grade, 2) );
							
						}
						else
						{
							//Smoller Offset -> continue..
							$lastOffset = (float) $lastOffset + (float) $offset ;
						}
					}
					$lastLat=$lat;
					$lastLon=$lon;
					$lastEle=$ele;
					$lastTime=$time;

				}
				
				array_push($points->dt,  null);
				array_push($points->lat,  null);
				array_push($points->lon,  null);
				array_push($points->ele,  null);
				array_push($points->dist, null);
				array_push($points->speed, null);
				array_push($points->hr, null);
				array_push($points->atemp, null);
				array_push($points->cad, null);
				array_push($points->grade, null);
				
				unset($trkpts);				
			
			}

			unset($nodes);
			
			try {
				array_pop($points->dt);
				array_pop($points->lat);
				array_pop($points->lon);
				array_pop($points->ele);
				array_pop($points->dist);
				array_pop($points->speed);
				array_pop($points->hr);
				array_pop($points->atemp);
				array_pop($points->cad);
				array_pop($points->grade);

			
				$_time = array_filter($points->dt);
				$_ele = array_filter($points->ele);
				$_dist = array_filter($points->dist);
				$_speed = array_filter($points->speed);
				$points->maxEle = count($_ele)?max($_ele):0;
				$points->minEle = count($_ele)?min($_ele):0;
				$points->totalLength = count($_dist)?max($_dist):0;
				$points->maxTime = count($_time)?max($_time):0;
				$points->minTime = count($_time)?min($_time):0;
				
				$points->avgSpeed = count($_speed)?array_sum($_speed) / count($_speed):0;
			} catch (Exception $e) { }
		
		}
		else
		{
		
			// gpx garmin case
			$gpx->registerXPathNamespace('gpxx', 'http://www.garmin.com/xmlschemas/GpxExtensions/v3');
			
			$nodes = $gpx->xpath('//gpxx:rpt');
		
			if ( count($nodes) > 0 )	
			{
			
				$lastLat = 0;
				$lastLon = 0;
				$lastEle = 0;
				$dist = 0;
				$lastOffset = 0;
			
				// Garmin case
				foreach($nodes as $rpt)
				{ 
				
					$lat = $rpt['lat'];
					$lon = $rpt['lon'];
					if ($lastLat == 0 && $lastLon == 0)
					{
						//Base Case
						array_push($points->lat,   (float)$lat );
						array_push($points->lon,   (float)$lon );
						array_push($points->ele,   0 );
						array_push($points->dist,  0 );
						array_push($points->speed, 0 );
						array_push($points->hr,    0 );
						array_push($points->atemp, 0 );
						array_push($points->cad,   0 );
						array_push($points->grade, 0 );
						$lastLat=$lat;
						$lastLon=$lon;
					}
					else
					{
						//Normal Case
						$offset = calculateDistance($lat, $lon, 0,$lastLat, $lastLon, 0, $distancetype);
						$dist = $dist + $offset;
						if (((float) $offset + (float) $lastOffset) > $gpxOffset)
						{
							//Bigger Offset -> write coordinate
							$lastOffset=0;
							array_push($points->lat,   (float)$lat );
							array_push($points->lon,   (float)$lon );
							array_push($points->ele,   0 );
							array_push($points->dist,  0 );
							array_push($points->speed, 0 );	
							array_push($points->hr,    0 );
							array_push($points->atemp, 0 );
							array_push($points->cad,   0 );
							array_push($points->grade, 0 );
						}
						else
						{
							//Smoller Offset -> continue..
							$lastOffset= (float) $lastOffset + (float) $offset;
						}
					}
					$lastLat=$lat;
					$lastLon=$lon;
				}
				unset($nodes);
			
			}
			else
			{
			
				//gpx strange case

				$nodes = $gpx->xpath('//rtept | //a:rtept | //b:rtept');
				if ( count($nodes) > 0 )
				{
				
					$lastLat = 0;
					$lastLon = 0;
					$lastEle = 0;
					$dist = 0;
					$lastOffset = 0;
				
					// Garmin case
					foreach($nodes as $rtept)
					{ 
					
						$lat = $rtept['lat'];
						$lon = $rtept['lon'];
						if ($lastLat == 0 && $lastLon == 0)
						{
							//Base Case
							array_push($points->lat,   (float)$lat );
							array_push($points->lon,   (float)$lon );
							array_push($points->ele,   0 );
							array_push($points->dist,  0 );
							array_push($points->speed, 0 );
							array_push($points->hr,    0 );
							array_push($points->atemp, 0 );
							array_push($points->cad,   0 );
							array_push($points->grade, 0 );
							$lastLat=$lat;
							$lastLon=$lon;
						}
						else
						{
							//Normal Case
							$offset = calculateDistance($lat, $lon, 0,$lastLat, $lastLon, 0, $distancetype);
							$dist = $dist + $offset;
							if (((float) $offset + (float) $lastOffset) > $gpxOffset)
							{
								//Bigger Offset -> write coordinate
								$lastOffset=0;
								array_push($points->lat,   (float)$lat );
								array_push($points->lon,   (float)$lon );
								array_push($points->ele,   0 );
								array_push($points->dist,  0 );
								array_push($points->speed, 0 );	
								array_push($points->hr,    0 );
								array_push($points->atemp, 0 );
								array_push($points->cad,   0 );
								array_push($points->grade, 0 );
							}
							else
							{
								//Smoller Offset -> continue..
								$lastOffset= (float) $lastOffset + (float) $offset;
							}
						}
						$lastLat=$lat;
						$lastLon=$lon;
					}
					unset($nodes);
					
				}

			}
		
		}
		
		unset($gpx);
		return $points;
	}	
	
	function getWayPoints($gpxPath)
	{
		$points = array();
		if (file_exists($gpxPath))
		{
			try {
				$gpx = simplexml_load_file($gpxPath, 'SimpleXMLElement', LIBXML_NONET);	    
			} catch (Exception $e) {
				echo "WP GPX Maps Error: Cant parse xml file " . $gpxPath;
				return $points;
			}
		
			$gpx->registerXPathNamespace('a', 'http://www.topografix.com/GPX/1/0');
			$gpx->registerXPathNamespace('b', 'http://www.topografix.com/GPX/1/1');
			$nodes = $gpx->xpath('//wpt | //a:wpt | //b:wpt');
			global $wpdb;
			
			if ( count($nodes) > 0 )	
			{
				// normal case
				foreach($nodes as $wpt)
				{
					$lat  = $wpt['lat'];
					$lon  = $wpt['lon'];
					$ele  = (string) $wpt->ele;
					$time = (string) $wpt->time;
					$name = (string) $wpt->name;
					$desc = (string) $wpt->desc;
					$sym  = (string) $wpt->sym;
					$type = (string) $wpt->type;
					$img  = '';
					
					$img_name = 'map-marker-' . $sym;
					$query = $wpdb->prepare(
						"SELECT ID FROM {$wpdb->prefix}posts WHERE post_name LIKE %s AND post_type = 'attachment'",
						$img_name
					);
					$img_id = $wpdb->get_var($query);
					if (!is_null($img_id)) {
						$img = wp_get_attachment_url($img_id);
					}
					
					array_push($points, array(
						"lat"  => (float)$lat,
						"lon"  => (float)$lon,
						"ele"  => (float)$ele,
						"time" => $time,
						"name" => $name,
						"desc" => $desc,
						"sym"  => $sym,
						"type" => $type,
						"img"  => $img
					));
				}
			}
		}
		return $points;
	}
	
	function toRadians($degrees)
	{
		return (float)($degrees * 3.1415926535897932385 / 180);
	}
	
	function calculateDistance($lat1,$lon1,$ele1,$lat2,$lon2,$ele2,$distancetype)
	{
	
		if ($distancetype == '2') // climb
		{
			return (float)$ele1 - (float)$ele2;
		}
		else if ($distancetype == '1') // flat
		{
			$alpha = (float)sin((float)toRadians((float) $lat2 - (float) $lat1) / 2);
			$beta = (float)sin((float)toRadians((float) $lon2 - (float) $lon1) / 2);
			//Distance in meters
			$a = (float) ( (float)$alpha * (float)$alpha) +  (float) ( (float)cos( (float)toRadians($lat1)) * (float)cos( (float)toRadians($lat2)) * (float)$beta * (float)$beta );
			$dist = 2 * 6369628.75 * (float)atan2((float)sqrt((float)$a), (float)sqrt(1 - (float) $a));
			return (float)sqrt((float)pow((float)$dist, 2) + pow((float) $lat1 - (float)$lat2, 2));	
		}
		else // normal
		{
			$alpha = (float)sin((float)toRadians((float) $lat2 - (float) $lat1) / 2);
			$beta = (float)sin((float)toRadians((float) $lon2 - (float) $lon1) / 2);
			//Distance in meters
			$a = (float) ( (float)$alpha * (float)$alpha) +  (float) ( (float)cos( (float)toRadians($lat1)) * (float)cos( (float)toRadians($lat2)) * (float)$beta * (float)$beta );
			$dist = 2 * 6369628.75 * (float)atan2((float)sqrt((float)$a), (float)sqrt(1 - (float) $a));
			$d = (float)sqrt((float)pow((float)$dist, 2) + pow((float) $lat1 - (float)$lat2, 2));	
			return sqrt((float)pow((float)$ele1-(float)$ele2,2)+(float)pow((float)$d,2));
		}

	}
	
	function my_date_diff($old_date, $new_date) {
	
		$t1 = strtotime($new_date);
		$t2 = strtotime($old_date);
		
		// milliceconds fix
		$t1 += date_getDecimals($new_date);
		$t2 += date_getDecimals($old_date);
	
		$offset = (float)($t1 - $t2);
	  
		//echo "$offset = $new_date - $old_date; ".strtotime($new_date)." ".strtotime($old_date)." <br />";
	  
	  return $offset;
	}

	function date_getDecimals($date)
	{
		if (preg_match('(\.([0-9]{2})Z?)', $date, $matches))
		{
			return (float)((float)$matches[1] / 100);
		}
		else
		{
			return 0;
		}
	}


	
?>
