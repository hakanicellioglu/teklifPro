<?php
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

$assemblyTypes = [
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

$statusLabels = [
    'draft'     => 'Taslak (müşteriye gitmedi)',
    'sent'      => 'Müşteriye gönderildi',
    'accepted'  => 'Müşteri onayladı',
    'rejected'  => 'Müşteri reddetti',
    'expired'   => 'Geçerlilik tarihi geçti',
    'cancelled' => 'Siz iptal ettiniz (revize edilmeyecek)',
];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Teklif bulunamadı.</div></div></body></html>';
    exit;
}

$error = null;
$success = null;

if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete' && $role === 'admin') {
    $token = $_POST['csrf_token'] ?? '';
    $postId = (int)($_POST['id'] ?? 0);
    if (!hash_equals($csrfToken, $token) || $postId !== $id) {
        $error = 'Geçersiz CSRF tokenı.';
    } else {
        try {
            $delStmt = $pdo->prepare('DELETE FROM generaloffers WHERE id = :id');
            $delStmt->execute([':id' => $id]);
            header('Location: quotations.php');
            exit;
        } catch (Exception $e) {
            $error = 'Teklif silinemedi.';
        }
    }
}

// Handle status update
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'update_status' &&
    $role === 'admin'
) {
    $token    = $_POST['csrf_token'] ?? '';
    $postId   = (int)($_POST['id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    if (!hash_equals($csrfToken, $token) || $postId !== $id || !array_key_exists($newStatus, $statusLabels)) {
        $_SESSION['flash_error'] = 'Geçersiz durum seçimi.';
    } else {
        try {
            $upd = $pdo->prepare('UPDATE generaloffers SET status = :status WHERE id = :id');
            $upd->execute([':status' => $newStatus, ':id' => $id]);
            $_SESSION['flash_success'] = 'Durum güncellendi.';
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Durum güncellenemedi.';
        }
    }
    header('Location: quotation_view.php?id=' . $id);
    exit;
}

// Handle per-guillotine optimize action
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'optimize_guillotine' &&
    $role === 'admin'
) {
    $token = $_POST['csrf_token'] ?? '';
    $gId   = filter_input(INPUT_POST, 'guillotine_id', FILTER_VALIDATE_INT);
    if (!hash_equals($csrfToken, $token) || !$gId) {
        $_SESSION['flash_error'] = 'Geçersiz CSRF tokenı.';
        header('Location: quotation_view.php?id=' . $id);
        exit;
    } else {
        $rules = require __DIR__ . '/rules.php';
        try {
            $pdo->beginTransaction();
            $pStmt = $pdo->prepare('SELECT p.unit_price, p.vat_rate, p.weight_per_meter, c.unit_type FROM products p LEFT JOIN categories c ON p.category = c.id WHERE LOWER(p.name) = LOWER(:name)');

            $gFetch = $pdo->prepare('SELECT * FROM guillotinesystems WHERE id = :gid AND general_offer_id = :goid');
            $gFetch->execute([':gid' => $gId, ':goid' => $id]);
            if ($row = $gFetch->fetch(PDO::FETCH_ASSOC)) {
                $width  = (float)$row['width'];
                $height = (float)$row['height'];
                $qty    = (int)$row['quantity'];
                $remote = $row['remote_quantity'] !== null ? (int)$row['remote_quantity'] : 0;
                if ($width <= 0 || $height <= 0 || $qty <= 0 || $remote < 0) {
                    throw new Exception('Geçersiz giyotin satırı.');
                }

                $base = 0.0;
                foreach ($rules['guillotine'] ?? [] as $rule) {
                    if (!is_callable($rule['match']) || !$rule['match']($row)) {
                        continue;
                    }
                    foreach ($rule['products'] as $prod) {
                        $calcQty = (float)$prod['qty']($row);
                        if ($calcQty <= 0) {
                            continue;
                        }
                        $pStmt->execute([':name' => $prod['name']]);
                        if ($p = $pStmt->fetch(PDO::FETCH_ASSOC)) {
                            $unit = (float)$p['unit_price'];
                            $vat  = (float)$p['vat_rate'];
                            $unitType = $p['unit_type'];
                            if ($unitType === 'kg/m') {
                                $weight = (float)$p['weight_per_meter'];
                                $base += $calcQty * $weight * $unit * (1 + $vat / 100);
                            } else {
                                $base += $calcQty * $unit * (1 + $vat / 100);
                            }
                        }
                    }
                }

                $rate = (float)($row['profit_rate'] ?? $row['profit_margin'] ?? 0);
                $profitAmount = $base * ($rate / 100);
                $totalAmount  = $base + $profitAmount;
                $gUpd = $pdo->prepare('UPDATE guillotinesystems SET profit_amount=:pamount, total_amount=:tamount WHERE id=:id');
                $gUpd->execute([
                    ':pamount' => $profitAmount,
                    ':tamount' => $totalAmount,
                    ':id'      => $gId,
                ]);

                // Recalculate overall totals
                $gSumStmt = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) FROM guillotinesystems WHERE general_offer_id = :id');
                $gSumStmt->execute([':id' => $id]);
                $gSum = (float)$gSumStmt->fetchColumn();
                $sSumStmt = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) FROM slidingsystems WHERE general_offer_id = :id');
                $sSumStmt->execute([':id' => $id]);
                $sSum = (float)$sSumStmt->fetchColumn();
                $overall = $gSum + $sSum;
                $upd = $pdo->prepare('UPDATE generaloffers SET total_amount = :t WHERE id = :id');
                $upd->execute([':t' => $overall, ':id' => $id]);

                $pdo->commit();
                $_SESSION['flash_success'] = 'Giyotin optimize edildi.';
                header('Location: optimizasyon.php?id=' . $id . '&gid=' . $gId);
                exit;
            } else {
                throw new Exception('Giyotin satırı bulunamadı.');
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash_error'] = 'Optimize işleminde hata oluştu: ' . $e->getMessage();
            header('Location: quotation_view.php?id=' . $id);
            exit;
        }
    }
}

