<?php
declare(strict_types=1);

/**
 * Detachable desk modules.
 *
 * Safety rules:
 * - Live/onboarded companies with no company_modules JSON keep today’s behaviour
 *   (modules are inferred from enabled_kinds + stock/planner/pnl flags).
 * - Testing desks always get every module.
 * - Saving modules writes both company_modules and the existing flag columns
 *   so older code paths stay accurate.
 */

/** @return array<string, array{label:string,blurb:string,group:string}> */
function company_module_catalog(): array
{
    return [
        'quotes' => [
            'label' => 'Quotations',
            'blurb' => 'Create and send quotations.',
            'group' => 'books',
        ],
        'invoices' => [
            'label' => 'Invoices',
            'blurb' => 'Issue invoices to clients.',
            'group' => 'books',
        ],
        'receipts' => [
            'label' => 'Receipts',
            'blurb' => 'Record money received.',
            'group' => 'books',
        ],
        'letters' => [
            'label' => 'Letters & delivery',
            'blurb' => 'Headed letters, delivery notes and custom documents.',
            'group' => 'books',
        ],
        'credit' => [
            'label' => 'Credit control',
            'blurb' => 'Debtors, creditors and balance reminders.',
            'group' => 'accounts',
        ],
        'stock' => [
            'label' => 'Stock & till',
            'blurb' => 'Stock list, Sale till and Purchases / restock.',
            'group' => 'ops',
        ],
        'till_day' => [
            'label' => 'Open / close day',
            'blurb' => 'Till float on Sale. Only applies when Stock & till is on.',
            'group' => 'ops',
        ],
        'planner' => [
            'label' => 'Planner',
            'blurb' => 'Notes, budget and calendar for the company admin.',
            'group' => 'ops',
        ],
        'pnl' => [
            'label' => 'Profit & Loss',
            'blurb' => 'P&L bookkeeping, refunds and return notes.',
            'group' => 'insights',
        ],
        'branches' => [
            'label' => 'Branches',
            'blurb' => 'Multi-location desks. Still requires Business or Pro plan.',
            'group' => 'ops',
        ],
    ];
}

/** @return list<string> */
function company_module_keys(): array
{
    return array_keys(company_module_catalog());
}

function company_is_testing(?array $company = null): bool
{
    $company = $company ?? (function_exists('current_company') ? current_company() : null);
    return $company ? !empty($company['testing_mode']) : false;
}

/** @return list<string> */
function company_modules_all(): array
{
    return company_module_keys();
}

/**
 * Infer modules from today’s flags so existing desks keep the same portal.
 *
 * @return list<string>
 */
function company_modules_infer(?array $company = null): array
{
    $company = $company ?? (function_exists('current_company') ? current_company() : null);
    if (!$company) {
        return ['quotes', 'invoices', 'receipts', 'letters', 'credit'];
    }
    // Read kinds directly — do not call company_enabled_kinds() (avoids recursion
    // if that helper later consults modules).
    $kinds = function_exists('parse_enabled_kinds')
        ? parse_enabled_kinds($company['enabled_kinds'] ?? '')
        : ['quotation', 'invoice', 'receipt', 'letter'];
    $out = [];
    if (in_array('quotation', $kinds, true)) {
        $out[] = 'quotes';
    }
    if (in_array('invoice', $kinds, true)) {
        $out[] = 'invoices';
    }
    if (in_array('receipt', $kinds, true)) {
        $out[] = 'receipts';
    }
    if (array_intersect(['letter', 'delivery', 'custom'], $kinds) !== []) {
        $out[] = 'letters';
    }
    // Legacy desks always had debtors/creditors — keep them on when inferring.
    $out[] = 'credit';
    if ((int) ($company['stock_enabled'] ?? 0) === 1) {
        $out[] = 'stock';
        if ((int) ($company['till_day_enabled'] ?? 0) === 1) {
            $out[] = 'till_day';
        }
    }
    if ((int) ($company['planner_enabled'] ?? 0) === 1) {
        $out[] = 'planner';
    }
    if ((int) ($company['pnl_enabled'] ?? 0) === 1) {
        $out[] = 'pnl';
    }
    if (function_exists('plan_includes_branches') && plan_includes_branches((string) ($company['plan'] ?? 'sme'))) {
        $out[] = 'branches';
    }
    return array_values(array_unique($out));
}

/**
 * @return list<string>
 */
