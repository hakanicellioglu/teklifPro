<?php
require __DIR__ . '/header.php';
// Fetch logged-in user's name
try {
    $stmt = $pdo->prepare('SELECT first_name FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $userName = $stmt->fetchColumn() ?: 'User';
} catch (Exception $e) {
    $userName = 'User';
}

$currentDate = date('l, F j, Y');

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

// Quotation status data for chart
try {
    $statusStmt = $pdo->query('SELECT status, COUNT(*) AS count FROM generaloffers GROUP BY status');
    $statusCounts = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $quotationStatuses = [
        'Active' => (int)($statusCounts['active'] ?? $statusCounts['Active'] ?? $activeQuotations),
        'Pending' => (int)($statusCounts['pending'] ?? $statusCounts['Pending'] ?? 0),
        'Closed' => (int)($statusCounts['closed'] ?? $statusCounts['Closed'] ?? 0),
    ];
} catch (Exception $e) {
    $quotationStatuses = [
        'Active' => (int)$activeQuotations,
        'Pending' => 0,
        'Closed' => 0,
    ];
}

?>
<div class="container py-4">
    <!-- Welcome Card -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4 class="card-title mb-1">Hoş geldin, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>!</h4>
            <p class="card-text text-muted mb-0"><?= $currentDate; ?></p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3 mb-4 text-center">
        <div class="col-6 col-md-3">
            <a href="quotations/create" class="btn btn-primary w-100 h-100 d-flex flex-column justify-content-center align-items-center py-3">
                <i class="bi bi-plus-circle fs-2 mb-1" aria-hidden="true"></i>
                <span>Teklif Oluştur</span>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="customers/add" class="btn btn-outline-primary w-100 h-100 d-flex flex-column justify-content-center align-items-center py-3">
                <i class="bi bi-person-plus fs-2 mb-1" aria-hidden="true"></i>
                <span>Müşteri Ekle</span>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="products" class="btn btn-outline-primary w-100 h-100 d-flex flex-column justify-content-center align-items-center py-3">
                <i class="bi bi-box-seam fs-2 mb-1" aria-hidden="true"></i>
                <span>Ürünleri Listele</span>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="settings" class="btn btn-outline-primary w-100 h-100 d-flex flex-column justify-content-center align-items-center py-3">
                <i class="bi bi-gear fs-2 mb-1" aria-hidden="true"></i>
                <span>Ayarlar</span>
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card h-100 shadow-sm text-center">
                <div class="card-body">
                    <i class="bi bi-people-fill text-primary fs-1" aria-hidden="true"></i>
                    <h5 class="card-title mt-2">Toplam Müşteri</h5>
                    <p class="display-6 mb-0"><?= (int)$totalCustomers ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm text-center">
                <div class="card-body">
                    <i class="bi bi-file-earmark-text text-success fs-1" aria-hidden="true"></i>
                    <h5 class="card-title mt-2">Aktif Teklifler</h5>
                    <p class="display-6 mb-0"><?= (int)$activeQuotations ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm text-center">
                <div class="card-body">
                    <i class="bi bi-clock-history text-secondary fs-1" aria-hidden="true"></i>
                    <h5 class="card-title mt-2">Son Güncellemeler</h5>
                    <p class="fs-5 mb-0">Last <?= count($recentActivity) ?> records</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity and Chart -->
    <div class="row g-4">
        <div class="col-lg-8">
            <h4 class="mb-3">Son Güncellemeler</h4>
            <ul class="list-group">
                <?php if ($recentActivity): ?>
                    <?php foreach ($recentActivity as $activity): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?= htmlspecialchars($activity['customer'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="text-muted small"><?= htmlspecialchars($activity['offer_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="list-group-item text-muted">Son güncelleme bulunamadı.</li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="col-lg-4">
            <h4 class="mb-3">Teklif Durumları</h4>
            <div class="card shadow-sm p-3">
                <canvas id="quotationChart" style="min-height:300px"></canvas>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    const qCtx = document.getElementById('quotationChart');
    new Chart(qCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($quotationStatuses)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($quotationStatuses)) ?>,
                backgroundColor: ['#0d6efd', '#ffc107', '#198754'],
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
</script>
</body>

</html>