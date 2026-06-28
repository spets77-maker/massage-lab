<?php
/**
 * Copy to config.local.php in the same folder (next to index.html).
 * Do not commit config.local.php — it contains secrets.
 *
 * All keys are optional; defaults are in includes/config.php
 */
return [
    'public_base_url' => 'https://yuliasmassagelab.com',
    // When true, session cookies use Secure only on real HTTPS requests (safe for php -S http://localhost).
    'cookie_secure' => true,
    'dev_mail_log' => false,
    // Set true temporarily to see exception messages in JSON (not for production).
    // 'debug' => true,

    // Local: php -S localhost:8080 router.php
    // 'public_base_url' => 'http://localhost:8080',
    // 'cookie_secure' => false,
    // 'dev_mail_log' => true,

    'db_path' => __DIR__ . '/db/app.sqlite',

    'admin_notify_email' => 'you@example.com',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_secure' => false,
    'smtp_user' => '',
    'smtp_pass' => '',
    'smtp_from' => 'Yulia\'s Massage Lab <you@example.com>',
];
