<?php
require __DIR__ . '/header.php';

function e(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function fmtFlex($v, string $unit = '', bool $currency = false): string
{
    if (!is_numeric($v)) {
        return '-';
    }
    $v = (float)$v;
    if ($currency) {
        return number_format($v, 2, ',', '.');
    }
    $unit = strtolower(trim($unit));
    if (in_array($unit, ['kg', 'kilogram', 'kg/m', 'm', 'metre', 'm²', 'm2'], true)) {
        $formatted = number_format($v, 3, ',', '.');
    } else {
        $formatted = number_format($v, 2, ',', '.');
    }
    $formatted = rtrim($formatted, '0');
    $formatted = rtrim($formatted, ',');
    return $formatted;
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

$stmt = $pdo->prepare('SELECT width, height, quantity, glass_type FROM guillotinesystems WHERE id = :id');
$stmt->execute([':id' => $quoteId]);
$system = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$system) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Giyotin sistemi bulunamadı.</div></div>';
    require __DIR__ . '/footer.php';
    exit;
}

$glassTypeRaw = (string)($system['glass_type'] ?? '');
error_log('glass_type from query: ' . $glassTypeRaw);
$glassType    = function_exists('mb_strtolower') ? mb_strtolower($glassTypeRaw, 'UTF-8') : strtolower($glassTypeRaw);
$isInsulated  = in_array($glassType, ['ısıcam', 'isicam'], true);
$isSingle     = in_array($glassType, ['tek cam', 'tekcam'], true);
if ($glassTypeRaw === '' || (!$isInsulated && !$isSingle)) {
    $glassDebug = '<div class="alert alert-warning">Cam tipi belirlenemedi: ' . e($glassTypeRaw) . '</div>';
} else {
    $glassDebug = '<div class="alert alert-warning">Cam tipi: ' . e($glassTypeRaw) . ' (' . ($isInsulated ? 'ısıcam' : 'tek cam') . ')</div>';
}

function calculateGuillotineCategoryTotals(PDO $pdo, array $row, bool $isInsulated): array
{
    $width  = max(0, (float)($row['width'] ?? 0));
    $height = max(0, (float)($row['height'] ?? 0));
    $qty    = max(0, (int)($row['quantity'] ?? 0));

    $rules = [
        ['name' => 'Motor Kutusu',        'measure' => fn($w,$h,$q) => $w - 14,                        'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Motor Kapak',         'measure' => fn($w,$h,$q) => $w - 15,                        'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Alt Kasa',            'measure' => fn($w,$h,$q) => $w,                              'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Tutamak',             'measure' => fn($w,$h,$q) => $w - 185,                        'qty' => fn($w,$h,$q) => $q],
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

    $categoryTotals   = [];
    $categoryQtyTotals = [];
    $items            = [];
    $base             = 0.0;
    $aluminumKg       = 0.0;

    foreach ($rules as $rule) {
        if ($isInsulated && in_array($rule['name'], ['Yatay Tek Cam Çıtası', 'Dikey Tek Cam Çıtası'], true)) {
            continue;
        }
        $measure = max(0, $rule['measure']($width, $height, $qty));
        $rq      = max(0, $rule['qty']($width, $height, $qty));
        if ($measure <= 0 || $rq <= 0) {
            continue;
        }
        $pStmt->execute([':name' => $rule['name']]);
        if ($p = $pStmt->fetch(PDO::FETCH_ASSOC)) {
            $unitPrice    = (float)($p['unit_price'] ?? 0);
            $vatRate      = (float)($p['vat_rate'] ?? 0);
            $unitPriceVat = $unitPrice * (1 + $vatRate / 100);
            $unitRaw      = (string)($p['unit'] ?? '');
            $unit         = strtolower($unitRaw);
            $lineTotal    = 0.0;
            $qtyDisplay   = 0.0;
            $kg           = 0.0;
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
                    $qtyDisplay = $kg;
                    $lineTotal  = $kg * $unitPriceVat;
                    break;
                case 'metre':
                case 'm':
                    $meters     = ($measure / 1000) * $rq;
                    $qtyDisplay = $meters;
                    $lineTotal  = $meters * $unitPriceVat;
                    break;
                case 'metrekare':
                case 'm²':
                case 'm2':
                    $area       = ($width * $height / 1000000) * $rq;
                    $qtyDisplay = $area;
                    $lineTotal  = $area * $unitPriceVat;
                    break;
                default:
                    $qtyDisplay = $rq;
                    $lineTotal  = $rq * $unitPriceVat;
            }
            $cat = trim((string)($p['category'] ?? ''));
            $cat = $cat !== '' ? $cat : 'Diğer';
            if (in_array($cat, ['Alüminyum', 'Alüminyum Boyalı', 'Alüminyum Fire'], true)) {
                if ($kg <= 0) {
                    $wpm = (float)($p['weight_per_meter'] ?? 0);
                    if ($wpm > 0 && ($unit === 'm' || $unit === 'metre')) {
                        $meters     = ($measure / 1000) * $rq;
                        $kg         = $meters * $wpm;
                        $qtyDisplay = $kg;
                    }
                }
                $lineTotal = $qtyDisplay * 200;
            }
            $base += $lineTotal;
            $categoryTotals[$cat]    = ($categoryTotals[$cat] ?? 0) + $lineTotal;
            $categoryQtyTotals[$cat] = ($categoryQtyTotals[$cat] ?? 0) + $qtyDisplay;
            if (strtolower($cat) === 'alüminyum') {
                $aluminumKg += $kg;
            }
            $items[] = [
                'category' => $cat,
                'name'     => $rule['name'],
                'measure'  => $measure,
                'unit'     => $unitRaw,
                'quantity' => $qtyDisplay,
                'total'    => $lineTotal,
            ];
        }
    }

    $kgPainted   = $aluminumKg * 1.01;
    $alPaintCost = $kgPainted * 200;
    $alWasteQty  = $kgPainted * 0.07;
    $alWasteCost = $alWasteQty * 200;
    $base += $alPaintCost + $alWasteCost;
    $categoryTotals['Alüminyum Boyalı']    = ($categoryTotals['Alüminyum Boyalı'] ?? 0) + $alPaintCost;
    $categoryQtyTotals['Alüminyum Boyalı'] = ($categoryQtyTotals['Alüminyum Boyalı'] ?? 0) + $kgPainted;
    $categoryTotals['Alüminyum Fire']     = ($categoryTotals['Alüminyum Fire'] ?? 0) + $alWasteCost;
    $categoryQtyTotals['Alüminyum Fire']  = ($categoryQtyTotals['Alüminyum Fire'] ?? 0) + $alWasteQty;
    $items[] = [
        'category' => 'Alüminyum Boyalı',
        'name'     => 'Alüminyum Boyalı',
        'measure'  => null,
        'unit'     => 'kg',
        'quantity' => $kgPainted,
        'total'    => $alPaintCost,
    ];
    $items[] = [
        'category' => 'Alüminyum Fire',
        'name'     => 'Alüminyum Fire',
        'measure'  => null,
        'unit'     => 'kg',
        'quantity' => $alWasteQty,
        'total'    => $alWasteCost,
    ];

    $area      = ($width * $height * $qty) / 1000000;
    $laborCost = $area * 40;
    $base     += $laborCost;
    $categoryTotals['İşçilik']    = ($categoryTotals['İşçilik'] ?? 0) + $laborCost;
    $categoryQtyTotals['İşçilik'] = ($categoryQtyTotals['İşçilik'] ?? 0) + $area;
    $items[] = [
        'category' => 'İşçilik',
        'name'     => 'İşçilik',
        'measure'  => null,
        'unit'     => 'm²',
        'quantity' => $area,
        'total'    => $laborCost,
    ];

    $rate   = (float)($row['profit_rate'] ?? $row['profit_margin'] ?? 0);
    $profit = $base * ($rate / 100);
    $total  = $base + $profit;
    $categoryTotals['Diğer']    = ($categoryTotals['Diğer'] ?? 0) + $profit;
    $categoryQtyTotals['Diğer'] = ($categoryQtyTotals['Diğer'] ?? 0) + 1;
    $items[] = [
        'category' => 'Diğer',
        'name'     => 'Kâr',
        'measure'  => null,
        'unit'     => '',
        'quantity' => 1,
        'total'    => $profit,
    ];

    return [
        'categories' => $categoryTotals,
        'quantities' => $categoryQtyTotals,
        'items'      => $items,
        'total'      => $total,
    ];
}

