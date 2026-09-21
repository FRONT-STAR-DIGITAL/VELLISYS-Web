<?php
declare(strict_types=1);

function import_kinds(): array
{
    $kinds = [
        'clients' => [
            'title' => 'Clients',
            'lead' => 'People and firms you already bill or pay. One name per row.',
            'icon' => 'clients',
            'file' => 'vellisys-clients',
        ],
        'documents' => [
            'title' => 'Documents',
            'lead' => 'Quotations, invoices, expenses, delivery notes and letters from the old books. One line per row; same Group number = one sheet.',
            'icon' => 'invoice',
            'file' => 'vellisys-documents',
        ],
        'receipts' => [
            'title' => 'Receipts',
            'lead' => 'Money already received. Put the old invoice number in Invoice number when you have it.',
            'icon' => 'receipt',
            'file' => 'vellisys-receipts',
        ],
        'sales' => [
            'title' => 'Sales',
            'lead' => 'Past sales that should sit as invoices. Paid = Y also writes a matching receipt.',
            'icon' => 'cart',
            'file' => 'vellisys-sales',
        ],
    ];
    if (company_stock_enabled()) {
        $kinds['stock'] = [
            'title' => 'Stock',
            'lead' => 'Products and services. Type is product or service. Services skip buying price and opening quantity.',
            'icon' => 'package',
            'file' => 'stock-template',
        ];
    }
    return $kinds;
}

function import_col_index(string $cellRef): int
{
    if (!preg_match('/^([A-Z]+)/', strtoupper($cellRef), $m)) {
        return 0;
    }
    $n = 0;
    foreach (str_split($m[1]) as $ch) {
        $n = $n * 26 + (ord($ch) - 64);
    }
    return max(0, $n - 1);
}

function import_xlsx_bytes(array $rows, string $sheetName = 'Sheet1'): string
{
    $xmlRows = '';
    $r = 1;
    foreach ($rows as $row) {
        $xmlRows .= '<row r="' . $r . '">';
        $c = 0;
        foreach ($row as $val) {
            $col = '';
            $n = $c;
            do {
                $col = chr(65 + ($n % 26)) . $col;
                $n = intdiv($n, 26) - 1;
            } while ($n >= 0);
            $esc = htmlspecialchars((string) $val, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $xmlRows .= '<c r="' . $col . $r . '" t="inlineStr"><is><t>' . $esc . '</t></is></c>';
            $c++;
        }
        $xmlRows .= '</row>';
        $r++;
    }
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
        . $xmlRows . '</sheetData></worksheet>';
    $nameEsc = htmlspecialchars($sheetName, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $nameEsc . '" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>';
    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', $ct);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('xl/workbook.xml', $wb);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();
    $bytes = (string) file_get_contents($tmp);
    @unlink($tmp);
    return $bytes;
}

function import_parse_xlsx(string $path): array
{
    if (!class_exists('ZipArchive')) {
        return [];
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return [];
    }
    $shared = [];
    $ss = $zip->getFromName('xl/sharedStrings.xml');
    if (is_string($ss) && $ss !== '') {
        if (preg_match_all('/<si\b[^>]*>(.*?)<\/si>/s', $ss, $sis)) {
            foreach ($sis[1] as $si) {
                $text = '';
                if (preg_match_all('/<t\b[^>]*>([^<]*)<\/t>/', $si, $tm)) {
                    $text = implode('', $tm[1]);
                }
                $shared[] = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }
    }
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!is_string($sheet) || $sheet === '') {
        return [];
    }
    $rows = [];
    if (!preg_match_all('/<row\b[^>]*>(.*?)<\/row>/s', $sheet, $rowMatch)) {
        return [];
    }
    foreach ($rowMatch[1] as $rowXml) {
        $cells = [];
        if (!preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/s', $rowXml, $cMatch, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($cMatch as $c) {
            $attrs = $c[1];
            $inner = $c[2];
            $ref = '';
            if (preg_match('/\br="([A-Z]+\d+)"/', $attrs, $rm)) {
                $ref = $rm[1];
            }
            $idx = $ref !== '' ? import_col_index($ref) : count($cells);
            $val = '';
            $type = '';
            if (preg_match('/\bt="([^"]+)"/', $attrs, $tm)) {
                $type = $tm[1];
            }
            if ($type === 'inlineStr' || $type === 'str') {
                if (preg_match_all('/<t\b[^>]*>([^<]*)<\/t>/', $inner, $tt)) {
                    $val = implode('', $tt[1]);
                } elseif (preg_match('/<v>([^<]*)<\/v>/', $inner, $vm)) {
                    $val = $vm[1];
                }
            } elseif ($type === 's') {
                $n = 0;
                if (preg_match('/<v>([^<]*)<\/v>/', $inner, $vm)) {
                    $n = (int) $vm[1];
                }
                $val = (string) ($shared[$n] ?? '');
            } elseif (preg_match('/<v>([^<]*)<\/v>/', $inner, $vm)) {
                $val = $vm[1];
            }
            $val = html_entity_decode((string) $val, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $cells[$idx] = $val;
        }
        if (!$cells) {
            continue;
        }
        $max = max(array_keys($cells));
        $out = [];
        for ($i = 0; $i <= $max; $i++) {
            $out[] = $cells[$i] ?? '';
        }
        $rows[] = $out;
    }
    return $rows;
}

function import_parse_csv(string $path): array
{
    $text = (string) file_get_contents($path);
    if ($text === '') {
        return [];
    }
    if (str_starts_with($text, "\xEF\xBB\xBF")) {
        $text = substr($text, 3);
    }
    $rows = [];
    foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $line) {
        if (trim($line) === '') {
            continue;
        }
        $rows[] = str_getcsv($line);
    }
    return $rows;
}

function import_parse_upload(string $tmp, string $name): array
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === 'xlsx') {
        return import_parse_xlsx($tmp);
    }
    if (in_array($ext, ['csv', 'txt'], true)) {
        return import_parse_csv($tmp);
    }
    $head = (string) file_get_contents($tmp, false, null, 0, 4);
    if (str_starts_with($head, 'PK')) {
        return import_parse_xlsx($tmp);
    }
    return import_parse_csv($tmp);
}

