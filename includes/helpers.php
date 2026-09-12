<?php
declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function normalize_currency(string $code, ?string $fallback = null): string
{
    $code = strtoupper(preg_replace('/[^A-Za-z]/', '', $code) ?? '');
    if (strlen($code) === 3) {
        return $code;
    }
    $fallback = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $fallback) ?? '');
    return strlen($fallback) === 3 ? $fallback : 'USD';
}

function posted_currency(string $key, ?string $fallback = null): string
{
    return normalize_currency(post($key), $fallback);
}

function currency_decimals(string $currency): int
{
    $currency = normalize_currency($currency, 'USD');
    $zero = ['BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
    return in_array($currency, $zero, true) ? 0 : 2;
}

function money($amount, ?string $currency = null): string
{
    $currency = normalize_currency((string) ($currency ?: default_currency()), default_currency());
    $n = (float) $amount;
    $dec = currency_decimals($currency);
    if ($dec === 0 && $n != floor($n)) {
        $dec = 2;
    }
    return $currency . ' ' . number_format($n, $dec, '.', ',');
}

function fx_ugx_per_usd(): float
{
    $n = (float) (branding()['fx_ugx_per_usd'] ?? 1);
    return $n > 0 ? $n : 1.0;
}

function fx_home_per_usd(?array $brand = null): float
{
    if ($brand) {
        $n = (float) ($brand['fx_ugx_per_usd'] ?? 0);
        return $n > 0 ? $n : 1.0;
    }
    return fx_ugx_per_usd();
}

function fx_rate_label(?float $rate = null, ?string $home = null): string
{
    $rate = $rate ?? fx_home_per_usd();
    $home = normalize_currency((string) ($home ?: default_currency()), 'USD');
    $dec = $rate == floor($rate) ? 0 : 2;
    return number_format($rate, $dec, '.', ',') . ' ' . $home . ' / USD';
}

function parse_fx_rate(string $raw): float
{
    $n = money_parse($raw);
    return $n > 0 ? $n : fx_home_per_usd();
}

function other_currency(string $currency, ?string $home = null): string
{
    $home = normalize_currency((string) ($home ?: default_currency()), 'USD');
    $currency = normalize_currency($currency, $home);
    return $currency === 'USD' ? $home : 'USD';
}

function round_money(float $amount, string $currency): float
{
    $dec = currency_decimals($currency);
    return round($amount, $dec);
}

function convert_money(float $amount, string $from, string $to, ?float $rate = null, ?string $home = null): float
{
    $home = normalize_currency((string) ($home ?: default_currency()), 'USD');
    $from = normalize_currency($from, $home);
    $to = normalize_currency($to, $home);
    if ($from === $to) {
        return round_money($amount, $to);
    }
    $rate = $rate ?? fx_home_per_usd();
    if ($rate <= 0) {
        $rate = 1.0;
    }
    $usd = $from === 'USD' ? $amount : $amount / $rate;
    if ($to === 'USD') {
        return round_money($usd, 'USD');
    }
    return round_money($usd * $rate, $to);
}

function money_pair($amount, string $currency, ?float $rate = null, ?string $home = null): string
{
    $home = normalize_currency((string) ($home ?: default_currency()), 'USD');
    $currency = normalize_currency($currency, $home);
    $alt = other_currency($currency, $home);
    return money($amount, $currency) . ' · ' . money(convert_money((float) $amount, $currency, $alt, $rate, $home), $alt);
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
    return normalize_currency((string) (branding()['currency'] ?? ''), 'UGX');
}

function doc_currency(?array $doc = null): string
{
    if ($doc && !empty($doc['currency'])) {
        return normalize_currency((string) $doc['currency'], default_currency());
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
    return [
        'USD' => 'US dollar',
        'EUR' => 'Euro',
        'GBP' => 'Pound sterling',
        'KES' => 'Kenyan shilling',
        'UGX' => 'Ugandan shilling',
        'TZS' => 'Tanzanian shilling',
        'RWF' => 'Rwandan franc',
        'NGN' => 'Nigerian naira',
        'GHS' => 'Ghanaian cedi',
        'ZAR' => 'South African rand',
        'AED' => 'UAE dirham',
        'SAR' => 'Saudi riyal',
        'INR' => 'Indian rupee',
        'CAD' => 'Canadian dollar',
        'AUD' => 'Australian dollar',
        'CHF' => 'Swiss franc',
        'JPY' => 'Japanese yen',
        'CNY' => 'Chinese yuan',
        'SGD' => 'Singapore dollar',
        'HKD' => 'Hong Kong dollar',
        'NZD' => 'New Zealand dollar',
        'SEK' => 'Swedish krona',
        'NOK' => 'Norwegian krone',
        'DKK' => 'Danish krone',
        'PLN' => 'Polish zloty',
        'BRL' => 'Brazilian real',
        'MXN' => 'Mexican peso',
        'EGP' => 'Egyptian pound',
        'MAD' => 'Moroccan dirham',
        'ZMW' => 'Zambian kwacha',
        'MWK' => 'Malawian kwacha',
        'BWP' => 'Botswana pula',
        'NAD' => 'Namibian dollar',
        'MUR' => 'Mauritian rupee',
        'ETB' => 'Ethiopian birr',
        'XOF' => 'West African CFA franc',
        'XAF' => 'Central African CFA franc',
    ];
}

function currency_field(string $id, string $name, string $value, array $attrs = []): void
{
    $value = normalize_currency($value, 'USD');
    $known = currencies();
    $isKnown = isset($known[$value]);
    $extra = '';
    foreach ($attrs as $k => $v) {
        if ($v === true) {
            $extra .= ' ' . $k;
        } elseif ($v !== false && $v !== null && $v !== '') {
            $extra .= ' ' . $k . '="' . h((string) $v) . '"';
        }
    }
    echo '<div class="currency-pick" data-currency-pick>';
    echo '<select class="currency-pick-list" id="' . h($id) . '-pick" data-currency-select aria-label="Choose a currency">';
    foreach ($known as $code => $label) {
        $sel = $isKnown && $value === $code ? ' selected' : '';
        echo '<option value="' . h($code) . '"' . $sel . '>' . h($code . ' · ' . $label) . '</option>';
    }
    echo '<option value="other"' . ($isKnown ? '' : ' selected') . '>Other — type a primary currency</option>';
    echo '</select>';
    echo '<label class="currency-custom-label" for="' . h($id) . '">Primary currency</label>';
    echo '<input class="currency-code" id="' . h($id) . '" name="' . h($name) . '" maxlength="3" spellcheck="false" autocomplete="off" value="' . h($value) . '" placeholder="KES" data-currency-custom' . $extra . '>';
    echo '</div>';
}

function currency_unit_name(string $currency): string
{
    $currency = normalize_currency($currency, 'USD');
    $names = [
        'USD' => 'dollars',
        'EUR' => 'euros',
        'GBP' => 'pounds',
        'KES' => 'Kenya shillings',
        'UGX' => 'Uganda shillings',
        'TZS' => 'Tanzania shillings',
        'RWF' => 'Rwanda francs',
        'NGN' => 'naira',
        'GHS' => 'cedis',
        'ZAR' => 'rand',
        'AED' => 'dirhams',
        'INR' => 'rupees',
        'JPY' => 'yen',
        'CHF' => 'francs',
        'CAD' => 'Canadian dollars',
        'AUD' => 'Australian dollars',
        'CNY' => 'yuan',
        'XOF' => 'CFA francs',
        'XAF' => 'CFA francs',
    ];
    return $names[$currency] ?? strtolower($currency);
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
            'name' => 'Estate panel',
            'blurb' => 'Deep header band, solid accent bar, harvest paper - no fade, no wash.',
        ],
        'night' => [
            'name' => 'Harbour block',
            'blurb' => 'Solid deep header and a flat accent rule. Built for dusk print.',
        ],
        'atelier' => [
            'name' => 'Atelier',
            'blurb' => 'Quiet studio letter. Thin rules, small caps - made to email.',
        ],
        'seal' => [
            'name' => 'Company seal',
            'blurb' => 'Centered mark and double hairline. Formal, for quotations and retainers.',
        ],
        'mark' => [
            'name' => 'Logo watermark',
            'blurb' => 'White sheet with the company logo faint in the centre of every page.',
        ],
        'bond' => [
            'name' => 'Bond watermark',
            'blurb' => 'Cream bond paper with a large tilted logo mark behind the lines.',
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
    $currency = normalize_currency((string) ($currency ?: default_currency()), default_currency());
    $n = round(abs((float) $amount), 2);
    $whole = (int) floor($n);
    $frac = (int) round(($n - $whole) * 100);
    $unit = currency_unit_name($currency);
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
    if (preg_match('#^https?://#i', $path)) {
        header('Location: ' . $path);
        exit;
    }
    header('Location: ' . url($path));
    exit;
}

function folio_redirect_then(string $path, callable $after): never
{
    $location = preg_match('#^https?://#i', $path) ? $path : url($path);
    session_write_close();
    ignore_user_abort(true);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $location, true, 303);
    header('Content-Length: 0');
    header('Connection: close');
    echo '';
    if (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    } elseif (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        flush();
    }
    try {
        $after();
    } catch (Throwable $e) {
        error_log('Vellisys after-redirect: ' . $e->getMessage());
    }
    exit;
}

function post(string $key, string $default = '', int $max = 4000): string
{
    $value = str_replace("\0", '', (string) ($_POST[$key] ?? $default));
    $value = trim($value);
    if ($max > 0 && mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }
    return $value;
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function form_honeypot_field(): string
{
    return '<div class="lp-hp" aria-hidden="true">'
        . '<label>Website<input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label>'
        . '<label>Fax<input type="text" name="company_fax" value="" tabindex="-1" autocomplete="off"></label>'
        . '</div>';
}

function form_mark_open(string $form): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        return;
    }
    if (!isset($_SESSION['form_at']) || !is_array($_SESSION['form_at'])) {
        $_SESSION['form_at'] = [];
    }
    $_SESSION['form_at'][$form] = time();
}

