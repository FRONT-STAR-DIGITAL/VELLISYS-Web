<?php
declare(strict_types=1);

function sheet_data(array $brand, array $doc): array
{
    $items = $doc['items'] ?? [];
    $net = doc_subtotal($items);
    $vat = doc_vat($items, (float) ($doc['vat_rate'] ?? 0));
    $total = $net + $vat;
    $palette = brand_palette($brand);
    $paid = (float) ($doc['paid'] ?? 0);
    $balance = (float) ($doc['balance'] ?? $total);
    $method = (string) ($doc['payment_method'] ?? '');
    $settlement = $doc['settlement'] ?? receipt_settlement($doc);
    return [
        'brand' => $brand,
        'doc' => $doc,
        'palette' => $palette,
        'color' => $palette['primary'],
        'accent' => $palette['accent'],
        'deep' => $palette['deep'],
        'tint' => $palette['tint'],
        'accent_tint' => $palette['accent_tint'],
        'vars' => brand_css_vars($brand),
        'heading' => kind_is_stationery($doc['kind'] ?? '')
            ? (($doc['kind'] ?? '') === 'custom' ? kind_meta('custom')['heading'] : '')
            : kind_meta($doc['kind'])['heading'],
        'items' => $items,
        'net' => $net,
        'vat' => $vat,
        'total' => $total,
        'paid' => $paid,
        'balance' => $balance,
        'show_vat' => doc_shows_vat($doc),
        'settlement' => $settlement,
        'comments' => $doc['notes'] ?: ($brand['invoice_comments'] ?? ''),
        'logo' => logo_url($brand),
        'cur' => doc_currency($doc),
        'home_cur' => default_currency(),
        'alt_cur' => other_currency(doc_currency($doc)),
        'fx_rate' => fx_home_per_usd(),
        'method' => $method,
        'methods' => payment_methods(),
    ];
}

function render_settlement(array $d): void
{
    if (($d['doc']['kind'] ?? '') !== 'receipt') {
        return;
    }
    $s = $d['settlement'] ?? [];
    $received = (float) ($s['received'] ?? $d['total']);
    $due = (float) ($s['balance'] ?? 0);
    $open = $due > 0.009;
    ?>
    <div class="d-rd">
      <div class="d-rd-row"><span>RECEIVED:</span><b><?= h(money($received, $d['cur'])) ?></b></div>
      <div class="d-rd-row<?= $open ? ' is-open' : '' ?>"><span>DUE:</span><b><?= h(money($due, $d['cur'])) ?></b></div>
    </div>
    <?php
}

function render_fx_equiv(array $d, $amount = null): void
{
    $amt = $amount === null ? (float) $d['total'] : (float) $amount;
    $alt = $d['alt_cur'] ?? other_currency($d['cur']);
    $home = $d['home_cur'] ?? default_currency();
    $conv = convert_money($amt, $d['cur'], $alt, $d['fx_rate'] ?? null, $home);
    $rate = (float) ($d['fx_rate'] ?? fx_home_per_usd());
    ?>
    <div class="d-fx"><?= h(money($conv, $alt)) ?> <em>at <?= h(fx_rate_label($rate, $home)) ?></em></div>
    <?php
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
    $qtyOnly = ($doc['kind'] ?? '') === 'delivery';
    $showVat = !$qtyOnly && doc_shows_vat($doc);
    ?>
    <table class="d-lines <?= h($cls) ?>">
      <thead>
        <tr style="background:<?= h($color) ?>;color:#fff">
          <?php if ($serial): ?><th class="c" style="width:44px">No.</th><?php endif; ?>
          <th style="width:22%">Item</th>
          <th>Description</th>
          <th class="c" style="width:64px">Qty</th>
          <?php if (!$qtyOnly): ?>
            <th class="r" style="width:110px">Unit price</th>
            <th class="r" style="width:120px">Total Amt</th>
            <?php if ($showVat): ?><th class="c" style="width:44px">VAT</th><?php endif; ?>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $item): ?>
          <tr style="background:<?= $i % 2 ? h($tint) : '#fff' ?>">
            <?php if ($serial): ?><td class="c"><?= $item ? (string) ($i + 1) : '' ?></td><?php endif; ?>
            <td class="item"><?= $item && line_item_name($item) !== '' ? h(line_item_name($item)) : ($item ? '&nbsp;' : '&nbsp;') ?></td>
            <td class="desc"><?= $item && line_item_description($item) !== '' ? nl2br(h(line_item_description($item))) : '&nbsp;' ?></td>
            <td class="c"><?= $item ? h(format_qty($item['qty'])) : '' ?></td>
            <?php if (!$qtyOnly): ?>
              <td class="r"><?= $item ? h(money($item['rate'], $cur)) : '' ?></td>
              <td class="r"><?= $item ? h(money(line_amount($item), $cur)) : '' ?></td>
              <?php if ($showVat): ?><td class="c"><?= $item ? (!empty($item['taxed']) ? 'Y' : 'N') : '' ?></td><?php endif; ?>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php
}

