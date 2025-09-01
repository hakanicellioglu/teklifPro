<?php
declare(strict_types=1);

try {
    require_once __DIR__ . '/../config.php';
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Veritabanı bağlantı hatası.']);
    exit;
}
require_once __DIR__ . '/../share_service.php';

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

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id && isset($_SERVER['REQUEST_URI'])) {
    if (preg_match('#/offers/(\d+)/share#', $_SERVER['REQUEST_URI'], $m)) {
        $id = (int)$m[1];
    }
}

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Geçersiz teklif ID.']);
    exit;
}

if (share_offer($pdo, $id)) {
    echo json_encode(['success' => true]);
    exit;
}

// determine if offer does not exist
try {
    $chk = $pdo->prepare('SELECT 1 FROM generaloffers WHERE id = :id');
    $chk->execute([':id' => $id]);
    $exists = $chk->fetchColumn() !== false;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Teklif güncellenemedi.']);
    exit;
}

if (!$exists) {
    http_response_code(404);
    echo json_encode(['error' => 'Teklif bulunamadı.']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Teklif güncellenemedi.']);
}
