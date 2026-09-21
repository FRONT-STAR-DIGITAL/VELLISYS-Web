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
        'comments' => brand_document_comments($brand, (string) ($doc['kind'] ?? ''), $doc['notes'] ?? ''),
        'logo' => logo_url($brand),
        'cur' => doc_currency($doc),
        'home_cur' => default_currency(),
        'alt_cur' => other_currency(doc_currency($doc)),
        'fx_rate' => fx_home_per_usd(),
        'method' => $method,
        'methods' => payment_methods(),
    ];
}

function render_sheet_logo(array $d, string $class = 'd-logo'): void
{
    $src = trim((string) ($d['logo'] ?? ''));
    if ($src === '') {
        return;
    }
    $alt = (string) ($d['brand']['name'] ?? '');
    echo '<img src="' . h($src) . '" alt="' . h($alt) . '" class="' . h($class) . '">';
}

function sheet_is_receipt(array $d): bool
{
    return ($d['doc']['kind'] ?? '') === 'receipt';
}

function sheet_received_amount(array $d): float
{
    $s = $d['settlement'] ?? [];
    $got = (float) ($s['received'] ?? ($d['doc']['allocated_amount'] ?? 0));
    if ($got > 0.009) {
        return $got;
    }
    $paid = (float) ($d['paid'] ?? 0);
    if ($paid > 0.009) {
        return $paid;
    }
    return (float) ($d['total'] ?? 0);
}

function sheet_due_amount(array $d): float
{
    if (function_exists('document_due_amount') && isset($d['doc'])) {
        return document_due_amount($d['doc']);
    }
    $s = $d['settlement'] ?? [];
    $due = (float) ($s['invoice_balance'] ?? 0);
    if ($due <= 0.009) {
        $due = (float) ($s['balance'] ?? $d['balance'] ?? 0);
    }
    return max(0, $due);
}

function sheet_words_amount(array $d): float
{
    return sheet_is_receipt($d) ? sheet_received_amount($d) : (float) ($d['total'] ?? 0);
}

function render_hero_money(array $d, string $class = 'd-total', string $valueTag = 'span'): void
{
    $receipt = sheet_is_receipt($d);
    $label = $receipt ? 'Received' : 'Total';
    $amt = $receipt ? sheet_received_amount($d) : (float) ($d['total'] ?? 0);
    echo '<div class="' . h($class) . '"><span>' . h($label) . '</span><' . $valueTag . '>' . h(money($amt, $d['cur'])) . '</' . $valueTag . '></div>';
}

function render_package_total_line(array $d, string $class = 'd-sum', string $valueTag = 'span'): void
{
    if (!sheet_is_receipt($d)) {
        return;
    }
    echo '<div class="' . h($class) . '"><span>Total</span><' . $valueTag . '>' . h(money((float) ($d['total'] ?? 0), $d['cur'])) . '</' . $valueTag . '></div>';
}

function render_sums_close(array $d, string $heroClass = 'd-total', string $sumClass = 'd-sum', string $valueTag = 'span'): void
{
    render_package_total_line($d, $sumClass, $valueTag);
    render_hero_money($d, $heroClass, $valueTag);
    render_fx_equiv($d);
    render_settlement($d);
}

