<?php
require_once __DIR__ . '/config.php';
$pdo = db();
$stmt = $pdo->query('SELECT * FROM app_config');
$rows = $stmt->fetchAll();
print_r($rows);