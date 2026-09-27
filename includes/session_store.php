<?php
declare(strict_types=1);

/**
 * Durable login for VPS / Docker / Traefik:
 * - PHP sessions stored in MySQL (survives redeploys and multi-replica hops)
 * - Signed vellisys_auth cookie restores the login if the PHP session row is lost
 * - No idle timeout — only logout.php (or suspended account) clears the login
 */

/** ~10 years. Cookie/session lifetime; not an idle timer. */
function folio_session_persist_seconds(): int
{
    if (defined('SESSION_PERSIST_SECONDS')) {
        return (int) SESSION_PERSIST_SECONDS;
    }
    return 60 * 60 * 24 * 365 * 10;
}

function folio_auth_cookie_name(): string
{
    return 'vellisys_auth';
}

function folio_auth_secret(): string
{
    static $secret = null;
    if (is_string($secret)) {
        return $secret;
    }
    $env = getenv('FOLIO_AUTH_SECRET');
    if (is_string($env) && $env !== '') {
        $secret = $env;
        return $secret;
    }
    try {
        $cfg = require ROOT_PATH . '/config/database.php';
        $secret = hash('sha256', 'vellisys-auth|' . ($cfg['name'] ?? '') . '|' . ($cfg['user'] ?? '') . '|' . ($cfg['pass'] ?? ''));
    } catch (Throwable $e) {
        $secret = hash('sha256', 'vellisys-auth|' . ROOT_PATH);
    }
    return $secret;
}

/**
 * Lightweight DB link for the session handler (no migrate / no install redirect).
 */
function folio_session_mysqli(): ?mysqli
{
    static $link = false;
    if ($link instanceof mysqli) {
        return $link;
    }
    if ($link === null) {
        return null;
    }
    try {
        $cfg = require ROOT_PATH . '/config/database.php';
        $m = mysqli_init();
        if ($m === false) {
            $link = null;
            return null;
        }
        $m->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
        $ok = @$m->real_connect(
            (string) ($cfg['host'] ?? '127.0.0.1'),
            (string) ($cfg['user'] ?? ''),
            (string) ($cfg['pass'] ?? ''),
            (string) ($cfg['name'] ?? '')
        );
        if (!$ok) {
            $link = null;
            return null;
        }
        $m->set_charset('utf8mb4');
        if (!folio_session_ensure_table($m)) {
            $link = null;
            return null;
        }
        $link = $m;
        return $link;
    } catch (Throwable $e) {
        $link = null;
        return null;
    }
}

function folio_session_ensure_table(mysqli $db): bool
{
    static $ready = false;
    if ($ready) {
        return true;
    }
    $sql = "CREATE TABLE IF NOT EXISTS php_sessions (
        id VARCHAR(128) NOT NULL PRIMARY KEY,
        data MEDIUMBLOB NOT NULL,
        expires_at INT UNSIGNED NOT NULL,
        KEY idx_php_sessions_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!@$db->query($sql)) {
        return false;
    }
    $ready = true;
    return true;
}

final class FolioDbSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        return folio_session_mysqli() instanceof mysqli;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $db = folio_session_mysqli();
        if (!$db) {
            return '';
        }
        $now = time();
        $stmt = @$db->prepare('SELECT data FROM php_sessions WHERE id = ? AND expires_at >= ? LIMIT 1');
        if (!$stmt) {
            return '';
        }
        $stmt->bind_param('si', $id, $now);
        if (!@$stmt->execute()) {
            $stmt->close();
            return '';
        }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            return '';
        }
        return (string) $row['data'];
    }

    public function write(string $id, string $data): bool
    {
        $db = folio_session_mysqli();
        if (!$db) {
            return false;
        }
        $lifetime = (int) ini_get('session.gc_maxlifetime');
        if ($lifetime < 3600) {
            $lifetime = folio_session_persist_seconds();
        }
        $expires = time() + $lifetime;
        $stmt = @$db->prepare(
            'INSERT INTO php_sessions (id, data, expires_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at)'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssi', $id, $data, $expires);
        $ok = @$stmt->execute();
        $stmt->close();
        return (bool) $ok;
    }

    public function destroy(string $id): bool
    {
        $db = folio_session_mysqli();
        if (!$db) {
            return true;
        }
        $stmt = @$db->prepare('DELETE FROM php_sessions WHERE id = ?');
        if (!$stmt) {
            return true;
        }
        $stmt->bind_param('s', $id);
        @$stmt->execute();
        $stmt->close();
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $db = folio_session_mysqli();
        if (!$db) {
            return 0;
        }
        $cutoff = time() - max(0, $max_lifetime);
        // Also drop rows past their own expires_at.
        @$db->query('DELETE FROM php_sessions WHERE expires_at < ' . (int) time() . ' OR expires_at < ' . (int) $cutoff);
        return $db->affected_rows;
    }
}

