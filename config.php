<?php
// config/config.php
declare(strict_types=1);

// Display errors (DEV) - PROD'da kapatın
ini_set('display_errors', '1'); 
error_reporting(E_ALL);

// Timezone
date_default_timezone_set('Europe/Istanbul');

// Session (güvenli cookie ayarları)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    ]);
    session_start();
}

// Database connection settings
const DB_HOST = 'localhost';
const DB_NAME = 'teklifpro';
const DB_USER = 'root';
const DB_PASS = '';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    // Türkçe sıralama
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_turkish_ci");
} catch (PDOException $e) {
    // PROD: hata mesajını göstermeyin, loglayın
    die('Database connection failed.');
}
