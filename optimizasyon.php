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
$currentDate = date('d.m.Y');
?>
<style>
    @media print {
        body {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        nav, footer.container, .btn, .navbar, .modal, #toastContainer { display: none !important; }
        main.container { margin: 0; padding: 0; max-width: none; }
        .print-header, .print-footer {
            position: fixed;
            left: 0;
            right: 0;
            background: #fff;
            color: #000;
        }
        .print-header {
            top: 0;
            text-align: center;
            padding: 10px 0;
            font-weight: bold;
        }
        .print-footer {
            bottom: 0;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
        }
        .print-footer .page-info:after {
            content: "Sayfa " counter(page) " / " counter(pages);
        }
        .product-page {
            break-after: page;
            display: flex;
            align-items: center;
            min-height: calc(100vh - 120px);
            padding: 80px 40px 60px;
            box-sizing: border-box;
        }
        .product-image {
            flex: 0 0 40%;
            text-align: center;
        }
        .product-image img {
            max-width: 100%;
            max-height: 400px;
        }
        .product-info {
            flex: 1;
            padding-left: 20px;
        }
    }
    @media screen {
        .print-header, .print-footer { display: none; }
        .product-page {
            display: flex;
            border: 1px solid #dee2e6;
            padding: 20px;
            margin-bottom: 20px;
            min-height: 400px;
        }
        .product-image {
            width: 40%;
            text-align: center;
        }
        .product-image img {
            max-width: 100%;
            max-height: 300px;
        }
        .product-info {
            flex: 1;
            padding-left: 20px;
        }
    }
</style>
<div class="print-header">
    TeklifPro – Optimizasyon Çıktısı - <?= e($currentDate) ?>
</div>
<div class="print-footer">
    <span>© 2025 TeklifPro</span>
    <span class="page-info"></span>
</div>
<?php foreach ($aggregated as $row):
    $unit = $row['qty'] ? $row['length'] / $row['qty'] : 0;
    $cat = strtolower($row['category'] ?? '');
?>
<div class="product-page">
    <div class="product-image">
        <?php if (!empty($row['image'])): ?>
            <img src="<?= e($row['image']) ?>" alt="<?= e($row['name']) ?>">
        <?php else: ?>
            <div>Görsel Yok</div>
        <?php endif; ?>
    </div>
    <div class="product-info">
        <h2><?= e($row['name']) ?></h2>
        <?php if (!empty($row['category'])): ?>
            <h4><?= e($row['category']) ?></h4>
        <?php endif; ?>
        <ul class="list-unstyled mb-0">
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
<?php endforeach; ?>
<?php require __DIR__ . '/footer.php';
