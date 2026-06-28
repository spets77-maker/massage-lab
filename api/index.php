<?php
declare(strict_types=1);

$config = require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/ratelimit.php';
require_once __DIR__ . '/../includes/mail.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = trim((string) ($_GET['r'] ?? ''), '/');
$input = yml_json_input();

if ($method === 'GET' && $path === 'health') {
    yml_respond(200, ['ok' => true, 'php' => PHP_VERSION]);
}

try {
    $pdo = yml_pdo($config);
} catch (Throwable $e) {
    error_log('[yml] database: ' . $e->getMessage());
    yml_respond(500, ['error' => 'Database error. Check that db/ is writable and SQLite is enabled.']);
}

try {
    yml_dispatch_api($config, $pdo, $method, $path, $input);
} catch (Throwable $e) {
    error_log('[yml] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    $msg = !empty($config['debug']) ? $e->getMessage() : 'Server error. Check PHP error_log.';
    yml_respond(500, ['error' => $msg]);
}

function yml_dispatch_api(array $config, PDO $pdo, string $method, string $path, array $input): void
{

// ——— Auth ———
if ($method === 'POST' && $path === 'auth/login') {
    $ip = yml_client_ip();
    if (!yml_login_rate_allowed($ip)) {
        yml_respond(429, ['error' => 'Too many attempts. Try again later.']);
    }
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $password = (string) ($input['password'] ?? '');
    $st = $pdo->prepare('SELECT id, email, password_hash FROM admins WHERE email = ?');
    $st->execute([$email]);
    $row = $st->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        yml_login_rate_hit($ip);
        yml_respond(401, ['error' => 'Invalid email or password']);
    }
    yml_login_rate_reset($ip);
    $_SESSION['admin_id'] = (int) $row['id'];
    $_SESSION['admin_email'] = $row['email'];
    yml_respond(200, ['ok' => true, 'email' => $row['email']]);
}

if ($method === 'POST' && $path === 'auth/logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    yml_respond(200, ['ok' => true]);
}

if ($method === 'GET' && $path === 'auth/me') {
    if (empty($_SESSION['admin_id'])) {
        yml_respond(401, ['error' => 'Unauthorized']);
    }
    yml_respond(200, ['email' => $_SESSION['admin_email'] ?? '']);
}

// ——— Public client forms (no admin session) ———
if ($method === 'GET' && preg_match('#^public/forms/([a-f0-9]+)$#', $path, $m)) {
    $token = $m[1];
    if (strlen($token) < 16) {
        yml_respond(400, ['error' => 'Invalid link']);
    }
    $st = $pdo->prepare(
        'SELECT fl.id AS link_id, fl.revoked, fl.customer_id,
                c.first_name, c.last_name, c.email, c.phone
         FROM form_links fl
         JOIN customers c ON c.id = fl.customer_id
         WHERE fl.token = ?'
    );
    $st->execute([$token]);
    $link = $st->fetch();
    if (!$link || (int) $link['revoked'] === 1) {
        yml_respond(404, ['error' => 'This link is invalid or has expired.']);
    }
    $w = $pdo->query("SELECT body_html FROM form_templates WHERE kind = 'waiver'")->fetch();
    $i = $pdo->query("SELECT body_html FROM form_templates WHERE kind = 'intake'")->fetch();
    $st = $pdo->prepare('SELECT kind, data_json, submitted_at FROM submissions WHERE link_id = ? ORDER BY submitted_at');
    $st->execute([(int) $link['link_id']]);
    $subs = $st->fetchAll();
    $waiverDone = false;
    $intakeDone = false;
    foreach ($subs as $s) {
        if ($s['kind'] === 'waiver') {
            $waiverDone = true;
        }
        if ($s['kind'] === 'intake') {
            $intakeDone = true;
        }
    }
    $subOut = [];
    foreach ($subs as $s) {
        $d = json_decode($s['data_json'], true);
        $subOut[] = [
            'kind' => $s['kind'],
            'submitted_at' => $s['submitted_at'],
            'data' => is_array($d) ? $d : [],
        ];
    }
    yml_respond(200, [
        'customer' => [
            'first_name' => $link['first_name'],
            'last_name' => $link['last_name'],
            'email' => $link['email'],
            'phone' => $link['phone'],
        ],
        'templates' => [
            'waiver_html' => $w ? (string) $w['body_html'] : '',
            'intake_html' => $i ? (string) $i['body_html'] : '',
        ],
        'status' => ['waiver_done' => $waiverDone, 'intake_done' => $intakeDone],
        'submissions' => $subOut,
    ]);
}

if ($method === 'POST' && preg_match('#^public/forms/([a-f0-9]+)/submit$#', $path, $m)) {
    $token = $m[1];
    $kind = $input['kind'] ?? '';
    if ($kind !== 'waiver' && $kind !== 'intake') {
        yml_respond(400, ['error' => 'Invalid form type']);
    }
    $st = $pdo->prepare('SELECT fl.id AS link_id, fl.revoked, fl.customer_id FROM form_links fl WHERE fl.token = ?');
    $st->execute([$token]);
    $link = $st->fetch();
    if (!$link || (int) $link['revoked'] === 1) {
        yml_respond(404, ['error' => 'This link is invalid or has expired.']);
    }
    $linkId = (int) $link['link_id'];
    $st = $pdo->prepare('SELECT id FROM submissions WHERE link_id = ? AND kind = ?');
    $st->execute([$linkId, $kind]);
    if ($st->fetch()) {
        yml_respond(409, ['error' => 'This form was already submitted.']);
    }
    $data = isset($input['data']) && is_array($input['data']) ? $input['data'] : [];
    $signature = trim((string) ($data['signature'] ?? ''));
    $agreed = !empty($data['agreed']);
    if (!$agreed || $signature === '') {
        yml_respond(400, ['error' => 'Please check the agreement box and sign with your full name.']);
    }
    $payload = array_merge($data, [
        'signature' => $signature,
        'agreed' => true,
        'submitted_client_at' => gmdate('c'),
    ]);
    if ($kind === 'intake') {
        $payload['medications'] = trim((string) ($data['medications'] ?? ''));
        $payload['conditions'] = trim((string) ($data['conditions'] ?? ''));
        $payload['allergies'] = trim((string) ($data['allergies'] ?? ''));
        $payload['other_notes'] = trim((string) ($data['other_notes'] ?? ''));
    }
    $dataJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $st = $pdo->prepare('INSERT INTO submissions (customer_id, link_id, kind, data_json, admin_seen) VALUES (?,?,?,?,0)');
    $st->execute([(int) $link['customer_id'], $linkId, $kind, $dataJson]);
    $sid = (int) $pdo->lastInsertId();
    $st = $pdo->prepare('SELECT first_name, last_name FROM customers WHERE id = ?');
    $st->execute([(int) $link['customer_id']]);
    $cust = $st->fetch();
    $name = $cust ? trim($cust['first_name'] . ' ' . $cust['last_name']) : '';
    yml_notify_admin_submission($config, $kind, $name, $sid);
    yml_respond(200, ['ok' => true, 'id' => $sid]);
}

yml_require_admin();

// ——— Templates ———
if ($method === 'GET' && preg_match('#^admin/templates/(waiver|intake)$#', $path, $m)) {
    $kind = $m[1];
    $st = $pdo->prepare('SELECT kind, body_html, updated_at FROM form_templates WHERE kind = ?');
    $st->execute([$kind]);
    $row = $st->fetch();
    if (!$row) {
        yml_respond(404, ['error' => 'Not found']);
    }
    yml_respond(200, $row);
}

if (($method === 'PUT' || $method === 'POST') && preg_match('#^admin/templates/(waiver|intake)$#', $path, $m)) {
    $kind = $m[1];
    $bodyHtml = isset($input['body_html']) && is_string($input['body_html']) ? $input['body_html'] : '';
    $st = $pdo->prepare("UPDATE form_templates SET body_html = ?, updated_at = datetime('now') WHERE kind = ?");
    $st->execute([$bodyHtml, $kind]);
    $st = $pdo->prepare('SELECT kind, body_html, updated_at FROM form_templates WHERE kind = ?');
    $st->execute([$kind]);
    yml_respond(200, $st->fetch());
}

// ——— Customers ———
if ($method === 'GET' && $path === 'admin/customers') {
    $sql = "SELECT c.*,
        (SELECT COUNT(*) FROM sop_notes s WHERE s.customer_id = c.id) AS sop_count,
        (SELECT body FROM sop_notes s WHERE s.customer_id = c.id ORDER BY created_at DESC LIMIT 1) AS sop_preview
       FROM customers c ORDER BY c.created_at DESC";
    yml_respond(200, $pdo->query($sql)->fetchAll());
}

if ($method === 'POST' && $path === 'admin/customers') {
    $fn = trim((string) ($input['first_name'] ?? ''));
    $ln = trim((string) ($input['last_name'] ?? ''));
    $phone = trim((string) ($input['phone'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    if ($fn === '' || $ln === '') {
        yml_respond(400, ['error' => 'First and last name are required']);
    }
    $st = $pdo->prepare("INSERT INTO customers (first_name, last_name, phone, email, updated_at) VALUES (?,?,?,?, datetime('now'))");
    $st->execute([$fn, $ln, $phone, $email]);
    $id = (int) $pdo->lastInsertId();
    $st = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $st->execute([$id]);
    yml_respond(201, $st->fetch());
}

if (($method === 'PATCH' || $method === 'POST') && preg_match('#^admin/customers/(\d+)$#', $path, $m)) {
    $id = (int) $m[1];
    $st = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $st->execute([$id]);
    $existing = $st->fetch();
    if (!$existing) {
        yml_respond(404, ['error' => 'Not found']);
    }
    $fn = array_key_exists('first_name', $input) ? trim((string) $input['first_name']) : $existing['first_name'];
    $ln = array_key_exists('last_name', $input) ? trim((string) $input['last_name']) : $existing['last_name'];
    $phone = array_key_exists('phone', $input) ? trim((string) $input['phone']) : $existing['phone'];
    $email = array_key_exists('email', $input) ? trim((string) $input['email']) : $existing['email'];
    if ($fn === '' || $ln === '') {
        yml_respond(400, ['error' => 'First and last name are required']);
    }
    $st = $pdo->prepare("UPDATE customers SET first_name=?, last_name=?, phone=?, email=?, updated_at=datetime('now') WHERE id=?");
    $st->execute([$fn, $ln, $phone, $email, $id]);
    $st = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $st->execute([$id]);
    yml_respond(200, $st->fetch());
}

if ($method === 'POST' && preg_match('#^admin/customers/(\d+)/send-link$#', $path, $m)) {
    $customerId = (int) $m[1];
    $st = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $st->execute([$customerId]);
    $customer = $st->fetch();
    if (!$customer) {
        yml_respond(404, ['error' => 'Customer not found']);
    }
    $token = bin2hex(random_bytes(24));
    $st = $pdo->prepare('INSERT INTO form_links (token, customer_id) VALUES (?, ?)');
    $st->execute([$token, $customerId]);
    $linkId = (int) $pdo->lastInsertId();
    $base = yml_public_base($config);
    $url = $base . '/client-forms.html?token=' . rawurlencode($token);

    $trimmedEmail = trim((string) $customer['email']);
    $emailInfo = [
        'attempted' => $trimmedEmail !== '',
        'sent' => false,
        'dev_log' => false,
        'to' => $trimmedEmail !== '' ? $trimmedEmail : null,
        'error' => null,
        'skipped_no_email' => $trimmedEmail === '',
    ];
    if ($trimmedEmail !== '') {
        $result = yml_send_client_form_link(
            $config,
            $trimmedEmail,
            (string) $customer['first_name'],
            (string) $customer['last_name'],
            $url
        );
        if ($result['ok']) {
            $emailInfo['sent'] = true;
            $emailInfo['dev_log'] = !empty($result['dev_log']);
        } elseif (($result['error'] ?? '') === 'no_transport') {
            $emailInfo['error'] = 'Email not configured. Set smtp_* in config.local.php or dev_mail_log for server error_log output.';
        } else {
            $emailInfo['error'] = $result['error'] ?? 'Send failed';
        }
    }
    yml_respond(200, ['token' => $token, 'url' => $url, 'link_id' => $linkId, 'email' => $emailInfo]);
}

if ($method === 'GET' && preg_match('#^admin/customers/(\d+)/notes$#', $path, $m)) {
    $st = $pdo->prepare('SELECT id, body, created_at FROM sop_notes WHERE customer_id = ? ORDER BY created_at DESC');
    $st->execute([(int) $m[1]]);
    yml_respond(200, $st->fetchAll());
}

if ($method === 'POST' && preg_match('#^admin/customers/(\d+)/notes$#', $path, $m)) {
    $customerId = (int) $m[1];
    $st = $pdo->prepare('SELECT id FROM customers WHERE id = ?');
    $st->execute([$customerId]);
    if (!$st->fetch()) {
        yml_respond(404, ['error' => 'Customer not found']);
    }
    $body = trim((string) ($input['body'] ?? ''));
    if ($body === '') {
        yml_respond(400, ['error' => 'Note text is required']);
    }
    $st = $pdo->prepare('INSERT INTO sop_notes (customer_id, body) VALUES (?, ?)');
    $st->execute([$customerId, $body]);
    $nid = (int) $pdo->lastInsertId();
    $st = $pdo->prepare('SELECT id, body, created_at FROM sop_notes WHERE id = ?');
    $st->execute([$nid]);
    yml_respond(201, $st->fetch());
}

if ($method === 'DELETE' && preg_match('#^admin/notes/(\d+)$#', $path, $m)) {
    $st = $pdo->prepare('DELETE FROM sop_notes WHERE id = ?');
    $st->execute([(int) $m[1]]);
    if ($st->rowCount() === 0) {
        yml_respond(404, ['error' => 'Not found']);
    }
    yml_respond(200, ['ok' => true]);
}

// ——— Submissions ———
if ($method === 'GET' && $path === 'admin/stats') {
    $n = (int) $pdo->query('SELECT COUNT(*) FROM submissions WHERE admin_seen = 0')->fetchColumn();
    yml_respond(200, ['unseen_submissions' => $n]);
}

if ($method === 'GET' && $path === 'admin/submissions') {
    $unseen = isset($_GET['unseen']) && $_GET['unseen'] === '1';
    if ($unseen) {
        $sql = "SELECT s.id, s.kind, s.data_json, s.submitted_at, s.admin_seen,
              s.customer_id, s.link_id,
              c.first_name, c.last_name, c.email, c.phone
       FROM submissions s
       JOIN customers c ON c.id = s.customer_id
       WHERE s.admin_seen = 0
       ORDER BY s.submitted_at DESC
       LIMIT 200";
    } else {
        $sql = "SELECT s.id, s.kind, s.data_json, s.submitted_at, s.admin_seen,
              s.customer_id, s.link_id,
              c.first_name, c.last_name, c.email, c.phone
       FROM submissions s
       JOIN customers c ON c.id = s.customer_id
       ORDER BY s.submitted_at DESC
       LIMIT 200";
    }
    yml_respond(200, $pdo->query($sql)->fetchAll());
}

if ($method === 'POST' && preg_match('#^admin/submissions/(\d+)/seen$#', $path, $m)) {
    $st = $pdo->prepare('UPDATE submissions SET admin_seen = 1 WHERE id = ?');
    $st->execute([(int) $m[1]]);
    if ($st->rowCount() === 0) {
        yml_respond(404, ['error' => 'Not found']);
    }
    yml_respond(200, ['ok' => true]);
}

if ($method === 'POST' && $path === 'admin/submissions/mark-all-seen') {
    $pdo->exec('UPDATE submissions SET admin_seen = 1 WHERE admin_seen = 0');
    yml_respond(200, ['ok' => true]);
}

yml_respond(404, ['error' => 'Not found']);
}
