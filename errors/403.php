<?php
http_response_code(403);
require __DIR__ . '/../bootstrap.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>403 Forbidden</title>
    <link rel="stylesheet" href="<?= url('assets/app.css') ?>">
</head>
<body>
<div class="container py-5 text-center">
    <h1>403 - Forbidden</h1>
    <p>You do not have permission to access this page.</p>
    <a href="<?= url('') ?>">Return home</a>
</div>
</body>
</html>
