<?php
declare(strict_types=1);

/** Field sales agents (Vellisys staff portal — not desk till access). */

function sales_statuses(): array
{
    return [
        'interested' => 'Interested',
        'follow_up' => 'Follow up',
        'rejected' => 'Rejected',
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

function sales_agent_save(array $fields, ?int $id = null): array
{
    $name = mb_substr(trim((string) ($fields['name'] ?? '')), 0, 120);
    $email = strtolower(mb_substr(trim((string) ($fields['email'] ?? '')), 0, 190));
    $phone = mb_substr(trim((string) ($fields['phone'] ?? '')), 0, 40);
    $job = mb_substr(trim((string) ($fields['job_title'] ?? 'Sales agent')), 0, 80) ?: 'Sales agent';
    $password = (string) ($fields['password'] ?? '');
    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a name and a working email.'];
    }
    $dup = db_one('SELECT id FROM users WHERE email = ? AND id <> ?', 'si', [$email, (int) ($id ?? 0)]);
    if ($dup) {
        return ['ok' => false, 'error' => 'That email already has a login.'];
    }
    if ($id) {
        $row = sales_agent($id);
        if (!$row) {
            return ['ok' => false, 'error' => 'Sales agent not found.'];
        }
        db_exec(
            "UPDATE users SET name=?, email=?, job_title=?, phone=? WHERE id=? AND role='sales_agent'",
            'ssssi',
            [$name, $email, $job, $phone, $id]
        );
        if ($password !== '') {
            db_exec('UPDATE users SET password_hash=? WHERE id=?', 'si', [password_hash($password, PASSWORD_DEFAULT), $id]);
        }
        return ['ok' => true, 'id' => $id];
    }
    if ($password === '') {
        $password = function_exists('generate_desk_password') ? generate_desk_password() : ('Vs-' . bin2hex(random_bytes(4)));
    }
    $newId = db_exec(
        "INSERT INTO users (name, job_title, email, password_hash, role, access, company_id, status, phone) VALUES (?,?,?,?, 'sales_agent', 'sales', NULL, 'live', ?)",
        'sssss',
        [$name, $job, $email, password_hash($password, PASSWORD_DEFAULT), $phone]
    );
    return ['ok' => true, 'id' => (int) $newId, 'password' => $password];
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
    return (bool) sales_today_clock($userId);
}

function sales_clock_in(int $userId, string $city, string $notes = ''): array
{
    if (sales_today_clock($userId)) {
        return ['ok' => true, 'already' => true];
    }
    $city = mb_substr(trim($city), 0, 120);
    if ($city === '') {
        return ['ok' => false, 'error' => 'Enter the city or area where you are.'];
    }
    $id = db_exec(
        'INSERT INTO sales_clock_ins (user_id, day_date, clocked_at, location_city, notes) VALUES (?,?,NOW(),?,?)',
        'isss',
        [$userId, today(), $city, mb_substr(trim($notes), 0, 500)]
    );
    return ['ok' => true, 'id' => (int) $id];
}

function sales_require_clock_in(): void
{
    if (!sales_is_clocked_in()) {
        flash('Clock in first. Enter where you are today.', 'err');
        redirect(sales_home());
    }
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
    $notes = mb_substr(trim((string) ($fields['notes'] ?? '')), 0, 2000);
    $nature = '';
    $package = '';
    $onboardDate = null;
    $followDate = null;
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
        $interest = max(0, min(5, (int) ($fields['interest_rating'] ?? 0)));
        if ($interest < 1) {
            return ['ok' => false, 'error' => 'Rate their interest from 1 to 5.'];
        }
    } elseif ($status === 'rejected') {
        $rejectedCat = trim((string) ($fields['rejected_category'] ?? ''));
        if (!isset(sales_reject_reasons()[$rejectedCat])) {
            return ['ok' => false, 'error' => 'Pick a rejection reason from the list.'];
        }
        $rejected = mb_substr(trim((string) ($fields['rejected_reason'] ?? '')), 0, 500);
        if ($rejected === '') {
            return ['ok' => false, 'error' => 'Explain the rejection below the dropdown.'];
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
        $from = (string) $row['status'];
        $followDone = $row['follow_up_done_at'] ?? null;
        if ($from === 'follow_up' && $status !== 'follow_up') {
            $followDone = date('Y-m-d H:i:s');
        }
        if ($status === 'follow_up' && $followDate !== ($row['follow_up_date'] ?? null)) {
            $followDone = null;
        }
        db_exec(
            'UPDATE sales_leads SET status=?, business_name=?, address=?, contact_name=?, contact_phone=?, city=?,
             nature_of_business=?, package_chosen=?, onboard_date=?, follow_up_date=?, follow_up_done_at=?, interest_rating=?,
             rejected_reason=?, rejected_category=?, notes=?, updated_at=NOW()
             WHERE id=?',
            'sssssssssssisssi',
            [$status, $business, $address, $contactName, $contactPhone, $city, $nature, $package, $onboardDate, $followDate, $followDone, $interest, $rejected, $rejectedCat, $notes, $id]
        );
        if ($from !== $status) {
            sales_lead_event($id, $agentId, 'status_change', $from, $status, $notes);
        } else {
            sales_lead_event($id, $agentId, 'update', $from, $status, $notes);
        }
        return ['ok' => true, 'id' => $id];
    }

    $newId = db_exec(
        'INSERT INTO sales_leads (agent_id, status, business_name, address, contact_name, contact_phone, city,
         nature_of_business, package_chosen, onboard_date, follow_up_date, interest_rating, rejected_reason, rejected_category, notes)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        'issssssssssisss',
        [$agentId, $status, $business, $address, $contactName, $contactPhone, $city, $nature, $package, $onboardDate, $followDate, $interest, $rejected, $rejectedCat, $notes]
    );
    sales_lead_event((int) $newId, $agentId, 'create', null, $status, $notes);
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
    if (!$row || (string) $row['status'] !== 'interested') {
        return ['ok' => false, 'error' => 'Only interested leads can be onboarded.'];
    }
    db_exec(
        "UPDATE sales_leads SET status='onboarded', signup_id=?, company_id=?, follow_up_done_at=COALESCE(follow_up_done_at, NOW()), updated_at=NOW() WHERE id=?",
        'iii',
        [$signupId, $companyId, $id]
    );
    sales_lead_event($id, (int) (current_user()['id'] ?? 0), 'onboarded', 'interested', 'onboarded', 'Sent to onboarding');
    return ['ok' => true];
}

