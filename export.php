<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$type = $_GET['type'] ?? 'documents';
$kind = $_GET['kind'] ?? 'invoice';

if ($type === 'clients') {
    $cid = current_company_id();
    $parties = db_all(
        "SELECT p.*,
            COALESCE(c.invoices,0) AS invoices,
            COALESCE(c.quotes,0) AS quotes,
            COALESCE(c.receipts,0) AS receipts
         FROM parties p
         LEFT JOIN (
           SELECT party_id,
             SUM(kind='invoice' AND status='issued') AS invoices,
             SUM(kind='quotation' AND status='issued') AS quotes,
             SUM(kind='receipt' AND status='issued') AS receipts
           FROM documents WHERE company_id = ? GROUP BY party_id
         ) c ON c.party_id = p.id
         WHERE p.company_id = ? ORDER BY p.name",
        'ii',
        [$cid, $cid]
    );
    $rows = [];
    foreach ($parties as $p) {
        $rows[] = [$p['name'], $p['kind'], $p['tin'], $p['email'], $p['phone'], $p['address'], $p['invoices'], $p['quotes'], $p['receipts']];
    }
    csv_download('clients.csv', ['Name', 'Kind', 'TIN', 'Email', 'Phone', 'Address', 'Invoices', 'Quotations', 'Receipts'], $rows);
}

if ($type === 'party') {
    $partyId = (int) ($_GET['id'] ?? 0);
    $party = db_one('SELECT * FROM parties WHERE id = ? AND company_id = ?', 'ii', [$partyId, current_company_id()]);
    if (!$party) {
        flash('Client not found.', 'err');
        redirect('clients.php');
    }
    [$extra, $types, $params] = period_sql('d.date');
    $docs = attach_document_totals(db_all(
        'SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.company_id = ? AND d.party_id = ?' . $extra . ' ORDER BY d.date DESC, d.id DESC',
        'ii' . $types,
        array_merge([current_company_id(), $partyId], $params)
    ));
    $rows = [];
    foreach ($docs as $d) {
        $rows[] = [
            $d['kind'],
            $d['number'],
            $d['date'],
            $d['totals']['net'],
            $d['totals']['vat'],
            $d['totals']['total'],
            $d['balance'] ?? 0,
            invoice_status_label($d),
            doc_currency($d),
        ];
    }
    $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $party['name']) ?: 'client';
    csv_download($safe . '.csv', ['Kind', 'Number', 'Date', 'Net', 'VAT', 'Total', 'Balance', 'Status', 'Currency'], $rows);
}

if ($type === 'document') {
    $doc = load_document((int) ($_GET['id'] ?? 0));
    if (!$doc) {
        flash('Document not found.', 'err');
        redirect('dashboard.php');
    }
    $cur = doc_currency($doc);
    $rows = [];
    foreach ($doc['items'] as $item) {
        $rows[] = [
            $doc['number'],
            $doc['date'],
            $doc['party_name'],
            $item['description'],
            format_qty($item['qty']),
            $item['unit'],
            $item['rate'],
            line_amount($item),
            !empty($item['taxed']) ? 'Y' : 'N',
            $cur,
        ];
    }
    csv_download(
        $doc['number'] . '.csv',
        ['Number', 'Date', 'Party', 'Item / description', 'Qty', 'Unit', 'Unit price', 'Full price', 'VAT line', 'Currency'],
        $rows
    );
}

if ($type === 'debtors') {
    $docs = array_values(array_filter(list_documents('invoice'), static fn ($d) => $d['status'] !== 'void' && ($d['balance'] ?? 0) > 0));
    $rows = [];
    foreach ($docs as $d) {
        $rows[] = [$d['number'], $d['party_name'], $d['date'], $d['due_date'], $d['totals']['total'], $d['paid'], $d['balance'], invoice_status_label($d), doc_currency($d)];
    }
    csv_download('debtors.csv', ['Invoice', 'Client', 'Date', 'Due', 'Amount', 'Paid', 'Balance', 'Status', 'Currency'], $rows);
}

if ($type === 'creditors') {
    $docs = array_values(array_filter(list_documents('expense'), static fn ($d) => $d['status'] !== 'void' && ($d['balance'] ?? 0) > 0));
    $rows = [];
    foreach ($docs as $d) {
        $rows[] = [$d['number'], $d['party_name'], $d['date'], $d['expense_category'], $d['totals']['total'], $d['paid'], $d['balance'], invoice_status_label($d), doc_currency($d)];
    }
    csv_download('creditors.csv', ['Bill', 'Supplier', 'Date', 'Category', 'Amount', 'Paid', 'Balance', 'Status', 'Currency'], $rows);
}

if ($type === 'reports') {
    $invoices = list_documents('invoice');
    $rows = [];
    foreach ($invoices as $d) {
        $rows[] = ['Invoice', $d['number'], $d['party_name'], $d['date'], $d['totals']['net'], $d['totals']['vat'], $d['totals']['total'], $d['balance'], invoice_status_label($d), doc_currency($d)];
    }
    foreach (list_documents('expense') as $d) {
        $rows[] = ['Expense', $d['number'], $d['party_name'], $d['date'], $d['totals']['net'], $d['totals']['vat'], $d['totals']['total'], $d['balance'], invoice_status_label($d), doc_currency($d)];
    }
    foreach (list_documents('receipt') as $d) {
        $rows[] = ['Receipt', $d['number'], $d['party_name'], $d['date'], $d['totals']['net'], $d['totals']['vat'], $d['allocated_amount'] ?: $d['totals']['total'], 0, invoice_status_label($d), doc_currency($d)];
    }
    csv_download('reports.csv', ['Kind', 'Number', 'Party', 'Date', 'Net', 'VAT', 'Total', 'Balance', 'Status', 'Currency'], $rows);
}

if (!in_array($kind, ['invoice', 'quotation', 'receipt', 'expense', 'letter'], true)) {
    $kind = 'invoice';
}
$docs = list_documents($kind);
$rows = [];
foreach ($docs as $d) {
    $rows[] = [
        $d['number'],
        $d['party_name'],
        $d['date'],
        $d['due_date'] ?? '',
        $d['expense_category'] ?? '',
        $d['subject'] ?? '',
        $d['totals']['net'],
        $d['totals']['vat'],
        $d['totals']['total'],
        $d['paid'] ?? 0,
        $d['balance'] ?? 0,
        invoice_status_label($d),
        doc_currency($d),
    ];
}
csv_download(
    $kind . 's.csv',
    ['Number', 'Party', 'Date', 'Due', 'Category', 'Subject', 'Net', 'VAT', 'Total', 'Paid', 'Balance', 'Status', 'Currency'],
    $rows
);