try {
    $stmt = $pdo->prepare('
        SELECT g.*, c.first_name, c.last_name, c.company AS customer_company, co.name AS company_name
        FROM generaloffers g
        JOIN customers c ON g.customer_id = c.id
        LEFT JOIN company co ON g.company_id = co.id
        WHERE g.id = :id
    ');
    $stmt->execute([':id' => $id]);
    $offer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$offer) {
        echo '<div class="container mt-4"><div class="alert alert-danger">Teklif bulunamadı.</div></div></body></html>';
        exit;
    }
} catch (Exception $e) {
    $error = 'Teklif verileri alınamadı.';
    $offer = null;
}

if (!$offer) {
    echo '<div class="container mt-4"><div class="alert alert-danger">' . e($error) . '</div></div></body></html>';
    exit;
}

$gDel = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_guillotine' && $role === 'admin');
if ($gDel) {
    $token = $_POST['csrf_token'] ?? '';
    $gId = filter_input(INPUT_POST, 'guillotine_id', FILTER_VALIDATE_INT);
    if (!hash_equals($csrfToken, $token) || !$gId) {
        $_SESSION['flash_error'] = 'Geçersiz CSRF tokenı.';
    } else {
        try {
            $del = $pdo->prepare('DELETE FROM guillotinesystems WHERE id = :gid AND general_offer_id = :goid');
            $del->execute([':gid' => $gId, ':goid' => $id]);
            if ($del->rowCount()) {
                $gSumStmt = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) FROM guillotinesystems WHERE general_offer_id = :id');
                $gSumStmt->execute([':id' => $id]);
                $gSum = (float)$gSumStmt->fetchColumn();
                $sSumStmt = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) FROM slidingsystems WHERE general_offer_id = :id');
                $sSumStmt->execute([':id' => $id]);
                $sSum = (float)$sSumStmt->fetchColumn();
                $overall = $gSum + $sSum;
                $upd = $pdo->prepare('UPDATE generaloffers SET total_amount = :total WHERE id = :id');
                $upd->execute([':total' => $overall, ':id' => $id]);
                $_SESSION['flash_success'] = 'Giyotin sistemi silindi.';
            } else {
                $_SESSION['flash_error'] = 'Giyotin sistemi silinemedi.';
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Giyotin sistemi silinemedi.';
        }
    }
    header('Location: quotation_view.php?id=' . $id);
    exit;
}

