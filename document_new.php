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
if ($existing && !user_can_edit_documents()) {
    flash('Your login cannot edit documents.', 'err');
    redirect('document_view.php?id=' . $editId);
}

$kind = $existing['kind'] ?? ($_GET['kind'] ?? post('kind') ?: 'invoice');
if (!in_array($kind, desk_kind_list(), true)) {
    $kind = 'invoice';
}
if (!$existing) {
    require_desk_kind($kind);
}
$meta = kind_meta($kind);
$customDef = company_custom_doc();
$prefillParty = (int) ($existing['party_id'] ?? ($_GET['party'] ?? 0));
$related = (int) ($existing['related_id'] ?? ($_GET['related'] ?? 0));
$parties = parties_for($kind);
$vatDefault = company_tax_rate();
if ($existing && (float) ($existing['vat_rate'] ?? 0) > 0) {
    $vatDefault = (float) $existing['vat_rate'];
}
$taxName = company_tax_name();
$taxLineDefault = company_tax_default();
$openInvoices = $kind === 'receipt' ? outstanding_invoices(null, $related ?: null) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $partyId = ensure_document_party($kind);
    } catch (Throwable $e) {
        flash($e->getMessage(), 'err');
        redirect($editId ? 'document_new.php?id=' . $editId : 'document_new.php?kind=' . $kind);
    }
    $items = [];
    $names = $_POST['item_name'] ?? [];
    $descs = $_POST['item_desc'] ?? [];
    $qtys = $_POST['item_qty'] ?? [];
    $rates = $_POST['item_rate'] ?? [];
    $taxed = $_POST['item_taxed'] ?? [];
    $stockIds = $_POST['item_stock_id'] ?? [];
    $anyTaxed = false;
    $keys = array_unique(array_merge(array_keys((array) $names), array_keys((array) $descs)));
    sort($keys, SORT_NUMERIC);
    foreach ($keys as $i) {
        $name = trim((string) ($names[$i] ?? ''));
        $desc = trim((string) ($descs[$i] ?? ''));
        if ($name === '' && $desc === '') {
            continue;
        }
        $isTaxed = !empty($taxed[$i]) ? 1 : 0;
        if ($isTaxed) {
            $anyTaxed = true;
        }
        $items[] = [
            'item_name' => $name,
            'description' => $desc,
            'qty' => round((float) ($qtys[$i] ?? 1), 2),
            'unit' => 'lot',
            'rate' => money_parse((string) ($rates[$i] ?? '0')),
            'taxed' => $isTaxed,
            'stock_item_id' => (int) ($stockIds[$i] ?? 0),
        ];
    }
    $allocPosted = in_array($kind, ['receipt', 'refund'], true) ? money_parse(post('allocated_amount')) : 0.0;
    $relatedPosted = (int) post('related_id') ?: null;
    if (kind_uses_lines($kind) && !$items && !($kind === 'receipt' && ($allocPosted > 0 || $relatedPosted))) {
        flash('Add at least one line.', 'err');
        redirect($editId ? 'document_new.php?id=' . $editId : 'document_new.php?kind=' . $kind . ($prefillParty ? '&party=' . $prefillParty : ''));
    }
    if ($kind === 'letter' && (post('subject') === '' || html_to_plain(post('body', '', 80000)) === '')) {
        flash('A headed letter needs a subject and a body.', 'err');
        redirect($editId ? 'document_new.php?id=' . $editId : 'document_new.php?kind=letter' . ($prefillParty ? '&party=' . $prefillParty : ''));
    }
    $customValues = [];
    if ($kind === 'custom') {
        foreach ($customDef['fields'] as $field) {
            $customValues[$field['key']] = trim((string) ($_POST['custom_field'][$field['key']] ?? ''));
        }
    }
    $payload = [
        'kind' => $kind,
        'party_id' => $partyId,
        'date' => post('date') ?: today(),
        'due_date' => post('due_date') ?: null,
        'vat_rate' => $anyTaxed ? $vatDefault : 0.0,
        'currency' => post('currency') ?: default_currency(),
        'notes' => post('notes') ?: null,
        'subject' => post('subject') ?: null,
        'body' => in_array($kind, ['letter', 'custom'], true) ? (posted_rich('body') ?: null) : (post('body') ?: null),
        'related_id' => $relatedPosted,
        'payment_method' => post('payment_method') ?: null,
        'payment_ref' => post('payment_ref') ?: null,
        'allocated_amount' => in_array($kind, ['receipt', 'refund'], true) ? $allocPosted : null,
        'expense_category' => post('expense_category') ?: null,
        'letter_template' => (post('letter_template') === '' || post('letter_template') === 'none') ? null : (post('letter_template') ?: null),
        'add_signature' => post('add_signature') === '1' ? 1 : 0,
        'doc_template' => doc_template_key(),
        'items' => $items,
        'custom_values' => $customValues,
        'branch_id' => post('branch_id'),
    ];
    try {
        if (post('doc_template') !== '') {
            apply_company_doc_template(post('doc_template'));
        }
        if (post('fx_ugx_per_usd') !== '') {
            apply_fx_rate(post('fx_ugx_per_usd'));
        }
        $payload['doc_template'] = doc_template_key();
        $payload['party_extras'] = function_exists('posted_to_extras') ? posted_to_extras() : [];
        $postedDate = (string) ($payload['date'] ?? today());
        $today = today();
        if (!user_can_backdate_documents()) {
            if ($existing) {
                $orig = (string) ($existing['date'] ?? $today);
                if ($postedDate < $today && $postedDate !== $orig) {
                    $payload['date'] = $orig < $today ? $orig : $today;
                }
            } elseif ($postedDate < $today) {
                $payload['date'] = $today;
            }
        }
        if ($existing) {
            update_document($editId, $payload);
            $id = $editId;
            flash($meta['singular'] . ' ' . load_document($id)['number'] . ' updated.');
        } else {
            $id = create_document($payload);
            flash($meta['singular'] . ' ' . load_document($id)['number'] . ' saved.');
        }
        if (isset($_POST['from_name'])) {
            $letterhead = posted_letterhead();
            save_document_letterhead($id, $letterhead);
            if (trim((string) ($letterhead['name'] ?? '')) !== '') {
                apply_letterhead_to_branding($letterhead);
            }
        }
        apply_posted_party($partyId);
        redirect('document_view.php?id=' . $id);
    } catch (Throwable $e) {
        flash($e->getMessage(), 'err');
        redirect($editId ? 'document_new.php?id=' . $editId : 'document_new.php?kind=' . $kind);
    }
}

