<?php
require __DIR__ . '/../header.php';
require __DIR__ . '/../components/page_header.php';
require __DIR__ . '/../components/form_group.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { header('Location: ../customer?error=' . urlencode('Geçersiz ID.')); exit; }
$stmt = $pdo->prepare('SELECT first_name, last_name, company_name, email, phone, address FROM customers WHERE id = :id');
$stmt->execute(['id'=>$id]);
$customer = $stmt->fetch();
if (!$customer) { header('Location: ../customer?error=' . urlencode('Müşteri bulunamadı.')); exit; }

$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    if ($firstName === '') { $errors['first_name']='İsim zorunludur.'; }
    if ($lastName === '') { $errors['last_name']='Soyisim zorunludur.'; }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['email']='Geçerli e-posta girin.'; }
    if ($phone === '') { $errors['phone']='Telefon zorunludur.'; }
    if ($address === '') { $errors['address']='Adres zorunludur.'; }
    if (!$errors) {
        try {
            $stmt = $pdo->prepare('UPDATE customers SET first_name=:first_name,last_name=:last_name,company_name=:company_name,email=:email,phone=:phone,address=:address WHERE id=:id');
            $stmt->execute(['first_name'=>$firstName,'last_name'=>$lastName,'company_name'=>$companyName,'email'=>$email,'phone'=>$phone,'address'=>$address,'id'=>$id]);
            $success = 'Müşteri güncellendi.';
            $customer = ['first_name'=>$firstName,'last_name'=>$lastName,'company_name'=>$companyName,'email'=>$email,'phone'=>$phone,'address'=>$address];
        } catch (Exception $e) { $errors['form']='Güncellenemedi.'; }
    }
}
?>
<?php page_header('Müşteriyi Düzenle'); ?>
<?php if ($success): ?><div class="alert alert-success" role="alert"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger" role="alert">Lütfen formu kontrol edin.</div><?php endif; ?>
<form method="post" id="editForm" novalidate>
<?php form_group('first_name','İsim',"<input type='text' class='form-control' id='first_name' name='first_name' required value='".htmlspecialchars($customer['first_name'] ?? '',ENT_QUOTES,'UTF-8')."'>",'', $errors['first_name'] ?? ''); ?>
<?php form_group('last_name','Soyisim',"<input type='text' class='form-control' id='last_name' name='last_name' required value='".htmlspecialchars($customer['last_name'] ?? '',ENT_QUOTES,'UTF-8')."'>",'', $errors['last_name'] ?? ''); ?>
<?php form_group('company_name','Şirket',"<input type='text' class='form-control' id='company_name' name='company_name' value='".htmlspecialchars($customer['company_name'] ?? '',ENT_QUOTES,'UTF-8')."'>",'Varsa şirket adı'); ?>
<?php form_group('email','Email',"<input type='email' class='form-control' id='email' name='email' required value='".htmlspecialchars($customer['email'] ?? '',ENT_QUOTES,'UTF-8')."'>",'', $errors['email'] ?? ''); ?>
<?php form_group('phone','Telefon',"<input type='tel' pattern='^[0-9\s\+\-]{10,}$' class='form-control' id='phone' name='phone' required value='".htmlspecialchars($customer['phone'] ?? '',ENT_QUOTES,'UTF-8')."'>",'Örn: 5xx xxx xx xx', $errors['phone'] ?? ''); ?>
<?php form_group('address','Adres',"<textarea class='form-control' id='address' name='address' rows='3' required>".htmlspecialchars($customer['address'] ?? '',ENT_QUOTES,'UTF-8')."</textarea>",'', $errors['address'] ?? ''); ?>
</form>
<form id="deleteForm" method="post" action="../customer" class="d-inline">
  <input type="hidden" name="delete_id" value="<?= (int)$id; ?>">
</form>
<div class="d-flex justify-content-end gap-2">
  <button form="deleteForm" type="submit" class="btn btn-danger" data-confirm="Bu müşteri silinsin mi?">Sil</button>
  <a href="../customer" class="btn btn-secondary">İptal</a>
  <button form="editForm" type="submit" class="btn btn-primary">Kaydet</button>
</div>
<?php require __DIR__ . '/../footer.php'; ?>
