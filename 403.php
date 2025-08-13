<?php
declare(strict_types=1);

http_response_code(403);
$pageTitle = "Yetkisiz Erişim (403)";

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/utils.php';
require_once __DIR__ . '/helpers/auth.php';

require_once __DIR__ . '/templates/header.php';
?>
<div class="text-center my-5">
    <h1 class="display-6 text-danger">403</h1>
    <p class="lead">Bu sayfaya erişim yetkiniz bulunmuyor.</p>
    <?php if (!is_logged_in()): ?>
        <p class="text-muted">Devam etmek için lütfen giriş yapın.</p>
        <a href="/login.php" class="btn btn-primary">Giriş Yap</a>
    <?php else: ?>
        <p class="text-muted">Gerekli rol veya izinlere sahip değilsiniz.</p>
        <a href="/index.php" class="btn btn-secondary">Ana Sayfa</a>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