function render_party_contact(array $doc): void
{
    $phones = array_values(array_filter([
        trim((string) ($doc['party_phone'] ?? '')),
        trim((string) ($doc['party_phone2'] ?? '')),
    ], static fn ($v) => $v !== ''));
    $place = party_place_line($doc);
    $addr = trim((string) ($doc['party_address'] ?? ''));
    $attn = trim((string) ($doc['party_contact'] ?? ''));
    $email = trim((string) ($doc['party_email'] ?? ''));
    ?>
    <div class="d-party-block">
      <strong><?= h((string) ($doc['party_name'] ?? '')) ?></strong>
      <?php if ($attn !== ''): ?><div>Attn: <?= h($attn) ?></div><?php endif; ?>
      <?php if ($addr !== ''): ?><div class="d-party-addr"><?= nl2br(h($addr)) ?></div><?php endif; ?>
      <?php if ($place !== ''): ?><div><?= h($place) ?></div><?php endif; ?>
      <?php if ($phones): ?><div><?= h(implode(' · ', $phones)) ?></div><?php endif; ?>
      <?php if ($email !== ''): ?><div><?= h($email) ?></div><?php endif; ?>
      <?php if (!empty($doc['party_tin'])): ?><div>TIN <?= h((string) $doc['party_tin']) ?></div><?php endif; ?>
    </div>
    <?php
}

function format_letter_html(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = trim($body);
    if ($body === '') {
        return '';
    }
    $parts = preg_split("/\n{2,}/", $body) ?: [$body];
    $html = '';
    foreach ($parts as $part) {
        $html .= '<p class="corr-p">' . nl2br(h($part), false) . '</p>';
    }
    return $html;
}

function watermark_markup(array $d): string
{
    $src = (string) ($d['logo'] ?? '');
    if ($src === '') {
        return '';
    }
    return '<img class="d-watermark" src="' . h($src) . '" alt="">';
}

function render_logo_watermark(array $d): void
{
    echo watermark_markup($d);
}

function sheet_uses_watermark(?array $doc = null): bool
{
    $key = doc_template_key($doc);
    if ($key === 'mark' || $key === 'bond') {
        return true;
    }
    return (int) (branding()['logo_bg'] ?? 0) === 1;
}

function inject_sheet_watermark(string $html, array $d, array $doc): string
{
    if (!sheet_uses_watermark($doc)) {
        return $html;
    }
    $html = preg_replace('/class="([^"]*invoice-sheet[^"]*)"/', 'class="$1 has-wm"', $html, 1) ?? $html;
    if (!str_contains($html, 'd-watermark')) {
        $wm = watermark_markup($d);
        if ($wm !== '') {
            $html = preg_replace('/(<article\b[^>]*>)/', '$1' . $wm, $html, 1) ?? $html;
        }
    }
    return $html;
}

function render_letter_body(array $doc): void
{
    ?>
    <div class="d-letter">
      <?php if (trim((string) $doc['subject']) !== ''): ?>
        <p class="d-letter-sub"><?= h((string) $doc['subject']) ?></p>
      <?php endif; ?>
      <div class="d-letter-body"><?= format_letter_html((string) $doc['body']) ?></div>
    </div>
    <?php
}

