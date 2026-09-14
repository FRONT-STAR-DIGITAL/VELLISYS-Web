<?php
declare(strict_types=1);

function company_stock_enabled(?array $company = null): bool
{
    $company = $company ?? current_company();
    return $company ? (int) ($company['stock_enabled'] ?? 0) === 1 : false;
}

function require_stock(): array
{
    $user = require_member();
    if (!company_stock_enabled()) {
        flash('Stock is not on for this desk. Ask Vellisys to switch it on.', 'err');
        redirect('dashboard.php');
    }
    return $user;
}

function stock_require_open_day(): void
{
    if (!stock_day_is_open()) {
        flash('Open the day first. Enter the cash you started with.', 'err');
        redirect('stock.php?tab=day');
    }
}

function stock_can_buy(): bool
{
    return user_access() !== 'sales';
}

function stock_tabs(): array
{
    return [
        'items' => ['Items', 'package'],
        'counts' => ['Counts', 'hash'],
        'purchases' => ['Purchases', 'expense'],
        'day' => ['Day', 'clock'],
    ];
}

function render_stock_subnav(string $active): void
{
    ?>
  <nav class="planner-tabs" aria-label="Stock sections">
    <?php foreach (stock_tabs() as $key => [$label, $iconName]): ?>
      <?php if ($key === 'purchases' && !stock_can_buy()) { continue; } ?>
      <a class="planner-tab<?= $active === $key ? ' is-on' : '' ?>" href="<?= h(url('stock.php?tab=' . $key)) ?>"><?= icon($iconName, 16) ?><span><?= h($label) ?></span></a>
    <?php endforeach; ?>
    <a class="planner-tab<?= $active === 'sale' ? ' is-on' : '' ?>" href="<?= h(url('sale.php')) ?>"><?= icon('cart', 16) ?><span>Sale</span></a>
  </nav>
    <?php
}

function stock_items(bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM stock_items WHERE company_id = ?';
    if ($activeOnly) {
        $sql .= ' AND active = 1';
    }
    $sql .= ' ORDER BY name';
    return db_all($sql, 'i', [current_company_id()]);
}

function stock_item(int $id): ?array
{
    return db_one('SELECT * FROM stock_items WHERE id = ? AND company_id = ?', 'ii', [$id, current_company_id()]);
}

function stock_search(string $q, int $limit = 12): array
{
    $q = trim($q);
    $cid = current_company_id();
    if ($q === '') {
        return db_all('SELECT * FROM stock_items WHERE company_id = ? AND active = 1 ORDER BY name LIMIT ?', 'ii', [$cid, $limit]);
    }
    $like = '%' . $q . '%';
    return db_all(
        'SELECT * FROM stock_items WHERE company_id = ? AND active = 1 AND (name LIKE ? OR sku LIKE ? OR description LIKE ?) ORDER BY name LIMIT ?',
        'isssi',
        [$cid, $like, $like, $like, $limit]
    );
}

function stock_stats(): array
{
    $cid = current_company_id();
    $row = db_one(
        'SELECT COUNT(*) AS n,
                COALESCE(SUM(qty_on_hand * buy_price), 0) AS cost,
                COALESCE(SUM(qty_on_hand * sell_price), 0) AS sell,
                COALESCE(SUM(CASE WHEN reorder_level > 0 AND qty_on_hand <= reorder_level THEN 1 ELSE 0 END), 0) AS low
         FROM stock_items WHERE company_id = ? AND active = 1',
        'i',
        [$cid]
    );
    return [
        'items' => (int) ($row['n'] ?? 0),
        'cost' => (float) ($row['cost'] ?? 0),
        'sell' => (float) ($row['sell'] ?? 0),
        'low' => (int) ($row['low'] ?? 0),
    ];
}

function stock_low_items(): array
{
    return db_all(
        'SELECT * FROM stock_items WHERE company_id = ? AND active = 1 AND reorder_level > 0 AND qty_on_hand <= reorder_level ORDER BY qty_on_hand, name',
        'i',
        [current_company_id()]
    );
}

