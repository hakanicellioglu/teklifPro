<?php
$pageTitle = "Yeni Müşteri Ekle";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_login();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Geçersiz güvenlik doğrulaması.');
    }

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $company    = trim($_POST['company'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');

    if ($first_name === '') $errors[] = "Ad alanı boş olamaz.";
    if ($last_name === '')  $errors[] = "Soyad alanı boş olamaz.";
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Geçerli bir e-posta adresi girin.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO customers (first_name, last_name, company, email, phone) VALUES (:first_name, :last_name, :company, :email, :phone)");
        $stmt->execute([
            ':first_name' => $first_name,
            ':last_name'  => $last_name,
            ':company'    => $company,
            ':email'      => $email,
            ':phone'      => $phone
        ]);
        redirect('customer.php');
    }
}

require_once __DIR__ . '/../../templates/header.php';
?>

<h1 class="h3 mb-4">Yeni Müşteri Ekle</h1>

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
        <label for="first_name" class="form-label">Ad</label>
        <input type="text" name="first_name" id="first_name" class="form-control" value="<?= e($_POST['first_name'] ?? ''); ?>" required>
    </div>
    <div class="mb-3">
        <label for="last_name" class="form-label">Soyad</label>
        <input type="text" name="last_name" id="last_name" class="form-control" value="<?= e($_POST['last_name'] ?? ''); ?>" required>
    </div>
    <div class="mb-3">
        <label for="company" class="form-label">Şirket</label>
        <input type="text" name="company" id="company" class="form-control" value="<?= e($_POST['company'] ?? ''); ?>">
    </div>
    <div class="mb-3">
        <label for="email" class="form-label">E-posta</label>
        <input type="email" name="email" id="email" class="form-control" value="<?= e($_POST['email'] ?? ''); ?>">
    </div>
    <div class="mb-3">
        <label for="phone" class="form-label">Telefon</label>
        <input type="text" name="phone" id="phone" class="form-control" value="<?= e($_POST['phone'] ?? ''); ?>">
    </div>
    <button type="submit" class="btn btn-success">Kaydet</button>
    <a href="customer.php" class="btn btn-secondary">İptal</a>
</form>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
