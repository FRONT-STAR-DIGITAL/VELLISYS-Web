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
            'UPDATE pnl_entries SET entry_date=?, kind=?, category=?, title=?, amount=?, notes=? WHERE id=? AND company_id=?',
            'ssssdsii',
            [$date, $kind, $category, $title, $amount, $notes !== '' ? $notes : null, $id, $cid]
        );
        return $id;
    }
    return db_exec(
        'INSERT INTO pnl_entries (company_id, user_id, entry_date, kind, category, title, amount, notes) VALUES (?,?,?,?,?,?,?,?)',
        'iissssds',
        [$cid, $uid, $date, $kind, $category, $title, $amount, $notes !== '' ? $notes : null]
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
    $sql = 'SELECT e.* FROM pnl_entries e WHERE e.company_id = ?' . $extra;
    $bind = 'i' . $types;
    $args = array_merge([$cid], $params);
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
    $scope = "d.company_id = ? AND d.status = 'issued'" . $extra;
    $bind = 'i' . $types;
    $args = array_merge([$cid], $params);
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
    $expenseTotal = $costs + $manualExpense + $refundOut;
    $net = $incomeTotal - $expenseTotal;

    return [
        'sales' => $sales,
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

function render_pnl_subnav(string $active): void
{
    $tabs = [
        ['pnl.php', 'Overview', 'reports'],
        ['pnl_entries.php', 'Ledger', 'bank'],
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
      <a class="planner-tab<?= $on ? ' is-on' : '' ?>" href="<?= h(url($href)) ?>"><?= icon($iconName, 16) ?><span><?= h($label) ?></span></a>
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
    $bag = $_SESSION['notif_dismissed'] ?? [];
    return isset($bag[$key]);
}

function notification_dismiss(string $key): void
{
    if (!isset($_SESSION['notif_dismissed']) || !is_array($_SESSION['notif_dismissed'])) {
        $_SESSION['notif_dismissed'] = [];
    }
    $_SESSION['notif_dismissed'][$key] = time();
    // Keep the bag from growing forever.
    if (count($_SESSION['notif_dismissed']) > 80) {
        asort($_SESSION['notif_dismissed']);
        $_SESSION['notif_dismissed'] = array_slice($_SESSION['notif_dismissed'], -60, null, true);
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