function stock_save_item(array $fields, ?int $id = null): array
{
    $cid = current_company_id();
    $name = mb_substr(trim((string) ($fields['name'] ?? '')), 0, 190);
    if ($name === '') {
        return ['ok' => false, 'error' => 'Name the product.'];
    }
    $sku = mb_substr(trim((string) ($fields['sku'] ?? '')), 0, 80);
    $desc = mb_substr(trim((string) ($fields['description'] ?? '')), 0, 500);
    $unit = mb_substr(trim((string) ($fields['unit'] ?? 'pc')), 0, 40) ?: 'pc';
    $buy = round((float) ($fields['buy_price'] ?? 0), 2);
    $sell = round((float) ($fields['sell_price'] ?? 0), 2);
    $reorder = round((float) ($fields['reorder_level'] ?? 0), 2);
    $taxed = empty($fields['taxed']) ? 0 : 1;
    $active = isset($fields['active']) && (int) $fields['active'] === 0 ? 0 : 1;
    if ($sku !== '') {
        $dup = db_one('SELECT id FROM stock_items WHERE company_id = ? AND sku = ? AND id <> ?', 'isi', [$cid, $sku, (int) ($id ?? 0)]);
        if ($dup) {
            return ['ok' => false, 'error' => 'That code is already on another product.'];
        }
    }
    if ($id) {
        $row = stock_item($id);
        if (!$row) {
            return ['ok' => false, 'error' => 'That product is not on this desk.'];
        }
        db_exec(
            'UPDATE stock_items SET sku=?, name=?, description=?, unit=?, buy_price=?, sell_price=?, reorder_level=?, taxed=?, active=? WHERE id=? AND company_id=?',
            'ssssdddiiii',
            [$sku, $name, $desc, $unit, $buy, $sell, $reorder, $taxed, $active, $id, $cid]
        );
        return ['ok' => true, 'id' => $id];
    }
    $qty = round((float) ($fields['qty_on_hand'] ?? 0), 2);
    $newId = db_exec(
        'INSERT INTO stock_items (company_id, sku, name, description, unit, buy_price, sell_price, reorder_level, qty_on_hand, taxed, active) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        'issssddddii',
        [$cid, $sku, $name, $desc, $unit, $buy, $sell, $reorder, max(0, $qty), $taxed, $active]
    );
    if ($qty > 0) {
        stock_move((int) $newId, 'in', $qty, $buy, null, 'Opening quantity');
    }
    return ['ok' => true, 'id' => (int) $newId];
}

function stock_move(int $itemId, string $kind, float $qty, float $unitCost = 0, ?int $documentId = null, string $note = ''): void
{
    if ($qty == 0.0) {
        return;
    }
    $cid = current_company_id();
    $item = stock_item($itemId);
    if (!$item) {
        return;
    }
    $delta = in_array($kind, ['out', 'sale'], true) ? -abs($qty) : abs($qty);
    if ($kind === 'adjust') {
        $delta = $qty;
    }
    db_exec(
        'INSERT INTO stock_moves (company_id, item_id, kind, qty, unit_cost, document_id, note, user_id) VALUES (?,?,?,?,?,?,?,?)',
        'iisddisi',
        [$cid, $itemId, $kind, $delta, $unitCost, $documentId, $note !== '' ? mb_substr($note, 0, 190) : null, (int) ($_SESSION['user_id'] ?? 0)]
    );
    db_exec('UPDATE stock_items SET qty_on_hand = qty_on_hand + ? WHERE id = ? AND company_id = ?', 'dii', [$delta, $itemId, $cid]);
}

