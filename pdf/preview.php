<?php
declare(strict_types=1);
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id']) || !filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT)) {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/../config.php';

function h(?string $v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$userId = (int)$_SESSION['user_id'];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Geçersiz istek');
}

$quote = null;
$guillotines = [];
$slidings = [];
$error = null;

try {
    $stmt = $pdo->prepare('SELECT g.*, c.first_name, c.last_name, c.company AS customer_company, co.name AS company_name FROM generaloffers g LEFT JOIN customers c ON g.customer_id = c.id LEFT JOIN company co ON g.company_id = co.id WHERE g.id = :id');
    $stmt->execute([':id' => $id]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$quote) {
        http_response_code(404);
        exit('Teklif bulunamadı');
    }

    $gStmt = $pdo->prepare('SELECT system_type, width, height, quantity, motor_system, ral_code, glass_type, glass_color, total_amount FROM guillotinesystems WHERE general_offer_id = :id');
    $gStmt->execute([':id' => $id]);
    $guillotines = $gStmt->fetchAll(PDO::FETCH_ASSOC);

    $sStmt = $pdo->prepare('SELECT system_type, width, height, quantity, wing_type, ral_code, glass_type, glass_color, total_amount FROM slidingsystems WHERE general_offer_id = :id');
    $sStmt->execute([':id' => $id]);
    $slidings = $sStmt->fetchAll(PDO::FETCH_ASSOC);

    $company = ['name' => '', 'logo' => null];
    try {
        $cStmt = $pdo->query('SELECT name, logo FROM company LIMIT 1');
        if ($cStmt) {
            $company = $cStmt->fetch(PDO::FETCH_ASSOC) ?: $company;
        }
    } catch (Throwable $e) {
        // ignore company fetch errors
    }

    $uStmt = $pdo->prepare('SELECT TRIM(CONCAT(first_name, " ", last_name)) AS full_name, username FROM users WHERE id = :id');
    $uStmt->execute([':id' => $userId]);
    $u = $uStmt->fetch(PDO::FETCH_ASSOC);
    $preparedBy = $u['full_name'] ?: ($u['username'] ?? '');
} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = 'Veriler yüklenemedi.';
}

$paymentLabels = [
    'cash' => 'Peşin',
    'bank_transfer' => 'Havale/EFT',
    'credit_card' => 'Kredi Kartı',
    'installment' => 'Taksitli',
    'other' => 'Diğer',
];

$paymentText = $paymentLabels[$quote['payment_method'] ?? ''] ?? ($quote['payment_method'] ?? '');

$gTotal = 0.0;
foreach ($guillotines as $g) { $gTotal += (float)($g['total_amount'] ?? 0); }
$sTotal = 0.0;
foreach ($slidings as $s) { $sTotal += (float)($s['total_amount'] ?? 0); }
$grandTotal = $gTotal + $sTotal;
$subTotal = $grandTotal / 1.2;
$vatAmount = $grandTotal - $subTotal;

$firstItem = $guillotines[0] ?? ($slidings[0] ?? null);
$summaryWidth = $firstItem['width'] ?? '';
$summaryHeight = $firstItem['height'] ?? '';
$summaryQty = $firstItem['quantity'] ?? '';

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teklif Önizleme</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-size:0.95rem; }
        h1,h2,h3,h4,h5 { font-size:1.15rem; }
        @media print {
            .d-print-none { display:none !important; }
            @page { margin:15mm; }
            table { page-break-inside:auto; }
            tr { page-break-inside:avoid; page-break-after:auto; }
        }
        .signature-box { height:60px; }
    </style>
