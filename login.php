<?php
declare(strict_types=1);

$pageTitle = "Giriş Yap";
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/utils.php';
require_once __DIR__ . '/helpers/auth.php';

// Zaten girişliyse ana sayfaya gönder
if (is_logged_in()) {
    redirect('/index.php');
}

// Güvenli yönlendirme hedefi (opsiyonel)
$next = $_GET['next'] ?? ($_POST['next'] ?? '/index.php');
// Yalnızca aynı site içi, mutlak olmayan yolları kabul et
if (!is_string($next) || str_contains($next, '://')) {
    $next = '/index.php';
}

$errors = [];
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Güvenlik doğrulaması başarısız.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Geçerli bir e-posta giriniz.';
        }
        if ($password === '') {
            $errors[] = 'Şifre boş olamaz.';
        }

        if (!$errors) {
            if (login($pdo, $email, $password)) {
                // Başarılı giriş
                redirect($next ?: '/index.php');
            } else {
                $errors[] = 'E-posta veya şifre hatalı.';
            }
        }
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header">
                <strong>Giriş Yap</strong>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <?= csrf_field(); ?>
                    <input type="hidden" name="next" value="<?= e($next); ?>">

                    <div class="mb-3">
                        <label for="email" class="form-label">E-posta</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= e($email); ?>" autocomplete="username" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Şifre</label>
                        <input type="password" class="form-control" id="password" name="password"
                               autocomplete="current-password" required>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Giriş Yap</button>
                        <a href="/index.php" class="btn btn-outline-secondary">Vazgeç</a>
                    </div>
                </form>
            </div>
        </div>
        <!-- <div class="text-center mt-3 small text-muted">
            Hesabın yok mu? <a href="/register.php">Kayıt ol</a>
        </div> -->
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
