<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo)) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/../mark_shared_service.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json');
}
$GLOBALS['response_code'] = 200;

$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    $GLOBALS['response_code'] = 400;
    @http_response_code(400);
    echo json_encode(['error' => 'Geçersiz CSRF tokenı.']);
    if (PHP_SAPI !== 'cli') {
        exit;
    }
    return;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) {
    $GLOBALS['response_code'] = 403;
    @http_response_code(403);
    echo json_encode(['error' => 'Yetki yok.']);
    if (PHP_SAPI !== 'cli') {
        exit;
    }
    return;
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id && isset($_SERVER['REQUEST_URI'])) {
    if (preg_match('#/offers/(\d+)/mark-shared#', $_SERVER['REQUEST_URI'], $m)) {
        $id = (int)$m[1];
    }
}
if (!$id) {
    $GLOBALS['response_code'] = 400;
    @http_response_code(400);
    echo json_encode(['error' => 'Geçersiz teklif ID.']);
    if (PHP_SAPI !== 'cli') {
        exit;
    }
    return;
}

$stmt = $pdo->prepare('SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :id');
$stmt->execute([':id' => $userId]);
$role = $stmt->fetchColumn() ?: 'user';

$perm = $pdo->prepare('SELECT g.id FROM generaloffers g LEFT JOIN company c ON g.company_id = c.id WHERE g.id = :id AND (:admin OR g.company_id IS NULL OR c.user_id = :uid)');
$perm->execute([':id' => $id, ':uid' => $userId, ':admin' => $role === 'admin' ? 1 : 0]);
if (!$perm->fetchColumn()) {
    $GLOBALS['response_code'] = 403;
    @http_response_code(403);
    echo json_encode(['error' => 'Yetki yok.']);
    if (PHP_SAPI !== 'cli') {
        exit;
    }
    return;
}

$result = mark_offer_shared($pdo, $id, $userId);
if ($result === false) {
    $GLOBALS['response_code'] = 400;
    @http_response_code(400);
    echo json_encode(['error' => 'Paylaşım kaydı oluşturulamadı.']);
    if (PHP_SAPI !== 'cli') {
        exit;
    }
    return;
}

echo json_encode(['success' => true, 'status' => $result['status'], 'share_count' => $result['share_count']]);
