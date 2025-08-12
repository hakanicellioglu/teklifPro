<?php
require __DIR__ . '/header.php';

function e(?string $v): string
{
  return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function formatPrice(float $price): string
{
  if (class_exists('NumberFormatter')) {
    $fmt = new NumberFormatter('tr_TR', NumberFormatter::CURRENCY);
    return $fmt->formatCurrency($price, 'TRY');
  }
  return number_format($price, 2, ',', '.') . ' TL';
}

$errors = [];
$error = null;
$success = null;
$vatAllowed = [1, 8, 18, 20];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$categoryOptions = ['Cam', 'Alüminyum', 'Aksesuar', 'Fitil'];
$unitTypeOptions = ['kg', 'metre', 'metrekare'];

// Determine whether optional width/height columns exist
$colStmt = $pdo->query('SHOW COLUMNS FROM products');
$prodCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
$hasDimensions = in_array('width', $prodCols, true) && in_array('height', $prodCols, true);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin') {
  $action = $_POST['action'] ?? '';
  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      try {
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute(['id' => $id]);
        header('Location: products?success=' . urlencode('Ürün silindi.'));
        exit;
      } catch (Exception $e) {
        $error = 'Ürün silinemedi.';
      }
    } else {
      $error = 'Geçersiz ürün ID.';
    }
  } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    $product_code = trim($_POST['product_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $unit_type = trim($_POST['unit_type'] ?? '');
    $unit_value = (float)($_POST['unit_value'] ?? 0);

    if ($category === '' || !in_array($category, $categoryOptions, true)) {
      $errors[] = 'Kategori seçilmelidir.';
    }
    if ($unit_type === '' || !in_array($unit_type, $unitTypeOptions, true)) {
      $errors[] = 'Birim türü seçilmelidir.';
    }
    if ($unit_value <= 0) {
      $errors[] = 'Birim değeri > 0 olmalıdır.';
    }

    $color = trim($_POST['color'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit_price = trim($_POST['unit_price'] ?? '');
    $vat_rate = trim($_POST['vat_rate'] ?? '');
    if ($vat_rate === '') {
      $vat_rate = null;
    } elseif (!in_array((int)$vat_rate, $vatAllowed, true)) {
      $errors[] = 'KDV oranı sadece %1, %8, %18 veya %20 olabilir.';
    } else {
      $vat_rate = (int)$vat_rate;
    }

    if ($name === '') {
      $errors[] = 'Ürün adı zorunludur.';
    }
    if ($unit_price === '' || !is_numeric($unit_price)) {
      $errors[] = 'Birim fiyatı geçerli bir sayı olmalıdır.';
    }
    if ($vat_rate !== '' && !is_numeric($vat_rate)) {
      $errors[] = 'KDV oranı geçerli bir sayı olmalıdır.';
    }

    $stmt = $pdo->prepare('SELECT image_data, image_mime FROM products WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
      $errors[] = 'Ürün bulunamadı.';
    }

    $imageData = $current['image_data'] ?? null;
    $imageMime = $current['image_mime'] ?? null;

    if (!empty($_FILES['image']['tmp_name'])) {
      if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
        $errors[] = 'Resim 2MB\'yi aşamaz.';
      } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['image']['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
          $errors[] = 'Yalnızca JPEG veya PNG dosyaları kabul edilir.';
        } else {
          $imageData = file_get_contents($_FILES['image']['tmp_name']);
          $imageMime = $mime;
        }
      }
    }

    if (!$errors) {
      try {
        $sql = 'UPDATE products SET product_code=:product_code, name=:name, category=:category, unit=:unit_type, weight_per_meter=:unit_value, color=:color, description=:description, unit_price=:unit_price, vat_rate=:vat_rate, image_data=:image_data, image_mime=:image_mime WHERE id=:id';
        $stmt = $pdo->prepare($sql);
        $params = [
          ':product_code' => $product_code ?: null,
          ':name' => $name,
          ':category' => $category ?: null,
          ':unit_type' => $unit_type ?: null,
          ':unit_value' => $unit_value,
          ':color' => $color ?: null,
          ':description' => $description ?: null,
          ':unit_price' => $unit_price,
          ':vat_rate' => $vat_rate !== '' ? $vat_rate : null,
          ':image_data' => $imageData,
          ':image_mime' => $imageMime,
          ':id' => $id,
        ];
        $stmt->execute($params);
        header('Location: products?success=' . urlencode('Ürün güncellendi.'));
        exit;
      } catch (Exception $e) {
        $error = 'Ürün güncellenemedi.';
      }
    } else {
      $error = implode(' ', $errors);
    }
  } elseif ($action === 'create') {
    $product_code = trim($_POST['product_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $unit_type = trim($_POST['unit_type'] ?? '');
    $unit_value = (float)($_POST['unit_value'] ?? 0);
    $color = trim($_POST['color'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit_price = trim($_POST['unit_price'] ?? '');
    $vat_rate = trim($_POST['vat_rate'] ?? '');

    if ($category === '' || !in_array($category, $categoryOptions, true)) {
      $errors[] = 'Kategori seçilmelidir.';
    }
    if ($unit_type === '' || !in_array($unit_type, $unitTypeOptions, true)) {
      $errors[] = 'Birim türü seçilmelidir.';
    }
    if ($unit_value <= 0) {
      $errors[] = 'Birim değeri > 0 olmalıdır.';
    }
    if ($vat_rate === '') {
      $vat_rate = null;
    } elseif (!in_array((int)$vat_rate, $vatAllowed, true)) {
      $errors[] = 'KDV oranı sadece %1, %8, %18 veya %20 olabilir.';
    } else {
      $vat_rate = (int)$vat_rate;
    }

    if ($name === '') {
      $errors[] = 'Ürün adı zorunludur.';
    }
    if ($unit_price === '' || !is_numeric($unit_price)) {
      $errors[] = 'Birim fiyatı geçerli bir sayı olmalıdır.';
    }
    if ($vat_rate !== '' && !is_numeric($vat_rate)) {
      $errors[] = 'KDV oranı geçerli bir sayı olmalıdır.';
    }

    $imageData = null;
    $imageMime = null;
    if (!empty($_FILES['image']['tmp_name'])) {
      if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
        $errors[] = 'Resim 2MB\'yi aşamaz.';
      } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['image']['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
          $errors[] = 'Yalnızca JPEG veya PNG dosyaları kabul edilir.';
        } else {
          $imageData = file_get_contents($_FILES['image']['tmp_name']);
          $imageMime = $mime;
        }
      }
    }

    if ($product_code === '') {
      $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(product_code, 5) AS UNSIGNED)) FROM products WHERE product_code LIKE 'PRD-%'");
      $maxCode = $stmt->fetchColumn();
      $nextCode = $maxCode ? ((int)$maxCode + 1) : 1;
      $product_code = sprintf('PRD-%02d', $nextCode);
    }

    if (!$errors) {
      try {
        $cols = 'product_code, name, category, unit, weight_per_meter, color, description, unit_price, vat_rate';
        $vals = ':product_code, :name, :category, :unit_type, :unit_value, :color, :description, :unit_price, :vat_rate';
        $cols .= ', image_data, image_mime';
        $vals .= ', :image_data, :image_mime';
        $stmt = $pdo->prepare("INSERT INTO products ($cols) VALUES ($vals)");
        $params = [
          ':product_code' => $product_code,
          ':name' => $name,
          ':category' => $category ?: null,
          ':unit_type' => $unit_type ?: null,
          ':unit_value' => $unit_value,
          ':color' => $color ?: null,
          ':description' => $description ?: null,
          ':unit_price' => $unit_price,
          ':vat_rate' => $vat_rate !== '' ? $vat_rate : null,
          ':image_data' => $imageData,
          ':image_mime' => $imageMime,
        ];
        $stmt->execute($params);
        header('Location: products?success=' . urlencode('Ürün eklendi.'));
        exit;
      } catch (Exception $e) {
        $error = 'Ürün eklenemedi.';
      }
    } else {
      $error = implode(' ', $errors);
    }
  }
}
$success = filter_input(INPUT_GET, 'success', FILTER_SANITIZE_SPECIAL_CHARS);
  $error = $error ?? filter_input(INPUT_GET, 'error', FILTER_SANITIZE_SPECIAL_CHARS);

