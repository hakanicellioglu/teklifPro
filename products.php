<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');

$role = $_SESSION['role'] ?? 'kullanıcı';

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$errors = [];

function handleImageUpload(string $field, array &$errors, ?string $current = null): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return $current;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Resim yüklenirken bir hata oluştu.';
        return $current;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        $errors[] = 'Resim 2MB\'den büyük olamaz.';
        return $current;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $type = $finfo->file($file['tmp_name']);
    $ext = match ($type) {
        'image/jpeg' => '.jpg',
        'image/png' => '.png',
        default => null,
    };
    if (!$ext) {
        $errors[] = 'Yalnızca JPEG veya PNG dosyaları kabul edilir.';
        return $current;
    }
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $filename = uniqid('prd_', true) . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
        $errors[] = 'Resim yükleme başarısız.';
        return $current;
    }
    if ($current && is_file($uploadDir . '/' . $current)) {
        unlink($uploadDir . '/' . $current);
    }
    return $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        die('Geçersiz CSRF belirteci');
    }
    if ($role !== 'admin') {
        die('Yetkisiz işlem');
    }
    $method = $_POST['_method'] ?? 'POST';
    if ($method === 'POST') {
        $product_code = trim($_POST['product_code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $unit = trim($_POST['unit'] ?? '');
        $color = trim($_POST['color'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $unit_price = trim($_POST['unit_price'] ?? '');
        $vat_rate = trim($_POST['vat_rate'] ?? '');

        if ($name === '') {
            $errors[] = 'Ürün adı zorunludur.';
        }
        if ($unit_price === '' || !is_numeric($unit_price)) {
            $errors[] = 'Birim fiyatı geçerli bir sayı olmalıdır.';
        }
        if ($vat_rate !== '' && !is_numeric($vat_rate)) {
            $errors[] = 'KDV oranı geçerli bir sayı olmalıdır.';
        }
        $image = handleImageUpload('image', $errors);
        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO products (product_code, name, category, unit, color, image_path, description, unit_price, vat_rate) VALUES (:product_code, :name, :category, :unit, :color, :image_path, :description, :unit_price, :vat_rate)');
            $stmt->execute([
                ':product_code' => $product_code,
                ':name' => $name,
                ':category' => $category,
                ':unit' => $unit,
                ':color' => $color,
                ':image_path' => $image,
                ':description' => $description,
                ':unit_price' => $unit_price,
                ':vat_rate' => $vat_rate !== '' ? $vat_rate : null,
            ]);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } elseif ($method === 'PUT') {
        $id = (int)($_POST['id'] ?? 0);
        $product_code = trim($_POST['product_code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $unit = trim($_POST['unit'] ?? '');
        $color = trim($_POST['color'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $unit_price = trim($_POST['unit_price'] ?? '');
        $vat_rate = trim($_POST['vat_rate'] ?? '');

        if ($name === '') {
            $errors[] = 'Ürün adı zorunludur.';
        }
        if ($unit_price === '' || !is_numeric($unit_price)) {
            $errors[] = 'Birim fiyatı geçerli bir sayı olmalıdır.';
        }
        if ($vat_rate !== '' && !is_numeric($vat_rate)) {
            $errors[] = 'KDV oranı geçerli bir sayı olmalıdır.';
        }
        $stmt = $pdo->prepare('SELECT image_path FROM products WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $currentImage = $stmt->fetchColumn();
        $image = handleImageUpload('image', $errors, $currentImage);
        if (!$errors) {
            $stmt = $pdo->prepare('UPDATE products SET product_code=:product_code, name=:name, category=:category, unit=:unit, color=:color, image_path=:image_path, description=:description, unit_price=:unit_price, vat_rate=:vat_rate WHERE id=:id');
            $stmt->execute([
                ':product_code' => $product_code,
                ':name' => $name,
                ':category' => $category,
                ':unit' => $unit,
                ':color' => $color,
                ':image_path' => $image,
                ':description' => $description,
                ':unit_price' => $unit_price,
                ':vat_rate' => $vat_rate !== '' ? $vat_rate : null,
                ':id' => $id,
            ]);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } elseif ($method === 'DELETE') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT image_path FROM products WHERE id=:id');
        $stmt->execute([':id' => $id]);
        $img = $stmt->fetchColumn();
        if ($img && is_file(__DIR__ . '/uploads/' . $img)) {
            unlink(__DIR__ . '/uploads/' . $img);
        }
        $stmt = $pdo->prepare('DELETE FROM products WHERE id=:id');
        $stmt->execute([':id' => $id]);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

$cats = $pdo->query('SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> "" ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

$search = trim($_GET['search'] ?? '');
$filterCat = trim($_GET['category'] ?? '');
$page = max((int)($_GET['page'] ?? 1), 1);
$perPage = 12;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(name LIKE :search OR product_code LIKE :search OR category LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
if ($filterCat !== '') {
    $where[] = 'category = :category';
    $params[':category'] = $filterCat;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM products ' . $whereSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($total / $perPage);

$productStmt = $pdo->prepare('SELECT * FROM products ' . $whereSql . ' ORDER BY id DESC LIMIT :limit OFFSET :offset');
foreach ($params as $key => $value) {
    $productStmt->bindValue($key, $value);
}
$productStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$productStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$productStmt->execute();
$products = $productStmt->fetchAll();
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ürünler</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <h1 class="mb-4">Ürünler</h1>
    <?php if ($role === 'admin'): ?>
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#createModal">Ürün Ekle</button>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $err): ?>
                <div><?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form class="row g-2 mb-4" method="get">
        <div class="col-sm-4">
            <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Ara...">
        </div>
        <div class="col-sm-3">
            <select name="category" class="form-select">
                <option value="">Kategori</option>
                <?php foreach ($cats as $cat): ?>
                    <option value="<?= e($cat) ?>" <?php if ($filterCat === $cat) echo 'selected'; ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-2">
            <button class="btn btn-secondary w-100" type="submit">Filtrele</button>
        </div>
    </form>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
        <?php foreach ($products as $p): ?>
        <?php
            $imgSrc = 'https://via.placeholder.com/300x200?text=Resim+Yok';
            if ($p['image_path'] && is_file(__DIR__ . '/uploads/' . $p['image_path'])) {
                $imgSrc = 'uploads/' . rawurlencode($p['image_path']);
            }
        ?>
        <div class="col">
            <div class="card h-100">
                <img src="<?= e($imgSrc) ?>" class="card-img-top" alt="<?= e($p['name']) ?>">
                <div class="card-body">
                    <h5 class="card-title"><?= e($p['name']) ?></h5>
                    <p class="card-text">
                        <strong>Kategori:</strong> <?= e($p['category']) ?><br>
                        <strong>Renk:</strong> <?= e($p['color']) ?><br>
                        <strong>Birim:</strong> <?= e($p['unit']) ?><br>
                        <strong>Fiyat:</strong> <?= number_format((float)$p['unit_price'], 2, ',', '.') ?> TL
                    </p>
                </div>
                <?php if ($role === 'admin'): ?>
                <div class="card-footer text-end">
                    <button class="btn btn-sm btn-warning me-1" data-bs-toggle="modal" data-bs-target="#editModal"
                        data-id="<?= $p['id'] ?>"
                        data-product_code="<?= e($p['product_code']) ?>"
                        data-name="<?= e($p['name']) ?>"
                        data-category="<?= e($p['category']) ?>"
                        data-unit="<?= e($p['unit']) ?>"
                        data-color="<?= e($p['color']) ?>"
                        data-description="<?= e($p['description']) ?>"
                        data-unit_price="<?= $p['unit_price'] ?>"
                        data-vat_rate="<?= $p['vat_rate'] ?>"
                    >Düzenle</button>
                    <form method="post" class="d-inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="_method" value="DELETE">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php $url = '?' . http_build_query(['search' => $search, 'category' => $filterCat, 'page' => $i]); ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= e($url) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php if ($role === 'admin'): ?>
<div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Ürün Ekle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <div class="mb-3">
          <label class="form-label">Ürün Kodu</label>
          <input type="text" name="product_code" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Adı*</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Kategori</label>
          <input type="text" name="category" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Birim</label>
          <input type="text" name="unit" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Renk</label>
          <input type="text" name="color" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Resim</label>
          <input type="file" name="image" accept="image/jpeg,image/png" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Açıklama</label>
          <textarea name="description" class="form-control"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Birim Fiyatı*</label>
          <input type="number" step="0.01" name="unit_price" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">KDV Oranı</label>
          <input type="number" step="0.01" name="vat_rate" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Kaydet</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Ürünü Düzenle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="id" id="edit-id">
        <div class="mb-3">
          <label class="form-label">Ürün Kodu</label>
          <input type="text" name="product_code" id="edit-product_code" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Adı*</label>
          <input type="text" name="name" id="edit-name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Kategori</label>
          <input type="text" name="category" id="edit-category" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Birim</label>
          <input type="text" name="unit" id="edit-unit" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Renk</label>
          <input type="text" name="color" id="edit-color" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Resim</label>
          <input type="file" name="image" accept="image/jpeg,image/png" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Açıklama</label>
          <textarea name="description" id="edit-description" class="form-control"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Birim Fiyatı*</label>
          <input type="number" step="0.01" name="unit_price" id="edit-unit_price" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">KDV Oranı</label>
          <input type="number" step="0.01" name="vat_rate" id="edit-vat_rate" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Güncelle</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($role === 'admin'): ?>
<script>
const editModal = document.getElementById('editModal');
editModal.addEventListener('show.bs.modal', event => {
  const button = event.relatedTarget;
  document.getElementById('edit-id').value = button.getAttribute('data-id');
  document.getElementById('edit-product_code').value = button.getAttribute('data-product_code');
  document.getElementById('edit-name').value = button.getAttribute('data-name');
  document.getElementById('edit-category').value = button.getAttribute('data-category');
  document.getElementById('edit-unit').value = button.getAttribute('data-unit');
  document.getElementById('edit-color').value = button.getAttribute('data-color');
  document.getElementById('edit-description').value = button.getAttribute('data-description');
  document.getElementById('edit-unit_price').value = button.getAttribute('data-unit_price');
  document.getElementById('edit-vat_rate').value = button.getAttribute('data-vat_rate');
});
</script>
<?php endif; ?>
</body>
</html>
