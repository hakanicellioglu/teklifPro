<?php
declare(strict_types=1);

http_response_code(404);
$pageTitle = "Sayfa Bulunamadı (404)";

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/utils.php';
require_once __DIR__ . '/helpers/auth.php';

require_once __DIR__ . '/templates/header.php';
?>
<div class="text-center my-5">
    <h1 class="display-6 text-warning">404</h1>
    <p class="lead">Üzgünüz, aradığınız sayfa bulunamadı.</p>
    <a href="/index.php" class="btn btn-primary">Ana Sayfa</a>
</div>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
