<?php
function page_header(string $title, string $actions = ''): void {
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h4 mb-0"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
  <div><?= $actions ?></div>
</div>
<?php
}
