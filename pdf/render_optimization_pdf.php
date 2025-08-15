<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- GEÇİCİ: Hata yakalamayı aç (sorunu bulunca kapatın) ---
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Missing or invalid id.');
}

$stmt = $pdo->prepare('SELECT id, width, height, quantity, remote_quantity, motor_system, ral_code FROM guillotinesystems WHERE general_offer_id = :id');
$stmt->execute([':id' => $id]);
$systems = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$systems) {
    http_response_code(404);
    exit('No systems found.');
}

$metaStmt = $pdo->prepare('SELECT quote_no AS project_name FROM generaloffers WHERE id = :id');
$metaStmt->execute([':id' => $id]);
$meta = $metaStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$projectName = (string)($meta['project_name'] ?? '');

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
        $totalLength = $measure * $qty;
        $pStmt->execute([':name' => $name]);
        $prod = $pStmt->fetch(PDO::FETCH_ASSOC);
        $wpm = (float)($prod['weight_per_meter'] ?? 0);
        $imageUrl = $prod['image_url'] ?? null;
        $category = $prod['category'] ?? null;
        $totalKg = $wpm * $totalLength / 1000;
        if (!isset($aggregated[$name])) {
            $aggregated[$name] = [
                'name'     => $name,
                'length'   => 0.0,
                'qty'      => 0,
                'kg'       => 0.0,
                'image'    => $imageUrl,
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

define('FPDF_FONTPATH', __DIR__ . '/Roboto/');
require __DIR__ . '/../libs/fpdf.php';
// if (!file_exists(__DIR__.'/Roboto/Roboto-Regular.php')) die('Roboto-Regular.php yok');
// if (!file_exists(__DIR__.'/Roboto/Roboto-Regular.z'))  die('Roboto-Regular.z yok');

$pdf = new FPDF();
$fontFile = __DIR__ . '/Roboto/Roboto-Regular.php';
if (is_file($fontFile)) {
    $pdf->AddFont('Roboto', '', 'Roboto-Regular.php');
    $fontName = 'Roboto';
} else {
    $fontName = 'Arial';
}

$pdf->SetTitle(enc('Optimizasyon Raporu'));
$marginPx = 50;
$pageMargin = $marginPx / 3.78; // approx. 50px
$pdf->SetMargins($pageMargin, $pageMargin, $pageMargin);
$pdf->SetAutoPageBreak(true, $pageMargin);
$pdf->AddPage();

$pageW = $pdf->GetPageWidth();
$pageH = $pdf->GetPageHeight();
$cardX = $pageMargin;

$pdf->SetFont($fontName, '', 12);
$pdf->Cell(0, 6, enc('Optimizasyon Raporu'), 0, 1, 'C');
$pdf->Ln(2);

// --- DİKKAT: Header kartı Y konumunu BAŞLIKTAN SONRA al ---
$cardY = $pdf->GetY();

$first = $systems[0];
$pdf->SetFont($fontName, '', 8);
$headerLines = [
    'Proje: ' . $projectName,
    'Motor Sistemi: ' . ($first['motor_system'] ?? ''),
    'RAL Kodu: ' . ($first['ral_code'] ?? ''),
];
$rightLines = [
    'Genişlik: ' . $first['width'] . ' mm',
    'Yükseklik: ' . $first['height'] . ' mm',
    'Adet: ' . $first['quantity'],
    'Kumanda Adedi: ' . ($first['remote_quantity'] ?? ''),
];

$lineH = 4;
$linesCnt = max(count($headerLines), count($rightLines));
$cardW = $pageW - 2 * $pageMargin;
$cardH = $linesCnt * $lineH + 4;
$pdf->Rect($cardX, $cardY, $cardW, $cardH);

$leftX = $cardX + 2;
$rightX = $cardX + $cardW / 2 + 2;
$y = $cardY + 2;
foreach ($headerLines as $line) {
    $pdf->SetXY($leftX, $y);
    $pdf->Cell($cardW / 2 - 4, $lineH, enc($line), 0, 2);
    $y += $lineH;
}
$y = $cardY + 2;
foreach ($rightLines as $line) {
    $pdf->SetXY($rightX, $y);
    $pdf->Cell($cardW / 2 - 4, $lineH, enc($line), 0, 2);
    $y += $lineH;
}

$gap = 5;
$yStart = $cardY + $cardH + $gap;
$cols = 5;
$rowsPerPage = 6;
$xStart = $pageMargin;
$col = 0;
$row = 0;

$availableH = $pageH - $yStart - $pageMargin - ($rowsPerPage - 1) * $gap;
$cardW = ($pageW - 2 * $xStart - ($cols - 1) * $gap) / $cols;
$cardH = $availableH / $rowsPerPage;
$imgMaxW = $cardW - 4;
$imgMaxH = $cardH - 15;

$pdf->SetFont($fontName, '', 7);
foreach ($aggregated as $rowData) {
    $x = $xStart + $col * ($cardW + $gap);
    $y = $yStart + $row * ($cardH + $gap);

    $pdf->Rect($x, $y, $cardW, $cardH);

    $imagePath = $rowData['image'] ?? null;
    $imgY = $y + 2;
    $drawn = false;
    if ($imagePath) {
        $abs = __DIR__ . '/../' . ltrim($imagePath, '/');
        if (is_file($abs)) {
            $info = @getimagesize($abs);
            if ($info) {
                [$iw, $ih] = $info;
                if ($iw > 0 && $ih > 0) {
                    $ratio = $iw / $ih;
                    $w = $imgMaxW;
                    $h = $w / $ratio;
                    if ($h > $imgMaxH) {
                        $h = $imgMaxH;
                        $w = $h * $ratio;
                    }
                    $pdf->Image($abs, $x + ($cardW - $w) / 2, $imgY, $w, $h);
                    $drawn = true;
                }
            }
        }
    }
    if (!$drawn) {
        $pdf->SetXY($x, $imgY + $imgMaxH / 2 - 1.5);
        $pdf->Cell($cardW, 3, enc('Görsel Yok'), 0, 0, 'C');
    }

    $currentY = $imgY + $imgMaxH + 2;
    $pdf->SetXY($x + 2, $currentY);
    $pdf->SetFont($fontName, '', 7);
    $pdf->MultiCell($cardW - 4, 3, enc($rowData['name']), 0, 'C');
    $currentY = $pdf->GetY();

    $pdf->SetXY($x + 2, $currentY);
    $pdf->SetFont($fontName, '', 7);
    $unit = $rowData['qty'] ? $rowData['length'] / $rowData['qty'] : 0;
    // Türkçe için daha güvenli küçük harf dönüşümü
    $cat = mb_strtolower((string)($rowData['category'] ?? ''), 'UTF-8');
    $lines = [];
    if ($cat === 'alüminyum') {
        $lines[] = 'Adet: ' . $rowData['qty'];
        $lines[] = 'Toplam Kg: ' . number_format($rowData['kg'], 3, ',', '.');
    } elseif ($cat === 'aksesuar' || $cat === 'fitil') {
        $lines[] = 'Birim Uzunluk: ' . number_format($unit / 1000, 2, ',', '.') . ' m';
        $lines[] = 'Toplam Uzunluk: ' . number_format($rowData['length'] / 1000, 2, ',', '.') . ' m';
    } else {
        $lines[] = 'Birim Uzunluk: ' . (int)round($unit) . ' mm';
        $lines[] = 'Toplam Uzunluk: ' . (int)round($rowData['length']) . ' mm';
        $lines[] = 'Adet: ' . $rowData['qty'];
        $lines[] = 'Toplam Kg: ' . number_format($rowData['kg'], 3, ',', '.');
    }
    foreach ($lines as $line) {
        $pdf->SetX($x + 2);
        $pdf->Cell($cardW - 4, 3, enc($line), 0, 2);
    }

    $col++;
    if ($col >= $cols) {
        $col = 0;
        $row++;
        if ($row >= $rowsPerPage) {
            // --- Yeni sayfada ızgara ölçülerini YENİDEN hesapla ---
            $pdf->AddPage();
            $pageW = $pdf->GetPageWidth();
            $pageH = $pdf->GetPageHeight();
            $xStart = $pageMargin;
            $yStart = $pageMargin;   // ikinci sayfadan itibaren header yok, direkt ızgara
            $availableH = $pageH - $yStart - $pageMargin - ($rowsPerPage - 1) * $gap;
            $cardW = ($pageW - 2 * $xStart - ($cols - 1) * $gap) / $cols;
            $cardH = $availableH / $rowsPerPage;
            $imgMaxW = $cardW - 4;
            $imgMaxH = $cardH - 15;
            $row = 0;
        }
    }
}

// --- Son anda çıkabilecek FPDF hatalarını yakalamak için sarmalayıcı ---
try {
    $pdf->Output('I', 'optimizasyon_raporu.pdf');
} catch (Throwable $e) {
    error_log('PDF Output error: ' . $e->getMessage());
    http_response_code(500);
    echo 'PDF oluşturulurken bir hata oluştu.';
}
