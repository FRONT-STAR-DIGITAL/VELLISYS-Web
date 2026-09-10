<?php
declare(strict_types=1);

function folio_http_host(): string
{
    return strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
}

function folio_is_live_host(): bool
{
    $flag = strtolower((string) (getenv('FOLIO_LIVE') ?: ''));
    if (in_array($flag, ['1', 'true', 'yes'], true)) {
        return true;
    }
    $host = folio_http_host();
    return $host === 'www.vellisys.com'
        || $host === 'vellisys.com'
        || str_ends_with($host, '.vellisys.com');
}

function folio_request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $fwd === 'https' || $fwd === 'https,http';
}
