<?php
function form_group(string $id, string $label, string $inputHtml, string $help = '', string $error = ''): void {
?>
<div class="mb-3">
  <label for="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>" class="form-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
  <?= $inputHtml ?>
  <?php if ($help): ?><div class="form-text"><?= htmlspecialchars($help, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
</div>
<?php
}
