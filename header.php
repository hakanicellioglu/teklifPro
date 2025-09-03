<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}
require_once __DIR__ . '/config.php';

$stmt = $pdo->prepare('SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :id');
$stmt->execute(['id' => $_SESSION['user_id']]);
$role = $stmt->fetchColumn() ?: 'user';

$uStmt = $pdo->prepare('SELECT TRIM(CONCAT(first_name, " ", last_name)) AS full_name, username FROM users WHERE id = :id');
$uStmt->execute(['id' => $_SESSION['user_id']]);
$u = $uStmt->fetch(PDO::FETCH_ASSOC);
$userName = $u['full_name'] ?: ($u['username'] ?? 'User');
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TeklifPro</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/app.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
</head>
<body>
<a class="visually-hidden-focusable" href="#content">İçeriğe geç</a>
<nav class="navbar navbar-expand-lg bg-body-secondary shadow-sm d-print-none" role="navigation">
  <div class="container">
    <a class="navbar-brand" href="dashboard">TeklifPro</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Menüyü Aç">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="customer"><i class="bi bi-people-fill me-1"></i>Müşteriler</a></li>
        <li class="nav-item"><a class="nav-link" href="products"><i class="bi bi-box-seam me-1"></i>Ürünler</a></li>
        <li class="nav-item"><a class="nav-link" href="quotations"><i class="bi bi-file-earmark-text me-1"></i>Teklifler</a></li>
      </ul>
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item">
          <button id="themeToggle" class="btn btn-link nav-link p-2" title="Tema" aria-label="Tema Değiştir"><i class="bi bi-moon"></i></button>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
            <li><a class="dropdown-item" href="settings">Ayarlar</a></li>
            <li><a class="dropdown-item" href="logout">Çıkış Yap</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
<main id="content" class="container py-4">
