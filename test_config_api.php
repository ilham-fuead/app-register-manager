<?php
/**
 * Test script for config API endpoints
 * Run via: php test_config_api.php
 */

require_once __DIR__ . '/config.php';

echo "Testing config API...\n\n";

// Test GET
$_GET = ['key' => 'root_path'];
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
include __DIR__ . '/api/config.php';
$output = ob_get_clean();
$response = json_decode($output, true);

echo "GET /api/config.php?key=root_path\n";
echo "Status: " . http_response_code() . "\n";
echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

// Test PUT
$_GET = ['key' => 'root_path'];
$_SERVER['REQUEST_METHOD'] = 'PUT';
$putData = json_encode(['value' => 'C:/laragon/test']);

// Simulate PUT request body
$_PUT_DATA = file_get_contents('php://input');
$putStream = fopen('php://memory', 'r+');
fwrite($putStream, $putData);
rewind($putStream);

ob_start();
include __DIR__ . '/api/config.php';
$output = ob_get_clean();
$response = json_decode($output, true);

echo "PUT /api/config.php (value='C:/laragon/test')\n";
echo "Status: " . http_response_code() . "\n";
echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

// Restore
db_config('root_path', 'C:/laragon/www');
echo "Restored original value.\n";