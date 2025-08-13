<h2>Oturum Aç</h2>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">
    <div class="mb-3">
        <label for="username" class="form-label"><i class="bi bi-person me-1"></i>Kullanıcı Adı</label>
        <input type="text" class="form-control" id="username" name="username" required>
    </div>
    <div class="mb-3">
        <label for="password" class="form-label"><i class="bi bi-lock me-1"></i>Parola</label>
        <input type="password" class="form-control" id="password" name="password" required>
    </div>
    <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i>Giriş Yap</button>
    <a href="/register" class="btn btn-link"><i class="bi bi-person-plus me-1"></i>Kayıt Ol</a>
</form>
