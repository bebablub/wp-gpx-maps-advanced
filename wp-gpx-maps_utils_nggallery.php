<?php
/**
 * Image helpers: NextGEN gallery images and post attachments, placed on the track via EXIF GPS data
 * or by matching the photo time to the track time.
 *
 * @package WP-GPX-Maps
 */

if (!defined('ABSPATH')) {
    exit;
}

function wpgpxmaps_ngg_active(): bool
{
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    return is_plugin_active('nextgen-gallery/nggallery.php') && class_exists('nggdb');
}

/**
 * Finds the track position for a timestamp. Returns array( 'lat' => float, 'lon' => float ) or null.
 *
 * @param int   $image_time Unix timestamp of the photo (already corrected by the offset).
 * @param array $dt         Track timestamps (null at segment breaks).
 * @param array $map        Track points array( lat, lon ) (null at segment breaks), same length as $dt.
 */
function wpgpxmaps_find_item_coordinate(int $image_time, array $dt, array $map): ?array
{
    $count = count($dt);
    for ($i = 1; $i < $count; $i++) {
        $previous = $dt[$i - 1];
        $current = $dt[$i];
        if (null === $previous || null === $current || empty($map[$i])) {
            continue;
        }
        if ($image_time >= $previous && $image_time <= $current) {
            return array(
                'lat' => $map[$i][0],
                'lon' => $map[$i][1],
            );
        }
    }
    return null;
}

/**
 * Converts an EXIF GPS coordinate (degrees, minutes, seconds as "a/b" fractions) to decimal degrees.
 */
function wpgpxmaps_exif_gps(mixed $coordinate, mixed $hemisphere): float
{
    if (!is_array($coordinate)) {
        return 0.0;
    }
    $degrees = isset($coordinate[0]) ? wpgpxmaps_gps_fraction($coordinate[0]) : 0.0;
    $minutes = isset($coordinate[1]) ? wpgpxmaps_gps_fraction($coordinate[1]) : 0.0;
    $seconds = isset($coordinate[2]) ? wpgpxmaps_gps_fraction($coordinate[2]) : 0.0;
    $flip = ('W' === $hemisphere || 'S' === $hemisphere) ? -1 : 1;
    return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
}

function wpgpxmaps_gps_fraction(mixed $part): float
{
    $parts = explode('/', (string) $part);
    if (1 === count($parts)) {
        return (float) $parts[0];
    }
    $denominator = (float) $parts[1];
    return 0.0 === $denominator ? (float) $parts[0] : (float) $parts[0] / $denominator;
}

/**
 * Reads the GPS position (lat, lon) and capture time from an image file's EXIF data.
 *
 * @return array{lat: float, lon: float, time: int}
 */
function wpgpxmaps_read_exif(string $file): array
{
    $result = array('lat' => 0.0, 'lon' => 0.0, 'time' => 0);
    if (!is_callable('exif_read_data') || !is_readable($file)) {
        return $result;
    }
    if (!in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), array('jpg', 'jpeg'), true)) {
        return $result;
    }
    try {
        $exif = @exif_read_data($file); // phpcs:ignore WordPress.PHP.NoSilencedErrors
    } catch (Throwable $e) {
        $exif = false;
    }
    if (!is_array($exif)) {
        return $result;
    }
    if (isset($exif['GPSLongitude'], $exif['GPSLongitudeRef'], $exif['GPSLatitude'], $exif['GPSLatitudeRef'])) {
        $result['lon'] = wpgpxmaps_exif_gps($exif['GPSLongitude'], $exif['GPSLongitudeRef']);
        $result['lat'] = wpgpxmaps_exif_gps($exif['GPSLatitude'], $exif['GPSLatitudeRef']);
    }
    $taken = $exif['DateTimeOriginal'] ?? $exif['DateTime'] ?? '';
    if (is_string($taken) && '' !== $taken) {
        $timestamp = strtotime($taken);
        $result['time'] = false === $timestamp ? 0 : $timestamp;
    }
    return $result;
}

/**
 * Images attached to a post, placed on the track.
 *
 * @return array<int, array{data: string, lat: float, lon: float}>
 */
