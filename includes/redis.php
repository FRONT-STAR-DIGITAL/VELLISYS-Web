<?php
declare(strict_types=1);

/**
 * Optional Redis for short-lived app cache (folio_remember, rate limits).
 * Sessions stay in MySQL — durable across redeploys.
 * If Redis is down or not configured, callers fall back to files / no-op.
 */

function folio_redis_enabled(): bool
{
    $host = trim((string) (getenv('FOLIO_REDIS_HOST') ?: ''));
    return $host !== '' && extension_loaded('redis');
}

function folio_redis(): ?Redis
{
    static $client = false;
    if ($client instanceof Redis) {
        return $client;
    }
    if ($client === null) {
        return null;
    }
    if (!folio_redis_enabled()) {
        $client = null;
        return null;
    }
    $host = trim((string) getenv('FOLIO_REDIS_HOST'));
    $port = (int) (getenv('FOLIO_REDIS_PORT') ?: 6379);
    $pass = (string) (getenv('FOLIO_REDIS_PASS') ?: '');
    $db = (int) (getenv('FOLIO_REDIS_DB') ?: 0);
    try {
        $r = new Redis();
        $r->connect($host, $port > 0 ? $port : 6379, 1.5);
        if ($pass !== '') {
            $r->auth($pass);
        }
        if ($db > 0) {
            $r->select($db);
        }
        $client = $r;
        return $client;
    } catch (Throwable $e) {
        $client = null;
        return null;
    }
}

function folio_redis_key(string $key): string
{
    return 'vellisys:' . ltrim($key, ':');
}

function folio_redis_get(string $key): mixed
{
    $r = folio_redis();
    if (!$r) {
        return null;
    }
    try {
        $raw = $r->get(folio_redis_key($key));
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $val = @unserialize($raw, ['allowed_classes' => false]);
        if ($val === false && $raw !== 'b:0;') {
            return null;
        }
        return $val;
    } catch (Throwable $e) {
        return null;
    }
}

function folio_redis_set(string $key, mixed $value, int $ttl = 90): bool
{
    $r = folio_redis();
    if (!$r) {
        return false;
    }
    try {
        $payload = serialize($value);
        $full = folio_redis_key($key);
        if ($ttl > 0) {
            return (bool) $r->setex($full, $ttl, $payload);
        }
        return (bool) $r->set($full, $payload);
    } catch (Throwable $e) {
        return false;
    }
}

function folio_redis_del(string $key): void
{
    $r = folio_redis();
    if (!$r) {
        return;
    }
    try {
        $r->del(folio_redis_key($key));
    } catch (Throwable $e) {
        // ignore
    }
}

/** Delete all Vellisys cache keys (SCAN — safe). */
function folio_redis_flush_prefix(): void
{
    $r = folio_redis();
    if (!$r) {
        return;
    }
    try {
        $it = null;
        while (true) {
            $keys = $r->scan($it, 'vellisys:*', 200);
            if ($keys === false) {
                break;
            }
            if (is_array($keys) && $keys) {
                $r->del(...$keys);
            }
            if ($it === 0 || $it === '0') {
                break;
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
}
