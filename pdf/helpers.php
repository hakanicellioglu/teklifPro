<?php
declare(strict_types=1);

if (!function_exists('enc')) {
    function enc(string $s): string {
        if (function_exists('iconv')) {
            $o = @iconv('UTF-8', 'Windows-1254//TRANSLIT', $s);
            if ($o !== false) {
                return $o;
            }
        }
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($s, 'Windows-1254', 'UTF-8');
        }
        return $s;
    }
}
