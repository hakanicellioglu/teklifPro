<?php
declare(strict_types=1);

$pageTitle = "Kayıt Ol";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/utils.php';
require_once __DIR__ . '/helpers/auth.php';

const ALLOW_SELF_REGISTER = true;
if (!ALLOW_SELF_REGISTER) { http_response_code(403); exit('Yeni kullanıcı kaydı kapalı.'); }
if (is_logged_in()) { redirect('/index.php'); }

$errors = [];
$first = trim($_POST['first_name'] ?? '');
$last  = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Güvenlik doğrulaması başarısız.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        if ($first === '') $errors[] = 'Ad zorunludur.';
        if ($last === '')  $errors[] = 'Soyad zorunludur.';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Geçerli bir e-posta giriniz.';
        if ($password === '' || strlen($password) < 6) $errors[] = 'Şifre en az 6 karakter olmalıdır.';
        if ($password !== $confirm) $errors[] = 'Şifre ve doğrulama uyuşmuyor.';

        // E-posta benzersiz mi?
        if (!$errors) {
            $chk = $pdo->prepare('SELECT 1 FROM users WHERE email = :e LIMIT 1');
            $chk->execute([':e' => $email]);
            if ($chk->fetchColumn()) {
                $errors[] = 'Bu e-posta ile kayıt mevcut.';
            }
        }

        if (!$errors) {
            // "user" rolünün id'sini al (yoksa 2 varsayılanı kullan)
            $roleId = 2;
            try {
                $rs = $pdo->prepare('SELECT id FROM roles WHERE name = :n LIMIT 1');
                $rs->execute([':n' => 'user']);
                $rid = $rs->fetchColumn();
                if ($rid) $roleId = (int)$rid;
            } catch (Throwable $e) { /* yoksa varsayılan 2 kullanılacak */ }

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins = $pdo->prepare('
                INSERT INTO users (first_name, last_name, email, password, status, role_id, created_at)
                VALUES (:f, :l, :e, :p, :s, :rid, NOW())
            ');
            $ins->execute([
                ':f'   => $first,
                ':l'   => $last,
                ':e'   => $email,
                ':p'   => $hash,
                ':s'   => 'active',
                ':rid' => $roleId,
            ]);

            $_SESSION['flash'] = 'Kayıt başarılı. Lütfen giriş yapınız.';
            redirect('/login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title><?= e($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header"><strong>Kaydol</strong></div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err); ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <?= csrf_field(); ?>
                    <div class="mb-3">
                        <label for="first_name" class="form-label">Ad</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?= e($first); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="last_name" class="form-label">Soyad</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?= e($last); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-posta</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($email); ?>" autocomplete="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Şifre</label>
                        <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Şifre (Tekrar)</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">Kayıt Ol</button>
                        <a href="/login.php" class="btn btn-outline-secondary">Zaten hesabım var</a>
                    </div>
                </form>
            </div>
        </div>
        <div class="small text-muted mt-3">
            Kayıt sonrası rolünüz <strong>user</strong> olarak atanır. Gerekirse yönetici tarafından güncellenebilir.
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
