<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

admin_period_default_today();
$period = period_range();
$from = $period['from'] !== '' ? $period['from'] : '2000-01-01';
$to = $period['to'] !== '' ? $period['to'] : desk_now()->format('Y-m-d');

$tab = (string) ($_GET['tab'] ?? 'overview');
if (!in_array($tab, ['overview', 'expenses', 'reports'], true)) {
    $tab = 'overview';
}
$payId = (int) ($_GET['pay'] ?? 0);
$error = '';
$ccy = platform_currency();

$filterQs = static function (string $tabName, array $extra = []) use ($period): string {
    $q = array_merge([
        'tab' => $tabName,
        'range' => (string) ($period['preset'] ?? ''),
        'from' => (string) ($period['from'] ?? ''),
        'to' => (string) ($period['to'] ?? ''),
    ], $extra);
    $q = array_filter($q, static fn ($v) => $v !== '' && $v !== null);
    return 'admin_finances.php?' . http_build_query($q);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) post('action');
    if ($action === 'add_expense') {
        $done = platform_expense_save([
            'title' => post('title'),
            'amount' => post('amount'),
            'currency' => post('currency') !== '' ? post('currency') : $ccy,
            'occurred_on' => post('occurred_on'),
            'note' => post('note'),
        ], (int) $user['id']);
        if (empty($done['ok'])) {
            $error = (string) ($done['error'] ?? 'Could not save expense.');
            $tab = 'expenses';
        } else {
            flash('Expense saved.');
            redirect($filterQs('expenses'));
        }
    } elseif ($action === 'delete_expense') {
        $done = platform_expense_delete((int) post('expense_id'));
        flash(empty($done['ok']) ? ($done['error'] ?? 'Could not delete.') : 'Expense deleted.', empty($done['ok']) ? 'err' : 'ok');
        redirect($filterQs((string) (post('return_tab') ?: 'expenses')));
    } elseif ($action === 'make_payment') {
        $cid = (int) post('company_id');
        $co = $cid > 0 ? db_one('SELECT * FROM companies WHERE id = ?', 'i', [$cid]) : null;
        if (!$co) {
            $error = 'Pick a company to record a payment.';
            $payId = $cid;
            $tab = 'overview';
        } else {
            $payAmount = money_parse(post('pay_amount'));
            $feeAmount = money_parse(post('fee_amount'));
            if ($feeAmount <= 0) {
                $feeAmount = company_fee_amount($co);
            }
            $feeCurrency = posted_currency('fee_currency', company_fee_currency($co));
            $term = parse_paid_term(post('paid_term'));
            $unit = normalize_paid_unit(post('paid_unit'));
            $term = clamp_paid_term($term > 0 ? $term : (float) ($co['paid_term'] ?? 0), $unit !== '' ? $unit : normalize_paid_unit($co['paid_unit'] ?? 'months'));
            $unit = $unit !== '' ? $unit : normalize_paid_unit((string) ($co['paid_unit'] ?? 'months'));
            $paidFrom = post('paid_from');
            if ($paidFrom === '' || !DateTime::createFromFormat('Y-m-d', $paidFrom)) {
                $paidFrom = (string) ($co['paid_from'] ?? '') ?: date('Y-m-d');
            }
            if ($payAmount <= 0.009) {
                $error = 'Enter the amount paid for this payment.';
                $payId = $cid;
                $tab = 'overview';
            } elseif ($term <= 0) {
                $error = 'Set the paid term (weeks, months or years) before recording payment.';
                $payId = $cid;
                $tab = 'overview';
            } else {
                $expires = compute_expiry_date($paidFrom, $term, $unit);
                if (!$expires) {
                    $error = 'Could not calculate the expiry date. Check the start date and term.';
                    $payId = $cid;
                    $tab = 'overview';
                } else {
                    $newPaid = round(company_fee_paid($co) + $payAmount, 2);
                    if ($feeAmount < $newPaid) {
                        $feeAmount = $newPaid;
                    }
                    db_exec(
                        'UPDATE companies SET paid_term=?, paid_unit=?, paid_from=?, expires_at=?, renewal_notice_sent_at=NULL, fee_amount=?, fee_paid=?, fee_currency=? WHERE id=?',
                        'dsssddsi',
                        [$term, $unit, $paidFrom, $expires, $feeAmount, $newPaid, $feeCurrency, $cid]
                    );
                    record_platform_fee($cid, $payAmount, $feeCurrency, 'term', 'Payment for ' . $co['name']);
                    company_mark_onboard_step($cid, 'paid_term');
                    company_mark_onboard_step($cid, 'package_paid');
                    flash(
                        'Recorded ' . money($payAmount, $feeCurrency) . ' for ' . $co['name']
                        . '. Paid ' . money($newPaid, $feeCurrency)
                        . ', balance ' . money(max(0, $feeAmount - $newPaid), $feeCurrency) . '.'
                    );
                    redirect('admin_payment_receipt.php?id=' . $cid . '&paid=' . rawurlencode((string) $payAmount));
                }
            }
        }
    }
}

