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
    return ['UGX' => 'UGX — Uganda shilling', 'USD' => 'USD — US dollar'];
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

function brand_color(): string
{
    $c = branding()['brand_color'] ?? '#82B440';
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $c) ? $c : '#82B440';
}

function logo_url(): string
{
    $path = branding()['logo_path'] ?? 'assets/img/ofagros-logo.png';
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
    return (int) ($_SESSION['company_id'] ?? 0);
}

function is_platform(?array $user = null): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    return ($user['role'] ?? '') === 'platform';
}

function letter_templates(): array
{
    $company = branding()['name'] ?? 'the company';
    return [
        'demand' => [
            'title' => 'Demand for payment',
            'heading' => 'DEMAND FOR PAYMENT',
            'subject' => 'Demand for payment',
            'body' => "Dear Sir / Madam,\n\nWe write in respect of amounts that remain unpaid on your account with {$company}. Kindly settle the balance within seven (7) days of this note.\n\nIf payment has already been made, please send the reference so we may update our books.\n\nYours faithfully,\nAccounts\n{$company}",
        ],
        'covering' => [
            'title' => 'Covering note',
            'heading' => 'COVERING NOTE',
            'subject' => 'Documents enclosed',
            'body' => "Dear Sir / Madam,\n\nPlease find enclosed the documents listed below. Kindly acknowledge receipt.\n\nYours faithfully,\nAccounts\n{$company}",
        ],
        'appointment' => [
            'title' => 'Appointment',
            'heading' => 'APPOINTMENT',
            'subject' => 'Confirmation of appointment',
            'body' => "Dear Sir / Madam,\n\nThis confirms our appointment as agreed. Please let us know if the date or time needs to change.\n\nYours faithfully,\nAccounts\n{$company}",
        ],
        'credit' => [
            'title' => 'Credit and goodwill',
            'heading' => 'CREDIT NOTE',
            'subject' => 'Credit on your account',
            'body' => "Dear Sir / Madam,\n\nWe have credited your account as a gesture of goodwill / in correction of the items discussed. The credit will appear on your next statement.\n\nYours faithfully,\nAccounts\n{$company}",
        ],
        'notice' => [
            'title' => 'Overdue notice',
            'heading' => 'OVERDUE NOTICE',
            'subject' => 'Account overdue',
            'body' => "Dear Sir / Madam,\n\nYour account with {$company} is now overdue. Please arrange payment at once to avoid interruption of supply.\n\nYours faithfully,\nAccounts\n{$company}",
        ],
    ];
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
    foreach ($rows as $row) {
        $n += match ($field) {
            'paid' => (float) ($row['paid'] ?? 0),
            'balance' => (float) ($row['balance'] ?? 0),
            default => (float) ($row['totals']['total'] ?? 0),
        };
    }
    return $n;
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

function folio_font_links(): void
{
    echo '<link rel="preload" href="' . h(asset('fonts/montserrat-400.woff2')) . '" as="font" type="font/woff2" crossorigin>';
    echo '<link rel="preload" href="' . h(asset('fonts/montserrat-600.woff2')) . '" as="font" type="font/woff2" crossorigin>';
}
