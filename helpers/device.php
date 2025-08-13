<?php
function is_mobile() {
    return preg_match('/Mobile|Android|iP(hone|od|ad)/i', $_SERVER['HTTP_USER_AGENT'] ?? '') === 1;
}
