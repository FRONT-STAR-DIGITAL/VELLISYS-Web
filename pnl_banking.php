<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_pnl();

$view = (string) ($_GET['view'] ?? 'activity');
if (!in_array($view, ['accounts', 'activity', 'reports'], true)) {
    $view = 'activity';
}
$editId = (int) ($_GET['edit'] ?? 0);
$editing = $editId ? bank_account($editId) : null;
$ccy = default_currency();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    $stay = post('view') ?: $view;
    try {
        if ($action === 'save_account') {
            $id = bank_account_save([
                'name' => post('name'),
                'bank_name' => post('bank_name'),
                'account_number' => post('account_number'),
                'opening_balance' => post('opening_balance'),
                'notes' => post('notes'),
                'is_active' => (int) post('id') === 0 || post('is_active') === '1',
            ], (int) post('id'));
            flash((int) post('id') ? 'Bank account updated.' : 'Bank account added.');
            redirect('pnl_banking.php?view=accounts');
        }
        if ($action === 'archive_account') {
            $row = bank_account((int) post('id'));
            if (!$row) {
                throw new RuntimeException('Bank account not found.');
            }
            bank_account_save([
                'name' => $row['name'],
                'bank_name' => $row['bank_name'],
                'account_number' => $row['account_number'],
                'opening_balance' => $row['opening_balance'],
                'notes' => $row['notes'],
                'is_active' => 0,
            ], (int) $row['id']);
            flash('Account archived.');
            redirect('pnl_banking.php?view=accounts');
        }
        if ($action === 'delete_account') {
            bank_account_delete((int) post('id'));
            flash('Bank account removed.');
            redirect('pnl_banking.php?view=accounts');
        }
        if ($action === 'txn') {
            bank_transaction_save([
                'account_id' => post('account_id'),
                'kind' => post('kind'),
                'amount' => post('amount'),
                'txn_date' => post('txn_date'),
                'person_name' => post('person_name'),
                'purpose' => post('purpose'),
                'notes' => post('notes'),
            ]);
            flash(post('kind') === 'withdraw' ? 'Withdrawal recorded.' : 'Deposit recorded.');
            redirect('pnl_banking.php?view=activity');
        }
        if ($action === 'delete_txn') {
            bank_transaction_delete((int) post('id'));
            flash('Bank line removed.');
            redirect('pnl_banking.php?view=activity');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $view = in_array($stay, ['accounts', 'activity', 'reports'], true) ? $stay : $view;
    }
}

$accounts = bank_accounts();
$activeAccounts = array_values(array_filter($accounts, static fn ($a) => (int) ($a['is_active'] ?? 1) === 1));
$txns = bank_transactions();
$totals = bank_period_totals($txns);
$bucket = pnl_report_bucket();
$report = bank_report_series($txns, $bucket);
$period = period_range();
$rangeLabel = $period['from'] ? format_date($period['from']) . ' – ' . format_date($period['to']) : 'all dates';
$cashOnHand = 0.0;
foreach ($accounts as $a) {
    if ((int) ($a['is_active'] ?? 1) === 1) {
        $cashOnHand += bank_account_balance((int) $a['id']);
    }
}

