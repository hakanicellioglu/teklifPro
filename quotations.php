<?php

declare(strict_types=1);
require __DIR__ . '/header.php';
require __DIR__ . '/components/page_header.php';

function e(?string $v): string
{
  return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

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
  'vadeli'        => 'Vadeli',
  'other'         => 'Diğer',
];
$statusLabels = [
  'active'    => 'Aktif',
  'pending'   => 'Beklemede',
  'closed'    => 'Kapalı',
  'draft'     => 'Taslak',
  'sent'      => 'Gönderildi',
  'accepted'  => 'Onaylandı',
  'rejected'  => 'Reddedildi',
  'expired'   => 'Süresi doldu',
  'cancelled' => 'İptal',
];

// idempotent migration for new columns
try {
  $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS assembly_type ENUM('demonte','musteri','bayi') NULL AFTER customer_id");
  $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS payment_method VARCHAR(100) NULL AFTER assembly_type");
  $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS validity_days INT NULL AFTER payment_method");
  $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS installment_term VARCHAR(100) NULL AFTER validity_days");
  $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS payment_type ENUM('cash','installment') NOT NULL DEFAULT 'cash' AFTER installment_term");
  $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS term_months INT NULL AFTER payment_type");
  $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS interest_mode ENUM('percent','fixed') NULL AFTER term_months");
  $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS interest_value DECIMAL(12,2) NULL AFTER interest_mode");
  $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS interest_amount DECIMAL(12,2) NULL AFTER interest_value");
  $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS total_with_interest DECIMAL(12,2) NULL AFTER interest_amount");
  $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS monthly_installment DECIMAL(12,2) NULL AFTER total_with_interest");
  $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS grace_days INT NULL DEFAULT 0 AFTER monthly_installment");
  $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS approval_token VARCHAR(64) NULL AFTER profit_amount");
  $pdo->exec("ALTER TABLE generaloffers ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER approval_token");
} catch (Exception $e) {
  // ignore migration errors
}

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$validPerPages = [10, 25, 50, 100];
$perPage = isset($_SESSION['per_page']) ? (int)$_SESSION['per_page'] : 10;
if (isset($_GET['per_page'])) {
  $pp = (int)$_GET['per_page'];
  if (!in_array($pp, $validPerPages, true)) {
    $pp = 10;
  }
  $_SESSION['per_page'] = $pp;
  $perPage = $pp;
} elseif (!in_array($perPage, $validPerPages, true)) {
  $perPage = 10;
  $_SESSION['per_page'] = 10;
}
$page = isset($_GET['page']) && ctype_digit((string)$_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
  $page = 1;
}

$headers = [
  ['label' => '#', 'key' => 'id'],
  ['label' => 'Müşteri', 'key' => 'customer'],
  ['label' => 'Montaj', 'key' => 'assembly_type'],
  ['label' => 'Ödeme', 'key' => 'payment_method'],
  ['label' => 'Süre', 'key' => 'validity_days'],
  ['label' => 'Vade', 'key' => 'installment_term'],
  ['label' => 'Tarih', 'key' => 'offer_date'],
  ['label' => 'Tutar', 'key' => 'total_amount'],
  ['label' => 'Onay Tarihi', 'key' => 'approved_at'],
  ['label' => 'Durum', 'key' => 'status'],
  ['label' => 'İşlemler', 'key' => null],
];
$allowedSorts = [
  'id' => 'g.id',
  'customer' => 'customer',
  'assembly_type' => 'g.assembly_type',
  'payment_method' => 'g.payment_method',
  'validity_days' => 'g.validity_days',
  'installment_term' => 'g.installment_term',
  'offer_date' => 'g.offer_date',
  'total_amount' => 'total_amount',
  'approved_at' => 'g.approved_at',
  'status' => 'g.status',
];
$sort = $_GET['sort'] ?? 'offer_date';
$dirParam = strtolower($_GET['dir'] ?? 'desc');
$dir = $dirParam === 'asc' ? 'ASC' : 'DESC';
if (!array_key_exists($sort, $allowedSorts)) {
  $sort = 'offer_date';
}
$orderSql = $allowedSorts[$sort] . ' ' . $dir;