/** Prefer MySQL sessions; fall back to disk under storage/sessions. */
function folio_register_session_store(): void
{
    $sessionDir = ROOT_PATH . '/storage/sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0770, true);
    }
    @chmod($sessionDir, 0770);

    $db = folio_session_mysqli();
    if ($db instanceof mysqli) {
        $handler = new FolioDbSessionHandler();
        session_set_save_handler($handler, true);
        return;
    }

    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    }
}

function folio_auth_token_issue(int $userId): string
{
    $exp = time() + folio_session_persist_seconds();
    $payload = $userId . '|' . $exp;
    $sig = hash_hmac('sha256', $payload, folio_auth_secret());
    return $payload . '|' . $sig;
}

function folio_auth_token_parse(string $raw): ?array
{
    $raw = trim($raw);
    if ($raw === '' || substr_count($raw, '|') !== 2) {
        return null;
    }
    [$uid, $exp, $sig] = explode('|', $raw, 3);
    if (!ctype_digit($uid) || !ctype_digit($exp) || !preg_match('/^[a-f0-9]{64}$/', $sig)) {
        return null;
    }
    $payload = $uid . '|' . $exp;
    $expect = hash_hmac('sha256', $payload, folio_auth_secret());
    if (!hash_equals($expect, $sig)) {
        return null;
    }
    $expTs = (int) $exp;
    if ($expTs < time() - 60) {
        return null;
    }
    return ['user_id' => (int) $uid, 'exp' => $expTs];
}

function folio_auth_cookie_set(int $userId): void
{
    if ($userId < 1 || headers_sent()) {
        return;
    }
    $token = folio_auth_token_issue($userId);
    $opts = [
        'expires' => time() + folio_session_persist_seconds(),
        'path' => '/',
        'secure' => function_exists('folio_request_is_https') && folio_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    setcookie(folio_auth_cookie_name(), $token, $opts);
    // Keep the marker cookie in sync so older code paths treat this as Remember me.
    setcookie('vellisys_rm', '1', $opts);
}

function folio_auth_cookie_clear(): void
{
    if (headers_sent()) {
        return;
    }
    $opts = [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => function_exists('folio_request_is_https') && folio_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    setcookie(folio_auth_cookie_name(), '', $opts);
}

/**
 * If the PHP session was lost (redeploy, replica hop, GC) but the signed
 * auth cookie is still valid, restore the login from the database.
 */
function folio_auth_resume(): void
{
    if (!empty($_SESSION['user_id'])) {
        return;
    }
    $raw = (string) ($_COOKIE[folio_auth_cookie_name()] ?? '');
    $parsed = folio_auth_token_parse($raw);
    if (!$parsed) {
        return;
    }
    if (!function_exists('db_one')) {
        return;
    }
    try {
        $user = db_one('SELECT id, company_id, role, status FROM users WHERE id = ?', 'i', [$parsed['user_id']]);
    } catch (Throwable $e) {
        return;
    }
    if (!$user) {
        folio_auth_cookie_clear();
        return;
    }
    if (($user['role'] ?? '') !== 'platform' && ($user['status'] ?? 'live') === 'suspended') {
        folio_auth_cookie_clear();
        return;
    }
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['company_id'] = (int) ($user['company_id'] ?? 0);
    $_SESSION['role'] = (string) ($user['role'] ?? 'member');
    $_SESSION['remember'] = 1;
    $_SESSION['last_activity'] = time();
    // Refresh the auth cookie so it keeps sliding forward with use.
    folio_auth_cookie_set((int) $user['id']);
}
