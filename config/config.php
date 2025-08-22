<?php
require __DIR__ . '/../bootstrap.php';

// Load environment variables from .env if present
if (file_exists(BASE_PATH . '/.env')) {
    $env = parse_ini_file(BASE_PATH . '/.env', false, INI_SCANNER_TYPED);
    foreach ($env as $k => $v) {
        $_ENV[$k] = $v;
        putenv("{$k}={$v}");
    }
}

// Database connection settings with environment fallback
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'teklifpro';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO(
        'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4',
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_turkish_ci');
} catch (PDOException $e) {
    throw $e;
}
