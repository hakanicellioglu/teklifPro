<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/../config.php';

function enc(string $s): string {
    $out = @iconv('UTF-8', 'ISO-8859-9//TRANSLIT', $s);
    return $out !== false ? $out : $s;
}

/**
 * Basic HTML escaper.
 */
function h(?string $v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Missing or invalid id.');
}

$stmt = $pdo->prepare('SELECT id, width, height, quantity FROM guillotinesystems WHERE general_offer_id = :id');
$stmt->execute([':id' => $id]);
$systems = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$systems) {
    http_response_code(404);
    exit('No systems found.');
}

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

$pStmt = $pdo->prepare('SELECT weight_per_meter, image_url, image_data, image_mime FROM products WHERE LOWER(name) = LOWER(:name)');

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
        $totalLength = $measure * $qty;
        $pStmt->execute([':name' => $name]);
        $prod = $pStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $wpm = (float)($prod['weight_per_meter'] ?? 0);

        // Determine product image if available
        $img = $prod['image_url'] ?? '';
        if (!$img && !empty($prod['image_data'])) {
            $mime = $prod['image_mime'] ?? 'image/png';
            $img  = 'data:' . $mime . ';base64,' . base64_encode($prod['image_data']);
        }

        $totalKg = $wpm * $totalLength / 1000;
        if (!isset($aggregated[$name])) {
            $aggregated[$name] = [
                'name'   => $name,
                'length' => 0.0,
                'qty'    => 0,
                'kg'     => 0.0,
                'image'  => '',
            ];
        }
        if ($img && !$aggregated[$name]['image']) {
            $aggregated[$name]['image'] = $img;
        }
        $aggregated[$name]['length'] += $totalLength;
        $aggregated[$name]['qty'] += $qty;
        $aggregated[$name]['kg'] += $totalKg;
    }
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Optimizasyon Sonucu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWmvMRGsiE232zraFMvx6bMpiKFF9volG/Gp2gbf+5Q5e0siJY9hw3rrodpAtxE" crossorigin="anonymous">
</head>
<body class="p-4">
<div class="container">
    <h2 class="text-center mb-4">Optimizasyon Sonucu</h2>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Parça</th>
                    <th>Görsel</th>
                    <th class="text-end">Birim Uzunluk</th>
                    <th class="text-end">Toplam Uzunluk</th>
                    <th class="text-end">Adet</th>
                    <th class="text-end">Toplam Kg</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($aggregated as $row): $unit = $row['qty'] ? $row['length'] / $row['qty'] : 0; ?>
                <tr>
                    <td><?= h($row['name']) ?></td>
                    <td><?php if (!empty($row['image'])): ?><img src="<?= h($row['image']) ?>" alt="<?= h($row['name']) ?>" class="img-thumbnail" style="max-width:60px;" /><?php endif; ?></td>
                    <td class="text-end"><?= (int)round($unit) ?></td>
                    <td class="text-end"><?= (int)round($row['length']) ?></td>
                    <td class="text-end"><?= (int)$row['qty'] ?></td>
                    <td class="text-end"><?= number_format($row['kg'], 3, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
