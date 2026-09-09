<?php
declare(strict_types=1);

function sheet_data(array $brand, array $doc): array
{
    $items = $doc['items'] ?? [];
    $net = doc_subtotal($items);
    $vat = doc_vat($items, (float) ($doc['vat_rate'] ?? 0));
    $total = $net + $vat;
    $paid = (float) ($doc['paid'] ?? 0);
    $balance = (float) ($doc['balance'] ?? $total);
    $method = (string) ($doc['payment_method'] ?? '');
    return [
        'brand' => $brand,
        'doc' => $doc,
        'color' => $brand['brand_color'] ?: '#82B440',
        'tint' => hex_tint($brand['brand_color'] ?: '#82B440', 0.92),
        'heading' => $doc['kind'] === 'letter' ? letter_heading($doc) : kind_meta($doc['kind'])['heading'],
        'items' => $items,
        'net' => $net,
        'vat' => $vat,
        'total' => $total,
        'paid' => $paid,
        'balance' => $balance,
        'comments' => $doc['notes'] ?: ($brand['invoice_comments'] ?? ''),
        'logo' => logo_url(),
        'cur' => doc_currency($doc),
        'method' => $method,
        'methods' => payment_methods(),
    ];
}

function sheet_tick(string $method, string $want): string
{
    return $method === $want ? 'is-on' : '';
}

function render_line_table(array $doc, string $color, string $tint, array $opts = []): void
{
    $items = $doc['items'] ?? [];
    $rows = $items;
    $min = $opts['min'] ?? 4;
    while (count($rows) < $min) {
        $rows[] = null;
    }
    $cur = doc_currency($doc);
    $serial = !empty($opts['serial']);
    $cls = $opts['class'] ?? '';
    ?>
    <table class="d-lines <?= h($cls) ?>">
      <thead>
        <tr style="background:<?= h($color) ?>;color:#fff">
          <?php if ($serial): ?><th class="c" style="width:44px">No.</th><?php endif; ?>
          <th>Item / description</th>
          <th class="c" style="width:64px">Qty</th>
          <th style="width:70px">Unit</th>
          <th class="r" style="width:110px">Unit price</th>
          <th class="r" style="width:120px">Full price</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $item): ?>
          <tr style="background:<?= $i % 2 ? h($tint) : '#fff' ?>">
            <?php if ($serial): ?><td class="c"><?= $item ? (string) ($i + 1) : '' ?></td><?php endif; ?>
            <td><?= $item ? h($item['description']) : '&nbsp;' ?></td>
            <td class="c"><?= $item ? h(format_qty($item['qty'])) : '' ?></td>
            <td><?= $item ? h((string) $item['unit']) : '' ?></td>
            <td class="r"><?= $item ? h(money($item['rate'], $cur)) : '' ?></td>
            <td class="r"><?= $item ? h(money(line_amount($item), $cur)) : '' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php
}

function render_letter_body(array $doc): void
{
    ?>
    <div class="d-letter">
      <p class="d-letter-sub"><?= h((string) $doc['subject']) ?></p>
      <div class="d-letter-body"><?= h((string) $doc['body']) ?></div>
    </div>
    <?php
}

