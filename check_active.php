<?php
require_once __DIR__ . '/config.php';
$pdo = db();
$stmt = $pdo->query("SELECT name, is_active FROM apps WHERE name LIKE '%ecaller%'");
$rows = $stmt->fetchAll();
print_r($rows);