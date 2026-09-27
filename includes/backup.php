<?php
declare(strict_types=1);

/**
 * In-app desk backup removed — hosting platform backups are sufficient.
 * Stubs remain so any stray call sites fail soft without fatal errors.
 */

function company_backup_dir(?int $cid = null): string
{
    $cid = $cid ?? (function_exists('current_company_id') ? current_company_id() : 0);
    return ROOT_PATH . '/uploads/backups/' . (int) $cid;
}

function company_backup_tables(): array
{
    return [];
}

function company_backup_payload(?int $cid = null): array
{
    return [];
}

function company_backup_write(bool $force = false, ?int $cid = null): ?string
{
    return null;
}

function company_backup_maybe(bool $force = false): void
{
    // No-op: backups are handled by the host, not the app.
}

function company_backup_list(?int $cid = null): array
{
    return [];
}

function company_backup_send(string $file, ?int $cid = null): void
{
    flash('In-app backup is no longer available.', 'err');
    redirect($cid ? ('admin_company.php?id=' . (int) $cid) : 'settings.php');
}

function company_backup_read_file(string $tmp): ?array
{
    return null;
}

function backup_insert_row(string $table, array $row, array $skip = ['id']): int
{
    return 0;
}

function company_backup_restore_payload(array $data, ?int $cid = null, array $opts = []): array
{
    return ['ok' => false, 'error' => 'In-app backup restore is no longer available.'];
}

/** Scopes a super admin can clear after a training run (keeps logins / branding / term). */
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
    try {
        $db = db();
        $t = $db->real_escape_string($table);
        $res = @$db->query("SHOW TABLES LIKE '$t'");
        return $res && $res->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
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

/**
 * Clear selected practice data for a company desk.
 * Keeps users, branding, mailbox settings and paid term.
 *
 * @param list<string> $scopes
 * @return array{ok:bool,error?:string,cleared?:list<string>}
 */
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
            if ($docIds && company_reset_has_table('document_items')) {
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
