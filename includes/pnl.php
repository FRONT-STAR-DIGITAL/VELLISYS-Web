<?php
declare(strict_types=1);

function plan_includes_pnl(string $plan): bool
{
    return normalize_company_plan($plan) === 'office';
}

function company_pnl_enabled(?array $company = null): bool
{
    $company = $company ?? current_company();
    if (!$company) {
        return false;
    }
    return (int) ($company['pnl_enabled'] ?? 0) === 1;
}

function pnl_resolve_enabled(string $plan, bool $checkboxOn, ?array $previous = null): int
{
    $plan = normalize_company_plan($plan);
    $prevPlan = normalize_company_plan((string) ($previous['plan'] ?? ''));
    if (plan_includes_pnl($plan) && !plan_includes_pnl($prevPlan)) {
        return 1;
    }
    if ($previous === null && plan_includes_pnl($plan)) {
        return 1;
    }
    return $checkboxOn ? 1 : 0;
}

function require_pnl(): array
{
    $user = require_desk_admin();
    if (!company_pnl_enabled()) {
        flash('Profit & Loss is not on for this desk. Ask Vellisys if you need Pro, or ask an admin to enable it.', 'err');
        redirect('dashboard.php');
    }
    return $user;
}

function pnl_view_branch(): array
{
    $user = current_user() ?: [];
    $admin = is_desk_admin($user);
    $enabled = function_exists('company_branches_enabled') && company_branches_enabled();
    if (!$enabled) {
        return ['enabled' => false, 'all' => true, 'id' => 0, 'label' => '', 'admin' => $admin];
    }
    if (!$admin) {
        $id = (int) ($user['branch_id'] ?? 0);
        return [
            'enabled' => true,
            'all' => false,
            'id' => $id,
            'label' => company_branch_label($id),
            'admin' => false,
        ];
    }
    $raw = (string) ($_GET['branch'] ?? 'all');
    if ($raw === '' || $raw === 'all') {
        return ['enabled' => true, 'all' => true, 'id' => 0, 'label' => 'Every branch', 'admin' => true];
    }
    $id = (int) $raw;
    if ($id > 0 && function_exists('normalize_branch_id') && !normalize_branch_id($id)) {
        $id = 0;
    }
    return [
        'enabled' => true,
        'all' => false,
        'id' => $id,
        'label' => company_branch_label($id),
        'admin' => true,
    ];
}

function pnl_branch_keep(): array
{
    $scope = pnl_view_branch();
    if (!$scope['enabled']) {
        return [];
    }
    return ['branch' => $scope['all'] ? 'all' : (string) $scope['id']];
}

function pnl_branch_sql(string $column): array
{
    $scope = pnl_view_branch();
    if (!$scope['enabled'] || $scope['all']) {
        return ['', '', []];
    }
    $id = (int) $scope['id'];
    if ($id < 1) {
        return [" AND ({$column} IS NULL OR {$column} = 0)", '', []];
    }
    return [" AND {$column} = ?", 'i', [$id]];
}

function pnl_posted_branch_id(): int
{
    $scope = pnl_view_branch();
    $user = current_user() ?: [];
    if ($scope['enabled'] && !$scope['admin']) {
        return (int) ($user['branch_id'] ?? 0);
    }
    if (array_key_exists('branch_id', $_POST)) {
        $raw = (int) post('branch_id');
        if ($raw < 1) {
            return 0;
        }
        return function_exists('normalize_branch_id') ? (int) (normalize_branch_id($raw) ?? 0) : $raw;
    }
    if ($scope['enabled'] && !$scope['all']) {
        return (int) $scope['id'];
    }
    return (int) ($user['branch_id'] ?? 0);
}

function pnl_redirect(string $script, array $extra = []): void
{
    $p = period_range();
    $qs = array_merge(pnl_branch_keep(), [
        'range' => $p['preset'],
        'from' => $p['from'],
        'to' => $p['to'],
        'bucket' => pnl_report_bucket(),
    ], $extra);
    redirect($script . '?' . http_build_query($qs));
}

function render_pnl_branch_chips(string $action, array $keep = []): void
{
    $scope = pnl_view_branch();
    if (!$scope['enabled'] || !$scope['admin']) {
        return;
    }
    $p = period_range();
    $base = array_merge($keep, [
        'range' => $p['preset'],
        'from' => $p['from'],
        'to' => $p['to'],
        'bucket' => pnl_report_bucket(),
    ]);
    ?>
  <div class="filter-chips" style="margin:0 0 12px">
    <a class="chip<?= $scope['all'] ? ' is-on' : '' ?>" href="<?= h(url($action . '?' . http_build_query(array_merge($base, ['branch' => 'all'])))) ?>">Every branch</a>
    <?php foreach (company_all_branches() as $b):
        $bid = (int) ($b['id'] ?? 0);
        $on = !$scope['all'] && (int) $scope['id'] === $bid;
        ?>
      <a class="chip<?= $on ? ' is-on' : '' ?>" href="<?= h(url($action . '?' . http_build_query(array_merge($base, ['branch' => (string) $bid])))) ?>"><?= h((string) $b['name']) ?></a>
    <?php endforeach; ?>
  </div>
    <?php
}

function render_pnl_branch_field(?int $selected = null, string $id = 'branch_id'): void
{
    if (!function_exists('company_branches_enabled') || !company_branches_enabled()) {
        echo '<input type="hidden" name="branch_id" value="0">';
        return;
    }
    $scope = pnl_view_branch();
    if (!$scope['admin']) {
        echo '<input type="hidden" name="branch_id" value="' . (int) $scope['id'] . '">';
        return;
    }
    if (!$scope['all']) {
        echo '<input type="hidden" name="branch_id" value="' . (int) $scope['id'] . '">';
        return;
    }
    $selected = $selected ?? (int) (current_user()['branch_id'] ?? 0);
    ?>
      <label for="<?= h($id) ?>">Branch</label>
      <select id="<?= h($id) ?>" name="branch_id" required>
        <?php render_branch_options($selected); ?>
      </select>
    <?php
}

function pnl_branch_name(mixed $id): string
{
    return company_branch_label((int) ($id ?? 0));
}

function pnl_show_branch_col(): bool
{
    $scope = pnl_view_branch();
    return !empty($scope['enabled']) && !empty($scope['all']);
}