function render_settlement(array $d): void
{
    if (!sheet_is_receipt($d)) {
        return;
    }
    $due = sheet_due_amount($d);
    $open = $due > 0.009;
    ?>
    <div class="d-rd">
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
    $amt = $amount === null
        ? (sheet_is_receipt($d) ? sheet_received_amount($d) : (float) $d['total'])
        : (float) $amount;
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
    $showVat = !$qtyOnly && doc_shows_vat($doc) && company_shows_line_col('vat');
    $taxName = trim((string) ($opts['tax_name'] ?? ''));
    if ($taxName === '') {
        $taxName = company_tax_name();
    }
    $compact = !empty($opts['compact']);
    $colItem = company_shows_line_col('item');
    $colDesc = company_shows_line_col('description');
    $colQty = company_shows_line_col('qty');
    $colRate = !$qtyOnly && company_shows_line_col('rate');
    $colTotal = !$qtyOnly && company_shows_line_col('total');
    $colDetails = $colItem || $colDesc;
    if (!$colDetails) {
        $colDetails = true;
        $colItem = true;
    }
    ?>
    <table class="d-lines <?= h($cls) ?>">
      <thead>
        <tr style="background:<?= h($color) ?>;color:#fff">
          <?php if ($compact): ?>
            <?php if ($colDetails): ?><th>Details</th><?php endif; ?>
            <?php if ($colQty): ?><th class="c" style="width:12%">Qty</th><?php endif; ?>
            <?php if ($colTotal): ?><th class="r" style="width:28%">Amount</th><?php elseif ($colRate): ?><th class="r" style="width:28%">Unit price</th><?php endif; ?>
          <?php else: ?>
            <?php if ($serial): ?><th class="c" style="width:44px">No.</th><?php endif; ?>
            <?php if ($colItem): ?><th style="width:22%">Item</th><?php endif; ?>
            <?php if ($colDesc): ?><th>Description</th><?php endif; ?>
            <?php if ($colQty): ?><th class="c" style="width:64px">Qty</th><?php endif; ?>
            <?php if ($colRate): ?><th class="r" style="width:110px">Unit price</th><?php endif; ?>
            <?php if ($colTotal): ?><th class="r" style="width:120px">Total Amt</th><?php endif; ?>
            <?php if ($showVat): ?><th class="c" style="width:44px"><?= h($taxName) ?></th><?php endif; ?>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $item): ?>
          <tr style="background:<?= $i % 2 ? h($tint) : '#fff' ?>">
            <?php if ($compact): ?>
              <?php if ($colDetails): ?>
              <td class="desc"><?php
                if (!$item) {
                    echo '&nbsp;';
                } else {
                    $name = $colItem ? line_item_name($item) : '';
                    $desc = $colDesc ? line_item_description($item) : '';
                    $parts = [];
                    if ($name !== '') {
                        $parts[] = '<span class="item">' . h($name) . '</span>';
                    }
                    if ($desc !== '') {
                        $parts[] = '<span class="twin-desc">' . nl2br(h($desc), false) . '</span>';
                    }
                    echo $parts ? implode(' ', $parts) : '&nbsp;';
                    if ($showVat && !empty($item['taxed'])) {
                        echo '<span class="twin-vat"> ' . h($taxName) . '</span>';
                    }
                }
              ?></td>
              <?php endif; ?>
              <?php if ($colQty): ?><td class="c"><?= $item ? h(format_qty($item['qty'])) : '' ?></td><?php endif; ?>
              <?php if ($colTotal): ?><td class="r"><?= $item ? h(money(line_amount($item), $cur)) : '' ?></td><?php elseif ($colRate): ?><td class="r"><?= $item ? h(money($item['rate'], $cur)) : '' ?></td><?php endif; ?>
            <?php else: ?>
              <?php if ($serial): ?><td class="c"><?= $item ? (string) ($i + 1) : '' ?></td><?php endif; ?>
              <?php if ($colItem): ?><td class="item"><?= $item && line_item_name($item) !== '' ? h(line_item_name($item)) : ($item ? '&nbsp;' : '&nbsp;') ?></td><?php endif; ?>
              <?php if ($colDesc): ?><td class="desc"><?= $item && line_item_description($item) !== '' ? nl2br(h(line_item_description($item))) : '&nbsp;' ?></td><?php endif; ?>
              <?php if ($colQty): ?><td class="c"><?= $item ? h(format_qty($item['qty'])) : '' ?></td><?php endif; ?>
              <?php if ($colRate): ?><td class="r"><?= $item ? h(money($item['rate'], $cur)) : '' ?></td><?php endif; ?>
              <?php if ($colTotal): ?><td class="r"><?= $item ? h(money(line_amount($item), $cur)) : '' ?></td><?php endif; ?>
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
    $lines = function_exists('document_party_to_lines') ? document_party_to_lines($doc) : [];
    $n = count($lines);
    $split = $n > 1 ? (int) ceil($n / 2) : $n;
    $cols = $n > 1 ? [array_slice($lines, 0, $split), array_slice($lines, $split)] : [$lines];
    echo '<div class="d-party-block' . ($n > 1 ? ' d-party-cols' : '') . '">';
    foreach ($cols as $col) {
        echo '<div class="d-party-col">';
        foreach ($col as $line) {
            $label = trim((string) ($line['label'] ?? ''));
            $text = (string) ($line['value'] ?? '');
            $cls = 'd-party-row' . (!empty($line['nl']) ? ' d-party-addr' : '');
            echo '<div class="' . $cls . '">';
            if ($label !== '') {
                echo '<b class="d-party-k">' . h($label) . ':</b>';
            }
            echo '<span class="d-party-v">' . (!empty($line['nl']) ? nl2br(h($text)) : h($text)) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
}

function render_amount_words(array $d): void
{
    if (!function_exists('sheet_shows_money') || !sheet_shows_money($d)) {
        return;
    }
    if (!function_exists('amount_in_words')) {
        return;
    }
    $words = trim((string) amount_in_words(sheet_words_amount($d), $d['cur'] ?? null));
    if ($words === '') {
        return;
    }
    echo '<p class="d-words">Amount in words: <b>' . h($words) . '</b></p>';
}

function slip_plain(string $text): string
{
    return str_replace(["\u{2014}", "\u{2013}", "\u{2212}", '—', '–', '−'], '-', $text);
}

function slip_item_summary(array $doc): string
{
    $parts = [];
    foreach ($doc['items'] ?? [] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = function_exists('line_item_name') ? trim((string) line_item_name($item)) : trim((string) ($item['item_name'] ?? ''));
        $desc = function_exists('line_item_description') ? trim((string) line_item_description($item)) : trim((string) ($item['description'] ?? ''));
        $bit = $name;
        if ($desc !== '' && strcasecmp($desc, $name) !== 0) {
            $bit = $bit === '' ? $desc : $bit . ' - ' . $desc;
        }
        if ($bit !== '') {
            $parts[] = $bit;
        }
    }
    return slip_plain(implode('; ', $parts));
}

function slip_cash_label(string $cur): string
{
    $cur = strtoupper(trim($cur));
    return match ($cur) {
        'UGX' => 'Shs.',
        'KES' => 'KSh',
        'TZS' => 'TSh',
        default => $cur !== '' ? $cur : 'Amt',
    };
}

function slip_to_lines(array $doc): array
{
    $filled = function_exists('document_party_to_lines') ? document_party_to_lines($doc) : [];
    $byLabel = [];
    foreach ($filled as $line) {
        $byLabel[mb_strtolower(trim((string) ($line['label'] ?? '')))] = $line;
    }
    $entity = function_exists('party_entity') ? party_entity([
        'entity' => (string) ($doc['party_entity'] ?? ''),
        'tin' => (string) ($doc['party_tin'] ?? ''),
        'contact_person' => (string) ($doc['party_contact'] ?? ''),
        'name' => (string) ($doc['party_name'] ?? ''),
    ]) : 'person';
    $profileKey = function_exists('entity_to_client_profile') ? entity_to_client_profile($entity) : 'people';
    $addrLabel = $profileKey === 'people' ? 'Residence' : 'Address';
    $labelForKey = [
        'contact_person' => 'Attn',
        'tin' => 'TIN',
        'phone' => 'Tel',
        'phone2' => 'Tel 2',
        'email' => 'Email',
        'address' => $addrLabel,
        'city' => 'City',
        'country' => 'Country',
    ];
    $out = [];
    $seen = [];
    $out[] = $byLabel['name'] ?? ['label' => 'Name', 'value' => trim((string) ($doc['party_name'] ?? ''))];
    $seen['name'] = true;
    if (!function_exists('client_to_order')) {
        return $filled ?: $out;
    }
    foreach (client_to_order(null, $profileKey) as $item) {
        if (($item['kind'] ?? '') === 'extra') {
            $lab = trim((string) ($item['label'] ?? ''));
            if ($lab === '') {
                continue;
            }
            $key = mb_strtolower($lab);
            $out[] = $byLabel[$key] ?? ['label' => $lab, 'value' => '', 'nl' => false];
            $seen[$key] = true;
            continue;
        }
        $k = (string) ($item['key'] ?? '');
        $lab = $labelForKey[$k] ?? '';
        if ($lab === '') {
            continue;
        }
        $key = mb_strtolower($lab);
        $out[] = $byLabel[$key] ?? ['label' => $lab, 'value' => '', 'nl' => $k === 'address'];
        $seen[$key] = true;
    }
    foreach ($filled as $line) {
        $key = mb_strtolower(trim((string) ($line['label'] ?? '')));
        if ($key === '' || !empty($seen[$key])) {
            continue;
        }
        $out[] = $line;
        $seen[$key] = true;
    }
    return $out;
}

function render_slip_dot(string $label, string $value, string $cls = ''): void
{
    $label = trim($label);
    echo '<p class="slip-line' . ($cls !== '' ? ' ' . $cls : '') . '">';
    if ($label !== '') {
        echo '<b>' . h($label) . '</b> ';
    }
    echo '<span>' . h(slip_plain($value)) . '</span></p>';
}

function render_slip_field_grid(array $lines): void
{
    $n = count($lines);
    $split = $n > 1 ? (int) ceil($n / 2) : $n;
    $cols = $n > 1 ? [array_slice($lines, 0, $split), array_slice($lines, $split)] : [$lines];
    echo '<div class="slip-fields' . ($n > 1 ? ' is-cols' : '') . '">';
    foreach ($cols as $col) {
        echo '<div class="slip-col">';
        foreach ($col as $line) {
            render_slip_dot((string) ($line['label'] ?? ''), (string) ($line['value'] ?? ''));
        }
        echo '</div>';
    }
    echo '</div>';
}

function slip_money_bits(array $d): array
{
    $doc = $d['doc'];
    $kind = (string) ($doc['kind'] ?? '');
    $paid = (float) ($kind === 'receipt'
        ? (($doc['settlement']['received'] ?? $doc['allocated_amount'] ?? $d['total']))
        : ($doc['paid'] ?? 0));
    if ($kind === 'invoice' && $paid <= 0 && !empty($d['paid'])) {
        $paid = (float) $d['paid'];
    }
    $due = sheet_is_receipt($d) ? sheet_due_amount($d) : max(0, round((float) $d['total'] - $paid, 2));
    $how = trim((string) (($d['methods'][$d['method'] ?? ''] ?? '') ?: ($d['method'] ?? '')));
    $wordAmt = sheet_is_receipt($d) ? $paid : (float) ($d['total'] ?? 0);
    $words = function_exists('amount_in_words') ? trim((string) amount_in_words($wordAmt, $d['cur'] ?? null)) : '';
    return [$paid, $due, $how, $words];
}

function format_letter_html(string $body): string
{
    $html = sanitize_rich_html($body);
    if ($html === '') {
        return '';
    }
    $html = preg_replace('/<p(\s|>)/i', '<p class="corr-p"$1', $html) ?? $html;
    $html = preg_replace('/font-size\s*:\s*[^;"]+;?/i', '', $html) ?? $html;
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

function document_has_e_signature(array $doc): bool
{
    return !empty($doc['add_signature']) && company_signature_url() !== '';
}

function render_company_signature(array $doc): void
{
    if (!document_has_e_signature($doc)) {
        return;
    }
    $src = company_signature_url();
    ?>
    <div class="d-sign has-stamp">
      <img src="<?= h($src) ?>" alt="Signature">
    </div>
    <?php
}

function render_letter_signature(array $doc): void
{
    render_company_signature($doc);
}

function render_authorized_signoff(array $doc, string $label = 'Authorized by'): void
{
    if (($doc['kind'] ?? '') === 'letter') {
        return;
    }
    ?>
    <div class="auth-sign<?= document_has_e_signature($doc) ? ' has-stamp' : '' ?>">
      <?php render_company_signature($doc); ?>
      <p><?= h($label) ?></p>
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
      <?php render_sheet_logo($d); ?>
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
      <?php render_sheet_logo($d); ?>
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
        <?php render_sums_close($d); ?>
        <?php render_amount_words($d); ?>
        <p class="d-payhint"><?= h($brand['payment_note'] ?? '') ?></p>
      </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php endif; ?>
  <?php render_authorized_signoff($doc); ?>
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
      <?php render_sheet_logo($d, 'd-logo sm'); ?>
      <div><?= h($brand['address']) ?><br><?= h($brand['email']) ?><br><?= h($brand['phone']) ?></div>
    </div>
    <h1><?= h($d['heading']) ?></h1>
    <div class="ledger-no">No. <?= h($doc['number']) ?></div>
  </div>
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
  <div class="ledger-grid">
    <label>Date <b><?= h(format_date($doc['date'])) ?></b></label>
    <label>Date <b><?= h(format_date($doc['date'])) ?></b></label>
    <div class="wide ledger-party">
      <span><?= ($doc['kind'] ?? '') === 'letter' ? '<span class="d-to-word">To</span>' : 'From' ?></span>
      <?php render_party_contact($doc); ?>
    </div>
    <?php if (kind_shows_money($doc['kind'] ?? '')): ?>
    <div class="ledger-amt"><span><?= h($d['cur']) ?></span><strong><?= h(number_format(sheet_is_receipt($d) ? sheet_received_amount($d) : (float) $d['total'], currency_decimals($d['cur']))) ?></strong></div>
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
    <b><?= h(amount_in_words(sheet_words_amount($d), $d['cur'])) ?></b>
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
        <div><span>By</span><?php render_company_signature($doc); ?><b><?= h($brand['account_name'] ?: 'Accounts') ?></b></div>
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
    <?php render_sheet_logo($d, 'd-logo sm'); ?>
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
    </div>
    <div>
      <div class="dot">Date <b><?= h(format_date($doc['date'])) ?></b></div>
      <?php if (!empty($doc['due_date'])): ?><div class="dot">Due <b><?= h(format_date($doc['due_date'])) ?></b></div><?php endif; ?>
      <?php if (sheet_shows_money($d)): ?><div class="dot">Currency <b><?= h($d['cur']) ?></b></div><?php endif; ?>
    </div>
    <?php render_party_contact($doc); ?>
  </div>
  <?php if ($doc['kind'] === 'letter'): ?>
    <?php render_letter_subject($doc); ?>
    <?php render_letter_body($doc); ?>
  <?php else: ?>
    <?php render_line_table($doc, $primary, $variant === 'amber' ? $d['accent_tint'] : $d['tint'], ['serial' => true, 'class' => 'bill-lines']); ?>
    <?php if (sheet_shows_money($d)): ?>
    <div class="bill-foot">
      <div class="bill-words"><span>In words</span><b><?= h(amount_in_words(sheet_words_amount($d), $d['cur'])) ?></b></div>
      <div class="bill-sums">
        <div><span>Sub total</span><b><?= h(money($d['net'], $d['cur'])) ?></b></div>
        <?php if (!empty($d['show_vat'])): ?><div><span><?= h($d['tax_label']) ?></span><b><?= h(money($d['vat'], $d['cur'])) ?></b></div><?php endif; ?>
        <?php render_sums_close($d, 'due', '', 'b'); ?>
      </div>
    </div>
    <?php endif; ?>
    <div class="bill-signs">
      <div>Received by</div>
      <div<?= document_has_e_signature($doc) ? ' class="has-stamp"' : '' ?>><?php render_company_signature($doc); ?>Authorized by</div>
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
        <?php render_sheet_logo($d, 'd-logo xs'); ?>
        <div>
          <strong><?= h($brand['name']) ?></strong>
          <span><?= h($brand['address']) ?></span>
        </div>
      </div>
      <h3><?= h($d['heading']) ?></h3>
      <div class="twin-meta">Receipt No. <b><?= h($doc['number']) ?></b> · Date <b><?= h(format_date($doc['date'])) ?></b></div>
      <div class="twin-fields">
        <?php render_party_contact($doc); ?>
        <?php if (sheet_shows_money($d)): ?>
        <div><span><?= sheet_is_receipt($d) ? 'Received' : 'Amount' ?></span><b><?= h(money(sheet_is_receipt($d) ? sheet_received_amount($d) : (float) $d['total'], $d['cur'])) ?></b></div>
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
        <?php render_amount_words($d); ?>
      <?php endif; ?>
      <?php if (sheet_shows_money($d)): ?>
      <div class="twin-pay">
        <?php foreach (['cash' => 'CASH', 'cheque' => 'CHEQUE', 'bank-transfer' => 'BANK', 'mobile-money' => 'MOMO'] as $k => $lab): ?>
          <span class="tick <?= sheet_tick($d['method'], $k) ?>"><?= h($lab) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="twin-sign<?= (($doc['kind'] ?? '') !== 'letter' && document_has_e_signature($doc)) ? ' has-stamp' : '' ?>"><?php if (($doc['kind'] ?? '') !== 'letter') { render_company_signature($doc); } ?>Authorized signature</div>
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
    <div class="stripe-banner">
      <?php render_sheet_logo($d, 'd-logo invert'); ?>
      <span><?= h($d['heading']) ?></span>
    </div>
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
      </div>
      <div class="stripe-to-fields">
        <?php render_party_contact($doc); ?>
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
        </div>
        <div class="stripe-fare">
          <div><span>Subtotal</span><b><?= h(money($d['net'], $d['cur'])) ?></b></div>
          <?php if (!empty($d['show_vat'])): ?><div><span><?= h($d['tax_name']) ?></span><b><?= h(money($d['vat'], $d['cur'])) ?></b></div><?php endif; ?>
          <?php render_sums_close($d, 'stripe-total', '', 'b'); ?>
        </div>
      </div>
      <?php render_amount_words($d); ?>
      <?php endif; ?>
    <?php endif; ?>
    <?php render_authorized_signoff($doc); ?>
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
    <?php render_sheet_logo($d); ?>
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
    <div class="estate-who-bar">
      <span>Prepared for</span>
      <div class="estate-who-meta">
        <div><span>Date</span><b><?= h(format_date($doc['date'])) ?></b></div>
        <?php if (!empty($doc['due_date'])): ?><div><span>Due</span><b><?= h(format_date($doc['due_date'])) ?></b></div><?php endif; ?>
        <?php if (sheet_shows_money($d)): ?><div><span>Currency</span><b><?= h($d['cur']) ?></b></div><?php endif; ?>
      </div>
    </div>
    <?php render_party_contact($doc); ?>
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
        <span><?= sheet_is_receipt($d) ? 'Received' : 'Total' ?></span>
        <b><?= h(money(sheet_is_receipt($d) ? sheet_received_amount($d) : (float) $d['total'], $d['cur'])) ?></b>
        <?php render_fx_equiv($d); ?>
        <small><?= h(amount_in_words(sheet_words_amount($d), $d['cur'])) ?></small>
      </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php render_authorized_signoff($doc); ?>
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
      <?php render_sheet_logo($d, 'd-logo invert'); ?>
      <p><?= h($brand['name']) ?></p>
    </div>
    <h1><?= h($d['heading']) ?></h1>
  </div>
  <div class="night-copper"></div>
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID - <?= h($doc['void_reason']) ?></p><?php endif; ?>
  <div class="night-body">
    <div class="night-pair">
      <div class="night-pair-bar">
        <span class="<?= ($doc['kind'] ?? '') === 'letter' ? 'd-to-word' : '' ?>"><?= ($doc['kind'] ?? '') === 'letter' ? 'To' : 'Client' ?></span>
        <div class="night-pair-ref">
          <span>Reference</span>
          <strong><?= h($doc['number']) ?></strong>
          <p><?= h(format_date($doc['date'])) ?><?php if (sheet_shows_money($d)): ?> · <?= h($d['cur']) ?><?php endif; ?>
            <?php if (!empty($doc['due_date'])): ?><br>Due <?= h(format_date($doc['due_date'])) ?><?php endif; ?>
          </p>
        </div>
      </div>
      <?php render_party_contact($doc); ?>
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
          <p><?= h(amount_in_words(sheet_words_amount($d), $d['cur'])) ?></p>
          <?php render_settlement($d); ?>
        </div>
        <div>
          <b><?= h(money(sheet_is_receipt($d) ? sheet_received_amount($d) : (float) $d['total'], $d['cur'])) ?></b>
          <?php render_fx_equiv($d); ?>
        </div>
      </div>
      <?php endif; ?>
    <?php endif; ?>
    <?php render_authorized_signoff($doc); ?>
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
      <?php render_sheet_logo($d); ?>
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
    <?php render_party_contact($doc); ?>
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
        <?php render_sums_close($d, 'atelier-total', '', 'b'); ?>
      </div>
      <?php render_amount_words($d); ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php render_authorized_signoff($doc); ?>
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
    <?php render_sheet_logo($d); ?>
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
  <div class="seal-for">
    <span class="<?= $doc['kind'] === 'letter' ? 'd-to-word' : '' ?>"><?= $doc['kind'] === 'letter' ? 'To' : 'In account with' ?></span>
    <?php render_party_contact($doc); ?>
  </div>
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
        <?php render_sums_close($d, 'seal-due', '', 'b'); ?>
      </aside>
      <?php render_amount_words($d); ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <footer class="seal-sign">
    <div>For and on behalf of <?= h($brand['name']) ?></div>
    <div<?= (($doc['kind'] ?? '') !== 'letter' && document_has_e_signature($doc)) ? ' class="has-stamp"' : '' ?>><?php if (($doc['kind'] ?? '') !== 'letter') { render_company_signature($doc); } ?>Authorised</div>
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
      <?php render_sheet_logo($d); ?>
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
        <?php render_sums_close($d, 'd-total', 'd-sum', 'b'); ?>
      </div>
      <?php render_amount_words($d); ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php render_authorized_signoff($doc); ?>
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
      <?php render_sheet_logo($d, 'd-logo sm'); ?>
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
    <?php render_party_contact($doc); ?>
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
        <?php render_sums_close($d, 'bond-due', '', 'b'); ?>
      </aside>
      <?php render_amount_words($d); ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php render_authorized_signoff($doc); ?>
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
          <?php render_sheet_logo($d); ?>
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
            <?php render_sums_close($d); ?>
          </div>
          <?php render_amount_words($d); ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php render_authorized_signoff($doc); ?>
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
        <?php render_sheet_logo($d); ?>
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
          <?php render_sums_close($d); ?>
        </div>
        <?php render_amount_words($d); ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php render_authorized_signoff($doc); ?>
  </div>
</article>
<?php
}

function render_sheet_booklet(array $d): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    $kind = (string) ($doc['kind'] ?? '');
    $qtyOnly = in_array($kind, ['delivery', 'return_note'], true);
    $showMoney = sheet_shows_money($d) && !$qtyOnly;
    [$paid, $due, $how, $words] = slip_money_bits($d);
    $contact = array_filter([
        (string) ($brand['address'] ?? ''),
        trim((string) ($brand['city'] ?? '')),
        (string) ($brand['phone'] ?? ''),
        (string) ($brand['email'] ?? ''),
        (string) ($brand['website'] ?? ''),
    ], static fn ($v) => trim($v) !== '');
    $rest = [];
    foreach (slip_to_lines($doc) as $line) {
        if (mb_strtolower((string) ($line['label'] ?? '')) === 'name') {
            continue;
        }
        $rest[] = $line;
    }
    $note = trim((string) ($d['comments'] ?? ''));
    $pay = $kind === 'invoice' ? trim((string) ($brand['payment_note'] ?? '')) : '';
    ?>
<article class="invoice-sheet sheet-booklet" style="<?= h($d['vars']) ?>">
  <div class="booklet-page">
    <header class="booklet-head">
      <?php render_sheet_logo($d, 'd-logo sm booklet-logo'); ?>
      <h1 class="slip-brand"><?= h((string) $brand['name']) ?></h1>
      <?php if (!empty($brand['tagline'])): ?><p class="slip-motto"><?= h((string) $brand['tagline']) ?></p><?php endif; ?>
      <div class="slip-co">
        <?php foreach ($contact as $row): ?><div><?= h(slip_plain($row)) ?></div><?php endforeach; ?>
        <?php if (!empty($brand['tin'])): ?><div>TIN <?= h($brand['tin']) ?></div><?php endif; ?>
      </div>
    </header>
    <div class="booklet-meta">
      <p class="slip-no"><span>No.</span> <b><?= h((string) $doc['number']) ?></b></p>
      <?php render_slip_dot('Date', format_date($doc['date'])); ?>
      <?php if (!empty($doc['due_date'])): ?>
        <?php render_slip_dot('Due', format_date($doc['due_date'])); ?>
      <?php endif; ?>
    </div>
    <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID</p><?php endif; ?>
    <?php render_slip_dot('RECEIVED with thanks from', (string) ($doc['party_name'] ?? ''), 'slip-lead'); ?>
    <?php render_slip_field_grid($rest); ?>
    <?php if ($kind === 'letter'): ?>
      <?php render_letter_subject($doc); ?>
      <?php render_letter_body($doc); ?>
    <?php else: ?>
      <?php if ($showMoney): ?>
        <?php render_slip_dot('The sum of', $words, 'slip-wide'); ?>
      <?php endif; ?>
      <?php render_slip_dot('Being payment of', slip_item_summary($doc), 'slip-wide'); ?>
      <div class="booklet-payrow">
        <?php render_slip_dot('Cash / Cheque', $how); ?>
        <?php if ($showMoney): ?>
          <?php render_slip_dot('Amount received', money($paid, $d['cur'])); ?>
        <?php endif; ?>
      </div>
      <?php if ($showMoney): ?>
        <?php render_slip_dot('Amount due', $due > 0.009 ? money($due, $d['cur']) : money(0, $d['cur'])); ?>
      <?php endif; ?>
      <?php if ($showMoney && !empty($d['show_vat'])): ?>
        <?php render_slip_dot((string) $d['tax_label'], money($d['vat'], $d['cur'])); ?>
      <?php endif; ?>
      <?php if ($showMoney): ?>
        <?php render_fx_equiv($d); ?>
      <?php endif; ?>
      <div class="booklet-footrow">
        <?php if ($showMoney): ?>
        <div class="slip-cash-stack">
        <div class="slip-cashbox">
          <span><?= h(slip_cash_label((string) $d['cur'])) ?></span>
          <b><?= h(money($paid, $d['cur'])) ?></b>
        </div>
        <?php if ($due > 0.009): ?>
          <p class="slip-due">Amount due <?= h(money($due, $d['cur'])) ?></p>
        <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="slip-sign<?= document_has_e_signature($doc) ? ' has-stamp' : '' ?>">
          <span>Signature</span>
          <?php render_letter_signature($doc); ?>
          <p class="slip-for">For: <?= h((string) $brand['name']) ?></p>
        </div>
      </div>
    <?php endif; ?>
    <p class="slip-thanks">Thank You</p>
    <?php if ($note !== ''): ?>
      <p class="slip-foot-note"><?= h(slip_plain($note)) ?></p>
    <?php elseif ($pay !== ''): ?>
      <p class="slip-foot-note"><?= h(slip_plain($pay)) ?></p>
    <?php endif; ?>
  </div>
</article>
<?php
}

function render_sheet_chit(array $d): void
{
    $brand = $d['brand'];
    $doc = $d['doc'];
    $kind = (string) ($doc['kind'] ?? '');
    $qtyOnly = in_array($kind, ['delivery', 'return_note'], true);
    $showMoney = sheet_shows_money($d) && !$qtyOnly;
    [$paid, $due, $how, $words] = slip_money_bits($d);
    $contact = array_filter([
        (string) ($brand['address'] ?? ''),
        trim((string) ($brand['city'] ?? '')),
        (string) ($brand['phone'] ?? ''),
        (string) ($brand['email'] ?? ''),
    ], static fn ($v) => trim($v) !== '');
    $rest = [];
    foreach (slip_to_lines($doc) as $line) {
        if (mb_strtolower((string) ($line['label'] ?? '')) === 'name') {
            continue;
        }
        $rest[] = $line;
    }
    ?>
<article class="invoice-sheet sheet-chit" style="<?= h($d['vars']) ?>">
  <div class="chit-page">
    <header class="chit-head">
      <?php render_sheet_logo($d, 'd-logo sm'); ?>
      <h1 class="chit-name"><?= h((string) $brand['name']) ?></h1>
      <?php if (!empty($brand['tagline'])): ?><p class="chit-tag"><?= h((string) $brand['tagline']) ?></p><?php endif; ?>
      <div class="chit-co">
        <?php foreach ($contact as $row): ?><div><?= h($row) ?></div><?php endforeach; ?>
        <?php if (!empty($brand['tin'])): ?><div>TIN <?= h($brand['tin']) ?></div><?php endif; ?>
      </div>
    </header>
    <div class="chit-meta">
      <p class="slip-no"><span>No.</span> <b><?= h((string) $doc['number']) ?></b></p>
      <p class="chit-kind"><?= h((string) $d['heading']) ?></p>
      <?php render_slip_dot('Date', format_date($doc['date'])); ?>
    </div>
    <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID</p><?php endif; ?>
    <?php render_slip_dot('RECEIVED with thanks from', (string) ($doc['party_name'] ?? ''), 'slip-lead'); ?>
    <?php render_slip_field_grid($rest); ?>
    <?php if ($kind === 'letter'): ?>
      <?php render_letter_subject($doc); ?>
      <?php render_letter_body($doc); ?>
    <?php else: ?>
      <?php if ($showMoney): ?>
        <?php render_slip_dot('The sum of', $words, 'slip-wide'); ?>
      <?php endif; ?>
      <?php render_slip_dot('Being payment of', slip_item_summary($doc), 'slip-wide'); ?>
      <div class="chit-payrow">
        <?php render_slip_dot('Cash / Cheque', $how); ?>
        <?php if ($showMoney): ?>
          <?php render_slip_dot('Amount received', money($paid, $d['cur'])); ?>
        <?php endif; ?>
      </div>
      <?php if ($showMoney): ?>
        <?php render_slip_dot('Amount due', $due > 0.009 ? money($due, $d['cur']) : money(0, $d['cur'])); ?>
      <?php endif; ?>
      <?php if ($showMoney && !empty($d['show_vat'])): ?>
        <?php render_slip_dot((string) $d['tax_label'], money($d['vat'], $d['cur'])); ?>
      <?php endif; ?>
      <?php if ($showMoney): ?>
        <?php render_fx_equiv($d); ?>
        <div class="chit-footrow">
          <div class="slip-cash-stack">
          <div class="slip-cashbox">
            <span><?= h(slip_cash_label((string) $d['cur'])) ?></span>
            <b><?= h(money($paid, $d['cur'])) ?></b>
          </div>
          <?php if ($due > 0.009): ?>
            <p class="slip-due">Amount due <?= h(money($due, $d['cur'])) ?></p>
          <?php endif; ?>
          </div>
          <div class="slip-sign<?= document_has_e_signature($doc) ? ' has-stamp' : '' ?>">
            <span>Signature</span>
            <?php render_letter_signature($doc); ?>
            <p class="slip-for">For: <?= h((string) $brand['name']) ?></p>
          </div>
        </div>
      <?php else: ?>
        <div class="slip-sign<?= document_has_e_signature($doc) ? ' has-stamp' : '' ?>">
          <span>Signature</span>
          <?php render_letter_signature($doc); ?>
          <p class="slip-for">For: <?= h((string) $brand['name']) ?></p>
        </div>
      <?php endif; ?>
    <?php endif; ?>
    <p class="slip-thanks">Thank You</p>
    <?php
    $note = trim((string) ($d['comments'] ?? ''));
    if ($note !== ''): ?>
      <p class="slip-foot-note"><?= h(slip_plain($note)) ?></p>
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
    $due = ($kind === 'receipt' && function_exists('document_due_amount'))
        ? document_due_amount($doc)
        : max(0, round((float) $d['total'] - $paid, 2));
    $comment = (string) ($d['comments'] ?? '');
    if ($isTill && strcasecmp($notes, 'Sale') === 0) {
        $comment = trim((string) ($brand['receipt_comments'] ?? ''));
    }
    ?>
<article class="invoice-sheet sheet-thermal" style="<?= h($d['vars']) ?>">
  <header class="thermal-head">
    <?php render_sheet_logo($d, 'd-logo xs'); ?>
    <strong><?= h($brand['name']) ?></strong>
    <p><?= h($brand['address']) ?></p>
    <p><?= h($brand['phone']) ?></p>
    <?php if (!empty($brand['tin'])): ?><p>TIN <?= h($brand['tin']) ?></p><?php endif; ?>
  </header>
  <p class="thermal-kind"><?= h($heading) ?></p>
  <p class="thermal-meta"><?= h($doc['number']) ?><br><?= h(format_date($doc['date'])) ?></p>
  <?php if ($doc['status'] === 'void'): ?><p class="d-void">VOID</p><?php endif; ?>
  <div class="thermal-to">
    <span>To</span>
    <?php render_party_contact($doc); ?>
  </div>
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
      <div><span>Total</span><b><?= h(money($d['total'], $d['cur'])) ?></b></div>
      <div class="thermal-total"><span><?= $isTill ? 'Received' : 'Total' ?></span><b><?= h(money($isTill ? $paid : $d['total'], $d['cur'])) ?></b></div>
      <?php if ($isTill): ?>
        <div><span>Due</span><b><?= h(money($due, $d['cur'])) ?></b></div>
        <?php
        $payHow = trim((string) (($d['methods'][$d['method']] ?? '') ?: $d['method']));
        if ($payHow !== ''):
        ?>
          <div><span>How</span><b><?= h($payHow) ?></b></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php render_amount_words($d); ?>
    <?php endif; ?>
    <?php if (trim($comment) !== ''): ?>
      <p class="thermal-note"><?= h($comment) ?></p>
    <?php endif; ?>
  <?php endif; ?>
  <?php render_authorized_signoff($doc); ?>
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
      <?php render_sheet_logo($d, 'd-logo sm'); ?>
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
            'booklet' => render_sheet_booklet($d),
            'chit' => render_sheet_chit($d),
            default => render_sheet_folio($d),
        };
    }
    echo inject_sheet_watermark((string) ob_get_clean(), $d, $doc);
}
