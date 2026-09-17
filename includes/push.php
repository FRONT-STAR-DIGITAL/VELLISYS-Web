<?php
declare(strict_types=1);

function vapid_b64url_encode(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function vapid_b64url_decode(string $b64): string
{
    $pad = strlen($b64) % 4;
    if ($pad > 0) {
        $b64 .= str_repeat('=', 4 - $pad);
    }
    $out = base64_decode(strtr($b64, '-_', '+/'), true);
    return $out === false ? '' : $out;
}

function vapid_ensure_tables(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;
    $db = db();
    @$db->query("CREATE TABLE IF NOT EXISTS schema_meta (
      k VARCHAR(40) PRIMARY KEY,
      v VARCHAR(40) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Private PEM is longer than VARCHAR(40).
    @$db->query("ALTER TABLE schema_meta MODIFY v TEXT NOT NULL");
    @$db->query("CREATE TABLE IF NOT EXISTS push_subscriptions (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      company_id INT NOT NULL DEFAULT 0,
      endpoint TEXT NOT NULL,
      endpoint_hash CHAR(64) NOT NULL,
      p256dh VARCHAR(255) NOT NULL,
      auth_key VARCHAR(255) NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY endpoint_hash (endpoint_hash),
      KEY user_id (user_id),
      KEY company_id (company_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @$db->query("CREATE TABLE IF NOT EXISTS push_sent (
      user_id INT NOT NULL,
      notif_key VARCHAR(80) NOT NULL,
      sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (user_id, notif_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function vapid_ec_public_uncompressed($key): string
{
    $det = openssl_pkey_get_details($key);
    $ec = $det['ec'] ?? [];
    $x = str_pad((string) ($ec['x'] ?? ''), 32, "\x00", STR_PAD_LEFT);
    $y = str_pad((string) ($ec['y'] ?? ''), 32, "\x00", STR_PAD_LEFT);
    return "\x04" . $x . $y;
}

function vapid_public_from_uncompressed(string $raw): mixed
{
    if (strlen($raw) !== 65 || $raw[0] !== "\x04") {
        return false;
    }
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw;
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    return openssl_pkey_get_public($pem);
}

function vapid_keys(): array
{
    static $keys = null;
    if (is_array($keys)) {
        return $keys;
    }
    vapid_ensure_tables();
    $pub = db_one("SELECT v FROM schema_meta WHERE k = 'vapid_public'");
    $pem = db_one("SELECT v FROM schema_meta WHERE k = 'vapid_private'");
    if ($pub && $pem && (string) $pub['v'] !== '' && (string) $pem['v'] !== '') {
        $keys = ['public' => (string) $pub['v'], 'private' => (string) $pem['v']];
        return $keys;
    }
    $key = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ]);
    if ($key === false) {
        throw new RuntimeException('Could not create notification keys.');
    }
    $exported = '';
    openssl_pkey_export($key, $exported);
    $public = vapid_b64url_encode(vapid_ec_public_uncompressed($key));
    db_exec("INSERT INTO schema_meta (k, v) VALUES ('vapid_public', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)", 's', [$public]);
    db_exec("INSERT INTO schema_meta (k, v) VALUES ('vapid_private', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)", 's', [$exported]);
    $keys = ['public' => $public, 'private' => $exported];
    return $keys;
}

function vapid_public_key(): string
{
    try {
        return vapid_keys()['public'];
    } catch (Throwable $e) {
        return '';
    }
}

function folio_html_root_attrs(): string
{
    $attrs = ' data-sw="' . h(url('sw.js')) . '" data-pwa-login="' . h(url('login.php')) . '"';
    if (current_user()) {
        $pub = vapid_public_key();
        if ($pub !== '') {
            $attrs .= ' data-vapid="' . h($pub) . '" data-push="' . h(url('push_subscribe.php')) . '" data-csrf="' . h(csrf_token()) . '"';
        }
        $badge = 0;
        if (function_exists('push_current_items_for_user')) {
            $badge = count(push_current_items_for_user());
        }
        $attrs .= ' data-badge="' . (int) $badge . '"';
    }
    return $attrs;
}

function vapid_ecdsa_der_to_jose(string $der): string
{
    $offset = 0;
    $readLen = static function (string $bin, int &$i): int {
        $len = ord($bin[$i++]);
        if (($len & 0x80) === 0) {
            return $len;
        }
        $n = $len & 0x7f;
        $val = 0;
        for ($k = 0; $k < $n; $k++) {
            $val = ($val << 8) | ord($bin[$i++]);
        }
        return $val;
    };
    $readInt = static function (string $bin, int &$i) use ($readLen): string {
        if (ord($bin[$i++]) !== 0x02) {
            return str_repeat("\x00", 32);
        }
        $len = $readLen($bin, $i);
        $bytes = substr($bin, $i, $len);
        $i += $len;
        $bytes = ltrim($bytes, "\x00");
        if (strlen($bytes) > 32) {
            $bytes = substr($bytes, -32);
        }
        return str_pad($bytes, 32, "\x00", STR_PAD_LEFT);
    };
    if ($der === '' || ord($der[$offset++]) !== 0x30) {
        return str_repeat("\x00", 64);
    }
    $readLen($der, $offset);
    return $readInt($der, $offset) . $readInt($der, $offset);
}

function vapid_jwt(string $audience): string
{
    $keys = vapid_keys();
    $header = vapid_b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES) ?: '{}');
    $body = vapid_b64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 12 * 3600,
        'sub' => 'mailto:' . (function_exists('product_email') ? product_email() : 'info@vellisys.com'),
    ], JSON_UNESCAPED_SLASHES) ?: '{}');
    $signing = $header . '.' . $body;
    $priv = openssl_pkey_get_private($keys['private']);
    if ($priv === false) {
        throw new RuntimeException('Notification signing key is missing.');
    }
    $sig = '';
    openssl_sign($signing, $sig, $priv, OPENSSL_ALGO_SHA256);
    return $signing . '.' . vapid_b64url_encode(vapid_ecdsa_der_to_jose($sig));
}

