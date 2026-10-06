<?php
declare(strict_types=1);

/** Field sales agents (Vellisys staff portal - not desk till access). */

function sales_statuses(): array
{
    return [
        'interested' => 'Interested',
        'follow_up' => 'Follow up',
        'rejected' => 'Rejected',
        'onboarding' => 'Onboarding',
        'onboarded' => 'Onboarded',
    ];
}

function sales_status_label(string $status): string
{
    return sales_statuses()[$status] ?? $status;
}

function sales_status_pill_class(string $status): string
{
    $status = trim($status);
    if (isset(sales_statuses()[$status])) {
        return 'pill sales-' . $status;
    }
    return 'pill';
}

/** Optional follow-up clock time as HH:MM:SS, or null when blank/invalid. */
function sales_normalize_follow_up_time(?string $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $raw, $m)) {
        $h = (int) $m[1];
        $i = (int) $m[2];
        if ($h >= 0 && $h <= 23 && $i >= 0 && $i <= 59) {
            return sprintf('%02d:%02d:00', $h, $i);
        }
    }
    return null;
}

/** Value for <input type="time"> from a DB TIME / posted string. */
function sales_follow_up_time_input(?string $raw): string
{
    $t = sales_normalize_follow_up_time($raw);
    return $t !== null ? substr($t, 0, 5) : '';
}

function sales_format_follow_up_time(?string $raw): string
{
    $t = sales_normalize_follow_up_time($raw);
    if ($t === null) {
        return '';
    }
    $ts = strtotime('1970-01-01 ' . $t);
    return $ts ? date('g:i A', $ts) : '';
}

/** Date plus optional time for lists and reminders. */
function sales_format_follow_up(?array $lead): string
{
    $d = trim((string) ($lead['follow_up_date'] ?? ''));
    if ($d === '') {
        return '-';
    }
    $out = function_exists('format_date') ? format_date($d) : $d;
    $timeLabel = sales_format_follow_up_time($lead['follow_up_time'] ?? null);
    if ($timeLabel !== '') {
        $out .= ' · ' . $timeLabel;
    }
    return $out;
}

/** Interest rating 1-5 label for follow-up tracking. */
function sales_interest_label(int $rating): string
{
    return match (max(0, min(5, $rating))) {
        1 => '1 · Low',
        2 => '2',
        3 => '3 · Medium',
        4 => '4 · High',
        5 => '5 · Very high',
        default => 'Not rated',
    };
}

function sales_interest_pill_class(int $rating): string
{
    $rating = max(0, min(5, $rating));
    if ($rating >= 4) {
        return 'pill sales-interest-high';
    }
    if ($rating === 3) {
        return 'pill sales-interest-mid';
    }
    if ($rating >= 1) {
        return 'pill sales-interest-low';
    }
    return 'pill muted';
}

/** Count of open follow-ups for a sales agent (menu badge). */
function sales_open_followups_count(int $agentId): int
{
    static $cache = [];
    if ($agentId < 1) {
        return 0;
    }
    if (array_key_exists($agentId, $cache)) {
        return $cache[$agentId];
    }
    try {
        $row = db_one(
            "SELECT COUNT(*) AS n FROM sales_leads
             WHERE agent_id = ? AND status = 'follow_up' AND deleted_at IS NULL
               AND follow_up_done_at IS NULL AND follow_up_date IS NOT NULL",
            'i',
            [$agentId]
        );
        $cache[$agentId] = (int) ($row['n'] ?? 0);
    } catch (Throwable $e) {
        $cache[$agentId] = 0;
    }
    return $cache[$agentId];
}

/**
 * Open follow-ups past their due date (not attended).
 * Pass null agentId for the whole team (super-admin).
 */
function sales_overdue_followups_count(?int $agentId = null): int
{
    static $cache = [];
    $key = $agentId === null ? 0 : (int) $agentId;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $where = "status = 'follow_up' AND deleted_at IS NULL
                  AND follow_up_done_at IS NULL AND follow_up_date IS NOT NULL
                  AND follow_up_date < ?";
        $types = 's';
        $params = [today()];
        if ($agentId !== null && $agentId > 0) {
            $where .= ' AND agent_id = ?';
            $types .= 'i';
            $params[] = $agentId;
        }
        $row = db_one("SELECT COUNT(*) AS n FROM sales_leads WHERE {$where}", $types, $params);
        $cache[$key] = (int) ($row['n'] ?? 0);
    } catch (Throwable $e) {
        $cache[$key] = 0;
    }
    return $cache[$key];
}

/** Whether an open follow-up lead is past its due date. */
function sales_lead_followup_overdue(?array $lead): bool
{
    if (!$lead) {
        return false;
    }
    if ((string) ($lead['status'] ?? '') !== 'follow_up') {
        return false;
    }
    if (!empty($lead['follow_up_done_at'])) {
        return false;
    }
    $due = trim((string) ($lead['follow_up_date'] ?? ''));
    return $due !== '' && $due < today();
}

function sales_reject_reasons(): array
{
    return [
        'pricing' => 'Pricing too high',
        'nature_of_business' => 'Cannot cover nature of business',
        'already_using' => 'Already using another system',
        'not_ready' => 'Not ready / timing',
        'no_budget' => 'No budget this period',
        'decision_maker' => 'Could not reach decision maker',
        'other' => 'Other',
    ];
}

function sales_reject_reason_label(string $key): string
{
    return sales_reject_reasons()[$key] ?? $key;
}

function sales_rejection_breakdown(?int $agentId, string $from, string $to): array
{
    $where = "status = 'rejected' AND DATE(created_at) >= ? AND DATE(created_at) <= ?";
    $types = 'ss';
    $params = [$from, $to];
    if ($agentId) {
        $where .= ' AND agent_id = ?';
        $types .= 'i';
        $params[] = $agentId;
    }
    $rows = db_all(
        "SELECT COALESCE(NULLIF(TRIM(rejected_category), ''), 'other') AS cat, COUNT(*) AS n
         FROM sales_leads WHERE {$where}
         GROUP BY COALESCE(NULLIF(TRIM(rejected_category), ''), 'other')
         ORDER BY n DESC, cat ASC",
        $types,
        $params
    );
    $labels = sales_reject_reasons();
    $out = [];
    $total = 0;
    foreach ($rows as $r) {
        $key = (string) $r['cat'];
        $n = (int) $r['n'];
        $total += $n;
        $out[] = [
            'key' => $key,
            'label' => $labels[$key] ?? ($key === 'other' ? 'Other / not set' : $key),
            'count' => $n,
        ];
    }
    foreach ($labels as $key => $label) {
        $found = false;
        foreach ($out as $row) {
            if ($row['key'] === $key) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $out[] = ['key' => $key, 'label' => $label, 'count' => 0];
        }
    }
    return ['total' => $total, 'rows' => $out];
}

function sales_render_rejection_report(array $breakdown, array $opts = []): void
{
    $rows = $breakdown['rows'] ?? [];
    $total = (int) ($breakdown['total'] ?? 0);
    $title = (string) ($opts['title'] ?? 'Rejections by reason');
    ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('ban', 16) ?><?= h($title) ?></h2></div>
  <?php if ($total < 1): ?>
    <p class="empty">No rejections in this period yet.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead><tr><th>Reason</th><th class="right">Count</th><th class="right">Share</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row):
              if ((int) $row['count'] < 1) {
                  continue;
              }
              $pct = $total > 0 ? (int) round(100 * (int) $row['count'] / $total) : 0;
              ?>
            <tr>
              <td><?= h((string) $row['label']) ?></td>
              <td class="right mono"><?= (int) $row['count'] ?></td>
              <td class="right mono"><?= $pct ?>%</td>
              <td style="min-width:120px">
                <div class="sales-goal-track" role="img" aria-label="<?= (int) $row['count'] ?> of <?= $total ?>">
                  <span style="width:<?= min(100, $pct) ?>%;background:#b42318"></span>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th>Total rejected</th><th class="right mono"><?= $total ?></th><th></th><th></th></tr></tfoot>
      </table>
    </div>
    <p class="hint" style="padding:0 16px 14px">Use these reasons to improve pricing, packaging and pitch.</p>
  <?php endif; ?>
</div>
    <?php
}

function is_sales_agent(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user && ($user['role'] ?? '') === 'sales_agent';
}

function sales_home(): string
{
    return 'sales_home.php';
}

function require_sales_agent(): array
{
    $user = require_login();
    if (!is_sales_agent($user)) {
        if (($user['role'] ?? '') === 'platform') {
            redirect('admin_sales.php');
        }
        flash('That page is for sales agents.', 'err');
        redirect(($user['role'] ?? '') === 'platform' ? platform_home() : 'dashboard.php');
    }
    if (($user['status'] ?? 'live') === 'suspended') {
        unset($_SESSION['user_id'], $_SESSION['company_id'], $_SESSION['role']);
        if (function_exists('clear_remember_cookies')) {
            clear_remember_cookies();
        }
        flash('Your sales login is suspended. Contact Vellisys.', 'err');
        redirect('login.php');
    }
    return $user;
}

function sales_packages(): array
{
    if (function_exists('pricing_packages')) {
        $out = [];
        foreach (pricing_packages() as $pkg) {
            $key = (string) ($pkg['key'] ?? '');
            $name = (string) ($pkg['name'] ?? $key);
            if ($key !== '') {
                $out[$key] = $name;
            }
        }
        if ($out) {
            return $out;
        }
    }
    return [
        'solo' => 'Vellisys Start',
        'studio' => 'Vellisys Business',
        'practice' => 'Vellisys Pro',
    ];
}

function sales_agents(bool $activeOnly = false): array
{
    $sql = "SELECT * FROM users WHERE role = 'sales_agent'";
    if ($activeOnly) {
        $sql .= " AND COALESCE(status, 'live') = 'live'";
    }
    $sql .= ' ORDER BY name, email';
    return db_all($sql);
}

function sales_agent(int $id): ?array
{
    return db_one("SELECT * FROM users WHERE id = ? AND role = 'sales_agent'", 'i', [$id]);
}

/** Display Employee ID for a sales agent (auto SA-0001 style when unset). */
function sales_employee_id(array $agent): string
{
    $code = trim((string) ($agent['employee_id'] ?? ''));
    if ($code !== '') {
        return $code;
    }
    $id = (int) ($agent['id'] ?? 0);
    return $id > 0 ? ('SA-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT)) : '';
}

function sales_agent_save(array $fields, ?int $id = null): array
{
    $name = mb_substr(trim((string) ($fields['name'] ?? '')), 0, 120);
    $email = strtolower(mb_substr(trim((string) ($fields['email'] ?? '')), 0, 190));
    $phone = mb_substr(trim((string) ($fields['phone'] ?? '')), 0, 40);
    $employeeId = mb_substr(trim((string) ($fields['employee_id'] ?? '')), 0, 40);
    $job = mb_substr(trim((string) ($fields['job_title'] ?? 'Sales agent')), 0, 80) ?: 'Sales agent';
    $password = (string) ($fields['password'] ?? '');
    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a name and a working email.'];
    }
    $dup = db_one('SELECT id FROM users WHERE email = ? AND id <> ?', 'si', [$email, (int) ($id ?? 0)]);
    if ($dup) {
        return ['ok' => false, 'error' => 'That email already has a login.'];
    }
    if ($employeeId !== '') {
        $eidDup = db_one(
            "SELECT id FROM users WHERE employee_id = ? AND role = 'sales_agent' AND id <> ?",
            'si',
            [$employeeId, (int) ($id ?? 0)]
        );
        if ($eidDup) {
            return ['ok' => false, 'error' => 'That Employee ID is already in use.'];
        }
    }
    if ($id) {
        $row = sales_agent($id);
        if (!$row) {
            return ['ok' => false, 'error' => 'Sales agent not found.'];
        }
        db_exec(
            "UPDATE users SET name=?, email=?, job_title=?, phone=?, employee_id=? WHERE id=? AND role='sales_agent'",
            'sssssi',
            [$name, $email, $job, $phone, $employeeId, $id]
        );
        if ($password !== '') {
            db_exec('UPDATE users SET password_hash=? WHERE id=?', 'si', [password_hash($password, PASSWORD_DEFAULT), $id]);
        }
        if ($employeeId === '') {
            $auto = 'SA-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
            db_exec("UPDATE users SET employee_id=? WHERE id=? AND role='sales_agent' AND employee_id=''", 'si', [$auto, $id]);
        }
        return ['ok' => true, 'id' => $id];
    }
    if ($password === '') {
        $password = function_exists('generate_desk_password') ? generate_desk_password() : ('Vs-' . bin2hex(random_bytes(4)));
    }
    $newId = db_exec(
        "INSERT INTO users (name, job_title, email, password_hash, role, access, company_id, status, phone, employee_id) VALUES (?,?,?,?, 'sales_agent', 'sales', NULL, 'live', ?, ?)",
        'ssssss',
        [$name, $job, $email, password_hash($password, PASSWORD_DEFAULT), $phone, $employeeId]
    );
    $newId = (int) $newId;
    if ($newId > 0 && $employeeId === '') {
        $auto = 'SA-' . str_pad((string) $newId, 4, '0', STR_PAD_LEFT);
        db_exec("UPDATE users SET employee_id=? WHERE id=? AND role='sales_agent'", 'si', [$auto, $newId]);
    }
    return ['ok' => true, 'id' => $newId, 'password' => $password];
}

function sales_agent_set_status(int $id, string $status): array
{
    $status = $status === 'suspended' ? 'suspended' : 'live';
    $row = sales_agent($id);
    if (!$row) {
        return ['ok' => false, 'error' => 'Sales agent not found.'];
    }
    db_exec("UPDATE users SET status=? WHERE id=? AND role='sales_agent'", 'si', [$status, $id]);
    return ['ok' => true];
}

function sales_agent_delete(int $id, string $confirmEmail = ''): array
{
    $row = sales_agent($id);
    if (!$row) {
        return ['ok' => false, 'error' => 'Sales agent not found.'];
    }
    $confirmEmail = strtolower(trim($confirmEmail));
    $agentEmail = strtolower(trim((string) ($row['email'] ?? '')));
    if ($confirmEmail === '' || $confirmEmail !== $agentEmail) {
        return ['ok' => false, 'error' => 'Type the agent’s email exactly to confirm delete.'];
    }
    db_exec('DELETE FROM sales_messages WHERE from_user_id = ? OR to_user_id = ?', 'ii', [$id, $id]);
    db_exec('DELETE FROM sales_clock_ins WHERE user_id = ?', 'i', [$id]);
    db_exec('DELETE FROM sales_targets WHERE agent_id = ?', 'i', [$id]);
    db_exec("DELETE FROM users WHERE id = ? AND role = 'sales_agent'", 'i', [$id]);
    return ['ok' => true, 'deleted' => true, 'message' => 'Sales agent deleted.'];
}

function sales_today_clock(?int $userId = null): ?array
{
    $uid = $userId ?? (int) (current_user()['id'] ?? 0);
    return db_one('SELECT * FROM sales_clock_ins WHERE user_id = ? AND day_date = ?', 'is', [$uid, today()]);
}

function sales_is_clocked_in(?int $userId = null): bool
{
    $row = sales_today_clock($userId);
    if (!$row) {
        return false;
    }
    return trim((string) ($row['clocked_out_at'] ?? '')) === '';
}

/** Minutes on the clock for a clock-in row (includes open session so far). */
function sales_clock_minutes(array $row, ?int $now = null): int
{
    $accrued = (int) ($row['minutes_accrued'] ?? 0);
    $out = trim((string) ($row['clocked_out_at'] ?? ''));
    if ($out !== '') {
        return max(0, $accrued);
    }
    // Prefer SQL-computed open minutes when present (avoids PHP/MySQL TZ skew).
    if (isset($row['open_mins'])) {
        return max(0, $accrued + (int) $row['open_mins']);
    }
    $id = (int) ($row['id'] ?? 0);
    if ($id > 0) {
        try {
            $live = db_one(
                'SELECT minutes_accrued,
                        CASE WHEN clocked_out_at IS NULL
                             THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, clocked_at, NOW()))
                             ELSE 0 END AS open_mins
                 FROM sales_clock_ins WHERE id = ?',
                'i',
                [$id]
            );
            if ($live) {
                return max(0, (int) ($live['minutes_accrued'] ?? 0) + (int) ($live['open_mins'] ?? 0));
            }
        } catch (Throwable $e) {
            // fall through
        }
    }
    $start = strtotime((string) ($row['clocked_at'] ?? ''));
    if (!$start) {
        return max(0, $accrued);
    }
    // Avoid PHP/MySQL timezone skew: only count whole minutes when clocks agree within 2 minutes.
    $now = $now ?? time();
    $mins = (int) floor(($now - $start) / 60);
    if ($mins > 120) {
        // Likely TZ skew; prefer accrued only until next SQL refresh.
        return max(0, $accrued);
    }
    return max(0, $accrued + max(0, $mins));
}

function sales_format_hours(float $hours): string
{
    if ($hours <= 0) {
        return '0h';
    }
    if ($hours < 10) {
        return rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.') . 'h';
    }
    return number_format($hours, 1, '.', '') . 'h';
}

/** Clock-in / clock-out wall time for daily boards (H:i in active desk zone). */
function sales_format_clock_time(?string $dt): string
{
    if ($dt === null || trim($dt) === '') {
        return '-';
    }
    $raw = trim($dt);
    try {
        $tzName = function_exists('desk_timezone_id') ? desk_timezone_id() : 'Africa/Kampala';
        $tz = new DateTimeZone($tzName);
        if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?/', $raw)) {
            $dtObj = new DateTimeImmutable(str_replace('T', ' ', substr($raw, 0, 19)), $tz);
        } else {
            $dtObj = (new DateTimeImmutable($raw))->setTimezone($tz);
        }
        return $dtObj->format('H:i');
    } catch (Throwable $e) {
        $t = strtotime($raw);
        return $t === false ? '-' : date('H:i', $t);
    }
}

/** Admin-only: date + submit time for a lead form (e.g. 28 Sep 2026 · 10:42). */
function sales_format_lead_submitted_at(?string $dt): string
{
    $raw = trim((string) $dt);
    if ($raw === '') {
        return '-';
    }
    $day = function_exists('format_date') ? format_date(substr($raw, 0, 10)) : substr($raw, 0, 10);
    $time = sales_format_clock_time($raw);
    if ($time === '-' || $time === '') {
        return $day !== '' ? $day : '-';
    }
    return ($day !== '' ? $day . ' · ' : '') . $time;
}

/**
 * Average minutes spent per client (gap from clock-in→first lead, then lead→lead).
 * Used for the “avg time per client” chart on agent + admin dashboards.
 *
 * @return list<array{date:string,minutes:float,samples:int}>
 */
