<?php
declare(strict_types=1);

if (ob_get_level() === 0) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

mb_internal_encoding('UTF-8');

require __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}

function h(?string $v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Invalid quotation id');
}

try {
    $stmt = $pdo->prepare(
        "SELECT q.*, c.first_name, c.last_name, c.company_name, u.username AS prepared_by
         FROM generaloffers q
         LEFT JOIN customers c ON c.id = q.customer_id
         LEFT JOIN users u ON u.id = q.user_id
         WHERE q.id = :id LIMIT 1"
    );
    $stmt->execute([':id' => $id]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$quote) {
        http_response_code(404);
        exit('Quotation not found');
    }

    $gStmt = $pdo->prepare(
        'SELECT system_type, width, height, quantity, motor_system, ral_code, glass_type, glass_color, total_amount
         FROM guillotinesystems WHERE general_offer_id = :id'
    );
    $gStmt->execute([':id' => $id]);
    $guillotines = $gStmt->fetchAll(PDO::FETCH_ASSOC);

    $sStmt = $pdo->prepare(
        'SELECT system_type, width, height, quantity, wing_type, ral_code, glass_type, glass_color, total_amount
         FROM slidingsystems WHERE general_offer_id = :id'
    );
    $sStmt->execute([':id' => $id]);
    $slidings = $sStmt->fetchAll(PDO::FETCH_ASSOC);

    $company = ['name' => '', 'logo' => null];
    $cStmt   = $pdo->query('SELECT name, logo FROM company LIMIT 1');
    if ($cStmt) {
        $company = $cStmt->fetch(PDO::FETCH_ASSOC) ?: $company;
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit('DB error');
}

$summary = [
    'customer'    => trim(($quote['first_name'] ?? '') . ' ' . ($quote['last_name'] ?? '')),
    'project'     => $quote['company_name'] ?? '',
    'quote_no'    => $quote['quote_no'] ?? (string) $quote['id'],
    'date'        => $quote['offer_date'] ?? date('Y-m-d'),
    'prepared_by' => $quote['prepared_by'] ?? '',
    'currency'    => 'TRY',
];

$vatRate = (float) ($quote['vat_rate'] ?? 0);

$gSubtotal = 0.0;
foreach ($guillotines as &$g) {
    $amt             = (float) ($g['total_amount'] ?? 0);
    $qty             = (float) ($g['quantity'] ?? 0);
    $g['unit']       = 'adet';
    $g['unit_price'] = $qty > 0 ? $amt / $qty : $amt;
    $g['vat']        = $vatRate;
    $gSubtotal      += $amt;
}
unset($g);
$gVat   = $gSubtotal * $vatRate / 100;
$gTotal = $gSubtotal + $gVat;

$sSubtotal = 0.0;
foreach ($slidings as &$s) {
    $amt             = (float) ($s['total_amount'] ?? 0);
    $qty             = (float) ($s['quantity'] ?? 0);
    $s['unit']       = 'adet';
    $s['unit_price'] = $qty > 0 ? $amt / $qty : $amt;
    $s['vat']        = $vatRate;
    $sSubtotal      += $amt;
}
unset($s);
$sVat   = $sSubtotal * $vatRate / 100;
$sTotal = $sSubtotal + $sVat;

$summary['subtotal']    = $gSubtotal + $sSubtotal;
$summary['vat_total']   = $gVat + $sVat;
$summary['grand_total'] = $summary['subtotal'] + $summary['vat_total'];

$filename = 'teklif_' . $id . '.pdf';

$canUseMpdf = class_exists('\\Mpdf\\Mpdf');

if ($canUseMpdf) {
    $css  = @file_get_contents(__DIR__ . '/templates/quotation.css');
    $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'default_font' => 'DejaVuSans']);
    $mpdf->SetAutoTopMargin    = 'stretch';
    $mpdf->SetAutoBottomMargin = 'stretch';
    if ($css) {
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
    }
    $mpdf->SetFooter('{PAGENO}/{nbpg}');

    ob_start();
    ?>
    <div>
        <h2><?=h($company['name'] ?? '')?></h2>
        <div><?=h('Teklif No: ' . $summary['quote_no'])?></div>
        <div><?=h('Tarih: ' . $summary['date'])?></div>
    </div>

    <h3>Teklif Özeti</h3>
    <table class="table zebra" style="page-break-inside:auto">
        <tbody>
        <tr><td>Ara Toplam</td><td class="num"><?=number_format($summary['subtotal'], 2, ',', '.') ?> ₺</td></tr>
        <tr><td>KDV</td><td class="num"><?=number_format($summary['vat_total'], 2, ',', '.') ?> ₺</td></tr>
        <tr><td>Genel Toplam</td><td class="num"><?=number_format($summary['grand_total'], 2, ',', '.') ?> ₺</td></tr>
        </tbody>
    </table>

    <?php if ($guillotines) { ?>
    <h3>Giyotin Teklifi</h3>
    <table class="table zebra" style="page-break-inside:auto">
        <thead>
        <tr>
            <th>Kategori</th><th>Ürün</th><th>Ölçü</th><th>Birim</th><th>Adet</th>
            <th>Birim Fiyatı</th><th>KDV %</th><th>Tutar</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($guillotines as $row): ?>
            <tr>
                <td><?=h($row['system_type'] ?? '')?></td>
                <td><?=h($row['motor_system'] ?? '')?></td>
                <td><?=h(($row['width'] ?? '') . 'x' . ($row['height'] ?? ''))?></td>
                <td><?=h($row['unit'])?></td>
                <td class="num"><?=h((string) $row['quantity'])?></td>
                <td class="num"><?=number_format($row['unit_price'], 2, ',', '.') ?> ₺</td>
                <td class="num"><?=number_format($row['vat'], 2, ',', '.')?></td>
                <td class="num"><?=number_format((float) $row['total_amount'], 2, ',', '.') ?> ₺</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
        <tr><th colspan="7" class="num">Ara Toplam</th><th class="num"><?=number_format($gSubtotal, 2, ',', '.') ?> ₺</th></tr>
        <tr><th colspan="7" class="num">KDV</th><th class="num"><?=number_format($gVat, 2, ',', '.') ?> ₺</th></tr>
        <tr><th colspan="7" class="num">Toplam</th><th class="num"><?=number_format($gTotal, 2, ',', '.') ?> ₺</th></tr>
        </tfoot>
    </table>
    <?php } ?>

    <?php if ($slidings) { ?>
    <h3>Sürme Teklifi</h3>
    <table class="table zebra" style="page-break-inside:auto">
        <thead>
        <tr>
            <th>Kategori</th><th>Ürün</th><th>Ölçü</th><th>Birim</th><th>Adet</th>
            <th>Birim Fiyatı</th><th>KDV %</th><th>Tutar</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($slidings as $row): ?>
            <tr>
                <td><?=h($row['system_type'] ?? '')?></td>
                <td><?=h($row['wing_type'] ?? '')?></td>
                <td><?=h(($row['width'] ?? '') . 'x' . ($row['height'] ?? ''))?></td>
                <td><?=h($row['unit'])?></td>
                <td class="num"><?=h((string) $row['quantity'])?></td>
                <td class="num"><?=number_format($row['unit_price'], 2, ',', '.') ?> ₺</td>
                <td class="num"><?=number_format($row['vat'], 2, ',', '.')?></td>
                <td class="num"><?=number_format((float) $row['total_amount'], 2, ',', '.') ?> ₺</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
        <tr><th colspan="7" class="num">Ara Toplam</th><th class="num"><?=number_format($sSubtotal, 2, ',', '.') ?> ₺</th></tr>
        <tr><th colspan="7" class="num">KDV</th><th class="num"><?=number_format($sVat, 2, ',', '.') ?> ₺</th></tr>
        <tr><th colspan="7" class="num">Toplam</th><th class="num"><?=number_format($sTotal, 2, ',', '.') ?> ₺</th></tr>
        </tfoot>
    </table>
    <?php } ?>
    <?php
    $html = ob_get_clean();
    $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    echo $mpdf->Output($filename, \Mpdf\Output\Destination::STRING_RETURN);
    exit;
}

