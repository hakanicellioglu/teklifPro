<?php
require __DIR__ . '/header.php';

function e(?string $v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function formatCurrency(float $v): string {
    if (class_exists('NumberFormatter')) {
        $fmt = new NumberFormatter('tr_TR', NumberFormatter::CURRENCY);
        if ($fmt) {
            return $fmt->formatCurrency($v, 'TRY');
        }
    }
    return number_format($v, 2, ',', '.') . ' ₺';
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Teklif bulunamadı.</div></div>'; 
    echo '</body></html>'; 
    exit;
}

// Fetch general offer and customer info
$stmt = $pdo->prepare('SELECT g.*, c.first_name, c.last_name, c.company AS customer_company FROM generaloffers g JOIN customers c ON g.customer_id = c.id WHERE g.id = :id');
$stmt->execute([':id' => $id]);
$offer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$offer) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Teklif bulunamadı.</div></div>'; 
    echo '</body></html>'; 
    exit;
}

// Fetch guillotine systems
$gStmt = $pdo->prepare('SELECT * FROM guillotinesystems WHERE general_offer_id = :id');
$gStmt->execute([':id' => $id]);
$systems = $gStmt->fetchAll(PDO::FETCH_ASSOC);
if (!$systems) {
    echo '<div class="container mt-4"><div class="alert alert-warning">Bu teklife ait giyotin sistemi bulunamadı.</div></div>'; 
    echo '</body></html>'; 
    exit;
}

