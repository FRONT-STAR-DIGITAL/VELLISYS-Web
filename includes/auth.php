<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    try {
        $user = db_one('SELECT * FROM users WHERE id = ?', 'i', [(int) $_SESSION['user_id']]);
    } catch (Throwable $e) {
        return null;
    }
    if (!$user) {
        return null;
    }
    $user['job_title'] = (string) ($user['job_title'] ?? '');
    $user['access'] = (string) ($user['access'] ?? (($user['role'] ?? '') === 'admin' ? 'admin' : 'books'));
    $user['role'] = (string) ($user['role'] ?? 'member');
    $user['status'] = (string) ($user['status'] ?? 'live');
    $user['branch_id'] = isset($user['branch_id']) && (int) $user['branch_id'] > 0 ? (int) $user['branch_id'] : null;
    if (($user['role'] ?? '') !== 'platform' && ($user['status'] ?? 'live') === 'suspended') {
        unset($_SESSION['user_id'], $_SESSION['company_id'], $_SESSION['role'], $_SESSION['acting_company_id']);
        return null;
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}

function require_member(): array
{
    $user = require_login();
    if (($user['role'] ?? '') === 'platform') {
        $acting = (int) ($_SESSION['acting_company_id'] ?? 0);
        if ($acting <= 0) {
            redirect(platform_home());
        }
        $_SESSION['company_id'] = $acting;
    }
    return $user;
}

function is_acting_admin(): bool
{
    return is_platform() && (int) ($_SESSION['acting_company_id'] ?? 0) > 0;
}

function require_platform(): array
{
    $user = require_login();
    if (($user['role'] ?? '') !== 'platform') {
        redirect('dashboard.php');
    }
    return $user;
}

function is_desk_admin(?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user) {
        return false;
    }
    if (($user['role'] ?? '') === 'platform') {
        return true;
    }
    return ($user['role'] ?? '') === 'admin';
}

function require_desk_admin(): array
{
    $user = require_member();
    if (!is_desk_admin($user)) {
        flash('Only the company admin can open that page.', 'err');
        redirect('dashboard.php');
    }
    return $user;
}

function user_access(?array $user = null): string
{
    $user = $user ?? current_user();
    if (!$user) {
        return 'books';
    }
    if (is_desk_admin($user)) {
        return 'admin';
    }
    $access = (string) ($user['access'] ?? 'books');
    return $access === 'sales' ? 'sales' : 'books';
}

function desk_staff_access_levels(): array
{
    return [
        'books' => [
            'label' => 'Desk',
            'hint' => 'Documents, stock, clients and email. No reports, profit, settings, branches or activities.',
        ],
        'sales' => [
            'label' => 'Sales',
            'hint' => 'Sale till, quotations, invoices, receipts, purchases, clients and email. No reports or admin pages.',
        ],
    ];
}

function desk_feature_catalog(): array
{
    return [
        'sale' => 'Sale till',
        'stock' => 'Stock',
        'purchases' => 'Purchases',
        'quotation' => 'Quotations',
        'invoice' => 'Invoices',
        'receipt' => 'Receipts',
        'expense' => 'Expenses',
        'delivery' => 'Delivery notes',
        'letter' => 'Letters',
        'custom' => 'Custom documents',
        'clients' => 'Clients',
        'debtors' => 'Debtors',
        'creditors' => 'Creditors',
        'email' => 'Email',
        'edit_documents' => 'Editing documents',
        'delete_documents' => 'Deleting documents',
        'delete_stock' => 'Deleting products',
        'backdate_documents' => 'Creating backdated documents',
    ];
}

function desk_feature_defaults(string $access): array
{
    if ($access === 'sales') {
        return ['sale', 'stock', 'purchases', 'quotation', 'invoice', 'receipt', 'clients', 'debtors', 'email'];
    }
    return array_keys(desk_feature_catalog());
}

