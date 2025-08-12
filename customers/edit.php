<?php
require __DIR__ . '/../header.php';

// Retrieve and validate customer ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: ../customer?error=' . urlencode('Invalid customer ID.'));
    exit;
}

// Fetch existing customer data
$stmt = $pdo->prepare('SELECT first_name, last_name, company_name, email, phone, address FROM customers WHERE id = :id');
$stmt->execute(['id' => $id]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: ../customer?error=' . urlencode('Customer not found.'));
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $lastName  = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $email       = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $phone       = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $address     = trim(filter_input(INPUT_POST, 'address', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $companyName = trim(filter_input(INPUT_POST, 'company_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

    if ($firstName === '') {
        $errors[] = 'First name is required.';
    }
    if ($lastName === '') {
        $errors[] = 'Last name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
            $stmt = $pdo->prepare('UPDATE customers SET first_name = :first_name, last_name = :last_name, company_name = :company_name, email = :email, phone = :phone, address = :address WHERE id = :id');
            $stmt->execute([
                'first_name'   => $firstName,
                'last_name'    => $lastName,
                'company_name' => $companyName,
                'email'        => $email,
                'phone'        => $phone,
                'address'      => $address,
                'id'           => $id,
            ]);
            $success = 'Customer updated successfully.';
            $customer = [
                'first_name'   => $firstName,
                'last_name'    => $lastName,
                'company_name' => $companyName,
                'email'        => $email,
                'phone'        => $phone,
                'address'      => $address,
            ];
        } catch (Exception $e) {
            $errors[] = 'Failed to update customer.';
        }
    }
}
?>
<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <h4 class="card-title mb-3">Müşteriyi Düzenle</h4>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <div class="mb-3">
                    <label for="first_name" class="form-label">İsim</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" required value="<?= htmlspecialchars($customer['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label for="last_name" class="form-label">Soyisim</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" required value="<?= htmlspecialchars($customer['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label for="company_name" class="form-label">Company Name</label>
                    <input type="text" class="form-control" id="company_name" name="company_name" value="<?= htmlspecialchars($customer['company_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($customer['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Telefon Numarası</label>
                    <input type="text" class="form-control" id="phone" name="phone" required value="<?= htmlspecialchars($customer['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label for="address" class="form-label">Adres</label>
                    <textarea class="form-control" id="address" name="address" rows="3" required><?= htmlspecialchars($customer['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="d-flex justify-content-end">
                    <a href="../customer" class="btn btn-secondary me-2">İptal</a>
                    <button type="submit" class="btn btn-primary">Güncelle</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>