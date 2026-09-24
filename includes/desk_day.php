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
        return ['tone' => 'flat', 'text' => '- 0%'];
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
    return ['tone' => 'flat', 'text' => '- 0%'];
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
    $hour = (int) date('G');
    $hello = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $who = trim((string) ($opts['user_name'] ?? ''));
    if ($who === '') {
        $who = 'there';
    } else {
        $who = explode(' ', $who)[0];
    }
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
      <p class="cdash-hello"><?= h($hello) ?> <?= h($who) ?></p>
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

/** Amount still owed by open debtors (invoices + open sales receipts). */
function desk_debtors_owed(): float
{
    if (!function_exists('list_open_debtors')) {
        return 0.0;
    }
    $base = default_currency();
    $n = 0.0;
    foreach (list_open_debtors() as $d) {
        $n += convert_money(document_due_amount($d), doc_currency($d), $base);
    }
    return round($n, 2);
}

function desk_docs_issued_count(string $from, string $to): int
{
    $cid = current_company_id();
    return (int) (db_one(
        "SELECT COUNT(*) c FROM documents WHERE company_id = ? AND status = 'issued' AND date >= ? AND date <= ?",
        'iss',
        [$cid, $from, $to]
    )['c'] ?? 0);
}

function desk_active_clients_count(): int
{
    $cid = current_company_id();
    return (int) (db_one(
        "SELECT COUNT(*) c FROM parties WHERE company_id = ? AND kind IN ('customer','both') AND (status IS NULL OR status = 'active')",
        'i',
        [$cid]
    )['c'] ?? 0);
}

/**
 * Six clickable desk tabs: Income received, Expenditure, Profit & Net Profit,
 * Amount owed by Debtors, Documents Issued, Active Clients.
 *
 * @param array{
 *   income: float,
 *   expense: float,
 *   profit: float,
 *   net: float,
 *   debtors: float,
 *   docs: int,
 *   clients: int,
 *   show_profit?: bool,
 *   income_href?: string,
 *   expense_href?: string,
 *   profit_href?: string,
 *   debtors_href?: string,
 *   docs_href?: string,
 *   clients_href?: string,
 *   income_trend?: array|null,
 *   expense_trend?: array|null,
 *   profit_trend?: array|null,
 * } $data
 */
