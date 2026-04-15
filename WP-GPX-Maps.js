/*
Plugin Name: WP-GPX-Maps
Plugin URI: http://www.devfarm.it/
Description: Draws a gpx track with altitude graph
Version: 1.3.15
Author: Bastianon Massimo
Author URI: http://www.pedemontanadelgrappa.it/
*/

(function ( $ ) {

	var infowindow;
	var CustomMarker;
	// Helpers to support Advanced Markers while keeping backward compatibility
	function supportsAdvancedMarkers() {
		return !!(google && google.maps && google.maps.marker && google.maps.marker.AdvancedMarkerElement);
	}

	function createIconContent(iconUrl, sizePx) {
		var img = document.createElement('img');
		img.src = iconUrl;
		if (sizePx) {
			img.style.width = sizePx + 'px';
			img.style.height = sizePx + 'px';
		}
		return img;
	}

	function createMarker(options) {
		// options: { map, position, title, iconUrl, zIndex }
		if (supportsAdvancedMarkers()) {
			var content = options.iconUrl ? createIconContent(options.iconUrl, 32) : null;
			var m = new google.maps.marker.AdvancedMarkerElement({
				map: options.map,
				position: options.position,
				title: options.title,
				zIndex: options.zIndex,
				content: content
			});
			// shim common methods used elsewhere
			m.setPosition = function(pos){ m.position = pos; };
			m.setTitle = function(t){ m.title = t; };
			return m;
		} else {
			var markerOpts = {
				map: options.map,
				position: options.position,
				title: options.title,
				zIndex: options.zIndex
			};
			if (options.iconUrl) { markerOpts.icon = options.iconUrl; }
			return new google.maps.Marker(markerOpts);
		}
	}

	// Lazily define CustomMarker to avoid referencing google.maps.OverlayView before API load
	function ensureCustomMarker() {
		if (CustomMarker || !(window.google && google.maps && google.maps.OverlayView)) { return; }
		CustomMarker = function( map, latlng, src, img_w, img_h) {
		this.latlng_ = latlng;
		this.setMap(map);
		this.src_ = src;
		this.img_w_ = img_w;
		this.img_h_ = img_h;
		}
		CustomMarker.prototype = new google.maps.OverlayView();
		CustomMarker.prototype.draw = function() {
	
		var me = this;

		// Check if the el has been created.
		var el = this.img_;
		if (!el) {

			this.img_ = document.createElement('img');
			el = this.img_;
			// Fixed thumbnail size for uniform appearance
			var THUMB_SIZE = 38; // px (2/3 of previous 56)
			var HOVER_SIZE = 94; // px (2/3 of previous 140)
			var smallW = THUMB_SIZE;
			var smallH = THUMB_SIZE;
			var HOVER_SCALE = HOVER_SIZE / THUMB_SIZE;
			el.style.cssText = "position:absolute;width:"+smallW+"px;height:"+smallH+"px;object-fit:cover;transform:scale(1);transform-origin:top left;transition:transform 120ms ease;will-change:transform;";
			el.style.objectFit = 'cover';
			el.setAttribute('decoding','async');
			el.setAttribute("class", "myngimages");
			el.setAttribute("lat",this.latlng_.lat());
			el.setAttribute("lon",this.latlng_.lng());
			el.src=this.src_;

			google.maps.event.addDomListener(el, "click", function(event) {
				google.maps.event.trigger(me, "click", el);
			});	
			
			jQuery(el).off('mouseenter mouseleave');
			var hovering = false;
			var restoreTimer = null;
			jQuery(el).on('mouseenter', function(){
				if (restoreTimer) { clearTimeout(restoreTimer); restoreTimer = null; }
				if (hovering) return; hovering = true;
				el.style.zIndex = 100;
				el.style.transform = 'scale(' + HOVER_SCALE + ')';
			});
			jQuery(el).on('mouseleave', function(){
				restoreTimer = setTimeout(function(){
					hovering = false;
					el.style.transform = 'scale(1)';
					el.style.zIndex = 1;
				}, 60);
			});

			// Then add the overlay to the DOM
			var panes = this.getPanes();
			panes.overlayImage.appendChild(el);
		}

		// Position the overlay 
		var point = this.getProjection().fromLatLngToDivPixel(this.latlng_);
			if (point) {
			  el.style.left = point.x + 'px';
			  el.style.top = point.y + 'px';
			  this.orig_left = point.x;
			  this.orig_top = point.y;
			}
		};

		CustomMarker.prototype.remove = function() {
		// Check if the overlay was on the map and needs to be removed.
		if (this.img_) {
		  this.img_.parentNode.removeChild(this.img_);
		  this.img_ = null;
		}
		};
	}
 
    $.fn.wpgpxmaps = function( params ) {

        // Ensure Google Maps API is loaded before initializing
        if (!(window.google && google.maps && google.maps.Map)) {
            var ctx = this;
            var args = arguments;
            var retries = 0;
            (function waitForGoogle(){
                if (window.google && google.maps && google.maps.Map) {
                    $.fn.wpgpxmaps.apply(ctx, args);
                } else if (retries++ < 60) { // up to ~6s
                    setTimeout(waitForGoogle, 100);
                } else {
                    ctx.each(function(){
                        var el = document.getElementById($(this).attr("id") + "_map");
                        if (el) {
                            el.innerHTML = '<p style="padding:10px;color:#c00;">Google Maps failed to load. Please check your API key and network connection.</p>';
                        }
                    });
                }
            })();
            return this;
        }

		var targetId = params.targetId;
		var mapType = params.mapType;
		var mapData = params.mapData;
		var graphDist = params.graphDist;
		var graphEle = params.graphEle;
		var graphSpeed = params.graphSpeed;
		var graphHr = params.graphHr;
		var graphAtemp = params.graphAtemp;
		var graphCad = params.graphCad;
		var graphGrade = params.graphGrade;
		var mapGrade = params.mapGrade || [];
		var waypoints = params.waypoints;
		var unit = params.unit;
		var unitspeed = params.unitspeed;
		var color1 = params.color1;
		var color2 = params.color2;
		var color3 = params.color3;
		var color4 = params.color4;
		var color5 = params.color5;
		var color6 = params.color6;
		var color7 = params.color7;
		var chartFrom1 = params.chartFrom1;
		var chartTo1 = params.chartTo1;
		var chartFrom2 = params.chartFrom2;
		var chartTo2 = params.chartTo2;
		var startIcon = params.startIcon;
		var waypointIcon = params.waypointIcon;
		var endIcon = params.endIcon;
		var currentIcon = params.currentIcon;
		var arrowRepeat = params.arrowRepeat; // percentage string like '5%'
		var zoomOnScrollWheel = params.zoomOnScrollWheel;
		var lng = params.langs;
		var pluginUrl = params.pluginUrl;
		var usegpsposition = params.usegpsposition;
		var currentpositioncon= params.currentpositioncon;
		var ThunderforestApiKey = params.TFApiKey;
		var MTApiKey = params.MTApiKey || '';
		var elevColoringEnabled = !!params.elevColoringEnabled;
		var elevColorThreshold = parseFloat(params.elevColorThreshold || '5');
		var elevColorMax = parseFloat(params.elevColorMax || '12');
		if (!(elevColorMax > elevColorThreshold)) { elevColorMax = elevColorThreshold + 1; }

		function buildFallbackMapGrade(){
 			try {
 				if (!elevColoringEnabled) return null;
 				if (!mapData || !mapData.length) return null;
 				if (!graphEle || !graphEle.length || !graphDist || !graphDist.length) return null;
				var n = Math.min(mapData.length, graphEle.length, graphDist.length);
				if (n < 2) return null;
				// conversion factors based on unit (server-side uom)
				var u = (unit || '0') + '';
				var distToMeters = 1.0;
				if (u === '2') distToMeters = 1000.0; // km
				else if (u === '1' || u === '4') distToMeters = 1609.344; // miles
				else if (u === '3' || u === '5') distToMeters = 1852.0; // NM
				var eleToMeters = (u === '1' || u === '5') ? 0.3048 : 1.0; // feet to m for 1,5
				var g = new Array(n);
				g[0] = 0;
				for (var i=1;i<n;i++){
					if (mapData[i] == null || mapData[i-1] == null) { g[i] = null; continue; }
					var d1 = graphDist[i-1], d2 = graphDist[i];
					var e1 = graphEle[i-1], e2 = graphEle[i];
					if (d1 == null || d2 == null || e1 == null || e2 == null) { g[i] = null; continue; }
					var dd = (d2 - d1) * distToMeters;
					var de = (e2 - e1) * eleToMeters;
					if (!isFinite(dd) || dd <= 0 || !isFinite(de)) { g[i] = 0; continue; }
					g[i] = (de / dd) * 100.0;
				}
				return g;
			} catch(e){ return null; }
		}
		
		var hasThunderforestApiKey = (ThunderforestApiKey + '').length > 0;
		var hasMaptilerApiKey = (MTApiKey + '').length > 0;

		// Unit of measure settings
		var l_s;
		var l_x;
		var l_y;
		var l_grade = { suf : "%", dec : 1 };
		var l_hr = { suf : "", dec : 0 };
		var l_cad = { suf : "", dec : 0 };
		
		var el = document.getElementById("wpgpxmaps_" + targetId);
		var el_map = document.getElementById("map_" + targetId);
		var el_chart = document.getElementById("chart_" + targetId);
		var el_report = document.getElementById("report_" + targetId);
		var el_osm_credits = document.getElementById("wpgpxmaps_" + targetId + "_osm_footer");
		var el_spinner = document.getElementById("spinner_" + targetId);
		// ensure spinner is visible until we explicitly hide it
		if (el_spinner) { el_spinner.style.display = 'flex'; }
		
		var mapWidth = el_map.style.width;
		
		var mapTypeIds = [];
		for(var type in google.maps.MapTypeId) {
			mapTypeIds.push(google.maps.MapTypeId[type]);
		}
		mapTypeIds.push("OSM1");
		mapTypeIds.push("OSM2");
		mapTypeIds.push("OSM3");
		mapTypeIds.push("OSM4");
		mapTypeIds.push("OSM5");
		mapTypeIds.push("OSM6");
		mapTypeIds.push("OSM7");
		mapTypeIds.push("OSM8");
		
		var ngImageMarkers = [];
		// Lightweight path decimation to speed up initial render on very large tracks
		function reduceMapData(data, maxPerSegment){
			try{
				var out = [];
				var seg = [];
				var count = data ? data.length : 0;
				for (var i=0;i<count;i++){
					var it = data[i];
					if (it === null){
						if (seg.length){
							var keep = seg.length;
							if (keep > maxPerSegment){
								var step = Math.ceil(keep / maxPerSegment);
								for (var j=0;j<keep;j+=step){ out.push(seg[j]); }
								if (out[out.length-1] !== seg[keep-1]) out.push(seg[keep-1]);
							}else{
								for (var j2=0;j2<keep;j2++){ out.push(seg[j2]); }
							}
						}
						out.push(null);
						seg = [];
					} else {
						seg.push(it);
					}
				}
				if (seg.length){
					var keep2 = seg.length;
					if (keep2 > maxPerSegment){
						var step2 = Math.ceil(keep2 / maxPerSegment);
						for (var k=0;k<keep2;k+=step2){ out.push(seg[k]); }
						if (out[out.length-1] !== seg[keep2-1]) out.push(seg[keep2-1]);
					}else{
						for (var k2=0;k2<keep2;k2++){ out.push(seg[k2]); }
					}
				}
				return out;
			}catch(e){ return data; }
		}
		
		function resolveImageUrl($img){
			var url = $img.attr('src') || '';
			var fallbacks = ['data-src','data-lazy-src','data-original','data-litespeed-src'];
			for (var k=0;k<fallbacks.length;k++){
				if (!url || url === '' || /placeholder|transparent/.test(url)){
					var cand = $img.attr(fallbacks[k]);
					if (cand) { url = cand; break; }
				}
			}
			// srcset handling: pick smallest width candidate to avoid loading 8k originals
			var srcset = $img.attr('data-srcset') || $img.attr('srcset');
			if (srcset){
				var best = null; var bestW = Infinity;
				var parts = srcset.split(',');
				for (var i=0;i<parts.length;i++){
					var seg = parts[i].trim();
					if (!seg) continue;
					var sp = seg.split(/\s+/);
					var candUrl = sp[0];
					var desc = sp[1] || '';
					var w = 0;
					if (/^\d+w$/.test(desc)) { w = parseInt(desc,10); }
					else if (/^\d+(?:\.\d+)?x$/.test(desc)) { w = parseFloat(desc) * 1000; }
					else { w = 1000000; }
					if (w < bestW) { bestW = w; best = candUrl; }
				}
				if (best) { url = best; }
			}
			return url;
		}

		function hideSpinner() {
			if (el_spinner) { el_spinner.style.display = 'none'; }
		}
		
		switch (mapType)
		{
			case 'TERRAIN': { mapType = google.maps.MapTypeId.TERRAIN; break;}
			case 'SATELLITE': { mapType = google.maps.MapTypeId.SATELLITE; break;}
			case 'ROADMAP': { mapType = google.maps.MapTypeId.ROADMAP; break;}
			case 'OSM1': { mapType = "OSM1"; break;}
			case 'OSM2': { mapType = "OSM2"; break;}
			case 'OSM3': { mapType = "OSM3"; break;}
			case 'OSM4': { mapType = "OSM4"; break;}
			case 'OSM5': { mapType = "OSM5"; break;}
			case 'OSM6': { mapType = "OSM6"; break;}
			case 'OSM7': { mapType = "OSM7"; break;}
			case 'OSM8': { mapType = "OSM8"; break;}
			default: { mapType = google.maps.MapTypeId.HYBRID; break;}
		}
		
		if ( mapType == "TERRAIN" || mapType == "SATELLITE" || mapType == "ROADMAP" )
		{
			// google maps
		} else {
			// Show OpenStreetMaps credits
			$(el_osm_credits).show();
		}
		
		var mapOptions = {
			mapTypeId: mapType,
			scrollwheel: (zoomOnScrollWheel == 'true'),
			//disable streetview because of missing icon
			streetViewControl: false
		};
		var mtc = { mapTypeIds: mapTypeIds };
		if (google && google.maps && google.maps.MapTypeControlStyle && google.maps.MapTypeControlStyle.DROPDOWN_MENU) {
			mtc.style = google.maps.MapTypeControlStyle.DROPDOWN_MENU;
		}
		mapOptions.mapTypeControlOptions = mtc;
		if (window.WPGPXMAPS_MAP_ID && (''+window.WPGPXMAPS_MAP_ID).length > 0) { mapOptions.mapId = window.WPGPXMAPS_MAP_ID; }
		var map = new google.maps.Map(el_map, mapOptions); 
		
		// helper to hide spinner after first render
		google.maps.event.addListenerOnce(map, 'idle', function(){
			// keep visible until we add track; will hide later when bounds/track ready
		});
			
		map.mapTypes.set("OSM1", new google.maps.ImageMapType({
			getTileUrl: function(coord, zoom) {
				return "https://tile.openstreetmap.org/" + zoom + "/" + coord.x + "/" + coord.y + ".png";
			},
			tileSize: new google.maps.Size(256, 256),
			name: "Open Street Map",
			alt : "Open Street Map",
			maxZoom: 18
		}));
		
		map.mapTypes.set("OSM2", new google.maps.ImageMapType({
			getTileUrl: function(coord, zoom) {
				if (hasThunderforestApiKey)
					return "https://a.tile.thunderforest.com/cycle/" + zoom + "/" + coord.x + "/" + coord.y + ".png?apikey=" + ThunderforestApiKey;
				else
					return "https://a.tile.opencyclemap.org/cycle/" + zoom + "/" + coord.x + "/" + coord.y + ".png";
			},
			tileSize: new google.maps.Size(256, 256),
			name: "Open Cycle Map",
			alt : "Open Cycle Map",
			maxZoom: 18
		}));
		
		map.mapTypes.set("OSM4", new google.maps.ImageMapType({
			getTileUrl: function(coord, zoom) {
				if (hasThunderforestApiKey)
					return "https://a.tile.thunderforest.com/transport/" + zoom + "/" + coord.x + "/" + coord.y + ".png?apikey=" + ThunderforestApiKey;
				else
					return "https://a.tile2.opencyclemap.org/transport/" + zoom + "/" + coord.x + "/" + coord.y + ".png";
			},
			tileSize: new google.maps.Size(256, 256),
			name: "Open Cycle Map - Transport",
			alt : "Open Cycle Map - Transport",
			maxZoom: 18
		}));
		
		map.mapTypes.set("OSM5", new google.maps.ImageMapType({
			getTileUrl: function(coord, zoom) {
				if (hasThunderforestApiKey)
					return "https://a.tile.thunderforest.com/landscape/" + zoom + "/" + coord.x + "/" + coord.y + ".png?apikey=" + ThunderforestApiKey;
				else
					return "https://a.tile3.opencyclemap.org/landscape/" + zoom + "/" + coord.x + "/" + coord.y + ".png";
			},
			tileSize: new google.maps.Size(256, 256),
			name: "Open Cycle Map - Landscape",
			alt : "Open Cycle Map - Landscape",
			maxZoom: 18
		}));
		
		map.mapTypes.set("OSM6", new google.maps.ImageMapType({
			getTileUrl: function(coord, zoom) {
				if (hasMaptilerApiKey) {
					return "https://api.maptiler.com/maps/outdoor-v2/256/" + zoom + "/" + coord.x + "/" + coord.y + ".png?key=" + MTApiKey;
				}
				return "";
			},
			tileSize: new google.maps.Size(256, 256),
			name: "MapTiler - Outdoor",
			alt : "MapTiler - Outdoor",
			maxZoom: 18
		}));
		
		map.mapTypes.set("OSM7", new google.maps.ImageMapType({
			getTileUrl: function(coord, zoom) {
				if (hasMaptilerApiKey) {
					return "https://api.maptiler.com/maps/topo-v2/256/" + zoom + "/" + coord.x + "/" + coord.y + ".png?key=" + MTApiKey;
				}
				return "";
			},
			tileSize: new google.maps.Size(256, 256),
			name: "MapTiler - Topo",
			alt : "MapTiler - Topo",
			maxZoom: 18
		}));
		
		map.mapTypes.set("OSM8", new google.maps.ImageMapType({
			getTileUrl: function(coord, zoom) {
				if (hasMaptilerApiKey) {
					return "https://api.maptiler.com/maps/landscape/256/" + zoom + "/" + coord.x + "/" + coord.y + ".png?key=" + MTApiKey;
				}
				return "";
			},
			tileSize: new google.maps.Size(256, 256),
			name: "MapTiler - Landscape",
			alt : "MapTiler - Landscape",
			maxZoom: 18
		}));
		
		var bounds = new google.maps.LatLngBounds();
		
		var markerCurrentPosition = null;
		
		if ( usegpsposition == "true" )
		{

			// Try HTML5 geolocation
			if(navigator.geolocation) {

				navigator.geolocation.getCurrentPosition(function(position) {
				
					// user position
					var pos = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
				
					// draw current position marker (AdvancedMarkerElement if available)
					markerCurrentPosition = createMarker({ map: map, position: pos, title: "you", iconUrl: currentpositioncon || null, zIndex: 10 });
									  
					// icon already applied via helper when provided
					bounds.extend(pos);
					
					map.setCenter(bounds.getCenter()); 
					map.fitBounds(bounds);
					
					
				}, function() {});
				
				navigator.geolocation.watchPosition(function(position){
														// move current position marker
														if (markerCurrentPosition != null)
														{
															markerCurrentPosition.setPosition(new google.maps.LatLng(position.coords.latitude, position.coords.longitude));
														}
													}, 
													function(e){
														// some errors
													}, 
													{
													  enableHighAccuracy: false,
													  timeout: 5000,
													  maximumAge: 0
													});
			}
		
		}
		
		
		// Print WayPoints
		if (!jQuery.isEmptyObject(waypoints))
		{

			var image = 'https://maps.google.com/mapfiles/ms/micons/flag.png';
			var shadow = 'https://maps.google.com/mapfiles/ms/micons/flag.shadow.png';
			
			if (waypointIcon!='')
			{
				image = waypointIcon;
				shadow = '';
			}
			
			jQuery.each(waypoints, function(i, wpt) {
				
				var lat= wpt.lat;
				var lon= wpt.lon;
				var sym= wpt.sym;
				var typ= wpt.type;
				var wim= image;
				var wsh= shadow;

				if (wpt.img) {
					wim = wpt.img; // pass URL; marker helper will handle
					wsh = '';
				}

				addWayPoint(map, wim, wsh, lat, lon, wpt.name, wpt.desc);
				bounds.extend(new google.maps.LatLng(lat, lon));
				
			});
		}
		
		// Print Images (deferred with small concurrency)
		jQuery("#ngimages_" + targetId).attr("style","display:block;position:absolute;left:-50000px");
		var hasImagesInPost = jQuery("#ngimages_" + targetId + " span").length > 0;
		var imageTasks = [];
		jQuery("#ngimages_" + targetId + " span").each(function(){
		
			var imageLat  = jQuery(this).attr("lat");
			var imageLon  = jQuery(this).attr("lon");
			var $container = jQuery(this);
			
			var processed = false;
			jQuery("img",this).each(function() {
				var $im = jQuery(this);
				var imageUrl  = resolveImageUrl($im);
				if (imageUrl) { $im.attr('src', imageUrl); }
				imageTasks.push({ url: imageUrl, lat: imageLat, lon: imageLon });
				processed = true;
			});
			if (!processed) {
				var href = jQuery('a', $container).attr('href');
				if (href) { imageTasks.push({ url: href, lat: imageLat, lon: imageLon }); }
			}
		});
		function processImageTasks(maxConcurrent){
			var idx = 0, running = 0;
			function next(){
				while (running < maxConcurrent && idx < imageTasks.length){
					running++;
					(function(task){
						var preload = new Image();
						preload.onload = function(){
							var img_w = preload.width, img_h = preload.height;
							var ilat = (''+task.lat).replace(",",".");
							var ilon = (''+task.lon).replace(",",".");
							var p = new google.maps.LatLng(parseFloat(ilat), parseFloat(ilon));
							ensureCustomMarker();
							var mc = new CustomMarker(map, p, task.url, img_w, img_h );
							ngImageMarkers.push(mc);
							running--; next();
						};
						preload.onerror = function(){ running--; next(); };
						preload.src = task.url;
					})(imageTasks[idx++]);
				}
			}
			if (imageTasks.length){ setTimeout(function(){ next(); }, 0); }
		}
		
		
		// helpers for elevation-based coloring
		function clamp01(x){ return x < 0 ? 0 : (x > 1 ? 1 : x); }
		function hexToRgb(hex){
			hex = (hex || '').replace('#','');
			if (hex.length === 3) { hex = hex.split('').map(function(c){return c+c;}).join(''); }
			var num = parseInt(hex,16);
			if (isNaN(num)) { return {r:51,g:102,b:204}; } // fallback #3366cc
			return { r: (num>>16)&255, g: (num>>8)&255, b: num&256 };
		}
		function rgbToHex(r,g,b){
			function c(v){ var s = (v|0).toString(16); return s.length===1?('0'+s):s; }
			return '#'+c(r)+c(g)+c(b);
		}
		function blendHex(baseHex, topHex, alpha){
			alpha = clamp01(alpha);
			var b = hexToRgb(baseHex), t = hexToRgb(topHex);
			var r = Math.round(b.r*(1-alpha) + t.r*alpha);
			var g = Math.round(b.g*(1-alpha) + t.g*alpha);
			var bl = Math.round(b.b*(1-alpha) + t.b*alpha);
			return rgbToHex(r,g,bl);
		}
		function quantize01(v, step){ step = step || 0.2; return Math.round(clamp01(v)/step)*step; }
		function smoothGrades(values, windowSize){
			var n = values ? values.length : 0;
			var out = new Array(n);
			var w = Math.max(1, Math.min(5, windowSize||3));
			for (var i=0;i<n;i++){
				var sum=0, count=0;
				for (var k=-w; k<=w; k++){
					var j = i+k;
					if (j>=0 && j<n){ var v = values[j]; if (typeof v === 'number' && !isNaN(v)) { sum+=v; count++; } }
				}
				out[i] = count>0 ? (sum/count) : values[i];
			}
			return out;
		}

		// Print Track
		if (mapData != '')		
		{
			var points = [];
			var lastCut=0;
			var polylinenes = [];
			var polyline_number=0;
			var color=0;
			var pathData = mapData;
			if (mapData.length > 7000) { pathData = reduceMapData(mapData, 3500); }
			if (elevColoringEnabled && mapData && mapData.length) {
 				var haveMapGrade = (mapGrade && mapGrade.length);
 				// In case mapGrade arrives as a string, try to parse
 				if (!haveMapGrade && typeof mapGrade === 'string') {
 					try { var parsedMG = JSON.parse('['+mapGrade+']'); if (parsedMG && parsedMG.length) { mapGrade = parsedMG; haveMapGrade = true; } } catch(e) {}
 				}
 				if (!haveMapGrade) { mapGrade = buildFallbackMapGrade() || []; }
				var gradesSmooth = (mapGrade && mapGrade.length === mapData.length) ? smoothGrades(mapGrade, 3) : mapGrade;
				// prepare arrow overlay paths (one per contiguous block)
				var arrowPaths = [];
				var currentArrowPath = [];
 				var withinBlock = false;
 				var baseHex = color1[polyline_number % color1.length] || (color1[color1.length-1] || '#3366cc');
 				var currentBucket = null;
 				var currentColorHex = null;
 				var segmentPath = [];
				var lastPoint = null;
 				function flushSegment(){
 					if (segmentPath.length > 1) {
						var po = { path: segmentPath.slice(0), strokeColor: currentColorHex || baseHex, strokeOpacity: .7, strokeWeight: 4, map: map };
 						var pl = new google.maps.Polyline(po);
 						polylinenes.push(pl);
 					}
 					segmentPath = [];
 				}
 				for (i=0; i < pathData.length; i++) {
 					if (pathData[i] == null) {
 						flushSegment();
 						withinBlock = false;
 						currentBucket = null; currentColorHex = null;
 						polyline_number = polyline_number + 1;
						if (currentArrowPath.length > 1) { arrowPaths.push(currentArrowPath.slice(0)); }
						currentArrowPath = [];
						lastPoint = null;
 						continue;
 					}
 					var p = new google.maps.LatLng(pathData[i][0], pathData[i][1]);
 					points.push(p);
 					bounds.extend(p);
 					if (!withinBlock) {
 						baseHex = color1[polyline_number % color1.length] || (color1[color1.length-1] || '#3366cc');
 						withinBlock = true;
 					}
					currentArrowPath.push(p);
 					var g = (gradesSmooth && gradesSmooth.length) ? gradesSmooth[Math.min(i, gradesSmooth.length - 1)] : 0;
 					var alpha = 0;
 					if (typeof g === 'number' && !isNaN(g) && g > 0) {
 						alpha = (g - elevColorThreshold) / (elevColorMax - elevColorThreshold);
 						alpha = clamp01(alpha);
 					}
 					var bucket = quantize01(alpha, 0.2); // 0.0, 0.2, ..., 1.0
 					var segColor = (bucket > 0) ? blendHex(baseHex, '#ff0000', bucket) : baseHex;
 					if (currentBucket === null) { currentBucket = bucket; currentColorHex = segColor; }
 					if (bucket !== currentBucket) {
						// avoid visible gaps: start new segment by overlapping last point
						flushSegment();
 						currentBucket = bucket; currentColorHex = segColor;
						if (lastPoint) { segmentPath.push(lastPoint); }
 					}
 					segmentPath.push(p);
					lastPoint = p;
 				}
 				flushSegment();
				if (currentArrowPath.length > 1) { arrowPaths.push(currentArrowPath.slice(0)); }
				// render arrow overlays per block to keep spacing independent from color segmentation
				if (arrowRepeat && arrowRepeat !== '') {
					for (var ap=0; ap<arrowPaths.length; ap++){
						var apath = arrowPaths[ap];
						if (apath.length > 1) {
							var apo = {
								path: apath,
								strokeColor: baseHex,
								strokeOpacity: 0,
								strokeWeight: 0,
								map: map,
								icons: [{ icon: { path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW, scale: 2, strokeOpacity: 1, strokeWeight: 2 }, repeat: arrowRepeat }]
							};
							var apl = new google.maps.Polyline(apo);
							polylinenes.push(apl);
						}
					}
				}
 			} else {
				for (i=0; i < mapData.length; i++) 
				{	
					if (mapData[i] == null)
					{
						color=color1[polyline_number % color1.length];
						var polyOptions = {
							path: points.slice(lastCut,i),
							strokeColor: color,
							strokeOpacity: .7,
							strokeWeight: 4,
							map: map
						};
						if (arrowRepeat && arrowRepeat !== '') {
							polyOptions.icons = [{
								icon: { path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW, scale: 2, strokeOpacity: 1, strokeWeight: 2 },
								repeat: arrowRepeat
							}];
						}
						var poly = new google.maps.Polyline(polyOptions);
						polylinenes.push(poly);
						lastCut=i;
						polyline_number= polyline_number +1;
					}
					else
					{
						var p = new google.maps.LatLng(mapData[i][0], mapData[i][1]);
						points.push(p);
						bounds.extend(p)			
					}
				}
				if (points.length != lastCut)
				{
					if ( polyline_number < color1.length)
					{
						color=color1[polyline_number];
					}
					else
					{
						color=color1[color1.length-1];
					}
					var polyOptions = {
						path: points.slice(lastCut),
						strokeColor: color,
						strokeOpacity: .7,
						strokeWeight: 4,
						map: map
					};
					if (arrowRepeat && arrowRepeat !== '') {
						polyOptions.icons = [{
							icon: { path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW, scale: 2, strokeOpacity: 1, strokeWeight: 2 },
							repeat: arrowRepeat
						}];
					}
					var poly = new google.maps.Polyline(polyOptions);
					polylinenes.push(poly)			
					currentPoints = [];
					polyline_number= polyline_number +1;
				}
			}
			
			if (startIcon != '')
			{
				createMarker({ map: map, position: points[0], title: "Start", iconUrl: startIcon, zIndex: 10 });
			}

			if (endIcon != '')
			{
				createMarker({ map: map, position: points[ points.length -1 ], title: "End", iconUrl: endIcon, zIndex: 10 });
			}

			var first = getItemFromArray(pathData,0)
			
			if (currentIcon == '')
			{
				currentIcon = "https://maps.google.com/mapfiles/kml/pal4/icon25.png";
			}
			
			var marker = createMarker({ map: map, position: new google.maps.LatLng(first[0], first[1]), title: "Start", iconUrl: currentIcon, zIndex: 10 });
			
			// Throttle hover updates to reduce work on frequent events
			var lastHoverUpdate = 0;
			var hoverThrottleMs = 80;
			for (i=0; i < polylinenes.length; i++) 
			{
				google.maps.event.addListener(polylinenes[i],'mouseover',function(event){
					var nowTs = Date.now ? Date.now() : new Date().getTime();
					if ((nowTs - lastHoverUpdate) < hoverThrottleMs) { return; }
					lastHoverUpdate = nowTs;
					if (marker)
					{
						marker.setPosition(event.latLng);
						marker.setTitle(lng.currentPosition);
						if (hchart)
						{
							var tooltip = hchart.tooltip;
							var l1 = event.latLng.lat();
							var l2 = event.latLng.lng();
							var ci = getClosestIndex(pathData,l1,l2);
							var items = [];
							var seriesLen = hchart.series.length;
							for(var i=0; i<seriesLen;i++)
							{
								items.push(hchart.series[i].data[ci]);
							}
							if (items.length > 0)
								tooltip.refresh(items);
						}
					}
				});		
			}
			// defer image loading to after track
			processImageTasks(3);
		}
		
		// defer hiding spinner until after track rendering completes
		hideSpinner();
		
		map.setCenter(bounds.getCenter()); 
		map.fitBounds(bounds);
		
		// FIX post tabs	
		var $_tab = $(el).closest(".wordpress-post-tabs").eq(0);	
		if ($_tab)
		{
			$("div > ul > li > a", $_tab).click(function(e){		
				setTimeout(function(e){		
					google.maps.event.trigger(map, 'resize');
					//map.setCenter(bounds.getCenter());
					map.fitBounds(bounds);
					tabResized = true;
				},10);
			});
		}	
		
		var controlUIcenter = null;
		var idFirstCenterChanged = true;
		
		google.maps.event.addListener(map, 'center_changed', function() {

			if (idFirstCenterChanged == true)
			{
				idFirstCenterChanged = false;
				return;
			}
		
			if (controlUIcenter == null)
			{
				// Set CSS for the control border
				controlUIcenter = document.createElement('img');
				controlUIcenter.src = pluginUrl + "/wp-gpx-maps/img/backToCenter.png";
				controlUIcenter.style.cursor = 'pointer';
				controlUIcenter.title = lng.backToCenter;
				controlDiv.appendChild(controlUIcenter);

				// Setup the click event listeners
				google.maps.event.addDomListener(controlUIcenter, 'click', function(event) {
					map.setCenter(bounds.getCenter()); 
					map.fitBounds(bounds);
					controlDiv.removeChild(controlUIcenter);
					controlUIcenter = null;
					return false;			
				});		
			}

		});
		
		var graphh = jQuery('#hchart_' + params.targetId).css("height");
		
		if (graphDist != '' && (graphEle != '' || graphSpeed != '' || graphHr != '' || graphAtemp != '' || graphCad != '') && graphh != "0px")
		{

			var valLen = graphDist.length;
		
			var l_y_arr = [];
			
			if (unit=="1")
			{
				l_x = { suf : "mi", dec : 1 };
				l_y = { suf : "ft", dec : 0 };
			}
			else if (unit=="2")
			{
				l_x = { suf : "km", dec : 1 };
				l_y = { suf : "m", dec : 0 };
			}
			else if (unit=="3")
			{
				l_x = { suf : "NM", dec : 1 };
				l_y = { suf : "m", dec : 0 };
			}		
			else if (unit=="4")
			{
				l_x = { suf : "mi", dec : 1 };
				l_y = { suf : "m", dec : 0 };
			}
			else if (unit=="5")
			{
				l_x = { suf : "NM", dec : 1 };
				l_y = { suf : "ft", dec : 0 };
			}
			else
			{
				l_x = { suf : "m", dec : 0 };
				l_y = { suf : "m", dec : 0 };
			}
			
			var nn = 1111.1;
			var _nn = nn.toLocaleString();
			var _nnLen = _nn.length;
			var decPoint = _nn.substring(_nnLen - 2, _nnLen - 1);
			var thousandsSep = _nn.substring(1, 2);
			
			if (decPoint == "1")
				decPoint = ".";
				
			if (thousandsSep == "1")
				thousandsSep = "";		
				
			// define the options
			var hoptions = {
				chart: {
					renderTo: 'hchart_' + params.targetId,
					type: 'area',
					events: {
						selection: function(event) {
							var chart = this;
							var xAxes = event && (event.xAxis || (event.detail && event.detail.xAxis));
							if (xAxes && xAxes[0]) {
								var xmin = xAxes[0].min, xmax = xAxes[0].max;
								el_report.innerHTML = 'Zoom: '+ (xmin).toFixed(l_x.dec) + ' ' + l_x.suf + ' -> '+ (xmax).toFixed(decPoint) + ' ' + l_x.suf + '<br />';

								var series = chart && chart.series ? chart.series : [];
								for (var i = 0; i < series.length; i++) {
									var dataX = {value: 0, count: 0};
									var points = series[i].points || series[i].data || [];
									for (var j = 0; j < points.length; j++) {
										var px = points[j].x, py = points[j].y;
										if (px >= xmin && px <= xmax && typeof py === 'number') {
											dataX.value += py;
											dataX.count +=1;
										}
									}
									var name = series[i].name;
									var avgLbl = (lng.avgLabel || 'avg');
									if (dataX.count > 0) {
										if (name == lng.altitude) {
											el_report.innerHTML += name + ' ' + avgLbl + ': ' + (dataX.value / dataX.count).toFixed(l_y.dec) + " " + l_y.suf + "<br />";
										} else if (name == lng.speed) {
											el_report.innerHTML += name + ' ' + avgLbl + ': ' + (dataX.value / dataX.count).toFixed(l_s.dec) + " " + l_s.suf + "<br />";
										} else if (name == lng.grade) {
											el_report.innerHTML += name + ' ' + avgLbl + ': ' + (dataX.value / dataX.count).toFixed(l_grade.dec) + " " + l_grade.suf + "<br />";
										} else if (name == lng.cadence) {
											el_report.innerHTML += name + ' ' + avgLbl + ': ' + (dataX.value / dataX.count).toFixed(l_cad.dec) + " " + l_cad.suf + "<br />";
										} else if (name == lng.heartRate) {
											el_report.innerHTML += name + ' ' + avgLbl + ': ' + (dataX.value / dataX.count).toFixed(l_hr.dec) + " " + l_hr.suf + "<br />";
										} else {
											el_report.innerHTML += name + ' ' + avgLbl + ': ' + (dataX.value / dataX.count) + "<br />";
										}
									}
								}

								el_report.innerHTML += "<br />";
							} else {
								el_report.innerHTML = '';
							}
						}
					},
					zoomType: 'x'
				},
				title: {
					text: null
				},
				xAxis: {
					type: 'integer',
					//gridLineWidth: 1,
					//tickInterval: 1000,
					labels: {
						formatter: function() {
							return Highcharts.numberFormat(this.value, l_x.dec,decPoint,thousandsSep) + l_x.suf;
						}
					}
				},
				yAxis: [],
				legend: {
					align: 'center',
					verticalAlign: 'top',
					y: -5,
					floating: true,
					borderWidth: 0
				},
				tooltip: {
					shared: true,
					crosshairs: true,
					formatter: function() {
						if (marker)
						{
							// find original index corresponding to this.x using eleIndexMap
							try {
								var series = hchart && hchart.series && hchart.series.length ? hchart.series[0] : null;
								if (series && series.data) {
									for (var pi=0; pi<series.data.length; pi++) {
										if (series.data[pi].x == this.x) {
											var orig = (typeof eleIndexMap !== 'undefined' && eleIndexMap.length>pi) ? eleIndexMap[pi] : pi;
											var point = getItemFromArray(mapData, orig);
											if (point) { marker.setPosition(new google.maps.LatLng(point[0],point[1])); }
											marker.setTitle(lng.currentPosition);
											break;
										}
									}
								} 
							} catch(e) {}
						}
						var tooltip = "<b>" + Highcharts.numberFormat(this.x, l_x.dec,decPoint,thousandsSep) + l_x.suf + "</b><br />"; 
						for (i=0; i < this.points.length; i++)
						{
							tooltip += this.points[i].series.name + ": " + Highcharts.numberFormat(this.points[i].y, l_y_arr[i].dec,decPoint,thousandsSep) + l_y_arr[i].suf + "<br />"; 					
						}
						return tooltip;
					}
				},
				plotOptions: {
					area: {
						fillOpacity: 0.1,
						connectNulls : true,
						marker: {
							enabled: false,
							symbol: 'circle',
							radius: 2,
							states: {
								hover: {
									enabled: true
								}
							}
						}					
					}
				},
				credits: {
					enabled: false
				},	
				series: []
			};
		
			if (graphEle != '')
			{
				
				var eleData = [];
				var eleIndexMap = [];
				var myelemin = 99999;
				var myelemax = -99999;
			
				for (i=0; i<valLen; i++) 
				{
					if (graphDist[i] != null)
					{
						var _graphEle = graphEle[i];
						eleData.push([graphDist[i],_graphEle]);
						eleIndexMap.push(i);
						if (_graphEle > myelemax) 
							myelemax = _graphEle; 
						if (_graphEle < myelemin) 
							myelemin = _graphEle;
					}
				}

				var yaxe = { 
					title: { text: null },
					labels: {
						align: 'right',
						x: -16,
						reserveSpace: true,
						formatter: function() {
							return Highcharts.numberFormat(this.value, l_y.dec,decPoint,thousandsSep) + l_y.suf;
						}
					}
				}
		
				if ( chartFrom1 != '' )
				{
					yaxe.min = chartFrom1;
					yaxe.startOnTick = false;
				}
				else { 
					yaxe.min = myelemin; 
				}
				
				if ( chartTo1 != '' )
				{
					yaxe.max = chartTo1;
					yaxe.endOnTick = false;
				}
				else { 
					yaxe.max = myelemax; 
				}
									
				hoptions.yAxis.push(yaxe);
				hoptions.series.push({
										name: lng.altitude,
										lineWidth: 1,
										marker: { radius: 0 },
										data : eleData,
										color: color2,
										yAxis: hoptions.series.length
									});			
				
				l_y_arr.push(l_y);
			}
			
			if (graphSpeed != '')			{
				if (unitspeed == '6') /* min/100m */				{					l_s = { suf : "min/100m", dec : 2 };				} 
				else if (unitspeed == '5') /* knots */
				{
					l_s = { suf : "knots", dec : 2 };
				} 
				else if (unitspeed == '4') /* min/miles */
				{
					l_s = { suf : "min/mi", dec : 2 };
				} 
				else if (unitspeed == '3') /* min/km */
				{
					l_s = { suf : "min/km", dec : 2 };
				} 
				else if (unitspeed == '2') /* miles/h */
				{
					l_s = { suf : "mi/h", dec : 0 };
				} 
				else if (unitspeed == '1') /* km/h */
				{
					l_s = { suf : "km/h", dec : 0 };
				} 
				else
				{
					l_s = { suf : "m/s", dec : 0 };
				}
				
				var speedData = [];
			
				for (i=0; i<valLen; i++) 
				{
					if (graphDist[i] != null)
						speedData.push([graphDist[i],graphSpeed[i]]);
				}

				var yaxe = { 
					title: { text: null },
					labels: {
						//align: 'right',
						formatter: function() {
							return Highcharts.numberFormat(this.value, l_s.dec,decPoint,thousandsSep) + l_s.suf;
						}
					},
					opposite: true
				}
							
				if ( chartFrom2 != '' )
				{
					yaxe.min = chartFrom2;
					yaxe.startOnTick = false;				
				}
				
				if ( chartTo2 != '' )
				{
					yaxe.max = chartTo2;
					yaxe.endOnTick = false;				
				}
									
				hoptions.yAxis.push(yaxe);
				hoptions.series.push({
										name: lng.speed,
										lineWidth: 1,
										marker: { radius: 0 },
										data : speedData,
										color: color3,
										yAxis: hoptions.series.length
									});			
				
				l_y_arr.push(l_s);
			}
			
			if (graphHr != '')
			{
				
				var hrData = [];
			
				for (i=0; i<valLen; i++) 
				{
					if (graphDist[i] != null)
					{
						var c = graphHr[i];
						if (c==0)
							c = null;
						hrData.push([graphDist[i],c]);				
					}
				}

				var yaxe = { 
					title: { text: null },
					labels: {
						//align: 'right',
						formatter: function() {
							return Highcharts.numberFormat(this.value, l_hr.dec,decPoint,thousandsSep) + l_hr.suf;
						}
					},
					opposite: true
				}

				hoptions.yAxis.push(yaxe);
				hoptions.series.push({
										name: lng.heartRate,
										lineWidth: 1,
										marker: { radius: 0 },
										data : hrData,
										color: color4,
										yAxis: hoptions.series.length
									});			
				
				l_y_arr.push(l_hr);
			}
			
			
			if (graphAtemp != '')
			{
				
				var atempData = [];
			
				for (i=0; i<valLen; i++) 
				{
					if (graphDist[i] != null)
					{
						var c = graphAtemp[i];
						if (c==0)
							c = null;
						atempData.push([graphDist[i],c]);				
					}
				}

				var yaxe = { 
					title: { text: null },
					labels: {
						//align: 'right',
						formatter: function() {
							return Highcharts.numberFormat(this.value, 1, decPoint,thousandsSep) + " °C";
						}
					},
					opposite: true
				}

				hoptions.yAxis.push(yaxe);
				hoptions.series.push({
										name: lng.atemp,
										lineWidth: 1,
										marker: { radius: 0 },
										data : atempData,
										color: color7,
										yAxis: hoptions.series.length
									});			
				
				l_y_arr.push({ suf : "°C", dec : 1 });
			}
			
			
			if (graphCad != '')
			{
				
				var cadData = [];
			
				for (i=0; i<valLen; i++) 
				{
					if (graphDist[i] != null)
					{
						var c = graphCad[i];
						if (c==0)
							c = null;
						cadData.push([graphDist[i],c]);
					}
				}

				var yaxe = { 
					title: { text: null },
					labels: {
						//align: 'right',
						formatter: function() {
							return Highcharts.numberFormat(this.value, l_cad.dec,decPoint,thousandsSep) + l_cad.suf;
						}
					},
					opposite: true
				}
									
				hoptions.yAxis.push(yaxe);
				hoptions.series.push({
										name: lng.cadence,
										lineWidth: 1,
										marker: { radius: 0 },
										data : cadData,
										color: color5,
										yAxis: hoptions.series.length
									});			
				
				l_y_arr.push(l_cad);
			}

			if (graphGrade != '')
			{
				
				var cadData = [];
			
				for (i=0; i<valLen; i++) 
				{
					if (graphDist[i] != null)
					{
						var c = graphGrade[i];
						if (c==0)
							c = null;
						cadData.push([graphDist[i],c]);
					}
				}

				var yaxe = { 
					title: { text: null },
					labels: {
						//align: 'right',
						formatter: function() {
							return Highcharts.numberFormat(this.value, l_grade.dec,decPoint,thousandsSep) + l_grade.suf;
						}
					},
					opposite: true
				}
									
				hoptions.yAxis.push(yaxe);
				hoptions.series.push({
										name: lng.grade,
										lineWidth: 1,
										marker: { radius: 0 },
										data : cadData,
										color: color6,
										yAxis: hoptions.series.length
									});			
				
				l_y_arr.push(l_grade);
			}

			if ((window.innerWidth >= 800) && !(/Mobile|iPhone|iPad|Android.*Mobile/i.test(navigator.userAgent))) {
				var hchart;
				setTimeout(function(){ hchart = new Highcharts.Chart(hoptions); }, 0);
				// Append average values under the chart (read-only UI)
				try {
					var hasEle = (graphEle && graphEle.length && graphEle.join('').length > 0);
					var hasSpeed = (graphSpeed && graphSpeed.length && graphSpeed.join('').length > 0);
					var $summary = jQuery('#report_' + params.targetId);
					if ($summary && (hasEle || hasSpeed)) {
						var avgHtml = '';
						if (hasEle) {
							var sumEle = 0, cntEle = 0, maxEle = -Infinity, minEle = Infinity;
							for (var i=0;i<graphEle.length;i++){ var v = graphEle[i]; if (typeof v === 'number'){ sumEle+=v; cntEle++; if (v>maxEle) maxEle=v; if (v<minEle) minEle=v; }}
							if (cntEle>0){
								var avgEle = Highcharts.numberFormat(sumEle/cntEle, 0) + (l_y && l_y.suf ? (' ' + l_y.suf) : '');
								avgHtml += '<div class="wpgpxmaps_avgele">'+ avgEle +'</div>';
							}
						}
						// remove speed average (already printed elsewhere)
						if (avgHtml) {
							// place after min elevation line in the report block if present
							try {
								var rep = document.getElementById('report_' + params.targetId);
								if (rep) {
									// append; CSS can adjust order; server summary already prints max/min totals
									var avgAltLabel = (lng.avgAltitude || 'Avg altitude');
									rep.insertAdjacentHTML('beforeend','<div>' + avgAltLabel + ': <b>'+avgHtml.replace(/<[^>]*>/g,'')+'</b></div>');
								}
							} catch(e) {}
						}
					}
				} catch(e) {}
			} else {
				jQuery("#hchart_" + params.targetId).css("display","none");
			}
		
		}
		else  {
			jQuery("#hchart_" + params.targetId).css("display","none");
		}
	
        return this;
    };
	
	function addWayPoint(map, image, shadow, lat, lon, title, descr)
	{
		var p = new google.maps.LatLng(lat, lon);
		var m = createMarker({ map: map, position: p, title: title, iconUrl: image, zIndex: 5 });
						  
		google.maps.event.addListener(m, 'click', function() {
			if (infowindow)
			{
				infowindow.close(); 		
			}
			var cnt = '';	
			
			if (title=='')
			{
				cnt = "<div>" + unescape(descr) + "</div>";
			}
			else
			{
				cnt = "<div><b>" + title + "</b><br />" + unescape(descr) + "</div>";
			}
			
			cnt += "<br /><p><a href='https://maps.google.com?daddr=" + lat + "," + lon + "' target='_blank'>Itin&eacute;raire</a></p>";
			
			infowindow = new google.maps.InfoWindow({ content: cnt});
			infowindow.open(map,m);
		});	
		/*
		google.maps.event.addListener(m, "mouseout", function () {
			if (infowindow)
			{
				infowindow.close();
			}
		});
		*/
	}

	function getItemFromArray(arr,index)
	{
		try
		{
		  return arr[index];
		}
		catch(e)
		{
			return [0,0];
		}
	}


	function getClosestIndex(points,lat,lon)
	{
		var dd=10000;
		var ii=0;
		for (i=0; i < points.length; i++) 
		{
			if (points[i]==null)
				continue;
		
			var d = dist(points[i][0], points[i][1], lat, lon);
			if ( d < dd )
			{
				ii = i;
				dd = d;
			}
		}
		return ii;
	}

	function getClosestImage(lat,lon,targetId)
	{
		var dd=10000;
		var img;
		var divImages = document.getElementById("ngimages_"+targetId);
		var img_spans = divImages.getElementsByTagName("span");   
		for (var i = 0; i < img_spans.length; i++) {   
			var imageLat = img_spans[i].getAttribute("lat");
			var imageLon = img_spans[i].getAttribute("lon");	
						
			imageLat = imageLat.replace(",", ".");
			imageLon = imageLon.replace(",", ".");
			
			var d = dist(imageLat, imageLon, lat, lon);
			if ( d < dd )
			{
				img = img_spans[i];
				dd = d;
			}		
		}
		return img;
	}

	function isNumeric(input){
		var RE = /^-{0,1}\d*\.{0,1}\d+$/;
		return (RE.test(input));
	}

	function dist(lat1,lon1,lat2,lon2)
	{
		// mathematically not correct but fast
		var dLat = (lat2-lat1);
		var dLon = (lon2-lon1);
		return Math.sqrt(dLat * dLat + dLon * dLon);
	}
	
}( jQuery ));
