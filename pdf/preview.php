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

require __DIR__ . '/../config/config.php';

function h(?string $v): string
{
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
$approveUrl = '';

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
        } catch (Throwable $e2) { /* ignore */
        }
    }

    $uStmt = $pdo->prepare('SELECT TRIM(CONCAT(first_name, " ", last_name)) AS full_name, username FROM users WHERE id = :id');
    $uStmt->execute([':id' => $userId]);
    $u = $uStmt->fetch(PDO::FETCH_ASSOC);
    $preparedBy = $u['full_name'] ?: ($u['username'] ?? '');
    if (!empty($quote['approval_token'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $approveUrl = $host ? $scheme . '://' . $host . '/approve.php?token=' . urlencode($quote['approval_token']) : '';
    }
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../public/assets/app.css" rel="stylesheet">
    <style>
        .signature-box{height:60px;}
        .action-bar{position:sticky;bottom:0;z-index:1020;}
        @media print{
            .d-print-none{display:none!important;}
            thead{display:table-header-group;}
            @page{margin:10mm;}
        }
    </style>
</head>

<body class="bg-white">
    <div class="container my-2">
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert"><?= h($error) ?></div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-3 g-3 mb-3">
                <div class="col">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Müşteri Bilgileri</div>
                        <div class="card-body small">
                            <div><span class="fw-semibold">Firma:</span> <?= h($quote['customer_company'] ?? '') ?></div>
                            <div><span class="fw-semibold">İlgili:</span> <?= h(trim(($quote['first_name'] ?? '') . ' ' . ($quote['last_name'] ?? ''))) ?></div>
                            <div><span class="fw-semibold">Telefon:</span> <?= h($quote['customer_phone'] ?? '') ?></div>
                            <div><span class="fw-semibold">Adres:</span> <?= nl2br(h($quote['customer_address'] ?? '')) ?></div>
                            <div><span class="fw-semibold">E-posta:</span> <?= h($quote['customer_email'] ?? '') ?></div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Teklif Bilgileri</div>
                        <div class="card-body small">
                            <div><span class="fw-semibold">Teslimat:</span> <?= h($quote['delivery_time'] ?? '') ?></div>
                            <div><span class="fw-semibold">Ödeme:</span> <span class="badge bg-info text-dark"><?= h($paymentText) ?></span></div>
                            <div><span class="fw-semibold">Vade:</span> <?= h($quote['payment_term'] ?? '') ?></div>
                            <div><span class="fw-semibold">Geçerlilik:</span> <?= h($validUntil) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Genel Bilgiler</div>
                        <div class="card-body small">
                            <div><span class="fw-semibold">Teklif No:</span> <?= h($quote['quote_no'] ?? '') ?></div>
                            <div><span class="fw-semibold">Tarih:</span> <?= h($quote['offer_date'] ?? '') ?></div>
                            <div><span class="fw-semibold">Hazırlayan:</span> <?= h($preparedBy) ?></div>
                            <?php if ($approveUrl): ?>
                            <div class="d-print-none"><span class="fw-semibold">Onay:</span> <a href="<?= h($approveUrl) ?>"><?= h($approveUrl) ?></a><button type="button" class="btn btn-sm btn-outline-secondary share-btn ms-2" data-url="<?= h($approveUrl) ?>">Paylaş</button></div>
                            <?php endif; ?>
                            <div><span class="fw-semibold">E-posta:</span> <?= h($company['email']) ?></div>
                        </div>
                    </div>
                </div>
            </div>


            <?php if ($systems): ?>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-striped align-middle" aria-label="Sistem Tablosu">
                        <thead class="table-light sticky-top" style="top:0;z-index:1;">
                        <tr>
                            <th scope="col">RAL</th>
                            <th scope="col">Cam Rengi</th>
                            <th scope="col">Sistem</th>
                            <th scope="col">Açıklama</th>
                            <th scope="col" class="text-end">Adet</th>
                            <th scope="col" class="text-end">Genişlik</th>
                            <th scope="col" class="text-end">Yükseklik</th>
                            <th scope="col" class="text-end">m²</th>
                            <th scope="col" class="text-end">Toplam m²</th>
                            <th scope="col" class="text-end">Tutar</th>
                        </tr>
                    </thead>
                        <tbody>
                            <?php foreach ($systems as $row): ?>
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
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info" role="alert">Sistem bulunamadı.</div>
            <?php endif; ?>

            <div class="row row-cols-1 row-cols-md-3 g-3 mb-3">
                <div class="col">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Açıklamalar</div>
                        <div class="card-body small">
                            <?php if (trim($quote['remarks'] ?? '') !== ''): ?>
                                <div class="text-break" style="white-space:pre-wrap;"><?= h($quote['remarks'] ?? '') ?></div>
                            <?php else: ?>
                                <div class="alert alert-secondary mb-0" role="alert">Açıklama bulunmamaktadır.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Banka Bilgileri</div>
                        <div class="card-body small">
                            <?php if (!empty($company['bank_account'])): ?>
                                <div class="text-break" style="white-space:pre-wrap;"><?= nl2br(h($company['bank_account'])) ?></div>
                            <?php else: ?>
                                <div class="alert alert-secondary mb-0" role="alert">Banka bilgisi bulunmamaktadır.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Tutar Özeti</div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <th scope="row">Ara Toplam</th>
                                        <td class="text-end"><?= number_format($subTotal, 2, ',', '.') ?> ₺</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">İskonto</th>
                                        <td class="text-end"><?= number_format($discountAmount, 2, ',', '.') ?> ₺</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">KDV</th>
                                        <td class="text-end"><?= number_format($vatAmount, 2, ',', '.') ?> ₺</td>
                                    </tr>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th scope="row">Genel Toplam</th>
                                        <td class="text-end fw-bold"><?= number_format($grandTotal, 2, ',', '.') ?> ₺</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
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
                            <label class="form-label" for="approverName">Onaylayan Adı</label>
                            <input type="text" class="form-control" id="approverName" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="approvalDate">Tarih</label>
                            <input type="date" class="form-control" id="approvalDate" value="<?= h(date('Y-m-d')) ?>" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="signatureBox">İmza</label>
                            <div id="signatureBox" class="border signature-box"></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="action-bar d-print-none border-top bg-light">
        <div class="container py-2 d-flex gap-2">
            <a href="render_quotation_pdf.php?id=<?= h((string)$id) ?>" class="btn btn-primary" aria-label="PDF indir"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
            <button class="btn btn-secondary" onclick="window.print()" aria-label="Yazdır"><i class="bi bi-printer me-1"></i>Yazdır</button>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../public/assets/app.js"></script>
</body>

</html>
<?php ob_end_flush(); ?>