function form_is_spam(string $form, int $minSeconds = 2): bool
{
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        return true;
    }
    if (trim((string) ($_POST['company_fax'] ?? '')) !== '') {
        return true;
    }
    if ($minSeconds <= 0) {
        return false;
    }
    $started = (int) (($_SESSION['form_at'][$form] ?? 0));
    if ($form === 'ask' && $started <= 0) {
        $started = (int) ($_SESSION['ask_form_at'] ?? 0);
    }
    return $started <= 0 || (time() - $started) < $minSeconds;
}

function join_fields_look_like_spam(): bool
{
    $name = trim(post('contact_name', '', 80));
    $company = trim(post('company_name', '', 160));
    $email = strtolower(trim(post('contact_email', '', 190)));
    $note = trim(post('join_note', '', 800));
    $blob = $name . "\n" . $company . "\n" . $note;
    if (preg_match('/https?:\/\/|www\.|\bbit\.ly\b|\btinyurl\b/i', $name . $company) === 1) {
        return true;
    }
    if (preg_match_all('/https?:\/\//i', $blob) >= 1) {
        return true;
    }
    if (preg_match('/\[url\s*=|href\s*=/i', $blob) === 1) {
        return true;
    }
    if ($email === '') {
        return false;
    }
    $at = strrpos($email, '@');
    $domain = $at === false ? '' : substr($email, $at + 1);
    if ($domain === '' || !str_contains($domain, '.') || str_starts_with($domain, '.') || str_ends_with($domain, '.')) {
        return true;
    }
    $host = strtolower($domain);
    $disposable = [
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', '10minutemail.com',
        'tempmail.com', 'temp-mail.org', 'trashmail.com', 'yopmail.com', 'sharklasers.com',
        'getnada.com', 'dispostable.com', 'moakt.com', 'throwawaymail.com',
    ];
    foreach ($disposable as $bad) {
        if ($host === $bad || str_ends_with($host, '.' . $bad)) {
            return true;
        }
    }
    if (preg_match('/(.)\1{8,}/', $name . $company) === 1) {
        return true;
    }
    return false;
}

function form_rate_blocked(string $bucket, int $max, int $seconds = 3600): bool
{
    $now = time();
    if (!isset($_SESSION['rate']) || !is_array($_SESSION['rate'])) {
        $_SESSION['rate'] = [];
    }
    $slot = $_SESSION['rate'][$bucket] ?? ['at' => $now, 'n' => 0];
    if ((int) ($slot['at'] ?? 0) < $now - $seconds) {
        $slot = ['at' => $now, 'n' => 0];
        $_SESSION['rate'][$bucket] = $slot;
    }
    if ((int) ($slot['n'] ?? 0) >= $max) {
        return true;
    }
    return folio_ip_rate_count($bucket, $seconds) >= $max;
}

function form_rate_hit(string $bucket, int $seconds = 3600): void
{
    $now = time();
    if (!isset($_SESSION['rate']) || !is_array($_SESSION['rate'])) {
        $_SESSION['rate'] = [];
    }
    $slot = $_SESSION['rate'][$bucket] ?? ['at' => $now, 'n' => 0];
    if ((int) ($slot['at'] ?? 0) < $now - $seconds) {
        $slot = ['at' => $now, 'n' => 0];
    }
    $slot['n'] = (int) ($slot['n'] ?? 0) + 1;
    $slot['at'] = (int) ($slot['at'] ?? $now);
    $_SESSION['rate'][$bucket] = $slot;
    folio_ip_rate_hit($bucket, $seconds);
}

function folio_rate_dir(): string
{
    $dir = rtrim(sys_get_temp_dir(), '/\\') . '/vellisys-rate';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

function folio_ip_rate_file(string $bucket): string
{
    $safe = preg_replace('/[^a-z0-9_-]/i', '', $bucket) ?: 'form';
    return folio_rate_dir() . '/' . $safe . '_' . visitor_ip_hash() . '.json';
}

function folio_ip_rate_times(string $bucket, int $seconds): array
{
    $file = folio_ip_rate_file($bucket);
    $now = time();
    $hits = [];
    if (is_file($file)) {
        $decoded = json_decode((string) @file_get_contents($file), true);
        if (is_array($decoded)) {
            foreach ($decoded as $stamp) {
                $t = (int) $stamp;
                if ($t > $now - $seconds) {
                    $hits[] = $t;
                }
            }
        }
    }
    return $hits;
}

function folio_ip_rate_count(string $bucket, int $seconds): int
{
    return count(folio_ip_rate_times($bucket, $seconds));
}

function folio_ip_rate_hit(string $bucket, int $seconds = 3600): void
{
    $hits = folio_ip_rate_times($bucket, $seconds);
    $hits[] = time();
    @file_put_contents(folio_ip_rate_file($bucket), json_encode($hits), LOCK_EX);
}

function folio_cache_dir(): string
{
    $dir = rtrim(sys_get_temp_dir(), '/\\') . '/vellisys-cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

function folio_remember(string $key, callable $fill, int $ttl = 90): mixed
{
    static $mem = [];
    if (array_key_exists($key, $mem)) {
        return $mem[$key];
    }
    $file = folio_cache_dir() . '/' . hash('sha256', $key) . '.ser';
    if (is_file($file) && filemtime($file) > time() - $ttl) {
        $raw = @file_get_contents($file);
        if (is_string($raw) && $raw !== '') {
            $val = @unserialize($raw, ['allowed_classes' => false]);
            if ($val !== false || $raw === 'b:0;') {
                $mem[$key] = $val;
                return $val;
            }
        }
    }
    $val = $fill();
    $mem[$key] = $val;
    @file_put_contents($file, serialize($val), LOCK_EX);
    return $val;
}

function folio_cache_bust(): void
{
    $dir = folio_cache_dir();
    foreach (glob($dir . '/*.ser') ?: [] as $file) {
        @unlink($file);
    }
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
    $path = ltrim((string) ($brand['logo_path'] ?? 'assets/img/ofagros-logo.png'), '/');
    foreach ([$path, 'assets/img/ofagros-logo.png', 'assets/img/ofagros-logo.svg'] as $rel) {
        $full = ROOT_PATH . '/' . $rel;
        if ($rel !== '' && is_file($full)) {
            return url($rel) . '?v=' . filemtime($full);
        }
    }
    return product_mark_url();
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

function csrf_valid(): bool
{
    $token = (string) ($_POST['csrf'] ?? '');
    $expect = (string) ($_SESSION['csrf'] ?? '');
    return $token !== '' && $expect !== '' && hash_equals($expect, $token);
}

function csrf_check(): void
{
    if (!csrf_valid()) {
        http_response_code(400);
        exit('Invalid session. Refresh and try again.');
    }
}

function record_website_signup(string $source, string $note = ''): array
{
    $name = post('contact_name', '', 80);
    $company = post('company_name', '', 160);
    $email = strtolower(post('contact_email', '', 190));
    $phone = post('contact_phone', '', 40);
    $note = mb_substr($note, 0, 2000);
    if ($name === '' || $company === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') {
        return ['ok' => false, 'error' => 'Your name, company, email and phone are enough - please fill those in.'];
    }
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) < 8 || strlen($digits) > 15) {
        return ['ok' => false, 'error' => 'Please enter a working phone number, including the country code if you can.'];
    }
    try {
        if (db_one('SELECT id FROM users WHERE email = ?', 's', [$email])) {
            return ['ok' => false, 'error' => 'That email already has a Vellisys login. Sign in, or use another mailbox.'];
        }
        if (db_one("SELECT id FROM signups WHERE email = ? AND status IN ('new','contacted')", 's', [$email])) {
            return ['ok' => false, 'error' => 'We already have this request. A Vellisys admin will call you.'];
        }
        $kind = in_array($source, ['quote', 'demo', 'register', 'checkout'], true) ? $source : 'register';
        try {
            db_exec(
                'INSERT INTO signups (name, company, email, phone, status, source, note) VALUES (?,?,?,?,?,?,?)',
                'sssssss',
                [$name, $company, $email, $phone, 'new', $kind, $note]
            );
        } catch (Throwable $e) {
            try {
                db_exec(
                    'INSERT INTO signups (name, company, email, phone, status, source) VALUES (?,?,?,?,?,?)',
                    'ssssss',
                    [$name, $company, $email, $phone, 'new', $kind]
                );
            } catch (Throwable $e2) {
                db_exec(
                    'INSERT INTO signups (name, company, email, phone, status) VALUES (?,?,?,?,?)',
                    'sssss',
                    [$name, $company, $email, $phone, 'new']
                );
            }
        }
    } catch (Throwable $e) {
        error_log('Vellisys signup save: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'We could not save that just now. Please try again in a moment.'];
    }
    return [
        'ok' => true,
        'signup' => [
            'name' => $name,
            'company' => $company,
            'email' => $email,
            'phone' => $phone,
            'source' => in_array($source, ['quote', 'demo', 'register', 'checkout'], true) ? $source : 'register',
            'note' => $note,
        ],
    ];
}

function take_pending_signup_mail(): ?array
{
    $signup = $_SESSION['signup_notify'] ?? null;
    unset($_SESSION['signup_notify']);
    return is_array($signup) ? $signup : null;
}

function send_pending_signup_mail(?array $signup): void
{
    if ($signup === null) {
        return;
    }
    session_write_close();
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
    if (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    } elseif (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    try {
        notify_admin_signup($signup);
    } catch (Throwable $e) {
        error_log('Vellisys signup mail: ' . $e->getMessage());
    }
}

function selectable_document_kinds(): array
{
    return [
        'quotation' => 'Quotations',
        'invoice' => 'Invoices',
        'receipt' => 'Receipts',
        'delivery' => 'Delivery notes',
        'letter' => 'Headed letters',
        'custom' => 'Custom documents',
    ];
}

function default_enabled_kinds(): array
{
    return ['quotation', 'invoice', 'receipt', 'letter'];
}

function parse_enabled_kinds(mixed $raw): array
{
    if (is_array($raw)) {
        $list = $raw;
    } else {
        $list = json_decode((string) $raw, true);
    }
    if (!is_array($list) || $list === []) {
        return default_enabled_kinds();
    }
    $allow = array_keys(selectable_document_kinds());
    $out = [];
    foreach ($list as $k) {
        $k = (string) $k;
        if (in_array($k, $allow, true) && !in_array($k, $out, true)) {
            $out[] = $k;
        }
    }
    return $out ?: default_enabled_kinds();
}

function company_enabled_kinds(?array $company = null): array
{
    $company = $company ?? current_company();
    return parse_enabled_kinds($company['enabled_kinds'] ?? '');
}

function company_allows_kind(string $kind, ?array $company = null): bool
{
    if ($kind === 'expense') {
        return true;
    }
    return in_array($kind, company_enabled_kinds($company), true);
}

function posted_enabled_kinds(): string
{
    $posted = $_POST['enabled_kinds'] ?? [];
    if (!is_array($posted)) {
        $posted = [];
    }
    return json_encode(parse_enabled_kinds($posted), JSON_UNESCAPED_UNICODE);
}

function default_custom_doc(): array
{
    return [
        'title' => 'Custom document',
        'has_body' => true,
        'fields' => [],
    ];
}

function parse_custom_doc(mixed $raw): array
{
    $data = is_array($raw) ? $raw : json_decode((string) $raw, true);
    $base = default_custom_doc();
    if (!is_array($data)) {
        return $base;
    }
    $title = trim((string) ($data['title'] ?? $base['title']));
    $base['title'] = $title !== '' ? mb_substr($title, 0, 80) : $base['title'];
    $base['has_body'] = !empty($data['has_body']);
    $fields = [];
    foreach ((array) ($data['fields'] ?? []) as $i => $field) {
        if (is_string($field)) {
            $label = trim($field);
            $key = '';
        } else {
            $label = trim((string) ($field['label'] ?? ''));
            $key = trim((string) ($field['key'] ?? ''));
        }
        if ($label === '') {
            continue;
        }
        if ($key === '') {
            $key = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $label), '_'));
        }
        if ($key === '') {
            $key = 'field_' . $i;
        }
        $fields[] = ['key' => mb_substr($key, 0, 40), 'label' => mb_substr($label, 0, 80)];
    }
    $base['fields'] = $fields;
    return $base;
}

