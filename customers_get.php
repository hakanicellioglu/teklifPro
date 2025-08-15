<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Yetkisiz.']);
    exit;
}
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Geçersiz ID.']);
    exit;
}
try {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$customer) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Müşteri bulunamadı.']);
        exit;
    }
    foreach ($customer as $k => $v) {
        if (is_string($v)) {
            $customer[$k] = htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        }
    }
    if (isset($customer['created_at'])) {
        $customer['registration_date'] = $customer['created_at'];
        unset($customer['created_at']);
    }
    echo json_encode(['ok' => true, 'customer' => $customer]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Sunucu hatası.']);
}
