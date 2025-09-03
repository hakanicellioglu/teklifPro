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

function h(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$userId = (int)$_SESSION['user_id'];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Geçersiz teklif ID\'si. <a href="../quotations.php">Teklif listesine dön</a>.');
}

$quote = null;
$guillotines = [];
$slidings = [];
$systems = [];
$error = null;
$approveUrl = '';

try {
    $stmt = $pdo->prepare('SELECT g.*, c.first_name, c.last_name, c.company_name AS customer_company, c.email AS customer_email, c.phone AS customer_phone, c.address AS customer_address, co.name AS company_name FROM generaloffers g LEFT JOIN customers c ON g.customer_id = c.id LEFT JOIN company co ON g.company_id = co.id AND co.user_id = :uid WHERE g.id = :id AND (g.company_id IS NULL OR co.id IS NOT NULL)');
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$quote) {
        http_response_code(404);
        exit('Teklif bulunamadı veya erişim yetkiniz yok. <a href="../quotations.php">Teklif listesine dön</a>.');
    }

    $gStmt = $pdo->prepare('SELECT system_type, width, height, quantity, motor_system, ral_code, glass_type, glass_color, total_amount FROM guillotinesystems WHERE general_offer_id = :id');
    $gStmt->execute([':id' => $id]);
    $guillotines = $gStmt->fetchAll(PDO::FETCH_ASSOC);

    $sStmt = $pdo->prepare('SELECT system_type, width, height, quantity, wing_type, ral_code, glass_type, glass_color, total_amount FROM slidingsystems WHERE general_offer_id = :id');
    $sStmt->execute([':id' => $id]);
    $slidings = $sStmt->fetchAll(PDO::FETCH_ASSOC);

    $company = ['name' => '', 'logo' => null, 'email' => '', 'phone' => '', 'address' => '', 'bank_account' => ''];
    try {
        $cStmt = $pdo->prepare('SELECT name, logo, email, phone, address, bank_account FROM company WHERE user_id = :uid LIMIT 1');
        $cStmt->execute([':uid' => $userId]);
        $company = array_merge($company, $cStmt->fetch(PDO::FETCH_ASSOC) ?: []);
    } catch (Throwable $e) {
        try {
            $cStmt = $pdo->prepare('SELECT name, logo, email, phone, address FROM company WHERE user_id = :uid LIMIT 1');
            $cStmt->execute([':uid' => $userId]);
            $company = array_merge($company, $cStmt->fetch(PDO::FETCH_ASSOC) ?: []);
        } catch (Throwable $e2) { /* ignore */ }
    }

    $uStmt = $pdo->prepare('SELECT TRIM(CONCAT(first_name, " ", last_name)) AS full_name, username FROM users WHERE id = :id');
    $uStmt->execute([':id' => $userId]);
    $u = $uStmt->fetch(PDO::FETCH_ASSOC);
    $preparedBy = $u['full_name'] ?: ($u['username'] ?? '');
    if (!empty($quote['approval_token'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $approveUrl = $host ? $scheme . '://' . $host . '/public/approve.php?token=' . urlencode($quote['approval_token']) : '';
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
    $validUntil = date('d.m.Y', strtotime($quote['offer_date'] . ' +' . ((int)$quote['validity_days']) . ' days'));
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiyat Teklifi - <?= h($quote['quote_no'] ?? '') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.4; color: #1f2937; background-color: #f9fafb; font-size: 12px; }
        .container { max-width: 1200px; margin: 0 auto; padding: 15px; background-color: #ffffff; min-height: 100vh; }

        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #2563eb; }
        .header-left { display: flex; align-items: flex-start; gap: 15px; flex: 1; }
        .logo-container { width: 100px; height: 50px; background-color: #f8fafc; border: 2px dashed #e5e7eb; border-radius: 6px; display: flex; flex-direction: column; justify-content: center; align-items: center; position: relative; overflow: hidden; flex-shrink: 0; }
        .logo-container img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .logo-placeholder { color: #6b7280; font-size: 8px; font-weight: 600; text-align: center; }
        .logo-path { color: #9ca3af; font-size: 6px; text-align: center; margin-top: 2px; font-family: monospace; }

        .company-info { flex: 1; }
        .company-name { font-size: 16px; font-weight: 700; color: #111827; margin-bottom: 4px; }
        .company-details { font-size: 9px; color: #4b5563; line-height: 1.3; }

        .header-right { text-align: right; flex-shrink: 0; }
        .quote-info { font-size: 9px; color: #4b5563; line-height: 1.4; }
        .quote-info strong { color: #1f2937; }

        .document-title { text-align: center; font-size: 18px; font-weight: 700; color: #1f2937; margin-bottom: 15px; letter-spacing: 1px; text-transform: uppercase; }

        .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px; }
        .info-card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); }
        .card-header { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: #ffffff; padding: 6px 8px; font-weight: 600; font-size: 9px; letter-spacing: 0.5px; }
        .card-body { padding: 8px; }
        .info-row { display: flex; margin-bottom: 3px; align-items: flex-start; }
        .info-label { font-weight: 600; color: #374151; min-width: 50px; margin-right: 6px; font-size: 8px; }
        .info-value { color: #1f2937; flex: 1; font-size: 8px; }
        .badge { background: #dbeafe; color: #1e40af; padding: 1px 4px; border-radius: 3px; font-size: 7px; font-weight: 500; }

        .table-section { margin: 12px 0; }
        .section-title { font-size: 11px; font-weight: 700; color: #1f2937; margin-bottom: 8px; padding-bottom: 3px; border-bottom: 1px solid #e5e7eb; }

        .modern-table { width: 100%; border-collapse: collapse; background: #ffffff; border-radius: 4px; overflow: hidden; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); font-size: 7px; }
        .modern-table th { background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); color: #374151; font-weight: 600; padding: 4px 3px; text-align: left; font-size: 7px; letter-spacing: 0.3px; border-bottom: 1px solid #cbd5e1; }
        .modern-table td { padding: 3px; border-bottom: 1px solid #f1f5f9; font-size: 7px; color: #1f2937; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* ÖZET KART (tutarlar) */
        .summary-card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); }
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-row { display: flex; justify-content: space-between; padding: 3px 6px; border-bottom: 1px solid #f1f5f9; }
        .summary-label { font-size: 8px; color: #374151; font-weight: 500; }
        .summary-value { font-size: 8px; color: #1f2937; font-weight: 500; }
        .total-row { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); font-weight: 700; color: #1e40af; font-size: 9px; }

        /* *** YAN YANA DÜZEN İÇİN GÜNCELLENDİ *** */
        .summary-section {
            display: grid;
            grid-template-columns: 1fr 1fr; /* İki sütun */
            gap: 10px;
            margin: 12px 0;
        }

        .approval-section { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px; margin: 12px 0; }
        .approval-title { font-size: 10px; font-weight: 700; color: #1f2937; margin-bottom: 6px; text-align: center; }
        .checkbox-container { margin-bottom: 6px; }
        .checkbox-container input[type="checkbox"] { margin-right: 4px; transform: scale(0.9); }
        .checkbox-container label { font-size: 8px; }
        .approval-fields { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-top: 6px; }
        .field-group { display: flex; flex-direction: column; }
        .field-label { font-weight: 600; color: #374151; margin-bottom: 2px; font-size: 8px; }
        .field-input { padding: 4px; border: 1px solid #d1d5db; border-radius: 3px; font-size: 7px; color: #1f2937; background: #ffffff; }
        .field-input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1); }
        .signature-box { height: 30px; border: 1px dashed #d1d5db; border-radius: 3px; background: #ffffff; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 7px; font-style: italic; }

        .action-bar { position: fixed; bottom: 0; left: 0; right: 0; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border-top: 1px solid #e5e7eb; padding: 8px 0; box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1); z-index: 1000; }
        .action-buttons { max-width: 1200px; margin: 0 auto; padding: 0 15px; display: flex; gap: 8px; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; font-weight: 600; font-size: 9px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; cursor: pointer; transition: all 0.3s ease; letter-spacing: 0.3px; }
        .btn-primary { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: #ffffff; }
        .btn-primary:hover { background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
        .btn-secondary { background: #6b7280; color: #ffffff; }
        .btn-secondary:hover { background: #4b5563; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3); }
        .alert { padding: 8px; border-radius: 4px; margin-bottom: 8px; font-weight: 500; font-size: 8px; }
        .alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
        .alert-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #2563eb; }

        @media print {
            body { background: #ffffff; font-size: 7px; }
            .container { max-width: none; margin: 0; padding: 8mm; box-shadow: none; }
            .action-bar, .d-print-none { display: none !important; }
            .info-card, .modern-table { box-shadow: none; border: 1px solid #e5e7eb; }
            @page { margin: 8mm; size: A4; }
            .header { border-bottom: 2px solid #2563eb; margin-bottom: 8px; padding-bottom: 8px; }
            .modern-table th { background: #f1f5f9 !important; }
            .document-title { margin-bottom: 8px; font-size: 14px; }
            .info-grid { margin-bottom: 8px; }
            .table-section { margin: 8px 0; }
            .summary-section { margin: 8px 0; }
            .approval-section { margin: 8px 0; padding: 8px; }
        }

        @media (max-width: 768px) {
            .container { padding: 10px; }
            .header { flex-direction: column; align-items: center; text-align: center; }
            .header-left { flex-direction: column; align-items: center; gap: 8px; }
            .header-right { margin-top: 15px; text-align: center; }
            .info-grid { grid-template-columns: 1fr; }
            .summary-section { grid-template-columns: 1fr; } /* mobil tek sütun */
            .approval-fields { grid-template-columns: 1fr; }
            .action-buttons { padding: 0 10px; justify-content: center; }
        }
    </style>
</head>

<body>
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php else: ?>

            <div class="header">
                <div class="header-left">
                    <div class="logo-container">
                        <?php if (!empty($company['logo']) && file_exists('../assets/' . $company['logo'])): ?>
                            <img src="../assets/<?= h($company['logo']) ?>" alt="<?= h($company['name']) ?> Logo">
                        <?php else: ?>
                            <div class="logo-placeholder">FİRMA LOGOSU</div>
                            <div class="logo-path">/assets/logo.png</div>
                        <?php endif; ?>
                    </div>

                    <div class="company-info">
                        <div class="company-name"><?= h($company['name']) ?></div>
                        <div class="company-details">
                            <?= h($company['email']) ?><br>
                            <?= h($company['phone']) ?><br>
                            <?= nl2br(h($company['address'])) ?>
                        </div>
                    </div>
                </div>

                <div class="header-right">
                    <div class="quote-info">
                        <strong>Teklif No:</strong> <?= h($quote['quote_no'] ?? '') ?><br>
                        <strong>Tarih:</strong> <?= h(date('d.m.Y', strtotime($quote['offer_date'] ?? 'now'))) ?><br>
                        <strong>Hazırlayan:</strong> <?= h($preparedBy) ?>
                    </div>
                </div>
            </div>

            <h1 class="document-title">Fiyat Teklifi</h1>

            <div class="info-grid">
                <div class="info-card">
                    <div class="card-header">Müşteri Bilgileri</div>
                    <div class="card-body">
                        <div class="info-row"><span class="info-label">Firma:</span><span class="info-value"><?= h($quote['customer_company'] ?? '') ?></span></div>
                        <div class="info-row"><span class="info-label">İlgili:</span><span class="info-value"><?= h(trim(($quote['first_name'] ?? '') . ' ' . ($quote['last_name'] ?? ''))) ?></span></div>
                        <div class="info-row"><span class="info-label">Telefon:</span><span class="info-value"><?= h($quote['customer_phone'] ?? '') ?></span></div>
                        <div class="info-row"><span class="info-label">E-posta:</span><span class="info-value"><?= h($quote['customer_email'] ?? '') ?></span></div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-header">Teklif Detayları</div>
                    <div class="card-body">
                        <div class="info-row"><span class="info-label">Teslimat:</span><span class="info-value"><?= h($quote['delivery_time'] ?? '') ?></span></div>
                        <div class="info-row"><span class="info-label">Ödeme:</span><span class="info-value"><span class="badge"><?= h($paymentText) ?></span></span></div>
                        <div class="info-row"><span class="info-label">Vade:</span><span class="info-value"><?= h($quote['payment_term'] ?? '') ?></span></div>
                        <?php if ($validUntil): ?>
                        <div class="info-row"><span class="info-label">Geçerlilik:</span><span class="info-value"><?= h($validUntil) ?></span></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-header">Ek Bilgiler</div>
                    <div class="card-body">
                        <?php if ($approveUrl): ?>
                        <div class="info-row d-print-none">
                            <span class="info-label">Onay Linki:</span>
                            <span class="info-value">
                                <a href="<?= h($approveUrl) ?>" style="color: #2563eb; text-decoration: none; font-weight: 500;"><?= h($approveUrl) ?></a>
                            </span>
                        </div>
                        <?php else: ?>
                        <div class="info-row">
                            <span class="info-label">Adres:</span>
                            <span class="info-value"><?= nl2br(h($quote['customer_address'] ?? '')) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($systems): ?>
            <div class="table-section">
                <h2 class="section-title">Sistem Detayları</h2>
                <div style="overflow-x: auto;">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th style="width: 8%;">RAL</th>
                                <th style="width: 10%;">Cam</th>
                                <th style="width: 12%;">Sistem</th>
                                <th style="width: 15%;">Açıklama</th>
                                <th style="width: 6%;" class="text-right">Adet</th>
                                <th style="width: 8%;" class="text-right">En</th>
                                <th style="width: 8%;" class="text-right">Boy</th>
                                <th style="width: 6%;" class="text-right">m²</th>
                                <th style="width: 10%;" class="text-right">Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($systems as $row): ?>
                            <tr>
                                <td><?= h($row['ral']) ?></td>
                                <td><?= h($row['glass']) ?></td>
                                <td><?= h($row['system']) ?></td>
                                <td><?= h($row['desc']) ?></td>
                                <td class="text-right"><?= h((string)$row['qty']) ?></td>
                                <td class="text-right"><?= h(number_format($row['width'], 0, ',', '.')) ?></td>
                                <td class="text-right"><?= h(number_format($row['height'], 0, ',', '.')) ?></td>
                                <td class="text-right"><?= h(number_format($row['total_area'], 2, ',', '.')) ?></td>
                                <td class="text-right"><?= number_format($row['total'], 2, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-info"><strong>Bilgi:</strong> Henüz sistem eklenmemiş.</div>
            <?php endif; ?>

            <!-- TUTAR ÖZETİ -->
            <div class="summary-card">
                <div class="card-header">Tutar Özeti</div>
                <div class="card-body" style="padding: 0;">
                    <div class="summary-row"><span class="summary-label">Ara Toplam:</span><span class="summary-value"><?= number_format($grossTotal, 2, ',', '.') ?> ₺</span></div>
                    <div class="summary-row"><span class="summary-label">İskonto:</span><span class="summary-value">-<?= number_format($discountAmount, 2, ',', '.') ?> ₺</span></div>
                    <div class="summary-row"><span class="summary-label">Alt Toplam:</span><span class="summary-value"><?= number_format($subTotal, 2, ',', '.') ?> ₺</span></div>
                    <div class="summary-row"><span class="summary-label">KDV (%<?= h((string)$vatRate) ?>):</span><span class="summary-value"><?= number_format($vatAmount, 2, ',', '.') ?> ₺</span></div>
                    <div class="summary-row total-row"><span class="summary-label">GENEL TOPLAM:</span><span class="summary-value"><?= number_format($grandTotal, 2, ',', '.') ?> ₺</span></div>
                </div>
            </div>

            <!-- AÇIKLAMALAR + BANKA BİLGİLERİ (YAN YANA) -->
            <div class="summary-section">
                <div class="summary-card">
                    <div class="card-header">Açıklamalar</div>
                    <div class="card-body">
                        <?php if (trim($quote['remarks'] ?? '') !== ''): ?>
                            <div style="white-space: pre-wrap; font-size: 8px; line-height: 1.4;">
                                <?= nl2br(h($quote['remarks'] ?? '')) ?>
                            </div>
                        <?php else: ?>
                            <div style="color: #6b7280; font-style: italic; font-size: 8px;">
                                Özel açıklama bulunmamaktadır.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="card-header">Banka Bilgileri</div>
                    <div class="card-body">
                        <?php if (!empty($company['bank_account'])): ?>
                            <div style="white-space: pre-wrap; font-size: 8px; line-height: 1.4;">
                                <?= nl2br(h($company['bank_account'])) ?>
                            </div>
                        <?php else: ?>
                            <div style="color: #6b7280; font-style: italic; font-size: 8px;">
                                Banka bilgisi eklenmemiş.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ONAY BÖLÜMÜ -->
            <div class="approval-section">
                <h3 class="approval-title">Teklif Onay Bölümü</h3>
                <div class="checkbox-container">
                    <input type="checkbox" id="confirmBox">
                    <label for="confirmBox" style="font-size: 8px; color: #374151; font-weight: 500;">
                        Yukarıda belirtilen şart ve koşulları okudum, anladım ve onaylıyorum.
                    </label>
                </div>

                <div class="approval-fields">
                    <div class="field-group">
                        <label class="field-label">Onaylayan Adı Soyadı</label>
                        <input type="text" class="field-input" id="approverName" placeholder="Ad ve soyadınızı yazın">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Onay Tarihi</label>
                        <input type="date" class="field-input" id="approvalDate" value="<?= h(date('Y-m-d')) ?>">
                    </div>
                    <div class="field-group">
                        <label class="field-label">İmza</label>
                        <div class="signature-box" id="signatureBox">İmza alanı</div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <div class="action-bar d-print-none">
        <div class="action-buttons">
            <a href="render_quotation_pdf.php?id=<?= h((string)$id) ?>" class="btn btn-primary">📄 PDF İndir</a>
            <button class="btn btn-secondary" onclick="window.print()">🖨️ Yazdır</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const shareButtons = document.querySelectorAll('.share-btn');
            shareButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const url = this.dataset.url;
                    if (navigator.share) {
                        navigator.share({ title: 'Teklif Onay Linki', url });
                    } else {
                        navigator.clipboard.writeText(url).then(() => { alert('Link panoya kopyalandı!'); });
                    }
                });
            });

            window.addEventListener('beforeprint', function() { document.body.style.paddingBottom = '0'; });
            window.addEventListener('afterprint', function() { document.body.style.paddingBottom = ''; });
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