function render_sheet_folio(array $d): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    ?>
<article class="invoice-sheet sheet-folio" style="--brand: <?= h($d['color']) ?>">
  <header class="d-row">
    <div>
      <img src="<?= h($d['logo']) ?>" alt="" class="d-logo">
      <div class="d-co">
        <div><?= h($brand['address']) ?></div>
        <div><?= h($brand['phone']) ?></div>
        <div><?= h($brand['email']) ?></div>
        <div><?= h($brand['website']) ?></div>
        <?php if (!empty($brand['tin'])): ?><div>TIN <?= h($brand['tin']) ?></div><?php endif; ?>
      </div>
    </div>
    <div class="d-meta-box">
      <p class="d-title" style="color:<?= h($d['color']) ?>"><?= h($d['heading']) ?></p>
      <table class="meta">
        <tr><td class="k">DATE</td><td><?= h(format_date($doc['date'])) ?></td></tr>
        <tr><td class="k">No.</td><td><?= h($doc['number']) ?></td></tr>
        <tr><td class="k">CURRENCY</td><td><?= h($d['cur']) ?></td></tr>
        <?php if (!empty($doc['due_date'])): ?>
          <tr><td class="k">DUE DATE</td><td><strong><?= h(format_date($doc['due_date'])) ?></strong></td></tr>
        <?php endif; ?>
      </table>
    </div>
  </header>
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
  <div class="bar" style="background:<?= h($d['color']) ?>"><?= $doc['kind'] === 'letter' ? 'TO' : 'BILL TO' ?></div>
  <div class="d-party">
    <strong><?= h($doc['party_name'] ?? '') ?></strong><br>
    <?= h($doc['party_address'] ?? '') ?><br>
    <?= h($doc['party_phone'] ?? '') ?><br>
    <?= h($doc['party_email'] ?? '') ?>
    <?php if (!empty($doc['party_tin'])): ?><br>TIN <?= h($doc['party_tin']) ?><?php endif; ?>
  </div>
  <?php if ($doc['kind'] === 'letter'): ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($doc, $d['color'], $d['tint']); ?>
    <div class="d-split">
      <div class="d-notes">
        <div class="bar" style="background:<?= h($d['color']) ?>">OTHER COMMENTS</div>
        <div class="d-notes-body"><?= h($d['comments']) ?></div>
      </div>
      <div class="d-sums">
        <div class="d-sum"><span>Subtotal</span><span><?= h(money($d['net'], $d['cur'])) ?></span></div>
        <div class="d-sum"><span>Tax<?= $d['vat'] ? ' 18%' : '' ?></span><span><?= $d['vat'] ? h(money($d['vat'], $d['cur'])) : '' ?></span></div>
        <div class="d-total" style="background:<?= h($d['color']) ?>"><span>Total</span><span><?= h(money($d['total'], $d['cur'])) ?></span></div>
        <p class="d-payhint"><?= h($brand['payment_note'] ?? '') ?></p>
      </div>
    </div>
  <?php endif; ?>
  <footer class="d-foot">
    <p>If you have questions, contact <?= h($brand['phone']) ?> or <?= h($brand['email']) ?>.</p>
    <p class="thanks">Thank You For Your Business!</p>
  </footer>
</article>
<?php
}

function render_sheet_ledger(array $d): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    $unit = $d['cur'] === 'USD' ? 'Dollars' : 'Shillings';
    ?>
<article class="invoice-sheet sheet-ledger">
  <div class="ledger-top">
    <div class="ledger-brand">
      <img src="<?= h($d['logo']) ?>" alt="" class="d-logo sm">
      <div><?= h($brand['address']) ?><br><?= h($brand['email']) ?><br><?= h($brand['phone']) ?></div>
    </div>
    <h1><?= h($d['heading']) ?></h1>
    <div class="ledger-no">No. <?= h($doc['number']) ?></div>
  </div>
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
  <div class="ledger-grid">
    <label>Date <b><?= h(format_date($doc['date'])) ?></b></label>
    <label class="wide">From <b><?= h($doc['party_name'] ?? '') ?></b></label>
    <div class="ledger-amt"><span><?= h($d['cur']) ?></span><strong><?= h(number_format($d['total'], $d['cur'] === 'USD' ? 2 : 0)) ?></strong></div>
  </div>
  <div class="ledger-words">
    <span>Amount in words</span>
    <b><?= h(amount_in_words($d['total'], $d['cur'])) ?></b>
    <em><?= h($unit) ?></em>
  </div>
  <?php if ($doc['kind'] === 'letter'): ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($doc, '#3d7ea6', '#e7f2f8'); ?>
    <div class="ledger-bottom">
      <table class="ledger-acct">
        <tr><th>Acct.</th><td><?= h($brand['account_number'] ?: '-') ?></td></tr>
        <tr><th>Paid</th><td><?= h(money($d['paid'] ?: ($doc['kind'] === 'receipt' ? $d['total'] : 0), $d['cur'])) ?></td></tr>
        <tr><th>Due</th><td><?= h(money($doc['kind'] === 'receipt' ? 0 : $d['balance'], $d['cur'])) ?></td></tr>
      </table>
      <div class="ledger-pay">
        <?php foreach (['cash' => 'Cash', 'cheque' => 'Cheque', 'mobile-money' => 'Mobile money', 'bank-transfer' => 'Bank'] as $k => $label): ?>
          <span class="tick <?= sheet_tick($d['method'], $k) ?>"><?= h($label) ?></span>
        <?php endforeach; ?>
      </div>
      <div class="ledger-sign">
        <div><span>From</span><b><?= h($brand['name']) ?></b></div>
        <div><span>To</span><b><?= h($doc['party_name'] ?? '') ?></b></div>
        <div><span>By</span><b><?= h($brand['account_name'] ?: 'Accounts') ?></b></div>
      </div>
    </div>
  <?php endif; ?>
