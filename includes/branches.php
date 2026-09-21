<?php
declare(strict_types=1);

function company_branches_enabled(?array $company = null): bool
{
    $company = $company ?? current_company();
    if (!$company) {
        return false;
    }
    return plan_includes_branches((string) ($company['plan'] ?? 'sme'));
}

function require_branches(): array
{
    $user = require_member();
    if (!company_branches_enabled()) {
        flash('Branches are on Vellisys Business and Pro desks. Ask Vellisys if you need them.', 'err');
        redirect('dashboard.php');
    }
    return $user;
}

function company_head_office(?array $brand = null): array
{
    $brand = $brand ?? branding();
    return [
        'id' => 0,
        'is_head' => true,
        'name' => 'Head office',
        'address' => (string) ($brand['address'] ?? ''),
        'city' => (string) ($brand['city'] ?? ''),
        'phone' => (string) ($brand['phone'] ?? ''),
        'email' => (string) ($brand['email'] ?? ''),
    ];
}

function company_location_limit(?array $company = null): int
{
    $company = $company ?? current_company();
    if (function_exists('plan_branch_limit')) {
        return plan_branch_limit($company);
    }
    return 1;
}

function company_named_branch_limit(?array $company = null): int
{
    return max(0, company_location_limit($company) - 1);
}

function company_location_count(?int $companyId = null): int
{
    return 1 + count(company_named_branches($companyId));
}

function company_can_add_named_branch(?array $company = null, ?int $companyId = null): bool
{
    $cid = $companyId ?? (int) ($company['id'] ?? current_company_id());
    return company_location_count($cid) < company_location_limit($company);
}

function company_named_branches(?int $companyId = null): array
{
    $cid = $companyId ?? current_company_id();
    if ($cid < 1) {
        return [];
    }
    return db_all('SELECT * FROM branches WHERE company_id = ? ORDER BY name', 'i', [$cid]);
}

function company_all_branches(?int $companyId = null, ?array $brand = null): array
{
    $out = [company_head_office($brand)];
    foreach (company_named_branches($companyId) as $row) {
        $row['id'] = (int) $row['id'];
        $row['is_head'] = false;
        $out[] = $row;
    }
    return $out;
}

