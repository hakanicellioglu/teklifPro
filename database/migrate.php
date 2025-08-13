<?php
require_once __DIR__ . '/../vendor/autoload.php';

$files = glob(__DIR__ . '/migrations/*.sql');
foreach ($files as $file) {
    $sql = file_get_contents($file);
    $pdo->exec($sql);
    echo 'Ran ' . basename($file) . PHP_EOL;
}
