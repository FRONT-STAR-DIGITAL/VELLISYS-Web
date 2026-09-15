<?php
declare(strict_types=1);

function platform_home(): string
{
    return 'admin_dashboard.php';
}

function platform_currency(): string
{
    $allowed = function_exists('pricing_currencies') ? array_keys(pricing_currencies()) : ['USD', 'UGX', 'KES', 'EUR', 'GBP'];
    $code = normalize_currency(platform_setting('admin_currency', 'USD'), 'USD');
    return in_array($code, $allowed, true) ? $code : 'USD';
}

function platform_ugx_rate(string $currency): float
{
    $currency = normalize_currency($currency, 'USD');
    if ($currency === 'UGX') {
        return 1.0;
    }
    $rates = function_exists('pricing_ugx_rates') ? pricing_ugx_rates() : [];
    $n = (float) ($rates[$currency] ?? 0);
    if ($n > 0) {
        return $n;
    }
    return $currency === 'USD' ? 3700.0 : 1.0;
}

function platform_convert(float $amount, string $from, ?string $to = null): float
{
    $from = normalize_currency($from, 'USD');
    $to = normalize_currency((string) ($to ?: platform_currency()), 'USD');
    if ($from === $to) {
        return round_money($amount, $to);
    }
    $ugx = $from === 'UGX' ? $amount : $amount * platform_ugx_rate($from);
    if ($to === 'UGX') {
        return round_money($ugx, 'UGX');
    }
    $per = platform_ugx_rate($to);
    return $per > 0 ? round_money($ugx / $per, $to) : round_money($ugx, $to);
}

function platform_money(float $amount, string $from = 'USD'): string
{
    $ccy = platform_currency();
    return money(platform_convert($amount, $from, $ccy), $ccy);
}

function platform_money_company_fee(array $company, string $field = 'paid'): string
{
    $amt = match ($field) {
        'amount' => company_fee_amount($company),
        'balance' => company_fee_balance($company),
        'remaining' => company_remaining_value($company),
        default => company_fee_paid($company),
    };
    return platform_money($amt, company_fee_currency($company));
}

function platform_online_window_minutes(): int
{
    return 5;
}

function user_is_online(?string $lastSeen): bool
{
    if ($lastSeen === null || trim($lastSeen) === '') {
        return false;
    }
    $t = strtotime($lastSeen);
    if ($t === false) {
        return false;
    }
    return (time() - $t) <= platform_online_window_minutes() * 60;
}

function touch_user_seen(int $userId, bool $login = false): void
{
    if ($userId < 1) {
        return;
    }
    try {
        if ($login && db_has_column(db(), 'users', 'last_login_at')) {
            db_exec('UPDATE users SET last_seen_at = NOW(), last_login_at = NOW() WHERE id = ?', 'i', [$userId]);
            return;
        }
        if (db_has_column(db(), 'users', 'last_seen_at')) {
            db_exec('UPDATE users SET last_seen_at = NOW() WHERE id = ?', 'i', [$userId]);
        }
    } catch (Throwable $e) {
        // Column may not exist until migrate.
    }
}

function record_platform_fee(int $companyId, float $amount, string $currency, string $source, string $note = '', ?string $when = null): void
{
    if ($amount <= 0.009) {
        return;
    }
    $currency = normalize_currency($currency, 'USD');
    $usd = platform_convert($amount, $currency, 'USD');
    $when = $when ?: desk_now()->format('Y-m-d H:i:s');
    try {
        $cid = $companyId > 0 ? $companyId : 0;
        $src = mb_substr($source, 0, 20);
        $noteVal = $note !== '' ? mb_substr($note, 0, 190) : '';
        db_exec(
            'INSERT INTO platform_fee_ledger (company_id, source, amount, currency, amount_usd, occurred_at, note) VALUES (?,?,?,?,?,?,?)',
            'isdsdss',
            [$cid, $src, $amount, $currency, $usd, $when, $noteVal]
        );
    } catch (Throwable $e) {
        error_log('Vellisys fee ledger: ' . $e->getMessage());
    }
}

