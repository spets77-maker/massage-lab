<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

/**
 * Only treat the request as HTTPS when it actually is (avoids dropping session on http://localhost
 * when config.local.php has cookie_secure true for production).
 */
function yml_request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if ($xfp !== '') {
        $first = strtolower(trim(explode(',', (string) $xfp)[0]));
        if ($first === 'https') {
            return true;
        }
    }
    return false;
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_name('ymlphp');
    $secureCookie = !empty($config['cookie_secure']) && yml_request_is_https();
    session_set_cookie_params([
        'lifetime' => 7 * 24 * 60 * 60,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

return $config;