$templates = letter_templates();
if ($existing && $kind === 'letter') {
    $tplKey = trim((string) ($existing['letter_template'] ?? ''));
    if ($tplKey === '' || !isset($templates[$tplKey])) {
        $tplKey = 'none';
    }
} else {
    $tplKey = trim((string) ($_GET['template'] ?? post('letter_template') ?: 'none'));
    if ($tplKey === '' || ($tplKey !== 'none' && !isset($templates[$tplKey]))) {
        $tplKey = 'none';
    }
}
$prefillTpl = ($tplKey !== 'none' && isset($templates[$tplKey]))
    ? $templates[$tplKey]
    : ['subject' => '', 'body' => '', 'title' => 'No template', 'heading' => ''];
if ($existing && $kind === 'letter') {
    $prefillTpl['subject'] = (string) $existing['subject'];
    $prefillTpl['body'] = (string) $existing['body'];
} elseif ($related && !$existing && $tplKey !== 'none') {
    $rel = load_document($related);
    if ($rel) {
        $prefillTpl['subject'] = $prefillTpl['subject'] . ' - ' . $rel['number'];
    }
}
$lines = kind_uses_lines($kind) ? ($existing['items'] ?? []) : [];
$minLines = $existing ? max(1, count($existing['items'] ?? [])) : 1;
while (kind_uses_lines($kind) && count($lines) < $minLines) {
    $lines[] = ['item_name' => '', 'description' => '', 'qty' => 1, 'unit' => 'lot', 'rate' => '', 'taxed' => $taxLineDefault];
}
$heading = $existing ? 'Edit ' . strtolower($meta['singular']) : $meta['verb'];
$docCurrency = $existing ? doc_currency($existing) : default_currency();
$docTpl = $existing ? doc_template_key($existing) : doc_template_key();
$allocValue = $existing ? (string) ($existing['allocated_amount'] ?: ($existing['totals']['total'] ?? '')) : '';
$toParty = $prefillParty ? db_one('SELECT * FROM parties WHERE id = ? AND company_id = ?', 'ii', [$prefillParty, current_company_id()]) : null;
if ($toParty && party_status($toParty) !== 'active') {
    $found = false;
    foreach ($parties as $p) {
        if ((int) $p['id'] === (int) $toParty['id']) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        array_unshift($parties, $toParty);
    }
}
$partyBook = [];
foreach ($parties as $p) {
    $partyBook[(int) $p['id']] = [
        'name' => (string) ($p['name'] ?? ''),
        'contact' => (string) ($p['contact_person'] ?? ''),
        'tin' => (string) ($p['tin'] ?? ''),
        'phone' => (string) ($p['phone'] ?? ''),
        'phone2' => (string) ($p['phone2'] ?? ''),
        'email' => (string) ($p['email'] ?? ''),
        'address' => (string) ($p['address'] ?? ''),
        'city' => (string) ($p['city'] ?? ''),
        'country' => (string) ($p['country'] ?? ''),
        'entity' => function_exists('party_entity') ? party_entity($p) : 'person',
        'extras' => function_exists('party_profile') ? party_profile($p) : [],
    ];
}