function record_platform_perf(int $ms, string $path = ''): void
{
    if ($ms < 8 || $ms > 30000) {
        return;
    }
    $path = mb_substr($path !== '' ? $path : (string) ($_SERVER['SCRIPT_NAME'] ?? ''), 0, 120);
    try {
        db_exec('INSERT INTO platform_perf_samples (ms, path, created_at) VALUES (?,?,NOW())', 'is', [$ms, $path]);
        db_exec('DELETE FROM platform_perf_samples WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
    } catch (Throwable $e) {
        // ignore
    }
}

function platform_avg_reload_ms(int $limit = 20): ?float
{
    try {
        $row = db_one('SELECT AVG(ms) AS avg_ms FROM (SELECT ms FROM platform_perf_samples ORDER BY id DESC LIMIT ' . (int) $limit . ') t');
        if ($row && $row['avg_ms'] !== null) {
            return (float) $row['avg_ms'];
        }
    } catch (Throwable $e) {
        return null;
    }
    return null;
}

function platform_health_from_ms(?float $ms): array
{
    if ($ms === null) {
        return ['key' => 'healthy', 'label' => 'Healthy', 'ms' => null];
    }
    if ($ms < 250) {
        return ['key' => 'fast', 'label' => 'Fast', 'ms' => $ms];
    }
    if ($ms < 1000) {
        return ['key' => 'healthy', 'label' => 'Healthy', 'ms' => $ms];
    }
    return ['key' => 'slow', 'label' => 'Slow', 'ms' => $ms];
}

function platform_online_users(): array
{
    try {
        return db_all(
            "SELECT u.id, u.name, u.email, u.company_id, u.last_seen_at, u.last_login_at, c.name AS company_name
             FROM users u
             LEFT JOIN companies c ON c.id = u.company_id
             WHERE u.role <> 'platform' AND u.last_seen_at IS NOT NULL AND u.last_seen_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
             ORDER BY u.last_seen_at DESC",
            'i',
            [platform_online_window_minutes()]
        );
    } catch (Throwable $e) {
        return [];
    }
}

function platform_company_presence(): array
{
    $rows = [];
    try {
        $rows = db_all(
            "SELECT c.id, c.name, c.status, c.plan, c.expires_at, c.paid_term, c.paid_unit, c.fee_amount, c.fee_paid, c.fee_currency,
                    (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id AND u.role <> 'platform') AS users,
                    (SELECT COUNT(*) FROM branches b WHERE b.company_id = c.id) AS branches,
                    (SELECT MAX(u.last_login_at) FROM users u WHERE u.company_id = c.id AND u.role <> 'platform') AS last_login_at,
                    (SELECT MAX(u.last_seen_at) FROM users u WHERE u.company_id = c.id AND u.role <> 'platform') AS last_seen_at,
                    (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id AND u.role <> 'platform' AND u.last_seen_at > DATE_SUB(NOW(), INTERVAL " . (int) platform_online_window_minutes() . " MINUTE)) AS online_users
             FROM companies c
             ORDER BY last_seen_at IS NULL, last_seen_at DESC, c.name"
        );
    } catch (Throwable $e) {
        try {
            $rows = db_all('SELECT c.*, 0 AS users, 0 AS branches, NULL AS last_login_at, NULL AS last_seen_at, 0 AS online_users FROM companies c ORDER BY c.name');
        } catch (Throwable $e2) {
            return [];
        }
    }
    return $rows;
}

function platform_usage_counts(int $companyId, string $from, string $to): array
{
    $docs = 0;
    $acts = 0;
    try {
        $d = db_one(
            "SELECT COUNT(*) AS c FROM documents WHERE company_id = ? AND status = 'issued' AND date BETWEEN ? AND ?",
            'iss',
            [$companyId, $from, $to]
        );
        $docs = (int) ($d['c'] ?? 0);
    } catch (Throwable $e) {
        $docs = 0;
    }
    try {
        $a = db_one(
            'SELECT COUNT(*) AS c FROM company_activities WHERE company_id = ? AND created_at BETWEEN ? AND ?',
            'iss',
            [$companyId, $from . ' 00:00:00', $to . ' 23:59:59']
        );
        $acts = (int) ($a['c'] ?? 0);
    } catch (Throwable $e) {
        $acts = 0;
    }
    return ['documents' => $docs, 'activities' => $acts, 'score' => $docs + $acts];
}

function platform_desk_health(array $row): array
{
    $seen = (string) ($row['last_seen_at'] ?? '');
    $login = (string) ($row['last_login_at'] ?? $seen);
    if (user_is_online($seen)) {
        return ['key' => 'fast', 'label' => 'Active now'];
    }
    $t = $login !== '' ? strtotime($login) : false;
    if ($t === false) {
        return ['key' => 'slow', 'label' => 'No sign-in yet'];
    }
    $days = (time() - $t) / 86400;
    if ($days < 2) {
        return ['key' => 'healthy', 'label' => 'Healthy'];
    }
    if ($days < 14) {
        return ['key' => 'healthy', 'label' => 'Quiet'];
    }
    return ['key' => 'slow', 'label' => 'Idle'];
}

function format_when(?string $dt): string
{
    if ($dt === null || trim($dt) === '') {
        return 'Never';
    }
    $t = strtotime($dt);
    if ($t === false) {
        return 'Never';
    }
    return date('j M Y, H:i', $t);
}