function sales_client_time_series(?int $agentId, string $from, string $to): array
{
    $where = 'DATE(created_at) >= ? AND DATE(created_at) <= ? AND deleted_at IS NULL';
    $types = 'ss';
    $params = [$from, $to];
    if ($agentId) {
        $where .= ' AND agent_id = ?';
        $types .= 'i';
        $params[] = $agentId;
    }
    $leads = [];
    try {
        $leads = db_all(
            "SELECT id, agent_id, created_at, DATE(created_at) AS day_date
             FROM sales_leads
             WHERE {$where}
             ORDER BY agent_id ASC, created_at ASC, id ASC",
            $types,
            $params
        );
    } catch (Throwable $e) {
        $leads = [];
    }

    $clockByAgentDay = [];
    try {
        $cWhere = 'day_date >= ? AND day_date <= ?';
        $cTypes = 'ss';
        $cParams = [$from, $to];
        if ($agentId) {
            $cWhere .= ' AND user_id = ?';
            $cTypes .= 'i';
            $cParams[] = $agentId;
        }
        $clocks = db_all(
            "SELECT user_id, day_date, clocked_at FROM sales_clock_ins WHERE {$cWhere}",
            $cTypes,
            $cParams
        );
        foreach ($clocks as $c) {
            $key = (int) ($c['user_id'] ?? 0) . '|' . (string) ($c['day_date'] ?? '');
            $clockByAgentDay[$key] = (string) ($c['clocked_at'] ?? '');
        }
    } catch (Throwable $e) {
        // Clock table may be missing on old installs.
    }

    // Group intervals by calendar day across agents.
    $dayIntervals = [];
    $prevByAgentDay = [];
    foreach ($leads as $lead) {
        $aid = (int) ($lead['agent_id'] ?? 0);
        $day = (string) ($lead['day_date'] ?? substr((string) ($lead['created_at'] ?? ''), 0, 10));
        $created = trim((string) ($lead['created_at'] ?? ''));
        if ($aid < 1 || $day === '' || $created === '') {
            continue;
        }
        $key = $aid . '|' . $day;
        $createdTs = strtotime($created);
        if ($createdTs === false) {
            continue;
        }
        if (!isset($prevByAgentDay[$key])) {
            $clockAt = $clockByAgentDay[$key] ?? '';
            $prevTs = $clockAt !== '' ? strtotime($clockAt) : false;
            // First lead of the day: prefer gap from clock-in; otherwise start the chain here.
            if ($prevTs !== false && $prevTs <= $createdTs) {
                $mins = (int) round(($createdTs - $prevTs) / 60);
                if ($mins >= 1 && $mins <= 180) {
                    $dayIntervals[$day][] = $mins;
                }
            }
            $prevByAgentDay[$key] = $createdTs;
            continue;
        }
        $prevTs = (int) $prevByAgentDay[$key];
        $mins = (int) round(($createdTs - $prevTs) / 60);
        if ($mins >= 1 && $mins <= 180) {
            $dayIntervals[$day][] = $mins;
        }
        $prevByAgentDay[$key] = $createdTs;
    }

    $out = [];
    $start = strtotime($from);
    $end = strtotime($to);
    if ($start && $end && ($end - $start) / 86400 <= 93) {
        for ($t = $start; $t <= $end; $t += 86400) {
            $d = date('Y-m-d', $t);
            $samples = $dayIntervals[$d] ?? [];
            $avg = $samples ? round(array_sum($samples) / count($samples), 1) : 0.0;
            $out[] = [
                'date' => $d,
                'minutes' => $avg,
                'samples' => count($samples),
            ];
        }
        return $out;
    }
    ksort($dayIntervals);
    foreach ($dayIntervals as $d => $samples) {
        $out[] = [
            'date' => $d,
            'minutes' => $samples ? round(array_sum($samples) / count($samples), 1) : 0.0,
            'samples' => count($samples),
        ];
    }
    return $out;
}

function sales_client_time_avg(?int $agentId, string $from, string $to): float
{
    $total = 0.0;
    $n = 0;
    foreach (sales_client_time_series($agentId, $from, $to) as $row) {
        $samples = (int) ($row['samples'] ?? 0);
        if ($samples < 1) {
            continue;
        }
        $total += (float) ($row['minutes'] ?? 0) * $samples;
        $n += $samples;
    }
    return $n > 0 ? round($total / $n, 1) : 0.0;
}

/**
 * Daily avg minutes/client broken out per sales agent for multi-line charts.
 * Same shape as sales_hours_series_by_agents: dates, labels, agents[].
 *
 * @return array{dates:list<string>,labels:list<string>,agents:list<array{id:int,name:string,color:string,minutes:list<float>,samples:list<int>,avg:float}>}
 */
function sales_client_time_series_by_agents(?int $agentId, string $from, string $to): array
{
    $dates = [];
    foreach (sales_client_time_series($agentId, $from, $to) as $row) {
        $dates[] = (string) ($row['date'] ?? '');
    }
    $dates = array_values(array_filter($dates, static fn ($d) => $d !== ''));

    $agents = [];
    if ($agentId) {
        $one = sales_agent($agentId);
        if ($one) {
            $agents = [$one];
        }
    } else {
        $agents = sales_agents(false);
    }

    $where = 'DATE(created_at) >= ? AND DATE(created_at) <= ? AND deleted_at IS NULL';
    $types = 'ss';
    $params = [$from, $to];
    if ($agentId) {
        $where .= ' AND agent_id = ?';
        $types .= 'i';
        $params[] = $agentId;
    }
    $leads = [];
    try {
        $leads = db_all(
            "SELECT id, agent_id, created_at, DATE(created_at) AS day_date
             FROM sales_leads
             WHERE {$where}
             ORDER BY agent_id ASC, created_at ASC, id ASC",
            $types,
            $params
        );
    } catch (Throwable $e) {
        $leads = [];
    }

    $clockByAgentDay = [];
    try {
        $cWhere = 'day_date >= ? AND day_date <= ?';
        $cTypes = 'ss';
        $cParams = [$from, $to];
        if ($agentId) {
            $cWhere .= ' AND user_id = ?';
            $cTypes .= 'i';
            $cParams[] = $agentId;
        }
        $clocks = db_all(
            "SELECT user_id, day_date, clocked_at FROM sales_clock_ins WHERE {$cWhere}",
            $cTypes,
            $cParams
        );
        foreach ($clocks as $c) {
            $key = (int) ($c['user_id'] ?? 0) . '|' . (string) ($c['day_date'] ?? '');
            $clockByAgentDay[$key] = (string) ($c['clocked_at'] ?? '');
        }
    } catch (Throwable $e) {
        // Clock table may be missing on old installs.
    }

    // Per agent, per day: list of interval minutes.
    $byAgentDay = [];
    $prevByAgentDay = [];
    foreach ($leads as $lead) {
        $aid = (int) ($lead['agent_id'] ?? 0);
        $day = (string) ($lead['day_date'] ?? substr((string) ($lead['created_at'] ?? ''), 0, 10));
        $created = trim((string) ($lead['created_at'] ?? ''));
        if ($aid < 1 || $day === '' || $created === '') {
            continue;
        }
        $key = $aid . '|' . $day;
        $createdTs = strtotime($created);
        if ($createdTs === false) {
            continue;
        }
        if (!isset($prevByAgentDay[$key])) {
            $clockAt = $clockByAgentDay[$key] ?? '';
            $prevTs = $clockAt !== '' ? strtotime($clockAt) : false;
            if ($prevTs !== false && $prevTs <= $createdTs) {
                $mins = (int) round(($createdTs - $prevTs) / 60);
                if ($mins >= 1 && $mins <= 180) {
                    $byAgentDay[$aid][$day][] = $mins;
                }
            }
            $prevByAgentDay[$key] = $createdTs;
            continue;
        }
        $prevTs = (int) $prevByAgentDay[$key];
        $mins = (int) round(($createdTs - $prevTs) / 60);
        if ($mins >= 1 && $mins <= 180) {
            $byAgentDay[$aid][$day][] = $mins;
        }
        $prevByAgentDay[$key] = $createdTs;
    }

    $knownIds = [];
    foreach ($agents as $a) {
        $knownIds[(int) $a['id']] = true;
    }
    foreach (array_keys($byAgentDay) as $uid) {
        if (!isset($knownIds[$uid])) {
            $extra = sales_agent((int) $uid);
            if ($extra) {
                $agents[] = $extra;
                $knownIds[(int) $uid] = true;
            }
        }
    }

    if ($dates === []) {
        $start = strtotime($from);
        $end = strtotime($to);
        if ($start && $end && ($end - $start) / 86400 <= 93) {
            for ($t = $start; $t <= $end; $t += 86400) {
                $dates[] = date('Y-m-d', $t);
            }
        }
    }

    $labels = array_map(static fn ($d) => date('j M', strtotime($d)), $dates);
    $series = [];
    $i = 0;
    foreach ($agents as $a) {
        $uid = (int) ($a['id'] ?? 0);
        if ($uid < 1) {
            continue;
        }
        $minutes = [];
        $samples = [];
        $weightSum = 0.0;
        $sampleTotal = 0;
        foreach ($dates as $d) {
            $intervals = $byAgentDay[$uid][$d] ?? [];
            $n = count($intervals);
            $avg = $n > 0 ? round(array_sum($intervals) / $n, 1) : 0.0;
            $minutes[] = $avg;
            $samples[] = $n;
            if ($n > 0) {
                $weightSum += $avg * $n;
                $sampleTotal += $n;
            }
        }
        if (!$agentId && $sampleTotal <= 0 && count($agents) > 8) {
            $i++;
            continue;
        }
        $series[] = [
            'id' => $uid,
            'name' => trim((string) ($a['name'] ?? '')) ?: ('Agent #' . $uid),
            'color' => sales_agent_line_color($uid, $i),
            'minutes' => $minutes,
            'samples' => $samples,
            'avg' => $sampleTotal > 0 ? round($weightSum / $sampleTotal, 1) : 0.0,
            'sample_total' => $sampleTotal,
        ];
        $i++;
    }

    if (!$agentId && count($series) > 12) {
        $worked = array_values(array_filter($series, static fn ($s) => ((int) ($s['sample_total'] ?? 0)) > 0));
        if ($worked) {
            $series = $worked;
        }
    }

    usort($series, static function ($a, $b) {
        $cmp = (($b['avg'] ?? 0) <=> ($a['avg'] ?? 0));
        return $cmp !== 0 ? $cmp : strcasecmp((string) $a['name'], (string) $b['name']);
    });

    foreach ($series as $idx => &$agentSeries) {
        $agentSeries['color'] = sales_agent_line_color((int) ($agentSeries['id'] ?? 0), $idx);
    }
    unset($agentSeries);

    return [
        'dates' => $dates,
        'labels' => $labels,
        'agents' => $series,
    ];
}

function sales_clock_in(int $userId, string $city, string $notes = ''): array
{
    $city = mb_substr(trim($city), 0, 120);
    if ($city === '') {
        return ['ok' => false, 'error' => 'Enter the city or area where you are.'];
    }
    $note = mb_substr(trim($notes), 0, 500);
    $existing = sales_today_clock($userId);
    if ($existing && trim((string) ($existing['clocked_out_at'] ?? '')) === '') {
        return ['ok' => true, 'already' => true];
    }
    if ($existing) {
        // Re-open after clock-out: keep accrued minutes, start a new open session.
        db_exec(
            'UPDATE sales_clock_ins SET clocked_at = NOW(), clocked_out_at = NULL, location_city = ?, notes = ? WHERE id = ?',
            'ssi',
            [$city, $note !== '' ? $note : (string) ($existing['notes'] ?? ''), (int) $existing['id']]
        );
        return ['ok' => true, 'id' => (int) $existing['id'], 'resumed' => true];
    }
    $id = db_exec(
        'INSERT INTO sales_clock_ins (user_id, day_date, clocked_at, location_city, notes) VALUES (?,?,NOW(),?,?)',
        'isss',
        [$userId, today(), $city, $note]
    );
    return ['ok' => true, 'id' => (int) $id];
}

function sales_clock_out(int $userId): array
{
    $row = sales_today_clock($userId);
    if (!$row) {
        return ['ok' => false, 'error' => 'You are not clocked in.'];
    }
    if (trim((string) ($row['clocked_out_at'] ?? '')) !== '') {
        return ['ok' => true, 'already' => true, 'minutes' => sales_clock_minutes($row)];
    }
    db_exec(
        'UPDATE sales_clock_ins
         SET minutes_accrued = minutes_accrued + GREATEST(0, TIMESTAMPDIFF(MINUTE, clocked_at, NOW())),
             clocked_out_at = NOW()
         WHERE id = ? AND clocked_out_at IS NULL',
        'i',
        [(int) $row['id']]
    );
    $fresh = sales_today_clock($userId) ?: $row;
    return ['ok' => true, 'minutes' => sales_clock_minutes($fresh)];
}

function sales_require_clock_in(): void
{
    if (!sales_is_clocked_in()) {
        flash('Clock in first to add a lead. Enter where you are today.', 'err');
        redirect(sales_home());
    }
}

/** Daily field hours for one agent (or all agents when $agentId is null). */
function sales_hours_series(?int $agentId, string $from, string $to): array
{
    $where = 'day_date >= ? AND day_date <= ?';
    $types = 'ss';
    $params = [$from, $to];
    if ($agentId) {
        $where .= ' AND user_id = ?';
        $types .= 'i';
        $params[] = $agentId;
    }
    $rows = [];
    try {
        $rows = db_all(
            "SELECT day_date, clocked_at, clocked_out_at, minutes_accrued,
                    CASE WHEN clocked_out_at IS NULL
                         THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, clocked_at, NOW()))
                         ELSE 0 END AS open_mins
             FROM sales_clock_ins WHERE {$where} ORDER BY day_date",
            $types,
            $params
        );
    } catch (Throwable $e) {
        $rows = [];
    }
    $byDay = [];
    foreach ($rows as $r) {
        $d = (string) ($r['day_date'] ?? '');
        if ($d === '') {
            continue;
        }
        $byDay[$d] = ($byDay[$d] ?? 0) + sales_clock_minutes($r);
    }
    $out = [];
    $start = strtotime($from);
    $end = strtotime($to);
    if ($start && $end && ($end - $start) / 86400 <= 93) {
        for ($t = $start; $t <= $end; $t += 86400) {
            $d = date('Y-m-d', $t);
            $mins = (int) ($byDay[$d] ?? 0);
            $out[] = [
                'date' => $d,
                'minutes' => $mins,
                'hours' => round($mins / 60, 2),
            ];
        }
        return $out;
    }
    ksort($byDay);
    foreach ($byDay as $d => $mins) {
        $out[] = [
            'date' => $d,
            'minutes' => (int) $mins,
            'hours' => round(((int) $mins) / 60, 2),
        ];
    }
    return $out;
}

function sales_hours_total(?int $agentId, string $from, string $to): float
{
    $sum = 0;
    foreach (sales_hours_series($agentId, $from, $to) as $row) {
        $sum += (int) ($row['minutes'] ?? 0);
    }
    return round($sum / 60, 2);
}

/** Distinct line colors for per-agent field-hours charts (stable by agent id). */
function sales_agent_line_colors(): array
{
    // High-contrast set: blue, green, purple, yellow, red, black - then clear extras.
    return [
        '#1E4EFF', // blue
        '#16a34a', // green
        '#7c3aed', // purple
        '#eab308', // yellow
        '#dc2626', // red
        '#0a0a0a', // black
        '#0891b2', // cyan
        '#ea580c', // orange
        '#db2777', // pink
        '#65a30d', // lime
        '#4f46e5', // indigo
        '#854d0e', // brown
    ];
}

function sales_agent_line_color(int $agentId, int $index = 0): string
{
    $palette = sales_agent_line_colors();
    $n = count($palette);
    if ($n < 1) {
        return '#1E4EFF';
    }
    // Prefer legend/series index so neighbouring agents never share similar hues.
    return $palette[$index % $n];
}

/**
 * Daily field hours broken out per sales agent for multi-line charts.
 * Returns ['dates' => [...], 'labels' => [...], 'agents' => [[id,name,color,hours[],minutes[],total_hours], ...]].
 */
function sales_hours_series_by_agents(?int $agentId, string $from, string $to): array
{
    $dates = [];
    foreach (sales_hours_series($agentId, $from, $to) as $row) {
        $dates[] = (string) ($row['date'] ?? '');
    }
    $dates = array_values(array_filter($dates, static fn ($d) => $d !== ''));

    $agents = [];
    if ($agentId) {
        $one = sales_agent($agentId);
        if ($one) {
            $agents = [$one];
        }
    } else {
        $agents = sales_agents(false);
    }

    $where = 'c.day_date >= ? AND c.day_date <= ?';
    $types = 'ss';
    $params = [$from, $to];
    if ($agentId) {
        $where .= ' AND c.user_id = ?';
        $types .= 'i';
        $params[] = $agentId;
    }

    $rows = [];
    try {
        $rows = db_all(
            "SELECT c.user_id, c.day_date, c.clocked_at, c.clocked_out_at, c.minutes_accrued,
                    CASE WHEN c.clocked_out_at IS NULL
                         THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, c.clocked_at, NOW()))
                         ELSE 0 END AS open_mins
             FROM sales_clock_ins c
             WHERE {$where}
             ORDER BY c.day_date, c.user_id",
            $types,
            $params
        );
    } catch (Throwable $e) {
        $rows = [];
    }

    $byAgentDay = [];
    foreach ($rows as $r) {
        $uid = (int) ($r['user_id'] ?? 0);
        $d = (string) ($r['day_date'] ?? '');
        if ($uid < 1 || $d === '') {
            continue;
        }
        $byAgentDay[$uid][$d] = ($byAgentDay[$uid][$d] ?? 0) + sales_clock_minutes($r);
    }

    // Include agents who clocked in the range even if inactive / missing from list.
    $knownIds = [];
    foreach ($agents as $a) {
        $knownIds[(int) $a['id']] = true;
    }
    foreach (array_keys($byAgentDay) as $uid) {
        if (!isset($knownIds[$uid])) {
            $extra = sales_agent((int) $uid);
            if ($extra) {
                $agents[] = $extra;
                $knownIds[(int) $uid] = true;
            }
        }
    }

    $labels = array_map(static fn ($d) => date('j M', strtotime($d)), $dates);
    $series = [];
    $i = 0;
    foreach ($agents as $a) {
        $uid = (int) ($a['id'] ?? 0);
        if ($uid < 1) {
            continue;
        }
        $hours = [];
        $minutes = [];
        $totalMins = 0;
        foreach ($dates as $d) {
            $mins = (int) ($byAgentDay[$uid][$d] ?? 0);
            $minutes[] = $mins;
            $hours[] = round($mins / 60, 2);
            $totalMins += $mins;
        }
        // Skip agents with zero hours when showing the full team (keeps the chart readable).
        if (!$agentId && $totalMins <= 0 && count($agents) > 8) {
            $i++;
            continue;
        }
        $series[] = [
            'id' => $uid,
            'name' => trim((string) ($a['name'] ?? '')) ?: ('Agent #' . $uid),
            'color' => sales_agent_line_color($uid, $i),
            'hours' => $hours,
            'minutes' => $minutes,
            'total_hours' => round($totalMins / 60, 2),
        ];
        $i++;
    }

    // Prefer agents who actually worked; still keep zeros when few agents.
    if (!$agentId && count($series) > 12) {
        $worked = array_values(array_filter($series, static fn ($s) => ($s['total_hours'] ?? 0) > 0));
        if ($worked) {
            $series = $worked;
        }
    }

    usort($series, static function ($a, $b) {
        $cmp = ($b['total_hours'] <=> $a['total_hours']);
        return $cmp !== 0 ? $cmp : strcasecmp((string) $a['name'], (string) $b['name']);
    });

    foreach ($series as $idx => &$agentSeries) {
        $agentSeries['color'] = sales_agent_line_color((int) ($agentSeries['id'] ?? 0), $idx);
    }
    unset($agentSeries);

    return [
        'dates' => $dates,
        'labels' => $labels,
        'agents' => $series,
    ];
}

