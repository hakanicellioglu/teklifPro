<?php
require __DIR__ . '/header.php';
require __DIR__ . '/components/page_header.php';
require __DIR__ . '/components/data_table.php';

function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$assemblyLabels = [
    'demonte' => 'Demonte',
    'musteri' => 'Müşteri Montajlı',
    'bayi'    => 'Bayi Montajlı',
];
$paymentLabels = [
    'cash'          => 'Peşin',
    'bank_transfer' => 'Havale/EFT',
    'credit_card'   => 'Kredi Kartı',
    'installment'   => 'Taksitli',
    'other'         => 'Diğer',
];

// idempotent migration for new columns
try {
    $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS assembly_type ENUM('demonte','musteri','bayi') NULL AFTER customer_id");
    $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS payment_method VARCHAR(100) NULL AFTER assembly_type");
    $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS validity_days INT NULL AFTER payment_method");
    $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS installment_term VARCHAR(100) NULL AFTER validity_days");
} catch (Exception $e) {
    // ignore migration errors
}

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$createErrors = [];
$createData = [
    'customer_id' => '',
    'offer_date' => '',
    'assembly_type' => '',
    'payment_method' => '',
    'validity_days' => '',
    'installment_term' => '',
];

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $token      = $_POST['csrf_token'] ?? '';
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $offerDate  = trim($_POST['offer_date'] ?? '');
    $assembly   = $_POST['assembly_type'] ?? '';
    $payment    = $_POST['payment_method'] ?? '';
    $validity   = trim($_POST['validity_days'] ?? '');
    $term       = trim($_POST['installment_term'] ?? '');

    $createData = [
        'customer_id'      => $customerId ? (string)$customerId : '',
        'offer_date'       => $offerDate,
        'assembly_type'    => $assembly,
        'payment_method'   => $payment,
        'validity_days'    => $validity,
        'installment_term' => $term,
    ];

    if (!hash_equals($csrfToken, $token)) {
        $createErrors['form'] = 'Geçersiz CSRF tokenı.';
    }
    if ($customerId <= 0) {
        $createErrors['customer_id'] = 'Müşteri zorunludur.';
    }
    if ($offerDate === '' || !strtotime($offerDate)) {
        $createErrors['offer_date'] = 'Geçerli tarih girin.';
    }
    if (!in_array($assembly, array_keys($assemblyLabels), true)) {
        $createErrors['assembly_type'] = 'Montaj tipi zorunludur.';
    }
    if (!in_array($payment, array_keys($paymentLabels), true)) {
        $createErrors['payment_method'] = 'Ödeme yöntemi zorunludur.';
    }
    $validityInt = null;
    if ($validity !== '') {
        if (!ctype_digit($validity) || (int)$validity < 1 || (int)$validity > 365) {
            $createErrors['validity_days'] = 'Teklif süresi 1–365 gün aralığında olmalıdır.';
        } else {
            $validityInt = (int)$validity;
        }
    }
    if ($term !== '' && mb_strlen($term) > 100) {
        $createErrors['installment_term'] = 'Vade en fazla 100 karakter olabilir.';
    }

    if (!$createErrors) {
        try {
            $stmt = $pdo->prepare("INSERT INTO generaloffers (customer_id, offer_date, assembly_type, payment_method, validity_days, installment_term) VALUES (:customer_id, :offer_date, :assembly_type, :payment_method, :validity_days, :installment_term)");
            $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
            $stmt->bindValue(':offer_date', $offerDate);
            $stmt->bindValue(':assembly_type', $assembly);
            $stmt->bindValue(':payment_method', $payment);
            $stmt->bindValue(':validity_days', $validityInt, $validityInt === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':installment_term', $term !== '' ? $term : null, $term === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();
            $newId = (int)$pdo->lastInsertId();
            $_SESSION['flash_success'] = 'Teklif oluşturuldu.';
            header('Location: quotation_view.php?id=' . $newId);
            exit;
        } catch (Exception $e) {
            $createErrors['form'] = 'Teklif oluşturulamadı.';
        }
    }
}

if ($action === 'delete') {
    $deleteId = (int)($_POST['id'] ?? 0);
    if ($deleteId) {
        try {
            $stmt = $pdo->prepare('DELETE FROM generaloffers WHERE id = :id');
            $stmt->execute([':id'=>$deleteId]);
            $_SESSION['flash_success'] = 'Teklif silindi.';
            header('Location: quotations.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Teklif silinemedi.';
        }
    }
}

$conditions = [];
$params = [];
if ($search !== '') { $conditions[] = 'CONCAT(c.first_name, " ", c.last_name) LIKE :term'; $params['term'] = "%$search%"; }
if ($status !== '') { $conditions[] = 'g.status = :status'; $params['status'] = $status; }
$sql = 'SELECT g.id, g.offer_date, g.status, g.assembly_type, g.payment_method, g.validity_days, g.installment_term, CONCAT(c.first_name, " ", c.last_name) AS customer,
        COALESCE(gs.sum_total,0)+COALESCE(ss.sum_total,0) AS total_amount
        FROM generaloffers g
        LEFT JOIN customers c ON g.customer_id=c.id
        LEFT JOIN (SELECT general_offer_id, SUM(total_amount) AS sum_total FROM guillotinesystems GROUP BY general_offer_id) gs ON gs.general_offer_id=g.id
        LEFT JOIN (SELECT general_offer_id, SUM(total_amount) AS sum_total FROM slidingsystems GROUP BY general_offer_id) ss ON ss.general_offer_id=g.id';
if ($conditions) { $sql .= ' WHERE '.implode(' AND ', $conditions); }
$sql .= ' ORDER BY g.offer_date DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$offers = $stmt->fetchAll();

$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);
$error   = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
?>
<?php page_header('Teklifler', '<a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal"><i class="bi bi-plus"></i> Yeni Teklif</a>'); ?>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-6">
    <input type="search" name="search" class="form-control" placeholder="Ara" value="<?= e($search) ?>">
  </div>
  <div class="col-md-6 d-flex align-items-center gap-2">
    <?php foreach ([""=>'Tümü','active'=>'Aktif','pending'=>'Beklemede','closed'=>'Kapalı'] as $code=>$label): ?>
      <a href="quotations?<?= http_build_query(['status'=>$code===''?null:$code, 'search'=>$search]) ?>" class="badge rounded-pill <?= $status===$code?'text-bg-primary':'text-bg-light' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
