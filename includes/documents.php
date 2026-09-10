<?php
declare(strict_types=1);

function today(): string
{
    return date('Y-m-d');
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
        default => 'DOC',
    };
}

function next_sequence(string $kind): int
{
    $row = db_one('SELECT COALESCE(MAX(sequence), 0) + 1 AS n FROM documents WHERE kind = ? AND company_id = ?', 'si', [$kind, current_company_id()]);
    return (int) ($row['n'] ?? 1);
}

function next_number(string $kind, int $sequence): string
{
    $prefix = branding()['prefix'] ?? 'OFG';
    return sprintf('%s-%s-%s-%04d', $prefix, kind_code($kind), date('Y'), $sequence);
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
    $party = (int) $data['party_id'];
    $rate = (float) ($data['vat_rate'] ?? 0.18);
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
    $currency = normalize_currency((string) ($data['currency'] ?? default_currency()), default_currency());
    $docTpl = trim((string) ($data['doc_template'] ?? ''));
    if ($docTpl === '' || !array_key_exists($docTpl, doc_templates())) {
        $docTpl = doc_template_key();
    }
    $userId = (int) ($data['created_by'] ?? ($_SESSION['user_id'] ?? 0));
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
        'INSERT INTO documents (company_id, kind, sequence, number, date, due_date, party_id, vat_rate, notes, subject, body, custom_values, status, related_id, payment_method, payment_ref, allocated_amount, expense_category, letter_template, created_by, currency, doc_template)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        'isisssidsssssissdssiss',
        [$cid, $kind, $seq, $number, $date, $due, $party, $rate, $notes, $subject, $body, $customValues, $status, $related, $method, $ref, $alloc, $cat, $tpl, $userId, $currency, $docTpl]
    );

    insert_document_items($id, $items);

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
        db_exec(
            'INSERT INTO document_items (document_id, item_name, description, qty, unit, rate, taxed) VALUES (?,?,?,?,?,?,?)',
            'issdsdi',
            [$id, $name, $desc, $qty, $unit, $itemRate, $taxed]
        );
    }
}

function cap_receipt_allocation(float $alloc, ?int $relatedId, ?int $exceptReceiptId = null): float
{
    if ($alloc <= 0 || !$relatedId) {
        return max(0, $alloc);
    }
    $inv = db_one('SELECT * FROM documents WHERE id = ? AND company_id = ? AND kind = \'invoice\' AND status = \'issued\'', 'ii', [$relatedId, current_company_id()]);
    if (!$inv) {
        return $alloc;
    }
    $inv['items'] = db_all('SELECT * FROM document_items WHERE document_id = ? ORDER BY id', 'i', [(int) $inv['id']]);
    $total = document_totals($inv)['total'];
    $remaining = max(0, round($total - invoice_paid((int) $inv['id'], $exceptReceiptId), 2));
    return min($alloc, $remaining);
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
    $party = (int) ($data['party_id'] ?? $doc['party_id']);
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
    $tpl = $data['letter_template'] ?? $doc['letter_template'];
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
        'UPDATE documents SET party_id=?, date=?, due_date=?, vat_rate=?, notes=?, subject=?, body=?, custom_values=?, related_id=?, payment_method=?, payment_ref=?, allocated_amount=?, expense_category=?, letter_template=?, currency=?, doc_template=? WHERE id=? AND company_id=?',
        'issdssssissdssssii',
        [$party, $date, $due, $rate, $notes, $subject, $body, $customValues, $related, $method, $ref, $alloc, $cat, $tpl, $currency, $docTpl, $id, current_company_id()]
    );
    db_exec('DELETE FROM document_items WHERE document_id = ?', 'i', [$id]);
    insert_document_items($id, $items);
}

