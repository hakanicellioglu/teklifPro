<?php
declare(strict_types=1);

// Bootstrap application (session, DB, helpers)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';

// --- CSRF check -----------------------------------------------------------
$sessionToken = $_SESSION['csrf_token'] ?? '';
$token = filter_input(INPUT_GET, 'csrf_token', FILTER_SANITIZE_STRING);
if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

// --- Fetch quotation ------------------------------------------------------
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    exit('Quotation not found');
}

try {
    // Master quotation data
    $stmt = $pdo->prepare(
        "SELECT g.*, c.first_name, c.last_name, c.company_name, c.email, c.phone, c.address
           FROM generaloffers g
           LEFT JOIN customers c ON c.id = g.customer_id
          WHERE g.id = :id
          LIMIT 1"
    );
    $stmt->execute([':id' => $id]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$quote) {
        http_response_code(404);
        exit('Quotation not found');
    }

    // Line items (try multiple table names)
    $items = [];
    $tables = [
        "SELECT qi.*, p.code, p.name, p.unit
           FROM quote_items qi
           LEFT JOIN products p ON p.id = qi.product_id
          WHERE qi.quote_id = :id
          ORDER BY qi.id",
        "SELECT qi.*, p.code, p.name, p.unit
           FROM quotation_items qi
           LEFT JOIN products p ON p.id = qi.product_id
          WHERE qi.quotation_id = :id
          ORDER BY qi.id",
    ];
    foreach ($tables as $sql) {
        try {
            $st = $pdo->prepare($sql);
            $st->execute([':id' => $id]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                $items = $rows;
                break;
            }
        } catch (Throwable $e) {
            // try next
        }
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Server error');
}

// --- Maps and helpers -----------------------------------------------------
$assemblyMap = [
    'demonte' => 'Demonte',
    'musteri' => 'Müşteri Montajlı',
    'bayi'    => 'Bayi Montajlı',
];

$paymentMap = [
    'cash'          => 'Peşin',
    'bank_transfer' => 'Havale/EFT',
    'credit_card'   => 'Kredi Kartı',
    'installment'   => 'Taksitli',
    'other'         => 'Diğer',
];

// Currency formatter
function money_tr(float $v): string {
    return number_format($v, 2, ',', '.') . ' ₺';
}

// --- Totals ---------------------------------------------------------------
$subTotal = 0.0;
$discountTotal = 0.0;
$vatTotal = 0.0;
$grandTotal = 0.0;

foreach ($items as &$it) {
    $qty  = (float)($it['quantity'] ?? $it['qty'] ?? 0);
    $price = (float)($it['unit_price'] ?? $it['price'] ?? 0);
    $discRate = (float)($it['discount_rate'] ?? 0);
    $vatRate  = (float)($it['vat_rate'] ?? 0);

    $line = $qty * $price;
    $disc = $line * $discRate / 100;
    $net  = $line - $disc;
    $vat  = $net * $vatRate / 100;
    $total = $net + $vat;

    $it['qty'] = $qty;
    $it['unit_price'] = $price;
    $it['disc_rate'] = $discRate;
    $it['vat_rate'] = $vatRate;
    $it['line_total'] = $total;

    $subTotal     += $line;
    $discountTotal += $disc;
    $vatTotal     += $vat;
    $grandTotal   += $total;
}
unset($it);

// --- PDF generation using FPDF (same pipeline as optimization PDF) -------
define('FPDF_FONTPATH', __DIR__ . '/Roboto/');
require __DIR__ . '/../libs/fpdf.php';

$pdf = new FPDF();
$fontFile = __DIR__ . '/Roboto/Roboto-Regular.php';
if (is_file($fontFile)) {
    $pdf->AddFont('Roboto', '', 'Roboto-Regular.php');
    $font = 'Roboto';
} else {
    $font = 'Arial';
}

$marginPx = 50; // match optimization PDF
$pageMargin = $marginPx / 3.78;
$pdf->SetMargins($pageMargin, $pageMargin, $pageMargin);
$pdf->SetAutoPageBreak(true, $pageMargin);
$pdf->AddPage();

// Header
$title = 'Teklif #' . ($quote['quote_no'] ?? $quote['id']);
$pdf->SetFont($font, '', 12);
$pdf->Cell(0, 8, enc($title), 0, 1, 'C');
$pdf->Ln(2);

$customer = trim(($quote['first_name'] ?? '') . ' ' . ($quote['last_name'] ?? ''));
if (!empty($quote['company_name'])) {
    $customer = $quote['company_name'];
}
$pdf->SetFont($font, '', 9);
$pdf->Cell(0, 5, enc('Müşteri: ' . $customer), 0, 1);
$pdf->Cell(0, 5, enc('Tarih: ' . date('d.m.Y', strtotime($quote['offer_date'] ?? 'now'))), 0, 1);
$assembly = $assemblyMap[$quote['assembly_type'] ?? ''] ?? '';
if ($assembly) {
    $pdf->Cell(0, 5, enc('Montaj: ' . $assembly), 0, 1);
}
$payment = $paymentMap[$quote['payment_method'] ?? ''] ?? '';
if ($payment) {
    $pdf->Cell(0, 5, enc('Ödeme: ' . $payment), 0, 1);
}
if (!empty($quote['validity_days'])) {
    $pdf->Cell(0, 5, enc('Geçerlilik: ' . (int)$quote['validity_days'] . ' gün'), 0, 1);
}
$pdf->Ln(3);

// Table header
$pdf->SetFont($font, 'B', 9);
$pdf->Cell(8, 8, '#', 1, 0, 'C');
$pdf->Cell(28, 8, enc('Kod'), 1, 0);
$pdf->Cell(60, 8, enc('Ürün'), 1, 0);
$pdf->Cell(14, 8, enc('Birim'), 1, 0, 'C');
$pdf->Cell(18, 8, enc('Adet'), 1, 0, 'R');
$pdf->Cell(26, 8, enc('Birim Fiyat'), 1, 0, 'R');
$pdf->Cell(15, 8, enc('KDV%'), 1, 0, 'R');
$pdf->Cell(25, 8, enc('Tutar'), 1, 1, 'R');

$pdf->SetFont($font, '', 9);
$index = 1;
foreach ($items as $it) {
    $pdf->Cell(8, 7, (string)$index, 1, 0, 'C');
    $pdf->Cell(28, 7, enc((string)($it['code'] ?? '')), 1, 0);
    $pdf->Cell(60, 7, enc((string)($it['name'] ?? '')), 1, 0);
    $pdf->Cell(14, 7, enc((string)($it['unit'] ?? '')), 1, 0, 'C');
$pdf->Cell(18, 7, number_format($it['qty'], 2, ',', '.'), 1, 0, 'R');
$pdf->Cell(26, 7, number_format($it['unit_price'], 2, ',', '.') . ' ₺', 1, 0, 'R');
$pdf->Cell(15, 7, number_format($it['vat_rate'], 2, ',', '.'), 1, 0, 'R');
$pdf->Cell(25, 7, number_format($it['line_total'], 2, ',', '.') . ' ₺', 1, 1, 'R');
    $index++;
}

// Totals
$pdf->SetFont($font, 'B', 9);
$pdf->Cell(155, 7, enc('Ara Toplam'), 1, 0, 'R');
$pdf->Cell(25, 7, number_format($subTotal, 2, ',', '.') . ' ₺', 1, 1, 'R');
$pdf->Cell(155, 7, enc('İskonto'), 1, 0, 'R');
$pdf->Cell(25, 7, number_format($discountTotal, 2, ',', '.') . ' ₺', 1, 1, 'R');
$pdf->Cell(155, 7, enc('KDV'), 1, 0, 'R');
$pdf->Cell(25, 7, number_format($vatTotal, 2, ',', '.') . ' ₺', 1, 1, 'R');
$pdf->Cell(155, 7, enc('Genel Toplam'), 1, 0, 'R');
$pdf->Cell(25, 7, number_format($grandTotal, 2, ',', '.') . ' ₺', 1, 1, 'R');

if (!empty($quote['notes'])) {
    $pdf->Ln(4);
    $pdf->SetFont($font, '', 9);
    $pdf->MultiCell(0, 5, enc('Notlar: ' . $quote['notes']));
}

// --- Output ---------------------------------------------------------------
$custSafe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $customer ?: 'Musteri');
$quoteNoSafe = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)($quote['quote_no'] ?? $quote['id']));
$fileName = 'Teklif_' . $quoteNoSafe . '_' . $custSafe . '_' . date('Ymd') . '.pdf';

$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;
exit;

