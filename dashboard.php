<?php
require __DIR__ . '/header.php';

// Fetch summary metrics
try {
    $totalCustomers = $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
} catch (Exception $e) {
    $totalCustomers = 0;
}

try {
    // Assuming every record in generaloffers is an active quotation
    $activeQuotations = $pdo->query('SELECT COUNT(*) FROM generaloffers')->fetchColumn();
} catch (Exception $e) {
    $activeQuotations = 0;
}

try {
    $recentStmt = $pdo->query(
        'SELECT g.offer_date, CONCAT(c.first_name, " ", c.last_name) AS customer '
            . 'FROM generaloffers g LEFT JOIN customers c ON g.customer_id = c.id '
            . 'ORDER BY g.offer_date DESC LIMIT 5'
    );
    $recentActivity = $recentStmt->fetchAll();
} catch (Exception $e) {
    $recentActivity = [];
}
?>
<div class="container mt-4">
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card text-bg-primary h-100">
                <div class="card-body text-center">
                    <i class="bi bi-people-fill fs-1" aria-hidden="true"></i>
                    <h5 class="card-title mt-2">Total Customers</h5>
                    <p class="card-text fs-2"><?= (int)$totalCustomers ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-bg-success h-100">
                <div class="card-body text-center">
                    <i class="bi bi-file-earmark-text fs-1" aria-hidden="true"></i>
                    <h5 class="card-title mt-2">Active Quotations</h5>
                    <p class="card-text fs-2"><?= (int)$activeQuotations ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-bg-secondary h-100">
                <div class="card-body text-center">
                    <i class="bi bi-clock-history fs-1" aria-hidden="true"></i>
                    <h5 class="card-title mt-2">Recent Activity</h5>
                    <p class="card-text fs-6">Last <?= count($recentActivity) ?> records</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-3 col-sm-6">
            <a href="customers" class="text-decoration-none">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-people fs-1 mb-2" aria-hidden="true"></i>
                        <h5 class="card-title">Customers</h5>
                    </div>
                </div>
            </a>
        </div>
        <?php if ($role === 'admin'): ?>
            <div class="col-md-3 col-sm-6">
                <a href="products" class="text-decoration-none">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-box-seam fs-1 mb-2" aria-hidden="true"></i>
                            <h5 class="card-title">Products</h5>
                        </div>
                    </div>
                </a>
            </div>
        <?php endif; ?>
        <div class="col-md-3 col-sm-6">
            <a href="quotations" class="text-decoration-none">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-file-earmark-text fs-1 mb-2" aria-hidden="true"></i>
                        <h5 class="card-title">Quotations</h5>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-sm-6">
            <a href="settings" class="text-decoration-none">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-gear fs-1 mb-2" aria-hidden="true"></i>
                        <h5 class="card-title">Settings</h5>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <h4>Recent Activity</h4>
            <ul class="list-group">
                <?php if ($recentActivity): ?>
                    <?php foreach ($recentActivity as $activity): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><?= htmlspecialchars($activity['customer'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="text-muted small"><?= htmlspecialchars($activity['offer_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="list-group-item text-muted">No recent activity found.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
</body>

</html>