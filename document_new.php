<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$editId = (int) ($_GET['id'] ?? post('document_id'));
$existing = $editId ? load_document($editId) : null;
if ($editId && !$existing) {
    flash('Document not found.', 'err');
    redirect('dashboard.php');
}
if ($existing && $existing['status'] === 'void') {
    flash('Voided documents cannot be edited.', 'err');
    redirect('document_view.php?id=' . $editId);
}

$kind = $existing['kind'] ?? ($_GET['kind'] ?? post('kind') ?: 'invoice');
if (!in_array($kind, ['invoice', 'quotation', 'receipt', 'expense', 'letter'], true)) {
    $kind = 'invoice';
}
$meta = kind_meta($kind);
$prefillParty = (int) ($existing['party_id'] ?? ($_GET['party'] ?? 0));
$related = (int) ($existing['related_id'] ?? ($_GET['related'] ?? 0));
$parties = parties_for($kind);
$vatDefault = 0.18;
$openInvoices = $kind === 'receipt' ? outstanding_invoices(null, $related ?: null) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $partyId = (int) post('party_id');
    if ($partyId <= 0) {
        flash('Choose a client or payee.', 'err');
        redirect($editId ? 'document_new.php?id=' . $editId : 'document_new.php?kind=' . $kind);
    }
    $items = [];
    $descs = $_POST['item_desc'] ?? [];
    $qtys = $_POST['item_qty'] ?? [];
    $units = $_POST['item_unit'] ?? [];
    $rates = $_POST['item_rate'] ?? [];
    $taxed = $_POST['item_taxed'] ?? [];
    foreach ($descs as $i => $desc) {
        $desc = trim((string) $desc);
        if ($desc === '') {
            continue;
        }
        $items[] = [
            'description' => $desc,
            'qty' => round((float) ($qtys[$i] ?? 1), 2),
            'unit' => (string) ($units[$i] ?? 'lot'),
            'rate' => money_parse((string) ($rates[$i] ?? '0')),
            'taxed' => !empty($taxed[$i]) ? 1 : 0,
        ];
    }
    $allocPosted = $kind === 'receipt' ? money_parse(post('allocated_amount')) : 0.0;
    $relatedPosted = (int) post('related_id') ?: null;
    if ($kind !== 'letter' && !$items && !($kind === 'receipt' && ($allocPosted > 0 || $relatedPosted))) {
        flash('Add at least one line.', 'err');
        redirect($editId ? 'document_new.php?id=' . $editId : 'document_new.php?kind=' . $kind . ($prefillParty ? '&party=' . $prefillParty : ''));
    }
    $payload = [
        'kind' => $kind,
        'party_id' => $partyId,
        'date' => post('date') ?: today(),
        'due_date' => post('due_date') ?: null,
        'vat_rate' => post('taxed_doc') === '1' ? $vatDefault : 0.0,
        'currency' => post('currency') ?: default_currency(),
        'notes' => post('notes') ?: null,
        'subject' => post('subject') ?: null,
        'body' => post('body') ?: null,
        'related_id' => $relatedPosted,
        'payment_method' => post('payment_method') ?: null,
        'payment_ref' => post('payment_ref') ?: null,
        'allocated_amount' => $kind === 'receipt' ? $allocPosted : null,
        'expense_category' => post('expense_category') ?: null,
        'letter_template' => post('letter_template') ?: null,
        'doc_template' => post('doc_template') ?: doc_template_key(),
        'items' => $items,
    ];
    try {
        if ($existing) {
            update_document($editId, $payload);
            $id = $editId;
            flash($meta['singular'] . ' ' . load_document($id)['number'] . ' updated.');
        } else {
            $id = create_document($payload);
            flash($meta['singular'] . ' ' . load_document($id)['number'] . ' saved.');
        }
        redirect('document_view.php?id=' . $id);
    } catch (Throwable $e) {
        flash($e->getMessage(), 'err');
        redirect($editId ? 'document_new.php?id=' . $editId : 'document_new.php?kind=' . $kind);
    }
}

$tplKey = $existing['letter_template'] ?? ($_GET['template'] ?? post('letter_template') ?: 'demand');
$templates = letter_templates();
if (!isset($templates[$tplKey])) {
    $tplKey = 'demand';
}
$prefillTpl = $templates[$tplKey];
if ($existing && $kind === 'letter') {
    $prefillTpl['subject'] = (string) $existing['subject'];
    $prefillTpl['body'] = (string) $existing['body'];
} elseif ($related && !$existing) {
    $rel = load_document($related);
    if ($rel) {
        $prefillTpl['subject'] = $prefillTpl['subject'] . ' - ' . $rel['number'];
    }
}
$lines = $kind === 'letter' ? [] : ($existing['items'] ?? []);
while ($kind !== 'letter' && count($lines) < 4) {
    $lines[] = ['description' => '', 'qty' => 1, 'unit' => 'lot', 'rate' => '', 'taxed' => $vatDefault > 0 && $kind !== 'receipt'];
}
$heading = $existing ? 'Edit ' . strtolower($meta['singular']) : $meta['verb'];
$vatOn = $existing ? ((float) $existing['vat_rate'] > 0) : true;
$docCurrency = $existing ? doc_currency($existing) : default_currency();
$docTpl = $existing ? doc_template_key($existing) : doc_template_key();
$allocValue = $existing ? (string) ($existing['allocated_amount'] ?: ($existing['totals']['total'] ?? '')) : '';