function pnl_branch_lede(): string
{
    $scope = pnl_view_branch();
    if (empty($scope['enabled'])) {
        return '';
    }
    if (!empty($scope['all'])) {
        return ' Combined across every branch. Each location keeps its own savings, bank accounts and ledger, so a branch can run a different line of business from Head office.';
    }
    return ' Books for ' . (string) $scope['label'] . ' only. Other branches keep separate figures.';
}

function pnl_href(string $script, array $extra = []): string
{
    $p = period_range();
    $file = (string) strtok($script, '?');
    $existing = [];
    $qpos = strpos($script, '?');
    if ($qpos !== false) {
        parse_str((string) substr($script, $qpos + 1), $existing);
    }
    $qs = array_merge(pnl_branch_keep(), [
        'range' => $p['preset'],
        'from' => $p['from'],
        'to' => $p['to'],
        'bucket' => pnl_report_bucket(),
    ], $existing, $extra);
    return url($file . '?' . http_build_query($qs));
}

function pnl_income_categories(): array
{
    return ['Sales', 'Services', 'Interest', 'Other income', 'Supplier refund', 'Adjustment'];
}

function pnl_expense_categories(): array
{
    return array_values(array_unique(array_merge(expense_categories(), [
        'Cost of sales',
        'Customer refund',
        'Bank charges',
        'Depreciation',
        'Adjustment',
    ])));
}

function pnl_entry_save(array $data, int $id = 0): int
{
    $cid = current_company_id();
    $uid = (int) (current_user()['id'] ?? 0);
    $title = trim((string) ($data['title'] ?? ''));
    $kind = (string) ($data['kind'] ?? 'expense');
    if (!in_array($kind, ['income', 'expense'], true)) {
        $kind = 'expense';
    }
    $category = trim((string) ($data['category'] ?? 'General')) ?: 'General';
    $amount = (float) preg_replace('/[^0-9.\-]/', '', (string) ($data['amount'] ?? '0'));
    $date = trim((string) ($data['entry_date'] ?? today()));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = today();
    }
    $notes = trim((string) ($data['notes'] ?? ''));
    $branchId = array_key_exists('branch_id', $data) ? (int) $data['branch_id'] : pnl_posted_branch_id();
    if ($branchId < 0) {
        $branchId = 0;
    }
    if ($title === '') {
        throw new RuntimeException('Give this entry a title.');
    }
    if ($amount == 0.0) {
        throw new RuntimeException('Enter an amount.');
    }
    if ($id > 0) {
        $row = db_one('SELECT id FROM pnl_entries WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
        if (!$row) {
            throw new RuntimeException('Entry not found.');
        }
        db_exec(
            'UPDATE pnl_entries SET entry_date=?, kind=?, category=?, title=?, amount=?, notes=?, branch_id=? WHERE id=? AND company_id=?',
            'ssssdsiii',
            [$date, $kind, $category, $title, $amount, $notes !== '' ? $notes : null, $branchId, $id, $cid]
        );
        return $id;
    }
    return db_exec(
        'INSERT INTO pnl_entries (company_id, user_id, entry_date, kind, category, title, amount, notes, branch_id) VALUES (?,?,?,?,?,?,?,?,?)',
        'iissssdsi',
        [$cid, $uid, $date, $kind, $category, $title, $amount, $notes !== '' ? $notes : null, $branchId]
    );
}

function pnl_entry_delete(int $id): void
{
    db_exec('DELETE FROM pnl_entries WHERE id = ? AND company_id = ?', 'ii', [$id, current_company_id()]);
}

function pnl_entries(?string $kind = null): array
{
    $cid = current_company_id();
    [$extra, $types, $params] = period_sql('e.entry_date');
    [$bSql, $bTypes, $bArgs] = pnl_branch_sql('e.branch_id');
    $sql = 'SELECT e.* FROM pnl_entries e WHERE e.company_id = ?' . $extra . $bSql;
    $bind = 'i' . $types . $bTypes;
    $args = array_merge([$cid], $params, $bArgs);
    if ($kind === 'income' || $kind === 'expense') {
        $sql .= ' AND e.kind = ?';
        $bind .= 's';
        $args[] = $kind;
    }
    $sql .= ' ORDER BY e.entry_date DESC, e.id DESC';
    return db_all($sql, $bind, $args);
}

function pnl_refund_direction(array $doc): string
{
    $cat = strtolower((string) ($doc['expense_category'] ?? ''));
    if (in_array($cat, ['in', 'supplier', 'supplier_refund', 'refund_in'], true)) {
        return 'in';
    }
    if (in_array($cat, ['out', 'customer', 'customer_refund', 'refund_out'], true)) {
        return 'out';
    }
    $relatedId = (int) ($doc['related_id'] ?? 0);
    if ($relatedId > 0) {
        $rel = db_one('SELECT kind FROM documents WHERE id = ? AND company_id = ?', 'ii', [$relatedId, current_company_id()]);
        if ($rel && ($rel['kind'] ?? '') === 'expense') {
            return 'in';
        }
        if ($rel && ($rel['kind'] ?? '') === 'invoice') {
            return 'out';
        }
    }
    return 'out';
}

function pnl_return_direction(array $doc): string
{
    $cat = strtolower((string) ($doc['expense_category'] ?? ''));
    if (in_array($cat, ['in', 'supplier', 'supplier_return'], true)) {
        return 'in';
    }
    return 'out';
}

function pnl_cogs_amount(string $from, string $to): float
{
    $cid = current_company_id();
    $base = default_currency();
    [$bSql, $bTypes, $bArgs] = pnl_branch_sql('d.branch_id');
    $rows = db_all(
        "SELECT d.currency,
                COALESCE(SUM(ROUND(i.qty * COALESCE(s.buy_price, 0), 2)), 0) AS cogs
         FROM documents d
         JOIN document_items i ON i.document_id = d.id
         LEFT JOIN stock_items s ON s.id = i.stock_item_id AND s.company_id = d.company_id
         WHERE d.company_id = ? AND d.status = 'issued' AND d.kind = 'invoice'
           AND d.date >= ? AND d.date <= ?
           AND i.stock_item_id IS NOT NULL AND i.stock_item_id > 0" . $bSql . '
         GROUP BY d.id, d.currency',
        'iss' . $bTypes,
        array_merge([$cid, $from, $to], $bArgs)
    );
    $cogs = 0.0;
    foreach ($rows as $d) {
        $cogs += convert_money((float) $d['cogs'], doc_currency($d), $base);
    }
    return round($cogs, 2);
}