function stock_apply_document(int $documentId, string $kind, array $items): void
{
    if (!company_stock_enabled()) {
        return;
    }
    $existing = db_all('SELECT id FROM stock_moves WHERE document_id = ? AND company_id = ?', 'ii', [$documentId, current_company_id()]);
    if ($existing) {
        return;
    }
    $moveKind = match ($kind) {
        'invoice', 'receipt' => 'sale',
        'expense' => 'in',
        default => '',
    };
    if ($moveKind === '') {
        return;
    }
    if ($kind === 'receipt') {
        return;
    }
    foreach ($items as $item) {
        $sid = (int) ($item['stock_item_id'] ?? 0);
        if ($sid < 1) {
            continue;
        }
        $qty = (float) ($item['qty'] ?? 0);
        if ($qty == 0.0) {
            continue;
        }
        $cost = (float) ($item['rate'] ?? 0);
        stock_move($sid, $moveKind, $qty, $cost, $documentId, $kind);
    }
}

function stock_reverse_document(int $documentId): void
{
    if (!company_stock_enabled()) {
        return;
    }
    $cid = current_company_id();
    $moves = db_all('SELECT * FROM stock_moves WHERE document_id = ? AND company_id = ?', 'ii', [$documentId, $cid]);
    foreach ($moves as $m) {
        db_exec('UPDATE stock_items SET qty_on_hand = qty_on_hand - ? WHERE id = ? AND company_id = ?', 'dii', [(float) $m['qty'], (int) $m['item_id'], $cid]);
    }
    db_exec('DELETE FROM stock_moves WHERE document_id = ? AND company_id = ?', 'ii', [$documentId, $cid]);
}

function stock_today(): ?array
{
    return db_one('SELECT * FROM stock_days WHERE company_id = ? AND day_date = ?', 'is', [current_company_id(), today()]);
}

function stock_day_is_open(): bool
{
    $d = stock_today();
    return $d && $d['closed_at'] === null;
}

function stock_day_open(float $cash): array
{
    $row = stock_today();
    if ($row && $row['closed_at'] === null) {
        return ['ok' => true, 'id' => (int) $row['id']];
    }
    if ($row && $row['closed_at'] !== null) {
        return ['ok' => false, 'error' => 'This day is already closed.'];
    }
    $id = db_exec(
        'INSERT INTO stock_days (company_id, day_date, open_cash, opened_by) VALUES (?,?,?,?)',
        'isdi',
        [current_company_id(), today(), max(0, $cash), (int) ($_SESSION['user_id'] ?? 0)]
    );
    return ['ok' => true, 'id' => (int) $id];
}

function stock_day_totals(string $date): array
{
    $cid = current_company_id();
    $inv = db_all(
        "SELECT d.vat_rate, d.currency,
                (SELECT COALESCE(SUM(ROUND(qty * rate, 2)), 0) FROM document_items i WHERE i.document_id = d.id) AS net,
                (SELECT COALESCE(SUM(CASE WHEN taxed = 1 THEN ROUND(qty * rate, 2) ELSE 0 END), 0) FROM document_items i WHERE i.document_id = d.id) AS taxed_net
         FROM documents d WHERE d.company_id = ? AND d.status = 'issued' AND d.kind = 'invoice' AND d.date = ?",
        'is',
        [$cid, $date]
    );
    $exps = db_all(
        "SELECT d.vat_rate, d.currency,
                (SELECT COALESCE(SUM(ROUND(qty * rate, 2)), 0) FROM document_items i WHERE i.document_id = d.id) AS net,
                (SELECT COALESCE(SUM(CASE WHEN taxed = 1 THEN ROUND(qty * rate, 2) ELSE 0 END), 0) FROM document_items i WHERE i.document_id = d.id) AS taxed_net
         FROM documents d WHERE d.company_id = ? AND d.status = 'issued' AND d.kind = 'expense' AND d.date = ?",
        'is',
        [$cid, $date]
    );
    $base = default_currency();
    $income = 0.0;
    $outTax = 0.0;
    foreach ($inv as $d) {
        $net = (float) $d['net'];
        $vat = round((float) $d['taxed_net'] * (float) $d['vat_rate'], 2);
        $income += convert_money($net, doc_currency($d), $base);
        $outTax += convert_money($vat, doc_currency($d), $base);
    }
    $expense = 0.0;
    $inTax = 0.0;
    foreach ($exps as $d) {
        $net = (float) $d['net'];
        $vat = round((float) $d['taxed_net'] * (float) $d['vat_rate'], 2);
        $expense += convert_money($net, doc_currency($d), $base);
        $inTax += convert_money($vat, doc_currency($d), $base);
    }
    return [
        'income' => round($income, 2),
        'expense' => round($expense, 2),
        'tax' => round($outTax - $inTax, 2),
        'profit' => round($income - $expense, 2),
    ];
}

