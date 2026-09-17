<?php
declare(strict_types=1);

function sheet_data(array $brand, array $doc): array
{
    $lh = decode_letterhead($doc['letterhead'] ?? '');
    if (function_exists('apply_branch_to_brand')) {
        $brand = apply_branch_to_brand($brand, $doc['branch_id'] ?? 0);
    }
    if ($lh) {
        foreach ($lh as $k => $v) {
            if (is_string($v) && trim($v) !== '') {
                $brand[$k] = $v;
            }
        }
    }
    $items = $doc['items'] ?? [];
    $net = doc_subtotal($items);
    $vat = doc_vat($items, (float) ($doc['vat_rate'] ?? 0));
    $total = $net + $vat;
    $palette = brand_palette($brand);
    $paid = (float) ($doc['paid'] ?? 0);
    $balance = (float) ($doc['balance'] ?? $total);
    $method = (string) ($doc['payment_method'] ?? '');
    $settlement = $doc['settlement'] ?? receipt_settlement($doc);
    $heading = '';
    if (($doc['kind'] ?? '') === 'custom') {
        $heading = kind_meta('custom')['heading'];
    } elseif (($doc['kind'] ?? '') === 'letter') {
        $heading = letter_heading($doc);
    } else {
        $heading = kind_meta($doc['kind'])['heading'];
    }
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
        'heading' => $heading,
        'items' => $items,
        'net' => $net,
        'vat' => $vat,
        'total' => $total,
        'paid' => $paid,
        'balance' => $balance,
        'show_vat' => doc_shows_vat($doc),
        'tax_name' => company_tax_name($brand),
        'tax_label' => tax_rate_label((float) ($doc['vat_rate'] ?? 0), $brand),
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
      <div class="d-rd-row">
        <span>Amount received</span>
        <b><?= h(money($received, $d['cur'])) ?></b>
      </div>
      <div class="d-rd-row<?= $open ? ' is-open' : '' ?>">
        <span>Amount due</span>
        <b><?= h(money($due, $d['cur'])) ?></b>
      </div>
    </div>
    <?php
}

function sheet_shows_money(array $d): bool
{
    return kind_shows_money((string) ($d['doc']['kind'] ?? ''));
}

function render_fx_equiv(array $d, $amount = null): void
{
    if (!sheet_shows_fx($d)) {
        return;
    }
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
    $qtyOnly = in_array(($doc['kind'] ?? ''), ['delivery', 'return_note'], true);
    $showVat = !$qtyOnly && doc_shows_vat($doc);
    $taxName = trim((string) ($opts['tax_name'] ?? ''));
    if ($taxName === '') {
        $taxName = company_tax_name();
    }
    $compact = !empty($opts['compact']);
    ?>
    <table class="d-lines <?= h($cls) ?>">
      <thead>
        <tr style="background:<?= h($color) ?>;color:#fff">
          <?php if ($compact): ?>
            <th>Details</th>
            <th class="c" style="width:12%">Qty</th>
            <?php if (!$qtyOnly): ?><th class="r" style="width:28%">Amount</th><?php endif; ?>
          <?php else: ?>
            <?php if ($serial): ?><th class="c" style="width:44px">No.</th><?php endif; ?>
            <th style="width:22%">Item</th>
            <th>Description</th>
            <th class="c" style="width:64px">Qty</th>
            <?php if (!$qtyOnly): ?>
              <th class="r" style="width:110px">Unit price</th>
              <th class="r" style="width:120px">Total Amt</th>
              <?php if ($showVat): ?><th class="c" style="width:44px"><?= h($taxName) ?></th><?php endif; ?>
            <?php endif; ?>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $item): ?>
          <tr style="background:<?= $i % 2 ? h($tint) : '#fff' ?>">
            <?php if ($compact): ?>
              <td class="desc"><?php
                if (!$item) {
                    echo '&nbsp;';
                } else {
                    $name = line_item_name($item);
                    $desc = line_item_description($item);
                    $parts = [];
                    if ($name !== '') {
                        $parts[] = '<span class="item">' . h($name) . '</span>';
                    }
                    if ($desc !== '') {
                        $parts[] = '<span class="twin-desc">' . nl2br(h($desc), false) . '</span>';
                    }
                    echo implode(' ', $parts);
                    if ($showVat && !empty($item['taxed'])) {
                        echo '<span class="twin-vat"> ' . h($taxName) . '</span>';
                    }
                }
              ?></td>
              <td class="c"><?= $item ? h(format_qty($item['qty'])) : '' ?></td>
              <?php if (!$qtyOnly): ?><td class="r"><?= $item ? h(money(line_amount($item), $cur)) : '' ?></td><?php endif; ?>
            <?php else: ?>
              <?php if ($serial): ?><td class="c"><?= $item ? (string) ($i + 1) : '' ?></td><?php endif; ?>
              <td class="item"><?= $item && line_item_name($item) !== '' ? h(line_item_name($item)) : ($item ? '&nbsp;' : '&nbsp;') ?></td>
              <td class="desc"><?= $item && line_item_description($item) !== '' ? nl2br(h(line_item_description($item))) : '&nbsp;' ?></td>
              <td class="c"><?= $item ? h(format_qty($item['qty'])) : '' ?></td>
              <?php if (!$qtyOnly): ?>
                <td class="r"><?= $item ? h(money($item['rate'], $cur)) : '' ?></td>
                <td class="r"><?= $item ? h(money(line_amount($item), $cur)) : '' ?></td>
                <?php if ($showVat): ?><td class="c"><?= $item ? (!empty($item['taxed']) ? 'Y' : 'N') : '' ?></td><?php endif; ?>
              <?php endif; ?>
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
      <?php
        $extras = function_exists('document_party_extras') ? document_party_extras($doc) : [];
        $fields = function_exists('company_client_fields') ? company_client_fields()['extras'] : [];
        foreach ($fields as $field) {
            $shown = format_client_extra_value($field, $extras[$field['key']] ?? '');
            if ($shown === '') {
                continue;
            }
            echo '<div>' . h($field['label']) . ': ' . h($shown) . '</div>';
        }
      ?>
    </div>
    <?php
}