function wpgpxmaps_get_attached_images(int $post_id, array $dt, array $map, int $dtoffset): array
{
    $result = array();
    if ($post_id <= 0) {
        return $result;
    }

    try {
        $attachments = get_children(
            array(
                'post_parent' => $post_id,
                'post_type' => 'attachment',
                'numberposts' => 200,
                'post_status' => 'inherit',
                'post_mime_type' => 'image',
                'order' => 'ASC',
                'orderby' => 'menu_order',
            )
        );

        foreach ($attachments as $attachment_id => $attachment) {
            $link = wp_get_attachment_link((int) $attachment_id, array(105, 105));
            if ('' === $link) {
                continue;
            }

            $exif = wpgpxmaps_read_exif((string) get_attached_file((int) $attachment_id));
            if (0.0 !== $exif['lat'] || 0.0 !== $exif['lon']) {
                $result[] = array(
                    'data' => $link,
                    'lat' => $exif['lat'],
                    'lon' => $exif['lon'],
                );
                continue;
            }

            $time = $exif['time'];
            if ($time <= 0) {
                $meta = wp_get_attachment_metadata((int) $attachment_id);
                $time = (int) ($meta['image_meta']['created_timestamp'] ?? 0);
            }
            if ($time <= 0 && !empty($attachment->post_date_gmt) && '0000-00-00 00:00:00' !== $attachment->post_date_gmt) {
                $time = (int) strtotime($attachment->post_date_gmt . ' UTC');
            }
            if ($time <= 0) {
                continue;
            }

            $position = wpgpxmaps_find_item_coordinate($time + $dtoffset, $dt, $map);
            if (null !== $position) {
                $result[] = array(
                    'data' => $link,
                    'lat' => $position['lat'],
                    'lon' => $position['lon'],
                );
            }
        }
    } catch (Throwable $e) {
        return $result;
    }

    return $result;
}

/**
 * NextGEN Gallery images, placed on the track.
 *
 * @param string $galleries Comma separated gallery IDs.
 * @param string $images    Comma separated image IDs.
 * @return array<int, array{data: string, lat: float, lon: float}>
 */
function wpgpxmaps_get_nggallery_images(string $galleries, string $images, array $dt, array $map, int $dtoffset): array
{
    $result = array();
    if (!wpgpxmaps_ngg_active()) {
        return $result;
    }

    $gallery_ids = array_filter(array_map('intval', explode(',', $galleries)));
    $image_ids = array_filter(array_map('intval', explode(',', $images)));

    try {
        $pictures = array();
        foreach ($gallery_ids as $gallery_id) {
            $pictures = array_merge($pictures, (array) nggdb::get_gallery($gallery_id));
        }
        foreach ($image_ids as $image_id) {
            $pictures[] = nggdb::find_image($image_id);
        }

        foreach ($pictures as $picture) {
            if (!is_object($picture) || empty($picture->thumbHTML)) {
                continue;
            }
            $item = array(
                'data' => str_replace("\n", '', (string) $picture->thumbHTML),
                'lat' => 0.0,
                'lon' => 0.0,
            );

            $exif = wpgpxmaps_read_exif((string) ($picture->imagePath ?? ''));
            if (0.0 !== $exif['lat'] || 0.0 !== $exif['lon']) {
                $item['lat'] = $exif['lat'];
                $item['lon'] = $exif['lon'];
                $result[] = $item;
                continue;
            }

            $time = isset($picture->imagedate) ? (int) strtotime((string) $picture->imagedate) : 0;
            if ($time > 0) {
                $position = wpgpxmaps_find_item_coordinate($time + $dtoffset, $dt, $map);
                if (null !== $position) {
                    $item['lat'] = $position['lat'];
                    $item['lon'] = $position['lon'];
                    $result[] = $item;
                }
            }
        }

        // NextGEN Gallery 2.x / Pro: render once so the lightbox scripts and gallery id are available.
        if ($result && class_exists('C_Component_Registry')) {
            $renderer = C_Component_Registry::get_instance()->get_utility('I_Displayed_Gallery_Renderer');
            $params = array(
                'gallery_ids' => $galleries,
                'image_ids' => $images,
                'display_type' => defined('NEXTGEN_GALLERY_BASIC_THUMBNAILS') ? NEXTGEN_GALLERY_BASIC_THUMBNAILS : 'photocrati-nextgen_basic_thumbnails',
                'images_per_page' => 999,
            );
            $inner = '';
            $dummy = (string) $renderer->display_images($params, $inner);
            if (preg_match('/data-nplmodal-gallery-id=["\'](.*?)["\']/', $dummy, $m) && '' !== $m[1]) {
                foreach ($result as $index => $entry) {
                    $result[$index]['data'] = str_replace('%PRO_LIGHTBOX_GALLERY_ID%', esc_attr($m[1]), $entry['data']);
                }
            }
        }
    } catch (Throwable $e) {
        return $result;
    }

    return $result;
}
