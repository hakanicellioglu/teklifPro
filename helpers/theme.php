<?php
function current_theme() {
    return $_SESSION['theme'] ?? 'light';
}

function set_theme($theme) {
    $_SESSION['theme'] = $theme;
}
