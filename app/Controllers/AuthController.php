<?php
namespace App\Controllers;

use PDO;

class AuthController
{
    protected PDO $db;

    public function __construct()
    {
        global $pdo; // using global from bootstrap
        $this->db = $pdo;
    }

    public function showLogin()
    {
        return view('auth/login');
    }

    public function login()
    {
        $errors = [];
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($username === '' || $password === '') {
            $errors[] = 'Both fields are required.';
        }
        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid CSRF token.';
        }
        if (!$errors) {
            $stmt = $this->db->prepare('SELECT id, password FROM users WHERE username = :username AND status = "active"');
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = ['id' => $user['id'], 'username' => $username];
                return redirect('/');
            }
            $errors[] = 'Invalid username or password.';
        }
        return view('auth/login', ['errors' => $errors]);
    }

    public function logout()
    {
        session_destroy();
        return redirect('/login');
    }
}
