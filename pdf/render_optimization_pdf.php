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
    $out = @iconv('UTF-8', 'Windows-1254//TRANSLIT', $s);
    return $out !== false ? $out : $s;
}

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

$metaStmt = $pdo->prepare('SELECT g.quote_no AS project_name, g.offer_date, g.payment_method, g.status, c.first_name, c.last_name, c.company_name FROM generaloffers g LEFT JOIN customers c ON g.customer_id = c.id WHERE g.id = :id');
$metaStmt->execute([':id' => $id]);
$meta = $metaStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$projectName = (string)($meta['project_name'] ?? '');
$customerName = trim(($meta['first_name'] ?? '') . ' ' . ($meta['last_name'] ?? ''));
if (!empty($meta['company_name'])) {
    $customerName .= ($customerName ? ' (' : '') . $meta['company_name'] . ($customerName ? ')' : '');
}
$offerDate = (string)($meta['offer_date'] ?? '');
$paymentMethod = (string)($meta['payment_method'] ?? '');
$offerStatus = (string)($meta['status'] ?? '');
$paymentLabels = [
    'cash'          => 'Peşin',
    'bank_transfer' => 'Havale/EFT',
    'credit_card'   => 'Kredi Kartı',
    'installment'   => 'Taksitli',
    'vadeli'        => 'Vadeli',
    'other'         => 'Diğer',
];
$statusLabels = [
    'active'    => 'Aktif',
    'pending'   => 'Beklemede',
    'closed'    => 'Kapalı',
    'draft'     => 'Taslak',
    'sent'      => 'Gönderildi',
    'accepted'  => 'Onaylandı',
    'rejected'  => 'Reddedildi',
    'expired'   => 'Süresi doldu',
    'cancelled' => 'İptal',
];
$paymentMethod = $paymentLabels[$paymentMethod] ?? $paymentMethod;
$offerStatus   = $statusLabels[$offerStatus] ?? $offerStatus;

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

require __DIR__ . '/../libs/fpdf.php';

$pdf = new FPDF();
$pdf->AddFont('Roboto', '', 'Roboto-Regular.php', __DIR__ . '/Roboto/');
$pdf->SetTitle(enc('Optimizasyon Raporu'));
$marginPx = 50;
$pageMargin = $marginPx / 3.78; // approx. 50px
$pdf->SetMargins($pageMargin, $pageMargin, $pageMargin);
$pdf->SetAutoPageBreak(true, $pageMargin);
$pdf->AddPage();
$pdf->SetFont('Roboto', '', 12);
$pdf->Cell(0, 6, enc('Optimizasyon Raporu'), 0, 1, 'C');
$pdf->Ln(2);

$first = $systems[0];
$pdf->SetFont('Roboto', '', 8);
$headerLines = [
    'Proje: ' . $projectName,
    'Müşteri: ' . $customerName,
    'Tarih: ' . $offerDate,
    'Ödeme: ' . $paymentMethod,
    'Durum: ' . $offerStatus,
];
$rightLines = [
    'Genişlik: ' . $first['width'] . ' mm',
    'Yükseklik: ' . $first['height'] . ' mm',
    'Adet: ' . $first['quantity'],
    'Kumanda Adedi: ' . ($first['remote_quantity'] ?? ''),
    'Motor Sistemi: ' . ($first['motor_system'] ?? ''),
    'RAL Kodu: ' . ($first['ral_code'] ?? ''),
];

$xLeft = $cardX + 2;
$yTop = $cardY + 2;
$colW = ($cardW - 4) / 2;
$pdf->SetXY($xLeft, $yTop);
foreach ($leftLines as $line) {
    $pdf->Cell($colW, 4, enc($line), 0, 2);
}
$xRight = $cardX + $cardW / 2 + 2;
$pdf->SetXY($xRight, $yTop);
foreach ($rightLines as $line) {
    $pdf->Cell($colW, 4, enc($line), 0, 2);
}

$pdf->SetY($cardY + $cardH + 3);

$gap = 5;
$cols = 5;
$rowsPerPage = 6;
$xStart = $pageMargin;
$yStart = $pdf->GetY();
$col = 0;
$row = 0;

$pageW = $pdf->GetPageWidth();
$cardW = ($pageW - 2 * $xStart - ($cols - 1) * $gap) / $cols;
$pageH = $pdf->GetPageHeight();
$availableH = $pageH - $yStart - $pageMargin - ($rowsPerPage - 1) * $gap;
$cardH = $availableH / $rowsPerPage;
$imgMaxW = $cardW - 4;
$imgMaxH = $cardH - 15;

$pdf->SetFont('Roboto', '', 7);
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
    $pdf->SetFont('Roboto', '', 7);
    $pdf->MultiCell($cardW - 4, 3, enc($rowData['name']), 0, 'C');
    $currentY = $pdf->GetY();

    $pdf->SetXY($x + 2, $currentY);
    $pdf->SetFont('Roboto', '', 6);
    $unit = $rowData['qty'] ? $rowData['length'] / $rowData['qty'] : 0;
    $cat = strtolower((string)($rowData['category'] ?? ''));
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
            $pdf->AddPage();
            $yStart = $pdf->GetY();
            $row = 0;
        }
    }
}

$pdf->Output('I', 'optimizasyon_raporu.pdf');