layout_start('Banking', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('bank') ?>Banking</h1>
    <p class="lede">Bank accounts, deposits and withdrawals for <?= h($rangeLabel) ?>, with daily, weekly or monthly reports.</p>
  </div>
  <div class="actions page-actions">
    <?php if ($view === 'accounts'): ?>
      <?php render_csv_link('bank_accounts', 'Export CSV'); ?>
    <?php elseif ($view === 'reports'): ?>
      <?php render_csv_link('bank_report', 'Export CSV', ['bucket' => $bucket]); ?>
    <?php else: ?>
      <?php render_csv_link('banking', 'Export CSV'); ?>
    <?php endif; ?>
  </div>
</div>
<?php render_pnl_subnav('pnl_banking.php'); ?>
<?php render_banking_subnav($view); ?>
<?php render_filters('pnl_banking.php', ['view' => $view, 'bucket' => $bucket]); ?>
<?php if ($view === 'reports'): ?>
  <?php render_pnl_bucket_chips('pnl_banking.php', ['view' => 'reports']); ?>
<?php endif; ?>
<?php if ($error): ?><p class="flash flash-err"><?= h($error) ?></p><?php endif; ?>

<div class="stats">
  <div class="card stat"><?= icon('bank', 20) ?><span>Cash in banks</span><strong><?= h(money($cashOnHand, $ccy)) ?></strong><em><?= count($activeAccounts) ?> active account<?= count($activeAccounts) === 1 ? '' : 's' ?></em></div>
  <div class="card stat"><?= icon('plus', 20) ?><span>Deposits</span><strong><?= h(money($totals['deposits'], $ccy)) ?></strong><em><?= h($rangeLabel) ?></em></div>
  <div class="card stat"><?= icon('upload', 20) ?><span>Withdrawals</span><strong><?= h(money($totals['withdrawals'], $ccy)) ?></strong><em><?= h($rangeLabel) ?></em></div>
  <div class="card stat"><?= icon('reports', 20) ?><span>Net movement</span><strong class="<?= $totals['net'] < 0 ? 'neg' : 'pos' ?>"><?= h(money($totals['net'], $ccy)) ?></strong><em><?= (int) $totals['count'] ?> line<?= $totals['count'] === 1 ? '' : 's' ?></em></div>
</div>

<?php if ($view === 'accounts'): ?>
<div class="desk-grid stock-split">
  <div class="card">
    <div class="card-head"><h2><?= icon($editing ? 'pencil' : 'plus', 16) ?><?= $editing ? 'Edit account' : 'Add bank account' ?></h2></div>
    <form method="post" class="pad-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_account">
      <input type="hidden" name="view" value="accounts">
      <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
      <label for="name">Account name</label>
      <input id="name" name="name" required maxlength="120" value="<?= h((string) ($editing['name'] ?? '')) ?>" placeholder="Operating, Stanbic current">
      <label for="bank_name">Bank</label>
      <input id="bank_name" name="bank_name" maxlength="160" value="<?= h((string) ($editing['bank_name'] ?? '')) ?>" placeholder="Stanbic, Equity, Centenary">
      <label for="account_number">Account number</label>
      <input id="account_number" name="account_number" maxlength="80" value="<?= h((string) ($editing['account_number'] ?? '')) ?>">
      <label for="opening_balance">Opening balance</label>
      <input id="opening_balance" name="opening_balance" inputmode="decimal" value="<?= h(isset($editing['opening_balance']) ? (string) $editing['opening_balance'] : '0') ?>">
      <label for="acct_notes">Note</label>
      <input id="acct_notes" name="notes" maxlength="500" value="<?= h((string) ($editing['notes'] ?? '')) ?>">
      <?php if ($editing): ?>
        <label class="check" style="margin-top:10px">
          <input type="checkbox" name="is_active" value="1" <?= (int) ($editing['is_active'] ?? 1) === 1 ? 'checked' : '' ?>> Active
        </label>
      <?php endif; ?>
      <div class="actions" style="margin-top:12px">
        <button class="btn" type="submit"><?= icon('check') ?><?= $editing ? 'Save account' : 'Add account' ?></button>
        <?php if ($editing): ?>
          <a class="btn ghost" href="<?= h(url('pnl_banking.php?view=accounts')) ?>">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
  <div class="card">
    <div class="card-head">
      <h2><?= icon('bank', 16) ?>Accounts</h2>
      <?php render_csv_link('bank_accounts', 'Export CSV'); ?>
    </div>
    <?php if (!$accounts): ?>
      <p class="empty">Add the company’s bank accounts so deposits and withdrawals have a home.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table class="grid">
          <thead>
            <tr>
              <th>Account</th>
              <th>Bank</th>
              <th>Number</th>
              <th class="right">Balance</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($accounts as $a):
                $bal = bank_account_balance((int) $a['id']);
                ?>
              <tr class="<?= (int) ($a['is_active'] ?? 1) === 1 ? '' : 'is-muted' ?>">
                <td><?= h($a['name']) ?><?= (int) ($a['is_active'] ?? 1) === 1 ? '' : ' (archived)' ?></td>
                <td><?= h((string) $a['bank_name']) ?></td>
                <td class="mono"><?= h((string) $a['account_number']) ?></td>
                <td class="right mono"><?= h(money($bal, $ccy)) ?></td>
                <td class="row-actions">
                  <a class="btn ghost sm" href="<?= h(url('pnl_banking.php?view=accounts&edit=' . (int) $a['id'])) ?>">Edit</a>
                  <?php if ((int) ($a['is_active'] ?? 1) === 1): ?>
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="archive_account">
                      <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                      <button class="btn ghost sm" type="submit">Archive</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" onsubmit="return confirm('Remove this account?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_account">
                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                    <button class="btn ghost sm" type="submit">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php elseif ($view === 'reports'):
    $labels = [];
    $depVals = [];
    $wdVals = [];
    foreach ($report['series'] as $key => $vals) {
        $labels[] = pnl_bucket_label((string) $key, $bucket);
        $depVals[] = round((float) $vals['deposits'], 2);
        $wdVals[] = round((float) $vals['withdrawals'], 2);
    }
    $acctLabels = array_keys($report['by_account']);
    $acctDep = [];
    $acctWd = [];
    foreach ($report['by_account'] as $row) {
        $acctDep[] = round((float) $row['deposits'], 2);
        $acctWd[] = round((float) $row['withdrawals'], 2);
    }
    $purposeLabels = array_keys($report['by_purpose']);
    $purposeVals = array_map(static fn ($v) => round((float) $v, 2), array_values($report['by_purpose']));
    $payload = json_encode([
        'labels' => $labels,
        'deposits' => $depVals,
        'withdrawals' => $wdVals,
        'acctLabels' => $acctLabels,
        'acctDep' => $acctDep,
        'acctWd' => $acctWd,
        'purposeLabels' => $purposeLabels,
        'purposeValues' => $purposeVals,
        'currency' => $ccy,
        'color' => brand_color(),
    ], JSON_UNESCAPED_UNICODE);
    ?>
<p class="hint" style="margin:-8px 0 16px">Grouped <?= $bucket === 'day' ? 'by day' : ($bucket === 'week' ? 'by week' : 'by month') ?> for <?= h($rangeLabel) ?>.</p>
<div class="chart-grid">
  <div class="card chart-box">
    <div class="card-head">
      <h2><?= icon('reports', 16) ?>Deposits vs withdrawals</h2>
      <?php if ($labels): render_chart_download('chart-bank-trend', 'banking-trend.png'); endif; ?>
    </div>
    <?php if (!$labels): ?>
      <p class="empty">Nothing in this period to plot.</p>
    <?php else: ?>
      <div class="chart-frame"><canvas id="chart-bank-trend"></canvas></div>
    <?php endif; ?>
  </div>
  <div class="card chart-box">
    <div class="card-head">
      <h2><?= icon('bank', 16) ?>By account</h2>
      <?php if ($acctLabels): render_chart_download('chart-bank-accounts', 'banking-accounts.png'); endif; ?>
    </div>
    <?php if (!$acctLabels): ?>
      <p class="empty">No account movement in this period.</p>
    <?php else: ?>
      <div class="chart-frame"><canvas id="chart-bank-accounts"></canvas></div>
    <?php endif; ?>
  </div>
</div>
<div class="card chart-box" style="margin-bottom:16px">
  <div class="card-head">
    <h2><?= icon('file', 16) ?>Withdrawal purposes</h2>
    <?php if ($purposeLabels): render_chart_download('chart-bank-purpose', 'banking-purposes.png'); endif; ?>
  </div>
  <?php if (!$purposeLabels): ?>
    <p class="empty">No withdrawals with a purpose in this period.</p>
  <?php else: ?>
    <div class="chart-frame"><canvas id="chart-bank-purpose"></canvas></div>
  <?php endif; ?>
</div>

<div class="desk-grid stock-split">
  <div class="card">
    <div class="card-head">
      <h2>Period summary</h2>
      <?php render_csv_link('bank_report', 'Export CSV', ['bucket' => $bucket]); ?>
    </div>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Period</th>
            <th class="right">Deposits</th>
            <th class="right">Withdrawals</th>
            <th class="right">Net</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$report['series']): ?>
            <tr><td colspan="4">No lines in this period.</td></tr>
          <?php else: foreach ($report['series'] as $key => $vals): ?>
            <tr>
              <td><?= h(pnl_bucket_label((string) $key, $bucket)) ?></td>
              <td class="right mono"><?= h(money($vals['deposits'], $ccy)) ?></td>
              <td class="right mono"><?= h(money($vals['withdrawals'], $ccy)) ?></td>
              <td class="right mono"><?= h(money($vals['deposits'] - $vals['withdrawals'], $ccy)) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <td>Total</td>
            <td class="right mono"><?= h(money($totals['deposits'], $ccy)) ?></td>
            <td class="right mono"><?= h(money($totals['withdrawals'], $ccy)) ?></td>
            <td class="right mono"><?= h(money($totals['net'], $ccy)) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h2>By account</h2></div>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Account</th>
            <th class="right">In</th>
            <th class="right">Out</th>
            <th class="right">Net</th>
            <th class="right">Balance now</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$accounts): ?>
            <tr><td colspan="5">No bank accounts yet.</td></tr>
          <?php else: foreach ($accounts as $a):
              $name = (string) $a['name'];
              $row = $report['by_account'][$name] ?? ['deposits' => 0, 'withdrawals' => 0];
              ?>
            <tr>
              <td><?= h($name) ?></td>
              <td class="right mono"><?= h(money($row['deposits'], $ccy)) ?></td>
              <td class="right mono"><?= h(money($row['withdrawals'], $ccy)) ?></td>
              <td class="right mono"><?= h(money($row['deposits'] - $row['withdrawals'], $ccy)) ?></td>
              <td class="right mono"><?= h(money(bank_account_balance((int) $a['id']), $ccy)) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="desk-grid stock-split" style="margin-top:16px">
  <div class="card">
    <div class="card-head"><h2>Depositors</h2></div>
    <?php if (!$totals['depositors']): ?>
      <p class="empty">No depositors in this period.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table class="grid">
          <thead><tr><th>Name</th><th class="right">Deposited</th></tr></thead>
          <tbody>
            <?php foreach ($totals['depositors'] as $name => $amt): ?>
              <tr><td><?= h((string) $name) ?></td><td class="right mono"><?= h(money($amt, $ccy)) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h2>Withdrawers</h2></div>
    <?php if (!$totals['withdrawers']): ?>
      <p class="empty">No withdrawals in this period.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table class="grid">
          <thead><tr><th>Name</th><th class="right">Withdrawn</th></tr></thead>
          <tbody>
            <?php foreach ($totals['withdrawers'] as $name => $amt): ?>
              <tr><td><?= h((string) $name) ?></td><td class="right mono"><?= h(money($amt, $ccy)) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h2>Withdrawal purposes</h2>
  </div>
  <?php if (!$report['by_purpose']): ?>
    <p class="empty">No withdrawal purposes in this period.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead><tr><th>Purpose</th><th class="right">Amount</th></tr></thead>
        <tbody>
          <?php foreach ($report['by_purpose'] as $purpose => $amt): ?>
            <tr><td><?= h((string) $purpose) ?></td><td class="right mono"><?= h(money($amt, $ccy)) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php
    $script = '<script src="' . h(asset('js/chart.umd.min.js')) . '" defer></script><script>
