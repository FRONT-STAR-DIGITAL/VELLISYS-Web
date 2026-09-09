<?php
declare(strict_types=1);
/** @var mysqli $db */

function seed_esc(mysqli $db, $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    return "'" . $db->real_escape_string((string) $value) . "'";
}

function seed_doc(mysqli $db, array $d, array $items): void
{
    $sql = 'INSERT INTO documents (kind, sequence, number, date, due_date, party_id, vat_rate, notes, subject, body, status, related_id, payment_method, payment_ref, allocated_amount, expense_category, created_by) VALUES ('
        . seed_esc($db, $d['kind']) . ','
        . (int) $d['sequence'] . ','
        . seed_esc($db, $d['number']) . ','
        . seed_esc($db, $d['date']) . ','
        . seed_esc($db, $d['due']) . ','
        . (int) $d['party'] . ','
        . (float) $d['vat'] . ','
        . seed_esc($db, $d['notes']) . ','
        . seed_esc($db, $d['subject']) . ','
        . seed_esc($db, $d['body']) . ','
        . seed_esc($db, $d['status']) . ','
        . seed_esc($db, $d['related']) . ','
        . seed_esc($db, $d['method']) . ','
        . seed_esc($db, $d['ref']) . ','
        . seed_esc($db, $d['alloc']) . ','
        . seed_esc($db, $d['cat']) . ','
        . (int) $d['user']
        . ')';
    if (!$db->query($sql)) {
        throw new RuntimeException('Seed document failed: ' . $db->error);
    }
    $id = (int) $db->insert_id;
    foreach ($items as $item) {
        $desc = $db->real_escape_string((string) $item[0]);
        $qty = (float) $item[1];
        $unit = $db->real_escape_string((string) $item[2]);
        $rate = (int) $item[3];
        $taxed = (int) $item[4];
        $db->query("INSERT INTO document_items (document_id, description, qty, unit, rate, taxed) VALUES ({$id}, '{$desc}', {$qty}, '{$unit}', {$rate}, {$taxed})");
    }
    if (in_array($d['kind'], ['invoice', 'receipt'], true)) {
        $total = 0;
        $vat = 0;
        foreach ($items as $item) {
            $line = (int) round($item[1] * $item[3]);
            $total += $line;
            if ($item[4] && $d['vat'] > 0) {
                $vat += (int) round($line * $d['vat']);
            }
        }
        $grand = $total + $vat;
        $stamp = '1000890123|' . $d['number'] . '|' . $d['date'] . '|' . $grand;
        $ver = strtoupper(substr(md5($stamp), 0, 8));
        $fdn = '256' . str_replace('-', '', $d['date']) . substr($ver, 0, 6);
        $payload = $db->real_escape_string(json_encode([
            'fdn' => $fdn,
            'tin' => '1000890123',
            'invoice' => $d['number'],
            'date' => $d['date'],
            'amount' => $grand,
            'verification' => $ver,
        ]));
        $db->query("UPDATE documents SET efris_fdn='{$fdn}', efris_verification='{$ver}', efris_payload='{$payload}' WHERE id={$id}");
    }
}

$userId = (int) $userId;
$demo = (int) $db->query("SELECT id FROM parties WHERE name='Demo Visitor'")->fetch_assoc()['id'];
$nile = (int) $db->query("SELECT id FROM parties WHERE name='Nile Coffee Traders Ltd'")->fetch_assoc()['id'];
$kituza = (int) $db->query("SELECT id FROM parties WHERE name='Kituza Estate Growers'")->fetch_assoc()['id'];
$pearl = (int) $db->query("SELECT id FROM parties WHERE name='Pearl Hotel Kampala'")->fetch_assoc()['id'];
$seedco = (int) $db->query("SELECT id FROM parties WHERE name='SeedCo Uganda Ltd'")->fetch_assoc()['id'];
$vivo = (int) $db->query("SELECT id FROM parties WHERE name='Vivo Energy Uganda'")->fetch_assoc()['id'];

$base = [
    'due' => null, 'notes' => null, 'subject' => null, 'body' => null, 'status' => 'issued',
    'related' => null, 'method' => null, 'ref' => null, 'alloc' => null, 'cat' => null, 'user' => $userId, 'vat' => 0.0,
];

seed_doc($db, array_merge($base, [
    'kind' => 'quotation', 'sequence' => 1, 'number' => 'OFG-QTN-2026-0001', 'date' => '2026-07-28',
    'due' => '2026-08-28', 'party' => $kituza, 'vat' => 0.18,
    'notes' => 'Prices held 30 days. Delivery to Mukono.',
]), [
    ['Shade-grown robusta seedlings, 6 months', 400, 'pcs', 4500, 1],
    ['Farm visit and planting supervision', 1, 'lot', 850000, 1],
]);

