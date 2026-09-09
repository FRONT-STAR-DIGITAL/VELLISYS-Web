<?php
declare(strict_types=1);

function today(): string
{
    return date('Y-m-d');
}

function line_amount(array $item): int
{
    return (int) round((float) ($item['qty'] ?? 0) * (int) ($item['rate'] ?? 0));
}

function doc_subtotal(array $items): int
{
    $sum = 0;
    foreach ($items as $item) {
        $sum += line_amount($item);
    }
    return $sum;
}

function doc_vat(array $items, float $rate): int
{
    if ($rate <= 0) {
        return 0;
    }
    $vat = 0;
    foreach ($items as $item) {
        if (!empty($item['taxed'])) {
            $vat += (int) round(line_amount($item) * $rate);
        }
    }
    return $vat;
}

function doc_total(array $items, float $rate): int
{
    return doc_subtotal($items) + doc_vat($items, $rate);
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
    $row = db_one('SELECT COALESCE(MAX(sequence), 0) + 1 AS n FROM documents WHERE kind = ?', 's', [$kind]);
    return (int) ($row['n'] ?? 1);
}

function next_number(string $kind, int $sequence): string
{
    $prefix = branding()['prefix'] ?? 'OFG';
    return sprintf('%s-%s-%s-%04d', $prefix, kind_code($kind), date('Y'), $sequence);
}

function apply_efris_mark(int $id, string $number, string $date, int $grand): void
{
    $tin = branding()['tin'] ?: '1000890123';
    $stamp = $tin . '|' . $number . '|' . $date . '|' . $grand;
    $ver = strtoupper(substr(md5($stamp), 0, 8));
    $fdn = '256' . str_replace('-', '', $date) . substr($ver, 0, 6);
    $payload = json_encode([
        'fdn' => $fdn,
        'tin' => $tin,
        'invoice' => $number,
        'date' => $date,
        'amount' => $grand,
        'verification' => $ver,
        'demo' => true,
    ]);
    db_exec(
        'UPDATE documents SET efris_fdn = ?, efris_verification = ?, efris_payload = ? WHERE id = ?',
        'sssi',
        [$fdn, $ver, $payload, $id]
    );
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
    $rate = (float) ($data['vat_rate'] ?? (branding()['plan'] === 'starter' ? 0 : 0.18));
    $notes = $data['notes'] ?? null;
    $subject = $data['subject'] ?? null;
    $body = $data['body'] ?? null;
    $status = (string) ($data['status'] ?? 'issued');
    $related = isset($data['related_id']) && $data['related_id'] ? (int) $data['related_id'] : null;
    $method = $data['payment_method'] ?? null;
    $ref = $data['payment_ref'] ?? null;
    $alloc = isset($data['allocated_amount']) ? (int) $data['allocated_amount'] : null;
    $cat = $data['expense_category'] ?? null;
    $userId = (int) ($data['created_by'] ?? ($_SESSION['user_id'] ?? 0));
    $items = $data['items'] ?? [];

    if ($kind === 'letter') {
        $items = $items ?: [['description' => '—', 'qty' => 1, 'unit' => 'lot', 'rate' => 0, 'taxed' => 0]];
        $rate = 0;
    }
    if ($kind === 'receipt' && (!$alloc || $alloc <= 0)) {
        $alloc = doc_subtotal($items);
    }

    $id = db_exec(
        'INSERT INTO documents (kind, sequence, number, date, due_date, party_id, vat_rate, notes, subject, body, status, related_id, payment_method, payment_ref, allocated_amount, expense_category, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        'sisssidssssissisi',
        [$kind, $seq, $number, $date, $due, $party, $rate, $notes, $subject, $body, $status, $related, $method, $ref, $alloc, $cat, $userId]
    );

    foreach ($items as $item) {
        $desc = trim((string) ($item['description'] ?? ''));
        if ($desc === '') {
            continue;
        }
        $qty = (float) ($item['qty'] ?? 1);
        $unit = (string) ($item['unit'] ?? 'lot');
        $itemRate = (int) ($item['rate'] ?? 0);
        $taxed = empty($item['taxed']) ? 0 : 1;
        db_exec(
            'INSERT INTO document_items (document_id, description, qty, unit, rate, taxed) VALUES (?,?,?,?,?,?)',
            'isdsii',
            [$id, $desc, $qty, $unit, $itemRate, $taxed]
        );
    }

    if (in_array($kind, ['invoice', 'receipt'], true) && $status === 'issued') {
        $loaded = load_document($id);
        if ($loaded) {
            $totals = document_totals($loaded);
            apply_efris_mark($id, $number, $date, $totals['total']);
        }
    }

    return $id;
}