function render_desk_metric_tabs(array $data): void
{
    $showProfit = array_key_exists('show_profit', $data)
        ? !empty($data['show_profit'])
        : (!function_exists('user_can_see_profit') || user_can_see_profit());
    $profitHref = (string) ($data['profit_href'] ?? '');
    if ($profitHref === '') {
        $profitHref = (function_exists('user_can_open') && user_can_open('reports.php'))
            ? url('reports.php')
            : '#desk-charts';
    }
    $profit = (float) ($data['profit'] ?? 0);
    $net = (float) ($data['net'] ?? 0);
    $debtors = (float) ($data['debtors'] ?? 0);
    $tabs = [
        [
            'tone' => 'income',
            'icon' => 'receipt',
            'label' => 'Income received',
            'value' => money((float) ($data['income'] ?? 0)),
            'trend' => $data['income_trend'] ?? null,
            'href' => (string) ($data['income_href'] ?? url('documents.php?kind=receipt')),
        ],
        [
            'tone' => 'spend',
            'icon' => 'wallet',
            'label' => 'Expenditure',
            'value' => money((float) ($data['expense'] ?? 0)),
            'trend' => $data['expense_trend'] ?? null,
            'href' => (string) ($data['expense_href'] ?? url('documents.php?kind=expense')),
        ],
        [
            'tone' => ($showProfit && $net < 0 ? 'loss' : 'profit'),
            'icon' => 'package',
            'label' => 'Profit & Net Profit',
            'value' => $showProfit ? money($profit) : '-',
            'sub' => $showProfit ? ('Net ' . money($net)) : 'Ask an admin',
            'trend' => $showProfit ? ($data['profit_trend'] ?? null) : null,
            'href' => $profitHref,
        ],
        [
            'tone' => ($debtors > 0.009 ? 'warn' : 'info'),
            'icon' => 'clients',
            'label' => 'Amount owed by Debtors',
            'value' => money($debtors),
            'href' => (string) ($data['debtors_href'] ?? url('debtors.php')),
        ],
        [
            'tone' => 'info',
            'icon' => 'file',
            'label' => 'Documents Issued',
            'value' => (string) (int) ($data['docs'] ?? 0),
            'href' => (string) ($data['docs_href'] ?? url('documents.php')),
        ],
        [
            'tone' => 'info',
            'icon' => 'building',
            'label' => 'Active Clients',
            'value' => (string) (int) ($data['clients'] ?? 0),
            'href' => (string) ($data['clients_href'] ?? url('clients.php?status=active')),
        ],
    ];
    ?>
<div class="cdash-metrics cdash-metrics-tabs" id="day-stats" data-day-stats>
  <?php foreach ($tabs as $tab) {
      render_desk_metric($tab);
  } ?>
</div>
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
        'labels' => array_map(static function ($m) {
            $t = strtotime((string) $m . '-01');
            return $t ? date('M Y', $t) : (string) $m;
        }, array_keys($dash['months'])),
        'income' => array_column(array_values($dash['months']), 'income'),
        'expense' => array_column(array_values($dash['months']), 'expense'),
        'profit' => array_column(array_values($dash['months']), 'profit'),
        'net' => array_column(array_values($dash['months']), 'net'),
    ];
    if (!$chartMonths['labels']) {
        $chartMonths = [
            'labels' => [date('M Y')],
            'income' => [(float) $rangeLive['income']],
            'expense' => [(float) $rangeLive['expense']],
            'profit' => [(float) $rangeLive['profit']],
            'net' => [(float) $rangeLive['net']],
        ];
    }

    [$prevFrom, $prevTo] = desk_period_shift($from, $to);
    $prevTotals = stock_range_totals($prevFrom, $prevTo);
    $incomeTrend = desk_pct_trend((float) $rangeLive['income'], (float) $prevTotals['income']);
    $expenseTrend = desk_pct_trend((float) $rangeLive['expense'], (float) $prevTotals['expense']);
    $netTrend = desk_pct_trend((float) $rangeLive['net'], (float) $prevTotals['net']);
    $profitHref = (function_exists('user_can_open') && user_can_open('reports.php'))
        ? url('reports.php')
        : '#desk-charts';
    ?>
<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>
<?php render_filters('dashboard.php', [], ['no_all' => true, 'live' => true]); ?>

<?php
render_desk_metric_tabs([
    'income' => (float) $rangeLive['income'],
    'expense' => (float) $rangeLive['expense'],
    'profit' => (float) $rangeLive['profit'],
    'net' => (float) $rangeLive['net'],
    'debtors' => desk_debtors_owed(),
    'docs' => desk_docs_issued_count($from, $to),
    'clients' => desk_active_clients_count(),
    'show_profit' => $showProfit,
    'income_href' => '#day-income',
    'expense_href' => '#day-spend',
    'profit_href' => $profitHref,
    'income_trend' => $incomeTrend,
    'expense_trend' => $expenseTrend,
    'profit_trend' => $netTrend,
]);
?>

<div class="desk-grid stock-split cdash-charts" id="desk-charts">
  <div class="card">
    <div class="card-head"><h2><?= icon('reports', 16) ?>Income vs expenditure</h2></div>
    <div class="chart-frame"><canvas id="chart-stock-days"></canvas></div>
  </div>
  <div class="card">
    <div class="card-head"><h2><?= icon('reports', 16) ?>Months</h2></div>
    <div class="chart-frame"><canvas id="chart-stock-months"></canvas></div>
  </div>
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
