<?php
$pageTitle = "Yeni Kategori Ekle";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_login();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Geçersiz güvenlik doğrulaması.');
    }

    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $errors[] = "Kategori adı boş olamaz.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (:name, :description)");
        $stmt->execute([
            ':name'        => $name,
            ':description' => $description !== '' ? $description : null
        ]);
        redirect('categories.php');
    }
}

require_once __DIR__ . '/../../templates/header.php';
?>

<h1 class="h3 mb-4">Yeni Kategori Ekle</h1>

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
        <label for="name" class="form-label">Kategori Adı</label>
        <input type="text" name="name" id="name" class="form-control"
               value="<?= e($_POST['name'] ?? ''); ?>" required>
    </div>
    <div class="mb-3">
        <label for="description" class="form-label">Açıklama</label>
        <textarea name="description" id="description" class="form-control" rows="3"><?= e($_POST['description'] ?? ''); ?></textarea>
    </div>
    <button type="submit" class="btn btn-success">Kaydet</button>
    <a href="categories.php" class="btn btn-secondary">İptal</a>
</form>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
