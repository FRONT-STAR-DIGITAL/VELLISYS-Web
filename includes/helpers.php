<?php
declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money($amount, ?string $currency = null): string
{
    $currency = strtoupper($currency ?: default_currency());
    $n = (float) $amount;
    if ($currency === 'USD') {
        return 'USD ' . number_format($n, 2, '.', ',');
    }
    return 'UGX ' . number_format($n, $n == floor($n) ? 0 : 2, '.', ',');
}

function fx_ugx_per_usd(): float
{
    $n = (float) (branding()['fx_ugx_per_usd'] ?? 3700);
    return $n > 0 ? $n : 3700.0;
}

function parse_fx_rate(string $raw): float
{
    $n = money_parse($raw);
    return $n > 0 ? $n : fx_ugx_per_usd();
}

function other_currency(string $currency): string
{
    return strtoupper($currency) === 'USD' ? 'UGX' : 'USD';
}

function round_money(float $amount, string $currency): float
{
    return strtoupper($currency) === 'USD' ? round($amount, 2) : round($amount, 0);
}

function convert_money(float $amount, string $from, string $to, ?float $rate = null): float
{
    $from = strtoupper($from) === 'USD' ? 'USD' : 'UGX';
    $to = strtoupper($to) === 'USD' ? 'USD' : 'UGX';
    if ($from === $to) {
        return round_money($amount, $to);
    }
    $rate = $rate ?? fx_ugx_per_usd();
    if ($rate <= 0) {
        $rate = 3700.0;
    }
    if ($from === 'USD') {
        return round_money($amount * $rate, 'UGX');
    }
    return round_money($amount / $rate, 'USD');
}

function money_pair($amount, string $currency, ?float $rate = null): string
{
    $currency = strtoupper($currency) === 'USD' ? 'USD' : 'UGX';
    $alt = other_currency($currency);
    return money($amount, $currency) . ' · ' . money(convert_money((float) $amount, $currency, $alt, $rate), $alt);
}

function apply_company_doc_template(string $key): void
{
    if (!array_key_exists($key, doc_templates())) {
        $key = 'folio';
    }
    $cid = current_company_id();
    db_exec('UPDATE branding SET doc_template = ? WHERE company_id = ?', 'si', [$key, $cid]);
    db_exec('UPDATE documents SET doc_template = ? WHERE company_id = ?', 'si', [$key, $cid]);
    branding(true);
}

function apply_fx_rate(string $raw): float
{
    $rate = parse_fx_rate($raw);
    db_exec('UPDATE branding SET fx_ugx_per_usd = ? WHERE company_id = ?', 'di', [$rate, current_company_id()]);
    branding(true);
    return $rate;
}

function ugx($amount, ?string $currency = null): string
{
    return money($amount, $currency);
}

function default_currency(): string
{
    $c = strtoupper((string) (branding()['currency'] ?? 'UGX'));
    return $c === 'USD' ? 'USD' : 'UGX';
}

function doc_currency(?array $doc = null): string
{
    if ($doc && !empty($doc['currency'])) {
        $c = strtoupper((string) $doc['currency']);
        return $c === 'USD' ? 'USD' : 'UGX';
    }
    return default_currency();
}

function money_parse(string $raw): float
{
    $raw = str_replace([',', ' '], '', trim($raw));
    if ($raw === '' || !is_numeric($raw)) {
        return 0.0;
    }
    return round((float) $raw, 2);
}

function format_qty($qty): string
{
    $n = (float) $qty;
    if (abs($n - round($n)) < 0.0001) {
        return (string) (int) round($n);
    }
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}

function currencies(): array
{
    return ['UGX' => 'UGX - Uganda shilling', 'USD' => 'USD - US dollar'];
}

function doc_templates(): array
{
    return [
        'folio' => [
            'name' => 'Folio bar',
            'blurb' => 'Primary colour on the original letterhead sheet.',
        ],
        'ledger' => [
            'name' => 'Colour ledger',
            'blurb' => 'Receipt-book layout washed in your primary and accent.',
        ],
        'crimson' => [
            'name' => 'Corner bill',
            'blurb' => 'Primary and deep geometric corners on a challan sheet.',
        ],
        'amber' => [
            'name' => 'Accent bill',
            'blurb' => 'Accent and deep corner blocks with a bold title pill.',
        ],
        'twin' => [
            'name' => 'Twin copy',
            'blurb' => 'Office copy and client copy, accent banner, deep type.',
        ],
        'stripe' => [
            'name' => 'Accent stripe',
            'blurb' => 'Deep title bar, accent rail, and a boxed total.',
        ],
        'estate' => [
            'name' => 'Estate cream',
            'blurb' => 'Deep header band, accent rule, harvest paper.',
        ],
        'night' => [
            'name' => 'Lake night',
            'blurb' => 'Deep dusk header and accent copper lines.',
        ],
    ];
}

function doc_template_key(?array $doc = null): string
{
    $key = strtolower((string) (branding()['doc_template'] ?? 'folio'));
    return array_key_exists($key, doc_templates()) ? $key : 'folio';
}