$createErrors = [];
$createData = [
  'customer_id' => '',
  'offer_date' => '',
  'assembly_type' => '',
  'payment_method' => '',
  'validity_days' => '',
  'installment_term' => '',
  'term_months' => '',
  'interest_value' => '',
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
  $termMonths = trim($_POST['term_months'] ?? '');
  $interest   = trim($_POST['interest_value'] ?? '');

  $createData = [
    'customer_id'      => $customerId ? (string)$customerId : '',
    'offer_date'       => $offerDate,
    'assembly_type'    => $assembly,
    'payment_method'   => $payment,
    'validity_days'    => $validity,
    'installment_term' => $term,
    'term_months'      => $termMonths,
    'interest_value'   => $interest,
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

  if ($payment === 'vadeli') {
    if ($termMonths === '' || !ctype_digit($termMonths) || (int)$termMonths < 1) {
      $createErrors['term_months'] = 'Vade süresi geçerli bir sayı olmalıdır.';
    }
    if ($interest === '' || !is_numeric($interest)) {
      $createErrors['interest_value'] = 'Vade farkı geçerli bir sayı olmalıdır.';
    }
  } else {
    $termMonths = '';
    $interest = '';
    $term = '';
  }

  if (!$createErrors) {
    try {
      $approvalToken = bin2hex(random_bytes(16));
      $stmt = $pdo->prepare("INSERT INTO generaloffers (customer_id, offer_date, assembly_type, payment_method, validity_days, installment_term, term_months, interest_value, approval_token) VALUES (:customer_id, :offer_date, :assembly_type, :payment_method, :validity_days, :installment_term, :term_months, :interest_value, :approval_token)");
      $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
      $stmt->bindValue(':offer_date', $offerDate);
      $stmt->bindValue(':assembly_type', $assembly);
      $stmt->bindValue(':payment_method', $payment);
      $stmt->bindValue(':validity_days', $validityInt, $validityInt === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
      $stmt->bindValue(':installment_term', $term !== '' ? $term : null, $term === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
      $stmt->bindValue(':term_months', $termMonths !== '' ? (int)$termMonths : null, $termMonths === '' ? PDO::PARAM_NULL : PDO::PARAM_INT);
      $stmt->bindValue(':interest_value', $interest !== '' ? $interest : null, $interest === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
      $stmt->bindValue(':approval_token', $approvalToken);
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
      $stmt->execute([':id' => $deleteId]);
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
if ($search !== '') {
  $conditions[] = 'CONCAT(c.first_name, " ", c.last_name) LIKE :term';
  $params['term'] = "%$search%";
}
if ($status !== '') {
  $conditions[] = 'g.status = :status';
  $params['status'] = $status;
}
$baseSql = 'FROM generaloffers g
        LEFT JOIN customers c ON g.customer_id=c.id
        LEFT JOIN (SELECT general_offer_id, SUM(total_amount) AS sum_total FROM guillotinesystems GROUP BY general_offer_id) gs ON gs.general_offer_id=g.id
        LEFT JOIN (SELECT general_offer_id, SUM(total_amount) AS sum_total FROM slidingsystems GROUP BY general_offer_id) ss ON ss.general_offer_id=g.id';
if ($conditions) {
  $baseSql .= ' WHERE ' . implode(' AND ', $conditions);
}
$countStmt = $pdo->prepare('SELECT COUNT(*) ' . $baseSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = (int)max(1, ceil($totalRows / $perPage));
if ($page > $totalPages) {
  $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$selectSql = 'SELECT g.id, g.offer_date, g.status, g.approved_at, g.assembly_type, g.payment_method, g.validity_days, g.installment_term, g.term_months, g.interest_value, CONCAT(c.first_name, " ", c.last_name) AS customer, c.company_name AS company,
        COALESCE(gs.sum_total,0)+COALESCE(ss.sum_total,0) AS total_amount ' . $baseSql . ' ORDER BY ' . $orderSql . ' LIMIT :limit OFFSET :offset';
$stmt = $pdo->prepare($selectSql);
foreach ($params as $k => $v) {
  $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$offers = $stmt->fetchAll();

unset($_SESSION['flash_error']);
?>
<?php page_header('Teklifler', '<a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal"><i class="bi bi-plus"></i> Yeni Teklif</a>'); ?>
<form class="row d-flex justify-content-end align-items-center" method="get">
  <!-- Sıralama ve yön gizli parametreler -->
  <input type="hidden" name="sort" value="<?= e($sort) ?>">
  <input type="hidden" name="dir" value="<?= e($dirParam) ?>">

  <?php foreach ($_GET as $k => $v): if (in_array($k, ['per_page', 'page', 'sort', 'dir', 'search', 'status'], true)) continue; ?>
    <input type="hidden" name="<?= e($k) ?>" value="<?= e((string)$v) ?>">
  <?php endforeach; ?>

  <!-- Sayfa başına -->
  <div class="col-12 col-md-1">
    <select name="per_page" class="form-select d-inline-block w-auto" onchange="this.form.submit()">
      <?php foreach ($validPerPages as $pp): ?>
        <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="btn btn-outline-secondary ms-2">Uygula</button></noscript>
  </div>

  <!-- Durum filtresi -->
  <div class="col-12 col-md-2">
    <select name="status" class="form-select" onchange="this.form.submit()">
      <?php foreach (array_merge(['' => 'Tümü'], $statusLabels) as $code => $label): ?>
        <option value="<?= e($code) ?>" <?= $status === $code ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Arama alanı -->
  <div class="col-12 col-md-3">
    <div class="input-group">
      <input type="search" name="search" class="form-control" placeholder="Ara" value="<?= e($search) ?>">
      <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
    </div>
  </div>
</form>

<div class="table-responsive" style="min-height: 50svh;">
  <table class="table table-hover align-middle">
    <thead class="table-light sticky-top">
      <tr class="text-center">
        <?php foreach ($headers as $h): ?>
          <th scope="col">
            <?= e($h['label']) ?>
            <?php if ($h['key']):
              $isCurrent = $sort === $h['key'];
              $nextDir   = ($isCurrent && $dirParam === 'asc') ? 'desc' : 'asc';
              $icon      = 'bi-arrow-down-up';
              if ($isCurrent) {
                $icon = $dirParam === 'asc' ? 'bi-caret-up-fill' : 'bi-caret-down-fill';
              }
            ?>
              <a href="?<?= http_build_query(array_merge($_GET, ['sort' => $h['key'], 'dir' => $nextDir, 'page' => 1])) ?>" class="btn btn-sm btn-link p-0"><i class="bi <?= $icon ?>"></i></a>
            <?php endif; ?>
          </th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php if ($offers): foreach ($offers as $o): ?>
          <tr class="text-center quotation-row" data-href="quotation_view.php?id=<?= (int)$o['id'] ?>" style="cursor:pointer;">
            <td><?= (int)$o['id'] ?></td>
            <td>
              <?= e($o['customer']) ?>
              <?php if (!empty($o['company'])): ?>
                (<?= e($o['company']) ?>)
              <?php endif; ?>
            </td>
            <td><?= e($assemblyLabels[$o['assembly_type']] ?? '') ?></td>
            <td><?= e($paymentLabels[$o['payment_method']] ?? '') ?></td>
            <td><?= $o['validity_days'] !== null ? (int)$o['validity_days'] . ' gün' : '' ?></td>
            <td>
              <?php if ($o['payment_method'] === 'vadeli'): ?>
                <?= $o['term_months'] !== null ? (int)$o['term_months'] . ' ay' : '' ?>
                <?= $o['interest_value'] !== null ? ' %' . e($o['interest_value']) : '' ?>
              <?php endif; ?>
            </td>
            <td><time datetime="<?= e($o['offer_date']) ?>"><?= e($o['offer_date']) ?></time></td>
            <td><?= number_format((float)$o['total_amount'], 2, ',', '.') ?> ₺</td>
            <td><?= $o['approved_at'] ? e($o['approved_at']) : '' ?></td>
            <td><?= e($statusLabels[$o['status']] ?? $o['status']) ?></td>
            <td class="text-center">
              <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="bi bi-three-dots"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a href="quotation_view.php?id=<?= (int)$o['id'] ?>" class="dropdown-item">Görüntüle</a></li>
                  <li>
                    <form method="post">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                      <button type="submit" class="dropdown-item text-danger" data-confirm="Bu teklif silinsin mi?">Sil</button>
                    </form>
                  </li>
                </ul>
              </div>
            </td>
          </tr>
        <?php endforeach;
      else: ?>
        <tr>
          <td colspan="11" class="text-center text-muted">Teklif bulunamadı.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$baseParams = $_GET;
unset($baseParams['page']);
$baseParams['per_page'] = $perPage;
?>
<nav aria-label="Sayfalama">
  <ul class="pagination justify-content-center">
    <?php $isFirst = $page <= 1; ?>
    <li class="page-item <?= $isFirst ? 'disabled' : '' ?>">
      <?php if ($isFirst): ?>
        <span class="page-link">İlk</span>
      <?php else: ?>
        <a class="page-link" href="?<?= http_build_query(array_merge($baseParams, ['page' => 1])) ?>">İlk</a>
      <?php endif; ?>
    </li>
    <li class="page-item <?= $isFirst ? 'disabled' : '' ?>">
      <?php if ($isFirst): ?>
        <span class="page-link">Önceki</span>
      <?php else: ?>
        <a class="page-link" href="?<?= http_build_query(array_merge($baseParams, ['page' => $page - 1])) ?>">Önceki</a>
      <?php endif; ?>
    </li>
    <?php
    $pages = $totalPages <= 7
      ? range(1, $totalPages)
      : array_unique(array_filter(array_merge(
        range(1, 3),
        range($page - 2, $page + 2),
        range($totalPages - 2, $totalPages)
      ), fn($p) => $p >= 1 && $p <= $totalPages));
    sort($pages);
    $prev = 0;
    foreach ($pages as $p):
      if ($prev && $p > $prev + 1): ?>
        <li class="page-item disabled"><span class="page-link">…</span></li>
      <?php endif; ?>
      <li class="page-item <?= $page === $p ? 'active' : '' ?>">
        <a class="page-link" href="?<?= http_build_query(array_merge($baseParams, ['page' => $p])) ?>"><?= $p ?></a>
      </li>
    <?php $prev = $p;
    endforeach; ?>
    <?php $isLast = $page >= $totalPages; ?>
    <li class="page-item <?= $isLast ? 'disabled' : '' ?>">
      <?php if ($isLast): ?>
        <span class="page-link">Sonraki</span>
      <?php else: ?>
        <a class="page-link" href="?<?= http_build_query(array_merge($baseParams, ['page' => $page + 1])) ?>">Sonraki</a>
      <?php endif; ?>
    </li>
    <li class="page-item <?= $isLast ? 'disabled' : '' ?>">
      <?php if ($isLast): ?>
        <span class="page-link">Son</span>
      <?php else: ?>
        <a class="page-link" href="?<?= http_build_query(array_merge($baseParams, ['page' => $totalPages])) ?>">Son</a>
      <?php endif; ?>
    </li>
  </ul>
</nav>

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
            <select name="customer_id" class="form-select <?= isset($createErrors['customer_id']) ? 'is-invalid' : '' ?>" required>
              <option value="">Seçiniz</option>
              <?php foreach ($customers as $c): $label = trim($c['first_name'] . ' ' . $c['last_name']);
                if (!empty($c['company'])) $label .= ' (' . $c['company'] . ')'; ?>
                <option value="<?= (int)$c['id'] ?>" <?= $createData['customer_id'] == (string)$c['id'] ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($createErrors['customer_id'])): ?><div class="invalid-feedback"><?= e($createErrors['customer_id']) ?></div><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Montaj Tipi</label>
            <select name="assembly_type" class="form-select <?= isset($createErrors['assembly_type']) ? 'is-invalid' : '' ?>" required>
              <option value="">Seçiniz</option>
              <?php foreach ($assemblyLabels as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $createData['assembly_type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($createErrors['assembly_type'])): ?><div class="invalid-feedback"><?= e($createErrors['assembly_type']) ?></div><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Ödeme Yöntemi</label>
            <select name="payment_method" class="form-select <?= isset($createErrors['payment_method']) ? 'is-invalid' : '' ?>" required>
              <option value="">Seçiniz</option>
              <?php foreach ($paymentLabels as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $createData['payment_method'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($createErrors['payment_method'])): ?><div class="invalid-feedback"><?= e($createErrors['payment_method']) ?></div><?php endif; ?>
          </div>
          <div class="mb-3 vadeli-fields" style="display:none;">
            <label class="form-label">Vade Süresi (ay)</label>
            <input type="number" min="1" name="term_months" class="form-control <?= isset($createErrors['term_months']) ? 'is-invalid' : '' ?>" value="<?= e($createData['term_months']) ?>">
            <?php if (isset($createErrors['term_months'])): ?><div class="invalid-feedback"><?= e($createErrors['term_months']) ?></div><?php endif; ?>
          </div>
          <div class="mb-3 vadeli-fields" style="display:none;">
            <label class="form-label">Vade Farkı (aylık)</label>
            <input type="number" step="0.01" name="interest_value" class="form-control <?= isset($createErrors['interest_value']) ? 'is-invalid' : '' ?>" value="<?= e($createData['interest_value']) ?>">
            <?php if (isset($createErrors['interest_value'])): ?><div class="invalid-feedback"><?= e($createErrors['interest_value']) ?></div><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Teklif Süresi (gün)</label>
            <input type="number" min="1" max="365" name="validity_days" class="form-control <?= isset($createErrors['validity_days']) ? 'is-invalid' : '' ?>" value="<?= e($createData['validity_days']) ?>" placeholder="örn. 15">
            <?php if (isset($createErrors['validity_days'])): ?><div class="invalid-feedback"><?= e($createErrors['validity_days']) ?></div><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Teklif Tarihi</label>
            <input type="date" name="offer_date" class="form-control <?= isset($createErrors['offer_date']) ? 'is-invalid' : '' ?>" value="<?= e($createData['offer_date']) ?>" required>
            <?php if (isset($createErrors['offer_date'])): ?><div class="invalid-feedback"><?= e($createErrors['offer_date']) ?></div><?php endif; ?>
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
<script>
  function toggleVadeliFields() {
    var payment = document.querySelector('#createModal select[name="payment_method"]').value;
    document.querySelectorAll('#createModal .vadeli-fields').forEach(function(el) {
      el.style.display = payment === 'vadeli' ? '' : 'none';
    });
    var inst = document.querySelector('#createModal .installment-field');
    if (inst) inst.style.display = payment === 'vadeli' ? '' : 'none';
  }
  document.querySelector('#createModal select[name="payment_method"]').addEventListener('change', toggleVadeliFields);
  toggleVadeliFields();

  document.querySelectorAll('.quotation-row').forEach(function(row) {
    row.addEventListener('click', function(e) {
      if (e.target.closest('a,button,form,input,select,textarea,label')) return;
      window.location = this.dataset.href;
    });
  });

  <?php if ($createErrors): ?>
    var createModal = new bootstrap.Modal(document.getElementById('createModal'));
    createModal.show();
  <?php endif; ?>
</script>
<?php require __DIR__ . '/footer.php'; ?>