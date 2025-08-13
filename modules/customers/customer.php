<?php
$pageTitle = "Müşteri Listesi";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_login();

$stmt = $pdo->query("SELECT id, first_name, last_name, company, email, phone FROM customers ORDER BY id DESC");
$customers = $stmt->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Müşteriler</h1>
    <a href="customer_add.php" class="btn btn-primary">+ Yeni Müşteri</a>
</div>

<table class="table table-bordered table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Ad Soyad</th>
            <th>Şirket</th>
            <th>E-posta</th>
            <th>Telefon</th>
            <th>İşlemler</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($customers as $c): ?>
        <tr>
            <td><?= e($c['id']); ?></td>
            <td><?= e($c['first_name'] . ' ' . $c['last_name']); ?></td>
            <td><?= e($c['company']); ?></td>
            <td><?= e($c['email']); ?></td>
            <td><?= e($c['phone']); ?></td>
            <td>
                <a href="customer_edit.php?id=<?= e($c['id']); ?>" class="btn btn-sm btn-warning">Düzenle</a>
                <a href="customer_delete.php?id=<?= e($c['id']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Silmek istediğinize emin misiniz?');">Sil</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
