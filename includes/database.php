<?php
declare(strict_types=1);

const YML_DEFAULT_ADMIN_EMAIL = 'spets77@gmail.com';
const YML_DEFAULT_ADMIN_PASSWORD = 'spets77@gmail.com';

const YML_DEFAULT_WAIVER = '<p><strong>Client waiver</strong></p>
<p>Please read carefully. By signing below you acknowledge that you have read and agree to this waiver.</p>
<ul>
  <li>I understand that massage therapy is not a substitute for medical care.</li>
  <li>I have disclosed health information truthfully on my intake form.</li>
  <li>I release the therapist from liability to the extent permitted by law for services received.</li>
</ul>
<p><em>Edit this text in Admin → Templates.</em></p>';

const YML_DEFAULT_INTAKE = '<p><strong>Health intake</strong></p>
<p>Please complete the questions below honestly so we can provide safe, appropriate care.</p>
<p><em>Edit this text in Admin → Templates.</em></p>';

function yml_db_path(array $config): string
{
    $p = $config['db_path'] ?? (dirname(__DIR__) . '/db/app.sqlite');
    $dir = dirname($p);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $p;
}

function yml_pdo(array $config): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $path = yml_db_path($config);
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 8000');
    yml_migrate($pdo);
    yml_seed($pdo);
    return $pdo;
}

function yml_migrate(PDO $pdo): void
{
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS admins (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      email TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS form_templates (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      kind TEXT NOT NULL UNIQUE CHECK (kind IN ('waiver', 'intake')),
      body_html TEXT NOT NULL,
      updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS customers (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      first_name TEXT NOT NULL,
      last_name TEXT NOT NULL,
      phone TEXT NOT NULL DEFAULT '',
      email TEXT NOT NULL DEFAULT '',
      created_at TEXT NOT NULL DEFAULT (datetime('now')),
      updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS form_links (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      token TEXT NOT NULL UNIQUE,
      customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
      created_at TEXT NOT NULL DEFAULT (datetime('now')),
      revoked INTEGER NOT NULL DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS submissions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
      link_id INTEGER NOT NULL REFERENCES form_links(id) ON DELETE CASCADE,
      kind TEXT NOT NULL CHECK (kind IN ('waiver', 'intake')),
      data_json TEXT NOT NULL,
      submitted_at TEXT NOT NULL DEFAULT (datetime('now')),
      admin_seen INTEGER NOT NULL DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS sop_notes (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
      body TEXT NOT NULL,
      created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE INDEX IF NOT EXISTS idx_submissions_seen ON submissions(admin_seen);
    CREATE INDEX IF NOT EXISTS idx_form_links_token ON form_links(token);
    CREATE INDEX IF NOT EXISTS idx_submissions_customer ON submissions(customer_id);
    ");
}

function yml_seed(PDO $pdo): void
{
    $n = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($n === 0) {
        $hash = password_hash(YML_DEFAULT_ADMIN_PASSWORD, PASSWORD_BCRYPT);
        $st = $pdo->prepare('INSERT INTO admins (email, password_hash) VALUES (?, ?)');
        $st->execute([strtolower(YML_DEFAULT_ADMIN_EMAIL), $hash]);
    }

    $t = (int) $pdo->query('SELECT COUNT(*) FROM form_templates')->fetchColumn();
    if ($t === 0) {
        $ins = $pdo->prepare('INSERT INTO form_templates (kind, body_html) VALUES (?, ?)');
        $ins->execute(['waiver', YML_DEFAULT_WAIVER]);
        $ins->execute(['intake', YML_DEFAULT_INTAKE]);
    }
}
