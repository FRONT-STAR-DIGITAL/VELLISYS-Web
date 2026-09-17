<?php
declare(strict_types=1);

function company_backup_dir(?int $cid = null): string
{
    $cid = $cid ?? current_company_id();
    $dir = ROOT_PATH . '/uploads/backups/' . $cid;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function company_backup_tables(): array
{
    return ['parties', 'stock_items', 'documents', 'document_items', 'stock_moves', 'stock_counts', 'stock_count_lines', 'stock_days'];
}

function company_backup_payload(?int $cid = null): array
{
    $cid = $cid ?? current_company_id();
    $docs = db_all('SELECT * FROM documents WHERE company_id = ? ORDER BY id', 'i', [$cid]);
    $docIds = array_map(static fn ($d) => (int) $d['id'], $docs);
    $items = [];
    if ($docIds) {
        $in = implode(',', array_fill(0, count($docIds), '?'));
        $types = str_repeat('i', count($docIds));
        $items = db_all('SELECT * FROM document_items WHERE document_id IN (' . $in . ') ORDER BY id', $types, $docIds);
    }
    $counts = db_all('SELECT * FROM stock_counts WHERE company_id = ? ORDER BY id', 'i', [$cid]);
    $countIds = array_map(static fn ($d) => (int) $d['id'], $counts);
    $countLines = [];
    if ($countIds) {
        $in = implode(',', array_fill(0, count($countIds), '?'));
        $types = str_repeat('i', count($countIds));
        $countLines = db_all('SELECT * FROM stock_count_lines WHERE count_id IN (' . $in . ') ORDER BY id', $types, $countIds);
    }
    $brand = db_one('SELECT * FROM branding WHERE company_id = ?', 'i', [$cid]);
    if ($brand) {
        unset($brand['logo_blob'], $brand['letterhead_blob']);
    }
    return [
        'v' => 1,
        'app' => 'vellisys',
        'company_id' => $cid,
        'exported_at' => date('c'),
        'tables' => [
            'parties' => db_all('SELECT * FROM parties WHERE company_id = ? ORDER BY id', 'i', [$cid]),
            'stock_items' => db_all('SELECT * FROM stock_items WHERE company_id = ? ORDER BY id', 'i', [$cid]),
            'documents' => $docs,
            'document_items' => $items,
            'stock_moves' => db_all('SELECT * FROM stock_moves WHERE company_id = ? ORDER BY id', 'i', [$cid]),
            'stock_counts' => $counts,
            'stock_count_lines' => $countLines,
            'stock_days' => db_all('SELECT * FROM stock_days WHERE company_id = ? ORDER BY id', 'i', [$cid]),
        ],
        'branding' => $brand,
    ];
}

function company_backup_write(bool $force = false, ?int $cid = null): ?string
{
    $cid = $cid ?? current_company_id();
    if ($cid < 1) {
        return null;
    }
    $dir = company_backup_dir($cid);
    $path = $dir . '/' . today() . '.json.gz';
    if (!$force && is_file($path)) {
        return $path;
    }
    $json = json_encode(company_backup_payload($cid), JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return null;
    }
    $gz = gzencode($json, 6);
    if ($gz === false) {
        return null;
    }
    if (file_put_contents($path, $gz) === false) {
        return null;
    }
    $files = glob($dir . '/*.json.gz') ?: [];
    rsort($files);
    foreach (array_slice($files, 14) as $old) {
        @unlink($old);
    }
    return $path;
}

function company_backup_maybe(bool $force = false): void
{
    static $done = false;
    if ($done && !$force) {
        return;
    }
    try {
        if (company_backup_write($force)) {
            $done = true;
        }
    } catch (Throwable $e) {
        // Backup must never block the desk.
    }
}

function company_backup_list(?int $cid = null): array
{
    $dir = company_backup_dir($cid);
    $files = glob($dir . '/*.json.gz') ?: [];
    rsort($files);
    $out = [];
    foreach ($files as $f) {
        $out[] = [
            'file' => basename($f),
            'path' => $f,
            'size' => (int) filesize($f),
            'mtime' => (int) filemtime($f),
        ];
    }
    return $out;
}

function company_backup_send(string $file, ?int $cid = null): void
{
    $base = basename($file);
    $admin = $cid !== null && $cid > 0;
    $fail = $admin ? ('admin_company.php?id=' . $cid) : 'settings.php#backup';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}\.json\.gz$/', $base)) {
        flash('That backup file is not allowed.', 'err');
        redirect($fail);
    }
    $path = company_backup_dir($cid) . '/' . $base;
    if (!is_file($path)) {
        flash('Backup not found.', 'err');
        redirect($fail);
    }
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="vellisys-backup-' . $base . '"');
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
    exit;
}

