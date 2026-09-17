<?php
declare(strict_types=1);

function nature_of_business_suggestions(): array
{
    return [
        'Retail shop',
        'Wholesale trader',
        'Driving school',
        'Law firm',
        'Clinic',
        'Pharmacy',
        'Estate agency',
        'Restaurant',
        'Hardware store',
        'School',
        'NGO',
        'Manufacturer',
        'Transporter',
        'Salon',
        'Accounting firm',
        'Construction',
        'Farm',
        'Hotel',
        'Workshop',
        'Church',
    ];
}

function sanitize_nature_of_business(string $raw): string
{
    $s = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);
    $s = preg_replace('/[^\p{L}\p{N} &\',.\/+-]/u', '', $s) ?? $s;
    return mb_substr($s, 0, 120);
}

function client_audience_options(): array
{
    return [
        'people' => 'Individuals (students, patients, walk-in buyers)',
        'organisations' => 'Companies and organisations',
        'both' => 'Both people and companies',
    ];
}

function normalize_client_audience(string $raw): string
{
    $raw = strtolower(trim($raw));
    return in_array($raw, ['people', 'organisations', 'both'], true) ? $raw : 'both';
}

function client_core_field_defs(): array
{
    return [
        'contact_person' => 'Contact person',
        'tin' => 'TIN',
        'phone' => 'Phone / Tel',
        'email' => 'Email',
        'address' => 'Address / Residence',
        'city' => 'City',
        'country' => 'Country',
        'phone2' => 'Second phone',
    ];
}

function client_extra_field_types(): array
{
    return [
        'text' => 'Short text',
        'tel' => 'Phone',
        'date' => 'Date',
        'number' => 'Number',
        'textarea' => 'Long text',
        'period' => 'Period (from–to)',
    ];
}

function default_client_core_fields(string $audience): array
{
    $audience = normalize_client_audience($audience);
    if ($audience === 'organisations') {
        return ['contact_person', 'tin', 'phone', 'email', 'address', 'country'];
    }
    if ($audience === 'people') {
        return ['phone', 'email', 'address'];
    }
    return ['phone', 'email', 'address', 'country'];
}

function client_profile_keys(): array
{
    return [
        'people' => 'To individuals',
        'organisations' => 'To company / organisation',
        'other' => 'Other',
    ];
}

function normalize_client_profile(string $raw): string
{
    $raw = strtolower(trim($raw));
    if ($raw === 'organisations' || $raw === 'organisation' || $raw === 'organization' || $raw === 'company') {
        return 'organisations';
    }
    if ($raw === 'other') {
        return 'other';
    }
    return 'people';
}

function entity_to_client_profile(string $entity): string
{
    return match (strtolower(trim($entity))) {
        'organisation', 'organization', 'company' => 'organisations',
        'other' => 'other',
        default => 'people',
    };
}

function client_profile_to_entity(string $profile): string
{
    return match (normalize_client_profile($profile)) {
        'organisations' => 'organisation',
        'other' => 'other',
        default => 'person',
    };
}

function default_client_field_set(string $profile): array
{
    $profile = normalize_client_profile($profile);
    $audience = $profile === 'organisations' ? 'organisations' : ($profile === 'people' ? 'people' : 'both');
    $core = default_client_core_fields($audience);
    return [
        'core' => $core,
        'extras' => [],
        'order' => array_map(static fn (string $k): array => ['kind' => 'core', 'key' => $k], $core),
    ];
}

function default_client_fields(string $audience = 'both'): array
{
    $audience = normalize_client_audience($audience);
    $profiles = [
        'people' => default_client_field_set('people'),
        'organisations' => default_client_field_set('organisations'),
        'other' => default_client_field_set('other'),
    ];
    $pick = $audience === 'organisations' ? 'organisations' : 'people';
    $legacy = $profiles[$pick];
    return [
        'audience' => $audience,
        'core' => $legacy['core'],
        'extras' => $legacy['extras'],
        'order' => $legacy['order'],
        'profiles' => $profiles,
    ];
}

