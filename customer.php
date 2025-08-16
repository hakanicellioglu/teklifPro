<?php declare(strict_types=1);
require __DIR__ . '/header.php';
require __DIR__ . '/components/page_header.php';
$pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_turkish_ci');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)($_POST['delete_id'] ?? 0);
    if ($deleteId > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM customers WHERE id = :id');
            $stmt->execute(['id' => $deleteId]);
            header('Location: customer.php?success=' . urlencode('Müşteri silindi.'));
            exit;
        } catch (Exception $e) {
            $error = 'Silme başarısız.';
        }
    } else { $error = 'Geçersiz müşteri ID.'; }
}

$search = trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$allowedPerPage = [10, 25, 50, 100];
if (isset($_GET['per_page'])) {
    $candidate = (int)$_GET['per_page'];
    if (!in_array($candidate, $allowedPerPage, true)) {
        $candidate = 10;
    }
    $_SESSION['per_page'] = $candidate;
}
$perPage = (int)($_SESSION['per_page'] ?? 10);
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 10;
    $_SESSION['per_page'] = $perPage;
}

$headers = [
    ['label' => 'İsim',          'key' => 'name'],
    ['label' => 'Şirket',        'key' => 'company_name'],
    ['label' => 'Email',         'key' => 'email'],
    ['label' => 'Telefon',       'key' => 'phone'],
    ['label' => 'Kayıt Tarihi',  'key' => 'registration_date'],
    ['label' => 'İşlemler',      'key' => null],
];
$allowedSorts = [
    'id'               => 'id',
    'name'             => 'first_name',
    'company_name'     => 'company_name',
    'email'            => 'email',
    'phone'            => 'phone',
    'registration_date'=> 'registration_date',
];
$sort = $_GET['sort'] ?? 'id';
$dirParam = strtolower($_GET['dir'] ?? 'desc');
$dir = $dirParam === 'asc' ? 'ASC' : 'DESC';
if (!array_key_exists($sort, $allowedSorts)) { $sort = 'id'; }
$orderSql = $allowedSorts[$sort] . ' ' . $dir;

$baseSql = 'FROM customers';
$params = [];
if ($search !== '') {
    $baseSql .= ' WHERE first_name LIKE :term OR last_name LIKE :term OR company_name LIKE :term OR email LIKE :term OR phone LIKE :term';
    $params['term'] = "%$search%";
}
$countStmt = $pdo->prepare('SELECT COUNT(*) ' . $baseSql);
foreach ($params as $key => $value) { $countStmt->bindValue(':' . $key, $value, PDO::PARAM_STR); }
$countStmt->execute();
$totalCustomers = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCustomers / $perPage));

$page = (int)($_GET['page'] ?? 1);
if ($page < 1) { $page = 1; }
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;

try { $hasDate = $pdo->query("SHOW COLUMNS FROM customers LIKE 'created_at'")->rowCount() > 0; } catch (Exception $e) { $hasDate = false; }
$sql = 'SELECT id, first_name, last_name, company_name, email, phone';
$sql .= $hasDate ? ', created_at AS registration_date' : ', NULL AS registration_date';
$sql .= ' ' . $baseSql;
$sql .= ' ORDER BY ' . $orderSql . ' LIMIT :limit OFFSET :offset';
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) { $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR); }
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll();

$success = filter_input(INPUT_GET, 'success', FILTER_SANITIZE_SPECIAL_CHARS);
$error = $error ?? filter_input(INPUT_GET, 'error', FILTER_SANITIZE_SPECIAL_CHARS);