function parse_user_features(mixed $raw, string $access = 'books'): array
{
    $allowed = array_keys(desk_feature_catalog());
    $fromStore = is_string($raw);
    $decoded = $raw;
    if ($fromStore && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
    }
    if (!is_array($decoded)) {
        return desk_feature_defaults($access === 'sales' ? 'sales' : 'books');
    }
    $out = [];
    foreach ($decoded as $key) {
        $key = (string) $key;
        if (in_array($key, $allowed, true)) {
            $out[] = $key;
        }
    }
    $out = array_values(array_unique($out));
    if ($access !== 'sales' && in_array('stock', $out, true) && !in_array('delete_stock', $out, true)) {
        $legacy = array_values(array_diff($allowed, ['delete_stock']));
        if (array_diff($legacy, $out) === []) {
            $out[] = 'delete_stock';
        }
    }
    return $out;
}

function posted_user_features(string $access): array
{
    $posted = $_POST['features'] ?? null;
    if (!is_array($posted)) {
        if (isset($_POST['features_posted'])) {
            return [];
        }
        return desk_feature_defaults($access);
    }
    return parse_user_features($posted, $access);
}

function user_features(?array $user = null): array
{
    $user = $user ?? current_user();
    if (!$user) {
        return [];
    }
    if (is_desk_admin($user)) {
        return array_keys(desk_feature_catalog());
    }
    $access = ((string) ($user['access'] ?? 'books')) === 'sales' ? 'sales' : 'books';
    return parse_user_features($user['features'] ?? '', $access);
}

function user_can_feature(string $key, ?array $user = null): bool
{
    if (is_desk_admin($user)) {
        return true;
    }
    return in_array($key, user_features($user), true);
}

function user_can_see_profit(?array $user = null): bool
{
    return is_desk_admin($user);
}

function user_can_edit_documents(?array $user = null): bool
{
    return is_desk_admin($user) || user_can_feature('edit_documents', $user);
}

function user_can_delete_documents(?array $user = null): bool
{
    return is_desk_admin($user) || user_can_feature('delete_documents', $user);
}

function user_can_delete_stock(?array $user = null): bool
{
    if (function_exists('company_stock_enabled') && !company_stock_enabled()) {
        return false;
    }
    return is_desk_admin($user) || user_can_feature('delete_stock', $user);
}

function user_can_backdate_documents(?array $user = null): bool
{
    return is_desk_admin($user) || user_can_feature('backdate_documents', $user);
}

function login_fail_reason(?string $set = null): string
{
    static $reason = '';
    if ($set !== null) {
        $reason = $set;
    }
    return $reason;
}

function user_is_suspended(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user && (($user['status'] ?? 'live') === 'suspended');
}

function render_desk_feature_checks(array $selected, string $name = 'features[]', ?array $company = null): void
{
    $stockOn = function_exists('company_stock_enabled') && company_stock_enabled($company);
    ?>
    <input type="hidden" name="features_posted" value="1">
    <div class="feature-checks">
      <?php foreach (desk_feature_catalog() as $key => $label): ?>
        <?php if (in_array($key, ['sale', 'stock', 'purchases', 'delete_stock'], true) && !$stockOn) { continue; } ?>
        <label class="check">
          <input type="checkbox" name="<?= h($name) ?>" value="<?= h($key) ?>" <?= in_array($key, $selected, true) ? 'checked' : '' ?>>
          <?= h($label) ?>
        </label>
      <?php endforeach; ?>
    </div>
    <?php
}

function user_allowed_kinds(?array $user = null): ?array
{
    if (is_desk_admin($user)) {
        return null;
    }
    $kinds = [];
    foreach (['quotation', 'invoice', 'receipt', 'expense', 'delivery', 'letter', 'custom', 'refund', 'return_note'] as $kind) {
        if (user_can_kind($kind, $user)) {
            $kinds[] = $kind;
        }
    }
    return $kinds;
}