function render_print_document_page(array $doc, bool $pdf = false): void
{
    $brand = branding();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= h((string) $doc['number']) ?></title>
  <?php product_icons(); ?>
  <?php folio_css_links(true, true); ?>
  <?php folio_font_links(); ?>
  <style>
    :root { <?= brand_css_vars() ?> }
    @page { size: A4; margin: 0; }
  </style>
</head>
<body class="print-body<?= $pdf ? ' print-pdf' : '' ?>">
  <?php if ($pdf): ?>
    <div class="pdf-bar">
      <p>This is the branded sheet. In the print dialog choose <strong>Save as PDF</strong> (or Microsoft Print to PDF).</p>
      <button class="btn sm" type="button" onclick="window.print()"><?= icon('file', 15) ?> Save PDF</button>
    </div>
  <?php endif; ?>
  <?php render_sheet($brand, $doc); ?>
  <script src="<?= h(asset('js/print-sheet.js')) ?>"></script>
</body>
</html>
    <?php
}

function render_sheet_correspondence(array $d): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    $isCustom = ($doc['kind'] ?? '') === 'custom';
    $custom = $isCustom ? company_custom_doc() : default_custom_doc();
    $values = is_array($doc['custom_values'] ?? null) ? $doc['custom_values'] : [];
    $heading = trim((string) $d['heading']);
    $subject = trim((string) ($doc['subject'] ?? ''));
    $body = (string) ($doc['body'] ?? '');
    $showBody = !$isCustom || !empty($custom['has_body']) || trim($body) !== '';
    $wm = sheet_uses_watermark($doc);
    ?>
<article class="invoice-sheet sheet-corr<?= $wm && in_array(doc_template_key($doc), ['mark', 'bond'], true) ? ' sheet-' . h(doc_template_key($doc)) : '' ?>" style="<?= h($d['vars']) ?>">
  <header class="corr-head">
    <div class="corr-brand">
      <img src="<?= h($d['logo']) ?>" alt="" class="d-logo">
      <div class="corr-co">
        <strong><?= h($brand['name']) ?></strong>
        <?php if (!empty($brand['tagline'])): ?><div><?= h($brand['tagline']) ?></div><?php endif; ?>
        <div><?= h($brand['address']) ?></div>
        <?php if (!empty($brand['city'])): ?><div><?= h($brand['city']) ?></div><?php endif; ?>
        <div><?= h($brand['phone']) ?></div>
        <div><?= h($brand['email']) ?></div>
        <?php if (!empty($brand['website'])): ?><div><?= h($brand['website']) ?></div><?php endif; ?>
        <?php if (!empty($brand['tin'])): ?><div>TIN <?= h($brand['tin']) ?></div><?php endif; ?>
      </div>
    </div>
    <div class="corr-meta">
      <?php if ($heading !== ''): ?><p class="corr-kind" style="color:<?= h($d['color']) ?>"><?= h($heading) ?></p><?php endif; ?>
      <div><span>Date</span><b><?= h(format_date($doc['date'])) ?></b></div>
      <div><span>Ref</span><b><?= h($doc['number']) ?></b></div>
    </div>
  </header>
  <hr class="corr-rule" style="border-color:<?= h($d['color']) ?>">
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
  <div class="corr-to">
    <span>To</span>
    <?php render_party_contact($doc); ?>
  </div>
  <?php if ($subject !== ''): ?>
    <p class="corr-subject"><span>Subject:</span> <?= h($subject) ?></p>
  <?php endif; ?>
  <?php if ($isCustom && $custom['fields']): ?>
    <dl class="corr-fields">
      <?php foreach ($custom['fields'] as $field):
          $val = trim((string) ($values[$field['key']] ?? ''));
          if ($val === '') {
              continue;
          }
          ?>
        <div>
          <dt><?= h($field['label']) ?></dt>
          <dd><?= nl2br(h($val)) ?></dd>
        </div>
      <?php endforeach; ?>
    </dl>
  <?php endif; ?>
  <?php if ($showBody): ?>
    <div class="corr-body"><?= format_letter_html($body) ?></div>
  <?php endif; ?>
  <footer class="corr-sign">
    <p>Yours faithfully,</p>
    <p><strong><?= h($brand['name']) ?></strong></p>
  </footer>
</article>
<?php
}