function sales_lead(int $id): ?array
{
    return db_one('SELECT l.*, u.name AS agent_name, u.email AS agent_email
        FROM sales_leads l LEFT JOIN users u ON u.id = l.agent_id
        WHERE l.id = ?', 'i', [$id]);
}

function sales_lead_event(int $leadId, int $agentId, string $type, ?string $from, ?string $to, string $note = ''): void
{
    db_exec(
        'INSERT INTO sales_lead_events (lead_id, agent_id, event_type, from_status, to_status, note) VALUES (?,?,?,?,?,?)',
        'iissss',
        [$leadId, $agentId, $type, $from, $to, mb_substr($note, 0, 500)]
    );
}

function sales_lead_save(array $fields, ?int $id = null, ?int $agentId = null): array
{
    $agentId = $agentId ?? (int) (current_user()['id'] ?? 0);
    $status = (string) ($fields['status'] ?? '');
    if (!isset(sales_statuses()[$status]) || $status === 'onboarded') {
        if ($status !== 'onboarded') {
            return ['ok' => false, 'error' => 'Choose Interested, Follow up or Rejected.'];
        }
    }
    $business = mb_substr(trim((string) ($fields['business_name'] ?? '')), 0, 190);
    $address = mb_substr(trim((string) ($fields['address'] ?? '')), 0, 190);
    $contactName = mb_substr(trim((string) ($fields['contact_name'] ?? '')), 0, 120);
    $contactPhone = mb_substr(trim((string) ($fields['contact_phone'] ?? '')), 0, 40);
    $city = mb_substr(trim((string) ($fields['city'] ?? '')), 0, 120);
    $notesProvided = array_key_exists('notes', $fields);
    $notes = $notesProvided ? mb_substr(trim((string) ($fields['notes'] ?? '')), 0, 2000) : '';
    $nature = '';
    $package = '';
    $onboardDate = null;
    $followDate = null;
    $followTime = null;
    $rejected = '';
    $rejectedCat = '';
    $interest = 0;

    if ($status === 'interested') {
        $nature = mb_substr(trim((string) ($fields['nature_of_business'] ?? '')), 0, 190);
        $package = mb_substr(trim((string) ($fields['package_chosen'] ?? '')), 0, 40);
        $od = trim((string) ($fields['onboard_date'] ?? ''));
        $onboardDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $od) ? $od : null;
    } elseif ($status === 'follow_up') {
        $fd = trim((string) ($fields['follow_up_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fd)) {
            return ['ok' => false, 'error' => 'Set a follow-up date.'];
        }
        $followDate = $fd;
        $followTime = sales_normalize_follow_up_time($fields['follow_up_time'] ?? null);
        $interest = max(0, min(5, (int) ($fields['interest_rating'] ?? 0)));
        if ($interest < 1) {
            return ['ok' => false, 'error' => 'Rate their interest from 1 to 5.'];
        }
    } elseif ($status === 'rejected') {
        $rejectedCat = trim((string) ($fields['rejected_category'] ?? ''));
        if (!isset(sales_reject_reasons()[$rejectedCat])) {
            return ['ok' => false, 'error' => 'Pick why they rejected Vellisys from the list.'];
        }
        $rejected = mb_substr(trim((string) ($fields['rejected_reason'] ?? '')), 0, 500);
        if ($rejected === '') {
            return ['ok' => false, 'error' => 'Explain the rejection.'];
        }
        $nature = mb_substr(trim((string) ($fields['nature_of_business'] ?? '')), 0, 190);
        if ($nature === '') {
            return ['ok' => false, 'error' => 'Add the nature of business.'];
        }
    }

    if ($id) {
        $row = sales_lead($id);
        if (!$row || (int) $row['agent_id'] !== $agentId && !is_platform()) {
            return ['ok' => false, 'error' => 'Lead not found.'];
        }
        if (!empty($row['deleted_at'])) {
            return ['ok' => false, 'error' => 'That lead was removed.'];
        }
        if (!$notesProvided) {
            $notes = (string) ($row['notes'] ?? '');
        }
        $from = (string) $row['status'];
        $followDone = $row['follow_up_done_at'] ?? null;
        if ($from === 'follow_up' && $status !== 'follow_up') {
            $followDone = date('Y-m-d H:i:s');
        }
        $prevFollowTime = sales_normalize_follow_up_time($row['follow_up_time'] ?? null);
        if ($status === 'follow_up' && (
            $followDate !== ($row['follow_up_date'] ?? null)
            || $followTime !== $prevFollowTime
        )) {
            $followDone = null;
        }
        db_exec(
            'UPDATE sales_leads SET status=?, business_name=?, address=?, contact_name=?, contact_phone=?, city=?,
             nature_of_business=?, package_chosen=?, onboard_date=?, follow_up_date=?, follow_up_time=?, follow_up_done_at=?, interest_rating=?,
             rejected_reason=?, rejected_category=?, notes=?, updated_at=NOW()
             WHERE id=?',
            'ssssssssssssisssi',
            [$status, $business, $address, $contactName, $contactPhone, $city, $nature, $package, $onboardDate, $followDate, $followTime, $followDone, $interest, $rejected, $rejectedCat, $notes, $id]
        );
        $eventNote = $status === 'rejected' ? $rejected : $notes;
        if ($from !== $status) {
            sales_lead_event($id, $agentId, 'status_change', $from, $status, $eventNote);
        } else {
            sales_lead_event($id, $agentId, 'update', $from, $status, $eventNote);
        }
        return ['ok' => true, 'id' => $id];
    }

    // Ignore a second submit of the same lead within ~2 minutes (double-tap / slow network).
    try {
        $dup = db_one(
            "SELECT id FROM sales_leads
             WHERE agent_id = ? AND status = ? AND business_name = ? AND city = ?
               AND nature_of_business = ? AND rejected_category = ? AND rejected_reason = ?
               AND COALESCE(contact_phone,'') = ? AND COALESCE(follow_up_date,'') = COALESCE(?,'')
               AND deleted_at IS NULL
               AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
             ORDER BY id DESC LIMIT 1",
            'issssssss',
            [$agentId, $status, $business, $city, $nature, $rejectedCat, $rejected, $contactPhone, $followDate]
        );
        if ($dup && (int) ($dup['id'] ?? 0) > 0) {
            return ['ok' => true, 'id' => (int) $dup['id'], 'duplicate' => true];
        }
    } catch (Throwable $e) {
        // Continue to insert if the guard query fails.
    }

    $newId = db_exec(
        'INSERT INTO sales_leads (agent_id, status, business_name, address, contact_name, contact_phone, city,
         nature_of_business, package_chosen, onboard_date, follow_up_date, follow_up_time, interest_rating, rejected_reason, rejected_category, notes)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        'isssssssssssisss',
        [$agentId, $status, $business, $address, $contactName, $contactPhone, $city, $nature, $package, $onboardDate, $followDate, $followTime, $interest, $rejected, $rejectedCat, $notes]
    );
    sales_lead_event((int) $newId, $agentId, 'create', null, $status, $status === 'rejected' ? $rejected : $notes);
    return ['ok' => true, 'id' => (int) $newId];
}

function sales_lead_soft_delete(int $id): array
{
    $row = sales_lead($id);
    if (!$row) {
        return ['ok' => false, 'error' => 'Lead not found.'];
    }
    if ((string) $row['status'] !== 'rejected') {
        return ['ok' => false, 'error' => 'Only rejected leads can be removed from the list. Reports still keep them.'];
    }
    db_exec('UPDATE sales_leads SET deleted_at = NOW() WHERE id = ?', 'i', [$id]);
    sales_lead_event($id, (int) (current_user()['id'] ?? 0), 'soft_delete', 'rejected', 'rejected', 'Removed from active list');
    return ['ok' => true];
}

function sales_lead_mark_onboarded(int $id, ?int $signupId = null, ?int $companyId = null): array
{
    $row = sales_lead($id);
    if (!$row || !in_array((string) $row['status'], ['interested', 'onboarding'], true)) {
        return ['ok' => false, 'error' => 'Only interested or onboarding leads can be marked onboarded.'];
    }
    db_exec(
        "UPDATE sales_leads SET status='onboarded', signup_id=COALESCE(?, signup_id), company_id=COALESCE(?, company_id), follow_up_done_at=COALESCE(follow_up_done_at, NOW()), updated_at=NOW() WHERE id=?",
        'iii',
        [$signupId, $companyId, $id]
    );
    sales_lead_event($id, (int) (current_user()['id'] ?? 0), 'onboarded', (string) $row['status'], 'onboarded', 'Company live');
    return ['ok' => true];
}

/** Create a company in onboarding from a sales lead and link the lead. */
function sales_begin_company_onboard(int $leadId): array
{
    $lead = sales_lead($leadId);
    if (!$lead || (string) $lead['status'] !== 'interested') {
        return ['ok' => false, 'error' => 'Only interested leads can start onboarding.'];
    }
    if (!empty($lead['company_id'])) {
        $existing = db_one('SELECT id, status FROM companies WHERE id = ?', 'i', [(int) $lead['company_id']]);
        if ($existing) {
            db_exec("UPDATE sales_leads SET status='onboarding', updated_at=NOW() WHERE id=?", 'i', [$leadId]);
            return ['ok' => true, 'company_id' => (int) $existing['id'], 'existing' => true];
        }
    }

    $name = trim((string) ($lead['business_name'] ?? ''));
    if ($name === '') {
        $name = 'Sales lead #' . $leadId;
    }
    $contact = trim((string) ($lead['contact_name'] ?? '')) ?: 'Desk admin';
    $phone = trim((string) ($lead['contact_phone'] ?? ''));
    $city = trim((string) ($lead['city'] ?? ''));
    $address = trim((string) ($lead['address'] ?? ''));
    $plan = normalize_company_plan((string) ($lead['package_chosen'] ?? 'sme'));
    $limit = plan_user_limit_max($plan);
    $kinds = implode(',', default_enabled_kinds());
    $notes = trim('Started from sales lead #' . $leadId
        . (!empty($lead['agent_name']) ? ' · agent ' . $lead['agent_name'] : '')
        . (!empty($lead['notes']) ? "\n" . $lead['notes'] : ''));

    $cid = db_exec(
        'INSERT INTO companies (name, status, plan, notes, enabled_kinds, user_limit, nature_of_business) VALUES (?,?,?,?,?,?,?)',
        'sssssis',
        [$name, 'onboarding', $plan, $notes !== '' ? $notes : null, $kinds, $limit, sanitize_nature_of_business((string) ($lead['nature_of_business'] ?? ''))]
    );

    $emailBase = 'lead' . $leadId . '.' . substr(bin2hex(random_bytes(2)), 0, 4);
    $userEmail = strtolower($emailBase . '@onboard.vellisys.ug');
    while (db_one('SELECT id FROM users WHERE email = ?', 's', [$userEmail])) {
        $userEmail = strtolower($emailBase . '.' . substr(bin2hex(random_bytes(2)), 0, 3) . '@onboard.vellisys.ug');
    }
    $password = generate_desk_password();
    $color = '#1E4EFF';
    $accent = '#C6A15B';
    $deep = function_exists('hex_shade') ? hex_shade($color, 0.52) : '#08143A';
    $prefix = function_exists('prefix_from_name') ? prefix_from_name($name) : 'VEL';

    db_exec(
        'INSERT INTO branding (company_id, name, tagline, tin, vat_no, address, city, phone, email, website, bank_name, account_name, account_number, brand_color, brand_accent, brand_deep, logo_path, prefix, payment_note, invoice_comments, receipt_comments, plan, currency)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        'issssssssssssssssssssss',
        [
            // Login email stays on the user row; document email starts blank until they set it.
            $cid, $name, '', '', '', $address, $city, $phone, '', '', '', $name, '',
            $color, $accent, $deep, '', $prefix,
            'Make payment to ' . $name . '.',
            "1. Payment is due by the date shown above.\n2. Quote the invoice number on the transfer.",
            'Payments made are not refundable.',
            'sme', 'UGX',
        ]
    );

    $made = create_desk_user($cid, [
        'name' => $contact,
        'email' => $userEmail,
        'password' => $password,
        'job_title' => 'Administrator',
        'access' => 'admin',
    ]);
    if (empty($made['ok'])) {
        return ['ok' => false, 'error' => (string) ($made['error'] ?? 'Could not create the desk login.'), 'id' => $cid];
    }

    if (function_exists('company_mark_onboard_step')) {
        company_mark_onboard_step($cid, 'desk_login');
    }

    db_exec(
        "UPDATE sales_leads SET status='onboarding', company_id=?, follow_up_done_at=COALESCE(follow_up_done_at, NOW()), updated_at=NOW() WHERE id=?",
        'ii',
        [$cid, $leadId]
    );
    sales_lead_event($leadId, (int) (current_user()['id'] ?? 0), 'onboarding', 'interested', 'onboarding', 'Company #' . $cid . ' created');

    if (function_exists('sales_vault_save')) {
        sales_vault_save([
            'company_id' => $cid,
            'company_name' => $name,
            'email' => $userEmail,
            'password' => (string) ($made['password'] ?? $password),
            'notes' => 'From sales onboard · lead #' . $leadId . ' · change this email before go-live',
        ]);
    }

    return [
        'ok' => true,
        'company_id' => $cid,
        'email' => $userEmail,
        'password' => (string) ($made['password'] ?? $password),
        'name' => $name,
    ];
}

function sales_sync_lead_for_company_status(int $companyId, string $status): void
{
    if ($companyId < 1) {
        return;
    }
    $lead = db_one('SELECT id, status FROM sales_leads WHERE company_id = ? ORDER BY id DESC LIMIT 1', 'i', [$companyId]);
    if (!$lead) {
        return;
    }
    if ($status === 'live' && (string) $lead['status'] !== 'onboarded') {
        db_exec("UPDATE sales_leads SET status='onboarded', updated_at=NOW() WHERE id=?", 'i', [(int) $lead['id']]);
        sales_lead_event((int) $lead['id'], (int) (current_user()['id'] ?? 0), 'onboarded', (string) $lead['status'], 'onboarded', 'Company marked live');
    } elseif ($status === 'onboarding' && (string) $lead['status'] === 'interested') {
        db_exec("UPDATE sales_leads SET status='onboarding', updated_at=NOW() WHERE id=?", 'i', [(int) $lead['id']]);
    }
}

/** Default length of a sales-agent testing desk (strictly 2 weeks). */
function sales_testing_default_days(): int
{
    return 14;
}

function company_is_testing(?array $company): bool
{
    return $company && !empty($company['testing_mode']);
}

function company_testing_expires_at(?array $company): ?DateTimeImmutable
{
    if (!$company || empty($company['testing_expires_at'])) {
        return null;
    }
    try {
        return new DateTimeImmutable((string) $company['testing_expires_at']);
    } catch (Throwable $e) {
        return null;
    }
}

function company_testing_expired(?array $company): bool
{
    if (!company_is_testing($company)) {
        return false;
    }
    $exp = company_testing_expires_at($company);
    if (!$exp) {
        return true;
    }
    $now = function_exists('desk_now') ? desk_now() : new DateTimeImmutable('now');
    return $exp < $now;
}

function company_testing_remaining_label(?array $company): string
{
    if (!company_is_testing($company)) {
        return '';
    }
    $exp = company_testing_expires_at($company);
    if (!$exp) {
        return 'No end date';
    }
    $now = function_exists('desk_now') ? desk_now() : new DateTimeImmutable('now');
    if ($exp < $now) {
        return 'Expired';
    }
    $secs = $exp->getTimestamp() - $now->getTimestamp();
    $days = (int) floor($secs / 86400);
    if ($days >= 1) {
        return $days . ' day' . ($days === 1 ? '' : 's') . ' left';
    }
    $hours = max(1, (int) ceil($secs / 3600));
    return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' left';
}

/** Default password for every testing desk. */
function sales_testing_default_password(): string
{
    return 'Folio2026';
}

/**
 * Desk login from the first word of the business name: mark@vellisys.com
 * Optional $preferred overrides when super admin sets a custom email.
 */
function sales_testing_generate_login(string $seed = '', string $preferred = ''): string
{
    $preferred = strtolower(trim($preferred));
    if ($preferred !== '' && filter_var($preferred, FILTER_VALIDATE_EMAIL)) {
        if (!db_one('SELECT id FROM users WHERE email = ?', 's', [$preferred])) {
            return $preferred;
        }
        return ''; // caller treats empty as "taken"
    }
    $parts = preg_split('/\s+/u', trim($seed)) ?: [];
    $first = preg_replace('/[^a-zA-Z0-9]+/', '', (string) ($parts[0] ?? ''));
    if ($first === '') {
        $first = 'desk';
    }
    $local = strtolower(substr($first, 0, 40));
    $email = $local . '@vellisys.com';
    $n = 0;
    while (db_one('SELECT id FROM users WHERE email = ?', 's', [$email])) {
        $n++;
        $email = $local . $n . '@vellisys.com';
        if ($n > 99) {
            $email = $local . substr(bin2hex(random_bytes(2)), 0, 4) . '@vellisys.com';
            if (!db_one('SELECT id FROM users WHERE email = ?', 's', [$email])) {
                break;
            }
        }
    }
    return $email;
}

function sales_testing_generate_password(): string
{
    return sales_testing_default_password();
}

/**
 * One-time / deploy: rewrite legacy trial logins (test*@test…, *@desk…) to
 * FirstWord@vellisys.com and set every testing desk password to Folio2026.
 * Keeps an existing *@vellisys.com login if a super admin already customized it.
 */
function sales_testing_normalize_logins(): int
{
    $rows = db_all('SELECT id, name FROM companies WHERE testing_mode = 1 ORDER BY id ASC');
    $n = 0;
    foreach ($rows as $row) {
        $cid = (int) $row['id'];
        $admin = db_one(
            "SELECT id, email FROM users WHERE company_id = ? AND role = 'admin' ORDER BY id ASC LIMIT 1",
            'i',
            [$cid]
        );
        if (!$admin) {
            continue;
        }
        $current = strtolower(trim((string) ($admin['email'] ?? '')));
        $email = $current;
        $needsRewrite = $current === ''
            || !str_ends_with($current, '@vellisys.com')
            || str_starts_with($current, 'test')
            || str_contains($current, '@test.');
        if ($needsRewrite) {
            $email = sales_testing_generate_login((string) $row['name']);
            if ($email === '') {
                continue;
            }
        }
        $done = sales_testing_set_login($cid, $email, true);
        if (!empty($done['ok'])) {
            $n++;
        }
    }
    return $n;
}

/** Super admin can change the testing desk sign-in email (and keep Folio2026). */
function sales_testing_set_login(int $companyId, string $email, bool $resetPassword = false): array
{
    $company = db_one('SELECT * FROM companies WHERE id = ? AND testing_mode = 1', 'i', [$companyId]);
    if (!$company) {
        return ['ok' => false, 'error' => 'Testing company not found.'];
    }
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid desk login email.'];
    }
    $admin = db_one("SELECT id, email FROM users WHERE company_id = ? AND role = 'admin' ORDER BY id ASC LIMIT 1", 'i', [$companyId]);
    if (!$admin) {
        return ['ok' => false, 'error' => 'No desk login on this testing company.'];
    }
    $uid = (int) $admin['id'];
    $taken = db_one('SELECT id FROM users WHERE email = ? AND id <> ?', 'si', [$email, $uid]);
    if ($taken) {
        return ['ok' => false, 'error' => 'That email already has a Vellisys login.'];
    }
    $password = sales_testing_default_password();
    if ($resetPassword) {
        db_exec(
            'UPDATE users SET email = ?, password_hash = ? WHERE id = ? AND company_id = ?',
            'ssii',
            [$email, password_hash($password, PASSWORD_DEFAULT), $uid, $companyId]
        );
    } else {
        db_exec('UPDATE users SET email = ? WHERE id = ? AND company_id = ?', 'sii', [$email, $uid, $companyId]);
        $password = '';
        $vault = db_one('SELECT password_enc FROM sales_vault WHERE company_id = ? ORDER BY id DESC LIMIT 1', 'i', [$companyId]);
        if ($vault) {
            $password = sales_vault_decrypt((string) $vault['password_enc']);
        }
        if ($password === '') {
            $password = sales_testing_default_password();
        }
    }
    $vaultRow = db_one('SELECT id FROM sales_vault WHERE company_id = ? ORDER BY id DESC LIMIT 1', 'i', [$companyId]);
    if ($vaultRow) {
        sales_vault_save([
            'company_id' => $companyId,
            'company_name' => (string) $company['name'],
            'email' => $email,
            'password' => $password !== '' ? $password : sales_testing_default_password(),
            'notes' => 'Trial desk · hand credentials to the client',
        ], (int) $vaultRow['id']);
    } else {
        sales_vault_save([
            'company_id' => $companyId,
            'company_name' => (string) $company['name'],
            'email' => $email,
            'password' => $password !== '' ? $password : sales_testing_default_password(),
            'notes' => 'Trial desk · hand credentials to the client',
        ]);
    }
    return ['ok' => true, 'email' => $email, 'password' => $password !== '' ? $password : sales_testing_default_password()];
}

