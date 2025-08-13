<?php
$pageTitle = "Teklif Sil";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('quotation.php');
}

// Kayıt var mı?
$stmt = $pdo->prepare("
    SELECT mq.id, mq.quote_no, c.name AS company_name, CONCAT(cus.first_name,' ',cus.last_name) AS customer_name
    FROM master_quotes mq
    LEFT JOIN companies c ON c.id = mq.company_id
    LEFT JOIN customers cus ON cus.id = mq.customer_id
    WHERE mq.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$quote = $stmt->fetch();
if (!$quote) {
    redirect('quotation.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Geçersiz güvenlik doğrulaması.');
    }
    $postId = (int)($_POST['id'] ?? 0);
    if ($postId !== (int)$quote['id']) {
        die('Kimlik doğrulaması başarısız.');
    }

    try {
        $del = $pdo->prepare("DELETE FROM master_quotes WHERE id = :id");
        $del->execute([':id' => $quote['id']]);
        redirect('quotation.php');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $error = "Bu teklif ilişkili kayıtlar (teklif kalemleri vb.) nedeniyle silinemiyor. Önce ilişkili kayıtları kaldırın.";
        } else {
            $error = "Silme sırasında bir hata oluştu.";
        }
    }
}

require_once __DIR__ . '/../../templates/header.php';
?>

<h1 class="h3 mb-4">Teklif Sil</h1>

<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error); ?></div>
<a href="quotation.php" class="btn btn-secondary">Listeye Dön</a>
<?php else: ?>
<div class="alert alert-warning">
    <strong>Uyarı:</strong>
    <em><?= e($quote['quote_no'] ?? ('#' . $quote['id'])); ?></em>
    (<?= e($quote['company_name'] ?? 'Şirket yok'); ?> / <?= e($quote['customer_name'] ?? 'Müşteri yok'); ?>)
    teklifini silmek üzeresiniz. Bu işlem geri alınamaz.
</div>

<form method="post">
    <?= csrf_field(); ?>
    <input type="hidden" name="id" value="<?= e((string)$quote['id']); ?>">
    <button type="submit" class="btn btn-danger">Evet, Sil</button>
    <a href="quotation.php" class="btn btn-secondary">İptal</a>
</form>
<?php endif; ?>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
