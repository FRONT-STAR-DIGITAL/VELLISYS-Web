<?php
declare(strict_types=1);

/**
 * Super Admin selective restore from a hosting backup upload.
 * Never auto-backs up. Only writes rows for the chosen live company_id.
 */

/** @return array<string,string> */
function restore_scopes(): array
{
    $scopes = function_exists('company_reset_scopes') ? company_reset_scopes() : [];
    $scopes['branding'] = 'Branding text (name, colours stay; logo files are not restored from SQL)';
    return $scopes;
}

/** Tables pulled from a hosting dump / JSON backup for desk restore. */
function restore_desk_tables(): array
{
    return [
        'parties',
        'stock_items',
        'stock_branch_qty',
        'documents',
        'document_items',
        'document_payments',
        'stock_moves',
        'stock_counts',
        'stock_count_lines',
        'stock_days',
        'emails',
        'company_activities',
        'planner_notes',
        'planner_goals',
        'planner_budget_items',
        'planner_events',
        'pnl_entries',
        'pnl_savings',
        'pnl_savings_moves',
        'bank_accounts',
        'bank_transactions',
        'branding',
        'companies',
    ];
}

function restore_upload_dir(): string
{
    $dir = ROOT_PATH . '/uploads/restore';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $deny = $dir . '/.htaccess';
    if (!is_file($deny)) {
        @file_put_contents($deny, "Require all denied\nDeny from all\n");
    }
    return $dir;
}

function restore_new_token(): string
{
    return bin2hex(random_bytes(16));
}

function restore_token_paths(string $token): array
{
    $token = preg_replace('/[^a-f0-9]/', '', strtolower($token)) ?? '';
    $dir = restore_upload_dir();
    return [
        'token' => $token,
        'upload' => $dir . '/' . $token . '.upload',
        'meta' => $dir . '/' . $token . '.meta.json',
        'payload' => $dir . '/' . $token . '.payload.json',
    ];
}

function restore_forget_token(?string $token): void
{
    if ($token === null || $token === '') {
        return;
    }
    $paths = restore_token_paths($token);
    foreach (['upload', 'meta', 'payload'] as $k) {
        if (is_file($paths[$k])) {
            @unlink($paths[$k]);
        }
    }
    if (!empty($_SESSION['restore_token']) && $_SESSION['restore_token'] === $token) {
        unset($_SESSION['restore_token'], $_SESSION['restore_meta']);
    }
}

function restore_housekeep_uploads(int $maxAgeSeconds = 7200): void
{
    $dir = restore_upload_dir();
    $files = glob($dir . '/*') ?: [];
    $cut = time() - max(600, $maxAgeSeconds);
    foreach ($files as $f) {
        $base = basename($f);
        if ($base === '.htaccess') {
            continue;
        }
        if (is_file($f) && (int) filemtime($f) < $cut) {
            @unlink($f);
        }
    }
}

/**
 * @return array{ok:bool,error?:string,token?:string,meta?:array}
 */
function restore_store_upload(array $file): array
{
    restore_housekeep_uploads();
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Choose a backup file to upload (.sql, .sql.gz, .json or .json.gz).'];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Upload failed. Try again.'];
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size < 20) {
        return ['ok' => false, 'error' => 'That file is empty.'];
    }
    if ($size > 250 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Backup is larger than 250 MB. Export a smaller dump or split it.'];
    }
    $name = strtolower((string) ($file['name'] ?? 'backup'));
    if (!preg_match('/\.(sql|sql\.gz|json|json\.gz)$/', $name)) {
        return ['ok' => false, 'error' => 'Use a hosting MySQL dump (.sql / .sql.gz) or a Vellisys JSON backup (.json / .json.gz).'];
    }
    $token = restore_new_token();
    $paths = restore_token_paths($token);
    if (!@move_uploaded_file($tmp, $paths['upload'])) {
        return ['ok' => false, 'error' => 'Could not store the upload on the server.'];
    }
    @chmod($paths['upload'], 0660);
    try {
        $meta = restore_inspect_upload($paths['upload'], $name);
    } catch (Throwable $e) {
        restore_forget_token($token);
        return ['ok' => false, 'error' => 'Could not read that backup: ' . $e->getMessage()];
    }
    if (empty($meta['ok'])) {
        restore_forget_token($token);
        return ['ok' => false, 'error' => (string) ($meta['error'] ?? 'Could not read that backup.')];
    }
    unset($meta['ok']);
    $meta['token'] = $token;
    $meta['original_name'] = basename($name);
    $meta['uploaded_at'] = date('c');
    file_put_contents($paths['meta'], json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $_SESSION['restore_token'] = $token;
    $_SESSION['restore_meta'] = $meta;
    return ['ok' => true, 'token' => $token, 'meta' => $meta];
}

/**
 * @return array{ok:bool,error?:string,format?:string,companies?:list<array{id:int,name:string}>,backup_company_id?:int}
 */