layout_start($heading, $user, ['kind' => $kind]);
?>
<div class="page-head">
  <div>
    <h1><?= icon($kind) ?><?= h($heading) ?><?php if ($existing): ?> <span class="mono" style="font-size:.55em;font-weight:600"><?= h($existing['number']) ?></span><?php endif; ?></h1>
    <p class="lede"><?php
      if ($existing) {
          echo 'Number stays the same. Change the client, lines, dates or design, then save.';
      } elseif ($kind === 'letter') {
          echo 'Pick a headed template, then edit the body. Stationery is applied from Settings.';
      } elseif ($kind === 'receipt') {
          echo 'Link an open invoice to record a part payment. Anything still unpaid stays on Debtors.';
      } else {
          echo 'Client, description, amount' . ($kind === 'invoice' ? ', due date' : '') . '. Numbering and branding are applied for you.';
      }
    ?></p>
  </div>
</div>

<form class="card form-wide" method="post" <?= $kind === 'letter' ? 'data-letter-templates' : '' ?> <?= $kind === 'receipt' ? 'data-receipt-form' : '' ?>>
  <?= csrf_field() ?>
  <input type="hidden" name="kind" value="<?= h($kind) ?>">
  <?php if ($existing): ?>
    <input type="hidden" name="document_id" value="<?= (int) $existing['id'] ?>">
  <?php endif; ?>
  <?php if ($kind !== 'receipt'): ?>
    <input type="hidden" name="related_id" value="<?= $related ?>">
  <?php endif; ?>

  <div class="form-grid">
    <?php if ($kind === 'receipt'): ?>
      <div style="grid-column:1 / -1">
        <label for="related_id">Against invoice</label>
        <select id="related_id" name="related_id" data-against-invoice>
          <option value="">No invoice - standalone receipt</option>
          <?php foreach ($openInvoices as $inv):
              $remain = (float) $inv['balance'];
              if ($existing && (int) $inv['id'] === $related) {
                  $remain = round($remain + (float) ($existing['allocated_amount'] ?? 0), 2);
              }
              ?>
            <option
              value="<?= (int) $inv['id'] ?>"
              <?= (int) $inv['id'] === $related ? 'selected' : '' ?>
              data-party="<?= (int) $inv['party_id'] ?>"
              data-currency="<?= h(doc_currency($inv)) ?>"
              data-balance="<?= h((string) $remain) ?>"
              data-number="<?= h($inv['number']) ?>"
            ><?= h($inv['number']) ?> · <?= h($inv['party_name']) ?> · <?= h(money($remain, doc_currency($inv))) ?> due</option>
          <?php endforeach; ?>
        </select>
        <p class="hint">Enter less than the remaining balance to record a part payment. The rest stays on the client in Debtors.</p>
      </div>
    <?php endif; ?>
    <div>
      <label for="party_id"><?= $kind === 'expense' ? 'Payee' : 'Client' ?></label>
      <select id="party_id" name="party_id" required>
        <option value="">Choose…</option>
        <?php foreach ($parties as $p): ?>
          <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === $prefillParty ? 'selected' : '' ?>><?= h($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="hint"><a href="<?= h(url('client_edit.php')) ?>">Add a new client</a></p>
    </div>
    <div>
      <label for="date">Date</label>
      <input id="date" name="date" type="date" value="<?= h((string) ($existing['date'] ?? today())) ?>" required>
    </div>
    <?php if (in_array($kind, ['invoice', 'quotation'], true)): ?>
      <div>
        <label for="due_date">Due date</label>
        <input id="due_date" name="due_date" type="date" value="<?= h((string) ($existing['due_date'] ?? date('Y-m-d', strtotime('+14 days')))) ?>">
      </div>
    <?php endif; ?>
    <?php if ($kind !== 'letter'): ?>
      <div>
        <label for="currency">Currency</label>
        <select id="currency" name="currency">
          <?php foreach (currencies() as $code => $label): ?>
            <option value="<?= h($code) ?>" <?= $docCurrency === $code ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="doc_template">Design</label>
        <select id="doc_template" name="doc_template">
          <?php foreach (doc_templates() as $key => $info): ?>
            <option value="<?= h($key) ?>" <?= $docTpl === $key ? 'selected' : '' ?>><?= h($info['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
    <?php if ($kind === 'expense'): ?>
      <div>
        <label for="expense_category">Category</label>
        <select id="expense_category" name="expense_category">
          <?php foreach (expense_categories() as $c): ?>
            <option <?= ($existing['expense_category'] ?? '') === $c ? 'selected' : '' ?>><?= h($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
    <?php if (in_array($kind, ['receipt', 'expense'], true)): ?>
      <?php if ($kind === 'receipt'): ?>
        <div>
          <label for="allocated_amount">Amount received</label>
          <input id="allocated_amount" name="allocated_amount" inputmode="decimal" value="<?= h($allocValue) ?>" placeholder="Leave blank to use the line total">
        </div>
      <?php endif; ?>
      <div>
        <label for="payment_method">Paid how</label>
        <select id="payment_method" name="payment_method">
          <?php foreach (payment_methods() as $k => $label): ?>
            <option value="<?= h($k) ?>" <?= ($existing['payment_method'] ?? '') === $k ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="payment_ref">Reference</label>
        <input id="payment_ref" name="payment_ref" placeholder="Bank slip, MoMo ID…" value="<?= h((string) ($existing['payment_ref'] ?? '')) ?>">
      </div>
    <?php endif; ?>
  </div>

  <?php if ($kind === 'letter'): ?>
    <label>Template</label>
    <div class="template-grid">
      <?php foreach ($templates as $key => $tpl): ?>
        <label class="template-card" data-subject="<?= h($tpl['subject']) ?>" data-body="<?= h($tpl['body']) ?>">
          <input type="radio" name="letter_template" value="<?= h($key) ?>" <?= $tplKey === $key ? 'checked' : '' ?>>
          <strong><?= h($tpl['title']) ?></strong>
          <em><?= h($tpl['heading']) ?></em>
        </label>
      <?php endforeach; ?>
    </div>
    <label for="subject">Subject</label>
    <input id="subject" name="subject" required value="<?= h($prefillTpl['subject']) ?>">
    <label for="body">Body</label>
    <textarea id="body" name="body" rows="12" required><?= h($prefillTpl['body']) ?></textarea>
  <?php else: ?>
    <?php if ($kind !== 'receipt'): ?>
      <label class="check">
        <input type="checkbox" name="taxed_doc" value="1" <?= $vatOn ? 'checked' : '' ?>>
        VAT 18% on taxed lines
      </label>
    <?php endif; ?>
    <table class="grid lines" id="lines">
      <thead>
        <tr>
          <th>Item / description</th>
          <th>Qty</th>
          <th>Unit</th>
          <th>Unit price</th>
          <th>VAT</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lines as $i => $line): ?>
          <tr>
            <td><input name="item_desc[<?= $i ?>]" placeholder="Coffee Estate Share" value="<?= h((string) ($line['description'] ?? '')) ?>"></td>
            <td>
              <div class="qty-wrap">
                <button type="button" class="qty-btn" data-qty-delta="-1" aria-label="Decrease quantity">-</button>
                <input name="item_qty[<?= $i ?>]" type="number" min="0" step="any" inputmode="decimal" value="<?= h((string) ($line['qty'] ?? 1)) ?>">
                <button type="button" class="qty-btn" data-qty-delta="1" aria-label="Increase quantity">+</button>
              </div>
            </td>
            <td><input name="item_unit[<?= $i ?>]" value="<?= h((string) ($line['unit'] ?? 'lot')) ?>"></td>
            <td><input name="item_rate[<?= $i ?>]" type="number" min="0" step="any" inputmode="decimal" placeholder="0" value="<?= h((string) ($line['rate'] ?? '')) ?>"></td>
            <td class="center"><input type="checkbox" name="item_taxed[<?= $i ?>]" value="1" <?= !empty($line['taxed']) ? 'checked' : '' ?>></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="hint" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <button class="btn ghost sm" type="button" data-add-line><?= icon('plus', 14) ?>Add row</button>
      <?php if ($kind === 'receipt'): ?>
        Lines can be left blank if you set the amount received against an invoice.
      <?php else: ?>
        Quantity steps in whole numbers; decimals such as 1.5 are allowed. Empty description rows are ignored.
      <?php endif; ?>
    </p>
    <label for="notes">Comments on the document</label>
    <textarea id="notes" name="notes" rows="4" placeholder="Payment is due by the date shown above."><?= h((string) ($existing['notes'] ?? ($kind === 'invoice' ? (string) branding()['invoice_comments'] : ''))) ?></textarea>
  <?php endif; ?>

  <div class="actions" style="margin-top:16px">
    <button class="btn" type="submit"><?= icon('check') ?>Save <?= h(strtolower($meta['singular'])) ?></button>
    <a class="btn ghost" href="<?= h(url($existing ? 'document_view.php?id=' . $existing['id'] : 'documents.php?kind=' . $kind)) ?>">Cancel</a>
  </div>
</form>
<?php layout_end(); ?>