function company_custom_doc(?array $company = null): array
{
    $company = $company ?? current_company();
    return parse_custom_doc($company['custom_doc'] ?? '');
}

function posted_custom_doc(): string
{
    $fields = [];
    foreach ((array) ($_POST['custom_field_label'] ?? []) as $i => $label) {
        $fields[] = [
            'key' => trim((string) (($_POST['custom_field_key'][$i] ?? ''))),
            'label' => trim((string) $label),
        ];
    }
    return json_encode(parse_custom_doc([
        'title' => post('custom_doc_title'),
        'has_body' => !empty($_POST['custom_doc_has_body']),
        'fields' => $fields,
    ]), JSON_UNESCAPED_UNICODE);
}

function desk_kind_list(): array
{
    return ['quotation', 'invoice', 'receipt', 'expense', 'letter', 'delivery', 'custom'];
}

function kind_meta(string $kind): array
{
    if ($kind === 'custom') {
        $title = company_custom_doc()['title'];
        return ['title' => $title, 'singular' => $title, 'heading' => strtoupper($title), 'verb' => 'New ' . strtolower($title)];
    }
    return match ($kind) {
        'quotation' => ['title' => 'Quotations', 'singular' => 'Quotation', 'heading' => 'QUOTATION', 'verb' => 'New quotation'],
        'invoice' => ['title' => 'Invoices', 'singular' => 'Invoice', 'heading' => 'INVOICE', 'verb' => 'New invoice'],
        'receipt' => ['title' => 'Receipts', 'singular' => 'Receipt', 'heading' => 'RECEIPT', 'verb' => 'New receipt'],
        'delivery' => ['title' => 'Delivery notes', 'singular' => 'Delivery note', 'heading' => 'DELIVERY NOTE', 'verb' => 'New delivery note'],
        'expense' => ['title' => 'Expenses', 'singular' => 'Expense', 'heading' => 'EXPENSE', 'verb' => 'Record expense'],
        'letter' => ['title' => 'Correspondence', 'singular' => 'Letter', 'heading' => '', 'verb' => 'New letter'],
        default => ['title' => 'Documents', 'singular' => 'Document', 'heading' => 'DOCUMENT', 'verb' => 'New'],
    };
}

function document_kind_icon(string $kind): string
{
    return match ($kind) {
        'delivery' => 'truck',
        'custom' => 'file',
        default => $kind,
    };
}

function kind_shows_money(string $kind): bool
{
    return !in_array($kind, ['letter', 'custom', 'delivery'], true);
}

function kind_is_stationery(string $kind): bool
{
    return in_array($kind, ['letter', 'custom'], true);
}

function kind_uses_lines(string $kind): bool
{
    return !kind_is_stationery($kind);
}

function kind_nav_label(string $kind): string
{
    if ($kind === 'custom') {
        return company_custom_doc()['title'];
    }
    if ($kind === 'expense') {
        return 'Expenses';
    }
    if ($kind === 'letter') {
        return 'Correspondence';
    }
    return selectable_document_kinds()[$kind] ?? kind_meta($kind)['title'];
}

function desk_primary_kind(?array $company = null): string
{
    $enabled = company_enabled_kinds($company);
    foreach (['invoice', 'quotation', 'receipt', 'delivery', 'letter', 'custom'] as $k) {
        if (in_array($k, $enabled, true)) {
            return $k;
        }
    }
    return 'expense';
}

function require_desk_kind(string $kind): void
{
    if (!company_allows_kind($kind) && $kind !== 'expense') {
        flash('This desk does not use that document.', 'err');
        redirect('dashboard.php');
    }
    if (function_exists('user_can_kind') && !user_can_kind($kind)) {
        flash('Your login cannot open that document.', 'err');
        redirect('dashboard.php');
    }
}

function desk_kind_nav_items(): array
{
    $enabled = company_enabled_kinds();
    $order = ['quotation', 'invoice', 'receipt', 'delivery', 'expense', 'letter', 'custom'];
    $out = [];
    foreach ($order as $kind) {
        if ($kind !== 'expense' && !in_array($kind, $enabled, true)) {
            continue;
        }
        if (function_exists('user_can_kind') && !user_can_kind($kind)) {
            continue;
        }
        $out[] = ['documents.php?kind=' . $kind, kind_nav_label($kind), document_kind_icon($kind), $kind];
    }
    return $out;
}

function party_place_line(array $src): string
{
    $city = trim((string) ($src['city'] ?? $src['party_city'] ?? ''));
    $country = trim((string) ($src['country'] ?? $src['party_country'] ?? ''));
    return trim($city . ($city !== '' && $country !== '' ? ', ' : '') . $country);
}

function posted_party_contacts(): array
{
    return [
        'contact_person' => post('contact_person') ?: null,
        'phone' => post('phone') ?: null,
        'phone2' => post('phone2') ?: null,
        'email' => post('email') ?: null,
        'address' => post('address') ?: null,
        'city' => post('city') ?: null,
        'country' => post('country') ?: null,
        'notes' => post('party_notes') ?: null,
    ];
}

function render_desk_kinds_fields(?array $company = null): void
{
    $enabled = $company ? company_enabled_kinds($company) : default_enabled_kinds();
    $custom = $company ? company_custom_doc($company) : default_custom_doc();
    $fields = $custom['fields'];
    while (count($fields) < 2) {
        $fields[] = ['key' => '', 'label' => ''];
    }
    $customOn = in_array('custom', $enabled, true);
    ?>
    <fieldset class="kinds-pick" data-kinds-form>
      <legend>Documents this desk uses</legend>
      <p class="hint">Tick only what this company needs. Expenses stay on for creditors. Custom documents are not letters - they get their own fields.</p>
      <div class="kinds-grid">
        <?php foreach (selectable_document_kinds() as $key => $label): ?>
          <label class="kinds-opt">
            <input type="checkbox" name="enabled_kinds[]" value="<?= h($key) ?>" <?= in_array($key, $enabled, true) ? 'checked' : '' ?> <?= $key === 'custom' ? 'data-custom-kind' : '' ?>>
            <span><?= h($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="custom-doc-box" data-custom-doc <?= $customOn ? '' : 'hidden' ?>>
        <h3>Custom document</h3>
        <p class="hint">A form the company fills on branded paper - job cards, work orders, certificates. Not a headed letter.</p>
        <label for="custom_doc_title">Title on the desk</label>
        <input id="custom_doc_title" name="custom_doc_title" value="<?= h($custom['title']) ?>" placeholder="Work order">
        <label class="kinds-opt" style="margin:10px 0">
          <input type="checkbox" name="custom_doc_has_body" value="1" <?= !empty($custom['has_body']) ? 'checked' : '' ?>>
          <span>Include a free-text body</span>
        </label>
        <p class="hint">Add labelled fields the company will fill on each document (job number, vehicle, site…).</p>
        <div class="custom-fields" data-custom-fields>
          <?php foreach ($fields as $i => $field): ?>
            <div class="custom-field-row">
              <input name="custom_field_label[]" value="<?= h($field['label']) ?>" placeholder="Field label">
              <input name="custom_field_key[]" value="<?= h($field['key']) ?>" placeholder="key (optional)">
            </div>
          <?php endforeach; ?>
        </div>
        <button class="btn ghost sm" type="button" data-add-custom-field><?= icon('plus', 14) ?>Add field</button>
      </div>
    </fieldset>
    <?php
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

function current_company(): ?array
{
    static $cache = [];
    $id = current_company_id();
    if ($id <= 0) {
        return null;
    }
    if (!array_key_exists($id, $cache)) {
        $cache[$id] = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$id]);
    }
    return $cache[$id];
}

function desk_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Africa/Kampala'));
}

function is_platform(?array $user = null): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    return ($user['role'] ?? '') === 'platform';
}

function clamp_user_limit(int $n): int
{
    return max(1, min(3, $n));
}

function company_user_limit(?array $company = null): int
{
    $company = $company ?? current_company();
    return clamp_user_limit((int) ($company['user_limit'] ?? 3));
}

function company_seat_count(int $companyId): int
{
    $row = db_one("SELECT COUNT(*) AS c FROM users WHERE company_id = ? AND role <> 'platform'", 'i', [$companyId]);
    return (int) ($row['c'] ?? 0);
}