// Helper to compute line items for a single system
function computeSystem(array $row, PDO $pdo, array &$alerts): array {
    $width = max(0, (float)($row['width'] ?? 0));
    $height = max(0, (float)($row['height'] ?? 0));
    $qty = max(0, (int)($row['quantity'] ?? 0));

    $rules = [
        ['label' => 'Motor Kutusu',          'name' => 'Motor Kutusu',  'measure' => fn($w,$h,$q) => $w - 14,                                            'qty' => fn($w,$h,$q) => $q],
        ['label' => 'Motor Kapak',           'name' => 'Motor Kapak',   'measure' => fn($w,$h,$q) => $w - 15,                                            'qty' => fn($w,$h,$q) => $q],
        ['label' => 'Alt Kasa',              'name' => 'Alt Kasa',      'measure' => fn($w,$h,$q) => $w,                                                 'qty' => fn($w,$h,$q) => $q],
        ['label' => 'Tutamak',               'name' => 'Tutamak',       'measure' => fn($w,$h,$q) => $w - 185,                                           'qty' => fn($w,$h,$q) => 6*$q],
        ['label' => 'Kenetli Baza',          'name' => 'Kenetli Baza',  'measure' => fn($w,$h,$q) => $w - 185,                                           'qty' => fn($w,$h,$q) => 3*$q],
        ['label' => 'Küpeşte Bazası',        'name' => 'Küpeşte Bazası',  'measure' => fn($w,$h,$q) => $w - 185,                                           'qty' => fn($w,$h,$q) => 2*$q],
        ['label' => 'Küpeşte',               'name' => 'Küpeşte',       'measure' => fn($w,$h,$q) => $w - 185,                                           'qty' => fn($w,$h,$q) => $q],
        ['label' => 'Yatay Tek Cam Çıtası',  'name' => 'Yatay Tek Cam Çıtası',    'measure' => fn($w,$h,$q) => ($w - 185) - 52,                                    'qty' => fn($w,$h,$q) => 11*$q],
        ['label' => 'Dikey Tek Cam Çıtası',  'name' => 'Dikey Tek Cam Çıtası',    'measure' => fn($w,$h,$q) => (($h - 290) / 3) - 5,                               'qty' => fn($w,$h,$q) => 11*$q],
        ['label' => 'Dikme',                 'name' => 'Dikme',         'measure' => fn($w,$h,$q) => $h - 166,                                           'qty' => fn($w,$h,$q) => 2*$q],
        ['label' => 'Orta Dikme',            'name' => 'Orta Dikme',    'measure' => fn($w,$h,$q) => $h - 166,                                           'qty' => fn($w,$h,$q) => 2*$q],
        ['label' => 'Son Kapatma',           'name' => 'Son Kapatma',   'measure' => fn($w,$h,$q) => $h - (($h - 290)/3) - 221,                          'qty' => fn($w,$h,$q) => 2*$q],
        ['label' => 'Kanat',                 'name' => 'Kanat',         'measure' => fn($w,$h,$q) => ($h - 290) / 3,                                      'qty' => fn($w,$h,$q) => 2*$q],
        ['label' => 'Dikey Baza',            'name' => 'Dikey Baza',    'measure' => fn($w,$h,$q) => ($h - 290) / 3,                                      'qty' => fn($w,$h,$q) => 4*$q],
        ['label' => 'Zincir',                'name' => 'Zincir',        'measure' => fn($w,$h,$q) => $h - (($h - 290)/3) - 221 + 600,                    'qty' => fn($w,$h,$q) => 2*$q],
        ['label' => 'Flatbelt Kayış',        'name' => 'Flatbelt Kayış',      'measure' => fn($w,$h,$q) => $h - (($h - 290)/3) - 221 + 600,                    'qty' => fn($w,$h,$q) => 2*$q],
        ['label' => 'Motor Borusu',          'name' => 'Motor Borusu',  'measure' => fn($w,$h,$q) => $w - 59,                                            'qty' => fn($w,$h,$q) => $q],
        ['label' => 'Motor Kutu Contası',    'name' => 'Motor Kutu Contası','measure' => fn($w,$h,$q) => ($w - 14)*$q + $w*$q,                                'qty' => fn($w,$h,$q) => 1],
        ['label' => 'Kanat Contası',         'name' => 'Kanat Contası',     'measure' => fn($w,$h,$q) => (($h - 290)/3)*$q*2,                                 'qty' => fn($w,$h,$q) => 1],
    ];

    $pStmt = $pdo->prepare('SELECT product_code, name, unit, unit_price, vat_rate, weight_per_meter FROM products WHERE LOWER(name) = LOWER(:name)');

    $lines = [];
    $base = 0.0;

    foreach ($rules as $rule) {
        $measure = max(0, $rule['measure']($width, $height, $qty));
        $rq = max(0, $rule['qty']($width, $height, $qty));
        if ($measure <= 0 || $rq <= 0) {
            $alerts[] = $rule['label'] . ' için geçersiz ölçü/adet.';
            continue;
        }
        $pStmt->execute([':name' => $rule['name']]);
        $product = $pStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            $alerts[] = 'Ürün bulunamadı: ' . $rule['name'];
            continue;
        }
        $unit = strtolower($product['unit'] ?? '');
        $unitPrice = (float)($product['unit_price'] ?? 0);
        $vatRate = (float)($product['vat_rate'] ?? 0);
        $unitPriceVat = $unitPrice * (1 + $vatRate / 100);

        $meters = null;
        $kg = null;
        $calcType = 'per_piece';
        $lineTotal = 0.0;

        switch ($unit) {
            case 'kilogram':
            case 'kg':
            case 'kg/m':
                $calcType = 'kg_by_length';
                $wpm = (float)($product['weight_per_meter'] ?? 0);
                if ($wpm <= 0) {
                    $alerts[] = $rule['label'] . ' için weight_per_meter eksik.';
                    continue 2;
                }
                $meters = ($measure / 1000) * $rq;
                $kg = $meters * $wpm;
                $lineTotal = $kg * $unitPriceVat;
                break;
            case 'metre':
            case 'm':
                $calcType = 'per_meter';
                $meters = ($measure / 1000) * $rq;
                $lineTotal = $meters * $unitPriceVat;
                break;
            case 'metrekare':
            case 'm²':
            case 'm2':
                $calcType = 'area_m2';
                $area = ($width * $height / 1000000) * $rq;
                $lineTotal = $area * $unitPriceVat;
                break;
            default:
                $calcType = 'per_piece';
                $lineTotal = $rq * $unitPriceVat;
        }

        $lines[] = [
            'name'       => $product['name'],
            'code'       => $product['product_code'],
            'calc'       => $calcType,
            'measure'    => $measure,
            'qty'        => $rq,
            'meters'     => $meters,
            'kg'         => $kg,
            'unit_price' => $unitPriceVat,
            'line_total' => $lineTotal,
        ];
        $base += $lineTotal;
    }

    $profitRate = (float)($row['profit_rate'] ?? 0);
    $profit = $base * ($profitRate / 100);
    $total = $base + $profit;

    return ['lines' => $lines, 'base' => $base, 'profitRate' => $profitRate, 'profit' => $profit, 'total' => $total];
}

$alerts = [];
$allLines = [];
$baseTotal = 0.0;
$profitAmount = 0.0;
$totalAmount = 0.0;
$systemResults = [];
foreach ($systems as $sys) {
    $res = computeSystem($sys, $pdo, $alerts);
    $systemResults[$sys['id']] = $res;
    $allLines = array_merge($allLines, $res['lines']);
    $baseTotal += $res['base'];
    $profitAmount += $res['profit'];
    $totalAmount += $res['total'];
}
$displayProfitRate = count($systems) === 1 ? $systemResults[$systems[0]['id']]['profitRate'] : null;