function restore_inspect_upload(string $path, string $originalName = ''): array
{
    $rawHead = (string) file_get_contents($path, false, null, 0, 8);
    $isGz = str_starts_with($rawHead, "\x1f\x8b");
    $lower = strtolower($originalName);
    $looksJson = str_ends_with($lower, '.json') || str_ends_with($lower, '.json.gz');
    if ($looksJson || restore_file_looks_like_json($path, $isGz)) {
        $data = restore_read_json_file($path);
        if ($data === null) {
            return ['ok' => false, 'error' => 'JSON backup could not be decoded.'];
        }
        $cid = (int) ($data['company_id'] ?? 0);
        $name = '';
        if (is_array($data['branding'] ?? null)) {
            $name = trim((string) ($data['branding']['name'] ?? ''));
        }
        if ($name === '' && $cid > 0) {
            $name = 'Company #' . $cid;
        }
        $companies = $cid > 0 ? [['id' => $cid, 'name' => $name !== '' ? $name : ('Company #' . $cid)]] : [];
        if (!$companies) {
            return ['ok' => false, 'error' => 'JSON backup has no company_id.'];
        }
        return [
            'ok' => true,
            'format' => 'json',
            'companies' => $companies,
            'backup_company_id' => $cid,
            'party_count' => is_array($data['tables']['parties'] ?? null) ? count($data['tables']['parties']) : 0,
            'document_count' => is_array($data['tables']['documents'] ?? null) ? count($data['tables']['documents']) : 0,
        ];
    }
    $companies = restore_sql_list_companies($path, $isGz);
    if (!$companies) {
        return ['ok' => false, 'error' => 'No companies table rows found in that SQL dump. Export a full MySQL dump from hosting.'];
    }
    return [
        'ok' => true,
        'format' => 'sql',
        'companies' => $companies,
        'backup_company_id' => (int) ($companies[0]['id'] ?? 0),
    ];
}

function restore_file_looks_like_json(string $path, bool $isGz): bool
{
    $fh = $isGz ? @gzopen($path, 'rb') : @fopen($path, 'rb');
    if (!$fh) {
        return false;
    }
    $chunk = $isGz ? (string) gzread($fh, 64) : (string) fread($fh, 64);
    if ($isGz) {
        gzclose($fh);
    } else {
        fclose($fh);
    }
    $chunk = ltrim($chunk);
    return str_starts_with($chunk, '{') || str_starts_with($chunk, '[');
}

function restore_read_json_file(string $path): ?array
{
    $raw = (string) file_get_contents($path);
    if ($raw === '') {
        return null;
    }
    if (str_starts_with($raw, "\x1f\x8b")) {
        $decoded = @gzdecode($raw);
        if ($decoded === false) {
            return null;
        }
        $raw = $decoded;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * @return list<array{id:int,name:string}>
 */
function restore_sql_list_companies(string $path, ?bool $isGz = null): array
{
    if ($isGz === null) {
        $head = (string) file_get_contents($path, false, null, 0, 2);
        $isGz = str_starts_with($head, "\x1f\x8b");
    }
    $rows = restore_sql_extract_table_rows($path, (bool) $isGz, 'companies', null);
    $out = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            continue;
        }
        $name = trim((string) ($row['name'] ?? ''));
        $out[] = ['id' => $id, 'name' => $name !== '' ? $name : ('Company #' . $id)];
    }
    usort($out, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));
    return $out;
}

/**
 * Build a Vellisys-shaped payload for one company from an uploaded backup.
 *
 * @return array{ok:bool,error?:string,data?:array}
 */
