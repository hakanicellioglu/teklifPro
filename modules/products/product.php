<?php
$pageTitle = "Ürün Listesi";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_login();

// Ürünleri kategori ile birlikte çek
$sql = "
SELECT 
    p.id, 
    p.code, 
    p.name, 
    c.name AS category, 
    p.unit_price, 
    p.unit
FROM products p
LEFT JOIN categories c ON c.id = p.category_id
ORDER BY p.id DESC
";
$stmt = $pdo->query($sql);
$products = $stmt->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Ürünler</h1>
    <a href="product_add.php" class="btn btn-primary">+ Yeni Ürün</a>
</div>

<table class="table table-bordered table-striped align-middle">
    <thead>
        <tr>
            <th width="60">ID</th>
            <th width="160">Kod</th>
            <th>Ad</th>
            <th width="160">Kategori</th>
            <th width="140">Birim Fiyat</th>
            <th width="100">Birim</th>
            <th width="160">İşlemler</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
            <td><?= e($p['id']); ?></td>
            <td><?= e($p['code'] ?? ''); ?></td>
            <td><?= e($p['name'] ?? ''); ?></td>
            <td><?= e($p['category'] ?? '—'); ?></td>
            <td>
                <?php 
                    $price = $p['unit_price'];
                    echo $price !== null && $price !== '' 
                        ? e(number_format((float)$price, 2, ',', '.')) . ' ₺' 
                        : '—';
                ?>
            </td>
            <td><?= e($p['unit'] ?? ''); ?></td>
            <td>
                <a href="product_edit.php?id=<?= e($p['id']); ?>" class="btn btn-sm btn-warning">Düzenle</a>
                <a href="product_delete.php?id=<?= e($p['id']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Silmek istediğinize emin misiniz?');">Sil</a>
            </td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($products)): ?>
        <tr>
            <td colspan="7" class="text-center text-muted">Kayıt bulunamadı.</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