function pnl_doc_amount(array $doc): float
{
    $base = default_currency();
    if (($doc['kind'] ?? '') === 'receipt' || ($doc['kind'] ?? '') === 'refund') {
        $amt = (float) ($doc['allocated_amount'] ?? 0);
        if ($amt <= 0 && isset($doc['totals']['total'])) {
            $amt = (float) $doc['totals']['total'];
        }
        return convert_money($amt, doc_currency($doc), $base);
    }
    $net = (float) ($doc['totals']['net'] ?? 0);
    return convert_money($net, doc_currency($doc), $base);
}

function pnl_summary(): array
{
    $cid = current_company_id();
    [$extra, $types, $params] = period_sql('d.date');
    [$bSql, $bTypes, $bArgs] = pnl_branch_sql('d.branch_id');
    $scope = "d.company_id = ? AND d.status = 'issued'" . $extra . $bSql;
    $bind = 'i' . $types . $bTypes;
    $args = array_merge([$cid], $params, $bArgs);
    $base = default_currency();

    $invoices = attach_document_totals(db_all(
        "SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE {$scope} AND d.kind = 'invoice'",
        $bind,
        $args
    ));
    $expenses = attach_document_totals(db_all(
        "SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE {$scope} AND d.kind = 'expense'",
        $bind,
        $args
    ));
    $receipts = attach_document_totals(db_all(
        "SELECT d.*, p.name AS party_name, r.kind AS related_kind
         FROM documents d JOIN parties p ON p.id = d.party_id
         LEFT JOIN documents r ON r.id = d.related_id
         WHERE {$scope} AND d.kind = 'receipt'",
        $bind,
        $args
    ));
    $refunds = attach_document_totals(db_all(
        "SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE {$scope} AND d.kind = 'refund'",
        $bind,
        $args
    ));
    $returns = attach_document_totals(db_all(
        "SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE {$scope} AND d.kind = 'return_note'",
        $bind,
        $args
    ));

    $sales = 0.0;
    foreach ($invoices as $d) {
        $sales += convert_money((float) $d['totals']['net'], doc_currency($d), $base);
    }
    $costs = 0.0;
    $byExpenseCat = [];
    foreach ($expenses as $d) {
        $amt = convert_money((float) $d['totals']['net'], doc_currency($d), $base);
        $costs += $amt;
        $cat = (string) ($d['expense_category'] ?: 'Other');
        $byExpenseCat[$cat] = ($byExpenseCat[$cat] ?? 0) + $amt;
    }

    $refundOut = 0.0;
    $refundIn = 0.0;
    foreach ($refunds as $d) {
        $amt = pnl_doc_amount($d);
        if (pnl_refund_direction($d) === 'in') {
            $refundIn += $amt;
        } else {
            $refundOut += $amt;
        }
    }

    $manualIncome = 0.0;
    $manualExpense = 0.0;
    $byIncomeCat = ['Sales' => $sales];
    foreach (pnl_entries() as $row) {
        $amt = (float) $row['amount'];
        $cat = (string) ($row['category'] ?: 'General');
        if ($row['kind'] === 'income') {
            $manualIncome += $amt;
            $byIncomeCat[$cat] = ($byIncomeCat[$cat] ?? 0) + $amt;
        } else {
            $manualExpense += $amt;
            $byExpenseCat[$cat] = ($byExpenseCat[$cat] ?? 0) + $amt;
        }
    }
    if ($refundIn > 0) {
        $byIncomeCat['Supplier refund'] = ($byIncomeCat['Supplier refund'] ?? 0) + $refundIn;
    }
    if ($refundOut > 0) {
        $byExpenseCat['Customer refund'] = ($byExpenseCat['Customer refund'] ?? 0) + $refundOut;
    }
    arsort($byIncomeCat);
    arsort($byExpenseCat);

    $cashIn = 0.0;
    $cashOut = 0.0;
    foreach ($receipts as $d) {
        $amt = pnl_doc_amount($d);
        if (($d['related_kind'] ?? '') === 'expense') {
            $cashOut += $amt;
        } else {
            $cashIn += $amt;
        }
    }
    $cashIn += $refundIn;
    $cashOut += $refundOut;

    $incomeTotal = $sales + $manualIncome + $refundIn;
    $stockPurchases = (float) ($byExpenseCat['Stock'] ?? 0);
    $operatingCosts = max(0, $costs - $stockPurchases);
    $p = period_range();
    $from = $p['from'] !== '' ? $p['from'] : '1970-01-01';
    $to = $p['to'] !== '' ? $p['to'] : today();
    $cogs = pnl_cogs_amount($from, $to);
    $profit = round($sales - $cogs, 2);
    $expenseTotal = $operatingCosts + $manualExpense + $refundOut;
    $net = round($profit + $manualIncome + $refundIn - $expenseTotal, 2);

    return [
        'sales' => $sales,
        'cogs' => $cogs,
        'profit' => $profit,
        'stock_purchases' => $stockPurchases,
        'operating_costs' => $operatingCosts,
        'manual_income' => $manualIncome,
        'refund_in' => $refundIn,
        'refund_out' => $refundOut,
        'costs' => $costs,
        'manual_expense' => $manualExpense,
        'income_total' => $incomeTotal,
        'expense_total' => $expenseTotal,
        'net' => $net,
        'cash_in' => $cashIn,
        'cash_out' => $cashOut,
        'cash_net' => $cashIn - $cashOut,
        'by_income_cat' => $byIncomeCat,
        'by_expense_cat' => $byExpenseCat,
        'invoices' => $invoices,
        'expenses' => $expenses,
        'receipts' => $receipts,
        'refunds' => $refunds,
        'returns' => $returns,
        'entries' => pnl_entries(),
        'currency' => $base,
    ];
}

/**
 * Monthly performance series for the last 12 calendar months (company home currency).
 */
