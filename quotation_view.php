<?php
require __DIR__ . '/header.php';

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

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Teklif bulunamadı.</div></div></body></html>';
    exit;
}

$error = null;

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

$guillotines = [];
$slidings = [];
if (!$error) {
    try {
        $gStmt = $pdo->prepare('SELECT system_type, width, height, quantity, motor_system, ral_code, glass_type, glass_color, total_amount FROM guillotinesystems WHERE general_offer_id = :id');
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
$totalFormatted = number_format($totalAmount, 2, ',', '.') . ' ₺';
$assemblyLabel = $assemblyTypes[$offer['assembly_type']] ?? 'Bilinmiyor';

?>
<div class="container mt-4">
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Teklif #<?= e((string)$offer['id']) ?></h5>
            <div>
                <a href="quotations.php" class="btn btn-secondary btn-sm">Geri Dön</a>
                <a href="quotation_edit.php?id=<?= e((string)$offer['id']) ?>" class="btn btn-primary btn-sm">Düzenle</a>
                <?php if ($role === 'admin'): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Bu teklifi silmek istediğinize emin misiniz?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= e((string)$offer['id']) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Sil</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-2">
                <div class="col-md-6"><strong>Müşteri:</strong> <?= e(trim($offer['first_name'] . ' ' . $offer['last_name'])) ?></div>
                <div class="col-md-6"><strong>Firma:</strong> <?= e($offer['company_name'] ?? $offer['customer_company']) ?></div>
            </div>
            <div class="row mb-2">
                <div class="col-md-6"><strong>Teklif Tarihi:</strong> <?= e(date('d.m.Y', strtotime($offer['offer_date']))) ?></div>
                <div class="col-md-6"><strong>Montaj Tipi:</strong> <?= e($assemblyLabel) ?></div>
            </div>
            <div class="row mb-2">
                <?php if (!empty($offer['payment_method'])): ?>
                    <div class="col-md-4"><strong>Ödeme:</strong> <?= e($offer['payment_method']) ?></div>
                <?php endif; ?>
                <?php if (!empty($offer['delivery_time'])): ?>
                    <div class="col-md-4"><strong>Teslim:</strong> <?= e($offer['delivery_time']) ?></div>
                <?php endif; ?>
                <?php if (!empty($offer['maturity_period'])): ?>
                    <div class="col-md-4"><strong>Vaade:</strong> <?= e($offer['maturity_period']) ?></div>
                <?php endif; ?>
            </div>
            <div class="row">
                <div class="col-md-6"><strong>Toplam Tutar:</strong> <?= e($totalFormatted) ?></div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Giyotin Sistemleri</h5>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addGuillotineModal">Add Guillotine System Offer</button>
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
                                    <?= e(number_format((float)$g['total_amount'], 2, ',', '.') . ' ₺') ?>
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
                                    <?= e(number_format((float)$g['total_amount'], 2, ',', '.') . ' ₺') ?>
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
                <div class="modal-header">
                    <h5 class="modal-title" id="addGuillotineLabel">Add Guillotine System Offer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="general_offer_id" value="<?= e((string)$offer['id']) ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="width" class="form-label">Width</label>
                            <input type="number" class="form-control" id="width" name="width" required>
                        </div>
                        <div class="col-md-6">
                            <label for="height" class="form-label">Height</label>
                            <input type="number" class="form-control" id="height" name="height" required>
                        </div>
                        <div class="col-md-6">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" required>
                        </div>
                        <div class="col-md-6">
                            <label for="motor_system" class="form-label">Motor System</label>
                            <select class="form-select" id="motor_system" name="motor_system">
                                <option value="Somfy">Somfy</option>
                                <option value="ASA">ASA</option>
                                <option value="Cuppon">Cuppon</option>
                                <option value="Mosel">Mosel</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="glass_type" class="form-label">Glass Type</label>
                            <select class="form-select" id="glass_type" name="glass_type">
                                <option value="Isıcam">Isıcam</option>
                                <option value="Tek Cam">Tek Cam</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="glass_color" class="form-label">Glass Color</label>
                            <select class="form-select" id="glass_color" name="glass_color">
                                <option value="Şeffaf">Şeffaf</option>
                                <option value="Füme">Füme</option>
                                <option value="Mavi">Mavi</option>
                                <option value="Yeşil">Yeşil</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="remote_control_qty" class="form-label">Remote Control Quantity</label>
                            <input type="number" class="form-control" id="remote_control_qty" name="remote_control_qty">
                        </div>
                        <div class="col-md-6">
                            <label for="ral_code" class="form-label">RAL Code</label>
                            <input type="text" class="form-control" id="ral_code" name="ral_code">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>

</html>