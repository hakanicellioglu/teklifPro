<?php
/** @var array $company */
/** @var array $quote */
$logoPath = __DIR__ . '/logo.png';
?>
<table width="100%">
  <tr>
    <td style="width:60%; vertical-align:top;">
      <div class="columns two" style="gap:0;">
        <div style="width:25%;">
          <?php if (is_file($logoPath)): ?>
            <img src="<?= $logoPath ?>" style="max-height:30mm;" alt="Logo">
          <?php endif; ?>
        </div>
        <div style="width:75%;">
          <strong><?= e($company['name']) ?></strong><br>
          <?= nl2br(e($company['address'])) ?><br>
          <?= e($company['email']) ?> • <?= e($company['phone']) ?>
        </div>
      </div>
    </td>
    <td style="width:40%; text-align:right;">
      <div class="keep-next"><span style="font-size:20px; font-weight:bold;">TEKLİF</span></div>
      <div>Teklif No: <?= e($quote['quote_no'] ?? $quote['id']) ?></div>
      <div>Tarih: <?= e(date('d.m.Y', strtotime($quote['created_at'] ?? 'now'))) ?></div>
      <?php if (!empty($quote['valid_until'])): ?>
      <div>Geçerlilik: <?= e(date('d.m.Y', strtotime($quote['valid_until']))) ?></div>
      <?php endif; ?>
    </td>
  </tr>
</table>