function parse_client_field_set(mixed $raw, string $profile): array
{
    $base = default_client_field_set($profile);
    $data = is_array($raw) ? $raw : [];
    $allowedCore = array_keys(client_core_field_defs());
    $core = [];
    if (isset($data['core']) && is_array($data['core'])) {
        foreach ($data['core'] as $key) {
            $key = (string) $key;
            if (in_array($key, $allowedCore, true) && !in_array($key, $core, true)) {
                $core[] = $key;
            }
        }
        $base['core'] = $core;
    }
    $extras = [];
    $used = [];
    foreach ((array) ($data['extras'] ?? []) as $i => $field) {
        if (is_string($field)) {
            $label = trim($field);
            $key = '';
            $type = 'text';
        } else {
            $label = trim((string) ($field['label'] ?? ''));
            $key = trim((string) ($field['key'] ?? ''));
            $type = strtolower(trim((string) ($field['type'] ?? 'text')));
        }
        if ($label === '') {
            continue;
        }
        if (!isset(client_extra_field_types()[$type])) {
            $type = 'text';
        }
        if ($key === '') {
            $key = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $label), '_'));
        }
        $key = preg_replace('/[^a-z0-9_]+/i', '_', $key) ?? $key;
        $key = strtolower(trim($key, '_'));
        if ($key === '' || isset($used[$key]) || in_array($key, $allowedCore, true) || $key === 'name' || $key === 'entity') {
            $key = 'field_' . ($i + 1);
        }
        $used[$key] = true;
        $extras[] = [
            'key' => mb_substr($key, 0, 40),
            'label' => mb_substr($label, 0, 80),
            'type' => $type,
        ];
    }
    $base['extras'] = $extras;
    $order = [];
    $orderIn = $data['order'] ?? null;
    if (is_array($orderIn)) {
        if ($orderIn === []) {
            $base['core'] = [];
            $base['extras'] = [];
            $base['order'] = [];
            return $base;
        }
        $extraByKey = [];
        foreach ($extras as $ex) {
            $extraByKey[$ex['key']] = $ex;
        }
        $coreFromOrder = [];
        $extrasFromOrder = [];
        $seen = [];
        foreach ($orderIn as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $kind = (($row['kind'] ?? '') === 'extra') ? 'extra' : 'core';
            $key = trim((string) ($row['key'] ?? ''));
            if ($kind === 'core') {
                if (!in_array($key, $allowedCore, true) || isset($seen['c' . $key])) {
                    continue;
                }
                $seen['c' . $key] = true;
                $coreFromOrder[] = $key;
                $order[] = ['kind' => 'core', 'key' => $key];
                continue;
            }
            $label = trim((string) ($row['label'] ?? ($extraByKey[$key]['label'] ?? '')));
            $type = strtolower(trim((string) ($row['type'] ?? ($extraByKey[$key]['type'] ?? 'text'))));
            if ($label === '') {
                continue;
            }
            if (!isset(client_extra_field_types()[$type])) {
                $type = 'text';
            }
            if ($key === '') {
                $key = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $label), '_'));
            }
            $key = strtolower(trim((string) preg_replace('/[^a-z0-9_]+/i', '_', $key), '_'));
            if ($key === '' || isset($seen['e' . $key]) || in_array($key, $allowedCore, true)) {
                $key = 'field_' . ($i + 1);
            }
            $seen['e' . $key] = true;
            $item = ['key' => mb_substr($key, 0, 40), 'label' => mb_substr($label, 0, 80), 'type' => $type];
            $extrasFromOrder[] = $item;
            $order[] = $item + ['kind' => 'extra'];
        }
        $base['core'] = $coreFromOrder;
        $base['extras'] = $extrasFromOrder;
        $base['order'] = $order;
        return $base;
    }
    foreach ($base['core'] as $key) {
        $order[] = ['kind' => 'core', 'key' => $key];
    }
    foreach ($extras as $ex) {
        $order[] = $ex + ['kind' => 'extra'];
    }
    $base['order'] = $order;
    return $base;
}

function parse_client_fields(mixed $raw, string $audienceFallback = 'both'): array
{
    $base = default_client_fields($audienceFallback);
    $data = is_array($raw) ? $raw : json_decode((string) $raw, true);
    if (!is_array($data)) {
        return $base;
    }
    $audience = normalize_client_audience((string) ($data['audience'] ?? $audienceFallback));
    $base['audience'] = $audience;
    $profilesIn = $data['profiles'] ?? null;
    if (is_array($profilesIn)) {
        foreach (array_keys(client_profile_keys()) as $pk) {
            $base['profiles'][$pk] = parse_client_field_set($profilesIn[$pk] ?? [], $pk);
        }
        $pick = $audience === 'organisations' ? 'organisations' : 'people';
        $base['core'] = $base['profiles'][$pick]['core'];
        $base['extras'] = $base['profiles'][$pick]['extras'];
        $base['order'] = $base['profiles'][$pick]['order'];
        return $base;
    }
    $legacy = parse_client_field_set($data, $audience === 'organisations' ? 'organisations' : 'people');
    if ($audience === 'organisations') {
        $base['profiles']['organisations'] = $legacy;
        $base['profiles']['people'] = default_client_field_set('people');
    } else {
        $base['profiles']['people'] = $legacy;
        $base['profiles']['organisations'] = default_client_field_set('organisations');
    }
    $base['profiles']['other'] = default_client_field_set('other');
    $base['core'] = $legacy['core'];
    $base['extras'] = $legacy['extras'];
    $base['order'] = $legacy['order'];
    return $base;
}