/**
 * Start a 2-week testing desk from an interested lead (same sales flow).
 * Uses the lead's business/contact fields and default document kinds.
 * Sales agents must supply the client's preferred login email; password is Folio2026.
 */
function sales_start_testing_from_lead(int $leadId, int $agentId, string $preferredEmail = ''): array
{
    $lead = sales_lead($leadId);
    if (!$lead || !empty($lead['deleted_at']) || (int) ($lead['agent_id'] ?? 0) !== $agentId) {
        return ['ok' => false, 'error' => 'Lead not found.'];
    }
    $status = (string) ($lead['status'] ?? '');
    if ($status !== 'interested') {
        return ['ok' => false, 'error' => 'Mark the lead as Interested before opening a test desk.'];
    }
    if (!empty($lead['company_id'])) {
        $existing = db_one(
            'SELECT id, testing_mode FROM companies WHERE id = ?',
            'i',
            [(int) $lead['company_id']]
        );
        if ($existing && !empty($existing['testing_mode'])) {
            return [
                'ok' => false,
                'error' => 'This lead already has a testing desk.',
                'company_id' => (int) $existing['id'],
            ];
        }
    }
    $name = trim((string) ($lead['business_name'] ?? ''));
    $contact = trim((string) ($lead['contact_name'] ?? ''));
    $phone = trim((string) ($lead['contact_phone'] ?? ''));
    if ($name === '' || $contact === '' || $phone === '') {
        return ['ok' => false, 'error' => 'Save business name, contact person and phone on this lead first.'];
    }
    $preferredEmail = strtolower(trim($preferredEmail));
    if ($preferredEmail === '' || !filter_var($preferredEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter the client\'s preferred login email.'];
    }
    if (db_one('SELECT id FROM users WHERE email = ?', 's', [$preferredEmail])) {
        return ['ok' => false, 'error' => 'That email already has a Vellisys login. Pick another.'];
    }
    return sales_create_testing_company([
        'name' => $name,
        'contact_name' => $contact,
        'phone' => $phone,
        'city' => (string) ($lead['city'] ?? ''),
        'address' => (string) ($lead['address'] ?? ''),
        'nature_of_business' => (string) ($lead['nature_of_business'] ?? ''),
        'lead_id' => $leadId,
        'user_email' => $preferredEmail,
        'enabled_kinds' => function_exists('default_enabled_kinds') ? default_enabled_kinds() : ['quotation', 'invoice', 'receipt', 'letter'],
        // Trials always run as Pro so clients can try stock, P&L and planner.
        'plan' => 'office',
    ], $agentId, false);
}

/**
 * Create a 2-week testing desk for a sales agent (or platform admin).
 * Expects POST fields: name, contact_name, phone, city, address, nature_of_business,
 * enabled_kinds[], client fields, line_columns[], optional lead_id / testing_owner_id / days.
 */
function sales_create_testing_company(array $fields, int $actorId, bool $asPlatform = false): array
{
    $name = trim((string) ($fields['name'] ?? $fields['business_name'] ?? ''));
    $contact = trim((string) ($fields['contact_name'] ?? $fields['user_name'] ?? ''));
    $phone = trim((string) ($fields['phone'] ?? $fields['contact_phone'] ?? ''));
    $city = trim((string) ($fields['city'] ?? ''));
    $address = trim((string) ($fields['address'] ?? ''));
    $nature = function_exists('sanitize_nature_of_business')
        ? sanitize_nature_of_business((string) ($fields['nature_of_business'] ?? ''))
        : trim((string) ($fields['nature_of_business'] ?? ''));
    $ownerId = $asPlatform
        ? ((int) ($fields['testing_owner_id'] ?? 0) ?: $actorId)
        : $actorId;
    $days = (int) ($fields['days'] ?? sales_testing_default_days());
    if ($days < 1) {
        $days = sales_testing_default_days();
    }
    if ($days > 90) {
        $days = 90;
    }
    $leadId = (int) ($fields['lead_id'] ?? 0);

    if ($name === '') {
        return ['ok' => false, 'error' => 'Enter the business name.'];
    }
    if ($contact === '') {
        return ['ok' => false, 'error' => 'Enter the contact person name.'];
    }
    if ($phone === '') {
        return ['ok' => false, 'error' => 'Enter a contact phone number.'];
    }

    $kindsPosted = $fields['enabled_kinds'] ?? ($_POST['enabled_kinds'] ?? []);
    if ((!is_array($kindsPosted) || $kindsPosted === []) && function_exists('default_enabled_kinds')) {
        $kindsPosted = default_enabled_kinds();
    }
    if (!is_array($kindsPosted) || $kindsPosted === []) {
        return ['ok' => false, 'error' => 'Select at least one document type for this test desk.'];
    }
    $preferredEmail = strtolower(trim((string) ($fields['user_email'] ?? $fields['desk_email'] ?? '')));
    if ($preferredEmail === '' || !filter_var($preferredEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter the client\'s preferred login email.'];
    }
    if (db_one('SELECT id FROM users WHERE email = ?', 's', [$preferredEmail])) {
        return ['ok' => false, 'error' => 'That email already has a Vellisys login. Pick another.'];
    }
    $userEmail = $preferredEmail;
    $password = sales_testing_default_password();
    // Prefer explicit field kinds (lead flow); fall back to the posted desk-kinds form.
    if (!empty($_POST['enabled_kinds']) && is_array($_POST['enabled_kinds']) && function_exists('posted_enabled_kinds')) {
        $kinds = posted_enabled_kinds();
        $customDoc = function_exists('posted_custom_doc') ? posted_custom_doc() : null;
        $lineCols = function_exists('posted_document_line_columns') ? posted_document_line_columns() : null;
    } else {
        $kindsList = function_exists('parse_enabled_kinds') ? parse_enabled_kinds($kindsPosted) : array_map('strval', $kindsPosted);
        $kinds = json_encode(array_values($kindsList), JSON_UNESCAPED_UNICODE) ?: '[]';
        $customDoc = null;
        $lineCols = null;
    }
    // Test desks always get Pro (office): stock, Profit & Loss, and Planner.
    $plan = 'office';
    $limit = function_exists('plan_user_limit_max') ? plan_user_limit_max($plan) : 4;
    $notes = trim('Testing mode · Pro trial · agent #' . $ownerId
        . ($leadId ? ' · lead #' . $leadId : '')
        . "\nStrict 2-week trial with stock, P&L and planner. Promote to onboard when the client is ready.");

    $expires = (function_exists('desk_now') ? desk_now() : new DateTimeImmutable('now'))
        ->modify('+' . $days . ' days')
        ->format('Y-m-d H:i:s');

    $cid = db_exec(
        'INSERT INTO companies (name, status, plan, notes, enabled_kinds, custom_doc, user_limit, nature_of_business, testing_mode, testing_owner_id, testing_expires_at) VALUES (?,?,?,?,?,?,?,?,1,?,?)',
        'ssssssisis',
        [$name, 'onboarding', $plan, $notes, $kinds, $customDoc, $limit, $nature, $ownerId, $expires]
    );
    if ($cid < 1) {
        return ['ok' => false, 'error' => 'Could not create the testing company.'];
    }

    // Flip Pro add-ons on (columns may be added by migrate ensures).
    db_exec('UPDATE companies SET plan = ?, user_limit = ? WHERE id = ?', 'sii', [$plan, $limit, $cid]);
    foreach (['stock_enabled', 'planner_enabled', 'pnl_enabled'] as $addonCol) {
        try {
            db_exec('UPDATE companies SET `' . $addonCol . '` = 1 WHERE id = ?', 'i', [$cid]);
        } catch (Throwable $e) {
            // ignore if column missing until migrate
        }
    }

    if (function_exists('posted_client_fields')) {
        $cfg = posted_client_fields();
        db_exec(
            'UPDATE companies SET client_audience=?, client_fields=?, line_columns=? WHERE id=?',
            'sssi',
            [$cfg['audience'], json_encode($cfg, JSON_UNESCAPED_UNICODE) ?: '{}', $lineCols ?? '', $cid]
        );
    } elseif ($lineCols !== null) {
        db_exec('UPDATE companies SET line_columns=? WHERE id=?', 'si', [$lineCols, $cid]);
    }
    $color = '#1E4EFF';
    $accent = '#C6A15B';
    $deep = function_exists('hex_shade') ? hex_shade($color, 0.52) : '#08143A';
    $prefix = function_exists('prefix_from_name') ? prefix_from_name($name) : 'VEL';

    db_exec(
        'INSERT INTO branding (company_id, name, tagline, tin, vat_no, address, city, phone, email, website, bank_name, account_name, account_number, brand_color, brand_accent, brand_deep, logo_path, prefix, payment_note, invoice_comments, receipt_comments, plan, currency)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        'issssssssssssssssssssss',
        [
            // Neutral tagline - client should not feel they are in a trial desk.
            $cid, $name, '', '', '', $address, $city, $phone, '', '', '', $name, '',
            $color, $accent, $deep, '', $prefix,
            'Make payment to ' . $name . '.',
            "1. Payment is due by the date shown above.\n2. Quote the invoice number on the transfer.",
            'Payments made are not refundable.',
            'office', 'UGX',
        ]
    );

    $made = create_desk_user($cid, [
        'name' => $contact,
        'email' => $userEmail,
        'password' => $password,
        'job_title' => 'Administrator',
        'access' => 'admin',
    ]);
    if (empty($made['ok'])) {
        if (function_exists('platform_delete_company')) {
            platform_delete_company($cid);
        }
        return ['ok' => false, 'error' => (string) ($made['error'] ?? 'Could not create the desk login.')];
    }

    if (function_exists('company_mark_onboard_step')) {
        company_mark_onboard_step($cid, 'desk_login');
    }

    if ($leadId > 0) {
        $lead = sales_lead($leadId);
        if ($lead && (empty($lead['company_id']) || (int) $lead['company_id'] === $cid)) {
            db_exec(
                'UPDATE sales_leads SET company_id=?, updated_at=NOW() WHERE id=?',
                'ii',
                [$cid, $leadId]
            );
            sales_lead_event($leadId, $actorId, 'testing', (string) ($lead['status'] ?? 'interested'), (string) ($lead['status'] ?? 'interested'), 'Testing company #' . $cid . ' for 2 weeks');
        }
    }

    sales_vault_save([
        'company_id' => $cid,
        'company_name' => $name,
        'email' => $userEmail,
        'password' => (string) ($made['password'] ?? $password),
        'notes' => 'Trial desk · expires ' . $expires . ' · hand credentials to the client',
    ]);

    return [
        'ok' => true,
        'company_id' => $cid,
        'name' => $name,
        'email' => $userEmail,
        'password' => (string) ($made['password'] ?? $password),
        'expires_at' => $expires,
        'days' => $days,
        'owner_id' => $ownerId,
    ];
}

/** Presence subselects for testing desks (last login / active now). */
function sales_testing_presence_select(): string
{
    $mins = function_exists('platform_online_window_minutes') ? (int) platform_online_window_minutes() : 5;
    return "(SELECT MAX(u.last_login_at) FROM users u WHERE u.company_id = c.id AND u.role <> 'platform') AS last_login_at,
            (SELECT MAX(u.last_seen_at) FROM users u WHERE u.company_id = c.id AND u.role <> 'platform') AS last_seen_at,
            (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id AND u.role <> 'platform'
               AND u.last_seen_at > DATE_SUB(NOW(), INTERVAL {$mins} MINUTE)) AS online_users";
}

function sales_testing_companies_for_agent(int $agentId): array
{
    if ($agentId < 1) {
        return [];
    }
    $presence = sales_testing_presence_select();
    try {
        return db_all(
            "SELECT c.*,
                    (SELECT u.email FROM users u WHERE u.company_id = c.id AND u.role = 'admin' ORDER BY u.id ASC LIMIT 1) AS desk_email,
                    (SELECT COUNT(*) FROM users u2 WHERE u2.company_id = c.id) AS users,
                    {$presence}
             FROM companies c
             WHERE c.testing_mode = 1 AND c.testing_owner_id = ?
             ORDER BY last_login_at IS NULL, last_login_at DESC, c.testing_expires_at ASC, c.id DESC",
            'i',
            [$agentId]
        );
    } catch (Throwable $e) {
        return db_all(
            "SELECT c.*,
                    (SELECT u.email FROM users u WHERE u.company_id = c.id AND u.role = 'admin' ORDER BY u.id ASC LIMIT 1) AS desk_email,
                    (SELECT COUNT(*) FROM users u2 WHERE u2.company_id = c.id) AS users
             FROM companies c
             WHERE c.testing_mode = 1 AND c.testing_owner_id = ?
             ORDER BY c.testing_expires_at ASC, c.id DESC",
            'i',
            [$agentId]
        );
    }
}

function admin_testing_companies(): array
{
    $presence = sales_testing_presence_select();
    try {
        return db_all(
            "SELECT c.*,
                    u.name AS owner_name,
                    u.email AS owner_email,
                    (SELECT du.email FROM users du WHERE du.company_id = c.id AND du.role = 'admin' ORDER BY du.id ASC LIMIT 1) AS desk_email,
                    (SELECT COUNT(*) FROM users u2 WHERE u2.company_id = c.id) AS users,
                    {$presence}
             FROM companies c
             LEFT JOIN users u ON u.id = c.testing_owner_id
             WHERE c.testing_mode = 1
             ORDER BY last_login_at IS NULL, last_login_at DESC, c.testing_expires_at ASC, c.id DESC"
        );
    } catch (Throwable $e) {
        return db_all(
            "SELECT c.*,
                    u.name AS owner_name,
                    u.email AS owner_email,
                    (SELECT du.email FROM users du WHERE du.company_id = c.id AND du.role = 'admin' ORDER BY du.id ASC LIMIT 1) AS desk_email,
                    (SELECT COUNT(*) FROM users u2 WHERE u2.company_id = c.id) AS users
             FROM companies c
             LEFT JOIN users u ON u.id = c.testing_owner_id
             WHERE c.testing_mode = 1
             ORDER BY c.testing_expires_at ASC, c.id DESC"
        );
    }
}

/** Active / last-login pills for a testing company row. */
function sales_testing_activity(array $company): array
{
    $online = (int) ($company['online_users'] ?? 0) > 0
        || (function_exists('user_is_online') && user_is_online((string) ($company['last_seen_at'] ?? '')));
    $login = (string) ($company['last_login_at'] ?? '');
    $health = function_exists('platform_desk_health')
        ? platform_desk_health($company)
        : ['key' => $login !== '' ? 'healthy' : 'slow', 'label' => $login !== '' ? 'Signed in' : 'No sign-in yet'];
    return [
        'online' => $online,
        'last_login' => $login,
        'last_login_label' => function_exists('format_when') ? format_when($login !== '' ? $login : null) : ($login !== '' ? $login : 'Never'),
        'status_key' => $online ? 'fast' : (string) ($health['key'] ?? 'slow'),
        'status_label' => $online ? 'Active now' : (string) ($health['label'] ?? 'No sign-in yet'),
    ];
}

