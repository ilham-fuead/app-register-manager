<?php
require_once __DIR__ . '/config.php';

$name = 'ecaller-dev';
$pdo = db();

// Get current state
$stmt = $pdo->prepare('SELECT name, is_active FROM apps WHERE name = :name');
$stmt->execute(['name' => $name]);
$before = $stmt->fetch();
echo "Before: ";
print_r($before);

// Toggle to inactive (is_active = 0)
$stmt = $pdo->prepare('UPDATE apps SET is_active = 0, updated_at = NOW() WHERE name = :name');
$stmt->execute(['name' => $name]);

// Get after state
$stmt = $pdo->prepare('SELECT name, is_active FROM apps WHERE name = :name');
$stmt->execute(['name' => $name]);
$after = $stmt->fetch();
echo "After: ";
print_r($after);

// Now refresh from DB via scan endpoint
// The scan endpoint reads from filesystem, doesn't change is_active
// So the is_active should persist