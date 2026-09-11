<?php
declare(strict_types=1);

function pesapal_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require ROOT_PATH . '/config/pesapal.php';
    }
    return $cfg;
}

/** Payment methods Pesapal presents on the hosted checkout. */
function pesapal_payment_methods(): array
{
    return [
        ['group' => 'Mobile money', 'items' => ['M-Pesa', 'Airtel Money', 'MTN Mobile Money', 'Tigo Pesa', 'Equitel']],
        ['group' => 'Cards', 'items' => ['Visa', 'Mastercard', 'American Express']],
        ['group' => 'Bank and wallet', 'items' => ['Bank transfer', 'Pesapal e-wallet']],
    ];
}

function pesapal_request(string $method, string $path, ?array $body = null, string $token = ''): array
{
    $cfg = pesapal_config();
    $url = $cfg['base'] . $path;
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
    ];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 12,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = is_string($raw) ? json_decode($raw, true) : null;
    return [
        'ok' => $err === '' && $code >= 200 && $code < 300,
        'code' => $code,
        'error' => $err,
        'body' => is_array($json) ? $json : [],
        'raw' => is_string($raw) ? $raw : '',
    ];
}

function pesapal_token(): array
{
    $cfg = pesapal_config();
    $res = pesapal_request('POST', '/Auth/RequestToken', [
        'consumer_key' => $cfg['consumer_key'],
        'consumer_secret' => $cfg['consumer_secret'],
    ]);
    $token = (string) ($res['body']['token'] ?? '');
    if ($token === '') {
        return ['ok' => false, 'error' => (string) ($res['body']['message'] ?? $res['error'] ?: 'Pesapal did not issue a token.')];
    }
    return ['ok' => true, 'token' => $token];
}

function pesapal_ipn_id(string $token): array
{
    $row = db_one("SELECT v FROM schema_meta WHERE k = 'pesapal_ipn_id'");
    $stored = trim((string) ($row['v'] ?? ''));
    if ($stored !== '') {
        return ['ok' => true, 'id' => $stored];
    }
    $cfg = pesapal_config();
    $ipnUrl = (string) (getenv('PESAPAL_IPN_URL') ?: $cfg['ipn_url'] ?? '');
    if ($ipnUrl === '') {
        $ipnUrl = absolute_url('pesapal_ipn.php');
    }
    $res = pesapal_request('POST', '/URLSetup/RegisterIPN', [
        'url' => $ipnUrl,
        'ipn_notification_type' => 'GET',
    ], $token);
    $id = (string) ($res['body']['ipn_id'] ?? $res['body']['ipnId'] ?? '');
    if ($id === '') {
        return ['ok' => false, 'error' => (string) ($res['body']['message'] ?? 'Could not register the Pesapal notification URL.')];
    }
    db_exec("REPLACE INTO schema_meta (k, v) VALUES ('pesapal_ipn_id', ?)", 's', [$id]);
    return ['ok' => true, 'id' => $id];
}

function pesapal_country_code(string $country): string
{
    $raw = strtoupper(trim($country));
    $raw = preg_replace('/[^A-Z ]/', '', $raw) ?? '';
    if (preg_match('/^[A-Z]{2}$/', $raw)) {
        return $raw;
    }
    $map = [
        'UGANDA' => 'UG',
        'KENYA' => 'KE',
        'TANZANIA' => 'TZ',
        'RWANDA' => 'RW',
        'BURUNDI' => 'BI',
        'SOUTH SUDAN' => 'SS',
        'CONGO' => 'CD',
        'DEMOCRATIC REPUBLIC OF THE CONGO' => 'CD',
        'DRC' => 'CD',
        'NIGERIA' => 'NG',
        'GHANA' => 'GH',
        'SOUTH AFRICA' => 'ZA',
        'UNITED KINGDOM' => 'GB',
        'UK' => 'GB',
        'GREAT BRITAIN' => 'GB',
        'UNITED STATES' => 'US',
        'USA' => 'US',
        'AMERICA' => 'US',
    ];
    return $map[$raw] ?? 'UG';
}

