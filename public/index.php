<?php
require_once __DIR__ . '/../vendor/autoload.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$routes = require __DIR__ . '/../routes/web.php';

foreach ($routes as $route) {
    list($httpMethod, $routePath, $handler) = $route;
    $pattern = '@^' . preg_replace('@\{([^/]+)\}@', '(?P<$1>[^/]+)', $routePath) . '$@';
    if ($httpMethod === $method && preg_match($pattern, $path, $matches)) {
        list($class, $action) = explode('@', $handler);
        $controller = new $class();
        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        return call_user_func_array([$controller, $action], $params);
    }
}
http_response_code(404);
echo '404 Not Found';
