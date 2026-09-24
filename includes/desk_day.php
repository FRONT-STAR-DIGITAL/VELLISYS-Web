<?php
declare(strict_types=1);

function desk_name_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        if (mb_strlen($letters) >= 2) {
            break;
        }
    }
    return $letters !== '' ? $letters : 'V';
}

function desk_pct_trend(float $current, float $previous): array
{
    if ($previous <= 0.009 && $current <= 0.009) {
        return ['tone' => 'flat', 'text' => '— 0%'];
    }
    if ($previous <= 0.009) {
        return ['tone' => 'up', 'text' => '+ 100%'];
    }
    $pct = (int) round((($current - $previous) / abs($previous)) * 100);
    if ($pct > 0) {
        return ['tone' => 'up', 'text' => '+ ' . $pct . '%'];
    }
    if ($pct < 0) {
        return ['tone' => 'down', 'text' => '↓ ' . abs($pct) . '%'];
    }
    return ['tone' => 'flat', 'text' => '— 0%'];
}

function desk_sparkline(array $values, string $stroke = ''): string
{
    $values = array_values(array_map('floatval', $values));
    if (count($values) < 2) {
        $values = $values ? [$values[0], $values[0]] : [0, 0];
    }
    $min = min($values);
    $max = max($values);
    $span = max(0.0001, $max - $min);
    $w = 72;
    $h = 28;
    $n = count($values);
    $pts = [];
    foreach ($values as $i => $v) {
        $x = $n === 1 ? 0 : ($i / ($n - 1)) * $w;
        $y = $h - (($v - $min) / $span) * ($h - 4) - 2;
        $pts[] = round($x, 1) . ',' . round($y, 1);
    }
    $color = $stroke !== '' ? $stroke : 'var(--brand)';
    return '<svg class="cdash-spark" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" aria-hidden="true"><polyline fill="none" stroke="' . h($color) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="' . h(implode(' ', $pts)) . '"/></svg>';
}

function desk_minibars(array $values, string $tone = 'brand'): string
{
    $values = array_values(array_map('floatval', $values));
    if (!$values) {
        $values = [0, 0, 0, 0, 0, 0, 0];
    }
    $max = max(1.0, max(array_map('abs', $values)));
    $html = '<span class="cdash-minibars is-' . h($tone) . '" aria-hidden="true">';
    foreach ($values as $v) {
        $pct = max(8, (int) round((abs($v) / $max) * 100));
        $html .= '<i style="height:' . $pct . '%"></i>';
    }
    return $html . '</span>';
}

function desk_period_shift(string $from, string $to): array
{
    $start = strtotime($from);
    $end = strtotime($to);
    if (!$start || !$end || $end < $start) {
        $today = today();
        return [$today, $today];
    }
    $days = (int) round(($end - $start) / 86400) + 1;
    $prevEnd = date('Y-m-d', $start - 86400);
    $prevFrom = date('Y-m-d', strtotime($prevEnd . ' -' . ($days - 1) . ' days'));
    return [$prevFrom, $prevEnd];
}

