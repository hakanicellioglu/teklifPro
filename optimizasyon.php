<?php
require __DIR__ . '/header.php';

function e(?string $v): string {
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
    'Motor Kutusu'         => fn($w,$h,$q) => [$w - 14, $q],
    'Motor Kapak'          => fn($w,$h,$q) => [$w - 15, $q],
    'Alt Kasa'             => fn($w,$h,$q) => [$w, $q],
    'Tutamak'              => fn($w,$h,$q) => [$w - 185, 2*$q],
    'Kenetli Baza'         => fn($w,$h,$q) => [$w - 185, 2*$q],
    'Küpeşte Bazası'       => fn($w,$h,$q) => [$w - 185, 2*$q],
    'Küpeşte'              => fn($w,$h,$q) => [$w - 185, $q],
    'Yatay Tek Cam Çıtası' => fn($w,$h,$q) => [($w - 185) - 52, 6*$q],
    'Dikey Tek Cam Çıtası' => fn($w,$h,$q) => [(($h - 290) / 3) - 5, 6*$q],
    'Dikme'                => fn($w,$h,$q) => [$h - 166, 2*$q],
    'Orta Dikme'           => fn($w,$h,$q) => [$h - 166, 2*$q],
    'Son Kapatma'          => fn($w,$h,$q) => [$h - (($h - 290)/3) - 221, 2*$q],
    'Kanat'                => fn($w,$h,$q) => [($h - 290) / 3, 2*$q],
    'Dikey Baza'           => fn($w,$h,$q) => [($h - 290) / 3, 4*$q],
    'Zincir'               => fn($w,$h,$q) => [$h - (($h - 290)/3) - 221 + 600, 2*$q],
    'Flatbelt Kayış'       => fn($w,$h,$q) => [$h - (($h - 290)/3) - 221 + 600, 2*$q],
    'Motor Borusu'         => fn($w,$h,$q) => [$w - 59, $q],
    'Motor Kutu Contası'   => fn($w,$h,$q) => [($w - 14)*$q + $w*$q, 1],
    'Kanat Contası'        => fn($w,$h,$q) => [(($h - 290)/3)*$q*2, 1],
];

$pStmt = $pdo->prepare('SELECT weight_per_meter FROM products WHERE LOWER(name) = LOWER(:name)');

$aggregated = [];
foreach ($systems as $sys) {
    $w = (float)$sys['width'];
    $h = (float)$sys['height'];
    $q = (int)$sys['quantity'];
    foreach ($rules as $name => $fn) {
        [$measure, $qty] = $fn($w,$h,$q);
        if ($measure <= 0 || $qty <= 0) {
            continue;
        }
        $totalLength = $measure * $qty; // mm
        $pStmt->execute([':name' => $name]);
        $wpm = (float)$pStmt->fetchColumn();
        $totalKg = $wpm * $totalLength / 1000; // convert mm to m
        if (!isset($aggregated[$name])) {
            $aggregated[$name] = ['name' => $name, 'length' => 0.0, 'qty' => 0, 'kg' => 0.0];
        }
        $aggregated[$name]['length'] += $totalLength;
        $aggregated[$name]['qty'] += $qty;
        $aggregated[$name]['kg'] += $totalKg;
    }
}

?>
<div class="container py-4">
    <h1>Optimizasyon Sonucu</h1>
    <table class="table table-bordered table-striped table-sm">
        <thead>
            <tr>
                <th>Ürün</th>
                <th>Toplam Uzunluk (mm)</th>
                <th>Adet</th>
                <th>Toplam Kg</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($aggregated as $row): ?>
            <tr>
                <td><?= e($row['name']) ?></td>
                <td><?= e((int)round($row['length'])) ?></td>
                <td><?= e((string)$row['qty']) ?></td>
                <td><?= e(number_format($row['kg'], 3, ',', '.')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/footer.php';
