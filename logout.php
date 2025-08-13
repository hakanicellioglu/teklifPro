<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/utils.php';
require_once __DIR__ . '/helpers/auth.php';

// Oturumu kapat
logout();

// Giriş sayfasına yönlendir
redirect('/login.php');