function desk_access_label(string $role, string $access = 'books'): string
{
    if ($role === 'admin' || $access === 'admin') {
        return 'Company admin';
    }
    $levels = function_exists('desk_staff_access_levels') ? desk_staff_access_levels() : [];
    return (string) ($levels[$access]['label'] ?? 'Books');
}

function create_desk_user(int $companyId, array $fields): array
{
    $company = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$companyId]);
    if (!$company) {
        return ['ok' => false, 'error' => 'Company not found.'];
    }
    $name = trim((string) ($fields['name'] ?? ''));
    $email = strtolower(trim((string) ($fields['email'] ?? '')));
    $password = (string) ($fields['password'] ?? '');
    $title = mb_substr(trim((string) ($fields['job_title'] ?? '')), 0, 80);
    $access = (string) ($fields['access'] ?? 'books');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Name and a valid email are required.'];
    }
    if ($password === '') {
        $password = 'folio2026';
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
    }
    if (db_one('SELECT id FROM users WHERE email = ?', 's', [$email])) {
        return ['ok' => false, 'error' => 'That email already has a Vellisys login.'];
    }
    $limit = company_user_limit($company);
    if (company_seat_count($companyId) >= $limit) {
        return ['ok' => false, 'error' => 'This desk already has its ' . $limit . ' login' . ($limit === 1 ? '' : 's') . '.'];
    }
    $hasAdmin = db_one("SELECT id FROM users WHERE company_id = ? AND role = 'admin'", 'i', [$companyId]);
    if (!$hasAdmin) {
        $role = 'admin';
        $access = 'admin';
        if ($title === '') {
            $title = 'Administrator';
        }
    } else {
        $role = 'member';
        if ($access !== 'sales') {
            $access = 'books';
        }
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    db_exec(
        'INSERT INTO users (name, job_title, email, password_hash, role, access, company_id) VALUES (?,?,?,?,?,?,?)',
        'ssssssi',
        [$name, $title, $email, $hash, $role, $access, $companyId]
    );
    return ['ok' => true, 'email' => $email, 'password' => $password, 'role' => $role];
}

function generate_desk_password(): string
{
    return 'Vs-' . substr(bin2hex(random_bytes(6)), 0, 10);
}

function store_company_logo_upload(int $companyId, string $field = 'logo'): array
{
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return ['ok' => true, 'path' => ''];
    }
    $ext = strtolower(pathinfo((string) ($_FILES[$field]['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'], true)) {
        return ['ok' => false, 'error' => 'Logo must be PNG, JPG, SVG, GIF or WebP.'];
    }
    if ((int) ($_FILES[$field]['size'] ?? 0) > 2_000_000) {
        return ['ok' => false, 'error' => 'Logo must be under 2 MB.'];
    }
    $dir = ROOT_PATH . '/uploads/logos';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Could not save the logo file.'];
    }
    $fname = 'logo-' . $companyId . '-' . date('YmdHis') . '.' . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $fname)) {
        return ['ok' => false, 'error' => 'Could not save the logo file.'];
    }
    return ['ok' => true, 'path' => 'uploads/logos/' . $fname];
}

