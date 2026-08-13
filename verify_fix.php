<?php
require_once __DIR__ . '/config.php';
$pdo = db();

// Set ecaller-dev to inactive
$pdo->prepare('UPDATE apps SET is_active = 0 WHERE name = "ecaller-dev"')->execute();

// Verify in DB
$stmt = $pdo->prepare('SELECT name, is_active FROM apps WHERE name = "ecaller-dev"');
$stmt->execute();
$row = $stmt->fetch();
echo "DB after toggle: ";
print_r($row);

// Now simulate what apps.php does
$sql = "SELECT 
    a.id, a.name, a.path, a.notes, a.is_active, a.is_pinned, a.pinned_at,
    a.created_at, a.updated_at
FROM apps a
WHERE a.name = 'ecaller-dev'";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$app = $stmt->fetch();
echo "\napps.php query result: ";
print_r($app);