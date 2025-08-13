<?php
$pageTitle = "Yeni Ürün Ekle";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_login();

// Kategorileri çek
$catStmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Geçersiz güvenlik doğrulaması.');
    }

    $code       = trim($_POST['code'] ?? '');
    $name       = trim($_POST['name'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $unitPrice  = trim($_POST['unit_price'] ?? '');
    $unit       = trim($_POST['unit'] ?? '');

    if ($name === '') $errors[] = "Ürün adı boş olamaz.";
    if ($unitPrice !== '' && !is_numeric($unitPrice)) {
        $errors[] = "Birim fiyat sayı olmalıdır.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO products (code, name, category_id, unit_price, unit)
            VALUES (:code, :name, :category_id, :unit_price, :unit)
        ");
        $stmt->execute([
            ':code'        => $code,
            ':name'        => $name,
            ':category_id' => $categoryId ?: null,
            ':unit_price'  => $unitPrice !== '' ? (float)$unitPrice : null,
            ':unit'        => $unit,
        ]);
        redirect('product.php');
    }
}

require_once __DIR__ . '/../../templates/header.php';
?>

<h1 class="h3 mb-4">Yeni Ürün Ekle</h1>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
            <li><?= e($err); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="post">
    <?= csrf_field(); ?>
    <div class="mb-3">
        <label for="code" class="form-label">Ürün Kodu</label>
        <input type="text" name="code" id="code" class="form-control" value="<?= e($_POST['code'] ?? ''); ?>">
    </div>
    <div class="mb-3">
        <label for="name" class="form-label">Ürün Adı</label>
        <input type="text" name="name" id="name" class="form-control" value="<?= e($_POST['name'] ?? ''); ?>" required>
    </div>
    <div class="mb-3">
        <label for="category_id" class="form-label">Kategori</label>
        <select name="category_id" id="category_id" class="form-select">
            <option value="">— Seçiniz —</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat['id']); ?>" <?= (($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : ''; ?>>
                    <?= e($cat['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3">
        <label for="unit_price" class="form-label">Birim Fiyat</label>
        <input type="text" name="unit_price" id="unit_price" class="form-control" value="<?= e($_POST['unit_price'] ?? ''); ?>">
    </div>
    <div class="mb-3">
        <label for="unit" class="form-label">Birim</label>
        <input type="text" name="unit" id="unit" class="form-control" value="<?= e($_POST['unit'] ?? ''); ?>">
    </div>
    <button type="submit" class="btn btn-success">Kaydet</button>
    <a href="product.php" class="btn btn-secondary">İptal</a>
</form>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
