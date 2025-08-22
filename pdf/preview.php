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
$systems = [];
$error = null;

try {
    $stmt = $pdo->prepare('SELECT g.*, c.first_name, c.last_name, c.company_name AS customer_company, c.email AS customer_email, c.phone AS customer_phone, c.address AS customer_address, co.name AS company_name FROM generaloffers g LEFT JOIN customers c ON g.customer_id = c.id LEFT JOIN company co ON g.company_id = co.id WHERE g.id = :id');
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

    $company = ['name' => '', 'logo' => null, 'email' => '', 'phone' => '', 'address' => '', 'bank_account' => ''];
    try {
        $cStmt = $pdo->query('SELECT name, logo, email, phone, address, bank_account FROM company LIMIT 1');
        if ($cStmt) {
            $company = array_merge($company, $cStmt->fetch(PDO::FETCH_ASSOC) ?: []);
        }
    } catch (Throwable $e) {
        try {
            $cStmt = $pdo->query('SELECT name, logo, email, phone, address FROM company LIMIT 1');
            if ($cStmt) {
                $company = array_merge($company, $cStmt->fetch(PDO::FETCH_ASSOC) ?: []);
            }
        } catch (Throwable $e2) { /* ignore */ }
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

$areaCalc = fn($w, $h) => (max(0, (float)$w) * max(0, (float)$h)) / 1000000;
$grossTotal = 0.0;

foreach ($guillotines as $g) {
    $line = (float)($g['total_amount'] ?? 0);
    $area = $areaCalc($g['width'] ?? 0, $g['height'] ?? 0);
    $systems[] = [
        'ral'        => $g['ral_code'] ?? '',
        'glass'      => $g['glass_color'] ?? '',
        'system'     => $g['system_type'] ?? '',
        'desc'       => trim(($g['glass_type'] ?? '') . ' ' . ($g['motor_system'] ?? '')),
        'qty'        => (float)($g['quantity'] ?? 0),
        'width'      => (float)($g['width'] ?? 0),
        'height'     => (float)($g['height'] ?? 0),
        'area'       => $area,
        'total_area' => $area * (float)($g['quantity'] ?? 0),
        'total'      => $line,
    ];
    $grossTotal += $line;
}

foreach ($slidings as $s) {
    $line = (float)($s['total_amount'] ?? 0);
    $area = $areaCalc($s['width'] ?? 0, $s['height'] ?? 0);
    $systems[] = [
        'ral'        => $s['ral_code'] ?? '',
        'glass'      => $s['glass_color'] ?? '',
        'system'     => $s['system_type'] ?? '',
        'desc'       => trim(($s['glass_type'] ?? '') . ' ' . ($s['wing_type'] ?? '')),
        'qty'        => (float)($s['quantity'] ?? 0),
        'width'      => (float)($s['width'] ?? 0),
        'height'     => (float)($s['height'] ?? 0),
        'area'       => $area,
        'total_area' => $area * (float)($s['quantity'] ?? 0),
        'total'      => $line,
    ];
    $grossTotal += $line;
}

$discountAmount = (float)($quote['discount_amount'] ?? 0);
if (!$discountAmount && isset($quote['discount_rate'])) {
    $discountAmount = $grossTotal * ((float)$quote['discount_rate']) / 100;
}
$subTotal = $grossTotal - $discountAmount;

$vatRate = (float)($quote['vat_rate'] ?? 0);
$vatAmount = (float)($quote['vat_amount'] ?? 0);
if (!$vatAmount && $vatRate) {
    $vatAmount = $subTotal * $vatRate / 100;
}
$grandTotal = $subTotal + $vatAmount;

$validUntil = '';
if (!empty($quote['offer_date']) && !empty($quote['validity_days'])) {
    $validUntil = date('Y-m-d', strtotime($quote['offer_date'] . ' +' . ((int)$quote['validity_days']) . ' days'));
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teklif Önizleme</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-size:0.85rem; }
        h1,h2,h3,h4,h5,h6 { font-size:1rem; }
        @media print {
            .d-print-none { display:none !important; }
            @page { margin:10mm; }
            table { page-break-inside:auto; }
            tr { page-break-inside:avoid; page-break-after:auto; }
        }
        .signature-box { height:60px; }
    </style>
</head>
<body class="bg-white">
<div class="container my-2">
<?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php else: ?>
    <div class="mb-3"><div class="row"><div class="col-md-6"><div class="d-flex align-items-center mb-2"><?php if (!empty($company['logo'])): ?><img src="<?= h($company['logo']) ?>" alt="<?= h($company['name']) ?>" style="height:60px;" class="me-2"><?php endif; ?><div><div class="fw-bold"><?= h($company['name']) ?></div><div><?= nl2br(h($company['address'])) ?></div><div><?= h($company['email']) ?> • <?= h($company['phone']) ?></div></div></div></div><div class="col-md-6 text-end"><div><strong>Teklif No:</strong> <?= h($quote['quote_no'] ?? '') ?></div><div><strong>Tarih:</strong> <?= h($quote['offer_date'] ?? '') ?></div><div><strong>Hazırlayan:</strong> <?= h($preparedBy) ?></div><div><strong>E-posta:</strong> <?= h($company['email']) ?></div></div></div></div><div class="row mb-3"><div class="col-md-6"><h5>Müşteri Bilgileri</h5><div><strong>Firma:</strong> <?= h($quote['customer_company'] ?? '') ?></div><div><strong>İlgili:</strong> <?= h(trim(($quote['first_name'] ?? '') . ' ' . ($quote['last_name'] ?? ''))) ?></div><div><strong>Telefon:</strong> <?= h($quote['customer_phone'] ?? '') ?></div><div><strong>Adres:</strong> <?= nl2br(h($quote['customer_address'] ?? '')) ?></div><div><strong>E-posta:</strong> <?= h($quote['customer_email'] ?? '') ?></div></div><div class="col-md-6"><h5>Teklif Bilgileri</h5><div><strong>Teslimat:</strong> <?= h($quote['delivery_time'] ?? '') ?></div><div><strong>Ödeme:</strong> <?= h($paymentText) ?></div><div><strong>Vade:</strong> <?= h($quote['payment_term'] ?? '') ?></div><div><strong>Geçerlilik:</strong> <?= h($validUntil) ?></div></div></div>

    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th>RAL</th>
                    <th>Cam Rengi</th>
                    <th>Sistem</th>
                    <th>Açıklama</th>
                    <th class="text-end">Adet</th>
                    <th class="text-end">Genişlik</th>
                    <th class="text-end">Yükseklik</th>
                    <th class="text-end">m²</th>
                    <th class="text-end">Toplam m²</th>
                    <th class="text-end">Tutar</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($systems): foreach ($systems as $row): ?>
                <tr>
                    <td><?= h($row['ral']) ?></td>
                    <td><?= h($row['glass']) ?></td>
                    <td><?= h($row['system']) ?></td>
                    <td><?= h($row['desc']) ?></td>
                    <td class="text-end"><?= h((string)$row['qty']) ?></td>
                    <td class="text-end"><?= h(number_format($row['width'], 2, ',', '.')) ?></td>
                    <td class="text-end"><?= h(number_format($row['height'], 2, ',', '.')) ?></td>
                    <td class="text-end"><?= h(number_format($row['area'], 2, ',', '.')) ?></td>
                    <td class="text-end"><?= h(number_format($row['total_area'], 2, ',', '.')) ?></td>
                    <td class="text-end"><?= number_format($row['total'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="10" class="text-center">Kayıt bulunamadı.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <h6>Açıklamalar</h6>
            <div class="border rounded p-2" style="min-height:5rem; white-space:pre-wrap;"><?= h($quote['remarks'] ?? '') ?></div>
            <?php if (!empty($company['bank_account'])): ?>
            <h6 class="mt-2">Banka Bilgileri</h6>
            <div class="border rounded p-2" style="white-space:pre-wrap;"><?= nl2br(h($company['bank_account'])) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <table class="table table-sm">
                <tr>
                    <th>Ara Toplam</th>
                    <td class="text-end"><?= number_format($subTotal, 2, ',', '.') ?> ₺</td>
                </tr>
                <tr>
                    <th>İskonto</th>
                    <td class="text-end"><?= number_format($discountAmount, 2, ',', '.') ?> ₺</td>
                </tr>
                <tr>
                    <th>KDV</th>
                    <td class="text-end"><?= number_format($vatAmount, 2, ',', '.') ?> ₺</td>
                </tr>
                <tr class="table-light">
                    <th>Genel Toplam</th>
                    <td class="text-end"><strong><?= number_format($grandTotal, 2, ',', '.') ?> ₺</strong></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="card mb-3">
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