function render_sheet_folio(array $d): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    ?>
<article class="invoice-sheet sheet-folio" style="<?= h($d['vars']) ?>">
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
        <?php if (kind_shows_money($doc['kind'] ?? '')): ?>
        <tr><td class="k">CURRENCY</td><td><?= h($d['cur']) ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($doc['due_date'])): ?>
          <tr><td class="k">DUE DATE</td><td><strong><?= h(format_date($doc['due_date'])) ?></strong></td></tr>
        <?php endif; ?>
      </table>
    </div>
  </header>
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
  <div class="bar" style="background:<?= h($d['color']) ?>"><?= kind_shows_money($doc['kind'] ?? '') ? 'BILL TO' : 'TO' ?></div>
  <div class="d-party">
    <?php render_party_contact($doc); ?>
  </div>
  <?php if (kind_is_stationery($doc['kind'] ?? '')): ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($doc, $d['color'], $d['tint']); ?>
    <div class="d-split">
      <div class="d-notes">
        <div class="bar" style="background:<?= h($d['color']) ?>">OTHER COMMENTS</div>
        <div class="d-notes-body"><?= h($d['comments']) ?></div>
      </div>
      <?php if (kind_shows_money($doc['kind'] ?? '')): ?>
      <div class="d-sums">
        <div class="d-sum"><span>Subtotal</span><span><?= h(money($d['net'], $d['cur'])) ?></span></div>
        <?php if (!empty($d['show_vat'])): ?>
          <div class="d-sum"><span>VAT 18%</span><span><?= h(money($d['vat'], $d['cur'])) ?></span></div>
        <?php endif; ?>
        <div class="d-total"><span>Total</span><span><?= h(money($d['total'], $d['cur'])) ?></span></div>
        <?php render_fx_equiv($d); ?>
        <?php render_settlement($d); ?>
        <p class="d-payhint"><?= h($brand['payment_note'] ?? '') ?></p>
      </div>
      <?php endif; ?>
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
    $unit = ucfirst(currency_unit_name($d['cur']));
    ?>
<article class="invoice-sheet sheet-ledger" style="<?= h($d['vars']) ?>">
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
    <label class="wide">From <b><?php render_party_contact($doc); ?></b></label>
    <?php if (kind_shows_money($doc['kind'] ?? '')): ?>
    <div class="ledger-amt"><span><?= h($d['cur']) ?></span><strong><?= h(number_format($d['total'], currency_decimals($d['cur']))) ?></strong></div>
    <?php endif; ?>
  </div>
  <?php if (kind_shows_money($doc['kind'] ?? '')): ?>
    <div style="text-align:right;margin:-4px 0 10px"><?php render_fx_equiv($d); ?></div>
  <?php endif; ?>
  <?php if (kind_shows_money($doc['kind'] ?? '')): ?>
  <div class="ledger-words">
    <span>Amount in words</span>
    <b><?= h(amount_in_words($d['total'], $d['cur'])) ?></b>
    <em><?= h($unit) ?></em>
  </div>
  <?php endif; ?>
  <?php if (kind_is_stationery($doc['kind'] ?? '')): ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($doc, $d['color'], $d['tint']); ?>
    <div class="ledger-bottom">
      <table class="ledger-acct">
        <tr><th>Acct.</th><td><?= h($brand['account_number'] ?: '-') ?></td></tr>
        <?php if ($doc['kind'] === 'receipt'): ?>
          <tr><th>RECEIVED:</th><td><?= h(money($d['settlement']['received'] ?? $d['total'], $d['cur'])) ?></td></tr>
          <tr><th>DUE:</th><td><?= h(money($d['settlement']['balance'] ?? 0, $d['cur'])) ?></td></tr>
        <?php endif; ?>
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
    $primary = $variant === 'amber' ? $d['accent'] : $d['color'];
    $deep = $d['deep'];
    ?>