function stock_day_close(float $cash, string $notes = ''): array
{
    $row = stock_today();
    if (!$row || $row['closed_at'] !== null) {
        return ['ok' => false, 'error' => 'Open the day first.'];
    }
    $tot = stock_day_totals(today());
    db_exec(
        'UPDATE stock_days SET close_cash=?, closed_by=?, closed_at=NOW(), income=?, expense=?, tax=?, notes=? WHERE id=? AND company_id=?',
        'didddsii',
        [$cash, (int) ($_SESSION['user_id'] ?? 0), $tot['income'], $tot['expense'], $tot['tax'], mb_substr($notes, 0, 500), (int) $row['id'], current_company_id()]
    );
    return ['ok' => true, 'totals' => $tot];
}

function stock_xlsx_bytes(array $rows): string
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
    $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Stock" sheetId="1" r:id="rId1"/></sheets></workbook>';
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

function stock_send_template(): void
{
    $rows = stock_import_template_rows();
    if (class_exists('ZipArchive')) {
        $name = 'stock-template.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: no-store');
        echo stock_xlsx_bytes($rows);
        exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stock-template.csv"');
    $out = fopen('php://output', 'w');
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function stock_recent_counts(int $limit = 8): array
{
    return db_all('SELECT * FROM stock_counts WHERE company_id = ? ORDER BY id DESC LIMIT ?', 'ii', [current_company_id(), $limit]);
}

function stock_recent_days(int $limit = 14): array
{
    return db_all('SELECT * FROM stock_days WHERE company_id = ? ORDER BY day_date DESC LIMIT ?', 'ii', [current_company_id(), $limit]);
}

function stock_walkin_party(): int
{
    $cid = current_company_id();
    $row = db_one("SELECT id FROM parties WHERE company_id = ? AND name = 'Walk-in' ORDER BY id LIMIT 1", 'i', [$cid]);
    if ($row) {
        return (int) $row['id'];
    }
    return db_exec(
        'INSERT INTO parties (company_id, name, kind) VALUES (?, ?, ?)',
        'iss',
        [$cid, 'Walk-in', 'customer']
    );
}

function stock_find_or_create_party(string $name, string $kind = 'customer'): int
{
    $name = trim($name);
    $cid = current_company_id();
    if ($name === '') {
        return stock_walkin_party();
    }
    $found = db_one('SELECT id FROM parties WHERE company_id = ? AND name = ? ORDER BY id DESC LIMIT 1', 'is', [$cid, $name]);
    if ($found) {
        return (int) $found['id'];
    }
    return db_exec('INSERT INTO parties (company_id, name, kind) VALUES (?,?,?)', 'iss', [$cid, $name, $kind]);
}

function stock_catalog_payload(): array
{
    $out = [];
    foreach (stock_items(true) as $row) {
        $out[] = [
            'id' => (int) $row['id'],
            'sku' => (string) $row['sku'],
            'name' => (string) $row['name'],
            'description' => (string) $row['description'],
            'unit' => (string) $row['unit'],
            'buy' => (float) $row['buy_price'],
            'sell' => (float) $row['sell_price'],
            'qty' => (float) $row['qty_on_hand'],
            'reorder' => (float) $row['reorder_level'],
            'taxed' => (int) $row['taxed'] === 1,
        ];
    }
    return $out;
}

function stock_import_template_rows(): array
{
    return [
        ['SKU', 'Name', 'Description', 'Unit', 'Buying price', 'Selling price', 'Reorder level', 'Opening qty', 'Tax Y/N'],
        ['RICE25', 'Rice 25kg', 'Local grain', 'bag', '90000', '110000', '5', '20', 'Y'],
        ['SOAP', 'Bar soap', 'Household', 'pc', '1500', '2500', '10', '40', 'Y'],
    ];
}

function stock_parse_upload(string $tmp, string $name): array
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $text = '';
    if ($ext === 'csv' || $ext === 'txt') {
        $text = (string) file_get_contents($tmp);
    } elseif ($ext === 'xlsx' && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($tmp) === true) {
            $shared = [];
            $ss = $zip->getFromName('xl/sharedStrings.xml');
            if (is_string($ss) && $ss !== '') {
                if (preg_match_all('/<t[^>]*>([^<]*)<\/t>/', $ss, $m)) {
                    $shared = $m[1];
                }
            }
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $rows = [];
            if (is_string($sheet) && preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $sheet, $rowMatch)) {
                foreach ($rowMatch[1] as $rowXml) {
                    $cells = [];
                    if (preg_match_all('/<c[^>]*r="([A-Z]+)\d+"[^>]*>(?:<v>([^<]*)<\/v>)?/s', $rowXml, $cMatch, PREG_SET_ORDER)) {
                        foreach ($cMatch as $c) {
                            $val = $c[2] ?? '';
                            if (str_contains($c[0], 't="s"') && isset($shared[(int) $val])) {
                                $val = html_entity_decode((string) $shared[(int) $val], ENT_QUOTES | ENT_XML1, 'UTF-8');
                            }
                            $cells[] = $val;
                        }
                    }
                    if ($cells) {
                        $rows[] = $cells;
                    }
                }
            }
            return $rows;
        }
    }
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

