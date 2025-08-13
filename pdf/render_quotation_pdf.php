<?php
declare(strict_types=1);

session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/../config.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    exit('Composer autoload file not found. Run "composer install".');
}
require $autoload;

function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function money_tr(float $v): string { return number_format($v, 2, ',', '.') . ' ₺'; }

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    exit('Not found');
}

$stmt = $pdo->prepare('SELECT g.*, c.first_name, c.last_name, COALESCE(c.company_name, c.company) AS company, c.email, c.phone, c.address FROM generaloffers g JOIN customers c ON g.customer_id = c.id WHERE g.id = :id');
$stmt->execute([':id' => $id]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$quote) {
    http_response_code(404);
    exit('Not found');
}

$items = [];
$gStmt = $pdo->prepare('SELECT system_type, width, height, quantity, total_amount FROM guillotinesystems WHERE general_offer_id = :id');
$gStmt->execute([':id' => $id]);
foreach ($gStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $qty = (int)$row['quantity'];
    $total = (float)$row['total_amount'];
    $unitPrice = $qty ? $total / $qty : $total;
    $items[] = [
        'code' => 'GU',
        'name' => $row['system_type'],
        'description' => $row['width'] . ' x ' . $row['height'],
        'unit' => 'adet',
        'qty' => $qty,
        'unit_price' => $unitPrice,
        'discount_rate' => 0,
        'vat_rate' => 0,
    ];
}
$sStmt = $pdo->prepare('SELECT system_type, width, height, quantity, total_amount FROM slidingsystems WHERE general_offer_id = :id');
$sStmt->execute([':id' => $id]);
foreach ($sStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $qty = (int)$row['quantity'];
    $total = (float)$row['total_amount'];
    $unitPrice = $qty ? $total / $qty : $total;
    $items[] = [
        'code' => 'SL',
        'name' => $row['system_type'],
        'description' => $row['width'] . ' x ' . $row['height'],
        'unit' => 'adet',
        'qty' => $qty,
        'unit_price' => $unitPrice,
        'discount_rate' => 0,
        'vat_rate' => 0,
    ];
}

$approveUrl = null;
if (!empty($quote['approve_token'])) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $approveUrl = 'https://' . $host . '/approve.php?t=' . urlencode($quote['approve_token']);
}

$subTotal = $discountTotal = $vatTotal = 0.0;
foreach ($items as $it) {
    $line = $it['qty'] * $it['unit_price'];
    $lineDisc = $line * $it['discount_rate'] / 100;
    $lineNet = $line - $lineDisc;
    $lineVat = $lineNet * $it['vat_rate'] / 100;
    $subTotal += $line;
    $discountTotal += $lineDisc;
    $vatTotal += $lineVat;
}
$grandTotal = $subTotal - $discountTotal + $vatTotal;

$company = [
    'name' => 'TeklifPro',
    'address' => '',
    'email' => 'info@example.com',
    'phone' => '+90 000 000 0000',
];

$header = (function () use ($company, $quote) {
    ob_start();
    include __DIR__ . '/templates/quotation.header.php';
    return ob_get_clean();
})();

$footer = (function () use ($company) {
    ob_start();
    include __DIR__ . '/templates/quotation.footer.php';
    return ob_get_clean();
})();

$body = (function () use ($quote, $items, $approveUrl, $subTotal, $discountTotal, $vatTotal, $grandTotal) {
    ob_start();
    include __DIR__ . '/templates/quotation.tpl.php';
    return ob_get_clean();
})();

$tmpDir = __DIR__ . '/../storage/mpdf_tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0777, true);
}

$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_top' => 42,
    'margin_bottom' => 18,
    'margin_left' => 12,
    'margin_right' => 12,
    'tempDir' => $tmpDir,
]);
$mpdf->use_kwt = true;
$mpdf->shrinkTablesToFit = 1;

$css = file_get_contents(__DIR__ . '/templates/quotation.css');
$mpdf->SetHTMLHeader($header);
$mpdf->SetHTMLFooter($footer);
$mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
$mpdf->WriteHTML($body, \Mpdf\HTMLParserMode::HTML_BODY);

$quoteNo = $quote['quote_no'] ?? $quote['id'];
$customer = $quote['company'] ?: trim(($quote['first_name'] ?? '') . ' ' . ($quote['last_name'] ?? ''));
$filename = 'Teklif_' . $quoteNo . '_' . preg_replace('/[^A-Za-z0-9]+/', '_', $customer) . '.pdf';

$mpdf->Output($filename, 'I');
