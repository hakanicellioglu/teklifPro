<?php
require __DIR__ . '/header.php';

function e(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$quoteId = filter_input(INPUT_GET, 'quote_id', FILTER_VALIDATE_INT);
if (!$quoteId) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Geçersiz giyotin ID.</div></div>';
    require __DIR__ . '/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM guillotinesystems WHERE id = :id');
$stmt->execute([':id' => $quoteId]);
$system = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$system) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Giyotin sistemi bulunamadı.</div></div>';
    require __DIR__ . '/footer.php';
    exit;
}

function calculateGuillotineCategoryTotals(PDO $pdo, array $row): array
{
    $width  = max(0, (float)($row['width'] ?? 0));
    $height = max(0, (float)($row['height'] ?? 0));
    $qty    = max(0, (int)($row['quantity'] ?? 0));

    $rules = [
        ['name' => 'Motor Kutusu',        'measure' => fn($w,$h,$q) => $w - 14,                        'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Motor Kapak',         'measure' => fn($w,$h,$q) => $w - 15,                        'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Alt Kasa',            'measure' => fn($w,$h,$q) => $w,                              'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Tutamak',             'measure' => fn($w,$h,$q) => $w - 185,                        'qty' => fn($w,$h,$q) => 6*$q],
        ['name' => 'Kenetli Baza',        'measure' => fn($w,$h,$q) => $w - 185,                        'qty' => fn($w,$h,$q) => 3*$q],
        ['name' => 'Küpeşte Bazası',      'measure' => fn($w,$h,$q) => $w - 185,                        'qty' => fn($w,$h,$q) => 2*$q],
        ['name' => 'Küpeşte',             'measure' => fn($w,$h,$q) => $w - 185,                        'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Yatay Tek Cam Çıtası','measure' => fn($w,$h,$q) => ($w - 185) - 52,                'qty' => fn($w,$h,$q) => 11*$q],
        ['name' => 'Dikey Tek Cam Çıtası','measure' => fn($w,$h,$q) => (($h - 290) / 3) - 5,           'qty' => fn($w,$h,$q) => 11*$q],
        ['name' => 'Dikme',               'measure' => fn($w,$h,$q) => $h - 166,                        'qty' => fn($w,$h,$q) => 2*$q],
        ['name' => 'Orta Dikme',          'measure' => fn($w,$h,$q) => $h - 166,                        'qty' => fn($w,$h,$q) => 2*$q],
        ['name' => 'Son Kapatma',         'measure' => fn($w,$h,$q) => $h - (($h - 290)/3) - 221,       'qty' => fn($w,$h,$q) => 2*$q],
        ['name' => 'Kanat',               'measure' => fn($w,$h,$q) => ($h - 290) / 3,                  'qty' => fn($w,$h,$q) => 2*$q],
        ['name' => 'Dikey Baza',          'measure' => fn($w,$h,$q) => ($h - 290) / 3,                  'qty' => fn($w,$h,$q) => 4*$q],
        ['name' => 'Zincir',              'measure' => fn($w,$h,$q) => $h - (($h - 290)/3) - 221 + 600, 'qty' => fn($w,$h,$q) => 2*$q],
        ['name' => 'Flatbelt Kayış',      'measure' => fn($w,$h,$q) => $h - (($h - 290)/3) - 221 + 600, 'qty' => fn($w,$h,$q) => 2*$q],
        ['name' => 'Motor Borusu',        'measure' => fn($w,$h,$q) => $w - 59,                         'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Motor Kutu Contası',  'measure' => fn($w,$h,$q) => ($w - 14)*$q + $w*$q,            'qty' => fn($w,$h,$q) => 1],
        ['name' => 'Kanat Contası',       'measure' => fn($w,$h,$q) => (($h - 290)/3)*$q*2,             'qty' => fn($w,$h,$q) => 1],
    ];

    $pStmt = $pdo->prepare('SELECT unit, unit_price, vat_rate, weight_per_meter, category FROM products WHERE LOWER(name) = LOWER(:name)');

    $categoryTotals = [];
    $base        = 0.0;
    $aluminumKg  = 0.0;

    foreach ($rules as $rule) {
        $measure = max(0, $rule['measure']($width, $height, $qty));
        $rq      = max(0, $rule['qty']($width, $height, $qty));
        if ($measure <= 0 || $rq <= 0) {
            continue;
        }
        $pStmt->execute([':name' => $rule['name']]);
        if ($p = $pStmt->fetch(PDO::FETCH_ASSOC)) {
            $unitPrice   = (float)($p['unit_price'] ?? 0);
            $vatRate     = (float)($p['vat_rate'] ?? 0);
            $unitPriceVat = $unitPrice * (1 + $vatRate / 100);
            $unit        = strtolower($p['unit'] ?? '');
            $lineTotal   = 0.0;
            $kg          = 0.0;
            switch ($unit) {
                case 'kilogram':
                case 'kg':
                case 'kg/m':
                    $wpm = (float)($p['weight_per_meter'] ?? 0);
                    if ($wpm <= 0) {
                        continue 2;
                    }
                    $meters    = ($measure / 1000) * $rq;
                    $kg        = $meters * $wpm;
                    $lineTotal = $kg * $unitPriceVat;
                    break;
                case 'metre':
                case 'm':
                    $meters    = ($measure / 1000) * $rq;
                    $lineTotal = $meters * $unitPriceVat;
                    break;
                case 'metrekare':
                case 'm²':
                case 'm2':
                    $area      = ($width * $height / 1000000) * $rq;
                    $lineTotal = $area * $unitPriceVat;
                    break;
                default:
                    $lineTotal = $rq * $unitPriceVat;
            }
            $base += $lineTotal;
            $cat = trim((string)($p['category'] ?? ''));
            $cat = $cat !== '' ? $cat : 'Diğer';
            $categoryTotals[$cat] = ($categoryTotals[$cat] ?? 0) + $lineTotal;
            if (strtolower($cat) === 'alüminyum') {
                $aluminumKg += $kg;
            }
        }
    }

    $kgPainted = $aluminumKg * 1.01;
    $alPaint   = $kgPainted * 200;
    $alWaste   = $kgPainted * 0.07 * 200;
    $base     += $alPaint + $alWaste;
    $categoryTotals['Alüminyum'] = ($categoryTotals['Alüminyum'] ?? 0) + $alPaint + $alWaste;

    $area      = ($width * $height * $qty) / 1000000;
    $laborCost = $area * 40;
    $base     += $laborCost;
    $categoryTotals['İşçilik'] = ($categoryTotals['İşçilik'] ?? 0) + $laborCost;

    $rate   = (float)($row['profit_rate'] ?? $row['profit_margin'] ?? 0);
    $profit = $base * ($rate / 100);
    $total  = $base + $profit;
    $categoryTotals['Diğer'] = ($categoryTotals['Diğer'] ?? 0) + $profit;

    return ['categories' => $categoryTotals, 'total' => $total];
}

$totals = calculateGuillotineCategoryTotals($pdo, $system);
$orderedCats = ['Alüminyum','Aksesuar','Fitil','Cam','İşçilik','Montaj','Diğer'];
$catTotals = array_merge(array_fill_keys($orderedCats, 0), $totals['categories']);
$total = $totals['total'];
$backUrl = 'quotation_view.php?id=' . urlencode((string)($system['general_offer_id'] ?? ''));
?>
<div class="container mt-4">
    <a href="<?= e($backUrl) ?>" class="btn btn-sm btn-secondary mb-3">&larr; Geri Dön</a>
    <h3>Giyotin Teklif Kalemleri</h3>
    <div class="table-responsive">
        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th class="text-end">Tutar (₺)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orderedCats as $label): ?>
                <tr>
                    <td><?= e($label) ?></td>
                    <td class="text-end"><?= e(number_format($catTotals[$label] ?? 0, 2, ',', '.')) ?> ₺</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>Genel Toplam</th>
                    <th class="text-end"><?= e(number_format($total, 2, ',', '.')) ?> ₺</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>

