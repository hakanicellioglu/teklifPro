<?php
require __DIR__ . '/header.php';

function e(?string $v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function fmtPrice(float $v): string {
    return number_format($v, 2, ',', '.') . ' ₺';
}

function fetchProduct(PDO $pdo, string $name): array {
    $stmt = $pdo->prepare('SELECT p.unit_price, p.weight_per_meter, p.width, p.height, c.unit_type FROM products p LEFT JOIN categories c ON p.category = c.id WHERE p.name = :name');
    $stmt->execute(['name' => $name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['unit_price' => 0, 'weight_per_meter' => 0, 'width' => 0, 'height' => 0, 'unit_type' => ''];
    }
    return [
        'unit_price' => (float)$row['unit_price'],
        'weight_per_meter' => (float)($row['weight_per_meter'] ?? 0),
        'width' => (float)($row['width'] ?? 0),
        'height' => (float)($row['height'] ?? 0),
        'unit_type' => $row['unit_type'] ?? '',
    ];
}

$results = [];
$grandTotal = 0.0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $width = isset($_POST['width']) ? (float)$_POST['width'] : 0;
    $height = isset($_POST['height']) ? (float)$_POST['height'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $kenetliQty = isset($_POST['kenetli_qty']) ? (int)$_POST['kenetli_qty'] : 0;
    $kupesteBazaQty = isset($_POST['kupeste_baza_qty']) ? (int)$_POST['kupeste_baza_qty'] : 0;

    if ($width <= 0) { $errors[] = 'Sistem genişliği pozitif olmalıdır.'; }
    if ($height <= 0) { $errors[] = 'Sistem yüksekliği pozitif olmalıdır.'; }
    if ($quantity <= 0) { $errors[] = 'Sistem adedi pozitif olmalıdır.'; }
    if ($kenetliQty <= 0) { $errors[] = 'Kenetli baza adedi pozitif olmalıdır.'; }
    if ($kupesteBazaQty <= 0) { $errors[] = 'Küpeşte bazası adedi pozitif olmalıdır.'; }

    if (!$errors) {
        // Pre-computed quantities
        $motorKutusuMeasurement = $width - 14;
        $motorKutusuQty = $quantity;
        $motorKutusuGasket = $motorKutusuMeasurement * $motorKutusuQty;

        $altKasaMeasurement = $width;
        $altKasaQty = $quantity;
        $altKasaGasket = $altKasaMeasurement * $altKasaQty;

        $tutamakMeasurement = $width - 185;
        $tutamakQty = 6 * $quantity - ($kenetliQty + $kupesteBazaQty);
        $tutamakGasket = $tutamakMeasurement * $tutamakQty;

        $kenetliMeasurement = $width - 185;
        $kenetliBazaQtyCalc = 3 * $quantity;
        $kenetliGasket = $kenetliMeasurement * $kenetliBazaQtyCalc;

        $kupesteBazasiMeasurement = $width - 185;
        $kupesteBazasiQtyCalc = 2 * $quantity;

        $kupesteMeasurement = $width - 185;
        $kupesteQty = $quantity;

        $yatayCitaMeasurement = $kenetliMeasurement - 52;
        $yatayCitaQty = $tutamakQty + $kenetliBazaQtyCalc + $kupesteBazasiQtyCalc;

        $verticalBaseMeasurement = ($height - 290) / 3;
        $dikeyCitaMeasurement = $verticalBaseMeasurement - 5;
        $dikeyCitaQty = $yatayCitaQty;

        $dikmeMeasurement = $height - 166;
        $dikmeQty = 2 * $quantity;
        $dikmeGasket = $dikmeMeasurement * $dikmeQty;

        $ortaDikmeMeasurement = $height - 166;
        $ortaDikmeQty = 2 * $quantity;
        $ortaDikmeGasket = $ortaDikmeMeasurement * $ortaDikmeQty * 2;

        $wingMeasurement = ($height - 290) / 3;
        $sonKapatmaMeasurement = $height - $wingMeasurement - 221;
        $sonKapatmaQty = 2 * $quantity;
        $sonKapatmaGasket = $sonKapatmaMeasurement * $sonKapatmaQty;

        $kanatMeasurement = $wingMeasurement;
        $kanatQty = 2 * $quantity;
        $kanatLongGasket = $kanatMeasurement * $kanatQty;
        $wingGasket = $kanatMeasurement * $kanatQty * 2;

        $dikeyBazaMeasurement = $wingMeasurement;
        $dikeyBazaQty = $quantity * 4;

        $zincirMeasurement = $height - $wingMeasurement - 221 + 600;
        $zincirQty = 2 * $quantity;

        $flatbeltMeasurement = $zincirMeasurement;
        $flatbeltQty = 2 * $quantity;

        $motorBorusuMeasurement = $width - 59;
        $motorBorusuQty = $quantity;

        $motorKutuContasiMeasurement = $motorKutusuGasket + $altKasaGasket; // mm
        $kanatContasiMeasurement = $wingGasket; // mm

        $partsDef = [
            ['Motor Kutusu', $motorKutusuMeasurement, $motorKutusuQty, $motorKutusuGasket],
            ['Motor Kapak', $width - 15, $quantity, null],
            ['Alt Kasa', $altKasaMeasurement, $altKasaQty, $altKasaGasket],
            ['Tutamak', $tutamakMeasurement, $tutamakQty, $tutamakGasket],
            ['Kenetli Baza', $kenetliMeasurement, $kenetliBazaQtyCalc, $kenetliGasket],
            ['Küpeşte Bazası', $kupesteBazasiMeasurement, $kupesteBazasiQtyCalc, null],
            ['Küpeşte', $kupesteMeasurement, $kupesteQty, null],
            ['Yatay Tek Cam Çıtası', $yatayCitaMeasurement, $yatayCitaQty, null],
            ['Dikey Tek Cam Çıtası', $dikeyCitaMeasurement, $dikeyCitaQty, null],
            ['Dikme', $dikmeMeasurement, $dikmeQty, $dikmeGasket],
            ['Orta Dikme', $ortaDikmeMeasurement, $ortaDikmeQty, $ortaDikmeGasket],
            ['Son Kapatma', $sonKapatmaMeasurement, $sonKapatmaQty, $sonKapatmaGasket],
            ['Kanat', $kanatMeasurement, $kanatQty, $kanatLongGasket, $wingGasket],
            ['Dikey Baza', $dikeyBazaMeasurement, $dikeyBazaQty, null],
            ['Zincir', $zincirMeasurement, $zincirQty, null],
            ['Flatbelt Kayış', $flatbeltMeasurement, $flatbeltQty, null],
            ['Motor Borusu', $motorBorusuMeasurement, $motorBorusuQty, null],
            ['Motor Kutu Contası', $motorKutuContasiMeasurement, 1, null],
            ['Kanat Contası', $kanatContasiMeasurement, 1, null],
        ];

        foreach ($partsDef as $def) {
            [$name, $measurement, $qtyPart, $gasketExtra, $wingGasketExtra] = array_pad($def, 5, null);
            $product = fetchProduct($pdo, $name);
            $unitPrice = $product['unit_price'];
            $unitType = $product['unit_type'];
            $length = ($measurement * $qtyPart) / 1000; // m
            $totalKg = null;
            switch ($unitType) {
                case 'kg/m':
                    $totalKg = $product['weight_per_meter'] * $length;
                    $totalPrice = $totalKg * $unitPrice;
                    break;
                case 'm':
                    $totalPrice = $length * $unitPrice;
                    break;
                case 'm²':
                    $area = ($product['width'] * $product['height'] / 1000000) * $qtyPart;
                    $totalPrice = $area * $unitPrice;
                    break;
                default: // adet
                    $totalPrice = $qtyPart * $unitPrice;
            }
            $extraInfo = '';
            if ($name === 'Motor Kutusu') {
                $extraInfo = 'Motor Kutu Contası: ' . $motorKutusuGasket . ' mm';
            } elseif ($name === 'Alt Kasa') {
                $extraInfo = 'Motor Kutu Contası: ' . $altKasaGasket . ' mm';
            } elseif ($name === 'Tutamak') {
                $extraInfo = 'Kısa Fitil: ' . $tutamakGasket . ' mm';
            } elseif ($name === 'Kenetli Baza') {
                $extraInfo = 'Kısa Fitil: ' . $kenetliGasket . ' mm';
            } elseif ($name === 'Dikme') {
                $extraInfo = 'Uzun Fitil: ' . $dikmeGasket . ' mm';
            } elseif ($name === 'Orta Dikme') {
                $extraInfo = 'Uzun Fitil: ' . $ortaDikmeGasket . ' mm';
            } elseif ($name === 'Son Kapatma') {
                $extraInfo = 'Uzun Fitil: ' . $sonKapatmaGasket . ' mm';
            } elseif ($name === 'Kanat') {
                $extraInfo = 'Uzun Fitil: ' . $kanatLongGasket . ' mm, Kanat Fitili: ' . $wingGasket . ' mm';
            } elseif ($name === 'Motor Kutu Contası') {
                $extraInfo = 'Uzunluk: ' . number_format($motorKutuContasiMeasurement / 1000, 2, ',', '.') . ' m';
            } elseif ($name === 'Kanat Contası') {
                $extraInfo = 'Uzunluk: ' . number_format($kanatContasiMeasurement / 1000, 2, ',', '.') . ' m';
            }

            $results[] = [
                'name' => $name,
                'measurement' => $measurement,
                'quantity' => $qtyPart,
                'total_kg' => $totalKg,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'extra' => $extraInfo,
            ];
            $grandTotal += $totalPrice;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Guillotine Optimization</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
    <h1>Guillotine Optimization</h1>
    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= e($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form method="post" class="row g-3 mb-4">
        <div class="col-md-2">
            <label class="form-label">System Width (mm)</label>
            <input type="number" name="width" step="0.01" class="form-control" value="<?= e($_POST['width'] ?? '') ?>" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">System Height (mm)</label>
            <input type="number" name="height" step="0.01" class="form-control" value="<?= e($_POST['height'] ?? '') ?>" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">System Quantity</label>
            <input type="number" name="quantity" class="form-control" value="<?= e($_POST['quantity'] ?? '') ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Kenetli Baza Quantity</label>
            <input type="number" name="kenetli_qty" class="form-control" value="<?= e($_POST['kenetli_qty'] ?? '') ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Küpeşte Bazası Quantity</label>
            <input type="number" name="kupeste_baza_qty" class="form-control" value="<?= e($_POST['kupeste_baza_qty'] ?? '') ?>" required>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Hesapla</button>
        </div>
    </form>

    <?php if ($results): ?>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Part Name</th>
                    <th>Measurement (mm)</th>
                    <th>Quantity</th>
                    <th>Total Weight (kg)</th>
                    <th>Unit Price</th>
                    <th>Total Price</th>
                    <th>Extra Info</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $r): ?>
                    <tr>
                        <td><?= e($r['name']) ?></td>
                        <td><?= e(number_format($r['measurement'], 2, ',', '.')) ?></td>
                        <td><?= e($r['quantity']) ?></td>
                        <td><?= $r['total_kg'] !== null ? e(number_format($r['total_kg'], 3, ',', '.')) : '-' ?></td>
                        <td><?= e(fmtPrice($r['unit_price'])) ?></td>
                        <td><?= e(fmtPrice($r['total_price'])) ?></td>
                        <td><?= e($r['extra']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5" class="text-end">Grand Total</th>
                    <th><?= e(fmtPrice($grandTotal)) ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</body>
</html>
