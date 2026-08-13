<?php
require_once __DIR__ . '/../config.php';

$sql = file_get_contents(__DIR__ . '/001_add_config_table.sql');
if ($sql === false) {
    die("Could not read migration file\n");
}

$pdo = db();
$pdo->exec($sql);
echo "Migration executed successfully\n";