<article class="invoice-sheet sheet-bill sheet-<?= h($variant) ?>" style="<?= h($d['vars']) ?>">
  <div class="bill-corner tl" style="--a:<?= h($primary) ?>;--b:<?= h($deep) ?>"></div>
  <div class="bill-corner br" style="--a:<?= h($deep) ?>;--b:<?= h($primary) ?>"></div>
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
    <?php render_line_table($doc, $primary, $variant === 'amber' ? $d['accent_tint'] : $d['tint'], ['serial' => true, 'class' => 'bill-lines']); ?>
    <div class="bill-foot">
      <div class="bill-words"><span>In words</span><b><?= h(amount_in_words($d['total'], $d['cur'])) ?></b></div>
      <div class="bill-sums">
        <div><span>Sub total</span><b><?= h(money($d['net'], $d['cur'])) ?></b></div>
        <?php if (!empty($d['show_vat'])): ?><div><span>VAT 18%</span><b><?= h(money($d['vat'], $d['cur'])) ?></b></div><?php endif; ?>
        <div class="due"><span>Total</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
        <?php render_fx_equiv($d); ?>
        <?php render_settlement($d); ?>
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
        <?php render_fx_equiv($d); ?>
        <div><span>Paid how</span><b><?= h($d['methods'][$d['method']] ?? ($d['method'] ?: '-')) ?></b></div>
      </div>
      <?php if ($doc['kind'] === 'letter'): ?>
        <?php render_letter_body($doc); ?>
      <?php else: ?>
        <?php render_line_table($doc, $d['deep'], $d['accent_tint'], ['min' => 3, 'class' => 'tiny']); ?>
        <?php render_settlement($d); ?>
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
<article class="invoice-sheet sheet-twin" style="<?= h($d['vars']) ?>">
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
<article class="invoice-sheet sheet-stripe" style="<?= h($d['vars']) ?>">
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
      <?php render_line_table($doc, $d['deep'], $d['accent_tint']); ?>
      <div class="stripe-payrow">
        <div>
          <span>Payment method</span>
          <em><?= h($d['methods'][$d['method']] ?? ($d['method'] ?: 'On account')) ?></em>
          <?php render_settlement($d); ?>
        </div>
        <div class="stripe-fare">
          <div><span>Subtotal</span><b><?= h(money($d['net'], $d['cur'])) ?></b></div>
          <?php if (!empty($d['show_vat'])): ?><div><span>VAT</span><b><?= h(money($d['vat'], $d['cur'])) ?></b></div><?php endif; ?>
          <div class="stripe-total"><span>Total</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
          <?php render_fx_equiv($d); ?>
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
<article class="invoice-sheet sheet-estate" style="<?= h($d['vars']) ?>">
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
    <?php render_line_table($doc, $d['deep'], $d['tint']); ?>
    <div class="estate-end">
      <div>
        <p><?= h($d['comments'] ?: ($brand['payment_note'] ?? '')) ?></p>
        <?php render_settlement($d); ?>
      </div>
      <div class="estate-total">
        <span>Harvest total</span>
        <b><?= h(money($d['total'], $d['cur'])) ?></b>
        <?php render_fx_equiv($d); ?>
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
<article class="invoice-sheet sheet-night" style="<?= h($d['vars']) ?>">
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
      <?php render_line_table($doc, $d['deep'], '#f7f4ee', ['serial' => true]); ?>
      <div class="night-total">
        <div>
          <span>In words</span>
          <p><?= h(amount_in_words($d['total'], $d['cur'])) ?></p>
          <?php render_settlement($d); ?>
        </div>
        <div>
          <b><?= h(money($d['total'], $d['cur'])) ?></b>
          <?php render_fx_equiv($d); ?>
        </div>
      </div>
    <?php endif; ?>
    <p class="night-foot"><?= h($brand['phone']) ?> · <?= h($brand['email']) ?> · <?= h($brand['website']) ?></p>
  </div>
</article>
<?php
}

