<?php
require __DIR__ . '/../header.php';

$errors = [];
$success = null;

$first_name = '';
$last_name = '';
$email = '';
$phone = '';
$address = '';
$company_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email        = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $phone        = trim($_POST['phone'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');

    if ($first_name === '') {
        $errors[] = 'First name is required.';
    }
    if ($last_name === '') {
        $errors[] = 'Last name is required.';
    }
    if (!$email) {
        $errors[] = 'A valid email is required.';
    }
    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    }
    if ($address === '') {
        $errors[] = 'Address is required.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('INSERT INTO customers (first_name, last_name, company_name, email, phone, address) VALUES (:first_name, :last_name, :company_name, :email, :phone, :address)');
            $stmt->execute([
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'company_name' => $company_name,
                'email'      => $email,
                'phone'      => $phone,
                'address'    => $address,
            ]);
            $success = 'Customer added successfully.';
            $first_name = $last_name = $company_name = $email = $phone = $address = '';
        } catch (Exception $e) {
            $errors[] = 'Failed to add customer.';
        }
    }
}
?>
<div class='container py-4'>
    <div class='card shadow-sm'>
        <div class='card-body'>
            <h4 class='card-title mb-3'>Müşteri Ekle</h4>

            <?php if ($success): ?>
                <div class='alert alert-success alert-dismissible fade show' role='alert'>
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class='alert alert-danger alert-dismissible fade show' role='alert'>
                    <?php foreach ($errors as $error): ?>
                        <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endforeach; ?>
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>
            <?php endif; ?>

            <form method='post' class='needs-validation' novalidate>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='first_name' class='form-label'>İsim</label>
                        <input type='text' class='form-control' id='first_name' name='first_name' value='<?= htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8'); ?>' required>
                        <div class='invalid-feedback'>Lütfen isminizi girin</div>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='last_name' class='form-label'>Soyisim</label>
                        <input type='text' class='form-control' id='last_name' name='last_name' value='<?= htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8'); ?>' required>
                        <div class='invalid-feedback'>Lütfen soyisminizi girin</div>
                    </div>
                </div>
                <div class='mb-3'>
                    <label for='company_name' class='form-label'>Şirket</label>
                    <input type='text' class='form-control' id='company_name' name='company_name' value='<?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8'); ?>'>
                </div>
                <div class='mb-3'>
                    <label for='email' class='form-label'>Email</label>
                    <input type='email' class='form-control' id='email' name='email' value='<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>' required>
                    <div class='invalid-feedback'>Lütfen geçerli bir email adresi girin.</div>
                </div>
                <div class='mb-3'>
                    <label for='phone' class='form-label'>Telefon Numarası</label>
                    <input type='text' class='form-control' id='phone' name='phone' value='<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>' required>
                    <div class='invalid-feedback'>Lütfen bir telefon numarası girin.</div>
                </div>
                <div class='mb-3'>
                    <label for='address' class='form-label'>Address</label>
                    <textarea class='form-control' id='address' name='address' rows='3' required><?= htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <div class='invalid-feedback'>Lütfen adres girin.</div>
                </div>
                <button type='submit' class='btn btn-primary'>Ekle</button>
                <a href='../customer' class='btn btn-secondary ms-2'>Geri Dön</a>
            </form>
        </div>
    </div>
</div>
<script>
(() => {
  'use strict';
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(form => {
    form.addEventListener('submit', event => {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
</script>
</body>
</html>