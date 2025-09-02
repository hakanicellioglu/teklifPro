<?php
require __DIR__ . '/bootstrap.php';

// Database connection settings
const DB_HOST = 'localhost';
const DB_NAME = 'teklifpro';
const DB_USER = 'root';
const DB_PASS = '';
const DEFAULT_REACTIVATION_DAYS = 14;

require __DIR__ . '/db.php';

try {
    $db = DbAdapter::create(DB_HOST, DB_NAME, DB_USER, DB_PASS);
    $pdo = $db;
} catch (Exception $e) {
    error_log($e->getMessage());
    die('Database connection error');
}

// Schema adjustments for offer expiration
try {
    $stmt = $db->prepare('SHOW COLUMNS FROM generaloffers LIKE ?');
    $stmt->execute(['valid_until']);
    if (!$stmt->fetch()) {
        $db->exec('ALTER TABLE generaloffers ADD COLUMN valid_until DATE NULL AFTER validity_days');
    }
} catch (Exception $e) {
    error_log($e->getMessage());
}

// Populate missing valid_until values
try {
    $db->exec(
        "UPDATE generaloffers
         SET valid_until = DATE_ADD(offer_date, INTERVAL validity_days DAY)
         WHERE valid_until IS NULL
           AND validity_days IS NOT NULL
           AND offer_date IS NOT NULL"
    );
} catch (Exception $e) {
    error_log($e->getMessage());
}

// Automatically mark expired offers
try {
    $db->exec(
        "UPDATE generaloffers
         SET status = 'expired'
         WHERE valid_until IS NOT NULL
           AND valid_until < CURDATE()
           AND status NOT IN ('accepted', 'rejected', 'cancelled', 'expired')"
    );
} catch (Exception $e) {
    error_log($e->getMessage());
}