$totals = calculateGuillotineCategoryTotals($pdo, $system, $isInsulated);
$orderedCats = ['Alüminyum','Alüminyum Boyalı','Alüminyum Fire','Aksesuar','Fitil','Cam','İşçilik','Montaj','Diğer'];
$catTotals    = array_merge(array_fill_keys($orderedCats, 0), $totals['categories']);
$catQtyTotals = array_merge(array_fill_keys($orderedCats, 0), $totals['quantities']);
$itemsByCat = [];
foreach ($totals['items'] as $it) {
    $itemsByCat[$it['category']][] = $it;
}
$total = $totals['total'];
$backUrl = 'quotation_view.php?id=' . urlencode((string)($system['general_offer_id'] ?? ''));
?>
<div class="container mt-4">
    <?= $glassDebug ?>
    <a href="<?= e($backUrl) ?>" class="btn btn-sm btn-secondary mb-3">&larr; Geri Dön</a>
    <h3>Giyotin Teklif Kalemleri</h3>
    <div class="table-responsive">
        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th>Kalem</th>
                    <th class="text-end">Ölçü</th>
                    <th>Birim</th>
                    <th class="text-end">Birim Değeri</th>
                    <th class="text-end">Tutar (₺)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orderedCats as $label): ?>
                <?php if (!empty($itemsByCat[$label])): ?>
                    <?php foreach ($itemsByCat[$label] as $item): ?>
                        <tr>
                            <td><?= e($label) ?></td>
                            <td><?= e($item['name']) ?></td>
                              <td class="text-end"><?= e(fmtFlex($item['measure'], $item['unit'])) ?></td>
                              <td><?= e($item['unit']) ?></td>
                              <td class="text-end"><?= e(fmtFlex($item['quantity'], $item['unit'])) ?></td>
                              <td class="text-end"><?= e(fmtFlex($item['total'], '', true)) ?> ₺</td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-light">
                        <th colspan="4" class="text-end">Toplam <?= e($label) ?></th>
                        <th class="text-end"><?= e(fmtFlex($catQtyTotals[$label] ?? 0)) ?></th>
                        <th class="text-end"><?= e(fmtFlex($catTotals[$label] ?? 0, '', true)) ?> ₺</th>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5">Genel Toplam</th>
                      <th class="text-end"><?= e(fmtFlex($total, '', true)) ?> ₺</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
