<?php
/** @var array $quote */
/** @var array $items */
/** @var string|null $approveUrl */
/** @var float $subTotal */
/** @var float $discountTotal */
/** @var float $vatTotal */
/** @var float $grandTotal */
?>
<div class="card no-split keep-next">
  <strong>Müşteri</strong>
  <div><?= e(trim(($quote['company'] ?: $quote['first_name'].' '.$quote['last_name']))) ?></div>
  <div><?= nl2br(e($quote['address'])) ?></div>
  <div><?= e($quote['email']) ?> • <?= e($quote['phone']) ?></div>
</div>
<div class="card no-split keep-next">
  <strong>Teklif Bilgileri</strong>
  <div class="columns two">
    <div>Montaj: <?= e($quote['assembly_type'] ?? '') ?></div>
    <div>Teslim Süresi: <?= e($quote['delivery_time'] ?? '') ?></div>
  </div>
  <div class="columns two">
    <div>Ödeme Yöntemi: <?= e($quote['payment_method'] ?? '') ?></div>
    <div>Vade: <?= e($quote['payment_term'] ?? '') ?></div>
  </div>
</div>
<table class="table-items no-split">
  <thead>
    <tr>
      <th class="code">Kod</th>
      <th class="name">Ad</th>
      <th class="desc">Açıklama</th>
      <th class="unit">Birim</th>
      <th class="qty num">Adet</th>
      <th class="uprice num">Birim Fiyat</th>
      <th class="disc num">İsk %</th>
      <th class="vat num">KDV %</th>
      <th class="total num">Tutar</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($items): foreach ($items as $it):
      $line = $it['qty'] * $it['unit_price'];
      $lineDisc = $line * $it['discount_rate'] / 100;
      $lineNet = $line - $lineDisc;
      $lineVat = $lineNet * $it['vat_rate'] / 100;
      $lineTotal = $lineNet + $lineVat;
    ?>
    <tr class="no-split">
      <td><?= e($it['code']) ?></td>
      <td><?= e($it['name']) ?></td>
      <td><?= nl2br(e($it['description'])) ?></td>
      <td><?= e($it['unit']) ?></td>
      <td class="num"><?= e((string)$it['qty']) ?></td>
      <td class="num"><?= money_tr($it['unit_price']) ?></td>
      <td class="num"><?= e((string)$it['discount_rate']) ?></td>
      <td class="num"><?= e((string)$it['vat_rate']) ?></td>
      <td class="num"><?= money_tr($lineTotal) ?></td>
    </tr>
    <?php endforeach; else: ?>
    <tr>
      <td colspan="9" class="text-center">Kalem yok</td>
    </tr>
    <?php endif; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="8" class="num">Ara Toplam</td>
      <td class="num"><?= money_tr($subTotal) ?></td>
    </tr>
    <tr>
      <td colspan="8" class="num">İskonto</td>
      <td class="num"><?= money_tr($discountTotal) ?></td>
    </tr>
    <tr>
      <td colspan="8" class="num">KDV</td>
      <td class="num"><?= money_tr($vatTotal) ?></td>
    </tr>
    <tr>
      <td colspan="8" class="num"><strong>Genel Toplam</strong></td>
      <td class="num"><strong><?= money_tr($grandTotal) ?></strong></td>
    </tr>
  </tfoot>
</table>
<?php if (!empty($quote['notes'])): ?>
<div class="card no-split">
  <strong>Notlar</strong>
  <div><?= nl2br(e($quote['notes'])) ?></div>
</div>
<?php endif; ?>
<div class="card approval no-split">
  <?php if ($approveUrl): ?>
  <div>Onay için: <a href="<?= e($approveUrl) ?>"><?= e($approveUrl) ?></a></div>
  <?php endif; ?>
  <div class="signatures">
    <div>Hazırlayan</div>
    <div>Onaylayan</div>
  </div>
</div>
