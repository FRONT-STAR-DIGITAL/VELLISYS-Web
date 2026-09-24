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

function sales_agent_delete(int $id): array
{
    $row = sales_agent($id);
    if (!$row) {
        return ['ok' => false, 'error' => 'Sales agent not found.'];
    }
    $n = (int) (db_one('SELECT COUNT(*) c FROM sales_leads WHERE agent_id = ? AND deleted_at IS NULL', 'i', [$id])['c'] ?? 0);
    if ($n > 0) {
        db_exec("UPDATE users SET status='suspended' WHERE id=?", 'i', [$id]);
        return ['ok' => true, 'suspended' => true, 'message' => 'Agent has leads, so the login was suspended instead of deleted.'];
    }
    db_exec('DELETE FROM sales_messages WHERE from_user_id = ? OR to_user_id = ?', 'ii', [$id, $id]);
    db_exec('DELETE FROM sales_clock_ins WHERE user_id = ?', 'i', [$id]);
    db_exec('DELETE FROM sales_targets WHERE agent_id = ?', 'i', [$id]);
    db_exec("DELETE FROM users WHERE id = ? AND role = 'sales_agent'", 'i', [$id]);
    return ['ok' => true, 'deleted' => true];
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
    } elseif ($status === 'rejected') {
        $rejected = mb_substr(trim((string) ($fields['rejected_reason'] ?? '')), 0, 500);
        if ($rejected === '') {
            return ['ok' => false, 'error' => 'Add a reason for rejection.'];
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
             nature_of_business=?, package_chosen=?, onboard_date=?, follow_up_date=?, follow_up_done_at=?, rejected_reason=?, notes=?, updated_at=NOW()
             WHERE id=?',
            'sssssssssssssi',
            [$status, $business, $address, $contactName, $contactPhone, $city, $nature, $package, $onboardDate, $followDate, $followDone, $rejected, $notes, $id]
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
         nature_of_business, package_chosen, onboard_date, follow_up_date, rejected_reason, notes)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
        'issssssssssss',
        [$agentId, $status, $business, $address, $contactName, $contactPhone, $city, $nature, $package, $onboardDate, $followDate, $rejected, $notes]
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
    $sales = $by['onboarded'] + $by['interested'];
    return [
        'by_status' => $by,
        'reach' => $reach,
        'sales' => $by['onboarded'],
        'interested' => $by['interested'],
        'follow_up' => $by['follow_up'],
        'rejected' => $by['rejected'],
        'onboarded' => $by['onboarded'],
    ];
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
                SUM(CASE WHEN l.status = 'onboarded' THEN 1 ELSE 0 END) AS sales,
                SUM(CASE WHEN l.status = 'interested' THEN 1 ELSE 0 END) AS interested,
                SUM(CASE WHEN l.status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN l.status = 'follow_up' THEN 1 ELSE 0 END) AS follow_up
         FROM users u
         LEFT JOIN sales_leads l ON l.agent_id = u.id
              AND DATE(l.created_at) >= ? AND DATE(l.created_at) <= ?
         WHERE u.role = 'sales_agent'
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
    $unread = sales_unread_count($userId);
    if ($unread > 0) {
        $notes[] = [
            'type' => 'message',
            'key' => 'sales-msg-' . $userId . '-' . $unread,
            'title' => $unread === 1 ? '1 new message from admin' : ($unread . ' new messages from admin'),
            'meta' => 'Open Messages',
            'href' => url('sales_messages.php'),
            'tone' => 'info',
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
