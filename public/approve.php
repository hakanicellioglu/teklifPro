<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

function h(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$token = $_GET['token'] ?? '';
if ($token === '') {
    http_response_code(400);
    exit('Geçersiz bağlantı.');
}

$stmt = $pdo->prepare('SELECT g.*, c.first_name, c.last_name, c.company_name AS customer_company FROM generaloffers g LEFT JOIN customers c ON g.customer_id = c.id WHERE g.approval_token = :t LIMIT 1');
$stmt->execute([':t' => $token]);
$offer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$offer) {
    http_response_code(404);
    exit('Teklif bulunamadı.');
}

// Fetch related product rows
$guillotines = [];
$slidings = [];
$items = [];
$totalAmount = 0.0;

try {
    $gStmt = $pdo->prepare('SELECT system_type, width, height, quantity, total_amount FROM guillotinesystems WHERE general_offer_id = :id');
    $gStmt->execute([':id' => $offer['id']]);
    $guillotines = $gStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // ignore
}

try {
    $sStmt = $pdo->prepare('SELECT system_type, width, height, quantity, total_amount FROM slidingsystems WHERE general_offer_id = :id');
    $sStmt->execute([':id' => $offer['id']]);
    $slidings = $sStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // ignore
}

foreach ($guillotines as $g) {
    $items[] = [
        'system'   => $g['system_type'],
        'width'    => $g['width'],
        'height'   => $g['height'],
        'quantity' => $g['quantity'],
        'amount'   => $g['total_amount'],
    ];
    $totalAmount += (float)$g['total_amount'];
}
foreach ($slidings as $s) {
    $items[] = [
        'system'   => $s['system_type'],
        'width'    => $s['width'],
        'height'   => $s['height'],
        'quantity' => $s['quantity'],
        'amount'   => $s['total_amount'],
    ];
    $totalAmount += (float)$s['total_amount'];
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $offer['status'] === 'pending') {
    $decision = $_POST['decision'] ?? '';
    if (in_array($decision, ['accepted', 'rejected'], true)) {
        $upd = $pdo->prepare('UPDATE generaloffers SET status = :st, approved_at = NOW(), approval_token = NULL WHERE id = :id AND status = \'pending\'');
        $upd->execute([':st' => $decision, ':id' => $offer['id']]);
        $offer['status'] = $decision;
        $offer['approved_at'] = date('Y-m-d H:i:s');
        $message = $decision === 'accepted' ? 'Teklif onaylandı.' : 'Teklif reddedildi.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teklif Onayı</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h1 class="mb-4">Teklif Onayı</h1>
    <?php if ($message): ?>
        <div class="alert alert-info"><?= h($message) ?></div>
    <?php endif; ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="mb-2"><strong>Teklif No:</strong> <?= h($offer['quote_no'] ?? (string)$offer['id']) ?></div>
            <div class="mb-2"><strong>Müşteri:</strong> <?= h(trim(($offer['first_name'] ?? '') . ' ' . ($offer['last_name'] ?? ''))) ?></div>
            <?php if (!empty($offer['customer_company'])): ?>
                <div class="mb-2"><strong>Firma:</strong> <?= h($offer['customer_company']) ?></div>
            <?php endif; ?>
            <div class="mb-2"><strong>Tarih:</strong> <?= h($offer['offer_date'] ?? '') ?></div>
            <div class="mb-2"><strong>Durum:</strong> <?= h($offer['status']) ?></div>
            <?php if (!empty($offer['approved_at'])): ?>
                <div class="mb-2"><strong>Onay Tarihi:</strong> <?= h($offer['approved_at']) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($items): ?>
    <table class="table table-bordered mb-3">
        <thead>
            <tr>
                <th>Sistem Tipi</th>
                <th>Ölçüler</th>
                <th>Adet</th>
                <th>Tutar</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td><?= h($it['system']) ?></td>
                <td><?= h($it['width'] . ' x ' . $it['height']) ?></td>
                <td><?= h((string)$it['quantity']) ?></td>
                <td><?= h(tr_money((float)$it['amount'])) ?> ₺</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3" class="text-end">Toplam</th>
                <th><?= h(tr_money((float)$totalAmount)) ?> ₺</th>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>
    <form method="post" class="d-flex gap-2">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <button type="submit" name="decision" value="accepted" class="btn btn-success" <?= $offer['status'] !== 'pending' ? 'disabled' : '' ?>>Onayla</button>
        <button type="submit" name="decision" value="rejected" class="btn btn-danger" <?= $offer['status'] !== 'pending' ? 'disabled' : '' ?>>Reddet</button>
    </form>
</div>
</body>
</html>
