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
$stmt = $pdo->prepare('SELECT g.*, g.quote_no AS offer_title, c.first_name, c.last_name, c.company_name AS customer_company, co.name AS company_name FROM generaloffers g LEFT JOIN customers c ON g.customer_id = c.id LEFT JOIN company co ON g.company_id = co.id WHERE g.approval_token = :t LIMIT 1');
$stmt->execute([':t' => $token]);
$offer = $stmt->fetch(PDO::FETCH_ASSOC);
$error = '';
if (!$offer) {
    http_response_code(404);
    $error = 'Teklif bulunamadı.';
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
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php else: ?>
        <div class="card mb-3">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Şirket</dt>
                    <dd class="col-sm-8"><?= h($offer['company_name'] ?? '') ?></dd>
                    <dt class="col-sm-4">Teklif Başlığı</dt>
                    <dd class="col-sm-8"><?= h($offer['offer_title'] ?? '') ?></dd>
                    <dt class="col-sm-4">Teklif Tutarı</dt>
                    <dd class="col-sm-8"><?= h($offer['total_amount']) ?></dd>
                    <?php if (!empty($offer['offer_validity'])): ?>
                        <dt class="col-sm-4">Geçerlilik Tarihi</dt>
                        <dd class="col-sm-8"><?= h($offer['offer_validity']) ?></dd>
                    <?php endif; ?>
                    <dt class="col-sm-4">Müşteri</dt>
                    <dd class="col-sm-8"><?= h(trim(($offer['first_name'] ?? '') . ' ' . ($offer['last_name'] ?? ''))) ?></dd>
                    <?php if (!empty($offer['customer_company'])): ?>
                        <dt class="col-sm-4">Müşteri Firma</dt>
                        <dd class="col-sm-8"><?= h($offer['customer_company']) ?></dd>
                    <?php endif; ?>
                    <dt class="col-sm-4">Tarih</dt>
                    <dd class="col-sm-8"><?= h($offer['offer_date'] ?? '') ?></dd>
                    <dt class="col-sm-4">Durum</dt>
                    <dd class="col-sm-8"><?= h($offer['status']) ?></dd>
                    <?php if (!empty($offer['approved_at'])): ?>
                        <dt class="col-sm-4">Onay Tarihi</dt>
                        <dd class="col-sm-8"><?= h($offer['approved_at']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
        <?php if ($offer['status'] === 'pending'): ?>
            <form method="post" class="d-flex gap-2">
                <input type="hidden" name="token" value="<?= h($token) ?>">
                <button type="submit" name="decision" value="accepted" class="btn btn-success">Onayla</button>
                <button type="submit" name="decision" value="rejected" class="btn btn-danger">Reddet</button>
            </form>
        <?php elseif ($offer['status'] === 'accepted'): ?>
            <div class="alert alert-success">
                Bu teklif onaylanmıştır<?php if (!empty($offer['approved_at'])): ?> (<?= h($offer['approved_at']) ?>)<?php endif; ?>.
            </div>
        <?php elseif ($offer['status'] === 'rejected'): ?>
            <div class="alert alert-danger">
                Bu teklif reddedilmiştir<?php if (!empty($offer['approved_at'])): ?> (<?= h($offer['approved_at']) ?>)<?php endif; ?>.
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
