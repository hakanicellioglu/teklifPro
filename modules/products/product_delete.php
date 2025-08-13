<?php
$pageTitle = "Ürün Sil";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('product.php');
}

// Kayıt var mı?
$stmt = $pdo->prepare("SELECT id, code, name FROM products WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$product = $stmt->fetch();
if (!$product) {
    redirect('product.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Geçersiz güvenlik doğrulaması.');
    }
    $postId = (int)($_POST['id'] ?? 0);
    if ($postId !== (int)$product['id']) {
        die('Kimlik doğrulaması başarısız.');
    }

    try {
        $del = $pdo->prepare("DELETE FROM products WHERE id = :id");
        $del->execute([':id' => $product['id']]);
        redirect('product.php');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $error = "Bu ürün ilişkili kayıtlar (teklif kalemleri vb.) nedeniyle silinemiyor. Önce ilişkili kayıtları kaldırın.";
        } else {
            $error = "Silme sırasında bir hata oluştu.";
        }
    }
}

require_once __DIR__ . '/../../templates/header.php';
?>

<h1 class="h3 mb-4">Ürün Sil</h1>

<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error); ?></div>
<a href="product.php" class="btn btn-secondary">Listeye Dön</a>
<?php else: ?>
<div class="alert alert-warning">
    <strong>Uyarı:</strong>
    <em><?= e($product['code'] ? $product['code'] . ' - ' : ''); ?><?= e($product['name']); ?></em>
    adlı ürünü silmek üzeresiniz. Bu işlem geri alınamaz.
</div>

<form method="post">
    <?= csrf_field(); ?>
    <input type="hidden" name="id" value="<?= e((string)$product['id']); ?>">
    <button type="submit" class="btn btn-danger">Evet, Sil</button>
    <a href="product.php" class="btn btn-secondary">İptal</a>
</form>
<?php endif; ?>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