function stock_import_rows(array $rows): array
{
    $added = 0;
    $updated = 0;
    $skipped = 0;
    $header = array_map(static fn ($v) => strtolower(trim((string) $v)), $rows[0] ?? []);
    $start = 0;
    if ($header && (str_contains($header[0] ?? '', 'sku') || str_contains($header[1] ?? '', 'name'))) {
        $start = 1;
    }
    for ($i = $start; $i < count($rows); $i++) {
        $r = $rows[$i];
        $sku = trim((string) ($r[0] ?? ''));
        $name = trim((string) ($r[1] ?? ''));
        if ($name === '') {
            $skipped++;
            continue;
        }
        $existing = null;
        if ($sku !== '') {
            $existing = db_one('SELECT id FROM stock_items WHERE company_id = ? AND sku = ?', 'is', [current_company_id(), $sku]);
        }
        if (!$existing) {
            $existing = db_one('SELECT id FROM stock_items WHERE company_id = ? AND name = ?', 'is', [current_company_id(), $name]);
        }
        $taxRaw = strtoupper(trim((string) ($r[8] ?? 'Y')));
        $fields = [
            'sku' => $sku,
            'name' => $name,
            'description' => (string) ($r[2] ?? ''),
            'unit' => (string) ($r[3] ?? 'pc'),
            'buy_price' => (float) str_replace(',', '', (string) ($r[4] ?? 0)),
            'sell_price' => (float) str_replace(',', '', (string) ($r[5] ?? 0)),
            'reorder_level' => (float) str_replace(',', '', (string) ($r[6] ?? 0)),
            'qty_on_hand' => (float) str_replace(',', '', (string) ($r[7] ?? 0)),
            'taxed' => in_array($taxRaw, ['N', 'NO', '0'], true) ? 0 : 1,
        ];
        if ($existing) {
            unset($fields['qty_on_hand']);
            $saved = stock_save_item($fields, (int) $existing['id']);
            if (!empty($saved['ok'])) {
                $updated++;
            } else {
                $skipped++;
            }
        } else {
            $saved = stock_save_item($fields, null);
            if (!empty($saved['ok'])) {
                $added++;
            } else {
                $skipped++;
            }
        }
    }
    return ['added' => $added, 'updated' => $updated, 'skipped' => $skipped];
}

