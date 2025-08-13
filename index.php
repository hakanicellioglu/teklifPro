<?php

declare(strict_types=1);

$pageTitle = "Ana Sayfa";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/utils.php';
require_once __DIR__ . '/helpers/auth.php';
require_login();

// İstatistikler
try {
    $totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
} catch (PDOException $e) {
    $totalCustomers = 0;
}
try {
    $totalCompanies = (int)$pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
} catch (PDOException $e) {
    $totalCompanies = 0;
}
try {
    $totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
} catch (PDOException $e) {
    $totalProducts = 0;
}
try {
    $totalQuotations = (int)$pdo->query("SELECT COUNT(*) FROM generaloffers")->fetchColumn();
} catch (PDOException $e) {
    $totalQuotations = 0;
}

require_once __DIR__ . '/templates/header.php';
?>

<h1 class="h3 mb-4">Yönetim Paneli</h1>

<div class="row g-4">
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="display-6 fw-bold text-primary"><?= e((string)$totalCustomers); ?></div>
                <div class="text-muted">Müşteri</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="display-6 fw-bold text-success"><?= e((string)$totalCompanies); ?></div>
                <div class="text-muted">Şirket</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="display-6 fw-bold text-warning"><?= e((string)$totalProducts); ?></div>
                <div class="text-muted">Ürün</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="display-6 fw-bold text-danger"><?= e((string)$totalQuotations); ?></div>
                <div class="text-muted">Teklif</div>
            </div>
        </div>
    </div>
</div>

<hr class="my-4">

<div class="row">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <strong>Son Teklifler</strong>
            </div>
            <div class="card-body">
                <?php
                $stmt = $pdo->query("
                SELECT go.id, go.quote_no, go.offer_date, go.status,
                       CONCAT(cus.first_name,' ',cus.last_name) AS customer_name
                FROM generaloffers go
                LEFT JOIN customers cus ON cus.id = go.customer_id
                ORDER BY go.offer_date DESC
                LIMIT 5
                ");
                $recentQuotes = $stmt->fetchAll();
                if ($recentQuotes):
                ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recentQuotes as $q): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= e($q['quote_no']); ?></strong>
                                    <div class="small text-muted"><?= e($q['customer_name'] ?? '—'); ?></div>
                                </div>
                                <span class="badge bg-secondary"><?= e($q['status'] ?? '—'); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-muted">Henüz teklif yok.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <strong>Son Eklenen Ürünler</strong>
            </div>
            <div class="card-body">
                <?php
                $stmt = $pdo->query("
                    SELECT code, name, created_at
                    FROM products
                    ORDER BY created_at DESC
                    LIMIT 5
                ");
                $recentProducts = $stmt->fetchAll();
                if ($recentProducts):
                ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recentProducts as $p): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= e($p['code']); ?></strong>
                                    <div class="small text-muted"><?= e($p['name']); ?></div>
                                </div>
                                <small class="text-muted"><?= e(date('d.m.Y', strtotime($p['created_at']))); ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-muted">Henüz ürün yok.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>