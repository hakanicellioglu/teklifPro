<?php
// Initialize or resume a secure session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated users to login page
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require __DIR__ . '/config.php';

// Fetch the current user's role from the database
$stmt = $pdo->prepare('SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :id');
$stmt->execute(['id' => $_SESSION['user_id']]);
$role = $stmt->fetchColumn() ?: 'user';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>TeklifPro</title>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">TeklifPro</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="customers.php">Customers</a></li>
                <?php if ($role === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="products.php">Products</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="quotations.php">Quotations</a></li>
                <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Page content should follow -->