function company_branch(?int $id, ?int $companyId = null, ?array $brand = null): ?array
{
    if ($id === null || $id < 1) {
        return company_head_office($brand);
    }
    $cid = $companyId ?? current_company_id();
    $row = db_one('SELECT * FROM branches WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
    if (!$row) {
        return company_head_office($brand);
    }
    $row['id'] = (int) $row['id'];
    $row['is_head'] = false;
    return $row;
}

function company_branch_label(?int $id, ?int $companyId = null): string
{
    $branch = company_branch($id, $companyId);
    return (string) ($branch['name'] ?? 'Head office');
}

function normalize_branch_id(mixed $raw, ?int $companyId = null): ?int
{
    if ($raw === null || $raw === '' || (int) $raw === 0) {
        return null;
    }
    $id = (int) $raw;
    $cid = $companyId ?? current_company_id();
    $row = db_one('SELECT id FROM branches WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
    return $row ? $id : null;
}

function resolve_document_branch_id(mixed $posted = null, ?array $user = null): ?int
{
    if (!company_branches_enabled()) {
        return null;
    }
    $user = $user ?? current_user();
    $own = normalize_branch_id($user['branch_id'] ?? null);
    if (!is_desk_admin($user)) {
        return $own;
    }
    if ($posted === null && !array_key_exists('branch_id', $_POST)) {
        return $own;
    }
    return normalize_branch_id($posted);
}

function apply_branch_to_brand(array $brand, mixed $branchId): array
{
    $id = (int) $branchId;
    if ($id < 1) {
        return $brand;
    }
    $cid = (int) ($brand['company_id'] ?? current_company_id());
    $branch = company_branch($id, $cid > 0 ? $cid : null, $brand);
    if (!$branch || !empty($branch['is_head'])) {
        return $brand;
    }
    foreach (['address', 'city', 'phone', 'email'] as $key) {
        $val = trim((string) ($branch[$key] ?? ''));
        if ($val !== '') {
            $brand[$key] = $val;
        }
    }
    return $brand;
}

function assign_user_branch(int $userId, mixed $branchId, ?int $companyId = null): bool
{
    $cid = $companyId ?? current_company_id();
    $member = db_one('SELECT id FROM users WHERE id = ? AND company_id = ? AND role <> \'platform\'', 'ii', [$userId, $cid]);
    if (!$member) {
        return false;
    }
    $bid = normalize_branch_id($branchId, $cid);
    if ($bid === null) {
        db_exec('UPDATE users SET branch_id = NULL WHERE id = ? AND company_id = ?', 'ii', [$userId, $cid]);
    } else {
        db_exec('UPDATE users SET branch_id = ? WHERE id = ? AND company_id = ?', 'iii', [$bid, $userId, $cid]);
    }
    return true;
}

function branch_staff(int $branchId, ?int $companyId = null): array
{
    $cid = $companyId ?? current_company_id();
    if ($branchId < 1) {
        return db_all(
            "SELECT id, name, job_title, email, role, access, branch_id FROM users WHERE company_id = ? AND role <> 'platform' AND (branch_id IS NULL OR branch_id = 0) ORDER BY role = 'admin' DESC, name",
            'i',
            [$cid]
        );
    }
    return db_all(
        "SELECT id, name, job_title, email, role, access, branch_id FROM users WHERE company_id = ? AND role <> 'platform' AND branch_id = ? ORDER BY role = 'admin' DESC, name",
        'ii',
        [$cid, $branchId]
    );
}

function save_named_branch(array $fields, ?int $id = null): array
{
    $cid = current_company_id();
    $name = mb_substr(trim((string) ($fields['name'] ?? '')), 0, 120);
    if ($name === '') {
        return ['ok' => false, 'error' => 'Name the branch.'];
    }
    if (strcasecmp($name, 'Head office') === 0) {
        return ['ok' => false, 'error' => 'Head office is the company address in Settings. Pick another name.'];
    }
    $address = mb_substr(trim((string) ($fields['address'] ?? '')), 0, 255);
    $city = mb_substr(trim((string) ($fields['city'] ?? '')), 0, 120);
    $phone = mb_substr(trim((string) ($fields['phone'] ?? '')), 0, 80);
    $email = mb_substr(trim((string) ($fields['email'] ?? '')), 0, 160);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Use a valid branch email, or leave it blank.'];
    }
    if ($id) {
        $row = db_one('SELECT id FROM branches WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
        if (!$row) {
            return ['ok' => false, 'error' => 'That branch is not on this desk.'];
        }
        db_exec(
            'UPDATE branches SET name=?, address=?, city=?, phone=?, email=? WHERE id=? AND company_id=?',
            'sssssii',
            [$name, $address, $city, $phone, $email, $id, $cid]
        );
        return ['ok' => true, 'id' => $id];
    }
    $cap = company_location_limit();
    $used = company_location_count($cid);
    if ($used >= $cap) {
        return [
            'ok' => false,
            'error' => 'This package allows up to ' . $cap . ' branch' . ($cap === 1 ? '' : 'es')
                . ', including Head office. You already use ' . $used
                . '. Several people can share a branch.',
        ];
    }
    $newId = db_exec(
        'INSERT INTO branches (company_id, name, address, city, phone, email) VALUES (?,?,?,?,?,?)',
        'isssss',
        [$cid, $name, $address, $city, $phone, $email]
    );
    return ['ok' => true, 'id' => $newId];
}

function delete_named_branch(int $id): bool
{
    $cid = current_company_id();
    $row = db_one('SELECT id FROM branches WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
    if (!$row) {
        return false;
    }
    db_exec('UPDATE users SET branch_id = NULL WHERE company_id = ? AND branch_id = ?', 'ii', [$cid, $id]);
    db_exec('UPDATE documents SET branch_id = NULL WHERE company_id = ? AND branch_id = ?', 'ii', [$cid, $id]);
    db_exec('DELETE FROM branches WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
    return true;
}

function render_branch_subnav(string $active): void
{
    ?>
  <nav class="planner-tabs" aria-label="Branch sections">
    <a class="planner-tab<?= $active === 'list' ? ' is-on' : '' ?>" href="<?= h(url('branches.php')) ?>"><?= icon('pin', 16) ?><span>Branches</span></a>
    <a class="planner-tab<?= $active === 'performance' ? ' is-on' : '' ?>" href="<?= h(url('branches.php?tab=performance')) ?>"><?= icon('reports', 16) ?><span>Performance</span></a>
  </nav>
    <?php
}

function document_branch_key(array $doc): int
{
    return isset($doc['branch_id']) && (int) $doc['branch_id'] > 0 ? (int) $doc['branch_id'] : 0;
}

function branch_performance_blank(): array
{
    return [
        'income' => 0.0,
        'billed' => 0.0,
        'expenses' => 0.0,
        'cogs' => 0.0,
        'profit' => 0.0,
        'net' => 0.0,
        'cash_in' => 0.0,
        'cash_out' => 0.0,
        'outstanding' => 0.0,
        'invoices' => 0,
        'expenses_n' => 0,
        'receipts' => 0,
        'quotes' => 0,
        'docs' => 0,
        'income_share' => 0.0,
        'expense_share' => 0.0,
    ];
}

function branch_performance_for_range(string $from, string $to): array
{
    $cid = current_company_id();
    $sqlFrom = $from !== '' ? $from : '1970-01-01';
    $sqlTo = $to !== '' ? $to : today();
    $base = default_currency();
    $rows = [];
    foreach (company_all_branches() as $b) {
        $id = (int) ($b['id'] ?? 0);
        $rows[$id] = branch_performance_blank() + [
            'id' => $id,
            'name' => (string) ($b['name'] ?? 'Branch'),
            'is_head' => !empty($b['is_head']),
        ];
    }

    $docs = db_all(
        "SELECT d.*, p.name AS party_name, r.kind AS related_kind
         FROM documents d
         LEFT JOIN parties p ON p.id = d.party_id
         LEFT JOIN documents r ON r.id = d.related_id
         WHERE d.company_id = ? AND d.status = 'issued' AND d.date >= ? AND d.date <= ?",
        'iss',
        [$cid, $sqlFrom, $sqlTo]
    );
    if (function_exists('attach_document_totals')) {
        $docs = attach_document_totals($docs);
    }

    foreach ($docs as $d) {
        $bid = document_branch_key($d);
        if (!isset($rows[$bid])) {
            $rows[$bid] = branch_performance_blank() + [
                'id' => $bid,
                'name' => 'Removed branch',
                'is_head' => false,
            ];
        }
        $rows[$bid]['docs']++;
        $kind = (string) ($d['kind'] ?? '');
        $cur = function_exists('doc_currency') ? doc_currency($d) : $base;
        if ($kind === 'invoice') {
            $net = convert_money((float) ($d['totals']['net'] ?? 0), $cur, $base);
            $billed = convert_money((float) ($d['totals']['total'] ?? 0), $cur, $base);
            $rows[$bid]['income'] += $net;
            $rows[$bid]['billed'] += $billed;
            $rows[$bid]['invoices']++;
            if ((float) ($d['balance'] ?? 0) > 0) {
                $rows[$bid]['outstanding'] += convert_money((float) $d['balance'], $cur, $base);
            }
        } elseif ($kind === 'expense') {
            $rows[$bid]['expenses_n']++;
            if (!function_exists('stock_is_stock_expense') || !stock_is_stock_expense($d)) {
                $rows[$bid]['expenses'] += convert_money((float) ($d['totals']['net'] ?? 0), $cur, $base);
            }
        } elseif ($kind === 'receipt') {
            $rows[$bid]['receipts']++;
            $amt = convert_money((float) ($d['allocated_amount'] ?: ($d['totals']['total'] ?? 0)), $cur, $base);
            if (($d['related_kind'] ?? '') === 'expense') {
                $rows[$bid]['cash_out'] += $amt;
            } else {
                $rows[$bid]['cash_in'] += $amt;
            }
            if (function_exists('receipt_is_sale') && receipt_is_sale($d)) {
                $due = function_exists('document_due_amount') ? document_due_amount($d) : (float) ($d['balance'] ?? 0);
                if ($due > 0.009) {
                    $rows[$bid]['outstanding'] += convert_money($due, $cur, $base);
                }
            }
        } elseif ($kind === 'quotation') {
            $rows[$bid]['quotes']++;
        }
    }

    $cogsRows = db_all(
        "SELECT COALESCE(d.branch_id, 0) AS bid, d.currency,
                COALESCE(SUM(ROUND(i.qty * COALESCE(s.buy_price, 0), 2)), 0) AS cogs
         FROM documents d
         JOIN document_items i ON i.document_id = d.id
         LEFT JOIN stock_items s ON s.id = i.stock_item_id AND s.company_id = d.company_id
         WHERE d.company_id = ? AND d.status = 'issued' AND d.kind = 'invoice'
           AND d.date >= ? AND d.date <= ? AND i.stock_item_id IS NOT NULL AND i.stock_item_id > 0
           AND COALESCE(s.is_service, 0) = 0
         GROUP BY COALESCE(d.branch_id, 0), d.currency",
        'iss',
        [$cid, $sqlFrom, $sqlTo]
    );
    foreach ($cogsRows as $d) {
        $bid = (int) ($d['bid'] ?? 0);
        if (!isset($rows[$bid])) {
            continue;
        }
        $fromCur = normalize_currency((string) ($d['currency'] ?? ''), $base);
        $rows[$bid]['cogs'] += convert_money((float) ($d['cogs'] ?? 0), $fromCur, $base);
    }

    $overall = branch_performance_blank() + [
        'id' => -1,
        'name' => 'Overall',
        'is_head' => false,
    ];
    $sumKeys = ['income', 'billed', 'expenses', 'cogs', 'cash_in', 'cash_out', 'outstanding', 'invoices', 'expenses_n', 'receipts', 'quotes', 'docs'];
    foreach ($rows as &$row) {
        if (function_exists('stock_finish_totals')) {
            $fin = stock_finish_totals([
                'income' => $row['income'],
                'cogs' => $row['cogs'],
                'expense' => $row['expenses'],
                'tax' => 0,
            ]);
            $row['profit'] = $fin['profit'];
            $row['net'] = $fin['net'];
        } else {
            $row['profit'] = round($row['income'] - $row['cogs'], 2);
            $row['net'] = round($row['profit'] - $row['expenses'], 2);
        }
        foreach ($sumKeys as $k) {
            $overall[$k] += $row[$k];
        }
        $overall['profit'] += $row['profit'];
        $overall['net'] += $row['net'];
    }
    unset($row);

    $incTot = (float) $overall['income'];
    $expTot = (float) $overall['expenses'];
    foreach ($rows as &$row) {
        $row['income_share'] = $incTot > 0 ? round(100 * $row['income'] / $incTot, 1) : 0.0;
        $row['expense_share'] = $expTot > 0 ? round(100 * $row['expenses'] / $expTot, 1) : 0.0;
    }
    unset($row);
    $overall['income_share'] = $incTot > 0 ? 100.0 : 0.0;
    $overall['expense_share'] = $expTot > 0 ? 100.0 : 0.0;

    return ['overall' => $overall, 'branches' => array_values($rows)];
}

function render_branch_options(?int $selected = null, bool $includeHead = true, ?int $companyId = null): void
{
    $selected = $selected === null ? 0 : $selected;
    if ($includeHead) {
        echo '<option value="0"' . ($selected < 1 ? ' selected' : '') . '>Head office</option>';
    }
    foreach (company_named_branches($companyId) as $row) {
        $id = (int) $row['id'];
        echo '<option value="' . $id . '"' . ($selected === $id ? ' selected' : '') . '>' . h((string) $row['name']) . '</option>';
    }
}
