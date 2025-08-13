<?php
// helpers/auth.php
declare(strict_types=1);

/** Oturumdaki kullanıcı (array|null) */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/** Basit rol kontrolü: user['role'] alandan (admin/user vs.) */
function has_role(string $role): bool
{
    $u = current_user();
    return $u && isset($u['role']) && $u['role'] === $role;
}

/** Koruma: giriş yoksa login'e yönlendir */
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['flash'] = 'Lütfen giriş yapın.';
        redirect('/login.php');
    }
}

/** Koruma: belirli rol gerekliyse */
function require_role(string $role): void
{
    require_login();
    if (!has_role($role)) {
        redirect('/403.php');
    }
}

/** Giriş (örnek: users tablosu -> email, password_hash, role) */
function login(PDO $pdo, string $identifier, string $password): bool
{
    // email ve username için AYRI parametre isimleri kullanalım
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.username,
            u.password AS password_hash,
            u.email,
            u.status,
            u.created_at,
            r.name AS role
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        WHERE (u.email = :email OR u.username = :username) AND u.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([
        ':email'    => $identifier,
        ':username' => $identifier,
    ]);
    $u = $stmt->fetch();

    if ($u && password_verify($password, $u['password_hash'])) {
        if (password_needs_rehash($u['password_hash'], PASSWORD_BCRYPT)) {
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            $up = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
            $up->execute([':p' => $newHash, ':id' => $u['id']]);
        }

        $_SESSION['user'] = [
            'id'         => (int)$u['id'],
            'first_name' => $u['first_name'],
            'last_name'  => $u['last_name'],
            'username'   => $u['username'],
            'email'      => $u['email'],
            'role'       => $u['role'] ?: 'user',
            'status'     => $u['status'] ?? 'active',
            'created_at' => $u['created_at'] ?? null,
        ];
        session_regenerate_id(true);
        return true;
    }
    return false;
}


/** Çıkış */
function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
