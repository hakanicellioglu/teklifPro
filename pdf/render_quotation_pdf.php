<?php
declare(strict_types=1);

session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/../config.php';

/**
 * Composer is OPTIONAL. If present and mPDF is installed, we will render HTML template.
 * Otherwise we fall back to FPDF and draw a clean, data-driven layout.
 */
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}

function h(?string $v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function enc(string $s): string {
    // FPDF uses ISO-8859-1 by default; convert Turkish UTF-8 safely.
    $out = @iconv('UTF-8', 'ISO-8859-9//TRANSLIT', $s);
    return $out !== false ? $out : $s;
}

// ---- Fetch data ----
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Missing or invalid quotation id.');
}

try {
    // Quote master
    $stmt = $pdo->prepare("
        SELECT q.*,
               c.first_name, c.last_name, c.email AS customer_email, c.phone AS customer_phone,
               COALESCE(c.company_name, '') AS company_name
          FROM generaloffers q
          LEFT JOIN customers c ON c.id = q.customer_id
          
         WHERE q.id = :id
         LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$quote) {
        http_response_code(404);
        exit('Quotation not found.');
    }

    // Quote items (try a couple of likely tables; prefer generic quote_items if exists)
    $items = [];
    $tables = [
        "SELECT qi.*, p.code AS product_code, p.name AS product_name 
           FROM quote_items qi 
           LEFT JOIN products p ON p.id = qi.product_id
          WHERE qi.quote_id = :id
          ORDER BY qi.id",
        // fallback (if a different table name is used)
        "SELECT qi.*, p.code AS product_code, p.name AS product_name 
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
            if ($rows && count($rows) > 0) {
                $items = $rows;
                break;
            }
        } catch (Throwable $e) {
            // ignore and try next
        }
    }

    // Company profile (logo/name) if available
    $company = ['name' => '', 'email' => '', 'phone' => '', 'logo' => null, 'address' => ''];
    try {
        $st = $pdo->query("SELECT name, email, phone, logo, address FROM company LIMIT 1");
        if ($st) { $company = $st->fetch(PDO::FETCH_ASSOC) ?: $company; }
    } catch (Throwable $e) { /* optional */ }

} catch (Throwable $e) {
    http_response_code(500);
    exit('DB error: ' . h($e->getMessage()));
}

// Label maps
$assemblyLabels = [
    'demonte' => 'Demonte',
    'musteri' => 'Müşteri Montajlı',
    'bayi'    => 'Bayi Montajlı',
];
$paymentLabels = [
    'cash'          => 'Peşin',
    'bank_transfer' => 'Havale/EFT',
    'credit_card'   => 'Kredi Kartı',
    'installment'   => 'Taksitli',
    'other'         => 'Diğer',
];

$assemblyText = $assemblyLabels[$quote['assembly_type'] ?? ''] ?? ($quote['assembly_type'] ?? '');
$paymentText  = $paymentLabels[$quote['payment_method'] ?? ''] ?? ($quote['payment_method'] ?? '');
$validityText = isset($quote['validity_days']) && $quote['validity_days'] !== null && $quote['validity_days'] !== '' ? ((int)$quote['validity_days']).' gün' : '';

// Try to render with mPDF if available
$canUseMpdf = class_exists('\\Mpdf\\Mpdf');

