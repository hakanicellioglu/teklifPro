<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/config.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $email      = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    if ($first_name === '' || $last_name === '' || $username === '' || !$email || $password === '' || $confirm === '') {
        $errors[] = 'All fields are required and email must be valid.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if (!$errors) {
        // Check for existing username or email
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
        $stmt->execute(['username' => $username, 'email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Username or email already exists.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = 'INSERT INTO users (first_name, last_name, username, password, email, status) '
            . 'VALUES (:first_name, :last_name, :username, :password, :email, "active")';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'username'   => $username,
            'password'   => $hash,
            'email'      => $email,
        ]);
        header('Location: login?registered=1');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5" style="max-width: 600px;">
        <h2>Kayıt Ol</h2>
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" novalidate>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="first_name" class="form-label"><i class="bi bi-person me-1" aria-hidden="true"></i>İsim</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="last_name" class="form-label"><i class="bi bi-person me-1" aria-hidden="true"></i>Soyisim</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                </div>
            </div>
            <div class="mb-3">
                <label for="username" class="form-label"><i class="bi bi-person-badge me-1" aria-hidden="true"></i>Kullanıcı Adı</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label"><i class="bi bi-envelope me-1" aria-hidden="true"></i>Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label"><i class="bi bi-lock me-1" aria-hidden="true"></i>Parola</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="confirm_password" class="form-label"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i>Parolayı Onayla</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Kayıt Ol</button>
            <a href="login" class="btn btn-link"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Oturum Aç</a>
        </form>
    </div>
</body>

</html>