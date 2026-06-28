<?php
declare(strict_types=1);

$defaults = [
    'public_base_url' => '',
    'db_path' => dirname(__DIR__) . '/db/app.sqlite',
    'cookie_secure' => false,
    'debug' => false,
    'dev_mail_log' => false,
    'admin_notify_email' => '',
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_secure' => false,
    'smtp_user' => '',
    'smtp_pass' => '',
    'smtp_from' => '',
];

$local = dirname(__DIR__) . '/config.local.php';
if (is_readable($local)) {
    $extra = require $local;
    if (is_array($extra)) {
        $defaults = array_merge($defaults, $extra);
    }
}

return $defaults;
