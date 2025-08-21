<?php
require __DIR__ . '/header.php';
require_once __DIR__ . '/guillotine_calculator.php';

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

$stmt = $pdo->prepare('SELECT * FROM guillotinesystems WHERE id = :id');
$stmt->execute([':id' => $quoteId]);
$system = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$system) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Giyotin sistemi bulunamadı.</div></div>';
    require __DIR__ . '/footer.php';
    exit;
}


$totals = calculateGuillotineCategoryTotals($pdo, $system);
$orderedCats = ['Alüminyum','Alüminyum Boyalı','Alüminyum Fire','Aksesuar','Fitil','Cam','İşçilik','Montaj','Diğer'];
$catTotals    = array_merge(array_fill_keys($orderedCats, 0), $totals['categories']);
$catQtyTotals = array_merge(array_fill_keys($orderedCats, 0), $totals['quantities']);
$itemsByCat = [];
foreach ($totals['items'] as $it) {
    $itemsByCat[$it['category']][] = $it;
}
$includedForTotal = ['Alüminyum Boyalı','Alüminyum Fire','Aksesuar','Fitil','Cam','İşçilik'];
$total = 0;
foreach ($includedForTotal as $c) {
    $total += $catTotals[$c] ?? 0;
}
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
                    <th>Kalem</th>
                    <th class="text-end">Ölçü</th>
                    <th>Birim</th>
                    <th class="text-end">Birim Değeri</th>
                    <th class="text-end">Genişlik (mm)</th>
                    <th class="text-end">Yükseklik (mm)</th>
                    <th class="text-end">Adet</th>
                    <th class="text-end">Toplam m²</th>
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
                              <td class="text-end"><?= e(isset($item['width']) ? fmtFlex($item['width']) : '') ?></td>
                              <td class="text-end"><?= e(isset($item['height']) ? fmtFlex($item['height']) : '') ?></td>
                              <td class="text-end"><?= e(isset($item['count']) ? fmtFlex($item['count']) : '') ?></td>
                              <td class="text-end"><?= e(isset($item['area']) ? fmtFlex($item['area']) : '') ?></td>
                              <td class="text-end"><?= e(fmtFlex($item['total'], '', true)) ?> ₺</td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-light">
                        <th colspan="8" class="text-end">Toplam <?= e($label) ?></th>
                        <th class="text-end"><?= e(fmtFlex($catQtyTotals[$label] ?? 0)) ?></th>
                        <th class="text-end"><?= e(fmtFlex($catTotals[$label] ?? 0, '', true)) ?> ₺</th>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="9">Genel Toplam</th>
                      <th class="text-end"><?= e(fmtFlex($total, '', true)) ?> ₺</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