function render_desk_company_card(?array $company, array $opts = []): void
{
    $brand = branding();
    $name = trim((string) ($company['name'] ?? $brand['name'] ?? 'Desk'));
    $status = (string) ($company['status'] ?? 'live');
    $statusLabel = match ($status) {
        'live' => 'Active',
        'onboarding' => 'Setup',
        'suspended' => 'Suspended',
        default => ucfirst($status !== '' ? $status : 'Active'),
    };
    $statusClass = match ($status) {
        'live' => 'is-ok',
        'onboarding' => 'is-warn',
        'suspended' => 'is-bad',
        default => 'is-ok',
    };
    $since = '';
    $created = substr((string) ($company['created_at'] ?? ''), 0, 10);
    if ($created !== '' && preg_match('/^\d{4}/', $created)) {
        $since = 'Since ' . substr($created, 0, 4);
    }
    $canQuote = !empty($opts['can_quote']);
    $canInvoice = !empty($opts['can_invoice']);
    $stockOn = !empty($opts['stock']);
    $logo = logo_url($brand);
    $initials = desk_name_initials($name);
    ?>
<div class="cdash-company card">
  <div class="cdash-company-main">
    <div class="cdash-company-mark" aria-hidden="true">
      <?php if ($logo !== ''): ?>
        <img src="<?= h($logo) ?>" alt="">
      <?php else: ?>
        <span><?= h($initials) ?></span>
      <?php endif; ?>
    </div>
    <div class="cdash-company-copy">
      <h1><?= h($name) ?></h1>
      <div class="cdash-badges">
        <span class="cdash-badge <?= $statusClass ?>"><?= h($statusLabel) ?></span>
        <?php if ($since !== ''): ?><span class="cdash-badge"><?= h($since) ?></span><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="cdash-actions">
    <?php if ($canQuote): ?><a class="btn ghost" href="<?= h(url('document_new.php?kind=quotation')) ?>"><?= icon('quotation', 16) ?>Quotation</a><?php endif; ?>
    <?php if ($canInvoice): ?><a class="btn" href="<?= h(url('document_new.php?kind=invoice')) ?>"><?= icon('invoice', 16) ?>Invoice</a><?php endif; ?>
    <?php if ($stockOn): ?><a class="btn ghost" href="<?= h(url('sale.php')) ?>"><?= icon('cart', 16) ?>Sale</a><?php endif; ?>
    <details class="cdash-more">
      <summary class="btn ghost"><?= icon('more', 16) ?>More</summary>
      <div class="cdash-more-panel">
        <a href="<?= h(url('activities.php')) ?>"><?= icon('clock', 16) ?>Activities</a>
        <a href="<?= h(url('clients.php')) ?>"><?= icon('clients', 16) ?>Clients</a>
        <?php if ($stockOn): ?><a href="<?= h(url('stock.php')) ?>"><?= icon('package', 16) ?>Stock</a><?php endif; ?>
        <?php if (!empty($opts['reports'])): ?><a href="<?= h(url('reports.php')) ?>"><?= icon('reports', 16) ?>Reports</a><?php endif; ?>
        <a href="<?= h(url('documents.php')) ?>"><?= icon('file', 16) ?>Documents</a>
      </div>
    </details>
  </div>
</div>
    <?php
}

