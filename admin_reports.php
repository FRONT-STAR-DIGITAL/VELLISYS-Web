<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'notice') {
    csrf_check();
    $id = (int) post('id');
    $company = $id ? db_one('SELECT * FROM companies WHERE id = ?', 'i', [$id]) : null;
    if (!$company) {
        flash('Company not found.', 'err');
        redirect('admin_reports.php');
    }
    $state = company_expiry_state($company);
    if (!in_array($state, ['soon', 'expired'], true)) {
        flash('Notices are for desks one month from expiry, or already lapsed.', 'err');
        redirect('admin_reports.php?preview=' . $id);
    }
    $result = send_renewal_notice($company, $user);
    if (!empty($result['error'])) {
        flash($result['error'], 'err');
        redirect('admin_reports.php?preview=' . $id);
    }
    $to = $result['contact']['email'] ?? '';
    if ($result['ok']) {
        flash('Renewal notice sent to ' . $to . '.');
    } else {
        flash('The notice was prepared and queued for ' . $to . '. Mail was not accepted on this host - send it from a live server, or copy the text below.');
    }
    redirect('admin_reports.php?preview=' . $id);
}

$companies = db_all('SELECT * FROM companies ORDER BY (expires_at IS NULL), expires_at, name');
$soon = [];
$expired = [];
$later = [];
$unset = [];
foreach ($companies as $c) {
    $state = company_expiry_state($c);
    if ($state === 'soon') {
        $soon[] = $c;
    } elseif ($state === 'expired') {
        $expired[] = $c;
    } elseif ($state === 'ok') {
        $later[] = $c;
    } else {
        $unset[] = $c;
    }
}

$previewId = (int) ($_GET['preview'] ?? 0);
$preview = null;
$previewCopy = null;
$previewContact = null;
if ($previewId) {
    $preview = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$previewId]);
    if ($preview) {
        $brand = branding_for($previewId) ?: [];
        $members = db_all('SELECT id, name, email FROM users WHERE company_id = ? ORDER BY id', 'i', [$previewId]);
        $previewContact = company_notice_email($previewId, $brand, $members);
        $previewCopy = renewal_notice_copy($preview, $previewContact);
    }
}

$notices = [];
try {
    $notices = db_all(
        'SELECT n.*, c.name AS company_name
         FROM renewal_notices n
         JOIN companies c ON c.id = n.company_id
         ORDER BY n.id DESC
         LIMIT 25'
    );
} catch (Throwable $e) {
    $notices = [];
}

$live = count(array_filter($companies, static fn ($c) => ($c['status'] ?? '') === 'live'));
$withTerm = count($companies) - count($unset);
$months = month_axis(12);
$statusCounts = ['onboarding' => 0, 'live' => 0, 'suspended' => 0];
$expiryCounts = ['ok' => 0, 'soon' => 0, 'expired' => 0, 'none' => 0];
$companyMonths = array_fill_keys($months, 0);
$totalFee = 0.0;
$totalPaid = 0.0;
$totalBalance = 0.0;
$totalRemaining = 0.0;
$feeNames = [];
$feePaidSeries = [];
$feeBalSeries = [];
foreach ($companies as $c) {
    $st = (string) ($c['status'] ?? 'onboarding');
    $statusCounts[$st] = ($statusCounts[$st] ?? 0) + 1;
    $ex = company_expiry_state($c);
    $expiryCounts[$ex] = ($expiryCounts[$ex] ?? 0) + 1;
    $createdYm = substr((string) ($c['created_at'] ?? ''), 0, 7);
    if (isset($companyMonths[$createdYm])) {
        $companyMonths[$createdYm]++;
    }
    $totalFee += company_fee_ugx($c, 'amount');
    $totalPaid += company_fee_ugx($c, 'paid');
    $totalBalance += company_fee_ugx($c, 'balance');
    $totalRemaining += company_fee_ugx($c, 'remaining');
    $feeNames[] = (string) $c['name'];
    $feePaidSeries[] = company_fee_ugx($c, 'paid');
    $feeBalSeries[] = company_fee_ugx($c, 'balance');
}