function stock_post_count(array $counted): array
{
    $cid = current_company_id();
    $countId = db_exec(
        'INSERT INTO stock_counts (company_id, counted_on, status, user_id) VALUES (?,?,?,?)',
        'issi',
        [$cid, today(), 'posted', (int) ($_SESSION['user_id'] ?? 0)]
    );
    foreach ($counted as $itemId => $qty) {
        $itemId = (int) $itemId;
        $item = stock_item($itemId);
        if (!$item) {
            continue;
        }
        $newQty = round((float) $qty, 2);
        $sys = (float) $item['qty_on_hand'];
        db_exec(
            'INSERT INTO stock_count_lines (count_id, item_id, system_qty, counted_qty) VALUES (?,?,?,?)',
            'iidd',
            [$countId, $itemId, $sys, $newQty]
        );
        $diff = $newQty - $sys;
        if (abs($diff) > 0.0001) {
            stock_move($itemId, 'adjust', $diff, (float) $item['buy_price'], null, 'Stock count');
        }
    }
    return ['ok' => true, 'id' => (int) $countId];
}

function stock_complete_sale(array $input): array
{
    $lines = $input['lines'] ?? [];
    $clean = [];
    $sub = 0.0;
    foreach ($lines as $line) {
        $sid = (int) ($line['stock_item_id'] ?? 0);
        $qty = round((float) ($line['qty'] ?? 0), 2);
        $price = round((float) ($line['price'] ?? 0), 2);
        if ($sid < 1 || $qty <= 0) {
            continue;
        }
        $item = stock_item($sid);
        if (!$item) {
            return ['ok' => false, 'error' => 'A product on the list is missing.'];
        }
        if ($qty - (float) $item['qty_on_hand'] > 0.0001) {
            return ['ok' => false, 'error' => $item['name'] . ' has only ' . rtrim(rtrim(number_format((float) $item['qty_on_hand'], 2, '.', ''), '0'), '.') . ' left.'];
        }
        $taxed = !empty($line['taxed']);
        $clean[] = [
            'stock_item_id' => $sid,
            'item_name' => (string) $item['name'],
            'description' => (string) $item['description'],
            'qty' => $qty,
            'unit' => (string) $item['unit'],
            'rate' => $price,
            'taxed' => $taxed ? 1 : 0,
        ];
        $sub += round($qty * $price, 2);
    }
    if (!$clean) {
        return ['ok' => false, 'error' => 'Add a product to sell.'];
    }
    $discount = round((float) ($input['discount'] ?? 0), 2);
    if ($discount < 0) {
        $discount = 0;
    }
    if ($discount > $sub) {
        $discount = $sub;
    }
    if ($discount > 0 && $sub > 0) {
        $factor = ($sub - $discount) / $sub;
        foreach ($clean as &$row) {
            $row['rate'] = round((float) $row['rate'] * $factor, 2);
        }
        unset($row);
    }
    $anyTaxed = false;
    foreach ($clean as $row) {
        if (!empty($row['taxed'])) {
            $anyTaxed = true;
        }
    }
    $name = trim((string) ($input['customer'] ?? ''));
    $partyId = (int) ($input['party_id'] ?? 0);
    if ($partyId < 1) {
        $partyId = stock_find_or_create_party($name !== '' ? $name : 'Walk-in', 'customer');
    }
    $paid = round((float) ($input['paid'] ?? 0), 2);
    $method = mb_substr(trim((string) ($input['method'] ?? 'Cash')), 0, 80) ?: 'Cash';
    $vatRate = $anyTaxed ? company_tax_rate() : 0.0;
    $invoiceId = create_document([
        'kind' => 'invoice',
        'party_id' => $partyId,
        'date' => today(),
        'due_date' => today(),
        'vat_rate' => $vatRate,
        'notes' => $discount > 0 ? 'Sale. Discount ' . money($discount) : 'Sale',
        'payment_method' => $method,
        'items' => $clean,
    ]);
    $inv = load_document($invoiceId);
    $grand = (float) ($inv['totals']['total'] ?? 0);
    $receiptId = 0;
    if ($paid > 0) {
        $give = min($paid, $grand);
        $receiptId = create_document([
            'kind' => 'receipt',
            'party_id' => $partyId,
            'date' => today(),
            'related_id' => $invoiceId,
            'allocated_amount' => $give,
            'payment_method' => $method,
            'notes' => $paid + 0.009 < $grand ? 'Part payment on sale' : 'Sale paid',
            'items' => [],
        ]);
    }
    if (function_exists('company_backup_maybe')) {
        company_backup_maybe(true);
    }
    return [
        'ok' => true,
        'invoice_id' => $invoiceId,
        'receipt_id' => $receiptId,
        'print_id' => $receiptId > 0 ? $receiptId : $invoiceId,
        'balance' => max(0, round($grand - min($paid, $grand), 2)),
    ];
}

