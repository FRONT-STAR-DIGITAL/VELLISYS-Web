<?php
declare(strict_types=1);

function render_line_table(array $doc, string $color, string $tint): void
{
    $items = $doc['items'] ?? [];
    $rows = $items;
    while (count($rows) < 4) {
        $rows[] = null;
    }
    $cur = doc_currency($doc);
    ?>
    <table style="width:100%;border-collapse:collapse;font-size:12px">
      <thead>
        <tr style="background:<?= h($color) ?>;color:#fff">
          <th style="text-align:left;padding:7px 8px">Item / description</th>
          <th style="text-align:center;width:70px">Qty</th>
          <th style="text-align:left;width:70px">Unit</th>
          <th style="text-align:right;padding:7px 8px;width:110px">Unit price</th>
          <th style="text-align:right;padding:7px 8px;width:120px">Full price</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $item): ?>
          <tr style="background:<?= $i % 2 ? h($tint) : '#fff' ?>">
            <td style="padding:8px 8px;height:28px"><?= $item ? h($item['description']) : '&nbsp;' ?></td>
            <td style="text-align:center"><?= $item ? h(format_qty($item['qty'])) : '' ?></td>
            <td><?= $item ? h((string) $item['unit']) : '' ?></td>
            <td style="text-align:right;padding:8px 8px"><?= $item ? h(money($item['rate'], $cur)) : '' ?></td>
            <td style="text-align:right;padding:8px 8px"><?= $item ? h(money(line_amount($item), $cur)) : '' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php
}

function render_expense_card(array $brand, array $doc): void
{
    $color = $brand['brand_color'] ?: '#82B440';
    $tint = hex_tint($color, 0.92);
    $items = $doc['items'] ?? [];
    $net = doc_subtotal($items);
    $vat = doc_vat($items, (float) $doc['vat_rate']);
    $total = $net + $vat;
    $paid = $doc['paid'] ?? expense_paid((int) $doc['id']);
    $balance = $doc['balance'] ?? max(0, $total - $paid);
    $cur = doc_currency($doc);
    $logo = logo_url();
    ?>
<article class="expense-card" style="--brand: <?= h($color) ?>">
  <div class="expense-card-top">
    <div>
      <span>Expense</span>
      <strong><?= h($doc['number']) ?></strong>
    </div>
    <div style="text-align:right">
      <span><?= h(format_date($doc['date'])) ?> · <?= h($cur) ?></span>
      <strong><?= h(money($total, $cur)) ?></strong>
    </div>
  </div>
  <div class="expense-card-body">
    <?php if ($doc['status'] === 'void'): ?>
      <p style="color:#b42318;font-weight:700;margin-top:0">VOID - <?= h($doc['void_reason']) ?></p>
    <?php endif; ?>
    <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:16px">
      <img src="<?= h($logo) ?>" alt="" style="height:36px;width:auto;max-width:180px;object-fit:contain">
      <span class="pill"><?= h(invoice_status_label($doc)) ?></span>
    </div>
    <div class="expense-meta">
      <div><span>Payee</span><b><?= h($doc['party_name'] ?? '') ?></b></div>
      <div><span>Category</span><b><?= h($doc['expense_category'] ?: 'Other') ?></b></div>
      <div><span>Paid how</span><b><?= h(payment_methods()[$doc['payment_method'] ?? ''] ?? ($doc['payment_method'] ?: '-')) ?></b></div>
      <div><span>Reference</span><b><?= h($doc['payment_ref'] ?: '-') ?></b></div>
    </div>
    <?php render_line_table($doc, $color, $tint); ?>
    <div class="expense-total"><span>Net</span><span><?= h(money($net, $cur)) ?></span></div>
    <?php if ($vat): ?><div class="expense-total"><span>VAT</span><span><?= h(money($vat, $cur)) ?></span></div><?php endif; ?>
    <div class="expense-total"><span>Total</span><span><?= h(money($total, $cur)) ?></span></div>
    <div class="expense-total"><span>Paid</span><span><?= h(money($paid, $cur)) ?></span></div>
    <div class="expense-total"><span>Balance</span><span><?= h(money($balance, $cur)) ?></span></div>
    <?php if (!empty($doc['notes'])): ?>
      <p class="hint" style="margin-top:14px;white-space:pre-wrap"><?= h($doc['notes']) ?></p>
    <?php endif; ?>
  </div>
</article>
<?php
}

