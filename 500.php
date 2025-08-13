<?php
declare(strict_types=1);

http_response_code(500);
$pageTitle = "Sunucu Hatası (500)";

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/utils.php';
require_once __DIR__ . '/helpers/auth.php';

require_once __DIR__ . '/templates/header.php';
?>
<div class="text-center my-5">
    <h1 class="display-6 text-danger">500</h1>
    <p class="lead">Bir şeyler ters gitti. Lütfen daha sonra tekrar deneyiniz.</p>
    <a href="/index.php" class="btn btn-primary">Ana Sayfa</a>
</div>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