function render_desk_metric(array $m): void
{
    $href = (string) ($m['href'] ?? '');
    $tag = $href !== '' ? 'a' : 'article';
    $tone = (string) ($m['tone'] ?? '');
    $trend = $m['trend'] ?? null;
    $chip = (string) ($m['chip'] ?? '');
    $chipTone = (string) ($m['chip_tone'] ?? 'flat');
    $sub = (string) ($m['sub'] ?? '');
    $visual = (string) ($m['visual'] ?? '');
    $size = (string) ($m['size'] ?? '');
    $classes = 'cdash-metric card' . ($size === 'lg' ? ' is-lg' : '') . ($tone !== '' ? ' is-' . $tone : '');
    ?>
<<?= $tag ?> class="<?= h($classes) ?>"<?= $href !== '' ? ' href="' . h($href) . '"' : '' ?>>
  <div class="cdash-metric-top">
    <span class="cdash-metric-icon<?= $tone !== '' ? ' is-' . h($tone) : '' ?>"><?= icon((string) ($m['icon'] ?? 'reports'), 18) ?></span>
    <?php if ($trend): ?>
      <em class="cdash-trend is-<?= h((string) $trend['tone']) ?>"><?= h((string) $trend['text']) ?></em>
    <?php elseif ($chip !== ''): ?>
      <em class="cdash-trend is-<?= h($chipTone) ?>"><?= h($chip) ?></em>
    <?php endif; ?>
  </div>
  <span class="cdash-metric-label"><?= h((string) ($m['label'] ?? '')) ?></span>
  <strong class="cdash-metric-value"><?= h((string) ($m['value'] ?? '')) ?></strong>
  <?php if ($sub !== ''): ?><span class="cdash-metric-sub"><?= h($sub) ?></span><?php endif; ?>
  <?php if ($visual !== ''): ?><div class="cdash-metric-visual"><?= $visual ?></div><?php endif; ?>
</<?= $tag ?>>
    <?php
}

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

    [$prevFrom, $prevTo] = desk_period_shift($from, $to);
    $prevTotals = stock_range_totals($prevFrom, $prevTo);
    $incomeTrend = desk_pct_trend((float) $rangeLive['income'], (float) $prevTotals['income']);
    $expenseTrend = desk_pct_trend((float) $rangeLive['expense'], (float) $prevTotals['expense']);
    $netTrend = desk_pct_trend((float) $rangeLive['net'], (float) $prevTotals['net']);
    $salesTrend = desk_pct_trend((float) $rangeLive['income'], (float) $prevTotals['income']);
    $incomeSeries = array_values($chartDays['income'] ?: [(float) $rangeLive['income']]);
    $expenseSeries = array_values($chartDays['expense'] ?: [(float) $rangeLive['expense']]);
    $netSeries = array_values($chartDays['net'] ?: [(float) $rangeLive['net']]);
    $salesCount = (int) ($daySales['total'] ?? count($daySales['rows'] ?? []));
    $dayStatus = $dayOpen ? 'Open' : ($todayDay ? 'Closed' : 'Closed');
    $dayTone = $dayOpen ? 'ok' : 'flat';
    ?>
<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>
<?php render_filters('dashboard.php', [], ['no_all' => true, 'live' => true]); ?>

<div class="cdash-metrics cdash-metrics-primary" id="day-stats" data-day-stats>
  <?php
    render_desk_metric([
        'size' => 'lg',
        'tone' => 'income',
        'icon' => 'invoice',
        'label' => 'Income',
        'value' => money($rangeLive['income']),
        'trend' => $incomeTrend,
        'visual' => desk_minibars($incomeSeries, 'income'),
        'href' => '#day-income',
    ]);
    render_desk_metric([
        'size' => 'lg',
        'tone' => 'spend',
        'icon' => 'wallet',
        'label' => 'Expenditure',
        'value' => money($rangeLive['expense']),
        'trend' => $expenseTrend,
        'visual' => desk_minibars($expenseSeries, 'spend'),
        'href' => '#day-spend',
    ]);
    if ($showProfit) {
        render_desk_metric([
            'size' => 'lg',
            'tone' => ((float) $rangeLive['net'] < 0 ? 'loss' : 'profit'),
            'icon' => 'package',
            'label' => 'Net profit',
            'value' => money($rangeLive['net']),
            'trend' => $netTrend,
            'visual' => desk_minibars($netSeries, ((float) $rangeLive['net'] < 0 ? 'loss' : 'profit')),
        ]);
    } else {
        render_desk_metric([
            'size' => 'lg',
            'tone' => 'day',
            'icon' => 'clock',
            'label' => 'Today',
            'value' => $dayStatus,
            'chip' => $dayOpen ? 'Till open' : 'Till closed',
            'chip_tone' => $dayTone,
        ]);
    }
  ?>
</div>