function push_encrypt_payload(string $payload, string $p256dh, string $auth): string
{
    $clientPub = vapid_b64url_decode($p256dh);
    $authSecret = vapid_b64url_decode($auth);
    $userKey = vapid_public_from_uncompressed($clientPub);
    if ($userKey === false || $authSecret === '') {
        throw new RuntimeException('That device could not be reached.');
    }
    $local = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ]);
    if ($local === false) {
        throw new RuntimeException('Could not encrypt the notification.');
    }
    $shared = openssl_pkey_derive($userKey, $local);
    if (!is_string($shared) || $shared === '') {
        throw new RuntimeException('Could not encrypt the notification.');
    }
    $localPub = vapid_ec_public_uncompressed($local);
    $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\x00" . $clientPub . $localPub, $authSecret);
    $salt = random_bytes(16);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);
    $plain = $payload . "\x02";
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($cipher === false) {
        throw new RuntimeException('Could not encrypt the notification.');
    }
    return $salt . pack('N', 4096) . chr(strlen($localPub)) . $localPub . $cipher . $tag;
}

function push_send_http(string $endpoint, string $body, string $audience): array
{
    $jwt = vapid_jwt($audience);
    $pub = vapid_keys()['public'];
    $headers = [
        'Authorization: vapid t=' . $jwt . ', k=' . $pub,
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'TTL: 86400',
        'Urgency: high',
        'Content-Length: ' . (string) strlen($body),
    ];
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 12,
        ],
    ]);
    $raw = @file_get_contents($endpoint, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    return ['status' => $status, 'body' => is_string($raw) ? $raw : ''];
}

function push_save_subscription(array $user, string $endpoint, string $p256dh, string $auth): void
{
    vapid_ensure_tables();
    $endpoint = trim($endpoint);
    $p256dh = trim($p256dh);
    $auth = trim($auth);
    if ($endpoint === '' || $p256dh === '' || $auth === '' || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('That device could not be saved.');
    }
    $hash = hash('sha256', $endpoint);
    db_exec(
        'INSERT INTO push_subscriptions (user_id, company_id, endpoint, endpoint_hash, p256dh, auth_key, created_at, last_seen)
         VALUES (?,?,?,?,?,?,NOW(),NOW())
         ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), company_id=VALUES(company_id), p256dh=VALUES(p256dh), auth_key=VALUES(auth_key), last_seen=NOW()',
        'iissss',
        [(int) $user['id'], (int) ($user['company_id'] ?? current_company_id()), $endpoint, $hash, $p256dh, $auth]
    );
}

function push_delete_subscription(string $endpoint): void
{
    vapid_ensure_tables();
    db_exec('DELETE FROM push_subscriptions WHERE endpoint_hash = ?', 's', [hash('sha256', $endpoint)]);
}

function push_drop_subscription_id(int $id): void
{
    db_exec('DELETE FROM push_subscriptions WHERE id = ?', 'i', [$id]);
}

function push_send_to_subscription(array $sub, array $payload): bool
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return false;
    }
    try {
        $body = push_encrypt_payload($json, (string) $sub['p256dh'], (string) $sub['auth_key']);
        $endpoint = (string) $sub['endpoint'];
        $parts = parse_url($endpoint);
        $audience = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        $res = push_send_http($endpoint, $body, $audience);
    } catch (Throwable $e) {
        return false;
    }
    if (in_array($res['status'], [404, 410], true)) {
        push_drop_subscription_id((int) $sub['id']);
        return false;
    }
    return $res['status'] >= 200 && $res['status'] < 300;
}

function push_mark_sent(int $userId, string $key): void
{
    if ($userId <= 0 || $key === '') {
        return;
    }
    vapid_ensure_tables();
    db_exec(
        'INSERT IGNORE INTO push_sent (user_id, notif_key, sent_at) VALUES (?,?,NOW())',
        'is',
        [$userId, $key]
    );
}

function push_already_sent(int $userId, string $key): bool
{
    if ($userId <= 0 || $key === '') {
        return true;
    }
    vapid_ensure_tables();
    $row = db_one('SELECT user_id FROM push_sent WHERE user_id = ? AND notif_key = ?', 'is', [$userId, $key]);
    return (bool) $row;
}

