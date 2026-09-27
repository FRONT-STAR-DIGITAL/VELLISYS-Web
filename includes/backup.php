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
