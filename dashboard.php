<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$brand = branding();

$invoices = attach_document_totals(db_all("SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.kind = 'invoice' ORDER BY d.date DESC, d.id DESC"));
$open = array_values(array_filter($invoices, static fn ($d) => $d['status'] !== 'void' && $d['balance'] > 0));
$overdue = array_values(array_filter($open, static fn ($d) => !empty($d['due_date']) && $d['due_date'] < today()));
$monthStart = date('Y-m-01');
$incomeMonth = 0;
foreach ($invoices as $d) {
    if ($d['status'] !== 'void' && $d['date'] >= $monthStart) {
        $incomeMonth += $d['totals']['total'];
    }
}
$expMonth = attach_document_totals(db_all("SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.kind = 'expense' AND d.status = 'issued' AND d.date >= ?", 's', [$monthStart]));
$expenseMonth = 0;
foreach ($expMonth as $d) {
    $expenseMonth += $d['totals']['total'];
}
$quotes = (int) (db_one("SELECT COUNT(*) c FROM documents WHERE kind='quotation' AND status='issued'")['c'] ?? 0);
$recent = attach_document_totals(db_all("SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id ORDER BY d.id DESC LIMIT 7"));
$queue = $overdue ?: $open;
$hour = (int) date('G');
$hello = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

layout_start('Desk', $user);
?>
<div class="desk-hero">
  <div>
    <p class="desk-kicker"><?= h($brand['name']) ?> · <?= h(ucfirst((string) $brand['plan'])) ?></p>
    <h1><?= h($hello) ?>, <?= h(explode(' ', $user['name'])[0]) ?>.</h1>
    <p class="lede">What needs sending or collecting today. Colour and letterhead live in Settings.</p>
  </div>
  <div class="actions">
    <a class="btn ghost" href="<?= h(url('document_new.php?kind=quotation')) ?>"><?= icon('quotation', 16) ?>Quotation</a>
    <a class="btn" href="<?= h(url('document_new.php?kind=invoice')) ?>"><?= icon('invoice', 16) ?>Invoice</a>
  </div>
</div>

<div class="desk-grid">
  <div class="card">
    <div class="card-head">
      <h2><?= $overdue ? 'Overdue' : 'Open invoices' ?></h2>
      <a class="btn ghost sm" href="<?= h(url('documents.php?kind=invoice')) ?>">All invoices</a>
    </div>
    <?php if (!$queue): ?>
      <p class="empty">Nothing outstanding. Issue an invoice when you are ready.</p>
    <?php else: ?>
      <div class="work-list">
        <?php foreach (array_slice($queue, 0, 6) as $doc): ?>
          <a class="work-row" href="<?= h(url('document_view.php?id=' . $doc['id'])) ?>">
            <div>
              <strong><?= h($doc['party_name']) ?></strong>
              <span><?= h($doc['number']) ?> · due <?= h(format_date($doc['due_date'])) ?></span>
            </div>
            <b><?= h(ugx($doc['balance'])) ?></b>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="meter">
    <a href="<?= h(url('documents.php?kind=invoice')) ?>">
      <span>Open</span>
      <strong><?= h(ugx(array_sum(array_column($open, 'balance')))) ?></strong>
    </a>
    <a href="<?= h(url('documents.php?kind=invoice')) ?>">
      <span>Overdue</span>
      <strong><?= h(ugx(array_sum(array_map(static fn ($d) => $d['balance'], $overdue)))) ?></strong>
    </a>
    <a href="<?= h(url('reports.php')) ?>">
      <span>Invoiced this month</span>
      <strong><?= h(ugx($incomeMonth)) ?></strong>
    </a>
    <div class="meter-row">
      <span>Spent this month</span>
      <strong><?= h(ugx($expenseMonth)) ?></strong>
    </div>
    <a href="<?= h(url('documents.php?kind=quotation')) ?>">
      <span>Open quotations</span>
      <strong><?= $quotes ?></strong>
    </a>
  </div>
</div>

<div class="card desk-recent">
  <div class="card-head">
    <h2>Recent</h2>
    <a class="btn ghost sm" href="<?= h(url('clients.php')) ?>">Clients</a>
  </div>
  <?php if (!$recent): ?>
    <p class="empty">Nothing issued yet.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>Number</th>
          <th>Client</th>
          <th>Date</th>
          <th class="right">Amount</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $doc): ?>
          <tr>
            <td class="mono"><a href="<?= h(url('document_view.php?id=' . $doc['id'])) ?>"><?= h($doc['number']) ?></a></td>
            <td><a href="<?= h(url('client_view.php?id=' . $doc['party_id'])) ?>"><?= h($doc['party_name']) ?></a></td>
            <td><?= h(format_date($doc['date'])) ?></td>
            <td class="right mono"><?= h(ugx($doc['totals']['total'])) ?></td>
            <td><span class="pill<?= invoice_status_label($doc) === 'Overdue' ? ' warn' : '' ?>"><?= h(invoice_status_label($doc)) ?></span></td>
            <td class="row-actions"><?php render_doc_actions($doc); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
