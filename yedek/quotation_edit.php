<?php
require __DIR__ . '/header.php';
require __DIR__ . '/components/page_header.php';
require __DIR__ . '/components/form_group.php';

function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$assemblyTypes = [
    'demonte' => 'Demonte',
    'musteri' => 'Müşteri Montajlı',
    'bayi'    => 'Bayi Montajlı',
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
$companies = $pdo->query('SELECT id, name FROM company ORDER BY name')->fetchAll();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId     = (int)($_POST['customer_id'] ?? 0);
    $companyId      = $_POST['company_id'] !== '' ? (int)$_POST['company_id'] : null;
    $offerDate      = trim($_POST['offer_date'] ?? '');
    $assemblyType   = $_POST['assembly_type'] ?? '';
    $paymentMethod  = trim($_POST['payment_method'] ?? '');
    $deliveryTime   = trim($_POST['delivery_time'] ?? '');
    $maturityPeriod = trim($_POST['maturity_period'] ?? '');

    if ($customerId <= 0) { $errors['customer_id'] = 'Müşteri zorunludur.'; }
    if ($offerDate === '' || !strtotime($offerDate)) { $errors['offer_date'] = 'Geçerli tarih girin.'; }
    if ($assemblyType === '' || !isset($assemblyTypes[$assemblyType])) { $errors['assembly_type'] = 'Montaj tipi seçiniz.'; }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('UPDATE generaloffers SET customer_id=:customer_id, company_id=:company_id, offer_date=:offer_date, assembly_type=:assembly_type, payment_method=:payment_method, delivery_time=:delivery_time, maturity_period=:maturity_period WHERE id=:id');
            $stmt->execute([
                ':customer_id'    => $customerId,
                ':company_id'     => $companyId,
                ':offer_date'     => $offerDate,
                ':assembly_type'  => $assemblyType,
                ':payment_method' => $paymentMethod !== '' ? $paymentMethod : null,
                ':delivery_time'  => $deliveryTime !== '' ? $deliveryTime : null,
                ':maturity_period'=> $maturityPeriod !== '' ? $maturityPeriod : null,
                ':id'             => $id,
            ]);
            $success = 'Teklif güncellendi.';
            $offer = array_merge($offer, [
                'customer_id'    => $customerId,
                'company_id'     => $companyId,
                'offer_date'     => $offerDate,
                'assembly_type'  => $assemblyType,
                'payment_method' => $paymentMethod,
                'delivery_time'  => $deliveryTime,
                'maturity_period'=> $maturityPeriod,
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
<?php
$customerOptions = '<option value="">Seçiniz</option>';
foreach ($customers as $c) {
    $label = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
    if (!empty($c['company'])) { $label .= ' (' . $c['company'] . ')'; }
    $selected = ((int)$offer['customer_id'] === (int)$c['id']) ? ' selected' : '';
    $customerOptions .= '<option value="' . (int)$c['id'] . '"' . $selected . '>' . e($label) . '</option>';
}
form_group('customer_id', 'Müşteri', "<select name='customer_id' id='customer_id' class='form-select' required>$customerOptions</select>", '', $errors['customer_id'] ?? '');

$companyOptions = '<option value="">Seçiniz</option>';
foreach ($companies as $co) {
    $selected = ((int)$offer['company_id'] === (int)$co['id']) ? ' selected' : '';
    $companyOptions .= '<option value="' . (int)$co['id'] . '"' . $selected . '>' . e($co['name']) . '</option>';
}
form_group('company_id', 'Şirket', "<select name='company_id' id='company_id' class='form-select'>$companyOptions</select>");

$assemblyOptions = '<option value="">Seçiniz</option>';
foreach ($assemblyTypes as $key => $label) {
    $selected = ($offer['assembly_type'] === $key) ? ' selected' : '';
    $assemblyOptions .= '<option value="' . e($key) . '"' . $selected . '>' . e($label) . '</option>';
}
form_group('assembly_type', 'Montaj Tipi', "<select name='assembly_type' id='assembly_type' class='form-select' required>$assemblyOptions</select>", '', $errors['assembly_type'] ?? '');

form_group('offer_date', 'Teklif Tarihi', "<input type='date' class='form-control' id='offer_date' name='offer_date' required value='" . e($offer['offer_date']) . "'>", '', $errors['offer_date'] ?? '');
form_group('payment_method', 'Ödeme', "<input type='text' class='form-control' id='payment_method' name='payment_method' value='" . e($offer['payment_method']) . "'>");
form_group('delivery_time', 'Teslim', "<input type='text' class='form-control' id='delivery_time' name='delivery_time' value='" . e($offer['delivery_time']) . "'>");
form_group('maturity_period', 'Vaade', "<input type='text' class='form-control' id='maturity_period' name='maturity_period' value='" . e($offer['maturity_period']) . "'>");
?>
<div class="d-flex justify-content-end gap-2">
  <a href="quotation_view.php?id=<?= (int)$id ?>" class="btn btn-secondary">İptal</a>
  <button type="submit" class="btn btn-primary">Kaydet</button>
</div>
</form>
<?php require __DIR__ . '/footer.php'; ?>