/**
 * @return list<array<string,mixed>>
 */
function push_subscriptions_for_user(int $userId): array
{
    vapid_ensure_tables();
    return db_all('SELECT * FROM push_subscriptions WHERE user_id = ?', 'i', [$userId]);
}

/**
 * @return list<int>
 */
function push_audience_user_ids(string $scope, int $companyId = 0): array
{
    if ($scope === 'platform') {
        $rows = db_all("SELECT id FROM users WHERE role = 'platform'");
        return array_map(static fn (array $r): int => (int) $r['id'], $rows);
    }
    $cid = $companyId > 0 ? $companyId : current_company_id();
    $rows = db_all(
        "SELECT id FROM users WHERE company_id = ? AND (role = 'admin' OR access = 'admin')",
        'i',
        [$cid]
    );
    return array_map(static fn (array $r): int => (int) $r['id'], $rows);
}

function push_deliver(array $userIds, string $title, string $body, string $url, string $tag): void
{
    foreach (array_unique(array_filter($userIds)) as $uid) {
        $uid = (int) $uid;
        if ($tag !== '' && push_already_sent($uid, $tag)) {
            continue;
        }
        $owner = db_one('SELECT * FROM users WHERE id = ?', 'i', [$uid]);
        $payload = [
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'tag' => $tag !== '' ? $tag : 'vellisys',
            'badge' => $owner ? count(push_current_items_for_user($owner)) : 1,
        ];
        $sent = false;
        foreach (push_subscriptions_for_user($uid) as $sub) {
            if (push_send_to_subscription($sub, $payload)) {
                $sent = true;
            }
        }
        if ($sent && $tag !== '') {
            push_mark_sent($uid, $tag);
        }
    }
}

function push_notify_item(array $item, string $scope = 'company', int $companyId = 0): void
{
    $title = trim((string) ($item['title'] ?? 'Vellisys'));
    $body = trim((string) ($item['meta'] ?? ''));
    $href = (string) ($item['href'] ?? url('dashboard.php'));
    if (!str_starts_with($href, 'http')) {
        $href = function_exists('absolute_url') ? absolute_url(ltrim($href, '/')) : $href;
    }
    $tag = (string) ($item['key'] ?? '');
    push_deliver(push_audience_user_ids($scope, $companyId), $title, $body !== '' ? $body : $title, $href, $tag);
}

/**
 * @return list<array{key:string,title:string,body:string,url:string}>
 */
function push_current_items_for_user(?array $user = null): array
{
    $user = $user ?? current_user();
    if (!$user) {
        return [];
    }
    $items = [];
    if (($user['role'] ?? '') === 'platform' && empty($_SESSION['acting_company_id'])) {
        $items = function_exists('platform_notifications') ? platform_notifications(40) : [];
    } elseif (function_exists('company_planner_enabled') && company_planner_enabled() && is_desk_admin($user)) {
        $items = enrich_planner_notifications(planner_notifications(40));
    }
    $out = [];
    foreach ($items as $n) {
        $href = (string) ($n['href'] ?? url('dashboard.php'));
        $out[] = [
            'key' => (string) ($n['key'] ?? ''),
            'title' => (string) ($n['title'] ?? 'Vellisys'),
            'body' => (string) ($n['meta'] ?? ''),
            'url' => $href,
        ];
    }
    return $out;
}

function push_sync_outstanding(?array $user = null): void
{
    $user = $user ?? current_user();
    if (!$user) {
        return;
    }
    $scope = (($user['role'] ?? '') === 'platform' && empty($_SESSION['acting_company_id'])) ? 'platform' : 'company';
    $cid = (int) ($user['company_id'] ?? current_company_id());
    foreach (push_current_items_for_user($user) as $n) {
        push_notify_item([
            'title' => $n['title'],
            'meta' => $n['body'],
            'href' => $n['url'],
            'key' => $n['key'],
        ], $scope, $cid);
    }
}

function push_schedule_sync(): void
{
    static $queued = false;
    if ($queued || !current_user()) {
        return;
    }
    $queued = true;
    register_shutdown_function(static function (): void {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        try {
            push_sync_outstanding();
        } catch (Throwable $e) {
            // never break the page
        }
    });
}

function render_push_settings_card(): void
{
    ?>
    <section class="card settings-card" id="notifications">
      <h2><?= icon('bell') ?>Notifications</h2>
      <p class="lede">Allow alerts on this phone or computer. Existing desk notices appear on the installed app, and new ones pop up like a WhatsApp message. On the installed app, the home-screen icon also shows how many are waiting, the way Gmail does. Use the installed app for the most reliable alerts.</p>
      <div class="push-settings" data-push-panel>
        <p class="muted" data-push-status>Checking this device…</p>
        <div class="settings-account-actions">
          <button class="btn" type="button" data-push-allow><?= icon('bell', 15) ?>Allow notifications</button>
          <button class="btn ghost" type="button" data-push-off hidden>Turn off on this device</button>
        </div>
      </div>
    </section>
    <?php
}
