<?php
require __DIR__ . '/header.php';
require __DIR__ . '/components/page_header.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/test.php';

function e(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

class PdoProductProvider implements ProductProviderInterface
{
    public function __construct(private PDO $pdo) {}

    public function getProduct(string $name): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.unit, p.unit_price, p.vat_rate, p.weight_per_meter, p.price_unit, c.name AS category '
                . 'FROM products p LEFT JOIN categories c ON p.category_id = c.id '
                . 'WHERE LOWER(p.name) = LOWER(:name)'
        );
        $stmt->execute([':name' => $name]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

$productProvider = new PdoProductProvider($pdo);

//
// Merge duplicate guillotine records (same dimensions and attributes)
// by summing their quantities and scaling existing totals.
//
function mergeGuillotineDuplicates(PDO $pdo, int $offerId): void
{
    $dupStmt = $pdo->prepare(
        'SELECT width, height, motor_system, remote_quantity, ral_code, glass_type, glass_color, profit_margin, '
        . 'MIN(id) AS keep_id, SUM(quantity) AS total_qty, GROUP_CONCAT(id ORDER BY id) AS ids, COUNT(*) AS cnt '
        . 'FROM guillotinesystems WHERE general_offer_id = :id '
        . 'GROUP BY width, height, motor_system, remote_quantity, ral_code, glass_type, glass_color, profit_margin '
        . 'HAVING cnt > 1'
    );
    $dupStmt->execute([':id' => $offerId]);

    foreach ($dupStmt->fetchAll(PDO::FETCH_ASSOC) as $group) {
        $ids      = array_map('intval', explode(',', (string)$group['ids']));
        $keepId   = (int) $group['keep_id'];
        $totalQty = (int) $group['total_qty'];

        $fetch = $pdo->prepare('SELECT quantity, profit_amount, total_amount FROM guillotinesystems WHERE id = :id');
        $fetch->execute([':id' => $keepId]);
        $row = $fetch->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            continue;
        }

        $unitProfit = ((float) $row['profit_amount']) / max(1, (int) $row['quantity']);
        $unitTotal  = ((float) $row['total_amount']) / max(1, (int) $row['quantity']);

        $pdo->prepare('UPDATE guillotinesystems SET quantity = :q, profit_amount = :p, total_amount = :t WHERE id = :id')
            ->execute([
                ':q' => $totalQty,
                ':p' => $unitProfit * $totalQty,
                ':t' => $unitTotal * $totalQty,
                ':id' => $keepId,
            ]);

        $idsToDelete = array_diff($ids, [$keepId]);
        if ($idsToDelete) {
            $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
            $del = $pdo->prepare("DELETE FROM guillotinesystems WHERE id IN ($placeholders)");
            $del->execute(array_values($idsToDelete));
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$userId = (int)($_SESSION['user_id'] ?? 0);

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
    'draft'       => 'Taslak',
    'approved'    => 'Onaylandı',
    'cancelled'   => 'İptal Edildi',
    'rejected'    => 'Reddedildi',
    'in_progress' => 'Devam Ediyor',
];
$statusClasses = [
    'draft'       => 'secondary',
    'approved'    => 'success',
    'cancelled'   => 'dark',
    'rejected'    => 'danger',
    'in_progress' => 'info',
];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Geçersiz teklif ID\'si. <a href="quotations.php">Teklif listesine dön</a>.</div></div></body></html>';
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


if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'recalc_guillotine' &&
    $role === 'admin'
) {
    $token = $_POST['csrf_token'] ?? '';
    $gId   = filter_input(INPUT_POST, 'guillotine_id', FILTER_VALIDATE_INT);
    if (!hash_equals($csrfToken, $token) || !$gId) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Geçersiz CSRF tokenı.']);
        exit;
    }
    try {
        $pdo->beginTransaction();
        $gFetch = $pdo->prepare('SELECT * FROM guillotinesystems WHERE id = :gid AND general_offer_id = :goid');
        $gFetch->execute([':gid' => $gId, ':goid' => $id]);
        if ($row = $gFetch->fetch(PDO::FETCH_ASSOC)) {
            if ((float)($row['width'] ?? 0) <= 0 || (float)($row['height'] ?? 0) <= 0 || (int)($row['quantity'] ?? 0) <= 0) {
                throw new Exception('Geçersiz giyotin satırı.');
            }
            $exchangeRates = fetchExchangeRates('TRY', ['USD', 'EUR']);
            if (empty($exchangeRates)) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                header('Content-Type: application/json', true, 500);
                echo json_encode(['error' => 'Kur bilgileri alınamadı.']);
                exit;
            }
            $result = calculateGuillotineTotals([
                'width'         => $row['width'],
                'height'        => $row['height'],
                'quantity'      => $row['quantity'],
                'glass_type'    => $row['glass_type'] ?? '',
                'profit_rate'   => $row['profit_rate'] ?? ($row['profit_margin'] ?? 0),
                'currency'      => 'TRY',
                'exchange_rates'=> $exchangeRates,
                'provider'      => $productProvider,
            ]);

            $tot = $result['totals'];
            $profitRate = (float)($row['profit_rate'] ?? ($row['profit_margin'] ?? 0));
            $demonteItems = [];

            $systemQty = (int)($row['quantity'] ?? 0);
            $motorName = (string)($row['motor_system'] ?? '');
            if ($motorName !== '') {
                $motorProduct = $productProvider->getProduct($motorName);
                if ($motorProduct) {
                    $motorCurrency = strtoupper((string)($motorProduct['price_unit'] ?? 'TRY'));
                    $motorPrice    = (float)($motorProduct['unit_price'] ?? 0);
                    if ($motorCurrency !== $result['currency']) {
                        $rate = $exchangeRates[$motorCurrency] ?? null;
                        if ($rate !== null) {
                            $motorPrice *= $rate;
                        }
                    }
                    $motorCost = $motorPrice * $systemQty;
                    $demonteItems[] = ['cost' => $motorCost];
                }
            }

            $remoteQty = (int)($row['remote_quantity'] ?? 0);
            if ($remoteQty > 0) {
                $remoteProduct = $productProvider->getProduct('Kumanda');
                if ($remoteProduct) {
                    $remoteCurrency = strtoupper((string)($remoteProduct['price_unit'] ?? 'TRY'));
                    $remotePrice    = (float)($remoteProduct['unit_price'] ?? 0);
                    if ($remoteCurrency !== $result['currency']) {
                        $rate = $exchangeRates[$remoteCurrency] ?? null;
                        if ($rate !== null) {
                            $remotePrice *= $rate;
                        }
                    }
                    $remoteCost = $remotePrice * $remoteQty;
                    $demonteItems[] = ['cost' => $remoteCost];
                }
            }

            $demonteProfitSum = 0.0;
            $demonteTotal = 0.0;
            foreach ($demonteItems as $item) {
                $profit = $item['cost'] * $profitRate / 100;
                $demonteProfitSum += $profit;
                $demonteTotal += $item['cost'] + $profit;
            }

            $salesTotal    = $tot['grand_total'] + $demonteTotal;
            $updatedProfit = $tot['profit'] + $demonteProfitSum;

            $gUpd = $pdo->prepare('UPDATE guillotinesystems SET profit_amount=:pamount, total_amount=:tamount WHERE id=:id');
            $gUpd->execute([
                ':pamount' => $updatedProfit,
                ':tamount' => $salesTotal,
                ':id'      => $gId,
            ]);

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
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        } else {
            throw new Exception('Giyotin satırı bulunamadı.');
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header('Content-Type: application/json', true, 500);
        echo json_encode(['error' => 'Hesaplama hatası.']);
        exit;
    }
}

try {
    $stmt = $pdo->prepare('
        SELECT g.*, c.first_name, c.last_name, c.company AS customer_company, co.name AS company_name
        FROM generaloffers g
        JOIN customers c ON g.customer_id = c.id
        LEFT JOIN company co ON g.company_id = co.id AND co.user_id = :uid
        WHERE g.id = :id AND (g.company_id IS NULL OR co.id IS NOT NULL)
    ');
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    $offer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$offer) {
        echo '<div class="container mt-4"><div class="alert alert-danger">Teklif bulunamadı veya erişim yetkiniz yok. <a href="quotations.php">Teklif listesine dön</a>.</div></div></body></html>';
        exit;
    }
} catch (Exception $e) {
    $error = 'Teklif verileri alınamadı.';
    $offer = null;
}

if (!$offer) {
    echo '<div class="container mt-4"><div class="alert alert-danger">' . e($error) . ' <a href="quotations.php">Teklif listesine dön</a>.</div></div></body></html>';
    exit;
}

$expired = isExpired($offer);
$approveUrl = '';
if (!empty($offer['approval_token'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? '';
    $approveUrl = $host ? $scheme . '://' . $host . '/public/approve.php?token=' . urlencode($offer['approval_token']) : '';
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
                && $profitMargin !== false && $profitMargin >= 0
                && ($remoteQty === null || ($remoteQty !== false && $remoteQty > 0));

            if (!$validNumbers) {
                $error = 'Tüm sayısal alanlar pozitif olmalıdır.';
            } else {
                $exchangeRates = fetchExchangeRates('TRY', ['USD', 'EUR']);
                if (empty($exchangeRates)) {
                    $error = 'Kur bilgileri alınamadı.';
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

                    $gId = $gId ?: (int)$pdo->lastInsertId();
                    $gFetch = $pdo->prepare('SELECT * FROM guillotinesystems WHERE id = :gid AND general_offer_id = :goid');
                    $gFetch->execute([':gid' => $gId, ':goid' => $id]);
                    if ($row = $gFetch->fetch(PDO::FETCH_ASSOC)) {
                        $result = calculateGuillotineTotals([
                            'width'         => $row['width'],
                            'height'        => $row['height'],
                            'quantity'      => $row['quantity'],
                            'glass_type'    => $row['glass_type'] ?? '',
                            'profit_rate'   => $row['profit_rate'] ?? ($row['profit_margin'] ?? 0),
                            'currency'      => 'TRY',
                            'exchange_rates'=> $exchangeRates,
                            'provider'      => $productProvider,
                        ]);

                        $tot = $result['totals'];
                        $profitRate = (float)($row['profit_rate'] ?? ($row['profit_margin'] ?? 0));
                        $demonteItems = [];

                        $systemQty = (int)($row['quantity'] ?? 0);
                        $motorName = (string)($row['motor_system'] ?? '');
                        if ($motorName !== '') {
                            $motorProduct = $productProvider->getProduct($motorName);
                            if ($motorProduct) {
                                $motorCurrency = strtoupper((string)($motorProduct['price_unit'] ?? 'TRY'));
                                $motorPrice    = (float)($motorProduct['unit_price'] ?? 0);
                                if ($motorCurrency !== $result['currency']) {
                                    $rate = $exchangeRates[$motorCurrency] ?? null;
                                    if ($rate !== null) {
                                        $motorPrice *= $rate;
                                    }
                                }
                                $motorCost = $motorPrice * $systemQty;
                                $demonteItems[] = ['cost' => $motorCost];
                            }
                        }

                        $remoteQty = (int)($row['remote_quantity'] ?? 0);
                        if ($remoteQty > 0) {
                            $remoteProduct = $productProvider->getProduct('Kumanda');
                            if ($remoteProduct) {
                                $remoteCurrency = strtoupper((string)($remoteProduct['price_unit'] ?? 'TRY'));
                                $remotePrice    = (float)($remoteProduct['unit_price'] ?? 0);
                                if ($remoteCurrency !== $result['currency']) {
                                    $rate = $exchangeRates[$remoteCurrency] ?? null;
                                    if ($rate !== null) {
                                        $remotePrice *= $rate;
                                    }
                                }
                                $remoteCost = $remotePrice * $remoteQty;
                                $demonteItems[] = ['cost' => $remoteCost];
                            }
                        }

                        $demonteProfitSum = 0.0;
                        $demonteTotal = 0.0;
                        foreach ($demonteItems as $item) {
                            $profit = $item['cost'] * $profitRate / 100;
                            $demonteProfitSum += $profit;
                            $demonteTotal += $item['cost'] + $profit;
                        }

                        $salesTotal    = $tot['grand_total'] + $demonteTotal;
                        $updatedProfit = $tot['profit'] + $demonteProfitSum;

                        $gUpd = $pdo->prepare('UPDATE guillotinesystems SET profit_amount=:pamount, total_amount=:tamount WHERE id=:id');
                        $gUpd->execute([
                            ':pamount' => $updatedProfit,
                            ':tamount' => $salesTotal,
                            ':id'      => $gId,
                        ]);
                    }

                    // Merge duplicate rows (same dimensions) and recalc totals
                    mergeGuillotineDuplicates($pdo, $id);

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
        // Ensure duplicate rows are merged before fetching
        mergeGuillotineDuplicates($pdo, $id);

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
$subtotalFormatted = tr_money($subtotalCalc) . ' ₺';
$vatFormatted = tr_money($vatAmountCalc) . ' ₺';
$totalFormatted = tr_money($totalAmount) . ' ₺';
$assemblyLabel = $assemblyTypes[$offer['assembly_type']] ?? 'Bilinmiyor';

?>
<style>
    .table-sticky-header thead th {
        position: sticky;
        top: 0;
        background: var(--bs-body-bg);
        z-index: 1;
        box-shadow: 0 2px 0 rgba(0,0,0,.05);
    }
    .w-total { min-width: 8rem; }
    .spin { animation: spin 1s linear infinite; display:inline-block; }
    @keyframes spin { to { transform: rotate(360deg); } }
    @media print {
        .btn, .dropdown, .btn-group { display: none !important; }
        body { background: #fff; }
        .card { box-shadow: none !important; }
        .table { border: 1px solid #000; }
        .table th, .table td { border: 1px solid #000 !important; }
    }
</style>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="quotations.php">Teklifler</a></li>
        <li class="breadcrumb-item active" aria-current="page">#<?= e((string)$offer['id']) ?></li>
    </ol>
</nav>
<?php if ($expired): ?>
    <style>body.offer-expired{filter:grayscale(1);}</style>
    <script>document.body.classList.add('offer-expired');</script>
<?php endif; ?>
<?php
ob_start();
if ($expired): ?>
    <button id="reactivateTrigger" class="btn btn-primary me-2 d-print-none" data-bs-toggle="modal" data-bs-target="#reactivateModal">Teklifi Yeniden Aktifleştir</button>
<?php endif; ?>
<div class="d-none d-md-inline-flex btn-group d-print-none" role="group" aria-label="Eylemler">
    <a href="quotation_edit.php?id=<?= e((string)$offer['id']) ?>" class="btn btn-primary offer-action" data-bs-toggle="tooltip" title="Düzenle" aria-label="Düzenle"><i class="bi bi-pencil"></i></a>
    <a href="/pdf/preview.php?id=<?= e((string)$offer['id']) ?>" class="btn btn-secondary offer-action" target="_blank" rel="noopener" data-bs-toggle="tooltip" title="PDF Önizleme" aria-label="PDF Önizleme"><i class="bi bi-file-earmark-pdf"></i></a>
    <?php if ($role === 'admin'): ?>
        <button type="button" class="btn btn-danger offer-action" data-bs-toggle="modal" data-bs-target="#deleteModal" aria-label="Sil" title="Sil"><i class="bi bi-trash"></i></button>
    <?php endif; ?>
</div>
<div class="dropdown d-md-none d-print-none">
    <button class="btn btn-primary dropdown-toggle" type="button" id="actionMenu" data-bs-toggle="dropdown" aria-expanded="false">
        Eylemler
    </button>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actionMenu">
        <li><a class="dropdown-item offer-action" href="quotation_edit.php?id=<?= e((string)$offer['id']) ?>"><i class="bi bi-pencil me-1"></i> Düzenle</a></li>
        <li><a class="dropdown-item offer-action" href="/pdf/preview.php?id=<?= e((string)$offer['id']) ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1"></i> PDF Önizleme</a></li>
        <?php if ($role === 'admin'): ?>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item text-danger offer-action" data-bs-toggle="modal" data-bs-target="#deleteModal"><i class="bi bi-trash me-1"></i> Sil</button></li>
        <?php endif; ?>
    </ul>
</div>
<?php
$actions = ob_get_clean();
$title = 'Teklif #' . e((string)$offer['id']);
if ($expired) {
    $title .= ' <span class="badge bg-secondary">Süresi Doldu</span>';
}
page_header($title, $actions, true);
?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Özet</h5>
    </div>
    <div class="card-body">
        <div class="row row-cols-1 row-cols-md-2 g-3">
            <div class="col">
                <div class="mb-2">
                    <span class="text-muted small d-block">Müşteri</span>
                    <span class="text-body"><?= e(trim($offer['first_name'] . ' ' . $offer['last_name'])) ?></span>
                </div>
                <div class="mb-2">
                    <span class="text-muted small d-block">Teklif Tarihi</span>
                    <span class="text-body"><?= e(date('d.m.Y', strtotime($offer['offer_date']))) ?></span>
                </div>
                <?php if (!empty($offer['payment_method'])): ?>
                    <div class="mb-2">
                        <span class="text-muted small d-block">Ödeme</span>
                        <span class="text-body"><?= e($paymentLabels[$offer['payment_method']] ?? $offer['payment_method']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($approveUrl): ?>
                    <div class="mb-2">
                        <span class="text-muted small d-block">Onay Linki</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary share-btn offer-action" data-url="<?= e($approveUrl) ?>">
                            <i class="bi bi-share me-1" aria-hidden="true"></i><span class="visually-hidden">Bağlantıyı kopyala</span> Paylaş
                        </button>
                    </div>
                <?php endif; ?>
                <div class="mb-2">
                    <?php if ($role === 'admin'): ?>
                        <form method="post" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="<?= e((string)$offer['id']) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <label for="statusSelect" class="form-label mb-0 small">Durum</label>
                            <select id="statusSelect" name="status" class="form-select form-select-sm w-auto">
                                <?php foreach ($statusLabels as $code => $label): ?>
                                    <option value="<?= e($code) ?>" <?= $offer['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">Kaydet</button>
                        </form>
                    <?php else: ?>
                        <span class="text-muted small d-block">Durum</span>
                        <span class="badge bg-<?= e($statusClasses[$offer['status']] ?? 'secondary') ?>"><?= e($statusLabels[$offer['status']] ?? $offer['status']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col">
                <?php if (!empty($offer['company_name']) || !empty($offer['customer_company'])): ?>
                    <div class="mb-2">
                        <span class="text-muted small d-block">Firma</span>
                        <span class="text-body"><?= e($offer['company_name'] ?? $offer['customer_company']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="mb-2">
                    <span class="text-muted small d-block">Montaj Tipi</span>
                    <span class="text-body"><?= e($assemblyLabel) ?></span>
                </div>
                <?php if (!empty($offer['validity_days'])): ?>
                    <div class="mb-2">
                        <span class="text-muted small d-block">Geçerlilik</span>
                        <span class="text-body"><?= (int)$offer['validity_days'] ?> gün</span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($offer['installment_term'])): ?>
                    <div class="mb-2">
                        <span class="text-muted small d-block">Vade</span>
                        <span class="text-body"><?= e($offer['installment_term']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="mb-2">
                    <span class="text-muted small d-block">Toplam Tutar</span>
                    <span class="fs-5 fw-semibold text-body"><?= e($totalFormatted) ?></span>
                </div>
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
                <table class="table table-sm table-striped table-sticky-header mb-0">
                    <thead>
                        <tr>
                            <th>Sistem</th>
                            <th class="text-end">En</th>
                            <th class="text-end">Boy</th>
                            <th class="text-end">Adet</th>
                            <th>Cam</th>
                            <th>Motor</th>
                            <th>RAL</th>
                            <th class="text-end w-total">Satır Toplamı</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($guillotines as $g): ?>
                            <tr>
                                <td><?= e($g['system_type']) ?></td>
                                <td class="text-end"><?= e($g['width']) ?></td>
                                <td class="text-end"><?= e($g['height']) ?></td>
                                <td class="text-end"><?= e($g['quantity']) ?></td>
                                <td class="text-truncate" style="max-width:120px;" title="<?= e(trim($g['glass_type'] . ' ' . $g['glass_color'])) ?>"><?= e(trim($g['glass_type'] . ' ' . $g['glass_color'])) ?></td>
                                <td class="text-truncate" style="max-width:100px;" title="<?= e($g['motor_system']) ?>"><?= e($g['motor_system']) ?></td>
                                <td class="text-truncate" style="max-width:80px;" title="<?= e($g['ral_code']) ?>"><?= e($g['ral_code']) ?></td>
                                <td class="text-end w-total"><?= e(number_format((float)$g['total_amount'], 2, ',', '.')) ?> ₺</td>
                                <td class="text-end">
                                    <div class="dropdown position-static">
                                        <button class="btn btn-sm btn-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="İşlemler">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" data-bs-auto-close="outside">
                                            <li>
                                                <a href="test.php?quote_id=<?= e((string)$g['id']) ?>" class="dropdown-item recalc-lines" data-gid="<?= e((string)$g['id']) ?>">
                                                    <i class="bi bi-list-ul me-1"></i> Kalemler
                                                </a>
                                            </li>
                                            <li>
                                                <a href="optimizasyon.php?quote_id=<?= e((string)$g['id']) ?>" class="dropdown-item">
                                                    <i class="bi bi-gear me-1"></i> Optimizasyon
                                                </a>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item edit-guillotine" data-bs-toggle="modal" data-bs-target="#addGuillotineModal" data-id="<?= e((string)$g['id']) ?>" data-width="<?= e((string)$g['width']) ?>" data-height="<?= e((string)$g['height']) ?>" data-quantity="<?= e((string)$g['quantity']) ?>" data-motor="<?= e((string)$g['motor_system']) ?>" data-glass-type="<?= e((string)$g['glass_type']) ?>" data-glass-color="<?= e((string)$g['glass_color']) ?>" data-remote="<?= e((string)$g['remote_quantity']) ?>" data-ral="<?= e((string)$g['ral_code']) ?>" data-profit="<?= e((string)$g['profit_margin']) ?>">
                                                    <i class="bi bi-pencil me-1"></i> Düzenle
                                                </button>
                                            </li>
                                            <?php if ($role === 'admin'): ?>
                                                <li>
                                                    <button type="button" class="dropdown-item text-danger delete-guillotine offer-row-action" data-id="<?= e((string)$g['id']) ?>" data-bs-toggle="modal" data-bs-target="#deleteGuillotineModal">
                                                        <i class="bi bi-trash me-1"></i> Sil
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-5 text-center text-muted">
                <i class="bi bi-inboxes display-6 mb-3"></i>
                <p class="mb-3">Kayıt yok</p>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addGuillotineModal">Yeni Ekle</button>
            </div>
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
                <table class="table table-sm table-striped table-sticky-header mb-0">
                    <thead>
                        <tr>
                            <th>Sistem</th>
                            <th class="text-end">En</th>
                            <th class="text-end">Boy</th>
                            <th class="text-end">Adet</th>
                            <th>Cam</th>
                            <th>Kanat</th>
                            <th>RAL</th>
                            <th class="text-end w-total">Satır Toplamı</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slidings as $s): ?>
                            <tr>
                                <td><?= e($s['system_type']) ?></td>
                                <td class="text-end"><?= e($s['width']) ?></td>
                                <td class="text-end"><?= e($s['height']) ?></td>
                                <td class="text-end"><?= e($s['quantity']) ?></td>
                                <td class="text-truncate" style="max-width:120px;" title="<?= e(trim($s['glass_type'] . ' ' . $s['glass_color'])) ?>"><?= e(trim($s['glass_type'] . ' ' . $s['glass_color'])) ?></td>
                                <td class="text-truncate" style="max-width:120px;" title="<?= e($s['wing_type']) ?>"><?= e($s['wing_type']) ?></td>
                                <td class="text-truncate" style="max-width:80px;" title="<?= e($s['ral_code']) ?>"><?= e($s['ral_code']) ?></td>
                                <td class="text-end w-total"><?= e(number_format((float)$s['total_amount'], 2, ',', '.')) ?> ₺</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-5 text-center text-muted">
                <i class="bi bi-inboxes display-6 mb-3"></i>
                <p class="mb-3">Kayıt yok</p>
                <a href="quotation_edit.php?id=<?= e((string)$offer['id']) ?>" class="btn btn-sm btn-primary">Yeni Ekle</a>
            </div>
<?php endif; ?>
    </div>
</div>

<div class="d-flex justify-content-end mb-4">
    <table class="table table-sm w-auto mb-0">
        <tbody>
            <tr>
                <th class="text-end">Hesaplanan Tutar (Vergisiz)</th>
                <td class="text-end"><?= e($subtotalFormatted) ?></td>
            </tr>
            <tr>
                <th class="text-end">KDV Tutarı</th>
                <td class="text-end"><?= e($vatFormatted) ?></td>
            </tr>
            <tr>
                <th class="text-end">Toplam Tutar (Vergili)</th>
                <td class="text-end"><?= e($totalFormatted) ?></td>
            </tr>
        </tbody>
    </table>
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
                            <label for="width" class="form-label">Genişlik <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="number" min="0.01" step="0.01" class="form-control" id="width" name="width" placeholder="mm" aria-required="true" required>
                            <div class="form-text">mm</div>
                        </div>
                        <div class="col-md-6">
                            <label for="height" class="form-label">Yükseklik <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="number" min="0.01" step="0.01" class="form-control" id="height" name="height" placeholder="mm" aria-required="true" required>
                            <div class="form-text">mm</div>
                        </div>
                        <div class="col-md-6">
                            <label for="quantity" class="form-label">Sistem Adedi <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="number" min="1" step="1" class="form-control" id="quantity" name="quantity" placeholder="1" aria-required="true" required>
                            <div class="form-text">adet</div>
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
                            <input type="number" min="1" step="1" class="form-control" id="remote_quantity" name="remote_quantity" placeholder="0">
                            <div class="form-text">adet</div>
                        </div>
                        <div class="col-md-6">
                            <label for="ral_code" class="form-label">RAL Kodu</label>
                            <input type="text" class="form-control" id="ral_code" name="ral_code" placeholder="Örn: 9016">
                        </div>
                        <div class="col-md-6">
                            <label for="profit_margin" class="form-label">Kâr Marjı <span class="text-danger" aria-hidden="true">*</span></label>
                            <div class="input-group">
                                <input type="number" min="0" step="0.01" class="form-control" id="profit_margin" name="profit_margin" placeholder="0" aria-required="true" required>
                                <span class="input-group-text">%</span>
                            </div>
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

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Teklifi Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">Bu teklifi silmek istediğinize emin misiniz?</div>
                <div class="modal-footer">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= e((string)$offer['id']) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger">Sil</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteGuillotineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Satırı Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">Bu giyotin sistemini silmek istediğinize emin misiniz?</div>
                <div class="modal-footer">
                    <input type="hidden" name="action" value="delete_guillotine">
                    <input type="hidden" name="guillotine_id" id="deleteGuillotineId">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger">Sil</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('addGuillotineModal').addEventListener('show.bs.modal', function(event) {
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
            this.querySelector('.modal-title').textContent = 'Giyotin Sistemi Teklifi Düzenle';
        } else {
            form.reset();
            form.querySelector('#guillotine_id').value = '';
            this.querySelector('.modal-title').textContent = 'Giyotin Sistemi Teklifi Ekle';
        }
    });

    const delModal = document.getElementById('deleteGuillotineModal');
    delModal?.addEventListener('show.bs.modal', function(event) {
        const btn = event.relatedTarget;
        this.querySelector('#deleteGuillotineId').value = btn.getAttribute('data-id');
    });

    document.querySelectorAll('.recalc-lines').forEach(function(link) {
        link.addEventListener('click', async function(e) {
            e.preventDefault();
            const url = this.href;
            const gid = this.dataset.gid;
            const original = this.innerHTML;
            this.classList.add('disabled');
            this.innerHTML = '<i class="bi bi-arrow-repeat spin me-1"></i>Yeniden Hesaplanıyor…';
            try {
                const res = await fetch('quotation_view.php?id=<?= e((string)$offer['id']) ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'recalc_guillotine',
                        guillotine_id: gid,
                        csrf_token: '<?= e($csrfToken) ?>'
                    })
                });
                if (!res.ok) throw new Error();
            } catch (err) {
                window.showToast && window.showToast('Hesaplama sırasında sorun oluştu, sayfa açılıyor…', 'warning');
            }
            window.location.href = url;
    });
});
</script>
<?php if ($expired): ?>
<div class="modal fade" id="reactivateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Teklifi Yeniden Aktifleştir</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body">Bu teklifi yeniden aktifleştirmek istediğinizden emin misiniz?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
        <button type="button" class="btn btn-primary" id="reactivateConfirm">Evet, Aktifleştir</button>
      </div>
    </div>
  </div>
</div>
<div class="toast-container position-fixed top-0 end-0 p-3">
  <div id="reactivateToast" class="toast text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">Teklif yeniden aktifleştirildi.</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Kapat"></button>
    </div>
  </div>
</div>
<?php endif; ?>
<script>
const offerExpired = <?= $expired ? 'true' : 'false' ?>;
if (offerExpired) {
    document.querySelectorAll('input, select, textarea, button').forEach(el => {
        if (!el.closest('#reactivateModal') && el.id !== 'reactivateTrigger') {
            el.disabled = true;
        }
    });
    document.querySelectorAll('.offer-action').forEach(el => {
        el.setAttribute('aria-disabled','true');
        el.setAttribute('tabindex','-1');
        el.classList.add('disabled');
        if (el.tagName === 'A') {
            el.removeAttribute('href');
        }
    });
    document.querySelectorAll('.offer-row-action').forEach(el => el.classList.add('d-none'));
}
document.getElementById('reactivateConfirm')?.addEventListener('click', async function(){
    const fd = new FormData();
    fd.append('csrf_token','<?= e($csrfToken) ?>');
    const res = await fetch('/offers/<?= e((string)$offer['id']) ?>/reactivate', {method:'POST', body: fd});
    const data = await res.json();
    if (data.success) {
        const toastEl = document.getElementById('reactivateToast');
        toastEl && new bootstrap.Toast(toastEl).show();
        setTimeout(()=>location.reload(),1500);
    } else {
        alert(data.error || 'Hata');
    }
});
</script>
<?php require __DIR__ . '/footer.php'; ?>
