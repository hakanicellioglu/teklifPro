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

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}

function enc(string $s): string {
    $out = @iconv('UTF-8', 'ISO-8859-9//TRANSLIT', $s);
    return $out !== false ? $out : $s;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Missing or invalid id');
}

$stmt = $pdo->prepare('SELECT id, width, height, quantity FROM guillotinesystems WHERE general_offer_id = :id');
$stmt->execute([':id' => $id]);
$systems = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$systems) {
    http_response_code(404);
    exit('No systems found');
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
    'Flatbelt Kayış'       => fn($w, $h, $q) => [$h - (($h - 290) / 3) - 221 + 600, 2 * $q],
    'Motor Borusu'         => fn($w, $h, $q) => [$w - 59, $q],
    'Motor Kutu Contası'   => fn($w, $h, $q) => [$w * $q * 2, 1],
    'Kanat Contası'        => fn($w, $h, $q) => [(($h - 290) / 3) * 2, $q],
];

$pStmt = $pdo->prepare('SELECT weight_per_meter FROM products WHERE LOWER(name) = LOWER(:name)');
$aggregated = [];
foreach ($systems as $sys) {
    $w = (float) $sys['width'];
    $h = (float) $sys['height'];
    $q = (int) $sys['quantity'];
    foreach ($rules as $name => $fn) {
        [$measure, $qty] = $fn($w, $h, $q);
        if ($measure <= 0 || $qty <= 0) {
            continue;
        }
        $totalLength = $measure * $qty;
        $pStmt->execute([':name' => $name]);
        $prod = $pStmt->fetch(PDO::FETCH_ASSOC);
        $wpm = (float) ($prod['weight_per_meter'] ?? 0);
        $totalKg = $wpm * $totalLength / 1000;
        if (!isset($aggregated[$name])) {
            $aggregated[$name] = [
                'name'   => $name,
                'length' => 0.0,
                'qty'    => 0,
                'kg'     => 0.0,
            ];
        }
        $aggregated[$name]['length'] += $totalLength;
        $aggregated[$name]['qty'] += $qty;
        $aggregated[$name]['kg'] += $totalKg;
    }
}

$canUseMpdf = class_exists('\\Mpdf\\Mpdf');

if ($canUseMpdf) {
    $rows = '';
    foreach ($aggregated as $row) {
        $unit = $row['qty'] ? $row['length'] / $row['qty'] : 0;
        $rows .= '<tr>'
            .'<td>'.htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8').'</td>'
            .'<td style="text-align:right">'.number_format($unit, 0, ',', '.').'</td>'
            .'<td style="text-align:right">'.number_format($row['length'], 0, ',', '.').'</td>'
            .'<td style="text-align:right">'.$row['qty'].'</td>'
            .'<td style="text-align:right">'.number_format($row['kg'], 3, ',', '.').'</td>'
            .'</tr>';
    }
    $html = '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><style>'
        .'table{width:100%;border-collapse:collapse;}'
        .'th,td{border:1px solid #888;padding:6px;font-size:10pt;}'
        .'h1{font-size:16pt;margin:0 0 10px;}'
        .'</style></head><body>'
        .'<h1>Optimizasyon Sonucu</h1>'
        .'<table>'
        .'<thead><tr><th>Parça</th><th>Birim Uzunluk (mm)</th><th>Toplam Uzunluk (mm)</th><th>Adet</th><th>Toplam Kg</th></tr></thead>'
        .'<tbody>'.$rows.'</tbody>'
        .'</table>'
        .'</body></html>';

    $mpdf = new \Mpdf\Mpdf(['tempDir' => __DIR__ . '/../storage/tmp']);
    $mpdf->WriteHTML($html);
    $mpdf->Output('optimizasyon.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

require __DIR__ . '/../libs/fpdf.php';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="optimizasyon.pdf"');

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,enc('Optimizasyon Sonucu'),0,1,'C');
$pdf->Ln(5);

$pdf->SetFont('Arial','B',11);
$pdf->Cell(60,8,enc('Parça'),1);
$pdf->Cell(30,8,enc('Birim Uz.'),1,0,'R');
$pdf->Cell(40,8,enc('Toplam Uz.'),1,0,'R');
$pdf->Cell(20,8,enc('Adet'),1,0,'R');
$pdf->Cell(30,8,enc('Kg'),1,1,'R');

$pdf->SetFont('Arial','',10);
foreach ($aggregated as $row) {
    $unit = $row['qty'] ? $row['length'] / $row['qty'] : 0;
    $pdf->Cell(60,8,enc($row['name']),1);
    $pdf->Cell(30,8,number_format($unit,0,',','.'),1,0,'R');
    $pdf->Cell(40,8,number_format($row['length'],0,',','.'),1,0,'R');
    $pdf->Cell(20,8,(string)$row['qty'],1,0,'R');
    $pdf->Cell(30,8,number_format($row['kg'],3,',','.'),1,1,'R');
}

$pdf->Output('I','optimizasyon.pdf');
exit;

