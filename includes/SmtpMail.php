<?php
declare(strict_types=1);

/**
 * Minimal SMTP client (STARTTLS + AUTH LOGIN). No Composer.
 */
final class YmlSmtpMail
{
    /**
     * @return array{ok: bool, dev_log?: bool, error?: string}
     */
    public static function send(array $cfg, string $to, string $subject, string $text, string $html): array
    {
        $host = trim((string) ($cfg['smtp_host'] ?? ''));
        if ($host === '') {
            if (!empty($cfg['dev_mail_log'])) {
                error_log("[YML mail dev]\nTo: {$to}\nSubject: {$subject}\n\n{$text}");
                return ['ok' => true, 'dev_log' => true];
            }
            return ['ok' => false, 'error' => 'no_transport'];
        }

        $port = (int) ($cfg['smtp_port'] ?? 587);
        $user = (string) ($cfg['smtp_user'] ?? '');
        $pass = (string) ($cfg['smtp_pass'] ?? '');
        $from = (string) ($cfg['smtp_from'] ?? $user);
        $implicitSsl = !empty($cfg['smtp_secure']) && $port === 465;

        $sock = null;
        try {
            $target = $implicitSsl ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
            $errno = 0;
            $errstr = '';
            $sock = @stream_socket_client($target, $errno, $errstr, 30, STREAM_CLIENT_CONNECT);
            if ($sock === false) {
                return ['ok' => false, 'error' => $errstr ?: 'connect failed'];
            }
            stream_set_timeout($sock, 45);

            self::expect($sock, [220]);

            self::sendLine($sock, 'EHLO yuliasmassagelab');
            self::expect($sock, [250]);

            if (!$implicitSsl) {
                self::sendLine($sock, 'STARTTLS');
                self::expect($sock, [220]);
                $crypto = defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')
                    ? STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT
                    : STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (!stream_socket_enable_crypto($sock, true, $crypto)) {
                    throw new RuntimeException('TLS handshake failed');
                }
                self::sendLine($sock, 'EHLO yuliasmassagelab');
                self::expect($sock, [250]);
            }

            if ($user !== '') {
                self::sendLine($sock, 'AUTH LOGIN');
                self::expect($sock, [334]);
                self::sendLine($sock, base64_encode($user));
                self::expect($sock, [334]);
                self::sendLine($sock, base64_encode($pass));
                self::expect($sock, [235]);
            }

            $fromAddr = self::extractAddr($from);
            self::sendLine($sock, 'MAIL FROM:<' . $fromAddr . '>');
            self::expect($sock, [250]);
            self::sendLine($sock, 'RCPT TO:<' . trim($to) . '>');
            self::expect($sock, [250, 251]);
            self::sendLine($sock, 'DATA');
            self::expect($sock, [354]);

            $boundary = 'bnd_' . bin2hex(random_bytes(8));
            $subj = self::encodeSubject($subject);
            $lines = [
                'From: ' . $from,
                'To: ' . $to,
                'Subject: ' . $subj,
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
                '',
                '--' . $boundary,
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode($text), 76, "\r\n"),
                '--' . $boundary,
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode($html), 76, "\r\n"),
                '--' . $boundary . '--',
                '',
            ];
            $payload = implode("\r\n", $lines);
            $payload = str_replace("\r\n.", "\r\n..", $payload);
            fwrite($sock, $payload . "\r\n.\r\n");
            self::expect($sock, [250]);

            self::sendLine($sock, 'QUIT');
            self::expect($sock, [221]);
            fclose($sock);
            return ['ok' => true, 'dev_log' => false];
        } catch (Throwable $e) {
            if (is_resource($sock)) {
                @fclose($sock);
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private static function sendLine($sock, string $line): void
    {
        fwrite($sock, $line . "\r\n");
    }

    /**
     * @param resource $sock
     * @param int[] $codes
     */
    private static function expect($sock, array $codes): void
    {
        $last = '';
        $code = 0;
        while (true) {
            $line = fgets($sock, 8192);
            if ($line === false) {
                throw new RuntimeException('SMTP connection closed');
            }
            $last = $line;
            $code = (int) substr($line, 0, 3);
            $continuation = strlen($line) > 3 && $line[3] === '-';
            if (!$continuation) {
                break;
            }
        }
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('SMTP: ' . trim($last));
        }
    }

    private static function extractAddr(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $m)) {
            return trim($m[1]);
        }
        return trim($from);
    }

    private static function encodeSubject(string $s): string
    {
        if (preg_match('/[^\x20-\x7E]/', $s)) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        }
        return $s;
    }
}