function restore_build_payload(string $token, int $backupCompanyId): array
{
    $paths = restore_token_paths($token);
    if ($paths['token'] === '' || !is_file($paths['upload']) || !is_file($paths['meta'])) {
        return ['ok' => false, 'error' => 'Upload expired. Upload the backup again.'];
    }
    $meta = json_decode((string) file_get_contents($paths['meta']), true) ?: [];
    $format = (string) ($meta['format'] ?? '');
    if (is_file($paths['payload'])) {
        $cached = json_decode((string) file_get_contents($paths['payload']), true);
        if (is_array($cached) && (int) ($cached['company_id'] ?? 0) === $backupCompanyId) {
            return ['ok' => true, 'data' => $cached];
        }
    }
    if ($format === 'json') {
        $data = restore_read_json_file($paths['upload']);
        if ($data === null) {
            return ['ok' => false, 'error' => 'Could not read the JSON backup.'];
        }
        if ((int) ($data['company_id'] ?? 0) !== $backupCompanyId && $backupCompanyId > 0) {
            // Single-company JSON: allow mismatch only when file has one company.
            $data['company_id'] = (int) ($data['company_id'] ?? $backupCompanyId);
        }
        $data['app'] = $data['app'] ?? 'vellisys';
        $data['v'] = (int) ($data['v'] ?? 1);
        file_put_contents($paths['payload'], json_encode($data, JSON_UNESCAPED_UNICODE));
        return ['ok' => true, 'data' => $data];
    }
    $head = (string) file_get_contents($paths['upload'], false, null, 0, 2);
    $isGz = str_starts_with($head, "\x1f\x8b");
    $tables = [];
    $childOnly = ['document_items', 'stock_count_lines', 'document_payments', 'companies'];
    foreach (restore_desk_tables() as $table) {
        if (in_array($table, $childOnly, true)) {
            continue;
        }
        $rows = restore_sql_extract_table_rows($paths['upload'], $isGz, $table, $backupCompanyId);
        if ($rows) {
            $tables[$table] = $rows;
        }
    }
    // Child tables without company_id: filter by parent ids from this company.
    $docIds = array_map(static fn ($r) => (int) ($r['id'] ?? 0), $tables['documents'] ?? []);
    $docIds = array_values(array_filter($docIds));
    if ($docIds) {
        $want = array_fill_keys($docIds, true);
        $allItems = restore_sql_extract_table_rows($paths['upload'], $isGz, 'document_items', null);
        $tables['document_items'] = array_values(array_filter(
            $allItems,
            static fn ($r) => isset($want[(int) ($r['document_id'] ?? 0)])
        ));
        $allPay = restore_sql_extract_table_rows($paths['upload'], $isGz, 'document_payments', null);
        $tables['document_payments'] = array_values(array_filter(
            $allPay,
            static fn ($r) => isset($want[(int) ($r['document_id'] ?? 0)])
                || (int) ($r['company_id'] ?? 0) === $backupCompanyId
        ));
    }
    $countIds = array_map(static fn ($r) => (int) ($r['id'] ?? 0), $tables['stock_counts'] ?? []);
    $countIds = array_values(array_filter($countIds));
    if ($countIds) {
        $wantCounts = array_fill_keys($countIds, true);
        $allLines = restore_sql_extract_table_rows($paths['upload'], $isGz, 'stock_count_lines', null);
        $tables['stock_count_lines'] = array_values(array_filter(
            $allLines,
            static fn ($r) => isset($wantCounts[(int) ($r['count_id'] ?? 0)])
        ));
    }
    $brand = null;
    if (!empty($tables['branding'][0]) && is_array($tables['branding'][0])) {
        $brand = $tables['branding'][0];
        unset($tables['branding']);
    }
    $data = [
        'v' => 1,
        'app' => 'vellisys',
        'company_id' => $backupCompanyId,
        'exported_at' => date('c'),
        'source' => 'sql_upload',
        'tables' => $tables,
        'branding' => $brand,
    ];
    file_put_contents($paths['payload'], json_encode($data, JSON_UNESCAPED_UNICODE));
    return ['ok' => true, 'data' => $data];
}

/**
 * Extract INSERT rows for a table from a mysqldump, optionally filtered by company_id.
 *
 * @return list<array<string,mixed>>
 */
function restore_sql_extract_table_rows(string $path, bool $isGz, string $table, ?int $companyId): array
{
    $table = preg_replace('/[^a-z0-9_]/', '', strtolower($table)) ?? '';
    if ($table === '') {
        return [];
    }
    $fh = $isGz ? @gzopen($path, 'rb') : @fopen($path, 'rb');
    if (!$fh) {
        return [];
    }
    $liveCols = restore_live_columns($table);
    $buf = '';
    $rows = [];
    $needle = 'INSERT INTO `' . $table . '`';
    $needle2 = 'INSERT INTO ' . $table . ' ';
    $read = static function () use ($fh, $isGz, &$buf): ?string {
        while (!str_contains($buf, ';')) {
            $chunk = $isGz ? gzread($fh, 1024 * 256) : fread($fh, 1024 * 256);
            if ($chunk === false || $chunk === '') {
                if ($buf === '') {
                    return null;
                }
                $out = $buf;
                $buf = '';
                return $out;
            }
            $buf .= $chunk;
            if (strlen($buf) > 12 * 1024 * 1024) {
                // Avoid runaway memory on malformed dumps - keep a sliding window.
                $pos = strpos($buf, 'INSERT INTO');
                if ($pos === false) {
                    $buf = substr($buf, -64);
                } elseif ($pos > 0) {
                    $buf = substr($buf, $pos);
                }
            }
        }
        $semi = restore_sql_find_statement_end($buf);
        if ($semi < 0) {
            return null;
        }
        $stmt = substr($buf, 0, $semi + 1);
        $buf = substr($buf, $semi + 1);
        return $stmt;
    };
    while (($stmt = $read()) !== null) {
        $trim = ltrim($stmt);
        if (!str_starts_with($trim, 'INSERT INTO')) {
            continue;
        }
        if (!str_contains($trim, $needle) && !preg_match('/^INSERT INTO\s+`' . preg_quote($table, '/') . '`/i', $trim)
            && !preg_match('/^INSERT INTO\s+' . preg_quote($table, '/') . '\s/i', $trim)) {
            continue;
        }
        foreach (restore_sql_parse_insert($trim, $liveCols) as $row) {
            if ($companyId !== null && $companyId > 0) {
                if (array_key_exists('company_id', $row) && (int) $row['company_id'] !== $companyId) {
                    continue;
                }
            }
            $rows[] = $row;
        }
    }
    if ($isGz) {
        gzclose($fh);
    } else {
        fclose($fh);
    }
    return $rows;
}

