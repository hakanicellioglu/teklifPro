<?php
$pageTitle = "Ürün Düzenle";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('product.php');
}

// Ürün kaydını çek
$stmt = $pdo->prepare("
    SELECT id, code, name, category_id, unit_price, unit
    FROM products
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$product = $stmt->fetch();
if (!$product) {
    redirect('product.php');
}

// Kategorileri çek
$catStmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Geçersiz güvenlik doğrulaması.');
    }

    $postId     = (int)($_POST['id'] ?? 0);
    if ($postId !== (int)$product['id']) {
        die('Kimlik doğrulaması başarısız.');
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

    if (!$errors) {
        $upd = $pdo->prepare("
            UPDATE products
            SET code = :code,
                name = :name,
                category_id = :category_id,
                unit_price = :unit_price,
                unit = :unit
            WHERE id = :id
        ");
        $upd->execute([
            ':code'        => $code,
            ':name'        => $name,
            ':category_id' => $categoryId ?: null,
            ':unit_price'  => ($unitPrice !== '' ? (float)$unitPrice : null),
            ':unit'        => $unit,
            ':id'          => $product['id'],
        ]);

        redirect('product.php');
    } else {
        // Formu kullanıcı girdileriyle tekrar doldur
        $product['code']        = $code;
        $product['name']        = $name;
        $product['category_id'] = $categoryId ?: null;
        $product['unit_price']  = ($unitPrice !== '' ? (float)$unitPrice : null);
        $product['unit']        = $unit;
    }
}

require_once __DIR__ . '/../../templates/header.php';
?>

<h1 class="h3 mb-4">Ürün Düzenle</h1>

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
    <input type="hidden" name="id" value="<?= e((string)$product['id']); ?>">

    <div class="mb-3">
        <label for="code" class="form-label">Ürün Kodu</label>
        <input type="text" name="code" id="code" class="form-control"
               value="<?= e($product['code'] ?? ''); ?>">
    </div>

    <div class="mb-3">
        <label for="name" class="form-label">Ürün Adı</label>
        <input type="text" name="name" id="name" class="form-control"
               value="<?= e($product['name'] ?? ''); ?>" required>
    </div>

    <div class="mb-3">
        <label for="category_id" class="form-label">Kategori</label>
        <select name="category_id" id="category_id" class="form-select">
            <option value="">— Seçiniz —</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat['id']); ?>"
                    <?= ((int)($product['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : ''; ?>>
                    <?= e($cat['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label for="unit_price" class="form-label">Birim Fiyat</label>
        <input type="text" name="unit_price" id="unit_price" class="form-control"
               value="<?= e($product['unit_price'] !== null ? (string)$product['unit_price'] : ''); ?>">
    </div>

    <div class="mb-3">
        <label for="unit" class="form-label">Birim</label>
        <input type="text" name="unit" id="unit" class="form-control"
               value="<?= e($product['unit'] ?? ''); ?>">
    </div>

    <button type="submit" class="btn btn-primary">Güncelle</button>
    <a href="product.php" class="btn btn-secondary">İptal</a>
</form>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
