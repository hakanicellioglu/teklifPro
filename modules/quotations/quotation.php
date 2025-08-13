<?php
$pageTitle = "Teklif Listesi";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_login();

/**
 * Varsayılan şema varsayımları:
 * master_quotes(id, quote_no, company_id, customer_id, offer_date, total_amount, status)
 * companies(id, name)
 * customers(id, first_name, last_name)
 */

// Toplam kayıt
$total = (int)$pdo->query("SELECT COUNT(*) FROM master_quotes")->fetchColumn();

// Sayfalama
$pg = paginate($total, 20);

// Kayıtları çek
$sql = "
SELECT 
    mq.id,
    mq.quote_no,
    mq.offer_date,
    mq.total_amount,
    mq.status,
    c.name AS company_name,
    CONCAT(cus.first_name, ' ', cus.last_name) AS customer_name
FROM master_quotes mq
LEFT JOIN companies c   ON c.id   = mq.company_id
LEFT JOIN customers cus ON cus.id = mq.customer_id
ORDER BY mq.id DESC
LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit',  $pg['limit'],  PDO::PARAM_INT);
$stmt->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$quotes = $stmt->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Teklifler</h1>
    <a href="quotation_add.php" class="btn btn-primary">+ Yeni Teklif</a>
</div>

<table class="table table-bordered table-striped align-middle">
    <thead>
        <tr>
            <th width="80">ID</th>
            <th width="160">Teklif No</th>
            <th>Şirket</th>
            <th>Müşteri</th>
            <th width="140">Tarih</th>
            <th width="160">Toplam</th>
            <th width="140">Durum</th>
            <th width="220">İşlemler</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($quotes as $q): ?>
        <tr>
            <td><?= e($q['id']); ?></td>
            <td><?= e($q['quote_no'] ?? '—'); ?></td>
            <td><?= e($q['company_name'] ?? '—'); ?></td>
            <td><?= e($q['customer_name'] ?? '—'); ?></td>
            <td><?= e($q['offer_date'] ? date('d.m.Y', strtotime($q['offer_date'])) : '—'); ?></td>
            <td>
                <?php 
                    $amt = $q['total_amount'];
                    echo ($amt !== null && $amt !== '') 
                        ? e(number_format((float)$amt, 2, ',', '.')) . ' ₺'
                        : '—';
                ?>
            </td>
            <td>
                <span class="badge bg-<?= ($q['status'] ?? '') === 'approved' ? 'success' : (($q['status'] ?? '') === 'rejected' ? 'danger' : 'secondary'); ?>">
                    <?= e($q['status'] ?? 'draft'); ?>
                </span>
            </td>
            <td>
                <a href="quotation_view.php?id=<?= e($q['id']); ?>" class="btn btn-sm btn-info">Görüntüle</a>
                <a href="quotation_edit.php?id=<?= e($q['id']); ?>" class="btn btn-sm btn-warning">Düzenle</a>
                <a href="quotation_delete.php?id=<?= e($q['id']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bu teklifi silmek istediğinize emin misiniz?');">Sil</a>
            </td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($quotes)): ?>
        <tr>
            <td colspan="8" class="text-center text-muted">Kayıt bulunamadı.</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php if ($pg['pages'] > 1): ?>
<nav aria-label="Sayfalama">
    <ul class="pagination">
        <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
            <li class="page-item <?= $i === $pg['page'] ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i; ?>"><?= $i; ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
