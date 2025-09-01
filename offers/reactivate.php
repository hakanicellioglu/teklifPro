<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../reactivate_service.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(400);
    echo json_encode(['error' => 'Geçersiz CSRF tokenı.']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) {
    http_response_code(403);
    echo json_encode(['error' => 'Yetki yok.']);
    exit;
}

// Extract ID from URL like /offers/{id}/reactivate
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id && isset($_SERVER['REQUEST_URI'])) {
    if (preg_match('#/offers/(\d+)/reactivate#', $_SERVER['REQUEST_URI'], $m)) {
        $id = (int)$m[1];
    }
}

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Geçersiz teklif ID.']);
    exit;
}

if (reactivate_offer($pdo, $id, $userId)) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Teklif yeniden aktifleştirilemedi.']);
}
