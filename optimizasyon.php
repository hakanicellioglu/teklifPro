<?php
require __DIR__ . '/header.php';

function e(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

// Retrieve offer id
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    echo '<div class="container py-4"><div class="alert alert-danger">Teklif bulunamadı.</div></div>';
    require __DIR__ . '/footer.php';
    exit;
}

// Fetch systems for the offer
$stmt = $pdo->prepare('SELECT id, width, height, quantity FROM guillotinesystems WHERE general_offer_id = :id');
$stmt->execute([':id' => $id]);
$systems = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$systems) {
    echo '<div class="container py-4"><div class="alert alert-warning">Bu teklife ait giyotin sistemi bulunamadı.</div></div>';
    require __DIR__ . '/footer.php';
    exit;
}

// Rules for calculating parts
$rules = [
    'Motor Kutusu'         => fn($w, $h, $q) => [$w - 14, $q],
    'Motor Kapak'          => fn($w, $h, $q) => [$w - 15, $q],
    'Alt Kasa'             => fn($w, $h, $q) => [$w, $q],
    'Tutamak'              => fn($w, $h, $q) => [$w - 185, 2 * $q],
    'Kenetli Baza'         => fn($w, $h, $q) => [$w - 185, 2 * $q],
    'Küpeşte Bazası'       => fn($w, $h, $q) => [$w - 185, 2 * $q],
    'Küpeşte'              => fn($w, $h, $q) => [$w - 185, $q],
    'Yatay Tek Cam Çıtası' => fn($w, $h, $q) => [($w - 185) - 52, 6 * $q],
    'Dikey Tek Cam Çıtası' => fn($w, $h, $q) => [(($h - 291) / 3) - 5, 6 * $q],
    'Dikme'                => fn($w, $h, $q) => [$h - 166, 2 * $q],
    'Orta Dikme'           => fn($w, $h, $q) => [$h - 166, 2 * $q],
    'Son Kapatma'          => fn($w, $h, $q) => [$h - (($h - 290) / 3) - 221, 2 * $q],
    'Kanat'                => fn($w, $h, $q) => [($h - 290) / 3, 2 * $q],
    'Dikey Baza'           => fn($w, $h, $q) => [($h - 290) / 3, 4 * $q],
    'Zincir'               => fn($w, $h, $q) => [$h - (($h - 290) / 3) - 221 + 600, 2 * $q],
    'Motor Borusu'         => fn($w, $h, $q) => [$w - 59, $q],
    'Motor Kutu Contası'   => fn($w, $h, $q) => [$w * $q * 2, 1],
    'Kanat Contası'        => fn($w, $h, $q) => [(($h - 290) / 3) * 2, $q],
    'Kıl Fitil'            => fn($w, $h, $q) => [(($w - 183) * 4) + (($h - 166) * 8) + ((($h - 290) / 3) * 2), $q],
];

$pStmt = $pdo->prepare('SELECT weight_per_meter, image_url, category FROM products WHERE LOWER(name) = LOWER(:name)');

$aggregated = [];
foreach ($systems as $sys) {
    $w = (float)$sys['width'];
    $h = (float)$sys['height'];
    $q = (int)$sys['quantity'];
    foreach ($rules as $name => $fn) {
        [$measure, $qty] = $fn($w, $h, $q);
        if ($measure <= 0 || $qty <= 0) {
            continue;
        }
        $totalLength = $measure * $qty; // mm
        $pStmt->execute([':name' => $name]);
        $prod = $pStmt->fetch(PDO::FETCH_ASSOC);
        $wpm = (float)($prod['weight_per_meter'] ?? 0);
        $imageUrl = $prod['image_url'] ?? null;
        $category = $prod['category'] ?? null;
        $totalKg = $wpm * $totalLength / 1000; // convert mm to m
        if (!isset($aggregated[$name])) {
            $aggregated[$name] = [
                'name'   => $name,
                'length' => 0.0,
                'qty'    => 0,
                'kg'     => 0.0,
                'image'  => $imageUrl,
                'category' => $category,
            ];
        } else {
            if (!$aggregated[$name]['image'] && $imageUrl) {
                $aggregated[$name]['image'] = $imageUrl;
            }
            if (empty($aggregated[$name]['category']) && $category) {
                $aggregated[$name]['category'] = $category;
            }
        }
        $aggregated[$name]['length'] += $totalLength;
        $aggregated[$name]['qty'] += $qty;
        $aggregated[$name]['kg'] += $totalKg;
    }
}
?>
<style>
@media print {
    .no-print {
        display: none !important;
    }
}
</style>
<div class="container py-4">
    <button type="button" class="btn btn-secondary my-3 no-print" onclick="window.close();">Geri Dön</button>
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <h1 class="mb-4">Optimizasyon Sonucu</h1>
        <div class="no-print">
            <button type="button" class="btn btn-primary ms-2" onclick="window.print();">Yazdır</button>
            <a href="pdf/render_optimization_pdf.php?id=<?= e((string)$id) ?>" class="btn btn-secondary ms-2" target="_blank">
                <i class="bi bi-file-earmark-pdf"></i> PDF İndir
            </a>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-md-3 g-4">
        <?php foreach ($aggregated as $row):
            $unit = $row['qty'] ? $row['length'] / $row['qty'] : 0;
        ?>
            <div class="col">
                <div class="card h-100">
                    <?php if (!empty($row['image'])): ?>
                        <img src="<?= e($row['image']) ?>" class="card-img-top" style="width: 150px; height: 150px; margin-left: auto; margin-right: auto; display: block;" alt="<?= e($row['name']) ?>">
                    <?php endif; ?>
                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center list-unstyled">
                            <h5 class="card-title mb-1"><?= e($row['name']) ?></h5>
                            <li><span class="bg-primary px-2 py-1 text-white rounded small"><?= e($row['category'] ?? '') ?></span></li>
                        </div>
                        <ul class="list-unstyled mb-0">
                            <?php $cat = strtolower($row['category'] ?? ''); ?>
                            <?php if ($cat === 'alüminyum'): ?>
                                <li>Adet: <?= e((string)$row['qty']) ?></li>
                                <li>Toplam Kg: <?= e(number_format($row['kg'], 3, ',', '.')) ?></li>
                            <?php elseif ($cat === 'aksesuar' || $cat === 'fitil'): ?>
                                <li>Birim Uzunluk: <?= e(number_format($unit / 1000, 2, ',', '.')) ?> m</li>
                                <li>Toplam Uzunluk: <?= e(number_format($row['length'] / 1000, 2, ',', '.')) ?> m</li>
                            <?php else: ?>
                                <li>Birim Uzunluk: <?= e((int)round($unit)) ?> mm</li>
                                <li>Toplam Uzunluk: <?= e((int)round($row['length'])) ?> mm</li>
                                <li>Adet: <?= e((string)$row['qty']) ?></li>
                                <li>Toplam Kg: <?= e(number_format($row['kg'], 3, ',', '.')) ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/footer.php';
