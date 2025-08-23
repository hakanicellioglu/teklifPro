<?php
http_response_code(500);
require_once __DIR__ . '/../../../bootstrap.php';
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>500 Internal Server Error</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-5">
    <div class="alert alert-danger text-center">Sunucu hatası oluştu.</div>
</div>
</body>
</html>
