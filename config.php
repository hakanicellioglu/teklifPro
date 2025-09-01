<?php
require __DIR__ . '/bootstrap.php';

// Database connection settings
const DB_HOST = 'localhost';
const DB_NAME = 'teklifpro';
const DB_USER = 'root';
const DB_PASS = '';
const DEFAULT_REACTIVATION_DAYS = 14;

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

// Schema adjustments for offer expiration
try {
    $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS valid_until DATE NULL AFTER validity_days");
} catch (Exception $e) {
    // Ignore if the table is missing
}

// Populate missing valid_until values
try {
    $pdo->exec(
        "UPDATE generaloffers
         SET valid_until = DATE_ADD(offer_date, INTERVAL validity_days DAY)
         WHERE valid_until IS NULL
           AND validity_days IS NOT NULL
           AND offer_date IS NOT NULL"
    );
} catch (Exception $e) {
}

// Automatically mark expired offers
try {
    $pdo->exec(
        "UPDATE generaloffers
         SET status = 'expired'
         WHERE valid_until IS NOT NULL
           AND valid_until < CURDATE()
           AND status NOT IN ('accepted', 'rejected', 'cancelled', 'expired')"
    );
} catch (Exception $e) {
    // Ignore errors (e.g., table does not exist)
}
