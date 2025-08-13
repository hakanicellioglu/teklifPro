<?php ?>
<nav class="navbar navbar-expand-lg bg-body-secondary mb-3">
  <div class="container">
    <a class="navbar-brand" href="/">TeklifPro</a>
    <ul class="navbar-nav ms-auto">
      <?php if (user()): ?>
        <li class="nav-item"><a class="nav-link" href="/logout">Çıkış</a></li>
      <?php else: ?>
        <li class="nav-item"><a class="nav-link" href="/login">Giriş</a></li>
      <?php endif; ?>
    </ul>
  </div>
</nav>