function hydrate_document(array $doc): array
{
    $id = (int) $doc['id'];
    $doc['items'] = db_all('SELECT * FROM document_items WHERE document_id = ? ORDER BY id', 'i', [$id]);
    $rawCustom = $doc['custom_values'] ?? '';
    $doc['custom_values'] = is_array($rawCustom) ? $rawCustom : (json_decode((string) $rawCustom, true) ?: []);
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
        'SELECT d.*, p.name AS party_name, p.email AS party_email, p.phone AS party_phone, p.phone2 AS party_phone2, p.address AS party_address, p.city AS party_city, p.country AS party_country, p.contact_person AS party_contact, p.tin AS party_tin, p.kind AS party_kind
         FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.id = ? AND d.company_id = ?',
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
    $due = max(0, round($charge - $received, 2));
    $out = [
        'received' => $received,
        'invoice_id' => null,
        'invoice_number' => null,
        'invoice_total' => 0.0,
        'invoice_paid' => $received,
        'balance' => $due,
        'invoice_balance' => 0.0,
    ];
    $relatedId = (int) ($doc['related_id'] ?? 0);
    if ($relatedId <= 0) {
        return $out;
    }
    $cid = (int) ($doc['company_id'] ?? current_company_id());
    $rel = db_one('SELECT * FROM documents WHERE id = ? AND company_id = ?', 'ii', [$relatedId, $cid]);
    if (!$rel || !in_array($rel['kind'], ['invoice', 'expense'], true)) {
        return $out;
    }
    $rel['items'] = db_all('SELECT * FROM document_items WHERE document_id = ? ORDER BY id', 'i', [(int) $rel['id']]);
    $total = document_totals($rel)['total'];
    $paid = payments_on_document((int) $rel['id'], doc_currency($rel));
    $remain = max(0, round($total - $paid, 2));
    $out['invoice_id'] = (int) $rel['id'];
    $out['invoice_number'] = $rel['number'];
    $out['invoice_total'] = $total;
    $out['invoice_paid'] = $paid;
    $out['invoice_balance'] = convert_money($remain, doc_currency($rel), doc_currency($doc));
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
    db_exec(
        'UPDATE documents SET status = \'void\', void_reason = ? WHERE id = ? AND company_id = ?',
        'sii',
        [$reason, $id, current_company_id()]
    );
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
    if ($relIds) {
        $relPh = implode(',', array_fill(0, count($relIds), '?'));
        $relTypes = str_repeat('i', count($relIds));
        $relDocs = db_all(
            "SELECT id, vat_rate, currency, kind FROM documents WHERE company_id = ? AND id IN ($relPh)",
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
            $row['paid'] = $received;
            $row['balance'] = max(0, round($charge - $received, 2));
            $rid = (int) ($row['related_id'] ?? 0);
            $row['invoice_balance'] = 0.0;
            if ($rid && isset($relTotals[$rid])) {
                $remain = max(0, round($relTotals[$rid] - ($relPaid[$rid] ?? 0), 2));
                $row['invoice_balance'] = convert_money($remain, $relCur[$rid] ?? doc_currency($row), doc_currency($row));
            }
        } else {
            $row['paid'] = 0;
            $row['balance'] = $row['totals']['total'];
        }
    }
    unset($row);
    return $rows;
}

function render_doc_actions(array $doc, bool $labeled = false): void
{
    $id = (int) $doc['id'];
    $void = ($doc['status'] ?? '') === 'void';
    $cls = $labeled ? 'btn ghost sm' : 'btn ghost sm icon-only';
    $pri = $labeled ? 'btn sm' : 'btn sm icon-only';
    $dang = $labeled ? 'btn danger sm' : 'btn danger sm icon-only';
    ?>
    <div class="actions">
      <a class="<?= $cls ?>" href="<?= h(url('document_view.php?id=' . $id)) ?>" title="View" aria-label="View"><?= icon('eye', 15) ?><?php if ($labeled): ?> View<?php endif; ?></a>
      <?php if (!$void): ?>
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
        <?php if ($doc['kind'] === 'expense' && ($doc['balance'] ?? 1) > 0): ?>
          <a class="<?= $pri ?>" href="<?= h(url('document_action.php?pay=' . $id)) ?>" title="Pay" aria-label="Pay"><?= icon('bank', 15) ?><?php if ($labeled): ?> Pay<?php endif; ?></a>
          <a class="<?= $cls ?>" href="<?= h(url('desk_mail.php?type=creditor&id=' . $id)) ?>" title="Message supplier" aria-label="Message supplier"><?= icon('letter', 15) ?><?php if ($labeled): ?> Message<?php endif; ?></a>
        <?php endif; ?>
        <form method="post" action="<?= h(url('document_action.php')) ?>" onsubmit="return confirm('Void this document?');">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="action" value="void">
          <input type="hidden" name="reason" value="Voided from desk">
          <button class="<?= $dang ?>" type="submit" title="Void" aria-label="Void"><?= icon('ban', 15) ?><?php if ($labeled): ?> Void<?php endif; ?></button>
        </form>
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
        return ((float) ($doc['invoice_balance'] ?? $doc['balance'] ?? 0)) > 0.009 ? 'Partially cleared' : 'Cleared';
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