function sales_leads_query(array $opts = []): array
{
    $where = ['1=1'];
    $types = '';
    $params = [];
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
    $sql = 'SELECT l.*, u.name AS agent_name FROM sales_leads l LEFT JOIN users u ON u.id = l.agent_id WHERE '
        . implode(' AND ', $where) . ' ORDER BY l.updated_at DESC, l.id DESC';
    if (!empty($opts['limit'])) {
        $sql .= ' LIMIT ' . (int) $opts['limit'];
    }
    return db_all($sql, $types, $params);
}

function sales_period_bounds(): array
{
    $p = period_range();
    $from = $p['from'] !== '' ? $p['from'] : '1970-01-01';
    $to = $p['to'] !== '' ? $p['to'] : today();
    return [$from, $to, $p];
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
    $by = ['interested' => 0, 'follow_up' => 0, 'rejected' => 0, 'onboarded' => 0];
    foreach ($rows as $r) {
        $by[(string) $r['status']] = (int) $r['n'];
    }
    $reach = array_sum($by);
    $wins = $by['onboarded'] + $by['interested'];
    return [
        'by_status' => $by,
        'reach' => $reach,
        'wins' => $wins,
        'sales' => $wins,
        'interested' => $by['interested'],
        'follow_up' => $by['follow_up'],
        'rejected' => $by['rejected'],
        'onboarded' => $by['onboarded'],
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
    $wins = (int) $stats['wins'];
    return [
        'kind' => $kind,
        'from' => $from,
        'to' => $to,
        'stat_to' => $statTo,
        'reach' => (int) $stats['reach'],
        'reach_goal' => (int) $goals['reach'],
        'sales' => $wins,
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
        if ($status === 'follow_up' && $followDate !== ($row['follow_up_date'] ?? null)) {
            $followDone = null;
        }
        if ($status === 'onboarded' && $from !== 'onboarded') {
            $followDone = $followDone ?: date('Y-m-d H:i:s');
        }
        db_exec(
            'UPDATE sales_leads SET agent_id=?, status=?, business_name=?, address=?, contact_name=?, contact_phone=?, city=?,
             nature_of_business=?, package_chosen=?, onboard_date=?, follow_up_date=?, follow_up_done_at=?, interest_rating=?,
             rejected_reason=?, rejected_category=?, notes=?, deleted_at=NULL, updated_at=NOW() WHERE id=?',
            'isssssssssssisssi',
            [$agentId, $status, $business, $address, $contactName, $contactPhone, $city, $nature, $package, $onboardDate, $followDate, $followDone, $interest, $rejected, $rejectedCat, $notes, $id]
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
         nature_of_business, package_chosen, onboard_date, follow_up_date, interest_rating, rejected_reason, rejected_category, notes)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        'issssssssssisss',
        [$agentId, $status, $business, $address, $contactName, $contactPhone, $city, $nature, $package, $onboardDate, $followDate, $interest, $rejected, $rejectedCat, $notes]
    );
    sales_lead_event((int) $newId, $actor ?: $agentId, 'create', null, $status, $notes);
    return ['ok' => true, 'id' => (int) $newId];
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
    $reachPct = sales_progress_pct($reach, max(1, $reachGoal));
    $salesPct = sales_progress_pct($sales, max(1, $salesGoal));
    $reachHit = $reachGoal > 0 && $reach >= $reachGoal;
    $salesHit = $salesGoal > 0 && $sales >= $salesGoal;
    $reachFill = min(100, $reachPct);
    $salesFill = min(100, $salesPct);
    ?>
<div class="sales-goal-bars<?= $compact ? ' is-compact' : '' ?>">
  <?php if ($showReach): ?>
    <div class="sales-goal-row<?= $reachHit ? ' is-hit' : '' ?>">
      <div class="sales-goal-meta">
        <span>Leads reached</span>
        <strong class="mono"><?= $reach ?>/<?= $reachGoal ?: '—' ?></strong>
      </div>
      <div class="sales-goal-track" role="img" aria-label="Leads <?= $reach ?> of <?= $reachGoal ?>">
        <span style="width:<?= $reachFill ?>%"></span>
      </div>
      <?php if ($reachHit && $reach > $reachGoal): ?>
        <span class="sales-goal-over">+<?= $reach - $reachGoal ?> over</span>
      <?php elseif ($reachHit): ?>
        <span class="sales-goal-over">Goal hit</span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($showSales): ?>
    <div class="sales-goal-row<?= $salesHit ? ' is-hit' : '' ?>">
      <div class="sales-goal-meta">
        <span>Sales (interested + onboarded)</span>
        <strong class="mono"><?= $sales ?>/<?= $salesGoal ?: '—' ?></strong>
      </div>
      <div class="sales-goal-track" role="img" aria-label="Sales <?= $sales ?> of <?= $salesGoal ?>">
        <span style="width:<?= $salesFill ?>%"></span>
      </div>
      <?php if ($salesHit && $sales > $salesGoal): ?>
        <span class="sales-goal-over">+<?= $sales - $salesGoal ?> over</span>
      <?php elseif ($salesHit): ?>
        <span class="sales-goal-over">Goal hit</span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
    <?php
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
            $out[$d] = ['date' => $d, 'reach' => 0, 'interested' => 0, 'follow_up' => 0, 'rejected' => 0, 'onboarded' => 0];
        }
    }
    foreach ($rows as $r) {
        $d = (string) $r['d'];
        if (!isset($out[$d])) {
            $out[$d] = ['date' => $d, 'reach' => 0, 'interested' => 0, 'follow_up' => 0, 'rejected' => 0, 'onboarded' => 0];
        }
        $st = (string) $r['status'];
        $n = (int) $r['n'];
        $out[$d]['reach'] += $n;
        if (isset($out[$d][$st])) {
            $out[$d][$st] += $n;
        }
    }
    ksort($out);
    return array_values($out);
}