function stock_complete_purchase(array $input): array
{
    $lines = $input['lines'] ?? [];
    $clean = [];
    foreach ($lines as $line) {
        $sid = (int) ($line['stock_item_id'] ?? 0);
        $qty = round((float) ($line['qty'] ?? 0), 2);
        $price = round((float) ($line['price'] ?? 0), 2);
        if ($sid < 1 || $qty <= 0) {
            continue;
        }
        $item = stock_item($sid);
        if (!$item) {
            return ['ok' => false, 'error' => 'A product on the list is missing.'];
        }
        $clean[] = [
            'stock_item_id' => $sid,
            'item_name' => (string) $item['name'],
            'description' => (string) $item['description'],
            'qty' => $qty,
            'unit' => (string) $item['unit'],
            'rate' => $price,
            'taxed' => !empty($line['taxed']) ? 1 : 0,
        ];
        db_exec('UPDATE stock_items SET buy_price = ? WHERE id = ? AND company_id = ?', 'dii', [$price, $sid, current_company_id()]);
    }
    if (!$clean) {
        return ['ok' => false, 'error' => 'Add a product to buy.'];
    }
    $name = trim((string) ($input['supplier'] ?? ''));
    $partyId = (int) ($input['party_id'] ?? 0);
    if ($partyId < 1) {
        $partyId = stock_find_or_create_party($name !== '' ? $name : 'Supplier', 'supplier');
    }
    $anyTaxed = false;
    foreach ($clean as $row) {
        if (!empty($row['taxed'])) {
            $anyTaxed = true;
        }
    }
    $paid = round((float) ($input['paid'] ?? 0), 2);
    $method = mb_substr(trim((string) ($input['method'] ?? 'Cash')), 0, 80) ?: 'Cash';
    $expenseId = create_document([
        'kind' => 'expense',
        'party_id' => $partyId,
        'date' => today(),
        'vat_rate' => $anyTaxed ? company_tax_rate() : 0.0,
        'expense_category' => 'Stock',
        'payment_method' => $method,
        'notes' => 'Stock purchase',
        'items' => $clean,
    ]);
    $exp = load_document($expenseId);
    $grand = (float) ($exp['totals']['total'] ?? 0);
    $receiptId = 0;
    if ($paid > 0) {
        $give = min($paid, $grand);
        $receiptId = create_document([
            'kind' => 'receipt',
            'party_id' => $partyId,
            'date' => today(),
            'related_id' => $expenseId,
            'allocated_amount' => $give,
            'payment_method' => $method,
            'notes' => $paid + 0.009 < $grand ? 'Part payment on stock purchase' : 'Stock purchase paid',
            'items' => [],
        ]);
    }
    if (function_exists('company_backup_maybe')) {
        company_backup_maybe(true);
    }
    return ['ok' => true, 'expense_id' => $expenseId, 'receipt_id' => $receiptId, 'balance' => max(0, round($grand - min($paid, $grand), 2))];
}