function load_document(int $id): ?array
{
    $doc = db_one(
        'SELECT d.*, p.name AS party_name, p.email AS party_email, p.phone AS party_phone, p.address AS party_address, p.tin AS party_tin, p.kind AS party_kind
         FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.id = ?',
        'i',
        [$id]
    );
    if (!$doc) {
        return null;
    }
    $doc['items'] = db_all('SELECT * FROM document_items WHERE document_id = ? ORDER BY id', 'i', [$id]);
    $doc['totals'] = document_totals($doc);
    return $doc;
}

function invoice_paid(int $invoiceId): int
{
    $row = db_one(
        'SELECT COALESCE(SUM(COALESCE(allocated_amount, 0)), 0) AS paid
         FROM documents WHERE kind = \'receipt\' AND related_id = ? AND status = \'issued\'',
        'i',
        [$invoiceId]
    );
    return (int) ($row['paid'] ?? 0);
}

function invoice_balance(array $doc): int
{
    $total = $doc['totals']['total'] ?? document_totals($doc)['total'];
    if (($doc['kind'] ?? '') !== 'invoice') {
        return $total;
    }
    return max(0, $total - invoice_paid((int) $doc['id']));
}

function void_document(int $id, string $reason): void
{
    db_exec('UPDATE documents SET status = \'void\', void_reason = ? WHERE id = ?', 'si', [$reason, $id]);
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
        'notes' => $doc['notes'],
        'related_id' => $doc['id'],
        'items' => $items,
    ]);
}

function receive_on_invoice(int $invoiceId, int $amount, string $method, string $ref): int
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
    $items = db_all("SELECT * FROM document_items WHERE document_id IN ($placeholders)", $types, $ids);
    $byDoc = [];
    foreach ($items as $item) {
        $byDoc[(int) $item['document_id']][] = $item;
    }
    foreach ($rows as &$row) {
        $row['items'] = $byDoc[(int) $row['id']] ?? [];
        $row['totals'] = document_totals($row);
        if ($row['kind'] === 'invoice') {
            $row['paid'] = invoice_paid((int) $row['id']);
            $row['balance'] = max(0, $row['totals']['total'] - $row['paid']);
        } else {
            $row['paid'] = 0;
            $row['balance'] = $row['totals']['total'];
        }
    }
    unset($row);
    return $rows;
}

function render_doc_actions(array $doc): void
{
    $id = (int) $doc['id'];
    $void = ($doc['status'] ?? '') === 'void';
    ?>
    <div class="actions">
      <a class="btn ghost sm" href="<?= h(url('document_view.php?id=' . $id)) ?>">View</a>
      <a class="btn ghost sm" href="<?= h(url('document_view.php?id=' . $id . '&print=1')) ?>">Print / PDF</a>
      <?php if (!$void): ?>
        <a class="btn ghost sm" href="<?= h(url('document_email.php?id=' . $id)) ?>">Email</a>
        <?php if ($doc['kind'] === 'quotation'): ?>
          <form method="post" action="<?= h(url('document_action.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="action" value="convert">
            <button class="btn sm" type="submit">Make invoice</button>
          </form>
        <?php endif; ?>
        <?php if ($doc['kind'] === 'invoice' && ($doc['balance'] ?? 1) > 0): ?>
          <a class="btn sm" href="<?= h(url('document_action.php?receive=' . $id)) ?>">Receipt</a>
        <?php endif; ?>
        <form method="post" action="<?= h(url('document_action.php')) ?>" onsubmit="return confirm('Void this document?');">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="action" value="void">
          <input type="hidden" name="reason" value="Voided from desk">
          <button class="btn danger sm" type="submit">Void</button>
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
    if ($doc['kind'] !== 'invoice') {
        return ucfirst($doc['status']);
    }
    $balance = $doc['balance'] ?? invoice_balance($doc);
    if ($balance <= 0) {
        return 'Paid';
    }
    $paid = $doc['paid'] ?? invoice_paid((int) $doc['id']);
    if ($paid > 0) {
        return 'Part paid';
    }
    if (!empty($doc['due_date']) && $doc['due_date'] < today()) {
        return 'Overdue';
    }
    return 'Issued';
}
