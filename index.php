<?php
require __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');

if ($uri === '' || $uri === 'index.php') {
    $uri = 'login';
}

$publicRoutes = ['login', 'register'];

if (!in_array($uri, $publicRoutes, true) && empty($_SESSION['user_id'])) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(403);
    view('errors/403');
    exit;
}

$file = __DIR__ . '/' . $uri . '.php';

if (is_file($file)) {
    include $file;
    exit;
}

if (ob_get_length()) {
    ob_clean();
}
http_response_code(404);
view('errors/404');
exit;
