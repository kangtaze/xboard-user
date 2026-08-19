<?php
/**
 * VP92 Speed Test - Upload Endpoint
 * Accepts POST data and measures size for upload speed calculation
 * No authentication required (public speed test)
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-redefine');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Read the raw POST body
$rawInput = file_get_contents('php://input');
$bytesReceived = strlen($rawInput);

echo json_encode([
    'bytes' => $bytesReceived,
    'status' => 'ok'
]);
