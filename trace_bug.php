<?php
require_once __DIR__ . '/config.php';
$pdo = db();

$name = 'ecaller-dev';

// Set to inactive
echo "Setting $name to inactive...\n";
$stmt = $pdo->prepare('UPDATE apps SET is_active = 0 WHERE name = :name');
$stmt->execute(['name' => $name]);

// Check state
$stmt = $pdo->prepare('SELECT name, is_active FROM apps WHERE name = :name');
$stmt->execute(['name' => $name]);
echo "After toggle off: ";
print_r($stmt->fetch());

// Now simulate what scan.php does - just read, no update
// The scan doesn't change is_active, so it should stay 0
echo "\nSimulating scan (no DB write for is_active)...\n";
echo "If is_active changed, bug is in scan.php\n";