function format_letter_html(string $body): string
{
    $html = sanitize_rich_html($body);
    if ($html === '') {
        return '';
    }
    $html = preg_replace('/<p(\s|>)/i', '<p class="corr-p"$1', $html) ?? $html;
    if (!preg_match('/<(p|ul|ol|div)\b/i', $html)) {
        $html = '<p class="corr-p">' . $html . '</p>';
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
    if (($doc['kind'] ?? '') === 'letter') {
        $html = preg_replace('/class="([^"]*invoice-sheet[^"]*)"/', 'class="$1 is-letter"', $html, 1) ?? $html;
    }
    if (doc_template_key($doc) === 'thermal') {
        return $html;
    }
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

function render_letter_subject(array $doc): void
{
    $subject = trim((string) ($doc['subject'] ?? ''));
    if ($subject === '') {
        return;
    }
    ?>
    <p class="d-letter-sub"><span>Subject:</span> <?= h($subject) ?></p>
    <?php
}

function render_letter_to_label(string $label = 'To'): void
{
    ?>
    <div class="d-letter-to"><span><?= h($label) ?></span></div>
    <?php
}

function render_letter_signature(array $doc): void
{
    if (empty($doc['add_signature'])) {
        return;
    }
    $src = company_signature_url();
    if ($src === '') {
        return;
    }
    ?>
    <div class="d-sign">
      <img src="<?= h($src) ?>" alt="Signature">
    </div>
    <?php
}

function render_letter_body(array $doc): void
{
    ?>
    <div class="d-letter">
      <div class="d-letter-body"><?= format_letter_html((string) $doc['body']) ?></div>
      <?php render_letter_signature($doc); ?>
    </div>
    <?php
}

function render_print_document_page(array $doc, bool $pdf = false): void
{
    $brand = branding();
    $thermal = doc_template_key($doc) === 'thermal' && ($doc['kind'] ?? '') !== 'custom' && ($doc['kind'] ?? '') !== 'expense';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title></title>
  <meta name="format-detection" content="telephone=no,email=no,address=no,date=no">
  <?php product_icons(); ?>
  <?php folio_css_links(true, true); ?>
  <?php folio_font_links(); ?>
  <style>
    :root { <?= brand_css_vars() ?> }
    @page { size: <?= $thermal ? '80mm auto' : 'A4' ?>; margin: 0; }
  </style>
</head>
<body class="print-body<?= $thermal ? ' print-thermal' : '' ?>">
  <div class="sheet-wrap">
    <div class="sheet-stage">
      <?php render_sheet($brand, $doc); ?>
    </div>
  </div>
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
  <?php render_letter_signature($doc); ?>
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
  <?php if (($doc['kind'] ?? '') === 'letter'): ?>
    <?php render_letter_to_label(); ?>
    <div class="d-party"><?php render_party_contact($doc); ?></div>
    <?php render_letter_subject($doc); ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
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
          <div class="d-sum"><span><?= h($d['tax_label']) ?></span><span><?= h(money($d['vat'], $d['cur'])) ?></span></div>
        <?php endif; ?>
        <div class="d-total"><span>Total</span><span><?= h(money($d['total'], $d['cur'])) ?></span></div>
        <?php render_fx_equiv($d); ?>
        <?php render_settlement($d); ?>
        <p class="d-payhint"><?= h($brand['payment_note'] ?? '') ?></p>
      </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
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
    <label class="wide"><?= ($doc['kind'] ?? '') === 'letter' ? '<span class="d-to-word">To</span>' : 'From' ?> <b><?php render_party_contact($doc); ?></b></label>
    <?php if (kind_shows_money($doc['kind'] ?? '')): ?>
    <div class="ledger-amt"><span><?= h($d['cur']) ?></span><strong><?= h(number_format($d['total'], currency_decimals($d['cur']))) ?></strong></div>
    <?php endif; ?>
  </div>
  <?php if (($doc['kind'] ?? '') === 'letter'): ?>
    <?php render_letter_subject($doc); ?>
  <?php endif; ?>
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
        <?php if (sheet_shows_money($d)): ?>
        <tr><th>Acct.</th><td><?= h($brand['account_number'] ?: '-') ?></td></tr>
        <?php if ($doc['kind'] === 'receipt'): ?>
          <tr><th>RECEIVED:</th><td><?= h(money($d['settlement']['received'] ?? $d['total'], $d['cur'])) ?></td></tr>
          <tr><th>DUE:</th><td><?= h(money($d['settlement']['balance'] ?? 0, $d['cur'])) ?></td></tr>
        <?php endif; ?>
        <?php endif; ?>
      </table>
      <?php if (sheet_shows_money($d)): ?>
      <div class="ledger-pay">
        <?php foreach (['cash' => 'Cash', 'cheque' => 'Cheque', 'mobile-money' => 'Mobile money', 'bank-transfer' => 'Bank'] as $k => $label): ?>
          <span class="tick <?= sheet_tick($d['method'], $k) ?>"><?= h($label) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
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
    $second = $variant === 'amber' ? $d['color'] : $d['accent'];
    ?>
<article class="invoice-sheet sheet-bill sheet-<?= h($variant) ?>" style="<?= h($d['vars']) ?>;--a:<?= h($primary) ?>;--b:<?= h($second) ?>">
  <div class="bill-corner tl" aria-hidden="true"></div>
  <div class="bill-corner br" aria-hidden="true"></div>
  <div class="bill-pad">
  <header class="bill-head">
    <img src="<?= h($d['logo']) ?>" alt="" class="d-logo sm">
    <div>
      <h2><?= h($brand['name']) ?></h2>
      <p><?= h($brand['address']) ?> · <?= h($brand['phone']) ?> · <?= h($brand['email']) ?></p>
    </div>
  </header>
  <div class="bill-pill" style="background:<?= h($primary) ?>;color:<?= h(contrast_on($primary)) ?>"><?= h($d['heading']) ?></div>
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
  <?php if (($doc['kind'] ?? '') === 'letter'): ?>
    <?php render_letter_to_label(); ?>
  <?php endif; ?>
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
      <?php if (sheet_shows_money($d)): ?><div class="dot">Currency <b><?= h($d['cur']) ?></b></div><?php endif; ?>
    </div>
  </div>
  <?php if ($doc['kind'] === 'letter'): ?>
    <?php render_letter_subject($doc); ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($doc, $primary, $variant === 'amber' ? $d['accent_tint'] : $d['tint'], ['serial' => true, 'class' => 'bill-lines']); ?>
    <?php if (sheet_shows_money($d)): ?>
    <div class="bill-foot">
      <div class="bill-words"><span>In words</span><b><?= h(amount_in_words($d['total'], $d['cur'])) ?></b></div>
      <div class="bill-sums">
        <div><span>Sub total</span><b><?= h(money($d['net'], $d['cur'])) ?></b></div>
        <?php if (!empty($d['show_vat'])): ?><div><span><?= h($d['tax_label']) ?></span><b><?= h(money($d['vat'], $d['cur'])) ?></b></div><?php endif; ?>
        <div class="due"><span>Total</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
        <?php render_fx_equiv($d); ?>
        <?php render_settlement($d); ?>
      </div>
    </div>
    <?php endif; ?>
    <div class="bill-signs">
      <div>Received by</div>
      <div>Authorized by</div>
    </div>
  <?php endif; ?>
  </div>
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
        <?php if (sheet_shows_money($d)): ?>
        <div><span>Amount</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
        <?php render_fx_equiv($d); ?>
        <div><span>Paid how</span><b><?= h($d['methods'][$d['method']] ?? ($d['method'] ?: '-')) ?></b></div>
        <?php endif; ?>
      </div>
      <?php if ($doc['kind'] === 'letter'): ?>
        <?php render_letter_subject($doc); ?>
        <?php render_letter_body($doc); ?>
      <?php else: ?>
        <?php render_line_table($doc, $d['deep'], $d['accent_tint'], ['min' => 3, 'class' => 'tiny twin-lines', 'compact' => true]); ?>
        <?php render_settlement($d); ?>
      <?php endif; ?>
      <?php if (sheet_shows_money($d)): ?>
      <div class="twin-pay">
        <?php foreach (['cash' => 'CASH', 'cheque' => 'CHEQUE', 'bank-transfer' => 'BANK', 'mobile-money' => 'MOMO'] as $k => $lab): ?>
          <span class="tick <?= sheet_tick($d['method'], $k) ?>"><?= h($lab) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
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
    <div class="stripe-parties">
      <div>
        <span>From</span>
        <b><?= h($brand['name']) ?></b>
        <p>
          <?= h($brand['address']) ?>
          <?php if (!empty($brand['city'])): ?><br><?= h($brand['city']) ?><?php endif; ?>
          <br><?= h($brand['phone']) ?>
          <?php if (!empty($brand['email'])): ?><br><?= h($brand['email']) ?><?php endif; ?>
          <?php if (!empty($brand['tin'])): ?><br>TIN <?= h($brand['tin']) ?><?php endif; ?>
        </p>
      </div>
      <div>
        <span>To</span>
        <b><?= h($doc['party_name'] ?? '') ?></b>
        <p>
          <?= h($doc['party_address'] ?? '') ?>
          <?php if (!empty($doc['party_phone'])): ?><br><?= h($doc['party_phone']) ?><?php endif; ?>
          <?php if (!empty($doc['party_email'])): ?><br><?= h($doc['party_email']) ?><?php endif; ?>
        </p>
      </div>
    </div>
    <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
    <div class="stripe-meta">
      <div><span>Number</span><b><?= h($doc['number']) ?></b></div>
      <div><span>Date</span><b><?= h(format_date($doc['date'])) ?></b></div>
      <?php if (!empty($doc['due_date'])): ?><div><span>Due</span><b><?= h(format_date($doc['due_date'])) ?></b></div><?php endif; ?>
      <?php if (sheet_shows_money($d)): ?><div><span>Currency</span><b><?= h($d['cur']) ?></b></div><?php endif; ?>
    </div>
    <?php if ($doc['kind'] === 'letter'): ?>
      <?php render_letter_subject($doc); ?>
      <?php render_letter_body($doc); ?>
    <?php else: ?>
      <p class="stripe-h">Line details</p>
      <?php render_line_table($doc, $d['color'], $d['accent_tint']); ?>
      <?php if (sheet_shows_money($d)): ?>
      <div class="stripe-payrow">
        <div>
          <span>Payment method</span>
          <em><?= h($d['methods'][$d['method']] ?? ($d['method'] ?: 'On account')) ?></em>
          <?php render_settlement($d); ?>
        </div>
        <div class="stripe-fare">
          <div><span>Subtotal</span><b><?= h(money($d['net'], $d['cur'])) ?></b></div>
          <?php if (!empty($d['show_vat'])): ?><div><span><?= h($d['tax_name']) ?></span><b><?= h(money($d['vat'], $d['cur'])) ?></b></div><?php endif; ?>
          <div class="stripe-total"><span>Total</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
          <?php render_fx_equiv($d); ?>
        </div>
      </div>
      <?php endif; ?>
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
      <?php if (sheet_shows_money($d)): ?><span>Currency</span><b><?= h($d['cur']) ?></b><?php endif; ?>
    </div>
  </div>
  <?php if ($doc['kind'] === 'letter'): ?>
    <?php render_letter_subject($doc); ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($doc, $d['color'], $d['tint']); ?>
    <div class="estate-end">
      <div>
        <p><?= h($d['comments'] ?: ($brand['payment_note'] ?? '')) ?></p>
        <?php render_settlement($d); ?>
      </div>
      <?php if (sheet_shows_money($d)): ?>
      <div class="estate-total">
        <span>Total</span>
        <b><?= h(money($d['total'], $d['cur'])) ?></b>
        <?php render_fx_equiv($d); ?>
        <small><?= h(amount_in_words($d['total'], $d['cur'])) ?></small>
      </div>
      <?php endif; ?>
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
        <span class="<?= ($doc['kind'] ?? '') === 'letter' ? 'd-to-word' : '' ?>"><?= ($doc['kind'] ?? '') === 'letter' ? 'To' : 'Client' ?></span>
        <strong><?= h($doc['party_name'] ?? '') ?></strong>
        <p><?= h($doc['party_address'] ?? '') ?><br><?= h($doc['party_email'] ?? '') ?></p>
      </div>
      <div>
        <span>Reference</span>
        <strong><?= h($doc['number']) ?></strong>
        <p><?= h(format_date($doc['date'])) ?><?php if (sheet_shows_money($d)): ?> · <?= h($d['cur']) ?><?php endif; ?>
          <?php if (!empty($doc['due_date'])): ?><br>Due <?= h(format_date($doc['due_date'])) ?><?php endif; ?>
        </p>
      </div>
    </div>
    <?php if ($doc['kind'] === 'letter'): ?>
      <?php render_letter_subject($doc); ?>
      <?php render_letter_body($doc); ?>
    <?php else: ?>
      <?php render_line_table($doc, $d['color'], '#ffffff', ['serial' => true]); ?>
      <?php if (sheet_shows_money($d)): ?>
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
      <?php if (sheet_shows_money($d)): ?><span><?= h($d['cur']) ?></span><?php endif; ?>
    </div>
  </header>
  <hr class="atelier-rule">
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
  <div class="atelier-party">
    <span class="<?= $doc['kind'] === 'letter' ? 'd-to-word' : '' ?>"><?= $doc['kind'] === 'letter' ? 'To' : 'Prepared for' ?></span>
    <strong><?= h($doc['party_name'] ?? '') ?></strong>
    <p><?= h($doc['party_address'] ?? '') ?><?= !empty($doc['party_email']) ? ' · ' . h($doc['party_email']) : '' ?></p>
  </div>
  <?php if ($doc['kind'] === 'letter'): ?>
    <?php render_letter_subject($doc); ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($doc, $d['deep'], $d['tint']); ?>
    <div class="atelier-end">
      <div class="atelier-note">
        <span>Notes</span>
        <p><?= h($d['comments'] ?: ($brand['payment_note'] ?? '')) ?></p>
      </div>
      <?php if (sheet_shows_money($d)): ?>
      <div class="atelier-sums">
        <div><span>Subtotal</span><b><?= h(money($d['net'], $d['cur'])) ?></b></div>
        <?php if (!empty($d['show_vat'])): ?><div><span><?= h($d['tax_label']) ?></span><b><?= h(money($d['vat'], $d['cur'])) ?></b></div><?php endif; ?>
        <div class="atelier-total"><span>Total</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
        <?php render_fx_equiv($d); ?>
        <?php render_settlement($d); ?>
      </div>
      <?php endif; ?>
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
    <?php if (sheet_shows_money($d)): ?><div><span>Currency</span><b><?= h($d['cur']) ?></b></div><?php endif; ?>
  </div>
  <p class="seal-for"><span class="<?= $doc['kind'] === 'letter' ? 'd-to-word' : '' ?>"><?= $doc['kind'] === 'letter' ? 'To' : 'In account with' ?></span> <strong><?= h($doc['party_name'] ?? '') ?></strong></p>
  <?php if ($doc['kind'] === 'letter'): ?>
    <?php render_letter_subject($doc); ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($doc, $d['deep'], $d['tint']); ?>
    <div class="seal-end">
      <p><?= h($d['comments'] ?: ($brand['payment_note'] ?? '')) ?></p>
      <?php if (sheet_shows_money($d)): ?>
      <aside>
        <div><span>Subtotal</span><b><?= h(money($d['net'], $d['cur'])) ?></b></div>
        <?php if (!empty($d['show_vat'])): ?><div><span><?= h($d['tax_label']) ?></span><b><?= h(money($d['vat'], $d['cur'])) ?></b></div><?php endif; ?>
        <div class="seal-due"><span>Total</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
        <?php render_fx_equiv($d); ?>
        <?php render_settlement($d); ?>
      </aside>
      <?php endif; ?>
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
      <?php if (sheet_shows_money($d)): ?><div><span>Currency</span><b><?= h($d['cur']) ?></b></div><?php endif; ?>
    </div>
  </header>
  <hr class="mark-rule">
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
  <div class="mark-who">
    <span>To</span>
    <?php render_party_contact($doc); ?>
  </div>
  <?php if ($doc['kind'] === 'letter'): ?>
    <?php render_letter_subject($doc); ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($doc, $d['color'], $d['tint']); ?>
    <div class="d-split">
      <div class="d-notes">
        <div class="d-notes-body"><?= h($d['comments'] ?: ($brand['payment_note'] ?? '')) ?></div>
      </div>
      <?php if (sheet_shows_money($d)): ?>
      <div class="d-sums">
        <div class="d-sum"><span>Subtotal</span><b><?= h(money($d['net'], $d['cur'])) ?></b></div>
        <?php if (!empty($d['show_vat'])): ?><div class="d-sum"><span><?= h($d['tax_label']) ?></span><b><?= h(money($d['vat'], $d['cur'])) ?></b></div><?php endif; ?>
        <div class="d-total"><span>Total</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
        <?php render_fx_equiv($d); ?>
        <?php render_settlement($d); ?>
      </div>
      <?php endif; ?>
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
      <img src="<?= h($d['logo']) ?>" alt="" class="d-logo sm">
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
    <?php render_letter_subject($doc); ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($doc, $d['deep'], '#ffffff', ['class' => 'bond-lines', 'compact' => true]); ?>
    <div class="bond-end">
      <p><?= h($d['comments'] ?: ($brand['payment_note'] ?? '')) ?></p>
      <?php if (sheet_shows_money($d)): ?>
      <aside>
        <div><span>Subtotal</span><b><?= h(money($d['net'], $d['cur'])) ?></b></div>
        <?php if (!empty($d['show_vat'])): ?><div><span><?= h($d['tax_label']) ?></span><b><?= h(money($d['vat'], $d['cur'])) ?></b></div><?php endif; ?>
        <div class="bond-due"><span>Total</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
        <?php render_fx_equiv($d); ?>
        <?php render_settlement($d); ?>
      </aside>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <footer class="bond-foot"><?= h($brand['website'] ?: $brand['email']) ?></footer>
</article>
<?php
}

function render_sheet_frame(array $d): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    ?>
<article class="invoice-sheet sheet-frame" style="<?= h($d['vars']) ?>">
  <div class="page-frame">
    <div class="page-frame-inner">
      <header class="d-row">
        <div>
          <img src="<?= h($d['logo']) ?>" alt="" class="d-logo">
          <div class="d-co">
            <strong><?= h($brand['name']) ?></strong>
            <div><?= h($brand['address']) ?></div>
            <div><?= h($brand['phone']) ?> · <?= h($brand['email']) ?></div>
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
      <?php if (($doc['kind'] ?? '') === 'letter'): ?>
        <?php render_letter_to_label(); ?>
        <div class="d-party"><?php render_party_contact($doc); ?></div>
        <?php render_letter_subject($doc); ?>
        <?php render_letter_body($doc); ?>
      <?php else: ?>
      <div class="bar" style="background:<?= h($d['color']) ?>"><?= kind_shows_money($doc['kind'] ?? '') ? 'BILL TO' : 'TO' ?></div>
      <div class="d-party"><?php render_party_contact($doc); ?></div>
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
              <div class="d-sum"><span><?= h($d['tax_label']) ?></span><span><?= h(money($d['vat'], $d['cur'])) ?></span></div>
            <?php endif; ?>
            <div class="d-total"><span>Total</span><span><?= h(money($d['total'], $d['cur'])) ?></span></div>
            <?php render_fx_equiv($d); ?>
            <?php render_settlement($d); ?>
          </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <footer class="d-foot">
        <p><?= h($brand['phone']) ?> · <?= h($brand['email']) ?></p>
        <p class="thanks">Thank You For Your Business!</p>
      </footer>
    </div>
  </div>
</article>
<?php
}

function render_sheet_inset(array $d): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    ?>
<article class="invoice-sheet sheet-inset" style="<?= h($d['vars']) ?>">
  <div class="page-inset">
    <header class="d-row">
      <div>
        <img src="<?= h($d['logo']) ?>" alt="" class="d-logo">
        <div class="d-co">
          <strong><?= h($brand['name']) ?></strong>
          <div><?= h($brand['address']) ?></div>
          <div><?= h($brand['phone']) ?></div>
          <div><?= h($brand['email']) ?></div>
        </div>
      </div>
      <div class="d-meta-box">
        <p class="d-title" style="color:<?= h($d['deep']) ?>"><?= h($d['heading']) ?></p>
        <table class="meta">
          <tr><td class="k">No.</td><td><?= h($doc['number']) ?></td></tr>
          <tr><td class="k">DATE</td><td><?= h(format_date($doc['date'])) ?></td></tr>
          <?php if (!empty($doc['due_date'])): ?>
            <tr><td class="k">DUE</td><td><?= h(format_date($doc['due_date'])) ?></td></tr>
          <?php endif; ?>
        </table>
      </div>
    </header>
    <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
    <div class="inset-to">
      <span class="<?= ($doc['kind'] ?? '') === 'letter' ? 'd-to-word' : '' ?>"><?= ($doc['kind'] ?? '') === 'letter' ? 'To' : (kind_shows_money($doc['kind'] ?? '') ? 'Bill to' : 'To') ?></span>
      <?php render_party_contact($doc); ?>
    </div>
    <?php if (($doc['kind'] ?? '') === 'letter'): ?>
      <?php render_letter_subject($doc); ?>
      <?php render_letter_body($doc); ?>
    <?php else: ?>
      <?php render_line_table($doc, $d['deep'], $d['tint']); ?>
      <div class="d-split">
        <div class="d-notes">
          <div class="d-notes-body"><?= h($d['comments'] ?: ($brand['payment_note'] ?? '')) ?></div>
        </div>
        <?php if (kind_shows_money($doc['kind'] ?? '')): ?>
        <div class="d-sums">
          <div class="d-sum"><span>Subtotal</span><span><?= h(money($d['net'], $d['cur'])) ?></span></div>
          <?php if (!empty($d['show_vat'])): ?>
            <div class="d-sum"><span><?= h($d['tax_label']) ?></span><span><?= h(money($d['vat'], $d['cur'])) ?></span></div>
          <?php endif; ?>
          <div class="d-total"><span>Total</span><span><?= h(money($d['total'], $d['cur'])) ?></span></div>
          <?php render_fx_equiv($d); ?>
          <?php render_settlement($d); ?>
        </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</article>
<?php
}

