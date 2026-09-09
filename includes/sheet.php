<?php
declare(strict_types=1);

function render_sheet(array $brand, array $doc): void
{
    $color = $brand['brand_color'] ?: '#82B440';
    $tint = hex_tint($color, 0.92);
    $meta = kind_meta($doc['kind']);
    $items = $doc['items'] ?? [];
    $net = doc_subtotal($items);
    $vat = doc_vat($items, (float) $doc['vat_rate']);
    $total = $net + $vat;
    $comments = $doc['notes'] ?: ($brand['invoice_comments'] ?? '');
    $rows = $items;
    while (count($rows) < 6 && $doc['kind'] !== 'letter') {
        $rows[] = null;
    }
    $logo = logo_url();
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
      <p style="margin:0;text-align:right;font-size:36px;font-weight:700;color:<?= h($color) ?>"><?= h($meta['heading']) ?></p>
      <table class="meta" style="width:100%;margin-top:12px;border-collapse:collapse;font-size:11px">
        <tr><td class="k">DATE</td><td><?= h(format_date($doc['date'])) ?></td></tr>
        <tr><td class="k"><?= $doc['kind'] === 'invoice' ? 'INVOICE #' : 'No.' ?></td><td><?= h($doc['number']) ?></td></tr>
        <?php if (!empty($doc['due_date'])): ?>
          <tr><td class="k">DUE DATE</td><td><strong><?= h(format_date($doc['due_date'])) ?></strong></td></tr>
        <?php endif; ?>
      </table>
    </div>
  </header>
  <?php if ($doc['status'] === 'void'): ?>
    <p style="color:#b42318;font-weight:700">VOID — <?= h($doc['void_reason']) ?></p>
  <?php endif; ?>
  <div class="bar" style="background:<?= h($color) ?>;margin-top:16px"><?= $doc['kind'] === 'letter' ? 'TO' : ($doc['kind'] === 'expense' ? 'PAYEE' : 'BILL TO') ?></div>
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
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="background:<?= h($color) ?>;color:#fff">
          <th style="text-align:left;padding:7px 10px">DESCRIPTION</th>
          <th style="width:70px">TAXED</th>
          <th style="text-align:right;padding:7px 10px;width:150px">AMOUNT</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $item): ?>
          <tr style="background:<?= $i % 2 ? h($tint) : '#fff' ?>">
            <td style="padding:8px 10px;height:28px"><?= $item ? h($item['description']) : '&nbsp;' ?></td>
            <td style="text-align:center"><?= $item && !empty($item['taxed']) ? 'Y' : '' ?></td>
            <td style="text-align:right;padding:8px 10px"><?= $item ? h(ugx(line_amount($item))) : '' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div style="display:flex;gap:24px;margin-top:8px">
      <div style="flex:1">
        <div class="bar" style="background:<?= h($color) ?>">OTHER COMMENTS</div>
        <div style="padding:10px 6px;white-space:pre-wrap"><?= h($comments) ?></div>
      </div>
      <div style="width:240px;padding-top:8px">
        <div style="display:flex;justify-content:space-between;padding:5px 4px"><span>Subtotal</span><span><?= h(ugx($net)) ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:5px 4px"><span>Tax<?= $vat ? ' 18%' : '' ?></span><span><?= $vat ? h(ugx($vat)) : '' ?></span></div>
        <div style="margin-top:6px;background:<?= h($color) ?>;color:#fff;font-weight:700;display:flex;justify-content:space-between;padding:8px 10px">
          <span>Total</span><span><?= h(ugx($total)) ?></span>
        </div>
        <p style="font-size:10px;margin-top:8px"><?= h($brand['payment_note'] ?? '') ?></p>
      </div>
    </div>
  <?php endif; ?>
  <?php if (!empty($doc['efris_fdn'])): ?>
    <div style="margin-top:22px;border:1px solid <?= h($color) ?>;padding:10px;font-size:11px">
      <strong style="color:<?= h($color) ?>">EFRIS fiscal mark</strong><br>
      FDN <?= h($doc['efris_fdn']) ?><br>
      Verification <?= h($doc['efris_verification']) ?><br>
      TIN <?= h($brand['tin']) ?><br>
      <span style="color:#666">Demo fiscalisation. Live URA accreditation is a later step.</span>
    </div>
  <?php endif; ?>
  <footer style="margin-top:28px;text-align:center;font-size:11px">
    <p>If you have questions, contact <?= h($brand['phone']) ?> or <?= h($brand['email']) ?>.</p>
    <p style="font-weight:700;font-style:italic;font-size:14px">Thank You For Your Business!</p>
  </footer>
</article>
<?php
}