</article>
<?php
}

function render_sheet_bill(array $d, string $variant): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    $primary = $variant === 'amber' ? '#1a237e' : '#111111';
    $accent = $variant === 'amber' ? '#f57c00' : '#c62828';
    ?>
<article class="invoice-sheet sheet-bill sheet-<?= h($variant) ?>">
  <div class="bill-corner tl" style="--a:<?= h($accent) ?>;--b:<?= h($primary) ?>"></div>
  <div class="bill-corner br" style="--a:<?= h($primary) ?>;--b:<?= h($accent) ?>"></div>
  <header class="bill-head">
    <img src="<?= h($d['logo']) ?>" alt="" class="d-logo sm">
    <div>
      <h2><?= h($brand['name']) ?></h2>
      <p><?= h($brand['address']) ?> · <?= h($brand['phone']) ?> · <?= h($brand['email']) ?></p>
    </div>
  </header>
  <div class="bill-pill" style="background:<?= h($primary) ?>"><?= h($d['heading']) ?></div>
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
  <div class="bill-who">
    <div>
      <div class="dot">No. <b><?= h($doc['number']) ?></b></div>
      <div class="dot">Name <b><?= h($doc['party_name'] ?? '') ?></b></div>
      <div class="dot">Address <b><?= h($doc['party_address'] ?? '-') ?></b></div>
      <div class="dot">Mobile <b><?= h($doc['party_phone'] ?? '-') ?></b></div>
    </div>
    <div>
      <div class="dot">Date <b><?= h(format_date($doc['date'])) ?></b></div>
      <div class="dot">Email <b><?= h($doc['party_email'] ?? '-') ?></b></div>
      <?php if (!empty($doc['due_date'])): ?><div class="dot">Due <b><?= h(format_date($doc['due_date'])) ?></b></div><?php endif; ?>
      <div class="dot">Currency <b><?= h($d['cur']) ?></b></div>
    </div>
  </div>
  <?php if ($doc['kind'] === 'letter'): ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($doc, $primary, $variant === 'amber' ? '#fff4e5' : '#fdecea', ['serial' => true, 'class' => 'bill-lines']); ?>
    <div class="bill-foot">
      <div class="bill-words"><span>In words</span><b><?= h(amount_in_words($d['total'], $d['cur'])) ?></b></div>
      <div class="bill-sums">
        <div><span>Sub total</span><b><?= h(money($d['net'], $d['cur'])) ?></b></div>
        <?php if ($d['vat']): ?><div><span>VAT 18%</span><b><?= h(money($d['vat'], $d['cur'])) ?></b></div><?php endif; ?>
        <div class="due"><span>Total</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
      </div>
    </div>
    <div class="bill-signs">
      <div>Received by</div>
      <div>Authorized by</div>
    </div>
  <?php endif; ?>
</article>
<?php
}