$signupMonths = array_fill_keys($months, 0);
$signupStatus = ['new' => 0, 'contacted' => 0, 'onboarded' => 0, 'declined' => 0];
$signups = [];
try {
    $signups = db_all('SELECT status, created_at FROM signups');
} catch (Throwable $e) {
    $signups = [];
}
foreach ($signups as $s) {
    $st = (string) ($s['status'] ?? 'new');
    $signupStatus[$st] = ($signupStatus[$st] ?? 0) + 1;
    $ym = substr((string) ($s['created_at'] ?? ''), 0, 7);
    if (isset($signupMonths[$ym])) {
        $signupMonths[$ym]++;
    }
}

$booksBy = [];
foreach ($companies as $c) {
    $booksBy[(int) $c['id']] = [
        'name' => (string) $c['name'],
        'invoiced' => 0.0,
        'collected' => 0.0,
        'outstanding' => 0.0,
        'expenses' => 0.0,
        'quotes' => 0,
        'invoices' => 0,
        'receipts' => 0,
        'expenses_n' => 0,
        'letters' => 0,
    ];
}
$bookMonths = [];
foreach ($months as $m) {
    $bookMonths[$m] = ['invoiced' => 0.0, 'collected' => 0.0, 'expenses' => 0.0];
}
foreach (platform_issued_documents() as $d) {
    $cid = (int) $d['company_id'];
    if (!isset($booksBy[$cid])) {
        continue;
    }
    $kind = (string) ($d['kind'] ?? '');
    $ugxAmt = convert_money((float) $d['totals']['total'], doc_currency($d), 'UGX');
    $ym = substr((string) $d['date'], 0, 7);
    if ($kind === 'invoice') {
        $booksBy[$cid]['invoiced'] += $ugxAmt;
        $booksBy[$cid]['outstanding'] += convert_money((float) $d['balance'], doc_currency($d), 'UGX');
        $booksBy[$cid]['invoices']++;
        if (isset($bookMonths[$ym])) {
            $bookMonths[$ym]['invoiced'] += $ugxAmt;
        }
    } elseif ($kind === 'expense') {
        $booksBy[$cid]['expenses'] += $ugxAmt;
        $booksBy[$cid]['expenses_n']++;
        if (isset($bookMonths[$ym])) {
            $bookMonths[$ym]['expenses'] += $ugxAmt;
        }
    } elseif ($kind === 'receipt') {
        $booksBy[$cid]['receipts']++;
        $got = convert_money((float) ($d['paid'] ?: $d['totals']['total']), doc_currency($d), 'UGX');
        if (($d['related_kind'] ?? '') !== 'expense') {
            $booksBy[$cid]['collected'] += $got;
            if (isset($bookMonths[$ym])) {
                $bookMonths[$ym]['collected'] += $got;
            }
        }
    } elseif ($kind === 'quotation') {
        $booksBy[$cid]['quotes']++;
    } elseif ($kind === 'letter') {
        $booksBy[$cid]['letters']++;
    }
}
$deskInvoiced = array_sum(array_column($booksBy, 'invoiced'));
$deskCollected = array_sum(array_column($booksBy, 'collected'));
$deskOutstanding = array_sum(array_column($booksBy, 'outstanding'));
$deskExpenses = array_sum(array_column($booksBy, 'expenses'));

$export = (string) ($_GET['export'] ?? '');
if ($export === 'fees') {
    $rows = [];
    foreach ($companies as $c) {
        $rows[] = [
            $c['name'],
            $c['status'],
            company_term_label($c),
            company_remaining_phrase($c),
            $c['expires_at'] ? format_date((string) $c['expires_at']) : '',
            company_fee_amount($c),
            company_fee_paid($c),
            company_fee_balance($c),
            company_remaining_value($c),
            company_fee_currency($c),
        ];
    }
    csv_download('vellisys-fees.csv', ['Company', 'Status', 'Term', 'Remaining', 'Expires', 'Fee', 'Paid', 'Balance', 'Unused value', 'Currency'], $rows);
}
if ($export === 'books') {
    $rows = [];
    foreach ($companies as $c) {
        $b = $booksBy[(int) $c['id']];
        $rows[] = [
            $c['name'],
            $c['status'],
            round($b['invoiced'], 2),
            round($b['collected'], 2),
            round($b['outstanding'], 2),
            round($b['expenses'], 2),
            $b['quotes'],
            $b['invoices'],
            $b['receipts'],
            $b['expenses_n'],
            $b['letters'],
        ];
    }
    csv_download('vellisys-desk-books.csv', ['Company', 'Status', 'Invoiced', 'Collected', 'Outstanding', 'Expenses', 'Quotations', 'Invoices', 'Receipts', 'Expenses issued', 'Letters'], $rows);
}