function parse_company_modules(mixed $raw): array
{
    if (is_array($raw)) {
        $list = $raw;
    } else {
        $list = json_decode((string) $raw, true);
    }
    if (!is_array($list)) {
        return [];
    }
    $allow = company_module_keys();
    $out = [];
    foreach ($list as $key) {
        $key = (string) $key;
        if (in_array($key, $allow, true) && !in_array($key, $out, true)) {
            $out[] = $key;
        }
    }
    return $out;
}

/**
 * Effective modules for a desk.
 * Testing → everything. Stored JSON → that list. Else infer (no change for live desks).
 *
 * @return list<string>
 */
function company_modules(?array $company = null): array
{
    $company = $company ?? (function_exists('current_company') ? current_company() : null);
    if (!$company) {
        return company_modules_infer(null);
    }
    if (company_is_testing($company)) {
        return company_modules_all();
    }
    $stored = parse_company_modules($company['company_modules'] ?? null);
    if ($stored !== []) {
        // till_day only makes sense with stock
        if (in_array('till_day', $stored, true) && !in_array('stock', $stored, true)) {
            $stored = array_values(array_diff($stored, ['till_day']));
        }
        return $stored;
    }
    return company_modules_infer($company);
}

function company_has_module(string $module, ?array $company = null): bool
{
    return in_array($module, company_modules($company), true);
}

function company_modules_stored(?array $company = null): bool
{
    $company = $company ?? (function_exists('current_company') ? current_company() : null);
    if (!$company) {
        return false;
    }
    return parse_company_modules($company['company_modules'] ?? null) !== [];
}

/**
 * Map modules → document kinds (expense always remains available separately).
 *
 * @param list<string> $modules
 * @return list<string>
 */
function company_modules_to_kinds(array $modules): array
{
    $kinds = [];
    if (in_array('quotes', $modules, true)) {
        $kinds[] = 'quotation';
    }
    if (in_array('invoices', $modules, true)) {
        $kinds[] = 'invoice';
    }
    if (in_array('receipts', $modules, true)) {
        $kinds[] = 'receipt';
    }
    if (in_array('letters', $modules, true)) {
        $kinds[] = 'letter';
        $kinds[] = 'delivery';
        $kinds[] = 'custom';
    }
    return $kinds !== [] ? $kinds : ['invoice', 'receipt'];
}

/**
 * @param list<string> $modules
 * @return array{enabled_kinds:string,stock_enabled:int,till_day_enabled:int,planner_enabled:int,pnl_enabled:int,company_modules:string}
 */
function company_modules_flag_payload(array $modules, string $plan = 'sme', ?array $previous = null): array
{
    $modules = parse_company_modules($modules);
    if (in_array('till_day', $modules, true) && !in_array('stock', $modules, true)) {
        $modules = array_values(array_diff($modules, ['till_day']));
    }
    $kinds = company_modules_to_kinds($modules);
    $stock = in_array('stock', $modules, true) ? 1 : 0;
    $till = ($stock === 1 && in_array('till_day', $modules, true)) ? 1 : 0;
    $plannerWanted = in_array('planner', $modules, true);
    $pnlWanted = in_array('pnl', $modules, true);
    // Honour plan auto-on helpers when present, but never force off an explicit module choice.
    $plannerOn = $plannerWanted ? 1 : 0;
    $pnlOn = $pnlWanted ? 1 : 0;
    if (function_exists('planner_resolve_enabled') && $plannerWanted) {
        $plannerOn = planner_resolve_enabled($plan, true, $previous);
    }
    if (function_exists('pnl_resolve_enabled') && $pnlWanted) {
        $pnlOn = pnl_resolve_enabled($plan, true, $previous);
    }
    // Branches stay plan-gated; module is recorded for clarity / future UI.
    if (in_array('branches', $modules, true) && function_exists('plan_includes_branches') && !plan_includes_branches($plan)) {
        $modules = array_values(array_diff($modules, ['branches']));
    }
    return [
        'enabled_kinds' => json_encode($kinds, JSON_UNESCAPED_UNICODE) ?: '["invoice","receipt"]',
        'stock_enabled' => $stock,
        'till_day_enabled' => $till,
        'planner_enabled' => $plannerOn,
        'pnl_enabled' => $pnlOn,
        'company_modules' => json_encode(array_values($modules), JSON_UNESCAPED_UNICODE) ?: '[]',
    ];
}

/** @return list<string> */
function posted_company_modules(): array
{
    $posted = $_POST['company_modules'] ?? null;
    if (!is_array($posted)) {
        return [];
    }
    return parse_company_modules($posted);
}

