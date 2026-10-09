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

/**
 * Sum ledger rows in platform currency from original amount+currency.
 * Avoids UGX→USD→UGX round-trip drift on amount_usd (e.g. 200,000 → 200,022).
 *
 * @return float Total in platform_currency()
 */
function platform_fee_sum(?string $fromDate = null, ?string $toDate = null): float
{
    $ccy = platform_currency();
    $sql = 'SELECT currency, COALESCE(SUM(amount),0) AS t FROM platform_fee_ledger';
    $types = '';
    $params = [];
    if ($fromDate !== null && $toDate !== null && $fromDate === $toDate) {
        $sql .= ' WHERE DATE(occurred_at) = ?';
        $types = 's';
        $params = [$fromDate];
    } elseif ($fromDate !== null && $toDate !== null) {
        $sql .= ' WHERE DATE(occurred_at) BETWEEN ? AND ?';
        $types = 'ss';
        $params = [$fromDate, $toDate];
    } elseif ($fromDate !== null) {
        $sql .= ' WHERE DATE(occurred_at) = ?';
        $types = 's';
        $params = [$fromDate];
    }
    $sql .= ' GROUP BY currency';
    try {
        $rows = $types !== '' ? db_all($sql, $types, $params) : db_all($sql);
    } catch (Throwable $e) {
        return 0.0;
    }
    $total = 0.0;
    foreach ($rows as $r) {
        $total += platform_convert((float) ($r['t'] ?? 0), (string) ($r['currency'] ?? 'USD'), $ccy);
    }
    return round_money($total, $ccy);
}

/**
 * Daily (or monthly) series of fee totals in platform currency.
 *
 * @param 'day'|'month' $grain
 * @return array<string, float> keyed by Y-m-d or Y-m
 */
function platform_finance_normalize_bucket(mixed $key, string $grain = 'day'): string
{
    $key = trim((string) $key);
    if ($key === '') {
        return '';
    }
    if ($grain === 'month') {
        return preg_match('/^(\d{4}-\d{2})/', $key, $m) ? $m[1] : $key;
    }
    return preg_match('/^(\d{4}-\d{2}-\d{2})/', $key, $m) ? $m[1] : $key;
}