<div class="cdash-metrics cdash-metrics-secondary">
  <?php
    render_desk_metric([
        'tone' => 'income',
        'icon' => 'cart',
        'label' => 'Sales',
        'value' => money($rangeLive['income']),
        'sub' => $salesCount . ($salesCount === 1 ? ' document' : ' documents'),
        'trend' => $salesTrend,
        'visual' => desk_sparkline($incomeSeries, 'var(--brand)'),
        'href' => '#day-income',
    ]);
    render_desk_metric([
        'tone' => 'info',
        'icon' => 'package',
        'label' => 'Products',
        'value' => (string) (int) $stats['items'],
        'chip' => 'Total',
        'chip_tone' => 'info',
        'href' => url('stock.php?tab=items'),
    ]);
    render_desk_metric([
        'tone' => 'info',
        'icon' => 'bank',
        'label' => 'Stock value',
        'value' => money($stats['cost']),
        'href' => url('stock.php'),
    ]);
    render_desk_metric([
        'tone' => ((int) $stats['low'] > 0 ? 'warn' : 'info'),
        'icon' => 'alert',
        'label' => 'Low stock',
        'value' => (string) (int) $stats['low'],
        'chip' => (int) $stats['low'] > 0 ? 'Items' : 'OK',
        'chip_tone' => (int) $stats['low'] > 0 ? 'warn' : 'ok',
        'href' => url('stock.php?tab=items'),
    ]);
  ?>
</div>

<div class="desk-grid stock-split cdash-day-panel">
  <div class="card">
    <div class="card-head"><h2><?= icon('clock', 16) ?><?= $dayOpen ? 'Close day' : 'Open day' ?></h2></div>
    <div class="pad-form">
      <?php if (!$todayDay): ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="open_day">
          <label for="open_cash">Opening cash</label>
          <input id="open_cash" name="open_cash" inputmode="decimal" required>
          <div class="actions" style="margin-top:12px"><button class="btn" type="submit"><?= icon('check') ?>Open day</button></div>
        </form>
      <?php elseif ($dayOpen): ?>
        <p class="cdash-day-note">Opened at <?= h(money((float) $todayDay['open_cash'])) ?></p>
        <?php
        $todayFloat = stock_float_vs_expenses(today(), today(), (float) (stock_day_totals(today())['expense'] ?? 0));
        if (($todayFloat['applied'] ?? 0) > 0.009 || ($todayFloat['open_cash'] ?? 0) > 0.009):
        ?>
          <p class="cdash-day-note">Float <?= h(money((float) $todayFloat['open_cash'])) ?> · left <?= h(money((float) $todayFloat['left'])) ?></p>
        <?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="close_day">
          <label for="close_cash">Closing cash</label>
          <input id="close_cash" name="close_cash" inputmode="decimal" required>
          <label for="notes">Note</label>
          <input id="notes" name="notes">
          <div class="actions" style="margin-top:12px"><button class="btn" type="submit"><?= icon('check') ?>Close day</button></div>
        </form>
      <?php else: ?>
        <p class="cdash-day-note">Closed at <?= h(money((float) ($todayDay['close_cash'] ?? 0))) ?></p>
      <?php endif; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h2><?= icon('reports', 16) ?>Income vs expenditure</h2></div>
    <div class="pad-form"><canvas id="chart-stock-days" height="180"></canvas></div>
  </div>
</div>
<div class="card" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('reports', 16) ?>Months</h2></div>
  <div class="pad-form"><canvas id="chart-stock-months" height="180"></canvas></div>
</div>
<div class="card" id="day-income" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('invoice', 16) ?>Sales</h2></div>
  <div class="pad-form"><?php stock_search_bar('dashboard.php', ['range' => $period['preset'], 'from' => $from, 'to' => $to], 'Search sales'); ?></div>
  <?php render_stock_docs_table($daySales, $base, 'sp', 'No sales in this period.'); ?>
</div>
<div class="card" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('package', 16) ?>Sold</h2></div>
  <?php render_stock_sold_table($daySold); ?>
</div>
<div class="card" id="day-spend" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('expense', 16) ?>Expenses</h2></div>
  <?php render_stock_docs_table($daySpend, $base, 'ep', 'No expenses in this period.'); ?>
</div>
<div class="card" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('clock', 16) ?>Daily</h2></div>
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
  <div class="card-head"><h2><?= icon('calendar', 16) ?>Monthly</h2></div>
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
  <div class="card-head"><h2><?= icon('package', 16) ?>Stock</h2></div>
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