function company_client_fields(?array $company = null): array
{
    $company = $company ?? current_company();
    $audience = normalize_client_audience((string) ($company['client_audience'] ?? 'both'));
    return parse_client_fields($company['client_fields'] ?? '', $audience);
}

function company_client_audience(?array $company = null): string
{
    return company_client_fields($company)['audience'];
}

function company_uses_core_client_field(string $key, ?array $company = null): bool
{
    return in_array($key, company_client_fields($company)['core'], true);
}

function posted_client_fields(): array
{
    $audience = normalize_client_audience(post('client_audience') ?: 'both');
    $kindsBag = $_POST['to_kind'] ?? null;
    $hasProfiles = is_array($kindsBag) && $kindsBag !== [] && !is_int(array_key_first($kindsBag));
    if (isset($_POST['to_order_present']) && $hasProfiles) {
        $profiles = [];
        foreach (array_keys(client_profile_keys()) as $pk) {
            $order = [];
            foreach ((array) ($kindsBag[$pk] ?? []) as $i => $kind) {
                $order[] = [
                    'kind' => (string) $kind,
                    'key' => (string) ($_POST['to_key'][$pk][$i] ?? ''),
                    'label' => (string) ($_POST['to_label'][$pk][$i] ?? ''),
                    'type' => (string) ($_POST['to_type'][$pk][$i] ?? 'text'),
                ];
            }
            $profiles[$pk] = ['order' => $order];
        }
        return parse_client_fields([
            'audience' => $audience,
            'profiles' => $profiles,
        ], $audience);
    }
    $kinds = $_POST['to_kind'] ?? null;
    if (isset($_POST['to_order_present']) || (is_array($kinds) && $kinds !== [] && is_int(array_key_first($kinds)))) {
        $order = [];
        foreach ((array) $kinds as $i => $kind) {
            $order[] = [
                'kind' => (string) $kind,
                'key' => (string) ($_POST['to_key'][$i] ?? ''),
                'label' => (string) ($_POST['to_label'][$i] ?? ''),
                'type' => (string) ($_POST['to_type'][$i] ?? 'text'),
            ];
        }
        return parse_client_fields([
            'audience' => $audience,
            'order' => $order,
        ], $audience);
    }
    $corePosted = $_POST['client_core'] ?? null;
    if (!is_array($corePosted)) {
        $core = default_client_core_fields($audience);
    } else {
        $core = [];
        foreach ($corePosted as $key) {
            $key = (string) $key;
            if (isset(client_core_field_defs()[$key]) && !in_array($key, $core, true)) {
                $core[] = $key;
            }
        }
    }
    $extras = [];
    foreach ((array) ($_POST['client_extra_label'] ?? []) as $i => $label) {
        $extras[] = [
            'key' => trim((string) ($_POST['client_extra_key'][$i] ?? '')),
            'label' => trim((string) $label),
            'type' => trim((string) ($_POST['client_extra_type'][$i] ?? 'text')),
        ];
    }
    return parse_client_fields([
        'audience' => $audience,
        'core' => $core,
        'extras' => $extras,
    ], $audience);
}

function client_to_order(?array $cfg = null, ?string $profile = null): array
{
    $cfg = $cfg ?? company_client_fields();
    $profile = $profile !== null && $profile !== '' ? normalize_client_profile($profile) : '';
    if ($profile !== '' && isset($cfg['profiles'][$profile]['order']) && is_array($cfg['profiles'][$profile]['order'])) {
        return $cfg['profiles'][$profile]['order'];
    }
    return is_array($cfg['order'] ?? null) ? $cfg['order'] : default_client_fields($cfg['audience'] ?? 'both')['order'];
}

function posted_client_fields_json(): string
{
    return json_encode(posted_client_fields(), JSON_UNESCAPED_UNICODE) ?: '{}';
}

function parse_party_profile(mixed $raw): array
{
    if (is_array($raw)) {
        return $raw;
    }
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : [];
}

function party_profile(array $party): array
{
    return parse_party_profile($party['profile'] ?? '');
}