function pnl_chart_data(?array $summary = null): array
{
    $cid = current_company_id();
    $base = default_currency();
    $months = [];
    $income = [];
    $expense = [];
    $cash = [];
    $anchor = new DateTimeImmutable('first day of this month 00:00:00');
    for ($i = 11; $i >= 0; $i--) {
        $m = $anchor->modify("-{$i} months");
        $key = $m->format('Y-m');
        $months[] = $m->format('M Y');
        $income[$key] = 0.0;
        $expense[$key] = 0.0;
        $cash[$key] = 0.0;
    }
    $from = $anchor->modify('-11 months')->format('Y-m-01');
    $to = $anchor->modify('last day of this month')->format('Y-m-d');
    [$bSql, $bTypes, $bArgs] = pnl_branch_sql('d.branch_id');

    $docs = attach_document_totals(db_all(
        "SELECT d.*, r.kind AS related_kind
         FROM documents d
         LEFT JOIN documents r ON r.id = d.related_id
         WHERE d.company_id = ? AND d.status = 'issued'
           AND d.date >= ? AND d.date <= ?
           AND d.kind IN ('invoice','expense','receipt','refund')" . $bSql,
        'iss' . $bTypes,
        array_merge([$cid, $from, $to], $bArgs)
    ));
    foreach ($docs as $d) {
        $key = substr((string) $d['date'], 0, 7);
        if (!isset($income[$key])) {
            continue;
        }
        $amt = pnl_doc_amount($d);
        $kind = (string) ($d['kind'] ?? '');
        if ($kind === 'invoice') {
            $income[$key] += $amt;
        } elseif ($kind === 'expense') {
            $expense[$key] += $amt;
        } elseif ($kind === 'refund') {
            if (pnl_refund_direction($d) === 'in') {
                $income[$key] += $amt;
                $cash[$key] += $amt;
            } else {
                $expense[$key] += $amt;
                $cash[$key] -= $amt;
            }
        } elseif ($kind === 'receipt') {
            if (($d['related_kind'] ?? '') === 'expense') {
                $cash[$key] -= $amt;
            } else {
                $cash[$key] += $amt;
            }
        }
    }

    [$eSql, $eTypes, $eArgs] = pnl_branch_sql('branch_id');
    $ledger = db_all(
        'SELECT entry_date, kind, amount FROM pnl_entries
         WHERE company_id = ? AND entry_date >= ? AND entry_date <= ?' . $eSql,
        'iss' . $eTypes,
        array_merge([$cid, $from, $to], $eArgs)
    );
    foreach ($ledger as $row) {
        $key = substr((string) ($row['entry_date'] ?? ''), 0, 7);
        if (!isset($income[$key])) {
            continue;
        }
        $amt = (float) $row['amount'];
        if (($row['kind'] ?? '') === 'income') {
            $income[$key] += $amt;
            $cash[$key] += $amt;
        } else {
            $expense[$key] += $amt;
            $cash[$key] -= $amt;
        }
    }

    $incomeVals = [];
    $expenseVals = [];
    $netVals = [];
    $cashVals = [];
    foreach (array_keys($income) as $key) {
        $incomeVals[] = round($income[$key], 2);
        $expenseVals[] = round($expense[$key], 2);
        $netVals[] = round($income[$key] - $expense[$key], 2);
        $cashVals[] = round($cash[$key], 2);
    }

    $summary = $summary ?? pnl_summary();
    $incomeCats = $summary['by_income_cat'] ?? [];
    $expenseCats = $summary['by_expense_cat'] ?? [];

    return [
        'months' => $months,
        'income' => $incomeVals,
        'expense' => $expenseVals,
        'net' => $netVals,
        'cash' => $cashVals,
        'incomeCatLabels' => array_map('strval', array_keys($incomeCats)),
        'incomeCatValues' => array_map(static fn ($v) => round((float) $v, 2), array_values($incomeCats)),
        'expenseCatLabels' => array_map('strval', array_keys($expenseCats)),
        'expenseCatValues' => array_map(static fn ($v) => round((float) $v, 2), array_values($expenseCats)),
        'currency' => $base,
    ];
}

function pnl_savings_get(): array
{
    $cid = current_company_id();
    $scope = pnl_view_branch();
    $saved = pnl_savings_balance();
    if ($scope['enabled'] && $scope['all']) {
        $targets = [];
        foreach (company_all_branches() as $b) {
            $id = (int) ($b['id'] ?? 0);
            $row = db_one('SELECT * FROM pnl_savings WHERE company_id = ? AND branch_id = ?', 'ii', [$cid, $id]);
            $targets[] = [
                'branch_id' => $id,
                'branch_name' => (string) ($b['name'] ?? pnl_branch_name($id)),
                'target_amount' => (float) ($row['target_amount'] ?? 0),
                'saved_amount' => pnl_savings_balance($id),
                'note' => (string) ($row['note'] ?? ''),
            ];
        }
        return [
            'target_amount' => 0.0,
            'saved_amount' => $saved,
            'note' => '',
            'all' => true,
            'targets' => $targets,
        ];
    }
    $bid = $scope['enabled'] ? (int) $scope['id'] : 0;
    $row = db_one('SELECT * FROM pnl_savings WHERE company_id = ? AND branch_id = ?', 'ii', [$cid, $bid]);
    return [
        'target_amount' => (float) ($row['target_amount'] ?? 0),
        'saved_amount' => $saved,
        'note' => (string) ($row['note'] ?? ''),
        'all' => false,
        'branch_id' => $bid,
    ];
}

function pnl_savings_balance(?int $branchId = null): float
{
    $cid = current_company_id();
    $sql = "SELECT COALESCE(SUM(CASE WHEN kind = 'deposit' THEN amount ELSE 0 END), 0)
                   - COALESCE(SUM(CASE WHEN kind = 'withdraw' THEN amount ELSE 0 END), 0) AS bal
            FROM pnl_savings_moves WHERE company_id = ?";
    $bind = 'i';
    $args = [$cid];
    if ($branchId !== null) {
        $sql .= ' AND branch_id = ?';
        $bind .= 'i';
        $args[] = $branchId;
    } else {
        [$bSql, $bTypes, $bArgs] = pnl_branch_sql('branch_id');
        $sql .= $bSql;
        $bind .= $bTypes;
        $args = array_merge($args, $bArgs);
    }
    try {
        $row = db_one($sql, $bind, $args);
        return round((float) ($row['bal'] ?? 0), 2);
    } catch (Throwable $e) {
        $row = db_one('SELECT SUM(saved_amount) AS bal FROM pnl_savings WHERE company_id = ?', 'i', [$cid]);
        return round((float) ($row['bal'] ?? 0), 2);
    }
}