function render_sheet_thermal(array $d): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    $qtyOnly = in_array(($doc['kind'] ?? ''), ['delivery', 'return_note'], true);
    $kind = (string) ($doc['kind'] ?? '');
    $notes = trim((string) ($doc['notes'] ?? ''));
    $isTill = $kind === 'receipt' || str_starts_with($notes, 'Sale');
    $heading = $isTill ? 'Receipt' : (string) $d['heading'];
    $slip = $doc;
    if ($kind === 'receipt') {
        $relatedId = (int) ($doc['related_id'] ?? 0);
        if ($relatedId > 0) {
            $inv = load_document($relatedId);
            if ($inv && ($inv['kind'] ?? '') === 'invoice' && !empty($inv['items'])) {
                $slip = $inv;
                $d['net'] = doc_subtotal($inv['items']);
                $d['vat'] = doc_vat($inv['items'], (float) ($inv['vat_rate'] ?? 0));
                $d['total'] = $d['net'] + $d['vat'];
                $d['show_vat'] = doc_shows_vat($inv);
                $d['cur'] = doc_currency($inv);
            }
        }
    }
    $paid = (float) ($doc['kind'] === 'receipt'
        ? (($doc['settlement']['received'] ?? $doc['allocated_amount'] ?? $d['total']))
        : ($doc['paid'] ?? 0));
    if ($kind === 'invoice' && $paid <= 0 && !empty($d['paid'])) {
        $paid = (float) $d['paid'];
    }
    $due = max(0, round((float) $d['total'] - $paid, 2));
    $comment = $notes;
    if (!$isTill) {
        $comment = (string) $d['comments'];
    } elseif (strcasecmp($comment, 'Sale') === 0) {
        $comment = '';
    }
    ?>