$gPost = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'guillotine');
if ($gPost) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, $token)) {
        $error = 'Geçersiz CSRF tokenı.';
    } else {
        try {
            try {
                $pdo->query('SELECT profit_margin FROM guillotinesystems LIMIT 1');
            } catch (Exception $e) {
                $pdo->exec('ALTER TABLE guillotinesystems ADD COLUMN profit_margin DECIMAL(5,2) DEFAULT NULL AFTER glass_color');
            }

            $gId = filter_input(INPUT_POST, 'guillotine_id', FILTER_VALIDATE_INT);
            $width = filter_input(INPUT_POST, 'width', FILTER_VALIDATE_FLOAT);
            $height = filter_input(INPUT_POST, 'height', FILTER_VALIDATE_FLOAT);
            $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
            $motor = $_POST['motor_system'] ?? null;
            $glassType = $_POST['glass_type'] ?? null;
            $glassColor = $_POST['glass_color'] ?? null;
            $remoteQty = filter_input(INPUT_POST, 'remote_quantity', FILTER_VALIDATE_INT);
            $ralCode = trim($_POST['ral_code'] ?? '');
            $profitMargin = filter_input(INPUT_POST, 'profit_margin', FILTER_VALIDATE_FLOAT);

            $validNumbers = $width !== false && $width > 0
                && $height !== false && $height > 0
                && $quantity !== false && $quantity > 0
                && $profitMargin !== false && $profitMargin > 0
                && ($remoteQty === null || ($remoteQty !== false && $remoteQty > 0));

            if (!$validNumbers) {
                $error = 'Tüm sayısal alanlar pozitif olmalıdır.';
            } else {
                if ($gId) {
                    $sql = 'UPDATE guillotinesystems SET width=:width, height=:height, quantity=:quantity, motor_system=:motor, remote_quantity=:remote, ral_code=:ral, glass_type=:glass_type, glass_color=:glass_color, profit_margin=:profit_margin WHERE id=:id AND general_offer_id=:goid';
                    $params = [
                        ':width' => $width,
                        ':height' => $height,
                        ':quantity' => $quantity,
                        ':motor' => $motor,
                        ':remote' => $remoteQty,
                        ':ral' => $ralCode,
                        ':glass_type' => $glassType,
                        ':glass_color' => $glassColor,
                        ':profit_margin' => $profitMargin,
                        ':id' => $gId,
                        ':goid' => $id,
                    ];
                } else {
                    $sql = 'INSERT INTO guillotinesystems (general_offer_id, system_type, width, height, quantity, motor_system, remote_quantity, ral_code, glass_type, glass_color, profit_margin) VALUES (:goid, :stype, :width, :height, :quantity, :motor, :remote, :ral, :glass_type, :glass_color, :profit_margin)';
                    $params = [
                        ':goid' => $id,
                        ':stype' => 'Guillotine',
                        ':width' => $width,
                        ':height' => $height,
                        ':quantity' => $quantity,
                        ':motor' => $motor,
                        ':remote' => $remoteQty,
                        ':ral' => $ralCode,
                        ':glass_type' => $glassType,
                        ':glass_color' => $glassColor,
                        ':profit_margin' => $profitMargin,
                    ];
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $gSumStmt = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) FROM guillotinesystems WHERE general_offer_id = :id');
                $gSumStmt->execute([':id' => $id]);
                $gSum = (float)$gSumStmt->fetchColumn();
                $sSumStmt = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) FROM slidingsystems WHERE general_offer_id = :id');
                $sSumStmt->execute([':id' => $id]);
                $sSum = (float)$sSumStmt->fetchColumn();
                $overall = $gSum + $sSum;
                $updStmt = $pdo->prepare('UPDATE generaloffers SET total_amount = :total WHERE id = :id');
                $updStmt->execute([':total' => $overall, ':id' => $id]);
                $success = $gId ? 'Giyotin sistemi güncellendi.' : 'Giyotin sistemi eklendi.';
            }
        } catch (Exception $e) {
            $error = 'Giyotin sistemi kaydedilemedi.';
        }
    }
}