function party_entity(array $party, ?array $company = null): string
{
    $raw = strtolower(trim((string) ($party['entity'] ?? $party['party_entity'] ?? '')));
    if (in_array($raw, ['person', 'organisation', 'other'], true)) {
        return $raw;
    }
    $tin = trim((string) ($party['tin'] ?? ''));
    $contact = trim((string) ($party['contact_person'] ?? ''));
    $name = trim((string) ($party['name'] ?? $party['party_name'] ?? ''));
    if ($tin !== '' || $contact !== '' || preg_match('/\b(ltd|limited|llc|inc|plc|llp|company|co\.|org|ngo|trust)\b/i', $name)) {
        return 'organisation';
    }
    return 'person';
}

function normalize_party_entity(string $raw, ?array $company = null): string
{
    $raw = strtolower(trim($raw));
    if (in_array($raw, ['organisation', 'organization', 'company'], true)) {
        return 'organisation';
    }
    if ($raw === 'other') {
        return 'other';
    }
    return 'person';
}

function document_party_extras(array $doc): array
{
    $fromDoc = parse_party_profile($doc['party_extras'] ?? '');
    if ($fromDoc) {
        return $fromDoc;
    }
    return parse_party_profile($doc['party_profile'] ?? '');
}

function document_party_to_lines(array $doc): array
{
    $lines = [];
    $name = trim((string) ($doc['party_name'] ?? ''));
    if ($name !== '') {
        $lines[] = ['label' => 'Name', 'value' => $name, 'span' => false];
    }
    $extras = document_party_extras($doc);
    $entity = party_entity([
        'entity' => (string) ($doc['party_entity'] ?? ''),
        'tin' => (string) ($doc['party_tin'] ?? ''),
        'contact_person' => (string) ($doc['party_contact'] ?? ''),
        'name' => (string) ($doc['party_name'] ?? ''),
    ]);
    $profileKey = entity_to_client_profile($entity);
    $addrLabel = $profileKey === 'people' ? 'Residence' : 'Address';
    foreach (client_to_order(null, $profileKey) as $item) {
        if (($item['kind'] ?? '') === 'extra') {
            $shown = format_client_extra_value($item, $extras[$item['key']] ?? '');
            if ($shown !== '') {
                $lines[] = ['label' => (string) $item['label'], 'value' => $shown, 'nl' => str_contains($shown, "\n")];
            }
            continue;
        }
        $key = (string) ($item['key'] ?? '');
        [$label, $value] = match ($key) {
            'contact_person' => ['Attn', trim((string) ($doc['party_contact'] ?? ''))],
            'tin' => ['TIN', trim((string) ($doc['party_tin'] ?? ''))],
            'phone' => ['Tel', trim((string) ($doc['party_phone'] ?? ''))],
            'phone2' => ['Tel 2', trim((string) ($doc['party_phone2'] ?? ''))],
            'email' => ['Email', trim((string) ($doc['party_email'] ?? ''))],
            'address' => [$addrLabel, trim((string) ($doc['party_address'] ?? ''))],
            'city' => ['City', trim((string) ($doc['party_city'] ?? $doc['city'] ?? ''))],
            'country' => ['Country', trim((string) ($doc['party_country'] ?? $doc['country'] ?? ''))],
            default => ['', ''],
        };
        if ($value === '') {
            continue;
        }
            $lines[] = ['label' => $label, 'value' => $value, 'nl' => $key === 'address' && str_contains($value, "\n")];
    }
    return $lines;
}

function format_client_extra_value(array $field, mixed $value): string
{
    if ($field['type'] === 'period') {
        if (is_array($value)) {
            $from = format_date((string) ($value['from'] ?? '')) ?: trim((string) ($value['from'] ?? ''));
            $to = format_date((string) ($value['to'] ?? '')) ?: trim((string) ($value['to'] ?? ''));
            if ($from === '' && $to === '') {
                return '';
            }
            if ($from === '') {
                return $to;
            }
            if ($to === '') {
                return $from;
            }
            return $from . ' – ' . $to;
        }
        return trim((string) $value);
    }
    $s = is_array($value) ? '' : trim((string) $value);
    if ($s === '') {
        return '';
    }
    if ($field['type'] === 'date') {
        return format_date($s) ?: $s;
    }
    return $s;
}

