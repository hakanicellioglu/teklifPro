<?php
require __DIR__ . '/bootstrap.php';

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
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_turkish_ci');
} catch (PDOException $e) {
    throw $e;
}

// Automatically mark expired offers
try {
    $pdo->exec(
        "UPDATE generaloffers
         SET status = 'expired'
         WHERE validity_days IS NOT NULL
           AND offer_date IS NOT NULL
           AND status NOT IN ('accepted', 'rejected', 'cancelled', 'expired')
           AND DATE_ADD(offer_date, INTERVAL validity_days DAY) < CURDATE()"
    );
} catch (Exception $e) {
    // Ignore errors (e.g., table does not exist)
}