$guillotines = [];
$slidings = [];
if (!$error) {
    try {
        $gStmt = $pdo->prepare('SELECT id, system_type, width, height, quantity, motor_system, remote_quantity, ral_code, glass_type, glass_color, profit_margin, total_amount FROM guillotinesystems WHERE general_offer_id = :id');
        $gStmt->execute([':id' => $id]);
        $guillotines = $gStmt->fetchAll();
    } catch (Exception $e) {
        $error = 'Giyotin sistemi verileri alınamadı.';
    }
}
if (!$error) {
    try {
        $sStmt = $pdo->prepare('SELECT system_type, width, height, quantity, wing_type, ral_code, lock_type, glass_type, glass_color, total_amount FROM slidingsystems WHERE general_offer_id = :id');
        $sStmt->execute([':id' => $id]);
        $slidings = $sStmt->fetchAll();
    } catch (Exception $e) {
        $error = 'Sürme sistemi verileri alınamadı.';
    }
}

$totalAmount = 0;
foreach ($guillotines as $g) {
    $totalAmount += (float)$g['total_amount'];
}
foreach ($slidings as $s) {
    $totalAmount += (float)$s['total_amount'];
}
$subtotalCalc = round($totalAmount / 1.2, 2);
$vatAmountCalc = round($totalAmount - $subtotalCalc, 2);
$totalFormatted = tr_money($totalAmount) . ' ₺';
$assemblyLabel = $assemblyTypes[$offer['assembly_type']] ?? 'Bilinmiyor';

?>
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="quotations.php">Teklifler</a></li>
    <li class="breadcrumb-item active" aria-current="page">#<?= e((string)$offer['id']) ?></li>
  </ol>
