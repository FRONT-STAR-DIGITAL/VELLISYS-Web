<?php
declare(strict_types=1);

function today(): string
{
    return desk_now()->format('Y-m-d');
}

function line_amount(array $item): float
{
    return round((float) ($item['qty'] ?? 0) * (float) ($item['rate'] ?? 0), 2);
}

function line_item_name(array $item): string
{
    return trim((string) ($item['item_name'] ?? ''));
}

function line_item_description(array $item): string
{
    return trim((string) ($item['description'] ?? ''));
}

function doc_subtotal(array $items): float
{
    $sum = 0.0;
    foreach ($items as $item) {
        $sum += line_amount($item);
    }
    return round($sum, 2);
}

function doc_vat(array $items, float $rate): float
{
    if ($rate <= 0) {
        return 0.0;
    }
    $vat = 0.0;
    foreach ($items as $item) {
        if (!empty($item['taxed'])) {
            $vat += round(line_amount($item) * $rate, 2);
        }
    }
    return round($vat, 2);
}

function doc_total(array $items, float $rate): float
{
    return round(doc_subtotal($items) + doc_vat($items, $rate), 2);
}

function doc_shows_vat(array $doc): bool
{
    if ((float) ($doc['vat_rate'] ?? 0) <= 0) {
        return false;
    }
    foreach ($doc['items'] ?? [] as $item) {
        if (!empty($item['taxed'])) {
            return true;
        }
    }
    return false;
}

function document_totals(array $doc): array
{
    $items = $doc['items'] ?? [];
    $rate = (float) ($doc['vat_rate'] ?? 0);
    $net = doc_subtotal($items);
    $vat = doc_vat($items, $rate);
    return ['net' => $net, 'vat' => $vat, 'total' => $net + $vat];
}

function kind_code(string $kind): string
{
    return match ($kind) {
        'quotation' => 'QTN',
        'invoice' => 'INV',
        'receipt' => 'RCT',
        'expense' => 'EXP',
        'letter' => 'LTR',
        'delivery' => 'DEL',
        'custom' => 'CUS',
        'refund' => 'REF',
        'return_note' => 'RET',
        default => 'DOC',
    };
}

function next_sequence(string $kind): int
{
    $row = db_one('SELECT COALESCE(MAX(sequence), 0) + 1 AS n FROM documents WHERE kind = ? AND company_id = ?', 'si', [$kind, current_company_id()]);
    return (int) ($row['n'] ?? 1);
}

function default_number_format(): string
{
    return '{prefix}-{kind}-{yyyy}-{seq:4}';
}

function sanitize_number_format(string $raw): string
{
    $raw = trim($raw);
    $raw = str_replace(['—', '–', '−'], '-', $raw);
    if ($raw === '') {
        return default_number_format();
    }
    $left = preg_replace('/\{prefix\}|\{kind\}|\{yyyy\}|\{yy\}|\{seq(?::\d+)?\}/', '', $raw) ?? $raw;
    $left = preg_replace('/[A-Za-z0-9._\/\- ]/', '', $left) ?? $left;
    if ($left !== '') {
        return default_number_format();
    }
    if (!preg_match('/\{seq(?::\d+)?\}/', $raw)) {
        $raw = rtrim($raw, '-/ ') . '-{seq:4}';
    }
    return substr($raw, 0, 80);
}

function format_document_number(string $kind, int $sequence, ?array $brand = null): string
{
    $brand = $brand ?? branding();
    $fmt = sanitize_number_format((string) ($brand['number_format'] ?? ''));
    $prefix = strtoupper((string) ($brand['prefix'] ?? 'DOC'));
    $out = strtr($fmt, [
        '{prefix}' => $prefix,
        '{kind}' => kind_code($kind),
        '{yyyy}' => date('Y'),
        '{yy}' => date('y'),
        '{seq}' => (string) $sequence,
    ]);
    $out = preg_replace_callback('/\{seq:(\d+)\}/', static function (array $m) use ($sequence): string {
        return str_pad((string) $sequence, max(1, (int) $m[1]), '0', STR_PAD_LEFT);
    }, $out) ?? $out;
    return $out;
}

function next_number(string $kind, int $sequence): string
{
    return format_document_number($kind, $sequence);
}

function decode_letterhead($raw): array
{
    if (is_array($raw)) {
        return $raw;
    }
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : [];
}

function posted_letterhead(): array
{
    $out = [];
    foreach (['name', 'tagline', 'address', 'city', 'phone', 'email', 'tin', 'website'] as $key) {
        $out[$key] = trim((string) ($_POST['from_' . $key] ?? ''));
    }
    return $out;
}

function save_document_letterhead(int $id, array $fields): void
{
    $json = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    db_exec('UPDATE documents SET letterhead = ? WHERE id = ? AND company_id = ?', 'sii', [$json, $id, current_company_id()]);
}

function apply_letterhead_to_branding(array $fields): void
{
    if (trim((string) ($fields['name'] ?? '')) === '') {
        return;
    }
    db_exec(
        'UPDATE branding SET name=?, tagline=?, address=?, city=?, phone=?, email=?, tin=?, website=? WHERE company_id=?',
        'ssssssssi',
        [
            $fields['name'],
            $fields['tagline'] ?? '',
            $fields['address'] ?? '',
            $fields['city'] ?? '',
            $fields['phone'] ?? '',
            $fields['email'] ?? '',
            $fields['tin'] ?? '',
            $fields['website'] ?? '',
            current_company_id(),
        ]
    );
    branding(true);
}

function ensure_document_party(string $kind): int
{
    $cid = current_company_id();
    $partyId = (int) post('party_id');
    $name = trim((string) ($_POST['to_name'] ?? ''));
    if ($partyId > 0) {
        $row = db_one('SELECT id FROM parties WHERE id = ? AND company_id = ?', 'ii', [$partyId, $cid]);
        if ($row) {
            return (int) $row['id'];
        }
    }
    if ($name === '') {
        if ($kind === 'expense') {
            return 0;
        }
        throw new RuntimeException('Choose an existing client or type a new name.');
    }
    $found = db_one('SELECT id FROM parties WHERE company_id = ? AND name = ? ORDER BY id DESC LIMIT 1', 'is', [$cid, $name]);
    if ($found) {
        return (int) $found['id'];
    }
    $partyKind = $kind === 'expense' ? 'supplier' : 'customer';
    if (in_array($kind, ['refund', 'return_note'], true)) {
        $partyKind = 'both';
    }
    $phone = trim((string) ($_POST['to_phone'] ?? '')) ?: null;
    $email = trim((string) ($_POST['to_email'] ?? '')) ?: null;
    $address = trim((string) ($_POST['to_address'] ?? '')) ?: null;
    $contact = trim((string) ($_POST['to_contact'] ?? '')) ?: null;
    $tin = trim((string) ($_POST['to_tin'] ?? '')) ?: null;
    $phone2 = trim((string) ($_POST['to_phone2'] ?? '')) ?: null;
    $city = trim((string) ($_POST['to_city'] ?? '')) ?: null;
    $country = trim((string) ($_POST['to_country'] ?? '')) ?: null;
    $entity = function_exists('normalize_party_entity') ? normalize_party_entity((string) ($_POST['to_entity'] ?? '')) : 'person';
    $profile = function_exists('posted_to_extras') ? json_encode(posted_to_extras(), JSON_UNESCAPED_UNICODE) : null;
    $id = db_exec(
        'INSERT INTO parties (company_id, name, kind, tin, contact_person, phone, phone2, email, address, city, country, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        'isssssssssss',
        [$cid, $name, $partyKind, $tin, $contact, $phone, $phone2, $email, $address, $city, $country, null]
    );
    persist_party_client_fields((int) $id, [
        'entity' => $entity,
        'profile' => $profile,
    ]);
    return (int) $id;
}

function persist_party_client_fields(int $partyId, array $fields): void
{
    if ($partyId < 1 || !function_exists('db_has_column')) {
        return;
    }
    $db = db();
    $cid = current_company_id();
    if (isset($fields['entity']) && db_has_column($db, 'parties', 'entity')) {
        db_exec('UPDATE parties SET entity=? WHERE id=? AND company_id=?', 'sii', [(string) $fields['entity'], $partyId, $cid]);
    }
    if (array_key_exists('profile', $fields) && db_has_column($db, 'parties', 'profile')) {
        db_exec('UPDATE parties SET profile=? WHERE id=? AND company_id=?', 'sii', [$fields['profile'], $partyId, $cid]);
    }
}

function persist_document_party_extras(int $docId, array $extras): void
{
    if ($docId < 1 || !function_exists('db_has_column') || !db_has_column(db(), 'documents', 'party_extras')) {
        return;
    }
    db_exec(
        'UPDATE documents SET party_extras=? WHERE id=? AND company_id=?',
        'sii',
        [json_encode(function_exists('compact_party_extras') ? compact_party_extras($extras) : $extras, JSON_UNESCAPED_UNICODE) ?: '{}', $docId, current_company_id()]
    );
}

function apply_posted_party(int $partyId): void
{
    if ($partyId <= 0) {
        return;
    }
    $cid = current_company_id();
    $party = db_one('SELECT * FROM parties WHERE id = ? AND company_id = ?', 'ii', [$partyId, $cid]);
    if (!$party) {
        return;
    }
    $name = trim((string) ($_POST['to_name'] ?? ''));
    if ($name === '') {
        $name = (string) ($party['name'] ?? '');
    }
    if ($name === '') {
        return;
    }
    $phone = array_key_exists('to_phone', $_POST)
        ? (trim((string) $_POST['to_phone']) ?: null)
        : ($party['phone'] ?? null);
    $phone2 = array_key_exists('to_phone2', $_POST)
        ? (trim((string) $_POST['to_phone2']) ?: null)
        : ($party['phone2'] ?? null);
    $email = array_key_exists('to_email', $_POST)
        ? (trim((string) $_POST['to_email']) ?: null)
        : ($party['email'] ?? null);
    $address = array_key_exists('to_address', $_POST)
        ? (trim((string) $_POST['to_address']) ?: null)
        : ($party['address'] ?? null);
    $contact = array_key_exists('to_contact', $_POST)
        ? (trim((string) $_POST['to_contact']) ?: null)
        : ($party['contact_person'] ?? null);
    $tin = array_key_exists('to_tin', $_POST)
        ? (trim((string) $_POST['to_tin']) ?: null)
        : ($party['tin'] ?? null);
    $city = array_key_exists('to_city', $_POST)
        ? (trim((string) $_POST['to_city']) ?: null)
        : ($party['city'] ?? null);
    $country = array_key_exists('to_country', $_POST)
        ? (trim((string) $_POST['to_country']) ?: null)
        : ($party['country'] ?? null);
    db_exec(
        'UPDATE parties SET name=?, phone=?, phone2=?, email=?, address=?, contact_person=?, tin=?, city=?, country=? WHERE id=? AND company_id=?',
        'sssssssssii',
        [$name, $phone, $phone2, $email, $address, $contact, $tin, $city, $country, $partyId, $cid]
    );
    if (function_exists('posted_to_extras')) {
        $merged = merge_party_profile(party_profile($party), posted_to_extras());
        persist_party_client_fields($partyId, [
            'entity' => normalize_party_entity((string) ($_POST['to_entity'] ?? ($party['entity'] ?? ''))),
            'profile' => json_encode(compact_party_extras($merged), JSON_UNESCAPED_UNICODE) ?: '{}',
        ]);
    }
}

