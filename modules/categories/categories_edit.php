<?php
$pageTitle = "Kategori Düzenle";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('categories.php');
}

// Kategori kaydını çek
$stmt = $pdo->prepare("SELECT id, name, description FROM categories WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$category = $stmt->fetch();
if (!$category) {
    redirect('categories.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Geçersiz güvenlik doğrulaması.');
    }

    $postId     = (int)($_POST['id'] ?? 0);
    if ($postId !== (int)$category['id']) {
        die('Kimlik doğrulaması başarısız.');
    }

    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $errors[] = "Kategori adı boş olamaz.";
    }

    if (!$errors) {
        $upd = $pdo->prepare("
            UPDATE categories
            SET name = :name,
                description = :description
            WHERE id = :id
        ");
        $upd->execute([
            ':name'        => $name,
            ':description' => $description !== '' ? $description : null,
            ':id'          => $category['id'],
        ]);

        redirect('categories.php');
    } else {
        $category['name']        = $name;
        $category['description'] = $description;
    }
}

require_once __DIR__ . '/../../templates/header.php';
?>

<h1 class="h3 mb-4">Kategori Düzenle</h1>

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
    <input type="hidden" name="id" value="<?= e((string)$category['id']); ?>">

    <div class="mb-3">
        <label for="name" class="form-label">Kategori Adı</label>
        <input type="text" name="name" id="name" class="form-control"
               value="<?= e($category['name']); ?>" required>
    </div>

    <div class="mb-3">
        <label for="description" class="form-label">Açıklama</label>
        <textarea name="description" id="description" class="form-control" rows="3"><?= e($category['description'] ?? ''); ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary">Güncelle</button>
    <a href="categories.php" class="btn btn-secondary">İptal</a>
</form>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