</nav>
<?php
$actions = '<a href="quotation_edit.php?id=' . e((string)$offer['id']) . '" class="btn btn-primary btn-icon"><i class="bi bi-pencil"></i>Düzenle</a>';
$actions .= ' <a href="pdf/render_quotation_pdf.php?id=' . e((string)$offer['id']) . '" class="btn btn-secondary btn-icon"><i class="bi bi-file-earmark-pdf"></i>PDF İndir</a>';
page_header('Teklif #' . e((string)$offer['id']), $actions);
?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Özet</h5>
        <?php if ($role === 'admin'): ?>
            <form method="post" class="d-inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= e((string)$offer['id']) ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Bu teklifi silmek istediğinize emin misiniz?">Sil</button>
            </form>
        <?php endif; ?>
    </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-2"><strong>Müşteri:</strong> <?= e(trim($offer['first_name'] . ' ' . $offer['last_name'])) ?></div>
                    <div class="mb-2"><strong>Teklif Tarihi:</strong> <?= e(date('d.m.Y', strtotime($offer['offer_date']))) ?></div>
                    <?php if (!empty($offer['payment_method'])): ?>
                        <div class="mb-2"><strong>Ödeme:</strong> <?= e($paymentLabels[$offer['payment_method']] ?? $offer['payment_method']) ?></div>
                    <?php endif; ?>
                    <div class="mb-2">
                        <?php if ($role === 'admin'): ?>
                            <form method="post" class="d-flex align-items-center gap-2">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="id" value="<?= e((string)$offer['id']) ?>">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <label class="form-label mb-0"><strong>Durum:</strong></label>
                                <select name="status" class="form-select form-select-sm w-auto">
                                    <?php foreach ($statusLabels as $code => $label): ?>
                                        <option value="<?= e($code) ?>" <?= $offer['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary">Kaydet</button>
                            </form>
                        <?php else: ?>
                            <strong>Durum:</strong> <?= e($statusLabels[$offer['status']] ?? $offer['status']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <?php if (!empty($offer['company_name']) || !empty($offer['customer_company'])): ?>
                        <div class="mb-2"><strong>Firma:</strong> <?= e($offer['company_name'] ?? $offer['customer_company']) ?></div>
                    <?php endif; ?>
                    <div class="mb-2"><strong>Montaj Tipi:</strong> <?= e($assemblyLabel) ?></div>
                    <?php if (!empty($offer['validity_days'])): ?>
                        <div class="mb-2"><strong>Geçerlilik:</strong> <?= (int)$offer['validity_days'] ?> gün</div>
                    <?php endif; ?>
                    <?php if (!empty($offer['installment_term'])): ?>
                        <div class="mb-2"><strong>Vade:</strong> <?= e($offer['installment_term']) ?></div>
                    <?php endif; ?>
                    <div class="mb-2"><strong>Toplam Tutar:</strong> <?= e($totalFormatted) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Giyotin Sistemleri</h5>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addGuillotineModal">Giyotin Sistemi Teklifi Ekle</button>
        </div>
        <div class="card-body p-0">
            <?php if ($guillotines): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Sistem</th>
                                <th>En</th>
                                <th>Boy</th>
                                <th>Adet</th>
                                <th>Cam</th>
                                <th>Motor</th>
                                <th>RAL</th>
                                <th class="text-end">Satır Toplamı</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($guillotines as $g): ?>
                                <tr>
                                    <td><?= e($g['system_type']) ?></td>
                                    <td><?= e($g['width']) ?></td>
                                    <td><?= e($g['height']) ?></td>
                                    <td><?= e($g['quantity']) ?></td>
                                    <td><?= e(trim($g['glass_type'] . ' ' . $g['glass_color'])) ?></td>
                                    <td><?= e($g['motor_system']) ?></td>
                                    <td><?= e($g['ral_code']) ?></td>
                                    <td class="text-end"><?= e(number_format((float)$g['total_amount'], 2, ',', '.')) ?> ₺</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-secondary edit-guillotine" data-bs-toggle="modal" data-bs-target="#addGuillotineModal"
                                            data-id="<?= e((string)$g['id']) ?>"
                                            data-width="<?= e((string)$g['width']) ?>"
                                            data-height="<?= e((string)$g['height']) ?>"
                                            data-quantity="<?= e((string)$g['quantity']) ?>"
                                            data-motor="<?= e($g['motor_system']) ?>"
                                            data-glass-type="<?= e($g['glass_type']) ?>"
                                            data-glass-color="<?= e($g['glass_color']) ?>"
                                            data-remote="<?= e((string)$g['remote_quantity']) ?>"
                                            data-ral="<?= e($g['ral_code']) ?>"
                                            data-profit="<?= e((string)$g['profit_margin']) ?>">
                                            Düzenle
                                        </button>
                                        <?php if ($role === 'admin' && strtolower((string)$g['system_type']) === 'guillotine'): ?>
                                            <form method="post" class="d-inline" target="_blank">
                                                <input type="hidden" name="action" value="optimize_guillotine">
                                                <input type="hidden" name="guillotine_id" value="<?= e((string)$g['id']) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-gear"></i> Optimize</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($role === 'admin'): ?>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Bu giyotin sistemini silmek istediğinize emin misiniz?');">
                                                <input type="hidden" name="action" value="delete_guillotine">
                                                <input type="hidden" name="guillotine_id" value="<?= e((string)$g['id']) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-3 text-muted">Giyotin sistemi bulunamadı.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Sürme Sistemleri</h5>
        </div>
        <div class="card-body p-0">
            <?php if ($slidings): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Sistem</th>
                                <th>En</th>
                                <th>Boy</th>
                                <th>Adet</th>
                                <th>Cam</th>
                                <th>Kanat</th>
                                <th>RAL</th>
                                <th class="text-end">Satır Toplamı</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($slidings as $s): ?>
                                <tr>
                                    <td><?= e($s['system_type']) ?></td>
                                    <td><?= e($s['width']) ?></td>
                                    <td><?= e($s['height']) ?></td>
                                    <td><?= e($s['quantity']) ?></td>
                                    <td><?= e(trim($s['glass_type'] . ' ' . $s['glass_color'])) ?></td>
                                    <td><?= e($s['wing_type']) ?></td>
                                    <td><?= e($s['ral_code']) ?></td>
                                    <td class="text-end"><?= e(number_format((float)$s['total_amount'], 2, ',', '.')) ?> ₺</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-3 text-muted">Sürme sistemi bulunamadı.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="addGuillotineModal" tabindex="-1" aria-labelledby="addGuillotineLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="form" value="guillotine">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="guillotine_id" id="guillotine_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="addGuillotineLabel">Giyotin Sistemi Teklifi Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="general_offer_id" value="<?= e((string)$offer['id']) ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="width" class="form-label">Genişlik</label>
                            <input type="number" min="0.01" step="0.01" class="form-control text-start" id="width" name="width" required>
                        </div>
                        <div class="col-md-6">
                            <label for="height" class="form-label">Yükseklik</label>
                            <input type="number" min="0.01" step="0.01" class="form-control text-start" id="height" name="height" required>
                        </div>
                        <div class="col-md-6">
                            <label for="quantity" class="form-label">Sistem Adedi</label>
                            <input type="number" min="1" step="1" class="form-control text-start" id="quantity" name="quantity" required>
                        </div>
                        <div class="col-md-6">
                            <label for="motor_system" class="form-label">Motor Sistemi</label>
                            <select class="form-select" id="motor_system" name="motor_system">
                                <option value="Somfy">Somfy</option>
                                <option value="ASA">ASA</option>
                                <option value="Cuppon">Cuppon</option>
                                <option value="Mosel">Mosel</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="glass_type" class="form-label">Cam Tipi</label>
                            <select class="form-select" id="glass_type" name="glass_type">
                                <option value="Isıcam">Isıcam</option>
                                <option value="Tek Cam">Tek Cam</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="glass_color" class="form-label">Cam Rengi</label>
                            <select class="form-select" id="glass_color" name="glass_color">
                                <option value="Şeffaf">Şeffaf</option>
                                <option value="Füme">Füme</option>
                                <option value="Mavi">Mavi</option>
                                <option value="Yeşil">Yeşil</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="remote_quantity" class="form-label">Kumanda Adedi</label>
                            <input type="number" min="1" step="1" class="form-control text-start" id="remote_quantity" name="remote_quantity">
                        </div>
                        <div class="col-md-6">
                            <label for="ral_code" class="form-label">RAL Kodu</label>
                            <input type="text" class="form-control" id="ral_code" name="ral_code">
                        </div>
                        <div class="col-md-6">
                            <label for="profit_margin" class="form-label">Kâr Marjı (%)</label>
                            <input type="number" min="0.01" step="0.01" class="form-control text-start" id="profit_margin" name="profit_margin">
                        </div>
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
document.getElementById('addGuillotineModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const form = this.querySelector('form');
    if (button && button.classList.contains('edit-guillotine')) {
        form.querySelector('#guillotine_id').value = button.getAttribute('data-id');
        form.querySelector('#width').value = button.getAttribute('data-width');
        form.querySelector('#height').value = button.getAttribute('data-height');
        form.querySelector('#quantity').value = button.getAttribute('data-quantity');
        form.querySelector('#motor_system').value = button.getAttribute('data-motor');
        form.querySelector('#glass_type').value = button.getAttribute('data-glass-type');
        form.querySelector('#glass_color').value = button.getAttribute('data-glass-color');
        form.querySelector('#remote_quantity').value = button.getAttribute('data-remote');
        form.querySelector('#ral_code').value = button.getAttribute('data-ral');
        form.querySelector('#profit_margin').value = button.getAttribute('data-profit');
        this.querySelector('.modal-title').textContent = 'Edit Guillotine System Offer';
    } else {
        form.reset();
        form.querySelector('#guillotine_id').value = '';
        this.querySelector('.modal-title').textContent = 'Add Guillotine System Offer';
    }
});

</script>
<?php require __DIR__ . '/footer.php'; ?>