function platform_fee_series(string $fromDate, string $toDate, string $grain = 'day'): array
{
    $ccy = platform_currency();
    $expr = $grain === 'month' ? 'DATE_FORMAT(occurred_at, "%Y-%m")' : 'DATE(occurred_at)';
    try {
        $rows = db_all(
            "SELECT {$expr} AS bucket, currency, COALESCE(SUM(amount),0) AS t
             FROM platform_fee_ledger
             WHERE DATE(occurred_at) BETWEEN ? AND ?
             GROUP BY bucket, currency",
            'ss',
            [$fromDate, $toDate]
        );
    } catch (Throwable $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $key = platform_finance_normalize_bucket($r['bucket'] ?? '', $grain);
        if ($key === '') {
            continue;
        }
        if (!isset($out[$key])) {
            $out[$key] = 0.0;
        }
        $out[$key] += platform_convert((float) ($r['t'] ?? 0), (string) ($r['currency'] ?? 'USD'), $ccy);
    }
    foreach ($out as $k => $v) {
        $out[$k] = round_money($v, $ccy);
    }
    return $out;
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
    // Throttle presence writes - every page was doing an UPDATE.
    if (!$login) {
        $key = 'seen_at_' . $userId;
        $last = (int) ($_SESSION[$key] ?? 0);
        if ($last > 0 && (time() - $last) < 180) {
            return;
        }
        $_SESSION[$key] = time();
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

/** Saved expense names for reuse (datalist). */
function platform_expense_titles(): array
{
    try {
        $rows = db_all('SELECT DISTINCT title FROM platform_expenses WHERE title <> \'\' ORDER BY title ASC LIMIT 300');
    } catch (Throwable $e) {
        return [];
    }
    return array_values(array_filter(array_map(static fn ($r) => (string) ($r['title'] ?? ''), $rows)));
}

function platform_expense_get(int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    try {
        return db_one('SELECT * FROM platform_expenses WHERE id = ?', 'i', [$id]);
    } catch (Throwable $e) {
        return null;
    }
}

function platform_expense_save(array $fields, ?int $actorId = null): array
{
    $id = (int) ($fields['id'] ?? 0);
    $title = mb_substr(trim((string) ($fields['title'] ?? $fields['expense'] ?? '')), 0, 190);
    $amount = function_exists('money_parse') ? money_parse((string) ($fields['amount'] ?? '0')) : (float) ($fields['amount'] ?? 0);
    $currency = normalize_currency((string) ($fields['currency'] ?? platform_currency()), platform_currency());
    $on = trim((string) ($fields['occurred_on'] ?? $fields['date'] ?? ''));
    $note = mb_substr(trim((string) ($fields['note'] ?? '')), 0, 500);
    if ($title === '') {
        return ['ok' => false, 'error' => 'Enter what the expense was for.'];
    }
    if ($amount <= 0.009) {
        return ['ok' => false, 'error' => 'Enter the expense amount.'];
    }
    if ($on === '' || !DateTime::createFromFormat('Y-m-d', $on)) {
        return ['ok' => false, 'error' => 'Pick the expense date.'];
    }
    $usd = platform_convert($amount, $currency, 'USD');
    $uid = (int) ($actorId ?? (current_user()['id'] ?? 0));
    try {
        if ($id > 0) {
            $existing = platform_expense_get($id);
            if (!$existing) {
                return ['ok' => false, 'error' => 'Expense not found.'];
            }
            db_exec(
                'UPDATE platform_expenses SET title=?, amount=?, currency=?, amount_usd=?, occurred_on=?, note=? WHERE id=?',
                'sdsdssi',
                [$title, $amount, $currency, $usd, $on, $note, $id]
            );
            return ['ok' => true, 'id' => $id];
        }
        $newId = db_exec(
            'INSERT INTO platform_expenses (title, amount, currency, amount_usd, occurred_on, note, created_by) VALUES (?,?,?,?,?,?,?)',
            'sdsdssi',
            [$title, $amount, $currency, $usd, $on, $note, $uid > 0 ? $uid : 0]
        );
        return ['ok' => true, 'id' => (int) $newId];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not save that expense.'];
    }
}

function platform_expense_delete(int $id): array
{
    if ($id < 1) {
        return ['ok' => false, 'error' => 'Expense not found.'];
    }
    try {
        $existing = platform_expense_get($id);
        if (!$existing) {
            return ['ok' => false, 'error' => 'Expense not found.'];
        }
        db_exec('DELETE FROM platform_expenses WHERE id = ?', 'i', [$id]);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not delete that expense.'];
    }
    return ['ok' => true];
}

/** @return list<array<string,mixed>> */
function platform_expenses_list(?string $fromDate = null, ?string $toDate = null, int $limit = 500): array
{
    $sql = 'SELECT * FROM platform_expenses';
    $types = '';
    $params = [];
    if ($fromDate !== null && $toDate !== null) {
        $sql .= ' WHERE occurred_on BETWEEN ? AND ?';
        $types = 'ss';
        $params = [$fromDate, $toDate];
    }
    $sql .= ' ORDER BY occurred_on DESC, id DESC LIMIT ' . max(1, min(2000, $limit));
    try {
        return $types !== '' ? db_all($sql, $types, $params) : db_all($sql);
    } catch (Throwable $e) {
        return [];
    }
}

function platform_expense_sum(?string $fromDate = null, ?string $toDate = null): float
{
    $ccy = platform_currency();
    $sql = 'SELECT currency, COALESCE(SUM(amount),0) AS t FROM platform_expenses';
    $types = '';
    $params = [];
    if ($fromDate !== null && $toDate !== null) {
        $sql .= ' WHERE occurred_on BETWEEN ? AND ?';
        $types = 'ss';
        $params = [$fromDate, $toDate];
    }
    $sql .= ' GROUP BY currency';
    try {
        $rows = $types !== '' ? db_all($sql, $types, $params) : db_all($sql);
    } catch (Throwable $e) {
        return 0.0;
    }
    $total = 0.0;
    foreach ($rows as $r) {
        $total += platform_convert((float) ($r['t'] ?? 0), (string) ($r['currency'] ?? 'USD'), $ccy);
    }
    return round_money($total, $ccy);
}

/**
 * @param 'day'|'month' $grain
 * @return array<string, float>
 */
function platform_expense_series(string $fromDate, string $toDate, string $grain = 'day'): array
{
    $ccy = platform_currency();
    $expr = $grain === 'month' ? 'DATE_FORMAT(occurred_on, "%Y-%m")' : 'occurred_on';
    try {
        $rows = db_all(
            "SELECT {$expr} AS bucket, currency, COALESCE(SUM(amount),0) AS t
             FROM platform_expenses
             WHERE occurred_on BETWEEN ? AND ?
             GROUP BY bucket, currency",
            'ss',
            [$fromDate, $toDate]
        );
    } catch (Throwable $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $key = platform_finance_normalize_bucket($r['bucket'] ?? '', $grain);
        if ($key === '') {
            continue;
        }
        if (!isset($out[$key])) {
            $out[$key] = 0.0;
        }
        $out[$key] += platform_convert((float) ($r['t'] ?? 0), (string) ($r['currency'] ?? 'USD'), $ccy);
    }
    foreach ($out as $k => $v) {
        $out[$k] = round_money($v, $ccy);
    }
    return $out;
}

/** Expense totals by title for the period (platform currency). */
function platform_expense_breakdown(?string $fromDate = null, ?string $toDate = null, int $limit = 12): array
{
    $ccy = platform_currency();
    $sql = 'SELECT title, currency, COALESCE(SUM(amount),0) AS t FROM platform_expenses';
    $types = '';
    $params = [];
    if ($fromDate !== null && $toDate !== null) {
        $sql .= ' WHERE occurred_on BETWEEN ? AND ?';
        $types = 'ss';
        $params = [$fromDate, $toDate];
    }
    $sql .= ' GROUP BY title, currency';
    try {
        $rows = $types !== '' ? db_all($sql, $types, $params) : db_all($sql);
    } catch (Throwable $e) {
        return [];
    }
    $byTitle = [];
    foreach ($rows as $r) {
        $title = trim((string) ($r['title'] ?? '')) ?: 'Other';
        if (!isset($byTitle[$title])) {
            $byTitle[$title] = 0.0;
        }
        $byTitle[$title] += platform_convert((float) ($r['t'] ?? 0), (string) ($r['currency'] ?? 'USD'), $ccy);
    }
    arsort($byTitle, SORT_NUMERIC);
    $out = [];
    $n = 0;
    foreach ($byTitle as $title => $amt) {
        $out[] = ['title' => $title, 'amount' => round_money((float) $amt, $ccy)];
        $n++;
        if ($n >= $limit) {
            break;
        }
    }
    return $out;
}

/**
 * Fill day or month buckets between two dates with zeros.
 * Long ranges keep the most recent buckets so charts stay aligned with current totals.
 *
 * @return list<string>
 */
function platform_finance_axis(string $fromDate, string $toDate, string $grain = 'day'): array
{
    try {
        $from = new DateTimeImmutable($fromDate);
        $to = new DateTimeImmutable($toDate);
    } catch (Throwable $e) {
        return [];
    }
    if ($to < $from) {
        [$from, $to] = [$to, $from];
    }
    $out = [];
    if ($grain === 'month') {
        $cursor = $from->modify('first day of this month');
        $end = $to->modify('first day of this month');
        while ($cursor <= $end) {
            $out[] = $cursor->format('Y-m');
            $cursor = $cursor->modify('+1 month');
        }
        if (count($out) > 60) {
            $out = array_values(array_slice($out, -60));
        }
        return $out;
    }
    $cursor = $from;
    while ($cursor <= $to) {
        $out[] = $cursor->format('Y-m-d');
        $cursor = $cursor->modify('+1 day');
    }
    if (count($out) > 120) {
        $out = array_values(array_slice($out, -120));
    }
    return $out;
}

/**
 * Chart X-axis that stays aligned with real series data (avoids empty charts when
 * long "by day" ranges or DATE key formatting would otherwise miss buckets).
 *
 * @param array<string, float> ...$seriesList
 * @return list<string>
 */
function platform_finance_chart_axis(string $fromDate, string $toDate, string $grain, array ...$seriesList): array
{
    $axis = platform_finance_axis($fromDate, $toDate, $grain);
    $dataKeys = [];
    foreach ($seriesList as $series) {
        foreach ($series as $k => $v) {
            if (abs((float) $v) < 0.0001) {
                continue;
            }
            $nk = platform_finance_normalize_bucket($k, $grain);
            if ($nk !== '') {
                $dataKeys[$nk] = true;
            }
        }
    }
    if (!$dataKeys) {
        return $axis;
    }
    $keys = array_keys($dataKeys);
    sort($keys);
    $overlap = false;
    foreach ($keys as $k) {
        if (in_array($k, $axis, true)) {
            $overlap = true;
            break;
        }
    }
    if (!$overlap) {
        return platform_finance_axis($keys[0], $keys[count($keys) - 1], $grain);
    }
    $missing = false;
    foreach ($keys as $k) {
        if (!in_array($k, $axis, true)) {
            $missing = true;
            break;
        }
    }
    if ($missing) {
        return platform_finance_axis($keys[0], $keys[count($keys) - 1], $grain);
    }
    return $axis;
}

function platform_bank_move_get(int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    try {
        return db_one('SELECT * FROM platform_bank_moves WHERE id = ?', 'i', [$id]);
    } catch (Throwable $e) {
        return null;
    }
}

function platform_bank_normalize_kind(string $kind): string
{
    $kind = strtolower(trim($kind));
    return in_array($kind, ['save', 'deposit', 'saving'], true) ? 'save' : 'withdraw';
}

function platform_bank_move_save(array $fields, ?int $actorId = null): array
{
    $id = (int) ($fields['id'] ?? 0);
    $kind = platform_bank_normalize_kind((string) ($fields['kind'] ?? 'save'));
    $amount = function_exists('money_parse') ? money_parse((string) ($fields['amount'] ?? '0')) : (float) ($fields['amount'] ?? 0);
    $currency = normalize_currency((string) ($fields['currency'] ?? platform_currency()), platform_currency());
    $on = trim((string) ($fields['occurred_on'] ?? $fields['date'] ?? ''));
    $note = mb_substr(trim((string) ($fields['note'] ?? '')), 0, 500);
    if ($amount <= 0.009) {
        return ['ok' => false, 'error' => 'Enter the amount.'];
    }
    if ($on === '' || !DateTime::createFromFormat('Y-m-d', $on)) {
        return ['ok' => false, 'error' => 'Pick the date.'];
    }
    $usd = platform_convert($amount, $currency, 'USD');
    $uid = (int) ($actorId ?? (current_user()['id'] ?? 0));
    try {
        if ($id > 0) {
            if (!platform_bank_move_get($id)) {
                return ['ok' => false, 'error' => 'Bank line not found.'];
            }
            db_exec(
                'UPDATE platform_bank_moves SET kind=?, amount=?, currency=?, amount_usd=?, occurred_on=?, note=? WHERE id=?',
                'sdsdssi',
                [$kind, $amount, $currency, $usd, $on, $note, $id]
            );
            return ['ok' => true, 'id' => $id];
        }
        $newId = db_exec(
            'INSERT INTO platform_bank_moves (kind, amount, currency, amount_usd, occurred_on, note, created_by) VALUES (?,?,?,?,?,?,?)',
            'sdsdssi',
            [$kind, $amount, $currency, $usd, $on, $note, $uid > 0 ? $uid : 0]
        );
        return ['ok' => true, 'id' => (int) $newId];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not save that bank line.'];
    }
}

function platform_bank_move_delete(int $id): array
{
    if ($id < 1) {
        return ['ok' => false, 'error' => 'Bank line not found.'];
    }
    try {
        if (!platform_bank_move_get($id)) {
            return ['ok' => false, 'error' => 'Bank line not found.'];
        }
        db_exec('DELETE FROM platform_bank_moves WHERE id = ?', 'i', [$id]);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not delete that bank line.'];
    }
    return ['ok' => true];
}

/** @return list<array<string,mixed>> */
function platform_bank_moves_list(?string $fromDate = null, ?string $toDate = null, int $limit = 500): array
{
    $sql = 'SELECT * FROM platform_bank_moves';
    $types = '';
    $params = [];
    if ($fromDate !== null && $toDate !== null) {
        $sql .= ' WHERE occurred_on BETWEEN ? AND ?';
        $types = 'ss';
        $params = [$fromDate, $toDate];
    }
    $sql .= ' ORDER BY occurred_on DESC, id DESC LIMIT ' . max(1, min(2000, $limit));
    try {
        return $types !== '' ? db_all($sql, $types, $params) : db_all($sql);
    } catch (Throwable $e) {
        return [];
    }
}

function platform_bank_sum(string $kind = '', ?string $fromDate = null, ?string $toDate = null): float
{
    $ccy = platform_currency();
    $sql = 'SELECT currency, COALESCE(SUM(amount),0) AS t FROM platform_bank_moves WHERE 1=1';
    $types = '';
    $params = [];
    if ($kind !== '') {
        $sql .= ' AND kind = ?';
        $types .= 's';
        $params[] = platform_bank_normalize_kind($kind);
    }
    if ($fromDate !== null && $toDate !== null) {
        $sql .= ' AND occurred_on BETWEEN ? AND ?';
        $types .= 'ss';
        $params[] = $fromDate;
        $params[] = $toDate;
    }
    $sql .= ' GROUP BY currency';
    try {
        $rows = $types !== '' ? db_all($sql, $types, $params) : db_all($sql);
    } catch (Throwable $e) {
        return 0.0;
    }
    $total = 0.0;
    foreach ($rows as $r) {
        $total += platform_convert((float) ($r['t'] ?? 0), (string) ($r['currency'] ?? 'USD'), $ccy);
    }
    return round_money($total, $ccy);
}

function platform_bank_balance(): float
{
    $ccy = platform_currency();
    return round_money(platform_bank_sum('save') - platform_bank_sum('withdraw'), $ccy);
}

/**
 * @param 'day'|'month' $grain
 * @return array<string, float>
 */
function platform_bank_series(string $kind, string $fromDate, string $toDate, string $grain = 'day'): array
{
    $ccy = platform_currency();
    $kind = platform_bank_normalize_kind($kind);
    $expr = $grain === 'month' ? 'DATE_FORMAT(occurred_on, "%Y-%m")' : 'occurred_on';
    try {
        $rows = db_all(
            "SELECT {$expr} AS bucket, currency, COALESCE(SUM(amount),0) AS t
             FROM platform_bank_moves
             WHERE kind = ? AND occurred_on BETWEEN ? AND ?
             GROUP BY bucket, currency",
            'sss',
            [$kind, $fromDate, $toDate]
        );
    } catch (Throwable $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $key = platform_finance_normalize_bucket($r['bucket'] ?? '', $grain);
        if ($key === '') {
            continue;
        }
        if (!isset($out[$key])) {
            $out[$key] = 0.0;
        }
        $out[$key] += platform_convert((float) ($r['t'] ?? 0), (string) ($r['currency'] ?? 'USD'), $ccy);
    }
    foreach ($out as $k => $v) {
        $out[$k] = round_money($v, $ccy);
    }
    return $out;
}

function record_platform_perf(int $ms, string $path = ''): void
{
    if ($ms < 8 || $ms > 30000) {
        return;
    }
    $path = mb_substr($path !== '' ? $path : (string) ($_SERVER['SCRIPT_NAME'] ?? ''), 0, 120);
    try {
        db_exec('INSERT INTO platform_perf_samples (ms, path, created_at) VALUES (?,?,NOW())', 'is', [$ms, $path]);
        // Prune at most once per day - DELETE on every ping was wasteful.
        $pruneKey = 'perf_prune_day';
        $day = date('Y-m-d');
        if ((string) ($_SESSION[$pruneKey] ?? '') !== $day) {
            $_SESSION[$pruneKey] = $day;
            db_exec('DELETE FROM platform_perf_samples WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
        }
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

/** Classify download speed (Mbps) for the Internet speed sensor. */
function platform_health_from_mbps(?float $mbps): array
{
    if ($mbps === null) {
        return ['key' => 'healthy', 'label' => 'Normal', 'mbps' => null];
    }
    if ($mbps >= 25) {
        return ['key' => 'fast', 'label' => 'Fast', 'mbps' => $mbps];
    }
    if ($mbps >= 5) {
        return ['key' => 'healthy', 'label' => 'Normal', 'mbps' => $mbps];
    }
    return ['key' => 'slow', 'label' => 'Slow', 'mbps' => $mbps];
}

function platform_reload_stats(int $limit = 20): array
{
    $ms = platform_avg_reload_ms($limit);
    $health = platform_health_from_ms($ms);
    $spark = [];
    try {
        $samples = db_all('SELECT ms FROM platform_perf_samples ORDER BY id DESC LIMIT 24');
        $spark = array_map(static fn ($r) => (float) ($r['ms'] ?? 0), array_reverse($samples));
    } catch (Throwable $e) {
        $spark = [];
    }
    return [
        'ms' => $ms,
        'label' => $ms === null ? '-' : ((int) round($ms) . ' ms'),
        'health' => $health,
        'spark' => $spark,
    ];
}

/**
 * Devices that installed the app and allowed alerts (push subscriptions).
 * Live = last_seen within the same online window as Active now.
 *
 * @return array{installs:int,live:int,users:int}
 */
function platform_device_install_stats(): array
{
    $out = ['installs' => 0, 'live' => 0, 'users' => 0];
    try {
        if (function_exists('vapid_ensure_tables')) {
            vapid_ensure_tables();
        }
        $mins = max(1, platform_online_window_minutes());
        $row = db_one(
            'SELECT
                COUNT(*) AS installs,
                COUNT(DISTINCT user_id) AS users,
                SUM(CASE WHEN last_seen > DATE_SUB(NOW(), INTERVAL ? MINUTE) THEN 1 ELSE 0 END) AS live
             FROM push_subscriptions',
            'i',
            [$mins]
        );
        if ($row) {
            $out['installs'] = (int) ($row['installs'] ?? 0);
            $out['users'] = (int) ($row['users'] ?? 0);
            $out['live'] = (int) ($row['live'] ?? 0);
        }
    } catch (Throwable $e) {
        // Table may not exist yet on a fresh install.
    }
    return $out;
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
                    c.loc_office, c.loc_street, c.loc_city, c.loc_region, c.loc_country,
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
    $raw = trim($dt);
    try {
        $tz = new DateTimeZone(function_exists('desk_timezone_id') ? desk_timezone_id() : 'Africa/Kampala');
        // Naive MySQL datetimes are wall-clock in the active desk/platform zone (EAT for Super Admin).
        if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?/', $raw)) {
            $dtObj = new DateTimeImmutable(str_replace('T', ' ', substr($raw, 0, 19)), $tz);
        } else {
            $dtObj = new DateTimeImmutable($raw);
            $dtObj = $dtObj->setTimezone($tz);
        }
        return $dtObj->format('j M Y, H:i');
    } catch (Throwable $e) {
        $t = strtotime($raw);
        return $t === false ? 'Never' : date('j M Y, H:i', $t);
    }
}

function platform_countries(): array
{
    return [
        'Uganda',
        'Kenya',
        'Tanzania',
        'Rwanda',
        'Burundi',
        'South Sudan',
        'Democratic Republic of the Congo',
        'Ethiopia',
        'Nigeria',
        'Ghana',
        'South Africa',
        'United Arab Emirates',
        'United Kingdom',
        'United States',
    ];
}

function uganda_regions(): array
{
    return ['Central', 'Eastern', 'Northern', 'Western'];
}

function uganda_districts(): array
{
    return [
        'Kampala', 'Wakiso', 'Mukono', 'Mpigi', 'Luweero', 'Nakasongola', 'Mityana', 'Buikwe',
        'Jinja', 'Mbale', 'Tororo', 'Soroti', 'Iganga', 'Busia',
        'Gulu', 'Lira', 'Arua', 'Kitgum', 'Moroto',
        'Mbarara', 'Fort Portal', 'Kasese', 'Kabale', 'Hoima', 'Masaka', 'Bushenyi',
    ];
}

function company_loc(array $company, string $field): string
{
    return trim((string) ($company['loc_' . $field] ?? ''));
}

function company_has_admin_location(array $company): bool
{
    return company_loc($company, 'country') !== '' || company_loc($company, 'city') !== '';
}

function company_location_line(array $company): string
{
    $parts = array_values(array_filter([
        company_loc($company, 'office'),
        company_loc($company, 'street'),
        company_loc($company, 'city'),
        company_loc($company, 'region'),
        company_loc($company, 'country'),
    ], static fn ($p) => $p !== ''));
    return $parts ? implode(', ', $parts) : '';
}

function posted_admin_location(): array
{
    $known = platform_countries();
    $country = post_plain('loc_country', 80);
    if ($country === 'other') {
        $country = post_plain('loc_country_other', 80);
    }
    if ($country !== '' && !in_array($country, $known, true)) {
        $country = mb_substr($country, 0, 80);
    }
    $regionUg = post_plain('loc_region_ug', 120);
    $regionOther = post_plain('loc_region_other', 120);
    $region = $country === 'Uganda' ? $regionUg : $regionOther;
    if ($country === '' && $regionUg !== '') {
        $region = $regionUg;
    }
    return [
        'office' => post_plain('loc_office', 80),
        'street' => post_plain('loc_street', 160),
        'city' => post_plain('loc_city', 120),
        'region' => $region,
        'country' => $country,
    ];
}

function location_place_key(array $company, string $level): string
{
    $country = company_loc($company, 'country');
    $region = company_loc($company, 'region');
    $city = company_loc($company, 'city');
    return match ($level) {
        'region' => ($country !== '' ? $country : 'No country') . ' · ' . ($region !== '' ? $region : 'No region'),
        'city' => ($city !== '' ? $city : 'No city') . ($region !== '' ? ' · ' . $region : '') . ($country !== '' ? ' · ' . $country : ''),
        default => $country !== '' ? $country : 'No country set',
    };
}

function location_perf_label(array $row): array
{
    $health = $row['desk_health'] ?? platform_desk_health($row);
    $score = (int) ($row['use_score'] ?? 0);
    $key = (string) ($health['key'] ?? 'slow');
    if ($key === 'fast' || ($key === 'healthy' && $score >= 8)) {
        return ['key' => 'fast', 'label' => 'Performing well'];
    }
    if ($key === 'healthy') {
        return ['key' => 'healthy', 'label' => 'Steady'];
    }
    if (($health['label'] ?? '') === 'No sign-in yet') {
        return ['key' => 'slow', 'label' => 'Not started'];
    }
    return ['key' => 'slow', 'label' => 'Needs attention'];
}

function platform_delete_company(int $id): array
{
    if ($id < 1) {
        return ['ok' => false, 'error' => 'Company not found.'];
    }
    $company = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$id]);
    if (!$company) {
        return ['ok' => false, 'error' => 'Company not found.'];
    }
    $name = (string) $company['name'];
    $db = db();
    $userIds = array_map(static fn ($r) => (int) $r['id'], db_all("SELECT id FROM users WHERE company_id = ? AND role <> 'platform'", 'i', [$id]));
    $hasReset = function_exists('company_reset_ids')
        && function_exists('company_reset_has_table')
        && function_exists('company_reset_in');
    $docIds = $hasReset
        ? company_reset_ids('SELECT id FROM documents WHERE company_id = ?', 'i', [$id])
        : [];
    $countIds = ($hasReset && company_reset_has_table('stock_counts'))
        ? company_reset_ids('SELECT id FROM stock_counts WHERE company_id = ?', 'i', [$id])
        : [];

    $db->begin_transaction();
    try {
        if ($hasReset && $docIds && company_reset_has_table('emails')) {
            company_reset_in('DELETE FROM emails WHERE document_id IN', $docIds);
        }
        if ($hasReset && $docIds) {
            company_reset_in('DELETE FROM document_items WHERE document_id IN', $docIds);
        }
        if ($hasReset && $countIds && company_reset_has_table('stock_count_lines')) {
            company_reset_in('DELETE FROM stock_count_lines WHERE count_id IN', $countIds);
        }
        if ($hasReset && $userIds && company_reset_has_table('notification_dismissals')) {
            company_reset_in('DELETE FROM notification_dismissals WHERE user_id IN', $userIds);
        }
        if ($hasReset && $userIds && company_reset_has_table('push_sent')) {
            company_reset_in('DELETE FROM push_sent WHERE user_id IN', $userIds);
        }
        if ($hasReset && $userIds && company_reset_has_table('push_subscriptions')) {
            company_reset_in('DELETE FROM push_subscriptions WHERE user_id IN', $userIds);
        }
        if ($hasReset && $userIds && company_reset_has_table('emails')) {
            company_reset_in('DELETE FROM emails WHERE document_id IS NULL AND user_id IN', $userIds);
        }

        $keep = ['companies', 'signups', 'website_orders'];
        $res = $db->query('SHOW TABLES');
        $tables = [];
        if ($res) {
            while ($row = $res->fetch_row()) {
                $tables[] = (string) $row[0];
            }
        }
        foreach ($tables as $table) {
            if (in_array($table, $keep, true)) {
                continue;
            }
            if ($table === 'users') {
                continue;
            }
            if (!function_exists('db_has_column') || !db_has_column($db, $table, 'company_id')) {
                continue;
            }
            $safe = '`' . str_replace('`', '', $table) . '`';
            db_exec("DELETE FROM {$safe} WHERE company_id = ?", 'i', [$id]);
        }
        // Drop fee rows for this desk so deleted demo accounts (e.g. Ofagros) do not inflate Taken in.
        try {
            db_exec('DELETE FROM platform_fee_ledger WHERE company_id = ?', 'i', [$id]);
        } catch (Throwable $e) {
            // Table may not exist on very old installs.
        }
        db_exec("DELETE FROM users WHERE company_id = ? AND role <> 'platform'", 'i', [$id]);
        if ($hasReset && company_reset_has_table('signups') && db_has_column($db, 'signups', 'company_id')) {
            db_exec('UPDATE signups SET company_id = NULL WHERE company_id = ?', 'i', [$id]);
        }
        if ($hasReset && company_reset_has_table('website_orders') && db_has_column($db, 'website_orders', 'company_id')) {
            db_exec('UPDATE website_orders SET company_id = NULL WHERE company_id = ?', 'i', [$id]);
        }
        db_exec('DELETE FROM companies WHERE id = ?', 'i', [$id]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        return ['ok' => false, 'error' => 'Could not delete that company. ' . $e->getMessage()];
    }

    $backupDir = ROOT_PATH . '/uploads/backups/' . $id;
    if (is_dir($backupDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backupDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($backupDir);
    }

    return ['ok' => true, 'name' => $name];
}