/** @return list<string> */
function restore_live_columns(string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    $safe = preg_replace('/[^a-z0-9_]/', '', strtolower($table)) ?? '';
    if ($safe === '' || !company_reset_has_table($safe)) {
        return $cache[$table] = [];
    }
    try {
        $db = db();
        $res = $db->query('SHOW COLUMNS FROM `' . $safe . '`');
        $cols = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cols[] = (string) $row['Field'];
            }
        }
        return $cache[$table] = $cols;
    } catch (Throwable $e) {
        return $cache[$table] = [];
    }
}

function restore_sql_find_statement_end(string $buf): int
{
    $len = strlen($buf);
    $inStr = false;
    $esc = false;
    $quote = '';
    for ($i = 0; $i < $len; $i++) {
        $ch = $buf[$i];
        if ($inStr) {
            if ($esc) {
                $esc = false;
                continue;
            }
            if ($ch === '\\') {
                $esc = true;
                continue;
            }
            if ($ch === $quote) {
                // MySQL dumps escape quotes by doubling.
                if ($i + 1 < $len && $buf[$i + 1] === $quote) {
                    $i++;
                    continue;
                }
                $inStr = false;
                $quote = '';
            }
            continue;
        }
        if ($ch === '\'' || $ch === '"' || $ch === '`') {
            $inStr = true;
            $quote = $ch;
            continue;
        }
        if ($ch === ';') {
            return $i;
        }
    }
    return -1;
}

/**
 * @param list<string> $fallbackCols
 * @return list<array<string,mixed>>
 */
function restore_sql_parse_insert(string $stmt, array $fallbackCols): array
{
    if (!preg_match('/^INSERT INTO\s+`?([a-z0-9_]+)`?\s*(?:\((.*?)\))?\s*VALUES\s*(.+);\s*$/is', trim($stmt), $m)) {
        return [];
    }
    $cols = $fallbackCols;
    if (trim((string) ($m[2] ?? '')) !== '') {
        preg_match_all('/`([^`]+)`|([a-z0-9_]+)/i', (string) $m[2], $cm, PREG_SET_ORDER);
        $parsed = [];
        foreach ($cm as $c) {
            $parsed[] = strtolower((string) ($c[1] !== '' ? $c[1] : $c[2]));
        }
        if ($parsed) {
            $cols = $parsed;
        }
    }
    if (!$cols) {
        return [];
    }
    $valuesSql = trim((string) $m[3]);
    $tuples = restore_sql_split_tuples($valuesSql);
    $out = [];
    foreach ($tuples as $tuple) {
        $vals = restore_sql_split_values($tuple);
        if (count($vals) < 1) {
            continue;
        }
        $row = [];
        foreach ($cols as $i => $col) {
            $row[$col] = $vals[$i] ?? null;
        }
        $out[] = $row;
    }
    return $out;
}

/** @return list<string> */
function restore_sql_split_tuples(string $valuesSql): array
{
    $valuesSql = trim($valuesSql);
    $out = [];
    $len = strlen($valuesSql);
    $depth = 0;
    $start = -1;
    $inStr = false;
    $esc = false;
    $quote = '';
    for ($i = 0; $i < $len; $i++) {
        $ch = $valuesSql[$i];
        if ($inStr) {
            if ($esc) {
                $esc = false;
                continue;
            }
            if ($ch === '\\') {
                $esc = true;
                continue;
            }
            if ($ch === $quote) {
                if ($i + 1 < $len && $valuesSql[$i + 1] === $quote) {
                    $i++;
                    continue;
                }
                $inStr = false;
                $quote = '';
            }
            continue;
        }
        if ($ch === '\'' || $ch === '"') {
            $inStr = true;
            $quote = $ch;
            continue;
        }
        if ($ch === '(') {
            if ($depth === 0) {
                $start = $i + 1;
            }
            $depth++;
            continue;
        }
        if ($ch === ')') {
            $depth--;
            if ($depth === 0 && $start >= 0) {
                $out[] = substr($valuesSql, $start, $i - $start);
                $start = -1;
            }
        }
    }
    return $out;
}

/** @return list<mixed> */
function restore_sql_split_values(string $tuple): array
{
    $out = [];
    $len = strlen($tuple);
    $start = 0;
    $inStr = false;
    $esc = false;
    $quote = '';
    for ($i = 0; $i <= $len; $i++) {
        $ch = $i < $len ? $tuple[$i] : ',';
        if ($inStr) {
            if ($esc) {
                $esc = false;
                continue;
            }
            if ($ch === '\\') {
                $esc = true;
                continue;
            }
            if ($ch === $quote) {
                if ($i + 1 < $len && $tuple[$i + 1] === $quote) {
                    $i++;
                    continue;
                }
                $inStr = false;
                $quote = '';
            }
            continue;
        }
        if ($ch === '\'' || $ch === '"') {
            $inStr = true;
            $quote = $ch;
            continue;
        }
        if ($ch === ',') {
            $raw = trim(substr($tuple, $start, $i - $start));
            $out[] = restore_sql_decode_value($raw);
            $start = $i + 1;
        }
    }
    return $out;
}