function user_can_kind(string $kind, ?array $user = null): bool
{
    if (!company_allows_kind($kind) && $kind !== 'expense') {
        return false;
    }
    if (is_desk_admin($user)) {
        return true;
    }
    if (in_array($kind, ['refund', 'return_note'], true)) {
        return false;
    }
    if (isset(desk_feature_catalog()[$kind])) {
        return user_can_feature($kind, $user);
    }
    return user_access($user) === 'books';
}

function user_can_open(string $script, string $kind = ''): bool
{
    $script = basename($script);
    $plannerScripts = ['planner.php', 'planner_notes.php', 'planner_goals.php', 'planner_budget.php', 'planner_calendar.php'];
    if (in_array($script, $plannerScripts, true)) {
        return company_planner_enabled() && is_desk_admin();
    }
    if ($script === 'branches.php') {
        return company_branches_enabled() && is_desk_admin();
    }
    if ($script === 'notify_action.php' || $script === 'activities.php') {
        return is_desk_admin();
    }
    $pnlScripts = ['pnl.php', 'pnl_entries.php', 'pnl_savings.php', 'pnl_banking.php'];
    if (in_array($script, $pnlScripts, true)) {
        return company_pnl_enabled() && is_desk_admin();
    }
    if (in_array($script, ['search.php', 'search_api.php'], true)) {
        return true;
    }
    if (in_array($script, ['stock.php', 'sale.php', 'stock_search.php'], true)) {
        if (!company_stock_enabled()) {
            return false;
        }
        if ($script === 'sale.php') {
            return user_can_feature('sale') && user_can_kind('invoice');
        }
        return user_can_feature('stock');
    }
    if (is_desk_admin()) {
        return true;
    }
    $adminOnly = ['settings.php', 'reports.php', 'branding.php'];
    if (in_array($script, $adminOnly, true)) {
        return false;
    }
    $kindScripts = ['documents.php', 'document_new.php', 'document_view.php', 'document_email.php', 'document_action.php', 'document_download.php', 'document_pdf.php'];
    if (in_array($script, $kindScripts, true)) {
        if ($kind === '') {
            return true;
        }
        return user_can_kind($kind);
    }
    if ($script === 'letter_docx.php') {
        return user_can_kind('letter');
    }
    if ($script === 'export.php') {
        return $kind === '' || user_can_kind($kind);
    }
    if ($script === 'clients.php') {
        return user_can_feature('clients');
    }
    if ($script === 'debtors.php') {
        return user_can_feature('debtors');
    }
    if ($script === 'creditors.php') {
        return user_can_feature('creditors');
    }
    if ($script === 'desk_mail.php') {
        return user_can_feature('email');
    }
    return true;
}

function attempt_login(string $email, string $password): bool
{
    login_fail_reason('');
    $user = db_one('SELECT * FROM users WHERE email = ?', 's', [strtolower($email)]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    if (($user['role'] ?? '') !== 'platform' && (($user['status'] ?? 'live') === 'suspended')) {
        login_fail_reason('This login is suspended. Ask your company admin to restore it.');
        return false;
    }
    $cid = (int) ($user['company_id'] ?? 0);
    if (($user['role'] ?? '') !== 'platform' && $cid > 0) {
        $co = db_one('SELECT status FROM companies WHERE id = ?', 'i', [$cid]);
        if ($co && (($co['status'] ?? '') === 'suspended')) {
            login_fail_reason('This company desk is suspended. Contact Vellisys.');
            return false;
        }
    }
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['company_id'] = $cid;
    $_SESSION['role'] = $user['role'] ?? 'member';
    if (function_exists('touch_user_seen')) {
        touch_user_seen((int) $user['id'], true);
    }
    return true;
}

function remember_login(bool $remember): void
{
    $lifetime = $remember ? 60 * 60 * 24 * 30 : 0;
    $_SESSION['remember'] = $remember ? 1 : 0;
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(), [
        'expires' => $lifetime > 0 ? time() + $lifetime : 0,
        'path' => $params['path'] ?: '/',
        'secure' => folio_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
