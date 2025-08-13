<?php
$pageTitle = "Yeni Teklif Ekle";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_login();

// Şirketler ve müşteriler
$companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll();
$customers = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM customers ORDER BY first_name, last_name")->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Geçersiz güvenlik doğrulaması.');
    }

    $quoteNo    = trim($_POST['quote_no'] ?? '');
    $companyId  = (int)($_POST['company_id'] ?? 0);
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $offerDate  = trim($_POST['offer_date'] ?? '');
    $status     = trim($_POST['status'] ?? 'draft');

    if ($quoteNo === '')   $errors[] = "Teklif numarası boş olamaz.";
    if ($companyId === 0)  $errors[] = "Lütfen bir şirket seçin.";
    if ($customerId === 0) $errors[] = "Lütfen bir müşteri seçin.";
    if ($offerDate === '') $errors[] = "Teklif tarihi boş olamaz.";

    if (!$errors) {
        $stmt = $pdo->prepare("
            INSERT INTO master_quotes (quote_no, company_id, customer_id, offer_date, status)
            VALUES (:quote_no, :company_id, :customer_id, :offer_date, :status)
        ");
        $stmt->execute([
            ':quote_no'    => $quoteNo,
            ':company_id'  => $companyId,
            ':customer_id' => $customerId,
            ':offer_date'  => date('Y-m-d', strtotime($offerDate)),
            ':status'      => $status
        ]);
        redirect('quotation.php');
    }
}

require_once __DIR__ . '/../../templates/header.php';
?>

<h1 class="h3 mb-4">Yeni Teklif Ekle</h1>

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
        <label for="quote_no" class="form-label">Teklif No</label>
        <input type="text" name="quote_no" id="quote_no" class="form-control" value="<?= e($_POST['quote_no'] ?? ''); ?>" required>
    </div>

    <div class="mb-3">
        <label for="company_id" class="form-label">Şirket</label>
        <select name="company_id" id="company_id" class="form-select" required>
            <option value="">— Seçiniz —</option>
            <?php foreach ($companies as $c): ?>
                <option value="<?= e($c['id']); ?>" <?= (($_POST['company_id'] ?? '') == $c['id']) ? 'selected' : ''; ?>>
                    <?= e($c['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label for="customer_id" class="form-label">Müşteri</label>
        <select name="customer_id" id="customer_id" class="form-select" required>
            <option value="">— Seçiniz —</option>
            <?php foreach ($customers as $cus): ?>
                <option value="<?= e($cus['id']); ?>" <?= (($_POST['customer_id'] ?? '') == $cus['id']) ? 'selected' : ''; ?>>
                    <?= e($cus['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label for="offer_date" class="form-label">Teklif Tarihi</label>
        <input type="date" name="offer_date" id="offer_date" class="form-control" value="<?= e($_POST['offer_date'] ?? date('Y-m-d')); ?>" required>
    </div>

    <div class="mb-3">
        <label for="status" class="form-label">Durum</label>
        <select name="status" id="status" class="form-select">
            <option value="draft"    <?= (($_POST['status'] ?? '') === 'draft')    ? 'selected' : ''; ?>>Taslak</option>
            <option value="approved" <?= (($_POST['status'] ?? '') === 'approved') ? 'selected' : ''; ?>>Onaylandı</option>
            <option value="rejected" <?= (($_POST['status'] ?? '') === 'rejected') ? 'selected' : ''; ?>>Reddedildi</option>
        </select>
    </div>

    <button type="submit" class="btn btn-success">Kaydet</button>
    <a href="quotation.php" class="btn btn-secondary">İptal</a>
</form>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