$paginationPages = [];
if ($totalPages > 1) {
    $range = 2;
    $pagesToShow = [];
    for ($i = 1; $i <= 2 && $i <= $totalPages; $i++) { $pagesToShow[] = $i; }
    for ($i = $totalPages - 1; $i <= $totalPages; $i++) { if ($i > 2) { $pagesToShow[] = $i; } }
    for ($i = $page - $range; $i <= $page + $range; $i++) { if ($i > 0 && $i <= $totalPages) { $pagesToShow[] = $i; } }
    $pagesToShow = array_unique($pagesToShow);
    sort($pagesToShow);
    $prev = 0;
    foreach ($pagesToShow as $p) {
        if ($prev && $p - $prev > 1) { $paginationPages[] = null; }
        $paginationPages[] = $p;
        $prev = $p;
    }
}
?>
<?php page_header('Müşteriler', '<button type="button" id="addCustomerBtn" class="btn btn-primary btn-icon"><i class="bi bi-person-plus"></i>Müşteri Ekle</button>'); ?>
<?php if ($success): ?><div class="alert alert-success" role="alert"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<form class="mb-3" method="get" role="search">
  <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES, 'UTF-8'); ?>">
  <input type="hidden" name="dir" value="<?= htmlspecialchars($dirParam, ENT_QUOTES, 'UTF-8'); ?>">
  <input type="hidden" name="per_page" value="<?= (int)$perPage; ?>">
  <div class="input-group">
    <input type="search" name="search" class="form-control" placeholder="Ara" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
  </div>
</form>

<form method="get" class="mb-3 d-flex justify-content-end align-items-center gap-2">
  <?php foreach ($_GET as $key => $value): if (!in_array($key, ['per_page', 'page'], true)): ?>
    <input type="hidden" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" value="<?= htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; endforeach; ?>
  <label for="per_page" class="form-label mb-0">Sayfa Boyutu</label>
  <select name="per_page" id="per_page" class="form-select form-select-sm w-auto">
    <?php foreach ($allowedPerPage as $size): ?>
      <option value="<?= $size ?>"<?= $size === $perPage ? ' selected' : '' ?>><?= $size ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-sm btn-outline-secondary">Uygula</button>
</form>
<div class="table-responsive">
<table class="table table-hover align-middle">
  <thead class="table-light sticky-top">
    <tr class="text-center">
      <?php foreach ($headers as $h): ?>
        <th scope="col">
          <?= htmlspecialchars($h['label'], ENT_QUOTES, 'UTF-8'); ?>
          <?php if ($h['key']):
            $isCurrent = $sort === $h['key'];
            $nextDir = ($isCurrent && $dirParam === 'asc') ? 'desc' : 'asc';
            $icon = 'bi-arrow-down-up';
            if ($isCurrent) {
              $icon = $dirParam === 'asc' ? 'bi-caret-up-fill' : 'bi-caret-down-fill';
            }
          ?>
            <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['sort'=>$h['key'],'dir'=>$nextDir,'page'=>1])), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-link p-0"><i class="bi <?= $icon ?>"></i></a>
          <?php endif; ?>
        </th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