function render_twin_half(array $d, string $label): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    ?>
    <div class="twin-copy">
      <div class="twin-label"><?= h($label) ?></div>
      <div class="twin-head">
        <img src="<?= h($d['logo']) ?>" alt="" class="d-logo xs">
        <div>
          <strong><?= h($brand['name']) ?></strong>
          <span><?= h($brand['address']) ?></span>
        </div>
      </div>
      <h3><?= h($d['heading']) ?></h3>
      <div class="twin-meta">Receipt No. <b><?= h($doc['number']) ?></b> · Date <b><?= h(format_date($doc['date'])) ?></b></div>
      <div class="twin-fields">
        <div><span>Name</span><b><?= h($doc['party_name'] ?? '') ?></b></div>
        <div><span>Amount</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
        <div><span>Due date</span><b><?= h($doc['due_date'] ? format_date($doc['due_date']) : '-') ?></b></div>
        <div><span>Paid how</span><b><?= h($d['methods'][$d['method']] ?? ($d['method'] ?: '-')) ?></b></div>
      </div>
      <?php if ($doc['kind'] === 'letter'): ?>
        <?php render_letter_body($doc); ?>
      <?php else: ?>
        <?php render_line_table($doc, '#1a237e', '#fff8e1', ['min' => 3, 'class' => 'tiny']); ?>
      <?php endif; ?>
      <div class="twin-pay">
        <?php foreach (['cash' => 'CASH', 'cheque' => 'CHEQUE', 'bank-transfer' => 'BANK', 'mobile-money' => 'MOMO'] as $k => $lab): ?>
          <span class="tick <?= sheet_tick($d['method'], $k) ?>"><?= h($lab) ?></span>
        <?php endforeach; ?>
      </div>
      <div class="twin-sign">Authorized signature</div>
    </div>
    <?php
}

function render_sheet_twin(array $d): void
{
    $doc = $d['doc'];
    ?>
<article class="invoice-sheet sheet-twin">
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
  <div class="twin-wrap">
    <?php render_twin_half($d, 'OFFICE COPY'); ?>
    <div class="twin-perf" aria-hidden="true"></div>
    <?php render_twin_half($d, 'CLIENT COPY'); ?>
  </div>
</article>
<?php
}

function render_sheet_stripe(array $d): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    ?>
<article class="invoice-sheet sheet-stripe">
  <div class="stripe-rail"></div>
  <div class="stripe-inner">
    <div class="stripe-banner"><span><?= h($d['heading']) ?></span></div>
    <p class="stripe-co"><?= h($brand['address']) ?> · <?= h($brand['phone']) ?> · <?= h($brand['email']) ?></p>
    <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
    <div class="stripe-meta">
      <div><span>Issued for</span><b><?= h($doc['party_name'] ?? '') ?></b></div>
      <div><span>Number</span><b><?= h($doc['number']) ?></b></div>
      <div><span>Date</span><b><?= h(format_date($doc['date'])) ?></b></div>
      <div><span>Currency</span><b><?= h($d['cur']) ?></b></div>
    </div>
    <?php if ($doc['kind'] === 'letter'): ?>
      <?php render_letter_body($doc); ?>
    <?php else: ?>
      <p class="stripe-h">Line details</p>
      <?php render_line_table($doc, '#6a1b9a', '#f3e5f5'); ?>
      <div class="stripe-payrow">
        <div>
          <span>Payment method</span>
          <em><?= h($d['methods'][$d['method']] ?? ($d['method'] ?: 'On account')) ?></em>
        </div>
        <div class="stripe-fare">
          <div><span>Subtotal</span><b><?= h(money($d['net'], $d['cur'])) ?></b></div>
          <?php if ($d['vat']): ?><div><span>VAT</span><b><?= h(money($d['vat'], $d['cur'])) ?></b></div><?php endif; ?>
          <div class="stripe-total"><span>Total</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</article>
<?php
}