function import_header_key(string $raw): string
{
    $k = strtolower(trim($raw));
    $k = str_replace(['/', '\\'], ' ', $k);
    $k = preg_replace('/[^a-z0-9]+/', '_', $k) ?? $k;
    $k = trim($k, '_');
    $aliases = [
        'client' => 'client',
        'client_name' => 'client',
        'customer' => 'client',
        'customer_name' => 'client',
        'party' => 'client',
        'name' => 'name',
        'kind' => 'kind',
        'type' => 'kind',
        'tin' => 'tin',
        'contact' => 'contact_person',
        'contact_person' => 'contact_person',
        'phone' => 'phone',
        'phone_1' => 'phone',
        'phone2' => 'phone2',
        'phone_2' => 'phone2',
        'email' => 'email',
        'address' => 'address',
        'city' => 'city',
        'country' => 'country',
        'notes' => 'notes',
        'note' => 'notes',
        'group' => 'group',
        'group_id' => 'group',
        'sheet' => 'group',
        'date' => 'date',
        'issued' => 'date',
        'number' => 'number',
        'doc_number' => 'number',
        'document_number' => 'number',
        'due_date' => 'due_date',
        'due' => 'due_date',
        'item' => 'item',
        'item_name' => 'item',
        'product' => 'item',
        'description' => 'description',
        'qty' => 'qty',
        'quantity' => 'qty',
        'unit' => 'unit',
        'rate' => 'rate',
        'price' => 'rate',
        'unit_price' => 'rate',
        'amount' => 'amount',
        'tax' => 'tax',
        'tax_y_n' => 'tax',
        'vat' => 'tax',
        'method' => 'method',
        'payment_method' => 'method',
        'reference' => 'reference',
        'ref' => 'reference',
        'payment_ref' => 'reference',
        'related_number' => 'related_number',
        'invoice_number' => 'related_number',
        'invoice' => 'related_number',
        'currency' => 'currency',
        'category' => 'category',
        'expense_category' => 'category',
        'paid' => 'paid',
        'paid_y_n' => 'paid',
        'subject' => 'subject',
        'body' => 'body',
        'sku' => 'sku',
        'buying_price' => 'buy_price',
        'buy_price' => 'buy_price',
        'selling_price' => 'sell_price',
        'sell_price' => 'sell_price',
        'reorder_level' => 'reorder_level',
        'opening_qty' => 'qty_on_hand',
        'opening_quantity' => 'qty_on_hand',
        'qty_on_hand' => 'qty_on_hand',
    ];
    return $aliases[$k] ?? $k;
}

