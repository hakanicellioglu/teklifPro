<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/../helpers/auth.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'TeklifPro'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/index.php">TeklifPro</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="/modules/customers/customer.php">Müşteriler</a></li>
                    <li class="nav-item"><a class="nav-link" href="/modules/products/product.php">Ürünler</a></li>
                    <li class="nav-item"><a class="nav-link" href="/modules/quotations/quotation.php">Teklifler</a></li>
                    <li class="nav-item"><a class="nav-link" href="/modules/categories/categories.php">Kategoriler</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <?php if (is_logged_in()): ?>
                        <li class="nav-item"><span class="navbar-text me-2">Merhaba, <?= e(current_user()['first_name']); ?></span></li>
                        <li class="nav-item"><a class="nav-link" href="/logout.php">Çıkış</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="/login.php">Giriş</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
