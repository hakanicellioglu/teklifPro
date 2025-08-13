<?php
http_response_code(500);
require __DIR__ . '/../bootstrap.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>500 Internal Server Error</title>
    <link rel="stylesheet" href="<?= url('assets/app.css') ?>">
</head>
<body>
<div class="container py-5 text-center">
    <h1>500 - Internal Server Error</h1>
    <p>Something went wrong. Please try again later.</p>
    <a href="<?= url('') ?>">Return home</a>
</div>
</body>
</html>
