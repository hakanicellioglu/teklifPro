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

$stmt = $pdo->prepare('SELECT q.*, c.first_name, c.last_name, c.company, c.email, c.phone, c.address FROM master_quotes q JOIN customers c ON q.customer_id = c.id WHERE q.id = :id');
$stmt->execute([':id' => $id]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$quote) {
    http_response_code(404);
    exit('Not found');
}

$iStmt = $pdo->prepare('SELECT code, name, description, unit, qty, unit_price, discount_rate, vat_rate FROM quote_items WHERE quote_id = :id ORDER BY id');
$iStmt->execute([':id' => $id]);
$items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

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

require __DIR__ . '/../libs/fpdf.php';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="teklif.pdf"');

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(40, 10, 'Merhaba Hakan Bey!');
$pdf->Output('I', 'teklif.pdf');
