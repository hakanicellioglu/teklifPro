<?php
require __DIR__ . '/../header.php';
require __DIR__ . '/../components/page_header.php';
require __DIR__ . '/../components/form_group.php';

$errors = [];
$success = null;
$first_name = $last_name = $email = $phone = $address = $company_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');

    if ($first_name === '') { $errors['first_name'] = 'İsim zorunludur.'; }
    if ($last_name === '') { $errors['last_name'] = 'Soyisim zorunludur.'; }
    if ($email === '') { $errors['email'] = 'Geçerli e-posta girin.'; }
    if ($phone === '') { $errors['phone'] = 'Telefon numarası zorunludur.'; }
    if ($address === '') { $errors['address'] = 'Adres zorunludur.'; }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('INSERT INTO customers (first_name, last_name, company_name, email, phone, address) VALUES (:first_name,:last_name,:company_name,:email,:phone,:address)');
            $stmt->execute([
                'first_name'=>$first_name,
                'last_name'=>$last_name,
                'company_name'=>$company_name,
                'email'=>$email,
                'phone'=>$phone,
                'address'=>$address,
            ]);
            $success = 'Müşteri eklendi.';
            $first_name = $last_name = $company_name = $email = $phone = $address = '';
        } catch (Exception $e) {
            $errors['form'] = 'Müşteri eklenemedi.';
        }
    }
}
?>
<?php page_header('Yeni Müşteri'); ?>
<?php if ($success): ?><div class="alert alert-success" role="alert"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger" role="alert">Lütfen formu kontrol edin.</div><?php endif; ?>
<form method="post" novalidate>
<?php form_group('first_name','İsim',"<input type='text' class='form-control' id='first_name' name='first_name' required value='".htmlspecialchars($first_name,ENT_QUOTES,'UTF-8')."'>",'', $errors['first_name'] ?? ''); ?>
<?php form_group('last_name','Soyisim',"<input type='text' class='form-control' id='last_name' name='last_name' required value='".htmlspecialchars($last_name,ENT_QUOTES,'UTF-8')."'>",'', $errors['last_name'] ?? ''); ?>
<?php form_group('company_name','Şirket',"<input type='text' class='form-control' id='company_name' name='company_name' value='".htmlspecialchars($company_name,ENT_QUOTES,'UTF-8')."'>",'Varsa şirket adı'); ?>
<?php form_group('email','Email',"<input type='email' class='form-control' id='email' name='email' required value='".htmlspecialchars($email,ENT_QUOTES,'UTF-8')."'>",'', $errors['email'] ?? ''); ?>
<?php form_group('phone','Telefon',"<input type='tel' pattern='^[0-9\s\+\-]{10,}$' class='form-control' id='phone' name='phone' required value='".htmlspecialchars($phone,ENT_QUOTES,'UTF-8')."'>",'Örn: 5xx xxx xx xx', $errors['phone'] ?? ''); ?>
<?php form_group('address','Adres',"<textarea class='form-control' id='address' name='address' rows='3' required>".htmlspecialchars($address,ENT_QUOTES,'UTF-8')."</textarea>",'', $errors['address'] ?? ''); ?>
  <div class="d-flex justify-content-end gap-2">
    <a href="../customer" class="btn btn-secondary">İptal</a>
    <button type="submit" class="btn btn-primary">Kaydet</button>
  </div>
</form>
<?php require __DIR__ . '/../footer.php'; ?>