function pnl_savings_save(array $data): void
{
    $cid = current_company_id();
    $bid = array_key_exists('branch_id', $data) ? (int) $data['branch_id'] : pnl_posted_branch_id();
    $target = max(0, round((float) ($data['target_amount'] ?? 0), 2));
    $saved = pnl_savings_balance($bid);
    $note = mb_substr(trim((string) ($data['note'] ?? '')), 0, 500);
    $now = desk_now()->format('Y-m-d H:i:s');
    db_exec(
        'INSERT INTO pnl_savings (company_id, branch_id, target_amount, saved_amount, note, updated_at) VALUES (?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE target_amount=VALUES(target_amount), saved_amount=VALUES(saved_amount), note=VALUES(note), updated_at=VALUES(updated_at)',
        'iiddss',
        [$cid, $bid, $target, $saved, $note, $now]
    );
}

function pnl_savings_sync_total(?int $branchId = null): void
{
    $cid = current_company_id();
    $bid = $branchId ?? pnl_posted_branch_id();
    $saved = pnl_savings_balance($bid);
    $now = desk_now()->format('Y-m-d H:i:s');
    db_exec(
        'INSERT INTO pnl_savings (company_id, branch_id, target_amount, saved_amount, note, updated_at) VALUES (?,?,0,?,?,?)
         ON DUPLICATE KEY UPDATE saved_amount=VALUES(saved_amount), updated_at=VALUES(updated_at)',
        'iidss',
        [$cid, $bid, $saved, '', $now]
    );
}

function pnl_valid_date(?string $date): string
{
    $date = trim((string) $date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return today();
    }
    return $date;
}

function pnl_savings_moves(): array
{
    $cid = current_company_id();
    [$extra, $types, $params] = period_sql('m.move_date');
    [$bSql, $bTypes, $bArgs] = pnl_branch_sql('m.branch_id');
    return db_all(
        'SELECT m.* FROM pnl_savings_moves m WHERE m.company_id = ?' . $extra . $bSql . ' ORDER BY m.move_date DESC, m.id DESC',
        'i' . $types . $bTypes,
        array_merge([$cid], $params, $bArgs)
    );
}

