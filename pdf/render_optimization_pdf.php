<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

http_response_code(501);
exit('PDF generation functionality has been removed.');
