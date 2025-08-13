<?php
$pageTitle = "Kategori Listesi";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_login();

$stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY name ASC");
$categories = $stmt->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Kategoriler</h1>
    <a href="categories_add.php" class="btn btn-primary">+ Yeni Kategori</a>
</div>

<table class="table table-bordered table-striped align-middle">
    <thead>
        <tr>
            <th width="80">ID</th>
            <th>Ad</th>
            <th>Açıklama</th>
            <th width="180">İşlemler</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($categories as $cat): ?>
        <tr>
            <td><?= e($cat['id']); ?></td>
            <td><?= e($cat['name']); ?></td>
            <td><?= e($cat['description'] ?? ''); ?></td>
            <td>
                <a href="categories_edit.php?id=<?= e($cat['id']); ?>" class="btn btn-sm btn-warning">Düzenle</a>
                <a href="categories_delete.php?id=<?= e($cat['id']); ?>" class="btn btn-sm btn-danger"
                   onclick="return confirm('Bu kategoriyi silmek istediğinize emin misiniz?');">Sil</a>
            </td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($categories)): ?>
        <tr>
            <td colspan="4" class="text-center text-muted">Kayıt bulunamadı.</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
