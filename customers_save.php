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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Yöntem desteklenmiyor.']);
    exit;
}
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'CSRF doğrulaması başarısız.']);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

// sanitize inputs
$fields = [
    'first_name' => htmlspecialchars(trim($_POST['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'last_name' => htmlspecialchars(trim($_POST['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'company_name' => htmlspecialchars(trim($_POST['company_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'email' => trim($_POST['email'] ?? ''),
    'phone' => htmlspecialchars(trim($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'tax_number' => htmlspecialchars(trim($_POST['tax_number'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'tax_office' => htmlspecialchars(trim($_POST['tax_office'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'address' => htmlspecialchars(trim($_POST['address'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'city' => htmlspecialchars(trim($_POST['city'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'country' => htmlspecialchars(trim($_POST['country'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'notes' => htmlspecialchars(trim($_POST['notes'] ?? ''), ENT_QUOTES, 'UTF-8'),
];

$errors = [];
if ($fields['first_name'] === '' && $fields['company_name'] === '') {
    $errors['first_name'] = 'İsim veya şirket adı gerekli.';
    $errors['company_name'] = 'İsim veya şirket adı gerekli.';
}
if ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Geçerli e-posta girin.';
}
if ($fields['phone'] !== '' && !preg_match('/^[0-9\s\+\-]{10,}$/', $fields['phone'])) {
    $errors['phone'] = 'Geçerli telefon girin.';
}

try {
    $columns = $pdo->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Sunucu hatası.']);
    exit;
}

// unique checks
if ($fields['email'] !== '' && in_array('email', $columns, true)) {
    $stmt = $pdo->prepare('SELECT id FROM customers WHERE email = :email AND id != :id');
    $stmt->execute(['email' => $fields['email'], 'id' => $id ?? 0]);
    if ($stmt->fetch()) {
        $errors['email'] = 'Bu e-posta zaten kayıtlı.';
    }
}
if ($fields['phone'] !== '' && in_array('phone', $columns, true)) {
    $stmt = $pdo->prepare('SELECT id FROM customers WHERE phone = :phone AND id != :id');
    $stmt->execute(['phone' => $fields['phone'], 'id' => $id ?? 0]);
    if ($stmt->fetch()) {
        $errors['phone'] = 'Bu telefon zaten kayıtlı.';
    }
}

if ($errors) {
    echo json_encode(['ok' => false, 'errors' => $errors]);
    exit;
}

$data = array_intersect_key($fields, array_flip($columns));

try {
    $pdo->beginTransaction();
    if ($id) {
        $set = [];
        foreach ($data as $col => $val) {
            $set[] = "$col = :$col";
        }
        $sql = 'UPDATE customers SET ' . implode(',', $set) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $data['id'] = $id;
        $stmt->execute($data);
    } else {
        $cols = array_keys($data);
        $sql = 'INSERT INTO customers (' . implode(',', $cols) . ') VALUES (' . implode(',', array_map(fn($c) => ':' . $c, $cols)) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        $id = (int)$pdo->lastInsertId();
    }
    $pdo->commit();
    echo json_encode(['ok' => true, 'id' => $id, 'message' => 'İşlem başarıyla kaydedildi.']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Kayıt başarısız.']);
}
