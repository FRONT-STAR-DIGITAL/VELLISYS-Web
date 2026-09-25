<?php
declare(strict_types=1);

function company_activity_kinds(): array
{
    return [
        'document' => 'Documents',
        'payment' => 'Payments',
        'email' => 'Email',
        'client' => 'Clients',
        'settings' => 'Settings',
        'planner' => 'Planner',
        'stock' => 'Stock',
        'branch' => 'Branches',
    ];
}

function resolve_activity_branch_id(array $opts = []): ?int
{
    if (!function_exists('company_branches_enabled') || !company_branches_enabled()) {
        return null;
    }
    if (array_key_exists('branch_id', $opts)) {
        return function_exists('normalize_branch_id') ? normalize_branch_id($opts['branch_id']) : null;
    }
    $user = function_exists('current_user') ? current_user() : null;
    return function_exists('normalize_branch_id') ? normalize_branch_id($user['branch_id'] ?? null) : null;
}

function record_company_activity(string $kind, string $title, array $opts = []): void
{
    $title = trim($title);
    if ($title === '') {
        return;
    }
    $kinds = company_activity_kinds();
    if (!isset($kinds[$kind])) {
        $kind = 'document';
    }
    $cid = (int) ($opts['company_id'] ?? 0);
    if ($cid <= 0) {
        try {
            $cid = (int) current_company_id();
        } catch (Throwable $e) {
            $cid = 0;
        }
    }
    if ($cid <= 0) {
        return;
    }
    $uid = (int) ($opts['user_id'] ?? 0);
    if ($uid <= 0) {
        $uid = (int) (current_user()['id'] ?? 0);
    }
    $detail = trim((string) ($opts['detail'] ?? ''));
    $href = trim((string) ($opts['href'] ?? ''));
    $refType = trim((string) ($opts['ref_type'] ?? ''));
    $refId = (int) ($opts['ref_id'] ?? 0);
    $branchId = resolve_activity_branch_id($opts);
    try {
        db_exec(
            'INSERT INTO company_activities (company_id, user_id, branch_id, kind, title, detail, href, ref_type, ref_id) VALUES (?,?,?,?,?,?,?,?,?)',
            'iiisssssi',
            [$cid, $uid, $branchId, $kind, mb_substr($title, 0, 190), $detail !== '' ? mb_substr($detail, 0, 500) : null, $href !== '' ? mb_substr($href, 0, 190) : null, $refType !== '' ? mb_substr($refType, 0, 40) : null, $refId > 0 ? $refId : null]
        );
    } catch (Throwable $e) {
        try {
            db_exec(
                'INSERT INTO company_activities (company_id, user_id, kind, title, detail, href, ref_type, ref_id) VALUES (?,?,?,?,?,?,?,?)',
                'iisssssi',
                [$cid, $uid, $kind, mb_substr($title, 0, 190), $detail !== '' ? mb_substr($detail, 0, 500) : null, $href !== '' ? mb_substr($href, 0, 190) : null, $refType !== '' ? mb_substr($refType, 0, 40) : null, $refId > 0 ? $refId : null]
            );
        } catch (Throwable $e2) {
            error_log('Vellisys activity: ' . $e2->getMessage());
        }
    }
}

function company_activities(array $opts = []): array
{
    $cid = (int) ($opts['company_id'] ?? current_company_id());
    $kind = trim((string) ($opts['kind'] ?? ''));
    $q = trim((string) ($opts['q'] ?? ''));
    $limit = max(1, min(200, (int) ($opts['limit'] ?? 80)));
    $sql = 'SELECT a.*, u.name AS actor_name
            FROM company_activities a
            LEFT JOIN users u ON u.id = a.user_id
            WHERE a.company_id = ?';
    $types = 'i';
    $params = [$cid];
    if ($kind !== '' && isset(company_activity_kinds()[$kind])) {
        $sql .= ' AND a.kind = ?';
        $types .= 's';
        $params[] = $kind;
    }
    if (array_key_exists('branch_id', $opts) && function_exists('company_branches_enabled') && company_branches_enabled()) {
        $raw = $opts['branch_id'];
        if ($raw === 'all' || $raw === null || $raw === '') {
            // every branch
        } elseif ((int) $raw === 0) {
            $sql .= ' AND (a.branch_id IS NULL OR a.branch_id = 0)';
        } else {
            $sql .= ' AND a.branch_id = ?';
            $types .= 'i';
            $params[] = (int) $raw;
        }
    }
    if ($q !== '') {
        $sql .= ' AND (a.title LIKE ? OR a.detail LIKE ?)';
        $types .= 'ss';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= ' ORDER BY a.id DESC LIMIT ' . $limit;
    try {
        $rows = db_all($sql, $types, $params);
    } catch (Throwable $e) {
        return [];
    }
    foreach ($rows as &$row) {
        $bid = isset($row['branch_id']) && (int) $row['branch_id'] > 0 ? (int) $row['branch_id'] : 0;
        $row['branch_id'] = $bid > 0 ? $bid : null;
        $row['branch_name'] = function_exists('company_branch_label')
            ? company_branch_label($bid)
            : 'Head office';
    }
    unset($row);
    return $rows;
}

function desk_safe_next(?string $next): string
{
    $next = basename(trim((string) $next));
    $ok = ['activities.php', 'dashboard.php', 'planner.php', 'planner_goals.php', 'settings.php', 'branches.php', 'tutorials.php', 'feedback.php', 'help.php', 'account.php'];
    return in_array($next, $ok, true) ? $next : 'dashboard.php';
}
