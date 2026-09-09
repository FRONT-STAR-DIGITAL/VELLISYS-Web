<?php
declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function ugx($amount): string
{
    return 'UGX ' . number_format((int) $amount, 0, '.', ',');
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

function branding(bool $refresh = false): array
{
    static $row = null;
    if ($refresh) {
        $row = null;
    }
    if ($row === null) {
        $row = db_one('SELECT * FROM branding WHERE id = 1') ?? [
            'name' => 'Folio',
            'tagline' => 'Your invoices, in your colours',
            'brand_color' => '#82B440',
            'logo_path' => 'assets/img/ofagros-logo.png',
            'prefix' => 'OFG',
            'plan' => 'sme',
            'phone' => '',
            'email' => '',
            'address' => '',
            'website' => '',
            'tin' => '',
            'payment_note' => '',
            'invoice_comments' => '',
        ];
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
    if ($kind === 'expense') {
        return db_all("SELECT * FROM parties WHERE kind IN ('supplier','both') ORDER BY name");
    }
    return db_all("SELECT * FROM parties WHERE kind IN ('customer','both') ORDER BY name");
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
        'letter' => ['title' => 'Letters', 'singular' => 'Letter', 'heading' => 'LETTER', 'verb' => 'New letter'],
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