function render_sheet(array $brand, array $doc): void
{
    if ($doc['kind'] === 'expense') {
        render_expense_card($brand, $doc);
        return;
    }

    $color = $brand['brand_color'] ?: '#82B440';
    $tint = hex_tint($color, 0.92);
    $meta = kind_meta($doc['kind']);
    $heading = $doc['kind'] === 'letter' ? letter_heading($doc) : $meta['heading'];
    $items = $doc['items'] ?? [];
    $net = doc_subtotal($items);
    $vat = doc_vat($items, (float) $doc['vat_rate']);
    $total = $net + $vat;
    $comments = $doc['notes'] ?: ($brand['invoice_comments'] ?? '');
    $logo = logo_url();
    $cur = doc_currency($doc);
    ?>
<article class="invoice-sheet" style="--brand: <?= h($color) ?>">
  <header style="display:flex;justify-content:space-between;gap:24px;">
    <div>
      <img src="<?= h($logo) ?>" alt="" style="height:52px;width:auto;max-width:260px;object-fit:contain">
      <div style="margin-top:10px;font-size:11px;line-height:1.45">
        <div><?= h($brand['address']) ?></div>
        <div><?= h($brand['phone']) ?></div>
        <div><?= h($brand['email']) ?></div>
        <div><?= h($brand['website']) ?></div>
        <?php if (!empty($brand['tin'])): ?><div>TIN <?= h($brand['tin']) ?></div><?php endif; ?>
      </div>
    </div>
    <div style="min-width:220px">
      <p style="margin:0;text-align:right;font-size:28px;font-weight:700;color:<?= h($color) ?>"><?= h($heading) ?></p>
      <table class="meta" style="width:100%;margin-top:12px;border-collapse:collapse;font-size:11px">
        <tr><td class="k">DATE</td><td><?= h(format_date($doc['date'])) ?></td></tr>
        <tr><td class="k">No.</td><td><?= h($doc['number']) ?></td></tr>
        <tr><td class="k">CURRENCY</td><td><?= h($cur) ?></td></tr>
        <?php if (!empty($doc['due_date'])): ?>
          <tr><td class="k">DUE DATE</td><td><strong><?= h(format_date($doc['due_date'])) ?></strong></td></tr>
        <?php endif; ?>
      </table>
    </div>
  </header>
  <?php if ($doc['status'] === 'void'): ?>
    <p style="color:#b42318;font-weight:700">VOID - <?= h($doc['void_reason']) ?></p>
  <?php endif; ?>
  <div class="bar" style="background:<?= h($color) ?>;margin-top:16px"><?= $doc['kind'] === 'letter' ? 'TO' : 'BILL TO' ?></div>
  <div style="padding:8px 4px 14px;line-height:1.45">
    <strong><?= h($doc['party_name'] ?? '') ?></strong><br>
    <?= h($doc['party_address'] ?? '') ?><br>
    <?= h($doc['party_phone'] ?? '') ?><br>
    <?= h($doc['party_email'] ?? '') ?>
    <?php if (!empty($doc['party_tin'])): ?><br>TIN <?= h($doc['party_tin']) ?><?php endif; ?>
  </div>
  <?php if ($doc['kind'] === 'letter'): ?>
    <div class="bar" style="background:<?= h($color) ?>">SUBJECT</div>
    <p style="padding:8px 4px;font-weight:700"><?= h($doc['subject']) ?></p>
    <div style="padding:8px 4px 24px;white-space:pre-wrap;line-height:1.65"><?= h($doc['body']) ?></div>
  <?php else: ?>
    <?php render_line_table($doc, $color, $tint); ?>
    <div style="display:flex;gap:24px;margin-top:8px">
      <div style="flex:1">
        <div class="bar" style="background:<?= h($color) ?>">OTHER COMMENTS</div>
        <div style="padding:10px 6px;white-space:pre-wrap"><?= h($comments) ?></div>
      </div>
      <div style="width:240px;padding-top:8px">
        <div style="display:flex;justify-content:space-between;padding:5px 4px"><span>Subtotal</span><span><?= h(money($net, $cur)) ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:5px 4px"><span>Tax<?= $vat ? ' 18%' : '' ?></span><span><?= $vat ? h(money($vat, $cur)) : '' ?></span></div>
        <div style="margin-top:6px;background:<?= h($color) ?>;color:#fff;font-weight:700;display:flex;justify-content:space-between;padding:8px 10px">
          <span>Total</span><span><?= h(money($total, $cur)) ?></span>
        </div>
        <p style="font-size:10px;margin-top:8px"><?= h($brand['payment_note'] ?? '') ?></p>
      </div>
    </div>
  <?php endif; ?>
  <footer style="margin-top:28px;text-align:center;font-size:11px">
    <p>If you have questions, contact <?= h($brand['phone']) ?> or <?= h($brand['email']) ?>.</p>
    <p style="font-weight:700;font-style:italic;font-size:14px">Thank You For Your Business!</p>
  </footer>
</article>
<?php
}