$adminChart = [
    'months' => $months,
    'signups' => array_values($signupMonths),
    'companies' => array_values($companyMonths),
    'statusLabels' => array_keys($statusCounts),
    'statusValues' => array_values($statusCounts),
    'expiryLabels' => ['In term', 'Due in a month', 'Expired', 'No term'],
    'expiryValues' => [$expiryCounts['ok'], $expiryCounts['soon'], $expiryCounts['expired'], $expiryCounts['none']],
    'feeNames' => $feeNames,
    'feePaid' => $feePaidSeries,
    'feeBalance' => $feeBalSeries,
    'bookInvoiced' => array_column($bookMonths, 'invoiced'),
    'bookCollected' => array_column($bookMonths, 'collected'),
    'bookExpenses' => array_column($bookMonths, 'expenses'),
    'funnelLabels' => array_keys($signupStatus),
    'funnelValues' => array_values($signupStatus),
    'currency' => 'UGX',
];

layout_admin_start('Reports', $user);

$expiryCell = static function (array $c): string {
    $state = company_expiry_state($c);
    $class = $state === 'expired' ? ' pill bad' : ($state === 'soon' ? ' pill warn' : ' pill');
    return '<span class="' . trim($class) . '">' . h(company_expiry_label($c)) . '</span>';
};

$row = static function (array $c) use ($expiryCell, $previewId): void {
    $state = company_expiry_state($c);
    $canNotice = in_array($state, ['soon', 'expired'], true);
    $sent = trim((string) ($c['renewal_notice_sent_at'] ?? ''));
    ?>
    <tr>
      <td><a href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><strong><?= h($c['name']) ?></strong></a></td>
      <td><span class="pill<?= $c['status'] === 'live' ? '' : ($c['status'] === 'suspended' ? ' bad' : ' warn') ?>"><?= h($c['status']) ?></span></td>
      <td><?= h(company_term_label($c)) ?></td>
      <td><?= $expiryCell($c) ?></td>
      <td><?= $c['expires_at'] ? h(format_date($c['expires_at'])) : '—' ?></td>
      <td><?= $sent !== '' ? h(substr($sent, 0, 16)) : '—' ?></td>
      <td class="row-actions">
        <div class="actions">
          <?php if ($canNotice): ?>
            <a class="btn sm<?= $previewId === (int) $c['id'] ? '' : ' ghost' ?>" href="<?= h(url('admin_reports.php?preview=' . $c['id'])) ?>"><?= icon('eye', 14) ?>Preview</a>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button class="btn sm" name="action" value="notice"><?= icon('send', 14) ?>Send notice</button>
            </form>
          <?php else: ?>
            <a class="btn ghost sm" href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><?= icon('calendar', 14) ?>Set term</a>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php
};
?>
<div class="page-head">
  <div>
    <h1><?= icon('reports') ?>Reports</h1>
    <p class="lede">Collections, balances, onboarding, and how every desk is performing. When a company is one month from expiry, preview the letter and send it in one click.</p>
  </div>
  <div class="actions">
    <a class="btn ghost" href="<?= h(url('admin_reports.php?export=fees')) ?>"><?= icon('download', 16) ?>Fees CSV</a>
    <a class="btn ghost" href="<?= h(url('admin_reports.php?export=books')) ?>"><?= icon('download', 16) ?>Desk books CSV</a>
  </div>
</div>

<div class="stats">
  <div class="card stat"><?= icon('building', 20) ?><span>Companies</span><strong><?= count($companies) ?></strong></div>
  <div class="card stat"><?= icon('check', 20) ?><span>Live</span><strong><?= $live ?></strong></div>
  <div class="card stat"><?= icon('alert', 20) ?><span>Due within a month</span><strong><?= count($soon) ?></strong></div>
  <div class="card stat"><?= icon('ban', 20) ?><span>Expired</span><strong><?= count($expired) ?></strong></div>
