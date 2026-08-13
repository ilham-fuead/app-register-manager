<?php
/**
 * App Manager — Configuration API
 *
 * Handles app root path and other settings storage.
 *
 * GET  /api/config.php           — Get all config keys
 * GET  /api/config.php?key=root_path  — Get specific key
 * PUT  /api/config.php?key=root_path  — Update a key
 */

require_once __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$key = $_GET['key'] ?? null;

switch ($method) {
    case 'GET':
        handle_get();
        break;
    case 'PUT':
        handle_put();
        break;
    default:
        json_response(['error' => 'Method not allowed'], 405);
}

// ————————————————————
// GET — Retrieve config
// ————————————————————

function handle_get(): void
{
    global $key;

    if ($key) {
        // Single key
        $value = db_config($key);
        if ($value !== null) {
            json_response([
                'key' => $key,
                'value' => $value,
            ]);
            return;
        }
        json_response(['error' => 'Config key not found'], 404);
    }

    // All keys - need to fetch all and decode each
    $pdo = db();
    $stmt = $pdo->query('SELECT `key`, `value` FROM app_config ORDER BY `key`');
    $rows = $stmt->fetchAll();
    $config = [];
    foreach ($rows as $row) {
        $decoded = json_decode($row['value'], true);
        $config[$row['key']] = $decoded !== null ? $decoded : $row['value'];
    }
    json_response($config);
}

// ————————————————————
// PUT — Update config
// ————————————————————

function handle_put(): void
{
    global $key;
    if (!$key) {
        json_response(['error' => 'Query parameter ?key= is required'], 400);
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body || !isset($body['value'])) {
        json_response(['error' => 'JSON body with "value" field required'], 400);
    }

    $value = $body['value'];
    db_config($key, $value);

    // Return fresh value
    $row = db_config($key); // reads from DB
    json_response([
        'key' => $key,
        'value' => $row,
        'message' => 'Config updated',
    ], 200);
}