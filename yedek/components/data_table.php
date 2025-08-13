<?php
function data_table_start(array $headers): void {
?>
<div class="table-responsive">
<table class="table table-hover align-middle">
  <thead class="table-light sticky-top">
    <tr>
      <?php foreach ($headers as $h): ?>
        <th scope="col"><?= htmlspecialchars($h, ENT_QUOTES, 'UTF-8'); ?></th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
<?php
}

function data_table_end(): void {
?>
  </tbody>
</table>
</div>
<?php
}
