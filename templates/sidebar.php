<?php
require_once __DIR__ . '/../helpers/auth.php';
?>
<div class="d-flex flex-column flex-shrink-0 p-3 bg-light border-end" style="width: 250px; min-height: 100vh;">
    <a href="/index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-decoration-none">
        <span class="fs-5 fw-bold">TeklifPro</span>
    </a>
    <hr>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="/modules/customers/customer.php" class="nav-link link-dark">Müşteriler</a>
        </li>
        <li>
            <a href="/modules/products/product.php" class="nav-link link-dark">Ürünler</a>
        </li>
        <li>
            <a href="/modules/quotations/quotation.php" class="nav-link link-dark">Teklifler</a>
        </li>
        <li>
            <a href="/modules/categories/categories.php" class="nav-link link-dark">Kategoriler</a>
        </li>
        <?php if (has_role('admin')): ?>
        <li>
            <a href="/settings.php" class="nav-link link-dark">Ayarlar</a>
        </li>
        <?php endif; ?>
    </ul>
    <hr>
    <?php if (is_logged_in()): ?>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <strong><?= e(current_user()['first_name'] ?? ''); ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                <li><a class="dropdown-item" href="/profile.php">Profil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="/logout.php">Çıkış</a></li>
            </ul>
        </div>
    <?php endif; ?>
</div>