function sales_top_agents(string $from, string $to, int $limit = 8): array
{
    $rows = db_all(
        "SELECT u.id, u.name, u.email,
                SUM(CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END) AS reach,
                SUM(CASE WHEN l.status IN ('onboarded','interested') THEN 1 ELSE 0 END) AS sales,
                SUM(CASE WHEN l.status = 'onboarded' THEN 1 ELSE 0 END) AS onboarded,
                SUM(CASE WHEN l.status = 'interested' THEN 1 ELSE 0 END) AS interested,
                SUM(CASE WHEN l.status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN l.status = 'follow_up' THEN 1 ELSE 0 END) AS follow_up
         FROM users u
         LEFT JOIN sales_leads l ON l.agent_id = u.id
              AND DATE(l.created_at) >= ? AND DATE(l.created_at) <= ?
         WHERE u.role = 'sales_agent' AND COALESCE(u.status, 'live') = 'live'
         GROUP BY u.id, u.name, u.email
         ORDER BY sales DESC, reach DESC, u.name
         LIMIT " . (int) $limit,
        'ss',
        [$from, $to]
    );
    return $rows;
}

function sales_message_send(int $fromId, int $toId, string $body): array
{
    $body = trim($body);
    if ($body === '') {
        return ['ok' => false, 'error' => 'Write a message.'];
    }
    if ($fromId === $toId) {
        return ['ok' => false, 'error' => 'Pick someone else to message.'];
    }
    $id = db_exec(
        'INSERT INTO sales_messages (from_user_id, to_user_id, body) VALUES (?,?,?)',
        'iis',
        [$fromId, $toId, mb_substr($body, 0, 2000)]
    );
    return ['ok' => true, 'id' => (int) $id];
}

