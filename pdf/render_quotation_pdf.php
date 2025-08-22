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

// ---- Fetch data ----
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Missing or invalid quotation id.');
}

$guillotines = [];
$slidings    = [];
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

    // Fetch guillotine and sliding systems
    $gStmt = $pdo->prepare('SELECT system_type, width, height, quantity, motor_system, ral_code, glass_type, glass_color, total_amount FROM guillotinesystems WHERE general_offer_id = :id');
    $gStmt->execute([':id' => $id]);
    $guillotines = $gStmt->fetchAll(PDO::FETCH_ASSOC);

    $sStmt = $pdo->prepare('SELECT system_type, width, height, quantity, wing_type, ral_code, glass_type, glass_color, total_amount FROM slidingsystems WHERE general_offer_id = :id');
    $sStmt->execute([':id' => $id]);
    $slidings = $sStmt->fetchAll(PDO::FETCH_ASSOC);

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
    $html = (function() use ($quote, $guillotines, $slidings, $company, $assemblyText, $paymentText, $validityText) {
        $tpl = __DIR__ . '/templates/quotation.tpl.php';
        if (is_file($tpl)) {
            // The template should read $quote, $items, $company, etc.
            ob_start();
            include $tpl;
            return ob_get_clean();
        } else {
            // Minimal inline HTML
            $customerFull = trim(($quote['first_name'] ?? '') . ' ' . ($quote['last_name'] ?? ''));
            $dateStr = $quote['offer_date'] ?? ($quote['created_at'] ?? date('Y-m-d'));
            $title = 'Teklif #' . (int)$quote['id'];

            $gRows = '';
            $gTotal = 0.0;
            foreach ($guillotines as $g) {
                $line = (float)($g['total_amount'] ?? 0);
                $gTotal += $line;
                $gRows .= '<tr>
                    <td>'.h($g['system_type'] ?? '').'</td>
                    <td>'.h($g['width'] ?? '').'</td>
                    <td>'.h($g['height'] ?? '').'</td>
                    <td>'.h($g['quantity'] ?? '').'</td>
                    <td>'.h(trim(($g['glass_type'] ?? '').' '.($g['glass_color'] ?? ''))).'</td>
                    <td>'.h($g['motor_system'] ?? '').'</td>
                    <td>'.h($g['ral_code'] ?? '').'</td>
                    <td style="text-align:right">'.number_format($line,2,',','.').'</td>
                </tr>';
            }

            $sRows = '';
            $sTotal = 0.0;
            foreach ($slidings as $s) {
                $line = (float)($s['total_amount'] ?? 0);
                $sTotal += $line;
                $sRows .= '<tr>
                    <td>'.h($s['system_type'] ?? '').'</td>
                    <td>'.h($s['width'] ?? '').'</td>
                    <td>'.h($s['height'] ?? '').'</td>
                    <td>'.h($s['quantity'] ?? '').'</td>
                    <td>'.h(trim(($s['glass_type'] ?? '').' '.($s['glass_color'] ?? ''))).'</td>
                    <td>'.h($s['wing_type'] ?? '').'</td>
                    <td>'.h($s['ral_code'] ?? '').'</td>
                    <td style="text-align:right">'.number_format($line,2,',','.').'</td>
                </tr>';
            }

            $grand = $gTotal + $sTotal;

            return '
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<style>
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11pt; }
h1 { font-size: 16pt; margin: 0 0 8pt; }
.table { width:100%; border-collapse: collapse; margin-bottom: 10px; }
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
  <h3>Giyotin Sistemleri</h3>
  '.($gRows ? '<table class="table"><thead><tr><th>Sistem</th><th>En</th><th>Boy</th><th>Adet</th><th>Cam</th><th>Motor</th><th>RAL</th><th class="text-right">Toplam</th></tr></thead><tbody>'.$gRows.'</tbody></table>' : '<p>Giyotin sistemi bulunamadı.</p>').'
  <h3>Sürme Sistemleri</h3>
  '.($sRows ? '<table class="table"><thead><tr><th>Sistem</th><th>En</th><th>Boy</th><th>Adet</th><th>Cam</th><th>Kanat</th><th>RAL</th><th class="text-right">Toplam</th></tr></thead><tbody>'.$sRows.'</tbody></table>' : '<p>Sürme sistemi bulunamadı.</p>').'
  <p class="text-right"><strong>Genel Toplam: '.number_format($grand,2,',','.').' </strong></p>
</body>
</html>';
        }
    })();

    try {
        $mpdfTemp = __DIR__ . '/../storage/mpdf_tmp';
        if (!is_dir($mpdfTemp)) {
            mkdir($mpdfTemp, 0777, true);
        }
        $mpdf = new \Mpdf\Mpdf(['tempDir' => $mpdfTemp]);
        $mpdf->WriteHTML($html);
        $mpdf->Output('teklif.pdf', \Mpdf\Output\Destination::INLINE);
        exit;
    } catch (Throwable $e) {
        error_log('mPDF render error: ' . $e->getMessage());
    }
}

// ---- FPDF fallback ----
define('FPDF_FONTPATH', __DIR__ . '/Roboto/');
require __DIR__ . '/../libs/fpdf.php';

