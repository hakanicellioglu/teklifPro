<?php
// Initialize or resume a secure session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated users to login page
if (empty($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

require __DIR__ . '/config.php';

// Fetch the current user's role from the database
$stmt = $pdo->prepare('SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :id');
$stmt->execute(['id' => $_SESSION['user_id']]);
$role = $stmt->fetchColumn() ?: 'user';


// Fetch logged-in user's display name (first_name + last_name, fallback: username)
$uStmt = $pdo->prepare('SELECT 
    TRIM(CONCAT(first_name, " ", last_name)) AS full_name, 
    username 
  FROM users 
  WHERE id = :id');
$uStmt->execute(['id' => $_SESSION['user_id']]);
$u = $uStmt->fetch(PDO::FETCH_ASSOC);

$userName = 'User';
if ($u) {
    if (!empty($u['full_name'])) {
        $userName = $u['full_name'];
    } elseif (!empty($u['username'])) {
        $userName = $u['username'];
    }
}


?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>TeklifPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="dashboard">TeklifPro</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="customer"><i class="bi bi-people-fill me-1" aria-hidden="true"></i>Customers</a></li>
                    <li class="nav-item"><a class="nav-link" href="products"><i class="bi bi-box-seam me-1" aria-hidden="true"></i>Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="quotations"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>Quotations</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="settings">Settings</a></li>
                            <li><a class="dropdown-item" href="logout">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Page content should follow -->