function apply_efris_mark(int $id, string $number, string $date, $grand): void
{
    // EFRIS marks are no longer printed on documents.
}

function create_document(array $data): int
{
    $kind = (string) $data['kind'];
    $seq = (int) ($data['sequence'] ?? next_sequence($kind));
    $number = (string) ($data['number'] ?? next_number($kind, $seq));
    $date = (string) ($data['date'] ?? today());
    $due = $data['due_date'] ?? null;
    $due = $due === '' ? null : $due;
    $party = (int) ($data['party_id'] ?? 0);
    if ($party < 1) {
        if ($kind !== 'expense') {
            throw new RuntimeException('Choose an existing client or type a new name.');
        }
        $party = null;
    }
    $rate = (float) ($data['vat_rate'] ?? company_tax_rate());
    $notes = $data['notes'] ?? null;
    $subject = $data['subject'] ?? null;
    $body = $data['body'] ?? null;
    $status = (string) ($data['status'] ?? 'issued');
    $related = isset($data['related_id']) && $data['related_id'] ? (int) $data['related_id'] : null;
    $method = $data['payment_method'] ?? null;
    $ref = $data['payment_ref'] ?? null;
    $alloc = isset($data['allocated_amount']) ? (float) $data['allocated_amount'] : null;
    $cat = $data['expense_category'] ?? null;
    $tpl = $data['letter_template'] ?? null;
    if ($tpl === 'none' || $tpl === '') {
        $tpl = null;
    }
    $addSig = !empty($data['add_signature']) ? 1 : 0;
    $currency = normalize_currency((string) ($data['currency'] ?? default_currency()), default_currency());
    $docTpl = trim((string) ($data['doc_template'] ?? ''));
    if ($docTpl === '' || !array_key_exists($docTpl, doc_templates())) {
        $docTpl = doc_template_key();
    }
    $userId = (int) ($data['created_by'] ?? ($_SESSION['user_id'] ?? 0));
    $branchId = function_exists('resolve_document_branch_id')
        ? resolve_document_branch_id($data['branch_id'] ?? null)
        : null;
    $items = $data['items'] ?? [];
    $customValues = $data['custom_values'] ?? null;
    if (is_array($customValues)) {
        $customValues = json_encode($customValues, JSON_UNESCAPED_UNICODE) ?: '{}';
    }
    $cid = current_company_id();

    if (in_array($kind, ['letter', 'custom'], true)) {
        $items = $items ?: [['item_name' => '', 'description' => '-', 'qty' => 1, 'unit' => 'lot', 'rate' => 0, 'taxed' => 0]];
        $rate = 0;
    }
    if ($kind === 'receipt') {
        if (!$alloc || $alloc <= 0) {
            $alloc = doc_subtotal($items);
        }
        if ($related) {
            $alloc = cap_receipt_allocation($alloc, $related);
        }
        if (!$items && $alloc > 0) {
            $label = 'Payment received';
            $inv = $related ? db_one('SELECT number FROM documents WHERE id = ? AND company_id = ?', 'ii', [$related, $cid]) : null;
            if ($inv) {
                $label = 'Payment on ' . $inv['number'];
            }
            $items = [['item_name' => 'Receipt', 'description' => $label, 'qty' => 1, 'unit' => 'lot', 'rate' => $alloc, 'taxed' => 0]];
        }
    }

    $id = db_exec(
        'INSERT INTO documents (company_id, kind, sequence, number, date, due_date, party_id, vat_rate, notes, subject, body, custom_values, status, related_id, payment_method, payment_ref, allocated_amount, expense_category, letter_template, created_by, branch_id, currency, doc_template, add_signature)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        'isisssidsssssissdssiissi',
        [$cid, $kind, $seq, $number, $date, $due, $party, $rate, $notes, $subject, $body, $customValues, $status, $related, $method, $ref, $alloc, $cat, $tpl, $userId, $branchId, $currency, $docTpl, $addSig]
    );

    if (function_exists('persist_document_party_extras')) {
        persist_document_party_extras((int) $id, is_array($data['party_extras'] ?? null) ? $data['party_extras'] : []);
    }

    insert_document_items($id, $items);
    if (function_exists('stock_apply_document')) {
        stock_apply_document($id, $kind, $items);
    }

    if (function_exists('record_company_activity')) {
        $partyName = '';
        $partyRow = db_one('SELECT name FROM parties WHERE id = ? AND company_id = ?', 'ii', [$party, $cid]);
        if ($partyRow) {
            $partyName = (string) $partyRow['name'];
        }
        $meta = kind_meta($kind);
        $payKinds = ['receipt' => 'payment', 'expense' => 'payment', 'refund' => 'payment'];
        record_company_activity($payKinds[$kind] ?? 'document', $meta['singular'] . ' ' . $number, [
            'detail' => trim($partyName . ($status === 'void' ? ' · voided' : ' issued')),
            'href' => 'document_view.php?id=' . $id,
            'ref_type' => 'document',
            'ref_id' => $id,
            'company_id' => $cid,
            'user_id' => $userId,
            'branch_id' => $branchId,
        ]);
    }

    if ($kind === 'invoice' && $due && function_exists('push_notify_item')) {
        push_notify_item([
            'title' => $number,
            'meta' => 'Invoice due ' . format_date($due),
            'href' => url('document_view.php?id=' . $id),
            'key' => 'invoice:' . $id,
        ], 'company', $cid);
    }

    return $id;
}

function insert_document_items(int $id, array $items): void
{
    foreach ($items as $item) {
        $name = trim((string) ($item['item_name'] ?? ''));
        $desc = trim((string) ($item['description'] ?? ''));
        if ($name === '' && $desc === '') {
            continue;
        }
        $qty = (float) ($item['qty'] ?? 1);
        $unit = (string) ($item['unit'] ?? 'lot');
        $itemRate = (float) ($item['rate'] ?? 0);
        $taxed = empty($item['taxed']) ? 0 : 1;
        $stockId = (int) ($item['stock_item_id'] ?? 0);
        if ($stockId < 1) {
            $stockId = 0;
        }
        db_exec(
            'INSERT INTO document_items (document_id, stock_item_id, item_name, description, qty, unit, rate, taxed) VALUES (?,?,?,?,?,?,?,?)',
            'iissdsdi',
            [$id, $stockId > 0 ? $stockId : 0, $name, $desc, $qty, $unit, $itemRate, $taxed]
        );
    }
}

function cap_receipt_allocation(float $alloc, ?int $relatedId, ?int $exceptReceiptId = null): float
{
    if ($alloc <= 0 || !$relatedId) {
        return max(0, $alloc);
    }
    $cid = current_company_id();
    $inv = db_one('SELECT * FROM documents WHERE id = ? AND company_id = ? AND kind = \'invoice\' AND status = \'issued\'', 'ii', [$relatedId, $cid]);
    if ($inv) {
        $inv['items'] = db_all('SELECT * FROM document_items WHERE document_id = ? ORDER BY id', 'i', [(int) $inv['id']]);
        $total = document_totals($inv)['total'];
        $remaining = max(0, round($total - invoice_paid((int) $inv['id'], $exceptReceiptId), 2));
        return min($alloc, $remaining);
    }
    $sale = db_one('SELECT * FROM documents WHERE id = ? AND company_id = ? AND kind = \'receipt\' AND status = \'issued\'', 'ii', [$relatedId, $cid]);
    if ($sale && (int) ($sale['related_id'] ?? 0) <= 0) {
        $sale['items'] = db_all('SELECT * FROM document_items WHERE document_id = ? ORDER BY id', 'i', [(int) $sale['id']]);
        $remaining = receipt_sale_due($sale);
        return min($alloc, $remaining);
    }
    return $alloc;
}

function update_document(int $id, array $data): void
{
    $doc = load_document($id);
    if (!$doc) {
        throw new RuntimeException('Document not found.');
    }
    if ($doc['status'] === 'void') {
        throw new RuntimeException('Voided documents cannot be edited.');
    }
    $party = (int) ($data['party_id'] ?? $doc['party_id'] ?? 0);
    if ($party < 1) {
        if (($doc['kind'] ?? '') !== 'expense') {
            throw new RuntimeException('Choose an existing client or type a new name.');
        }
        $party = null;
    }
    $date = (string) ($data['date'] ?? $doc['date']);
    $due = $data['due_date'] ?? $doc['due_date'];
    $due = $due === '' ? null : $due;
    $rate = (float) ($data['vat_rate'] ?? $doc['vat_rate']);
    $notes = $data['notes'] ?? $doc['notes'];
    $subject = $data['subject'] ?? $doc['subject'];
    $body = $data['body'] ?? $doc['body'];
    $related = isset($data['related_id']) && $data['related_id'] ? (int) $data['related_id'] : null;
    $method = $data['payment_method'] ?? $doc['payment_method'];
    $ref = $data['payment_ref'] ?? $doc['payment_ref'];
    $alloc = isset($data['allocated_amount']) ? (float) $data['allocated_amount'] : (float) ($doc['allocated_amount'] ?? 0);
    $cat = $data['expense_category'] ?? $doc['expense_category'];
    if (array_key_exists('letter_template', $data)) {
        $tpl = $data['letter_template'];
        if ($tpl === 'none' || $tpl === '') {
            $tpl = null;
        }
    } else {
        $tpl = $doc['letter_template'];
    }
    $addSig = array_key_exists('add_signature', $data)
        ? (!empty($data['add_signature']) ? 1 : 0)
        : (int) ($doc['add_signature'] ?? 0);
    $branchId = array_key_exists('branch_id', $data)
        ? (function_exists('resolve_document_branch_id') ? resolve_document_branch_id($data['branch_id']) : null)
        : (isset($doc['branch_id']) && (int) $doc['branch_id'] > 0 ? (int) $doc['branch_id'] : null);
    $currency = normalize_currency((string) ($data['currency'] ?? doc_currency($doc)), doc_currency($doc));
    $docTpl = trim((string) ($data['doc_template'] ?? doc_template_key($doc)));
    if ($docTpl === '' || !array_key_exists($docTpl, doc_templates())) {
        $docTpl = doc_template_key($doc);
    }
    $items = $data['items'] ?? $doc['items'];
    $customValues = $data['custom_values'] ?? ($doc['custom_values'] ?? null);
    if (is_array($customValues)) {
        $customValues = json_encode($customValues, JSON_UNESCAPED_UNICODE) ?: '{}';
    } elseif ($customValues === null) {
        $customValues = is_array($doc['custom_values'] ?? null) ? json_encode($doc['custom_values']) : (string) ($doc['custom_values'] ?? '');
    }
    if (in_array($doc['kind'], ['letter', 'custom'], true)) {
        $items = $items ?: [['item_name' => '', 'description' => '-', 'qty' => 1, 'unit' => 'lot', 'rate' => 0, 'taxed' => 0]];
        $rate = 0;
    }
    if ($doc['kind'] === 'receipt') {
        if (!$alloc || $alloc <= 0) {
            $alloc = doc_subtotal($items);
        }
        $alloc = cap_receipt_allocation($alloc, $related, $id);
    } else {
        $alloc = $doc['kind'] === 'expense' ? $alloc : null;
        if ($doc['kind'] !== 'expense') {
            $alloc = null;
        }
    }

    db_exec(
        'UPDATE documents SET party_id=?, date=?, due_date=?, vat_rate=?, notes=?, subject=?, body=?, custom_values=?, related_id=?, payment_method=?, payment_ref=?, allocated_amount=?, expense_category=?, letter_template=?, currency=?, doc_template=?, add_signature=?, branch_id=? WHERE id=? AND company_id=?',
        'issdssssissdssssiiii',
        [$party, $date, $due, $rate, $notes, $subject, $body, $customValues, $related, $method, $ref, $alloc, $cat, $tpl, $currency, $docTpl, $addSig, $branchId, $id, current_company_id()]
    );
    if (function_exists('persist_document_party_extras') && array_key_exists('party_extras', $data)) {
        persist_document_party_extras($id, is_array($data['party_extras']) ? $data['party_extras'] : []);
    }
    db_exec('DELETE FROM document_items WHERE document_id = ?', 'i', [$id]);
    if (function_exists('stock_reverse_document')) {
        stock_reverse_document($id);
    }
    insert_document_items($id, $items);
    if (function_exists('stock_apply_document')) {
        stock_apply_document($id, (string) $doc['kind'], $items);
    }
}

