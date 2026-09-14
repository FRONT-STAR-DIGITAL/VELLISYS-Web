<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$id = (int) ($_GET['id'] ?? 0);
$cid = current_company_id();
$party = db_one('SELECT * FROM parties WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
if (!$party) {
    flash('Client not found.', 'err');
    redirect('clients.php');
}

[$extra, $types, $params] = period_sql('d.date');
$docs = attach_document_totals(db_all(
    'SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.company_id = ? AND d.party_id = ? AND d.kind <> ?' . $extra . ' ORDER BY d.date DESC, d.id DESC',
    'iis' . $types,
    array_merge([$cid, $id, 'expense'], $params)
));
$byKind = [];
foreach (desk_kind_list() as $k) {
    if ($k === 'expense') {
        continue;
    }
    $byKind[$k] = [];
}
foreach ($docs as $d) {
    if (($d['kind'] ?? '') === 'expense') {
        continue;
    }
    $byKind[$d['kind']][] = $d;
}
$owed = 0.0;
foreach ($byKind['invoice'] as $inv) {
    if ($inv['status'] !== 'void') {
        $owed += (float) ($inv['balance'] ?? 0);
    }
}

$clientKinds = array_values(array_filter(
    desk_kind_nav_items(),
    static fn (array $item): bool => ($item[3] ?? '') !== 'expense'
));

layout_start($party['name'], $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('clients') ?><?= h($party['name']) ?></h1>
    <dl class="party-brief">
      <div><dt>Kind</dt><dd><?= h($party['kind']) ?></dd></div>
      <div><dt>Status</dt><dd><span class="pill<?= party_status($party) === 'inactive' ? ' warn' : '' ?>"><?= h(party_status_label($party)) ?></span></dd></div>
      <?php if (!empty($party['contact_person'])): ?><div><dt>Attn</dt><dd><?= h($party['contact_person']) ?></dd></div><?php endif; ?>
      <?php if ($party['email']): ?><div><dt>Email</dt><dd><a href="mailto:<?= h($party['email']) ?>"><?= h($party['email']) ?></a></dd></div><?php endif; ?>
      <?php if (trim((string) $party['phone']) !== ''): ?><div><dt>Phone</dt><dd><a href="<?= h(phone_tel_href((string) $party['phone'])) ?>"><?= h($party['phone']) ?></a></dd></div><?php endif; ?>
      <?php if (!empty($party['phone2'])): ?><div><dt>Phone 2</dt><dd><a href="<?= h(phone_tel_href((string) $party['phone2'])) ?>"><?= h($party['phone2']) ?></a></dd></div><?php endif; ?>
      <?php if ($party['tin']): ?><div><dt>TIN</dt><dd><?= h($party['tin']) ?></dd></div><?php endif; ?>
      <?php if ($owed > 0): ?><div><dt>Outstanding</dt><dd><?= h(money($owed)) ?></dd></div><?php endif; ?>
      <?php
        $place = party_place_line($party);
        $addrBits = array_filter([trim((string) ($party['address'] ?? '')), $place]);
      ?>
      <?php if ($addrBits): ?><div class="party-brief-wide"><dt>Address</dt><dd><?= h(implode(', ', $addrBits)) ?></dd></div><?php endif; ?>
    </dl>
  </div>
  <div class="actions page-actions">
    <a class="btn ghost" href="<?= h(export_query('party', ['id' => (string) $id])) ?>"><?= icon('download', 16) ?>CSV</a>
    <a class="btn ghost" href="<?= h(url('desk_mail.php?party=' . $id)) ?>"><?= icon('send') ?>Email</a>
    <a class="btn ghost" href="<?= h(url('client_edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Edit</a>
    <?php if (party_status($party) !== 'deleted'): ?>
      <form method="post" action="<?= h(url('client_action.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="status">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="next" value="view">
        <input type="hidden" name="status" value="<?= party_status($party) === 'inactive' ? 'active' : 'inactive' ?>">
        <button class="btn ghost" type="submit"><?= icon(party_status($party) === 'inactive' ? 'check' : 'ban') ?><?= party_status($party) === 'inactive' ? 'Mark active' : 'Mark inactive' ?></button>
      </form>
      <?php render_party_delete_button($id, true); ?>
    <?php endif; ?>
  </div>
</div>

<?php render_filters('client_view.php', ['id' => (string) $id]); ?>

<div class="action-grid">
  <?php foreach ($clientKinds as [$href, $label, $iconName, $qKind]):
      $count = count($byKind[$qKind] ?? []);
      ?>
    <a class="card action-tile" href="<?= h(url('document_new.php?kind=' . $qKind . '&party=' . $id)) ?>">
      <?= icon($iconName, 20) ?>
      <span><?= h(kind_meta($qKind)['verb']) ?></span>
      <strong><?= $count ?> issued</strong>
    </a>
  <?php endforeach; ?>
  <a class="card action-tile" href="<?= h(url('desk_mail.php?party=' . $id)) ?>">
    <?= icon('send', 20) ?>
    <span>Email this client</span>
    <strong><?= $party['email'] ? h($party['email']) : 'Add an email to send' ?></strong>
  </a>
</div>

<?php foreach ($clientKinds as [$href, $title, $iconName, $kind]): ?>
  <div class="card" style="margin-top:16px">
    <div class="card-head">
      <h2><?= icon($iconName, 16) ?><?= h($title) ?></h2>
      <a class="btn sm" href="<?= h(url('document_new.php?kind=' . $kind . '&party=' . $id)) ?>"><?= icon('plus', 14) ?>Add</a>
    </div>
    <?php if (!$byKind[$kind]): ?>
      <p class="empty">None yet.</p>
    <?php else: ?>
      <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Number</th>
            <th>Date</th>
            <?php if (kind_shows_money($kind)): ?><th class="right">Amount</th><?php endif; ?>
            <?php if ($kind === 'invoice'): ?><th class="right">Balance</th><?php endif; ?>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($byKind[$kind] as $doc): ?>
            <tr>
              <td class="mono"><a href="<?= h(url('document_view.php?id=' . $doc['id'])) ?>"><?= h($doc['number']) ?></a></td>
              <td class="date-cell"><?= h(format_date($doc['date'])) ?></td>
              <?php if (kind_shows_money($kind)): ?>
                <td class="right mono"><?= h(money($doc['totals']['total'], doc_currency($doc))) ?></td>
              <?php endif; ?>
              <?php if ($kind === 'invoice'): ?>
                <td class="right mono"><?= h(money($doc['balance'] ?? 0, doc_currency($doc))) ?></td>
              <?php endif; ?>
              <td><span class="pill"><?= h(invoice_status_label($doc)) ?></span></td>
              <td class="row-actions"><?php render_doc_actions($doc); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if (kind_shows_money($kind)): ?>
          <tfoot>
            <tr>
              <td colspan="2">Totals</td>
              <td class="right mono"><?= h(money(documents_sum($byKind[$kind]))) ?></td>
              <?php if ($kind === 'invoice'): ?>
                <td class="right mono"><?= h(money(documents_sum($byKind[$kind], 'balance'))) ?></td>
              <?php endif; ?>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        <?php endif; ?>
      </table>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php layout_end(); ?>