function number_to_words(int $n): string
{
    if ($n === 0) {
        return 'zero';
    }
    $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
    $chunk = static function (int $x) use ($ones, $tens): string {
        $parts = [];
        if ($x >= 100) {
            $parts[] = $ones[(int) floor($x / 100)] . ' hundred';
            $x %= 100;
        }
        if ($x >= 20) {
            $parts[] = $tens[(int) floor($x / 10)] . ($x % 10 ? '-' . $ones[$x % 10] : '');
        } elseif ($x > 0) {
            $parts[] = $ones[$x];
        }
        return implode(' ', $parts);
    };
    $scales = [[1000000000, 'billion'], [1000000, 'million'], [1000, 'thousand']];
    $parts = [];
    $rest = abs($n);
    foreach ($scales as [$size, $name]) {
        if ($rest >= $size) {
            $parts[] = $chunk((int) floor($rest / $size)) . ' ' . $name;
            $rest %= $size;
        }
    }
    if ($rest > 0) {
        $parts[] = $chunk($rest);
    }
    return implode(' ', $parts);
}

function amount_in_words($amount, ?string $currency = null): string
{
    $currency = strtoupper($currency ?: default_currency());
    $n = round(abs((float) $amount), 2);
    $whole = (int) floor($n);
    $frac = (int) round(($n - $whole) * 100);
    $unit = $currency === 'USD' ? 'dollars' : 'shillings';
    $out = ucfirst(number_to_words($whole)) . ' ' . $unit;
    if ($frac > 0) {
        $out .= ' and ' . number_to_words($frac) . ' cents';
    }
    return $out . ' only';
}

