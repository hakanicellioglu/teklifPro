<?php
declare(strict_types=1);

require BASE_PATH . '/config/config.php';

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

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $decision = $_POST['decision'] ?? '';
    if (in_array($decision, ['accepted', 'rejected'], true)) {
        $upd = $pdo->prepare('UPDATE generaloffers SET status = :st, approved_at = NOW() WHERE id = :id');
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
    <form method="post" class="d-flex gap-2">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <button type="submit" name="decision" value="accepted" class="btn btn-success">Onayla</button>
        <button type="submit" name="decision" value="rejected" class="btn btn-danger">Reddet</button>
    </form>
</div>
</body>
</html>
