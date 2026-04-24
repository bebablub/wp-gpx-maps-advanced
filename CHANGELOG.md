# Changelog - WP-GPX-Maps

All notable changes to the WP-GPX-Maps plugin are documented in this file.

## [3.0.0] - 2026-04-24

### Map Engine Migration Finalized

This release finalizes the map engine migration to MapLibre while keeping Google Maps available as a legacy fallback.

#### Added

- New setting: `wpgpxmaps_map_engine` (`google` or `maplibre`)
- Conditional asset loading for selected engine:
  - Google Maps API loaded only in `google` mode
  - Self-hosted MapLibre assets loaded only in `maplibre` mode
- Shortcode runtime now passes selected engine into frontend initialization

#### Finalized

- MapLibre is now the default engine for fresh installs and unspecified engine values

#### MapLibre Features

- Raster map rendering with OSM/Thunderforest/MapTiler tile selection
- GPX track rendering with bounds fit
- Start/end/waypoint markers
- Extreme markers (max altitude / max speed)
- Current position marker and browser geolocation updates
- Chart rendering in MapLibre mode
- Bidirectional map/chart hover synchronization
- NGG image overlay markers with hide/show image control
- Back-to-center control
- Map type selector control

#### Backward Compatibility

- Google Maps path remains available as legacy fallback
- Existing shortcode/data output format remains compatible
- Existing options continue to work

---

## [2.1.1] - Previous Release

[Historical changelog available in git history]
