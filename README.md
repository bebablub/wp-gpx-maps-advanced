# WP GPX Maps

**Contributors:** bastianonm, Stephan Klein, Michel Selerin, Benjamin Barinka
**Tags:** maps, gpx, gps, graph, chart, google maps, track, garmin, image, nextgen-gallery, nextgen, exif, OpenStreetMap, OpenCycleMap, Hike&Bike, heart rate, heartrate, cadence
**Requires at least:** 2.0.0
**Tested up to:** 6.4
**Stable tag:** 2.1.0

Draws a GPX track with altitude graph. You can also display your NextGen Gallery images in the map.

---

## Description

This plugin takes a GPX file with the track you have made and displays the map of the track with an interactive altitude graph (where available).

Based up on version 1.3.17 of
**GitHub:** https://github.com/devfarm-it/wp-gpx-maps

### Features

- Fully configurable: custom colors, custom icons, multiple language support
- Privacy mode to trim the start and end of the track and hide exact start/end locations
- Grade-based elevation coloring on the map (highlights steep sections)
- Direction arrows along the track at configurable intervals
- HTML5 GPS position: show the visitor's real-time location on the map and follow their movement
- Distance type selector: Normal (3D), Flat (horizontal only), or Climb (elevation only) for mountaineering reports
- Spinner overlay while map initializes
- Conditional asset loading (scripts only enqueued when shortcode is present)
- DNS prefetch/preconnect resource hints for faster third-party connections

### Supported Charts

- Altitude
- Speed
- Heart Rate
- Temperature
- Cadence
- Grade

### NextGen Gallery Integration

Display your NextGen Gallery images inside the map. Even if you don't have a GPS camera, this plugin can retrieve the image position starting from the image date and your GPX file.

### Post Attachments Integration

