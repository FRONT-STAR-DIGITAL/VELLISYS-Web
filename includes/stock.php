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
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script === 'sale.php') {
        if (function_exists('user_can_feature') && !user_can_feature('sale')) {
            flash('Your login cannot open sales.', 'err');
            redirect('dashboard.php');
        }
    } elseif (function_exists('user_can_feature') && !user_can_feature('stock')) {
        flash('Your login cannot open stock.', 'err');
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
    return !function_exists('user_can_feature') || user_can_feature('purchases');
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

function stock_item_is_service(array $row): bool
{
    return (int) ($row['is_service'] ?? 0) === 1;
}

function stock_goods_only(array $items): array
{
    return array_values(array_filter($items, static fn ($row) => !stock_item_is_service($row)));
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

function stock_items_by_ids(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));
    if (!$ids) {
        return [];
    }
    $cid = current_company_id();
    $in = implode(',', array_fill(0, count($ids), '?'));
    $types = 'i' . str_repeat('i', count($ids));
    $rows = db_all('SELECT * FROM stock_items WHERE company_id = ? AND id IN (' . $in . ')', $types, array_merge([$cid], $ids));
    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['id']] = $row;
    }
    return $map;
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
        'SELECT COALESCE(SUM(CASE WHEN COALESCE(is_service, 0) = 0 THEN 1 ELSE 0 END), 0) AS n,
                COALESCE(SUM(CASE WHEN COALESCE(is_service, 0) = 0 THEN qty_on_hand * buy_price ELSE 0 END), 0) AS cost,
                COALESCE(SUM(CASE WHEN COALESCE(is_service, 0) = 0 THEN qty_on_hand * sell_price ELSE 0 END), 0) AS sell,
                COALESCE(SUM(CASE WHEN COALESCE(is_service, 0) = 0 AND reorder_level > 0 AND qty_on_hand <= reorder_level THEN 1 ELSE 0 END), 0) AS low
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
        'SELECT * FROM stock_items WHERE company_id = ? AND active = 1 AND COALESCE(is_service, 0) = 0 AND reorder_level > 0 AND qty_on_hand <= reorder_level ORDER BY qty_on_hand, name',
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
    $isService = !empty($fields['is_service']);
    $buy = $isService ? 0.0 : round((float) ($fields['buy_price'] ?? 0), 2);
    $sell = round((float) ($fields['sell_price'] ?? 0), 2);
    $reorder = $isService ? 0.0 : round((float) ($fields['reorder_level'] ?? 0), 2);
    $taxed = empty($fields['taxed']) ? 0 : 1;
    $active = isset($fields['active']) && (int) $fields['active'] === 0 ? 0 : 1;
    $svc = $isService ? 1 : 0;
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
        if ($isService && !stock_item_is_service($row) && (float) ($row['qty_on_hand'] ?? 0) != 0.0) {
            stock_move($id, 'adjust', -(float) $row['qty_on_hand'], 0, null, 'Converted to service');
        }
        db_exec(
            'UPDATE stock_items SET sku=?, name=?, description=?, unit=?, buy_price=?, sell_price=?, reorder_level=?, taxed=?, active=?, is_service=? WHERE id=? AND company_id=?',
            'ssssdddiiiii',
            [$sku, $name, $desc, $unit, $buy, $sell, $reorder, $taxed, $active, $svc, $id, $cid]
        );
        return ['ok' => true, 'id' => $id];
    }
    $qty = $isService ? 0.0 : round((float) ($fields['qty_on_hand'] ?? 0), 2);
    $newId = db_exec(
        'INSERT INTO stock_items (company_id, sku, name, description, unit, buy_price, sell_price, reorder_level, qty_on_hand, taxed, active, is_service) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        'issssddddiii',
        [$cid, $sku, $name, $desc, $unit, $buy, $sell, $reorder, 0, $taxed, $active, $svc]
    );
    if ($qty > 0) {
        stock_move((int) $newId, 'in', $qty, $buy, null, 'Opening quantity');
    }
    return ['ok' => true, 'id' => (int) $newId];
}

