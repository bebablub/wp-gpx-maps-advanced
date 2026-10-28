<?php
/**
 * Removes the plugin options and cached tracks when the plugin is deleted.
 * Uploaded GPX files in uploads/gpx are kept.
 *
 * @package WP-GPX-Maps
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$option_names = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('wpgpxmaps_') . '%'));
foreach ((array) $option_names as $option_name) {
    delete_option($option_name);
}

$uploads = wp_upload_dir(null, false);
$cache = rtrim((string) ($uploads['basedir'] ?? ''), '/\\') . '/gpx/~cache';
if ('' !== ($uploads['basedir'] ?? '') && is_dir($cache)) {
    foreach ((array) scandir($cache) as $name) {
        $file = $cache . '/' . $name;
        if ('.' !== $name && '..' !== $name && is_file($file)) {
            wp_delete_file($file);
        }
    }
    @rmdir($cache); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}
