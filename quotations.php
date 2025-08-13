<?php
require __DIR__ . '/header.php';
require __DIR__ . '/components/page_header.php';
require __DIR__ . '/components/data_table.php';

function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$deleteId = ($_POST['action'] ?? '') === 'delete' ? (int)($_POST['id'] ?? 0) : 0;
if ($deleteId) {
    try {
        $stmt = $pdo->prepare('DELETE FROM generaloffers WHERE id = :id');
        $stmt->execute([':id'=>$deleteId]);
        $_SESSION['flash_success'] = 'Teklif silindi.';
        header('Location: quotations.php');
        exit;
    } catch (Exception $e) { $_SESSION['flash_error'] = 'Teklif silinemedi.'; }
}

$conditions = [];
$params = [];
if ($search !== '') { $conditions[] = 'CONCAT(c.first_name, " ", c.last_name) LIKE :term'; $params['term'] = "%$search%"; }
if ($status !== '') { $conditions[] = 'g.status = :status'; $params['status'] = $status; }
$sql = 'SELECT g.id, g.offer_date, g.status, CONCAT(c.first_name, " ", c.last_name) AS customer,
        COALESCE(gs.sum_total,0)+COALESCE(ss.sum_total,0) AS total_amount
        FROM generaloffers g
        LEFT JOIN customers c ON g.customer_id=c.id
        LEFT JOIN (SELECT general_offer_id, SUM(total_amount) AS sum_total FROM guillotinesystems GROUP BY general_offer_id) gs ON gs.general_offer_id=g.id
        LEFT JOIN (SELECT general_offer_id, SUM(total_amount) AS sum_total FROM slidingsystems GROUP BY general_offer_id) ss ON ss.general_offer_id=g.id';
if ($conditions) { $sql .= ' WHERE '.implode(' AND ', $conditions); }
$sql .= ' ORDER BY g.offer_date DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$offers = $stmt->fetchAll();

$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);
$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
?>
<?php page_header('Teklifler', '<a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal"><i class="bi bi-plus"></i> Yeni Teklif</a>'); ?>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-6">
    <input type="search" name="search" class="form-control" placeholder="Ara" value="<?= e($search) ?>">
  </div>
  <div class="col-md-6 d-flex align-items-center gap-2">
    <?php foreach ([""=>'Tümü','active'=>'Aktif','pending'=>'Beklemede','closed'=>'Kapalı'] as $code=>$label): ?>
      <a href="quotations?<?= http_build_query(['status'=>$code===''?null:$code, 'search'=>$search]) ?>" class="badge rounded-pill <?= $status===$code?'text-bg-primary':'text-bg-light' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
</form>
<?php if ($success): ?><div class="alert alert-success" role="alert"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php data_table_start(['#','Müşteri','Tarih','Tutar','Durum','İşlemler']); ?>
<?php if ($offers): foreach ($offers as $o): ?>
<tr>
  <td><?= (int)$o['id'] ?></td>
  <td><?= e($o['customer']) ?></td>
  <td><time datetime="<?= e($o['offer_date']) ?>"><?= e($o['offer_date']) ?></time></td>
  <td><?= number_format((float)$o['total_amount'],2,',','.') ?> ₺</td>
  <td><?= e($o['status']) ?></td>
  <td class="text-end">
    <a href="quotation_view.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Görüntüle"><i class="bi bi-eye"></i></a>
    <a href="quotation_view.php?id=<?= (int)$o['id'] ?>&edit=1" class="btn btn-sm btn-outline-secondary" title="Düzenle"><i class="bi bi-pencil"></i></a>
    <form method="post" class="d-inline">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
      <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Bu teklif silinsin mi?" title="Sil"><i class="bi bi-trash"></i></button>
    </form>
  </td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="6" class="text-center text-muted">Teklif bulunamadı.</td></tr>
<?php endif; ?>
<?php data_table_end(); ?>

<?php
// Fetch customers and companies for create modal
$customers = $pdo->query('SELECT id, first_name, last_name, company_name AS company FROM customers ORDER BY first_name')->fetchAll();
$companies = $pdo->query('SELECT id, name FROM company ORDER BY name')->fetchAll();
?>
<div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="quotation_view.php">
        <div class="modal-header">
          <h5 class="modal-title">Yeni Teklif</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Müşteri</label>
            <select name="customer_id" class="form-select" required>
              <option value="">Seçiniz</option>
              <?php foreach ($customers as $c): $label = trim($c['first_name'].' '.$c['last_name']); if (!empty($c['company'])) $label.=' ('.$c['company'].')'; ?>
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
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
          <button type="submit" class="btn btn-primary">Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