function render_sheet_estate(array $d): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    ?>
<article class="invoice-sheet sheet-estate">
  <div class="estate-band">
    <img src="<?= h($d['logo']) ?>" alt="" class="d-logo">
    <div>
      <em><?= h($brand['tagline'] ?: 'Estate books') ?></em>
      <h1><?= h($brand['name']) ?></h1>
      <p><?= h($brand['address']) ?> · TIN <?= h($brand['tin'] ?: '-') ?></p>
    </div>
    <div class="estate-no">
      <span><?= h($d['heading']) ?></span>
      <b><?= h($doc['number']) ?></b>
    </div>
  </div>
  <div class="estate-gold"></div>
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
  <div class="estate-who">
    <div>
      <span>Prepared for</span>
      <strong><?= h($doc['party_name'] ?? '') ?></strong>
      <p><?= h($doc['party_address'] ?? '') ?></p>
    </div>
    <div>
      <span>Date</span><b><?= h(format_date($doc['date'])) ?></b>
      <?php if (!empty($doc['due_date'])): ?><span>Due</span><b><?= h(format_date($doc['due_date'])) ?></b><?php endif; ?>
      <span>Currency</span><b><?= h($d['cur']) ?></b>
    </div>
  </div>
  <?php if ($doc['kind'] === 'letter'): ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($doc, '#2e4a28', '#eef3e6'); ?>
    <div class="estate-end">
      <p><?= h($d['comments'] ?: ($brand['payment_note'] ?? '')) ?></p>
      <div class="estate-total">
        <span>Harvest total</span>
        <b><?= h(money($d['total'], $d['cur'])) ?></b>
        <small><?= h(amount_in_words($d['total'], $d['cur'])) ?></small>
      </div>
    </div>
  <?php endif; ?>
</article>
<?php
}

function render_sheet_night(array $d): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    ?>
<article class="invoice-sheet sheet-night">
  <div class="night-sky">
    <div>
      <img src="<?= h($d['logo']) ?>" alt="" class="d-logo invert">
      <p><?= h($brand['name']) ?></p>
    </div>
    <h1><?= h($d['heading']) ?></h1>
  </div>
  <div class="night-copper"></div>
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
  <div class="night-body">
    <div class="night-pair">
      <div>
        <span>Client</span>
        <strong><?= h($doc['party_name'] ?? '') ?></strong>
        <p><?= h($doc['party_address'] ?? '') ?><br><?= h($doc['party_email'] ?? '') ?></p>
      </div>
      <div>
        <span>Reference</span>
        <strong><?= h($doc['number']) ?></strong>
        <p><?= h(format_date($doc['date'])) ?> · <?= h($d['cur']) ?>
          <?php if (!empty($doc['due_date'])): ?><br>Due <?= h(format_date($doc['due_date'])) ?><?php endif; ?>
        </p>
      </div>
    </div>
    <?php if ($doc['kind'] === 'letter'): ?>
      <?php render_letter_body($doc); ?>
    <?php else: ?>
      <?php render_line_table($doc, '#1b2838', '#f7f4ee', ['serial' => true]); ?>
      <div class="night-total">
        <div>
          <span>In words</span>
          <p><?= h(amount_in_words($d['total'], $d['cur'])) ?></p>
        </div>
        <b><?= h(money($d['total'], $d['cur'])) ?></b>
      </div>
    <?php endif; ?>
    <p class="night-foot"><?= h($brand['phone']) ?> · <?= h($brand['email']) ?> · <?= h($brand['website']) ?></p>
  </div>
</article>
<?php
}

function render_expense_card(array $brand, array $doc): void
{
    render_sheet($brand, $doc);
}

function render_sheet(array $brand, array $doc): void
{
    $d = sheet_data($brand, $doc);
    match (doc_template_key($doc)) {
        'ledger' => render_sheet_ledger($d),
        'crimson' => render_sheet_bill($d, 'crimson'),
        'amber' => render_sheet_bill($d, 'amber'),
        'twin' => render_sheet_twin($d),
        'stripe' => render_sheet_stripe($d),
        'estate' => render_sheet_estate($d),
        'night' => render_sheet_night($d),
        default => render_sheet_folio($d),
    };
}