</form>
<?php if ($success): ?><div class="alert alert-success" role="alert"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php data_table_start(['#','Müşteri','Montaj','Ödeme','Süre','Vade','Tarih','Tutar','Durum','İşlemler']); ?>
<?php if ($offers): foreach ($offers as $o): ?>
<tr>
  <td><?= (int)$o['id'] ?></td>
  <td><?= e($o['customer']) ?></td>
  <td><?= e($assemblyLabels[$o['assembly_type']] ?? '') ?></td>
  <td><?= e($paymentLabels[$o['payment_method']] ?? '') ?></td>
  <td><?= $o['validity_days'] !== null ? (int)$o['validity_days'].' gün' : '' ?></td>
  <td><?= e($o['installment_term'] ?? '') ?></td>
  <td><time datetime="<?= e($o['offer_date']) ?>"><?= e($o['offer_date']) ?></time></td>
  <td><?= number_format((float)$o['total_amount'],2,',','.') ?> ₺</td>
  <td><?= e($o['status']) ?></td>
  <td class="text-end">
    <a href="quotation_view.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Görüntüle"><i class="bi bi-eye"></i></a>
    <a href="quotation_view.php?id=<?= (int)$o['id'] ?>&edit=1" class="btn btn-sm btn-outline-secondary" title="Düzenle"><i class="bi bi-pencil"></i></a>
    <form method="post" class="d-inline">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Bu teklif silinsin mi?" title="Sil"><i class="bi bi-trash"></i></button>
    </form>
  </td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="10" class="text-center text-muted">Teklif bulunamadı.</td></tr>
<?php endif; ?>
<?php data_table_end(); ?>

<?php
$customers = $pdo->query('SELECT id, first_name, last_name, company_name AS company FROM customers ORDER BY first_name')->fetchAll();
?>
<div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="quotations.php">
        <div class="modal-header">
          <h5 class="modal-title">Yeni Teklif</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php if (!empty($createErrors['form'])): ?><div class="alert alert-danger"><?= e($createErrors['form']) ?></div><?php endif; ?>
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
          <div class="mb-3">
            <label class="form-label">Müşteri</label>
            <select name="customer_id" class="form-select <?= isset($createErrors['customer_id'])?'is-invalid':'' ?>" required>
              <option value="">Seçiniz</option>
              <?php foreach ($customers as $c): $label = trim($c['first_name'].' '.$c['last_name']); if (!empty($c['company'])) $label .= ' ('.$c['company'].')'; ?>
              <option value="<?= (int)$c['id'] ?>" <?= $createData['customer_id']==(string)$c['id']?'selected':'' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if(isset($createErrors['customer_id'])): ?><div class="invalid-feedback"><?= e($createErrors['customer_id']) ?></div><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Montaj Tipi</label>
            <select name="assembly_type" class="form-select <?= isset($createErrors['assembly_type'])?'is-invalid':'' ?>" required>
              <option value="">Seçiniz</option>
              <?php foreach ($assemblyLabels as $key=>$label): ?>
              <option value="<?= e($key) ?>" <?= $createData['assembly_type']===$key?'selected':'' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if(isset($createErrors['assembly_type'])): ?><div class="invalid-feedback"><?= e($createErrors['assembly_type']) ?></div><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Ödeme Yöntemi</label>
            <select name="payment_method" class="form-select <?= isset($createErrors['payment_method'])?'is-invalid':'' ?>" required>
              <option value="">Seçiniz</option>
              <?php foreach ($paymentLabels as $key=>$label): ?>
              <option value="<?= e($key) ?>" <?= $createData['payment_method']===$key?'selected':'' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if(isset($createErrors['payment_method'])): ?><div class="invalid-feedback"><?= e($createErrors['payment_method']) ?></div><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Teklif Süresi (gün)</label>
            <input type="number" min="1" max="365" name="validity_days" class="form-control <?= isset($createErrors['validity_days'])?'is-invalid':'' ?>" value="<?= e($createData['validity_days']) ?>" placeholder="örn. 15">
            <?php if(isset($createErrors['validity_days'])): ?><div class="invalid-feedback"><?= e($createErrors['validity_days']) ?></div><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Vade</label>
            <input type="text" name="installment_term" class="form-control <?= isset($createErrors['installment_term'])?'is-invalid':'' ?>" value="<?= e($createData['installment_term']) ?>" placeholder="3 taksit (aylık)">
            <?php if(isset($createErrors['installment_term'])): ?><div class="invalid-feedback"><?= e($createErrors['installment_term']) ?></div><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Teklif Tarihi</label>
            <input type="date" name="offer_date" class="form-control <?= isset($createErrors['offer_date'])?'is-invalid':'' ?>" value="<?= e($createData['offer_date']) ?>" required>
            <?php if(isset($createErrors['offer_date'])): ?><div class="invalid-feedback"><?= e($createErrors['offer_date']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
          <button type="submit" class="btn btn-primary">Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php if ($createErrors): ?>
<script>
  var createModal = new bootstrap.Modal(document.getElementById('createModal'));
  createModal.show();
</script>
<?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>

