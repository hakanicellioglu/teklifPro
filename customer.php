<?php
require __DIR__ . '/header.php';

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)($_POST['delete_id'] ?? 0);
    if ($deleteId > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM customers WHERE id = :id');
            $stmt->execute(['id' => $deleteId]);
            header('Location: customer?success=' . urlencode('Customer deleted successfully.'));
            exit;
        } catch (Exception $e) {
            $error = 'Failed to delete customer.';
        }
    } else {
        $error = 'Invalid customer ID.';
    }
}

$search = trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Determine if registration date column exists
try {
    $hasDate = $pdo->query("SHOW COLUMNS FROM customers LIKE 'created_at'")->rowCount() > 0;
} catch (Exception $e) {
    $hasDate = false;
}

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
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll();

// Count total for pagination
$countSql = 'SELECT COUNT(*) FROM customers';
if ($search !== '') {
    $countSql .= ' WHERE first_name LIKE :term OR last_name LIKE :term OR company_name LIKE :term OR email LIKE :term OR phone LIKE :term';
}
$countStmt = $pdo->prepare($countSql);
if ($search !== '') {
    $countStmt->bindValue(':term', "%$search%", PDO::PARAM_STR);
}
$countStmt->execute();
$totalCustomers = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalCustomers / $limit);

$success = filter_input(INPUT_GET, 'success', FILTER_SANITIZE_SPECIAL_CHARS);
$error = $error ?? filter_input(INPUT_GET, 'error', FILTER_SANITIZE_SPECIAL_CHARS);
?>
<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="card-title mb-0">Müşteriler</h4>
                <a href="customers/add" class="btn btn-primary"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Yeni Müşteri Ekle</a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form class="mb-3" method="get" novalidate>
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search" aria-hidden="true"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>İsim</th>
                            <th>Company Name</th>
                            <th>Email</th>
                            <th>Telefon Numarası</th>
                            <th>Kayıt Tarihi</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($customers): ?>
                            <?php foreach ($customers as $cust): ?>
                                <tr>
                                    <td><?= htmlspecialchars(trim(($cust['first_name'] ?? '') . ' ' . ($cust['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($cust['company_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($cust['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($cust['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($cust['registration_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-end">
                                        <a href="customers/edit?id=<?= (int)$cust['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this customer?');">
                                            <input type="hidden" name="delete_id" value="<?= (int)$cust['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Müşteri bulunamadı.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php $query = http_build_query(['page' => $i, 'search' => $search]); ?>
                            <li class="page-item<?= $i === $page ? ' active' : ''; ?>">
                                <a class="page-link" href="customer?<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
