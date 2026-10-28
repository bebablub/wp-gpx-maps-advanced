<?php
/**
 * Admin "Tracks" tab: upload, delete, list and clear the cache.
 *
 * @package WP-GPX-Maps
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handles the POST actions of the tracks tab and returns notices to print as [type, message].
 *
 * @return array<int, array{0: string, 1: string}>
 */
function wpgpxmaps_admin_tracks_actions(string $gpx_dir): array
{
	$notices = array();
	if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) {
		return $notices;
	}
	if (!isset($_POST['wpgpxmaps_tracks_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wpgpxmaps_tracks_nonce'])), 'wpgpxmaps_tracks')) {
		return $notices;
	}
	if (!current_user_can('publish_posts') && !current_user_can('manage_options')) {
		return $notices;
	}

	if (isset($_POST['delete']) && is_string($_POST['delete'])) {
		$name = sanitize_file_name(wp_unslash($_POST['delete']));
		$real_dir = realpath($gpx_dir);
		$real_file = '' !== $name ? realpath($gpx_dir . '/' . $name) : false;
		if (
			false !== $real_dir && false !== $real_file && is_file($real_file)
			&& 'gpx' === strtolower(pathinfo($real_file, PATHINFO_EXTENSION))
			&& str_starts_with(str_replace('\\', '/', $real_file), rtrim(str_replace('\\', '/', $real_dir), '/') . '/')
		) {
			wp_delete_file($real_file);
			/* translators: %s: file name */
			$notices[] = array('success', sprintf(__('File %s has been deleted.', 'wp-gpx-maps'), $name));
		} else {
			$notices[] = array('error', __('The file could not be deleted.', 'wp-gpx-maps'));
		}
	}

	if (isset($_POST['clearcache']) && current_user_can('manage_options')) {
		wpgpxmaps_clear_cache();
		$notices[] = array('success', __('The cache is now empty.', 'wp-gpx-maps'));
	}

	if (isset($_POST['upload'])) {
		$file = isset($_FILES['uploadedfile']) && is_array($_FILES['uploadedfile']) ? $_FILES['uploadedfile'] : array();
		$notices[] = wpgpxmaps_admin_handle_upload($file, $gpx_dir);
	}

	return $notices;
}

/**
 * Validates and stores an uploaded GPX file.
 *
 * @param array<string, mixed> $file One entry of $_FILES.
 * @return array{0: string, 1: string}
 */
function wpgpxmaps_admin_handle_upload(array $file, string $gpx_dir): array
{
	$error = array('error', __('There was an error uploading the file, please try again.', 'wp-gpx-maps'));
	if (empty($file['tmp_name']) || !is_string($file['tmp_name']) || UPLOAD_ERR_OK !== (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)) {
		return $error;
	}
	if (!is_uploaded_file($file['tmp_name'])) {
		return $error;
	}

	$name = sanitize_file_name(basename((string) wp_unslash($file['name'] ?? '')));
	if ('' === $name || 'gpx' !== strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
		return array('error', __('File not supported. Only .gpx files can be uploaded.', 'wp-gpx-maps'));
	}
	if ((int) ($file['size'] ?? 0) > wpgpxmaps_max_gpx_size()) {
		return array('error', __('The file is too big.', 'wp-gpx-maps'));
	}

	$head = (string) file_get_contents($file['tmp_name'], false, null, 0, 4096);
	if (!str_contains($head, '<gpx') || str_contains($head, '<!DOCTYPE') || str_contains($head, '<!ENTITY')) {
		return array('error', __('File not supported. The file is not a valid GPX file.', 'wp-gpx-maps'));
	}

	if (!is_dir($gpx_dir) && !wp_mkdir_p($gpx_dir)) {
		return $error;
	}
	if (!move_uploaded_file($file['tmp_name'], $gpx_dir . '/' . $name)) {
		return $error;
	}
	/* translators: %s: file name */
	return array('success', sprintf(__('File %s has been uploaded.', 'wp-gpx-maps'), $name));
}