<?php if ($customers): foreach ($customers as $cust): ?>
<tr class="text-center" data-id="<?= (int)$cust['id']; ?>">
  <td class="col-name"><?= htmlspecialchars(trim(($cust['first_name'] ?? '') . ' ' . ($cust['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
  <td class="col-company"><?= htmlspecialchars($cust['company_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
  <td class="col-email"><?= htmlspecialchars($cust['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
  <td class="col-phone"><?= htmlspecialchars($cust['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
  <td><?= htmlspecialchars($cust['registration_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
  <td class="text-center">
    <button type="button" class="btn btn-sm btn-outline-secondary editCustomerBtn" data-id="<?= (int)$cust['id']; ?>" title="Düzenle"><i class="bi bi-pencil"></i></button>
    <form method="post" class="d-inline">
      <input type="hidden" name="delete_id" value="<?= (int)$cust['id']; ?>">
      <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Bu müşteri silinsin mi?" title="Sil"><i class="bi bi-trash"></i></button>
    </form>
  </td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="6" class="text-center text-muted">Kriterlere uygun müşteri bulunamadı.</td></tr>
<?php endif; ?>
  </tbody>
</table>
</div>
<?php if ($totalPages > 1): ?>
<?php $baseParams = $_GET; $baseParams['per_page'] = $perPage; unset($baseParams['page']); ?>
<nav aria-label="Sayfalama">
  <ul class="pagination justify-content-center">
    <?php $firstQuery = http_build_query(array_merge($baseParams, ['page' => 1])); ?>
    <li class="page-item<?= $page === 1 ? ' disabled' : ''; ?>">
      <?php if ($page === 1): ?>
        <span class="page-link">İlk</span>
      <?php else: ?>
        <a class="page-link" href="customer.php?<?= htmlspecialchars($firstQuery, ENT_QUOTES, 'UTF-8'); ?>" aria-label="İlk">İlk</a>
      <?php endif; ?>
    </li>
    <?php $prevQuery = http_build_query(array_merge($baseParams, ['page' => $page - 1])); ?>
    <li class="page-item<?= $page === 1 ? ' disabled' : ''; ?>">
      <?php if ($page === 1): ?>
        <span class="page-link">Önceki</span>
      <?php else: ?>
        <a class="page-link" href="customer.php?<?= htmlspecialchars($prevQuery, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Önceki">Önceki</a>
      <?php endif; ?>
    </li>
    <?php foreach ($paginationPages as $pg): ?>
      <?php if ($pg === null): ?>
        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
      <?php elseif ($pg === $page): ?>
        <li class="page-item active" aria-current="page"><span class="page-link"><?= htmlspecialchars((string)$pg, ENT_QUOTES, 'UTF-8'); ?></span></li>
      <?php else: ?>
        <?php $query = http_build_query(array_merge($baseParams, ['page' => $pg])); ?>
        <li class="page-item"><a class="page-link" href="customer.php?<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string)$pg, ENT_QUOTES, 'UTF-8'); ?></a></li>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php $nextQuery = http_build_query(array_merge($baseParams, ['page' => $page + 1])); ?>
    <li class="page-item<?= $page >= $totalPages ? ' disabled' : ''; ?>">
      <?php if ($page >= $totalPages): ?>
        <span class="page-link">Sonraki</span>
      <?php else: ?>
        <a class="page-link" href="customer.php?<?= htmlspecialchars($nextQuery, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Sonraki">Sonraki</a>
      <?php endif; ?>
    </li>
    <?php $lastQuery = http_build_query(array_merge($baseParams, ['page' => $totalPages])); ?>
    <li class="page-item<?= $page >= $totalPages ? ' disabled' : ''; ?>">
      <?php if ($page >= $totalPages): ?>
        <span class="page-link">Son</span>
      <?php else: ?>
        <a class="page-link" href="customer.php?<?= htmlspecialchars($lastQuery, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Son">Son</a>
      <?php endif; ?>
    </li>
  </ul>
</nav>
<?php endif; ?>
<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="customerForm">
        <div class="modal-header">
          <h5 class="modal-title" id="customerModalLabel">Müşteri Ekle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="customer_id">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="first_name" name="first_name" placeholder="İsim">
                <label for="first_name">İsim</label>
                <div class="invalid-feedback"></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Soyisim">
                <label for="last_name">Soyisim</label>
                <div class="invalid-feedback"></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="company_name" name="company_name" placeholder="Şirket">
                <label for="company_name">Şirket</label>
                <div class="invalid-feedback"></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="email" class="form-control" id="email" name="email" placeholder="E-posta">
                <label for="email">E-posta</label>
                <div class="invalid-feedback"></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="tel" class="form-control" id="phone" name="phone" placeholder="Telefon" pattern="^[0-9\s\+\-]{10,}$">
                <label for="phone">Telefon</label>
                <div class="invalid-feedback"></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="tax_number" name="tax_number" placeholder="Vergi No">
                <label for="tax_number">Vergi No</label>
                <div class="invalid-feedback"></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="tax_office" name="tax_office" placeholder="Vergi Dairesi">
                <label for="tax_office">Vergi Dairesi</label>
                <div class="invalid-feedback"></div>
              </div>
            </div>
            <div class="col-12">
              <div class="form-floating">
                <textarea class="form-control" id="address" name="address" placeholder="Adres" style="height:100px"></textarea>
                <label for="address">Adres</label>
                <div class="invalid-feedback"></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="city" name="city" placeholder="Şehir">
                <label for="city">Şehir</label>
                <div class="invalid-feedback"></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="country" name="country" placeholder="Ülke">
                <label for="country">Ülke</label>
                <div class="invalid-feedback"></div>
              </div>
            </div>
            <div class="col-12">
              <div class="form-floating">
                <textarea class="form-control" id="notes" name="notes" placeholder="Notlar" style="height:100px"></textarea>
                <label for="notes">Notlar</label>
                <div class="invalid-feedback"></div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
          <button type="submit" id="saveCustomerBtn" class="btn btn-primary">Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
