<?php
function log_action($userId, $action, $meta = []) {
    $line = sprintf("%s\t%s\t%s\t%s\n", date('c'), $userId, $action, json_encode($meta));
    $file = getenv('LOG_CHANNEL') ?: __DIR__ . '/../storage/logs/app.log';
    file_put_contents($file, $line, FILE_APPEND);
}
