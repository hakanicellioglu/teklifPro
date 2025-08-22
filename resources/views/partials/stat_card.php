<?php
function stat_card(string $icon, string $title, string $value): void {
?>
<div class="col-md-4">
  <div class="card text-center h-100 shadow-sm">
    <div class="card-body">
      <i class="bi <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?> fs-1" aria-hidden="true"></i>
      <h5 class="mt-2"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h5>
      <p class="display-6 mb-0"><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
  </div>
</div>
<?php
}
