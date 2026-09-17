<?php
declare(strict_types=1);

function search_query(): string
{
    $q = trim((string) ($_GET['q'] ?? ''));
    $q = preg_replace('/\s+/', ' ', $q) ?? $q;
    return mb_substr($q, 0, 120);
}

function search_is_platform_portal(): bool
{
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== 'platform') {
        return false;
    }
    return (int) ($_SESSION['acting_company_id'] ?? 0) <= 0;
}

function search_like(string $q): string
{
    $q = str_replace(['\\', '%', '_'], '', $q);
    return '%' . $q . '%';
}

function search_rank(string $q, string ...$fields): int
{
    $needle = mb_strtolower($q);
    $best = 0;
    foreach ($fields as $i => $field) {
        $hay = mb_strtolower(trim($field));
        if ($hay === '') {
            continue;
        }
        if ($hay === $needle) {
            $best = max($best, $i === 0 ? 100 : 80);
        } elseif (str_starts_with($hay, $needle)) {
            $best = max($best, $i === 0 ? 90 : 60);
        } elseif (str_contains($hay, $needle)) {
            $best = max($best, $i === 0 ? 70 : 40);
        }
    }
    return $best;
}

function search_desk(string $q, int $limit = 8): array
{
    $q = trim($q);
    $cid = current_company_id();
    $out = [
        'documents' => [],
        'clients' => [],
        'products' => [],
        'exact' => null,
    ];
    if ($q === '' || $cid <= 0) {
        return $out;
    }
    $like = search_like($q);

    $docs = db_all(
        "SELECT d.id, d.number, d.kind, d.date, d.status, p.name AS party_name
         FROM documents d
         JOIN parties p ON p.id = d.party_id
         WHERE d.company_id = ?
           AND (d.number LIKE ? OR p.name LIKE ? OR IFNULL(d.subject,'') LIKE ? OR IFNULL(d.notes,'') LIKE ?)
         ORDER BY d.date DESC, d.id DESC
         LIMIT 40",
        'issss',
        [$cid, $like, $like, $like, $like]
    );
    $ranked = [];
    foreach ($docs as $d) {
        $score = search_rank($q, (string) $d['number'], (string) $d['party_name'], (string) ($d['subject'] ?? ''));
        if ($score === 0) {
            $score = 10;
        }
        $href = url('document_view.php?id=' . (int) $d['id']);
        $kind = (string) $d['kind'];
        $meta = function_exists('kind_meta') ? kind_meta($kind)['singular'] : $kind;
        $item = [
            'type' => 'document',
            'id' => (int) $d['id'],
            'title' => (string) $d['number'],
            'subtitle' => trim($meta . ' · ' . (string) $d['party_name'] . ' · ' . format_date((string) $d['date'])),
            'href' => $href,
            'kind' => $kind,
            'status' => (string) $d['status'],
            'valid' => (string) $d['status'] !== 'void',
            'score' => $score,
        ];
        if (strcasecmp((string) $d['number'], $q) === 0 && $out['exact'] === null) {
            $out['exact'] = $item;
        }
        $ranked[] = $item;
    }
    usort($ranked, static fn ($a, $b) => $b['score'] <=> $a['score']);
    $out['documents'] = array_slice($ranked, 0, $limit);

    if (!function_exists('user_can_open') || user_can_open('clients.php')) {
        $parties = db_all(
            "SELECT id, name, phone, email, tin FROM parties
             WHERE company_id = ? AND (status IS NULL OR status <> 'deleted')
               AND (name LIKE ? OR IFNULL(phone,'') LIKE ? OR IFNULL(email,'') LIKE ? OR IFNULL(tin,'') LIKE ?)
             ORDER BY name LIMIT 20",
            'issss',
            [$cid, $like, $like, $like, $like]
        );
        $clients = [];
        foreach ($parties as $p) {
            $bits = array_filter([(string) ($p['phone'] ?? ''), (string) ($p['email'] ?? '')]);
            $clients[] = [
                'type' => 'client',
                'id' => (int) $p['id'],
                'title' => (string) $p['name'],
                'subtitle' => implode(' · ', $bits) ?: 'Client',
                'href' => url('client_view.php?id=' . (int) $p['id']),
                'score' => search_rank($q, (string) $p['name'], (string) ($p['tin'] ?? ''), (string) ($p['phone'] ?? '')),
            ];
        }
        usort($clients, static fn ($a, $b) => $b['score'] <=> $a['score']);
        $out['clients'] = array_slice($clients, 0, $limit);
    }

    if (function_exists('company_stock_enabled') && company_stock_enabled() && user_can_open('stock.php')) {
        try {
            $items = db_all(
                "SELECT id, name, sku, sell_price FROM stock_items
                 WHERE company_id = ? AND active = 1
                   AND (name LIKE ? OR IFNULL(sku,'') LIKE ? OR IFNULL(description,'') LIKE ?)
                 ORDER BY name LIMIT 20",
                'isss',
                [$cid, $like, $like, $like]
            );
            $products = [];
            foreach ($items as $it) {
                $sku = trim((string) ($it['sku'] ?? ''));
                $products[] = [
                    'type' => 'product',
                    'id' => (int) $it['id'],
                    'title' => (string) $it['name'],
                    'subtitle' => trim(($sku !== '' ? $sku . ' · ' : '') . money((float) ($it['sell_price'] ?? 0))),
                    'href' => url('stock.php?q=' . rawurlencode((string) $it['name'])),
                    'score' => search_rank($q, (string) $it['name'], $sku),
                ];
            }
            usort($products, static fn ($a, $b) => $b['score'] <=> $a['score']);
            $out['products'] = array_slice($products, 0, $limit);
        } catch (Throwable $e) {
            $out['products'] = [];
        }
    }

    return $out;
}