function company_client_extras_all(?array $company = null): array
{
    $cfg = company_client_fields($company);
    $out = [];
    $seen = [];
    foreach (($cfg['profiles'] ?? []) as $set) {
        foreach (($set['extras'] ?? []) as $field) {
            $k = (string) ($field['key'] ?? '');
            if ($k === '' || isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = $field;
        }
    }
    return $out ?: (array) ($cfg['extras'] ?? []);
}
{
    $out = [];
    $posted = $_POST['to_extra'] ?? [];
    if (!is_array($posted)) {
        $posted = [];
    }
    foreach (company_client_extras_all($company) as $field) {
        $key = $field['key'];
        $raw = $posted[$key] ?? null;
        if ($field['type'] === 'period') {
            $from = trim((string) (is_array($raw) ? ($raw['from'] ?? '') : ''));
            $to = trim((string) (is_array($raw) ? ($raw['to'] ?? '') : ''));
        if ($from === '' && $to === '') {
            $out[$key] = '';
            continue;
        }
        $out[$key] = ['from' => mb_substr($from, 0, 40), 'to' => mb_substr($to, 0, 40)];
        continue;
        }
        $val = is_array($raw) ? '' : trim((string) $raw);
        $out[$key] = $val === '' ? '' : mb_substr($val, 0, $field['type'] === 'textarea' ? 800 : 190);
    }
    return $out;
}

function compact_party_extras(array $extras): array
{
    $out = [];
    foreach ($extras as $k => $v) {
        if ($v === '' || $v === null) {
            continue;
        }
        if (is_array($v)) {
            $from = trim((string) ($v['from'] ?? ''));
            $to = trim((string) ($v['to'] ?? ''));
            if ($from === '' && $to === '') {
                continue;
            }
        }
        $out[$k] = $v;
    }
    return $out;
}

function merge_party_profile(array $existing, array $incoming): array
{
    foreach ($incoming as $k => $v) {
        $existing[$k] = $v;
    }
    foreach (company_client_extras_all() as $field) {
        $key = $field['key'];
        if (!array_key_exists($key, $incoming)) {
            continue;
        }
        if ($incoming[$key] === '' || $incoming[$key] === null || $incoming[$key] === []) {
            unset($existing[$key]);
        }
    }
    return $existing;
}

function country_norm_key(string $raw): string
{
    $s = strtolower(trim($raw));
    $s = preg_replace('/[^a-z]/', '', $s) ?? $s;
    $aliases = [
        'ug' => 'uganda',
        'uga' => 'uganda',
        'ugandan' => 'uganda',
        'republicofuganda' => 'uganda',
        'us' => 'unitedstates',
        'usa' => 'unitedstates',
        'unitedstatesofamerica' => 'unitedstates',
        'uk' => 'unitedkingdom',
        'gb' => 'unitedkingdom',
        'greatbritain' => 'unitedkingdom',
        'ke' => 'kenya',
        'ken' => 'kenya',
        'tz' => 'tanzania',
        'tza' => 'tanzania',
        'rw' => 'rwanda',
        'rwa' => 'rwanda',
        'ss' => 'southsudan',
        'ssd' => 'southsudan',
    ];
    return $aliases[$s] ?? $s;
}

function home_country_name(?array $company = null): string
{
    $company = $company ?? (function_exists('current_company') ? current_company() : null);
    $c = '';
    if ($company && function_exists('company_loc')) {
        $c = trim((string) company_loc($company, 'country'));
    }
    return $c !== '' ? $c : 'Uganda';
}

function party_is_international(array $src, ?array $company = null): bool
{
    $party = trim((string) ($src['party_country'] ?? $src['country'] ?? ''));
    if ($party === '') {
        return false;
    }
    $home = country_norm_key(home_country_name($company));
    $theirs = country_norm_key($party);
    return $theirs !== '' && $home !== '' && $theirs !== $home;
}

function sheet_shows_fx(array $d): bool
{
    if (function_exists('sheet_shows_money') && !sheet_shows_money($d)) {
        return false;
    }
    $home = $d['home_cur'] ?? (function_exists('default_currency') ? default_currency() : 'UGX');
    $cur = $d['cur'] ?? $home;
    if (function_exists('normalize_currency')) {
        $cur = normalize_currency((string) $cur, (string) $home);
        $home = normalize_currency((string) $home, 'UGX');
    }
    if ($cur !== $home) {
        return true;
    }
    return party_is_international($d['doc'] ?? [], function_exists('current_company') ? current_company() : null);
}

function client_to_name_label(?array $company = null, string $entity = 'person'): string
{
    return match (normalize_party_entity($entity, $company)) {
        'person' => 'Client name',
        'organisation' => 'Company name',
        default => 'Name',
    };
}

function render_nature_of_business_field(string $value, string $id = 'nature_of_business'): void
{
    ?>
    <label for="<?= h($id) ?>">Nature of business</label>
    <input id="<?= h($id) ?>" name="nature_of_business" list="nature-of-business" maxlength="120" value="<?= h($value) ?>" placeholder="Driving school, law firm, shop…">
    <datalist id="nature-of-business">
      <?php foreach (nature_of_business_suggestions() as $n): ?>
        <option value="<?= h($n) ?>">
      <?php endforeach; ?>
    </datalist>
    <p class="hint">What this company does. Super admin then sets To fields for individuals, companies, and other on this page.</p>
    <?php
}

function render_to_order_row(array $item, string $profile = 'people'): void
{
    $kind = (($item['kind'] ?? '') === 'extra') ? 'extra' : 'core';
    $key = (string) ($item['key'] ?? '');
    $cores = client_core_field_defs();
    $label = $kind === 'core' ? ($cores[$key] ?? $key) : (string) ($item['label'] ?? '');
    $type = (string) ($item['type'] ?? 'text');
    $profile = normalize_client_profile($profile);
    $n = '[' . $profile . '][]';
    ?>
    <div class="to-order-row" data-to-order-row data-to-kind="<?= h($kind) ?>">
      <div class="to-order-move">
        <button class="btn ghost sm to-order-btn" type="button" data-to-move="-1" aria-label="Move up"><?= icon('chevron-up', 16) ?></button>
        <button class="btn ghost sm to-order-btn" type="button" data-to-move="1" aria-label="Move down"><?= icon('chevron-down', 16) ?></button>
      </div>
      <input type="hidden" name="to_kind<?= $n ?>" value="<?= h($kind) ?>">
      <?php if ($kind === 'core'): ?>
        <input type="hidden" name="to_key<?= $n ?>" value="<?= h($key) ?>">
        <input type="hidden" name="to_label<?= $n ?>" value="<?= h($label) ?>">
        <input type="hidden" name="to_type<?= $n ?>" value="text">
        <span class="to-order-label"><?= h($label) ?></span>
        <span class="to-order-kind">Usual</span>
      <?php else: ?>
        <input type="hidden" name="to_key<?= $n ?>" value="<?= h($key) ?>">
        <input name="to_label<?= $n ?>" value="<?= h($label) ?>" placeholder="e.g. Vehicle no" aria-label="Field label">
        <select name="to_type<?= $n ?>" aria-label="Field type">
          <?php foreach (client_extra_field_types() as $tk => $tl): ?>
            <option value="<?= h($tk) ?>" <?= $type === $tk ? 'selected' : '' ?>><?= h($tl) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <button class="btn ghost sm to-order-remove" type="button" data-to-remove aria-label="Remove"><?= icon('x', 14) ?></button>
    </div>
    <?php
}

function render_client_fields_admin(?array $company = null): void
{
    $cfg = $company ? company_client_fields($company) : posted_client_fields();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$company) {
        $cfg = default_client_fields('both');
    }
    $active = normalize_client_profile((string) ($_GET['to_tab'] ?? 'people'));
    ?>
    <fieldset class="client-fields-box">
      <legend>Client details on the To section</legend>
      <p class="hint">Set a different To block for individuals, companies, and anything else. On a new document the desk picks a category and only that tab’s fields appear. Name is always first.</p>
      <input type="hidden" name="client_audience" value="both">
      <input type="hidden" name="to_order_present" value="1">
      <div class="to-field-tabs" data-to-field-tabs>
        <?php foreach (client_profile_keys() as $pk => $label): ?>
          <button class="to-field-tab<?= $pk === $active ? ' is-on' : '' ?>" type="button" data-to-tab="<?= h($pk) ?>"><?= h($label) ?></button>
        <?php endforeach; ?>
      </div>
      <?php foreach (client_profile_keys() as $pk => $label):
          $set = $cfg['profiles'][$pk] ?? default_client_field_set($pk);
          $order = is_array($set['order'] ?? null) ? $set['order'] : default_client_field_set($pk)['order'];
          $usedCores = [];
          foreach ($order as $item) {
              if (($item['kind'] ?? '') === 'core') {
                  $usedCores[] = (string) $item['key'];
              }
          }
          ?>
        <div class="to-tab-panel" data-to-tab-panel="<?= h($pk) ?>" <?= $pk === $active ? '' : 'hidden' ?>>
          <p class="hint"><?php
            echo match ($pk) {
                'people' => 'Fields for a person: student, patient, walk-in buyer.',
                'organisations' => 'Usual company fields: contact person, TIN, address, country.',
                default => 'A third set when the client is neither a person nor a registered firm.',
            };
          ?></p>
          <div class="to-order-list" data-client-fields data-to-order="<?= h($pk) ?>">
            <?php foreach ($order as $item): ?>
              <?php render_to_order_row($item, $pk); ?>
            <?php endforeach; ?>
          </div>
          <div class="to-order-add">
            <label class="sr-only" for="to_add_core_<?= h($pk) ?>">Add a usual field</label>
            <select id="to_add_core_<?= h($pk) ?>" data-to-add-core>
              <option value="">Add usual field…</option>
              <?php foreach (client_core_field_defs() as $key => $clabel): ?>
                <option value="<?= h($key) ?>" <?= in_array($key, $usedCores, true) ? 'disabled' : '' ?>><?= h($clabel) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn ghost sm" type="button" data-add-client-field><?= icon('plus', 14) ?>Add open field</button>
          </div>
        </div>
      <?php endforeach; ?>
    </fieldset>
    <?php
}