layout_start($heading, $user, ['kind' => $kind]);
?>
<div class="page-head">
  <div>
    <h1><?= icon(document_kind_icon($kind)) ?><?= h($heading) ?><?php if ($existing): ?> <span class="mono" style="font-size:.55em;font-weight:600"><?= h($existing['number']) ?></span><?php endif; ?></h1>
    <p class="lede"><?php
      if ($existing) {
          echo 'Number stays the same. Change the client, lines, dates or design, then save.';
      } elseif ($kind === 'letter') {
          echo 'Start blank or pick a starting text for the body. The printed letter has no template title - the subject is the heading. Download Word to get the letter as it stands now, including the starting text you have selected.';
      } elseif ($kind === 'custom') {
          echo h($customDef['title']) . ' - fill the fields this company uses' . (!empty($customDef['has_body']) ? ', then the body if you need it' : '') . '.';
      } elseif ($kind === 'delivery') {
          echo 'Item, description and quantity. No prices - this is a delivery note, not a bill.';
      } elseif ($kind === 'receipt') {
          echo 'Link an open invoice to record a part payment. Anything still unpaid stays on Debtors.';
      } elseif ($kind === 'expense') {
          echo 'Record what the company spent. This is an expense for your books - not a bill sent to a client.';
      } elseif ($kind === 'refund') {
          echo 'Record money refunded to a customer or received back from a supplier. Link an invoice or expense when you can.';
      } elseif ($kind === 'return_note') {
          echo 'List goods returned by a customer or sent back to a supplier. Quantities only. Pair with a refund when money moves.';
      } else {
          echo 'Client, item, description, amount' . ($kind === 'invoice' ? ', due date' : '') . '. Numbering and branding are applied for you.';
      }
    ?></p>
  </div>
  <?php if ($kind === 'letter'): ?>
  <div class="actions page-actions">
    <a class="btn ghost" href="<?= h(url('letter_docx.php' . ($existing ? '?id=' . (int) $existing['id'] : ''))) ?>" data-letter-docx><?= icon('download', 16) ?>Word template</a>
  </div>
  <?php endif; ?>
</div>