function pesapal_submit_order(array $order, string $token, string $ipnId): array
{
    $names = preg_split('/\s+/', trim((string) $order['name']), 2) ?: [];
    $first = $names[0] ?? 'Accounts';
    $last = $names[1] ?? $order['company'];
    $displayCcy = (string) $order['currency'];
    $payCcy = pricing_pay_currency($displayCcy);
    $payAmount = $payCcy === $displayCcy ? (float) $order['amount'] : (float) $order['amount_ugx'];
    $payload = [
        'id' => (string) $order['merchant_ref'],
        'currency' => $payCcy,
        'amount' => $payAmount,
        'description' => mb_substr('Vellisys ' . ($order['plan_name'] ?? 'desk') . ' for ' . $order['company'], 0, 100),
        'callback_url' => absolute_url('pesapal_callback.php'),
        'cancellation_url' => absolute_url('pesapal_callback.php?cancel=1'),
        'notification_id' => $ipnId,
        'redirect_mode' => 'PARENT_WINDOW',
        'billing_address' => [
            'email_address' => (string) $order['email'],
            'phone_number' => (string) $order['phone'],
            'country_code' => pesapal_country_code((string) ($order['country'] ?? '')),
            'first_name' => $first,
            'middle_name' => '',
            'last_name' => $last,
            'line_1' => (string) $order['company'],
            'city' => (string) ($order['city'] ?? ''),
        ],
    ];
    $res = pesapal_request('POST', '/Transactions/SubmitOrderRequest', $payload, $token);
    $redirect = (string) ($res['body']['redirect_url'] ?? '');
    $tracking = (string) ($res['body']['order_tracking_id'] ?? '');
    if ($redirect === '' || $tracking === '') {
        return ['ok' => false, 'error' => (string) ($res['body']['message'] ?? $res['error'] ?: 'Pesapal did not return a payment page.')];
    }
    return ['ok' => true, 'redirect' => $redirect, 'tracking' => $tracking, 'body' => $res['body']];
}

/** Pesapal hosted checkout URL, or empty if it is not a pesapal.com https address. */
function pesapal_hosted_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || !preg_match('#^https://#i', $url)) {
        return '';
    }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if ($host === '' || !preg_match('/(^|\.)pesapal\.com$/', $host)) {
        return '';
    }
    return $url;
}

function order_hosted_pay_url(?array $order): string
{
    if (!$order) {
        return '';
    }
    $status = (string) ($order['status'] ?? '');
    if (!in_array($status, ['draft', 'pending'], true)) {
        return '';
    }
    return pesapal_hosted_url((string) ($order['pesapal_redirect'] ?? ''));
}

function order_rotate_merchant_ref(array $order): array
{
    $id = (int) ($order['id'] ?? 0);
    if ($id < 1) {
        return $order;
    }
    $now = desk_now()->format('Y-m-d H:i:s');
    db_exec(
        'UPDATE website_orders SET merchant_ref=?, pesapal_tracking=?, pesapal_redirect=NULL, last_error=?, updated_at=? WHERE id=?',
        'ssssi',
        [order_merchant_ref(), '', '', $now, $id]
    );
    return db_one('SELECT * FROM website_orders WHERE id = ?', 'i', [$id]) ?: $order;
}

function pesapal_transaction_status(string $tracking, string $token): array
{
    $path = '/Transactions/GetTransactionStatus?orderTrackingId=' . rawurlencode($tracking);
    $res = pesapal_request('GET', $path, null, $token);
    $statusCode = (int) ($res['body']['status_code'] ?? $res['body']['statusCode'] ?? -1);
    $map = [
        0 => 'invalid',
        1 => 'paid',
        2 => 'failed',
        3 => 'cancelled',
        4 => 'pending',
    ];
    $status = $map[$statusCode] ?? 'pending';
    $desc = strtoupper((string) ($res['body']['payment_status_description'] ?? $res['body']['paymentStatusDescription'] ?? ''));
    if ($desc === 'COMPLETED') {
        $status = 'paid';
    } elseif (in_array($desc, ['FAILED', 'INVALID'], true)) {
        $status = 'failed';
    } elseif ($desc === 'REVERSED') {
        $status = 'cancelled';
    }
    return [
        'ok' => !empty($res['ok']),
        'status' => $status,
        'code' => $statusCode,
        'body' => $res['body'],
        'error' => $res['error'],
    ];
}