<article class="invoice-sheet sheet-thermal" style="<?= h($d['vars']) ?>">
  <header class="thermal-head">
    <img src="<?= h($d['logo']) ?>" alt="" class="d-logo xs">
    <strong><?= h($brand['name']) ?></strong>
    <p><?= h($brand['address']) ?></p>
    <p><?= h($brand['phone']) ?></p>
    <?php if (!empty($brand['tin'])): ?><p>TIN <?= h($brand['tin']) ?></p><?php endif; ?>
  </header>
  <p class="thermal-kind"><?= h($heading) ?></p>
  <p class="thermal-meta"><?= h($doc['number']) ?><br><?= h(format_date($doc['date'])) ?></p>
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID</p><?php endif; ?>
  <p class="thermal-to"><span>To</span> <?= h($doc['party_name'] ?? '') ?></p>
  <?php if ($kind === 'letter'): ?>
    <?php render_letter_subject($doc); ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($slip, '#111', '#f4f4f4', ['compact' => true, 'min' => 1, 'class' => 'thermal-lines']); ?>
    <?php if (kind_shows_money($slip['kind'] ?? $kind) && !$qtyOnly): ?>
    <div class="thermal-sums">
      <div><span>Subtotal</span><b><?= h(money($d['net'], $d['cur'])) ?></b></div>
      <?php if (!empty($d['show_vat'])): ?>
        <div><span><?= h($d['tax_label']) ?></span><b><?= h(money($d['vat'], $d['cur'])) ?></b></div>
      <?php endif; ?>
      <div class="thermal-total"><span>Total</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
      <?php if ($isTill): ?>
        <div><span>Paid</span><b><?= h(money($paid, $d['cur'])) ?></b></div>
        <?php if ($due > 0.009): ?>
          <div><span>Due</span><b><?= h(money($due, $d['cur'])) ?></b></div>
        <?php endif; ?>
        <?php
        $payHow = trim((string) (($d['methods'][$d['method']] ?? '') ?: $d['method']));
        if ($payHow !== ''):
        ?>
          <div><span>How</span><b><?= h($payHow) ?></b></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (trim($comment) !== ''): ?>
      <p class="thermal-note"><?= h($comment) ?></p>
    <?php endif; ?>
  <?php endif; ?>
  <p class="thermal-thanks">Thank you</p>
  <p class="thermal-foot"><?= h($brand['email']) ?></p>