<form class="card form-wide document-form" method="post" <?= $kind === 'letter' ? 'data-letter-templates' : '' ?> <?= $kind === 'receipt' ? 'data-receipt-form' : '' ?> data-fx-form data-fx-home="<?= h(default_currency()) ?>" data-tax-rate="<?= h((string) $vatDefault) ?>" data-tax-name="<?= h($taxName) ?>" data-doc-kind="<?= h($kind) ?>" data-party-book="<?= h(json_encode($partyBook, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') ?>">
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
      <div class="doc-span">
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
    <div<?= in_array($kind, ['invoice', 'quotation'], true) ? '' : ' class="doc-span"' ?>>
      <label for="date">Date</label>
      <?php
        $canBackdate = user_can_backdate_documents();
        $dateVal = (string) ($existing['date'] ?? today());
        $dateMin = $canBackdate ? '1990-01-01' : today();
        if (!$canBackdate && $existing && $dateVal < today()) {
            $dateMin = $dateVal;
        }
      ?>
      <div class="doc-date-control">
        <?= icon('calendar', 18) ?>
        <input id="date" name="date" type="date" min="<?= h($dateMin) ?>" max="2100-12-31" value="<?= h($dateVal) ?>" required data-date-input<?= (!$canBackdate && $existing && $dateVal < today()) ? ' readonly' : '' ?>>
        <span class="doc-date-pretty" data-date-pretty></span>
      </div>
      <p class="hint"><?= $canBackdate ? 'Type or pick any date, including a past date if you need to backdate the sheet.' : 'This login can date new sheets today or later. Ask the company admin for backdating.' ?></p>
    </div>
    <?php if (in_array($kind, ['invoice', 'quotation'], true)): ?>
      <div>
        <label for="due_date">Due date</label>
        <div class="doc-date-control">
          <?= icon('calendar', 18) ?>
          <input id="due_date" name="due_date" type="date" min="1990-01-01" max="2100-12-31" value="<?= h((string) ($existing['due_date'] ?? date('Y-m-d', strtotime('+14 days')))) ?>" data-date-input>
          <span class="doc-date-pretty" data-date-pretty></span>
        </div>
      </div>
    <?php endif; ?>
    <?php if (company_branches_enabled()): ?>
      <div>
        <label for="branch_id">Branch</label>
        <?php
          $issueBranch = (int) ($existing['branch_id'] ?? ($user['branch_id'] ?? 0));
          if (!is_desk_admin($user)) {
              $issueBranch = (int) ($user['branch_id'] ?? 0);
          }
        ?>
        <?php if (is_desk_admin($user)): ?>
          <select id="branch_id" name="branch_id">
            <?php render_branch_options($issueBranch); ?>
          </select>
          <p class="hint">The address on this sheet is the branch you pick. Head office uses the company address in Settings.</p>
        <?php else: ?>
          <input type="hidden" name="branch_id" value="<?= $issueBranch ?>">
          <p class="hint">Issued from <strong><?= h(company_branch_label($issueBranch)) ?></strong>.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($kind === 'expense'): ?>
    <div>
      <label for="to_name">Party (optional)</label>
      <div class="client-combo" data-client-combo>
        <input type="hidden" id="party_id" name="party_id" value="<?= $prefillParty ?: '' ?>">
        <input id="to_name" name="to_name" autocomplete="off" placeholder="Payee if you have one…" value="<?= h((string) ($toParty['name'] ?? '')) ?>" data-client-search>
        <div class="client-combo-panel" data-client-panel hidden>
          <button type="button" class="client-combo-scroll" data-client-scroll="-1" aria-label="Scroll client list up"><?= icon('chevron-up', 16) ?></button>
          <ul class="client-combo-list" data-client-list></ul>
          <button type="button" class="client-combo-scroll" data-client-scroll="1" aria-label="Scroll client list down"><?= icon('chevron-down', 16) ?></button>
        </div>
      </div>
      <p class="hint">Leave blank if this spend has no supplier. <a href="<?= h(url('client_edit.php')) ?>">Open the full client form</a></p>
    </div>
    <?php else: ?>
    <?php render_document_to_fields($toParty, $existing); ?>
    <?php endif; ?>
    <?php if (kind_shows_money($kind)): ?>
      <div>
        <label for="currency">Currency</label>
        <?php currency_field('currency', 'currency', $docCurrency, ['data-fx-currency' => $docCurrency]); ?>
        <p class="hint" data-fx-preview></p>
      </div>
      <div>
        <label for="fx_ugx_per_usd">1 USD equals</label>
        <div class="fx-row">
          <input id="fx_ugx_per_usd" name="fx_ugx_per_usd" data-fx-rate inputmode="decimal" value="<?= h(rtrim(rtrim(number_format(fx_home_per_usd(), 4, '.', ''), '0'), '.')) ?>">
          <span data-fx-home-label><?= h(default_currency()) ?></span>
        </div>
        <p class="hint">USD converts into <?= h(default_currency()) ?> at this rate. Other currencies stay as entered.</p>
      </div>
    <?php endif; ?>
    <?php if ($kind !== 'custom' && $kind !== 'expense'): ?>
    <div>
      <label for="doc_template">Design</label>
      <select id="doc_template" name="doc_template">
        <?php foreach (doc_templates() as $key => $info): ?>
          <option value="<?= h($key) ?>" <?= $docTpl === $key ? 'selected' : '' ?>><?= h($info['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="hint">This layout prints on invoices, quotations, receipts and letters.</p>
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
    <?php if ($kind === 'refund'): ?>
      <div>
        <label for="expense_category">Direction</label>
        <select id="expense_category" name="expense_category">
          <?php $dir = (string) ($existing['expense_category'] ?? 'out'); ?>
          <option value="out" <?= in_array($dir, ['out', 'customer', 'customer_refund', 'refund_out', ''], true) ? 'selected' : '' ?>>Out: refund to customer</option>
          <option value="in" <?= in_array($dir, ['in', 'supplier', 'supplier_refund', 'refund_in'], true) ? 'selected' : '' ?>>In: refund from supplier</option>
        </select>
      </div>
      <div>
        <label for="allocated_amount">Refund amount</label>
        <input id="allocated_amount" name="allocated_amount" inputmode="decimal" value="<?= h($allocValue) ?>" placeholder="Leave blank to use the line total">
      </div>
    <?php endif; ?>
    <?php if ($kind === 'return_note'): ?>
      <div>
        <label for="expense_category">Direction</label>
        <select id="expense_category" name="expense_category">
          <?php $dir = (string) ($existing['expense_category'] ?? 'out'); ?>
          <option value="out" <?= in_array($dir, ['out', 'customer', 'customer_return', ''], true) ? 'selected' : '' ?>>From customer</option>
          <option value="in" <?= in_array($dir, ['in', 'supplier', 'supplier_return'], true) ? 'selected' : '' ?>>To supplier</option>
        </select>
      </div>
    <?php endif; ?>
    <?php if (in_array($kind, ['receipt', 'expense', 'refund'], true)): ?>
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
    <label>Starting text</label>
    <p class="hint">Leave blank, or pick a starting text. Subject and body stay required either way.</p>
    <div class="template-grid">
        <label class="template-card" data-subject="" data-body="" data-sign="0">
        <input type="radio" name="letter_template" value="none" <?= $tplKey === 'none' ? 'checked' : '' ?>>
        <strong>No template</strong>
        <em>Blank letter</em>
      </label>
      <?php foreach ($templates as $key => $tpl): ?>
        <label class="template-card" data-subject="<?= h($tpl['subject']) ?>" data-body="<?= h($tpl['body']) ?>" data-sign="1">
          <input type="radio" name="letter_template" value="<?= h($key) ?>" <?= $tplKey === $key ? 'checked' : '' ?>>
          <strong><?= h($tpl['title']) ?></strong>
          <em><?= h($tpl['heading']) ?></em>
        </label>
      <?php endforeach; ?>
    </div>
    <label for="subject">Subject</label>
    <input id="subject" name="subject" required value="<?= h($prefillTpl['subject']) ?>">
    <label for="body">Body</label>
    <?php render_rich_editor('body', 'body', (string) $prefillTpl['body'], ['rows' => 16, 'required' => true, 'placeholder' => 'Write the letter. Use the toolbar for bold, lists and alignment.']); ?>
    <?php render_add_signature_checkbox($existing, $tplKey !== 'none'); ?>
  <?php elseif ($kind === 'custom'): ?>
    <?php $savedCustom = is_array($existing['custom_values'] ?? null) ? $existing['custom_values'] : []; ?>
    <?php if ($customDef['fields']): ?>
      <div class="form-grid custom-values-grid">
        <?php foreach ($customDef['fields'] as $field): ?>
          <div>
            <label for="cf-<?= h($field['key']) ?>"><?= h($field['label']) ?></label>
            <input id="cf-<?= h($field['key']) ?>" name="custom_field[<?= h($field['key']) ?>]" value="<?= h((string) ($savedCustom[$field['key']] ?? '')) ?>">
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="hint">No extra fields were set for this document. Super admin can add them on the company page.</p>
    <?php endif; ?>
    <?php if (!empty($customDef['has_body'])): ?>
      <label for="body">Body</label>
      <textarea id="body" name="body" class="letter-body-field" rows="16"><?= h((string) ($existing['body'] ?? '')) ?></textarea>
    <?php endif; ?>
    <label for="notes">Internal note (not printed)</label>
    <textarea id="notes" name="notes" rows="3"><?= h((string) ($existing['notes'] ?? '')) ?></textarea>
    <?php render_add_signature_checkbox($existing); ?>
  <?php else: ?>
    <div class="lines-panel" data-lines-panel data-delivery="<?= in_array($kind, ['delivery', 'return_note'], true) ? '1' : '0' ?>" data-line-cols="<?= h(json_encode(company_document_line_columns(), JSON_UNESCAPED_UNICODE) ?: '[]') ?>">
      <?php
        $qtyOnly = in_array($kind, ['delivery', 'return_note'], true);
        $colItem = company_shows_line_col('item');
        $colDesc = company_shows_line_col('description');
        $colQty = company_shows_line_col('qty');
        $colRate = !$qtyOnly && company_shows_line_col('rate');
        $colTotal = !$qtyOnly && company_shows_line_col('total');
        $colVat = !$qtyOnly && company_shows_line_col('vat');
        if (!$colItem && !$colDesc) {
            $colItem = true;
        }
      ?>
      <div class="lines-wrap">
      <table class="grid lines" id="lines" data-lines data-tax-default="<?= (int) $taxLineDefault ?>"<?= (function_exists('company_stock_enabled') && company_stock_enabled()) ? ' data-stock-catalog="1"' : '' ?>>
        <thead>
          <tr>
            <?php if ($colItem): ?><th>Item</th><?php endif; ?>
            <?php if ($colDesc): ?><th>Description</th><?php endif; ?>
            <?php if ($colQty): ?><th>Qty</th><?php endif; ?>
            <?php if ($colRate): ?><th class="right">Unit price</th><?php endif; ?>
            <?php if ($colTotal): ?><th class="right">Total Amt</th><?php endif; ?>
            <?php if ($colVat): ?><th class="center"><?= h($taxName) ?></th><?php endif; ?>
            <th class="center lines-del-col"> </th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lines as $i => $line):
              $qty = (float) ($line['qty'] ?? 1);
              $rate = (float) ($line['rate'] ?? 0);
              $lineTotal = $qty * $rate;
              ?>
            <tr>
              <?php if ($colItem): ?>
              <td class="line-item">
                <input type="hidden" name="item_stock_id[<?= $i ?>]" value="<?= (int) ($line['stock_item_id'] ?? 0) ?>">
                <input name="item_name[<?= $i ?>]" placeholder="Item" value="<?= h((string) ($line['item_name'] ?? '')) ?>" autocomplete="off">
              </td>
              <?php else: ?>
                <input type="hidden" name="item_stock_id[<?= $i ?>]" value="<?= (int) ($line['stock_item_id'] ?? 0) ?>">
                <input type="hidden" name="item_name[<?= $i ?>]" value="<?= h((string) ($line['item_name'] ?? '')) ?>">
              <?php endif; ?>
              <?php if ($colDesc): ?>
              <td class="line-desc"><textarea name="item_desc[<?= $i ?>]" rows="2" placeholder="Description"><?= h((string) ($line['description'] ?? '')) ?></textarea></td>
              <?php else: ?>
                <input type="hidden" name="item_desc[<?= $i ?>]" value="<?= h((string) ($line['description'] ?? '')) ?>">
              <?php endif; ?>
              <?php if ($colQty): ?>
              <td class="line-qty">
                <div class="qty-wrap">
                  <button type="button" class="qty-btn" data-qty-delta="-1" aria-label="Decrease quantity">-</button>
                  <input name="item_qty[<?= $i ?>]" type="number" min="0" step="any" inputmode="decimal" value="<?= h((string) ($line['qty'] ?? 1)) ?>" data-line-qty>
                  <button type="button" class="qty-btn" data-qty-delta="1" aria-label="Increase quantity">+</button>
                </div>
              </td>
              <?php else: ?>
                <input type="hidden" name="item_qty[<?= $i ?>]" value="<?= h((string) ($line['qty'] ?? 1)) ?>" data-line-qty>
              <?php endif; ?>
              <?php if ($qtyOnly): ?>
                <input type="hidden" name="item_rate[<?= $i ?>]" value="0">
              <?php else: ?>
                <?php if ($colRate): ?>
                <td class="line-rate"><input name="item_rate[<?= $i ?>]" type="number" min="0" step="any" inputmode="decimal" placeholder="0" value="<?= h((string) ($line['rate'] ?? '')) ?>" data-line-rate></td>
                <?php else: ?>
                <input type="hidden" name="item_rate[<?= $i ?>]" value="<?= h((string) ($line['rate'] ?? '0')) ?>" data-line-rate>
                <?php endif; ?>
                <?php if ($colTotal): ?>
                <td class="line-total right"><input name="item_total[<?= $i ?>]" inputmode="decimal" placeholder="0" value="<?= $lineTotal ? h(rtrim(rtrim(number_format($lineTotal, 2, '.', ''), '0'), '.')) : '' ?>" data-line-total autocomplete="off"></td>
                <?php endif; ?>
                <?php if ($colVat): ?>
                <td class="line-vat center">
                  <label class="vat-yn">
                    <input type="checkbox" name="item_taxed[<?= $i ?>]" value="1" <?= !empty($line['taxed']) ? 'checked' : '' ?> data-vat-box>
                    <span data-vat-yn><?= !empty($line['taxed']) ? 'Y' : 'N' ?></span>
                  </label>
                </td>
                <?php endif; ?>
              <?php endif; ?>
              <td class="center lines-del-col">
                <button type="button" class="btn ghost sm icon-only" data-remove-line title="Remove" aria-label="Remove"><?= icon('x', 14) ?></button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <p class="lines-toolbar">
        <button class="btn ghost sm" type="button" data-add-line><?= icon('plus', 14) ?>Add row</button>
        <button class="btn ghost sm" type="button" data-remove-last-line><?= icon('trash', 14) ?>Delete row</button>
        <?php if ($kind === 'receipt'): ?>
          <span class="hint">Lines can be left blank if you set the amount received against an invoice.</span>
        <?php elseif ($kind === 'delivery'): ?>
          <span class="hint">Add as many rows as you need - long lists print on extra pages.</span>
        <?php elseif ($kind === 'expense'): ?>
          <span class="hint">What was bought or paid for. Expenses open as a detail card, not stationery.</span>
        <?php else: ?>
          <span class="hint">Type an item. Names in stock appear as you type. Keep typing if it is not in stock. Tick <?= h($taxName) ?> for Y.</span>
        <?php endif; ?>
      </p>
      <?php if (!in_array($kind, ['delivery', 'return_note'], true)): ?>
      <div class="doc-sum" data-doc-sum>
        <span>Subtotal <strong data-doc-sub><?= h(money_behind(0, $docCurrency)) ?></strong></span>
        <span><?= h($taxName) ?> <strong data-doc-tax><?= h(money_behind(0, $docCurrency)) ?></strong></span>
        <span>Total <strong data-doc-grand><?= h(money_behind(0, $docCurrency)) ?></strong></span>
        <?php if (in_array($kind, ['invoice', 'receipt', 'expense', 'refund'], true)): ?>
          <span data-doc-due-wrap>Due <strong data-doc-due><?= h(money_behind(0, $docCurrency)) ?></strong></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($kind !== 'expense'): ?>
      <div class="lines-preview" data-lines-preview>
        <div class="lines-preview-head">
          <strong>Document preview</strong>
          <span class="muted">How this table will look on the printed sheet. On a phone, swipe sideways to see every column.</span>
        </div>
        <div class="table-scroll">
          <table class="grid lines-preview-table">
            <thead>
              <tr>
                <?php if ($colItem): ?><th>Item</th><?php endif; ?>
                <?php if ($colDesc): ?><th>Description</th><?php endif; ?>
                <?php if ($colQty): ?><th class="center">Qty</th><?php endif; ?>
                <?php if ($colRate): ?><th class="right">Unit price</th><?php endif; ?>
                <?php if ($colTotal): ?><th class="right">Total Amt</th><?php endif; ?>
                <?php if ($colVat): ?><th class="center"><?= h($taxName) ?></th><?php endif; ?>
              </tr>
            </thead>
            <tbody data-lines-preview-body></tbody>
            <?php if (!$qtyOnly && ($colTotal || $colRate)): ?>
            <tfoot data-lines-preview-foot></tfoot>
            <?php endif; ?>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <label for="notes"><?= $kind === 'expense' ? 'Notes' : 'Comments on the document' ?></label>
    <?php
    $commentSeed = (string) ($existing['notes'] ?? '');
    if ($commentSeed === '' && !$existing) {
        $commentSeed = brand_document_comments(branding(), (string) $kind, '');
    }
    $commentPh = 'Comments on this document.';
    if ($kind === 'expense') {
        $commentPh = 'Optional note for your records.';
    } elseif ($kind === 'invoice') {
        $commentPh = 'Printed on this invoice. Leave blank to use default invoice comments from Settings.';
    } elseif ($kind === 'receipt') {
        $commentPh = 'Printed on this receipt. Leave blank to use default receipt comments from Settings.';
    }
    ?>
    <textarea id="notes" name="notes" rows="4" placeholder="<?= h($commentPh) ?>"><?= h($commentSeed) ?></textarea>
    <?php if ($kind !== 'expense') { render_add_signature_checkbox($existing); } ?>
  <?php endif; ?>

  <div class="actions sticky-save">
    <button class="btn" type="submit"><?= icon('check') ?>Save <?= h(strtolower($meta['singular'])) ?></button>
    <a class="btn ghost" href="<?= h(url($existing ? 'document_view.php?id=' . $existing['id'] : 'documents.php?kind=' . $kind)) ?>">Cancel</a>
  </div>
</form>
<?php
$stockJs = '';
if (function_exists('company_stock_enabled') && company_stock_enabled() && $kind !== 'letter' && $kind !== 'custom') {
    $stockJs = '<script type="application/json" id="desk-stock-catalog">' . json_encode(stock_catalog_payload(), JSON_UNESCAPED_UNICODE) . '</script>';
}
layout_end($stockJs);

