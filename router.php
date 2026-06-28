<?php
/**
 * Local dev: php -S localhost:8080 router.php
 *
 * Pretty URLs /api/foo → api/index.php?r=foo
 * Direct /api/index.php?r=foo must NOT be rewritten to r=index.php (see below).
 */
declare(strict_types=1);

$pathOnly = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$pathOnly = $pathOnly !== false && $pathOnly !== '' ? $pathOnly : '/';
$pathOnly = urldecode($pathOnly);

if ($pathOnly === '/api/index.php') {
    require __DIR__ . '/api/index.php';
    return true;
}

if (preg_match('#^/api/(.*)$#', $pathOnly, $m)) {
    $_GET['r'] = $m[1];
    require __DIR__ . '/api/index.php';
    return true;
}

return false;
