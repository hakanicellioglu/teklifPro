<?php
require __DIR__ . '/header.php';
require __DIR__ . '/components/page_header.php';
require __DIR__ . '/components/stat_card.php';

try {
    $stmt = $pdo->prepare('SELECT first_name FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $userName = $stmt->fetchColumn() ?: 'User';
} catch (Exception $e) {
    $userName = 'User';
}
$currentDate = date('d F Y');

try { $totalCustomers = $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn(); } catch (Exception $e) { $totalCustomers = 0; }
try { $activeQuotations = $pdo->query('SELECT COUNT(*) FROM generaloffers')->fetchColumn(); } catch (Exception $e) { $activeQuotations = 0; }
try {
    $recentStmt = $pdo->query('SELECT g.offer_date, CONCAT(c.first_name, " ", c.last_name) AS customer FROM generaloffers g LEFT JOIN customers c ON g.customer_id=c.id ORDER BY g.offer_date DESC LIMIT 5');
    $recentActivity = $recentStmt->fetchAll();
} catch (Exception $e) { $recentActivity = []; }
try {
    $statusStmt = $pdo->query('SELECT status, COUNT(*) AS count FROM generaloffers GROUP BY status');
    $statusCounts = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $quotationStatuses = [
        'Aktif' => (int)($statusCounts['active'] ?? $statusCounts['Aktif'] ?? $activeQuotations),
        'Beklemede' => (int)($statusCounts['pending'] ?? $statusCounts['Beklemede'] ?? 0),
        'Kapalı' => (int)($statusCounts['closed'] ?? $statusCounts['Kapalı'] ?? 0),
    ];
} catch (Exception $e) {
    $quotationStatuses = ['Aktif'=>$activeQuotations,'Beklemede'=>0,'Kapalı'=>0];
}
?>
<?php page_header('Gösterge Paneli'); ?>
<div class="mb-4">
  <div class="card shadow-sm">
    <div class="card-body">
      <h2 class="h5 mb-1">Hoş geldin, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></h2>
      <p class="text-muted mb-0"><?= $currentDate; ?></p>
    </div>
  </div>
</div>
<div class="row g-4 mb-4">
  <?php stat_card('bi-people-fill', 'Müşteri Sayısı', (string)(int)$totalCustomers); ?>
  <?php stat_card('bi-file-earmark-text', 'Aktif Teklifler', (string)(int)$activeQuotations); ?>
  <?php stat_card('bi-clock-history', 'Son Güncellemeler', (string)count($recentActivity)); ?>
</div>
<div class="row g-4">
  <div class="col-lg-8">
    <h2 class="h5 mb-3">Son Aktivite</h2>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead class="table-light sticky-top">
          <tr><th scope="col">Müşteri</th><th scope="col" class="text-end">Tarih</th></tr>
        </thead>
        <tbody>
        <?php if ($recentActivity): foreach ($recentActivity as $act): ?>
          <tr>
            <td><?= htmlspecialchars($act['customer'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="text-end"><time datetime="<?= htmlspecialchars($act['offer_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($act['offer_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></time></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="2" class="text-center text-muted">Kayıt bulunamadı.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-lg-4">
    <h2 class="h5 mb-3">Teklif Durumları</h2>
    <div class="card p-3 shadow-sm">
      <canvas id="quotationChart" style="min-height:300px"></canvas>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const qCtx = document.getElementById('quotationChart');
new Chart(qCtx,{type:'doughnut',data:{labels:<?= json_encode(array_keys($quotationStatuses)) ?>,datasets:[{data:<?= json_encode(array_values($quotationStatuses)) ?>,backgroundColor:['#0d6efd','#ffc107','#198754']}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
</script>
<?php require __DIR__ . '/footer.php'; ?>
