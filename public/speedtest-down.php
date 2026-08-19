<?php
/**
 * VP92 Speed Test - Download Endpoint
 * Generates random data for client-side download speed measurement
 * No authentication required (public speed test)
 */

// Prevent output buffering
while (ob_get_level()) {
    ob_end_clean();
}

// Generate random data in chunks (memory efficient)
$chunkSize = 1048576; // 1MB chunks
$totalBytes = isset($_GET['bytes']) ? min((int)$_GET['bytes'], 52428800) : 10485760; // max 50MB, default 10MB

header('Content-Type: application/octet-stream');
header('Content-Length: ' . $totalBytes);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('X-Speedtest: download');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$remaining = $totalBytes;
while ($remaining > 0 && !connection_aborted()) {
    $size = min($chunkSize, $remaining);
    echo str_repeat("\0", $size); // null bytes are fast to generate
    $remaining -= $size;
    flush();
}
