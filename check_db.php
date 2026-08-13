<?php
require_once __DIR__ . '/config.php';
$pdo = db();
$stmt = $pdo->query('SELECT * FROM app_config');
print_r($stmt->fetchAll());

// Test db_config read
echo "\nDirect db_config('root_path') result:\n";
var_dump(db_config('root_path'));