<?php
http_response_code(404);
require __DIR__ . '/../bootstrap.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>404 Not Found</title>
    <link rel="stylesheet" href="<?= url('assets/app.css') ?>">
</head>
<body>
<div class="container py-5 text-center">
    <h1>404 - Page Not Found</h1>
    <p>The page you requested could not be found.</p>
    <a href="<?= url('') ?>">Return home</a>
</div>
</body>
</html>