/**
 * Persist every module + legacy flags so a desk that just left testing keeps
 * full access until an admin detaches modules deliberately.
 */
function company_modules_materialize_all(int $companyId, ?array $company = null, string $plan = ''): void
{
    if ($companyId < 1 || !function_exists('db_exec')) {
        return;
    }
    $company = $company ?? (function_exists('db_one') ? db_one('SELECT * FROM companies WHERE id = ?', 'i', [$companyId]) : null);
    if (!$company) {
        return;
    }
    $plan = $plan !== '' ? $plan : (string) ($company['plan'] ?? 'sme');
    $flags = company_modules_flag_payload(company_modules_all(), $plan, $company);
    try {
        if (function_exists('db_has_column') && db_has_column(db(), 'companies', 'company_modules')) {
            db_exec(
                'UPDATE companies SET enabled_kinds=?, stock_enabled=?, till_day_enabled=?, planner_enabled=?, pnl_enabled=?, company_modules=? WHERE id=?',
                'siiiisi',
                [
                    $flags['enabled_kinds'],
                    $flags['stock_enabled'],
                    $flags['till_day_enabled'],
                    $flags['planner_enabled'],
                    $flags['pnl_enabled'],
                    $flags['company_modules'],
                    $companyId,
                ]
            );
            return;
        }
    } catch (Throwable $e) {
        // Fall through to flag-only write.
    }
    try {
        db_exec(
            'UPDATE companies SET enabled_kinds=?, stock_enabled=?, till_day_enabled=?, planner_enabled=?, pnl_enabled=? WHERE id=?',
            'siiiii',
            [
                $flags['enabled_kinds'],
                $flags['stock_enabled'],
                $flags['till_day_enabled'],
                $flags['planner_enabled'],
                $flags['pnl_enabled'],
                $companyId,
            ]
        );
    } catch (Throwable $e) {
        db_exec(
            'UPDATE companies SET enabled_kinds=?, stock_enabled=?, planner_enabled=?, pnl_enabled=? WHERE id=?',
            'siiii',
            [
                $flags['enabled_kinds'],
                $flags['stock_enabled'],
                $flags['planner_enabled'],
                $flags['pnl_enabled'],
                $companyId,
            ]
        );
    }
}

function render_company_modules_fields(?array $company = null, string $name = 'company_modules[]'): void
{
    $company = $company ?? [];
    $testing = company_is_testing($company);
    $selected = company_modules($company);
    $catalog = company_module_catalog();
    $groups = [
        'books' => 'Documents',
        'accounts' => 'Accounts',
        'ops' => 'Operations',
        'insights' => 'Insights',
    ];
    ?>
    <div class="company-modules" data-company-modules style="margin-top:16px">
      <h3 style="margin:0 0 6px;font-size:1rem">Desk modules</h3>
      <?php if ($testing): ?>
        <p class="hint" style="margin:0 0 12px">This desk is in <strong>testing</strong> — every module is on so they can try the full product. After onboard, tick only what they will use.</p>
      <?php else: ?>
        <p class="hint" style="margin:0 0 12px">Detach what this client will not use. Core desk (home, clients, expenses, email, reports, settings) always stays. Existing live desks keep their current setup until you change ticks here.</p>
      <?php endif; ?>
      <?php foreach ($groups as $groupKey => $groupLabel): ?>
        <p class="hint" style="margin:12px 0 6px;font-weight:700"><?= h($groupLabel) ?></p>
        <div class="form-grid" style="gap:8px">
          <?php foreach ($catalog as $key => $info):
              if (($info['group'] ?? '') !== $groupKey) {
                  continue;
              }
              $on = in_array($key, $selected, true);
              ?>
            <label class="check" for="mod_<?= h($key) ?>" style="align-items:flex-start">
              <input
                id="mod_<?= h($key) ?>"
                type="checkbox"
                name="<?= h($name) ?>"
                value="<?= h($key) ?>"
                <?= $on ? 'checked' : '' ?>
                <?= $testing ? 'disabled' : '' ?>
                data-company-module="<?= h($key) ?>"
              >
              <span>
                <strong><?= h($info['label']) ?></strong>
                <span class="hint" style="display:block;margin:2px 0 0"><?= h($info['blurb']) ?></span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <?php if ($testing): ?>
        <?php foreach ($selected as $key): ?>
          <input type="hidden" name="<?= h($name) ?>" value="<?= h($key) ?>">
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php
}
