<?php
// helpers/auth.php
declare(strict_types=1);

/** Oturumdaki kullanıcı (array|null) */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

/** Basit rol kontrolü: user['role'] alandan (admin/user vs.) */
function has_role(string $role): bool {
    $u = current_user();
    return $u && isset($u['role']) && $u['role'] === $role;
}

/** Koruma: giriş yoksa login'e yönlendir */
function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['flash'] = 'Lütfen giriş yapın.';
        redirect('/login.php');
    }
}

/** Koruma: belirli rol gerekliyse */
function require_role(string $role): void {
    require_login();
    if (!has_role($role)) {
        redirect('/403.php');
    }
}

/** Giriş (örnek: users tablosu -> email, password_hash, role) */
function login(PDO $pdo, string $email, string $password): bool {
    $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, password AS password_hash, role, status, created_at 
                           FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $u = $stmt->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        // Rehash gerekiyorsa
        if (password_needs_rehash($u['password_hash'], PASSWORD_BCRYPT)) {
            $new = password_hash($password, PASSWORD_BCRYPT);
            $up  = $pdo->prepare('UPDATE users SET password = :p WHERE id = :id');
            $up->execute([':p' => $new, ':id' => $u['id']]);
        }
        // Oturuma minimal bilgi
        $_SESSION['user'] = [
            'id'         => (int)$u['id'],
            'first_name' => $u['first_name'],
            'last_name'  => $u['last_name'],
            'email'      => $u['email'],
            'role'       => $u['role'] ?? 'user',
            'status'     => $u['status'] ?? '',
            'created_at' => $u['created_at'] ?? null,
        ];
        // Session fixation önleme
        session_regenerate_id(true);
        return true;
    }
    return false;
}

/** Çıkış */
function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