function hydrate_document(array $doc): array
{
    $id = (int) $doc['id'];
    $doc['items'] = db_all('SELECT * FROM document_items WHERE document_id = ? ORDER BY id', 'i', [$id]);
    $rawCustom = $doc['custom_values'] ?? '';
    $doc['custom_values'] = is_array($rawCustom) ? $rawCustom : (json_decode((string) $rawCustom, true) ?: []);
    $rawExtras = $doc['party_extras'] ?? '';
    $doc['party_extras'] = is_array($rawExtras) ? $rawExtras : (json_decode((string) $rawExtras, true) ?: []);
    if (!$doc['party_extras'] && !empty($doc['party_profile'])) {
        $doc['party_extras'] = parse_party_profile($doc['party_profile']);
    }
    $doc['party_name'] = (string) ($doc['party_name'] ?? '');
    $doc['party_id'] = (int) ($doc['party_id'] ?? 0);
    $doc['totals'] = document_totals($doc);
    if ($doc['kind'] === 'invoice') {
        $doc['paid'] = invoice_paid((int) $doc['id']);
        $doc['balance'] = max(0, $doc['totals']['total'] - $doc['paid']);
    } elseif ($doc['kind'] === 'expense') {
        $doc['paid'] = expense_paid((int) $doc['id']);
        $doc['balance'] = max(0, $doc['totals']['total'] - $doc['paid']);
    } elseif ($doc['kind'] === 'receipt') {
        $doc['settlement'] = receipt_settlement($doc);
        $doc['paid'] = (float) ($doc['settlement']['received'] ?? $doc['totals']['total']);
        $doc['balance'] = (float) ($doc['settlement']['balance'] ?? 0);
        $doc['invoice_balance'] = (float) ($doc['settlement']['invoice_balance'] ?? 0);
    } else {
        $doc['paid'] = 0;
        $doc['balance'] = $doc['totals']['total'];
    }
    return $doc;
}

function load_document(int $id): ?array
{
    $doc = db_one(
        'SELECT d.*, p.name AS party_name, p.email AS party_email, p.phone AS party_phone, p.phone2 AS party_phone2, p.address AS party_address, p.city AS party_city, p.country AS party_country, p.contact_person AS party_contact, p.tin AS party_tin, p.kind AS party_kind, p.profile AS party_profile, p.entity AS party_entity
         FROM documents d LEFT JOIN parties p ON p.id = d.party_id WHERE d.id = ? AND d.company_id = ?',
        'ii',
        [$id, current_company_id()]
    );
    return $doc ? hydrate_document($doc) : null;
}

function document_share_secret(): string
{
    static $secret = null;
    if ($secret === null) {
        $cfg = require ROOT_PATH . '/config/database.php';
        $secret = hash('sha256', ($cfg['name'] ?? 'folio') . '|' . ($cfg['user'] ?? 'root') . '|vellisys-doc-share');
    }
    return $secret;
}

function document_share_token(array $doc): string
{
    return hash_hmac('sha256', (int) ($doc['id'] ?? 0) . ':' . (int) ($doc['company_id'] ?? 0) . ':' . (string) ($doc['number'] ?? ''), document_share_secret());
}

function document_share_url(array $doc): string
{
    return absolute_url('share.php?id=' . (int) $doc['id'] . '&t=' . document_share_token($doc));
}

function document_sheet_chrome(): string
{
    foreach (['/usr/bin/google-chrome-stable', '/usr/bin/google-chrome', '/usr/local/bin/google-chrome'] as $bin) {
        if (is_executable($bin)) {
            return $bin;
        }
    }
    return '';
}

function document_download_filename(array $doc): string
{
    $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($doc['number'] ?? 'document')) ?: 'document';
    return $base . '.pdf';
}

function document_local_file_uri(string $abs): string
{
    $abs = str_replace('\\', '/', $abs);
    $parts = explode('/', $abs);
    $enc = [];
    foreach ($parts as $part) {
        $enc[] = $part === '' ? '' : rawurlencode($part);
    }
    return 'file://' . implode('/', $enc);
}

function document_rewrite_css_urls(string $css, string $fromDir): string
{
    return (string) preg_replace_callback('/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i', static function (array $m) use ($fromDir): string {
        $url = trim($m[2]);
        if ($url === '' || str_starts_with($url, 'data:') || preg_match('#^(https?:|file:|//)#i', $url)) {
            return $m[0];
        }
        $path = strtok($url, '?#') ?: $url;
        $full = realpath($fromDir . '/' . $path);
        if ($full === false || !is_file($full)) {
            return $m[0];
        }
        return 'url("' . document_local_file_uri($full) . '")';
    }, $css) ?: $css;
}

function document_rewrite_html_local_urls(string $html): string
{
    return (string) preg_replace_callback('/\b(src|href)=([\'"])([^\'"]+)\2/i', static function (array $m): string {
        $url = $m[3];
        if ($url === '' || str_starts_with($url, 'data:') || str_starts_with($url, '#') || preg_match('#^(https?:|file:|mailto:|//)#i', $url)) {
            return $m[0];
        }
        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($path === '' || $path === false) {
            return $m[0];
        }
        $full = ROOT_PATH . '/' . ltrim($path, '/');
        if (!is_file($full)) {
            return $m[0];
        }
        return $m[1] . '=' . $m[2] . document_local_file_uri($full) . $m[2];
    }, $html) ?: $html;
}

function document_sheet_print_html(array $doc): string
{
    require_once ROOT_PATH . '/includes/sheet.php';
    $cid = (int) ($doc['company_id'] ?? current_company_id());
    $prev = $GLOBALS['folio_company_override'] ?? null;
    if ($cid > 0) {
        $GLOBALS['folio_company_override'] = $cid;
    }
    $brand = branding_for($cid > 0 ? $cid : current_company_id());
    $thermal = doc_template_key($doc) === 'thermal'
        && ($doc['kind'] ?? '') !== 'custom'
        && ($doc['kind'] ?? '') !== 'expense';
    $appCss = document_rewrite_css_urls((string) file_get_contents(ROOT_PATH . '/assets/css/app.css'), ROOT_PATH . '/assets/css');
    $designCss = document_rewrite_css_urls((string) file_get_contents(ROOT_PATH . '/assets/css/designs.css'), ROOT_PATH . '/assets/css');
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title></title>
  <meta name="format-detection" content="telephone=no,email=no,address=no,date=no">
  <style><?= $appCss ?></style>
  <style><?= $designCss ?></style>
  <style>
    :root { <?= brand_css_vars($brand) ?> }
    @page { size: <?= $thermal ? '80mm auto' : 'A4' ?>; margin: 0; }
    html, body.print-body { background: #fff !important; margin: 0; padding: 0; }
    a[href]::after, a[href]::before { content: none !important; }
    a { color: inherit !important; text-decoration: none !important; }
    .sheet-wrap, .sheet-stage { padding: 0 !important; margin: 0 !important; }
    .invoice-sheet { transform: none !important; zoom: 1 !important; box-shadow: none !important; }
  </style>
</head>
<body class="print-body<?= $thermal ? ' print-thermal' : '' ?>">
  <div class="sheet-wrap">
    <div class="sheet-stage">
      <?php render_sheet($brand, $doc); ?>
    </div>
  </div>
</body>
</html>
    <?php
    $html = (string) ob_get_clean();
    if ($prev === null) {
        unset($GLOBALS['folio_company_override']);
    } else {
        $GLOBALS['folio_company_override'] = $prev;
    }
    return document_rewrite_html_local_urls($html);
}

function document_chrome_print_target(string $chrome, string $target): ?string
{
    $id = bin2hex(random_bytes(4));
    $dir = sys_get_temp_dir() . '/vellisys-chrome-' . $id;
    $pdf = sys_get_temp_dir() . '/vellisys-doc-' . $id . '.pdf';
    $err = $pdf . '.log';
    if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return null;
    }
    $cmd = 'timeout 40s ' . escapeshellcmd($chrome)
        . ' --headless=new --disable-gpu --no-sandbox --disable-dev-shm-usage'
        . ' --hide-scrollbars --no-first-run --no-default-browser-check'
        . ' --allow-file-access-from-files --disable-extensions --disable-popup-blocking'
        . ' --no-pdf-header-footer --print-to-pdf-no-header'
        . ' --run-all-compositor-stages-before-draw --virtual-time-budget=12000'
        . ' --user-data-dir=' . escapeshellarg($dir)
        . ' --print-to-pdf=' . escapeshellarg($pdf)
        . ' ' . escapeshellarg($target)
        . ' >' . escapeshellarg($err) . ' 2>&1';
    exec($cmd, $ignored, $code);
    $bytes = '';
    if (is_file($pdf) && filesize($pdf) > 800) {
        $bytes = (string) file_get_contents($pdf);
    }
    @unlink($pdf);
    @unlink($err);
    $it = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($dir);
    if ($code !== 0 || !str_starts_with($bytes, '%PDF')) {
        return null;
    }
    return $bytes;
}

