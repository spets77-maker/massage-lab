<?php
declare(strict_types=1);

function yml_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function yml_login_rate_allowed(string $ip): bool
{
    $file = sys_get_temp_dir() . '/yml_login_' . md5($ip) . '.json';
    $now = time();
    $window = 15 * 60;
    $max = 20;
    if (!is_readable($file)) {
        return true;
    }
    $raw = @file_get_contents($file);
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        return true;
    }
    $t = (int) ($data['t'] ?? 0);
    $n = (int) ($data['n'] ?? 0);
    if ($now - $t > $window) {
        return true;
    }
    return $n < $max;
}

function yml_login_rate_hit(string $ip): void
{
    $file = sys_get_temp_dir() . '/yml_login_' . md5($ip) . '.json';
    $now = time();
    $window = 15 * 60;
    $n = 1;
    if (is_readable($file)) {
        $data = json_decode((string) file_get_contents($file), true);
        if (is_array($data)) {
            $t = (int) ($data['t'] ?? 0);
            if ($now - $t <= $window) {
                $n = (int) ($data['n'] ?? 0) + 1;
            }
        }
    }
    file_put_contents($file, json_encode(['t' => $now, 'n' => $n]));
}

function yml_login_rate_reset(string $ip): void
{
    $file = sys_get_temp_dir() . '/yml_login_' . md5($ip) . '.json';
    @unlink($file);
}