function render_to_extra_input(array $field, mixed $value, string $profile = ''): void
{
    $key = $field['key'];
    $name = 'to_extra[' . $key . ']';
    $id = 'to_extra_' . $key . ($profile !== '' ? '_' . $profile : '');
    $type = $field['type'];
    if ($type === 'period') {
        $from = is_array($value) ? (string) ($value['from'] ?? '') : '';
        $to = is_array($value) ? (string) ($value['to'] ?? '') : '';
        ?>
        <div class="doc-span">
          <label><?= h($field['label']) ?></label>
          <div class="fx-row to-period">
            <input id="<?= h($id) ?>_from" name="<?= h($name) ?>[from]" type="date" value="<?= h($from) ?>" data-to-extra="<?= h($key) ?>" data-to-extra-part="from" aria-label="<?= h($field['label']) ?> from">
            <span>to</span>
            <input id="<?= h($id) ?>_to" name="<?= h($name) ?>[to]" type="date" value="<?= h($to) ?>" data-to-extra="<?= h($key) ?>" data-to-extra-part="to" aria-label="<?= h($field['label']) ?> to">
          </div>
        </div>
        <?php
        return;
    }
    $val = is_array($value) ? '' : (string) $value;
    $inputType = match ($type) {
        'tel' => 'tel',
        'date' => 'date',
        'number' => 'text',
        'email' => 'email',
        default => 'text',
    };
    ?>
    <div<?= $type === 'textarea' ? ' class="doc-span"' : '' ?>>
      <label for="<?= h($id) ?>"><?= h($field['label']) ?></label>
      <?php if ($type === 'textarea'): ?>
        <textarea id="<?= h($id) ?>" name="<?= h($name) ?>" rows="3" data-to-extra="<?= h($key) ?>"><?= h($val) ?></textarea>
      <?php else: ?>
        <input id="<?= h($id) ?>" name="<?= h($name) ?>" type="<?= h($inputType) ?>" <?= $type === 'number' ? 'inputmode="decimal"' : '' ?> value="<?= h($val) ?>" data-to-extra="<?= h($key) ?>">
      <?php endif; ?>
    </div>
    <?php
}