function document_sheet_print_urls(array $doc): array
{
    $path = '/share.php?id=' . (int) ($doc['id'] ?? 0) . '&t=' . rawurlencode(document_share_token($doc)) . '&sheet=1';
    $urls = [];
    $port = (int) ($_SERVER['SERVER_PORT'] ?? 0);
    if ($port > 0) {
        $urls[] = 'http://127.0.0.1:' . $port . $path;
    }
    foreach ([43219, 43230, 43231, 43232, 43233] as $tryPort) {
        $urls[] = 'http://127.0.0.1:' . $tryPort . $path;
    }
    $urls[] = document_share_url($doc) . '&sheet=1';
    return array_values(array_unique($urls));
}

function document_url_looks_like_sheet(string $url): bool
{
    $ctx = stream_context_create([
        'http' => ['timeout' => 4, 'ignore_errors' => true],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    return is_string($body) && str_contains($body, 'invoice-sheet');
}

function document_sheet_pdf_bytes(array $doc): string
{
    $chrome = document_sheet_chrome();
    if ($chrome === '') {
        throw new RuntimeException('Chrome is required to download the current document.');
    }

    $html = document_sheet_print_html($doc);
    $htmlPath = sys_get_temp_dir() . '/vellisys-sheet-' . (int) ($doc['id'] ?? 0) . '-' . bin2hex(random_bytes(4)) . '.html';
    if (file_put_contents($htmlPath, $html) !== false) {
        $bytes = document_chrome_print_target($chrome, document_local_file_uri($htmlPath));
        @unlink($htmlPath);
        if (is_string($bytes) && $bytes !== '') {
            return $bytes;
        }
    } else {
        @unlink($htmlPath);
    }

    foreach (document_sheet_print_urls($doc) as $url) {
        if (!document_url_looks_like_sheet($url)) {
            continue;
        }
        $bytes = document_chrome_print_target($chrome, $url);
        if (is_string($bytes) && $bytes !== '') {
            return $bytes;
        }
    }

    throw new RuntimeException('Could not print the current document. Use Print instead.');
}

function send_document_pdf(array $doc, string $disposition = 'attachment'): void
{
    $bytes = document_sheet_pdf_bytes($doc);
    $name = document_download_filename($doc);
    $mode = $disposition === 'inline' ? 'inline' : 'attachment';
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . $mode . '; filename="' . $name . '"');
    header('Content-Length: ' . (string) strlen($bytes));
    header('Cache-Control: private, no-store');
    echo $bytes;
    exit;
}

function send_document_download(array $doc): void
{
    try {
        send_document_pdf($doc, 'attachment');
    } catch (Throwable $e) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Download</title></head><body style="font-family:Montserrat,sans-serif;padding:48px;text-align:center">';
        echo '<p>' . h($e->getMessage()) . '</p>';
        echo '</body></html>';
        exit;
    }
}

function document_print_as_html(array $doc): bool
{
    $kind = (string) ($doc['kind'] ?? '');
    $thermal = doc_template_key($doc) === 'thermal'
        && $kind !== 'custom'
        && $kind !== 'expense';
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ios = str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') || str_contains($ua, 'iPod');
    $android = str_contains($ua, 'Android');
    $mobile = $ios || $android || str_contains($ua, 'Mobile');
    // Phones/tablets get the HTML slip so it can fit the screen; desktop can use PDF.
    return $thermal || $mobile;
}

