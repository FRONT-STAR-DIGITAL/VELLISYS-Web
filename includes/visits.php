<?php
declare(strict_types=1);

function visit_country_names(): array
{
    return [
        'UG' => 'Uganda', 'KE' => 'Kenya', 'TZ' => 'Tanzania', 'RW' => 'Rwanda',
        'BI' => 'Burundi', 'SS' => 'South Sudan', 'CD' => 'DR Congo', 'NG' => 'Nigeria',
        'GH' => 'Ghana', 'ZA' => 'South Africa', 'US' => 'United States', 'GB' => 'United Kingdom',
        'AE' => 'United Arab Emirates', 'IN' => 'India', 'DE' => 'Germany', 'FR' => 'France',
        'NL' => 'Netherlands', 'CA' => 'Canada', 'AU' => 'Australia', 'IE' => 'Ireland',
        'CN' => 'China', 'JP' => 'Japan', 'BR' => 'Brazil', 'EG' => 'Egypt',
        'ET' => 'Ethiopia', 'ZM' => 'Zambia', 'MW' => 'Malawi', 'ZW' => 'Zimbabwe',
        'SO' => 'Somalia', 'SD' => 'Sudan',
    ];
}

function visit_tz_country(string $tz): string
{
    $map = [
        'Africa/Kampala' => 'UG', 'Africa/Nairobi' => 'KE', 'Africa/Dar_es_Salaam' => 'TZ',
        'Africa/Kigali' => 'RW', 'Africa/Bujumbura' => 'BI', 'Africa/Juba' => 'SS',
        'Africa/Kinshasa' => 'CD', 'Africa/Lubumbashi' => 'CD', 'Africa/Lagos' => 'NG',
        'Africa/Accra' => 'GH', 'Africa/Johannesburg' => 'ZA', 'Africa/Cairo' => 'EG',
        'Africa/Addis_Ababa' => 'ET', 'Africa/Lusaka' => 'ZM', 'Africa/Blantyre' => 'MW',
        'Africa/Harare' => 'ZW', 'Africa/Mogadishu' => 'SO', 'Africa/Khartoum' => 'SD',
        'America/New_York' => 'US', 'America/Chicago' => 'US', 'America/Los_Angeles' => 'US',
        'Europe/London' => 'GB', 'Europe/Dublin' => 'IE', 'Europe/Berlin' => 'DE',
        'Europe/Paris' => 'FR', 'Europe/Amsterdam' => 'NL', 'Asia/Dubai' => 'AE',
        'Asia/Kolkata' => 'IN', 'Asia/Shanghai' => 'CN', 'Asia/Tokyo' => 'JP',
        'Australia/Sydney' => 'AU', 'America/Toronto' => 'CA',
    ];
    return $map[$tz] ?? '';
}

function visit_detect_country(): array
{
    $code = strtoupper(trim((string) (
        $_SERVER['HTTP_CF_IPCOUNTRY']
        ?? $_SERVER['HTTP_X_APPENGINE_COUNTRY']
        ?? $_SERVER['GEOIP_COUNTRY_CODE']
        ?? $_SERVER['HTTP_X_COUNTRY_CODE']
        ?? ''
    )));
    if ($code === 'XX' || $code === 'T1') {
        $code = '';
    }
    if ($code === '') {
        $code = visit_tz_country((string) ($_COOKIE['vellisys_tz'] ?? ''));
    }
    $code = preg_match('/^[A-Z]{2}$/', $code) ? $code : '';
    $names = visit_country_names();
    $name = $names[$code] ?? ($code !== '' ? $code : 'Unknown');
    return ['code' => $code, 'name' => $name];
}

function visit_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        $raw = trim((string) ($_SERVER[$key] ?? ''));
        if ($raw === '') {
            continue;
        }
        $ip = trim(explode(',', $raw)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return '';
}

function visit_page_kind(string $script): string
{
    return match ($script) {
        'index.php', '' => 'landing',
        'checkout.php', 'pesapal_callback.php' => 'checkout',
        'login.php', 'forgot.php' => 'login',
        'register.php', 'onboard.php', 'demo.php' => 'onboard',
        'ask.php', 'quote.php', 'privacy.php', 'terms.php' => 'landing',
        default => str_starts_with($script, 'admin_') ? 'admin' : 'desk',
    };
}

