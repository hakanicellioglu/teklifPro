<?php
// helpers/utils.php
declare(strict_types=1);

/** HTML escape */
function e(?string $v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

/** Güvenli yönlendirme ve çıkış */
function redirect(string $url, int $code = 302): never {
    header('Location: ' . $url, true, $code);
    exit;
}

/** CSRF token üret/al */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Formlarda gizli alan */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">';
}

/** CSRF doğrulama */
function verify_csrf(?string $token): bool {
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/** Tipik filtreler */
function in_int(string $key, int $default = 0, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int {
    $v = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
    if ($v === false || $v === null) $v = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
    $v = ($v === false || $v === null) ? $default : $v;
    return max($min, min($max, (int)$v));
}

function in_str(string $key, string $default = ''): string {
    $v = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW) ?? filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
    return is_string($v) ? trim($v) : $default;
}

/** Basit sayfalama yardımcıları */
function paginate(int $total, int $perPage = 20): array {
    $page = max(1, in_int('page', 1));
    $pages = max(1, (int)ceil($total / $perPage));
    $offset = ($page - 1) * $perPage;
    return ['page'=>$page, 'pages'=>$pages, 'limit'=>$perPage, 'offset'=>$offset];
}