function order_public_id(): string
{
    return bin2hex(random_bytes(8));
}

function order_merchant_ref(): string
{
    return 'VS-' . strtoupper(bin2hex(random_bytes(6)));
}

function save_website_order(array $data, ?int $id = null): array
{
    $plan = pricing_package((string) ($data['plan'] ?? ''));
    if (!$plan) {
        return ['ok' => false, 'error' => 'Pick a package.'];
    }
    $currency = strtoupper((string) ($data['currency'] ?? pricing_display_currency()));
    if (!isset(pricing_currencies()[$currency])) {
        $currency = 'UGX';
    }
    $name = mb_substr(trim((string) ($data['name'] ?? '')), 0, 80);
    $company = mb_substr(trim((string) ($data['company'] ?? '')), 0, 160);
    $email = strtolower(mb_substr(trim((string) ($data['email'] ?? '')), 0, 190));
    $phone = mb_substr(trim((string) ($data['phone'] ?? '')), 0, 40);
    $city = mb_substr(trim((string) ($data['city'] ?? '')), 0, 80);
    $country = mb_substr(trim((string) ($data['country'] ?? '')), 0, 80);
    $status = (string) ($data['status'] ?? 'draft');
    if (!in_array($status, ['draft', 'pending', 'paid', 'failed', 'cancelled'], true)) {
        $status = 'draft';
    }
    $amountUgx = (float) $plan['price_ugx'];
    $amount = pricing_convert_ugx($amountUgx, $currency);
    $now = desk_now()->format('Y-m-d H:i:s');
    if ($id) {
        $row = db_one('SELECT * FROM website_orders WHERE id = ?', 'i', [$id]);
        if ($row && ($row['status'] ?? '') === 'paid') {
            return ['ok' => true, 'order' => $row];
        }
        db_exec(
            'UPDATE website_orders SET plan=?, currency=?, amount=?, amount_ugx=?, name=?, company=?, email=?, phone=?, city=?, country=?, status=?, updated_at=? WHERE id=?',
            'ssddssssssssi',
            [$plan['key'], $currency, $amount, $amountUgx, $name, $company, $email, $phone, $city, $country, $status, $now, $id]
        );
        $row = db_one('SELECT * FROM website_orders WHERE id = ?', 'i', [$id]);
        return ['ok' => true, 'order' => $row];
    }
    $publicId = order_public_id();
    $ref = order_merchant_ref();
    $newId = db_exec(
        'INSERT INTO website_orders (public_id, merchant_ref, plan, currency, amount, amount_ugx, name, company, email, phone, city, country, status, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        'ssssddsssssssss',
        [$publicId, $ref, $plan['key'], $currency, $amount, $amountUgx, $name, $company, $email, $phone, $city, $country, $status, $now, $now]
    );
    $row = db_one('SELECT * FROM website_orders WHERE id = ?', 'i', [$newId]);
    return ['ok' => true, 'order' => $row];
}

function order_by_public(string $publicId): ?array
{
    return db_one('SELECT * FROM website_orders WHERE public_id = ?', 's', [$publicId]) ?: null;
}

function order_by_merchant(string $ref): ?array
{
    return db_one('SELECT * FROM website_orders WHERE merchant_ref = ?', 's', [$ref]) ?: null;
}

function order_by_tracking(string $tracking): ?array
{
    return db_one('SELECT * FROM website_orders WHERE pesapal_tracking = ?', 's', [$tracking]) ?: null;
}

function apply_order_payment_status(array $order, string $status, string $error = ''): array
{
    if (($order['status'] ?? '') === 'paid') {
        return $order;
    }
    if (($order['status'] ?? '') === $status && $status !== 'pending') {
        return $order;
    }
    $now = desk_now()->format('Y-m-d H:i:s');
    db_exec(
        'UPDATE website_orders SET status=?, last_error=?, updated_at=? WHERE id=?',
        'sssi',
        [$status, $error, $now, (int) $order['id']]
    );
    $fresh = db_one('SELECT * FROM website_orders WHERE id = ?', 'i', [(int) $order['id']]) ?: $order;
    if ($status === 'paid') {
        attach_order_signup($fresh, 'paid');
        $fresh = provision_paid_order($fresh);
        notify_admin_order($fresh, 'paid');
    } elseif (in_array($status, ['failed', 'cancelled'], true)) {
        attach_order_signup($fresh, $status);
        notify_admin_order($fresh, $status);
    }
    return $fresh;
}