function import_assoc_rows(array $rows): array
{
    $header = [];
    $start = 0;
    foreach ($rows as $i => $row) {
        $first = trim((string) ($row[0] ?? ''));
        if ($first === '' && count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
            continue;
        }
        if (str_starts_with($first, '#')) {
            continue;
        }
        $header = [];
        foreach ($row as $cell) {
            $header[] = import_header_key((string) $cell);
        }
        $start = $i + 1;
        break;
    }
    if (!$header) {
        return [];
    }
    $out = [];
    for ($i = $start; $i < count($rows); $i++) {
        $row = $rows[$i];
        $first = trim((string) ($row[0] ?? ''));
        if (str_starts_with($first, '#')) {
            continue;
        }
        $assoc = [];
        $any = false;
        foreach ($header as $ci => $key) {
            if ($key === '') {
                continue;
            }
            $val = trim((string) ($row[$ci] ?? ''));
            if ($val !== '') {
                $any = true;
            }
            if (!isset($assoc[$key]) || $assoc[$key] === '') {
                $assoc[$key] = $val;
            }
        }
        if ($any) {
            $out[] = $assoc;
        }
    }
    return $out;
}

function import_parse_date(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return $raw;
    }
    if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/', $raw, $m)) {
        $d = (int) $m[1];
        $mo = (int) $m[2];
        $y = (int) $m[3];
        if ($d > 12 && $mo <= 12) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
        if ($mo > 12 && $d <= 12) {
            return sprintf('%04d-%02d-%02d', $y, $d, $mo);
        }
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
    if (is_numeric($raw)) {
        $n = (float) $raw;
        if ($n > 20000 && $n < 80000) {
            $base = new DateTimeImmutable('1899-12-30');
            return $base->modify('+' . (int) round($n) . ' days')->format('Y-m-d');
        }
    }
    $ts = strtotime($raw);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }
    return null;
}

function import_yes(string $raw): bool
{
    $v = strtoupper(trim($raw));
    return in_array($v, ['Y', 'YES', '1', 'TRUE', 'T'], true);
}

function import_money(string $raw): float
{
    $raw = trim(str_replace([',', ' '], '', $raw));
    if ($raw === '') {
        return 0.0;
    }
    return round((float) $raw, 2);
}

function import_kind(string $raw): string
{
    $v = strtolower(trim($raw));
    $v = str_replace([' ', '-'], '_', $v);
    return match ($v) {
        'quote', 'quotation', 'qtn', 'quotes' => 'quotation',
        'inv', 'invoice', 'invoices', 'bill' => 'invoice',
        'rct', 'receipt', 'receipts', 'payment' => 'receipt',
        'exp', 'expense', 'expenses', 'bill_from_supplier' => 'expense',
        'letter', 'letters', 'ltr' => 'letter',
        'delivery', 'del', 'delivery_note', 'dn' => 'delivery',
        'refund', 'refunds' => 'refund',
        'return', 'return_note', 'returns', 'ret' => 'return_note',
        'custom' => 'custom',
        default => $v,
    };
}

function import_party_kind(string $raw, string $fallback = 'customer'): string
{
    $v = strtolower(trim($raw));
    if (in_array($v, ['supplier', 'vendor', 'creditor'], true)) {
        return 'supplier';
    }
    if (in_array($v, ['both', 'customer_supplier'], true)) {
        return 'both';
    }
    if (in_array($v, ['customer', 'client', 'debtor'], true)) {
        return 'customer';
    }
    return in_array($fallback, ['customer', 'supplier', 'both'], true) ? $fallback : 'customer';
}