$allTaken = 0.0;
$periodTaken = 0.0;
$allExpenses = 0.0;
$periodExpenses = 0.0;
$ledger = [];
$expenses = [];
try {
    $allTaken = platform_fee_sum();
    $periodTaken = platform_fee_sum($from, $to);
    $allExpenses = platform_expense_sum();
    $periodExpenses = platform_expense_sum($from, $to);
    $ledger = db_all(
        'SELECT l.*, c.name AS company_name
         FROM platform_fee_ledger l
         LEFT JOIN companies c ON c.id = l.company_id
         WHERE DATE(l.occurred_at) BETWEEN ? AND ?
         ORDER BY l.occurred_at DESC, l.id DESC
         LIMIT 500',
        'ss',
        [$from, $to]
    );
    $expenses = platform_expenses_list($from, $to, 500);
} catch (Throwable $e) {
    $ledger = [];
    $expenses = [];
}

$periodProfit = round_money($periodTaken - $periodExpenses, $ccy);
$allProfit = round_money($allTaken - $allExpenses, $ccy);

$companies = db_all('SELECT * FROM companies ORDER BY name');
$feeBalance = 0.0;
foreach ($companies as $c) {
    $feeBalance += platform_convert(company_fee_balance($c), company_fee_currency($c), $ccy);
}

$expenseTitles = platform_expense_titles();
$payCompany = null;
foreach ($companies as $c) {
    if ((int) $c['id'] === $payId) {
        $payCompany = $c;
        break;
    }
}

// Reports series
$grain = (string) ($_GET['grain'] ?? '');
$daysSpan = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);
if (!in_array($grain, ['day', 'month'], true)) {
    $grain = $daysSpan > 62 ? 'month' : 'day';
}
$axis = platform_finance_axis($from, $to, $grain);
$takenSeries = platform_fee_series($from, $to, $grain);
$expenseSeries = platform_expense_series($from, $to, $grain);
$takenChart = [];
$expenseChart = [];
$profitChart = [];
foreach ($axis as $bucket) {
    $tin = (float) ($takenSeries[$bucket] ?? 0);
    $ex = (float) ($expenseSeries[$bucket] ?? 0);
    $takenChart[] = round($tin, 2);
    $expenseChart[] = round($ex, 2);
    $profitChart[] = round($tin - $ex, 2);
}
$axisLabels = array_map(static function (string $b) use ($grain): string {
    if ($grain === 'month') {
        return $b;
    }
    try {
        return (new DateTimeImmutable($b))->format('j M');
    } catch (Throwable $e) {
        return $b;
    }
}, $axis);
$expenseBreak = platform_expense_breakdown($from, $to, 10);
$breakLabels = array_column($expenseBreak, 'title');
$breakData = array_map(static fn ($r) => (float) $r['amount'], $expenseBreak);

$hasCharts = array_sum($takenChart) > 0 || array_sum($expenseChart) > 0;

