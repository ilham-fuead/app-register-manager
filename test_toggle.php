<?php
require_once __DIR__ . '/config.php';

// Toggle ecaller-api to active
$appName = 'ecaller-api';
$newActive = 1; // 1 = active, 0 = inactive

$pdo = db();
$stmt = $pdo->prepare('UPDATE apps SET is_active = :active, updated_at = NOW() WHERE name = :name');
$stmt->execute(['active' => $newActive, 'name' => $appName]);

// Verify
$stmt = $pdo->prepare('SELECT name, is_active FROM apps WHERE name = :name');
$stmt->execute(['name' => $appName]);
$row = $stmt->fetch();
echo "After setting to active:\n";
print_r($row);