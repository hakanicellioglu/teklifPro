<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/config.php';

$errors = [];
$hasCompanyColumn = false;
try {
    $hasCompanyColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'company_id'")->rowCount() > 0;
} catch (Exception $e) {
    $hasCompanyColumn = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'Both fields are required.';
    }

    if (!$errors) {
        $sql = 'SELECT id, password' . ($hasCompanyColumn ? ', company_id' : '') . ' FROM users WHERE username = :username AND status = "active"';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            if ($hasCompanyColumn && isset($user['company_id'])) {
                $_SESSION['company_id'] = (int)$user['company_id'];
            }
            header('Location: dashboard');
            exit;
        } else {
            $errors[] = 'Invalid username or password.';
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5" style="max-width: 500px;">
        <h2>Oturum Aç</h2>
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" novalidate>
            <div class="mb-3">
                <label for="username" class="form-label"><i class="bi bi-person me-1" aria-hidden="true"></i>Kullanıcı Adı</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label"><i class="bi bi-lock me-1" aria-hidden="true"></i>Parola</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Giriş Yap</button>
            <a href="register" class="btn btn-link"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Kayıt Ol</a>
        </form>
    </div>
</body>

</html>