Extended by Stephan Klein ([klein-gedruckt.de](https://klein-gedruckt.de/2015/03/wordpress-plugin-wp-gpx-maps/)) to support displaying all images attached to a post without using NGG.

### Translations (18 languages)

| Language | Locale |
|---|---|
| Catalan | ca |
| Dutch | nl_NL |
| English | (default) |
| French | fr_FR |
| German | de_DE |
| Hungarian | hu_HU |
| Italian | it_IT |
| Norwegian | nb_NO |
| Polish | pl_PL |
| Portuguese (Brazilian) | pt_BR |
| Russian | ru_RU |
| Spanish | es_ES |
| Swedish | sv_SE |
| Turkish | tr_TR |
| Bulgarian | bg_BG |
| Slovak | cs_CZ |
| Japanese | ja_JP |

Many thanks to all who helped with translations.

- iPhone/iPad/iPod compatible

**Demo:** http://www.pedemontanadelgrappa.it/category/mappe/
**Support Forum:** http://www.devfarm.it/forums/forum/wp-gpx-maps/

---

## Self-Hosting Highcharts

Since version 2.0.1, WP-GPX-Maps includes self-hosted Highcharts files to avoid rate limiting and improve loading performance.

### Files Included

The plugin bundles:
- **Highcharts v11.x** (latest) - Used when "Highcharts v11" is enabled in settings
- **Highcharts accessibility module** - Required for v11 to remove console warnings
- **Highcharts v3.0.10** - Legacy fallback option

### File Location

```
wp-gpx-maps/js/highcharts/
  v11/
    highcharts.js
    modules/accessibility.js
  v3.0.10/
    highcharts.js
```

### How to Use

1. **Default (v11):** Enable "Highcharts v11" in Settings > WP-GPX-Maps
2. **Legacy mode:** Disable the toggle to use Highcharts v3.0.10

No additional configuration needed -- files are automatically loaded from the plugin directory.

### License Note

Highcharts is free for non-commercial usage and development. For commercial websites, please review their [licensing options](https://shop.highsoft.com/).

---

## Supported GPX Namespaces

- http://www.topografix.com/GPX/1/0
- http://www.topografix.com/GPX/1/1
- http://www.garmin.com/xmlschemas/GpxExtensions/v3
- http://www.garmin.com/xmlschemas/TrackPointExtension/v1

Thanks to: [www.securcube.net](http://www.securcube.net/), [www.devfarm.it](http://www.devfarm.it/), [www.pedemontanadelgrappa.it](http://www.pedemontanadelgrappa.it/)

Up to version 1.1.15, [Highcharts](http://www.highcharts.com/) is the only available rendering engine. Please respect their license and pricing (free for non-commercial usage only).

---

## Installation

1. Use the classic WordPress plugin installer or copy the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the "Plugins" menu in WordPress
3. Add the shortcode `[sgpx gpx=">relative path to your gpx<"]` or `[sgpx gpx=">http://somesite.com/files/yourfile.gpx<"]`

---

## Frequently Asked Questions

### What are all available shortcode attributes?

| Attribute | Description | Default |
|---|---|---|
| `gpx` | Relative path to GPX file | |
| `width` | Width in pixels | 100% |
| `mheight` | Map height | 450px |
| `gheight` | Graph height (set to 0 to hide) | 200px |
| `mtype` | Map type: HYBRID, ROADMAP, SATELLITE, TERRAIN, OSM1 (Open Street Map), OSM2 (Open Cycle Map), OSM3 (Hike & Bike), OSM4 (Open Cycle Map - Transport), OSM5 (Open Cycle Map - Landscape), OSM6 (MapTiler - Outdoor), OSM7 (MapTiler - Topo), OSM8 (MapTiler - Landscape) | HYBRID |
| `waypoints` | Show GPX waypoints on the map | FALSE |
| `donotreducegpx` | Print all points without reducing | FALSE |
| `pointsoffset` | Skip points closer than XX meters | 10 |
| `uom` | Unit of measure: 0=meters, 1=feet/miles, 2=meters/km, 3=meters/nautical miles, 4=meters/miles, 5=feet/nautical miles | 0 |
| `mlinecolor` | Map line color | #3366cc |
| `glinecolor` | Altitude line color | #3366cc |
| `showele` | Show elevation data in chart | TRUE |
| `showspeed` | Show speed in chart | FALSE |
| `showhr` | Show heart rate in chart | FALSE |
| `showcad` | Show cadence in chart | FALSE |
| `showgrade` | Show grade in chart | FALSE |
| `glinecolorspeed` | Speed line color | #ff0000 |
| `glinecolorhr` | Heart rate line color | #ff77bd |
| `glinecolorcad` | Cadence line color | #beecff |
| `glinecolorgrade` | Grade line color | #beecff |
| `uomspeed` | Speed unit: 0=m/s, 1=km/h, 2=miles/h, 3=min/km, 4=min/miles, 5=knots | 0 |
| `chartFrom1` | Minimum value for altitude chart | (auto) |
| `chartTo1` | Maximum value for altitude chart | (auto) |
| `chartFrom2` | Minimum value for speed chart | (auto) |
| `chartTo2` | Maximum value for speed chart | (auto) |
| `arrowskm` | Direction arrows every N km (decimals allowed, e.g. `0.5`) | (disabled) |
| `startIcon` | Start track icon URL | |
| `endIcon` | End track icon URL | |
| `currentIcon` | Current position icon (on mouse hover) | |
| `waypointicon` | Waypoint custom icon URL | |
| `nggalleries` | NextGen Gallery ID(s), comma-separated | |
| `ngimages` | NextGen Image ID(s), comma-separated | |
| `dtoffset` | Difference in seconds between GPX tool date and camera date | 0 |
| `usegpsposition` | Show the visitor's real-time GPS position on the map via HTML5 Geolocation | FALSE |
| `currentpositioncon` | Custom icon URL for the visitor's GPS position marker | (default pin) |
| `distanceType` | Distance calculation: 0=Normal (3D), 1=Flat (horizontal only), 2=Climb (elevation only) | 0 |
| `zoomonscrollwheel` | Zoom map with mouse scroll wheel | FALSE |
| `download` | Allow users to download the GPX file | FALSE |
| `skipcache` | Do not use cache (slower, not recommended) | FALSE |
| `privacymode` | Remove points at the start and end of the track to hide the exact location | FALSE |
| `summary` | Print summary details table | FALSE |
| `summarytotlen` | Show total distance in summary | FALSE |
| `summarymaxele` | Show max elevation in summary | FALSE |
| `summaryminele` | Show min elevation in summary | FALSE |
| `summaryeleup` | Show total climbing in summary | FALSE |
| `summaryeledown` | Show total descent in summary | FALSE |
| `summaryavgele` | Show average elevation in summary | FALSE |
| `summaryavgspeed` | Show average speed in summary | FALSE |
| `summarytotaltime` | Show total time in summary | FALSE |

### What happens if I have a very large GPX?

This plugin prints a reduced number of points to speed up JavaScript and page load.

### Is it free?

Yes!

---

## Screenshots

1. Simple GPX
2. GPX with waypoints
3. Admin area - List of tracks
4. Admin area - Settings
5. Altitude & Speed
6. Altitude & Speed & Heart rate

---

## Changelog

### 2.1.0 (new)

- OSM6: MapTiler Outdoor (hillshading, contour lines, hiking trails).
- OSM7: MapTiler Topo (classic topographic map with contours).
- OSM8: MapTiler Landscape (nature-focused with hillshading).
- DNS prefetch/preconnect updated for api.maptiler.com.

### 2.0.1 (new)

**New features:**
- Elevation coloring on the map for high slopes (grade-based polyline coloring from base color to red).
- Direction arrows along track via `arrowskm` (every N km) with admin defaults (`wpgpxmaps_arrows_enabled`, `wpgpxmaps_arrows_km`).
- Privacy mode via `privacymode` and admin default `wpgpxmaps_privacymode`, trimming approximately 5% of points from the track with a minimum of 100 and maximum of 500 total points removed.
- Conditional loading: Google Maps/Highcharts only enqueued when shortcode is present.
- Resource hints: DNS prefetch/preconnect for Google/Highcharts/OSM/Thunderforest providers.
- Spinner overlay while map initializes; corrected hide timing.
- Summary table: added Avg elevation (units respected), Move ratio, Time climbing, Avg climbing speed.
- Hiding start and end position icons (leave URL empty).

**Security/compatibility:**
- Added nonces and verification for admin actions (upload/delete/clear cache).
- Prepared SQL for waypoints; escaped output in admin and front-end.
- SSRF hardening for remote GPX: http/https only, private IP ranges blocked, downloads via WordPress HTTP API, temp files via `wp_tempnam()`.
- XML parsing hardened with `LIBXML_NONET` (block external entities).
- Cache switched to JSON with UTF-8 substitution; cache file permission set to 0644.
- EXIF parsing fixes: safe file handles; robust fallbacks when EXIF missing.
- PHP 8.x fixes: null/empty guards, reindex arrays after `unset`, division-by-zero protection, stricter numeric casts.

**Google Maps:**
- Async loader with retry guard to avoid "Map is not a constructor" timing errors.
- Map ID support (Advanced Markers); automatic fallback to classic markers if unsupported.
- Guarded `MapTypeControlStyle.DROPDOWN_MENU` usage; disabled Street View control icon.
- Throttled polyline hover updates to reduce CPU; shortened readiness wait.

**Highcharts:**
- Optional Highcharts v11 (with `accessibility.js`) and legacy v3 fallback; v11 is default when unset.
- Self-hosted Highcharts files in `js/highcharts/` to avoid CDN rate limiting.
- Fixed selection callback for v11; improved y-axis label spacing to prevent overlaps.

**Images (NextGen/attachments):**
- More robust lazy-loading handling and URL resolution; fallback to `<a href>` when `<img>` missing.
- Preloading and hover behavior refined to prevent flicker and pointer ping-pong.
- Added EXIF fallback to attachment metadata timestamps when GPS coordinates are absent.

**Providers/tiles:**
- Switched all OSM/OCM tiles to HTTPS; footer credits shown for non-Google layers.

**Admin:**
- Settings: Map ID, Highcharts v11 toggle, default arrows settings.
- Elevation highlight settings: enable/disable, grade threshold, max grade.
- Notices on plugin pages for missing Google Maps API key / Map ID (non-blocking).
- Upgraded admin `bootstrap-table` to 1.13.2 (scripts/styles loaded only on plugin pages).

**Other:**
- Folder shortcode processing aligned with main shortcode logic.
- Download link fix for local files; remote URLs untouched.

### 1.3.17

- Security hardening: Added CSRF protection, SQL injection prevention, and SSRF mitigations.
- Modernized Google Maps API integration with async loading and Advanced Markers support.
- Improved cache handling with JSON format and safer deserialization.
- Fixed file handle leaks and null pointer exceptions.
- Updated deprecated WordPress functions.
- Enhanced folder shortcode reliability and alignment with main shortcode.
- Added HTTPS for all tile URLs to prevent mixed content warnings.
- Removed legacy color picker dependency.
- Updated tested WordPress version to 6.4.
- Contributors: Benjamin Barinka

### 1.3.16

- Added Norwegian nb_NO translation (thanks to thordivel).
- Added Japanese ja_JP translation (thanks to dentos).

### 1.3.15

- Switched to HTTPS where possible (thanks to delitestudio).

### 1.3.14

- Added Thunderforest API Key on settings for OpenCycleMap.

### 1.3.13

- Added Google Maps API key on settings.
- Removed parameter `sensor` on Google Maps JS.
- Added unit of measure of speed for swimmers: min/100 meters.

### 1.3.12

- Fix incompatibility with Debian PHP7 (thanks to phbaer). https://github.com/devfarm-it/wp-gpx-maps/pull/5

### 1.3.10

- Improved German translations (thanks to Konrad). http://tadesse.de/7882/2015-wanderung-ostrov-tisa-ii/

### 1.3.9

- Retrieve waypoints in JSON, possibility to add a custom marker (changed by Michel Selerin).

### 1.3.8

- Improved Google Maps visualization.

### 1.3.7

- NextGen Gallery Attachment support. Thanks to Stephan Klein (https://klein-gedruckt.de/2015/03/wordpress-plugin-wp-gpx-maps/).

### 1.3.6

- Fix: remote file download issue.
- Fix: download file link with WPML.
- Improved cache with filetime (thanks to David).

### 1.3.5

- Fix: Garmin cadence again.
- Fix: WP Tabs.

### 1.3.4

- Fix: Garmin cadence.
- Infowindows closing on mouseout.

### 1.3.3

- Add feet/Nautical Miles units (thanks to elperepat).
- Update OpenStreetMaps Credits.
- WP Tabs fix.

### 1.3.2

- Fix: left axis not visible (downgrade Highcharts to v3.0.10).
- Fix: fullscreen map JS error.

### 1.3.1

- Fix: http/https JavaScript registration.
- Fix: full screen map CSS issue.

### 1.3.0

- Speed improvement.
- Rewritten JS classes.
- Added Temperature chart.
- Added HTML5 GPS position (follow the GPX with your mobile phone/tablet/PC).

### 1.2.6

- Speed improvement.

### 1.2.5

- Added Catalan translation (thanks to Edgar).
- Updated Spanish translation (thanks to Dani).
- Added different types of distance: Normal, Flat (don't consider altitude) and Climb distance.

### 1.2.4

- Added Bulgarian translation (thanks to Svilen Savov).
- Added possibility to hide the elevation chart.

### 1.2.2

- Smaller map type selector.
- New map: MapToolKit - Terrain.
- Fix: Google Maps exception for NextGen Gallery.

### 1.2.1

- Fix: NextGen Gallery 1.9 compatibility.

### 1.2.0

- NextGen Gallery 2 support.
- NextGen Gallery Pro support.

### 1.1.46

- Added meters/miles chart unit of measure.
- Added Russian translation (thanks to G.A.P).

### 1.1.45

- Added nautical miles as distance (thanks to Anders).

### 1.1.44

- Added chart zoom feature.
- Some small bug fixes.

### 1.1.43

- Added Portuguese (Brazilian) translation (thanks to Andre Ramos).
- New map: Open Cycle Map - Transport.
- New map: Open Cycle Map - Landscape.

### 1.1.42

- qTranslate compatible.

### 1.1.41

- Added Polish translation (thanks to Sebastian).
- Fix: Spanish translation.
- Minor JavaScript improvement.

### 1.1.40

- Improved Italian translation.
- Added grade chart (beta).

### 1.1.39

- Added French translation (thanks to Herve).
- Added Nautical Miles per Hour (Knots) unit of measure.

### 1.1.38

- Fix: Garmin GPX cadence and heart rate.
- Updated Turkish translation (thanks to Edip).
- Added Hungarian translation (thanks to Tami).

### 1.1.36

- Even Editor and Author users can upload their own GPX. Administrators can see all GPX files. Other users can see only their uploads.

### 1.1.35

- Fix: In the post list, sometime the maps were not displaying correctly.
- Various improvements for multi track GPX (thanks to GPSTracks.tv).
- Summary table is now available even without chart (thanks to David).

### 1.1.34

- 2 decimals for unit of measure min/km and min/mi.
- Translation file updated (a couple of phrases added).
- File list reverse order (from newer to older).
- NGGallery integration: division by zero fixed.

### 1.1.33

- Decimals reduced to 1 for unit of measure min/km and min/mi.
- Map zoom and center position works with waypoints-only files.
- Automatic scale works again (thanks to Markus).

### 1.1.32

- You can exclude cache (slower and not recommended).
- You can decide what to show in the summary table.
- German translation (thanks to Ali).

### 1.1.31

- Fixed fullscreen map image slideshow.

### 1.1.30

- Multi track GPX support.
- NextGen Gallery images positions derived from date. Adjust the date with the shortcode attribute `dtoffset`.
- If you set chart height (`gheight`) to 0, the graph is hidden.
- Fix: All images should work, independent from browser cache.

### 1.1.29

- Decimal separator works with all browsers.
- Minutes per mile and minutes per kilometer were wrong.

### 1.1.28

- Decimal and thousand separator derived from browser language.
- Added summary table (see settings): Total distance, Max elevation, Min elevation, Total climbing, Total descent, Average speed.
- Added 2 speed units of measure: minutes per mile and minutes per kilometer.

### 1.1.26

- Multilanguage implementation (front-end only).
- Map full screen mode.
- Added waypoint custom icon.

### 1.1.25

- Added possibility to download your GPX.

### 1.1.23

- Security fix, please update!

### 1.1.22

- Enable map zoom on scroll wheel (check settings).
- Test attributes in GET params.

### 1.1.21

- Google Maps images fixed (templates with bad CSS).
- Upgrade to Google Maps 3.9.

### 1.1.20

- Google Maps images fixed in Yoko theme.

### 1.1.19

- Include jQuery if needed.

### 1.1.17

- Remove zero values from cadence and heart rate charts.
- NextGen Gallery improvement.

### 1.1.16

- Cadence chart (where available).
- Minor bug fixes.

### 1.1.15

- Migration from Google Chart to Highcharts.
- Heart rate chart (where available).

### 1.1.14

- Added CSS to avoid map bars display issue.

### 1.1.13

- Added new types of maps: Open Street Map, Open Cycle Map, Hike & Bike.
- Fixed NextGen Gallery caching problem.

### 1.1.12

- NextGen Gallery display bug fixes.

---

## Upgrade Notice

See changelog above for details on each version.