function stock_delete_item(int $id): array
{
    if (function_exists('user_can_delete_stock') && !user_can_delete_stock()) {
        return ['ok' => false, 'error' => 'Your login cannot delete products.'];
    }
    $row = stock_item($id);
    if (!$row) {
        return ['ok' => false, 'error' => 'That product is not on this desk.'];
    }
    $cid = current_company_id();
    db_exec(
        'UPDATE document_items di INNER JOIN documents d ON d.id = di.document_id SET di.stock_item_id = NULL WHERE di.stock_item_id = ? AND d.company_id = ?',
        'ii',
        [$id, $cid]
    );
    db_exec(
        'DELETE cl FROM stock_count_lines cl INNER JOIN stock_counts c ON c.id = cl.count_id WHERE cl.item_id = ? AND c.company_id = ?',
        'ii',
        [$id, $cid]
    );
    db_exec('DELETE FROM stock_moves WHERE item_id = ? AND company_id = ?', 'ii', [$id, $cid]);
    db_exec('DELETE FROM stock_items WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
    if (function_exists('record_company_activity')) {
        record_company_activity('stock', 'Product deleted', [
            'detail' => (string) $row['name'],
            'href' => 'stock.php?tab=items',
        ]);
    }
    return ['ok' => true, 'name' => (string) $row['name']];
}

function stock_delete_button(int $id, string $class = 'btn danger sm'): void
{
    if ($id <= 0 || (function_exists('user_can_delete_stock') && !user_can_delete_stock())) {
        return;
    }
    ?>
    <form method="post" onsubmit="return confirm('Delete this product from the list? Sheets already issued keep the name. This cannot be undone.');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_item">
      <input type="hidden" name="item_id" value="<?= $id ?>">
      <button class="<?= h($class) ?>" type="submit"><?= icon('trash', 14) ?>Delete</button>
    </form>
    <?php
}

function stock_move(int $itemId, string $kind, float $qty, float $unitCost = 0, ?int $documentId = null, string $note = ''): void
{
    if ($qty == 0.0) {
        return;
    }
    $cid = current_company_id();
    $item = stock_item($itemId);
    if (!$item || stock_item_is_service($item)) {
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
        $row = stock_item($sid) ?: [];
        if (stock_item_is_service($row)) {
            continue;
        }
        $cost = $moveKind === 'sale'
            ? (float) ($row['buy_price'] ?? 0)
            : (float) ($item['rate'] ?? ($row['buy_price'] ?? 0));
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

function stock_blank_totals(): array
{
    return [
        'income' => 0.0,
        'cogs' => 0.0,
        'expense' => 0.0,
        'tax' => 0.0,
        'profit' => 0.0,
        'net' => 0.0,
    ];
}

function stock_finish_totals(array $row): array
{
    $row['income'] = round((float) ($row['income'] ?? 0), 2);
    $row['cogs'] = round((float) ($row['cogs'] ?? 0), 2);
    $row['expense'] = round((float) ($row['expense'] ?? 0), 2);
    $row['tax'] = round((float) ($row['tax'] ?? 0), 2);
    $row['profit'] = round($row['income'] - $row['cogs'], 2);
    $row['net'] = round($row['profit'] - $row['expense'], 2);
    return $row;
}

function stock_is_stock_expense(array $doc): bool
{
    $cat = strtolower(trim((string) ($doc['expense_category'] ?? '')));
    return $cat === 'stock';
}

function stock_day_totals(string $date): array
{
    $by = stock_performance_range($date, $date);
    return $by[$date] ?? stock_finish_totals(stock_blank_totals());
}

function stock_range_totals(string $from, string $to): array
{
    $sum = stock_blank_totals();
    foreach (stock_performance_range($from, $to) as $row) {
        $sum['income'] += $row['income'];
        $sum['cogs'] += $row['cogs'];
        $sum['expense'] += $row['expense'];
        $sum['tax'] += $row['tax'];
    }
    return stock_finish_totals($sum);
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
            'buy' => (float) $row['buy_price'],
            'sell' => (float) $row['sell_price'],
            'qty' => stock_item_is_service($row) ? null : (float) $row['qty_on_hand'],
            'taxed' => (int) $row['taxed'] === 1,
            'service' => stock_item_is_service($row),
        ];
    }
    return $out;
}

function stock_import_template_rows(): array
{
    return [
        ['SKU', 'Name', 'Description', 'Unit', 'Type', 'Buying price', 'Selling price', 'Reorder level', 'Opening qty', 'Tax Y/N'],
        ['RICE25', 'Rice 25kg', 'Local grain', 'bag', 'product', '90000', '110000', '5', '20', 'Y'],
        ['LESSON', 'Driving lesson', 'One hour', 'hr', 'service', '', '80000', '', '', 'Y'],
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
        $taxRaw = strtoupper(trim((string) ($r[8] ?? '')));
        $col4 = trim((string) ($r[4] ?? ''));
        $typed = (bool) preg_match('/^(product|products|good|goods|item|service|services|svc)$/i', $col4);
        $isService = $typed && (bool) preg_match('/^serv/i', $col4);
        if ($typed) {
            $buy = (float) str_replace(',', '', (string) ($r[5] ?? 0));
            $sell = (float) str_replace(',', '', (string) ($r[6] ?? 0));
            $reorder = (float) str_replace(',', '', (string) ($r[7] ?? 0));
            $qty = (float) str_replace(',', '', (string) ($r[8] ?? 0));
            $taxRaw = strtoupper(trim((string) ($r[9] ?? '')));
        } else {
            $buy = (float) str_replace(',', '', $col4);
            $sell = (float) str_replace(',', '', (string) ($r[5] ?? 0));
            $reorder = (float) str_replace(',', '', (string) ($r[6] ?? 0));
            $qty = (float) str_replace(',', '', (string) ($r[7] ?? 0));
        }
        if ($taxRaw === '') {
            $taxed = company_tax_default();
        } else {
            $taxed = in_array($taxRaw, ['N', 'NO', '0'], true) ? 0 : 1;
        }
        $fields = [
            'sku' => $sku,
            'name' => $name,
            'description' => (string) ($r[2] ?? ''),
            'unit' => (string) ($r[3] ?? 'pc'),
            'is_service' => $isService ? 1 : 0,
            'buy_price' => $buy,
            'sell_price' => $sell,
            'reorder_level' => $reorder,
            'qty_on_hand' => $qty,
            'taxed' => $taxed,
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
        if (!$item || stock_item_is_service($item)) {
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
    $ids = [];
    foreach ($lines as $line) {
        $ids[] = (int) ($line['stock_item_id'] ?? 0);
    }
    $stockMap = stock_items_by_ids($ids);
    $clean = [];
    $sub = 0.0;
    foreach ($lines as $line) {
        $sid = (int) ($line['stock_item_id'] ?? 0);
        $qty = round((float) ($line['qty'] ?? 0), 2);
        $price = round((float) ($line['price'] ?? 0), 2);
        if ($sid < 1 || $qty <= 0) {
            continue;
        }
        $item = $stockMap[$sid] ?? null;
        if (!$item) {
            return ['ok' => false, 'error' => 'A product on the list is missing.'];
        }
        if (!stock_item_is_service($item) && $qty - (float) $item['qty_on_hand'] > 0.0001) {
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
    $method = stock_payment_key((string) ($input['method'] ?? 'cash'));
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
    $grand = doc_total($clean, $vatRate);
    $receiptId = 0;
    if (!empty($input['pay_all'])) {
        $paid = $grand;
    } else {
        $paid = round((float) ($input['paid'] ?? 0), 2);
    }
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
        company_backup_maybe();
    }
    return [
        'ok' => true,
        'invoice_id' => $invoiceId,
        'receipt_id' => $receiptId,
        'print_id' => $invoiceId,
        'balance' => max(0, round($grand - min($paid, $grand), 2)),
    ];
}

function stock_complete_purchase(array $input): array
{
    $lines = $input['lines'] ?? [];
    $clean = [];
    foreach ($lines as $line) {
        $qty = round((float) ($line['qty'] ?? 0), 2);
        $price = round((float) ($line['price'] ?? 0), 2);
        if ($qty <= 0) {
            continue;
        }
        $sid = stock_ensure_item_id($line, $price);
        if ($sid < 1) {
            continue;
        }
        $item = stock_item($sid);
        if (!$item) {
            return ['ok' => false, 'error' => 'A product on the list is missing.'];
        }
        if (stock_item_is_service($item)) {
            continue;
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
    $method = stock_payment_key((string) ($input['method'] ?? 'cash'));
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
        company_backup_maybe();
    }
    return ['ok' => true, 'expense_id' => $expenseId, 'receipt_id' => $receiptId, 'balance' => max(0, round($grand - min($paid, $grand), 2))];
}

function stock_payment_key(string $raw): string
{
    $raw = trim($raw);
    $methods = payment_methods();
    if (isset($methods[$raw])) {
        return $raw;
    }
    foreach ($methods as $key => $label) {
        if (strcasecmp($label, $raw) === 0) {
            return $key;
        }
    }
    return 'cash';
}

function stock_qty_label(float $n): string
{
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') ?: '0';
}

function stock_ensure_item_id(array $line, float $price): int
{
    $sid = (int) ($line['stock_item_id'] ?? 0);
    if ($sid > 0 && stock_item($sid)) {
        return $sid;
    }
    $name = trim((string) ($line['name'] ?? ''));
    if ($name === '') {
        return 0;
    }
    $found = db_one('SELECT id FROM stock_items WHERE company_id = ? AND name = ? ORDER BY id DESC LIMIT 1', 'is', [current_company_id(), $name]);
    if ($found) {
        return (int) $found['id'];
    }
    $saved = stock_save_item([
        'name' => $name,
        'sku' => (string) ($line['sku'] ?? ''),
        'description' => (string) ($line['description'] ?? ''),
        'unit' => (string) ($line['unit'] ?? 'pc'),
        'buy_price' => $price,
        'sell_price' => $price,
        'qty_on_hand' => 0,
        'taxed' => !empty($line['taxed']) ? 1 : 0,
    ]);
    return !empty($saved['ok']) ? (int) $saved['id'] : 0;
}

function stock_last_print_id(): int
{
    $cid = current_company_id();
    $sid = (int) ($_SESSION['stock_last_print'] ?? 0);
    if ($sid > 0) {
        $ok = db_one('SELECT id FROM documents WHERE id = ? AND company_id = ? AND status = \'issued\'', 'ii', [$sid, $cid]);
        if ($ok) {
            return $sid;
        }
    }
    $inv = db_one(
        "SELECT id FROM documents WHERE company_id = ? AND kind = 'invoice' AND status = 'issued' ORDER BY id DESC LIMIT 1",
        'i',
        [$cid]
    );
    if (!$inv) {
        return 0;
    }
    $rec = db_one(
        "SELECT id FROM documents WHERE company_id = ? AND kind = 'receipt' AND related_id = ? AND status = 'issued' ORDER BY id DESC LIMIT 1",
        'ii',
        [$cid, (int) $inv['id']]
    );
    return $rec ? (int) $rec['id'] : (int) $inv['id'];
}

function stock_page_key(string $key): int
{
    return max(1, (int) ($_GET[$key] ?? 1));
}

function stock_q(): string
{
    return trim((string) ($_GET['q'] ?? ''));
}

function stock_pager(string $base, int $page, int $pages, string $pageKey = 'p'): void
{
    if ($pages <= 1) {
        return;
    }
    $q = stock_q();
    $mk = static function (int $n) use ($base, $pageKey, $q): string {
        $sep = str_contains($base, '?') ? '&' : '?';
        $url = $base . $sep . $pageKey . '=' . $n;
        if ($q !== '') {
            $url .= '&q=' . rawurlencode($q);
        }
        foreach (['range', 'from', 'to'] as $k) {
            $v = trim((string) ($_GET[$k] ?? ''));
            if ($v !== '' && !str_contains($url, $k . '=')) {
                $url .= '&' . $k . '=' . rawurlencode($v);
            }
        }
        return $url;
    };
    ?>
  <p class="stock-pager">
    <?php if ($page > 1): ?>
      <a class="btn ghost sm" href="<?= h(url($mk($page - 1))) ?>">Previous</a>
    <?php endif; ?>
    <span>Page <?= (int) $page ?> of <?= (int) $pages ?></span>
    <?php if ($page < $pages): ?>
      <a class="btn sm" href="<?= h(url($mk($page + 1))) ?>">Next page</a>
    <?php endif; ?>
  </p>
    <?php
}

function stock_search_bar(string $script, array $hidden = [], string $placeholder = 'Search'): void
{
    ?>
  <form class="stock-search" method="get" action="<?= h(url($script)) ?>">
    <?php foreach ($hidden as $k => $v): ?>
      <input type="hidden" name="<?= h((string) $k) ?>" value="<?= h((string) $v) ?>">
    <?php endforeach; ?>
    <input type="search" name="q" value="<?= h(stock_q()) ?>" placeholder="<?= h($placeholder) ?>" autocomplete="off">
    <button class="btn ghost sm" type="submit"><?= icon('eye', 14) ?>Search</button>
  </form>
    <?php
}

function stock_slice(array $rows, int $page, int $per = 20): array
{
    $total = count($rows);
    $pages = max(1, (int) ceil($total / $per));
    $page = min(max(1, $page), $pages);
    return [
        'rows' => array_slice($rows, ($page - 1) * $per, $per),
        'page' => $page,
        'pages' => $pages,
        'total' => $total,
        'from' => $total ? (($page - 1) * $per) + 1 : 0,
    ];
}

function stock_filter_items(array $items, string $q): array
{
    $q = mb_strtolower(trim($q));
    if ($q === '') {
        return $items;
    }
    return array_values(array_filter($items, static function (array $row) use ($q): bool {
        $hay = mb_strtolower(($row['name'] ?? '') . ' ' . ($row['sku'] ?? '') . ' ' . ($row['description'] ?? ''));
        return str_contains($hay, $q);
    }));
}

function stock_search_docs(string $kind, string $q, int $page, int $per = 20, ?string $date = null, ?string $category = null, ?string $dateTo = null): array
{
    $cid = current_company_id();
    $where = 'd.company_id = ? AND d.kind = ?';
    $types = 'is';
    $params = [$cid, $kind];
    if ($date) {
        if ($dateTo) {
            $where .= ' AND d.date >= ? AND d.date <= ?';
            $types .= 'ss';
            $params[] = $date;
            $params[] = $dateTo;
        } else {
            $where .= ' AND d.date = ?';
            $types .= 's';
            $params[] = $date;
        }
    }
    if ($category !== null && $category !== '') {
        $where .= ' AND d.expense_category = ?';
        $types .= 's';
        $params[] = $category;
    }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where .= ' AND (d.number LIKE ? OR p.name LIKE ?)';
        $types .= 'ss';
        $params[] = $like;
        $params[] = $like;
    }
    $join = ' FROM documents d JOIN parties p ON p.id = d.party_id WHERE ' . $where;
    $count = db_one('SELECT COUNT(*) AS n' . $join, $types, $params);
    $total = (int) ($count['n'] ?? 0);
    $pages = max(1, (int) ceil($total / max(1, $per)));
    $page = min(max(1, $page), $pages);
    $off = ($page - 1) * $per;
    $rows = db_all(
        'SELECT d.*, p.name AS party_name' . $join . ' ORDER BY d.id DESC LIMIT ' . (int) $per . ' OFFSET ' . (int) $off,
        $types,
        $params
    );
    if (function_exists('attach_document_totals')) {
        $rows = attach_document_totals($rows);
    }
    return ['rows' => $rows, 'page' => $page, 'pages' => $pages, 'total' => $total, 'from' => $total ? $off + 1 : 0];
}

function stock_performance_range(string $from, string $to): array
{
    $cid = current_company_id();
    $docs = db_all(
        "SELECT d.id, d.date, d.kind, d.vat_rate, d.currency, d.expense_category,
                (SELECT COALESCE(SUM(ROUND(qty * rate, 2)), 0) FROM document_items i WHERE i.document_id = d.id) AS net,
                (SELECT COALESCE(SUM(CASE WHEN taxed = 1 THEN ROUND(qty * rate, 2) ELSE 0 END), 0) FROM document_items i WHERE i.document_id = d.id) AS taxed_net
         FROM documents d
         WHERE d.company_id = ? AND d.status = 'issued' AND d.kind IN ('invoice','expense') AND d.date >= ? AND d.date <= ?",
        'iss',
        [$cid, $from, $to]
    );
    $cogsRows = db_all(
        "SELECT d.id, d.date, d.currency,
                COALESCE(SUM(ROUND(i.qty * COALESCE(s.buy_price, 0), 2)), 0) AS cogs
         FROM documents d
         JOIN document_items i ON i.document_id = d.id
         LEFT JOIN stock_items s ON s.id = i.stock_item_id AND s.company_id = d.company_id
         WHERE d.company_id = ? AND d.status = 'issued' AND d.kind = 'invoice'
           AND d.date >= ? AND d.date <= ? AND i.stock_item_id IS NOT NULL AND i.stock_item_id > 0
           AND COALESCE(s.is_service, 0) = 0
         GROUP BY d.id, d.date, d.currency",
        'iss',
        [$cid, $from, $to]
    );
    $by = [];
    $base = default_currency();
    foreach ($docs as $d) {
        $day = (string) $d['date'];
        if (!isset($by[$day])) {
            $by[$day] = stock_blank_totals();
        }
        $net = convert_money((float) $d['net'], doc_currency($d), $base);
        $vat = convert_money(round((float) $d['taxed_net'] * (float) $d['vat_rate'], 2), doc_currency($d), $base);
        if ($d['kind'] === 'invoice') {
            $by[$day]['income'] += $net;
            $by[$day]['tax'] += $vat;
        } elseif (!stock_is_stock_expense($d)) {
            $by[$day]['expense'] += $net;
            $by[$day]['tax'] -= $vat;
        }
    }
    foreach ($cogsRows as $d) {
        $day = (string) $d['date'];
        if (!isset($by[$day])) {
            $by[$day] = stock_blank_totals();
        }
        $by[$day]['cogs'] += convert_money((float) $d['cogs'], doc_currency($d), $base);
    }
    ksort($by);
    foreach ($by as $day => $row) {
        $by[$day] = stock_finish_totals($row);
    }
    return $by;
}

function stock_month_roll(array $byDay): array
{
    $out = [];
    foreach ($byDay as $day => $row) {
        $m = substr((string) $day, 0, 7);
        if (!isset($out[$m])) {
            $out[$m] = stock_blank_totals();
        }
        $out[$m]['income'] += $row['income'];
        $out[$m]['cogs'] += $row['cogs'] ?? 0;
        $out[$m]['expense'] += $row['expense'];
        $out[$m]['tax'] += $row['tax'];
    }
    ksort($out);
    foreach ($out as $m => $row) {
        $out[$m] = stock_finish_totals($row);
    }
    return $out;
}

function stock_day_dashboard(string $from, string $to): array
{
    $byDay = stock_performance_range($from, $to);
    $totals = stock_range_totals($from, $to);
    $months = stock_month_roll($byDay);
    $days = [];
    $start = strtotime($from);
    $end = strtotime($to);
    $span = ($start && $end) ? (int) round(($end - $start) / 86400) : 0;
    if ($start && $end && $span >= 0 && $span <= 62) {
        for ($t = $start; $t <= $end; $t += 86400) {
            $d = date('Y-m-d', $t);
            $days[$d] = $byDay[$d] ?? stock_finish_totals(stock_blank_totals());
        }
    } else {
        $days = $byDay;
    }
    $q = stock_q();
    $showProfit = !function_exists('user_can_see_profit') || user_can_see_profit();
    $sales = stock_search_docs('invoice', $q, stock_page_key('sp'), 20, $from, null, $to);
    $spend = stock_search_docs('expense', $q, stock_page_key('ep'), 20, $from, null, $to);
    return [
        'from' => $from,
        'to' => $to,
        'totals' => $totals,
        'days' => $days,
        'months' => $months,
        'sales' => $sales,
        'spend' => $spend,
        'show_profit' => $showProfit,
    ];
}

function render_stock_payment_select(string $name, bool $disabled = false, string $selected = 'cash'): void
{
    $selected = stock_payment_key($selected);
    ?>
    <select id="<?= h($name) ?>" name="<?= h($name) ?>" <?= $disabled ? 'disabled' : '' ?>>
      <?php foreach (payment_methods() as $k => $label): ?>
        <option value="<?= h($k) ?>" <?= $selected === $k ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
    <?php
}

function render_stock_docs_table(array $page, string $base, string $pageKey, string $empty): void
{
    $rows = $page['rows'];
    $n = (int) ($page['from'] ?? 1);
    if (!$rows): ?>
      <p class="empty"><?= h($empty) ?></p>
    <?php else: ?>
      <div class="table-scroll">
        <table class="grid">
          <thead>
            <tr>
              <th>#</th>
              <th>Number</th>
              <th>Name</th>
              <th>Date</th>
              <th class="right">Amount</th>
              <th class="right">Paid</th>
              <th class="right">Balance</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $doc): ?>
              <tr>
                <td class="mono"><?= (int) $n ?></td>
                <td class="mono"><a href="<?= h(url('document_view.php?id=' . (int) $doc['id'])) ?>"><?= h($doc['number']) ?></a></td>
                <td><?= h((string) ($doc['party_name'] ?? '')) ?></td>
                <td class="date-cell"><?= h(format_date($doc['date'])) ?></td>
                <td class="right mono"><?= h(money((float) ($doc['totals']['total'] ?? 0), doc_currency($doc))) ?></td>
                <td class="right mono"><?= h(money((float) ($doc['paid'] ?? 0), doc_currency($doc))) ?></td>
                <td class="right mono"><?= h(money((float) ($doc['balance'] ?? 0), doc_currency($doc))) ?></td>
                <td><span class="pill"><?= h(invoice_status_label($doc)) ?></span></td>
                <td class="row-actions"><?php render_doc_actions($doc); ?></td>
              </tr>
              <?php $n++; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php stock_pager($base, (int) $page['page'], (int) $page['pages'], $pageKey); ?>
    <?php endif;
}