layout_admin_start('Finances', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('bank') ?>Finances</h1>
    <p class="lede">Money in, expenses and profit in <?= h($ccy) ?>.</p>
  </div>
  <?php if ($tab === 'expenses'): ?>
    <div class="actions page-actions">
      <a class="btn" href="#add-expense"><?= icon('plus', 16) ?>Add expense</a>
    </div>
  <?php endif; ?>
</div>

<nav class="planner-tabs" aria-label="Finance sections">
  <a class="planner-tab<?= $tab === 'overview' ? ' is-on' : '' ?>" href="<?= h(url($filterQs('overview'))) ?>"><?= icon('bank', 16) ?><span>Overview</span></a>
  <a class="planner-tab<?= $tab === 'expenses' ? ' is-on' : '' ?>" href="<?= h(url($filterQs('expenses'))) ?>"><?= icon('expense', 16) ?><span>Expenses</span></a>
  <a class="planner-tab<?= $tab === 'reports' ? ' is-on' : '' ?>" href="<?= h(url($filterQs('reports'))) ?>"><?= icon('reports', 16) ?><span>Reports</span></a>
</nav>

<?php
$filterKeep = ['tab' => $tab];
if ($tab === 'reports') {
    $filterKeep['grain'] = $grain;
}
render_filters('admin_finances.php', $filterKeep, ['live' => true, 'today_first' => true]);
?>
<?php if ($error): ?><p class="flash flash-err"><?= h($error) ?></p><?php endif; ?>

<div class="stats">
  <div class="card stat"><?= icon('invoice', 20) ?><span>Taken in</span><strong><?= h(money($periodTaken, $ccy)) ?></strong><em>This period</em></div>
  <div class="card stat"><?= icon('expense', 20) ?><span>Expenses</span><strong><?= h(money($periodExpenses, $ccy)) ?></strong><em>This period</em></div>
  <div class="card stat"><?= icon('reports', 20) ?><span>Profit</span><strong class="<?= $periodProfit < 0 ? 'neg' : 'pos' ?>"><?= h(money($periodProfit, $ccy)) ?></strong><em>Taken in − expenses</em></div>
  <div class="card stat"><?= icon('receipt', 20) ?><span>Still due</span><strong><?= h(money($feeBalance, $ccy)) ?></strong><em>On company terms</em></div>
</div>

<?php if ($tab === 'overview'): ?>

<?php if ($payCompany): ?>
<div class="card form-wide" style="margin-bottom:24px" id="make-payment">
  <div class="card-head"><h2><?= icon('bank', 16) ?>Make payment · <?= h((string) $payCompany['name']) ?></h2></div>
  <form class="pad-form" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="make_payment">
    <input type="hidden" name="company_id" value="<?= (int) $payCompany['id'] ?>">
    <div class="form-grid">
      <div>
        <label for="pay_amount">Amount paid now</label>
        <input id="pay_amount" name="pay_amount" inputmode="decimal" required value="<?= h(post('pay_amount') !== '' ? post('pay_amount') : (company_fee_balance($payCompany) > 0 ? (string) company_fee_balance($payCompany) : '')) ?>" placeholder="0">
      </div>
      <div>
        <label for="fee_amount">Fee for this term</label>
        <input id="fee_amount" name="fee_amount" inputmode="decimal" value="<?= h(post('fee_amount') !== '' ? post('fee_amount') : (company_fee_amount($payCompany) > 0 ? (string) company_fee_amount($payCompany) : '')) ?>" placeholder="0">
      </div>
      <div>
        <label for="fee_currency">Currency</label>
        <?php currency_field('fee_currency', 'fee_currency', company_fee_currency($payCompany)); ?>
      </div>
      <div>
        <label for="paid_from">Period starts</label>
        <input id="paid_from" name="paid_from" type="date" value="<?= h((string) ($payCompany['paid_from'] ?: date('Y-m-d'))) ?>">
      </div>
      <div>
        <label for="paid_term">Term number</label>
        <input id="paid_term" name="paid_term" type="number" min="0" max="520" step="0.01" value="<?= h(format_paid_term_number((float) ($payCompany['paid_term'] ?? 1) ?: 1)) ?>">
      </div>
      <div>
        <label for="paid_unit">Term unit</label>
        <select id="paid_unit" name="paid_unit">
          <option value="weeks" <?= ($payCompany['paid_unit'] ?? '') === 'weeks' ? 'selected' : '' ?>>Weeks</option>
          <option value="months" <?= ($payCompany['paid_unit'] ?? 'months') === 'months' ? 'selected' : '' ?>>Months</option>
          <option value="years" <?= ($payCompany['paid_unit'] ?? '') === 'years' ? 'selected' : '' ?>>Years</option>
        </select>
      </div>
    </div>
    <p class="hint">
      Already paid <?= h(platform_money_company_fee($payCompany, 'paid')) ?>
      · Balance <?= h(platform_money_company_fee($payCompany, 'balance')) ?>.
    </p>
    <div class="actions">
      <button class="btn" type="submit"><?= icon('check', 16) ?>Record payment</button>
      <a class="btn ghost" href="<?= h(url($filterQs('overview'))) ?>">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('invoice', 16) ?>Taken in</h2></div>
  <?php if (!$ledger): ?>
    <p class="empty">No payments in this date range.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid finance-totals-table">
        <thead>
          <tr>
            <th>When</th>
            <th>Company</th>
            <th>Source</th>
            <th class="right">Amount</th>
            <th>Note</th>
            <th class="row-actions">Receipt</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ledger as $row):
              $lcid = (int) ($row['company_id'] ?? 0);
              ?>
            <tr>
              <td class="mono"><?= h(format_when($row['occurred_at'] ?? null)) ?></td>
              <td><?= h((string) ($row['company_name'] ?? 'Vellisys')) ?></td>
              <td><?= h((string) ($row['source'] ?? '')) ?></td>
              <td class="right mono"><?= h(platform_money((float) $row['amount'], (string) $row['currency'])) ?></td>
              <td><?= h((string) ($row['note'] ?? '')) ?></td>
              <td class="row-actions">
                <?php if ($lcid > 0): ?>
                  <a class="btn icon-only" href="<?= h(url('admin_payment_receipt.php?id=' . $lcid . '&paid=' . rawurlencode((string) $row['amount']))) ?>" title="Preview receipt" aria-label="Preview receipt"><?= icon('eye', 15) ?></a>
                <?php else: ?>-<?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3"><strong>Total</strong></td>
            <td class="right mono"><strong><?= h(money($periodTaken, $ccy)) ?></strong></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px">
  <div class="card-head">
    <h2><?= icon('expense', 16) ?>Expenses</h2>
    <a class="btn ghost sm" href="<?= h(url($filterQs('expenses'))) ?>"><?= icon('plus', 14) ?>Add</a>
  </div>
  <?php if (!$expenses): ?>
    <p class="empty">No expenses in this date range. <a href="<?= h(url($filterQs('expenses'))) ?>">Add an expense</a>.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid finance-totals-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Expense</th>
            <th class="right">Amount</th>
            <th>Note</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($expenses as $ex): ?>
            <tr>
              <td class="mono"><?= h(format_date((string) $ex['occurred_on'])) ?></td>
              <td><?= h((string) $ex['title']) ?></td>
              <td class="right mono"><?= h(platform_money((float) $ex['amount'], (string) $ex['currency'])) ?></td>
              <td><?= h((string) ($ex['note'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2"><strong>Total</strong></td>
            <td class="right mono"><strong><?= h(money($periodExpenses, $ccy)) ?></strong></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('building', 16) ?>Companies · paid and balance</h2></div>
  <?php if (!$companies): ?>
    <p class="empty">No companies yet.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Company</th>
            <th>Period</th>
            <th>Renews</th>
            <th class="right">Amount paid</th>
            <th class="right">Balance remaining</th>
            <th class="row-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($companies as $c):
              $cid = (int) $c['id'];
              $hasTerm = (bool) company_expires_on($c);
              $wa = $hasTerm ? payment_receipt_whatsapp_url($c) : '';
              ?>
            <tr>
              <td><a href="<?= h(url('admin_company.php?id=' . $cid)) ?>"><strong><?= h($c['name']) ?></strong></a></td>
              <td><?= h(payment_receipt_period_label($c)) ?></td>
              <td><?= !empty($c['expires_at']) ? h(format_date((string) $c['expires_at'])) : '-' ?></td>
              <td class="right mono"><?= h(platform_money_company_fee($c, 'paid')) ?></td>
              <td class="right mono"><?= h(platform_money_company_fee($c, 'balance')) ?></td>
              <td class="row-actions">
                <div class="actions">
                  <a class="btn icon-only" href="<?= h(url($filterQs('overview', ['pay' => (string) $cid]) . '#make-payment')) ?>" title="Make payment" aria-label="Make payment"><?= icon('bank', 15) ?></a>
                  <?php if ($hasTerm): ?>
                    <a class="btn icon-only" href="<?= h(url('admin_payment_receipt.php?id=' . $cid)) ?>" title="Preview receipt" aria-label="Preview receipt"><?= icon('eye', 15) ?></a>
                    <?php if ($wa !== ''): ?>
                      <a class="btn icon-only" href="<?= h($wa) ?>" target="_blank" rel="noopener" title="Share on WhatsApp" aria-label="Share on WhatsApp"><?= icon('share', 15) ?></a>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<p class="hint">All time: taken in <?= h(money($allTaken, $ccy)) ?> · expenses <?= h(money($allExpenses, $ccy)) ?> · profit <span class="<?= $allProfit < 0 ? 'neg' : 'pos' ?>"><?= h(money($allProfit, $ccy)) ?></span>.</p>

<?php elseif ($tab === 'expenses'): ?>

<div class="card pad-form" style="margin-bottom:24px" id="add-expense">
  <div class="card-head"><h2><?= icon('plus', 16) ?>Add expense</h2></div>
  <form method="post" class="pad-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_expense">
    <div class="form-grid">
      <div class="full">
        <label for="title">Expense</label>
        <input id="title" name="title" list="expense-titles" required value="<?= h(post('title')) ?>" placeholder="e.g. Fuel, Hosting, Airtime" autocomplete="off">
        <datalist id="expense-titles">
          <?php foreach ($expenseTitles as $t): ?>
            <option value="<?= h($t) ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <p class="hint" style="margin:4px 0 0">Past expense names appear as you type.</p>
      </div>
      <div>
        <label for="occurred_on">Date</label>
        <input id="occurred_on" name="occurred_on" type="date" required value="<?= h(post('occurred_on') !== '' ? post('occurred_on') : desk_now()->format('Y-m-d')) ?>">
      </div>
      <div>
        <label for="amount">Amount</label>
        <input id="amount" name="amount" inputmode="decimal" required value="<?= h(post('amount')) ?>" placeholder="0">
      </div>
      <div>
        <label for="currency">Currency</label>
        <?php currency_field('currency', 'currency', post('currency') !== '' ? post('currency') : $ccy); ?>
      </div>
      <div class="full">
        <label for="note">Note <span class="muted">(optional)</span></label>
        <input id="note" name="note" value="<?= h(post('note')) ?>" placeholder="Optional detail">
      </div>
    </div>
    <div class="actions" style="margin-top:12px">
      <button class="btn" type="submit"><?= icon('check', 16) ?>Save expense</button>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-head"><h2><?= icon('expense', 16) ?>Expenses · <?= h(format_date($from)) ?><?= $from !== $to ? ' – ' . h(format_date($to)) : '' ?></h2></div>
  <?php if (!$expenses): ?>
    <p class="empty">No expenses in this date range.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid finance-totals-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Expense</th>
            <th class="right">Amount</th>
            <th>Note</th>
            <th class="row-actions"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($expenses as $ex): ?>
            <tr>
              <td class="mono"><?= h(format_date((string) $ex['occurred_on'])) ?></td>
              <td><?= h((string) $ex['title']) ?></td>
              <td class="right mono"><?= h(platform_money((float) $ex['amount'], (string) $ex['currency'])) ?></td>
              <td><?= h((string) ($ex['note'] ?? '')) ?></td>
              <td class="row-actions">
                <form method="post" class="inline-form" onsubmit="return confirm('Delete this expense?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_expense">
                  <input type="hidden" name="expense_id" value="<?= (int) $ex['id'] ?>">
                  <input type="hidden" name="return_tab" value="expenses">
                  <button class="btn danger sm" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2"><strong>Total</strong></td>
            <td class="right mono"><strong><?= h(money($periodExpenses, $ccy)) ?></strong></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php else: /* reports */ ?>

<div class="filter-chips" style="margin:0 0 16px">
  <a class="chip<?= $grain === 'day' ? ' is-on' : '' ?>" href="<?= h(url($filterQs('reports', ['grain' => 'day']))) ?>">By day</a>
  <a class="chip<?= $grain === 'month' ? ' is-on' : '' ?>" href="<?= h(url($filterQs('reports', ['grain' => 'month']))) ?>">By month</a>
</div>

<div class="card chart-box" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('reports', 16) ?>Taken in vs expenses</h2></div>
  <?php if (!$hasCharts): ?>
    <p class="empty">No taken-in or expense amounts in this range yet.</p>
  <?php else: ?>
    <canvas id="chart-taken-vs-expenses" height="120"></canvas>
  <?php endif; ?>
</div>

<div class="card chart-box" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('reports', 16) ?>Profit over time</h2></div>
  <?php if (!$hasCharts): ?>
    <p class="empty">Profit plots here once you have money in or expenses.</p>
  <?php else: ?>
    <canvas id="chart-profit" height="120"></canvas>
  <?php endif; ?>
</div>

<div class="stats" style="margin-bottom:24px">
  <div class="card stat"><?= icon('invoice', 20) ?><span>Taken in</span><strong><?= h(money($periodTaken, $ccy)) ?></strong></div>
  <div class="card stat"><?= icon('expense', 20) ?><span>Expenses</span><strong><?= h(money($periodExpenses, $ccy)) ?></strong></div>
  <div class="card stat"><?= icon('reports', 20) ?><span>Profit</span><strong class="<?= $periodProfit < 0 ? 'neg' : 'pos' ?>"><?= h(money($periodProfit, $ccy)) ?></strong></div>
</div>

<?php if ($expenseBreak): ?>
<div class="card chart-box" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('expense', 16) ?>Expenses by name</h2></div>
  <canvas id="chart-expense-break" height="120"></canvas>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('bank', 16) ?>Period summary</h2></div>
  <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>Metric</th>
          <th class="right">This period</th>
          <th class="right">All time</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Taken in</td>
          <td class="right mono"><?= h(money($periodTaken, $ccy)) ?></td>
          <td class="right mono"><?= h(money($allTaken, $ccy)) ?></td>
        </tr>
        <tr>
          <td>Expenses</td>
          <td class="right mono"><?= h(money($periodExpenses, $ccy)) ?></td>
          <td class="right mono"><?= h(money($allExpenses, $ccy)) ?></td>
        </tr>
        <tr>
          <td><strong>Profit</strong></td>
          <td class="right mono"><strong class="<?= $periodProfit < 0 ? 'neg' : 'pos' ?>"><?= h(money($periodProfit, $ccy)) ?></strong></td>
          <td class="right mono"><strong class="<?= $allProfit < 0 ? 'neg' : 'pos' ?>"><?= h(money($allProfit, $ccy)) ?></strong></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

<?php
$chartJs = '';
if ($tab === 'reports' && $hasCharts) {
    $profitColors = array_map(static fn ($v) => $v < 0 ? 'rgba(180,35,24,0.75)' : 'rgba(30,78,255,0.75)', $profitChart);
    $chartJs = '<script src="' . h(asset('js/chart.umd.min.js')) . '"></script><script>
(function(){
  var labels=' . json_encode(array_values($axisLabels)) . ';
  var taken=' . json_encode($takenChart) . ';
  var expenses=' . json_encode($expenseChart) . ';
  var profit=' . json_encode($profitChart) . ';
  var profitColors=' . json_encode(array_values($profitColors)) . ';
  var breakLabels=' . json_encode(array_values($breakLabels)) . ';
  var breakData=' . json_encode(array_values($breakData)) . ';
  var moneyFmt=window.vellisysChartMoney ? window.vellisysChartMoney(' . json_encode($ccy) . ') : null;
  function tip(){ return (typeof window.vellisysChartTooltip==="function") ? window.vellisysChartTooltip(moneyFmt) : {enabled:true}; }
  function go(){
    if(!window.Chart){ setTimeout(go,40); return; }
    var a=document.getElementById("chart-taken-vs-expenses");
    if(a){
      new Chart(a,{
        type:"line",
        data:{labels:labels,datasets:[
          {label:"Taken in",data:taken,borderColor:"#1E4EFF",backgroundColor:"rgba(30,78,255,0.12)",tension:0.25,fill:false},
          {label:"Expenses",data:expenses,borderColor:"#b42318",backgroundColor:"rgba(180,35,24,0.12)",tension:0.25,fill:false}
        ]},
        options:{responsive:true,interaction:{mode:"index",intersect:false},plugins:{legend:{position:"bottom"},tooltip:tip()},scales:{y:{beginAtZero:true}}}
      });
    }
    var b=document.getElementById("chart-profit");
    if(b){
      new Chart(b,{
        type:"bar",
        data:{labels:labels,datasets:[{label:"Profit",data:profit,backgroundColor:profitColors,borderRadius:4}]},
        options:{responsive:true,plugins:{legend:{display:false},tooltip:tip()},scales:{y:{beginAtZero:true}}}
      });
    }
    var c=document.getElementById("chart-expense-break");
    if(c && breakData.length){
      new Chart(c,{
        type:"doughnut",
        data:{labels:breakLabels,datasets:[{data:breakData,backgroundColor:["#1E4EFF","#0f766e","#b45309","#b42318","#6d28d9","#0369a1","#4d7c0f","#9f1239","#334155","#ca8a04"]}]},
        options:{responsive:true,plugins:{legend:{position:"bottom"},tooltip:tip()}}
      });
    }
  }
  go();
})();
</script>';
}
layout_end($chartJs);
?>