if ($canUseMpdf) {
    // Build HTML via template if exists, else inline template
    $html = (function() use ($quote, $items, $company, $assemblyText, $paymentText, $validityText) {
        $tpl = __DIR__ . '/templates/quotation.tpl.php';
        if (is_file($tpl)) {
            // The template should read $quote, $items, $company, etc.
            ob_start();
            include $tpl;
            return ob_get_clean();
        } else {
            // Minimal inline HTML
            $customerFull = trim(($quote['first_name'] ?? '') . ' ' . ($quote['last_name'] ?? ''));
            $dateStr = $quote['created_at'] ?? date('Y-m-d');
            $title = 'Teklif #' . (int)$quote['id'];
            $rows = '';
            $total = 0.0;
            foreach ($items as $i => $it) {
                $qty = (float)($it['quantity'] ?? $it['qty'] ?? 0);
                $price = (float)($it['unit_price'] ?? $it['price'] ?? 0);
                $line = $qty * $price;
                $total += $line;
                $rows .= '<tr>
                    <td>'.($i+1).'</td>
                    <td>'.h($it['product_code'] ?? '').'</td>
                    <td>'.h($it['product_name'] ?? $it['name'] ?? '').'</td>
                    <td style="text-align:right">'.number_format($qty, 2, ',', '.').'</td>
                    <td style="text-align:right">'.number_format($price, 2, ',', '.').'</td>
                    <td style="text-align:right">'.number_format($line, 2, ',', '.').'</td>
                </tr>';
            }
            return '
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<style>
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11pt; }
h1 { font-size: 16pt; margin: 0 0 8pt; }
.table { width:100%; border-collapse: collapse; }
.table th, .table td { border:1px solid #888; padding:6px; }
.text-right { text-align:right; }
.small { font-size: 9pt; color:#333; }
</style>
</head>
<body>
  <h1>'.h($title).'</h1>
  <div class="small">Tarih: '.h($dateStr).'</div>
  <div class="small">Müşteri: '.h($customerFull).'</div>
  '.($assemblyText ? '<div class="small">Montaj: '.h($assemblyText).'</div>' : '').'
  '.($paymentText ? '<div class="small">Ödeme: '.h($paymentText).'</div>' : '').'
  '.($validityText ? '<div class="small">Geçerlilik: '.h($validityText).'</div>' : '').'

  <br>
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>Kod</th>
        <th>Ürün</th>
        <th class="text-right">Adet</th>
        <th class="text-right">Birim Fiyat</th>
        <th class="text-right">Tutar</th>
      </tr>
    </thead>
    <tbody>
      '.$rows.'
    </tbody>
  </table>
  <p class="text-right"><strong>Genel Toplam: ' . number_format($total, 2, ',', '.') . ' </strong></p>
</body>
</html>';
        }
    })();

    $mpdf = new \Mpdf\Mpdf(['tempDir' => __DIR__ . '/../storage/tmp']);
    $mpdf->WriteHTML($html);
    $mpdf->Output('teklif.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

// ---- FPDF fallback ----
require __DIR__ . '/../libs/fpdf.php';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename=\"teklif.pdf\"');

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

// Header
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 8, enc('TEKLİF #' . (string)$quote['id']), 0, 1);
$pdf->SetFont('Arial', '', 11);
$customerFull = trim(($quote['first_name'] ?? '') . ' ' . ($quote['last_name'] ?? ''));
$pdf->Cell(0, 6, enc('Müşteri: ' . $customerFull), 0, 1);
if (!empty($assemblyText)) { $pdf->Cell(0, 6, enc('Montaj: ' . $assemblyText), 0, 1); }
if (!empty($paymentText))  { $pdf->Cell(0, 6, enc('Ödeme: ' . $paymentText), 0, 1); }
if (!empty($validityText)) { $pdf->Cell(0, 6, enc('Geçerlilik: ' . $validityText), 0, 1); }
$pdf->Ln(2);

// Table header
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(10, 8, '#', 1, 0, 'C');
$pdf->Cell(30, 8, enc('Kod'), 1, 0);
$pdf->Cell(80, 8, enc('Ürün'), 1, 0);
$pdf->Cell(20, 8, enc('Adet'), 1, 0, 'R');
$pdf->Cell(25, 8, enc('Birim'), 1, 0, 'R');
$pdf->Cell(25, 8, enc('Tutar'), 1, 1, 'R');

$pdf->SetFont('Arial', '', 10);
$total = 0.0;
$index = 1;
foreach ($items as $it) {
    $qty   = (float)($it['quantity'] ?? $it['qty'] ?? 0);
    $price = (float)($it['unit_price'] ?? $it['price'] ?? 0);
    $line  = $qty * $price;
    $total += $line;

    $pdf->Cell(10, 7, (string)$index, 1, 0, 'C');
    $pdf->Cell(30, 7, enc((string)($it['product_code'] ?? '')), 1, 0);
    $pdf->Cell(80, 7, enc((string)($it['product_name'] ?? $it['name'] ?? '')), 1, 0);
    $pdf->Cell(20, 7, number_format($qty, 2, ',', '.'), 1, 0, 'R');
    $pdf->Cell(25, 7, number_format($price, 2, ',', '.'), 1, 0, 'R');
    $pdf->Cell(25, 7, number_format($line, 2, ',', '.'), 1, 1, 'R');
    $index++;
}

// Total
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(165, 8, enc('Genel Toplam'), 1, 0, 'R');
$pdf->Cell(25, 8, number_format($total, 2, ',', '.'), 1, 1, 'R');

$pdf->Output('I', 'teklif.pdf');
