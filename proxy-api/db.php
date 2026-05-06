<?php
function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS licenses (
            id                    INTEGER PRIMARY KEY AUTOINCREMENT,
            license_key           TEXT UNIQUE,
            email                 TEXT NOT NULL,
            site_url              TEXT DEFAULT '',
            pf_subscription_token TEXT DEFAULT '',
            pf_payment_id         TEXT DEFAULT '',
            session_token         TEXT DEFAULT '',
            status                TEXT NOT NULL DEFAULT 'pending',
            monthly_requests      INTEGER NOT NULL DEFAULT 0,
            monthly_limit         INTEGER NOT NULL DEFAULT 200,
            usage_reset_date      TEXT NOT NULL DEFAULT '',
            created_at            TEXT NOT NULL DEFAULT (datetime('now')),
            last_used_at          TEXT DEFAULT ''
        );
        CREATE INDEX IF NOT EXISTS idx_key      ON licenses(license_key);
        CREATE INDEX IF NOT EXISTS idx_session  ON licenses(session_token);
        CREATE INDEX IF NOT EXISTS idx_pf_token ON licenses(pf_subscription_token);
    ");
    // Migration: add columns if upgrading from older schema
    $cols = array_column($pdo->query('PRAGMA table_info(licenses)')->fetchAll(), 'name');
    if (!in_array('pf_subscription_token', $cols)) {
        $pdo->exec("ALTER TABLE licenses ADD COLUMN pf_subscription_token TEXT DEFAULT ''");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pf_token ON licenses(pf_subscription_token)");
    }
    if (!in_array('pf_payment_id', $cols)) {
        $pdo->exec("ALTER TABLE licenses ADD COLUMN pf_payment_id TEXT DEFAULT ''");
    }
    return $pdo;
}