seed_doc($db, array_merge($base, [
    'kind' => 'invoice', 'sequence' => 1, 'number' => 'OFG-INV-20260815-61C38E', 'date' => '2026-08-15',
    'due' => '2026-08-22', 'party' => $demo, 'vat' => 0.0,
    'notes' => "1. Payment is due by the date shown above.\n2. Pay through the Ofagros client portal. Pesapal processes the payment.\n3. Farm work starts after this invoice is marked paid.",
]), [
    ['Coffee Estate Share', 1, 'lot', 12500000, 0],
]);

seed_doc($db, array_merge($base, [
    'kind' => 'invoice', 'sequence' => 2, 'number' => 'OFG-INV-2026-0002', 'date' => '2026-06-04',
    'due' => '2026-06-18', 'party' => $nile, 'vat' => 0.18, 'notes' => 'Net 14 days. LPO NCT/26/441.',
]), [
    ['Washed arabica, FAQ, 60kg bags', 80, 'bags', 980000, 1],
    ['Transport to Kampala warehouse', 1, 'trip', 1200000, 1],
]);

seed_doc($db, array_merge($base, [
    'kind' => 'invoice', 'sequence' => 3, 'number' => 'OFG-INV-2026-0003', 'date' => '2026-08-29',
    'due' => '2026-09-12', 'party' => $pearl, 'vat' => 0.18, 'notes' => 'Breakfast blend supply, September.',
]), [
    ['Breakfast blend, 1kg retail packs', 240, 'packs', 28000, 1],
]);

seed_doc($db, array_merge($base, [
    'kind' => 'invoice', 'sequence' => 4, 'number' => 'OFG-INV-2026-0004', 'date' => '2026-03-20',
    'due' => '2026-04-03', 'party' => $kituza, 'vat' => 0.18, 'notes' => 'Overdue. Followed up 12 May and 3 August.',
]), [
    ['Pruning and rejuvenation, 12 acres', 1, 'lot', 6800000, 1],
]);

$inv2 = (int) $db->query("SELECT id FROM documents WHERE number='OFG-INV-2026-0002'")->fetch_assoc()['id'];
seed_doc($db, array_merge($base, [
    'kind' => 'receipt', 'sequence' => 1, 'number' => 'OFG-RCT-2026-0001', 'date' => '2026-06-20',
    'party' => $nile, 'related' => $inv2, 'method' => 'bank-transfer', 'ref' => 'STN-4412901',
    'alloc' => 20000000, 'notes' => 'Part payment, received with thanks.',
]), [
    ['Payment on account', 1, 'lot', 20000000, 0],
]);

seed_doc($db, array_merge($base, [
    'kind' => 'expense', 'sequence' => 1, 'number' => 'OFG-EXP-2026-0001', 'date' => '2026-08-02',
    'party' => $seedco, 'vat' => 0.18, 'cat' => 'Farm inputs', 'notes' => 'Supplier invoice SC/26/1902',
]), [
    ['NPK 17:17:17, 50kg', 40, 'bags', 148000, 1],
]);

seed_doc($db, array_merge($base, [
    'kind' => 'expense', 'sequence' => 2, 'number' => 'OFG-EXP-2026-0002', 'date' => '2026-08-18',
    'party' => $vivo, 'cat' => 'Fuel', 'method' => 'mobile-money', 'notes' => 'Field team, Mukono run',
]), [
    ['Diesel', 1, 'lot', 640000, 0],
]);

seed_doc($db, array_merge($base, [
    'kind' => 'expense', 'sequence' => 3, 'number' => 'OFG-EXP-2026-0003', 'date' => '2026-07-01',
    'party' => $seedco, 'cat' => 'Rent',
]), [
    ['Store rent, Industrial Area, July', 1, 'month', 2400000, 0],
]);

seed_doc($db, array_merge($base, [
    'kind' => 'letter', 'sequence' => 1, 'number' => 'OFG-LTR-2026-0001', 'date' => '2026-09-01',
    'party' => $nile, 'subject' => 'Demand for the balance on OFG-INV-2026-0002',
    'body' => "Dear Accounts,\n\nWe write in respect of our invoice for washed arabica delivered in June. We acknowledge your transfer of UGX 20,000,000 and kindly request settlement of the remaining balance within seven days.\n\nYours faithfully,\nAccounts\nOfagros Limited",
]), [
    ['—', 1, 'lot', 0, 0],
]);
