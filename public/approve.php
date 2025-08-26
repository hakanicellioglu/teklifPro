<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

function h(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$token = $_GET['token'] ?? '';
if ($token === '') {
    http_response_code(400);
    exit('Geçersiz bağlantı.');
}

$stmt = $pdo->prepare('SELECT g.*, c.first_name, c.last_name, c.company_name AS customer_company FROM generaloffers g LEFT JOIN customers c ON g.customer_id = c.id WHERE g.approval_token = :t LIMIT 1');
$stmt->execute([':t' => $token]);
$offer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$offer) {
    http_response_code(404);
    exit('Teklif bulunamadı.');
}

// Fetch related product rows
$guillotines = [];
$slidings = [];
$items = [];
$totalAmount = 0.0;

try {
    $gStmt = $pdo->prepare('SELECT system_type, width, height, quantity, total_amount FROM guillotinesystems WHERE general_offer_id = :id');
    $gStmt->execute([':id' => $offer['id']]);
    $guillotines = $gStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // ignore
}

try {
    $sStmt = $pdo->prepare('SELECT system_type, width, height, quantity, total_amount FROM slidingsystems WHERE general_offer_id = :id');
    $sStmt->execute([':id' => $offer['id']]);
    $slidings = $sStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // ignore
}

foreach ($guillotines as $g) {
    $items[] = [
        'system'   => $g['system_type'],
        'width'    => $g['width'],
        'height'   => $g['height'],
        'quantity' => $g['quantity'],
        'amount'   => $g['total_amount'],
    ];
    $totalAmount += (float)$g['total_amount'];
}
foreach ($slidings as $s) {
    $items[] = [
        'system'   => $s['system_type'],
        'width'    => $s['width'],
        'height'   => $s['height'],
        'quantity' => $s['quantity'],
        'amount'   => $s['total_amount'],
    ];
    $totalAmount += (float)$s['total_amount'];
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $offer['status'] === 'pending') {
    $decision = $_POST['decision'] ?? '';
    if (in_array($decision, ['accepted', 'rejected'], true)) {
        $upd = $pdo->prepare('UPDATE generaloffers SET status = :st, approved_at = NOW(), approval_token = NULL WHERE id = :id AND status = \'pending\'');
        $upd->execute([':st' => $decision, ':id' => $offer['id']]);
        $offer['status'] = $decision;
        $offer['approved_at'] = date('Y-m-d H:i:s');
        $message = $decision === 'accepted' ? 'Teklif onaylandı.' : 'Teklif reddedildi.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teklif Onayı</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .table-sticky thead th { position: sticky; top: 0; background: var(--bs-body-bg); z-index: 1; box-shadow: inset 0 -1px 0 var(--bs-border-color); }
        .amount-col { min-width: 140px; }
        @media print {
            .btn, form, .toast-container, .alert-info { display: none !important; }
            .card { box-shadow: none !important; border-color: #000 !important; background: #fff !important; }
            .table { border: 1px solid #000 !important; }
            .table th, .table td { border: 1px solid #000 !important; }
        }
        .btn:disabled { cursor: not-allowed; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4 py-md-5">
    <?php
    $statusAlertMap = [
        'pending'  => ['class' => 'alert-warning', 'text' => 'Bu teklif henüz onaylanmamıştır.'],
        'accepted' => ['class' => 'alert-success', 'text' => 'Teklif onaylandı.'],
        'rejected' => ['class' => 'alert-danger', 'text' => 'Teklif reddedildi.'],
    ];
    $statusAlert = $statusAlertMap[$offer['status']] ?? null;
    if ($message): ?>
        <div class="alert alert-info" role="alert"><?= h($message) ?></div>
    <?php endif; ?>
    <?php if ($statusAlert): ?>
        <div class="alert <?= $statusAlert['class'] ?> mb-4" role="alert"><?= h($statusAlert['text']) ?></div>
    <?php endif; ?>

    <header class="mb-4">
        <h1 class="h4 mb-1">Teklif Onayı</h1>
        <h2 class="h6 text-muted">Müşteri onay/ret işlemi – token bazlı erişim</h2>
    </header>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="h5 mb-0">Teklif Özeti</span>
            <?php
            $badgeMap = [
                'pending'  => 'bg-warning-subtle text-warning-emphasis',
                'accepted' => 'bg-success-subtle text-success-emphasis',
                'rejected' => 'bg-danger-subtle text-danger-emphasis',
            ];
            $badgeClass = $badgeMap[$offer['status']] ?? 'bg-secondary-subtle text-secondary-emphasis';
            ?>
            <span class="badge <?= $badgeClass ?> text-uppercase"><?= h($offer['status']) ?></span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <span class="text-secondary small">Teklif No</span>
                    <div class="fw-semibold text-body-emphasis"><?= h($offer['quote_no'] ?? (string)$offer['id']) ?></div>
                    <span class="text-secondary small">Müşteri Adı Soyadı</span>
                    <div class="fw-semibold text-body-emphasis"><?= h(trim(($offer['first_name'] ?? '') . ' ' . ($offer['last_name'] ?? ''))) ?></div>
                    <?php if (!empty($offer['customer_company'])): ?>
                        <span class="text-secondary small">Firma</span>
                        <div class="fw-semibold text-body-emphasis"><?= h($offer['customer_company']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <span class="text-secondary small">Tarih</span>
                    <div class="fw-semibold text-body-emphasis"><?= h($offer['offer_date'] ?? '') ?></div>
                    <span class="text-secondary small">Durum</span>
                    <div class="fw-semibold text-body-emphasis"><?= h($offer['status']) ?></div>
                    <?php if (!empty($offer['approved_at'])): ?>
                        <span class="text-secondary small">Onay Tarihi</span>
                        <div class="fw-semibold text-body-emphasis"><?= h($offer['approved_at']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <span class="h5 mb-0">Kalemler</span>
        </div>
        <?php if ($items): ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0 table-sticky">
                <thead>
                    <tr>
                        <th>Sistem Tipi</th>
                        <th>Ölçüler</th>
                        <th>Adet</th>
                        <th class="text-end amount-col">Tutar</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td class="text-truncate" title="<?= h($it['system']) ?>" style="max-width: 200px;" data-bs-toggle="tooltip"><?= h($it['system']) ?></td>
                        <td><?= h($it['width'] . ' x ' . $it['height']) ?></td>
                        <td><?= h((string)$it['quantity']) ?></td>
                        <td class="text-end amount-col"><?= h(tr_money((float)$it['amount'])) ?> ₺</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Toplam</th>
                        <th class="text-end amount-col fw-semibold fs-6"><?= h(tr_money((float)$totalAmount)) ?> ₺</th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php else: ?>
        <div class="card-body text-center text-secondary">
            <i class="bi bi-inboxes display-6 mb-2" aria-hidden="true"></i>
            <p class="mb-0">Kalem bulunamadı.</p>
        </div>
        <?php endif; ?>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <span class="h5 mb-0">İşlem</span>
        </div>
        <div class="card-body">
            <?php if ($offer['status'] === 'pending'): ?>
                <form method="post" class="d-flex flex-wrap gap-2">
                    <input type="hidden" name="token" value="<?= h($token) ?>">
                    <button type="submit" name="decision" value="accepted" class="btn btn-success d-flex align-items-center gap-1"><i class="bi bi-check-circle" aria-hidden="true"></i><span>Onayla</span></button>
                    <button type="submit" name="decision" value="rejected" class="btn btn-danger d-flex align-items-center gap-1"><i class="bi bi-x-circle" aria-hidden="true"></i><span>Reddet</span></button>
                </form>
                <div class="form-text mt-2">Bu işlem kalıcıdır.</div>
            <?php else: ?>
                <div class="alert alert-info mb-3" role="alert">Teklif <?= h($offer['status'] === 'accepted' ? 'onaylanmıştır' : 'reddedilmiştir') ?>.</div>
                <form method="post" class="d-flex flex-wrap gap-2">
                    <input type="hidden" name="token" value="<?= h($token) ?>">
                    <button type="submit" name="decision" value="accepted" class="btn btn-success d-flex align-items-center gap-1" disabled><i class="bi bi-check-circle" aria-hidden="true"></i><span>Onayla</span></button>
                    <button type="submit" name="decision" value="rejected" class="btn btn-danger d-flex align-items-center gap-1" disabled><i class="bi bi-x-circle" aria-hidden="true"></i><span>Reddet</span></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

</div>

<div class="toast-container position-fixed top-0 end-0 p-3">
    <?php if ($message): ?>
    <div class="toast align-items-center text-bg-info border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body"><?= h($message) ?></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Kapat"></button>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    document.querySelectorAll('form button[type="submit"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.disabled) return;
            btn.disabled = true;
            if (!btn.querySelector('.spinner-border')) {
                var sp = document.createElement('span');
                sp.className = 'spinner-border spinner-border-sm ms-2';
                sp.setAttribute('aria-hidden', 'true');
                btn.appendChild(sp);
            }
        });
    });

    var toastEl = document.querySelector('.toast');
    if (toastEl) {
        var toast = new bootstrap.Toast(toastEl, { delay: 3000 });
        toast.show();
    }
});
</script>
</body>
</html>
