<?php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (empty($_SESSION['user_id'])) {
  header('Location: login');
  exit;
}
$stmt = $pdo->prepare('SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :id');
$stmt->execute(['id' => $_SESSION['user_id']]);
$role = $stmt->fetchColumn() ?: 'user';

function e(?string $v): string
{
  return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function formatPrice(float $price, string $currency = 'TRY'): string
{
  if (class_exists('NumberFormatter')) {
    $fmt = new NumberFormatter('tr_TR', NumberFormatter::CURRENCY);
    return $fmt->formatCurrency($price, $currency);
  }
  return number_format($price, 2, ',', '.') . ' ' . $currency;
}

$errors = [];
$error = null;
$success = null;
$vatAllowed = [0, 1, 8, 18, 20];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$unitTypeOptions = ['kg/m', 'm', 'm²', 'adet', 'set'];
$unitValueOneTypes = ['m', 'm²', 'adet', 'set'];

// Fetch categories from database
$categoryStmt = $pdo->query('SELECT id, name FROM categories ORDER BY name');
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
$categoryMap = [];
foreach ($categories as $cat) {
  $categoryMap[(int)$cat['id']] = $cat['name'];
}
$validCategoryIds = array_keys($categoryMap);

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
    $category_id = (int)($_POST['category_id'] ?? 0);
    $unit_type = trim($_POST['unit_type'] ?? '');
    $unit_value = (float)($_POST['unit_value'] ?? 0);
    $channel_count = $_POST['channel_count'] ?? null;
    $categoryName = $categoryMap[$category_id] ?? '';
    $isKumanda = mb_strtolower($categoryName, 'UTF-8') === 'kumanda';
    if ($isKumanda) {
      if (!in_array((int)$channel_count, [5, 10, 15], true)) {
        $errors[] = 'Kumanda kategorisi için Kanal Adedi alanı zorunludur ve 5, 10 veya 15 olmalıdır.';
      } else {
        $channel_count = (int)$channel_count;
      }
      $unit_value = null;
    } else {
      $channel_count = null;
      if (in_array($unit_type, $unitValueOneTypes, true)) {
        $unit_value = 1;
      }
      if ($unit_value <= 0) {
        $errors[] = 'Birim değeri > 0 olmalıdır.';
      }
    }

    if ($category_id === 0 || !in_array($category_id, $validCategoryIds, true)) {
      $errors[] = 'Kategori seçilmelidir.';
    }
    if ($unit_type === '' || !in_array($unit_type, $unitTypeOptions, true)) {
      $errors[] = 'Birim türü seçilmelidir.';
    }

    $color = trim($_POST['color'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit_price = trim($_POST['unit_price'] ?? '');
    $price_unit = strtoupper(trim($_POST['price_unit'] ?? 'TRY'));
    $vat_rate = trim($_POST['vat_rate'] ?? '');
    $allowedPriceUnits = ['TRY', 'USD', 'EUR'];
    if (!in_array($price_unit, $allowedPriceUnits, true)) {
      $price_unit = 'TRY';
    }
    if ($vat_rate === '') {
      $vat_rate = null;
    } elseif (!in_array((int)$vat_rate, $vatAllowed, true)) {
      $errors[] = 'KDV oranı sadece %0, %1, %8, %18 veya %20 olabilir.';
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

    $stmt = $pdo->prepare('SELECT image_url FROM products WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
      $errors[] = 'Ürün bulunamadı.';
    }

    $imageUrl = $current['image_url'] ?? null;

    if (!empty($_FILES['product_image']['tmp_name'])) {
      if ($_FILES['product_image']['size'] > 5 * 1024 * 1024) {
        $errors[] = 'Görsel 5MB\'yi aşamaz.';
      } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['product_image']['tmp_name']);
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
          $errors[] = 'Yalnızca JPG, PNG, GIF veya WebP dosyaları kabul edilir.';
        } else {
          $uploadDir = __DIR__ . '/uploads/';
          if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
          }
          $ext = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
          $filename = uniqid('prod_', true) . '.' . strtolower($ext);
          $targetPath = $uploadDir . $filename;
          if (!move_uploaded_file($_FILES['product_image']['tmp_name'], $targetPath)) {
            $errors[] = 'Görsel kaydedilemedi.';
          } else {
            if ($imageUrl && file_exists(__DIR__ . '/' . $imageUrl)) {
              @unlink(__DIR__ . '/' . $imageUrl);
            }
            $imageUrl = 'uploads/' . $filename;
          }
        }
      }
    }

    if (!$errors) {
      try {
        $sql = 'UPDATE products SET product_code=:product_code, name=:name, category_id=:category_id, unit=:unit_type, channel_count=:channel_count, weight_per_meter=:unit_value, color=:color, description=:description, price_unit=:price_unit, unit_price=:unit_price, vat_rate=:vat_rate, image_url=:image_url WHERE id=:id';
        $stmt = $pdo->prepare($sql);
        $params = [
          ':product_code' => $product_code ?: null,
          ':name' => $name,
          ':category_id' => $category_id ?: null,
          ':unit_type' => $unit_type ?: null,
          ':channel_count' => $channel_count,
          ':unit_value' => $unit_value,
          ':color' => $color ?: null,
          ':description' => $description ?: null,
          ':price_unit' => $price_unit,
          ':unit_price' => $unit_price,
          ':vat_rate' => $vat_rate !== '' ? $vat_rate : null,
          ':image_url' => $imageUrl,
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
    $category_id = (int)($_POST['category_id'] ?? 0);
    $unit_type = trim($_POST['unit_type'] ?? '');
    $unit_value = (float)($_POST['unit_value'] ?? 0);
    $channel_count = $_POST['channel_count'] ?? null;
    $categoryName = $categoryMap[$category_id] ?? '';
    $isKumanda = mb_strtolower($categoryName, 'UTF-8') === 'kumanda';
    if ($isKumanda) {
      if (!in_array((int)$channel_count, [5, 10, 15], true)) {
        $errors[] = 'Kumanda kategorisi için Kanal Adedi alanı zorunludur ve 5, 10 veya 15 olmalıdır.';
      } else {
        $channel_count = (int)$channel_count;
      }
      $unit_value = null;
    } else {
      $channel_count = null;
      if (in_array($unit_type, $unitValueOneTypes, true)) {
        $unit_value = 1;
      }
      if ($unit_value <= 0) {
        $errors[] = 'Birim değeri > 0 olmalıdır.';
      }
    }
    $color = trim($_POST['color'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit_price = trim($_POST['unit_price'] ?? '');
    $price_unit = strtoupper(trim($_POST['price_unit'] ?? 'TRY'));
    $vat_rate = trim($_POST['vat_rate'] ?? '');
    $allowedPriceUnits = ['TRY', 'USD', 'EUR'];
    if (!in_array($price_unit, $allowedPriceUnits, true)) {
      $price_unit = 'TRY';
    }

    if ($category_id === 0 || !in_array($category_id, $validCategoryIds, true)) {
      $errors[] = 'Kategori seçilmelidir.';
    }
    if ($unit_type === '' || !in_array($unit_type, $unitTypeOptions, true)) {
      $errors[] = 'Birim türü seçilmelidir.';
    }
    if ($vat_rate === '') {
      $vat_rate = null;
    } elseif (!in_array((int)$vat_rate, $vatAllowed, true)) {
      $errors[] = 'KDV oranı sadece %0, %1, %8, %18 veya %20 olabilir.';
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

    $imageUrl = null;
    if (!empty($_FILES['product_image']['tmp_name'])) {
      if ($_FILES['product_image']['size'] > 5 * 1024 * 1024) {
        $errors[] = 'Görsel 5MB\'yi aşamaz.';
      } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['product_image']['tmp_name']);
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
          $errors[] = 'Yalnızca JPG, PNG, GIF veya WebP dosyaları kabul edilir.';
        } else {
          $uploadDir = __DIR__ . '/uploads/';
          if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
          }
          $ext = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
          $filename = uniqid('prod_', true) . '.' . strtolower($ext);
          $targetPath = $uploadDir . $filename;
          if (!move_uploaded_file($_FILES['product_image']['tmp_name'], $targetPath)) {
            $errors[] = 'Görsel kaydedilemedi.';
          } else {
            $imageUrl = 'uploads/' . $filename;
          }
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
        $cols = 'product_code, name, category_id, unit, channel_count, weight_per_meter, color, description, price_unit, unit_price, vat_rate, image_url';
        $vals = ':product_code, :name, :category_id, :unit_type, :channel_count, :unit_value, :color, :description, :price_unit, :unit_price, :vat_rate, :image_url';
        $stmt = $pdo->prepare("INSERT INTO products ($cols) VALUES ($vals)");
        $params = [
          ':product_code' => $product_code,
          ':name' => $name,
          ':category_id' => $category_id ?: null,
          ':unit_type' => $unit_type ?: null,
          ':channel_count' => $channel_count,
          ':unit_value' => $unit_value,
          ':color' => $color ?: null,
          ':description' => $description ?: null,
          ':price_unit' => $price_unit,
          ':unit_price' => $unit_price,
          ':vat_rate' => $vat_rate !== '' ? $vat_rate : null,
          ':image_url' => $imageUrl,
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

$fields = 'p.id, p.product_code, p.name, p.category_id, c.name AS category, p.unit AS unit_type, p.channel_count, p.weight_per_meter AS unit_value, p.color, p.image_url, p.description, p.price_unit, p.unit_price, p.vat_rate';
if ($hasDimensions) {
  $fields .= ', p.width, p.height';
}
$stmt = $pdo->query("SELECT $fields FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$productCount = count($products);
require __DIR__ . '/header.php';
?>
<style>
  .image-upload-area {
    border: 2px dashed #ced4da;
    border-radius: 0.5rem;
    padding: 1rem;
    text-align: center;
    cursor: pointer;
    transition: background-color 0.2s ease-in-out;
  }

  .image-upload-area:hover,
  .image-upload-area.dragover {
    background-color: #f8f9fa;
  }

  .image-upload-area .image-input {
    display: none;
  }

  .image-preview img {
    max-width: 100%;
    height: auto;
    display: block;
    margin: 0 auto;
  }

  .default-image {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #6c757d;
  }

  .upload-instructions {
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: #6c757d;
  }
</style>
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

  <p class="text-muted mb-3">Toplam <?= e($productCount) ?> ürün gösteriliyor.</p>

  <?php if (!$products): ?>
    <div class="alert alert-warning text-warning bg-warning bg-opacity-10 border-0" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>Henüz ürün bulunmamaktadır.
    </div>
  <?php else: ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-3">
      <?php foreach ($products as $p): ?>
        <div class="col">
          <div class="card h-100">
            <?php if (!empty($p['image_url'])): ?>
              <img src="<?= e($p['image_url']) ?>" class="card-img-top" alt="<?= e($p['name']) ?>">
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
                <?= e(formatPrice((float)$p['unit_price'], $p['price_unit'] ?? 'TRY')) ?>
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
                          <div class="col-md-12">
                            <div class="form-group">
                              <label for="product_image-<?= $p['id'] ?>" class="form-label">
                                <i class="fas fa-image"></i>
                                Ürün Görseli
                              </label>
                              <div class="image-upload-area" id="imageUploadArea-<?= $p['id'] ?>">
                                <div class="image-preview" id="imagePreview-<?= $p['id'] ?>">
                                  <?php if (!empty($p['image_url'])): ?>
                                    <img src="<?= e($p['image_url']) ?>" alt="Preview">
                                  <?php else: ?>
                                    <div class="default-image">
                                      <i class="fas fa-box"></i>
                                      <span>Görsel Yükle</span>
                                    </div>
                                  <?php endif; ?>
                                </div>
                                <input type="file" id="product_image-<?= $p['id'] ?>" name="product_image" accept="image/*" class="image-input">
                                <div class="upload-instructions">
                                  <p>JPG, PNG, GIF or WebP format</p>
                                  <p>Maximum 5MB</p>
                                </div>
                              </div>
                            </div>
                          </div>
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
                            <select name="category_id" id="category-<?= $p['id'] ?>" class="form-select category-select" required>
                              <option value="">Seçiniz</option>
                              <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat['id']) ?>" <?= ($p['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          <div class="col-md-6 channel-count-group">
                            <label class="form-label" for="channel_count-<?= $p['id'] ?>">Kanal Adedi</label>
                            <select id="channel_count-<?= $p['id'] ?>" name="channel_count" class="form-select channel-count-field">
                              <option value="">Seçiniz</option>
                              <?php foreach ([5, 10, 15] as $n): ?>
                                <option value="<?= $n ?>" <?= ((int)($p['channel_count'] ?? 0) === $n) ? 'selected' : '' ?>><?= $n ?></option>
                              <?php endforeach; ?>
                            </select>
                            <div class="form-text">Only required for remote (‘kumanda’) products.</div>
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
                          <div class="col-md-6 unit-value-group">
                            <label class="form-label">Birim Değeri *</label>
                            <input type="number" step="0.001" min="0.001" name="unit_value" class="form-control unit-value-field" required value="<?= e($p['unit_value']) ?>">
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Renk</label>
                            <input type="text" name="color" class="form-control" value="<?= e($p['color']) ?>">
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Fiyat Birimi ve Fiyatı *</label>
                            <div class="input-group">
                              <select name="price_unit" class="form-select">
                                <option value="TRY" <?= ($p['price_unit'] === 'TRY') ? 'selected' : '' ?>>TL</option>
                                <option value="USD" <?= ($p['price_unit'] === 'USD') ? 'selected' : '' ?>>USD</option>
                                <option value="EUR" <?= ($p['price_unit'] === 'EUR') ? 'selected' : '' ?>>EUR</option>
                              </select>
                              <input type="number" step="0.01" name="unit_price" class="form-control" required value="<?= e($p['unit_price']) ?>">
                            </div>
                          </div>
                          <?php
                          $allowedVat = [0, 1, 8, 18, 20];
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
                <div class="col-md-12">
                  <div class="form-group">
                    <label for="product_image-create" class="form-label">
                      <i class="fas fa-image"></i>
                      Ürün Görseli
                    </label>
                    <div class="image-upload-area" id="imageUploadArea-create">
                      <div class="image-preview" id="imagePreview-create">
                        <div class="default-image">
                          <i class="fas fa-box"></i>
                          <span>Görsel Yükle</span>
                        </div>
                      </div>
                      <input type="file" id="product_image-create" name="product_image" accept="image/*" class="image-input">
                      <div class="upload-instructions">
                        <p>JPG, PNG, GIF or WebP format</p>
                        <p>Maximum 5MB</p>
                      </div>
                    </div>
                  </div>
                </div>
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
                  <select name="category_id" id="category-create" class="form-select category-select" required>
                    <option value="">Seçiniz</option>
                    <?php foreach ($categories as $cat): ?>
                      <option value="<?= e($cat['id']) ?>" <?= (isset($_POST['category_id']) && (int)$_POST['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6 channel-count-group">
                  <label class="form-label" for="channel_count-create">Kanal Adedi</label>
                  <select id="channel_count-create" name="channel_count" class="form-select channel-count-field">
                    <option value="">Seçiniz</option>
                    <?php foreach ([5, 10, 15] as $n): ?>
                      <option value="<?= $n ?>" <?= (isset($_POST['channel_count']) && (int)$_POST['channel_count'] === $n) ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text">Only required for remote (‘kumanda’) products.</div>
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
                <div class="col-md-6 unit-value-group">
                  <label class="form-label">Birim Değeri *</label>
                  <input type="number" step="0.001" min="0.001" name="unit_value" class="form-control" required value="<?= e($_POST['unit_value'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Renk</label>
                  <input type="text" name="color" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Fiyat Birimi ve Fiyatı *</label>
                  <div class="input-group">
                    <select name="price_unit" class="form-select">
                      <option value="TRY" <?= (($_POST['price_unit'] ?? 'TRY') === 'TRY') ? 'selected' : '' ?>>TL</option>
                      <option value="USD" <?= (($_POST['price_unit'] ?? 'TRY') === 'USD') ? 'selected' : '' ?>>USD</option>
                      <option value="EUR" <?= (($_POST['price_unit'] ?? 'TRY') === 'EUR') ? 'selected' : '' ?>>EUR</option>
                    </select>
                    <input type="number" step="0.01" name="unit_price" class="form-control" required>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">KDV Oranı</label>
                  <select name="vat_rate" class="form-select">
                    <option value="">Seçiniz</option>
                    <?php foreach ([0, 1, 8, 18, 20] as $vr): ?>
                      <option value="<?= $vr ?>" <?= (isset($_POST['vat_rate']) && $_POST['vat_rate'] === (string)$vr) ? 'selected' : '' ?>>%<?= $vr ?></option>
                    <?php endforeach; ?>
                  </select>

                </div>
                <div class="col-12">
                  <label class="form-label">Açıklama</label>
                  <textarea name="description" class="form-control" rows="3" style="width: 100%;
            min-height: 100px;
            max-height: 300px;
            padding: 10px;
            font-size: 14px;
            line-height: 1.5;
            resize: none;
            overflow-y: auto;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 6px;"></textarea>
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
  document.querySelectorAll('.image-upload-area').forEach(area => {
    const fileInput = area.querySelector('.image-input');
    const preview = area.querySelector('.image-preview');
    const defaultHtml = preview.innerHTML;

    const resetPreview = () => {
      preview.innerHTML = defaultHtml;
    };

    const showImage = file => {
      const reader = new FileReader();
      reader.onload = e => {
        preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
      };
      reader.readAsDataURL(file);
    };

    area.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', () => {
      const file = fileInput.files[0];
      if (file) {
        showImage(file);
      } else {
        resetPreview();
      }
    });

    area.addEventListener('dragover', e => {
      e.preventDefault();
      area.classList.add('dragover');
    });

    area.addEventListener('dragleave', e => {
      e.preventDefault();
      area.classList.remove('dragover');
    });

    area.addEventListener('drop', e => {
      e.preventDefault();
      area.classList.remove('dragover');
      if (e.dataTransfer.files && e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        fileInput.dispatchEvent(new Event('change'));
      }
    });
  });

  document.querySelectorAll('select[name="unit_type"]').forEach(select => {
    const unitGroup = select.closest('form')?.querySelector('.unit-value-group');
    const unitInput = unitGroup?.querySelector('input[name="unit_value"]');
    const readOnlyTypes = ['m', 'm²', 'adet', 'set'];

    const toggleUnitValue = () => {
      const type = select.value;
      const show = type === 'kg/m' || readOnlyTypes.includes(type);
      if (unitGroup) unitGroup.style.display = show ? '' : 'none';
      if (unitInput) {
        unitInput.required = type === 'kg/m';
        if (readOnlyTypes.includes(type)) {
          unitInput.readOnly = true;
          unitInput.value = '1';
        } else {
          unitInput.readOnly = false;
          if (type !== 'kg/m') {
            unitInput.value = '';
          }
        }
      }
    };

    toggleUnitValue();
    select.addEventListener('change', toggleUnitValue);
  });
</script>
<?php require __DIR__ . '/footer.php'; ?>