function restore_sql_decode_value(string $raw): mixed
{
    if ($raw === '' || strcasecmp($raw, 'NULL') === 0) {
        return null;
    }
    if ($raw[0] === '\'' || $raw[0] === '"') {
        $q = $raw[0];
        $inner = substr($raw, 1, -1);
        $inner = str_replace(['\\' . $q, $q . $q, '\\n', '\\r', '\\t', '\\\\', '\\0', '\\Z'], [$q, $q, "\n", "\r", "\t", '\\', "\0", "\x1a"], $inner);
        return $inner;
    }
    if (is_numeric($raw)) {
        return str_contains($raw, '.') ? (float) $raw : (int) $raw;
    }
    return $raw;
}

function restore_insert_row(string $table, array $row, array $skip = ['id']): int
{
    $safe = preg_replace('/[^a-z0-9_]/', '', strtolower($table)) ?? '';
    if ($safe === '' || !company_reset_has_table($safe)) {
        return 0;
    }
    $live = restore_live_columns($safe);
    if (!$live) {
        return 0;
    }
    $cols = [];
    $vals = [];
    $types = '';
    foreach ($live as $col) {
        if (in_array($col, $skip, true)) {
            continue;
        }
        if (!array_key_exists($col, $row)) {
            continue;
        }
        $v = $row[$col];
        $cols[] = '`' . $col . '`';
        $vals[] = $v;
        if (is_int($v)) {
            $types .= 'i';
        } elseif (is_float($v)) {
            $types .= 'd';
        } elseif ($v === null) {
            $types .= 's';
        } else {
            $types .= 's';
        }
    }
    if (!$cols) {
        return 0;
    }
    $sql = 'INSERT INTO `' . $safe . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    return db_exec($sql, $types, $vals);
}

/**
 * Restore selected scopes into one live company from a payload.
 * Only deletes/inserts rows for that company_id - other desks are untouched.
 *
 * @param list<string> $scopes
 * @return array{ok:bool,error?:string,restored?:list<string>,counts?:array<string,int>}
 */
