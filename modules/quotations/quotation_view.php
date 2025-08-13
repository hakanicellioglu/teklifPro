<?php
$pageTitle = "Teklif Detayı";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('quotation.php');
}

/**
 * Varsayılan şema varsayımları:
 * master_quotes(id, quote_no, company_id, customer_id, offer_date, status, total_amount, notes)
 * companies(id, name, address, phone, email)
 * customers(id, first_name, last_name, company, email, phone)
 * quote_items(id, quote_id, product_id, description, quantity, unit, unit_price)
 * products(id, code, name, unit)
 */

// Teklif üst bilgisi
$qStmt = $pdo->prepare("
    SELECT mq.id, mq.quote_no, mq.offer_date, mq.status, mq.total_amount, mq.notes,
           c.id AS company_id, c.name AS company_name, c.address AS company_address, c.phone AS company_phone, c.email AS company_email,
           cus.id AS customer_id, CONCAT(cus.first_name,' ',cus.last_name) AS customer_name, cus.company AS customer_company, cus.email AS customer_email, cus.phone AS customer_phone
    FROM master_quotes mq
    LEFT JOIN companies c   ON c.id   = mq.company_id
    LEFT JOIN customers cus ON cus.id = mq.customer_id
    WHERE mq.id = :id
    LIMIT 1
");
$qStmt->execute([':id' => $id]);
$quote = $qStmt->fetch();
if (!$quote) {
    redirect('quotation.php');
}

// Kalemler
$iStmt = $pdo->prepare("
    SELECT qi.id, qi.product_id, qi.description, qi.quantity, qi.unit, qi.unit_price,
           p.code AS product_code, p.name AS product_name
    FROM quote_items qi
    LEFT JOIN products p ON p.id = qi.product_id
    WHERE qi.quote_id = :qid
    ORDER BY qi.id ASC
");
try {
    $iStmt->execute([':qid' => $quote['id']]);
    $items = $iStmt->fetchAll();
} catch (PDOException $e) {
    // Eğer quote_items yoksa, boş kabul et
    $items = [];
}

// Hesaplamalar
$subTotal = 0.0;
foreach ($items as $it) {
    $lineTotal = ((float)($it['unit_price'] ?? 0)) * ((float)($it['quantity'] ?? 0));
    $subTotal += $lineTotal;
}
$grandTotal = $quote['total_amount'] !== null ? (float)$quote['total_amount'] : $subTotal;

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Teklif #<?= e($quote['quote_no'] ?? ('Q-' . $quote['id'])); ?></h1>
    <div class="d-flex gap-2">
        <a href="quotation_edit.php?id=<?= e($quote['id']); ?>" class="btn btn-warning">Düzenle</a>
        <a href="/pdf_generator.php?id=<?= e($quote['id']); ?>" class="btn btn-outline-secondary" target="_blank">PDF Oluştur</a>
        <a href="quotation.php" class="btn btn-secondary">Listeye Dön</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">Şirket</div>
            <div class="card-body small">
                <div class="fw-semibold mb-1"><?= e($quote['company_name'] ?? '—'); ?></div>
                <div><?= nl2br(e($quote['company_address'] ?? '')); ?></div>
                <div class="mt-2"><?= e($quote['company_phone'] ?? ''); ?></div>
                <div><?= e($quote['company_email'] ?? ''); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">Müşteri</div>
            <div class="card-body small">
                <div class="fw-semibold mb-1"><?= e($quote['customer_name'] ?? '—'); ?></div>
                <div><?= e($quote['customer_company'] ?? ''); ?></div>
                <div class="mt-2"><?= e($quote['customer_phone'] ?? ''); ?></div>
                <div><?= e($quote['customer_email'] ?? ''); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">Teklif Bilgileri</div>
            <div class="card-body small">
                <div><span class="text-muted">Teklif No:</span> <?= e($quote['quote_no'] ?? '—'); ?></div>
                <div><span class="text-muted">Tarih:</span> <?= e($quote['offer_date'] ? date('d.m.Y', strtotime($quote['offer_date'])) : '—'); ?></div>
                <div>
                    <span class="text-muted">Durum:</span>
                    <span class="badge bg-<?= ($quote['status'] ?? '') === 'approved' ? 'success' : (($quote['status'] ?? '') === 'rejected' ? 'danger' : 'secondary'); ?>">
                        <?= e($quote['status'] ?? 'draft'); ?>
                    </span>
                </div>
                <div><span class="text-muted">Toplam:</span>
                    <?= e(number_format($grandTotal, 2, ',', '.')); ?> ₺
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Kalemler</span>
        <a href="quotation_edit.php?id=<?= e($quote['id']); ?>" class="btn btn-sm btn-outline-primary">Kalemleri Düzenle</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="80">#</th>
                        <th width="160">Kod</th>
                        <th>Ürün / Açıklama</th>
                        <th width="120" class="text-end">Miktar</th>
                        <th width="100">Birim</th>
                        <th width="140" class="text-end">Birim Fiyat</th>
                        <th width="160" class="text-end">Tutar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $idx => $it): 
                            $qty = (float)($it['quantity'] ?? 0);
                            $price = (float)($it['unit_price'] ?? 0);
                            $line = $qty * $price;
                        ?>
                        <tr>
                            <td><?= e($idx + 1); ?></td>
                            <td><?= e($it['product_code'] ?? ''); ?></td>
                            <td>
                                <div class="fw-semibold"><?= e($it['product_name'] ?? ''); ?></div>
                                <?php if (!empty($it['description'])): ?>
                                    <div class="text-muted small"><?= nl2br(e($it['description'])); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= e(number_format($qty, 2, ',', '.')); ?></td>
                            <td><?= e($it['unit'] ?? ''); ?></td>
                            <td class="text-end"><?= e(number_format($price, 2, ',', '.')); ?> ₺</td>
                            <td class="text-end"><?= e(number_format($line, 2, ',', '.')); ?> ₺</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted">Kalem bulunamadı.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="6" class="text-end">Ara Toplam</th>
                        <th class="text-end"><?= e(number_format($subTotal, 2, ',', '.')); ?> ₺</th>
                    </tr>
                    <?php if ($quote['total_amount'] !== null && (float)$quote['total_amount'] !== (float)$subTotal): ?>
                    <tr>
                        <th colspan="6" class="text-end">Genel Toplam</th>
                        <th class="text-end"><?= e(number_format((float)$quote['total_amount'], 2, ',', '.')); ?> ₺</th>
                    </tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($quote['notes'])): ?>
<div class="card mt-4">
    <div class="card-header">Notlar</div>
    <div class="card-body">
        <div class="small"><?= nl2br(e($quote['notes'])); ?></div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
