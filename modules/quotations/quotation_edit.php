<?php
$pageTitle = "Teklif Düzenle";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('quotation.php');
}

// Kayıt çek
$stmt = $pdo->prepare("
    SELECT id, quote_no, company_id, customer_id, offer_date, status, total_amount
    FROM master_quotes
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$quote = $stmt->fetch();
if (!$quote) {
    redirect('quotation.php');
}

// Yardımcı veriler
$companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll();
$customers = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM customers ORDER BY first_name, last_name")->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Geçersiz güvenlik doğrulaması.');
    }

    $postId     = (int)($_POST['id'] ?? 0);
    if ($postId !== (int)$quote['id']) {
        die('Kimlik doğrulaması başarısız.');
    }

    $quoteNo    = trim($_POST['quote_no'] ?? '');
    $companyId  = (int)($_POST['company_id'] ?? 0);
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $offerDate  = trim($_POST['offer_date'] ?? '');
    $status     = trim($_POST['status'] ?? 'draft');
    $totalAmt   = trim($_POST['total_amount'] ?? '');

    if ($quoteNo === '')   $errors[] = "Teklif numarası boş olamaz.";
    if ($companyId === 0)  $errors[] = "Lütfen bir şirket seçin.";
    if ($customerId === 0) $errors[] = "Lütfen bir müşteri seçin.";
    if ($offerDate === '') $errors[] = "Teklif tarihi boş olamaz.";
    if ($totalAmt !== '' && !is_numeric($totalAmt)) {
        $errors[] = "Toplam tutar sayı olmalıdır.";
    }

    if (!$errors) {
        $upd = $pdo->prepare("
            UPDATE master_quotes
            SET quote_no    = :quote_no,
                company_id  = :company_id,
                customer_id = :customer_id,
                offer_date  = :offer_date,
                status      = :status,
                total_amount= :total_amount
            WHERE id = :id
        ");
        $upd->execute([
            ':quote_no'     => $quoteNo,
            ':company_id'   => $companyId,
            ':customer_id'  => $customerId,
            ':offer_date'   => date('Y-m-d', strtotime($offerDate)),
            ':status'       => $status,
            ':total_amount' => ($totalAmt !== '' ? (float)$totalAmt : null),
            ':id'           => $quote['id'],
        ]);

        redirect('quotation.php');
    } else {
        // Formu kullanıcı girdileriyle tekrar doldur
        $quote['quote_no']     = $quoteNo;
        $quote['company_id']   = $companyId;
        $quote['customer_id']  = $customerId;
        $quote['offer_date']   = $offerDate;
        $quote['status']       = $status;
        $quote['total_amount'] = ($totalAmt !== '' ? (float)$totalAmt : null);
    }
}

require_once __DIR__ . '/../../templates/header.php';
?>

<h1 class="h3 mb-4">Teklif Düzenle</h1>

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
    <input type="hidden" name="id" value="<?= e((string)$quote['id']); ?>">

    <div class="mb-3">
        <label for="quote_no" class="form-label">Teklif No</label>
        <input type="text" name="quote_no" id="quote_no" class="form-control"
               value="<?= e($quote['quote_no'] ?? ''); ?>" required>
    </div>

    <div class="mb-3">
        <label for="company_id" class="form-label">Şirket</label>
        <select name="company_id" id="company_id" class="form-select" required>
            <option value="">— Seçiniz —</option>
            <?php foreach ($companies as $c): ?>
                <option value="<?= e($c['id']); ?>" <?= ((int)$quote['company_id'] === (int)$c['id']) ? 'selected' : ''; ?>>
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
                <option value="<?= e($cus['id']); ?>" <?= ((int)$quote['customer_id'] === (int)$cus['id']) ? 'selected' : ''; ?>>
                    <?= e($cus['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label for="offer_date" class="form-label">Teklif Tarihi</label>
        <input type="date" name="offer_date" id="offer_date" class="form-control"
               value="<?= e(date('Y-m-d', strtotime($quote['offer_date']))); ?>" required>
    </div>

    <div class="mb-3">
        <label for="status" class="form-label">Durum</label>
        <select name="status" id="status" class="form-select">
            <option value="draft"    <?= (($quote['status'] ?? '') === 'draft')    ? 'selected' : ''; ?>>Taslak</option>
            <option value="approved" <?= (($quote['status'] ?? '') === 'approved') ? 'selected' : ''; ?>>Onaylandı</option>
            <option value="rejected" <?= (($quote['status'] ?? '') === 'rejected') ? 'selected' : ''; ?>>Reddedildi</option>
        </select>
    </div>

    <div class="mb-3">
        <label for="total_amount" class="form-label">Toplam Tutar (₺)</label>
        <input type="text" name="total_amount" id="total_amount" class="form-control"
               value="<?= e($quote['total_amount'] !== null ? (string)$quote['total_amount'] : ''); ?>">
    </div>

    <button type="submit" class="btn btn-primary">Güncelle</button>
    <a href="quotation.php" class="btn btn-secondary">İptal</a>
</form>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
