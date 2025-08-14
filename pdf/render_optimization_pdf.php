<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

// Attempt to load Composer autoloader (for mPDF)
$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}

require __DIR__ . '/../config.php';

function h(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

// FPDF uses ISO-8859-9; helper for fallback
function enc(string $s): string
{
    $out = @iconv('UTF-8', 'ISO-8859-9//TRANSLIT', $s);
    return $out !== false ? $out : $s;
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
        $totalLength = $measure * $qty;
        $pStmt->execute([':name' => $name]);
        $prod = $pStmt->fetch(PDO::FETCH_ASSOC);
        $wpm = (float)($prod['weight_per_meter'] ?? 0);
        $image = $prod['image_url'] ?? null;
        $totalKg = $wpm * $totalLength / 1000; // convert mm to m
        if (!isset($aggregated[$name])) {
            $aggregated[$name] = [
                'name'   => $name,
                'length' => 0.0,
                'qty'    => 0,
                'kg'     => 0.0,
                'image'  => $image,
            ];
        } else {
            if (!$aggregated[$name]['image'] && $image) {
                $aggregated[$name]['image'] = $image;
            }
        }
        $aggregated[$name]['length'] += $totalLength;
        $aggregated[$name]['qty'] += $qty;
        $aggregated[$name]['kg'] += $totalKg;
    }
}

// Render with mPDF if available for full design replication
if (class_exists('\\Mpdf\\Mpdf')) {
    $html = (function () use ($aggregated) {
        $cols = 3;
        $htmlRows = '';
        $i = 0;
        foreach ($aggregated as $row) {
            $unit = $row['qty'] ? $row['length'] / $row['qty'] : 0;
            if ($i % $cols === 0) {
                $htmlRows .= '<tr>';
            }
            $htmlRows .= '<td class="card">';
            if (!empty($row['image'])) {
                $htmlRows .= '<img src="' . h($row['image']) . '" alt="' . h($row['name']) . '">';
            }
            $htmlRows .= '<div class="card-title">' . h($row['name']) . '</div>';
            $htmlRows .= '<ul>';
            $htmlRows .= '<li>Birim Uzunluk: ' . h((string)(int)round($unit)) . ' mm</li>';
            $htmlRows .= '<li>Toplam Uzunluk: ' . h((string)(int)round($row['length'])) . ' mm</li>';
            $htmlRows .= '<li>Adet: ' . h((string)$row['qty']) . '</li>';
            $htmlRows .= '<li>Toplam Kg: ' . h(number_format($row['kg'], 3, ',', '.')) . '</li>';
            $htmlRows .= '</ul>';
            $htmlRows .= '</td>';
            if ($i % $cols === $cols - 1) {
                $htmlRows .= '</tr>';
            }
            $i++;
        }
        if ($i % $cols !== 0) {
            $htmlRows .= str_repeat('<td class="card"></td>', $cols - ($i % $cols)) . '</tr>';
        }
        return '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><style>
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size:11pt; }
            h1 { text-align:center; font-size:16pt; margin-bottom:20px; }
            table.grid { width:100%; border-collapse:separate; border-spacing:10px; }
            td.card { border:1px solid #dee2e6; border-radius:4px; padding:10px; vertical-align:top; width:' . (100 / $cols) . '%; }
            td.card img { width:150px; height:150px; display:block; margin:0 auto 10px; }
            td.card .card-title { font-size:14pt; margin-bottom:8px; text-align:center; }
            td.card ul { list-style:none; padding:0; margin:0; }
            td.card ul li { margin-bottom:2px; }
        </style></head><body><h1>Optimizasyon Sonucu</h1><table class="grid">' . $htmlRows . '</table></body></html>';
    })();

    $mpdf = new \Mpdf\Mpdf(['tempDir' => __DIR__ . '/../storage/tmp']);
    $mpdf->WriteHTML($html);
    $mpdf->Output('optimizasyon.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

// Fallback: basic table using FPDF
require __DIR__ . '/../libs/fpdf.php';
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 8, enc('Optimizasyon Sonucu'), 0, 1, 'C');
$pdf->Ln(4);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 8, enc('Parça'), 1, 0);
$pdf->Cell(30, 8, enc('Birim Uzunluk'), 1, 0, 'R');
$pdf->Cell(30, 8, enc('Toplam Uzunluk'), 1, 0, 'R');
$pdf->Cell(25, 8, enc('Adet'), 1, 0, 'R');
$pdf->Cell(25, 8, enc('Toplam Kg'), 1, 1, 'R');
$pdf->SetFont('Arial', '', 10);
foreach ($aggregated as $row) {
    $unit = $row['qty'] ? $row['length'] / $row['qty'] : 0;
    $pdf->Cell(60, 7, enc($row['name']), 1, 0);
    $pdf->Cell(30, 7, (string)(int)round($unit), 1, 0, 'R');
    $pdf->Cell(30, 7, (string)(int)round($row['length']), 1, 0, 'R');
    $pdf->Cell(25, 7, (string)$row['qty'], 1, 0, 'R');
    $pdf->Cell(25, 7, number_format($row['kg'], 3, ',', '.'), 1, 1, 'R');
}
$pdf->Output('I', 'optimizasyon.pdf');
