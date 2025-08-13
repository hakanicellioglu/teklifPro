<?php
use Dotenv\Dotenv;

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'name' => getenv('SESSION_NAME') ?: 'teklifpro_session'
    ]);
}

$root = dirname(__DIR__);

if (file_exists($root . '/.env')) {
    $dotenv = Dotenv::createImmutable($root);
    $dotenv->load();
}

$config = require $root . '/config/app.php';
date_default_timezone_set($config['timezone']);

$db = require $root . '/config/database.php';
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;port=%s;charset=%s', $db['host'], $db['database'], $db['port'], $db['charset']),
        $db['username'],
        $db['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec('SET NAMES ' . $db['charset'] . ' COLLATE ' . $db['collation']);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

function view(string $name, array $data = []) {
    extract($data);
    $path = __DIR__ . '/../app/Views/' . str_replace('.', '/', $name) . '.php';
    ob_start();
    require $path;
    $content = ob_get_clean();
    $layout = __DIR__ . '/../app/Views/layouts/main.php';
    if (file_exists($layout)) {
        require $layout;
    } else {
        echo $content;
    }
}

function redirect(string $path) {
    header('Location: ' . $path);
    exit;
}