function import_find_or_create_party(array $fields, string $fallbackKind = 'customer'): int
{
    $cid = current_company_id();
    $name = mb_substr(trim((string) ($fields['name'] ?? $fields['client'] ?? '')), 0, 190);
    if ($name === '') {
        $name = 'Walk-in';
    }
    $kind = import_party_kind((string) ($fields['kind'] ?? ''), $fallbackKind);
    $found = db_one('SELECT * FROM parties WHERE company_id = ? AND name = ? ORDER BY id DESC LIMIT 1', 'is', [$cid, $name]);
    $tin = trim((string) ($fields['tin'] ?? '')) ?: null;
    $contact = trim((string) ($fields['contact_person'] ?? '')) ?: null;
    $phone = trim((string) ($fields['phone'] ?? '')) ?: null;
    $phone2 = trim((string) ($fields['phone2'] ?? '')) ?: null;
    $email = trim((string) ($fields['email'] ?? '')) ?: null;
    $address = trim((string) ($fields['address'] ?? '')) ?: null;
    $city = trim((string) ($fields['city'] ?? '')) ?: null;
    $country = trim((string) ($fields['country'] ?? '')) ?: null;
    $notes = trim((string) ($fields['notes'] ?? '')) ?: null;
    if ($found) {
        $id = (int) $found['id'];
        $nextKind = (string) $found['kind'];
        if ($nextKind !== $kind && $nextKind !== 'both' && $kind !== 'both') {
            $nextKind = 'both';
        } elseif ($kind === 'both') {
            $nextKind = 'both';
        }
        db_exec(
            'UPDATE parties SET kind=?, tin=COALESCE(NULLIF(?, \'\'), tin), contact_person=COALESCE(NULLIF(?, \'\'), contact_person), phone=COALESCE(NULLIF(?, \'\'), phone), phone2=COALESCE(NULLIF(?, \'\'), phone2), email=COALESCE(NULLIF(?, \'\'), email), address=COALESCE(NULLIF(?, \'\'), address), city=COALESCE(NULLIF(?, \'\'), city), country=COALESCE(NULLIF(?, \'\'), country), notes=COALESCE(NULLIF(?, \'\'), notes) WHERE id=? AND company_id=?',
            'ssssssssssii',
            [$nextKind, $tin ?? '', $contact ?? '', $phone ?? '', $phone2 ?? '', $email ?? '', $address ?? '', $city ?? '', $country ?? '', $notes ?? '', $id, $cid]
        );
        return $id;
    }
    return db_exec(
        'INSERT INTO parties (company_id, name, kind, status, tin, contact_person, phone, phone2, email, address, city, country, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
        'issssssssssss',
        [$cid, $name, $kind, 'active', $tin, $contact, $phone, $phone2, $email, $address, $city, $country, $notes]
    );
}

function import_number_taken(string $number): bool
{
    $number = trim($number);
    if ($number === '') {
        return false;
    }
    $row = db_one('SELECT id FROM documents WHERE company_id = ? AND number = ?', 'is', [current_company_id(), $number]);
    return (bool) $row;
}

function import_find_document_id(string $number): ?int
{
    $number = trim($number);
    if ($number === '') {
        return null;
    }
    $row = db_one('SELECT id FROM documents WHERE company_id = ? AND number = ?', 'is', [current_company_id(), $number]);
    return $row ? (int) $row['id'] : null;
}

function import_line_item(array $row, float $fallbackRate = 0): array
{
    $qty = import_money((string) ($row['qty'] ?? '1'));
    if ($qty == 0.0) {
        $qty = 1.0;
    }
    $rate = import_money((string) ($row['rate'] ?? ''));
    if ($rate == 0.0 && isset($row['amount']) && import_money((string) $row['amount']) != 0.0 && $qty != 0.0) {
        $rate = round(import_money((string) $row['amount']) / $qty, 2);
    }
    if ($rate == 0.0 && $fallbackRate != 0.0) {
        $rate = $fallbackRate;
    }
    $item = trim((string) ($row['item'] ?? ''));
    $desc = trim((string) ($row['description'] ?? ''));
    if ($item === '') {
        $item = $desc !== '' ? mb_substr($desc, 0, 80) : 'Line';
    }
    return [
        'item_name' => mb_substr($item, 0, 160),
        'description' => $desc !== '' ? $desc : $item,
        'qty' => $qty,
        'unit' => mb_substr(trim((string) ($row['unit'] ?? 'lot')) ?: 'lot', 0, 30),
        'rate' => $rate,
        'taxed' => import_yes((string) ($row['tax'] ?? 'N')) ? 1 : 0,
    ];
}