function send_document_print_pdf(array $doc): bool
{
    if (document_print_as_html($doc)) {
        return false;
    }
    try {
        send_document_pdf($doc, 'inline');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function document_share_message(array $doc): string
{
    $brand = branding();
    $meta = kind_meta($doc['kind']);
    $text = $meta['singular'] . ' ' . $doc['number'] . ' from ' . $brand['name'];
    $total = (float) ($doc['totals']['total'] ?? $doc['paid'] ?? 0);
    if (!in_array($doc['kind'] ?? '', ['letter', 'custom', 'delivery'], true) && $total > 0) {
        $text .= ' (' . money($total, doc_currency($doc)) . ')';
    }
    return $text . '. Open the sheet: ' . document_share_url($doc);
}

function document_whatsapp_url(array $doc): string
{
    return 'https://wa.me/?text=' . rawurlencode(document_share_message($doc));
}

function document_mailto_url(array $doc): string
{
    $to = (string) ($doc['party_email'] ?? '');
    $brand = branding();
    $meta = kind_meta($doc['kind']);
    $subject = $meta['singular'] . ' ' . $doc['number'] . ' from ' . $brand['name'];
    $body = 'Dear ' . ($doc['party_name'] ?? '') . ",\n\nPlease find " . strtolower($meta['singular']) . ' ' . $doc['number'] . ".\n\n" . document_share_url($doc) . "\n\nKind regards,\n" . $brand['name'];
    $href = 'mailto:' . rawurlencode($to);
    $href .= '?subject=' . rawurlencode($subject) . '&body=' . rawurlencode($body);
    return $href;
}

function receipt_settlement(array $doc): ?array
{
    if (($doc['kind'] ?? '') !== 'receipt') {
        return null;
    }
    $received = receipt_received_amount($doc);
    $charge = (float) (($doc['totals']['total'] ?? document_totals($doc)['total'] ?? 0));
    if ($received <= 0 || ($charge > 0 && $received > $charge + 0.009)) {
        $received = $charge;
    }
    $later = payments_on_document((int) $doc['id'], doc_currency($doc));
    $collected = round($received + $later, 2);
    $due = max(0, round($charge - $collected, 2));
    $out = [
        'received' => $received,
        'invoice_id' => null,
        'invoice_number' => null,
        'invoice_total' => $charge,
        'invoice_paid' => $collected,
        'balance' => $due,
        'invoice_balance' => $due,
    ];
    $relatedId = (int) ($doc['related_id'] ?? 0);
    if ($relatedId <= 0) {
        return $out;
    }
    $cid = (int) ($doc['company_id'] ?? current_company_id());
    $rel = db_one('SELECT * FROM documents WHERE id = ? AND company_id = ?', 'ii', [$relatedId, $cid]);
    if (!$rel) {
        return $out;
    }
    $rel['items'] = db_all('SELECT * FROM document_items WHERE document_id = ? ORDER BY id', 'i', [(int) $rel['id']]);
    if ($rel['kind'] === 'receipt') {
        $remain = receipt_sale_due($rel);
        $out['invoice_id'] = (int) $rel['id'];
        $out['invoice_number'] = $rel['number'];
        $out['invoice_total'] = (float) (document_totals($rel)['total'] ?? 0);
        $out['invoice_paid'] = max(0, round($out['invoice_total'] - $remain, 2));
        $out['invoice_balance'] = convert_money($remain, doc_currency($rel), doc_currency($doc));
        $out['balance'] = 0.0;
        return $out;
    }
    if (!in_array($rel['kind'], ['invoice', 'expense'], true)) {
        return $out;
    }
    $total = document_totals($rel)['total'];
    $paid = payments_on_document((int) $rel['id'], doc_currency($rel));
    $remain = max(0, round($total - $paid, 2));
    $out['invoice_id'] = (int) $rel['id'];
    $out['invoice_number'] = $rel['number'];
    $out['invoice_total'] = $total;
    $out['invoice_paid'] = $paid;
    $out['invoice_balance'] = convert_money($remain, doc_currency($rel), doc_currency($doc));
    $out['balance'] = 0.0;
    return $out;
}

function outstanding_invoices(?int $partyId = null, ?int $keepId = null): array
{
    $sql = 'SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id
            WHERE d.company_id = ? AND d.kind = \'invoice\' AND d.status = \'issued\'';
    $types = 'i';
    $params = [current_company_id()];
    if ($partyId) {
        $sql .= ' AND d.party_id = ?';
        $types .= 'i';
        $params[] = $partyId;
    }
    $sql .= ' ORDER BY d.date DESC, d.id DESC';
    $rows = attach_document_totals(db_all($sql, $types, $params));
    return array_values(array_filter($rows, static function ($d) use ($keepId) {
        if ($keepId && (int) $d['id'] === $keepId) {
            return true;
        }
        return ((float) ($d['balance'] ?? 0)) > 0.009;
    }));
}

function related_receipts(int $relatedId, ?int $exceptReceiptId = null): array
{
    $sql = 'SELECT id, allocated_amount, currency FROM documents
            WHERE kind = \'receipt\' AND related_id = ? AND status = \'issued\' AND company_id = ?';
    $types = 'ii';
    $params = [$relatedId, current_company_id()];
    if ($exceptReceiptId) {
        $sql .= ' AND id <> ?';
        $types .= 'i';
        $params[] = $exceptReceiptId;
    }
    return db_all($sql, $types, $params);
}

function receipt_received_amount(array $doc): float
{
    $received = (float) ($doc['allocated_amount'] ?? 0);
    if ($received > 0) {
        return $received;
    }
    return (float) (($doc['totals']['total'] ?? document_totals($doc)['total'] ?? 0));
}

function payments_on_document(int $relatedId, string $toCurrency, ?int $exceptReceiptId = null): float
{
    $sum = 0.0;
    foreach (related_receipts($relatedId, $exceptReceiptId) as $row) {
        $amt = (float) ($row['allocated_amount'] ?? 0);
        if ($amt <= 0) {
            continue;
        }
        $sum += convert_money($amt, doc_currency($row), $toCurrency);
    }
    return round_money($sum, $toCurrency);
}

function invoice_paid(int $invoiceId, ?int $exceptReceiptId = null): float
{
    $inv = db_one('SELECT currency FROM documents WHERE id = ? AND company_id = ?', 'ii', [$invoiceId, current_company_id()]);
    return payments_on_document($invoiceId, $inv ? doc_currency($inv) : default_currency(), $exceptReceiptId);
}

function invoice_balance(array $doc): float
{
    $total = $doc['totals']['total'] ?? document_totals($doc)['total'];
    return max(0, round((float) $total - invoice_paid((int) $doc['id']), 2));
}

function receipt_is_sale(array $doc): bool
{
    return ($doc['kind'] ?? '') === 'receipt' && (int) ($doc['related_id'] ?? 0) <= 0;
}

function receipt_sale_due(array $doc): float
{
    if (($doc['kind'] ?? '') !== 'receipt') {
        return 0.0;
    }
    $charge = (float) (($doc['totals']['total'] ?? 0) ?: (document_totals($doc)['total'] ?? 0));
    $received = receipt_received_amount($doc);
    if ($received <= 0 || ($charge > 0 && $received > $charge + 0.009)) {
        $received = $charge;
    }
    $later = payments_on_document((int) $doc['id'], doc_currency($doc));
    return max(0, round($charge - $received - $later, 2));
}

function receipt_due_amount(array $doc): float
{
    if (($doc['kind'] ?? '') !== 'receipt') {
        return 0.0;
    }
    if (isset($doc['invoice_balance'])) {
        return max(0, (float) $doc['invoice_balance']);
    }
    if (receipt_is_sale($doc)) {
        return receipt_sale_due($doc);
    }
    return max(0, (float) ($doc['balance'] ?? 0));
}

function document_due_amount(array $doc): float
{
    $kind = (string) ($doc['kind'] ?? '');
    if ($kind === 'invoice' || $kind === 'expense') {
        return max(0, (float) ($doc['balance'] ?? 0));
    }
    if ($kind === 'receipt') {
        return receipt_due_amount($doc);
    }
    return 0.0;
}

function receipt_collect_target(array $doc): int
{
    if (($doc['status'] ?? '') === 'void') {
        return 0;
    }
    $kind = (string) ($doc['kind'] ?? '');
    if ($kind === 'invoice' && document_due_amount($doc) > 0.009) {
        return (int) $doc['id'];
    }
    if ($kind !== 'receipt') {
        return 0;
    }
    $rid = (int) ($doc['related_id'] ?? 0);
    if ($rid <= 0) {
        return receipt_sale_due($doc) > 0.009 ? (int) $doc['id'] : 0;
    }
    return receipt_due_amount($doc) > 0.009 ? $rid : 0;
}

function list_open_debtors(): array
{
    $invoices = array_values(array_filter(
        list_documents('invoice'),
        static fn ($d) => ($d['status'] ?? '') !== 'void' && document_due_amount($d) > 0.009
    ));
    $sales = array_values(array_filter(
        list_documents('receipt'),
        static fn ($d) => ($d['status'] ?? '') !== 'void' && receipt_is_sale($d) && document_due_amount($d) > 0.009
    ));
    $rows = array_merge($invoices, $sales);
    usort($rows, static function ($a, $b) {
        $da = (string) ($a['date'] ?? '');
        $db = (string) ($b['date'] ?? '');
        if ($da === $db) {
            return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
        }
        return $db <=> $da;
    });
    return $rows;
}

function expense_paid(int $expenseId): float
{
    $exp = db_one('SELECT currency FROM documents WHERE id = ? AND company_id = ?', 'ii', [$expenseId, current_company_id()]);
    return payments_on_document($expenseId, $exp ? doc_currency($exp) : default_currency());
}

function expense_balance(array $doc): float
{
    $total = $doc['totals']['total'] ?? document_totals($doc)['total'];
    return max(0, round((float) $total - expense_paid((int) $doc['id']), 2));
}

function pay_creditor(int $expenseId, float $amount, string $method, string $ref): int
{
    $doc = load_document($expenseId);
    if (!$doc || $doc['kind'] !== 'expense' || $doc['status'] === 'void') {
        throw new RuntimeException('This bill cannot take a payment.');
    }
    $balance = expense_balance($doc);
    $amount = min($amount, $balance);
    if ($amount <= 0) {
        throw new RuntimeException('Nothing remains on this bill.');
    }
    return create_document([
        'kind' => 'receipt',
        'party_id' => $doc['party_id'],
        'date' => today(),
        'vat_rate' => 0,
        'currency' => doc_currency($doc),
        'doc_template' => doc_template_key($doc),
        'notes' => 'Payment to supplier against ' . $doc['number'] . '.',
        'related_id' => $doc['id'],
        'payment_method' => $method,
        'payment_ref' => $ref ?: null,
        'allocated_amount' => $amount,
        'items' => [[
            'item_name' => 'Payment',
            'description' => 'Payment on ' . $doc['number'],
            'qty' => 1,
            'unit' => 'lot',
            'rate' => $amount,
            'taxed' => 0,
        ]],
    ]);
}

function void_document(int $id, string $reason): void
{
    $cid = current_company_id();
    $doc = db_one('SELECT number, kind, branch_id FROM documents WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
    db_exec(
        'UPDATE documents SET status = \'void\', void_reason = ? WHERE id = ? AND company_id = ?',
        'sii',
        [$reason, $id, $cid]
    );
    if (function_exists('stock_reverse_document')) {
        stock_reverse_document($id);
    }
    if ($doc && function_exists('record_company_activity')) {
        $meta = kind_meta((string) $doc['kind']);
        record_company_activity('document', $meta['singular'] . ' ' . $doc['number'] . ' voided', [
            'detail' => trim($reason),
            'href' => 'document_view.php?id=' . $id,
            'ref_type' => 'document',
            'ref_id' => $id,
            'branch_id' => $doc['branch_id'] ?? null,
        ]);
    }
}

function convert_quotation_to_invoice(int $quoteId): int
{
    $doc = load_document($quoteId);
    if (!$doc || $doc['kind'] !== 'quotation' || $doc['status'] === 'void') {
        throw new RuntimeException('This quotation cannot be converted.');
    }
    $items = [];
    foreach ($doc['items'] as $item) {
        $items[] = [
            'item_name' => $item['item_name'] ?? '',
            'description' => $item['description'],
            'qty' => $item['qty'],
            'unit' => $item['unit'],
            'rate' => $item['rate'],
            'taxed' => $item['taxed'],
            'stock_item_id' => (int) ($item['stock_item_id'] ?? 0),
        ];
    }
    return create_document([
        'kind' => 'invoice',
        'party_id' => $doc['party_id'],
        'date' => today(),
        'due_date' => date('Y-m-d', strtotime('+14 days')),
        'vat_rate' => (float) $doc['vat_rate'],
        'currency' => doc_currency($doc),
        'doc_template' => doc_template_key($doc),
        'notes' => $doc['notes'],
        'related_id' => $doc['id'],
        'items' => $items,
    ]);
}

function receive_on_invoice(int $invoiceId, float $amount, string $method, string $ref): int
{
    $doc = load_document($invoiceId);
    if (!$doc || $doc['kind'] !== 'invoice' || $doc['status'] === 'void') {
        throw new RuntimeException('This invoice cannot take a receipt.');
    }
    $balance = invoice_balance($doc);
    $amount = min($amount, $balance);
    if ($amount <= 0) {
        throw new RuntimeException('Nothing remains on this invoice.');
    }
    $part = ($balance - $amount) > 0.009;
    $label = ($part ? 'Part payment on ' : 'Payment on ') . $doc['number'];
    $notes = $part
        ? 'Part payment against ' . $doc['number'] . '. Balance remaining on the invoice.'
        : 'Received with thanks against ' . $doc['number'] . '.';
    return create_document([
        'kind' => 'receipt',
        'party_id' => $doc['party_id'],
        'date' => today(),
        'vat_rate' => 0,
        'currency' => doc_currency($doc),
        'doc_template' => doc_template_key($doc),
        'notes' => $notes,
        'related_id' => $doc['id'],
        'payment_method' => $method,
        'payment_ref' => $ref ?: null,
        'allocated_amount' => $amount,
        'items' => [[
            'item_name' => $part ? 'Part payment' : 'Payment',
            'description' => $label,
            'qty' => 1,
            'unit' => 'lot',
            'rate' => $amount,
            'taxed' => 0,
        ]],
    ]);
}

function receive_on_receipt(int $receiptId, float $amount, string $method, string $ref): int
{
    $doc = load_document($receiptId);
    if (!$doc || $doc['kind'] !== 'receipt' || $doc['status'] === 'void') {
        throw new RuntimeException('This receipt cannot take a payment.');
    }
    $related = (int) ($doc['related_id'] ?? 0);
    if ($related > 0) {
        $rel = load_document($related);
        if ($rel && $rel['kind'] === 'invoice') {
            return receive_on_invoice($related, $amount, $method, $ref);
        }
        if ($rel && $rel['kind'] === 'receipt') {
            return receive_on_receipt($related, $amount, $method, $ref);
        }
        throw new RuntimeException('This receipt is not a sale that can take a further payment.');
    }
    $balance = receipt_sale_due($doc);
    $amount = min($amount, $balance);
    if ($amount <= 0) {
        throw new RuntimeException('Nothing remains on this receipt.');
    }
    $part = ($balance - $amount) > 0.009;
    $label = ($part ? 'Part payment on ' : 'Payment on ') . $doc['number'];
    $notes = $part
        ? 'Part payment against ' . $doc['number'] . '. Balance remaining on Debtors.'
        : 'Received with thanks against ' . $doc['number'] . '.';
    return create_document([
        'kind' => 'receipt',
        'party_id' => $doc['party_id'],
        'date' => today(),
        'vat_rate' => 0,
        'currency' => doc_currency($doc),
        'doc_template' => doc_template_key($doc),
        'notes' => $notes,
        'related_id' => $doc['id'],
        'payment_method' => $method,
        'payment_ref' => $ref ?: null,
        'allocated_amount' => $amount,
        'items' => [[
            'item_name' => $part ? 'Part payment' : 'Payment',
            'description' => $label,
            'qty' => 1,
            'unit' => 'lot',
            'rate' => $amount,
            'taxed' => 0,
        ]],
    ]);
}

function attach_document_totals(array $rows): array
{
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
    $payDocs = db_all(
        "SELECT related_id, allocated_amount, currency FROM documents
         WHERE kind = 'receipt' AND status = 'issued' AND company_id = ? AND related_id IN ($placeholders)",
        'i' . $types,
        array_merge([current_company_id()], $ids)
    );
    $curById = [];
    foreach ($rows as $row) {
        $curById[(int) $row['id']] = doc_currency($row);
    }
    $paidBy = [];
    foreach ($payDocs as $p) {
        $rid = (int) $p['related_id'];
        $to = $curById[$rid] ?? default_currency();
        $paidBy[$rid] = ($paidBy[$rid] ?? 0) + convert_money((float) ($p['allocated_amount'] ?? 0), doc_currency($p), $to);
    }
    foreach ($paidBy as $rid => $amt) {
        $paidBy[$rid] = round_money($amt, $curById[$rid] ?? default_currency());
    }
    $relIds = [];
    foreach ($rows as $row) {
        if (($row['kind'] ?? '') === 'receipt' && !empty($row['related_id'])) {
            $relIds[] = (int) $row['related_id'];
        }
    }
    $relIds = array_values(array_unique($relIds));
    $relTotals = [];
    $relPaid = [];
    $relCur = [];
    $relKindById = [];
    $relAlloc = [];
    if ($relIds) {
        $relPh = implode(',', array_fill(0, count($relIds), '?'));
        $relTypes = str_repeat('i', count($relIds));
        $relDocs = db_all(
            "SELECT id, vat_rate, currency, kind, allocated_amount FROM documents WHERE company_id = ? AND id IN ($relPh)",
            'i' . $relTypes,
            array_merge([current_company_id()], $relIds)
        );
        $relSums = db_all(
            "SELECT document_id,
                    COALESCE(SUM(ROUND(qty * rate, 2)), 0) AS net,
                    COALESCE(SUM(CASE WHEN taxed = 1 THEN ROUND(qty * rate, 2) ELSE 0 END), 0) AS taxed_net
             FROM document_items WHERE document_id IN ($relPh)
             GROUP BY document_id",
            $relTypes,
            $relIds
        );
        $relAgg = [];
        foreach ($relSums as $sum) {
            $relAgg[(int) $sum['document_id']] = $sum;
        }
        foreach ($relDocs as $inv) {
            $agg = $relAgg[(int) $inv['id']] ?? ['net' => 0, 'taxed_net' => 0];
            $net = round((float) $agg['net'], 2);
            $vat = round((float) $agg['taxed_net'] * (float) ($inv['vat_rate'] ?? 0), 2);
            $relTotals[(int) $inv['id']] = round($net + $vat, 2);
            $relCur[(int) $inv['id']] = doc_currency($inv);
            $relPaid[(int) $inv['id']] = payments_on_document((int) $inv['id'], doc_currency($inv));
            $relKindById[(int) $inv['id']] = (string) ($inv['kind'] ?? '');
            $relAlloc[(int) $inv['id']] = (float) ($inv['allocated_amount'] ?? 0);
        }
    }
    foreach ($rows as &$row) {
        $agg = $byDoc[(int) $row['id']] ?? ['net' => 0, 'taxed_net' => 0];
        $net = round((float) $agg['net'], 2);
        $vat = round((float) $agg['taxed_net'] * (float) ($row['vat_rate'] ?? 0), 2);
        $row['items'] = [];
        $row['totals'] = ['net' => $net, 'vat' => $vat, 'total' => round($net + $vat, 2)];
        if ($row['kind'] === 'invoice' || $row['kind'] === 'expense') {
            $row['paid'] = $paidBy[(int) $row['id']] ?? 0.0;
            $row['balance'] = max(0, round((float) $row['totals']['total'] - (float) $row['paid'], 2));
        } elseif ($row['kind'] === 'receipt') {
            $received = (float) ($row['allocated_amount'] ?? 0);
            $charge = (float) $row['totals']['total'];
            if ($received <= 0 || ($charge > 0 && $received > $charge + 0.009)) {
                $received = $charge;
            }
            $later = $paidBy[(int) $row['id']] ?? 0.0;
            $saleDue = max(0, round($charge - $received - $later, 2));
            $row['paid'] = $received;
            $row['balance'] = $saleDue;
            $rid = (int) ($row['related_id'] ?? 0);
            $row['invoice_balance'] = $rid > 0 ? 0.0 : $saleDue;
            if ($rid && isset($relTotals[$rid])) {
                $remain = max(0, round($relTotals[$rid] - ($relPaid[$rid] ?? 0), 2));
                $relKind = $relKindById[$rid] ?? '';
                if ($relKind === 'receipt') {
                    $parentCash = (float) ($relAlloc[$rid] ?? 0);
                    $parentCharge = (float) $relTotals[$rid];
                    if ($parentCash <= 0 || ($parentCharge > 0 && $parentCash > $parentCharge + 0.009)) {
                        $parentCash = $parentCharge;
                    }
                    $remain = max(0, round($parentCharge - $parentCash - ($relPaid[$rid] ?? 0), 2));
                }
                $row['invoice_balance'] = convert_money($remain, $relCur[$rid] ?? doc_currency($row), doc_currency($row));
                $row['balance'] = 0.0;
            }
        } else {
            $row['paid'] = 0;
            $row['balance'] = $row['totals']['total'];
        }
    }
    unset($row);
    return $rows;
}

function document_make_payment_href(array $doc): string
{
    $target = receipt_collect_target($doc);
    if ($target <= 0) {
        return '';
    }
    return url('document_action.php?receive=' . $target);
}

function render_make_payment_button(array $doc, bool $labeled = false): void
{
    $href = document_make_payment_href($doc);
    if ($href === '') {
        return;
    }
    unset($labeled);
    ?>
      <a class="btn sm" href="<?= h($href) ?>" title="Make payment" aria-label="Make payment"><?= icon('receipt', 15) ?> Make payment</a>
    <?php
}

function render_doc_actions(array $doc, bool $labeled = false): void
{
    $id = (int) $doc['id'];
    $void = ($doc['status'] ?? '') === 'void';
    $cls = $labeled ? 'btn ghost sm' : 'btn ghost sm icon-only';
    $pri = $labeled ? 'btn sm' : 'btn sm icon-only';
    $dang = $labeled ? 'btn danger sm' : 'btn danger sm icon-only';
    ?>
    <div class="actions doc-toolbar">
      <?php if ($labeled && $doc['kind'] !== 'letter'): ?>
        <a class="<?= $cls ?>" href="<?= h(url('export.php?type=document&id=' . $id)) ?>" title="CSV" aria-label="CSV"><?= icon('download', 15) ?> CSV</a>
      <?php endif; ?>
      <?php if ($doc['kind'] === 'letter'): ?>
        <a class="<?= $cls ?>" href="<?= h(url('letter_docx.php?id=' . $id)) ?>" title="Download Word" aria-label="Download Word"><?= icon('download', 15) ?><?php if ($labeled): ?> Word<?php endif; ?></a>
      <?php endif; ?>
      <?php if (!$labeled): ?>
        <a class="<?= $cls ?>" href="<?= h(url('document_view.php?id=' . $id)) ?>" title="View" aria-label="View"><?= icon('eye', 15) ?></a>
      <?php endif; ?>
      <?php if (!$void && user_can_edit_documents()): ?>
        <a class="<?= $cls ?>" href="<?= h(url('document_new.php?id=' . $id)) ?>" title="Edit" aria-label="Edit"><?= icon('pencil', 15) ?><?php if ($labeled): ?> Edit<?php endif; ?></a>
      <?php endif; ?>
      <a class="<?= $cls ?>" href="<?= h(url('document_view.php?id=' . $id . '&print=1')) ?>" title="Print" aria-label="Print"><?= icon('printer', 15) ?><?php if ($labeled): ?> Print<?php endif; ?></a>
      <?php if (!$void): ?>
        <details class="share-pop">
          <summary class="<?= $cls ?>" title="Share" aria-label="Share"><?= icon('share', 15) ?><?php if ($labeled): ?> Share<?php endif; ?></summary>
          <div class="share-pop-list">
            <a href="<?= h(document_whatsapp_url($doc)) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 15) ?>WhatsApp</a>
            <a href="<?= h(url('document_email.php?id=' . $id)) ?>"><?= icon('letter', 15) ?>Email</a>
          </div>
        </details>
        <?php if ($doc['kind'] === 'quotation'): ?>
          <form method="post" action="<?= h(url('document_action.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="action" value="convert">
            <button class="<?= $pri ?>" type="submit" title="Make invoice" aria-label="Make invoice"><?= icon('convert', 15) ?><?php if ($labeled): ?> Invoice<?php endif; ?></button>
          </form>
        <?php endif; ?>
        <?php if ($doc['kind'] === 'invoice' && ($doc['balance'] ?? 1) > 0): ?>
          <a class="<?= $pri ?>" href="<?= h(url('document_action.php?receive=' . $id)) ?>" title="Receipt" aria-label="Receipt"><?= icon('receipt', 15) ?><?php if ($labeled): ?> Receipt<?php endif; ?></a>
          <a class="<?= $cls ?>" href="<?= h(url('desk_mail.php?type=reminder&id=' . $id)) ?>" title="Remind" aria-label="Remind"><?= icon('send', 15) ?><?php if ($labeled): ?> Remind<?php endif; ?></a>
        <?php endif; ?>
        <?php if ($doc['kind'] === 'receipt'): ?>
          <?php render_make_payment_button($doc, $labeled); ?>
        <?php endif; ?>
        <?php if ($doc['kind'] === 'expense' && ($doc['balance'] ?? 1) > 0): ?>
          <a class="<?= $pri ?>" href="<?= h(url('document_action.php?pay=' . $id)) ?>" title="Pay" aria-label="Pay"><?= icon('bank', 15) ?><?php if ($labeled): ?> Pay<?php endif; ?></a>
          <a class="<?= $cls ?>" href="<?= h(url('desk_mail.php?type=creditor&id=' . $id)) ?>" title="Message supplier" aria-label="Message supplier"><?= icon('letter', 15) ?><?php if ($labeled): ?> Message<?php endif; ?></a>
        <?php endif; ?>
        <?php if (user_can_delete_documents()): ?>
        <form method="post" action="<?= h(url('document_action.php')) ?>" onsubmit="return confirm('Delete this document? It will be voided and kept in the books.');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="action" value="void">
            <input type="hidden" name="reason" value="Deleted from desk">
            <button class="<?= $dang ?>" type="submit" title="Delete" aria-label="Delete"><?= icon('trash', 15) ?><?php if ($labeled): ?> Delete<?php endif; ?></button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php
}

function invoice_status_label(array $doc): string
{
    if ($doc['status'] === 'void') {
        return 'Void';
    }
    if (($doc['kind'] ?? '') === 'receipt') {
        return receipt_due_amount($doc) > 0.009 ? 'Partially cleared' : 'Cleared';
    }
    if (!in_array($doc['kind'], ['invoice', 'expense'], true)) {
        return ucfirst($doc['status']);
    }
    $balance = $doc['balance'] ?? ($doc['kind'] === 'expense' ? expense_balance($doc) : invoice_balance($doc));
    if ($balance <= 0) {
        return 'Paid';
    }
    $paid = $doc['paid'] ?? ($doc['kind'] === 'expense' ? expense_paid((int) $doc['id']) : invoice_paid((int) $doc['id']));
    if ($paid > 0) {
        return 'Part paid';
    }
    if ($doc['kind'] === 'invoice' && !empty($doc['due_date']) && $doc['due_date'] < today()) {
        return 'Overdue';
    }
    return 'Issued';
}

/** Products and services sold on invoices and standalone sale receipts. */
function document_sold_lines(?string $from = null, ?string $to = null): array
{
    $cid = current_company_id();
    if ($cid < 1) {
        return [];
    }
    $extra = '';
    $types = 'i';
    $params = [$cid];
    if ($from && $to) {
        $extra = ' AND d.date >= ? AND d.date <= ?';
        $types .= 'ss';
        $params[] = $from;
        $params[] = $to;
    } elseif (function_exists('period_sql')) {
        [$pExtra, $pTypes, $pArgs] = period_sql('d.date');
        $extra = $pExtra;
        $types .= $pTypes;
        $params = array_merge($params, $pArgs);
    }
    $hasService = function_exists('db_has_column') && db_has_column(db(), 'stock_items', 'is_service');
    $svcSelect = $hasService ? 'COALESCE(s.is_service, 0)' : '0';
    $joinStock = $hasService
        ? 'LEFT JOIN stock_items s ON s.id = i.stock_item_id AND s.company_id = d.company_id AND i.stock_item_id > 0'
        : '';
    try {
        $sold = db_all(
            "SELECT i.item_name, i.description, i.qty, i.rate, i.stock_item_id, {$svcSelect} AS is_service,
                    d.currency, d.kind AS doc_kind, d.id AS doc_id
             FROM document_items i
             INNER JOIN documents d ON d.id = i.document_id
             {$joinStock}
             WHERE d.company_id = ? AND d.status = 'issued'
               AND (d.kind = 'invoice' OR (d.kind = 'receipt' AND COALESCE(d.related_id, 0) = 0))
               {$extra}",
            $types,
            $params
        );
    } catch (Throwable $e) {
        error_log('document_sold_lines: ' . $e->getMessage());
        return [];
    }
    $byName = [];
    if ($hasService) {
        foreach (db_all('SELECT id, name, is_service FROM stock_items WHERE company_id = ?', 'i', [$cid]) as $item) {
            $byName[mb_strtolower(trim((string) $item['name']))] = $item;
        }
    }
    $income = [];
    $base = default_currency();
    foreach ($sold as $row) {
        $name = trim((string) ($row['item_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($row['description'] ?? '')) ?: 'Sale';
        }
        $svcRow = (int) ($row['is_service'] ?? 0) === 1;
        $stockId = (int) ($row['stock_item_id'] ?? 0);
        if (!$svcRow && $stockId < 1 && isset($byName[mb_strtolower($name)])) {
            $hit = $byName[mb_strtolower($name)];
            $svcRow = (int) ($hit['is_service'] ?? 0) === 1;
            $stockId = (int) ($hit['id'] ?? 0);
        }
        $kind = $svcRow ? 'service' : ($stockId > 0 ? 'product' : 'other');
        if ($kind === 'other' && ($row['doc_kind'] ?? '') === 'receipt') {
            $kind = 'service';
        }
        $key = $kind . "\0" . mb_strtolower($name);
        if (!isset($income[$key])) {
            $income[$key] = ['name' => $name, 'kind' => $kind, 'qty' => 0.0, 'amount' => 0.0];
        }
        $income[$key]['qty'] += (float) ($row['qty'] ?? 0);
        $line = round((float) ($row['qty'] ?? 0) * (float) ($row['rate'] ?? 0), 2);
        $fromCur = function_exists('normalize_currency')
            ? normalize_currency((string) ($row['currency'] ?? ''), $base)
            : (string) ($row['currency'] ?? $base);
        $income[$key]['amount'] += function_exists('convert_money') ? convert_money($line, $fromCur, $base) : $line;
    }
    try {
        $bare = db_all(
            "SELECT d.id, d.allocated_amount, d.currency, d.subject, d.notes
             FROM documents d
             WHERE d.company_id = ? AND d.status = 'issued' AND d.kind = 'receipt'
               AND COALESCE(d.related_id, 0) = 0
               AND NOT EXISTS (SELECT 1 FROM document_items i WHERE i.document_id = d.id)
               {$extra}",
            $types,
            $params
        );
    } catch (Throwable $e) {
        error_log('document_sold_lines receipts: ' . $e->getMessage());
        $bare = [];
    }
    foreach ($bare as $row) {
        $amt = (float) ($row['allocated_amount'] ?? 0);
        if ($amt <= 0.009) {
            continue;
        }
        $name = trim((string) ($row['subject'] ?? ''));
        if ($name === '') {
            $name = 'Services';
        }
        $key = "service\0" . mb_strtolower($name);
        if (!isset($income[$key])) {
            $income[$key] = ['name' => $name, 'kind' => 'service', 'qty' => 0.0, 'amount' => 0.0];
        }
        $fromCur = function_exists('normalize_currency')
            ? normalize_currency((string) ($row['currency'] ?? ''), $base)
            : (string) ($row['currency'] ?? $base);
        $income[$key]['qty'] += 1;
        $income[$key]['amount'] += function_exists('convert_money') ? convert_money($amt, $fromCur, $base) : $amt;
    }
    $income = array_values($income);
    usort($income, static fn ($a, $b) => $b['amount'] <=> $a['amount']);
    foreach ($income as &$row) {
        $row['qty'] = round((float) $row['qty'], 2);
        $row['amount'] = round((float) $row['amount'], 2);
    }
    unset($row);
    return $income;
}

/**
 * Profit on money collected in a date range.
 * Products: collected share of selling price minus the same share of buying price.
 * Services (and receipts with no product lines): collected amount is profit — no sell-minus-buy.
 *
 * @return array{days: array<string, array<string, float>>, income: list<array>, collected: float, cogs: float, profit: float}
 */
function report_collection_margin(?string $from = null, ?string $to = null): array
{
    $empty = ['days' => [], 'income' => [], 'collected' => 0.0, 'cogs' => 0.0, 'profit' => 0.0];
    $cid = current_company_id();
    if ($cid < 1) {
        return $empty;
    }
    $extra = '';
    $types = 'i';
    $params = [$cid];
    if ($from && $to) {
        $extra = ' AND d.date >= ? AND d.date <= ?';
        $types .= 'ss';
        $params[] = $from;
        $params[] = $to;
    } elseif (function_exists('period_sql')) {
        [$pExtra, $pTypes, $pArgs] = period_sql('d.date');
        $extra = $pExtra;
        $types .= $pTypes;
        $params = array_merge($params, $pArgs);
    }
    $receipts = db_all(
        "SELECT d.id, d.date, d.currency, d.related_id, d.allocated_amount, d.subject, d.notes,
                r.kind AS related_kind
         FROM documents d
         LEFT JOIN documents r ON r.id = d.related_id
         WHERE d.company_id = ? AND d.kind = 'receipt' AND d.status = 'issued' {$extra}
         ORDER BY d.date, d.id",
        $types,
        $params
    );
    if (!$receipts) {
        return $empty;
    }
    $sheetIds = [];
    foreach ($receipts as $row) {
        $sheetIds[(int) $row['id']] = true;
        $rel = (int) ($row['related_id'] ?? 0);
        if ($rel > 0 && ($row['related_kind'] ?? '') !== 'expense') {
            $sheetIds[$rel] = true;
        }
    }
    $ids = array_keys($sheetIds);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $itemRows = db_all(
        'SELECT document_id, item_name, description, qty, rate, stock_item_id FROM document_items WHERE document_id IN (' . $ph . ') ORDER BY id',
        str_repeat('i', count($ids)),
        $ids
    );
    $itemsByDoc = [];
    foreach ($itemRows as $item) {
        $itemsByDoc[(int) $item['document_id']][] = $item;
    }
    $stock = [];
    $byName = [];
    $hasService = function_exists('db_has_column') && db_has_column(db(), 'stock_items', 'is_service');
    $stockSql = $hasService
        ? 'SELECT id, name, buy_price, sell_price, COALESCE(is_service, 0) AS is_service FROM stock_items WHERE company_id = ?'
        : 'SELECT id, name, buy_price, sell_price, 0 AS is_service FROM stock_items WHERE company_id = ?';
    try {
        foreach (db_all($stockSql, 'i', [$cid]) as $item) {
            $stock[(int) $item['id']] = $item;
            $byName[mb_strtolower(trim((string) $item['name']))] = $item;
        }
    } catch (Throwable $e) {
        $stock = [];
    }
    $base = default_currency();
    $days = [];
    $income = [];
    $collectedTotal = 0.0;
    $cogsTotal = 0.0;
    foreach ($receipts as $row) {
        if (($row['related_kind'] ?? '') === 'expense') {
            continue;
        }
        $day = (string) $row['date'];
        if (!isset($days[$day])) {
            $days[$day] = ['collected' => 0.0, 'cogs' => 0.0, 'profit' => 0.0];
        }
        $ccy = function_exists('doc_currency') ? doc_currency($row) : (string) ($row['currency'] ?? $base);
        $received = (float) ($row['allocated_amount'] ?? 0);
        if ($received <= 0 && function_exists('receipt_received_amount')) {
            $received = receipt_received_amount($row);
        }
        if ($received <= 0) {
            foreach ($itemsByDoc[(int) $row['id']] ?? [] as $own) {
                $received += round((float) ($own['qty'] ?? 0) * (float) ($own['rate'] ?? 0), 2);
            }
        }
        if ($received <= 0.009) {
            continue;
        }
        $received = function_exists('convert_money') ? convert_money($received, $ccy, $base) : $received;
        $sheetId = (int) $row['id'];
        $rel = (int) ($row['related_id'] ?? 0);
        if ($rel > 0 && !empty($itemsByDoc[$rel])) {
            $sheetId = $rel;
        }
        $lines = $itemsByDoc[$sheetId] ?? [];
        $sellTotal = 0.0;
        $parsed = [];
        foreach ($lines as $line) {
            $lineSell = round((float) ($line['qty'] ?? 0) * (float) ($line['rate'] ?? 0), 2);
            $lineSell = function_exists('convert_money') ? convert_money($lineSell, $ccy, $base) : $lineSell;
            $name = trim((string) ($line['item_name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($line['description'] ?? '')) ?: 'Sale';
            }
            $stockId = (int) ($line['stock_item_id'] ?? 0);
            $hit = $stockId > 0 ? ($stock[$stockId] ?? null) : ($byName[mb_strtolower($name)] ?? null);
            $product = $hit && (int) ($hit['is_service'] ?? 0) !== 1;
            $lineCogs = 0.0;
            if ($product) {
                $lineCogs = round((float) ($line['qty'] ?? 0) * (float) ($hit['buy_price'] ?? 0), 2);
                $lineCogs = function_exists('convert_money') ? convert_money($lineCogs, $ccy, $base) : $lineCogs;
            }
            $parsed[] = [
                'name' => $name,
                'kind' => $product ? 'product' : 'service',
                'qty' => (float) ($line['qty'] ?? 0),
                'sell' => $lineSell,
                'cogs' => $lineCogs,
            ];
            $sellTotal += $lineSell;
        }
        $ratio = 1.0;
        if ($sellTotal > 0.009) {
            $ratio = min(1.0, $received / $sellTotal);
        } elseif (!$parsed) {
            $parsed[] = [
                'name' => trim((string) ($row['subject'] ?? '')) ?: 'Services',
                'kind' => 'service',
                'qty' => 1.0,
                'sell' => $received,
                'cogs' => 0.0,
            ];
            $sellTotal = $received;
            $ratio = 1.0;
        }
        $cogs = 0.0;
        foreach ($parsed as $line) {
            $share = $sellTotal > 0.009 ? $line['sell'] * $ratio : $received;
            $lineCogs = $line['cogs'] * $ratio;
            $cogs += $lineCogs;
            $qty = $line['qty'] * $ratio;
            $key = $line['kind'] . "\0" . mb_strtolower($line['name']);
            if (!isset($income[$key])) {
                $income[$key] = ['name' => $line['name'], 'kind' => $line['kind'], 'qty' => 0.0, 'amount' => 0.0];
            }
            $income[$key]['qty'] += $qty;
            $income[$key]['amount'] += $share;
        }
        $days[$day]['collected'] += $received;
        $days[$day]['cogs'] += $cogs;
        $days[$day]['profit'] += ($received - $cogs);
        $collectedTotal += $received;
        $cogsTotal += $cogs;
    }
    $income = array_values($income);
    usort($income, static fn ($a, $b) => $b['amount'] <=> $a['amount']);
    foreach ($income as &$row) {
        $row['qty'] = round((float) $row['qty'], 2);
        $row['amount'] = round((float) $row['amount'], 2);
    }
    unset($row);
    foreach ($days as $day => $row) {
        $days[$day]['collected'] = round((float) $row['collected'], 2);
        $days[$day]['cogs'] = round((float) $row['cogs'], 2);
        $days[$day]['profit'] = round((float) $row['profit'], 2);
    }
    ksort($days);
    $collectedTotal = round($collectedTotal, 2);
    $cogsTotal = round($cogsTotal, 2);
    return [
        'days' => $days,
        'income' => $income,
        'collected' => $collectedTotal,
        'cogs' => $cogsTotal,
        'profit' => round($collectedTotal - $cogsTotal, 2),
    ];
}

/** Buying-price cost of products sold on invoices and standalone receipts. */
function document_sold_cogs(?string $from = null, ?string $to = null): float
{
    $cid = current_company_id();
    if ($cid < 1) {
        return 0.0;
    }
    $extra = '';
    $types = 'i';
    $params = [$cid];
    if ($from && $to) {
        $extra = ' AND d.date >= ? AND d.date <= ?';
        $types .= 'ss';
        $params[] = $from;
        $params[] = $to;
    } elseif (function_exists('period_sql')) {
        [$pExtra, $pTypes, $pArgs] = period_sql('d.date');
        $extra = $pExtra;
        $types .= $pTypes;
        $params = array_merge($params, $pArgs);
    }
    $hasService = function_exists('db_has_column') && db_has_column(db(), 'stock_items', 'is_service');
    $svcWhere = $hasService ? ' AND COALESCE(s.is_service, 0) = 0' : '';
    try {
        $rows = db_all(
            "SELECT i.qty, d.currency, COALESCE(s.buy_price, 0) AS buy_price
             FROM document_items i
             INNER JOIN documents d ON d.id = i.document_id
             LEFT JOIN stock_items s ON s.id = i.stock_item_id AND s.company_id = d.company_id
             WHERE d.company_id = ? AND d.status = 'issued'
               AND (d.kind = 'invoice' OR (d.kind = 'receipt' AND COALESCE(d.related_id, 0) = 0))
               AND i.stock_item_id IS NOT NULL AND i.stock_item_id > 0
               {$svcWhere}
               {$extra}",
            $types,
            $params
        );
    } catch (Throwable $e) {
        error_log('document_sold_cogs: ' . $e->getMessage());
        return 0.0;
    }
    $cogs = 0.0;
    $base = default_currency();
    foreach ($rows as $row) {
        $line = round((float) ($row['qty'] ?? 0) * (float) ($row['buy_price'] ?? 0), 2);
        $fromCur = function_exists('normalize_currency')
            ? normalize_currency((string) ($row['currency'] ?? ''), $base)
            : (string) ($row['currency'] ?? $base);
        $cogs += function_exists('convert_money') ? convert_money($line, $fromCur, $base) : $line;
    }
    return round($cogs, 2);
}

/** Sold products/services, expenses, and net profit for the current report period. */
function report_performance_statement(?int $companyId = null): array
{
    $cid = $companyId ?? current_company_id();
    $empty = ['income' => [], 'expenses' => [], 'income_total' => 0.0, 'expense_total' => 0.0, 'cogs' => 0.0, 'profit' => 0.0, 'net' => 0.0];
    if ($cid < 1) {
        return $empty;
    }
    $p = function_exists('period_range') ? period_range() : ['from' => '', 'to' => ''];
    $from = $p['from'] !== '' ? $p['from'] : null;
    $to = $p['to'] !== '' ? $p['to'] : null;
    try {
        $margin = report_collection_margin($from, $to);
    } catch (Throwable $e) {
        error_log('report_performance_statement: ' . $e->getMessage());
        return $empty;
    }
    $income = $margin['income'];
    $incomeTotal = (float) $margin['collected'];
    $cogs = (float) $margin['cogs'];
    $profit = (float) $margin['profit'];

    [$extra, $types, $params] = period_sql('d.date');
    $base = default_currency();
    try {
        $spent = db_all(
            "SELECT i.item_name, i.description, i.qty, i.rate, d.expense_category, d.currency, d.id
             FROM documents d
             LEFT JOIN document_items i ON i.document_id = d.id
             WHERE d.company_id = ? AND d.status = 'issued' AND d.kind = 'expense' {$extra}",
            'i' . $types,
            array_merge([$cid], $params)
        );
    } catch (Throwable $e) {
        error_log('report_performance_statement expenses: ' . $e->getMessage());
        $spent = [];
    }
    $expenses = [];
    foreach ($spent as $row) {
        if (function_exists('stock_is_stock_expense') && stock_is_stock_expense($row)) {
            continue;
        }
        $name = trim((string) ($row['item_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($row['description'] ?? '')) ?: trim((string) ($row['expense_category'] ?? '')) ?: 'Expense';
        }
        $key = mb_strtolower($name);
        if (!isset($expenses[$key])) {
            $expenses[$key] = ['name' => $name, 'kind' => 'expense', 'qty' => 0.0, 'amount' => 0.0];
        }
        $qty = (float) ($row['qty'] ?? 0);
        $rate = (float) ($row['rate'] ?? 0);
        if ($qty == 0.0 && $rate == 0.0) {
            continue;
        }
        $expenses[$key]['qty'] += $qty > 0 ? $qty : 1;
        $line = round(($qty > 0 ? $qty : 1) * $rate, 2);
        $expenses[$key]['amount'] += convert_money($line, normalize_currency((string) ($row['currency'] ?? ''), $base), $base);
    }
    $expenses = array_values($expenses);
    usort($expenses, static fn ($a, $b) => $b['amount'] <=> $a['amount']);
    $expenseTotal = 0.0;
    foreach ($expenses as &$row) {
        $row['qty'] = round((float) $row['qty'], 2);
        $row['amount'] = round((float) $row['amount'], 2);
        $expenseTotal += $row['amount'];
    }
    unset($row);

    $profit = round($incomeTotal - $cogs, 2);
    return [
        'income' => $income,
        'expenses' => $expenses,
        'income_total' => round($incomeTotal, 2),
        'expense_total' => round($expenseTotal, 2),
        'cogs' => round($cogs, 2),
        'profit' => $profit,
        'net' => round($profit - $expenseTotal, 2),
    ];
}

/** Taxed lines in the current report period, with collecting receipt numbers. */
function report_tax_payable(?int $companyId = null): array
{
    $cid = $companyId ?? current_company_id();
    $base = default_currency();
    $empty = ['lines' => [], 'output' => 0.0, 'input' => 0.0, 'payable' => 0.0];
    if ($cid < 1) {
        return $empty;
    }
    [$extra, $types, $params] = period_sql('d.date');
    $raw = db_all(
        "SELECT d.id, d.kind, d.number, d.date, d.vat_rate, d.currency, d.related_id, d.party_id,
                p.name AS party_name,
                i.item_name, i.description, ROUND(i.qty * i.rate, 2) AS taxable
         FROM documents d
         LEFT JOIN parties p ON p.id = d.party_id
         INNER JOIN document_items i ON i.document_id = d.id
         WHERE d.company_id = ? AND d.status = 'issued' AND i.taxed = 1
           AND d.kind IN ('invoice','receipt','expense')
           AND d.vat_rate > 0
           {$extra}
         ORDER BY d.date DESC, d.id DESC, i.id ASC",
        'i' . $types,
        array_merge([$cid], $params)
    );
    $sheetIds = [];
    foreach ($raw as $row) {
        $kind = (string) ($row['kind'] ?? '');
        if ($kind === 'receipt' && (int) ($row['related_id'] ?? 0) > 0) {
            continue;
        }
        $sheetIds[(int) $row['id']] = true;
    }
    $receiptsBySheet = [];
    if ($sheetIds) {
        $ids = array_keys($sheetIds);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pays = db_all(
            "SELECT id, number, related_id FROM documents
             WHERE company_id = ? AND kind = 'receipt' AND status = 'issued' AND related_id IN ($ph)
             ORDER BY date, id",
            'i' . str_repeat('i', count($ids)),
            array_merge([$cid], $ids)
        );
        foreach ($pays as $pay) {
            $rid = (int) ($pay['related_id'] ?? 0);
            $receiptsBySheet[$rid][] = [
                'id' => (int) $pay['id'],
                'number' => (string) $pay['number'],
            ];
        }
    }
    $lines = [];
    $output = 0.0;
    $input = 0.0;
    foreach ($raw as $row) {
        $kind = (string) ($row['kind'] ?? '');
        $related = (int) ($row['related_id'] ?? 0);
        if ($kind === 'receipt' && $related > 0) {
            continue;
        }
        $rate = (float) ($row['vat_rate'] ?? 0);
        $taxable = (float) ($row['taxable'] ?? 0);
        $tax = round($taxable * $rate, 2);
        if ($tax <= 0 && $taxable <= 0) {
            continue;
        }
        $from = doc_currency($row);
        $taxableHome = convert_money($taxable, $from, $base);
        $taxHome = convert_money($tax, $from, $base);
        $name = trim((string) ($row['item_name'] ?? ''));
        $desc = trim((string) ($row['description'] ?? ''));
        $item = $name !== '' ? $name : ($desc !== '' ? $desc : 'Item');
        $sheetId = (int) $row['id'];
        $receipts = $receiptsBySheet[$sheetId] ?? [];
        if ($kind === 'receipt') {
            $receipts = [['id' => $sheetId, 'number' => (string) $row['number']]];
        }
        $side = $kind === 'expense' ? 'input' : 'output';
        if ($side === 'input') {
            $input += $taxHome;
        } else {
            $output += $taxHome;
        }
        $lines[] = [
            'id' => $sheetId,
            'kind' => $kind,
            'side' => $side,
            'number' => (string) $row['number'],
            'date' => (string) $row['date'],
            'party_id' => (int) ($row['party_id'] ?? 0),
            'party_name' => (string) ($row['party_name'] ?? ''),
            'item' => $item,
            'taxable' => $taxableHome,
            'tax' => $taxHome,
            'receipts' => $receipts,
            'rate' => $rate,
        ];
    }
    return [
        'lines' => $lines,
        'output' => round($output, 2),
        'input' => round($input, 2),
        'payable' => round($output - $input, 2),
    ];
}
