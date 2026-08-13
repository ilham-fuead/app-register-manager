<?php
require_once __DIR__ . '/config.php';
$pdo = db();
$stmt = $pdo->query('SHOW COLUMNS FROM apps WHERE Field = "is_active"');
$row = $stmt->fetch();
print_r($row);