function import_create_document(array $data): array
{
    $kind = (string) $data['kind'];
    $allowed = ['quotation', 'invoice', 'receipt', 'expense', 'letter', 'delivery', 'custom', 'refund', 'return_note'];
    if (!in_array($kind, $allowed, true)) {
        return ['ok' => false, 'error' => 'Unknown document kind.'];
    }
    $number = trim((string) ($data['number'] ?? ''));
    if ($number !== '' && import_number_taken($number)) {
        return ['ok' => false, 'error' => 'Number already on this desk.', 'skipped' => true];
    }
    $payload = $data;
    if ($number === '') {
        unset($payload['number']);
    }
    try {
        $id = create_document($payload);
        return ['ok' => true, 'id' => $id];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function import_template_rows(string $kind): array
{
    $note = ['# Fill this sheet, keep the header row, then upload in Settings → Bring in books. Dates as YYYY-MM-DD. Upload adds to this desk; it does not wipe what is already there.'];
    return match ($kind) {
        'clients' => [
            array_pad($note, 11, ''),
            ['Name', 'Kind', 'TIN', 'Contact person', 'Phone', 'Phone2', 'Email', 'Address', 'City', 'Country', 'Notes'],
            ['Acme Traders', 'customer', '', 'Jane Okello', '0700000001', '', 'accounts@acme.test', 'Plot 12 Kampala Rd', 'Kampala', 'Uganda', 'Brought from old books'],
            ['Nile Supplies', 'supplier', '', '', '0700000002', '', 'sales@nile.test', '', 'Jinja', 'Uganda', ''],
        ],
        'documents' => [
            array_pad(['# Kind: quotation, invoice, expense, delivery, letter. Same Group = one sheet. Leave Number blank to let Vellisys number it.'], 18, ''),
            ['Group', 'Kind', 'Date', 'Number', 'Client', 'Due date', 'Item', 'Description', 'Qty', 'Unit', 'Rate', 'Tax', 'Notes', 'Method', 'Reference', 'Related number', 'Currency', 'Category'],
            ['1', 'invoice', '2025-06-01', '', 'Acme Traders', '2025-06-30', 'Consulting', 'June retainer', '1', 'lot', '800000', 'Y', 'Opening invoice', '', '', '', '', ''],
            ['1', 'invoice', '2025-06-01', '', 'Acme Traders', '2025-06-30', 'Transport', 'Site visits', '2', 'trip', '40000', 'N', '', '', '', '', '', ''],
            ['2', 'quotation', '2025-05-20', '', 'Acme Traders', '', 'Website', 'Refresh home page', '1', 'job', '2500000', 'Y', '', '', '', '', '', ''],
            ['3', 'expense', '2025-06-04', '', 'Nile Supplies', '', 'Stationery', 'Toner and paper', '1', 'lot', '180000', 'N', '', '', '', '', '', 'Office'],
        ],
        'receipts' => [
            array_pad(['# Amount is what you received. Invoice number is the Vellisys or imported invoice to allocate against when you know it.'], 8, ''),
            ['Date', 'Client', 'Amount', 'Method', 'Reference', 'Invoice number', 'Notes', 'Currency'],
            ['2025-06-15', 'Acme Traders', '500000', 'Mobile money', 'MM-991', '', 'Part payment from old books', ''],
            ['2025-06-28', 'Acme Traders', '380000', 'Bank', 'FT-220', '', 'Balance on June invoice', ''],
        ],
        'sales' => [
            array_pad(['# Same Group = one invoice. Paid Y also writes a receipt. Tax Y/N. Leave Client blank for Walk-in.'], 13, ''),
            ['Group', 'Date', 'Client', 'Item', 'Description', 'Qty', 'Unit', 'Rate', 'Tax', 'Paid', 'Method', 'Notes', 'Currency'],
            ['1', '2025-07-02', 'Walk-in', 'Rice 25kg', '', '3', 'bag', '110000', 'Y', 'Y', 'Cash', 'Counter sale', ''],
            ['1', '2025-07-02', 'Walk-in', 'Bar soap', '', '6', 'pc', '2500', 'Y', 'Y', 'Cash', '', ''],
            ['2', '2025-07-03', 'Acme Traders', 'Delivery', 'Jinja drop', '1', 'trip', '80000', 'N', 'N', '', 'On account', ''],
        ],
        'stock' => stock_import_template_rows(),
        default => [array_pad($note, 2, ''), ['Name', 'Notes']],
    };
}

function import_send_template(string $kind): void
{
    $kinds = import_kinds();
    if (!isset($kinds[$kind])) {
        http_response_code(404);
        echo 'Unknown template.';
        exit;
    }
    if ($kind === 'stock') {
        stock_send_template();
        return;
    }
    $rows = import_template_rows($kind);
    $base = $kinds[$kind]['file'];
    if (class_exists('ZipArchive')) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $base . '.xlsx"');
        header('Cache-Control: no-store');
        echo import_xlsx_bytes($rows, $kinds[$kind]['title']);
        exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $base . '.csv"');
    $out = fopen('php://output', 'w');
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function import_clients(array $assoc): array
{
    $added = 0;
    $updated = 0;
    $skipped = 0;
    foreach ($assoc as $row) {
        $name = trim((string) ($row['name'] ?? $row['client'] ?? ''));
        if ($name === '') {
            $skipped++;
            continue;
        }
        $cid = current_company_id();
        $exists = db_one('SELECT id FROM parties WHERE company_id = ? AND name = ?', 'is', [$cid, $name]);
        import_find_or_create_party($row, 'customer');
        if ($exists) {
            $updated++;
        } else {
            $added++;
        }
    }
    return ['added' => $added, 'updated' => $updated, 'skipped' => $skipped, 'docs' => 0];
}

function import_group_rows(array $assoc): array
{
    $groups = [];
    $auto = 0;
    foreach ($assoc as $row) {
        $g = trim((string) ($row['group'] ?? ''));
        if ($g === '') {
            $auto++;
            $g = '__row_' . $auto;
        }
        $groups[$g][] = $row;
    }
    return $groups;
}

function import_documents(array $assoc): array
{
    $added = 0;
    $skipped = 0;
    $errors = 0;
    foreach (import_group_rows($assoc) as $rows) {
        $head = $rows[0];
        $kind = import_kind((string) ($head['kind'] ?? 'invoice'));
        if (!in_array($kind, ['quotation', 'invoice', 'receipt', 'expense', 'letter', 'delivery', 'custom', 'refund', 'return_note'], true)) {
            $kind = 'invoice';
        }
        $partyKind = $kind === 'expense' ? 'supplier' : 'customer';
        $partyId = import_find_or_create_party([
            'name' => $head['client'] ?? $head['name'] ?? '',
            'kind' => $partyKind,
        ], $partyKind);
        $date = import_parse_date((string) ($head['date'] ?? '')) ?: today();
        $due = import_parse_date((string) ($head['due_date'] ?? ''));
        $number = trim((string) ($head['number'] ?? ''));
        $items = [];
        foreach ($rows as $line) {
            if (in_array($kind, ['letter', 'custom'], true)) {
                continue;
            }
            $items[] = import_line_item($line);
        }
        $body = trim((string) ($head['body'] ?? $head['description'] ?? ''));
        $subject = trim((string) ($head['subject'] ?? $head['notes'] ?? ''));
        $related = import_find_document_id((string) ($head['related_number'] ?? ''));
        $res = import_create_document([
            'kind' => $kind,
            'date' => $date,
            'due_date' => $due,
            'number' => $number,
            'party_id' => $partyId,
            'items' => $items,
            'notes' => trim((string) ($head['notes'] ?? '')) ?: null,
            'subject' => $subject !== '' ? $subject : null,
            'body' => $body !== '' ? $body : null,
            'payment_method' => trim((string) ($head['method'] ?? '')) ?: null,
            'payment_ref' => trim((string) ($head['reference'] ?? '')) ?: null,
            'related_id' => $related,
            'expense_category' => trim((string) ($head['category'] ?? '')) ?: null,
            'currency' => trim((string) ($head['currency'] ?? '')) ?: default_currency(),
            'vat_rate' => company_tax_rate(),
        ]);
        if (!empty($res['ok'])) {
            $added++;
        } elseif (!empty($res['skipped'])) {
            $skipped++;
        } else {
            $errors++;
        }
    }
    return ['added' => $added, 'updated' => 0, 'skipped' => $skipped, 'docs' => $added, 'errors' => $errors];
}

function import_receipts(array $assoc): array
{
    $added = 0;
    $skipped = 0;
    $errors = 0;
    foreach ($assoc as $row) {
        $client = trim((string) ($row['client'] ?? $row['name'] ?? ''));
        $amount = import_money((string) ($row['amount'] ?? $row['rate'] ?? '0'));
        if ($amount <= 0) {
            $skipped++;
            continue;
        }
        $partyId = import_find_or_create_party(['name' => $client], 'customer');
        $related = import_find_document_id((string) ($row['related_number'] ?? ''));
        $date = import_parse_date((string) ($row['date'] ?? '')) ?: today();
        $number = trim((string) ($row['number'] ?? ''));
        $res = import_create_document([
            'kind' => 'receipt',
            'date' => $date,
            'number' => $number,
            'party_id' => $partyId,
            'allocated_amount' => $amount,
            'items' => [[
                'item_name' => 'Receipt',
                'description' => trim((string) ($row['notes'] ?? 'Payment received')) ?: 'Payment received',
                'qty' => 1,
                'unit' => 'lot',
                'rate' => $amount,
                'taxed' => 0,
            ]],
            'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
            'payment_method' => trim((string) ($row['method'] ?? '')) ?: null,
            'payment_ref' => trim((string) ($row['reference'] ?? '')) ?: null,
            'related_id' => $related,
            'currency' => trim((string) ($row['currency'] ?? '')) ?: default_currency(),
            'vat_rate' => 0,
        ]);
        if (!empty($res['ok'])) {
            $added++;
        } elseif (!empty($res['skipped'])) {
            $skipped++;
        } else {
            $errors++;
        }
    }
    return ['added' => $added, 'updated' => 0, 'skipped' => $skipped, 'docs' => $added, 'errors' => $errors];
}

function import_sales(array $assoc): array
{
    $added = 0;
    $receipts = 0;
    $skipped = 0;
    $errors = 0;
    foreach (import_group_rows($assoc) as $rows) {
        $head = $rows[0];
        $partyId = import_find_or_create_party([
            'name' => $head['client'] ?? $head['name'] ?? 'Walk-in',
            'kind' => 'customer',
        ], 'customer');
        $date = import_parse_date((string) ($head['date'] ?? '')) ?: today();
        $items = [];
        $paidFlag = false;
        foreach ($rows as $line) {
            $items[] = import_line_item($line);
            if (import_yes((string) ($line['paid'] ?? $head['paid'] ?? 'N'))) {
                $paidFlag = true;
            }
        }
        $number = trim((string) ($head['number'] ?? ''));
        $inv = import_create_document([
            'kind' => 'invoice',
            'date' => $date,
            'number' => $number,
            'party_id' => $partyId,
            'items' => $items,
            'notes' => trim((string) ($head['notes'] ?? '')) ?: null,
            'payment_method' => trim((string) ($head['method'] ?? '')) ?: null,
            'currency' => trim((string) ($head['currency'] ?? '')) ?: default_currency(),
            'vat_rate' => company_tax_rate(),
        ]);
        if (!empty($inv['skipped'])) {
            $skipped++;
            continue;
        }
        if (empty($inv['ok'])) {
            $errors++;
            continue;
        }
        $added++;
        if ($paidFlag) {
            $totals = document_totals(['items' => $items, 'vat_rate' => company_tax_rate()]);
            $pay = import_create_document([
                'kind' => 'receipt',
                'date' => $date,
                'party_id' => $partyId,
                'related_id' => (int) $inv['id'],
                'allocated_amount' => $totals['total'],
                'items' => [[
                    'item_name' => 'Receipt',
                    'description' => 'Payment on imported sale',
                    'qty' => 1,
                    'unit' => 'lot',
                    'rate' => $totals['total'],
                    'taxed' => 0,
                ]],
                'payment_method' => trim((string) ($head['method'] ?? '')) ?: 'Cash',
                'currency' => trim((string) ($head['currency'] ?? '')) ?: default_currency(),
                'vat_rate' => 0,
            ]);
            if (!empty($pay['ok'])) {
                $receipts++;
            }
        }
    }
    return ['added' => $added, 'updated' => 0, 'skipped' => $skipped, 'docs' => $added, 'receipts' => $receipts, 'errors' => $errors];
}

function import_run(string $kind, string $tmp, string $filename): array
{
    $kinds = import_kinds();
    if (!isset($kinds[$kind])) {
        return ['ok' => false, 'error' => 'Choose which template you are uploading.'];
    }
    $rows = import_parse_upload($tmp, $filename);
    if (!$rows) {
        return ['ok' => false, 'error' => 'Could not read that file. Download the template, fill it in Excel, and upload the .xlsx or .csv.'];
    }
    if (count($rows) > 2500) {
        return ['ok' => false, 'error' => 'That file has too many rows. Split it under 2,500 rows and upload again.'];
    }
    $assoc = import_assoc_rows($rows);
    if (!$assoc && $kind !== 'stock') {
        return ['ok' => false, 'error' => 'No data rows found under the header. Keep the header row from the template.'];
    }
    if ($kind === 'stock') {
        $res = stock_import_rows($rows);
        $res['docs'] = 0;
        $res['ok'] = true;
        $res['kind'] = 'stock';
        return $res;
    }
    $res = match ($kind) {
        'clients' => import_clients($assoc),
        'documents' => import_documents($assoc),
        'receipts' => import_receipts($assoc),
        'sales' => import_sales($assoc),
        default => ['added' => 0, 'updated' => 0, 'skipped' => 0, 'docs' => 0],
    };
    $res['ok'] = true;
    $res['kind'] = $kind;
    if (function_exists('record_company_activity') && (!empty($res['added']) || !empty($res['updated']) || !empty($res['receipts']))) {
        $bits = [];
        if (!empty($res['added'])) {
            $bits[] = $res['added'] . ' ' . $kinds[$kind]['title'];
        }
        if (!empty($res['updated'])) {
            $bits[] = $res['updated'] . ' updated';
        }
        if (!empty($res['receipts'])) {
            $bits[] = $res['receipts'] . ' receipts';
        }
        record_company_activity('settings', 'Imported books from Excel', [
            'detail' => implode(', ', $bits),
            'href' => 'settings.php#import',
            'ref_type' => 'import',
        ]);
    }
    return $res;
}

function import_flash_message(array $res): string
{
    $kind = (string) ($res['kind'] ?? '');
    $added = (int) ($res['added'] ?? 0);
    $updated = (int) ($res['updated'] ?? 0);
    $skipped = (int) ($res['skipped'] ?? 0);
    $errors = (int) ($res['errors'] ?? 0);
    $receipts = (int) ($res['receipts'] ?? 0);
    $parts = [];
    if ($kind === 'clients') {
        $parts[] = $added . ' new client' . ($added === 1 ? '' : 's');
        if ($updated) {
            $parts[] = $updated . ' already on the desk (details filled where blank)';
        }
    } elseif ($kind === 'stock') {
        $parts[] = $added . ' new item' . ($added === 1 ? '' : 's');
        if ($updated) {
            $parts[] = $updated . ' updated';
        }
    } elseif ($kind === 'sales') {
        $parts[] = $added . ' sale' . ($added === 1 ? '' : 's') . ' as invoices';
        if ($receipts) {
            $parts[] = $receipts . ' receipt' . ($receipts === 1 ? '' : 's') . ' for paid rows';
        }
    } else {
        $label = $kind === 'receipts' ? 'receipt' : 'document';
        $parts[] = $added . ' new ' . $label . ($added === 1 ? '' : 's');
    }
    if ($skipped) {
        $parts[] = $skipped . ' skipped (already numbered on this desk, or a blank row)';
    }
    if ($errors) {
        $parts[] = $errors . ' could not be saved';
    }
    $msg = 'Brought into this desk: ' . implode('. ', $parts) . '. Nothing already here was wiped.';
    return $msg;
}
