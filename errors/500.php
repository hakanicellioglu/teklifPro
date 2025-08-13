<?php
http_response_code(500);
require __DIR__ . '/../bootstrap.php';
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 Internal Server Error</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('assets/app.css') ?>">
</head>
<body class="bg-light">
<div class="container py-5 text-center">
    <h1 class="display-4">500 - Internal Server Error</h1>
    <p class="lead">Something went wrong. Please try again later.</p>
    <a href="<?= url('') ?>" class="btn btn-primary mt-3">Return home</a>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
