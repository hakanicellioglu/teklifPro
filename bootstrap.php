<?php
if (!defined('BOOTSTRAP_LOADED')) {
    define('BOOTSTRAP_LOADED', true);

    ini_set('display_errors', '0');
    ini_set('log_errors', '1');

    function url(string $path = ''): string
    {
        return '/' . ltrim($path, '/');
    }

    function tr_money(float $v): string
    {
        return number_format($v, 2, ',', '.');
    }

    set_error_handler(function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler(function ($e) {
        error_log($e);
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code(500);
        include __DIR__ . '/errors/500.php';
        exit;
    });
}
