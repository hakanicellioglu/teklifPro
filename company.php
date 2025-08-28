<?php declare(strict_types=1);
// When embedded inside settings.php the header and footer are already loaded.
if (!defined('SETTINGS_COMPANY_EMBED')) {
    require __DIR__ . '/header.php';
}
require_once __DIR__ . '/components/page_header.php';

// Ensure CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$errors = [];
$success = '';

$userId = (int)($_SESSION['user_id'] ?? 0);

// Fetch existing company record
$company = [
    'id' => null,
    'name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'logo' => ''
];

try {
    $stmt = $pdo->prepare('SELECT id, name, email, phone, address, logo FROM company WHERE user_id = :uid LIMIT 1');
    $stmt->execute([':uid' => $userId]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $company = array_merge($company, $row);
    }
} catch (Exception $e) {
    // Ignore fetch errors; defaults used
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_form'])) {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Geçersiz CSRF belirteci.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $logoPath = $company['logo'] ?? '';

    if ($name === '') {
        $errors['name'] = 'Şirket adı gerekli.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Geçersiz e-posta.';
    }
    if ($phone !== '' && !preg_match('/^[0-9 +\-]{6,}$/', $phone)) {
        $errors['phone'] = 'Geçersiz telefon.';
    }

    if (!empty($_FILES['logo']['tmp_name'])) {
        $file = $_FILES['logo'];
        if (is_uploaded_file($file['tmp_name'])) {
            $info = @getimagesize($file['tmp_name']);
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif'];
            if (!$info || !isset($allowed[$info['mime']])) {
                $errors['logo'] = 'Geçersiz logo dosyası.';
            } else {
                $ext = $allowed[$info['mime']];
                $newName = 'logo_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $dest = __DIR__ . '/assets/' . $newName;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    $errors['logo'] = 'Logo yüklenemedi.';
                } else {
                    if ($logoPath && file_exists(__DIR__ . '/assets/' . $logoPath)) {
                        @unlink(__DIR__ . '/assets/' . $logoPath);
                    }
                    $logoPath = $newName;
                }
            }
        } else {
            $errors['logo'] = 'Dosya yüklemesi başarısız.';
        }
    }

    if (!$errors) {
        try {
            if ($company['id']) {
                $stmt = $pdo->prepare('UPDATE company SET name = :name, email = :email, phone = :phone, address = :address, logo = :logo WHERE id = :id AND user_id = :uid');
                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'logo' => $logoPath,
                    'id' => $company['id'],
                    'uid' => $userId
                ]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO company (name, email, phone, address, logo, user_id) VALUES (:name, :email, :phone, :address, :logo, :uid)');
                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'logo' => $logoPath,
                    'uid' => $userId
                ]);
                $company['id'] = (int)$pdo->lastInsertId();
            }
            $company['name'] = $name;
            $company['email'] = $email;
            $company['phone'] = $phone;
            $company['address'] = $address;
            $company['logo'] = $logoPath;
            $success = 'Şirket bilgileri güncellendi.';
        } catch (Exception $e) {
            error_log('Company save failed [' . $e->getCode() . ']: ' . $e->getMessage());
            $errors['general'] = 'Şirket bilgileri kaydedilemedi. Lütfen daha sonra tekrar deneyin.';
        }
    }
}
?>
<?php page_header('Şirket Ayarları'); ?>
<?php if (!empty($errors['general'])): ?>
<div class="alert alert-danger" role="alert"><?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success" role="alert"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="mb-5">
  <input type="hidden" name="company_form" value="1">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
  <div class="mb-3">
    <label for="name" class="form-label">Şirket Adı</label>
    <input type="text" class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="name" name="name" value="<?= htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
    <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
  </div>
  <div class="mb-3">
    <label for="email" class="form-label">E-posta</label>
    <input type="email" class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="email" name="email" value="<?= htmlspecialchars($company['email'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
  </div>
  <div class="mb-3">
    <label for="phone" class="form-label">Telefon</label>
    <input type="text" class="form-control<?= isset($errors['phone']) ? ' is-invalid' : '' ?>" id="phone" name="phone" value="<?= htmlspecialchars($company['phone'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['phone'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
  </div>
  <div class="mb-3">
    <label for="address" class="form-label">Adres</label>
    <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($company['address'], ENT_QUOTES, 'UTF-8'); ?></textarea>
  </div>
  <div class="mb-3">
    <label for="logo" class="form-label">Logo</label>
    <?php if (!empty($company['logo']) && file_exists(__DIR__ . '/assets/' . $company['logo'])): ?>
      <div class="mb-2"><img src="assets/<?= htmlspecialchars($company['logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" style="max-height:100px"></div>
    <?php endif; ?>
    <input type="file" class="form-control<?= isset($errors['logo']) ? ' is-invalid' : '' ?>" id="logo" name="logo" accept="image/*">
    <?php if (isset($errors['logo'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['logo'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
  </div>
  <button type="submit" class="btn btn-primary">Kaydet</button>
</form>
<?php if (!defined('SETTINGS_COMPANY_EMBED')) { require __DIR__ . '/footer.php'; } ?>
