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
    $currency = strtoupper((string) ($data['currency'] ?? default_currency()));
    if ($currency !== 'USD') {
        $currency = 'UGX';
    }
    $docTpl = trim((string) ($data['doc_template'] ?? ''));
    if ($docTpl === '' || !array_key_exists($docTpl, doc_templates())) {
        $docTpl = doc_template_key();
    }
    $userId = (int) ($data['created_by'] ?? ($_SESSION['user_id'] ?? 0));
    $items = $data['items'] ?? [];
    $cid = current_company_id();

    if ($kind === 'letter') {
        $items = $items ?: [['description' => '-', 'qty' => 1, 'unit' => 'lot', 'rate' => 0, 'taxed' => 0]];
        $rate = 0;
    }
    if ($kind === 'receipt' && (!$alloc || $alloc <= 0)) {
        $alloc = doc_subtotal($items);
    }

    $id = db_exec(
        'INSERT INTO documents (company_id, kind, sequence, number, date, due_date, party_id, vat_rate, notes, subject, body, status, related_id, payment_method, payment_ref, allocated_amount, expense_category, letter_template, created_by, currency, doc_template)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        'isisssidssssissdssiss',
        [$cid, $kind, $seq, $number, $date, $due, $party, $rate, $notes, $subject, $body, $status, $related, $method, $ref, $alloc, $cat, $tpl, $userId, $currency, $docTpl]
    );

    foreach ($items as $item) {
        $desc = trim((string) ($item['description'] ?? ''));
        if ($desc === '') {
            continue;
        }
        $qty = (float) ($item['qty'] ?? 1);
        $unit = (string) ($item['unit'] ?? 'lot');
        $itemRate = (float) ($item['rate'] ?? 0);
        $taxed = empty($item['taxed']) ? 0 : 1;
        db_exec(
            'INSERT INTO document_items (document_id, description, qty, unit, rate, taxed) VALUES (?,?,?,?,?,?)',
            'isdsdi',
            [$id, $desc, $qty, $unit, $itemRate, $taxed]
        );
    }

    return $id;
}

function load_document(int $id): ?array
{
    $doc = db_one(
        'SELECT d.*, p.name AS party_name, p.email AS party_email, p.phone AS party_phone, p.address AS party_address, p.tin AS party_tin, p.kind AS party_kind
         FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.id = ? AND d.company_id = ?',
        'ii',
        [$id, current_company_id()]
    );
    if (!$doc) {
        return null;
    }
    $doc['items'] = db_all('SELECT * FROM document_items WHERE document_id = ? ORDER BY id', 'i', [$id]);
    $doc['totals'] = document_totals($doc);
    if ($doc['kind'] === 'invoice') {
        $doc['paid'] = invoice_paid((int) $doc['id']);
        $doc['balance'] = max(0, $doc['totals']['total'] - $doc['paid']);
    } elseif ($doc['kind'] === 'expense') {
        $doc['paid'] = expense_paid((int) $doc['id']);
        $doc['balance'] = max(0, $doc['totals']['total'] - $doc['paid']);
    } else {
        $doc['paid'] = 0;
        $doc['balance'] = $doc['totals']['total'];
    }
    return $doc;
}

function invoice_paid(int $invoiceId): float
{
    $row = db_one(
        'SELECT COALESCE(SUM(COALESCE(allocated_amount, 0)), 0) AS paid
         FROM documents WHERE kind = \'receipt\' AND related_id = ? AND status = \'issued\' AND company_id = ?',
        'ii',
        [$invoiceId, current_company_id()]
    );
    return (float) ($row['paid'] ?? 0);
}

function invoice_balance(array $doc): float
{
    $total = $doc['totals']['total'] ?? document_totals($doc)['total'];
    return max(0, round((float) $total - invoice_paid((int) $doc['id']), 2));
}

function expense_paid(int $expenseId): float
{
    $row = db_one(
        'SELECT COALESCE(SUM(COALESCE(allocated_amount, 0)), 0) AS paid
         FROM documents WHERE kind = \'receipt\' AND related_id = ? AND status = \'issued\' AND company_id = ?',
        'ii',
        [$expenseId, current_company_id()]
    );
    return (float) ($row['paid'] ?? 0);
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
    return create_document([
        'kind' => 'receipt',
        'party_id' => $doc['party_id'],
        'date' => today(),
        'vat_rate' => 0,
        'currency' => doc_currency($doc),
        'doc_template' => doc_template_key($doc),
        'notes' => 'Received with thanks against ' . $doc['number'] . '.',
        'related_id' => $doc['id'],
        'payment_method' => $method,
        'payment_ref' => $ref ?: null,
        'allocated_amount' => $amount,
        'items' => [[
            'description' => 'Payment on ' . $doc['number'],
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
    $paidRows = db_all(
        "SELECT related_id, COALESCE(SUM(COALESCE(allocated_amount, 0)), 0) AS paid
         FROM documents WHERE kind = 'receipt' AND status = 'issued' AND company_id = ? AND related_id IN ($placeholders)
         GROUP BY related_id",
        'i' . $types,
        array_merge([current_company_id()], $ids)
    );
    $paidBy = [];
    foreach ($paidRows as $p) {
        $paidBy[(int) $p['related_id']] = (float) $p['paid'];
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
      <a class="<?= $cls ?>" href="<?= h(url('document_view.php?id=' . $id . '&print=1')) ?>" title="Print" aria-label="Print"><?= icon('printer', 15) ?><?php if ($labeled): ?> Print<?php endif; ?></a>
      <?php if (!$void): ?>
        <a class="<?= $cls ?>" href="<?= h(url('document_email.php?id=' . $id)) ?>" title="Email" aria-label="Email"><?= icon('send', 15) ?><?php if ($labeled): ?> Email<?php endif; ?></a>
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
          <a class="<?= $cls ?>" href="<?= h(url('document_new.php?kind=letter&party=' . (int) $doc['party_id'] . '&template=demand&related=' . $id)) ?>" title="Remind" aria-label="Remind"><?= icon('send', 15) ?><?php if ($labeled): ?> Remind<?php endif; ?></a>
        <?php endif; ?>
        <?php if ($doc['kind'] === 'expense' && ($doc['balance'] ?? 1) > 0): ?>
          <a class="<?= $pri ?>" href="<?= h(url('document_action.php?pay=' . $id)) ?>" title="Pay" aria-label="Pay"><?= icon('bank', 15) ?><?php if ($labeled): ?> Pay<?php endif; ?></a>
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
