<?php
$pageTitle = "Müşteri Sil";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('customer.php');
}

// Kayıt var mı?
$stmt = $pdo->prepare("SELECT id, first_name, last_name, company FROM customers WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$customer = $stmt->fetch();
if (!$customer) {
    redirect('customer.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Geçersiz güvenlik doğrulaması.');
    }
    $postId = (int)($_POST['id'] ?? 0);
    if ($postId !== (int)$customer['id']) {
        die('Kimlik doğrulaması başarısız.');
    }

    try {
        $del = $pdo->prepare("DELETE FROM customers WHERE id = :id");
        $del->execute([':id' => $customer['id']]);
        redirect('customer.php');
    } catch (PDOException $e) {
        // 23000: Integrity constraint violation (muhtemel FK bağımlılığı)
        if ($e->getCode() === '23000') {
            $error = "Bu müşteri bağlı kayıtlar (teklifler vb.) nedeniyle silinemiyor. Önce ilişkili kayıtları kaldırın.";
        } else {
            $error = "Silme sırasında bir hata oluştu.";
        }
    }
}

require_once __DIR__ . '/../../templates/header.php';
?>

<h1 class="h3 mb-4">Müşteri Sil</h1>

<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error); ?></div>
<a href="customer.php" class="btn btn-secondary">Listeye Dön</a>
<?php else: ?>
<div class="alert alert-warning">
    <strong>Uyarı:</strong> <em><?= e($customer['first_name'] . ' ' . $customer['last_name']); ?></em>
    (<?= e($customer['company'] ?: 'Şirket bilgisi yok'); ?>) adlı müşteriyi silmek üzeresiniz. Bu işlem geri alınamaz.
</div>

<form method="post">
    <?= csrf_field(); ?>
    <input type="hidden" name="id" value="<?= e((string)$customer['id']); ?>">
    <button type="submit" class="btn btn-danger">Evet, Sil</button>
    <a href="customer.php" class="btn btn-secondary">İptal</a>
</form>
<?php endif; ?>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
