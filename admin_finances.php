<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$period = period_range();
$from = $period['from'] !== '' ? $period['from'] : '2000-01-01';
$to = $period['to'] !== '' ? $period['to'] : desk_now()->format('Y-m-d');

$payId = (int) ($_GET['pay'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) post('action');
    if ($action === 'make_payment') {
        $cid = (int) post('company_id');
        $co = $cid > 0 ? db_one('SELECT * FROM companies WHERE id = ?', 'i', [$cid]) : null;
        if (!$co) {
            $error = 'Pick a company to record a payment.';
            $payId = $cid;
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
            } elseif ($term <= 0) {
                $error = 'Set the paid term (weeks, months or years) before recording payment.';
                $payId = $cid;
            } else {
                $expires = compute_expiry_date($paidFrom, $term, $unit);
                if (!$expires) {
                    $error = 'Could not calculate the expiry date. Check the start date and term.';
                    $payId = $cid;
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

$allUsd = 0.0;
$periodUsd = 0.0;
$ledger = [];
try {
    $row = db_one('SELECT COALESCE(SUM(amount_usd),0) AS t FROM platform_fee_ledger');
    $allUsd = (float) ($row['t'] ?? 0);
    $row = db_one('SELECT COALESCE(SUM(amount_usd),0) AS t FROM platform_fee_ledger WHERE DATE(occurred_at) BETWEEN ? AND ?', 'ss', [$from, $to]);
    $periodUsd = (float) ($row['t'] ?? 0);
    $ledger = db_all(
        'SELECT l.*, c.name AS company_name
         FROM platform_fee_ledger l
         LEFT JOIN companies c ON c.id = l.company_id
         WHERE DATE(l.occurred_at) BETWEEN ? AND ?
         ORDER BY l.occurred_at DESC, l.id DESC
         LIMIT 200',
        'ss',
        [$from, $to]
    );
} catch (Throwable $e) {
    $ledger = [];
}

$companies = db_all('SELECT * FROM companies ORDER BY name');
$feeBalance = 0.0;
foreach ($companies as $c) {
    $feeBalance += platform_convert(company_fee_balance($c), company_fee_currency($c), 'USD');
}

$months = month_axis(12);
$monthSum = array_fill_keys($months, 0.0);
try {
    $series = db_all('SELECT DATE_FORMAT(occurred_at, "%Y-%m") AS ym, SUM(amount_usd) AS t FROM platform_fee_ledger GROUP BY ym');
    foreach ($series as $s) {
        $ym = (string) ($s['ym'] ?? '');
        if (isset($monthSum[$ym])) {
            $monthSum[$ym] = (float) $s['t'];
        }
    }
} catch (Throwable $e) {
}

$ccy = platform_currency();
$chart = [];
foreach ($months as $ym) {
    $chart[] = round(platform_convert($monthSum[$ym], 'USD', $ccy), 2);
}

$payCompany = null;
foreach ($companies as $c) {
    if ((int) $c['id'] === $payId) {
        $payCompany = $c;
        break;
    }
}

layout_admin_start('Finances', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('bank') ?>Finances</h1>
    <p class="lede">Companies, amounts paid and balances. Record a payment, preview the receipt, and share it on WhatsApp.</p>
  </div>
</div>
<?php render_filters('admin_finances.php', [], ['live' => true]); ?>
<?php if ($error): ?><p class="flash flash-err"><?= h($error) ?></p><?php endif; ?>

<div class="stats">
  <div class="card stat"><?= icon('invoice', 20) ?><span>This period</span><strong><?= h(platform_money($periodUsd, 'USD')) ?></strong></div>
  <div class="card stat"><?= icon('bank', 20) ?><span>All time</span><strong><?= h(platform_money($allUsd, 'USD')) ?></strong></div>
  <div class="card stat"><?= icon('receipt', 20) ?><span>Still due on terms</span><strong><?= h(platform_money($feeBalance, 'USD')) ?></strong></div>
  <div class="card stat"><?= icon('building', 20) ?><span>Paying companies</span><strong><?= count(array_filter($companies, static fn ($c) => company_fee_paid($c) > 0)) ?></strong></div>
</div>

<div class="card chart-box" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('reports', 16) ?>Taken in over time</h2></div>
  <?php if (array_sum($chart) <= 0): ?>
    <p class="empty">When a package is paid or you record a payment, it plots here in <?= h($ccy) ?>.</p>
  <?php else: ?>
    <canvas id="chart-finance"></canvas>
  <?php endif; ?>
</div>

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
      Saving adds this payment, updates the balance, then opens the receipt to preview and share on WhatsApp.
    </p>
    <div class="actions">
      <button class="btn" type="submit"><?= icon('check', 16) ?>Record payment</button>
      <a class="btn ghost" href="<?= h(url('admin_finances.php')) ?>">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

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
                  <a class="btn icon-only" href="<?= h(url('admin_finances.php?pay=' . $cid . '#make-payment')) ?>" title="Make payment" aria-label="Make payment"><?= icon('bank', 15) ?></a>
                  <?php if ($hasTerm): ?>
                    <a class="btn icon-only" href="<?= h(url('admin_payment_receipt.php?id=' . $cid)) ?>" title="Preview receipt" aria-label="Preview receipt"><?= icon('eye', 15) ?></a>
                    <?php if ($wa !== ''): ?>
                      <a class="btn icon-only" href="<?= h($wa) ?>" target="_blank" rel="noopener" title="Share receipt on WhatsApp" aria-label="Share on WhatsApp"><?= icon('share', 15) ?></a>
                    <?php endif; ?>
                  <?php else: ?>
                    <a class="btn icon-only" href="<?= h(url('admin_company.php?id=' . $cid)) ?>" title="Set paid term first" aria-label="Set paid term"><?= icon('calendar', 15) ?></a>
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

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('receipt', 16) ?>Money in</h2></div>
  <?php if (!$ledger): ?>
    <p class="empty">No payments in this date range.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
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
                  <div class="actions">
                    <a class="btn icon-only" href="<?= h(url('admin_payment_receipt.php?id=' . $lcid . '&paid=' . rawurlencode((string) $row['amount']))) ?>" title="Preview receipt" aria-label="Preview receipt"><?= icon('eye', 15) ?></a>
                  </div>
                <?php else: ?>-<?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if (array_sum($chart) > 0): ?>
<script src="<?= h(asset('js/chart.umd.min.js')) ?>"></script>
<script>
(function () {
  var el = document.getElementById('chart-finance');
  if (!el || !window.Chart) return;
  new Chart(el, {
    type: 'line',
    data: {
      labels: <?= json_encode($months) ?>,
      datasets: [{ label: <?= json_encode('Taken in (' . $ccy . ')') ?>, data: <?= json_encode($chart) ?>, borderColor: '#1E4EFF', tension: 0.25, fill: false }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });
})();
</script>
<?php endif; ?>
<?php layout_end(); ?>
