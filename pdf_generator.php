<?php
// PDF: Teklif çıktısı
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/utils.php';
require_once __DIR__ . '/helpers/auth.php';
require_login();

// === Girdi & Veri ===
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Geçersiz istek.');
}

// Teklif üst bilgisi
$q = $pdo->prepare("
    SELECT mq.id, mq.quote_no, mq.offer_date, mq.status, mq.total_amount, mq.notes,
           c.name  AS company_name,  c.address AS company_address, c.phone AS company_phone, c.email AS company_email,
           CONCAT(cus.first_name,' ',cus.last_name) AS customer_name, cus.company AS customer_company, cus.phone AS customer_phone, cus.email AS customer_email
    FROM master_quotes mq
    LEFT JOIN companies c   ON c.id   = mq.company_id
    LEFT JOIN customers cus ON cus.id = mq.customer_id
    WHERE mq.id = :id
    LIMIT 1
");
$q->execute([':id' => $id]);
$quote = $q->fetch();
if (!$quote) {
    http_response_code(404);
    exit('Teklif bulunamadı.');
}

// Kalemler
$i = $pdo->prepare("
    SELECT qi.id, qi.product_id, qi.description, qi.quantity, qi.unit, qi.unit_price,
           p.code AS product_code, p.name AS product_name
    FROM quote_items qi
    LEFT JOIN products p ON p.id = qi.product_id
    WHERE qi.quote_id = :qid
    ORDER BY qi.id ASC
");
try {
    $i->execute([':qid' => $quote['id']]);
    $items = $i->fetchAll();
} catch (PDOException $e) {
    $items = [];
}

// Hesaplamalar
$subTotal = 0.0;
foreach ($items as $it) {
    $subTotal += ((float)($it['unit_price'] ?? 0)) * ((float)($it['quantity'] ?? 0));
}
$grandTotal = $quote['total_amount'] !== null ? (float)$quote['total_amount'] : $subTotal;

// === HTML Şablon ===
$badge = match ($quote['status'] ?? 'draft') {
    'approved' => 'style="background:#28a745;color:#fff;padding:2px 6px;border-radius:4px;font-size:11px"',
    'rejected' => 'style="background:#dc3545;color:#fff;padding:2px 6px;border-radius:4px;font-size:11px"',
    default    => 'style="background:#6c757d;color:#fff;padding:2px 6px;border-radius:4px;font-size:11px"',
};

$rowsHtml = '';
if (!empty($items)) {
    foreach ($items as $idx => $it) {
        $qty   = (float)($it['quantity'] ?? 0);
        $price = (float)($it['unit_price'] ?? 0);
        $line  = $qty * $price;
        $rowsHtml .= '
        <tr>
            <td>'.e((string)($idx+1)).'</td>
            <td>'.e($it['product_code'] ?? '').'</td>
            <td>
                <div><strong>'.e($it['product_name'] ?? '').'</strong></div>'.
                (!empty($it['description']) ? '<div style="color:#6c757d;font-size:11px">'.nl2br(e($it['description'])).'</div>' : '')
            .'</td>
            <td style="text-align:right">'.e(number_format($qty, 2, ',', '.')).'</td>
            <td>'.e($it['unit'] ?? '').'</td>
            <td style="text-align:right">'.e(number_format($price, 2, ',', '.')).' ₺</td>
            <td style="text-align:right">'.e(number_format($line, 2, ',', '.')).' ₺</td>
        </tr>';
    }
} else {
    $rowsHtml = '<tr><td colspan="7" style="text-align:center;color:#6c757d">Kalem bulunamadı.</td></tr>';
}

$html = '
<html>
<head>
<meta charset="UTF-8">
<style>
*{font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size:12px;}
h1,h2,h3{margin:0;padding:0}
.table{width:100%;border-collapse:collapse;margin-top:10px}
.table th,.table td{border:1px solid #dee2e6;padding:6px}
.table thead th{background:#f8f9fa}
.header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #343a40;padding-bottom:8px;margin-bottom:8px}
.small{font-size:11px;color:#495057}
.kutu{border:1px solid #dee2e6;border-radius:6px;padding:8px}
.right{text-align:right}
.mt-8{margin-top:8px}.mt-16{margin-top:16px}
</style>
</head>
<body>
    <div class="header">
        <div>
            <h2>Teklif</h2>
            <div class="small">No: '.e($quote['quote_no'] ?? ('Q-'.$quote['id'])).'</div>
            <div class="small">Tarih: '.e($quote['offer_date'] ? date('d.m.Y', strtotime($quote['offer_date'])) : '').'</div>
            <div class="small">Durum: <span '.$badge.'>'.e($quote['status'] ?? 'draft').'</span></div>
        </div>
        <div class="right small">
            Oluşturma: '.date('d.m.Y H:i').'
        </div>
    </div>

    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td width="48%" class="kutu" valign="top">
                <strong>Şirket</strong><br/>
                '.e($quote['company_name'] ?? '—').'<br/>'.
                (!empty($quote['company_address']) ? nl2br(e($quote['company_address'])).'<br/>' : '').
                (!empty($quote['company_phone'])   ? e($quote['company_phone']).'<br/>' : '').
                (!empty($quote['company_email'])   ? e($quote['company_email']) : '')
            .'</td>
            <td width="4%"></td>
            <td width="48%" class="kutu" valign="top">
                <strong>Müşteri</strong><br/>
                '.e($quote['customer_name'] ?? '—').'<br/>'.
                (!empty($quote['customer_company']) ? e($quote['customer_company']).'<br/>' : '').
                (!empty($quote['customer_phone'])   ? e($quote['customer_phone']).'<br/>' : '').
                (!empty($quote['customer_email'])   ? e($quote['customer_email']) : '')
            .'</td>
        </tr>
    </table>

    <table class="table mt-16">
        <thead>
            <tr>
                <th style="width:40px">#</th>
                <th style="width:120px">Kod</th>
                <th>Ürün / Açıklama</th>
                <th style="width:90px" class="right">Miktar</th>
                <th style="width:70px">Birim</th>
                <th style="width:100px" class="right">Birim Fiyat</th>
                <th style="width:110px" class="right">Tutar</th>
            </tr>
        </thead>
        <tbody>'.$rowsHtml.'</tbody>
        <tfoot>
            <tr>
                <th colspan="6" class="right">Ara Toplam</th>
                <th class="right">'.e(number_format($subTotal, 2, ',', '.')).' ₺</th>
            </tr>'.
            ($quote['total_amount'] !== null && (float)$quote['total_amount'] !== (float)$subTotal
                ? '<tr>
                    <th colspan="6" class="right">Genel Toplam</th>
                    <th class="right">'.e(number_format((float)$quote['total_amount'], 2, ',', '.')).' ₺</th>
                   </tr>'
                : '')
        .'</tfoot>
    </table>'.

    (!empty($quote['notes']) ? '
    <div class="kutu mt-16">
        <strong>Notlar</strong><br/>'.
        nl2br(e($quote['notes'])).
    '</div>' : '') .'
</body>
</html>
';

// === mPDF ile çıktı ===
try {
    // mPDF (Composer ile kurulu olmalı: composer require mpdf/mpdf)
    if (!class_exists('\\Mpdf\\Mpdf')) {
        require_once __DIR__ . '/vendor/autoload.php';
    }
    if (!class_exists('\\Mpdf\\Mpdf')) {
        throw new RuntimeException('mPDF kurulumu bulunamadı. Lütfen "composer require mpdf/mpdf" komutuyla yükleyin.');
    }

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 10,
        'margin_right'=> 10,
        'margin_top'  => 10,
        'margin_bottom'=> 12,
        'tempDir' => __DIR__ . '/logs' // writables
    ]);
    $mpdf->SetTitle('Teklif - '.($quote['quote_no'] ?? ('Q-'.$quote['id'])));
    $mpdf->WriteHTML($html);
    $fileName = 'Teklif_'.preg_replace('/[^A-Za-z0-9_\-]/','_', $quote['quote_no'] ?? ('Q-'.$quote['id'])).'.pdf';
    $mpdf->Output($fileName, \Mpdf\Output\Destination::INLINE); // tarayıcıda göster
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "PDF oluşturulamadı.\n\n";
    echo "Hata: " . $e->getMessage() . "\n";
    echo "Not: mPDF kurulu değilse kurulum için: composer require mpdf/mpdf\n";
}
