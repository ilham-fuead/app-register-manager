<?php
/**
 * App Manager — Database Configuration
 * Laragon local MySQL: root@localhost, no password.
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'app_manager');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Return a PDO connection. Reuses across calls within a request.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/**
 * JSON response helper.
 */
function json_response(mixed $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Read and decode a JSON file. Returns null if file missing or invalid.
 */
function read_json_file(string $path): ?array
{
    if (!file_exists($path)) {
        return null;
    }
    $content = file_get_contents($path);
    if ($content === false) {
        return null;
    }
    return json_decode($content, true);
}

/**
 * App Manager Configuration helper.
 * Returns/updates settings stored in the `app_config` table.
 * Falls back to storage provided as a second argument.
 */
function db_config(string $key, mixed $value = null)
{
    $pdo = db();

    if ($value === null) {
        // Read from DB; fall back to storage array or return null
        $stmt = $pdo->prepare('SELECT `value` FROM app_config WHERE `key` = :key');
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();
        if ($row) {
            $decoded = json_decode($row['value'], true);
            return $decoded !== null ? $decoded : $row['value'];
        }
        return null;
    }

    // Write to DB (upsert)
    $stmt = $pdo->prepare('SELECT `id` FROM app_config WHERE `key` = :key');
    $stmt->execute(['key' => $key]);
    $exists = $stmt->fetch() !== false;

    if ($exists) {
        $stmt = $pdo->prepare('UPDATE app_config SET `value` = :value WHERE `key` = :key');
    } else {
        $stmt = $pdo->prepare('INSERT INTO app_config (`key`, `value`) VALUES (:key, :value)');
    }

    $encoded = is_array($value) ? json_encode($value) : (string)$value;
    $stmt->execute(['key' => $key, 'value' => $encoded]);
}
