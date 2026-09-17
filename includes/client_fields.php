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

function default_client_fields(string $audience = 'both'): array
{
    $audience = normalize_client_audience($audience);
    $core = default_client_core_fields($audience);
    return [
        'audience' => $audience,
        'core' => $core,
        'extras' => [],
        'order' => array_map(static fn (string $k): array => ['kind' => 'core', 'key' => $k], $core),
    ];
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
    if (is_array($orderIn) && $orderIn !== []) {
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
    $kinds = $_POST['to_kind'] ?? null;
    if (isset($_POST['to_order_present']) || (is_array($kinds) && $kinds !== [])) {
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

function client_to_order(?array $cfg = null): array
{
    $cfg = $cfg ?? company_client_fields();
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
    $raw = strtolower(trim((string) ($party['entity'] ?? '')));
    if (in_array($raw, ['person', 'organisation'], true)) {
        return $raw;
    }
    $audience = company_client_audience($company);
    if ($audience === 'organisations') {
        return 'organisation';
    }
    if ($audience === 'people') {
        return 'person';
    }
    $tin = trim((string) ($party['tin'] ?? ''));
    $contact = trim((string) ($party['contact_person'] ?? ''));
    $name = trim((string) ($party['name'] ?? ''));
    if ($tin !== '' || $contact !== '' || preg_match('/\b(ltd|limited|llc|inc|plc|llp|company|co\.|org|ngo|trust)\b/i', $name)) {
        return 'organisation';
    }
    return 'person';
}

function normalize_party_entity(string $raw, ?array $company = null): string
{
    $audience = company_client_audience($company);
    if ($audience === 'people') {
        return 'person';
    }
    if ($audience === 'organisations') {
        return 'organisation';
    }
    return strtolower(trim($raw)) === 'organisation' ? 'organisation' : 'person';
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
    $addrLabel = function_exists('company_client_audience') && company_client_audience() === 'people'
        ? 'Residence'
        : 'Address';
    foreach (client_to_order() as $item) {
        if (($item['kind'] ?? '') === 'extra') {
            $shown = format_client_extra_value($item, $extras[$item['key']] ?? '');
            if ($shown !== '') {
                $lines[] = ['label' => (string) $item['label'], 'value' => $shown, 'span' => ($item['type'] ?? '') === 'textarea' || ($item['type'] ?? '') === 'period'];
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
        $lines[] = ['label' => $label, 'value' => $value, 'span' => $key === 'address', 'nl' => $key === 'address'];
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

function posted_to_extras(?array $company = null): array
{
    $out = [];
    $posted = $_POST['to_extra'] ?? [];
    if (!is_array($posted)) {
        $posted = [];
    }
    foreach (company_client_fields($company)['extras'] as $field) {
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
    foreach (company_client_fields()['extras'] as $field) {
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

function client_to_name_label(?array $company = null): string
{
    return match (company_client_audience($company)) {
        'people' => 'Client name',
        'organisations' => 'Company name',
        default => 'Customer name',
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
    <p class="hint">What this company does. Used to choose which client details appear on quotations and invoices.</p>
    <?php
}

function render_to_order_row(array $item): void
{
    $kind = (($item['kind'] ?? '') === 'extra') ? 'extra' : 'core';
    $key = (string) ($item['key'] ?? '');
    $cores = client_core_field_defs();
    $label = $kind === 'core' ? ($cores[$key] ?? $key) : (string) ($item['label'] ?? '');
    $type = (string) ($item['type'] ?? 'text');
    ?>
    <div class="to-order-row" data-to-order-row data-to-kind="<?= h($kind) ?>">
      <div class="to-order-move">
        <button class="btn ghost sm to-order-btn" type="button" data-to-move="-1" aria-label="Move up"><?= icon('chevron-up', 16) ?></button>
        <button class="btn ghost sm to-order-btn" type="button" data-to-move="1" aria-label="Move down"><?= icon('chevron-down', 16) ?></button>
      </div>
      <input type="hidden" name="to_kind[]" value="<?= h($kind) ?>">
      <?php if ($kind === 'core'): ?>
        <input type="hidden" name="to_key[]" value="<?= h($key) ?>">
        <input type="hidden" name="to_label[]" value="<?= h($label) ?>">
        <input type="hidden" name="to_type[]" value="text">
        <span class="to-order-label"><?= h($label) ?></span>
        <span class="to-order-kind">Usual</span>
      <?php else: ?>
        <input type="hidden" name="to_key[]" value="<?= h($key) ?>">
        <input name="to_label[]" value="<?= h($label) ?>" placeholder="e.g. Vehicle no" aria-label="Field label">
        <select name="to_type[]" aria-label="Field type">
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
        $cfg = default_client_fields(post('client_audience') ?: 'both');
    }
    $order = client_to_order($cfg);
    $usedCores = [];
    foreach ($order as $item) {
        if (($item['kind'] ?? '') === 'core') {
            $usedCores[] = (string) $item['key'];
        }
    }
    ?>
    <fieldset class="client-fields-box">
      <legend>Client details on the To section</legend>
      <p class="hint">Name is always first. Add the fields this desk fills, then move a row up or down so the sheet matches how they write (driving school: date, tel, occupation, residence…).</p>
      <label for="client_audience">Who they invoice</label>
      <select id="client_audience" name="client_audience">
        <?php foreach (client_audience_options() as $k => $label): ?>
          <option value="<?= h($k) ?>" <?= $cfg['audience'] === $k ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="hint">Pick both when a firm bills companies and individuals. The desk then chooses person or organisation on each client.</p>
      <p class="hint" style="margin-top:10px"><strong>Order on the document</strong></p>
      <input type="hidden" name="to_order_present" value="1">
      <div class="to-order-list" data-client-fields data-to-order>
        <?php foreach ($order as $item): ?>
          <?php render_to_order_row($item); ?>
        <?php endforeach; ?>
      </div>
      <div class="to-order-add">
        <label class="sr-only" for="to_add_core">Add a usual field</label>
        <select id="to_add_core" data-to-add-core>
          <option value="">Add usual field…</option>
          <?php foreach (client_core_field_defs() as $key => $label): ?>
            <option value="<?= h($key) ?>" <?= in_array($key, $usedCores, true) ? 'disabled' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn ghost sm" type="button" data-add-client-field><?= icon('plus', 14) ?>Add open field</button>
      </div>
    </fieldset>
    <?php
}

function render_to_extra_input(array $field, mixed $value): void
{
    $key = $field['key'];
    $name = 'to_extra[' . $key . ']';
    $id = 'to_extra_' . $key;
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

function render_to_core_input(string $key, ?array $party): void
{
    $party = $party ?? [];
    $val = static fn (string $col): string => (string) ($party[$col] ?? '');
    switch ($key) {
        case 'contact_person':
            ?>
            <div>
              <label for="to_contact">Contact person</label>
              <input id="to_contact" name="to_contact" value="<?= h($val('contact_person')) ?>">
            </div>
            <?php
            return;
        case 'tin':
            ?>
            <div>
              <label for="to_tin">TIN</label>
              <input id="to_tin" name="to_tin" value="<?= h($val('tin')) ?>">
            </div>
            <?php
            return;
        case 'address':
            ?>
            <div>
              <label for="to_address"><?= company_client_audience() === 'people' ? 'Residence / address' : 'Address' ?></label>
              <input id="to_address" name="to_address" value="<?= h($val('address')) ?>">
            </div>
            <?php
            return;
        case 'phone':
            ?>
            <div>
              <label for="to_phone">Tel</label>
              <input id="to_phone" name="to_phone" value="<?= h($val('phone')) ?>">
            </div>
            <?php
            return;
        case 'phone2':
            ?>
            <div>
              <label for="to_phone2">Second phone</label>
              <input id="to_phone2" name="to_phone2" value="<?= h($val('phone2')) ?>">
            </div>
            <?php
            return;
        case 'email':
            ?>
            <div>
              <label for="to_email">Email</label>
              <input id="to_email" name="to_email" type="email" value="<?= h($val('email')) ?>">
            </div>
            <?php
            return;
        case 'city':
            ?>
            <div>
              <label for="to_city">City</label>
              <input id="to_city" name="to_city" value="<?= h($val('city')) ?>">
            </div>
            <?php
            return;
        case 'country':
            ?>
            <div>
              <label for="to_country">Country</label>
              <input id="to_country" name="to_country" value="<?= h($val('country')) ?>" placeholder="Leave blank if local">
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
    $entity = $party ? party_entity($party) : normalize_party_entity(post('to_entity') ?: '', null);
    $nameLabel = client_to_name_label();
    ?>
    <div class="doc-client-block doc-span">
      <h2 class="doc-client-title">To</h2>
      <div class="form-grid doc-client-grid" data-to-fields>
        <?php if ($cfg['audience'] === 'both'): ?>
          <div>
            <label for="to_entity">This client is</label>
            <select id="to_entity" name="to_entity">
              <option value="person" <?= $entity === 'person' ? 'selected' : '' ?>>A person</option>
              <option value="organisation" <?= $entity === 'organisation' ? 'selected' : '' ?>>A company / organisation</option>
            </select>
          </div>
        <?php else: ?>
          <input type="hidden" name="to_entity" value="<?= h($cfg['audience'] === 'organisations' ? 'organisation' : 'person') ?>">
        <?php endif; ?>
        <div>
          <label for="to_name"><?= h($nameLabel) ?></label>
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
        <?php foreach (client_to_order($cfg) as $item): ?>
          <?php if (($item['kind'] ?? '') === 'extra'): ?>
            <?php render_to_extra_input($item, $profile[$item['key']] ?? ''); ?>
          <?php else: ?>
            <?php render_to_core_input((string) ($item['key'] ?? ''), $party); ?>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
}

function render_client_edit_extras(array $party): void
{
    $cfg = company_client_fields();
    if (!$cfg['extras']) {
        return;
    }
    $profile = party_profile($party);
    echo '<h2 class="doc-client-title" style="margin:18px 0 8px">Details this business collects</h2>';
    echo '<div class="form-grid">';
    foreach ($cfg['extras'] as $field) {
        render_to_extra_input($field, $profile[$field['key']] ?? '');
    }
    echo '</div>';
}
