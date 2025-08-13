<?php
require __DIR__ . '/header.php';
require __DIR__ . '/components/page_header.php';
require __DIR__ . '/components/data_table.php';
$pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_turkish_ci');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)($_POST['delete_id'] ?? 0);
    if ($deleteId > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM customers WHERE id = :id');
            $stmt->execute(['id' => $deleteId]);
            header('Location: customer?success=' . urlencode('Müşteri silindi.'));
            exit;
        } catch (Exception $e) {
            $error = 'Silme başarısız.';
        }
    } else { $error = 'Geçersiz müşteri ID.'; }
}

$search = trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

try { $hasDate = $pdo->query("SHOW COLUMNS FROM customers LIKE 'created_at'")->rowCount() > 0; } catch (Exception $e) { $hasDate = false; }
$sql = 'SELECT id, first_name, last_name, company_name, email, phone';
$sql .= $hasDate ? ', created_at AS registration_date' : ', NULL AS registration_date';
$sql .= ' FROM customers';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE first_name LIKE :term OR last_name LIKE :term OR company_name LIKE :term OR email LIKE :term OR phone LIKE :term';
    $params['term'] = "%$search%";
}
$sql .= ' ORDER BY id DESC LIMIT :limit OFFSET :offset';
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) { $stmt->bindValue($key, $value, PDO::PARAM_STR); }
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll();

$countSql = 'SELECT COUNT(*) FROM customers';
if ($search !== '') { $countSql .= ' WHERE first_name LIKE :term OR last_name LIKE :term OR company_name LIKE :term OR email LIKE :term OR phone LIKE :term'; }
$countStmt = $pdo->prepare($countSql);
if ($search !== '') { $countStmt->bindValue(':term', "%$search%", PDO::PARAM_STR); }
$countStmt->execute();
$totalCustomers = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalCustomers / $limit);

$success = filter_input(INPUT_GET, 'success', FILTER_SANITIZE_SPECIAL_CHARS);
$error = $error ?? filter_input(INPUT_GET, 'error', FILTER_SANITIZE_SPECIAL_CHARS);
?>
<?php page_header('Müşteriler', '<a href="customers/add" class="btn btn-primary btn-icon"><i class="bi bi-person-plus"></i>Yeni Müşteri</a>'); ?>
<?php if ($success): ?><div class="alert alert-success" role="alert"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<form class="mb-3" method="get" role="search">
  <div class="input-group">
    <input type="search" name="search" class="form-control" placeholder="Ara" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
  </div>
</form>
<?php data_table_start(['İsim','Şirket','Email','Telefon','Kayıt Tarihi','İşlemler'], 'text-center'); ?>
<?php if ($customers): foreach ($customers as $cust): ?>
<tr class="text-center">
  <td><?= htmlspecialchars(trim(($cust['first_name'] ?? '') . ' ' . ($cust['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
  <td><?= htmlspecialchars($cust['company_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
  <td><?= htmlspecialchars($cust['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
  <td><?= htmlspecialchars($cust['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
  <td><?= htmlspecialchars($cust['registration_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
  <td class="text-center">
    <a href="customers/edit?id=<?= (int)$cust['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Düzenle"><i class="bi bi-pencil"></i></a>
    <form method="post" class="d-inline">
      <input type="hidden" name="delete_id" value="<?= (int)$cust['id']; ?>">
      <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Bu müşteri silinsin mi?" title="Sil"><i class="bi bi-trash"></i></button>
    </form>
  </td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="6" class="text-center text-muted">Müşteri bulunamadı.</td></tr>
<?php endif; ?>
<?php data_table_end(); ?>
<?php if ($totalPages > 1): ?>
<nav aria-label="Sayfalar">
  <ul class="pagination justify-content-center">
    <?php for ($i = 1; $i <= $totalPages; $i++): $query = http_build_query(['page'=>$i,'search'=>$search]); ?>
      <li class="page-item<?= $i === $page ? ' active' : ''; ?>"><a class="page-link" href="customer?<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>"><?= $i; ?></a></li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>
