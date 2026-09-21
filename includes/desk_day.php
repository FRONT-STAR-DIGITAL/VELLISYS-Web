<?php
declare(strict_types=1);

function render_desk_day(string $error = ''): string
{
    $period = period_range();
    $from = $period['from'] !== '' ? $period['from'] : today();
    $to = $period['to'] !== '' ? $period['to'] : today();
    $dash = stock_day_dashboard($from, $to);
    $rangeLive = $dash['totals'];
    $showProfit = !empty($dash['show_profit']);
    $daySales = $dash['sales'];
    $daySpend = $dash['spend'];
    $daySold = $dash['sold'] ?? [];
    $float = $dash['float'] ?? stock_float_vs_expenses($from, $to, (float) $rangeLive['expense']);
    $todayDay = stock_today();
    $dayOpen = stock_day_is_open();
    $stats = stock_stats();
    $items = stock_items(false);
    $q = stock_q();
    $base = desk_day_url();
    $dayRows = [];
    foreach (array_reverse($dash['days'], true) as $d => $row) {
        $dayRows[] = ['date' => $d] + $row;
    }
    $daysPage = stock_slice($dayRows, stock_page_key('dp'));
    $monthRows = [];
    foreach (array_reverse($dash['months'], true) as $m => $row) {
        $monthRows[] = ['date' => $m] + $row;
    }
    $stockSnap = stock_slice(stock_filter_items(stock_goods_only($items), $q), stock_page_key('ip'));
    $rangeLabel = $from === $to ? format_date($from) : (format_date($from) . ' – ' . format_date($to));
    $chartDays = [
        'labels' => array_map(static fn ($d) => date('j M', strtotime((string) $d)), array_keys($dash['days'])),
        'income' => array_column(array_values($dash['days']), 'income'),
        'expense' => array_column(array_values($dash['days']), 'expense'),
        'profit' => array_column(array_values($dash['days']), 'profit'),
        'net' => array_column(array_values($dash['days']), 'net'),
    ];
    $chartMonths = [
        'labels' => array_keys($dash['months']),
        'income' => array_column(array_values($dash['months']), 'income'),
        'expense' => array_column(array_values($dash['months']), 'expense'),
        'profit' => array_column(array_values($dash['months']), 'profit'),
        'net' => array_column(array_values($dash['months']), 'net'),
    ];
    ?>
<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>
<?php render_filters('dashboard.php', [], ['no_all' => true, 'live' => true]); ?>
<p class="hint" style="margin:-8px 0 12px">Showing <?= h($rangeLabel) ?>. Sales include invoices, Sale till and quick receipts (amount received counts as services when the receipt was not a till sale). Profit is selling price minus buying price on goods sold. Net profit is that profit minus expenses (stock purchases are not counted twice).</p>
<?php render_desk_day_float_note($float); ?>
<div class="stats" id="day-stats" data-day-stats>
  <div class="card stat"><?= icon('clock', 20) ?><span>Today</span><strong><?= $dayOpen ? 'Open' : ($todayDay ? 'Closed' : 'Not opened') ?></strong></div>
  <a class="card stat" href="#day-income"><?= icon('invoice', 20) ?><span>Income</span><strong data-stat="income"><?= h(money($rangeLive['income'])) ?></strong></a>
  <a class="card stat" href="#day-spend"><?= icon('expense', 20) ?><span>Expenditure</span><strong data-stat="expense"><?= h(money($rangeLive['expense'])) ?></strong></a>
  <?php if ($showProfit): ?>
  <div class="card stat"><?= icon('package', 20) ?><span>Profit</span><strong data-stat="profit"><?= h(money($rangeLive['profit'])) ?></strong><em>Sell minus buy</em></div>
  <div class="card stat"><?= icon('wallet', 20) ?><span>Net profit</span><strong data-stat="net"><?= h(money($rangeLive['net'])) ?></strong><em>Tax <?= h(money($rangeLive['tax'])) ?></em></div>
  <?php endif; ?>
</div>
<div class="stats">
  <div class="card stat"><?= icon('package', 20) ?><span>Products</span><strong><?= (int) $stats['items'] ?></strong></div>
  <div class="card stat"><?= icon('bank', 20) ?><span>Stock at cost</span><strong><?= h(money($stats['cost'])) ?></strong></div>
  <div class="card stat"><?= icon('alert', 20) ?><span>Low stock</span><strong><?= (int) $stats['low'] ?></strong></div>
</div>
<div class="desk-grid stock-split">
  <div class="card">
    <div class="card-head"><h2><?= icon('clock', 16) ?><?= $dayOpen ? 'Close this day' : 'Open this day' ?></h2></div>
    <div class="pad-form">
      <?php if (!$todayDay): ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="open_day">
          <label for="open_cash">Cash you started with</label>
          <input id="open_cash" name="open_cash" inputmode="decimal" required>
          <p class="hint">Count the till. Type that amount. Expenses today are cleared from this float first, and that is noted on Day.</p>
          <div class="actions" style="margin-top:12px"><button class="btn" type="submit"><?= icon('check') ?>Open day</button></div>
        </form>
      <?php elseif ($dayOpen): ?>
        <p class="lede">Started with <?= h(money((float) $todayDay['open_cash'])) ?>.</p>
        <?php
        $todayFloat = stock_float_vs_expenses(today(), today(), (float) (stock_day_totals(today())['expense'] ?? 0));
        render_desk_day_float_note($todayFloat);
        ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="close_day">
          <label for="close_cash">Cash you closed with</label>
          <input id="close_cash" name="close_cash" inputmode="decimal" required>
          <label for="notes">Note</label>
          <input id="notes" name="notes">
          <div class="actions" style="margin-top:12px"><button class="btn" type="submit"><?= icon('check') ?>Close day</button></div>
        </form>
      <?php else: ?>
        <p class="lede">Closed with <?= h(money((float) ($todayDay['close_cash'] ?? 0))) ?>.</p>
        <?php
        $todayFloat = stock_float_vs_expenses(today(), today(), (float) (stock_day_totals(today())['expense'] ?? 0));
        render_desk_day_float_note($todayFloat);
        ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h2><?= icon('reports', 16) ?>In this period</h2></div>
    <div class="pad-form"><canvas id="chart-stock-days" height="180"></canvas></div>
  </div>
</div>
<div class="card" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('reports', 16) ?>Months</h2></div>
  <div class="pad-form"><canvas id="chart-stock-months" height="180"></canvas></div>
</div>
<div class="card" id="day-income" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('invoice', 16) ?>Sales</h2></div>
  <div class="pad-form"><?php stock_search_bar('dashboard.php', ['range' => $period['preset'], 'from' => $from, 'to' => $to], 'Search sales and receipts'); ?></div>
  <p class="hint" style="margin:0 22px 12px">Invoices, Sale till and quick receipts. Receipts issued without a sale still count here as services for the amount received.</p>
  <?php render_stock_docs_table($daySales, $base, 'sp', 'No sales in this period.'); ?>
</div>
<div class="card" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('package', 16) ?>Products and services sold</h2></div>
  <p class="hint" style="margin:0 22px 12px">Goods and services from Sale, invoices and quick receipts in this period.</p>
  <?php render_stock_sold_table($daySold); ?>
</div>
<div class="card" id="day-spend" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('expense', 16) ?>Expenses</h2></div>
  <?php render_stock_docs_table($daySpend, $base, 'ep', 'No expenses in this period.'); ?>
</div>
<div class="card" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('clock', 16) ?>Daily performance</h2></div>
  <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>#</th>
          <th>Date</th>
          <th class="right">Income</th>
          <th class="right">Spend</th>
          <?php if ($showProfit): ?><th class="right">Profit</th><th class="right">Net</th><?php endif; ?>
          <th class="right">Tax</th>
        </tr>
      </thead>
      <tbody>
        <?php $dn = (int) $daysPage['from']; foreach ($daysPage['rows'] as $d): ?>
          <tr>
            <td class="mono"><?= $dn++ ?></td>
            <td><?= h(format_date($d['date'])) ?></td>
            <td class="right mono"><?= h(money((float) $d['income'])) ?></td>
            <td class="right mono"><?= h(money((float) $d['expense'])) ?></td>
            <?php if ($showProfit): ?>
              <td class="right mono"><?= h(money((float) $d['profit'])) ?></td>
              <td class="right mono"><?= h(money((float) ($d['net'] ?? 0))) ?></td>
            <?php endif; ?>
            <td class="right mono"><?= h(money((float) $d['tax'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php stock_pager($base, (int) $daysPage['page'], (int) $daysPage['pages'], 'dp'); ?>
</div>
<div class="card" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('calendar', 16) ?>Monthly performance</h2></div>
  <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>#</th>
          <th>Month</th>
          <th class="right">Income</th>
          <th class="right">Spend</th>
          <?php if ($showProfit): ?><th class="right">Profit</th><th class="right">Net</th><?php endif; ?>
          <th class="right">Tax</th>
        </tr>
      </thead>
      <tbody>
        <?php $mn = 1; foreach ($monthRows as $d): ?>
          <tr>
            <td class="mono"><?= $mn++ ?></td>
            <td><?= h($d['date']) ?></td>
            <td class="right mono"><?= h(money((float) $d['income'])) ?></td>
            <td class="right mono"><?= h(money((float) $d['expense'])) ?></td>
            <?php if ($showProfit): ?>
              <td class="right mono"><?= h(money((float) $d['profit'])) ?></td>
              <td class="right mono"><?= h(money((float) ($d['net'] ?? 0))) ?></td>
            <?php endif; ?>
            <td class="right mono"><?= h(money((float) $d['tax'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="card" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('package', 16) ?>Stock now</h2></div>
  <div class="table-scroll">
    <table class="grid">
      <thead><tr><th>#</th><th>Item</th><th class="right">On hand</th><th class="right">At cost</th><th class="right">At sell</th><th>Actions</th></tr></thead>
      <tbody>
        <?php $sn = (int) $stockSnap['from']; foreach ($stockSnap['rows'] as $row): ?>
          <tr>
            <td class="mono"><?= $sn++ ?></td>
            <td><?= h($row['name']) ?></td>
            <td class="right mono"><?= h(stock_qty_label((float) $row['qty_on_hand'])) ?></td>
            <td class="right mono"><?= h(money((float) $row['qty_on_hand'] * (float) $row['buy_price'])) ?></td>
            <td class="right mono"><?= h(money((float) $row['qty_on_hand'] * (float) $row['sell_price'])) ?></td>
            <td class="row-actions">
              <a class="btn ghost sm" href="<?= h(url('stock.php?tab=items&edit=' . (int) $row['id'])) ?>"><?= icon('pencil', 14) ?>Edit</a>
              <?php stock_delete_button((int) $row['id']); ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php stock_pager($base, (int) $stockSnap['page'], (int) $stockSnap['pages'], 'ip'); ?>
</div>
<?php
    $payload = json_encode([
        'days' => $chartDays,
        'months' => $chartMonths,
        'currency' => default_currency(),
        'color' => branding()['brand_color'] ?? '#82B440',
        'showProfit' => $showProfit,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    return '<script src="' . h(asset('js/chart.umd.min.js')) . '"></script><script>window.vellisysDayCharts=' . $payload . ';</script><script src="' . h(asset('js/stock-day.js')) . '"></script>';
}