function render_sheet_atelier(array $d): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    ?>
<article class="invoice-sheet sheet-atelier" style="<?= h($d['vars']) ?>">
  <header class="atelier-head">
    <div class="atelier-brand">
      <img src="<?= h($d['logo']) ?>" alt="" class="d-logo">
      <p class="atelier-kicker"><?= h($brand['name']) ?></p>
      <p><?= h($brand['address']) ?><?= !empty($brand['tin']) ? ' · TIN ' . h($brand['tin']) : '' ?></p>
    </div>
    <div class="atelier-meta">
      <em><?= h($d['heading']) ?></em>
      <strong><?= h($doc['number']) ?></strong>
      <span>Issued <?= h(format_date($doc['date'])) ?></span>
      <?php if (!empty($doc['due_date'])): ?><span>Due <?= h(format_date($doc['due_date'])) ?></span><?php endif; ?>
      <span><?= h($d['cur']) ?></span>
    </div>
  </header>
  <hr class="atelier-rule">
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
  <div class="atelier-party">
    <span><?= $doc['kind'] === 'letter' ? 'To' : 'Prepared for' ?></span>
    <strong><?= h($doc['party_name'] ?? '') ?></strong>
    <p><?= h($doc['party_address'] ?? '') ?><?= !empty($doc['party_email']) ? ' · ' . h($doc['party_email']) : '' ?></p>
  </div>
  <?php if ($doc['kind'] === 'letter'): ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($doc, $d['deep'], $d['tint']); ?>
    <div class="atelier-end">
      <div class="atelier-note">
        <span>Notes</span>
        <p><?= h($d['comments'] ?: ($brand['payment_note'] ?? '')) ?></p>
      </div>
      <div class="atelier-sums">
        <div><span>Subtotal</span><b><?= h(money($d['net'], $d['cur'])) ?></b></div>
        <?php if (!empty($d['show_vat'])): ?><div><span>VAT 18%</span><b><?= h(money($d['vat'], $d['cur'])) ?></b></div><?php endif; ?>
        <div class="atelier-total"><span>Amount due</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
        <?php render_fx_equiv($d); ?>
        <?php render_settlement($d); ?>
      </div>
    </div>
  <?php endif; ?>
  <footer class="atelier-foot">
    <?= h($brand['phone']) ?> · <?= h($brand['email']) ?> · <?= h($brand['website']) ?>
  </footer>
</article>
<?php
}

function render_sheet_seal(array $d): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    ?>
<article class="invoice-sheet sheet-seal" style="<?= h($d['vars']) ?>">
  <header class="seal-head">
    <img src="<?= h($d['logo']) ?>" alt="" class="d-logo">
    <h1><?= h($brand['name']) ?></h1>
    <p><?= h($brand['address']) ?> · <?= h($brand['phone']) ?> · <?= h($brand['email']) ?></p>
    <div class="seal-title">
      <i></i>
      <strong><?= h($d['heading']) ?></strong>
      <i></i>
    </div>
  </header>
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
  <div class="seal-meta">
    <div><span>Reference</span><b><?= h($doc['number']) ?></b></div>
    <div><span>Date</span><b><?= h(format_date($doc['date'])) ?></b></div>
    <?php if (!empty($doc['due_date'])): ?><div><span>Due</span><b><?= h(format_date($doc['due_date'])) ?></b></div><?php endif; ?>
    <div><span>Currency</span><b><?= h($d['cur']) ?></b></div>
  </div>
  <p class="seal-for"><span><?= $doc['kind'] === 'letter' ? 'Addressed to' : 'In account with' ?></span> <strong><?= h($doc['party_name'] ?? '') ?></strong></p>
  <?php if ($doc['kind'] === 'letter'): ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($doc, $d['deep'], $d['tint']); ?>
    <div class="seal-end">
      <p><?= h($d['comments'] ?: ($brand['payment_note'] ?? '')) ?></p>
      <aside>
        <div><span>Subtotal</span><b><?= h(money($d['net'], $d['cur'])) ?></b></div>
        <?php if (!empty($d['show_vat'])): ?><div><span>VAT 18%</span><b><?= h(money($d['vat'], $d['cur'])) ?></b></div><?php endif; ?>
        <div class="seal-due"><span>Total</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
        <?php render_fx_equiv($d); ?>
        <?php render_settlement($d); ?>
      </aside>
    </div>
  <?php endif; ?>
  <footer class="seal-sign">
    <div>For and on behalf of <?= h($brand['name']) ?></div>
    <div>Authorised</div>
  </footer>
</article>
<?php
}

