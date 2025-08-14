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
            $columns = [
                'profit_margin' => 'ALTER TABLE guillotinesystems ADD COLUMN profit_margin DECIMAL(5,2) DEFAULT NULL AFTER glass_color',
                'profit_rate'   => 'ALTER TABLE guillotinesystems ADD COLUMN profit_rate DECIMAL(5,2) DEFAULT NULL AFTER profit_margin',
                'profit_amount' => 'ALTER TABLE guillotinesystems ADD COLUMN profit_amount DECIMAL(10,2) DEFAULT NULL AFTER profit_rate',
                'total_amount'  => 'ALTER TABLE guillotinesystems ADD COLUMN total_amount DECIMAL(15,2) DEFAULT NULL AFTER profit_amount',
            ];
            foreach ($columns as $col => $sqlAlter) {
                try {
                    $pdo->query("SELECT $col FROM guillotinesystems LIMIT 1");
                } catch (Exception $e) {
                    $pdo->exec($sqlAlter);
                }
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
            $cost = filter_input(INPUT_POST, 'cost', FILTER_VALIDATE_FLOAT);
            $price = filter_input(INPUT_POST, 'selling_price', FILTER_VALIDATE_FLOAT);

            $validNumbers = $width !== false && $width > 0
                && $height !== false && $height > 0
                && $quantity !== false && $quantity > 0
                && $cost !== false && $cost > 0
                && $price !== false && $price > 0
                && ($remoteQty === null || ($remoteQty !== false && $remoteQty > 0));

            if (!$validNumbers) {
                $error = 'Tüm sayısal alanlar pozitif olmalıdır.';
            } else {
                $costTotal   = $cost * $quantity;
                $totalAmount = $price * $quantity;
                $profitAmount = $totalAmount - $costTotal;
                $profitMargin = $totalAmount > 0 ? ($profitAmount / $totalAmount) * 100 : 0;
                $profitRate   = $costTotal > 0 ? ($profitAmount / $costTotal) * 100 : 0;

                if ($gId) {
                    $sql = 'UPDATE guillotinesystems SET width=:width, height=:height, quantity=:quantity, motor_system=:motor, remote_quantity=:remote, ral_code=:ral, glass_type=:glass_type, glass_color=:glass_color, profit_margin=:profit_margin, profit_rate=:profit_rate, profit_amount=:profit_amount, total_amount=:total_amount WHERE id=:id AND general_offer_id=:goid';
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
                        ':profit_rate' => $profitRate,
                        ':profit_amount' => $profitAmount,
                        ':total_amount' => $totalAmount,
                        ':id' => $gId,
                        ':goid' => $id,
                    ];
                } else {
                    $sql = 'INSERT INTO guillotinesystems (general_offer_id, system_type, width, height, quantity, motor_system, remote_quantity, ral_code, glass_type, glass_color, profit_margin, profit_rate, profit_amount, total_amount) VALUES (:goid, :stype, :width, :height, :quantity, :motor, :remote, :ral, :glass_type, :glass_color, :profit_margin, :profit_rate, :profit_amount, :total_amount)';
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
                        ':profit_rate' => $profitRate,
                        ':profit_amount' => $profitAmount,
                        ':total_amount' => $totalAmount,
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
        $gStmt = $pdo->prepare('SELECT id, system_type, width, height, quantity, motor_system, remote_quantity, ral_code, glass_type, glass_color, profit_margin, profit_amount, total_amount FROM guillotinesystems WHERE general_offer_id = :id');
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

$paymentErrors = [];
$paymentData = [
    'payment_type' => $offer['payment_type'] ?? 'cash',
    'term_months' => $offer['term_months'] !== null ? (string)$offer['term_months'] : '',
    'interest_mode' => $offer['interest_mode'] ?? 'percent',
    'interest_value' => $offer['interest_value'] !== null ? (string)$offer['interest_value'] : '',
    'grace_days' => (string)($offer['grace_days'] ?? 0),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_payment') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, $token)) {
        $paymentErrors['form'] = 'Geçersiz CSRF tokenı.';
    }
    $paymentType = $_POST['payment_type'] === 'installment' ? 'installment' : 'cash';
    $termMonths = null;
    $interestMode = null;
    $interestValue = null;
    $graceDays = 0;
    $interestAmount = null;
    $totalWithInterest = round($subtotalCalc + $vatAmountCalc, 2);
    $monthlyInstallment = null;
    if ($paymentType === 'installment') {
        $termMonths = filter_input(INPUT_POST, 'term_months', FILTER_VALIDATE_INT);
        if ($termMonths === false || $termMonths < 1 || $termMonths > 60) {
            $paymentErrors['term_months'] = '1-60 arası ay giriniz.';
        }
        $interestMode = $_POST['interest_mode'] === 'fixed' ? 'fixed' : 'percent';
        if ($interestMode === 'percent') {
            $interestValue = filter_var($_POST['interest_value_percent'], FILTER_VALIDATE_FLOAT);
            if ($interestValue === false || $interestValue < 0 || $interestValue > 100) {
                $paymentErrors['interest_value'] = '0-100 arası yüzde giriniz.';
            }
        } else {
            $interestValue = filter_var($_POST['interest_value_fixed'], FILTER_VALIDATE_FLOAT);
            if ($interestValue === false || $interestValue < 0) {
                $paymentErrors['interest_value'] = '0 veya üzeri tutar giriniz.';
            }
        }
        $graceDays = filter_input(INPUT_POST, 'grace_days', FILTER_VALIDATE_INT);
        if ($graceDays === false || $graceDays < 0 || $graceDays > 180) {
            $paymentErrors['grace_days'] = '0-180 arası gün giriniz.';
            $graceDays = 0;
        }
        if (!$paymentErrors) {
            if ($interestMode === 'percent') {
                $interestAmount = round(($subtotalCalc + $vatAmountCalc) * ($interestValue / 100), 2);
            } else {
                $interestAmount = round($interestValue, 2);
            }
            $totalWithInterest = round($subtotalCalc + $vatAmountCalc + $interestAmount, 2);
            $monthlyInstallment = round($totalWithInterest / $termMonths, 2);
        }
    }
    if (!$paymentErrors) {
        try {
            $upd = $pdo->prepare('UPDATE generaloffers SET payment_type=:payment_type, term_months=:term_months, interest_mode=:interest_mode, interest_value=:interest_value, interest_amount=:interest_amount, total_with_interest=:total_with_interest, monthly_installment=:monthly_installment, grace_days=:grace_days WHERE id=:id');
            $upd->bindValue(':payment_type', $paymentType);
            $upd->bindValue(':term_months', $termMonths, $termMonths === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $upd->bindValue(':interest_mode', $interestMode, $interestMode === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $upd->bindValue(':interest_value', $interestValue, $interestValue === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $upd->bindValue(':interest_amount', $interestAmount, $interestAmount === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $upd->bindValue(':total_with_interest', $totalWithInterest, PDO::PARAM_STR);
            $upd->bindValue(':monthly_installment', $monthlyInstallment, $monthlyInstallment === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $upd->bindValue(':grace_days', $graceDays, PDO::PARAM_INT);
            $upd->bindValue(':id', $id, PDO::PARAM_INT);
            $upd->execute();
            $success = 'Ödeme şartları güncellendi.';
            $offer = array_merge($offer, [
                'payment_type' => $paymentType,
                'term_months' => $termMonths,
                'interest_mode' => $interestMode,
                'interest_value' => $interestValue,
                'interest_amount' => $interestAmount,
                'total_with_interest' => $totalWithInterest,
                'monthly_installment' => $monthlyInstallment,
                'grace_days' => $graceDays,
            ]);
            $paymentData = [
                'payment_type' => $paymentType,
                'term_months' => $termMonths !== null ? (string)$termMonths : '',
                'interest_mode' => $interestMode,
                'interest_value' => $interestValue !== null ? (string)$interestValue : '',
                'grace_days' => (string)$graceDays,
            ];
        } catch (Exception $e) {
            $paymentErrors['form'] = 'Ödeme şartları kaydedilemedi.';
        }
    } else {
        http_response_code(422);
    }
}
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
                    <div class="col-md-4"><strong>Ödeme:</strong> <?= e($paymentLabels[$offer['payment_method']] ?? $offer['payment_method']) ?></div>
                <?php endif; ?>
                <?php if (!empty($offer['validity_days'])): ?>
                    <div class="col-md-4"><strong>Geçerlilik:</strong> <?= (int)$offer['validity_days'] ?> gün</div>
                <?php endif; ?>
                <?php if (!empty($offer['installment_term'])): ?>
                    <div class="col-md-4"><strong>Vade:</strong> <?= e($offer['installment_term']) ?></div>
                <?php endif; ?>
            </div>
            <div class="row mb-2">
                <div class="col-md-6">
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
            <div class="row">
                <div class="col-md-6"><strong>Toplam Tutar:</strong> <?= e($totalFormatted) ?></div>
            </div>
        </div>
    </div>

    <div class="card mb-4" id="payment-terms" data-subtotal="<?= e((string)$subtotalCalc) ?>" data-vat="<?= e((string)$vatAmountCalc) ?>">
        <div class="card-header">
            <h5 class="mb-0">Ödeme Şartları</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($paymentErrors['form'])): ?><div class="alert alert-danger"><?= e($paymentErrors['form']) ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="update_payment">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div class="mb-3">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="payment_type" id="payment_type_cash" value="cash" <?= $paymentData['payment_type'] === 'cash' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="payment_type_cash">Peşin</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="payment_type" id="payment_type_installment" value="installment" <?= $paymentData['payment_type'] === 'installment' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="payment_type_installment">Vadeli</label>
                    </div>
                </div>
                <div id="installment_section" <?= $paymentData['payment_type'] === 'installment' ? '' : 'style="display:none;"' ?>>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Vade (Ay)</label>
                            <input type="number" name="term_months" id="term_months" min="1" max="60" class="form-control text-start <?= isset($paymentErrors['term_months']) ? 'is-invalid' : '' ?>" value="<?= e($paymentData['term_months']) ?>">
                            <?php if(isset($paymentErrors['term_months'])): ?><div class="invalid-feedback"><?= e($paymentErrors['term_months']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label d-block">Vade Farkı Türü</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="interest_mode" id="interest_mode_percent" value="percent" <?= $paymentData['interest_mode'] === 'percent' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="interest_mode_percent">Yüzde (%)</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="interest_mode" id="interest_mode_fixed" value="fixed" <?= $paymentData['interest_mode'] === 'fixed' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="interest_mode_fixed">Tutar (TRY)</label>
                            </div>
                            <div class="mt-2" id="interest_percent_field" <?= $paymentData['interest_mode'] === 'percent' ? '' : 'style="display:none;"' ?>>
                                <input type="number" step="0.01" min="0" max="100" name="interest_value_percent" id="interest_value_percent" class="form-control text-start <?= isset($paymentErrors['interest_value']) ? 'is-invalid' : '' ?>" value="<?= $paymentData['interest_mode'] === 'percent' ? e($paymentData['interest_value']) : '' ?>">
                                <?php if(isset($paymentErrors['interest_value']) && $paymentData['interest_mode'] === 'percent'): ?><div class="invalid-feedback"><?= e($paymentErrors['interest_value']) ?></div><?php endif; ?>
                            </div>
                            <div class="mt-2" id="interest_fixed_field" <?= $paymentData['interest_mode'] === 'fixed' ? '' : 'style="display:none;"' ?>>
                                <input type="number" step="0.01" min="0" name="interest_value_fixed" id="interest_value_fixed" class="form-control text-start <?= isset($paymentErrors['interest_value']) ? 'is-invalid' : '' ?>" value="<?= $paymentData['interest_mode'] === 'fixed' ? e($paymentData['interest_value']) : '' ?>">
                                <?php if(isset($paymentErrors['interest_value']) && $paymentData['interest_mode'] === 'fixed'): ?><div class="invalid-feedback"><?= e($paymentErrors['interest_value']) ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Erteleme (Gün)</label>
                            <input type="number" name="grace_days" id="grace_days" min="0" max="180" class="form-control text-start <?= isset($paymentErrors['grace_days']) ? 'is-invalid' : '' ?>" value="<?= e($paymentData['grace_days']) ?>">
                            <?php if(isset($paymentErrors['grace_days'])): ?><div class="invalid-feedback"><?= e($paymentErrors['grace_days']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Vade Farkı Tutarı (TRY)</label>
                            <input type="text" id="interest_amount_preview" class="form-control" readonly value="<?= $offer['interest_amount'] !== null ? e(tr_money((float)$offer['interest_amount'])) : '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Toplam (Vade Dahil) (TRY)</label>
                            <input type="text" id="total_with_interest_preview" class="form-control" readonly value="<?= $offer['total_with_interest'] !== null ? e(tr_money((float)$offer['total_with_interest'])) : '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Aylık Taksit (TRY)</label>
                            <input type="text" id="monthly_installment_preview" class="form-control" readonly value="<?= $offer['monthly_installment'] !== null ? e(tr_money((float)$offer['monthly_installment'])) : '' ?>">
                        </div>
                    </div>
                </div>
                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
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
                                            data-cost="<?= e((string)(((float)$g['total_amount'] - (float)$g['profit_amount']) / max((float)$g['quantity'], 1))) ?>"
                                            data-price="<?= e((string)((float)$g['total_amount'] / max((float)$g['quantity'], 1))) ?>">
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
                            <label for="cost" class="form-label">Maliyet (Birim)</label>
                            <input type="number" min="0.01" step="0.01" class="form-control text-start" id="cost" name="cost" required>
                        </div>
                        <div class="col-md-6">
                            <label for="selling_price" class="form-label">Satış Fiyatı (Birim)</label>
                            <input type="number" min="0.01" step="0.01" class="form-control text-start" id="selling_price" name="selling_price" required>
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
        form.querySelector('#cost').value = button.getAttribute('data-cost');
        form.querySelector('#selling_price').value = button.getAttribute('data-price');
        this.querySelector('.modal-title').textContent = 'Edit Guillotine System Offer';
    } else {
        form.reset();
        form.querySelector('#guillotine_id').value = '';
        this.querySelector('.modal-title').textContent = 'Add Guillotine System Offer';
    }
});

const payCard = document.getElementById('payment-terms');
if (payCard) {
    const subtotal = parseFloat(payCard.dataset.subtotal || '0');
    const vat = parseFloat(payCard.dataset.vat || '0');
    const installmentSection = document.getElementById('installment_section');
    const interestPercentField = document.getElementById('interest_percent_field');
    const interestFixedField = document.getElementById('interest_fixed_field');
    const interestAmountPreview = document.getElementById('interest_amount_preview');
    const totalWithInterestPreview = document.getElementById('total_with_interest_preview');
    const monthlyInstallmentPreview = document.getElementById('monthly_installment_preview');

    function recalc() {
        const paymentType = document.querySelector('input[name="payment_type"]:checked').value;
        if (paymentType !== 'installment') {
            installmentSection.style.display = 'none';
            interestAmountPreview.value = '';
            totalWithInterestPreview.value = '';
            monthlyInstallmentPreview.value = '';
            return;
        }
        installmentSection.style.display = '';
        const term = parseInt(document.getElementById('term_months').value, 10) || 0;
        const mode = document.querySelector('input[name="interest_mode"]:checked').value;
        let interestValue = 0;
        let interestAmount = 0;
        if (mode === 'percent') {
            interestPercentField.style.display = '';
            interestFixedField.style.display = 'none';
            interestValue = parseFloat(document.getElementById('interest_value_percent').value) || 0;
            interestAmount = (subtotal + vat) * (interestValue / 100);
        } else {
            interestPercentField.style.display = 'none';
            interestFixedField.style.display = '';
            interestValue = parseFloat(document.getElementById('interest_value_fixed').value) || 0;
            interestAmount = interestValue;
        }
        const totalWithInterest = subtotal + vat + interestAmount;
        const monthly = term ? totalWithInterest / term : 0;
        interestAmountPreview.value = interestAmount.toFixed(2).replace('.', ',');
        totalWithInterestPreview.value = totalWithInterest.toFixed(2).replace('.', ',');
        monthlyInstallmentPreview.value = monthly.toFixed(2).replace('.', ',');
    }

    document.querySelectorAll('input[name="payment_type"]').forEach(el => el.addEventListener('change', recalc));
    document.querySelectorAll('input[name="interest_mode"]').forEach(el => el.addEventListener('change', recalc));
    ['term_months','interest_value_percent','interest_value_fixed'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.addEventListener('input', recalc); }
    });
    recalc();
}
</script>
<?php require __DIR__ . '/footer.php'; ?>