function platform_create_company(?int $signupId = null): array
{
    $name = post('name');
    $userName = post('user_name');
    $userEmail = strtolower(post('user_email'));
    $password = post('user_password');
    $generated = false;
    if ($password === '') {
        $password = generate_desk_password();
        $generated = true;
    }
    $kindsPosted = $_POST['enabled_kinds'] ?? [];
    $status = post('status') ?: 'onboarding';
    if (!in_array($status, ['onboarding', 'live'], true)) {
        $status = 'onboarding';
    }
    if ($name === '' || $userName === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Company name, desk admin and a valid email are required.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters, or leave it blank to generate one.'];
    }
    if (db_one('SELECT id FROM users WHERE email = ?', 's', [$userEmail])) {
        return ['ok' => false, 'error' => 'That email already has a Vellisys login.'];
    }
    if (!is_array($kindsPosted) || $kindsPosted === []) {
        return ['ok' => false, 'error' => 'Select at least one document type this company will use.'];
    }
    $mailEmail = strtolower(post('mail_email'));
    if ($mailEmail !== '' && !filter_var($mailEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'The sending mailbox must be a valid email.'];
    }
    if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
        $ext = strtolower(pathinfo((string) ($_FILES['logo']['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'], true)) {
            return ['ok' => false, 'error' => 'Logo must be PNG, JPG, SVG, GIF or WebP.'];
        }
        if ((int) ($_FILES['logo']['size'] ?? 0) > 2_000_000) {
            return ['ok' => false, 'error' => 'Logo must be under 2 MB.'];
        }
    }

    $currency = posted_currency('currency', 'USD');
    $color = parse_hex_color(post('brand_color'), '#1E4EFF');
    $accent = parse_hex_color(post('brand_accent'), '#C6A15B');
    $deep = parse_hex_color(post('brand_deep'), '#08143A');
    $prefix = strtoupper(post('prefix') ?: prefix_from_name($name));
    $limit = clamp_user_limit((int) post('user_limit') ?: 3);
    $accountName = post('account_name') ?: $name;
    $paymentNote = post('payment_note') ?: ('Make payment to ' . $name . '.');
    $comments = post('invoice_comments') ?: "1. Payment is due by the date shown above.\n2. Quote the invoice number on the transfer.";

    $cid = db_exec(
        'INSERT INTO companies (name, status, plan, notes, enabled_kinds, custom_doc, user_limit) VALUES (?,?,?,?,?,?,?)',
        'ssssssi',
        [$name, $status, 'sme', post('notes') ?: null, posted_enabled_kinds(), posted_custom_doc(), $limit]
    );

    $hasPaidTerm = false;
    $term = (int) post('paid_term');
    $unit = post('paid_unit') === 'years' ? 'years' : 'months';
    if ($term > 0) {
        if ($unit === 'years' && $term > 20) {
            $term = 20;
        }
        if ($unit === 'months' && $term > 120) {
            $term = 120;
        }
        $from = post('paid_from');
        if ($from === '' || !DateTime::createFromFormat('Y-m-d', $from)) {
            $from = date('Y-m-d');
        }
        $expires = compute_expiry_date($from, $term, $unit);
        $feeAmount = money_parse(post('fee_amount'));
        $paidRaw = str_replace([',', ' '], '', post('fee_paid'));
        $feePaid = $paidRaw === '' ? $feeAmount : money_parse($paidRaw);
        $feeCurrency = posted_currency('fee_currency', $currency);
        if ($expires) {
            db_exec(
                'UPDATE companies SET paid_term=?, paid_unit=?, paid_from=?, expires_at=?, fee_amount=?, fee_paid=?, fee_currency=? WHERE id=?',
                'isssddsi',
                [$term, $unit, $from, $expires, $feeAmount, $feePaid, $feeCurrency, $cid]
            );
            $hasPaidTerm = true;
        }
    }

    if ($mailEmail !== '') {
        $provider = post('mail_provider');
        if (!isset(mail_provider_presets()[$provider])) {
            $provider = 'hostinger';
        }
        $preset = mail_provider_presets()[$provider];
        $fromName = post('mail_from_name') ?: $name;
        $host = post('smtp_host') ?: $preset['smtp_host'];
        $port = (int) post('smtp_port') ?: (int) $preset['smtp_port'];
        $secure = post('smtp_secure');
        if (!in_array($secure, ['ssl', 'tls', 'none'], true)) {
            $secure = $preset['smtp_secure'];
        }
        $popHost = post('pop_host') ?: $preset['pop_host'];
        $popPort = (int) post('pop_port') ?: (int) $preset['pop_port'];
        $imapHost = post('imap_host') ?: $preset['imap_host'];
        $imapPort = (int) post('imap_port') ?: (int) $preset['imap_port'];
        $stored = post('mail_password') !== '' ? mail_encrypt_secret(post('mail_password')) : null;
        db_exec(
            'UPDATE companies SET mail_provider=?, mail_email=?, mail_password=?, mail_from_name=?, smtp_host=?, smtp_port=?, smtp_secure=?, pop_host=?, pop_port=?, imap_host=?, imap_port=? WHERE id=?',
            'sssssissisii',
            [$provider, $mailEmail, $stored, $fromName, $host, $port, $secure, $popHost, $popPort, $imapHost, $imapPort, $cid]
        );
    }

    db_exec(
        'INSERT INTO branding (company_id, name, tagline, tin, vat_no, address, city, phone, email, website, bank_name, account_name, account_number, brand_color, brand_accent, brand_deep, logo_path, prefix, payment_note, invoice_comments, plan, currency)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        'isssssssssssssssssssss',
        [
            $cid,
            $name,
            post('tagline'),
            post('tin'),
            post('vat_no'),
            post('address'),
            post('city'),
            post('phone'),
            $userEmail,
            post('website'),
            post('bank_name'),
            $accountName,
            post('account_number'),
            $color,
            $accent,
            $deep,
            '',
            $prefix,
            $paymentNote,
            $comments,
            'sme',
            $currency,
        ]
    );

    $logo = store_company_logo_upload($cid);
    if (!empty($logo['path'])) {
        db_exec('UPDATE branding SET logo_path=? WHERE company_id=?', 'si', [$logo['path'], $cid]);
    } elseif (empty($logo['ok'])) {
        return ['ok' => false, 'error' => (string) ($logo['error'] ?? 'Could not save the logo.'), 'id' => $cid];
    }

    $made = create_desk_user($cid, [
        'name' => $userName,
        'email' => $userEmail,
        'password' => $password,
        'job_title' => post('user_title') ?: 'Administrator',
        'access' => 'admin',
    ]);
    if (empty($made['ok'])) {
        return ['ok' => false, 'error' => (string) ($made['error'] ?? 'Could not create the desk login.'), 'id' => $cid];
    }

    if ($signupId) {
        db_exec("UPDATE signups SET status = 'onboarded', company_id = ? WHERE id = ?", 'ii', [$cid, $signupId]);
    }

    company_mark_onboard_step($cid, 'desk_login');
    if ($hasPaidTerm) {
        company_mark_onboard_step($cid, 'paid_term');
    }
    if ($mailEmail !== '') {
        company_mark_onboard_step($cid, 'mailbox');
    }
    if ($status === 'live') {
        company_mark_onboard_step($cid, 'desk_live');
    }

    return [
        'ok' => true,
        'id' => $cid,
        'name' => $name,
        'email' => $userEmail,
        'password' => $made['password'] ?? $password,
        'generated' => $generated,
        'status' => $status,
        'send_welcome' => post('send_welcome') !== '',
        'send_receipt' => post('send_receipt') !== '',
        'has_paid_term' => $hasPaidTerm,
    ];
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

function product_from_name(): string
{
    return 'VELLISYS';
}

function product_email(): string
{
    return 'info@vellisys.com';
}

function public_packages_url(): string
{
    return rtrim(url(''), '/') . '/#pricing';
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
    $photos = [
        'assets/img/landing/old-receipt-1.jpg',
        'assets/img/landing/old-receipt-2.jpg',
        'assets/img/landing/old-receipt-3.jpg',
        'assets/img/landing/old-receipt-4.jpg',
        'assets/img/landing/old-receipt-5.jpg',
        'assets/img/landing/old-receipt-6.jpg',
        'assets/img/landing/old-receipt-7.jpg',
    ];
    return array_values(array_filter($photos, static fn ($rel) => is_file(ROOT_PATH . '/' . $rel)));
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
    return folio_remember('trust_clients', static function (): array {
        try {
            $rows = db_all('SELECT * FROM trust_clients ORDER BY sort, id');
            if ($rows) {
                return $rows;
            }
        } catch (Throwable $e) {
            // Table may not exist until migrate runs.
        }
        return trust_client_defaults();
    });
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

function product_v_mark_url(): string
{
    $full = ROOT_PATH . '/assets/img/v-mark.png';
    if (is_file($full)) {
        return asset('img/v-mark.png');
    }
    return product_mark_url();
}

function render_gate_legal(): void
{
    ?>
    <div class="gate-legal">
      <nav>
        <a href="<?= h(url('privacy.php')) ?>">Privacy</a>
        <a href="<?= h(url('terms.php')) ?>">Terms</a>
        <a href="<?= h(url()) ?>#ask">Support</a>
      </nav>
      <p>© <?= h((string) date('Y')) ?> <?= h(product_name()) ?>. All rights reserved.</p>
    </div>
    <?php
}

function render_gate_home(): void
{
    ?>
    <p class="gate-home">
      <a href="<?= h(url()) ?>"><?= icon('arrow-left', 16) ?> Back to home</a>
    </p>
    <?php
}

function render_gate_card_mark(): void
{
    ?>
    <img class="gate-stack-mark" src="<?= h(product_v_mark_url()) ?>" alt="" decoding="async">
    <?php
}

function gate_art(string $heading, string $lead, string $switchHtml = '', array $opts = []): void
{
    $kicker = array_key_exists('kicker', $opts) ? (string) $opts['kicker'] : '';
    $tag = array_key_exists('tag', $opts) ? (string) $opts['tag'] : '';
    $headingHtml = $opts['heading_html'] ?? null;
    ?>
    <aside class="gate-art">
      <img class="gate-watermark" src="<?= h(product_v_mark_url()) ?>" alt="" decoding="async">
      <a class="lp-brand" href="<?= h(url()) ?>">
        <img class="lp-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
      </a>
      <div class="gate-art-inner">
        <?php if ($kicker !== ''): ?><p class="gate-kicker"><?= h($kicker) ?></p><?php endif; ?>
        <h1><?= $headingHtml !== null ? $headingHtml : h($heading) ?></h1>
        <?php if ($lead !== ''): ?><p><?= h($lead) ?></p><?php endif; ?>
      </div>
      <?php if ($tag !== ''): ?><p class="gate-tag"><?= h($tag) ?></p><?php endif; ?>
      <?php if ($switchHtml !== ''): ?><p class="gate-art-foot"><?= $switchHtml ?></p><?php endif; ?>
    </aside>
    <?php
}

function public_header(string $page = 'home'): void
{
    if (function_exists('record_site_visit')) {
        record_site_visit();
    }
    $ticker = $page === 'checkout' ? [] : landing_ticker_lines();
    ?>
  <header class="lp-chrome" data-lp-chrome>
  <?php if ($ticker): ?>
  <div class="lp-ticker" data-lp-ticker>
    <div class="lp-ticker-track" data-marquee-track>
      <?php for ($i = 0; $i < 2; $i++): ?>
        <div class="lp-ticker-set" data-marquee-set <?= $i === 1 ? 'aria-hidden="true"' : '' ?>>
          <?php foreach ($ticker as $n => $line): ?>
            <p class="lp-ticker-line<?= $n % 2 === 1 ? ' is-alt' : '' ?>"><?= h($line) ?></p>
            <span class="lp-ticker-sep" aria-hidden="true">|</span>
          <?php endforeach; ?>
        </div>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
  <div class="lp-nav">
    <a class="lp-brand" href="<?= h(url()) ?>">
      <img class="lp-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
    </a>
    <nav>
      <?php if ($page !== 'home'): ?>
        <a class="lp-nav-home" href="<?= h(url()) ?>" aria-label="Home" title="Home"><?= icon('home', 18) ?></a>
      <?php endif; ?>
      <label class="lp-fx" for="lp-ccy">
        <span class="visually-hidden">Currency</span>
        <select id="lp-ccy" name="ccy" data-lp-ccy aria-label="Display currency">
          <?php $ccy = pricing_display_currency(); ?>
          <?php foreach (pricing_currencies() as $code => $meta): ?>
            <option value="<?= h($code) ?>" <?= $ccy === $code ? 'selected' : '' ?>><?= h($meta['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php if ($page !== 'checkout'): ?>
        <a class="lp-btn lp-btn-ghost lp-nav-wide" href="<?= h(url('demo.php')) ?>">Book a demo</a>
      <?php endif; ?>
      <a class="lp-btn lp-btn-ghost" href="<?= h(url('login.php')) ?>">Sign in</a>
      <?php if ($page !== 'checkout'): ?>
        <a class="lp-btn lp-btn-solid" href="<?= h($page === 'home' ? '#pricing' : (rtrim(url(), '/') . '/#pricing')) ?>">Get a desk</a>
      <?php endif; ?>
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
        <img class="lp-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <p>Books you can share in one click - branded to each client, in their currency, with many templates to choose from. Anywhere in the world. A product of <?= h(product_maker_name()) ?>.</p>
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
    $waMark = '<svg class="lp-wa-mark" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';
    ?>
  <div class="lp-floats" data-lp-floats>
    <button type="button" class="lp-top" data-lp-top hidden aria-label="Back to top"><?= icon('chevron-up', 20) ?></button>
    <div class="lp-wa" data-lp-wa>
      <div class="lp-wa-panel" id="lp-wa-panel" hidden>
        <div class="lp-wa-head">
          <strong>WhatsApp an agent</strong>
          <button type="button" class="lp-wa-x" data-lp-wa-close aria-label="Close WhatsApp"><?= icon('x', 18) ?></button>
        </div>
        <div class="lp-wa-home" data-lp-wa-home>
          <?php foreach ($agents as $agent): ?>
            <button type="button" class="lp-wa-agent" data-lp-agent data-name="<?= h($agent['name']) ?>" data-phone="<?= h(phone_digits($agent['phone'])) ?>">
              <span class="lp-wa-ava"><?= icon('user', 20) ?></span>
              <span>
                <b><?= h($agent['name']) ?></b>
                <em><?= h($agent['phone']) ?></em>
              </span>
            </button>
          <?php endforeach; ?>
        </div>
        <form class="lp-wa-compose" data-lp-wa-compose hidden>
          <p>To <strong data-lp-agent-label>Agent</strong></p>
          <label class="lp-hp" for="lp-wa-text">Message</label>
          <textarea id="lp-wa-text" data-lp-wa-text rows="4" maxlength="1000" placeholder="Type your message"></textarea>
          <button class="lp-wa-send" type="submit"><?= icon('send', 16) ?>Send</button>
          <button class="lp-wa-back" type="button" data-lp-wa-back>All agents</button>
        </form>
      </div>
      <button type="button" class="lp-wa-fab" data-lp-wa-toggle aria-expanded="false" aria-controls="lp-wa-panel" aria-label="WhatsApp an agent"><?= $waMark ?></button>
    </div>
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

function clip_text(string $text, int $max = 90): string
{
    $text = trim($text);
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $max)) . '…';
    }
    if (strlen($text) <= $max) {
        return $text;
    }
    return rtrim(substr($text, 0, $max)) . '…';
}

function company_expires_on(array $company): ?string
{
    $d = trim((string) ($company['expires_at'] ?? ''));
    return $d !== '' ? $d : null;
}

function company_days_left(?string $expires): ?int
{
    if (!$expires) {
        return null;
    }
    $end = DateTime::createFromFormat('Y-m-d', substr($expires, 0, 10));
    if (!$end) {
        return null;
    }
    $today = new DateTime('today');
    return (int) $today->diff($end)->format('%r%a');
}

function company_term_label(array $company): string
{
    $n = (int) ($company['paid_term'] ?? 0);
    if ($n <= 0 || !company_expires_on($company)) {
        return 'Not set';
    }
    $unit = ($company['paid_unit'] ?? 'months') === 'years' ? ($n === 1 ? 'year' : 'years') : ($n === 1 ? 'month' : 'months');
    return $n . ' ' . $unit;
}

function company_expiry_label(array $company): string
{
    $expires = company_expires_on($company);
    $days = company_days_left($expires);
    $state = company_expiry_state($company);
    return match ($state) {
        'soon' => $days === 0 ? 'Ends today' : ($days === 1 ? '1 day left' : $days . ' days left'),
        'expired' => 'Expired ' . format_date($expires),
        'ok' => 'Until ' . format_date($expires),
        default => 'No term',
    };
}

function company_expiry_state(array $company): string
{
    $days = company_days_left(company_expires_on($company));
    if ($days === null) {
        return 'none';
    }
    if ($days < 0) {
        return 'expired';
    }
    if ($days <= 31) {
        return 'soon';
    }
    return 'ok';
}

function parse_money_string(string $raw): float
{
    return money_parse($raw);
}

function company_remaining_phrase(array $company): string
{
    $expires = company_expires_on($company);
    if (!$expires) {
        return 'No paid term set';
    }
    $end = DateTime::createFromFormat('Y-m-d', substr($expires, 0, 10));
    if (!$end) {
        return 'No paid term set';
    }
    $end->setTime(0, 0, 0);
    $today = new DateTime('today');
    if ($end == $today) {
        return 'Expires today';
    }
    $diff = $today->diff($end);
    $parts = [];
    if ($diff->y > 0) {
        $parts[] = $diff->y . ($diff->y === 1 ? ' year' : ' years');
    }
    if ($diff->m > 0) {
        $parts[] = $diff->m . ($diff->m === 1 ? ' month' : ' months');
    }
    if ($diff->d > 0 || !$parts) {
        $parts[] = $diff->d === 1 ? '1 day' : $diff->d . ' days';
    }
    $span = implode(' ', $parts);
    return $diff->invert ? ('Expired ' . $span . ' ago') : ($span . ' left');
}

function company_expiry_date_label(array $company): string
{
    $expires = company_expires_on($company);
    if (!$expires) {
        return 'Expiry not set';
    }
    $when = format_date($expires);
    return company_expiry_state($company) === 'expired' ? ('Ended ' . $when) : ('Expires ' . $when);
}

function company_fee_currency(array $company): string
{
    return normalize_currency((string) ($company['fee_currency'] ?? ''), 'USD');
}

function company_fee_amount(array $company): float
{
    return max(0, round((float) ($company['fee_amount'] ?? 0), 2));
}

function company_fee_paid(array $company): float
{
    return max(0, round((float) ($company['fee_paid'] ?? 0), 2));
}

function company_fee_balance(array $company): float
{
    return max(0, round(company_fee_amount($company) - company_fee_paid($company), 2));
}

function onboard_step_defs(): array
{
    return [
        'package_paid' => 'Package paid',
        'paid_term' => 'Paid term recorded',
        'receipt_email' => 'Receipt email sent',
        'credentials_set' => 'Admin credentials set',
        'desk_login' => 'Desk login created',
        'first_login' => 'Client first sign-in',
        'branding_saved' => 'Company branding saved',
        'mailbox' => 'Sending mailbox assigned',
        'welcome_email' => 'Welcome email sent',
        'desk_live' => 'Desk marked live',
        'login_confirmed' => 'Client confirms login',
    ];
}

function company_onboard_map(array $company): array
{
    $raw = trim((string) ($company['onboard_steps'] ?? ''));
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function company_onboard_done(array $company, string $key): bool
{
    $map = company_onboard_map($company);
    return !empty($map[$key]);
}

function company_onboard_when(array $company, string $key): string
{
    $map = company_onboard_map($company);
    $at = trim((string) ($map[$key] ?? ''));
    if ($at === '') {
        return '';
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $at) ?: DateTime::createFromFormat('Y-m-d', substr($at, 0, 10));
    return $dt ? format_date($dt->format('Y-m-d')) : $at;
}

function company_onboard_progress(array $company): array
{
    $defs = onboard_step_defs();
    $done = 0;
    foreach (array_keys($defs) as $key) {
        if (company_onboard_done($company, $key)) {
            $done++;
        }
    }
    return ['done' => $done, 'total' => count($defs)];
}

function company_save_onboard_steps(int $companyId, array $doneKeys, ?array $previous = null): void
{
    $now = date('Y-m-d H:i:s');
    $prev = $previous ?? [];
    $out = [];
    foreach (array_keys(onboard_step_defs()) as $key) {
        if (!in_array($key, $doneKeys, true)) {
            continue;
        }
        $out[$key] = (isset($prev[$key]) && is_string($prev[$key]) && $prev[$key] !== '') ? $prev[$key] : $now;
    }
    db_exec('UPDATE companies SET onboard_steps=? WHERE id=?', 'si', [json_encode($out, JSON_UNESCAPED_SLASHES), $companyId]);
}

function company_mark_onboard_step(int $companyId, string $key): void
{
    if (!isset(onboard_step_defs()[$key]) || $companyId <= 0) {
        return;
    }
    $row = db_one('SELECT onboard_steps FROM companies WHERE id = ?', 'i', [$companyId]);
    if (!$row) {
        return;
    }
    $map = company_onboard_map($row);
    if (!empty($map[$key])) {
        return;
    }
    $map[$key] = date('Y-m-d H:i:s');
    $clean = [];
    foreach (array_keys(onboard_step_defs()) as $k) {
        if (!empty($map[$k])) {
            $clean[$k] = $map[$k];
        }
    }
    db_exec('UPDATE companies SET onboard_steps=? WHERE id=?', 'si', [json_encode($clean, JSON_UNESCAPED_SLASHES), $companyId]);
}

function company_term_days(array $company): ?int
{
    $from = trim((string) ($company['paid_from'] ?? ''));
    $expires = company_expires_on($company);
    if ($from === '' || !$expires) {
        return null;
    }
    $a = DateTime::createFromFormat('Y-m-d', substr($from, 0, 10));
    $b = DateTime::createFromFormat('Y-m-d', substr($expires, 0, 10));
    if (!$a || !$b) {
        return null;
    }
    $n = (int) $a->diff($b)->days;
    return $n > 0 ? $n : 1;
}

function company_remaining_value(array $company): float
{
    $paid = company_fee_paid($company);
    $daysLeft = company_days_left(company_expires_on($company));
    $termDays = company_term_days($company);
    if ($paid <= 0 || $daysLeft === null || $termDays === null || $termDays <= 0) {
        return 0.0;
    }
    if ($daysLeft <= 0) {
        return 0.0;
    }
    return round($paid * min($daysLeft, $termDays) / $termDays, 2);
}

function company_fx_context(int $companyId): array
{
    $brand = $companyId > 0 ? branding_for($companyId) : [];
    $home = normalize_currency((string) ($brand['currency'] ?? ''), 'USD');
    $rate = (float) ($brand['fx_ugx_per_usd'] ?? 0);
    return ['home' => $home, 'rate' => $rate > 0 ? $rate : 1.0];
}

function company_fee_ugx(array $company, string $field): float
{
    $amt = match ($field) {
        'amount' => company_fee_amount($company),
        'balance' => company_fee_balance($company),
        'remaining' => company_remaining_value($company),
        default => company_fee_paid($company),
    };
    $fx = company_fx_context((int) ($company['id'] ?? 0));
    return convert_money($amt, company_fee_currency($company), 'USD', $fx['rate'], $fx['home']);
}

function month_axis(int $months = 12): array
{
    $out = [];
    $cursor = new DateTime('first day of this month');
    for ($i = $months - 1; $i >= 0; $i--) {
        $out[] = (clone $cursor)->modify('-' . $i . ' months')->format('Y-m');
    }
    return $out;
}

function platform_issued_documents(): array
{
    $rows = db_all(
        "SELECT d.id, d.company_id, d.kind, d.date, d.currency, d.vat_rate, d.related_id, d.allocated_amount,
                r.kind AS related_kind
         FROM documents d
         LEFT JOIN documents r ON r.id = d.related_id
         WHERE d.status = 'issued'"
    );
    if (!$rows) {
        return [];
    }
    $ids = array_map(static fn ($r) => (int) $r['id'], $rows);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sums = db_all(
        "SELECT document_id,
                COALESCE(SUM(ROUND(qty * rate, 2)), 0) AS net,
                COALESCE(SUM(CASE WHEN taxed = 1 THEN ROUND(qty * rate, 2) ELSE 0 END), 0) AS taxed_net
         FROM document_items WHERE document_id IN ($placeholders)
         GROUP BY document_id",
        $types,
        $ids
    );
    $byDoc = [];
    foreach ($sums as $sum) {
        $byDoc[(int) $sum['document_id']] = $sum;
    }
    $byId = [];
    foreach ($rows as $row) {
        $byId[(int) $row['id']] = $row;
    }
    $paidBy = [];
    foreach ($rows as $row) {
        if (($row['kind'] ?? '') !== 'receipt') {
            continue;
        }
        $rid = (int) ($row['related_id'] ?? 0);
        if ($rid <= 0) {
            continue;
        }
        $agg = $byDoc[(int) $row['id']] ?? ['net' => 0, 'taxed_net' => 0];
        $net = round((float) $agg['net'], 2);
        $vat = round((float) $agg['taxed_net'] * (float) ($row['vat_rate'] ?? 0), 2);
        $charge = round($net + $vat, 2);
        $amt = (float) ($row['allocated_amount'] ?? 0);
        if ($amt <= 0 || ($charge > 0 && $amt > $charge + 0.009)) {
            $amt = $charge;
        }
        $to = isset($byId[$rid]) ? doc_currency($byId[$rid]) : doc_currency($row);
        $paidBy[$rid] = ($paidBy[$rid] ?? 0) + convert_money($amt, doc_currency($row), $to);
    }
    foreach ($rows as &$row) {
        $agg = $byDoc[(int) $row['id']] ?? ['net' => 0, 'taxed_net' => 0];
        $net = round((float) $agg['net'], 2);
        $vat = round((float) $agg['taxed_net'] * (float) ($row['vat_rate'] ?? 0), 2);
        $row['totals'] = ['net' => $net, 'vat' => $vat, 'total' => round($net + $vat, 2)];
        if ($row['kind'] === 'invoice' || $row['kind'] === 'expense') {
            $row['paid'] = round_money($paidBy[(int) $row['id']] ?? 0.0, doc_currency($row));
            $row['balance'] = max(0, round((float) $row['totals']['total'] - (float) $row['paid'], 2));
        } else {
            $received = (float) ($row['allocated_amount'] ?? 0);
            $charge = (float) $row['totals']['total'];
            if ($received <= 0 || ($charge > 0 && $received > $charge + 0.009)) {
                $received = $charge;
            }
            $row['paid'] = $received;
            $row['balance'] = 0.0;
        }
    }
    unset($row);
    return $rows;
}

function compute_expiry_date(string $from, int $term, string $unit): ?string
{
    if ($term <= 0) {
        return null;
    }
    $unit = $unit === 'years' ? 'years' : 'months';
    $dt = DateTime::createFromFormat('Y-m-d', substr($from, 0, 10));
    if (!$dt) {
        return null;
    }
    $dt->modify('+' . $term . ' ' . $unit);
    return $dt->format('Y-m-d');
}

function company_notice_email(int $companyId, array $brand = [], array $members = []): array
{
    $email = trim((string) ($brand['email'] ?? ''));
    $name = trim((string) ($brand['name'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        foreach ($members as $m) {
            if (filter_var((string) ($m['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
                $email = (string) $m['email'];
                $name = (string) ($m['name'] ?: $name);
                break;
            }
        }
    }
    if ($name === '') {
        $name = 'the team';
    }
    return ['email' => $email, 'name' => $name];
}

function visitor_ip_hash(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $cfg = require ROOT_PATH . '/config/database.php';
    $secret = hash('sha256', ($cfg['name'] ?? 'folio') . '|' . ($cfg['user'] ?? 'root') . '|vellisys-ask');
    return hash('sha256', $ip . '|' . $secret);
}

function landing_review_defaults(): array
{
    return [
        ['name' => 'Priya Menon', 'role' => 'Accounts, Harbour & Co.', 'quote' => 'Our quotations finally look like us. Clients stopped asking if the PDF came from our office.', 'sort' => 10],
        ['name' => 'Daniel Okello', 'role' => 'Finance lead, Westland Traders', 'quote' => 'Invoices and receipts match. Debtors is honest by Friday without a spreadsheet chase.', 'sort' => 20],
        ['name' => 'Sofia Alvarez', 'role' => 'Practice manager, Nueve Studio', 'quote' => 'Headed letters and invoices share one letterhead. We send from the desk in a tap.', 'sort' => 30],
        ['name' => 'James Boateng', 'role' => 'Owner, Accra Millworks', 'quote' => 'We bill in GHS. Reports still add up. The yard team opens the books on phones.', 'sort' => 40],
        ['name' => 'Mei Chen', 'role' => 'Operations, Pacific Supply', 'quote' => 'Ten templates, our colours. Automated reminders replaced the awkward WhatsApp chase.', 'sort' => 50],
        ['name' => 'Amina Yusuf', 'role' => 'Director, Sahel Goods', 'quote' => 'Onboarding was a login in the mailbox. The desk is the professional we needed.', 'sort' => 60],
    ];
}

function landing_review_section_defaults(): array
{
    return [
        'kicker' => 'Testimonials',
        'heading' => 'What clients say',
    ];
}

function landing_review_section(): array
{
    return folio_remember('landing_review_section', static function (): array {
        $defaults = landing_review_section_defaults();
        try {
            $row = db_one('SELECT kicker, heading FROM landing_review_section WHERE id = 1');
            if ($row) {
                $kicker = trim((string) ($row['kicker'] ?? ''));
                $heading = trim((string) ($row['heading'] ?? ''));
                return [
                    'kicker' => $kicker !== '' ? $kicker : $defaults['kicker'],
                    'heading' => $heading !== '' ? $heading : $defaults['heading'],
                ];
            }
        } catch (Throwable $e) {
            // Table may not exist until migrate runs.
        }
        return $defaults;
    });
}

function landing_reviews(): array
{
    return folio_remember('landing_reviews', static function (): array {
        try {
            return db_all('SELECT * FROM landing_reviews ORDER BY sort, id');
        } catch (Throwable $e) {
            return landing_review_defaults();
        }
    });
}

function desk_manage_items(): array
{
    return [
        ['icon' => 'quotation', 'title' => 'Quotations', 'body' => 'Raise a quote, share it branded, convert it to an invoice when they say yes.'],
        ['icon' => 'invoice', 'title' => 'Invoices', 'body' => 'Issue full or part-paid invoices. Balances stay visible until they are cleared.'],
        ['icon' => 'receipt', 'title' => 'Receipts', 'body' => 'Record what came in. RECEIVED and DUE print on the sheet, in your currency.'],
        ['icon' => 'expense', 'title' => 'Expenses', 'body' => 'Log what the company spent - fuel, rent, suppliers - with VAT and currency on the same desk as the sales books.'],
        ['icon' => 'truck', 'title' => 'Delivery notes', 'body' => 'List what left the store, with quantities. No prices - goods out, not a bill.'],
        ['icon' => 'file', 'title' => 'Custom documents', 'body' => 'A form you define at onboarding - fields, a body, or both - on the same branded paper.'],
        ['icon' => 'clients', 'title' => 'Debtors', 'body' => 'See who still owes you. Send a reminder from the row, from the company mailbox.'],
        ['icon' => 'bank', 'title' => 'Creditors', 'body' => 'Track suppliers you still need to pay. Note a payment or write to them from the desk.'],
        ['icon' => 'letter', 'title' => 'Headed letters', 'body' => 'Correspondence on the same stationery as the books. Print or email in one click.'],
        ['icon' => 'send', 'title' => 'Send emails', 'body' => 'Quotations, invoices, receipts, letters and reminders leave from your assigned mailbox.'],
        ['icon' => 'palette', 'title' => '10+ templates', 'body' => 'Pick Folio, Ledger, Twin copy, Estate, Night and more. The whole books follow that layout.'],
        ['icon' => 'image', 'title' => 'Your company branding', 'body' => 'Logo, three colours, letterhead. Every document looks like it left your office.'],
    ];
}

function landing_faqs(): array
{
    return [
        [
            'q' => 'How do I get a desk?',
            'a' => 'Pay for a package, register for us to onboard you, or book a demo first. After an online payment confirms you set the admin email and password, then sign in and finish branding on Settings.',
        ],
        [
            'q' => 'How is Vellisys different?',
            'a' => 'Vellisys is branded books software. Quotations, invoices and receipts leave in your logo, colours and currency, from one desk. Teams that want that stationery look use it as their books desk.',
        ],
        [
            'q' => 'Are the documents in our branding?',
            'a' => 'Yes. Every quotation, invoice, receipt, delivery note, expense, headed letter and custom document uses the company logo, three brand colours, and one of the templates you pick in Settings.',
        ],
        [
            'q' => 'Can we work in our own currency?',
            'a' => 'Yes. In Settings you enter the currency you bill in - UGX, KES, EUR, USD or any other three-letter code. Documents can also be in USD; set how many of your currency equal one dollar so reports can add them up.',
        ],
        [
            'q' => 'How do I pay for a package?',
            'a' => 'Choose a package, enter the company, then continue to the secure Pesapal page in this tab (mobile money, cards, bank or wallet). When payment finishes you return here. The charge is in the currency you picked on this page. The desk itself can still bill your clients in any currency you set in Settings.',
            'link' => ['href' => '#pay', 'label' => 'See how payment works'],
        ],
        [
            'q' => 'How much does a desk cost?',
            'a' => (static function (): string {
                $names = array_values(array_filter(array_map(static fn (array $p): string => trim((string) ($p['name'] ?? '')), pricing_packages())));
                $list = $names ? implode(', ', $names) : 'the packages on this page';
                return 'Packages on this page: ' . $list . '. Billed per year. Pay online, register for manual onboarding, or book a demo. After an online payment you set your admin email and password and finish branding on Settings.';
            })(),
            'link' => ['href' => '#pricing', 'label' => 'See packages'],
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
    foreach (['assets/img/vellisys-mark.png', 'assets/img/vellisys-logo.png', 'assets/img/logo.png'] as $rel) {
        $full = ROOT_PATH . '/' . $rel;
        if (is_file($full)) {
            return asset(substr($rel, strlen('assets/')));
        }
    }
    return asset('img/vellisys-mark.png');
}

function landing_ticker_defaults(): array
{
    return [
        ['body' => 'Join 100+ businesses and corporate companies using Vellisys', 'sort' => 10],
        ['body' => 'Stop losing the books. Share them branded, in one click.', 'sort' => 20],
        ['body' => 'Built for East Africa. Used across Africa and worldwide.', 'sort' => 30],
        ['body' => 'Branded books for companies anywhere in the world.', 'sort' => 40],
    ];
}

function landing_ticker_lines(): array
{
    return folio_remember('landing_ticker', static function (): array {
        try {
            $rows = db_all('SELECT body FROM landing_ticker ORDER BY sort, id');
            $lines = [];
            foreach ($rows as $row) {
                $body = trim((string) ($row['body'] ?? ''));
                if ($body !== '') {
                    $lines[] = $body;
                }
            }
            if ($lines) {
                return $lines;
            }
        } catch (Throwable $e) {
            // Table may not exist until migrate runs.
        }
        return array_column(landing_ticker_defaults(), 'body');
    });
}

function landing_way_image_url(): string
{
    foreach (['img/landing/nw.png', 'img/landing/new.png'] as $rel) {
        $full = ROOT_PATH . '/assets/' . $rel;
        if (is_file($full)) {
            return asset($rel);
        }
    }
    return product_mark_url();
}

function product_original_logo_url(): string
{
    return product_logo_url();
}

function product_logo_file(): string
{
    foreach (['assets/img/our-logo.png', 'assets/img/vellisys-logo.png', 'assets/img/logo.png'] as $rel) {
        $full = ROOT_PATH . '/' . $rel;
        if (is_file($full)) {
            return $full;
        }
    }
    return '';
}

function product_email_logo_file(): string
{
    foreach (['assets/img/vellisys-email-logo.png', 'assets/img/our-logo.png', 'assets/img/vellisys-logo.png', 'assets/img/logo.png'] as $rel) {
        $full = ROOT_PATH . '/' . $rel;
        if (is_file($full)) {
            return $full;
        }
    }
    return '';
}

function product_logo_url(): string
{
    $file = product_logo_file();
    if ($file !== '') {
        $rel = ltrim(str_replace(ROOT_PATH . '/', '', $file), '/');
        if (str_starts_with($rel, 'assets/')) {
            $rel = substr($rel, strlen('assets/'));
        }
        return asset($rel);
    }
    return asset('img/vellisys-logo.png');
}

function landing_card_defaults(): array
{
    return [
        ['slot' => 'familiar_1', 'section' => 'familiar', 'sort' => 1, 'image_path' => 'assets/img/landing/landing-receipts.png', 'title' => 'Still stuffing receipts in a drawer?', 'body' => 'Slips, phone photos, part payments in whatever currency you actually use. By month-end you are guessing what is still owed.'],
        ['slot' => 'familiar_2', 'section' => 'familiar', 'sort' => 2, 'image_path' => 'assets/img/landing/landing-whatsapp.png', 'title' => 'Did that invoice vanish into WhatsApp?', 'body' => 'Quotes in email. Invoices in a chat. Nobody has one number for who still owes the company.'],
        ['slot' => 'familiar_3', 'section' => 'familiar', 'sort' => 3, 'image_path' => 'assets/img/landing/landing-office.png', 'title' => 'Can you only open the books at the office?', 'body' => 'If you are on the road, the PC is off, or the accountant is out, the records are out of reach.'],
        ['slot' => 'help_1', 'section' => 'help', 'sort' => 4, 'image_path' => 'assets/img/landing/landing-share.png', 'title' => 'Your client\'s brand. Many templates.', 'body' => 'Every quotation, invoice and receipt is customised to the client\'s logo and colours. Choose from many templates, then email, WhatsApp or print the sheet.'],
        ['slot' => 'help_2', 'section' => 'help', 'sort' => 5, 'image_path' => 'assets/img/landing/landing-anywhere.png', 'title' => 'Open the books from wherever you are.', 'body' => 'Anywhere in the world. Sign in and this month is there - invoices, receipts, expenses, reports - on the screen in front of you.'],
        ['slot' => 'help_3', 'section' => 'help', 'sort' => 6, 'image_path' => 'assets/img/landing/landing-desk.png', 'title' => 'Quotes, invoices, receipts. One desk.', 'body' => 'Pick a template once. The whole books print in that layout, in the company colours. Quotations convert to invoices. Invoices take full or part receipts.'],
        ['slot' => 'steps_1', 'section' => 'steps', 'sort' => 7, 'image_path' => 'assets/img/landing/landing-form.png', 'title' => 'Register', 'body' => 'Name, company, email, phone. That is the whole form. No password to invent. From any country.'],
        ['slot' => 'steps_2', 'section' => 'steps', 'sort' => 8, 'image_path' => 'assets/img/landing/landing-call.png', 'title' => 'We call you', 'body' => 'A Vellisys admin sees the sign-up and reaches out to onboard the company.'],
        ['slot' => 'steps_3', 'section' => 'steps', 'sort' => 9, 'image_path' => 'assets/img/landing/landing-live.png', 'title' => 'Get onboarded', 'body' => 'You get a login. The books are yours, in your currency, on any device, any time.'],
    ];
}

function landing_cards(string $section = ''): array
{
    return folio_remember('landing_cards:' . $section, static function () use ($section): array {
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
    });
}

function landing_card_image_url(array $card): string
{
    $rel = ltrim((string) ($card['image_path'] ?? ''), '/');
    $full = $rel !== '' ? ROOT_PATH . '/' . $rel : '';
    if ($full && is_file($full)) {
        return url($rel) . '?v=' . filemtime($full);
    }
    foreach (landing_card_defaults() as $d) {
        if ($d['slot'] === ($card['slot'] ?? '')) {
            $fallback = ltrim($d['image_path'], '/');
            $ff = ROOT_PATH . '/' . $fallback;
            if (is_file($ff)) {
                return url($fallback) . '?v=' . filemtime($ff);
            }
        }
    }
    return product_mark_url();
}

function product_icons(): void
{
    $mark = asset('img/vellisys-avatar.png');
    if (!is_file(ROOT_PATH . '/assets/img/vellisys-avatar.png')) {
        $mark = product_mark_url();
    }
    $apple = is_file(ROOT_PATH . '/assets/img/vellisys-avatar.png')
        ? asset('img/vellisys-avatar.png')
        : url('assets/img/pwa-180.png');
    echo '<link rel="icon" type="image/png" href="' . h($mark) . '">';
    echo '<link rel="apple-touch-icon" href="' . h($apple) . '">';
    echo '<link rel="manifest" href="' . h(url('manifest.php')) . '">';
    echo '<meta name="theme-color" content="#08143A">';
    echo '<meta name="mobile-web-app-capable" content="yes">';
    echo '<meta name="apple-mobile-web-app-capable" content="yes">';
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">';
    echo '<meta name="apple-mobile-web-app-title" content="' . h(product_name()) . '">';
}

function folio_critical_css(string $surface = 'landing'): void
{
    if ($surface === 'desk') {
        echo '<style>html,body{margin:0;background:#fff}html{background:#fff}body{font-family:Montserrat,"Segoe UI",sans-serif;color:#10182c}.desk-body{background:#fff}.app{display:flex;min-height:100vh}.nav{width:72px;flex-shrink:0;background:#fff}</style>';
        return;
    }
    echo '<style>html{background:#f5f7fc;scroll-behavior:smooth;overflow-x:hidden;overflow-x:clip}body{margin:0;font-family:Montserrat,"Segoe UI",sans-serif;color:#10182c;background:#f5f7fc}body.gate{background:#08143a;color:#fff}.lp-chrome{position:sticky;top:0;z-index:40}.lp-ticker{background:#08143a;color:#fff;height:34px;overflow:hidden}.lp-nav{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 5vw;background:rgba(255,255,255,.92);border-bottom:1px solid rgba(8,20,58,.08)}.lp-logo{display:block;height:38px;width:auto;background:transparent}.lp-btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 18px;border-radius:12px;font-weight:700;text-decoration:none}.lp-btn-solid{background:#1e4eff;color:#fff}.lp-btn-ghost{background:#fff;color:#08143a;border:1px solid rgba(8,20,58,.12)}.lp-floats{position:fixed;right:16px;bottom:16px;z-index:80}.lp-wa-fab{width:48px;height:48px;border:0;border-radius:50%;background:#25d366;color:#fff}</style>';
}

function folio_stylesheet(string $path, bool $preload = true): void
{
    $href = h(asset($path));
    if ($preload) {
        echo '<link rel="preload" href="' . $href . '" as="style">';
    }
    echo '<link rel="stylesheet" href="' . $href . '">';
}

function folio_css_links(bool $critical = true, ?bool $sheet = null): void
{
    if ($critical) {
        folio_critical_css('desk');
    }
    folio_stylesheet('css/app.css');
    if ($sheet === null) {
        $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $sheet = in_array($here, ['document_view.php', 'document_new.php', 'document_action.php', 'share.php'], true);
    }
    if ($sheet) {
        folio_stylesheet('css/designs.css', false);
    }
}

function folio_pwa_detect_script(): void
{
    echo '<script>(function(){var d=document.documentElement;if(navigator.standalone===true){d.classList.add("is-pwa");return}var m=["standalone","fullscreen","minimal-ui","window-controls-overlay"];for(var i=0;i<m.length;i++){try{if(window.matchMedia("(display-mode: "+m[i]+")").matches){d.classList.add("is-pwa");return}}catch(e){}}})();</script>';
}

function folio_landing_head(): void
{
    folio_critical_css('landing');
    folio_stylesheet('css/landing.css');
    folio_font_links();
    product_public_meta();
    folio_pwa_detect_script();
}

function product_seo_description(): string
{
    return 'Vellisys keeps quotations, invoices and receipts on one desk, in your branding and your currency. Branded books software for East Africa, Africa and worldwide. Anywhere in the world: pay, register, or book a demo, then get onboarded.';
}

function product_public_meta(): void
{
    $name = product_name();
    $desc = product_seo_description();
    $url = function_exists('absolute_url') ? absolute_url('') : '/';
    $logo = function_exists('absolute_url') ? absolute_url(ltrim((string) parse_url(product_original_logo_url(), PHP_URL_PATH), '/')) : product_original_logo_url();
    echo '<meta name="description" content="' . h($desc) . '">' . "\n";
    echo '<meta name="keywords" content="Vellisys, East Africa accounting software, Africa invoicing, branded books, Uganda, Kenya, financial management">' . "\n";
    echo '<meta property="og:site_name" content="' . h($name) . '">' . "\n";
    echo '<meta property="og:title" content="' . h($name . ' · Stop losing the books') . '">' . "\n";
    echo '<meta property="og:description" content="' . h($desc) . '">' . "\n";
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:url" content="' . h($url) . '">' . "\n";
    echo '<meta property="og:image" content="' . h($logo) . '">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . h($name . ' · Stop losing the books') . '">' . "\n";
    echo '<meta name="twitter:description" content="' . h($desc) . '">' . "\n";
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => $name,
        'url' => $url,
        'image' => $logo,
        'applicationCategory' => 'FinanceApplication',
        'operatingSystem' => 'Web',
        'description' => $desc,
        'offers' => [
            '@type' => 'Offer',
            'priceCurrency' => 'UGX',
            'availability' => 'https://schema.org/InStock',
            'url' => function_exists('absolute_url') ? absolute_url('index.php#pricing') : $url,
        ],
        'areaServed' => [
            ['@type' => 'AdministrativeArea', 'name' => 'East Africa'],
            ['@type' => 'Continent', 'name' => 'Africa'],
            ['@type' => 'Place', 'name' => 'Worldwide'],
        ],
        'brand' => [
            '@type' => 'Brand',
            'name' => $name,
        ],
        'featureList' => [
            'Branded quotations, invoices and receipts',
            'Company logo, colours and currency',
            'Branded books desk for companies worldwide',
            'East Africa, Africa and worldwide',
        ],
    ];
    echo '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n";
}

function folio_font_links(): void
{
    echo '<link rel="preload" href="' . h(asset('fonts/montserrat-400.woff2')) . '" as="font" type="font/woff2" crossorigin>';
    echo '<link rel="preload" href="' . h(asset('fonts/montserrat-600.woff2')) . '" as="font" type="font/woff2" crossorigin>';
    echo '<link rel="preload" href="' . h(asset('fonts/montserrat-700.woff2')) . '" as="font" type="font/woff2" crossorigin>';
}

function signup_source_label(?string $source): string
{
    return match ($source ?? '') {
        'quote' => 'Quote',
        'checkout' => 'Checkout',
        'demo' => 'Demo',
        'register' => 'Register',
        default => 'Sign-up',
    };
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