function company_backup_read_file(string $tmp): ?array
{
    $raw = (string) file_get_contents($tmp);
    if ($raw === '') {
        return null;
    }
    if (str_starts_with($raw, "\x1f\x8b")) {
        $raw = (string) gzdecode($raw);
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function backup_insert_row(string $table, array $row, array $skip = ['id']): int
{
    $cols = [];
    $vals = [];
    $types = '';
    foreach ($row as $k => $v) {
        if (in_array($k, $skip, true)) {
            continue;
        }
        $cols[] = $k;
        $vals[] = $v;
        $types .= is_int($v) ? 'i' : (is_float($v) ? 'd' : 's');
    }
    if (!$cols) {
        return 0;
    }
    $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    return db_exec($sql, $types, $vals);
}

function company_backup_restore_payload(array $data, ?int $cid = null, array $opts = []): array
{
    if (($data['app'] ?? '') !== 'vellisys' && (int) ($data['v'] ?? 0) < 1) {
        return ['ok' => false, 'error' => 'This file is not a Vellisys backup.'];
    }
    $tables = $data['tables'] ?? null;
    if (!is_array($tables)) {
        return ['ok' => false, 'error' => 'Backup is missing desk data.'];
    }
    $cid = $cid ?? (function_exists('current_company_id') ? current_company_id() : 0);
    if ($cid < 1) {
        return ['ok' => false, 'error' => 'No company to restore into.'];
    }
    $restoreBrand = !empty($opts['branding']);
    $db = db();
    $db->begin_transaction();
    try {
        $countIds = array_map(static fn ($r) => (int) $r['id'], db_all('SELECT id FROM stock_counts WHERE company_id = ?', 'i', [$cid]));
        if ($countIds) {
            $in = implode(',', array_fill(0, count($countIds), '?'));
            $types = str_repeat('i', count($countIds));
            db_prepare('DELETE FROM stock_count_lines WHERE count_id IN (' . $in . ')', $types, $countIds)->execute();
        }
        db_exec('DELETE FROM stock_counts WHERE company_id = ?', 'i', [$cid]);
        db_exec('DELETE FROM stock_moves WHERE company_id = ?', 'i', [$cid]);
        db_exec('DELETE FROM stock_days WHERE company_id = ?', 'i', [$cid]);
        $docIds = array_map(static fn ($r) => (int) $r['id'], db_all('SELECT id FROM documents WHERE company_id = ?', 'i', [$cid]));
        if ($docIds) {
            $in = implode(',', array_fill(0, count($docIds), '?'));
            $types = str_repeat('i', count($docIds));
            db_prepare('DELETE FROM document_items WHERE document_id IN (' . $in . ')', $types, $docIds)->execute();
        }
        db_exec('DELETE FROM documents WHERE company_id = ?', 'i', [$cid]);
        db_exec('DELETE FROM stock_items WHERE company_id = ?', 'i', [$cid]);
        db_exec('DELETE FROM parties WHERE company_id = ?', 'i', [$cid]);

        $partyMap = [];
        foreach ($tables['parties'] ?? [] as $row) {
            $old = (int) $row['id'];
            $row['company_id'] = $cid;
            $partyMap[$old] = backup_insert_row('parties', $row);
        }
        $stockMap = [];
        foreach ($tables['stock_items'] ?? [] as $row) {
            $old = (int) $row['id'];
            $row['company_id'] = $cid;
            $stockMap[$old] = backup_insert_row('stock_items', $row);
        }
        $docMap = [];
        foreach ($tables['documents'] ?? [] as $row) {
            $old = (int) $row['id'];
            $row['company_id'] = $cid;
            $pid = (int) ($row['party_id'] ?? 0);
            $row['party_id'] = $partyMap[$pid] ?? ($partyMap[array_key_first($partyMap)] ?? 0);
            if ((int) $row['party_id'] < 1) {
                continue;
            }
            $row['related_id'] = null;
            $row['created_by'] = (int) ($_SESSION['user_id'] ?? 0);
            $docMap[$old] = backup_insert_row('documents', $row);
        }
        foreach ($tables['documents'] ?? [] as $row) {
            $old = (int) $row['id'];
            $rel = (int) ($row['related_id'] ?? 0);
            if ($rel > 0 && isset($docMap[$old], $docMap[$rel])) {
                db_exec('UPDATE documents SET related_id = ? WHERE id = ? AND company_id = ?', 'iii', [$docMap[$rel], $docMap[$old], $cid]);
            }
        }
        foreach ($tables['document_items'] ?? [] as $row) {
            $did = (int) ($row['document_id'] ?? 0);
            if (!isset($docMap[$did])) {
                continue;
            }
            $row['document_id'] = $docMap[$did];
            $sid = (int) ($row['stock_item_id'] ?? 0);
            $row['stock_item_id'] = $sid > 0 ? ($stockMap[$sid] ?? null) : null;
            backup_insert_row('document_items', $row);
        }
        foreach ($tables['stock_moves'] ?? [] as $row) {
            $row['company_id'] = $cid;
            $iid = (int) ($row['item_id'] ?? 0);
            if (!isset($stockMap[$iid])) {
                continue;
            }
            $row['item_id'] = $stockMap[$iid];
            $did = (int) ($row['document_id'] ?? 0);
            $row['document_id'] = $did > 0 ? ($docMap[$did] ?? null) : null;
            backup_insert_row('stock_moves', $row);
        }
        $countMap = [];
        foreach ($tables['stock_counts'] ?? [] as $row) {
            $old = (int) $row['id'];
            $row['company_id'] = $cid;
            $countMap[$old] = backup_insert_row('stock_counts', $row);
        }
        foreach ($tables['stock_count_lines'] ?? [] as $row) {
            $cidOld = (int) ($row['count_id'] ?? 0);
            $iid = (int) ($row['item_id'] ?? 0);
            if (!isset($countMap[$cidOld], $stockMap[$iid])) {
                continue;
            }
            $row['count_id'] = $countMap[$cidOld];
            $row['item_id'] = $stockMap[$iid];
            backup_insert_row('stock_count_lines', $row);
        }
        foreach ($tables['stock_days'] ?? [] as $row) {
            $row['company_id'] = $cid;
            backup_insert_row('stock_days', $row);
        }
        $brand = $data['branding'] ?? null;
        if ($restoreBrand && is_array($brand)) {
            unset($brand['id'], $brand['company_id'], $brand['logo_path'], $brand['logo_blob'], $brand['letterhead_blob']);
            foreach (['name', 'tagline', 'tin', 'vat_no', 'address', 'city', 'phone', 'email', 'website', 'bank_name', 'account_name', 'account_number', 'payment_note', 'invoice_comments', 'currency'] as $col) {
                if (array_key_exists($col, $brand)) {
                    db_exec('UPDATE branding SET `' . $col . '` = ? WHERE company_id = ?', 'si', [(string) $brand[$col], $cid]);
                }
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        return ['ok' => false, 'error' => 'Could not restore that backup.'];
    }
    branding(true);
    return ['ok' => true];
}

function company_reset_scopes(): array
{
    return [
        'documents' => 'Documents (quotations, invoices, receipts, expenses, letters and their lines)',
        'stock' => 'Stock (items, counts, day close, and movements)',
        'clients' => 'Clients and suppliers',
        'mail' => 'Desk email log for this company',
        'activities' => 'Activity log',
        'planner' => 'Planner (notes, tasks, budget, calendar)',
        'pnl' => 'Profit & Loss entries, savings and banking',
    ];
}

function company_reset_has_table(string $table): bool
{
    $db = db();
    $t = $db->real_escape_string($table);
    $res = @$db->query("SHOW TABLES LIKE '$t'");
    return $res && $res->num_rows > 0;
}

function company_reset_ids(string $sql, string $types, array $params): array
{
    return array_map(static fn ($r) => (int) $r['id'], db_all($sql, $types, $params));
}

function company_reset_in(string $sqlPrefix, array $ids): void
{
    if (!$ids) {
        return;
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    db_prepare($sqlPrefix . ' (' . $in . ')', $types, $ids)->execute();
}

function company_reset_training_data(int $cid, array $scopes): array
{
    if ($cid < 1) {
        return ['ok' => false, 'error' => 'Company not found.'];
    }
    $allowed = array_keys(company_reset_scopes());
    $picked = [];
    foreach ($scopes as $key) {
        $key = (string) $key;
        if (in_array($key, $allowed, true)) {
            $picked[] = $key;
        }
    }
    $picked = array_values(array_unique($picked));
    if (!$picked) {
        return ['ok' => false, 'error' => 'Tick at least one item to clear.'];
    }
    if (in_array('clients', $picked, true) && !in_array('documents', $picked, true)) {
        $picked[] = 'documents';
    }
    $db = db();
    $db->begin_transaction();
    try {
        $cleared = [];
        if (in_array('documents', $picked, true)) {
            $docIds = company_reset_ids('SELECT id FROM documents WHERE company_id = ?', 'i', [$cid]);
            if ($docIds && company_reset_has_table('emails')) {
                company_reset_in('DELETE FROM emails WHERE document_id IN', $docIds);
            }
            if ($docIds) {
                company_reset_in('DELETE FROM document_items WHERE document_id IN', $docIds);
            }
            db_exec('DELETE FROM documents WHERE company_id = ?', 'i', [$cid]);
            $cleared[] = 'documents';
        }
        if (in_array('stock', $picked, true) && company_reset_has_table('stock_items')) {
            $countIds = company_reset_has_table('stock_counts')
                ? company_reset_ids('SELECT id FROM stock_counts WHERE company_id = ?', 'i', [$cid])
                : [];
            if ($countIds && company_reset_has_table('stock_count_lines')) {
                company_reset_in('DELETE FROM stock_count_lines WHERE count_id IN', $countIds);
            }
            if (company_reset_has_table('stock_counts')) {
                db_exec('DELETE FROM stock_counts WHERE company_id = ?', 'i', [$cid]);
            }
            if (company_reset_has_table('stock_moves')) {
                db_exec('DELETE FROM stock_moves WHERE company_id = ?', 'i', [$cid]);
            }
            if (company_reset_has_table('stock_days')) {
                db_exec('DELETE FROM stock_days WHERE company_id = ?', 'i', [$cid]);
            }
            db_exec('DELETE FROM stock_items WHERE company_id = ?', 'i', [$cid]);
            $cleared[] = 'stock';
        }
        if (in_array('clients', $picked, true)) {
            db_exec('DELETE FROM parties WHERE company_id = ?', 'i', [$cid]);
            $cleared[] = 'clients';
        }
        if (in_array('mail', $picked, true) && company_reset_has_table('emails')) {
            $users = db_all('SELECT id FROM users WHERE company_id = ?', 'i', [$cid]);
            $uids = array_map(static fn ($u) => (int) $u['id'], $users);
            $co = db_one('SELECT mail_email FROM companies WHERE id = ?', 'i', [$cid]);
            $box = strtolower(trim((string) ($co['mail_email'] ?? '')));
            if ($uids) {
                company_reset_in('DELETE FROM emails WHERE document_id IS NULL AND user_id IN', $uids);
            }
            if ($box !== '') {
                db_exec('DELETE FROM emails WHERE from_email = ? AND document_id IS NULL', 's', [$box]);
            }
            $cleared[] = 'mail';
        }
        if (in_array('activities', $picked, true) && company_reset_has_table('company_activities')) {
            db_exec('DELETE FROM company_activities WHERE company_id = ?', 'i', [$cid]);
            $cleared[] = 'activities';
        }
        if (in_array('planner', $picked, true)) {
            foreach (['planner_notes', 'planner_goals', 'planner_budget_items', 'planner_events'] as $table) {
                if (company_reset_has_table($table)) {
                    db_exec('DELETE FROM `' . $table . '` WHERE company_id = ?', 'i', [$cid]);
                }
            }
            $cleared[] = 'planner';
        }
        if (in_array('pnl', $picked, true)) {
            foreach (['pnl_entries', 'pnl_savings', 'pnl_savings_moves', 'bank_transactions', 'bank_accounts'] as $table) {
                if (company_reset_has_table($table)) {
                    db_exec('DELETE FROM `' . $table . '` WHERE company_id = ?', 'i', [$cid]);
                }
            }
            $cleared[] = 'pnl';
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        return ['ok' => false, 'error' => 'Could not clear that desk. ' . $e->getMessage()];
    }
    if (function_exists('record_company_activity')) {
        record_company_activity('settings', 'Training data cleared', [
            'company_id' => $cid,
            'detail' => implode(', ', $cleared),
            'href' => 'admin_company.php?id=' . $cid,
        ]);
    }
    return ['ok' => true, 'cleared' => $cleared];
}