</div>
<div class="stats">
  <div class="card stat"><?= icon('bank', 20) ?><span>Fees collected</span><strong><?= h(ugx($totalPaid)) ?></strong></div>
  <div class="card stat"><?= icon('invoice', 20) ?><span>Fee balances</span><strong><?= h(ugx($totalBalance)) ?></strong></div>
  <div class="card stat"><?= icon('receipt', 20) ?><span>Desk collections</span><strong><?= h(ugx($deskCollected)) ?></strong></div>
  <div class="card stat"><?= icon('clients', 20) ?><span>Desk outstanding</span><strong><?= h(ugx($deskOutstanding)) ?></strong></div>
</div>
<p class="hint" style="margin:-12px 0 20px"><?= $withTerm ?> of <?= count($companies) ?> <?= count($companies) === 1 ? 'company has' : 'companies have' ?> a paid term on file. Remaining unused term value <?= h(ugx($totalRemaining)) ?>. Desk invoiced <?= h(ugx($deskInvoiced)) ?> · expenses <?= h(ugx($deskExpenses)) ?>.</p>

<div class="chart-grid equal">
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('building', 16) ?>Onboarding over time</h2></div>
    <canvas id="chart-onboard"></canvas>
  </div>
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('check', 16) ?>Company status</h2></div>
    <canvas id="chart-status"></canvas>
  </div>
</div>
<div class="chart-grid equal">
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('calendar', 16) ?>Expiry mix</h2></div>
    <canvas id="chart-expiry"></canvas>
  </div>
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('letter', 16) ?>Sign-up funnel</h2></div>
    <?php if (array_sum($signupStatus) === 0): ?>
      <p class="empty">No website sign-ups yet.</p>
    <?php else: ?>
      <canvas id="chart-funnel"></canvas>
    <?php endif; ?>
  </div>
</div>
<div class="chart-grid equal">
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('bank', 16) ?>Fees paid vs balance</h2></div>
    <?php if ($totalPaid <= 0 && $totalBalance <= 0): ?>
      <p class="empty">Set a fee on each company under Paid term to plot collections here.</p>
    <?php else: ?>
      <canvas id="chart-fees"></canvas>
    <?php endif; ?>
  </div>
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('reports', 16) ?>Desk books over time</h2></div>
    <canvas id="chart-books"></canvas>
  </div>