function sales_testing_company(int $companyId, ?int $agentId = null): ?array
{
    $row = db_one('SELECT * FROM companies WHERE id = ? AND testing_mode = 1', 'i', [$companyId]);
    if (!$row) {
        return null;
    }
    if ($agentId !== null && (int) ($row['testing_owner_id'] ?? 0) !== $agentId) {
        return null;
    }
    return $row;
}

function sales_testing_credentials(int $companyId): array
{
    $vault = db_one('SELECT * FROM sales_vault WHERE company_id = ? ORDER BY id DESC LIMIT 1', 'i', [$companyId]);
    $admin = db_one("SELECT id, name, email FROM users WHERE company_id = ? AND role = 'admin' ORDER BY id ASC LIMIT 1", 'i', [$companyId]);
    $email = (string) ($admin['email'] ?? ($vault['email'] ?? ''));
    $password = $vault ? sales_vault_decrypt((string) $vault['password_enc']) : '';
    return [
        'user_id' => (int) ($admin['id'] ?? 0),
        'name' => (string) ($admin['name'] ?? ''),
        'email' => $email,
        'password' => $password,
    ];
}

function sales_adjust_testing_days(int $companyId, int $deltaDays): array
{
    $company = db_one('SELECT * FROM companies WHERE id = ? AND testing_mode = 1', 'i', [$companyId]);
    if (!$company) {
        return ['ok' => false, 'error' => 'Testing company not found.'];
    }
    $exp = company_testing_expires_at($company) ?: (function_exists('desk_now') ? desk_now() : new DateTimeImmutable('now'));
    $now = function_exists('desk_now') ? desk_now() : new DateTimeImmutable('now');
    if ($exp < $now) {
        $exp = $now;
    }
    $next = $exp->modify(($deltaDays >= 0 ? '+' : '') . $deltaDays . ' days');
    if ($next < $now) {
        $next = $now->modify('+1 hour');
    }
    $stamp = $next->format('Y-m-d H:i:s');
    db_exec('UPDATE companies SET testing_expires_at = ? WHERE id = ?', 'si', [$stamp, $companyId]);
    return ['ok' => true, 'expires_at' => $stamp];
}

function sales_set_testing_expiry(int $companyId, string $when): array
{
    $company = db_one('SELECT * FROM companies WHERE id = ? AND testing_mode = 1', 'i', [$companyId]);
    if (!$company) {
        return ['ok' => false, 'error' => 'Testing company not found.'];
    }
    $when = trim($when);
    if ($when === '') {
        return ['ok' => false, 'error' => 'Pick an end date.'];
    }
    try {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $when)) {
            $when .= ' 23:59:59';
        }
        $dt = new DateTimeImmutable($when);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'That end date is not valid.'];
    }
    $stamp = $dt->format('Y-m-d H:i:s');
    db_exec('UPDATE companies SET testing_expires_at = ? WHERE id = ?', 'si', [$stamp, $companyId]);
    return ['ok' => true, 'expires_at' => $stamp];
}

/**
 * Promote a testing desk to normal onboard.
 *
 * Options:
 * - desk_email: lasting login (blank = keep current)
 * - keep_email: when true, ignore desk_email and keep current login
 * - data_mode: "keep" (default) or "clean" (wipe practice books via training reset)
 */
function sales_promote_testing_to_onboard(int $companyId, array $opts = []): array
{
    $company = db_one('SELECT * FROM companies WHERE id = ? AND testing_mode = 1', 'i', [$companyId]);
    if (!$company) {
        return ['ok' => false, 'error' => 'Testing company not found.'];
    }

    $admin = db_one(
        "SELECT id, email FROM users WHERE company_id = ? AND role = 'admin' ORDER BY id ASC LIMIT 1",
        'i',
        [$companyId]
    );
    if (!$admin) {
        return ['ok' => false, 'error' => 'No desk login on this testing company.'];
    }

    $currentEmail = strtolower(trim((string) ($admin['email'] ?? '')));
    $keepEmail = !empty($opts['keep_email']);
    $email = $keepEmail
        ? $currentEmail
        : strtolower(trim((string) ($opts['desk_email'] ?? $currentEmail)));
    if ($email === '') {
        $email = $currentEmail;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid desk login email.'];
    }

    $dataMode = strtolower(trim((string) ($opts['data_mode'] ?? 'keep')));
    if (!in_array($dataMode, ['keep', 'clean'], true)) {
        return ['ok' => false, 'error' => 'Choose keep or clean data.'];
    }

    $emailChanged = $email !== $currentEmail;
    $password = '';
    if ($emailChanged) {
        $taken = db_one('SELECT id FROM users WHERE email = ? AND id <> ?', 'si', [$email, (int) $admin['id']]);
        if ($taken) {
            return ['ok' => false, 'error' => 'That email already has a Vellisys login.'];
        }
        $login = sales_testing_set_login($companyId, $email, true);
        if (empty($login['ok'])) {
            return ['ok' => false, 'error' => (string) ($login['error'] ?? 'Could not update desk login.')];
        }
        $password = (string) ($login['password'] ?? sales_testing_default_password());
        $email = (string) ($login['email'] ?? $email);
    } else {
        $creds = sales_testing_credentials($companyId);
        $password = (string) ($creds['password'] ?? '');
        if ($password === '') {
            $password = sales_testing_default_password();
        }
    }

    $cleared = [];
    if ($dataMode === 'clean') {
        if (!function_exists('company_reset_training_data') || !function_exists('company_reset_scopes')) {
            return ['ok' => false, 'error' => 'Data clean is unavailable right now. Try again after refresh.'];
        }
        $wipe = company_reset_training_data($companyId, array_keys(company_reset_scopes()));
        if (empty($wipe['ok'])) {
            return ['ok' => false, 'error' => (string) ($wipe['error'] ?? 'Could not clean data.')];
        }
        $cleared = $wipe['cleared'] ?? [];
    }

    $noteBit = $dataMode === 'clean' ? 'data cleaned' : 'data kept';
    if ($emailChanged) {
        $noteBit .= '; login ' . $email;
    } else {
        $noteBit .= '; login kept';
    }
    db_exec(
        "UPDATE companies SET testing_mode = 0, testing_owner_id = NULL, testing_expires_at = NULL, status = 'onboarding',
         notes = CONCAT(COALESCE(notes,''), '\nPromoted from testing on ', DATE_FORMAT(NOW(), '%Y-%m-%d'), ' · ', ?)
         WHERE id = ?",
        'si',
        [$noteBit, $companyId]
    );

    $vaultRow = db_one('SELECT id FROM sales_vault WHERE company_id = ? ORDER BY id DESC LIMIT 1', 'i', [$companyId]);
    if ($vaultRow && function_exists('sales_vault_save')) {
        sales_vault_save([
            'company_id' => $companyId,
            'company_name' => (string) $company['name'],
            'email' => $email,
            'password' => $password,
            'notes' => 'Promoted from testing · ' . $noteBit,
        ], (int) $vaultRow['id']);
    }

    $lead = db_one('SELECT id, status FROM sales_leads WHERE company_id = ? ORDER BY id DESC LIMIT 1', 'i', [$companyId]);
    if ($lead && (string) $lead['status'] === 'interested') {
        db_exec("UPDATE sales_leads SET status='onboarding', updated_at=NOW() WHERE id=?", 'i', [(int) $lead['id']]);
        sales_lead_event(
            (int) $lead['id'],
            (int) (current_user()['id'] ?? 0),
            'onboarding',
            'interested',
            'onboarding',
            'Promoted testing company #' . $companyId . ' · ' . $noteBit
        );
    }

    return [
        'ok' => true,
        'company_id' => $companyId,
        'email' => $email,
        'password' => $password,
        'email_changed' => $emailChanged,
        'data_mode' => $dataMode,
        'cleared' => $cleared,
    ];
}

function sales_update_testing_company(int $companyId, array $fields, ?int $agentId = null): array
{
    $company = sales_testing_company($companyId, $agentId);
    if (!$company && $agentId === null) {
        $company = db_one('SELECT * FROM companies WHERE id = ? AND testing_mode = 1', 'i', [$companyId]);
    }
    if (!$company) {
        return ['ok' => false, 'error' => 'Testing company not found.'];
    }
    $name = trim((string) ($fields['name'] ?? $company['name']));
    $phone = trim((string) ($fields['phone'] ?? ''));
    $city = trim((string) ($fields['city'] ?? ''));
    $address = trim((string) ($fields['address'] ?? ''));
    $nature = function_exists('sanitize_nature_of_business')
        ? sanitize_nature_of_business((string) ($fields['nature_of_business'] ?? ($company['nature_of_business'] ?? '')))
        : trim((string) ($fields['nature_of_business'] ?? ''));
    if ($name === '') {
        return ['ok' => false, 'error' => 'Enter the business name.'];
    }
    $kindsPosted = $fields['enabled_kinds'] ?? ($_POST['enabled_kinds'] ?? null);
    $kinds = is_array($kindsPosted) && $kindsPosted !== [] && function_exists('posted_enabled_kinds')
        ? posted_enabled_kinds()
        : (string) ($company['enabled_kinds'] ?? '');
    $customDoc = function_exists('posted_custom_doc') && is_array($kindsPosted)
        ? posted_custom_doc()
        : (string) ($company['custom_doc'] ?? '');
    $lineCols = function_exists('posted_document_line_columns') && (isset($_POST['line_columns_present']) || isset($_POST['line_col_kind']) || isset($_POST['line_columns']))
        ? posted_document_line_columns()
        : (string) ($company['line_columns'] ?? '');

    db_exec(
        'UPDATE companies SET name=?, nature_of_business=?, enabled_kinds=?, custom_doc=?, line_columns=? WHERE id=?',
        'sssssi',
        [$name, $nature, $kinds, $customDoc, $lineCols, $companyId]
    );
    if (function_exists('posted_client_fields') && !empty($_POST['to_order_present'])) {
        $cfg = posted_client_fields();
        db_exec(
            'UPDATE companies SET client_audience=?, client_fields=? WHERE id=?',
            'ssi',
            [$cfg['audience'], json_encode($cfg, JSON_UNESCAPED_UNICODE) ?: '{}', $companyId]
        );
    }
    db_exec(
        'UPDATE branding SET name=?, phone=?, city=?, address=?, account_name=? WHERE company_id=?',
        'sssssi',
        [$name, $phone, $city, $address, $name, $companyId]
    );
    $contact = trim((string) ($fields['contact_name'] ?? ''));
    if ($contact !== '') {
        $admin = db_one("SELECT id FROM users WHERE company_id = ? AND role = 'admin' ORDER BY id ASC LIMIT 1", 'i', [$companyId]);
        if ($admin) {
            db_exec('UPDATE users SET name = ? WHERE id = ?', 'si', [$contact, (int) $admin['id']]);
        }
    }
    return ['ok' => true, 'company_id' => $companyId];
}

/** Open a testing desk as the desk admin (agent walkthrough), with return to sales portal. */
function sales_testing_desk_enter(int $companyId, array $fromUser): array
{
    $role = (string) ($fromUser['role'] ?? '');
    $agentId = $role === 'platform' ? null : (int) ($fromUser['id'] ?? 0);
    $company = $agentId ? sales_testing_company($companyId, $agentId) : db_one('SELECT * FROM companies WHERE id = ? AND testing_mode = 1', 'i', [$companyId]);
    if (!$company) {
        return ['ok' => false, 'error' => 'Testing company not found.'];
    }
    if (company_testing_expired($company)) {
        return ['ok' => false, 'error' => 'This testing desk has ended.'];
    }
    $admin = db_one("SELECT * FROM users WHERE company_id = ? AND role = 'admin' ORDER BY id ASC LIMIT 1", 'i', [$companyId]);
    if (!$admin) {
        return ['ok' => false, 'error' => 'No desk login on this testing company.'];
    }
    $_SESSION['sales_demo_return'] = [
        'user_id' => (int) $fromUser['id'],
        'company_id' => (int) ($fromUser['company_id'] ?? 0),
        'role' => $role,
        'acting_company_id' => (int) ($_SESSION['acting_company_id'] ?? 0),
        'from_testing' => $companyId,
    ];
    unset($_SESSION['acting_company_id']);
    $_SESSION['user_id'] = (int) $admin['id'];
    $_SESSION['company_id'] = $companyId;
    $_SESSION['role'] = (string) ($admin['role'] ?? 'admin');
    unset($_SESSION['desk_welcome']);
    if (function_exists('mark_desk_welcome_seen')) {
        mark_desk_welcome_seen((int) $admin['id']);
    }
    if (function_exists('branding')) {
        try {
            branding(true);
        } catch (Throwable $e) {
            // ignore
        }
    }
    return ['ok' => true, 'company_id' => $companyId, 'name' => (string) $company['name']];
}

/**
 * Shared WHERE builder for lead lists / chip counts.
 * @return array{where:list<string>,types:string,params:list<mixed>,join_company:bool}
 */