function flash(?string $message = null, string $type = 'ok'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['text' => $message, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function post(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function folio_defaults(): array
{
    return [
        'name' => 'Folio',
        'tagline' => 'Your invoices, in your colours',
        'brand_color' => '#82B440',
        'brand_accent' => '#C6A15B',
        'brand_deep' => '#1F3A12',
        'logo_path' => 'assets/img/ofagros-logo.png',
        'prefix' => 'FOL',
        'plan' => 'sme',
        'currency' => 'UGX',
        'phone' => '',
        'email' => '',
        'address' => '',
        'website' => '',
        'tin' => '',
        'payment_note' => '',
        'invoice_comments' => '',
        'letter_templates' => '',
        'doc_template' => 'folio',
        'fx_ugx_per_usd' => 3700,
    ];
}

function branding_for(int $companyId): array
{
    if ($companyId > 0) {
        $row = db_one('SELECT * FROM branding WHERE company_id = ?', 'i', [$companyId]);
        if ($row) {
            return $row;
        }
    }
    return folio_defaults();
}

function branding(bool $refresh = false): array
{
    static $row = null;
    if ($refresh) {
        $row = null;
    }
    if ($row === null) {
        $cid = current_company_id();
        $row = $cid > 0 ? branding_for($cid) : folio_defaults();
    }
    return $row;
}

function parse_hex_color(?string $raw, string $fallback = '#82B440'): string
{
    $c = strtoupper(trim((string) $raw));
    if ($c !== '' && !str_starts_with($c, '#')) {
        $c = '#' . $c;
    }
    return preg_match('/^#[0-9A-F]{6}$/', $c) ? $c : $fallback;
}

function hex_to_rgb(string $hex): array
{
    $hex = ltrim(parse_hex_color($hex), '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

function rgb_to_hex(int $r, int $g, int $b): string
{
    return sprintf('#%02X%02X%02X', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
}

function hex_mix(string $a, string $b, float $t): string
{
    $t = max(0, min(1, $t));
    [$ar, $ag, $ab] = hex_to_rgb($a);
    [$br, $bg, $bb] = hex_to_rgb($b);
    return rgb_to_hex(
        (int) round($ar + ($br - $ar) * $t),
        (int) round($ag + ($bg - $ag) * $t),
        (int) round($ab + ($bb - $ab) * $t)
    );
}

function hex_shade(string $hex, float $amount): string
{
    return hex_mix($hex, '#000000', max(0, min(1, $amount)));
}

function contrast_on(string $hex): string
{
    [$r, $g, $b] = hex_to_rgb($hex);
    $y = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
    return $y > 0.58 ? '#141712' : '#FFFFFF';
}

function brand_palette(?array $brand = null): array
{
    $brand = $brand ?? branding();
    $primary = parse_hex_color($brand['brand_color'] ?? '', '#82B440');
    $accent = parse_hex_color($brand['brand_accent'] ?? '', '');
    $deep = parse_hex_color($brand['brand_deep'] ?? '', '');
    if ($accent === '') {
        $accent = hex_mix($primary, '#C6A15B', 0.62);
    }
    if ($deep === '') {
        $deep = hex_shade($primary, 0.58);
    }
    return [
        'primary' => $primary,
        'accent' => $accent,
        'deep' => $deep,
        'tint' => hex_tint($primary, 0.9),
        'accent_tint' => hex_tint($accent, 0.88),
        'deep_tint' => hex_tint($deep, 0.9),
        'on_primary' => contrast_on($primary),
        'on_accent' => contrast_on($accent),
        'on_deep' => contrast_on($deep),
    ];
}

function brand_css_vars(?array $brand = null): string
{
    $p = brand_palette($brand);
    return '--brand:' . $p['primary']
        . ';--brand-2:' . $p['accent']
        . ';--brand-3:' . $p['deep']
        . ';--brand-tint:' . $p['tint']
        . ';--brand-2-tint:' . $p['accent_tint']
        . ';--brand-3-tint:' . $p['deep_tint']
        . ';--on-brand:' . $p['on_primary']
        . ';--on-brand-2:' . $p['on_accent']
        . ';--on-brand-3:' . $p['on_deep'];
}

function brand_color(): string
{
    return brand_palette()['primary'];
}

function brand_accent(): string
{
    return brand_palette()['accent'];
}

function brand_deep(): string
{
    return brand_palette()['deep'];
}

function logo_url(?array $brand = null): string
{
    $brand = $brand ?? branding();
    $path = $brand['logo_path'] ?? 'assets/img/ofagros-logo.png';
    if ($path && is_file(ROOT_PATH . '/' . ltrim($path, '/'))) {
        return url($path);
    }
    return url('assets/img/ofagros-logo.png');
}

function parties_for(string $kind = 'customer'): array
{
    $cid = current_company_id();
    if ($kind === 'expense') {
        return db_all("SELECT * FROM parties WHERE company_id = ? AND kind IN ('supplier','both') ORDER BY name", 'i', [$cid]);
    }
    return db_all("SELECT * FROM parties WHERE company_id = ? AND kind IN ('customer','both') ORDER BY name", 'i', [$cid]);
}

function expense_categories(): array
{
    return ['Farm inputs', 'Fuel', 'Rent', 'Salaries', 'Transport', 'Utilities', 'Professional fees', 'Other'];
}

function payment_methods(): array
{
    return [
        'bank-transfer' => 'Bank transfer',
        'mobile-money' => 'Mobile money',
        'cash' => 'Cash',
        'cheque' => 'Cheque',
    ];
}

function hex_tint(string $hex, float $mix = 0.9): string
{
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $mixc = static fn (int $c) => (int) round($c * (1 - $mix) + 255 * $mix);
    return sprintf('rgb(%d,%d,%d)', $mixc($r), $mixc($g), $mixc($b));
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $ok = hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '');
    if (!$ok) {
        http_response_code(400);
        exit('Invalid session. Refresh and try again.');
    }
}

function kind_meta(string $kind): array
{
    return match ($kind) {
        'quotation' => ['title' => 'Quotations', 'singular' => 'Quotation', 'heading' => 'QUOTATION', 'verb' => 'New quotation'],
        'invoice' => ['title' => 'Invoices', 'singular' => 'Invoice', 'heading' => 'INVOICE', 'verb' => 'New invoice'],
        'receipt' => ['title' => 'Receipts', 'singular' => 'Receipt', 'heading' => 'RECEIPT', 'verb' => 'New receipt'],
        'expense' => ['title' => 'Expenses', 'singular' => 'Expense', 'heading' => 'EXPENSE', 'verb' => 'Record expense'],
        'letter' => ['title' => 'Correspondence', 'singular' => 'Note', 'heading' => '', 'verb' => 'New correspondence'],
        default => ['title' => 'Documents', 'singular' => 'Document', 'heading' => 'DOCUMENT', 'verb' => 'New'],
    };
}

function format_date(?string $iso): string
{
    if (!$iso) {
        return '';
    }
    $dt = DateTime::createFromFormat('Y-m-d', substr($iso, 0, 10));
    return $dt ? $dt->format('d/m/Y') : $iso;
}

function current_company_id(): int
{
    if (!empty($GLOBALS['folio_company_override'])) {
        return (int) $GLOBALS['folio_company_override'];
    }
    return (int) ($_SESSION['company_id'] ?? 0);
}

function is_platform(?array $user = null): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    return ($user['role'] ?? '') === 'platform';
}

function letter_template_defaults(): array
{
    return [
        'demand' => [
            'title' => 'Demand for payment',
            'heading' => 'DEMAND FOR PAYMENT',
            'subject' => 'Demand for payment',
            'body' => "Dear Sir / Madam,\n\nWe write in respect of amounts that remain unpaid on your account with {company}. Kindly settle the balance within seven (7) days of this note.\n\nIf payment has already been made, please send the reference so we may update our books.\n\nYours faithfully,\nAccounts\n{company}",
        ],
        'covering' => [
            'title' => 'Covering note',
            'heading' => 'COVERING NOTE',
            'subject' => 'Documents enclosed',
            'body' => "Dear Sir / Madam,\n\nPlease find enclosed the documents listed below. Kindly acknowledge receipt.\n\nYours faithfully,\nAccounts\n{company}",
        ],
        'appointment' => [
            'title' => 'Appointment',
            'heading' => 'APPOINTMENT',
            'subject' => 'Confirmation of appointment',
            'body' => "Dear Sir / Madam,\n\nThis confirms our appointment as agreed. Please let us know if the date or time needs to change.\n\nYours faithfully,\nAccounts\n{company}",
        ],
        'credit' => [
            'title' => 'Credit and goodwill',
            'heading' => 'CREDIT NOTE',
            'subject' => 'Credit on your account',
            'body' => "Dear Sir / Madam,\n\nWe have credited your account as a gesture of goodwill / in correction of the items discussed. The credit will appear on your next statement.\n\nYours faithfully,\nAccounts\n{company}",
        ],
        'notice' => [
            'title' => 'Overdue notice',
            'heading' => 'OVERDUE NOTICE',
            'subject' => 'Account overdue',
            'body' => "Dear Sir / Madam,\n\nYour account with {company} is now overdue. Please arrange payment at once to avoid interruption of supply.\n\nYours faithfully,\nAccounts\n{company}",
        ],
    ];
}

function apply_template_vars(array $tpl): array
{
    $company = (string) (branding()['name'] ?? 'the company');
    foreach (['title', 'heading', 'subject', 'body'] as $field) {
        $tpl[$field] = str_replace('{company}', $company, (string) ($tpl[$field] ?? ''));
    }
    return $tpl;
}

function letter_templates(bool $raw = false): array
{
    $templates = letter_template_defaults();
    $saved = json_decode((string) (branding()['letter_templates'] ?? ''), true);
    if (is_array($saved)) {
        foreach ($saved as $key => $tpl) {
            if (!is_array($tpl)) {
                continue;
            }
            $key = preg_replace('/[^a-z0-9_]+/', '', strtolower((string) $key)) ?: '';
            if ($key === '') {
                continue;
            }
            $base = $templates[$key] ?? ['title' => '', 'heading' => '', 'subject' => '', 'body' => ''];
            foreach (['title', 'heading', 'subject', 'body'] as $field) {
                if (isset($tpl[$field])) {
                    $base[$field] = (string) $tpl[$field];
                }
            }
            if (trim($base['title']) === '') {
                unset($templates[$key]);
                continue;
            }
            if ($base['heading'] === '') {
                $base['heading'] = strtoupper($base['title']);
            }
            if ($base['subject'] === '') {
                $base['subject'] = $base['title'];
            }
            $templates[$key] = $base;
        }
    }
    if ($raw) {
        return $templates;
    }
    return array_map('apply_template_vars', $templates);
}

function encode_letter_templates(?array $posted): string
{
    $out = [];
    foreach ($posted ?? [] as $key => $tpl) {
        if (!is_array($tpl)) {
            continue;
        }
        $title = trim((string) ($tpl['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $key = preg_replace('/[^a-z0-9_]+/', '', strtolower((string) $key)) ?: 'note';
        if (isset($out[$key])) {
            $key .= '_' . substr(bin2hex(random_bytes(2)), 0, 4);
        }
        $heading = trim((string) ($tpl['heading'] ?? ''));
        $subject = trim((string) ($tpl['subject'] ?? ''));
        $out[$key] = [
            'title' => $title,
            'heading' => $heading !== '' ? $heading : strtoupper($title),
            'subject' => $subject !== '' ? $subject : $title,
            'body' => (string) ($tpl['body'] ?? ''),
        ];
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function letter_heading(array $doc): string
{
    $key = $doc['letter_template'] ?? '';
    $templates = letter_templates();
    if ($key && isset($templates[$key])) {
        return $templates[$key]['heading'];
    }
    return $doc['subject'] ?: 'NOTE';
}

function period_range(): array
{
    $preset = $_GET['range'] ?? 'all';
    $from = trim((string) ($_GET['from'] ?? ''));
    $to = trim((string) ($_GET['to'] ?? ''));
    $today = today();
    $monday = date('Y-m-d', strtotime('monday this week'));
    $map = [
        'today' => [$today, $today],
        'this_week' => [$monday, $today],
        'last_week' => [date('Y-m-d', strtotime('monday last week')), date('Y-m-d', strtotime('sunday last week'))],
        'this_month' => [date('Y-m-01'), $today],
        'last_month' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    ];
    if ($from && $to) {
        $preset = 'custom';
    } elseif (isset($map[$preset])) {
        [$from, $to] = $map[$preset];
    } else {
        $preset = 'all';
        $from = '';
        $to = '';
    }
    return ['preset' => $preset, 'from' => $from, 'to' => $to];
}

function period_sql(string $column = 'd.date'): array
{
    $p = period_range();
    if ($p['from'] === '' || $p['to'] === '') {
        return ['', '', []];
    }
    return [" AND {$column} >= ? AND {$column} <= ?", 'ss', [$p['from'], $p['to']]];
}

function render_filters(string $action, array $keep = []): void
{
    $p = period_range();
    $qs = static function (array $extra) use ($keep): string {
        return http_build_query(array_merge($keep, $extra));
    };
    $chips = [
        'all' => 'All',
        'today' => 'Today',
        'this_week' => 'This week',
        'last_week' => 'Last week',
        'this_month' => 'This month',
        'last_month' => 'Last month',
    ];
    ?>
    <form class="filters" method="get" action="<?= h(url($action)) ?>">
      <?php foreach ($keep as $k => $v): ?>
        <input type="hidden" name="<?= h((string) $k) ?>" value="<?= h((string) $v) ?>">
      <?php endforeach; ?>
      <div class="filter-chips">
        <?php foreach ($chips as $key => $label): ?>
          <a class="chip<?= $p['preset'] === $key ? ' is-on' : '' ?>" href="<?= h(url($action . '?' . $qs(['range' => $key, 'from' => '', 'to' => '']))) ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
      </div>
      <label>From <input type="date" name="from" value="<?= h($p['from']) ?>"></label>
      <label>To <input type="date" name="to" value="<?= h($p['to']) ?>"></label>
      <button class="btn ghost sm" type="submit">Apply</button>
    </form>
    <?php
}

function list_documents(string $kind): array
{
    [$extra, $types, $params] = period_sql('d.date');
    $sql = 'SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id
            WHERE d.company_id = ? AND d.kind = ?' . $extra . ' ORDER BY d.date DESC, d.id DESC';
    return attach_document_totals(db_all($sql, 'is' . $types, array_merge([current_company_id(), $kind], $params)));
}

function documents_sum(array $rows, string $field = 'total'): float
{
    $n = 0.0;
    $base = default_currency();
    foreach ($rows as $row) {
        $amt = match ($field) {
            'paid' => (float) ($row['paid'] ?? 0),
            'balance' => (float) ($row['balance'] ?? 0),
            default => (float) ($row['totals']['total'] ?? 0),
        };
        $n += convert_money($amt, doc_currency($row), $base);
    }
    return round_money($n, $base);
}

function export_query(string $type, array $extra = []): string
{
    $p = period_range();
    return url('export.php?' . http_build_query(array_merge([
        'type' => $type,
        'range' => $p['preset'],
        'from' => $p['from'],
        'to' => $p['to'],
    ], $extra)));
}

function csv_download(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function prefix_from_name(string $name): string
{
    $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $name) ?: 'FOL');
    return substr($letters . 'XXX', 0, 3);
}

function product_name(): string
{
    return 'Vellisys';
}

function product_email(): string
{
    return 'info@vellisys.com';
}

function product_phones(): array
{
    return array_column(product_agents(), 'phone');
}

function product_agents(): array
{
    return [
        ['name' => 'Agent 1', 'phone' => '+256 779 971 024'],
        ['name' => 'Agent 2', 'phone' => '+256 756 524 451'],
    ];
}

function phone_digits(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function product_maker_name(): string
{
    return 'FS Digital';
}

function product_maker_url(): string
{
    return 'https://frontstardigital.com';
}

function product_po_box(): string
{
    return 'P.O.Box 202317 Kampala GPO';
}

function old_way_photos(): array
{
    return [
        'assets/img/landing/old-receipt-1.jpg',
        'assets/img/landing/old-receipt-2.jpg',
        'assets/img/landing/old-receipt-3.jpg',
        'assets/img/landing/old-receipt-4.jpg',
        'assets/img/landing/old-receipt-5.jpg',
        'assets/img/landing/old-receipt-6.jpg',
        'assets/img/landing/old-receipt-7.jpg',
    ];
}

function trust_client_defaults(): array
{
    $names = [
        'Ofagros',
        'Kira Estates',
        'Nile Hardware',
        'Jinja Pack',
        'Rwenzori Mills',
        'Gulu Trade',
        'Entebbe Marine',
        'Mbale Grain',
        'Fort Portal Tea',
        'Masaka Dairy',
    ];
    $rows = [];
    foreach ($names as $i => $name) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '');
        $slug = trim((string) $slug, '-');
        $rows[] = [
            'name' => $name,
            'logo_path' => 'assets/img/landing/clients/' . $slug . '.svg',
            'sort' => ($i + 1) * 10,
        ];
    }
    return $rows;
}

function trust_clients(): array
{
    try {
        $rows = db_all('SELECT * FROM trust_clients ORDER BY sort, id');
        if ($rows) {
            return $rows;
        }
    } catch (Throwable $e) {
        // Table may not exist until migrate runs.
    }
    return trust_client_defaults();
}

function public_file_url(string $rel): string
{
    $rel = ltrim($rel, '/');
    $full = $rel !== '' ? ROOT_PATH . '/' . $rel : '';
    if ($full && is_file($full)) {
        return url($rel) . '?v=' . filemtime($full);
    }
    return product_mark_url();
}

function trust_client_logo_url(array $client): string
{
    return public_file_url((string) ($client['logo_path'] ?? ''));
}

function save_uploaded_image(string $field, string $destDir, string $prefix, int $maxBytes = 2_000_000): array
{
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return ['ok' => true, 'path' => null];
    }
    $file = $_FILES[$field];
    if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'That file did not upload. Try again.'];
    }
    if ((int) ($file['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'error' => 'Keep the picture under 2 MB.'];
    }
    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }
    if (!in_array($ext, ['png', 'jpg', 'gif', 'webp', 'svg'], true)) {
        return ['ok' => false, 'error' => 'Use PNG, JPG, WebP, GIF or SVG.'];
    }
    $tmp = (string) $file['tmp_name'];
    if ($ext === 'svg') {
        $raw = (string) file_get_contents($tmp);
        if ($raw === '' || !preg_match('/<svg[\s>]/i', $raw) || preg_match('/<script|javascript:|on\w+\s*=|<foreignObject/i', $raw)) {
            return ['ok' => false, 'error' => 'That SVG is not a safe logo file.'];
        }
    } else {
        $info = @getimagesize($tmp);
        if (!$info) {
            return ['ok' => false, 'error' => 'That file is not a picture.'];
        }
    }
    $dir = ROOT_PATH . '/' . trim($destDir, '/');
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Could not create the upload folder.'];
    }
    $fname = preg_replace('/[^a-z0-9\-]+/i', '-', $prefix) . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    $fname = strtolower(trim((string) $fname, '-'));
    if (!move_uploaded_file($tmp, $dir . '/' . $fname)) {
        return ['ok' => false, 'error' => 'Could not save that picture.'];
    }
    return ['ok' => true, 'path' => trim($destDir, '/') . '/' . $fname];
}

function maybe_unlink_upload(string $rel): void
{
    $rel = ltrim($rel, '/');
    if ($rel === '' || !str_starts_with($rel, 'uploads/')) {
        return;
    }
    $full = ROOT_PATH . '/' . $rel;
    if (is_file($full)) {
        @unlink($full);
    }
}

function gate_art(string $heading, string $lead, string $switchHtml): void
{
    ?>
    <aside class="gate-art">
      <img class="gate-watermark" src="<?= h(asset('img/landing/nw.png')) ?>" alt="">
      <div class="gate-art-inner">
        <a class="lp-brand" href="<?= h(url()) ?>">
          <img class="lp-logo lp-logo-on-dark" src="<?= h(product_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        </a>
        <h1><?= h($heading) ?></h1>
        <p><?= h($lead) ?></p>
        <span class="gate-dots" aria-hidden="true"><i></i><i></i><i></i></span>
      </div>
      <p class="gate-art-foot"><?= $switchHtml ?></p>
      <svg class="gate-wave gate-wave-y" viewBox="0 0 80 900" preserveAspectRatio="none" aria-hidden="true">
        <path d="M80 0 C18 180 18 720 80 900 L80 0 Z" fill="#fff"/>
      </svg>
      <svg class="gate-wave gate-wave-x" viewBox="0 0 400 72" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0 72 C90 8 310 8 400 72 L400 72 L0 72 Z" fill="#fff"/>
      </svg>
    </aside>
    <?php
}

function public_header(string $page = 'home'): void
{
    ?>
  <header class="lp-chrome">
  <div class="lp-nav">
    <a class="lp-brand" href="<?= h(url()) ?>">
      <img class="lp-logo" src="<?= h(product_logo_url()) ?>" alt="<?= h(product_name()) ?>">
    </a>
    <nav>
      <?php if ($page !== 'home'): ?>
        <a class="lp-nav-home" href="<?= h(url()) ?>">Home</a>
      <?php endif; ?>
      <a class="lp-btn lp-btn-ghost" href="<?= h(url('login.php')) ?>">Sign in</a>
      <a class="lp-btn lp-btn-solid" href="<?= h(url('register.php')) ?>">Get a desk</a>
    </nav>
  </div>
  </header>
    <?php
}

function public_footer(): void
{
    ?>
  <footer class="lp-foot">
    <div class="lp-foot-grid">
      <div class="lp-foot-brand">
        <img class="lp-logo lp-logo-on-dark" src="<?= h(product_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <p>Books you can share in one click - branded to each client, with many templates to choose from. A product of <?= h(product_maker_name()) ?>.</p>
      </div>
      <div>
        <h3>Talk to us</h3>
        <p class="lp-foot-line"><?= icon('letter', 18) ?><a href="mailto:<?= h(product_email()) ?>"><?= h(product_email()) ?></a></p>
        <?php foreach (product_agents() as $agent): ?>
          <p class="lp-foot-line"><?= icon('whatsapp', 18) ?><a href="tel:+<?= h(phone_digits($agent['phone'])) ?>"><strong><?= h($agent['name']) ?></strong> <?= h($agent['phone']) ?></a></p>
        <?php endforeach; ?>
        <p class="lp-foot-line"><?= icon('help', 18) ?><a href="<?= h(url()) ?>#ask">Have a question</a></p>
      </div>
      <div>
        <h3>FS Digital</h3>
        <p class="lp-foot-line"><?= icon('pin', 18) ?><span><?= h(product_po_box()) ?></span></p>
        <p class="lp-foot-line"><?= icon('globe', 18) ?><a href="<?= h(product_maker_url()) ?>" rel="noopener">frontstardigital.com</a></p>
      </div>
    </div>
    <p class="lp-copy">© <?= h((string) date('Y')) ?> <?= h(product_name()) ?>. All rights reserved.</p>
  </footer>
  <?php public_float_widgets(); ?>
    <?php
}

function public_float_widgets(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $agents = product_agents();
    $waMark = '<svg class="icon" width="26" height="26" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';
    ?>
  <div class="lp-floats" data-lp-floats>
    <button type="button" class="lp-top" data-lp-top hidden><?= icon('chevron-up', 22) ?><span>Top</span></button>
    <div class="lp-wa" data-lp-wa>
      <div class="lp-wa-panel" id="lp-wa-panel" hidden>
        <div class="lp-wa-head">
          <div>
            <strong>WhatsApp an agent</strong>
            <p>Pick Agent 1 or Agent 2. Chat stays on this page.</p>
          </div>
          <button type="button" class="lp-wa-x" data-lp-wa-close aria-label="Close WhatsApp"><?= icon('x', 18) ?></button>
        </div>
        <div class="lp-wa-home" data-lp-wa-home>
          <?php foreach ($agents as $agent): ?>
            <button type="button" class="lp-wa-agent" data-lp-agent data-name="<?= h($agent['name']) ?>" data-phone="<?= h(phone_digits($agent['phone'])) ?>">
              <span class="lp-wa-ava"><?= h(preg_replace('/\D+/', '', $agent['name']) ?: 'A') ?></span>
              <span>
                <b><?= h($agent['name']) ?></b>
                <em><?= h($agent['phone']) ?></em>
              </span>
            </button>
          <?php endforeach; ?>
        </div>
        <form class="lp-wa-compose" data-lp-wa-compose hidden>
          <p>To <strong data-lp-agent-label>Agent</strong></p>
          <label for="lp-wa-text">Message
            <textarea id="lp-wa-text" data-lp-wa-text rows="4" maxlength="1000">Hello, I have a question about a Vellisys desk.</textarea>
          </label>
          <button class="lp-wa-send" type="submit"><?= icon('whatsapp', 16) ?>Open chat here</button>
          <button class="lp-wa-back" type="button" data-lp-wa-back>All agents</button>
        </form>
      </div>
      <button type="button" class="lp-wa-fab" data-lp-wa-toggle aria-expanded="false" aria-controls="lp-wa-panel" aria-label="WhatsApp an agent"><?= $waMark ?></button>
    </div>
  </div>
  <div class="lp-wa-dock" data-lp-wa-dock hidden>
    <header>
      <strong data-lp-wa-dock-title>WhatsApp</strong>
      <button type="button" class="lp-wa-x" data-lp-wa-dock-close aria-label="Close chat"><?= icon('x', 18) ?></button>
    </header>
    <iframe data-lp-wa-frame title="WhatsApp chat" referrerpolicy="no-referrer"></iframe>
    <p class="lp-wa-note">If the chat stays blank, WhatsApp blocked the panel. Use <button type="button" class="lp-wa-win" data-lp-wa-window>Open WhatsApp in a window</button> - this page stays open.</p>
  </div>
    <?php
}

function product_css_vars(): string
{
    return '--brand:#1E4EFF;--brand-2:#8EB0FF;--brand-3:#08143A'
        . ';--brand-tint:#e8eeff;--brand-2-tint:#eef3ff;--brand-3-tint:#d5dbeb'
        . ';--on-brand:#ffffff;--on-brand-2:#08143A;--on-brand-3:#ffffff'
        . ';--nav:#08143A;--brand-ink:#08143A';
}

function platform_admin_email(): string
{
    return 'admin@vellisys.ug';
}

function platform_admin_password(): string
{
    return 'vellisys-admin-2026';
}

function new_signup_count(): int
{
    try {
        $row = db_one("SELECT COUNT(*) AS c FROM signups WHERE status = 'new'");
        return (int) ($row['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function new_question_count(): int
{
    try {
        $row = db_one("SELECT COUNT(*) AS c FROM questions WHERE status = 'new'");
        return (int) ($row['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function visitor_ip_hash(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $cfg = require ROOT_PATH . '/config/database.php';
    $secret = hash('sha256', ($cfg['name'] ?? 'folio') . '|' . ($cfg['user'] ?? 'root') . '|vellisys-ask');
    return hash('sha256', $ip . '|' . $secret);
}

function landing_faqs(): array
{
    return [
        [
            'q' => 'How do I get a desk?',
            'a' => 'Leave four fields on Register. A Vellisys admin calls you, onboards the company, and issues a login. There is no password to invent on the website.',
        ],
        [
            'q' => 'Are the documents in our branding?',
            'a' => 'Yes. Every quotation, invoice, receipt, expense and headed note uses the company logo, three brand colours, and one of eight templates you pick in Settings.',
        ],
        [
            'q' => 'Can we work in UGX and USD?',
            'a' => 'Each document is UGX or USD. Settings holds the rate (1 USD = n UGX). Debtors and reports convert mixed currencies at that rate.',
        ],
        [
            'q' => 'What are Debtors and Creditors?',
            'a' => 'Debtors lists clients who still owe you after full or part receipts. Creditors lists suppliers you still need to pay. Take a receipt, send a reminder, or record a payment from the row.',
        ],
        [
            'q' => 'How do we send a sheet to a client?',
            'a' => 'Share opens WhatsApp or email with a link to the branded sheet. You can also print or save as PDF from the browser.',
        ],
        [
            'q' => 'Who sees the books?',
            'a' => 'Only users on that company desk. Platform admin can open a desk to help onboard. Clients who receive a share link see that one sheet, not the rest of the books.',
        ],
        [
            'q' => 'How do I ask something the list does not cover?',
            'a' => 'Use the form on this page. A Vellisys admin reads it and replies by email. Do not send passwords or payment details here.',
        ],
    ];
}

function product_mark_url(): string
{
    return asset('img/vellisys-mark.png');
}

function product_logo_url(): string
{
    $path = ROOT_PATH . '/assets/img/our-logo.png';
    if (is_file($path)) {
        return asset('img/our-logo.png');
    }
    return asset('img/vellisys-logo.png');
}

function landing_card_defaults(): array
{
    return [
        ['slot' => 'familiar_1', 'section' => 'familiar', 'sort' => 1, 'image_path' => 'assets/img/landing/landing-receipts.png', 'title' => 'Still stuffing receipts in a drawer?', 'body' => 'Slips, phone photos, part payments in UGX and USD. By month-end you are guessing what is still owed.'],
        ['slot' => 'familiar_2', 'section' => 'familiar', 'sort' => 2, 'image_path' => 'assets/img/landing/landing-whatsapp.png', 'title' => 'Did that invoice vanish into WhatsApp?', 'body' => 'Quotes in email. Invoices in a chat. Nobody has one number for who still owes the company.'],
        ['slot' => 'familiar_3', 'section' => 'familiar', 'sort' => 3, 'image_path' => 'assets/img/landing/landing-office.png', 'title' => 'Can you only open the books at the office?', 'body' => 'If you are on the road, the PC is off, or the accountant is out, the records are out of reach.'],
        ['slot' => 'help_1', 'section' => 'help', 'sort' => 4, 'image_path' => 'assets/img/landing/landing-share.png', 'title' => 'Your client\'s brand. Many templates.', 'body' => 'Every quotation, invoice and receipt is customised to the client\'s logo and colours. Choose from many templates, then email, WhatsApp or print the sheet.'],
        ['slot' => 'help_2', 'section' => 'help', 'sort' => 5, 'image_path' => 'assets/img/landing/landing-anywhere.png', 'title' => 'Open the books from wherever you are.', 'body' => 'Sign in and this month is there - invoices, receipts, expenses, reports - on the screen in front of you.'],
        ['slot' => 'help_3', 'section' => 'help', 'sort' => 6, 'image_path' => 'assets/img/landing/landing-desk.png', 'title' => 'Quotes, invoices, receipts. One desk.', 'body' => 'Pick a template once. The whole books print in that layout, in the company colours. Quotations convert to invoices. Invoices take full or part receipts.'],
        ['slot' => 'steps_1', 'section' => 'steps', 'sort' => 7, 'image_path' => 'assets/img/landing/landing-form.png', 'title' => 'Leave your details', 'body' => 'Name, company, email, phone. That is the whole form. No password to invent.'],
        ['slot' => 'steps_2', 'section' => 'steps', 'sort' => 8, 'image_path' => 'assets/img/landing/landing-call.png', 'title' => 'We call you', 'body' => 'A Vellisys admin sees the sign-up and reaches out to onboard your company.'],
        ['slot' => 'steps_3', 'section' => 'steps', 'sort' => 9, 'image_path' => 'assets/img/landing/landing-live.png', 'title' => 'Your desk goes live', 'body' => 'You get a login. The books are yours, on any device, any time.'],
    ];
}

function landing_cards(string $section = ''): array
{
    try {
        if ($section !== '') {
            return db_all('SELECT * FROM landing_cards WHERE section = ? ORDER BY sort, id', 's', [$section]);
        }
        return db_all('SELECT * FROM landing_cards ORDER BY sort, id');
    } catch (Throwable $e) {
        $rows = landing_card_defaults();
        if ($section === '') {
            return $rows;
        }
        return array_values(array_filter($rows, static fn ($r) => $r['section'] === $section));
    }
}

function landing_card_image_url(array $card): string
{
    $rel = (string) ($card['image_path'] ?? '');
    $full = $rel !== '' ? ROOT_PATH . '/' . ltrim($rel, '/') : '';
    if ($full && is_file($full)) {
        return url(ltrim($rel, '/')) . '?v=' . filemtime($full);
    }
    foreach (landing_card_defaults() as $d) {
        if ($d['slot'] === ($card['slot'] ?? '')) {
            return asset(substr($d['image_path'], strlen('assets/')));
        }
    }
    return product_mark_url();
}

function product_icons(): void
{
    echo '<link rel="icon" type="image/png" href="' . h(product_mark_url()) . '">';
    echo '<link rel="apple-touch-icon" href="' . h(product_mark_url()) . '">';
}

function folio_css_links(): void
{
    echo '<link rel="stylesheet" href="' . h(asset('css/app.css')) . '">';
    echo '<link rel="stylesheet" href="' . h(asset('css/designs.css')) . '">';
}

function folio_font_links(): void
{
    echo '<link rel="preload" href="' . h(asset('fonts/montserrat-400.woff2')) . '" as="font" type="font/woff2" crossorigin>';
    echo '<link rel="preload" href="' . h(asset('fonts/montserrat-600.woff2')) . '" as="font" type="font/woff2" crossorigin>';
    echo '<link rel="preload" href="' . h(asset('fonts/montserrat-700.woff2')) . '" as="font" type="font/woff2" crossorigin>';
}