$pdf = new FPDF();
$fontFile = __DIR__ . '/Roboto/Roboto-Regular.php';
if (is_file($fontFile)) {
    $pdf->AddFont('Roboto', '', 'Roboto-Regular.php');
    $fontName = 'Roboto';
} else {
    $fontName = 'Arial';
}
$pdf->SetTitle(enc('TEKLİF #' . (string)$quote['id']));
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// Header
$pdf->SetFont($fontName, 'B', 14);
$pdf->Cell(0, 8, enc('TEKLİF #' . (string)$quote['id']), 0, 1);
$pdf->SetFont($fontName, '', 11);
$customerFull = trim(($quote['first_name'] ?? '') . ' ' . ($quote['last_name'] ?? ''));
$pdf->Cell(0, 6, enc('Müşteri: ' . $customerFull), 0, 1);
if (!empty($assemblyText)) { $pdf->Cell(0, 6, enc('Montaj: ' . $assemblyText), 0, 1); }
if (!empty($paymentText))  { $pdf->Cell(0, 6, enc('Ödeme: ' . $paymentText), 0, 1); }
if (!empty($validityText)) { $pdf->Cell(0, 6, enc('Geçerlilik: ' . $validityText), 0, 1); }
$pdf->Ln(2);

$total = 0.0;

// Guillotine table
$pdf->SetFont($fontName, 'B', 10);
$pdf->Cell(0, 6, enc('Giyotin Sistemleri'), 0, 1);
if ($guillotines) {
    $pdf->SetFont($fontName, 'B', 9);
    $pdf->Cell(25, 7, enc('Sistem'), 1, 0);
    $pdf->Cell(20, 7, enc('En'), 1, 0, 'R');
    $pdf->Cell(20, 7, enc('Boy'), 1, 0, 'R');
    $pdf->Cell(15, 7, enc('Adet'), 1, 0, 'R');
    $pdf->Cell(30, 7, enc('Cam'), 1, 0);
    $pdf->Cell(25, 7, enc('Motor'), 1, 0);
    $pdf->Cell(20, 7, enc('RAL'), 1, 0);
    $pdf->Cell(35, 7, enc('Toplam'), 1, 1, 'R');
    $pdf->SetFont($fontName, '', 9);
    foreach ($guillotines as $g) {
        $line = (float)($g['total_amount'] ?? 0);
        $total += $line;
        $pdf->Cell(25, 6, enc((string)($g['system_type'] ?? '')), 1, 0);
        $pdf->Cell(20, 6, enc((string)($g['width'] ?? '')), 1, 0, 'R');
        $pdf->Cell(20, 6, enc((string)($g['height'] ?? '')), 1, 0, 'R');
        $pdf->Cell(15, 6, enc((string)($g['quantity'] ?? '')), 1, 0, 'R');
        $pdf->Cell(30, 6, enc(trim(($g['glass_type'] ?? '') . ' ' . ($g['glass_color'] ?? ''))), 1, 0);
        $pdf->Cell(25, 6, enc((string)($g['motor_system'] ?? '')), 1, 0);
        $pdf->Cell(20, 6, enc((string)($g['ral_code'] ?? '')), 1, 0);
        $pdf->Cell(35, 6, number_format($line, 2, ',', '.'), 1, 1, 'R');
    }
} else {
    $pdf->SetFont($fontName, '', 9);
    $pdf->Cell(0, 6, enc('Giyotin sistemi bulunamadı.'), 1, 1);
}
$pdf->Ln(4);

// Sliding table
$pdf->SetFont($fontName, 'B', 10);
$pdf->Cell(0, 6, enc('Sürme Sistemleri'), 0, 1);
if ($slidings) {
    $pdf->SetFont($fontName, 'B', 9);
    $pdf->Cell(25, 7, enc('Sistem'), 1, 0);
    $pdf->Cell(20, 7, enc('En'), 1, 0, 'R');
    $pdf->Cell(20, 7, enc('Boy'), 1, 0, 'R');
    $pdf->Cell(15, 7, enc('Adet'), 1, 0, 'R');
    $pdf->Cell(30, 7, enc('Cam'), 1, 0);
    $pdf->Cell(25, 7, enc('Kanat'), 1, 0);
    $pdf->Cell(20, 7, enc('RAL'), 1, 0);
    $pdf->Cell(35, 7, enc('Toplam'), 1, 1, 'R');
    $pdf->SetFont($fontName, '', 9);
    foreach ($slidings as $s) {
        $line = (float)($s['total_amount'] ?? 0);
        $total += $line;
        $pdf->Cell(25, 6, enc((string)($s['system_type'] ?? '')), 1, 0);
        $pdf->Cell(20, 6, enc((string)($s['width'] ?? '')), 1, 0, 'R');
        $pdf->Cell(20, 6, enc((string)($s['height'] ?? '')), 1, 0, 'R');
        $pdf->Cell(15, 6, enc((string)($s['quantity'] ?? '')), 1, 0, 'R');
        $pdf->Cell(30, 6, enc(trim(($s['glass_type'] ?? '') . ' ' . ($s['glass_color'] ?? ''))), 1, 0);
        $pdf->Cell(25, 6, enc((string)($s['wing_type'] ?? '')), 1, 0);
        $pdf->Cell(20, 6, enc((string)($s['ral_code'] ?? '')), 1, 0);
        $pdf->Cell(35, 6, number_format($line, 2, ',', '.'), 1, 1, 'R');
    }
} else {
    $pdf->SetFont($fontName, '', 9);
    $pdf->Cell(0, 6, enc('Sürme sistemi bulunamadı.'), 1, 1);
}
$pdf->Ln(4);

$pdf->SetFont($fontName, 'B', 11);
$pdf->Cell(0, 8, enc('Genel Toplam: ' . number_format($total, 2, ',', '.')), 0, 1, 'R');

try {
    if (ob_get_length()) {
        ob_end_clean();
    }
    $pdf->Output('I', 'teklif.pdf');
} catch (Throwable $e) {
    error_log('PDF Output error: ' . $e->getMessage());
    http_response_code(500);
    echo 'PDF oluşturulurken bir hata oluştu.';
}
