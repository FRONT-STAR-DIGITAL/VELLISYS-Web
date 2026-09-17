<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_pnl();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    try {
        if ($action === 'delete_move') {
            pnl_savings_move_delete((int) post('id'));
            flash('Savings line removed.');
            redirect('pnl_savings.php');
        }
        if ($action === 'move') {
            pnl_savings_move_save([
                'kind' => post('kind'),
                'amount' => post('amount'),
                'move_date' => post('move_date'),
                'person_name' => post('person_name'),
                'purpose' => post('purpose'),
                'notes' => post('notes'),
            ]);
            flash(post('kind') === 'withdraw' ? 'Withdrawal recorded.' : 'Deposit recorded.');
            redirect('pnl_savings.php');
        }
        pnl_savings_save([
            'target_amount' => money_parse(post('target_amount')),
            'note' => post('note'),
        ]);
        flash('Savings target saved.');
        redirect('pnl_savings.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$summary = pnl_summary();
$goal = pnl_savings_get();
$ccy = $summary['currency'];
$net = (float) $summary['net'];
$target = (float) $goal['target_amount'];
$saved = (float) $goal['saved_amount'];
$progressNet = $target > 0 ? min(100, max(0, round(($net / $target) * 100, 1))) : 0;
$progressSaved = $target > 0 ? min(100, max(0, round(($saved / $target) * 100, 1))) : 0;
$period = period_range();
$rangeLabel = $period['from'] ? format_date($period['from']) . ' – ' . format_date($period['to']) : 'all dates';
$moves = pnl_savings_moves();
$moveIn = 0.0;
$moveOut = 0.0;
foreach ($moves as $m) {
    if (($m['kind'] ?? '') === 'withdraw') {
        $moveOut += (float) $m['amount'];
    } else {
        $moveIn += (float) $m['amount'];
    }
}

layout_start('Savings', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('wallet') ?>Savings</h1>
    <p class="lede">Set a target, then deposit and withdraw against the amount set aside. Profit here is selling minus buying. Net profit is that profit minus expenses for <?= h($rangeLabel) ?>.</p>
  </div>
  <div class="actions page-actions">
    <?php render_csv_link('savings', 'Export CSV'); ?>
    <a class="btn ghost" href="<?= h(url('pnl_banking.php')) ?>"><?= icon('bank', 16) ?>Banking</a>
  </div>
</div>
<?php render_pnl_subnav('pnl_savings.php'); ?>
<?php render_filters('pnl_savings.php'); ?>
<?php if ($error): ?><p class="flash flash-err"><?= h($error) ?></p><?php endif; ?>

<div class="stats">
  <div class="card stat"><?= icon('package', 20) ?><span>Profit</span><strong><?= h(money($summary['profit'] ?? 0, $ccy)) ?></strong><em>Sell minus buy</em></div>
  <div class="card stat"><?= icon('reports', 20) ?><span>Net profit</span><strong class="<?= $net < 0 ? 'neg' : 'pos' ?>"><?= h(money($net, $ccy)) ?></strong></div>
  <div class="card stat"><?= icon('wallet', 20) ?><span>Target</span><strong><?= h(money($target, $ccy)) ?></strong></div>
  <div class="card stat"><?= icon('bank', 20) ?><span>Set aside</span><strong><?= h(money($saved, $ccy)) ?></strong></div>
  <div class="card stat"><?= icon('plus', 20) ?><span>Deposits</span><strong><?= h(money($moveIn, $ccy)) ?></strong><em><?= h($rangeLabel) ?></em></div>
  <div class="card stat"><?= icon('upload', 20) ?><span>Withdrawals</span><strong><?= h(money($moveOut, $ccy)) ?></strong><em><?= h($rangeLabel) ?></em></div>
</div>

<div class="desk-grid stock-split">
  <div class="card">
    <div class="card-head"><h2><?= icon('reports', 16) ?>Towards the target</h2></div>
    <div class="pad-form">
      <?php if ($target <= 0): ?>
        <p class="empty">Set a target to see how this period’s net profit compares.</p>
      <?php else: ?>
        <p class="lede">Net profit is <?= h((string) $progressNet) ?>% of the target. Amount set aside is <?= h((string) $progressSaved) ?>%.</p>
        <div class="savings-bar" role="img" aria-label="Net profit towards target">
          <span style="width:<?= h((string) $progressNet) ?>%"></span>
        </div>
        <p class="hint" style="margin-top:12px">Set aside</p>
        <div class="savings-bar is-saved" role="img" aria-label="Amount set aside">
          <span style="width:<?= h((string) $progressSaved) ?>%"></span>
        </div>
        <?php if ($net >= $target && $target > 0): ?>
          <p class="hint" style="margin-top:12px">This period’s net profit has reached the target.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h2><?= icon('settings', 16) ?>Target</h2></div>
    <form method="post" class="pad-form">
      <?= csrf_field() ?>
      <label for="target_amount">Savings target</label>
      <input id="target_amount" name="target_amount" inputmode="decimal" value="<?= h($target > 0 ? (string) $target : '') ?>" placeholder="0">
      <label for="note">Note</label>
      <input id="note" name="note" value="<?= h($goal['note']) ?>" maxlength="500" placeholder="School fees, stock float, tax">
      <div class="actions" style="margin-top:12px">
        <button class="btn" type="submit"><?= icon('check') ?>Save target</button>
      </div>
    </form>
  </div>
</div>

<div class="desk-grid stock-split" style="margin-top:16px">
  <div class="card">
    <div class="card-head"><h2><?= icon('plus', 16) ?>Deposit</h2></div>
    <form method="post" class="pad-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="move">
      <input type="hidden" name="kind" value="deposit">
      <label for="dep_date">Date</label>
      <input id="dep_date" type="date" name="move_date" required value="<?= h(today()) ?>">
      <label for="dep_amount">Amount</label>
      <input id="dep_amount" name="amount" inputmode="decimal" required placeholder="0">
      <label for="dep_person">Depositor’s name</label>
      <input id="dep_person" name="person_name" required maxlength="160" placeholder="Who is putting this aside">
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
      <input type="hidden" name="action" value="move">
      <input type="hidden" name="kind" value="withdraw">
      <label for="w_date">Date</label>
      <input id="w_date" type="date" name="move_date" required value="<?= h(today()) ?>">
      <label for="w_amount">Amount</label>
      <input id="w_amount" name="amount" inputmode="decimal" required placeholder="0">
      <label for="w_person">Withdrawer’s name</label>
      <input id="w_person" name="person_name" required maxlength="160" placeholder="Who is taking this out">
      <label for="w_purpose">Purpose of withdrawal</label>
      <input id="w_purpose" name="purpose" required maxlength="255" placeholder="School fees, stock, tax">
      <label for="w_notes">Note</label>
      <input id="w_notes" name="notes" maxlength="500" placeholder="Optional">
      <div class="actions" style="margin-top:12px">
        <button class="btn" type="submit"><?= icon('check') ?>Record withdrawal</button>
      </div>
    </form>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h2><?= icon('file', 16) ?>Savings movements</h2>
    <?php render_csv_link('savings', 'Export CSV'); ?>
  </div>
  <?php if (!$moves): ?>
    <p class="empty">No deposits or withdrawals in this period.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Person</th>
            <th>Purpose</th>
            <th class="right">Amount</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($moves as $m): ?>
            <tr>
              <td><?= h(format_date($m['move_date'])) ?></td>
              <td><?= ($m['kind'] ?? '') === 'withdraw' ? 'Withdrawal' : 'Deposit' ?></td>
              <td><?= h((string) $m['person_name']) ?></td>
              <td><?= h((string) ($m['purpose'] ?: ($m['notes'] ?? ''))) ?></td>
              <td class="right mono"><?= h(money((float) $m['amount'], $ccy)) ?></td>
              <td class="row-actions">
                <form method="post" onsubmit="return confirm('Remove this line?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_move">
                  <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
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
