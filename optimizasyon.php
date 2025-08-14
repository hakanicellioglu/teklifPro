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
    'Flatbelt Kayış'       => fn($w, $h, $q) => [$h - (($h - 290) / 3) - 221 + 600, 2 * $q],
    'Motor Borusu'         => fn($w, $h, $q) => [$w - 59, $q],
    'Motor Kutu Contası'   => fn($w, $h, $q) => [$w * $q * 2, 1],
    'Kanat Contası'        => fn($w, $h, $q) => [(($h - 290) / 3) * 2, $q],
];

$pStmt = $pdo->prepare('SELECT weight_per_meter, image_url FROM products WHERE LOWER(name) = LOWER(:name)');

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
        $totalKg = $wpm * $totalLength / 1000; // convert mm to m
        if (!isset($aggregated[$name])) {
            $aggregated[$name] = [
                'name'   => $name,
                'length' => 0.0,
                'qty'    => 0,
                'kg'     => 0.0,
                'image'  => $imageUrl,
            ];
        } else {
            if (!$aggregated[$name]['image'] && $imageUrl) {
                $aggregated[$name]['image'] = $imageUrl;
            }
        }
        $aggregated[$name]['length'] += $totalLength;
        $aggregated[$name]['qty'] += $qty;
        $aggregated[$name]['kg'] += $totalKg;
    }
}
?>
<div class="container py-4 bg-primary bg-opacity-10 rounded">
    <h1 class="mb-4 text-primary">Optimizasyon Sonucu</h1>
    <div class="mb-4">
        <a href="quotation_view.php?id=<?= e((string)$id) ?>" class="btn btn-primary me-2">Teklife Dön</a>
        <button onclick="window.print()" class="btn btn-success me-2">Yazdır</button>
        <a href="#" class="btn btn-warning me-2" role="button">İndir</a>
        <a href="#" class="btn btn-danger" role="button">Sil</a>
    </div>
    <div class="row row-cols-1 row-cols-md-3 g-4">
        <?php foreach ($aggregated as $row):
            $unit = $row['qty'] ? $row['length'] / $row['qty'] : 0;
        ?>
            <div class="col">
                <div class="card h-100 border-info">
                    <?php if (!empty($row['image'])): ?>
                        <img src="<?= e($row['image']) ?>" class="card-img-top" style="width: 150px; height: 150px; margin-left: auto; margin-right: auto; display: block;" alt="<?= e($row['name']) ?>">
                    <?php endif; ?>
                    <div class="card-body bg-info bg-opacity-10">
                        <h5 class="card-title text-success"><?= e($row['name']) ?></h5>
                        <ul class="list-unstyled mb-0">
                            <li><span class="badge bg-secondary">Birim Uzunluk</span> <span class="badge bg-info text-dark"><?= e((int)round($unit)) ?> mm</span></li>
                            <li><span class="badge bg-secondary">Toplam Uzunluk</span> <span class="badge bg-warning text-dark"><?= e((int)round($row['length'])) ?> mm</span></li>
                            <li><span class="badge bg-secondary">Adet</span> <span class="badge bg-success"><?= e((string)$row['qty']) ?></span></li>
                            <li><span class="badge bg-secondary">Toplam Kg</span> <span class="badge bg-danger"><?= e(number_format($row['kg'], 3, ',', '.')) ?></span></li>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/footer.php';