function render_sheet_mark(array $d): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    ?>
<article class="invoice-sheet sheet-mark" style="<?= h($d['vars']) ?>">
  <header class="mark-head">
    <div>
      <img src="<?= h($d['logo']) ?>" alt="" class="d-logo">
      <div class="d-co">
        <strong><?= h($brand['name']) ?></strong>
        <div><?= h($brand['address']) ?></div>
        <div><?= h($brand['phone']) ?> · <?= h($brand['email']) ?></div>
        <?php if (!empty($brand['tin'])): ?><div>TIN <?= h($brand['tin']) ?></div><?php endif; ?>
      </div>
    </div>
    <div class="mark-meta">
      <p class="mark-kind"><?= h($d['heading']) ?></p>
      <div><span>Date</span><b><?= h(format_date($doc['date'])) ?></b></div>
      <div><span>No.</span><b><?= h($doc['number']) ?></b></div>
      <div><span>Currency</span><b><?= h($d['cur']) ?></b></div>
    </div>
  </header>
  <hr class="mark-rule">
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
  <div class="mark-who">
    <span>To</span>
    <?php render_party_contact($doc); ?>
  </div>
  <?php if ($doc['kind'] === 'letter'): ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($doc, $d['color'], $d['tint']); ?>
    <div class="d-split">
      <div class="d-notes">
        <div class="d-notes-body"><?= h($d['comments'] ?: ($brand['payment_note'] ?? '')) ?></div>
      </div>
      <div class="d-sums">
        <div class="d-sum"><span>Subtotal</span><b><?= h(money($d['net'], $d['cur'])) ?></b></div>
        <?php if (!empty($d['show_vat'])): ?><div class="d-sum"><span>VAT 18%</span><b><?= h(money($d['vat'], $d['cur'])) ?></b></div><?php endif; ?>
        <div class="d-total"><span>Total</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
        <?php render_fx_equiv($d); ?>
        <?php render_settlement($d); ?>
      </div>
    </div>
  <?php endif; ?>
</article>
<?php
}

function render_sheet_bond(array $d): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    ?>
<article class="invoice-sheet sheet-bond" style="<?= h($d['vars']) ?>">
  <header class="bond-head">
    <div class="bond-brand">
      <img src="<?= h($d['logo']) ?>" alt="" class="d-logo">
      <div>
        <strong><?= h($brand['name']) ?></strong>
        <p><?= h($brand['address']) ?></p>
        <p><?= h($brand['phone']) ?> · <?= h($brand['email']) ?></p>
      </div>
    </div>
    <div class="bond-stamp">
      <em><?= h($d['heading']) ?></em>
      <b><?= h($doc['number']) ?></b>
      <span><?= h(format_date($doc['date'])) ?></span>
    </div>
  </header>
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
  <div class="bond-who">
    <span>In account with</span>
    <strong><?= h($doc['party_name'] ?? '') ?></strong>
    <p><?= h($doc['party_address'] ?? '') ?></p>
  </div>
  <?php if ($doc['kind'] === 'letter'): ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($doc, $d['deep'], '#fff8ee'); ?>
    <div class="bond-end">
      <p><?= h($d['comments'] ?: ($brand['payment_note'] ?? '')) ?></p>
      <aside>
        <div><span>Subtotal</span><b><?= h(money($d['net'], $d['cur'])) ?></b></div>
        <?php if (!empty($d['show_vat'])): ?><div><span>VAT 18%</span><b><?= h(money($d['vat'], $d['cur'])) ?></b></div><?php endif; ?>
        <div class="bond-due"><span>Amount due</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
        <?php render_fx_equiv($d); ?>
        <?php render_settlement($d); ?>
      </aside>
    </div>
  <?php endif; ?>
  <footer class="bond-foot"><?= h($brand['website'] ?: $brand['email']) ?></footer>
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
    ob_start();
    if (kind_is_stationery($doc['kind'] ?? '')) {
        render_sheet_correspondence($d);
    } else {
        match (doc_template_key($doc)) {
            'ledger' => render_sheet_ledger($d),
            'crimson' => render_sheet_bill($d, 'crimson'),
            'amber' => render_sheet_bill($d, 'amber'),
            'twin' => render_sheet_twin($d),
            'stripe' => render_sheet_stripe($d),
            'estate' => render_sheet_estate($d),
            'night' => render_sheet_night($d),
            'atelier' => render_sheet_atelier($d),
            'seal' => render_sheet_seal($d),
            'mark' => render_sheet_mark($d),
            'bond' => render_sheet_bond($d),
            default => render_sheet_folio($d),
        };
    }
    echo inject_sheet_watermark((string) ob_get_clean(), $d, $doc);
}