</article>
<?php
}

function render_expense_card(array $brand, array $doc): void
{
    $d = sheet_data($brand, $doc);
    $cur = $d['cur'];
    $paid = (float) ($doc['paid'] ?? 0);
    $balance = (float) ($doc['balance'] ?? max(0, $d['total'] - $paid));
    $method = (string) ($doc['payment_method'] ?? '');
    $methods = payment_methods();
    ?>
<div class="expense-card">
  <div class="expense-card-top">
    <div>
      <span>Expense</span>
      <strong><?= h((string) $doc['number']) ?></strong>
    </div>
    <div class="right">
      <span><?= h(format_date($doc['date'] ?? null)) ?></span>
      <strong><?= h(invoice_status_label($doc)) ?></strong>
    </div>
  </div>
  <div class="expense-card-body">
    <div class="expense-meta">
      <div><span>Payee</span><b><?= h((string) ($doc['party_name'] ?? '')) ?></b></div>
      <div><span>Category</span><b><?= h((string) ($doc['expense_category'] ?: 'Other')) ?></b></div>
      <?php if ($method !== ''): ?>
        <div><span>Paid how</span><b><?= h($methods[$method] ?? $method) ?></b></div>
      <?php endif; ?>
      <?php if (!empty($doc['payment_ref'])): ?>
        <div><span>Reference</span><b><?= h((string) $doc['payment_ref']) ?></b></div>
      <?php endif; ?>
      <div><span>Currency</span><b><?= h($cur) ?></b></div>
      <div><span>Paid</span><b><?= h(money($paid, $cur)) ?></b></div>
    </div>
    <?php if (!empty($doc['items'])): ?>
      <table class="grid">
        <thead>
          <tr>
            <th>Item</th>
            <th>Description</th>
            <th class="right">Qty</th>
            <th class="right">Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($doc['items'] as $item): ?>
            <tr>
              <td><?= h(line_item_name($item)) ?></td>
              <td><?= nl2br(h(line_item_description($item))) ?></td>
              <td class="right mono"><?= h(format_qty($item['qty'] ?? 0)) ?></td>
              <td class="right mono"><?= h(money(line_amount($item), $cur)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <div class="expense-total">
      <span>Total<?= $balance > 0.009 ? ' · Balance ' . money($balance, $cur) : '' ?></span>
      <strong><?= h(money($d['total'], $cur)) ?></strong>
    </div>
    <?php if (!empty($doc['notes'])): ?>
      <p class="muted" style="margin:14px 0 0;white-space:pre-wrap"><?= h((string) $doc['notes']) ?></p>
    <?php endif; ?>
  </div>
</div>
<?php
}

function render_sheet(array $brand, array $doc): void
{
    if (($doc['kind'] ?? '') === 'expense') {
        render_expense_card($brand, $doc);
        return;
    }
    $d = sheet_data($brand, $doc);
    ob_start();
    if (($doc['kind'] ?? '') === 'custom') {
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
            'frame' => render_sheet_frame($d),
            'inset' => render_sheet_inset($d),
            'thermal' => render_sheet_thermal($d),
            default => render_sheet_folio($d),
        };
    }
    echo inject_sheet_watermark((string) ob_get_clean(), $d, $doc);
}