function sales_messages_for(int $userId, ?int $withId = null, int $limit = 80): array
{
    if ($withId) {
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

function sales_messages_mark_read(int $userId, ?int $fromId = null): void
{
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
        "SELECT l.*, u.name AS agent_name FROM sales_leads l LEFT JOIN users u ON u.id = l.agent_id WHERE {$where} ORDER BY l.follow_up_date, l.id",
        $types,
        $params
    );
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
            'href' => url('sales_messages.php?with=' . (int) $m['from_user_id']),
            'tone' => 'info',
        ];
        if ($seenUnread >= 5) {
            break;
        }
    }
    foreach (sales_followups_due($userId, 1) as $lead) {
        $when = (string) ($lead['follow_up_date'] ?? '');
        $label = $when === today() ? 'today' : 'tomorrow';
        $notes[] = [
            'type' => 'follow_up',
            'key' => 'sales-fu-' . (int) $lead['id'] . '-' . $when,
            'title' => 'Follow up: ' . (trim((string) $lead['business_name']) ?: 'Business'),
            'meta' => 'Due ' . $label . ($lead['city'] ? ' · ' . $lead['city'] : ''),
            'href' => url('sales_lead_edit.php?id=' . (int) $lead['id']),
            'tone' => 'warn',
        ];
    }
    if (!sales_is_clocked_in($userId)) {
        $notes[] = [
            'type' => 'clock',
            'key' => 'sales-clock-' . today(),
            'title' => 'Clock in for today',
            'meta' => 'Enter your city before logging visits',
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

function sales_admin_unread_count(int $platformUserId): int
{
    return (int) (db_one(
        'SELECT COUNT(*) c FROM sales_messages m
         JOIN users u ON u.id = m.from_user_id AND u.role = \'sales_agent\'
         WHERE m.to_user_id = ? AND m.read_at IS NULL',
        'i',
        [$platformUserId]
    )['c'] ?? 0);
}

function sales_layout_start(string $title, array $user): void
{
    $flash = flash();
    $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $unread = sales_unread_count((int) $user['id']);
    $notes = sales_notifications_for_agent((int) $user['id']);
    $noteCount = count($notes);
    $nav = [
        ['sales_home.php', 'Home', 'home'],
        ['sales_leads.php', 'Leads', 'clients'],
        ['sales_performance.php', 'Performance', 'reports'],
        ['sales_demo.php', 'Demo', 'building'],
        ['sales_messages.php', 'Messages', 'mail'],
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
<body class="desk-body sales-body">
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
      <?php foreach ($nav as [$href, $label, $iconName]):
          $file = strtok($href, '?');
          $active = $file === $here || ($here === 'sales_lead_edit.php' && $file === 'sales_leads.php');
          $badge = ($file === 'sales_messages.php' && $unread) ? $unread : 0;
          ?>
        <a class="<?= $active ? 'is-on' : '' ?>" href="<?= h(url($href)) ?>" title="<?= h($label) ?>"<?= $badge ? ' data-badge="' . (int) $badge . '"' : '' ?>><?= icon($iconName, 18) ?><span><?= h($label) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="nav-user">
      <span class="nav-user-name"><?= icon('user', 16) ?><span><?= h($user['name']) ?></span></span>
      <span class="nav-user-mail"><?= h($user['email']) ?></span>
      <a href="<?= h(url('logout.php')) ?>" title="Sign out"><?= icon('logout', 15) ?><span>Sign out</span></a>
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
<nav class="app-tabbar sales-tabbar" aria-label="Sales">
  <a class="app-tab<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'sales_home.php' ? ' is-on' : '' ?>" href="<?= h(url('sales_home.php')) ?>"><?= icon('home', 22) ?><span>Home</span></a>
  <a class="app-tab<?= in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['sales_leads.php', 'sales_lead_edit.php'], true) ? ' is-on' : '' ?>" href="<?= h(url('sales_leads.php')) ?>"><?= icon('clients', 22) ?><span>Leads</span></a>
  <a class="app-tab app-tab-create" href="<?= h(url('sales_lead_edit.php')) ?>"><span class="app-tab-plus"><?= icon('plus', 26) ?></span><span>New</span></a>
  <a class="app-tab<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'sales_performance.php' ? ' is-on' : '' ?>" href="<?= h(url('sales_performance.php')) ?>"><?= icon('reports', 22) ?><span>Stats</span></a>
  <a class="app-tab<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'sales_messages.php' ? ' is-on' : '' ?>" href="<?= h(url('sales_messages.php')) ?>"><?= icon('mail', 22) ?><span>Chat</span></a>
</nav>
<script src="<?= h(asset('js/app.js')) ?>" defer></script>
<?= $extra ?>
</body>
</html>
<?php
}