function attach_order_signup(array $order, string $payStatus): ?int
{
    $email = strtolower(trim((string) $order['email']));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $plan = pricing_package((string) $order['plan']);
    $place = trim((string) ($order['city'] ?? '') . (((string) ($order['city'] ?? '') !== '' && (string) ($order['country'] ?? '') !== '') ? ', ' : '') . (string) ($order['country'] ?? ''));
    $note = ($plan['name'] ?? $order['plan']) . ' · ' . $order['currency'] . ' ' . $order['amount'] . ' · payment ' . $payStatus . ($place !== '' ? ' · ' . $place : '');
    $existing = (int) ($order['signup_id'] ?? 0);
    if ($existing) {
        db_exec('UPDATE signups SET note=? WHERE id=?', 'si', [$note, $existing]);
        return $existing;
    }
    $found = db_one("SELECT id FROM signups WHERE email = ? AND status IN ('new','contacted') ORDER BY id DESC LIMIT 1", 's', [$email]);
    if ($found) {
        db_exec('UPDATE signups SET note=?, source=? WHERE id=?', 'ssi', [$note, 'checkout', (int) $found['id']]);
        db_exec('UPDATE website_orders SET signup_id=? WHERE id=?', 'ii', [(int) $found['id'], (int) $order['id']]);
        return (int) $found['id'];
    }
    $sid = db_exec(
        'INSERT INTO signups (name, company, email, phone, status, source, note) VALUES (?,?,?,?,?,?,?)',
        'sssssss',
        [
            (string) $order['name'],
            (string) $order['company'],
            $email,
            (string) $order['phone'],
            'new',
            'checkout',
            $note,
        ]
    );
    db_exec('UPDATE website_orders SET signup_id=? WHERE id=?', 'ii', [$sid, (int) $order['id']]);
    return $sid;
}

function start_pesapal_payment(array $order): array
{
    $auth = pesapal_token();
    if (empty($auth['ok'])) {
        return ['ok' => false, 'error' => (string) ($auth['error'] ?? 'Could not sign in to Pesapal.')];
    }
    $ipn = pesapal_ipn_id($auth['token']);
    if (empty($ipn['ok'])) {
        return ['ok' => false, 'error' => (string) ($ipn['error'] ?? 'Could not register Pesapal notifications.')];
    }
    $plan = pricing_package((string) $order['plan']);
    $order['plan_name'] = $plan['name'] ?? 'desk';
    $sent = pesapal_submit_order($order, $auth['token'], $ipn['id']);
    if (empty($sent['ok'])) {
        return ['ok' => false, 'error' => (string) ($sent['error'] ?? 'Pesapal did not accept the order.')];
    }
    $now = desk_now()->format('Y-m-d H:i:s');
    db_exec(
        'UPDATE website_orders SET pesapal_tracking=?, pesapal_redirect=?, status=?, updated_at=? WHERE id=?',
        'ssssi',
        [$sent['tracking'], $sent['redirect'], 'pending', $now, (int) $order['id']]
    );
    return ['ok' => true, 'redirect' => $sent['redirect'], 'tracking' => $sent['tracking']];
}

function refresh_order_from_pesapal(array $order): array
{
    $tracking = trim((string) ($order['pesapal_tracking'] ?? ''));
    if ($tracking === '') {
        return $order;
    }
    $auth = pesapal_token();
    if (empty($auth['ok'])) {
        return $order;
    }
    $st = pesapal_transaction_status($tracking, $auth['token']);
    if (empty($st['ok'])) {
        return $order;
    }
    if ($st['status'] === 'pending') {
        return $order;
    }
    return apply_order_payment_status($order, $st['status']);
}