function restore_apply_to_company(array $data, int $liveCompanyId, array $scopes, array $opts = []): array
{
    if ($liveCompanyId < 1) {
        return ['ok' => false, 'error' => 'Pick the live company to restore into.'];
    }
    $live = db_one('SELECT id, name FROM companies WHERE id = ?', 'i', [$liveCompanyId]);
    if (!$live) {
        return ['ok' => false, 'error' => 'That live company was not found.'];
    }
    $tables = $data['tables'] ?? null;
    if (!is_array($tables)) {
        return ['ok' => false, 'error' => 'Backup is missing desk tables.'];
    }
    $allowed = array_keys(restore_scopes());
    $picked = [];
    foreach ($scopes as $s) {
        $s = (string) $s;
        if (in_array($s, $allowed, true)) {
            $picked[] = $s;
        }
    }
    $picked = array_values(array_unique($picked));
    if (!$picked) {
        return ['ok' => false, 'error' => 'Tick at least one item to restore.'];
    }
    if (in_array('clients', $picked, true) && !in_array('documents', $picked, true)) {
        $picked[] = 'documents';
    }
    $partyFilter = (int) ($opts['party_id'] ?? 0);
    $replace = ($opts['mode'] ?? 'replace') !== 'merge';
    $userId = (int) ($opts['user_id'] ?? ($_SESSION['user_id'] ?? 0));

    // Optional: only one client from the backup.
    if ($partyFilter > 0) {
        $parties = array_values(array_filter(
            (array) ($tables['parties'] ?? []),
            static fn ($r) => is_array($r) && (int) ($r['id'] ?? 0) === $partyFilter
        ));
        if (!$parties) {
            return ['ok' => false, 'error' => 'That client was not found in the backup.'];
        }
        $tables['parties'] = $parties;
        $docIdsKeep = [];
        $docs = [];
        foreach ((array) ($tables['documents'] ?? []) as $doc) {
            if (!is_array($doc)) {
                continue;
            }
            if ((int) ($doc['party_id'] ?? 0) === $partyFilter) {
                $docs[] = $doc;
                $docIdsKeep[] = (int) ($doc['id'] ?? 0);
            }
        }
        $tables['documents'] = $docs;
        $tables['document_items'] = array_values(array_filter(
            (array) ($tables['document_items'] ?? []),
            static fn ($r) => is_array($r) && in_array((int) ($r['document_id'] ?? 0), $docIdsKeep, true)
        ));
        if (isset($tables['document_payments'])) {
            $tables['document_payments'] = array_values(array_filter(
                (array) $tables['document_payments'],
                static fn ($r) => is_array($r) && in_array((int) ($r['document_id'] ?? 0), $docIdsKeep, true)
            ));
        }
        // Client-only restores should not wipe whole-company stock / planner / pnl.
        $picked = array_values(array_intersect($picked, ['clients', 'documents', 'mail']));
        if (!$picked) {
            $picked = ['clients', 'documents'];
        }
    }

    $db = db();
    $db->begin_transaction();
    try {
        $restored = [];
        $counts = [];

        if ($replace) {
            $clearScopes = array_values(array_intersect($picked, array_keys(company_reset_scopes())));
            if ($partyFilter > 0) {
                // Only clear this client's documents on the live desk.
                $livePartyIds = [];
                $wantName = strtolower(trim((string) ($tables['parties'][0]['name'] ?? '')));
                if ($wantName !== '') {
                    foreach (db_all('SELECT id, name FROM parties WHERE company_id = ?', 'i', [$liveCompanyId]) as $p) {
                        if (strtolower(trim((string) ($p['name'] ?? ''))) === $wantName) {
                            $livePartyIds[] = (int) $p['id'];
                        }
                    }
                }
                if ($livePartyIds && in_array('documents', $picked, true)) {
                    $in = implode(',', array_fill(0, count($livePartyIds), '?'));
                    $types = str_repeat('i', count($livePartyIds));
                    $docIds = array_map(
                        static fn ($r) => (int) $r['id'],
                        db_all('SELECT id FROM documents WHERE company_id = ? AND party_id IN (' . $in . ')', 'i' . $types, array_merge([$liveCompanyId], $livePartyIds))
                    );
                    if ($docIds) {
                        if (company_reset_has_table('document_payments')) {
                            company_reset_in('DELETE FROM document_payments WHERE document_id IN', $docIds);
                        }
                        if (company_reset_has_table('emails')) {
                            company_reset_in('DELETE FROM emails WHERE document_id IN', $docIds);
                        }
                        company_reset_in('DELETE FROM document_items WHERE document_id IN', $docIds);
                        company_reset_in('DELETE FROM documents WHERE id IN', $docIds);
                    }
                    if (in_array('clients', $picked, true)) {
                        company_reset_in('DELETE FROM parties WHERE id IN', $livePartyIds);
                    }
                }
            } elseif ($clearScopes) {
                $wipe = company_reset_training_data($liveCompanyId, $clearScopes);
                if (empty($wipe['ok'])) {
                    throw new RuntimeException((string) ($wipe['error'] ?? 'Could not clear the live desk before restore.'));
                }
            }
        }

        $partyMap = [];
        $stockMap = [];
        $docMap = [];

        if (in_array('clients', $picked, true) || in_array('documents', $picked, true)) {
            foreach ((array) ($tables['parties'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $old = (int) ($row['id'] ?? 0);
                $row['company_id'] = $liveCompanyId;
                $newId = restore_insert_row('parties', $row);
                if ($old > 0 && $newId > 0) {
                    $partyMap[$old] = $newId;
                }
            }
            $counts['parties'] = count($partyMap);
            if ($partyMap) {
                $restored[] = 'clients';
            }
        }

        if (in_array('stock', $picked, true)) {
            foreach ((array) ($tables['stock_items'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $old = (int) ($row['id'] ?? 0);
                $row['company_id'] = $liveCompanyId;
                $newId = restore_insert_row('stock_items', $row);
                if ($old > 0 && $newId > 0) {
                    $stockMap[$old] = $newId;
                }
            }
            foreach ((array) ($tables['stock_branch_qty'] ?? []) as $row) {
                if (!is_array($row) || !company_reset_has_table('stock_branch_qty')) {
                    continue;
                }
                $iid = (int) ($row['item_id'] ?? 0);
                if (!isset($stockMap[$iid])) {
                    continue;
                }
                $row['company_id'] = $liveCompanyId;
                $row['item_id'] = $stockMap[$iid];
                restore_insert_row('stock_branch_qty', $row, ['id']);
            }
            $countMap = [];
            foreach ((array) ($tables['stock_counts'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $old = (int) ($row['id'] ?? 0);
                $row['company_id'] = $liveCompanyId;
                $newId = restore_insert_row('stock_counts', $row);
                if ($old > 0 && $newId > 0) {
                    $countMap[$old] = $newId;
                }
            }
            foreach ((array) ($tables['stock_count_lines'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $cidOld = (int) ($row['count_id'] ?? 0);
                $iid = (int) ($row['item_id'] ?? 0);
                if (!isset($countMap[$cidOld], $stockMap[$iid])) {
                    continue;
                }
                $row['count_id'] = $countMap[$cidOld];
                $row['item_id'] = $stockMap[$iid];
                restore_insert_row('stock_count_lines', $row);
            }
            foreach ((array) ($tables['stock_days'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $row['company_id'] = $liveCompanyId;
                restore_insert_row('stock_days', $row);
            }
            $counts['stock_items'] = count($stockMap);
            $restored[] = 'stock';
        }

        if (in_array('documents', $picked, true)) {
            // Documents need parties. If clients were not selected, insert only parties
            // referenced by restored docs without wiping the rest of the live client list.
            if (!$partyMap && empty(array_intersect(['clients'], $picked)) && $partyFilter < 1) {
                $needed = [];
                foreach ((array) ($tables['documents'] ?? []) as $doc) {
                    if (is_array($doc)) {
                        $needed[(int) ($doc['party_id'] ?? 0)] = true;
                    }
                }
                foreach ((array) ($tables['parties'] ?? []) as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $old = (int) ($row['id'] ?? 0);
                    if ($old < 1 || empty($needed[$old])) {
                        continue;
                    }
                    $name = trim((string) ($row['name'] ?? ''));
                    $existing = $name !== ''
                        ? db_one('SELECT id FROM parties WHERE company_id = ? AND name = ? LIMIT 1', 'is', [$liveCompanyId, $name])
                        : null;
                    if ($existing) {
                        $partyMap[$old] = (int) $existing['id'];
                        continue;
                    }
                    $row['company_id'] = $liveCompanyId;
                    $newId = restore_insert_row('parties', $row);
                    if ($newId > 0) {
                        $partyMap[$old] = $newId;
                    }
                }
            }
            // Stock map may be empty when stock scope was not picked - still map items if present.
            if (!$stockMap && !empty($tables['stock_items'])) {
                foreach ((array) $tables['stock_items'] as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $old = (int) ($row['id'] ?? 0);
                    // Prefer matching existing live stock by sku/name rather than inserting.
                    $sku = trim((string) ($row['sku'] ?? ''));
                    $name = trim((string) ($row['name'] ?? ''));
                    $found = null;
                    if ($sku !== '') {
                        $found = db_one('SELECT id FROM stock_items WHERE company_id = ? AND sku = ? LIMIT 1', 'is', [$liveCompanyId, $sku]);
                    }
                    if (!$found && $name !== '') {
                        $found = db_one('SELECT id FROM stock_items WHERE company_id = ? AND name = ? LIMIT 1', 'is', [$liveCompanyId, $name]);
                    }
                    if ($found && $old > 0) {
                        $stockMap[$old] = (int) $found['id'];
                    }
                }
            }
            if (!$partyMap && !empty($tables['parties'])) {
                foreach ((array) $tables['parties'] as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $old = (int) ($row['id'] ?? 0);
                    $row['company_id'] = $liveCompanyId;
                    $newId = restore_insert_row('parties', $row);
                    if ($old > 0 && $newId > 0) {
                        $partyMap[$old] = $newId;
                    }
                }
            }
            foreach ((array) ($tables['documents'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $old = (int) ($row['id'] ?? 0);
                $row['company_id'] = $liveCompanyId;
                $pid = (int) ($row['party_id'] ?? 0);
                $row['party_id'] = $partyMap[$pid] ?? 0;
                if ((int) $row['party_id'] < 1) {
                    continue;
                }
                $row['related_id'] = null;
                if (array_key_exists('created_by', $row)) {
                    $row['created_by'] = $userId > 0 ? $userId : ($row['created_by'] ?? null);
                }
                $newId = restore_insert_row('documents', $row);
                if ($old > 0 && $newId > 0) {
                    $docMap[$old] = $newId;
                }
            }
            foreach ((array) ($tables['documents'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $old = (int) ($row['id'] ?? 0);
                $rel = (int) ($row['related_id'] ?? 0);
                if ($rel > 0 && isset($docMap[$old], $docMap[$rel])) {
                    db_exec('UPDATE documents SET related_id = ? WHERE id = ? AND company_id = ?', 'iii', [$docMap[$rel], $docMap[$old], $liveCompanyId]);
                }
            }
            $itemCount = 0;
            foreach ((array) ($tables['document_items'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $did = (int) ($row['document_id'] ?? 0);
                if (!isset($docMap[$did])) {
                    continue;
                }
                $row['document_id'] = $docMap[$did];
                $sid = (int) ($row['stock_item_id'] ?? 0);
                $row['stock_item_id'] = $sid > 0 ? ($stockMap[$sid] ?? 0) : 0;
                restore_insert_row('document_items', $row);
                $itemCount++;
            }
            foreach ((array) ($tables['document_payments'] ?? []) as $row) {
                if (!is_array($row) || !company_reset_has_table('document_payments')) {
                    continue;
                }
                $did = (int) ($row['document_id'] ?? 0);
                if (!isset($docMap[$did])) {
                    continue;
                }
                $row['document_id'] = $docMap[$did];
                if (array_key_exists('company_id', $row)) {
                    $row['company_id'] = $liveCompanyId;
                }
                restore_insert_row('document_payments', $row);
            }
            $counts['documents'] = count($docMap);
            $counts['document_items'] = $itemCount;
            $restored[] = 'documents';
        }

        // Stock moves after documents so sale links remap correctly.
        if (in_array('stock', $picked, true) && !empty($tables['stock_moves']) && $stockMap) {
            $moveN = 0;
            foreach ((array) $tables['stock_moves'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $iid = (int) ($row['item_id'] ?? 0);
                if (!isset($stockMap[$iid])) {
                    continue;
                }
                $row['company_id'] = $liveCompanyId;
                $row['item_id'] = $stockMap[$iid];
                $did = (int) ($row['document_id'] ?? 0);
                $row['document_id'] = $did > 0 ? ($docMap[$did] ?? null) : null;
                restore_insert_row('stock_moves', $row);
                $moveN++;
            }
            $counts['stock_moves'] = $moveN;
        }

        if (in_array('mail', $picked, true) && company_reset_has_table('emails')) {
            $n = 0;
            foreach ((array) ($tables['emails'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $did = (int) ($row['document_id'] ?? 0);
                if ($did > 0) {
                    if (!isset($docMap[$did])) {
                        continue;
                    }
                    $row['document_id'] = $docMap[$did];
                }
                if (array_key_exists('company_id', $row)) {
                    $row['company_id'] = $liveCompanyId;
                }
                restore_insert_row('emails', $row);
                $n++;
            }
            $counts['emails'] = $n;
            if ($n) {
                $restored[] = 'mail';
            }
        }

        if (in_array('activities', $picked, true) && company_reset_has_table('company_activities')) {
            $n = 0;
            foreach ((array) ($tables['company_activities'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $row['company_id'] = $liveCompanyId;
                restore_insert_row('company_activities', $row);
                $n++;
            }
            $counts['activities'] = $n;
            if ($n) {
                $restored[] = 'activities';
            }
        }

        if (in_array('planner', $picked, true)) {
            $n = 0;
            foreach (['planner_notes', 'planner_goals', 'planner_budget_items', 'planner_events'] as $table) {
                foreach ((array) ($tables[$table] ?? []) as $row) {
                    if (!is_array($row) || !company_reset_has_table($table)) {
                        continue;
                    }
                    $row['company_id'] = $liveCompanyId;
                    if (isset($row['party_id'])) {
                        $pid = (int) $row['party_id'];
                        $row['party_id'] = $pid > 0 ? ($partyMap[$pid] ?? null) : null;
                    }
                    restore_insert_row($table, $row);
                    $n++;
                }
            }
            $counts['planner'] = $n;
            if ($n) {
                $restored[] = 'planner';
            }
        }

        if (in_array('pnl', $picked, true)) {
            $n = 0;
            $bankMap = [];
            foreach ((array) ($tables['bank_accounts'] ?? []) as $row) {
                if (!is_array($row) || !company_reset_has_table('bank_accounts')) {
                    continue;
                }
                $old = (int) ($row['id'] ?? 0);
                $row['company_id'] = $liveCompanyId;
                $newId = restore_insert_row('bank_accounts', $row);
                if ($old > 0 && $newId > 0) {
                    $bankMap[$old] = $newId;
                }
                $n++;
            }
            foreach ((array) ($tables['bank_transactions'] ?? []) as $row) {
                if (!is_array($row) || !company_reset_has_table('bank_transactions')) {
                    continue;
                }
                $row['company_id'] = $liveCompanyId;
                if (isset($row['account_id'])) {
                    $aid = (int) $row['account_id'];
                    $row['account_id'] = $bankMap[$aid] ?? $aid;
                }
                restore_insert_row('bank_transactions', $row);
                $n++;
            }
            foreach (['pnl_entries', 'pnl_savings', 'pnl_savings_moves'] as $table) {
                foreach ((array) ($tables[$table] ?? []) as $row) {
                    if (!is_array($row) || !company_reset_has_table($table)) {
                        continue;
                    }
                    $row['company_id'] = $liveCompanyId;
                    restore_insert_row($table, $row);
                    $n++;
                }
            }
            $counts['pnl'] = $n;
            if ($n) {
                $restored[] = 'pnl';
            }
        }

        if (in_array('branding', $picked, true) && is_array($data['branding'] ?? null)) {
            $brand = $data['branding'];
            unset($brand['id'], $brand['company_id'], $brand['logo_path'], $brand['logo_blob'], $brand['letterhead_blob'], $brand['signature_path']);
            foreach (['name', 'tagline', 'tin', 'vat_no', 'address', 'city', 'phone', 'email', 'website', 'bank_name', 'account_name', 'account_number', 'payment_note', 'invoice_comments', 'receipt_comments', 'currency'] as $col) {
                if (array_key_exists($col, $brand) && db_has_column(db(), 'branding', $col)) {
                    db_exec('UPDATE branding SET `' . $col . '` = ? WHERE company_id = ?', 'si', [(string) $brand[$col], $liveCompanyId]);
                }
            }
            $restored[] = 'branding';
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        return ['ok' => false, 'error' => 'Restore failed: ' . $e->getMessage()];
    }

    if (function_exists('branding')) {
        try {
            branding(true);
        } catch (Throwable $e) {
        }
    }
    if (function_exists('record_company_activity')) {
        record_company_activity('settings', 'Desk data restored from backup', [
            'company_id' => $liveCompanyId,
            'detail' => implode(', ', array_unique($restored)),
            'href' => 'admin_company.php?id=' . $liveCompanyId,
        ]);
    }
    return [
        'ok' => true,
        'restored' => array_values(array_unique($restored)),
        'counts' => $counts,
    ];
}

/**
 * @return list<array{id:int,name:string}>
 */
function restore_payload_parties(array $data): array
{
    $out = [];
    foreach ((array) ($data['tables']['parties'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            continue;
        }
        $name = trim((string) ($row['name'] ?? ''));
        $out[] = ['id' => $id, 'name' => $name !== '' ? $name : ('Client #' . $id)];
    }
    usort($out, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));
    return $out;
}
