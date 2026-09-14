<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_pnl();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        pnl_savings_save([
            'target_amount' => money_parse(post('target_amount')),
            'saved_amount' => money_parse(post('saved_amount')),
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

layout_start('Savings', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('wallet') ?>Savings</h1>
    <p class="lede">Set a savings target. Profit here is selling minus buying. Net profit is that profit minus expenses for <?= h($rangeLabel) ?>.</p>
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
      <label for="saved_amount">Amount already set aside</label>
      <input id="saved_amount" name="saved_amount" inputmode="decimal" value="<?= h($saved > 0 ? (string) $saved : '') ?>" placeholder="0">
      <label for="note">Note</label>
      <input id="note" name="note" value="<?= h($goal['note']) ?>" maxlength="500" placeholder="School fees, stock float, tax">
      <div class="actions" style="margin-top:12px">
        <button class="btn" type="submit"><?= icon('check') ?>Save target</button>
      </div>
    </form>
  </div>
</div>
<?php
layout_end();