</div>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('bank', 16) ?>What each client paid</h2></div>
  <?php if (!$companies): ?>
    <p class="empty">No companies yet.</p>
  <?php else: ?>
    <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>Company</th>
          <th>Status</th>
          <th>Term</th>
          <th>Remaining</th>
          <th>Expires</th>
          <th class="right">Fee</th>
          <th class="right">Paid</th>
          <th class="right">Balance</th>
          <th class="right">Unused value</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($companies as $c): ?>
          <tr>
            <td><a href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><strong><?= h($c['name']) ?></strong></a></td>
            <td><span class="pill<?= $c['status'] === 'live' ? '' : ($c['status'] === 'suspended' ? ' bad' : ' warn') ?>"><?= h($c['status']) ?></span></td>
            <td><?= h(company_term_label($c)) ?></td>
            <td><?= h(company_remaining_phrase($c)) ?></td>
            <td><?= $c['expires_at'] ? h(format_date((string) $c['expires_at'])) : '—' ?></td>
            <td class="right mono"><?= h(money(company_fee_amount($c), company_fee_currency($c))) ?></td>
            <td class="right mono"><?= h(money(company_fee_paid($c), company_fee_currency($c))) ?></td>
            <td class="right mono"><?= h(money(company_fee_balance($c), company_fee_currency($c))) ?></td>
            <td class="right mono"><?= h(money(company_remaining_value($c), company_fee_currency($c))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="5">Totals (UGX)</td>
          <td class="right mono"><?= h(ugx($totalFee)) ?></td>
          <td class="right mono"><?= h(ugx($totalPaid)) ?></td>
          <td class="right mono"><?= h(ugx($totalBalance)) ?></td>
          <td class="right mono"><?= h(ugx($totalRemaining)) ?></td>
        </tr>
      </tfoot>
    </table>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('desk', 16) ?>System performance</h2></div>
  <?php if (!$companies): ?>
    <p class="empty">No desks to measure.</p>
  <?php else: ?>
    <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>Company</th>
          <th class="right">Invoiced</th>
          <th class="right">Collected</th>
          <th class="right">Outstanding</th>
          <th class="right">Expenses</th>
          <th class="right">Quotes</th>
          <th class="right">Invoices</th>
          <th class="right">Receipts</th>
          <th class="right">Letters</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($companies as $c): $b = $booksBy[(int) $c['id']]; ?>
          <tr>
            <td><a href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><strong><?= h($c['name']) ?></strong></a></td>
            <td class="right mono"><?= h(ugx($b['invoiced'])) ?></td>
            <td class="right mono"><?= h(ugx($b['collected'])) ?></td>
            <td class="right mono"><?= h(ugx($b['outstanding'])) ?></td>
            <td class="right mono"><?= h(ugx($b['expenses'])) ?></td>
            <td class="right mono"><?= (int) $b['quotes'] ?></td>
            <td class="right mono"><?= (int) $b['invoices'] ?></td>
            <td class="right mono"><?= (int) $b['receipts'] ?></td>
            <td class="right mono"><?= (int) $b['letters'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td>Totals</td>
          <td class="right mono"><?= h(ugx($deskInvoiced)) ?></td>
          <td class="right mono"><?= h(ugx($deskCollected)) ?></td>
          <td class="right mono"><?= h(ugx($deskOutstanding)) ?></td>
          <td class="right mono"><?= h(ugx($deskExpenses)) ?></td>
          <td colspan="4"></td>
        </tr>
      </tfoot>
    </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($preview && $previewCopy): ?>
<div class="card" style="margin-bottom:24px">
  <div class="card-head">
    <h2><?= icon('letter', 16) ?>Prepared notice for <?= h($preview['name']) ?></h2>
  </div>
  <div style="padding:0 22px 18px">
    <p class="lede" style="margin-bottom:12px">
      To: <?= $previewContact['email'] !== '' ? h($previewContact['email']) : 'No email on file' ?>
      · <?= h(company_expiry_label($preview)) ?>
      · Term <?= h(company_term_label($preview)) ?>
    </p>
    <div class="mail-preview">
      <div class="mail-preview-head"><?= h($previewCopy['subject']) ?></div>
      <div class="mail-preview-body"><?= email_html_preview($previewCopy['html']) ?></div>
    </div>
    <div class="actions" style="margin-top:14px">
      <?php if (in_array(company_expiry_state($preview), ['soon', 'expired'], true) && $previewContact['email'] !== ''): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $preview['id'] ?>">
        <button class="btn" name="action" value="notice"><?= icon('send', 16) ?>Send this notice</button>
      </form>
      <?php elseif ($previewContact['email'] === ''): ?>
        <p class="hint" style="margin:0">Add a public email on the company stationery, or a desk login, before sending.</p>
      <?php endif; ?>
      <a class="btn ghost" href="<?= h(url('admin_company.php?id=' . $preview['id'])) ?>">Open company</a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('alert', 16) ?>Due within a month</h2></div>
  <?php if (!$soon): ?>
    <p class="empty">No desks expire in the next 31 days. Set a paid term on a company to track it here.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>Company</th>
          <th>Status</th>
          <th>Term</th>
          <th>Window</th>
          <th>Expires</th>
          <th>Last notice</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($soon as $c) { $row($c); } ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('ban', 16) ?>Expired</h2></div>
  <?php if (!$expired): ?>
    <p class="empty">No lapsed desks.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>Company</th>
          <th>Status</th>
          <th>Term</th>
          <th>Window</th>
          <th>Expires</th>
          <th>Last notice</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($expired as $c) { $row($c); } ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('calendar', 16) ?>Later expiries</h2></div>
  <?php if (!$later): ?>
    <p class="empty">No desks with a term more than a month out.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>Company</th>
          <th>Status</th>
          <th>Term</th>
          <th>Window</th>
          <th>Expires</th>
          <th>Last notice</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($later as $c) { $row($c); } ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if ($unset): ?>
<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('building', 16) ?>No paid term yet</h2></div>
  <table class="grid">
    <thead>
      <tr>
        <th>Company</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($unset as $c): ?>
        <tr>
          <td><a href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><strong><?= h($c['name']) ?></strong></a></td>
          <td><span class="pill<?= $c['status'] === 'live' ? '' : ' warn' ?>"><?= h($c['status']) ?></span></td>
          <td><a class="btn sm" href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><?= icon('calendar', 14) ?>Set term</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><h2><?= icon('letter', 16) ?>Notices sent</h2></div>
  <?php if (!$notices): ?>
    <p class="empty">No renewal notices yet. When a desk is within a month of expiry, send the prepared letter from this page.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>When</th>
          <th>Company</th>
          <th>To</th>
          <th>Subject</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($notices as $n): ?>
          <tr>
            <td class="mono"><?= h(substr((string) $n['created_at'], 0, 16)) ?></td>
            <td><?= h($n['company_name']) ?></td>
            <td class="mono"><?= h($n['to_email']) ?></td>
            <td><?= h($n['subject']) ?></td>
            <td><span class="pill<?= $n['status'] === 'queued' ? ' warn' : '' ?>"><?= h($n['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php
$payload = json_encode($adminChart, JSON_UNESCAPED_UNICODE);
$script = '<script src="' . h(asset('js/chart.umd.min.js')) . '"></script><script>
(function(){
  var d = ' . $payload . ';
  Chart.defaults.font.family = "Montserrat, sans-serif";
  Chart.defaults.color = "#66705f";
  var palette = ["#82B440","#1f3a12","#c4a35a","#4a6fa5","#b42318","#6b7c5e"];
  function money(v){ return (d.currency || "UGX") + " " + Number(v).toLocaleString("en-UG"); }
  var onboard = document.getElementById("chart-onboard");
  if (onboard) {
    new Chart(onboard, {
      type: "line",
      data: {
        labels: d.months,
        datasets: [
          { label: "Sign-ups", data: d.signups, borderColor: "#4a6fa5", backgroundColor: "rgba(74,111,165,.12)", tension: .25, fill: true },
          { label: "Companies created", data: d.companies, borderColor: "#82B440", backgroundColor: "rgba(130,180,64,.18)", tension: .25, fill: true }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, scales: { y: { ticks: { precision: 0 } } } }
    });
  }
  var status = document.getElementById("chart-status");
  if (status) {
    new Chart(status, {
      type: "pie",
      data: { labels: d.statusLabels, datasets: [{ data: d.statusValues, backgroundColor: ["#c4a35a","#82B440","#b42318"] }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
    });
  }
  var expiry = document.getElementById("chart-expiry");
  if (expiry) {
    new Chart(expiry, {
      type: "doughnut",
      data: { labels: d.expiryLabels, datasets: [{ data: d.expiryValues, backgroundColor: ["#82B440","#c4a35a","#b42318","#6b7c5e"] }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
    });
  }
  var funnel = document.getElementById("chart-funnel");
  if (funnel) {
    new Chart(funnel, {
      type: "pie",
      data: { labels: d.funnelLabels, datasets: [{ data: d.funnelValues, backgroundColor: palette }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
    });
  }
  var fees = document.getElementById("chart-fees");
  if (fees) {
    new Chart(fees, {
      type: "bar",
      data: {
        labels: d.feeNames,
        datasets: [
          { label: "Paid", data: d.feePaid, backgroundColor: "#82B440" },
          { label: "Balance", data: d.feeBalance, backgroundColor: "#b42318" }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, scales: { x: { stacked: true }, y: { stacked: true, ticks: { callback: money } } } }
    });
  }
  var books = document.getElementById("chart-books");
  if (books) {
    new Chart(books, {
      type: "line",
      data: {
        labels: d.months,
        datasets: [
          { label: "Invoiced", data: d.bookInvoiced, borderColor: "#82B440", backgroundColor: "rgba(130,180,64,.16)", tension: .25, fill: true },
          { label: "Collected", data: d.bookCollected, borderColor: "#1f3a12", tension: .25, fill: false },
          { label: "Expenses", data: d.bookExpenses, borderColor: "#b42318", backgroundColor: "rgba(180,35,24,.1)", tension: .25, fill: true }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, scales: { y: { ticks: { callback: money } } } }
    });
  }
})();
</script>';
layout_end($script);
?>
