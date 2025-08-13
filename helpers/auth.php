<?php
function user() {
    return $_SESSION['user'] ?? null;
}

function auth_required() {
    if (!user()) {
        redirect('login');
    }
}