document.addEventListener("DOMContentLoaded", function () {
  var d = ' . $payload . ';
  var brand = d.color || "#1E4EFF";
  if (typeof Chart === "undefined") return;
  Chart.defaults.font.family = "Montserrat, sans-serif";
  Chart.defaults.color = "#66705f";
  function money(v){
    var n = Number(v);
    if (!isFinite(n)) return "";
    var cur = d.currency || "";
    return (cur ? cur + " " : "") + Math.round(n).toLocaleString("en-US");
  }
  var trend = document.getElementById("chart-bank-trend");
  if (trend) {
    new Chart(trend, {
      type: "bar",
      data: {
        labels: d.labels,
        datasets: [
          { label: "Deposits", data: d.deposits, backgroundColor: brand },
          { label: "Withdrawals", data: d.withdrawals, backgroundColor: "#b42318" }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, scales: { y: { ticks: { callback: money } } } }
    });
  }
  var ac = document.getElementById("chart-bank-accounts");
  if (ac) {
    new Chart(ac, {
      type: "bar",
      data: {
        labels: d.acctLabels,
        datasets: [
          { label: "Deposits", data: d.acctDep, backgroundColor: brand },
          { label: "Withdrawals", data: d.acctWd, backgroundColor: "#b42318" }
        ]
      },
      options: { indexAxis: "y", responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, scales: { x: { ticks: { callback: money } } } }
    });
  }
  var purpose = document.getElementById("chart-bank-purpose");
  if (purpose) {
    new Chart(purpose, {
      type: "doughnut",
      data: { labels: d.purposeLabels, datasets: [{ data: d.purposeValues, backgroundColor: [brand,"#1f3a12","#c4a35a","#4a6fa5","#b42318","#6b7c5e","#8d6e63","#546e7a"] }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
    });
  }
});
</script>';
    layout_end($script);
    return;
endif; ?>

<?php if (!$activeAccounts): ?>
  <div class="card">
    <p class="empty">Add a bank account first, then record deposits and withdrawals. <a href="<?= h(url('pnl_banking.php?view=accounts')) ?>">Open accounts</a></p>
  </div>
<?php else: ?>
<div class="desk-grid stock-split">
  <div class="card">
    <div class="card-head"><h2><?= icon('plus', 16) ?>Deposit</h2></div>
    <form method="post" class="pad-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="txn">
      <input type="hidden" name="kind" value="deposit">
      <input type="hidden" name="view" value="activity">
      <label for="dep_account">Bank account</label>
      <select id="dep_account" name="account_id" required>
        <?php foreach ($activeAccounts as $a): ?>
          <option value="<?= (int) $a['id'] ?>"><?= h($a['name']) ?><?= $a['bank_name'] ? ' · ' . h($a['bank_name']) : '' ?></option>
        <?php endforeach; ?>
      </select>
      <label for="dep_date">Date</label>
      <input id="dep_date" type="date" name="txn_date" required value="<?= h(today()) ?>">
      <label for="dep_amount">Amount</label>
      <input id="dep_amount" name="amount" inputmode="decimal" required placeholder="0">
      <label for="dep_person">Depositor’s name</label>
      <input id="dep_person" name="person_name" required maxlength="160" placeholder="Who paid in">
      <label for="dep_notes">Note</label>
      <input id="dep_notes" name="notes" maxlength="500" placeholder="Optional">
      <div class="actions" style="margin-top:12px">
        <button class="btn" type="submit"><?= icon('check') ?>Record deposit</button>
      </div>
    </form>
  </div>
  <div class="card">
    <div class="card-head"><h2><?= icon('upload', 16) ?>Withdraw</h2></div>
    <form method="post" class="pad-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="txn">
      <input type="hidden" name="kind" value="withdraw">
      <input type="hidden" name="view" value="activity">
      <label for="w_account">Bank account</label>
      <select id="w_account" name="account_id" required>
        <?php foreach ($activeAccounts as $a): ?>
          <option value="<?= (int) $a['id'] ?>"><?= h($a['name']) ?><?= $a['bank_name'] ? ' · ' . h($a['bank_name']) : '' ?></option>
        <?php endforeach; ?>
      </select>
      <label for="w_date">Date</label>
      <input id="w_date" type="date" name="txn_date" required value="<?= h(today()) ?>">
      <label for="w_amount">Amount</label>
      <input id="w_amount" name="amount" inputmode="decimal" required placeholder="0">
      <label for="w_person">Withdrawer’s name</label>
      <input id="w_person" name="person_name" required maxlength="160" placeholder="Who is taking the money">
      <label for="w_purpose">Purpose of withdrawal</label>
      <input id="w_purpose" name="purpose" required maxlength="255" placeholder="Rent, stock, salaries">
      <label for="w_notes">Note</label>
      <input id="w_notes" name="notes" maxlength="500" placeholder="Optional">
      <div class="actions" style="margin-top:12px">
        <button class="btn" type="submit"><?= icon('check') ?>Record withdrawal</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h2><?= icon('file', 16) ?>Deposits and withdrawals</h2>
    <?php render_csv_link('banking', 'Export CSV'); ?>
  </div>
  <?php if (!$txns): ?>
    <p class="empty">No deposits or withdrawals in this period.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Account</th>
            <th>Person</th>
            <th>Purpose</th>
            <th class="right">Amount</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($txns as $t): ?>
            <tr>
              <td><?= h(format_date($t['txn_date'])) ?></td>
              <td><?= ($t['kind'] ?? '') === 'withdraw' ? 'Withdrawal' : 'Deposit' ?></td>
              <td><?= h((string) $t['account_name']) ?></td>
              <td><?= h((string) $t['person_name']) ?></td>
              <td><?= h((string) ($t['purpose'] ?: ($t['notes'] ?? ''))) ?></td>
              <td class="right mono"><?= h(money((float) $t['amount'], $ccy)) ?></td>
              <td class="row-actions">
                <form method="post" onsubmit="return confirm('Remove this line?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_txn">
                  <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                  <button class="btn ghost sm" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php
layout_end();