function sales_leads_filters(array $opts = []): array
{
    $where = ['1=1'];
    $types = '';
    $params = [];
    $joinCompany = !empty($opts['on_test']);
    if (empty($opts['include_deleted'])) {
        $where[] = 'l.deleted_at IS NULL';
    }
    if (!empty($opts['agent_id'])) {
        $where[] = 'l.agent_id = ?';
        $types .= 'i';
        $params[] = (int) $opts['agent_id'];
    }
    if (!empty($opts['status'])) {
        $where[] = 'l.status = ?';
        $types .= 's';
        $params[] = (string) $opts['status'];
    }
    if (!empty($opts['follow_bucket'])) {
        if ($opts['follow_bucket'] === 'due') {
            $where[] = "l.status = 'follow_up' AND l.follow_up_date IS NOT NULL AND l.follow_up_done_at IS NULL";
        } elseif ($opts['follow_bucket'] === 'done') {
            $where[] = 'l.follow_up_done_at IS NOT NULL';
        } elseif ($opts['follow_bucket'] === 'overdue') {
            $where[] = "l.status = 'follow_up' AND l.follow_up_date < ? AND l.follow_up_done_at IS NULL";
            $types .= 's';
            $params[] = today();
        }
    }
    if (!empty($opts['from'])) {
        $where[] = 'DATE(l.created_at) >= ?';
        $types .= 's';
        $params[] = $opts['from'];
    }
    if (!empty($opts['to'])) {
        $where[] = 'DATE(l.created_at) <= ?';
        $types .= 's';
        $params[] = $opts['to'];
    }
    if (!empty($opts['q'])) {
        $like = '%' . $opts['q'] . '%';
        $where[] = '(l.business_name LIKE ? OR l.contact_name LIKE ? OR l.contact_phone LIKE ? OR l.city LIKE ? OR l.address LIKE ?)';
        $types .= 'sssss';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if (!empty($opts['on_test'])) {
        $where[] = 'c.testing_mode = 1';
        $joinCompany = true;
    }
    return [
        'where' => $where,
        'types' => $types,
        'params' => $params,
        'join_company' => $joinCompany,
    ];
}

function sales_leads_count(array $opts = []): int
{
    $f = sales_leads_filters($opts);
    $sql = 'SELECT COUNT(*) AS n FROM sales_leads l';
    if (!empty($f['join_company'])) {
        $sql .= ' LEFT JOIN companies c ON c.id = l.company_id';
    }
    $sql .= ' WHERE ' . implode(' AND ', $f['where']);
    try {
        $row = db_one($sql, $f['types'], $f['params']);
        return (int) ($row['n'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function sales_leads_query(array $opts = []): array
{
    $f = sales_leads_filters($opts);
    $sql = 'SELECT l.*, u.name AS agent_name,
                   c.testing_mode AS company_testing_mode,
                   c.testing_expires_at AS company_testing_expires_at,
                   c.status AS company_status
            FROM sales_leads l
            LEFT JOIN users u ON u.id = l.agent_id
            LEFT JOIN companies c ON c.id = l.company_id
            WHERE '
        . implode(' AND ', $f['where']);
    if (!empty($opts['follow_bucket']) && in_array((string) $opts['follow_bucket'], ['due', 'overdue'], true)) {
        // High interest first so agents prioritise warm follow-ups.
        $sql .= ' ORDER BY l.interest_rating DESC, l.follow_up_date ASC, l.follow_up_time IS NULL, l.follow_up_time ASC, l.id ASC';
    } else {
        $sql .= ' ORDER BY l.updated_at DESC, l.id DESC';
    }
    if (!empty($opts['limit'])) {
        $sql .= ' LIMIT ' . (int) $opts['limit'];
    }
    return db_all($sql, $f['types'], $f['params']);
}

/** Lead badge when a testing desk is linked. */
function sales_lead_testing_label(?array $lead): string
{
    if (!$lead || empty($lead['company_id']) || empty($lead['company_testing_mode'])) {
        return '';
    }
    if (function_exists('company_testing_expired') && company_testing_expired([
        'testing_mode' => 1,
        'testing_expires_at' => $lead['company_testing_expires_at'] ?? null,
    ])) {
        return 'Test ended';
    }
    return 'On test';
}

function sales_period_bounds(): array
{
    $p = period_range();
    $from = $p['from'] !== '' ? $p['from'] : '1970-01-01';
    $to = $p['to'] !== '' ? $p['to'] : today();
    return [$from, $to, $p];
}

/** Companies put into testing mode in a date range (by company created_at). */
function sales_testing_count(?int $agentId, string $from, string $to): int
{
    $where = 'testing_mode = 1 AND DATE(created_at) >= ? AND DATE(created_at) <= ?';
    $types = 'ss';
    $params = [$from, $to];
    if ($agentId) {
        $where .= ' AND testing_owner_id = ?';
        $types .= 'i';
        $params[] = $agentId;
    }
    try {
        $row = db_one("SELECT COUNT(*) AS n FROM companies WHERE {$where}", $types, $params);
        return (int) ($row['n'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/** Currently active testing desks (not filtered by report period). */
function sales_testing_active_count(?int $agentId = null): int
{
    $where = 'testing_mode = 1';
    $types = '';
    $params = [];
    if ($agentId) {
        $where .= ' AND testing_owner_id = ?';
        $types = 'i';
        $params[] = $agentId;
    }
    try {
        $row = $types === ''
            ? db_one("SELECT COUNT(*) AS n FROM companies WHERE {$where}")
            : db_one("SELECT COUNT(*) AS n FROM companies WHERE {$where}", $types, $params);
        return (int) ($row['n'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function sales_stats(?int $agentId, string $from, string $to): array
{
    // Soft-deleted rejected leads still count in reports.
    $where = 'DATE(created_at) >= ? AND DATE(created_at) <= ?';
    $types = 'ss';
    $params = [$from, $to];
    if ($agentId) {
        $where .= ' AND agent_id = ?';
        $types .= 'i';
        $params[] = $agentId;
    }
    $rows = db_all(
        "SELECT status, COUNT(*) AS n FROM sales_leads WHERE {$where} GROUP BY status",
        $types,
        $params
    );
    $by = ['interested' => 0, 'follow_up' => 0, 'rejected' => 0, 'onboarding' => 0, 'onboarded' => 0];
    foreach ($rows as $r) {
        $key = (string) $r['status'];
        if (!isset($by[$key])) {
            $by[$key] = 0;
        }
        $by[$key] = (int) $r['n'];
    }
    $reach = array_sum($by);
    // Real sales = clients moved into onboarding / onboarded (not merely interested).
    $sales = $by['onboarded'] + $by['onboarding'];
    $onTest = sales_testing_count($agentId, $from, $to);
    return [
        'by_status' => $by,
        'reach' => $reach,
        'wins' => $sales,
        'sales' => $sales,
        'interested' => $by['interested'],
        'follow_up' => $by['follow_up'],
        'rejected' => $by['rejected'],
        'onboarding' => $by['onboarding'],
        'onboarded' => $by['onboarded'],
        'on_test' => $onTest,
        'testing' => $onTest,
        'testing_active' => sales_testing_active_count($agentId),
    ];
}

function sales_goal_default_values(): array
{
    return [
        'daily_reach' => 10,
        'daily_sales' => 2,
        'weekly_reach' => 0,
        'weekly_sales' => 10,
        'monthly_reach' => 0,
        'monthly_sales' => 30,
    ];
}

function sales_goal_defaults(): array
{
    $fallbacks = sales_goal_default_values();
    try {
        $row = db_one('SELECT * FROM sales_goal_defaults WHERE id = 1');
    } catch (Throwable $e) {
        return $fallbacks;
    }
    if (!$row) {
        return $fallbacks;
    }
    return [
        'daily_reach' => max(0, (int) ($row['daily_reach'] ?? $fallbacks['daily_reach'])),
        'daily_sales' => max(0, (int) ($row['daily_sales'] ?? $fallbacks['daily_sales'])),
        'weekly_reach' => max(0, (int) ($row['weekly_reach'] ?? $fallbacks['weekly_reach'])),
        'weekly_sales' => max(0, (int) ($row['weekly_sales'] ?? $fallbacks['weekly_sales'])),
        'monthly_reach' => max(0, (int) ($row['monthly_reach'] ?? $fallbacks['monthly_reach'])),
        'monthly_sales' => max(0, (int) ($row['monthly_sales'] ?? $fallbacks['monthly_sales'])),
    ];
}

function sales_goal_defaults_save(array $fields): array
{
    $vals = [
        'daily_reach' => max(0, (int) ($fields['daily_reach'] ?? 10)),
        'daily_sales' => max(0, (int) ($fields['daily_sales'] ?? 2)),
        'weekly_reach' => max(0, (int) ($fields['weekly_reach'] ?? 0)),
        'weekly_sales' => max(0, (int) ($fields['weekly_sales'] ?? 10)),
        'monthly_reach' => max(0, (int) ($fields['monthly_reach'] ?? 0)),
        'monthly_sales' => max(0, (int) ($fields['monthly_sales'] ?? 30)),
    ];
    if ($vals['daily_reach'] < 1 && $vals['daily_sales'] < 1
        && $vals['weekly_reach'] < 1 && $vals['weekly_sales'] < 1
        && $vals['monthly_reach'] < 1 && $vals['monthly_sales'] < 1) {
        return ['ok' => false, 'error' => 'Set at least one goal number.'];
    }
    $existing = db_one('SELECT id FROM sales_goal_defaults WHERE id = 1');
    if ($existing) {
        db_exec(
            'UPDATE sales_goal_defaults SET daily_reach=?, daily_sales=?, weekly_reach=?, weekly_sales=?, monthly_reach=?, monthly_sales=?, updated_at=NOW() WHERE id=1',
            'iiiiii',
            [$vals['daily_reach'], $vals['daily_sales'], $vals['weekly_reach'], $vals['weekly_sales'], $vals['monthly_reach'], $vals['monthly_sales']]
        );
    } else {
        db_exec(
            'INSERT INTO sales_goal_defaults (id, daily_reach, daily_sales, weekly_reach, weekly_sales, monthly_reach, monthly_sales) VALUES (1,?,?,?,?,?,?)',
            'iiiiii',
            [$vals['daily_reach'], $vals['daily_sales'], $vals['weekly_reach'], $vals['weekly_sales'], $vals['monthly_reach'], $vals['monthly_sales']]
        );
    }
    return ['ok' => true] + $vals;
}

/** @return array{0:string,1:string} from, to inclusive */
function sales_period_window(string $kind, ?string $onDate = null): array
{
    $on = $onDate ?: today();
    $ts = strtotime($on) ?: time();
    if ($kind === 'daily') {
        $d = date('Y-m-d', $ts);
        return [$d, $d];
    }
    if ($kind === 'weekly') {
        $dow = (int) date('N', $ts);
        $start = date('Y-m-d', strtotime('-' . ($dow - 1) . ' days', $ts));
        $end = date('Y-m-d', strtotime('+' . (7 - $dow) . ' days', $ts));
        return [$start, $end];
    }
    return [date('Y-m-01', $ts), date('Y-m-t', $ts)];
}

function sales_goals_for_period(string $kind): array
{
    $d = sales_goal_defaults();
    if ($kind === 'daily') {
        return ['reach' => $d['daily_reach'], 'sales' => $d['daily_sales']];
    }
    if ($kind === 'weekly') {
        return ['reach' => $d['weekly_reach'], 'sales' => $d['weekly_sales']];
    }
    return ['reach' => $d['monthly_reach'], 'sales' => $d['monthly_sales']];
}

function sales_progress(?int $agentId, string $kind, ?string $onDate = null): array
{
    $kind = in_array($kind, ['daily', 'weekly', 'monthly'], true) ? $kind : 'daily';
    [$from, $to] = sales_period_window($kind, $onDate);
    $today = today();
    $statTo = $to > $today ? $today : $to;
    $stats = sales_stats($agentId, $from, $statTo);
    $goals = sales_goals_for_period($kind);
    // Goal progress tracks interested clients (field targets), not closed sales.
    return [
        'kind' => $kind,
        'from' => $from,
        'to' => $to,
        'stat_to' => $statTo,
        'reach' => (int) $stats['reach'],
        'reach_goal' => (int) $goals['reach'],
        'sales' => (int) $stats['interested'],
        'sales_goal' => (int) $goals['sales'],
        'stats' => $stats,
    ];
}

function sales_progress_pct(int $current, int $goal): int
{
    if ($goal < 1) {
        return $current > 0 ? 100 : 0;
    }
    return (int) round(100 * $current / $goal);
}

/** open | hit | over | none */
function sales_goal_status(int $current, int $goal): string
{
    if ($goal < 1) {
        return 'none';
    }
    if ($current > $goal) {
        return 'over';
    }
    if ($current >= $goal) {
        return 'hit';
    }
    return 'open';
}

function sales_goal_over_by(int $current, int $goal): int
{
    return ($goal > 0 && $current > $goal) ? ($current - $goal) : 0;
}

function sales_agents_daily_progress(): array
{
    $out = [];
    foreach (sales_agents(true) as $agent) {
        $id = (int) $agent['id'];
        $progress = sales_progress($id, 'daily');
        $out[] = [
            'agent' => $agent,
            'progress' => $progress,
            'clock' => sales_today_clock($id),
            'on_test' => (int) ($progress['stats']['on_test'] ?? 0),
            'testing_active' => sales_testing_active_count($id),
        ];
    }
    return $out;
}

function sales_target_for(?int $agentId, ?string $onDate = null): ?array
{
    $onDate = $onDate ?: today();
    if ($agentId) {
        $row = db_one(
            'SELECT * FROM sales_targets WHERE agent_id = ? AND period_start <= ? AND period_end >= ? ORDER BY period_start DESC LIMIT 1',
            'iss',
            [$agentId, $onDate, $onDate]
        );
        if ($row) {
            return $row;
        }
    }
    return db_one(
        'SELECT * FROM sales_targets WHERE agent_id IS NULL AND period_start <= ? AND period_end >= ? ORDER BY period_start DESC LIMIT 1',
        'ss',
        [$onDate, $onDate]
    );
}

function sales_target_save(array $fields): array
{
    $agentId = (int) ($fields['agent_id'] ?? 0) ?: null;
    $start = trim((string) ($fields['period_start'] ?? ''));
    $end = trim((string) ($fields['period_end'] ?? ''));
    $reach = max(0, (int) ($fields['reach_target'] ?? 0));
    $sales = max(0, (int) ($fields['sales_target'] ?? 0));
    $notes = mb_substr(trim((string) ($fields['notes'] ?? '')), 0, 500);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        return ['ok' => false, 'error' => 'Set a period start and end.'];
    }
    if ($end < $start) {
        return ['ok' => false, 'error' => 'Period end must be on or after the start.'];
    }
    if ($reach < 1 && $sales < 1) {
        return ['ok' => false, 'error' => 'Set a reach or sales target.'];
    }
    $id = (int) ($fields['id'] ?? 0);
    $uid = (int) (current_user()['id'] ?? 0);
    if ($id > 0) {
        db_exec(
            'UPDATE sales_targets SET agent_id=?, period_start=?, period_end=?, reach_target=?, sales_target=?, notes=? WHERE id=?',
            'issiisi',
            [$agentId, $start, $end, $reach, $sales, $notes, $id]
        );
        return ['ok' => true, 'id' => $id];
    }
    $newId = db_exec(
        'INSERT INTO sales_targets (agent_id, period_start, period_end, reach_target, sales_target, notes, created_by) VALUES (?,?,?,?,?,?,?)',
        'issiisi',
        [$agentId, $start, $end, $reach, $sales, $notes, $uid]
    );
    return ['ok' => true, 'id' => (int) $newId];
}

function sales_lead_hard_delete(int $id): array
{
    $row = sales_lead($id);
    if (!$row) {
        return ['ok' => false, 'error' => 'Lead not found.'];
    }
    if ((string) $row['status'] !== 'rejected' && empty($row['deleted_at'])) {
        return ['ok' => false, 'error' => 'Only rejected (not interested) businesses can be deleted.'];
    }
    db_exec('DELETE FROM sales_lead_events WHERE lead_id = ?', 'i', [$id]);
    db_exec('DELETE FROM sales_leads WHERE id = ?', 'i', [$id]);
    return ['ok' => true];
}

function sales_lead_admin_save(array $fields, ?int $id = null): array
{
    $agentId = (int) ($fields['agent_id'] ?? 0);
    if ($agentId < 1 || !sales_agent($agentId)) {
        return ['ok' => false, 'error' => 'Pick a sales agent for this business.'];
    }
    $status = (string) ($fields['status'] ?? '');
    if (!isset(sales_statuses()[$status])) {
        return ['ok' => false, 'error' => 'Choose a valid status.'];
    }
    $business = mb_substr(trim((string) ($fields['business_name'] ?? '')), 0, 190);
    $address = mb_substr(trim((string) ($fields['address'] ?? '')), 0, 190);
    $contactName = mb_substr(trim((string) ($fields['contact_name'] ?? '')), 0, 120);
    $contactPhone = mb_substr(trim((string) ($fields['contact_phone'] ?? '')), 0, 40);
    $city = mb_substr(trim((string) ($fields['city'] ?? '')), 0, 120);
    $notes = mb_substr(trim((string) ($fields['notes'] ?? '')), 0, 2000);
    $nature = mb_substr(trim((string) ($fields['nature_of_business'] ?? '')), 0, 190);
    $package = mb_substr(trim((string) ($fields['package_chosen'] ?? '')), 0, 40);
    $onboardDate = null;
    $followDate = null;
    $followTime = null;
    $rejected = '';
    $rejectedCat = '';
    $interest = max(0, min(5, (int) ($fields['interest_rating'] ?? 0)));
    $od = trim((string) ($fields['onboard_date'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $od)) {
        $onboardDate = $od;
    }
    $fd = trim((string) ($fields['follow_up_date'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fd)) {
        $followDate = $fd;
    }
    if ($status === 'follow_up') {
        $followTime = sales_normalize_follow_up_time($fields['follow_up_time'] ?? null);
    }
    if ($status === 'follow_up' && !$followDate) {
        return ['ok' => false, 'error' => 'Set a follow-up date.'];
    }
    if ($status === 'follow_up' && $interest < 1) {
        return ['ok' => false, 'error' => 'Rate their interest from 1 to 5.'];
    }
    if ($status === 'rejected') {
        $rejectedCat = trim((string) ($fields['rejected_category'] ?? ''));
        if (!isset(sales_reject_reasons()[$rejectedCat])) {
            return ['ok' => false, 'error' => 'Pick a rejection reason from the list.'];
        }
        $rejected = mb_substr(trim((string) ($fields['rejected_reason'] ?? '')), 0, 500);
        if ($rejected === '') {
            return ['ok' => false, 'error' => 'Explain the rejection below the dropdown.'];
        }
    }
    $actor = (int) (current_user()['id'] ?? 0);
    if ($id) {
        $row = sales_lead($id);
        if (!$row) {
            return ['ok' => false, 'error' => 'Lead not found.'];
        }
        $from = (string) $row['status'];
        $followDone = $row['follow_up_done_at'] ?? null;
        if ($from === 'follow_up' && $status !== 'follow_up') {
            $followDone = date('Y-m-d H:i:s');
        }
        $prevFollowTime = sales_normalize_follow_up_time($row['follow_up_time'] ?? null);
        if ($status === 'follow_up' && (
            $followDate !== ($row['follow_up_date'] ?? null)
            || $followTime !== $prevFollowTime
        )) {
            $followDone = null;
        }
        if ($status === 'onboarded' && $from !== 'onboarded') {
            $followDone = $followDone ?: date('Y-m-d H:i:s');
        }
        db_exec(
            'UPDATE sales_leads SET agent_id=?, status=?, business_name=?, address=?, contact_name=?, contact_phone=?, city=?,
             nature_of_business=?, package_chosen=?, onboard_date=?, follow_up_date=?, follow_up_time=?, follow_up_done_at=?, interest_rating=?,
             rejected_reason=?, rejected_category=?, notes=?, deleted_at=NULL, updated_at=NOW() WHERE id=?',
            'issssssssssssisssi',
            [$agentId, $status, $business, $address, $contactName, $contactPhone, $city, $nature, $package, $onboardDate, $followDate, $followTime, $followDone, $interest, $rejected, $rejectedCat, $notes, $id]
        );
        if ($from !== $status) {
            sales_lead_event($id, $actor ?: $agentId, 'status_change', $from, $status, $notes);
        } else {
            sales_lead_event($id, $actor ?: $agentId, 'update', $from, $status, $notes);
        }
        return ['ok' => true, 'id' => $id];
    }
    $newId = db_exec(
        'INSERT INTO sales_leads (agent_id, status, business_name, address, contact_name, contact_phone, city,
         nature_of_business, package_chosen, onboard_date, follow_up_date, follow_up_time, interest_rating, rejected_reason, rejected_category, notes)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        'isssssssssssisss',
        [$agentId, $status, $business, $address, $contactName, $contactPhone, $city, $nature, $package, $onboardDate, $followDate, $followTime, $interest, $rejected, $rejectedCat, $notes]
    );
    sales_lead_event((int) $newId, $actor ?: $agentId, 'create', null, $status, $notes);
    return ['ok' => true, 'id' => (int) $newId];
}

function sales_render_goal_row(string $label, int $current, int $goal, array $opts = []): void
{
    $status = sales_goal_status($current, $goal);
    $overBy = sales_goal_over_by($current, $goal);
    $pct = sales_progress_pct($current, max(1, $goal));
    // When over target, fill the track and mark where the goal sat.
    $mark = null;
    $baseFill = min(100, $pct);
    $extraFill = 0;
    if ($status === 'over' && $current > 0) {
        $mark = (int) round(100 * $goal / $current);
        $mark = max(4, min(96, $mark));
        $baseFill = $mark;
        $extraFill = 100 - $mark;
    }
    $aria = $goal > 0
        ? ($status === 'over'
            ? "{$label}: {$current}, exceeded target {$goal} by {$overBy}"
            : "{$label}: {$current} of {$goal}")
        : "{$label}: {$current}";
    $rowClass = 'sales-goal-row'
        . ($status === 'hit' ? ' is-hit' : '')
        . ($status === 'over' ? ' is-over' : '');
    ?>
    <div class="<?= h($rowClass) ?>">
      <div class="sales-goal-meta">
        <span><?= h($label) ?></span>
        <strong class="mono"><?= $current ?><?= $goal > 0 ? '/' . $goal : '' ?></strong>
      </div>
      <div class="sales-goal-track<?= $status === 'over' ? ' is-over' : '' ?>" role="img" aria-label="<?= h($aria) ?>">
        <span class="sales-goal-fill" style="width:<?= $baseFill ?>%"></span>
        <?php if ($extraFill > 0): ?>
          <span class="sales-goal-fill is-extra" style="left:<?= $baseFill ?>%;width:<?= $extraFill ?>%"></span>
        <?php endif; ?>
        <?php if ($mark !== null): ?>
          <i class="sales-goal-mark" style="left:<?= $mark ?>%" title="Target <?= $goal ?>"></i>
        <?php endif; ?>
      </div>
      <?php if ($status === 'over'): ?>
        <span class="sales-goal-over">Exceeded by +<?= $overBy ?> · target was <?= $goal ?></span>
      <?php elseif ($status === 'hit'): ?>
        <span class="sales-goal-over is-hit">Target hit</span>
      <?php elseif ($goal > 0 && empty($opts['hide_remaining'])): ?>
        <span class="sales-goal-remain"><?= max(0, $goal - $current) ?> to go</span>
      <?php endif; ?>
    </div>
    <?php
}

function sales_render_goal_bars(array $progress, array $opts = []): void
{
    $compact = !empty($opts['compact']);
    $reachGoal = (int) ($progress['reach_goal'] ?? 0);
    $salesGoal = (int) ($progress['sales_goal'] ?? 0);
    $reach = (int) ($progress['reach'] ?? 0);
    $sales = (int) ($progress['sales'] ?? 0);
    $showReach = $reachGoal > 0 || !empty($opts['force_reach']);
    $showSales = $salesGoal > 0 || !empty($opts['force_sales']);
    if (!$showReach && !$showSales) {
        echo '<p class="muted">No goals set for this period.</p>';
        return;
    }
    $rowOpts = ['hide_remaining' => $compact];
    $salesLabel = 'Interested clients';
    ?>
<div class="sales-goal-bars<?= $compact ? ' is-compact' : '' ?>">
  <?php if ($showReach): ?>
    <?php sales_render_goal_row('Leads reached', $reach, $reachGoal, $rowOpts); ?>
  <?php endif; ?>
  <?php if ($showSales): ?>
    <?php sales_render_goal_row($salesLabel, $sales, $salesGoal, $rowOpts); ?>
  <?php endif; ?>
</div>
    <?php
}

/** Short chip for dashboards when a goal is exceeded. */
function sales_render_goal_chip(int $current, int $goal, string $noun = ''): void
{
    $status = sales_goal_status($current, $goal);
    if ($status === 'over') {
        $over = sales_goal_over_by($current, $goal);
        echo '<em class="sales-goal-chip is-over">+' . $over . ' over'
            . ($noun !== '' ? ' ' . h($noun) : '')
            . '</em>';
        return;
    }
    if ($status === 'hit') {
        echo '<em class="sales-goal-chip is-hit">Target hit</em>';
    }
}

function sales_series(?int $agentId, string $from, string $to): array
{
    // Include soft-deleted rows so hiding a rejected lead does not rewrite charts.
    $where = 'DATE(created_at) >= ? AND DATE(created_at) <= ?';
    $types = 'ss';
    $params = [$from, $to];
    if ($agentId) {
        $where .= ' AND agent_id = ?';
        $types .= 'i';
        $params[] = $agentId;
    }
    $rows = db_all(
        "SELECT DATE(created_at) d, status, COUNT(*) n FROM sales_leads WHERE {$where} GROUP BY DATE(created_at), status ORDER BY d",
        $types,
        $params
    );
    $out = [];
    $start = strtotime($from);
    $end = strtotime($to);
    if ($start && $end && ($end - $start) / 86400 <= 62) {
        for ($t = $start; $t <= $end; $t += 86400) {
            $d = date('Y-m-d', $t);
            $out[$d] = ['date' => $d, 'reach' => 0, 'interested' => 0, 'follow_up' => 0, 'rejected' => 0, 'onboarding' => 0, 'onboarded' => 0, 'sales' => 0, 'on_test' => 0];
        }
    }
    foreach ($rows as $r) {
        $d = (string) $r['d'];
        if (!isset($out[$d])) {
            $out[$d] = ['date' => $d, 'reach' => 0, 'interested' => 0, 'follow_up' => 0, 'rejected' => 0, 'onboarding' => 0, 'onboarded' => 0, 'sales' => 0, 'on_test' => 0];
        }
        $st = (string) $r['status'];
        $n = (int) $r['n'];
        $out[$d]['reach'] += $n;
        if (isset($out[$d][$st])) {
            $out[$d][$st] += $n;
        }
        if ($st === 'onboarded' || $st === 'onboarding') {
            $out[$d]['sales'] += $n;
        }
    }
    try {
        $tWhere = 'testing_mode = 1 AND DATE(created_at) >= ? AND DATE(created_at) <= ?';
        $tTypes = 'ss';
        $tParams = [$from, $to];
        if ($agentId) {
            $tWhere .= ' AND testing_owner_id = ?';
            $tTypes .= 'i';
            $tParams[] = $agentId;
        }
        $tRows = db_all(
            "SELECT DATE(created_at) d, COUNT(*) n FROM companies WHERE {$tWhere} GROUP BY DATE(created_at)",
            $tTypes,
            $tParams
        );
        foreach ($tRows as $tr) {
            $d = (string) $tr['d'];
            if (!isset($out[$d])) {
                $out[$d] = ['date' => $d, 'reach' => 0, 'interested' => 0, 'follow_up' => 0, 'rejected' => 0, 'onboarding' => 0, 'onboarded' => 0, 'sales' => 0, 'on_test' => 0];
            }
            $out[$d]['on_test'] = (int) $tr['n'];
        }
    } catch (Throwable $e) {
        // ignore if testing columns not ready
    }
    ksort($out);
    return array_values($out);
}

function sales_top_agents(string $from, string $to, int $limit = 8): array
{
    $rows = db_all(
        "SELECT u.id, u.name, u.email,
                SUM(CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END) AS reach,
                SUM(CASE WHEN l.status = 'interested' THEN 1 ELSE 0 END) AS interested,
                SUM(CASE WHEN l.status IN ('onboarded','onboarding') THEN 1 ELSE 0 END) AS sales,
                SUM(CASE WHEN l.status = 'onboarded' THEN 1 ELSE 0 END) AS onboarded,
                SUM(CASE WHEN l.status = 'onboarding' THEN 1 ELSE 0 END) AS onboarding,
                SUM(CASE WHEN l.status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN l.status = 'follow_up' THEN 1 ELSE 0 END) AS follow_up,
                (SELECT COUNT(*) FROM companies c
                  WHERE c.testing_mode = 1 AND c.testing_owner_id = u.id
                    AND DATE(c.created_at) >= ? AND DATE(c.created_at) <= ?) AS on_test
         FROM users u
         LEFT JOIN sales_leads l ON l.agent_id = u.id
              AND DATE(l.created_at) >= ? AND DATE(l.created_at) <= ?
         WHERE u.role = 'sales_agent' AND COALESCE(u.status, 'live') = 'live'
         GROUP BY u.id, u.name, u.email
         ORDER BY interested DESC, sales DESC, reach DESC, u.name
         LIMIT " . (int) $limit,
        'ssss',
        [$from, $to, $from, $to]
    );
    return $rows;
}

function sales_primary_admin_id(): int
{
    $row = db_one("SELECT id FROM users WHERE role = 'platform' AND COALESCE(status,'live') = 'live' ORDER BY id ASC LIMIT 1");
    return (int) ($row['id'] ?? 0);
}

function sales_platform_admin_ids(): array
{
    return array_map(
        static fn ($r) => (int) $r['id'],
        db_all("SELECT id FROM users WHERE role = 'platform' AND COALESCE(status,'live') = 'live' ORDER BY id ASC")
    );
}

function sales_message_send(int $fromId, int $toId, string $body): array
{
    $body = trim($body);
    if ($body === '') {
        return ['ok' => false, 'error' => 'Write a message.'];
    }
    $from = db_one('SELECT id, name, role FROM users WHERE id = ?', 'i', [$fromId]);
    if (!$from) {
        return ['ok' => false, 'error' => 'Sender not found.'];
    }
    // Agents always message the shared Vellisys admin inbox (primary platform account).
    if (($from['role'] ?? '') === 'sales_agent') {
        $primary = sales_primary_admin_id();
        if ($primary < 1) {
            return ['ok' => false, 'error' => 'No super admin mailbox is ready yet.'];
        }
        $toId = $primary;
    }
    if ($fromId === $toId) {
        return ['ok' => false, 'error' => 'Pick someone else to message.'];
    }
    $id = db_exec(
        'INSERT INTO sales_messages (from_user_id, to_user_id, body) VALUES (?,?,?)',
        'iis',
        [$fromId, $toId, mb_substr($body, 0, 2000)]
    );
    $row = [
        'id' => (int) $id,
        'from_user_id' => $fromId,
        'to_user_id' => $toId,
        'from_name' => (string) ($from['name'] ?? 'Sender'),
        'body' => mb_substr($body, 0, 2000),
    ];
    sales_notify_message($row);
    return ['ok' => true, 'id' => (int) $id, 'to_user_id' => $toId];
}

function sales_notify_message(array $row): void
{
    $fromId = (int) ($row['from_user_id'] ?? 0);
    $toId = (int) ($row['to_user_id'] ?? 0);
    $fromName = trim((string) ($row['from_name'] ?? 'Sender')) ?: 'Sender';
    $body = (string) ($row['body'] ?? '');
    $from = db_one('SELECT role FROM users WHERE id = ?', 'i', [$fromId]);
    $fromRole = (string) ($from['role'] ?? '');

    if ($fromRole === 'sales_agent') {
        $href = url('admin_sales.php?tab=messages&with=' . $fromId);
        if (function_exists('platform_alert_add')) {
            platform_alert_add(
                'sales_message',
                'Sales message · ' . $fromName,
                clip_text($body, 90),
                $href,
                '',
                'normal'
            );
        }
        // Push / bell for every super admin (shared platform dashboard).
        if (function_exists('push_notify_item')) {
            push_notify_item([
                'title' => 'Sales · ' . $fromName,
                'meta' => clip_text($body, 80),
                'href' => $href,
                'key' => 'sales-msg:' . (int) ($row['id'] ?? 0),
            ], 'platform');
        }
        return;
    }

    // Admin → agent
    $href = url('sales_messages.php');
    if (function_exists('push_notify_item')) {
        push_notify_item([
            'title' => 'Message from Vellisys admin',
            'meta' => clip_text($body, 80),
            'href' => $href,
            'key' => 'sales-msg:' . (int) ($row['id'] ?? 0),
        ], 'user:' . $toId);
    }
}

function sales_messages_for(int $userId, ?int $withId = null, int $limit = 80): array
{
    if ($withId) {
        $me = db_one('SELECT role FROM users WHERE id = ?', 'i', [$userId]);
        // Super admin: show the whole agent thread with any platform admin.
        if (($me['role'] ?? '') === 'platform') {
            return sales_messages_agent_thread($withId, $limit);
        }
        return db_all(
            'SELECT m.*, f.name AS from_name, t.name AS to_name
             FROM sales_messages m
             JOIN users f ON f.id = m.from_user_id
             JOIN users t ON t.id = m.to_user_id
             WHERE (m.from_user_id = ? AND m.to_user_id = ?) OR (m.from_user_id = ? AND m.to_user_id = ?)
             ORDER BY m.id DESC LIMIT ' . (int) $limit,
            'iiii',
            [$userId, $withId, $withId, $userId]
        );
    }
    return db_all(
        'SELECT m.*, f.name AS from_name, t.name AS to_name
         FROM sales_messages m
         JOIN users f ON f.id = m.from_user_id
         JOIN users t ON t.id = m.to_user_id
         WHERE m.from_user_id = ? OR m.to_user_id = ?
         ORDER BY m.id DESC LIMIT ' . (int) $limit,
        'ii',
        [$userId, $userId]
    );
}

/** All messages between one sales agent and any platform admin. */
function sales_messages_agent_thread(int $agentId, int $limit = 80): array
{
    return db_all(
        "SELECT m.*, f.name AS from_name, t.name AS to_name
         FROM sales_messages m
         JOIN users f ON f.id = m.from_user_id
         JOIN users t ON t.id = m.to_user_id
         WHERE (m.from_user_id = ? OR m.to_user_id = ?)
           AND (f.role IN ('platform','sales_agent') AND t.role IN ('platform','sales_agent'))
         ORDER BY m.id DESC LIMIT " . (int) $limit,
        'ii',
        [$agentId, $agentId]
    );
}

function sales_messages_mark_read(int $userId, ?int $fromId = null): void
{
    $me = db_one('SELECT role FROM users WHERE id = ?', 'i', [$userId]);
    if (($me['role'] ?? '') === 'platform' && $fromId) {
        // Any super admin opening the thread clears unread for all admins from that agent.
        $adminIds = sales_platform_admin_ids();
        if ($adminIds) {
            $place = implode(',', array_fill(0, count($adminIds), '?'));
            $types = str_repeat('i', count($adminIds) + 1);
            $params = array_merge([$fromId], $adminIds);
            db_exec(
                "UPDATE sales_messages SET read_at = NOW()
                 WHERE from_user_id = ? AND to_user_id IN ({$place}) AND read_at IS NULL",
                $types,
                $params
            );
        }
        return;
    }
    if ($fromId) {
        db_exec('UPDATE sales_messages SET read_at = NOW() WHERE to_user_id = ? AND from_user_id = ? AND read_at IS NULL', 'ii', [$userId, $fromId]);
        return;
    }
    db_exec('UPDATE sales_messages SET read_at = NOW() WHERE to_user_id = ? AND read_at IS NULL', 'i', [$userId]);
}

function sales_unread_count(int $userId): int
{
    return (int) (db_one('SELECT COUNT(*) c FROM sales_messages WHERE to_user_id = ? AND read_at IS NULL', 'i', [$userId])['c'] ?? 0);
}

function sales_admin_unread_count(int $platformUserId = 0): int
{
    // Count all unread agent → any platform admin messages so every super admin sees the badge.
    return (int) (db_one(
        "SELECT COUNT(*) c FROM sales_messages m
         JOIN users f ON f.id = m.from_user_id AND f.role = 'sales_agent'
         JOIN users t ON t.id = m.to_user_id AND t.role = 'platform'
         WHERE m.read_at IS NULL"
    )['c'] ?? 0);
}

function sales_agent_unread_from(int $agentId): int
{
    return (int) (db_one(
        "SELECT COUNT(*) c FROM sales_messages m
         JOIN users t ON t.id = m.to_user_id AND t.role = 'platform'
         WHERE m.from_user_id = ? AND m.read_at IS NULL",
        'i',
        [$agentId]
    )['c'] ?? 0);
}

function sales_avatar_url(?array $user): string
{
    $rel = ltrim((string) ($user['avatar_path'] ?? ''), '/');
    if ($rel === '' || !is_file(ROOT_PATH . '/' . $rel)) {
        return '';
    }
    return url($rel) . '?v=' . filemtime(ROOT_PATH . '/' . $rel);
}

function sales_save_avatar(int $userId, string $field = 'avatar'): array
{
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return ['ok' => true, 'path' => ''];
    }
    $ext = strtolower(pathinfo((string) ($_FILES[$field]['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
        return ['ok' => false, 'error' => 'Photo must be PNG, JPG, GIF or WebP.'];
    }
    if ((int) ($_FILES[$field]['size'] ?? 0) > 2_000_000) {
        return ['ok' => false, 'error' => 'Photo must be under 2 MB.'];
    }
    $dir = ROOT_PATH . '/uploads/avatars';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Could not prepare the photo folder.'];
    }
    $fname = 'u' . $userId . '-' . bin2hex(random_bytes(4)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $fname)) {
        return ['ok' => false, 'error' => 'Could not save the photo.'];
    }
    $rel = 'uploads/avatars/' . $fname;
    $old = db_one('SELECT avatar_path FROM users WHERE id = ?', 'i', [$userId]);
    db_exec('UPDATE users SET avatar_path = ? WHERE id = ?', 'si', [$rel, $userId]);
    $oldRel = ltrim((string) ($old['avatar_path'] ?? ''), '/');
    if ($oldRel !== '' && is_file(ROOT_PATH . '/' . $oldRel) && str_contains($oldRel, 'uploads/avatars/')) {
        @unlink(ROOT_PATH . '/' . $oldRel);
    }
    return ['ok' => true, 'path' => $rel];
}

function sales_change_password(int $userId, string $current, string $next, string $again): array
{
    $row = db_one('SELECT password_hash FROM users WHERE id = ?', 'i', [$userId]);
    if (!$row || !password_verify($current, (string) $row['password_hash'])) {
        return ['ok' => false, 'error' => 'Current password is not correct.'];
    }
    if (strlen($next) < 8) {
        return ['ok' => false, 'error' => 'New password must be at least 8 characters.'];
    }
    if ($next !== $again) {
        return ['ok' => false, 'error' => 'The two new passwords do not match.'];
    }
    db_exec('UPDATE users SET password_hash = ? WHERE id = ?', 'si', [password_hash($next, PASSWORD_DEFAULT), $userId]);
    return ['ok' => true];
}

function sales_followups_due(?int $agentId = null, int $withinDays = 1): array
{
    $to = date('Y-m-d', strtotime('+' . max(0, $withinDays) . ' days'));
    $where = "l.status = 'follow_up' AND l.deleted_at IS NULL AND l.follow_up_done_at IS NULL AND l.follow_up_date IS NOT NULL AND l.follow_up_date <= ?";
    $types = 's';
    $params = [$to];
    if ($agentId) {
        $where .= ' AND l.agent_id = ?';
        $types .= 'i';
        $params[] = $agentId;
    }
    return db_all(
        "SELECT l.*, u.name AS agent_name FROM sales_leads l LEFT JOIN users u ON u.id = l.agent_id WHERE {$where} ORDER BY l.follow_up_date, l.follow_up_time IS NULL, l.follow_up_time, l.id",
        $types,
        $params
    );
}

/** All open follow-ups for an agent (or everyone), soonest first. */
function sales_followups_open(?int $agentId = null, int $limit = 80): array
{
    $where = "l.status = 'follow_up' AND l.deleted_at IS NULL AND l.follow_up_done_at IS NULL AND l.follow_up_date IS NOT NULL";
    $types = '';
    $params = [];
    if ($agentId) {
        $where .= ' AND l.agent_id = ?';
        $types .= 'i';
        $params[] = $agentId;
    }
    $sql = "SELECT l.*, u.name AS agent_name
            FROM sales_leads l
            LEFT JOIN users u ON u.id = l.agent_id
            WHERE {$where}
            ORDER BY l.interest_rating DESC, l.follow_up_date ASC, l.follow_up_time IS NULL, l.follow_up_time ASC, l.id ASC";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . max(1, min(200, $limit));
    }
    return $types !== '' ? db_all($sql, $types, $params) : db_all($sql);
}

/** Contact block for a sales lead (call / WhatsApp). */
function sales_render_lead_contact(array $lead, array $opts = []): void
{
    $name = trim((string) ($lead['contact_name'] ?? ''));
    $phone = trim((string) ($lead['contact_phone'] ?? ''));
    $city = trim((string) ($lead['city'] ?? ''));
    $address = trim((string) ($lead['address'] ?? ''));
    $business = trim((string) ($lead['business_name'] ?? ''));
    $compact = !empty($opts['compact']);
    $tel = $phone !== '' && function_exists('phone_tel_href') ? phone_tel_href($phone) : '';
    $wa = $phone !== '' && function_exists('phone_whatsapp_href')
        ? phone_whatsapp_href($phone, $business !== '' ? ('Hi, following up about ' . $business) : 'Hi')
        : '';
    ?>
  <div class="lead-contact<?= $compact ? ' is-compact' : '' ?>">
    <?php if (!$compact): ?>
      <h2 style="margin-top:0"><?= icon('user', 16) ?>Contact</h2>
    <?php endif; ?>
    <dl class="party-brief">
      <?php if ($name !== ''): ?><div><dt>Person</dt><dd><?= h($name) ?></dd></div><?php endif; ?>
      <?php if ($phone !== ''): ?>
        <div>
          <dt>Phone</dt>
          <dd>
            <?php if ($tel !== ''): ?>
              <a href="<?= h($tel) ?>"><?= h($phone) ?></a>
            <?php else: ?>
              <?= h($phone) ?>
            <?php endif; ?>
          </dd>
        </div>
      <?php endif; ?>
      <?php if ($city !== ''): ?><div><dt>City</dt><dd><?= h($city) ?></dd></div><?php endif; ?>
      <?php if ($address !== '' && !$compact): ?><div class="party-brief-wide"><dt>Address</dt><dd><?= h($address) ?></dd></div><?php endif; ?>
    </dl>
    <?php if ($phone !== ''): ?>
      <div class="actions wrap-actions" style="margin-top:10px">
        <?php if ($tel !== ''): ?>
          <a class="btn" href="<?= h($tel) ?>"><?= icon('phone', 14) ?>Call</a>
        <?php endif; ?>
        <?php if ($wa !== ''): ?>
          <a class="btn ghost" href="<?= h($wa) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 14) ?>WhatsApp</a>
        <?php endif; ?>
      </div>
    <?php elseif (!$compact): ?>
      <p class="hint" style="margin:8px 0 0">Add a phone number below so you can call this follow-up.</p>
    <?php endif; ?>
  </div>
    <?php
}

function sales_vault_key(): string
{
    $secret = getenv('FOLIO_VAULT_KEY') ?: (defined('ROOT_PATH') ? ROOT_PATH . '|vellisys-vault' : 'vellisys-vault');
    return hash('sha256', $secret, true);
}

function sales_vault_encrypt(string $plain): string
{
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', sales_vault_key(), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}

function sales_vault_decrypt(string $blob): string
{
    $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) < 17) {
        return '';
    }
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', sales_vault_key(), OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}

function sales_vault_list(string $q = ''): array
{
    if ($q !== '') {
        $like = '%' . $q . '%';
        return db_all(
            'SELECT * FROM sales_vault WHERE company_name LIKE ? OR email LIKE ? OR notes LIKE ? ORDER BY updated_at DESC, id DESC',
            'sss',
            [$like, $like, $like]
        );
    }
    return db_all('SELECT * FROM sales_vault ORDER BY updated_at DESC, id DESC');
}

function sales_vault_save(array $fields, ?int $id = null): array
{
    $name = mb_substr(trim((string) ($fields['company_name'] ?? '')), 0, 190);
    $email = mb_substr(trim((string) ($fields['email'] ?? '')), 0, 190);
    $password = (string) ($fields['password'] ?? '');
    $notes = mb_substr(trim((string) ($fields['notes'] ?? '')), 0, 1000);
    $companyId = (int) ($fields['company_id'] ?? 0) ?: null;
    if ($name === '' && $email === '') {
        return ['ok' => false, 'error' => 'Enter a company or an email.'];
    }
    $uid = (int) (current_user()['id'] ?? 0);
    if ($id) {
        $row = db_one('SELECT * FROM sales_vault WHERE id = ?', 'i', [$id]);
        if (!$row) {
            return ['ok' => false, 'error' => 'Entry not found.'];
        }
        $enc = $password !== '' ? sales_vault_encrypt($password) : (string) $row['password_enc'];
        db_exec(
            'UPDATE sales_vault SET company_id=?, company_name=?, email=?, password_enc=?, notes=?, updated_at=NOW() WHERE id=?',
            'issssi',
            [$companyId, $name, $email, $enc, $notes, $id]
        );
        return ['ok' => true, 'id' => $id];
    }
    if ($password === '') {
        return ['ok' => false, 'error' => 'Enter the password to store.'];
    }
    $newId = db_exec(
        'INSERT INTO sales_vault (company_id, company_name, email, password_enc, notes, created_by) VALUES (?,?,?,?,?,?)',
        'issssi',
        [$companyId, $name, $email, sales_vault_encrypt($password), $notes, $uid]
    );
    return ['ok' => true, 'id' => (int) $newId];
}

function sales_vault_delete(int $id): array
{
    db_exec('DELETE FROM sales_vault WHERE id = ?', 'i', [$id]);
    return ['ok' => true];
}

function sales_notifications_for_agent(int $userId): array
{
    $notes = [];
    $unreadMsgs = sales_messages_for($userId, null, 12);
    $seenUnread = 0;
    foreach ($unreadMsgs as $m) {
        if ((int) $m['to_user_id'] !== $userId || !empty($m['read_at'])) {
            continue;
        }
        $seenUnread++;
        $notes[] = [
            'type' => 'message',
            'key' => 'sales-msg-item-' . (int) $m['id'],
            'title' => 'Message from ' . (trim((string) ($m['from_name'] ?? 'admin')) ?: 'admin'),
            'meta' => clip_text((string) ($m['body'] ?? ''), 80),
            'href' => url('sales_messages.php'),
            'tone' => 'info',
        ];
        if ($seenUnread >= 5) {
            break;
        }
    }
    foreach (sales_followups_due($userId, 1) as $lead) {
        $when = (string) ($lead['follow_up_date'] ?? '');
        $label = $when === today() ? 'today' : 'tomorrow';
        $timeLabel = sales_format_follow_up_time($lead['follow_up_time'] ?? null);
        $notes[] = [
            'type' => 'follow_up',
            'key' => 'sales-fu-' . (int) $lead['id'] . '-' . $when,
            'title' => 'Follow up: ' . (trim((string) $lead['business_name']) ?: 'Business'),
            'meta' => 'Due ' . $label
                . ($timeLabel !== '' ? ' · ' . $timeLabel : '')
                . ($lead['city'] ? ' · ' . $lead['city'] : ''),
            'href' => url('sales_lead_edit.php?id=' . (int) $lead['id']),
            'tone' => 'warn',
        ];
    }
    if (!sales_is_clocked_in($userId)) {
        $notes[] = [
            'type' => 'clock',
            'key' => 'sales-clock-' . today(),
            'title' => 'Clock in to add a lead',
            'meta' => 'Only needed when logging a new visit',
            'href' => url(sales_home()),
            'tone' => 'info',
        ];
    }
    return $notes;
}

function sales_demo_credentials(): array
{
    return [
        'email' => 'demo@vellisys.ug',
        'password' => 'demo-sales-2026',
        'name' => 'Vellisys Sales Demo',
    ];
}

function sales_demo_user(): ?array
{
    if (function_exists('folio_ensure_sales_demo')) {
        try {
            folio_ensure_sales_demo(db());
        } catch (Throwable $e) {
            // ignore
        }
    }
    return db_one("SELECT * FROM users WHERE email = ? AND role = 'admin'", 's', ['demo@vellisys.ug']);
}

function sales_demo_company_id(): int
{
    $u = sales_demo_user();
    return $u ? (int) ($u['company_id'] ?? 0) : 0;
}

function sales_demo_enter_from(?array $fromUser = null): array
{
    $fromUser = $fromUser ?? current_user();
    if (!$fromUser) {
        return ['ok' => false, 'error' => 'Sign in first.'];
    }
    $demo = sales_demo_user();
    if (!$demo || (int) ($demo['company_id'] ?? 0) < 1) {
        return ['ok' => false, 'error' => 'Demo desk is not ready yet.'];
    }
    $_SESSION['sales_demo_return'] = [
        'user_id' => (int) $fromUser['id'],
        'company_id' => (int) ($fromUser['company_id'] ?? 0),
        'role' => (string) ($fromUser['role'] ?? ''),
        'acting_company_id' => (int) ($_SESSION['acting_company_id'] ?? 0),
    ];
    unset($_SESSION['acting_company_id']);
    $_SESSION['user_id'] = (int) $demo['id'];
    $_SESSION['company_id'] = (int) $demo['company_id'];
    $_SESSION['role'] = (string) ($demo['role'] ?? 'admin');
    unset($_SESSION['desk_welcome']);
    // Keep the walkthrough clear of the first-login modal (covers charts / bottom bar).
    if (function_exists('mark_desk_welcome_seen')) {
        mark_desk_welcome_seen((int) $demo['id']);
    }
    if (function_exists('branding')) {
        try {
            branding(true);
        } catch (Throwable $e) {
            // ignore
        }
    }
    return ['ok' => true, 'company_id' => (int) $demo['company_id']];
}

function sales_demo_leave(): array
{
    $ret = $_SESSION['sales_demo_return'] ?? null;
    unset($_SESSION['sales_demo_return']);
    if (!is_array($ret) || (int) ($ret['user_id'] ?? 0) < 1) {
        return ['ok' => false, 'error' => 'No demo session to leave.'];
    }
    $_SESSION['user_id'] = (int) $ret['user_id'];
    $_SESSION['company_id'] = (int) ($ret['company_id'] ?? 0);
    $_SESSION['role'] = (string) ($ret['role'] ?? '');
    $acting = (int) ($ret['acting_company_id'] ?? 0);
    if ($acting > 0) {
        $_SESSION['acting_company_id'] = $acting;
        $_SESSION['company_id'] = $acting;
    } else {
        unset($_SESSION['acting_company_id']);
    }
    return ['ok' => true, 'role' => (string) ($ret['role'] ?? '')];
}

function sales_demo_active(): bool
{
    return !empty($_SESSION['sales_demo_return']) && is_array($_SESSION['sales_demo_return']);
}

function sales_demo_return_home(): string
{
    $role = (string) (($_SESSION['sales_demo_return']['role'] ?? '') ?: '');
    if ($role === 'platform') {
        return platform_home();
    }
    if ($role === 'sales_agent') {
        return sales_home();
    }
    return 'dashboard.php';
}

function platform_alert_add(string $kind, string $title, string $meta = '', string $href = '', string $email = '', string $urgency = 'normal'): void
{
    try {
        db_exec(
            'INSERT INTO platform_alerts (kind, urgency, title, meta, href, ref_email) VALUES (?,?,?,?,?,?)',
            'ssssss',
            [$kind, $urgency, mb_substr($title, 0, 190), mb_substr($meta, 0, 255), mb_substr($href, 0, 255), mb_substr(strtolower($email), 0, 190)]
        );
    } catch (Throwable $e) {
        // ignore if migrate not yet applied
    }
}

function platform_alerts_unread(int $limit = 20): array
{
    try {
        return db_all('SELECT * FROM platform_alerts ORDER BY (urgency = \'urgent\') DESC, id DESC LIMIT ' . (int) $limit);
    } catch (Throwable $e) {
        return [];
    }
}

function sales_layout_start(string $title, array $user): void
{
    if (function_exists('touch_user_seen')) {
        touch_user_seen((int) $user['id']);
    }
    $flash = flash();
    $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $unread = sales_unread_count((int) $user['id']);
    $openFollowN = sales_open_followups_count((int) $user['id']);
    $notes = sales_notifications_for_agent((int) $user['id']);
    $noteCount = count($notes);
    $avatar = sales_avatar_url($user);
    $leadsBucket = (string) ($_GET['bucket'] ?? '');
    // Grouped field nav: work → companies → chat → results → tools → account.
    $navGroups = [
        ['label' => 'Work', 'items' => [
            ['sales_home.php', 'Home', 'home'],
            ['sales_leads.php', 'Leads', 'clients'],
            ['sales_leads.php?bucket=pending', 'Open follow-ups', 'calendar', false, 'followups'],
            ['sales_testing.php', 'On Testing', 'building'],
        ]],
        ['label' => 'Chat', 'items' => [
            ['sales_messages.php', 'Messages', 'mail'],
        ]],
        ['label' => 'Results', 'items' => [
            ['sales_performance.php', 'Performance', 'reports'],
        ]],
        ['label' => 'Tools', 'items' => [
            ['sales_demo.php', 'Demo', 'desk'],
            // Same-origin marketing home - session stays; index.php allows sales_agent viewers.
            ['index.php', 'Website', 'globe'],
        ]],
        ['label' => 'Account', 'items' => [
            ['sales_profile.php', 'Profile', 'user'],
        ]],
    ];
    ?>
<!DOCTYPE html>
<html lang="en"<?= function_exists('folio_html_root_attrs') ? folio_html_root_attrs() : '' ?>>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= h($title) ?> · Sales · <?= h(product_name()) ?></title>
  <?php product_icons(); ?>
  <?php folio_css_links(); ?>
  <?php folio_font_links(); ?>
  <style>:root { <?= product_css_vars() ?> }</style>
  <?php render_nav_boot_script(); ?>
</head>
<body class="desk-body sales-body<?= $here === 'sales_lead_edit.php' ? ' is-lead-edit' : '' ?>">
<?php render_page_loader(); ?>
<div class="app">
  <div class="nav-scrim" data-nav-scrim hidden></div>
  <button type="button" class="bell-scrim" data-bell-scrim hidden aria-label="Close notifications"></button>
  <aside class="nav" data-nav>
    <a class="brand" href="<?= h(url(sales_home())) ?>">
      <img class="brand-logo" src="<?= h(product_mark_url()) ?>" alt="<?= h(product_name()) ?>">
      <strong>Sales field</strong>
    </a>
    <nav>
      <?php foreach ($navGroups as $group): ?>
        <div class="nav-group">
          <?php if (($group['label'] ?? '') !== ''): ?>
            <p class="nav-group-label"><?= h((string) $group['label']) ?></p>
          <?php endif; ?>
          <?php foreach ($group['items'] as $item):
              [$href, $label, $iconName] = $item;
              $external = !empty($item[3]);
              $badgeKind = (string) ($item[4] ?? '');
              $file = $external ? '' : (string) strtok($href, '?');
              $isFollowNav = !$external && str_contains($href, 'bucket=pending');
              $active = false;
              if (!$external) {
                  if ($isFollowNav) {
                      $active = $here === 'sales_leads.php' && $leadsBucket === 'pending';
                  } elseif ($file === 'sales_leads.php') {
                      $active = ($here === 'sales_leads.php' && $leadsBucket !== 'pending')
                          || $here === 'sales_lead_edit.php';
                  } else {
                      $active = $file === $here
                          || (in_array($here, ['sales_company.php', 'sales_company_new.php', 'sales_desk.php', 'sales_companies.php'], true) && $file === 'sales_testing.php');
                  }
              }
              $badge = 0;
              $badgeDanger = false;
              if (!$external && $file === 'sales_messages.php' && $unread) {
                  $badge = $unread;
              } elseif ($badgeKind === 'followups' && $openFollowN > 0) {
                  $badge = $openFollowN;
                  $badgeDanger = true;
              }
              $linkHref = $external ? $href : url($href);
              ?>
            <a class="<?= $active ? 'is-on' : '' ?>" href="<?= h($linkHref) ?>" title="<?= h($label) ?>"<?= $external ? ' target="_blank" rel="noopener noreferrer"' : '' ?><?= $badge ? ' data-badge="' . (int) $badge . '"' : '' ?><?= $badgeDanger ? ' data-badge-danger' : '' ?>><?= icon($iconName, 18) ?><span><?= h($label) ?></span></a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <a class="nav-sign-out" href="<?= h(url('logout.php')) ?>" title="Sign out"><?= icon('logout', 18) ?><span>Sign out</span></a>
    </nav>
    <div class="nav-user">
      <span class="nav-user-name">
        <?php if ($avatar !== ''): ?>
          <img class="nav-user-avatar" src="<?= h($avatar) ?>" alt="">
        <?php else: ?>
          <?= icon('user', 16) ?>
        <?php endif; ?>
        <span><?= h($user['name']) ?></span>
      </span>
      <span class="nav-user-mail"><?= h($user['email']) ?></span>
      <a href="<?= h(url('sales_profile.php')) ?>" title="Profile"><?= icon('user', 15) ?><span>Profile</span></a>
      <a class="nav-sign-out" href="<?= h(url('logout.php')) ?>" title="Sign out"><?= icon('logout', 15) ?><span>Sign out</span></a>
    </div>
  </aside>
  <div class="main">
    <header class="top">
      <button class="nav-toggle" type="button" data-nav-toggle aria-label="Menu" aria-expanded="false"><?= icon('menu', 20) ?></button>
      <div class="top-meta">
        <?php if (function_exists('render_top_clock')) { render_top_clock(); } ?>
      </div>
      <div class="top-actions">
        <details class="top-bell">
          <summary class="header-settings<?= $noteCount ? ' has-badge' : '' ?>" title="Notifications" aria-label="Notifications">
            <?= icon('bell', 20) ?>
            <?php if ($noteCount): ?><span class="top-bell-count"><?= $noteCount > 9 ? '9+' : $noteCount ?></span><?php endif; ?>
          </summary>
          <div class="top-bell-panel">
            <strong>Coming up</strong>
            <?php if (!$notes): ?>
              <p class="muted">Nothing waiting right now.</p>
            <?php else: ?>
              <ul>
                <?php foreach ($notes as $n): ?>
                  <li class="top-bell-item">
                    <div class="top-bell-main">
                      <a href="<?= h($n['href']) ?>">
                        <span class="top-bell-title"><?= h($n['title']) ?></span>
                        <span class="top-bell-meta"><?= h($n['meta']) ?></span>
                      </a>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </details>
        <a class="btn" href="<?= h(url('sales_lead_edit.php')) ?>"><?= icon('plus', 16) ?>New lead</a>
      </div>
    </header>
    <?php if ($flash): ?>
      <div class="flash flash-<?= h($flash['type']) ?>"><?= $flash['type'] === 'ok' ? icon('check', 16) : icon('alert', 16) ?><?= h($flash['text']) ?></div>
    <?php endif; ?>
    <div class="content">
<?php
}

function sales_layout_end(string $extra = ''): void
{
    ?>
    </div>
  </div>
</div>
<nav class="app-tabbar sales-tabbar" aria-label="Sales" data-app-tabbar style="position:fixed;left:0;right:0;bottom:0;top:auto;width:100%;z-index:9999;margin:0">
  <a class="app-tab<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'sales_home.php' ? ' is-on' : '' ?>" href="<?= h(url('sales_home.php')) ?>"><?= icon('home', 22) ?><span>Home</span></a>
  <a class="app-tab<?= in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['sales_leads.php', 'sales_lead_edit.php'], true) ? ' is-on' : '' ?>" href="<?= h(url('sales_leads.php')) ?>"><?= icon('clients', 22) ?><span>Leads</span></a>
  <a class="app-tab<?= in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['sales_testing.php', 'sales_companies.php', 'sales_company.php', 'sales_company_new.php', 'sales_desk.php'], true) ? ' is-on' : '' ?>" href="<?= h(url('sales_testing.php')) ?>"><?= icon('building', 22) ?><span>Testing</span></a>
  <a class="app-tab app-tab-create" href="<?= h(url('sales_lead_edit.php')) ?>"><span class="app-tab-plus"><?= icon('plus', 26) ?></span><span>Lead</span></a>
  <a class="app-tab<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'sales_messages.php' ? ' is-on' : '' ?>" href="<?= h(url('sales_messages.php')) ?>"><?= icon('mail', 22) ?><span>Chat</span></a>
</nav>
<script src="<?= h(asset('js/app.js')) ?>" defer></script>
<?= $extra ?>
</body>
</html>
<?php
}