function record_site_visit(?string $script = null): void
{
    static $done = false;
    if ($done || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }
    $script = $script ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (in_array($script, ['document_pdf.php', 'export.php', 'sw.js', 'manifest.php'], true)) {
        return;
    }
    if (!function_exists('db') || !function_exists('db_has_column')) {
        return;
    }
    try {
        if (!db_has_column(db(), 'site_visits', 'page_kind')) {
            return;
        }
    } catch (Throwable $e) {
        return;
    }
    $ip = visit_client_ip();
    $hash = $ip !== '' ? hash('sha1', $ip . '|vellisys-visit') : '';
    $path = mb_substr((string) ($_SERVER['REQUEST_URI'] ?? '/' . $script), 0, 190);
    $kind = visit_page_kind($script);
    $isApp = !empty($_COOKIE['vellisys_app']);
    if ($isApp && $kind === 'desk') {
        $kind = 'app';
    }
    $key = 'visit:' . $hash . ':' . $kind . ':' . $script;
    $last = (int) ($_SESSION[$key] ?? 0);
    if ($last > 0 && (time() - $last) < 1200) {
        $done = true;
        return;
    }
    $geo = visit_detect_country();
    $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $device = preg_match('/mobile|android|iphone|ipad/', $ua) ? 'phone' : 'desktop';
    $now = function_exists('desk_now') ? desk_now()->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
    try {
        db_exec(
            'INSERT INTO site_visits (occurred_at, path, page_kind, country, country_name, is_app, device, ip_hash) VALUES (?,?,?,?,?,?,?,?)',
            'sssssiss',
            [$now, $path, $kind, $geo['code'], $geo['name'], $isApp ? 1 : 0, $device, $hash]
        );
        $_SESSION[$key] = time();
    } catch (Throwable $e) {
        error_log('Vellisys visit: ' . $e->getMessage());
    }
    $done = true;
}

function visit_report(int $days = 30): array
{
    $empty = [
        'days' => $days,
        'landing' => 0,
        'desk' => 0,
        'app' => 0,
        'checkout' => 0,
        'login' => 0,
        'total' => 0,
        'countries' => [],
        'daily' => ['labels' => [], 'landing' => [], 'desk' => [], 'app' => []],
    ];
    try {
        if (!db_has_column(db(), 'site_visits', 'page_kind')) {
            return $empty;
        }
    } catch (Throwable $e) {
        return $empty;
    }
    $since = desk_now()->modify('-' . max(1, $days) . ' days')->format('Y-m-d 00:00:00');
    $rows = db_all('SELECT occurred_at, page_kind, country, country_name, is_app FROM site_visits WHERE occurred_at >= ?', 's', [$since]);
    $counts = ['landing' => 0, 'desk' => 0, 'app' => 0, 'checkout' => 0, 'login' => 0, 'onboard' => 0, 'admin' => 0, 'other' => 0];
    $countries = [];
    $daily = [];
    $today = desk_now();
    for ($i = $days - 1; $i >= 0; $i--) {
        $key = $today->modify('-' . $i . ' days')->format('Y-m-d');
        $daily[$key] = ['landing' => 0, 'desk' => 0, 'app' => 0];
    }
    foreach ($rows as $row) {
        $kind = (string) ($row['page_kind'] ?? 'other');
        if ((int) ($row['is_app'] ?? 0) === 1 && $kind === 'desk') {
            $kind = 'app';
        }
        if (!isset($counts[$kind])) {
            $kind = 'other';
        }
        $counts[$kind]++;
        $day = substr((string) $row['occurred_at'], 0, 10);
        if (isset($daily[$day])) {
            if ($kind === 'landing' || $kind === 'checkout') {
                $daily[$day]['landing']++;
            } elseif ($kind === 'app') {
                $daily[$day]['app']++;
            } elseif (in_array($kind, ['desk', 'login', 'onboard'], true)) {
                $daily[$day]['desk']++;
            }
        }
        $name = trim((string) ($row['country_name'] ?? '')) ?: 'Unknown';
        $code = strtoupper((string) ($row['country'] ?? ''));
        $ck = $code !== '' ? $code : $name;
        if (!isset($countries[$ck])) {
            $countries[$ck] = ['code' => $code, 'name' => $name, 'visits' => 0];
        }
        $countries[$ck]['visits']++;
    }
    usort($countries, static fn ($a, $b) => $b['visits'] <=> $a['visits']);
    return [
        'days' => $days,
        'landing' => $counts['landing'] + $counts['checkout'],
        'desk' => $counts['desk'] + $counts['login'] + $counts['onboard'],
        'app' => $counts['app'],
        'checkout' => $counts['checkout'],
        'login' => $counts['login'],
        'total' => count($rows),
        'countries' => array_values($countries),
        'daily' => [
            'labels' => array_keys($daily),
            'landing' => array_column($daily, 'landing'),
            'desk' => array_column($daily, 'desk'),
            'app' => array_column($daily, 'app'),
        ],
    ];
}