function render_to_core_input(string $key, ?array $party, string $profile = ''): void
{
    $party = $party ?? [];
    $val = static fn (string $col): string => (string) ($party[$col] ?? '');
    $sid = $profile !== '' ? '_' . $profile : '';
    switch ($key) {
        case 'contact_person':
            ?>
            <div>
              <label for="to_contact<?= h($sid) ?>">Contact person</label>
              <input id="to_contact<?= h($sid) ?>" name="to_contact" value="<?= h($val('contact_person')) ?>">
            </div>
            <?php
            return;
        case 'tin':
            ?>
            <div>
              <label for="to_tin<?= h($sid) ?>">TIN</label>
              <input id="to_tin<?= h($sid) ?>" name="to_tin" value="<?= h($val('tin')) ?>">
            </div>
            <?php
            return;
        case 'address':
            ?>
            <div>
              <label for="to_address<?= h($sid) ?>"><?= $profile === 'people' ? 'Residence / address' : 'Address' ?></label>
              <input id="to_address<?= h($sid) ?>" name="to_address" value="<?= h($val('address')) ?>">
            </div>
            <?php
            return;
        case 'phone':
            ?>
            <div>
              <label for="to_phone<?= h($sid) ?>">Tel</label>
              <input id="to_phone<?= h($sid) ?>" name="to_phone" value="<?= h($val('phone')) ?>">
            </div>
            <?php
            return;
        case 'phone2':
            ?>
            <div>
              <label for="to_phone2<?= h($sid) ?>">Second phone</label>
              <input id="to_phone2<?= h($sid) ?>" name="to_phone2" value="<?= h($val('phone2')) ?>">
            </div>
            <?php
            return;
        case 'email':
            ?>
            <div>
              <label for="to_email<?= h($sid) ?>">Email</label>
              <input id="to_email<?= h($sid) ?>" name="to_email" type="email" value="<?= h($val('email')) ?>">
            </div>
            <?php
            return;
        case 'city':
            ?>
            <div>
              <label for="to_city<?= h($sid) ?>">City</label>
              <input id="to_city<?= h($sid) ?>" name="to_city" value="<?= h($val('city')) ?>">
            </div>
            <?php
            return;
        case 'country':
            ?>
            <div>
              <label for="to_country<?= h($sid) ?>">Country</label>
              <input id="to_country<?= h($sid) ?>" name="to_country" value="<?= h($val('country')) ?>" placeholder="Leave blank if local">
              <p class="hint">Fill this only for a foreign client. Local sheets do not show a USD conversion.</p>
            </div>
            <?php
            return;
    }
}

