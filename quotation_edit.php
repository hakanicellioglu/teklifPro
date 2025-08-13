<?php
require __DIR__ . '/header.php';
require __DIR__ . '/components/page_header.php';

function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$assemblyTypes = [
    'demonte' => 'Demonte',
    'musteri' => 'Müşteri Montajlı',
    'bayi'    => 'Bayi Montajlı',
];

$paymentLabels = [
    'cash'          => 'Peşin',
    'bank_transfer' => 'Havale/EFT',
    'credit_card'   => 'Kredi Kartı',
    'installment'   => 'Taksitli',
    'vadeli'        => 'Vadeli',
    'other'         => 'Diğer',
];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: quotations.php?error=' . urlencode('Teklif bulunamadı.'));
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM generaloffers WHERE id = :id');
$stmt->execute([':id' => $id]);
$offer = $stmt->fetch();
if (!$offer) {
    header('Location: quotations.php?error=' . urlencode('Teklif bulunamadı.'));
    exit;
}

$customers = $pdo->query('SELECT id, first_name, last_name, company_name AS company FROM customers ORDER BY first_name')->fetchAll();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId      = (int)($_POST['customer_id'] ?? 0);
    $offerDate       = trim($_POST['offer_date'] ?? '');
    $assemblyType    = $_POST['assembly_type'] ?? '';
    $paymentMethod   = $_POST['payment_method'] ?? '';
    $validityDays    = trim($_POST['validity_days'] ?? '');
    $installmentTerm = trim($_POST['installment_term'] ?? '');
    $termMonths      = trim($_POST['term_months'] ?? '');
    $interestValue   = trim($_POST['interest_value'] ?? '');

    if ($customerId <= 0) { $errors['customer_id'] = 'Müşteri zorunludur.'; }
    if ($offerDate === '' || !strtotime($offerDate)) { $errors['offer_date'] = 'Geçerli tarih girin.'; }
    if ($assemblyType === '' || !isset($assemblyTypes[$assemblyType])) { $errors['assembly_type'] = 'Montaj tipi seçiniz.'; }
    if ($paymentMethod === '' || !isset($paymentLabels[$paymentMethod])) { $errors['payment_method'] = 'Ödeme yöntemi seçiniz.'; }

    $validityInt = null;
    if ($validityDays !== '') {
        if (!ctype_digit($validityDays) || (int)$validityDays < 1 || (int)$validityDays > 365) {
            $errors['validity_days'] = 'Teklif süresi 1–365 gün aralığında olmalıdır.';
        } else {
            $validityInt = (int)$validityDays;
        }
    }
    if ($paymentMethod === 'vadeli') {
        if ($termMonths === '' || !ctype_digit($termMonths) || (int)$termMonths < 1) {
            $errors['term_months'] = 'Vade süresi geçerli bir sayı olmalıdır.';
        }
        if ($interestValue === '' || !is_numeric($interestValue)) {
            $errors['interest_value'] = 'Vade farkı geçerli bir sayı olmalıdır.';
        }
        $installmentTerm = '';
    } else {
        if ($installmentTerm !== '' && mb_strlen($installmentTerm) > 100) {
            $errors['installment_term'] = 'Vade en fazla 100 karakter olabilir.';
        }
        $termMonths = '';
        $interestValue = '';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('UPDATE generaloffers SET customer_id=:customer_id, offer_date=:offer_date, assembly_type=:assembly_type, payment_method=:payment_method, validity_days=:validity_days, installment_term=:installment_term, term_months=:term_months, interest_value=:interest_value WHERE id=:id');
            $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
            $stmt->bindValue(':offer_date', $offerDate);
            $stmt->bindValue(':assembly_type', $assemblyType);
            $stmt->bindValue(':payment_method', $paymentMethod);
            $stmt->bindValue(':validity_days', $validityInt, $validityInt === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':installment_term', $installmentTerm !== '' ? $installmentTerm : null, $installmentTerm === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':term_months', $termMonths !== '' ? (int)$termMonths : null, $termMonths === '' ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':interest_value', $interestValue !== '' ? $interestValue : null, $interestValue === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $success = 'Teklif güncellendi.';
            $offer = array_merge($offer, [
                'customer_id'      => $customerId,
                'offer_date'       => $offerDate,
                'assembly_type'    => $assemblyType,
                'payment_method'   => $paymentMethod,
                'validity_days'    => $validityInt,
                'installment_term' => $installmentTerm,
                'term_months'      => $termMonths !== '' ? (int)$termMonths : null,
                'interest_value'   => $interestValue !== '' ? $interestValue : null,
            ]);
        } catch (Exception $e) {
            $errors['form'] = 'Güncellenemedi.';
        }
    }
}

