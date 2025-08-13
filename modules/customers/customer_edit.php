<?php
$pageTitle = "Müşteri Düzenle";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('customer.php');
}

// Kayıt çek
$stmt = $pdo->prepare("SELECT id, first_name, last_name, company, email, phone FROM customers WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$customer = $stmt->fetch();

if (!$customer) {
    // Kayıt yoksa listeye dön
    redirect('customer.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Geçersiz güvenlik doğrulaması.');
    }

    $postId     = (int)($_POST['id'] ?? 0);
    if ($postId !== (int)$customer['id']) {
        die('Kimlik doğrulaması başarısız.');
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

    if (!$errors) {
        $upd = $pdo->prepare(
            "UPDATE customers
             SET first_name = :first_name,
                 last_name  = :last_name,
                 company    = :company,
                 email      = :email,
                 phone      = :phone
             WHERE id = :id"
        );
        $upd->execute([
            ':first_name' => $first_name,
            ':last_name'  => $last_name,
            ':company'    => $company,
            ':email'      => $email,
            ':phone'      => $phone,
            ':id'         => $customer['id'],
        ]);
        redirect('customer.php');
    } else {
        // Formu kullanıcı girdileriyle tekrar doldur
        $customer['first_name'] = $first_name;
        $customer['last_name']  = $last_name;
        $customer['company']    = $company;
        $customer['email']      = $email;
        $customer['phone']      = $phone;
    }
}

require_once __DIR__ . '/../../templates/header.php';
?>

<h1 class="h3 mb-4">Müşteri Düzenle</h1>

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
    <input type="hidden" name="id" value="<?= e((string)$customer['id']); ?>">
    <div class="mb-3">
        <label for="first_name" class="form-label">Ad</label>
        <input type="text" name="first_name" id="first_name" class="form-control"
               value="<?= e($customer['first_name']); ?>" required>
    </div>
    <div class="mb-3">
        <label for="last_name" class="form-label">Soyad</label>
        <input type="text" name="last_name" id="last_name" class="form-control"
               value="<?= e($customer['last_name']); ?>" required>
    </div>
    <div class="mb-3">
        <label for="company" class="form-label">Şirket</label>
        <input type="text" name="company" id="company" class="form-control"
               value="<?= e($customer['company']); ?>">
    </div>
    <div class="mb-3">
        <label for="email" class="form-label">E-posta</label>
        <input type="email" name="email" id="email" class="form-control"
               value="<?= e($customer['email']); ?>">
    </div>
    <div class="mb-3">
        <label for="phone" class="form-label">Telefon</label>
        <input type="text" name="phone" id="phone" class="form-control"
               value="<?= e($customer['phone']); ?>">
    </div>

    <button type="submit" class="btn btn-primary">Güncelle</button>
    <a href="customer.php" class="btn btn-secondary">İptal</a>
</form>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
