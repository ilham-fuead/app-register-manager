<?php
require_once __DIR__ . '/config.php';
$pdo = db();
$stmt = $pdo->query('DESCRIBE apps');
while ($row = $stmt->fetch()) {
    print_r($row);
}