page_header('Teklifi Düzenle');
?>
<?php if ($success): ?><div class="alert alert-success" role="alert"><?= e($success) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger" role="alert">Lütfen formu kontrol edin.</div><?php endif; ?>
<form method="post" novalidate>
  <?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= e($errors['form']) ?></div><?php endif; ?>
  <div class="mb-3">
    <label class="form-label">Müşteri</label>
    <select name="customer_id" class="form-select <?= isset($errors['customer_id'])?'is-invalid':'' ?>" required>
      <option value="">Seçiniz</option>
      <?php foreach ($customers as $c): $label = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')); if (!empty($c['company'])) $label .= ' (' . $c['company'] . ')'; ?>
      <option value="<?= (int)$c['id'] ?>" <?= ((int)$offer['customer_id'] === (int)$c['id'])?'selected':'' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if(isset($errors['customer_id'])): ?><div class="invalid-feedback"><?= e($errors['customer_id']) ?></div><?php endif; ?>
  </div>
  <div class="mb-3">
    <label class="form-label">Montaj Tipi</label>
    <select name="assembly_type" class="form-select <?= isset($errors['assembly_type'])?'is-invalid':'' ?>" required>
      <option value="">Seçiniz</option>
      <?php foreach ($assemblyTypes as $key=>$label): ?>
      <option value="<?= e($key) ?>" <?= $offer['assembly_type']===$key?'selected':'' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if(isset($errors['assembly_type'])): ?><div class="invalid-feedback"><?= e($errors['assembly_type']) ?></div><?php endif; ?>
  </div>
  <div class="mb-3">
    <label class="form-label">Ödeme Yöntemi</label>
    <select name="payment_method" class="form-select <?= isset($errors['payment_method'])?'is-invalid':'' ?>" required>
      <option value="">Seçiniz</option>
      <?php foreach ($paymentLabels as $key=>$label): ?>
      <option value="<?= e($key) ?>" <?= $offer['payment_method']===$key?'selected':'' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if(isset($errors['payment_method'])): ?><div class="invalid-feedback"><?= e($errors['payment_method']) ?></div><?php endif; ?>
  </div>
  <div class="mb-3 vadeli-fields" style="display:none;">
    <label class="form-label">Vade Süresi (ay)</label>
    <input type="number" min="1" name="term_months" class="form-control <?= isset($errors['term_months'])?'is-invalid':'' ?>" value="<?= e($offer['term_months'] !== null ? (string)(int)$offer['term_months'] : '') ?>">
    <?php if(isset($errors['term_months'])): ?><div class="invalid-feedback"><?= e($errors['term_months']) ?></div><?php endif; ?>
  </div>
  <div class="mb-3 vadeli-fields" style="display:none;">
    <label class="form-label">Vade Farkı (aylık)</label>
    <input type="number" step="0.01" name="interest_value" class="form-control <?= isset($errors['interest_value'])?'is-invalid':'' ?>" value="<?= e($offer['interest_value']) ?>">
    <?php if(isset($errors['interest_value'])): ?><div class="invalid-feedback"><?= e($errors['interest_value']) ?></div><?php endif; ?>
  </div>
  <div class="mb-3">
    <label class="form-label">Teklif Süresi (gün)</label>
    <input type="number" min="1" max="365" name="validity_days" class="form-control <?= isset($errors['validity_days'])?'is-invalid':'' ?>" value="<?= e($offer['validity_days'] !== null ? (string)(int)$offer['validity_days'] : '') ?>" placeholder="örn. 15">
    <?php if(isset($errors['validity_days'])): ?><div class="invalid-feedback"><?= e($errors['validity_days']) ?></div><?php endif; ?>
  </div>
  <div class="mb-3 installment-field">
    <label class="form-label">Vade</label>
    <input type="text" name="installment_term" class="form-control <?= isset($errors['installment_term'])?'is-invalid':'' ?>" value="<?= e($offer['installment_term'] ?? '') ?>" placeholder="3 taksit (aylık)">
    <?php if(isset($errors['installment_term'])): ?><div class="invalid-feedback"><?= e($errors['installment_term']) ?></div><?php endif; ?>
  </div>
  <div class="mb-3">
    <label class="form-label">Teklif Tarihi</label>
    <input type="date" name="offer_date" class="form-control <?= isset($errors['offer_date'])?'is-invalid':'' ?>" value="<?= e($offer['offer_date']) ?>" required>
    <?php if(isset($errors['offer_date'])): ?><div class="invalid-feedback"><?= e($errors['offer_date']) ?></div><?php endif; ?>
  </div>
  <div class="d-flex justify-content-end gap-2">
    <a href="quotation_view.php?id=<?= (int)$id ?>" class="btn btn-secondary">İptal</a>
    <button type="submit" class="btn btn-primary">Kaydet</button>
  </div>
</form>
<script>
function toggleVadeliFields() {
  var payment = document.querySelector('select[name="payment_method"]').value;
  document.querySelectorAll('.vadeli-fields').forEach(function(el){
    el.style.display = payment === 'vadeli' ? '' : 'none';
  });
  var inst = document.querySelector('.installment-field');
  if (inst) inst.style.display = payment === 'vadeli' ? 'none' : '';
}
document.querySelector('select[name="payment_method"]').addEventListener('change', toggleVadeliFields);
toggleVadeliFields();
</script>
<?php require __DIR__ . '/footer.php'; ?>