function wpgpxmaps_admin_tracks_tab(string $gpx_dir): void
{
	$notices = '' !== $gpx_dir ? wpgpxmaps_admin_tracks_actions($gpx_dir) : array();
	foreach ($notices as list($type, $message)) {
		printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($type), esc_html($message));
	}

	if ('' === $gpx_dir) {
		return;
	}

	$relative = wpgpxmaps_relative_path($gpx_dir);

	if (is_dir($gpx_dir) && is_writable($gpx_dir)) {
		echo '<div class="tablenav top" style="height:auto">';
		echo '<form method="post" enctype="multipart/form-data" style="float:left;margin:5px 20px 0 0">';
		wp_nonce_field('wpgpxmaps_tracks', 'wpgpxmaps_tracks_nonce');
		echo '<label>' . esc_html__('Choose a file to upload:', 'wp-gpx-maps') . ' <input type="file" name="uploadedfile" accept=".gpx,application/gpx+xml,application/xml,text/xml" required /></label> ';
		echo '<input type="submit" name="upload" class="button" value="' . esc_attr__('Upload', 'wp-gpx-maps') . '" />';
		echo '</form>';
		if (current_user_can('manage_options')) {
			echo '<form method="post" style="float:left;margin:5px 20px 0 0">';
			wp_nonce_field('wpgpxmaps_tracks', 'wpgpxmaps_tracks_nonce');
			echo '<input type="submit" name="clearcache" class="button" value="' . esc_attr__('Clear Cache', 'wp-gpx-maps') . '" />';
			echo '</form>';
		}
		echo '</div><div class="clear"></div>';
	}

	$files = array();
	if (is_dir($gpx_dir) && is_readable($gpx_dir)) {
		foreach ((array) scandir($gpx_dir) as $entry) {
			$path = $gpx_dir . '/' . $entry;
			if ('gpx' === strtolower(pathinfo($entry, PATHINFO_EXTENSION)) && is_file($path)) {
				$files[$entry] = (int) filemtime($path);
			}
		}
		arsort($files);
	}

	echo '<table class="wp-list-table widefat striped"><thead><tr>';
	echo '<th scope="col">' . esc_html__('File', 'wp-gpx-maps') . '</th>';
	echo '<th scope="col">' . esc_html__('Last modified', 'wp-gpx-maps') . '</th>';
	echo '<th scope="col">' . esc_html__('File size', 'wp-gpx-maps') . '</th>';
	echo '<th scope="col">' . esc_html__('Shortcode', 'wp-gpx-maps') . '</th>';
	echo '<th scope="col"></th></tr></thead><tbody>';

	if (!$files) {
		echo '<tr><td colspan="5">' . esc_html__('No tracks uploaded yet.', 'wp-gpx-maps') . '</td></tr>';
	}

	foreach ($files as $entry => $mtime) {
		$path = $gpx_dir . '/' . $entry;
		$url = wpgpxmaps_local_path_to_url((string) realpath($path));
		echo '<tr>';
		echo '<td><strong>' . esc_html($entry) . '</strong></td>';
		echo '<td>' . esc_html(wp_date('Y-m-d H:i:s', $mtime)) . '</td>';
		echo '<td>' . esc_html(size_format((int) filesize($path))) . '</td>';
		echo '<td><code>' . esc_html('[sgpx gpx="' . $relative . $entry . '"]') . '</code></td>';
		echo '<td>';
		if ('' !== $url) {
			echo '<a href="' . esc_url($url) . '" download>' . esc_html__('Download', 'wp-gpx-maps') . '</a> | ';
		}
		echo '<form method="post" style="display:inline">';
		wp_nonce_field('wpgpxmaps_tracks', 'wpgpxmaps_tracks_nonce');
		echo '<input type="hidden" name="delete" value="' . esc_attr($entry) . '" />';
		/* translators: %s: file name */
		$confirm = sprintf(__('Delete this file: %s?', 'wp-gpx-maps'), $entry);
		echo '<button type="submit" class="button-link button-link-delete" onclick="return confirm(' . esc_attr(wp_json_encode($confirm)) . ');">' . esc_html__('Delete', 'wp-gpx-maps') . '</button>';
		echo '</form></td></tr>';
	}

	echo '</tbody></table>';
}