</head>
<body class="bg-white">
<div class="container my-3">
<?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-2">
            <?php if (!empty($company['logo'])): ?><img src="<?= h($company['logo']) ?>" alt="<?= h($company['name']) ?>" style="height:60px;" class="me-2"><?php endif; ?>
            <div class="fw-bold"><?= h($company['name']) ?></div>
        </div>
        <div class="text-end">
            <div class="fw-bold">Teklif No: <?= h($quote['quote_no'] ?? '') ?></div>
            <div>Tarih: <?= h($quote['offer_date'] ?? '') ?></div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6"><strong>Müşteri:</strong> <?= h(trim(($quote['first_name'] ?? '') . ' ' . ($quote['last_name'] ?? ''))) ?></div>
                <div class="col-md-6"><strong>Proje/Saha:</strong> <?= h($quote['quote_no'] ?? '') ?></div>
                <div class="col-md-6"><strong>Hazırlayan:</strong> <?= h($preparedBy) ?></div>
                <div class="col-md-6"><strong>Geçerlilik Tarihi:</strong> <?php
                    $valid = '';
                    if (!empty($quote['offer_date']) && !empty($quote['validity_days'])) {
                        $valid = date('Y-m-d', strtotime($quote['offer_date'].' +'.((int)$quote['validity_days']).' days'));
                    }
                    echo h($valid);
                ?></div>
            </div>
        </div>
    </div>
    <h5 class="mb-3">Teklif Özeti</h5>
    <div class="row row-cols-2 row-cols-md-4 g-2 mb-4">
        <div class="col"><div class="card h-100"><div class="card-body"><div class="small text-muted">Genişlik</div><div><?= h((string)$summaryWidth) ?></div></div></div></div>
        <div class="col"><div class="card h-100"><div class="card-body"><div class="small text-muted">Yükseklik</div><div><?= h((string)$summaryHeight) ?></div></div></div></div>
        <div class="col"><div class="card h-100"><div class="card-body"><div class="small text-muted">Adet</div><div><?= h((string)$summaryQty) ?></div></div></div></div>
        <div class="col"><div class="card h-100"><div class="card-body"><div class="small text-muted">Ödeme Yöntemi</div><div><?= h($paymentText) ?></div></div></div></div>
        <div class="col"><div class="card h-100"><div class="card-body"><div class="small text-muted">Para Birimi</div><div>TRY</div></div></div></div>
        <div class="col"><div class="card h-100"><div class="card-body"><div class="small text-muted">Ara Toplam</div><div><?= number_format($subTotal,2,',','.') ?> ₺</div></div></div></div>
        <div class="col"><div class="card h-100"><div class="card-body"><div class="small text-muted">KDV</div><div><?= number_format($vatAmount,2,',','.') ?> ₺</div></div></div></div>
        <div class="col"><div class="card h-100"><div class="card-body"><div class="small text-muted">Genel Toplam</div><div><?= number_format($grandTotal,2,',','.') ?> ₺</div></div></div></div>
    </div>

    <h5 class="mt-4">Giyotin Teklifi</h5>
    <div class="table-responsive mb-4">
    <table class="table table-sm table-striped table-bordered">
        <thead>
            <tr>
                <th>Kategori</th>
                <th>Ürün</th>
                <th>Ölçü</th>
                <th>Birim</th>
                <th class="text-end">Adet</th>
                <th class="text-end">Birim Fiyatı</th>
                <th class="text-end">KDV %</th>
                <th class="text-end">Tutar</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($guillotines): foreach ($guillotines as $g): ?>
            <?php $line = (float)($g['total_amount'] ?? 0); $unit = $line / max(1,(float)$g['quantity']); ?>
            <tr>
                <td><?= h($g['system_type'] ?? '') ?></td>
                <td><?= h(trim(($g['glass_type'] ?? '') . ' ' . ($g['glass_color'] ?? ''))) ?></td>
                <td><?= h(($g['width'] ?? '') . ' x ' . ($g['height'] ?? '')) ?></td>
                <td>Adet</td>
                <td class="text-end"><?= h((string)($g['quantity'] ?? '')) ?></td>
                <td class="text-end"><?= number_format($unit,2,',','.') ?></td>
                <td class="text-end">20</td>
                <td class="text-end"><?= number_format($line,2,',','.') ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="8" class="text-center">Kayıt bulunamadı.</td></tr>
        <?php endif; ?>
        </tbody>
        <?php if ($guillotines): ?>
        <tfoot>
            <tr>
                <th colspan="7" class="text-end">Ara Toplam</th>
                <th class="text-end"><?= number_format($gTotal/1.2,2,',','.') ?></th>
            </tr>
            <tr>
                <th colspan="7" class="text-end">KDV</th>
                <th class="text-end"><?= number_format($gTotal - $gTotal/1.2,2,',','.') ?></th>
            </tr>
            <tr>
                <th colspan="7" class="text-end">Toplam</th>
                <th class="text-end"><?= number_format($gTotal,2,',','.') ?></th>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
    </div>

    <h5 class="mt-4">Sürme Teklifi</h5>
    <div class="table-responsive mb-4">
    <table class="table table-sm table-striped table-bordered">
        <thead>
            <tr>
                <th>Kategori</th>
                <th>Ürün</th>
                <th>Ölçü</th>
                <th>Birim</th>
                <th class="text-end">Adet</th>
                <th class="text-end">Birim Fiyatı</th>
                <th class="text-end">KDV %</th>
                <th class="text-end">Tutar</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($slidings): foreach ($slidings as $s): ?>
            <?php $line = (float)($s['total_amount'] ?? 0); $unit = $line / max(1,(float)$s['quantity']); ?>
            <tr>
                <td><?= h($s['system_type'] ?? '') ?></td>
                <td><?= h(trim(($s['glass_type'] ?? '') . ' ' . ($s['glass_color'] ?? ''))) ?></td>
                <td><?= h(($s['width'] ?? '') . ' x ' . ($s['height'] ?? '')) ?></td>
                <td>Adet</td>
                <td class="text-end"><?= h((string)($s['quantity'] ?? '')) ?></td>
                <td class="text-end"><?= number_format($unit,2,',','.') ?></td>
                <td class="text-end">20</td>
                <td class="text-end"><?= number_format($line,2,',','.') ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="8" class="text-center">Kayıt bulunamadı.</td></tr>
        <?php endif; ?>
        </tbody>
        <?php if ($slidings): ?>
        <tfoot>
            <tr>
                <th colspan="7" class="text-end">Ara Toplam</th>
                <th class="text-end"><?= number_format($sTotal/1.2,2,',','.') ?></th>
            </tr>
            <tr>
                <th colspan="7" class="text-end">KDV</th>
                <th class="text-end"><?= number_format($sTotal - $sTotal/1.2,2,',','.') ?></th>
            </tr>
            <tr>
                <th colspan="7" class="text-end">Toplam</th>
                <th class="text-end"><?= number_format($sTotal,2,',','.') ?></th>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
    </div>

    <h5 class="mt-4">Açıklama Alanı</h5>
    <div class="mb-4">
        <div class="form-control" readonly style="min-height:6rem; white-space:pre-wrap;"><?= h($quote['notes'] ?? '') ?></div>
    </div>

    <h5 class="mt-4">Onay Alanı</h5>
    <div class="card mb-5">
        <div class="card-body">
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="confirmBox">
                <label class="form-check-label" for="confirmBox">Yukarıdaki şartları okudum ve onaylıyorum.</label>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Onaylayan Adı</label>
                    <input type="text" class="form-control" />
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tarih</label>
                    <input type="date" class="form-control" value="<?= h(date('Y-m-d')) ?>" />
                </div>
                <div class="col-md-4">
                    <label class="form-label">İmza</label>
                    <div class="border signature-box"></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>
<div class="d-print-none border-top bg-light position-sticky bottom-0 py-2">
    <div class="container d-flex gap-2">
        <a href="render_quotation_pdf.php?id=<?= h((string)$id) ?>" class="btn btn-primary">PDF İndir</a>
        <button class="btn btn-secondary" onclick="window.print()">Yazdır</button>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