function render_document_to_fields(?array $party, ?array $doc = null): void
{
    $cfg = company_client_fields();
    $profile = [];
    if ($doc) {
        $profile = document_party_extras($doc);
    }
    if (!$profile && $party) {
        $profile = party_profile($party);
    }
    $entity = $party ? party_entity($party) : normalize_party_entity(post('to_entity') ?: 'person', null);
    $nameLabel = client_to_name_label(null, $entity);
    $activeProfile = entity_to_client_profile($entity);
    ?>
    <div class="doc-client-block doc-span">
      <h2 class="doc-client-title">To</h2>
      <div class="form-grid doc-client-grid" data-to-fields>
        <div>
          <label for="to_entity">This client is</label>
          <select id="to_entity" name="to_entity" data-to-entity>
            <option value="person" <?= $entity === 'person' ? 'selected' : '' ?>>An individual</option>
            <option value="organisation" <?= $entity === 'organisation' ? 'selected' : '' ?>>A company / organisation</option>
            <option value="other" <?= $entity === 'other' ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
        <div>
          <label for="to_name" data-to-name-label><?= h($nameLabel) ?></label>
          <div class="client-combo" data-client-combo>
            <input type="hidden" id="party_id" name="party_id" value="<?= $party ? (int) $party['id'] : '' ?>">
            <input id="to_name" name="to_name" required autocomplete="off" placeholder="Start typing a name…" value="<?= h((string) ($party['name'] ?? '')) ?>" data-client-search>
            <div class="client-combo-panel" data-client-panel hidden>
              <button type="button" class="client-combo-scroll" data-client-scroll="-1" aria-label="Scroll client list up"><?= icon('chevron-up', 16) ?></button>
              <ul class="client-combo-list" data-client-list></ul>
              <button type="button" class="client-combo-scroll" data-client-scroll="1" aria-label="Scroll client list down"><?= icon('chevron-down', 16) ?></button>
            </div>
          </div>
          <p class="hint">Choose a saved client or type a new one. They are added when you save.</p>
        </div>
        <?php foreach (client_profile_keys() as $pk => $plabel):
            $on = $pk === $activeProfile;
            $order = client_to_order($cfg, $pk);
            ?>
          <div class="doc-span to-profile-fields" data-to-profile="<?= h($pk) ?>" <?= $on ? '' : 'hidden' ?>>
            <div class="form-grid doc-client-grid">
              <?php foreach ($order as $item): ?>
                <?php if (($item['kind'] ?? '') === 'extra'): ?>
                  <?php render_to_extra_input($item, $profile[$item['key']] ?? '', $pk); ?>
                <?php else: ?>
                  <?php render_to_core_input((string) ($item['key'] ?? ''), $party, $pk); ?>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
}

function render_client_edit_extras(array $party): void
{
    $cfg = company_client_fields();
    $profile = party_profile($party);
    $entity = party_entity($party);
    $active = entity_to_client_profile($entity);
    $any = false;
    foreach ($cfg['profiles'] ?? [] as $set) {
        if (!empty($set['extras'])) {
            $any = true;
            break;
        }
    }
    if (!$any) {
        return;
    }
    echo '<h2 class="doc-client-title" style="margin:18px 0 8px">Details this business collects</h2>';
    foreach (client_profile_keys() as $pk => $plabel) {
        $extras = $cfg['profiles'][$pk]['extras'] ?? [];
        if (!$extras) {
            continue;
        }
        $on = $pk === $active;
        echo '<div class="form-grid to-profile-fields" data-to-profile="' . h($pk) . '"' . ($on ? '' : ' hidden') . '>';
        foreach ($extras as $field) {
            render_to_extra_input($field, $profile[$field['key']] ?? '', $pk);
        }
        echo '</div>';
    }
}
