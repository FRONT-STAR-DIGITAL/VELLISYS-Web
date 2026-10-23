<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_sales_agent();

$companies = sales_onboarded_companies_for_agent((int) $user['id']);
$withBalance = 0;
foreach ($companies as $c) {
    if (company_fee_balance($c) > 0.009) {
        $withBalance++;
    }
}

$paidLabel = money(0);
$balanceLabel = money(0);
$feeLabel = money(0);
if (function_exists('platform_companies_fee_totals')) {
    $totals = platform_companies_fee_totals($companies);
    $sumCcy = (string) ($totals['currency'] ?? 'USD');
    $paidLabel = money((float) $totals['paid'], $sumCcy);
    $balanceLabel = money((float) $totals['balance'], $sumCcy);
    $feeLabel = money((float) $totals['amount'], $sumCcy);
}

sales_layout_start('Companies', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('building') ?>Companies</h1>
    <p class="lede">Your onboarded clients - companies assigned to you as sales. When admin updates amount paid, fee, paid and balance update here.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn ghost" href="<?= h(url('sales_testing.php')) ?>"><?= icon('desk', 14) ?>On Testing</a>
    <a class="btn" href="<?= h(url('sales_leads.php?status=onboarded')) ?>"><?= icon('flag', 14) ?>Onboarded leads</a>
  </div>
</div>

<?php if ($companies): ?>
<div class="stats" style="margin-bottom:16px">
  <div class="card stat"><?= icon('flag', 20) ?><span>Onboarded clients</span><strong><?= count($companies) ?></strong></div>
  <div class="card stat"><?= icon('bank', 20) ?><span>Amount paid</span><strong><?= h($paidLabel) ?></strong></div>
  <div class="card stat"><?= icon('reports', 20) ?><span>Balance left</span><strong><?= h($balanceLabel) ?></strong><?php if ($withBalance): ?><em class="muted"><?= (int) $withBalance ?> with balance</em><?php endif; ?></div>
  <div class="card stat"><?= icon('clients', 20) ?><span>Fee total</span><strong><?= h($feeLabel) ?></strong></div>
</div>
<?php endif; ?>

<div class="card">
  <?php if (!$companies): ?>
    <p class="empty">No onboarded clients yet. When admin assigns a company to you - or a lead of yours is onboarded - it shows here with fee, amount paid and balance.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Client</th>
            <th>Contact</th>
            <th>Period</th>
            <th class="right">Fee</th>
            <th class="right">Amount paid</th>
            <th class="right">Balance left</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($companies as $c):
              $leadId = (int) ($c['lead_id'] ?? 0);
              $contact = trim((string) ($c['contact_name'] ?? ''));
              $phone = trim((string) ($c['contact_phone'] ?? ''));
              $paid = company_fee_paid($c);
              $balance = company_fee_balance($c);
              $feeCcy = company_fee_currency($c);
              $period = function_exists('payment_receipt_period_label')
                  ? payment_receipt_period_label($c)
                  : '-';
              ?>
            <tr>
              <td>
                <strong><?= h((string) ($c['name'] ?: ($c['business_name'] ?? 'Client'))) ?></strong>
                <?php if (!empty($c['lead_city'])): ?>
                  <div class="muted"><?= h((string) $c['lead_city']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($contact !== '' || $phone !== ''): ?>
                  <?= h(trim($contact . ($contact && $phone ? ' · ' : '') . $phone)) ?>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
              <td><?= h($period !== '' ? $period : '-') ?></td>
              <td class="right mono"><?= h(money(company_fee_amount($c), $feeCcy)) ?></td>
              <td class="right mono"><?= h(money($paid, $feeCcy)) ?></td>
              <td class="right mono<?= $balance > 0.009 ? ' neg' : ' pos' ?>">
                <?= h(money($balance, $feeCcy)) ?>
              </td>
              <td class="row-actions">
                <?php if ($leadId > 0): ?>
                  <a class="btn ghost sm" href="<?= h(url('sales_lead_edit.php?id=' . $leadId)) ?>">Lead</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="hint">Balances come from company Amount paid on admin. Installment updates show here as soon as admin saves them.</p>
  <?php endif; ?>
</div>
<?php sales_layout_end(); ?>