$fields = 'id, product_code, name, category, unit AS unit_type, weight_per_meter AS unit_value, color, image_data, image_mime, description, unit_price, vat_rate';
if ($hasDimensions) {
  $fields .= ', width, height';
}
$stmt = $pdo->query("SELECT $fields FROM products ORDER BY id DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Ürünler</h4>
    <?php if ($role === 'admin'): ?>
      <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createModal"><i class="bi bi-plus"></i> Yeni Ürün</button>
    <?php endif; ?>
  </div>
  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?= e($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= e($error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (!$products): ?>
    <div class="alert alert-warning text-warning bg-warning bg-opacity-10 border-0" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>Henüz ürün bulunmamaktadır.
    </div>
  <?php else: ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-3">
      <?php foreach ($products as $p): ?>
        <div class="col">
          <div class="card h-100">
            <?php if (!empty($p['image_data'])): ?>
              <img src="data:<?= e($p['image_mime']) ?>;base64,<?= base64_encode($p['image_data']) ?>" class="card-img-top" alt="<?= e($p['name']) ?>">
            <?php else: ?>
              <svg class="bd-placeholder-img card-img-top" width="100%" height="180" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Placeholder" preserveAspectRatio="xMidYMid slice" focusable="false">
                <rect width="100%" height="100%" fill="#e9ecef"></rect><text x="50%" y="50%" fill="#6c757d" dy=".3em" text-anchor="middle">Resim yok</text>
              </svg>
            <?php endif; ?>
            <div class="card-body">
              <h5 class="card-title"><?= e($p['name']) ?></h5>
              <?php if ($p['category']): ?>
                <p class="card-text mb-1"><?= e($p['category']) ?></p>
              <?php endif; ?>
              <?php if ($p['color']): ?>
                <p class="card-text mb-1"><?= e($p['color']) ?></p>
              <?php endif; ?>
              <?php if ($p['unit_type']): ?>
                <p class="card-text mb-1"><?= e($p['unit_type']) ?> <?= e($p['unit_value']) ?></p>
              <?php endif; ?>
              <p class="card-text fw-semibold">
                <?= e(formatPrice((float)$p['unit_price'])) ?>
                <?= $p['unit_type'] ? ' / ' . e($p['unit_type']) : '' ?>
              </p>
            </div>
            <?php if ($role === 'admin'): ?>
              <div class="card-footer d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit-<?= $p['id'] ?>"><i class="bi bi-pencil-square"></i></button>
                <form method="post" class="m-0" onsubmit="return confirm('Bu ürünü silmek istediğinizden emin misiniz?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </div>
              <div class="modal fade" id="edit-<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                    <form method="post" enctype="multipart/form-data">
                      <input type="hidden" name="action" value="edit">
                      <input type="hidden" name="id" value="<?= $p['id'] ?>">
                      <div class="modal-header">
                        <h5 class="modal-title">Ürünü Düzenle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <div class="row g-3">
                          <div class="col-md-6">
                            <label class="form-label">Ürün Kodu</label>
                            <input type="text" name="product_code" class="form-control" value="<?= e($p['product_code']) ?>">
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Adı *</label>
                            <input type="text" name="name" class="form-control" required value="<?= e($p['name']) ?>">
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Kategori *</label>
                            <select name="category" class="form-select" required>
                              <option value="">Seçiniz</option>
                              <?php foreach ($categoryOptions as $cat): ?>
                                <option value="<?= e($cat) ?>" <?= ($p['category'] === $cat) ? 'selected' : '' ?>><?= e($cat) ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Birim Türü *</label>
                            <select name="unit_type" class="form-select" required>
                              <option value="">Seçiniz</option>
                              <?php foreach ($unitTypeOptions as $ut): ?>
                                <option value="<?= e($ut) ?>" <?= ($p['unit_type'] === $ut) ? 'selected' : '' ?>><?= e($ut) ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Birim Değeri *</label>
                            <input type="number" step="0.001" min="0.001" name="unit_value" class="form-control" required value="<?= e($p['unit_value']) ?>">
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Renk</label>
                            <input type="text" name="color" class="form-control" value="<?= e($p['color']) ?>">
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Fiyat *</label>
                            <input type="number" step="10" name="unit_price" class="form-control" required value="<?= e($p['unit_price']) ?>">
                          </div>
                          <?php
                          $allowedVat = [1, 8, 18, 20];
                          $currentVat = is_null($p['vat_rate']) ? null : (int)round((float)$p['vat_rate']); // "20.00" -> 20
                          ?>
                          <div class="col-md-6">
                            <label class="form-label">KDV Oranı</label>
                            <select name="vat_rate" class="form-select">
                              <option value="">Seçiniz</option>
                              <?php foreach ($allowedVat as $vr): ?>
                                <option value="<?= $vr ?>" <?= ($currentVat === (int)$vr) ? 'selected' : '' ?>>%<?= $vr ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>

                          <div class="col-md-6">
                            <label class="form-label">Resim (JPEG/PNG, 2MB)</label>
                            <input type="file" name="image" class="form-control">
                          </div>
                          <div class="col-12">
                            <label class="form-label">Açıklama</label>
                            <textarea name="description" class="form-control" rows="3"><?= e($p['description']) ?></textarea>
                          </div>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($role === 'admin'): ?>
    <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
              <h5 class="modal-title">Yeni Ürün</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Ürün Kodu</label>
                  <input type="text" name="product_code" class="form-control"
                    value="<?= e($_POST['product_code'] ?? '') ?>"
                    placeholder="Boş bırakılırsa otomatik oluşturulur">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Adı *</label>
                  <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Kategori *</label>
                  <select name="category" class="form-select" required>
                    <option value="">Seçiniz</option>
                    <?php foreach ($categoryOptions as $cat): ?>
                      <option value="<?= e($cat) ?>" <?= (isset($_POST['category']) && $_POST['category'] === $cat) ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Birim Türü *</label>
                  <select name="unit_type" class="form-select" required>
                    <option value="">Seçiniz</option>
                    <?php foreach ($unitTypeOptions as $ut): ?>
                      <option value="<?= e($ut) ?>" <?= (isset($_POST['unit_type']) && $_POST['unit_type'] === $ut) ? 'selected' : '' ?>><?= e($ut) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Birim Değeri *</label>
                  <input type="number" step="0.001" min="0.001" name="unit_value" class="form-control" required value="<?= e($_POST['unit_value'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Renk</label>
                  <input type="text" name="color" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Fiyat *</label>
                  <input type="number" step="0.01" name="unit_price" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">KDV Oranı</label>
                  <select name="vat_rate" class="form-select">
                    <option value="">Seçiniz</option>
                    <?php foreach ([1, 8, 18, 20] as $vr): ?>
                      <option value="<?= $vr ?>" <?= (isset($_POST['vat_rate']) && $_POST['vat_rate'] === (string)$vr) ? 'selected' : '' ?>>%<?= $vr ?></option>
                    <?php endforeach; ?>
                  </select>

                </div>
                <div class="col-md-6">
                  <label class="form-label">Resim (JPEG/PNG, 2MB)</label>
                  <input type="file" name="image" class="form-control">
                </div>
                <div class="col-12">
                  <label class="form-label">Açıklama</label>
                  <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
              <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<script>
// No additional JavaScript required
</script>
</body>

</html>