function search_platform(string $q, int $limit = 8): array
{
    $q = trim($q);
    $out = [
        'companies' => [],
        'documents' => [],
        'signups' => [],
        'exact' => null,
    ];
    if ($q === '') {
        return $out;
    }
    $like = search_like($q);

    $companies = db_all(
        "SELECT id, name, status, nature_of_business FROM companies
         WHERE name LIKE ? OR IFNULL(nature_of_business,'') LIKE ?
         ORDER BY name LIMIT 20",
        'ss',
        [$like, $like]
    );
    $co = [];
    foreach ($companies as $c) {
        $co[] = [
            'type' => 'company',
            'id' => (int) $c['id'],
            'title' => (string) $c['name'],
            'subtitle' => trim((string) $c['status'] . (trim((string) ($c['nature_of_business'] ?? '')) !== '' ? ' · ' . $c['nature_of_business'] : '')),
            'href' => url('admin_company.php?id=' . (int) $c['id']),
            'score' => search_rank($q, (string) $c['name']),
        ];
    }
    usort($co, static fn ($a, $b) => $b['score'] <=> $a['score']);
    $out['companies'] = array_slice($co, 0, $limit);

    $docs = db_all(
        "SELECT d.id, d.number, d.kind, d.date, d.status, d.company_id, c.name AS company_name, p.name AS party_name
         FROM documents d
         JOIN companies c ON c.id = d.company_id
         JOIN parties p ON p.id = d.party_id
         WHERE d.number LIKE ? OR c.name LIKE ? OR p.name LIKE ?
         ORDER BY d.date DESC, d.id DESC
         LIMIT 40",
        'sss',
        [$like, $like, $like]
    );
    $ranked = [];
    foreach ($docs as $d) {
        $score = search_rank($q, (string) $d['number'], (string) $d['company_name'], (string) $d['party_name']);
        $item = [
            'type' => 'document',
            'id' => (int) $d['id'],
            'title' => (string) $d['number'],
            'subtitle' => trim(kind_meta((string) $d['kind'])['singular'] . ' · ' . $d['company_name'] . ' · ' . $d['party_name']),
            'href' => url('admin_desk.php?id=' . (int) $d['company_id'] . '&next=' . rawurlencode('document_view.php?id=' . (int) $d['id'])),
            'kind' => (string) $d['kind'],
            'status' => (string) $d['status'],
            'valid' => (string) $d['status'] !== 'void',
            'score' => $score ?: 10,
        ];
        if (strcasecmp((string) $d['number'], $q) === 0 && $out['exact'] === null) {
            $out['exact'] = $item;
        }
        $ranked[] = $item;
    }
    usort($ranked, static fn ($a, $b) => $b['score'] <=> $a['score']);
    $out['documents'] = array_slice($ranked, 0, $limit);

    $signups = db_all(
        "SELECT id, name, company, email, phone, status FROM signups
         WHERE name LIKE ? OR company LIKE ? OR email LIKE ? OR phone LIKE ?
         ORDER BY created_at DESC LIMIT 20",
        'ssss',
        [$like, $like, $like, $like]
    );
    $su = [];
    foreach ($signups as $s) {
        $su[] = [
            'type' => 'signup',
            'id' => (int) $s['id'],
            'title' => (string) $s['company'],
            'subtitle' => trim($s['name'] . ' · ' . $s['email'] . ' · ' . $s['status']),
            'href' => url('admin_signups.php'),
            'score' => search_rank($q, (string) $s['company'], (string) $s['name'], (string) $s['email']),
        ];
    }
    usort($su, static fn ($a, $b) => $b['score'] <=> $a['score']);
    $out['signups'] = array_slice($su, 0, $limit);

    return $out;
}

function search_run(string $q, int $limit = 8): array
{
    return search_is_platform_portal() ? search_platform($q, $limit) : search_desk($q, $limit);
}

function search_placeholder(): string
{
    return search_is_platform_portal()
        ? 'Search companies, documents, sign-ups…'
        : 'Search documents, clients, products…';
}

function search_flat_hits(array $data, int $limit = 12): array
{
    $rows = [];
    if (!empty($data['exact'])) {
        $rows[] = $data['exact'] + ['badge' => !empty($data['exact']['valid']) ? 'Valid' : 'Void'];
    }
    foreach (['documents', 'clients', 'products', 'companies', 'signups'] as $group) {
        foreach ($data[$group] ?? [] as $hit) {
            if (!empty($data['exact']) && ($hit['type'] ?? '') === 'document' && (int) $hit['id'] === (int) $data['exact']['id']) {
                continue;
            }
            $rows[] = $hit;
        }
    }
    return array_slice($rows, 0, $limit);
}