// Handle apply totals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($role === 'admin') && ($_POST['action'] ?? '') === 'apply') {
    $token = $_POST['csrf_token'] ?? '';
    $postId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!hash_equals($csrfToken, $token) || $postId !== $id) {
        $alerts[] = 'Geçersiz CSRF tokenı.';
    } else {
        try {
            $pdo->beginTransaction();
            $upd = $pdo->prepare('UPDATE guillotinesystems SET profit_amount = :p, total_amount = :t WHERE id = :id');
            foreach ($systems as $sys) {
                $res = computeSystem($sys, $pdo, $tmp = []);
                $upd->execute([':p' => $res['profit'], ':t' => $res['total'], ':id' => $sys['id']]);
            }
            $sumG = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) FROM guillotinesystems WHERE general_offer_id = :id');
            $sumG->execute([':id' => $id]);
            $gTotal = (float)$sumG->fetchColumn();
            $sumS = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) FROM slidingsystems WHERE general_offer_id = :id');
            $sumS->execute([':id' => $id]);
            $sTotal = (float)$sumS->fetchColumn();
            $overall = $gTotal + $sTotal;
            $pdo->prepare('UPDATE generaloffers SET total_amount = :t WHERE id = :id')->execute([':t' => $overall, ':id' => $id]);
            $pdo->commit();
            $_SESSION['flash_success'] = 'Toplamlar uygulandı.';
            header('Location: quotation_view.php?id=' . $id . '&success=1');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $alerts[] = 'Toplamlar uygulanamadı.';
        }
    }
}
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Optimizasyon Sonucu</h2>
        <div>
            <a href="quotation_view.php?id=<?= e((string)$id) ?>" class="btn btn-secondary">Back to Quotation</a>
            <?php if ($role === 'admin'): ?>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="apply">
                    <input type="hidden" name="id" value="<?= e((string)$id) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <button type="submit" class="btn btn-primary">Apply Totals</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php foreach ($alerts as $msg): ?>
        <div class="alert alert-warning"><?= e($msg) ?></div>
    <?php endforeach; ?>

    <div class="card mb-4">
        <div class="card-body">
            <p><strong>Teklif ID:</strong> <?= e((string)$offer['id']) ?></p>
            <p><strong>Müşteri:</strong> <?= e(trim(($offer['customer_company'] ? $offer['customer_company'] : $offer['first_name'] . ' ' . $offer['last_name']))) ?></p>
            <p><strong>Montaj Tipi:</strong> <?= e($offer['assembly_type'] ?? '') ?></p>
            <table class="table table-sm">
                <thead><tr><th>Sistem</th><th>Genişlik (mm)</th><th>Yükseklik (mm)</th><th>Adet</th></tr></thead>
                <tbody>
                <?php foreach ($systems as $idx => $s): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td><?= e((string)$s['width']) ?></td>
                        <td><?= e((string)$s['height']) ?></td>
                        <td><?= e((string)$s['quantity']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th>Ürün</th>
                    <th>Kod</th>
                    <th>Hesap Türü</th>
                    <th>Ölçü (mm)</th>
                    <th>Adet</th>
                    <th>Metre</th>
                    <th>Kg</th>
                    <th>Birim Fiyat (KDV dahil)</th>
                    <th>Satır Tutarı (TRY)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allLines as $line): ?>
                    <tr>
                        <td><?= e($line['name']) ?></td>
                        <td><?= e($line['code']) ?></td>
                        <td><?= e($line['calc']) ?></td>
                        <td><?= $line['measure'] !== null ? e((string)number_format($line['measure'], 2, ',', '.')) : '' ?></td>
                        <td><?= e((string)$line['qty']) ?></td>
                        <td><?= $line['meters'] !== null ? e((string)number_format($line['meters'], 2, ',', '.')) : '' ?></td>
                        <td><?= $line['kg'] !== null ? e((string)number_format($line['kg'], 2, ',', '.')) : '' ?></td>
                        <td class="text-end"><?= e(formatCurrency($line['unit_price'])) ?></td>
                        <td class="text-end"><?= e(formatCurrency($line['line_total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="8" class="text-end">Ara Toplam</th>
                    <th class="text-end"><?= e(formatCurrency($baseTotal)) ?></th>
                </tr>
                <tr>
                    <th colspan="8" class="text-end">Kar Payı (%)</th>
                    <th class="text-end"><?= $displayProfitRate !== null ? e((string)$displayProfitRate) : '-' ?></th>
                </tr>
                <tr>
                    <th colspan="8" class="text-end">Kar Tutarı</th>
                    <th class="text-end"><?= e(formatCurrency($profitAmount)) ?></th>
                </tr>
                <tr>
                    <th colspan="8" class="text-end">Toplam</th>
                    <th class="text-end"><?= e(formatCurrency($totalAmount)) ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
</body>
</html>
