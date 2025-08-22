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
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../test.php';

// CSRF validation
$csrf = filter_input(INPUT_GET, 'csrf_token', FILTER_SANITIZE_STRING);
if (!$csrf || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

// Quotation id
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    exit('Invalid quotation id.');
}

// Product provider used by pricing helpers
class PdoProductProvider implements ProductProviderInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getProduct(string $name): ?array
    {
        $stmt = $this->pdo->prepare('SELECT unit, unit_price, vat_rate, weight_per_meter, category FROM products WHERE LOWER(name) = LOWER(:name)');
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

try {
    $stmt = $pdo->prepare('SELECT g.*, c.first_name, c.last_name, c.company AS customer_company, c.address AS customer_address, c.email AS customer_email, c.phone AS customer_phone FROM generaloffers g JOIN customers c ON g.customer_id = c.id WHERE g.id = :id');
    $stmt->execute([':id' => $id]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$quote) {
        http_response_code(404);
        exit('Quotation not found.');
    }

    $itemStmt = $pdo->prepare('SELECT * FROM guillotinesystems WHERE general_offer_id = :id');
    $itemStmt->execute([':id' => $id]);
    $guillotines = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Database error.');
}

$provider = new PdoProductProvider($pdo);
$rows = [];
$subTotal = 0.0;
foreach ($guillotines as $g) {
    try {
        $res = calculateGuillotineTotals([
            'width'       => $g['width'],
            'height'      => $g['height'],
            'quantity'    => $g['quantity'],
            'glass_type'  => $g['glass_type'] ?? '',
            'profit_rate' => $g['profit_rate'] ?? ($g['profit_margin'] ?? 0),
            'provider'    => $provider,
        ]);
        $amount = $res['totals']['grand_total'];
    } catch (Throwable $e) {
        $amount = (float)($g['total_amount'] ?? 0);
    }
    $subTotal += $amount;
    $rows[] = [
        'system' => (string)($g['system_type'] ?? ''),
        'width'  => (string)($g['width'] ?? ''),
        'height' => (string)($g['height'] ?? ''),
        'qty'    => (string)($g['quantity'] ?? ''),
        'glass'  => trim(($g['glass_type'] ?? '') . ' ' . ($g['glass_color'] ?? '')),
        'motor'  => (string)($g['motor_system'] ?? ''),
        'ral'    => (string)($g['ral_code'] ?? ''),
        'total'  => $amount,
    ];
}

$vatRate = (float)($quote['vat_rate'] ?? 20);
$vat = $subTotal * $vatRate / 100;
$grandTotal = $subTotal + $vat;

define('FPDF_FONTPATH', __DIR__ . '/Roboto/');
require __DIR__ . '/../libs/fpdf.php';

$pdf = new FPDF('P', 'mm', 'A4');
$fontFile = __DIR__ . '/Roboto/Roboto-Regular.php';
if (is_file($fontFile)) {
    $pdf->AddFont('Roboto', '', 'Roboto-Regular.php');
    $fontName = 'Roboto';
} else {
    $fontName = 'Arial';
}

$pdf->SetTitle(enc('Teklif'));
$marginPx = 50;
$pageMargin = $marginPx / 3.78;
$pdf->SetMargins($pageMargin, $pageMargin, $pageMargin);
$pdf->SetAutoPageBreak(true, $pageMargin);
$pdf->AddPage();

$pdf->SetFont($fontName, '', 12);
$pdf->Cell(0, 6, enc('Teklif #' . ($quote['quote_no'] ?? $quote['id'])), 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont($fontName, '', 9);
$customerName = trim($quote['customer_company'] ?: ($quote['first_name'] . ' ' . $quote['last_name']));
$pdf->Cell(0, 5, enc('Müşteri: ' . $customerName), 0, 1);
$pdf->Cell(0, 5, enc('Tarih: ' . ($quote['offer_date'] ?? date('Y-m-d'))), 0, 1);
$pdf->Ln(3);

// Table header
$pdf->SetFont($fontName, 'B', 8);
$pdf->Cell(25, 8, enc('Sistem'), 1, 0);
$pdf->Cell(15, 8, enc('En'), 1, 0, 'R');
$pdf->Cell(15, 8, enc('Boy'), 1, 0, 'R');
$pdf->Cell(15, 8, enc('Adet'), 1, 0, 'R');
$pdf->Cell(35, 8, enc('Cam'), 1, 0);
$pdf->Cell(35, 8, enc('Motor'), 1, 0);
$pdf->Cell(20, 8, enc('RAL'), 1, 0);
$pdf->Cell(30, 8, enc('Toplam'), 1, 1, 'R');

$pdf->SetFont($fontName, '', 8);
foreach ($rows as $r) {
    $pdf->Cell(25, 7, enc($r['system']), 1, 0);
    $pdf->Cell(15, 7, enc($r['width']), 1, 0, 'R');
    $pdf->Cell(15, 7, enc($r['height']), 1, 0, 'R');
    $pdf->Cell(15, 7, enc($r['qty']), 1, 0, 'R');
    $pdf->Cell(35, 7, enc($r['glass']), 1, 0);
    $pdf->Cell(35, 7, enc($r['motor']), 1, 0);
    $pdf->Cell(20, 7, enc($r['ral']), 1, 0);
    $pdf->Cell(30, 7, enc(number_format($r['total'], 2, ',', '.') . ' ₺'), 1, 1, 'R');
}

$pdf->Ln(3);
$pdf->SetFont($fontName, '', 9);
$pdf->Cell(175, 6, enc('Ara Toplam'), 1, 0, 'R');
$pdf->Cell(30, 6, enc(number_format($subTotal, 2, ',', '.') . ' ₺'), 1, 1, 'R');
$pdf->Cell(175, 6, enc('KDV (' . $vatRate . '%)'), 1, 0, 'R');
$pdf->Cell(30, 6, enc(number_format($vat, 2, ',', '.') . ' ₺'), 1, 1, 'R');
$pdf->Cell(175, 6, enc('Genel Toplam'), 1, 0, 'R');
$pdf->Cell(30, 6, enc(number_format($grandTotal, 2, ',', '.') . ' ₺'), 1, 1, 'R');

$filenameBase = $quote['quote_no'] ?: (string)$quote['id'];
$filenameSafe = preg_replace('/[^A-Za-z0-9_-]/', '', $filenameBase);
$filename = 'Teklif_' . $filenameSafe . '_' . date('Ymd') . '.pdf';
$pdf->Output('D', $filename);