define('FPDF_FONTPATH', __DIR__ . '/fonts/');
require __DIR__ . '/../libs/fpdf.php';

class PDF extends FPDF {
    public string $logo = '';
    public string $company = '';
    public string $quoteNo = '';
    public string $quoteDate = '';

    function Header(): void {
        if ($this->logo && file_exists($this->logo)) {
            $this->Image($this->logo, 15, 10, 30);
        }
        $this->SetFont('DejaVu', 'B', 12);
        $this->Cell(0, 6, $this->company, 0, 1, 'R');
        $this->SetFont('DejaVu', '', 10);
        $this->Cell(0, 5, 'Teklif No: ' . $this->quoteNo, 0, 1, 'R');
        $this->Cell(0, 5, 'Tarih: ' . $this->quoteDate, 0, 1, 'R');
        $this->Ln(5);
    }

    function Footer(): void {
        $this->SetY(-15);
        $this->SetFont('DejaVu', '', 8);
        $this->Cell(0, 10, $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf            = new PDF();
$pdf->logo      = $company['logo'] ? __DIR__ . '/../' . ltrim((string) $company['logo'], '/') : '';
$pdf->company   = $company['name'] ?? '';
$pdf->quoteNo   = $summary['quote_no'];
$pdf->quoteDate = $summary['date'];

$pdf->AliasNbPages();
$pdf->SetMargins(15, 40, 15);
$pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
$pdf->AddPage();
$pdf->SetFont('DejaVu', '', 10);

$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 6, 'Teklif Özeti', 0, 1);
$pdf->Ln(1);
$w = 80;
$pdf->Cell($w, 6, 'Ara Toplam', 1, 0, 'L', true);
$pdf->Cell($w, 6, number_format($summary['subtotal'], 2, ',', '.') . ' ₺', 1, 1, 'R', true);
$pdf->Cell($w, 6, 'KDV', 1, 0, 'L');
$pdf->Cell($w, 6, number_format($summary['vat_total'], 2, ',', '.') . ' ₺', 1, 1, 'R');
$pdf->Cell($w, 6, 'Genel Toplam', 1, 0, 'L', true);
$pdf->Cell($w, 6, number_format($summary['grand_total'], 2, ',', '.') . ' ₺', 1, 1, 'R', true);
$pdf->Ln(4);

if ($guillotines) {
    $pdf->SetFont('DejaVu', 'B', 10);
    $pdf->Cell(0, 6, 'Giyotin Teklifi', 0, 1);
    $pdf->SetFont('DejaVu', '', 9);
    $headers = ['Kategori', 'Ürün', 'Ölçü', 'Birim', 'Adet', 'Birim Fiyatı', 'KDV %', 'Tutar'];
    $widths  = [25, 30, 25, 15, 15, 25, 15, 30];
    foreach ($headers as $i => $hcell) {
        $pdf->Cell($widths[$i], 7, $hcell, 1, 0, 'C', true);
    }
    $pdf->Ln();
    $fill = false;
    foreach ($guillotines as $row) {
        $pdf->Cell($widths[0], 6, $row['system_type'], 1, 0, 'L', $fill);
        $pdf->Cell($widths[1], 6, $row['motor_system'], 1, 0, 'L', $fill);
        $pdf->Cell($widths[2], 6, $row['width'] . 'x' . $row['height'], 1, 0, 'L', $fill);
        $pdf->Cell($widths[3], 6, $row['unit'], 1, 0, 'L', $fill);
        $pdf->Cell($widths[4], 6, (string) $row['quantity'], 1, 0, 'R', $fill);
        $pdf->Cell($widths[5], 6, number_format($row['unit_price'], 2, ',', '.') . ' ₺', 1, 0, 'R', $fill);
        $pdf->Cell($widths[6], 6, number_format($row['vat'], 2, ',', '.'), 1, 0, 'R', $fill);
        $pdf->Cell($widths[7], 6, number_format((float) $row['total_amount'], 2, ',', '.') . ' ₺', 1, 1, 'R', $fill);
        $fill = !$fill;
    }
    $pdf->Cell(array_sum($widths) - $widths[7], 6, 'Ara Toplam', 1, 0, 'R', true);
    $pdf->Cell($widths[7], 6, number_format($gSubtotal, 2, ',', '.') . ' ₺', 1, 1, 'R', true);
    $pdf->Cell(array_sum($widths) - $widths[7], 6, 'KDV', 1, 0, 'R');
    $pdf->Cell($widths[7], 6, number_format($gVat, 2, ',', '.') . ' ₺', 1, 1, 'R');
    $pdf->Cell(array_sum($widths) - $widths[7], 6, 'Toplam', 1, 0, 'R', true);
    $pdf->Cell($widths[7], 6, number_format($gTotal, 2, ',', '.') . ' ₺', 1, 1, 'R', true);
    $pdf->Ln(4);
}

if ($slidings) {
    $pdf->SetFont('DejaVu', 'B', 10);
    $pdf->Cell(0, 6, 'Sürme Teklifi', 0, 1);
    $pdf->SetFont('DejaVu', '', 9);
    $headers = ['Kategori', 'Ürün', 'Ölçü', 'Birim', 'Adet', 'Birim Fiyatı', 'KDV %', 'Tutar'];
    $widths  = [25, 30, 25, 15, 15, 25, 15, 30];
    foreach ($headers as $i => $hcell) {
        $pdf->Cell($widths[$i], 7, $hcell, 1, 0, 'C', true);
    }
    $pdf->Ln();
    $fill = false;
    foreach ($slidings as $row) {
        $pdf->Cell($widths[0], 6, $row['system_type'], 1, 0, 'L', $fill);
        $pdf->Cell($widths[1], 6, $row['wing_type'], 1, 0, 'L', $fill);
        $pdf->Cell($widths[2], 6, $row['width'] . 'x' . $row['height'], 1, 0, 'L', $fill);
        $pdf->Cell($widths[3], 6, $row['unit'], 1, 0, 'L', $fill);
        $pdf->Cell($widths[4], 6, (string) $row['quantity'], 1, 0, 'R', $fill);
        $pdf->Cell($widths[5], 6, number_format($row['unit_price'], 2, ',', '.') . ' ₺', 1, 0, 'R', $fill);
        $pdf->Cell($widths[6], 6, number_format($row['vat'], 2, ',', '.'), 1, 0, 'R', $fill);
        $pdf->Cell($widths[7], 6, number_format((float) $row['total_amount'], 2, ',', '.') . ' ₺', 1, 1, 'R', $fill);
        $fill = !$fill;
    }
    $pdf->Cell(array_sum($widths) - $widths[7], 6, 'Ara Toplam', 1, 0, 'R', true);
    $pdf->Cell($widths[7], 6, number_format($sSubtotal, 2, ',', '.') . ' ₺', 1, 1, 'R', true);
    $pdf->Cell(array_sum($widths) - $widths[7], 6, 'KDV', 1, 0, 'R');
    $pdf->Cell($widths[7], 6, number_format($sVat, 2, ',', '.') . ' ₺', 1, 1, 'R');
    $pdf->Cell(array_sum($widths) - $widths[7], 6, 'Toplam', 1, 0, 'R', true);
    $pdf->Cell($widths[7], 6, number_format($sTotal, 2, ',', '.') . ' ₺', 1, 1, 'R', true);
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
echo $pdf->Output('S');
exit;

