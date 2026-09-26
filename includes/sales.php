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
        flash('Clock in first. Enter where you are today.', 'err');
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
            $cid, $name, '', '', '', $address, $city, $phone, $userEmail, '', '', $name, '',
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
    $by = ['interested' => 0, 'follow_up' => 0, 'rejected' => 0, 'onboarding' => 0, 'onboarded' => 0];
    foreach ($rows as $r) {
        $key = (string) $r['status'];
        if (!isset($by[$key])) {
            $by[$key] = 0;
        }
        $by[$key] = (int) $r['n'];
    }
    $reach = array_sum($by);
    $wins = $by['onboarded'] + $by['onboarding'] + $by['interested'];
    return [
        'by_status' => $by,
        'reach' => $reach,
        'wins' => $wins,
        'sales' => $wins,
        'interested' => $by['interested'],
        'follow_up' => $by['follow_up'],
        'rejected' => $by['rejected'],
        'onboarding' => $by['onboarding'],
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
        <strong class="mono"><?= $reach ?>/<?= $reachGoal ?: '-' ?></strong>
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
        <strong class="mono"><?= $sales ?>/<?= $salesGoal ?: '-' ?></strong>
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
    $notes = sales_notifications_for_agent((int) $user['id']);
    $noteCount = count($notes);
    $avatar = sales_avatar_url($user);
    $nav = [
        ['sales_home.php', 'Home', 'home'],
        ['sales_leads.php', 'Leads', 'clients'],
        ['sales_performance.php', 'Performance', 'reports'],
        ['sales_demo.php', 'Demo', 'building'],
        ['sales_messages.php', 'Messages', 'mail'],
        ['sales_profile.php', 'Profile', 'user'],
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
      <?php foreach ($nav as [$href, $label, $iconName]):
          $file = strtok($href, '?');
          $active = $file === $here || ($here === 'sales_lead_edit.php' && $file === 'sales_leads.php');
          $badge = ($file === 'sales_messages.php' && $unread) ? $unread : 0;
          ?>
        <a class="<?= $active ? 'is-on' : '' ?>" href="<?= h(url($href)) ?>" title="<?= h($label) ?>"<?= $badge ? ' data-badge="' . (int) $badge . '"' : '' ?>><?= icon($iconName, 18) ?><span><?= h($label) ?></span></a>
      <?php endforeach; ?>
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
