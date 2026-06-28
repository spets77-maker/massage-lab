<?php
declare(strict_types=1);

require_once __DIR__ . '/SmtpMail.php';

function yml_notify_admin_submission(array $config, string $kind, string $customerName, int $submissionId): void
{
    $to = trim((string) ($config['admin_notify_email'] ?? ''));
    if ($to === '') {
        $to = trim((string) ($config['smtp_user'] ?? ''));
    }
    if ($to === '') {
        return;
    }
    $subject = "[YML] Client submitted {$kind} form (#{$submissionId})";
    $text = ($customerName !== '' ? $customerName : 'A client') . " submitted the {$kind} form.\nSubmission id: {$submissionId}\n";
    $html = '<p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
    $from = (string) ($config['smtp_from'] ?: $config['smtp_user'] ?: 'noreply@localhost');
    YmlSmtpMail::send($config, $to, $subject, $text, $html);
}

/**
 * @return array{ok: bool, dev_log?: bool, error?: string}
 */
function yml_send_client_form_link(array $config, string $to, string $firstName, string $lastName, string $url): array
{
    $name = trim($firstName . ' ' . $lastName);
    if ($name === '') {
        $name = 'there';
    }
    $subject = "Complete your forms — Yulia's Massage Lab";
    $text = "Hi {$name},\n\nPlease use this personal link to complete your waiver and health intake (works on phone or computer):\n\n{$url}\n\nIf the link does not open, copy and paste it into your browser.\n\nThank you,\nYulia's Massage Lab";
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $html = "<p>Hi {$safeName},</p><p>Please use this personal link to complete your <strong>waiver</strong> and <strong>health intake</strong>:</p><p><a href=\"{$safeUrl}\">{$safeUrl}</a></p><p>If the link does not open, copy and paste it into your browser.</p><p>Thank you,<br>Yulia's Massage Lab</p>";
    return YmlSmtpMail::send($config, $to, $subject, $text, $html);
}
