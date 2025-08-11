<?php
require __DIR__ . '/header.php';

function e(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

// Montaj tipi etiketleri
$assemblyTypes = [
    'demonte' => 'Demonte',
    'musteri' => 'Müşteri Montajlı',
    'bayi'    => 'Bayi Montajlı',
];

$action = $_POST['action'] ?? '';
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $companyId = (int)($_POST['company_id'] ?? 0);
        $companyId = $companyId > 0 ? $companyId : null;
        $offerDate = $_POST['offer_date'] ?? '';
        $assemblyType = trim($_POST['assembly_type'] ?? '');

        if ($customerId <= 0) {
            $error = 'Müşteri zorunludur.';
        } elseif ($offerDate === '') {
            $error = 'Teklif tarihi zorunludur.';
        }

        if (!$error) {
            try {
                $stmt = $pdo->prepare('INSERT INTO generaloffers (customer_id, company_id, offer_date, assembly_type) VALUES (:customer_id, :company_id, :offer_date, :assembly_type)');
                $stmt->execute([
                    ':customer_id' => $customerId,
                    ':company_id' => $companyId,
                    ':offer_date' => $offerDate,
                    ':assembly_type' => $assemblyType ?: null,
                ]);
                $success = 'Teklif eklendi.';
            } catch (Exception $e) {
                $error = 'Teklif eklenemedi.';
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $companyId = (int)($_POST['company_id'] ?? 0);
        $companyId = $companyId > 0 ? $companyId : null;
        $offerDate = $_POST['offer_date'] ?? '';
        $assemblyType = trim($_POST['assembly_type'] ?? '');

        if ($id <= 0) {
            $error = 'Geçersiz ID.';
        } elseif ($customerId <= 0) {
            $error = 'Müşteri zorunludur.';
        } elseif ($offerDate === '') {
            $error = 'Teklif tarihi zorunludur.';
        }

        if (!$error) {
            try {
                $stmt = $pdo->prepare('UPDATE generaloffers SET customer_id = :customer_id, company_id = :company_id, offer_date = :offer_date, assembly_type = :assembly_type WHERE id = :id');
                $stmt->execute([
                    ':customer_id' => $customerId,
                    ':company_id' => $companyId,
                    ':offer_date' => $offerDate,
                    ':assembly_type' => $assemblyType ?: null,
                    ':id' => $id,
                ]);
                $success = 'Teklif güncellendi.';
            } catch (Exception $e) {
                $error = 'Teklif güncellenemedi.';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare('DELETE FROM generaloffers WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $success = 'Teklif silindi.';
            } catch (Exception $e) {
                $error = 'Teklif silinemedi.';
            }
        } else {
            $error = 'Geçersiz ID.';
        }
    }
}

// Fetch offers
try {
    $stmt = $pdo->prepare('
        SELECT 
            g.id,
            g.customer_id,
            g.company_id,
            g.offer_date,
            g.assembly_type,
            c.first_name,
            c.last_name,
            co.name AS company_name,
            COALESCE(gs.sum_total, 0) + COALESCE(ss.sum_total, 0) AS total_amount
        FROM generaloffers g
        LEFT JOIN customers c ON g.customer_id = c.id
        LEFT JOIN company co ON g.company_id = co.id
        LEFT JOIN (
            SELECT general_offer_id, SUM(total_amount) AS sum_total
            FROM guillotinesystems
            GROUP BY general_offer_id
        ) gs ON gs.general_offer_id = g.id
        LEFT JOIN (
            SELECT general_offer_id, SUM(total_amount) AS sum_total
            FROM slidingsystems
            GROUP BY general_offer_id
        ) ss ON ss.general_offer_id = g.id
        ORDER BY g.offer_date DESC
    ');
    $stmt->execute();
    $offers = $stmt->fetchAll();
} catch (Exception $e) {
    $offers = [];
    $error = $error ?: 'Teklifler alınamadı.';
}

// Fetch customers
$customers = [];
try {
    $cStmt = $pdo->prepare('SELECT id, first_name, last_name, company FROM customers ORDER BY first_name, last_name');
    $cStmt->execute();
    $customers = $cStmt->fetchAll();
} catch (Exception $e) {
    // ignore
}

// Fetch companies
$companies = [];
try {
    $coStmt = $pdo->prepare('SELECT id, name FROM company ORDER BY name');
    $coStmt->execute();
    $companies = $coStmt->fetchAll();
} catch (Exception $e) {
    // ignore
}

?>
<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-3">
                <h4 class="card-title mb-0">Teklifler</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">Yeni Teklif Ekle</button>
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
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Müşteri</th>
                            <th>Firma</th>
                            <th>Teklif Tarihi</th>
                            <th>Montaj Tipi</th>
                            <th>Toplam Tutar</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($offers): ?>
                            <?php foreach ($offers as $o): ?>
                                <tr>
                                    <td><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?></td>
                                    <td><?= e($o['company_name']) ?></td>
                                    <td><?= e($o['offer_date']) ?></td>
                                    <td><?= e($assemblyTypes[$o['assembly_type']] ?? $o['assembly_type']) ?></td>
                                    <td><?= e($o['total_amount']) ?></td>
                                    <td class="text-end">
                                        <a href="quotation_view.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-primary me-1">Görüntüle</a>
                                        <button class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#editModal<?= (int)$o['id'] ?>">Düzenle</button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Teklifi silmek istediğinize emin misiniz?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                                        </form>
                                    </td>
                                </tr>
                                <div class="modal fade" id="editModal<?= (int)$o['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Teklifi Düzenle</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Müşteri</label>
                                                        <select name="customer_id" class="form-select" required>
                                                            <option value="">Seçiniz</option>
                                                            <?php foreach ($customers as $c): ?>
                                                        <?php
                                                                    $label = trim($c['first_name'] . ' ' . $c['last_name']);
                                                                    if (!empty($c['company'])) {
                                                                        $label .= ' (' . $c['company'] . ')';
                                                                    }
                                                                ?>
                                                                <option value="<?= (int)$c['id'] ?>" <?= $c['id'] == $o['customer_id'] ? 'selected' : '' ?>><?= e($label) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Şirket</label>
                                                        <select name="company_id" class="form-select">
                                                            <option value="">Seçiniz</option>
                                                            <?php foreach ($companies as $co): ?>
                                                                <option value="<?= (int)$co['id'] ?>" <?= $co['id'] == $o['company_id'] ? 'selected' : '' ?>><?= e($co['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Teklif Tarihi</label>
                                                        <input type="date" name="offer_date" class="form-control" required value="<?= e($o['offer_date']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Montaj Tipi</label>
                                                        <select name="assembly_type" class="form-select">
                                                            <option value="">Seçiniz</option>
                                                            <?php foreach ($assemblyTypes as $code => $label): ?>
                                                                <option value="<?= e($code) ?>" <?= $code === $o['assembly_type'] ? 'selected' : '' ?>><?= e($label) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
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
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Teklif bulunamadı.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Teklif</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Müşteri</label>
                        <select name="customer_id" class="form-select" required>
                            <option value="">Seçiniz</option>
                            <?php foreach ($customers as $c): ?>
                        <?php
                            $label = trim($c['first_name'] . ' ' . $c['last_name']);
                            if (!empty($c['company'])) {
                                $label .= ' (' . $c['company'] . ')';
                            }
                        ?>
                        <option value="<?= (int)$c['id'] ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Şirket</label>
                <select name="company_id" class="form-select">
                    <option value="">Seçiniz</option>
                    <?php foreach ($companies as $co): ?>
                        <option value="<?= (int)$co['id'] ?>"><?= e($co['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Teklif Tarihi</label>
                <input type="date" name="offer_date" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Montaj Tipi</label>
                <select name="assembly_type" class="form-select">
                    <option value="">Seçiniz</option>
                    <?php foreach ($assemblyTypes as $code => $label): ?>
                        <option value="<?= e($code) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
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

</body>
</html>
