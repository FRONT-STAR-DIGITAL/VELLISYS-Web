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