function pnl_savings_move_save(array $data, int $id = 0): int
{
    $cid = current_company_id();
    $uid = (int) (current_user()['id'] ?? 0);
    $bid = array_key_exists('branch_id', $data) ? (int) $data['branch_id'] : pnl_posted_branch_id();
    if ($bid < 0) {
        $bid = 0;
    }
    $kind = (string) ($data['kind'] ?? 'deposit');
    if (!in_array($kind, ['deposit', 'withdraw'], true)) {
        $kind = 'deposit';
    }
    $amount = round((float) preg_replace('/[^0-9.\-]/', '', (string) ($data['amount'] ?? '0')), 2);
    $date = pnl_valid_date($data['move_date'] ?? today());
    $person = mb_substr(trim((string) ($data['person_name'] ?? '')), 0, 160);
    $purpose = mb_substr(trim((string) ($data['purpose'] ?? '')), 0, 255);
    $notes = mb_substr(trim((string) ($data['notes'] ?? '')), 0, 500);
    if ($amount <= 0) {
        throw new RuntimeException('Enter an amount greater than zero.');
    }
    if ($person === '') {
        throw new RuntimeException($kind === 'withdraw' ? 'Name the person withdrawing.' : 'Name the depositor.');
    }
    if ($kind === 'withdraw' && $purpose === '') {
        throw new RuntimeException('Give a purpose for the withdrawal.');
    }
    $current = pnl_savings_balance($bid);
    $prior = 0.0;
    if ($id > 0) {
        $row = db_one('SELECT * FROM pnl_savings_moves WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
        if (!$row) {
            throw new RuntimeException('That savings line was not found.');
        }
        $prior = (string) $row['kind'] === 'withdraw' ? -((float) $row['amount']) : (float) $row['amount'];
        if (!array_key_exists('branch_id', $data)) {
            $bid = (int) ($row['branch_id'] ?? 0);
            $current = pnl_savings_balance($bid);
        }
    }
    $next = $current - $prior + ($kind === 'withdraw' ? -$amount : $amount);
    if ($next < -0.009) {
        throw new RuntimeException('There is not enough set aside for that withdrawal.');
    }
    if ($id > 0) {
        db_exec(
            'UPDATE pnl_savings_moves SET kind=?, move_date=?, amount=?, person_name=?, purpose=?, notes=?, branch_id=? WHERE id=? AND company_id=?',
            'ssdsssiii',
            [$kind, $date, $amount, $person, $purpose, $notes, $bid, $id, $cid]
        );
        pnl_savings_sync_total($bid);
        return $id;
    }
    $newId = db_exec(
        'INSERT INTO pnl_savings_moves (company_id, branch_id, kind, move_date, amount, person_name, purpose, notes, user_id) VALUES (?,?,?,?,?,?,?,?,?)',
        'iissdsssi',
        [$cid, $bid, $kind, $date, $amount, $person, $purpose, $notes, $uid]
    );
    pnl_savings_sync_total($bid);
    return $newId;
}

function pnl_savings_move_delete(int $id): void
{
    $cid = current_company_id();
    $row = db_one('SELECT branch_id FROM pnl_savings_moves WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
    db_exec('DELETE FROM pnl_savings_moves WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
    pnl_savings_sync_total((int) ($row['branch_id'] ?? 0));
}

function bank_accounts(bool $activeOnly = false): array
{
    [$bSql, $bTypes, $bArgs] = pnl_branch_sql('branch_id');
    $sql = 'SELECT * FROM bank_accounts WHERE company_id = ?' . $bSql;
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY is_active DESC, name';
    return db_all($sql, 'i' . $bTypes, array_merge([current_company_id()], $bArgs));
}

function bank_account(int $id): ?array
{
    return db_one('SELECT * FROM bank_accounts WHERE id = ? AND company_id = ?', 'ii', [$id, current_company_id()]) ?: null;
}

function bank_account_save(array $data, int $id = 0): int
{
    $cid = current_company_id();
    $name = mb_substr(trim((string) ($data['name'] ?? '')), 0, 120);
    $bank = mb_substr(trim((string) ($data['bank_name'] ?? '')), 0, 160);
    $number = mb_substr(trim((string) ($data['account_number'] ?? '')), 0, 80);
    $opening = round((float) preg_replace('/[^0-9.\-]/', '', (string) ($data['opening_balance'] ?? '0')), 2);
    $notes = mb_substr(trim((string) ($data['notes'] ?? '')), 0, 500);
    $active = !empty($data['is_active']) ? 1 : 0;
    $bid = array_key_exists('branch_id', $data) ? (int) $data['branch_id'] : pnl_posted_branch_id();
    if ($bid < 0) {
        $bid = 0;
    }
    if ($name === '') {
        throw new RuntimeException('Name this bank account.');
    }
    if ($id > 0) {
        $row = bank_account($id);
        if (!$row) {
            throw new RuntimeException('Bank account not found.');
        }
        if (!bank_account_allowed($row)) {
            throw new RuntimeException('That account belongs to another branch.');
        }
        db_exec(
            'UPDATE bank_accounts SET name=?, bank_name=?, account_number=?, opening_balance=?, notes=?, is_active=?, branch_id=? WHERE id=? AND company_id=?',
            'sssdsiiii',
            [$name, $bank, $number, $opening, $notes, $active, $bid, $id, $cid]
        );
        db_exec('UPDATE bank_transactions SET branch_id=? WHERE account_id=? AND company_id=?', 'iii', [$bid, $id, $cid]);
        return $id;
    }
    return db_exec(
        'INSERT INTO bank_accounts (company_id, branch_id, name, bank_name, account_number, opening_balance, notes, is_active) VALUES (?,?,?,?,?,?,?,1)',
        'iisssds',
        [$cid, $bid, $name, $bank, $number, $opening, $notes]
    );
}

function bank_account_delete(int $id): void
{
    $cid = current_company_id();
    $acct = bank_account($id);
    if (!$acct) {
        throw new RuntimeException('Bank account not found.');
    }
    if (!bank_account_allowed($acct)) {
        throw new RuntimeException('That account belongs to another branch.');
    }
    $used = db_one('SELECT id FROM bank_transactions WHERE account_id = ? AND company_id = ? LIMIT 1', 'ii', [$id, $cid]);
    if ($used) {
        throw new RuntimeException('This account has deposits or withdrawals. Archive it instead of deleting.');
    }
    db_exec('DELETE FROM bank_accounts WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
}

function bank_account_balance(int $accountId): float
{
    $acct = bank_account($accountId);
    if (!$acct) {
        return 0.0;
    }
    $row = db_one(
        "SELECT COALESCE(SUM(CASE WHEN kind = 'deposit' THEN amount ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN kind = 'withdraw' THEN amount ELSE 0 END), 0) AS moved
         FROM bank_transactions WHERE company_id = ? AND account_id = ?",
        'ii',
        [current_company_id(), $accountId]
    );
    return round((float) $acct['opening_balance'] + (float) ($row['moved'] ?? 0), 2);
}

function bank_transactions(?int $accountId = null): array
{
    $cid = current_company_id();
    [$extra, $types, $params] = period_sql('t.txn_date');
    [$bSql, $bTypes, $bArgs] = pnl_branch_sql('t.branch_id');
    $sql = 'SELECT t.*, a.name AS account_name, a.bank_name, a.branch_id AS account_branch_id
            FROM bank_transactions t
            JOIN bank_accounts a ON a.id = t.account_id AND a.company_id = t.company_id
            WHERE t.company_id = ?' . $extra . $bSql;
    $bind = 'i' . $types . $bTypes;
    $args = array_merge([$cid], $params, $bArgs);
    if ($accountId && $accountId > 0) {
        $sql .= ' AND t.account_id = ?';
        $bind .= 'i';
        $args[] = $accountId;
    }
    $sql .= ' ORDER BY t.txn_date DESC, t.id DESC';
    return db_all($sql, $bind, $args);
}

function bank_account_allowed(array $acct): bool
{
    $scope = pnl_view_branch();
    if (empty($scope['enabled'])) {
        return true;
    }
    $bid = (int) ($acct['branch_id'] ?? 0);
    if (!empty($scope['admin']) && !empty($scope['all'])) {
        return true;
    }
    return $bid === (int) $scope['id'];
}

function bank_transaction_save(array $data, int $id = 0): int
{
    $cid = current_company_id();
    $uid = (int) (current_user()['id'] ?? 0);
    $accountId = (int) ($data['account_id'] ?? 0);
    $acct = bank_account($accountId);
    if (!$acct) {
        throw new RuntimeException('Pick a bank account.');
    }
    if (!bank_account_allowed($acct)) {
        throw new RuntimeException('That account belongs to another branch.');
    }
    $bid = (int) ($acct['branch_id'] ?? 0);
    $kind = (string) ($data['kind'] ?? 'deposit');
    if (!in_array($kind, ['deposit', 'withdraw'], true)) {
        $kind = 'deposit';
    }
    $amount = round((float) preg_replace('/[^0-9.\-]/', '', (string) ($data['amount'] ?? '0')), 2);
    $date = pnl_valid_date($data['txn_date'] ?? today());
    $person = mb_substr(trim((string) ($data['person_name'] ?? '')), 0, 160);
    $purpose = mb_substr(trim((string) ($data['purpose'] ?? '')), 0, 255);
    $notes = mb_substr(trim((string) ($data['notes'] ?? '')), 0, 500);
    if ($amount <= 0) {
        throw new RuntimeException('Enter an amount greater than zero.');
    }
    if ($person === '') {
        throw new RuntimeException($kind === 'withdraw' ? 'Name the person withdrawing.' : 'Name the depositor.');
    }
    if ($kind === 'withdraw' && $purpose === '') {
        throw new RuntimeException('Give a purpose for the withdrawal.');
    }
    $balance = bank_account_balance($accountId);
    $prior = 0.0;
    if ($id > 0) {
        $row = db_one('SELECT * FROM bank_transactions WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
        if (!$row) {
            throw new RuntimeException('That bank line was not found.');
        }
        if ((int) $row['account_id'] === $accountId) {
            $prior = (string) $row['kind'] === 'withdraw' ? -((float) $row['amount']) : (float) $row['amount'];
        }
    }
    $next = $balance - $prior + ($kind === 'withdraw' ? -$amount : $amount);
    if ($next < -0.009) {
        throw new RuntimeException('That withdrawal is more than the account balance.');
    }
    if ($id > 0) {
        db_exec(
            'UPDATE bank_transactions SET account_id=?, kind=?, txn_date=?, amount=?, person_name=?, purpose=?, notes=?, branch_id=? WHERE id=? AND company_id=?',
            'issdssiiii',
            [$accountId, $kind, $date, $amount, $person, $purpose, $notes, $bid, $id, $cid]
        );
        return $id;
    }
    return db_exec(
        'INSERT INTO bank_transactions (company_id, branch_id, account_id, kind, txn_date, amount, person_name, purpose, notes, user_id) VALUES (?,?,?,?,?,?,?,?,?,?)',
        'iiissdsssi',
        [$cid, $bid, $accountId, $kind, $date, $amount, $person, $purpose, $notes, $uid]
    );
}

function bank_transaction_delete(int $id): void
{
    db_exec('DELETE FROM bank_transactions WHERE id = ? AND company_id = ?', 'ii', [$id, current_company_id()]);
}

function pnl_report_bucket(): string
{
    $b = (string) ($_GET['bucket'] ?? 'month');
    return in_array($b, ['day', 'week', 'month'], true) ? $b : 'month';
}

function pnl_bucket_key(string $date, string $bucket): string
{
    $t = strtotime($date . ' 12:00:00') ?: time();
    if ($bucket === 'week') {
        $n = (int) date('N', $t);
        return date('Y-m-d', $t - (($n - 1) * 86400));
    }
    if ($bucket === 'month') {
        return date('Y-m-01', $t);
    }
    return date('Y-m-d', $t);
}

function pnl_bucket_label(string $key, string $bucket): string
{
    if ($bucket === 'month') {
        $t = strtotime($key . ' 12:00:00');
        return $t ? date('F Y', $t) : $key;
    }
    if ($bucket === 'week') {
        return 'Week of ' . format_date($key);
    }
    return format_date($key);
}

function render_pnl_bucket_chips(string $action, array $keep = []): void
{
    $on = pnl_report_bucket();
    $chips = ['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly'];
    $p = period_range();
    ?>
  <nav class="planner-tabs" aria-label="Report grouping">
    <?php foreach ($chips as $key => $label):
        $qs = http_build_query(array_merge(pnl_branch_keep(), $keep, [
            'bucket' => $key,
            'range' => $p['preset'],
            'from' => $p['from'],
            'to' => $p['to'],
        ]));
        ?>
      <a class="planner-tab<?= $on === $key ? ' is-on' : '' ?>" href="<?= h(url($action . '?' . $qs)) ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
  </nav>
    <?php
}

function render_csv_link(string $type, string $label = 'Export CSV', array $extra = []): void
{
    echo '<a class="btn ghost sm" href="' . h(export_query($type, array_merge(pnl_branch_keep(), $extra))) . '">' . icon('download', 16) . h($label) . '</a>';
}

function render_chart_download(string $canvasId, string $file = ''): void
{
    $file = $file !== '' ? $file : ($canvasId . '.png');
    echo '<button class="btn ghost sm" type="button" data-chart-download="' . h($canvasId) . '" data-chart-file="' . h($file) . '">' . icon('download', 16) . 'Download</button>';
}

function bank_period_totals(array $txns): array
{
    $in = 0.0;
    $out = 0.0;
    $depositors = [];
    $withdrawers = [];
    foreach ($txns as $t) {
        $amt = (float) $t['amount'];
        if (($t['kind'] ?? '') === 'withdraw') {
            $out += $amt;
            $name = trim((string) ($t['person_name'] ?? ''));
            if ($name !== '') {
                $withdrawers[$name] = ($withdrawers[$name] ?? 0) + $amt;
            }
        } else {
            $in += $amt;
            $name = trim((string) ($t['person_name'] ?? ''));
            if ($name !== '') {
                $depositors[$name] = ($depositors[$name] ?? 0) + $amt;
            }
        }
    }
    arsort($depositors);
    arsort($withdrawers);
    return [
        'deposits' => round($in, 2),
        'withdrawals' => round($out, 2),
        'net' => round($in - $out, 2),
        'count' => count($txns),
        'depositors' => $depositors,
        'withdrawers' => $withdrawers,
    ];
}

function bank_report_series(array $txns, string $bucket): array
{
    $series = [];
    $byAccount = [];
    $byPurpose = [];
    $byBranch = [];
    foreach ($txns as $t) {
        $key = pnl_bucket_key((string) $t['txn_date'], $bucket);
        if (!isset($series[$key])) {
            $series[$key] = ['deposits' => 0.0, 'withdrawals' => 0.0];
        }
        $amt = (float) $t['amount'];
        if (($t['kind'] ?? '') === 'withdraw') {
            $series[$key]['withdrawals'] += $amt;
            $purpose = trim((string) ($t['purpose'] ?? '')) ?: 'Unspecified';
            $byPurpose[$purpose] = ($byPurpose[$purpose] ?? 0) + $amt;
        } else {
            $series[$key]['deposits'] += $amt;
        }
        $acct = (string) ($t['account_name'] ?? 'Account');
        if (!isset($byAccount[$acct])) {
            $byAccount[$acct] = ['deposits' => 0.0, 'withdrawals' => 0.0];
        }
        if (($t['kind'] ?? '') === 'withdraw') {
            $byAccount[$acct]['withdrawals'] += $amt;
        } else {
            $byAccount[$acct]['deposits'] += $amt;
        }
        $branch = pnl_branch_name($t['branch_id'] ?? $t['account_branch_id'] ?? 0);
        if (!isset($byBranch[$branch])) {
            $byBranch[$branch] = ['deposits' => 0.0, 'withdrawals' => 0.0];
        }
        if (($t['kind'] ?? '') === 'withdraw') {
            $byBranch[$branch]['withdrawals'] += $amt;
        } else {
            $byBranch[$branch]['deposits'] += $amt;
        }
    }
    ksort($series);
    arsort($byPurpose);
    return [
        'series' => $series,
        'by_account' => $byAccount,
        'by_purpose' => $byPurpose,
        'by_branch' => $byBranch,
    ];
}

function savings_report_series(array $moves, string $bucket): array
{
    $series = [];
    foreach ($moves as $t) {
        $key = pnl_bucket_key((string) $t['move_date'], $bucket);
        if (!isset($series[$key])) {
            $series[$key] = ['deposits' => 0.0, 'withdrawals' => 0.0];
        }
        $amt = (float) $t['amount'];
        if (($t['kind'] ?? '') === 'withdraw') {
            $series[$key]['withdrawals'] += $amt;
        } else {
            $series[$key]['deposits'] += $amt;
        }
    }
    ksort($series);
    return $series;
}

function render_pnl_subnav(string $active): void
{
    $tabs = [
        ['pnl.php', 'Overview', 'reports'],
        ['pnl_entries.php', 'Ledger', 'bank'],
        ['pnl_savings.php', 'Savings', 'wallet'],
        ['pnl_banking.php', 'Banking', 'bank'],
        ['documents.php?kind=refund', 'Refunds', 'wallet'],
        ['documents.php?kind=return_note', 'Returns', 'truck'],
    ];
    ?>
  <nav class="planner-tabs pnl-tabs" aria-label="Profit and loss sections">
    <?php foreach ($tabs as [$href, $label, $iconName]):
        $file = (string) strtok($href, '?');
        $on = $active === $href || $active === $file;
        if ($active === 'refund' && str_contains($href, 'kind=refund')) {
            $on = true;
        }
        if ($active === 'return_note' && str_contains($href, 'kind=return_note')) {
            $on = true;
        }
        ?>
      <a class="planner-tab<?= $on ? ' is-on' : '' ?>" href="<?= h(pnl_href($href)) ?>"><?= icon($iconName, 16) ?><span><?= h($label) ?></span></a>
    <?php endforeach; ?>
  </nav>
    <?php
}

function render_banking_subnav(string $view): void
{
    $p = period_range();
    $tabs = [
        'accounts' => 'Accounts',
        'activity' => 'Deposits & withdrawals',
        'reports' => 'Reports',
    ];
    ?>
  <nav class="planner-tabs" aria-label="Banking sections">
    <?php foreach ($tabs as $key => $label):
        $qs = http_build_query(array_merge(pnl_branch_keep(), [
            'view' => $key,
            'range' => $p['preset'],
            'from' => $p['from'],
            'to' => $p['to'],
            'bucket' => pnl_report_bucket(),
        ]));
        ?>
      <a class="planner-tab<?= $view === $key ? ' is-on' : '' ?>" href="<?= h(url('pnl_banking.php?' . $qs)) ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
  </nav>
    <?php
}


function notification_dismiss_key(array $n): string
{
    if (!empty($n['key'])) {
        return (string) $n['key'];
    }
    return sha1(($n['type'] ?? '') . '|' . ($n['href'] ?? '') . '|' . ($n['title'] ?? ''));
}

function notification_is_dismissed(string $key): bool
{
    if ($key === '') {
        return false;
    }
    $bag = $_SESSION['notif_dismissed'] ?? [];
    if (isset($bag[$key])) {
        return true;
    }
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        return false;
    }
    try {
        $row = db_one(
            'SELECT 1 AS ok FROM notification_dismissals WHERE user_id = ? AND dismiss_key = ? LIMIT 1',
            'is',
            [$uid, $key]
        );
        if ($row) {
            if (!isset($_SESSION['notif_dismissed']) || !is_array($_SESSION['notif_dismissed'])) {
                $_SESSION['notif_dismissed'] = [];
            }
            $_SESSION['notif_dismissed'][$key] = time();
            return true;
        }
    } catch (Throwable $e) {
        // Table may not exist yet on a brand-new install.
    }
    return false;
}

function notification_dismiss(string $key): void
{
    $key = trim($key);
    if ($key === '') {
        return;
    }
    if (!isset($_SESSION['notif_dismissed']) || !is_array($_SESSION['notif_dismissed'])) {
        $_SESSION['notif_dismissed'] = [];
    }
    $_SESSION['notif_dismissed'][$key] = time();
    // Keep the bag from growing forever.
    if (count($_SESSION['notif_dismissed']) > 80) {
        asort($_SESSION['notif_dismissed']);
        $_SESSION['notif_dismissed'] = array_slice($_SESSION['notif_dismissed'], -60, null, true);
    }
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        return;
    }
    try {
        db_exec(
            'INSERT IGNORE INTO notification_dismissals (user_id, dismiss_key, created_at) VALUES (?,?,NOW())',
            'is',
            [$uid, substr($key, 0, 64)]
        );
    } catch (Throwable $e) {
        // Ignore if migration has not created the table yet.
    }
}

function enrich_planner_notifications(array $items): array
{
    $out = [];
    foreach ($items as $n) {
        $type = (string) ($n['type'] ?? '');
        if ($type === 'event' && preg_match('/edit=(\d+)/', (string) ($n['href'] ?? ''), $m)) {
            $n['event_id'] = (int) $m[1];
            $n['key'] = 'event:' . $n['event_id'];
            $n['actions'] = [
                ['label' => 'Done', 'action' => 'done', 'class' => 'btn sm'],
                ['label' => 'Open', 'href' => $n['href'], 'class' => 'btn ghost sm'],
            ];
        } elseif ($type === 'note' && preg_match('/edit=(\d+)/', (string) ($n['href'] ?? ''), $m)) {
            $n['note_id'] = (int) $m[1];
            $n['key'] = 'note:' . $n['note_id'];
            $n['actions'] = [
                ['label' => 'Open', 'href' => $n['href'], 'class' => 'btn ghost sm'],
            ];
        } elseif ($type === 'invoice' && preg_match('/id=(\d+)/', (string) ($n['href'] ?? ''), $m)) {
            $n['document_id'] = (int) $m[1];
            $n['key'] = 'invoice:' . $n['document_id'];
            $n['actions'] = [
                ['label' => 'Receive', 'href' => url('document_action.php?receive=' . $n['document_id']), 'class' => 'btn sm'],
                ['label' => 'View', 'href' => $n['href'], 'class' => 'btn ghost sm'],
            ];
        } else {
            $n['key'] = notification_dismiss_key($n);
            $n['actions'] = [
                ['label' => 'Open', 'href' => $n['href'], 'class' => 'btn ghost sm'],
            ];
        }
        if (notification_is_dismissed((string) $n['key'])) {
            continue;
        }
        $out[] = $n;
    }
    return $out;
}
