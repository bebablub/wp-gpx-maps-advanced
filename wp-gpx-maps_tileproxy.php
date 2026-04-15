<?php
/**
 * WP-GPX-Maps Tile Proxy
 * Proxies tile requests to Maptoolkit API with authentication headers
 */

// Prevent direct access (allow only from WordPress request context)
if (!defined('ABSPATH')) {
    // Check if we're being called from a WordPress request
    if (!isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], home_url()) === false) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

// Get API key from WordPress options
$rapidapi_key = get_option('wpgpxmaps_maptoolkit_apikey');

if (!$rapidapi_key || empty($rapidapi_key)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Maptoolkit API key not configured']);
    exit;
}

// Configuration
$rapidapi_host = 'maptoolkit.p.rapidapi.com';
$cache_dir = plugin_dir_path(__FILE__) . 'tilecache';
$cache_ttl = 60 * 60 * 24 * 7; // 7 days

// Basic input validation
$z = isset($_GET['z']) ? intval($_GET['z']) : null;
$x = isset($_GET['x']) ? intval($_GET['x']) : null;
$y = isset($_GET['y']) ? intval($_GET['y']) : null;
$style = isset($_GET['style']) ? preg_replace('/[^a-z0-9._-]/i', '', $_GET['style']) : 'terrainrgb.webp';

// Validate required parameters
if ($z === null || $x === null || $y === null) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing required parameters: z, x, y']);
    exit;
}

// Restrict zoom range to avoid abuse
if ($z < 0 || $z > 18) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid zoom level (must be 0-18)']);
    exit;
}

// Validate tile coordinates (reasonable bounds)
if ($x < 0 || $y < 0 || $x > pow(2, $z) || $y > pow(2, $z)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid tile coordinates']);
    exit;
}

// Ensure cache directory exists
if (!is_dir($cache_dir)) {
    if (!mkdir($cache_dir, 0750, true)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Failed to create cache directory']);
        exit;
    }
}

// Build cache key and path
$cache_key = sprintf('mt_%s_%d_%d_%d_%s', $rapidapi_host, $z, $x, $y, $style);
$cache_file = $cache_dir . '/' . md5($cache_key);

// Serve from cache if fresh
if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_ttl)) {
    // Determine content type based on file extension
    $ext = strtolower(pathinfo($style, PATHINFO_EXTENSION));
    $mime_types = [
        'webp' => 'image/webp',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg'
    ];
    $content_type = $mime_types[$ext] ?? 'image/webp';
    
    header('Content-Type: ' . $content_type);
    header('Cache-Control: public, max-age=' . $cache_ttl);
    readfile($cache_file);
    exit;
}

// Remote tile URL
$remote_url = sprintf('https://%s/tiles/1/%d/%d/%d/%s', $rapidapi_host, $z, $x, $y, $style);

// Use cURL to fetch remote tile with headers
$ch = curl_init($remote_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-rapidapi-host: ' . $rapidapi_host,
    'x-rapidapi-key: ' . $rapidapi_key
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$error = curl_error($ch);
curl_close($ch);

if ($http_code !== 200 || !$response) {
    http_response_code($http_code ?: 502);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Upstream tile fetch failed',
        'status' => $http_code,
        'curl_error' => $error
    ]);
    exit;
}

// Save to cache (atomic write)
$tmp = $cache_file . '.tmp';
if (file_put_contents($tmp, $response) !== false) {
    rename($tmp, $cache_file);
} else {
    // Cache save failed, but still return the tile
    @unlink($tmp);
}

// Determine content type
$ext = strtolower(pathinfo($style, PATHINFO_EXTENSION));
$mime_types = [
    'webp' => 'image/webp',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg'
];
$content_type = $mime_types[$ext] ?? $content_type ?: 'image/webp';

// Return tile to client
header('Content-Type: ' . $content_type);
header('Cache-Control: public, max-age=' . $cache_ttl);
echo $response;
exit;
