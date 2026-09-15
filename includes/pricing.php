<?php
declare(strict_types=1);

function pricing_currencies(): array
{
    return [
        'UGX' => ['label' => 'UGX', 'name' => 'Uganda shilling', 'decimals' => 0],
        'KES' => ['label' => 'KES', 'name' => 'Kenya shilling', 'decimals' => 0],
        'USD' => ['label' => 'USD', 'name' => 'US dollar', 'decimals' => 2],
        'EUR' => ['label' => 'EUR', 'name' => 'Euro', 'decimals' => 2],
        'GBP' => ['label' => 'GBP', 'name' => 'Pound sterling', 'decimals' => 2],
        'RWF' => ['label' => 'RWF', 'name' => 'Rwanda franc', 'decimals' => 0],
    ];
}

/** Average UGX per 1 unit of that currency. */
function pricing_ugx_rate_defaults(): array
{
    return [
        'UGX' => 1.0,
        'KES' => 28.5,
        'USD' => 3700.0,
        'EUR' => 4050.0,
        'GBP' => 4750.0,
        'RWF' => 2.55,
    ];
}

function pricing_package_defaults(): array
{
    return [
        'solo' => [
            'key' => 'solo',
            'name' => 'Vellisys Start',
            'kicker' => 'Starting package',
            'ribbon' => '',
            'popular' => false,
            'seats' => 2,
            'price_ugx' => 150000,
            'was_ugx' => 200000,
            'cta' => 'Select Vellisys Start',
            'lead' => 'Up to 2 people on the desk. Head office only. The books in your colours.',
            'points' => [
                'Up to 2 people on the desk',
                'Head office only (no extra branches)',
                'Branded quotations, invoices and receipts',
                'Clients, debtors and share by email or WhatsApp',
                'Print and PDF from the browser',
                'Reports for the person who signs in',
                'Activity log of major desk events',
            ],
            'sort' => 10,
        ],
        'studio' => [
            'key' => 'studio',
            'name' => 'Vellisys Business',
            'kicker' => '',
            'ribbon' => 'Most companies',
            'popular' => true,
            'seats' => 3,
            'price_ugx' => 200000,
            'was_ugx' => 280000,
            'cta' => 'Select Vellisys Business',
            'lead' => 'Up to 3 people on the desk and up to 2 branches. Access levels and the full sales loop.',
            'points' => [
                'Up to 3 people on the desk',
                'Access levels: Books or Sales',
                'Everything in Vellisys Start',
                'Expenses, creditors and delivery notes',
                'Planner notes, budget, calendar, and tasks',
                'Headed letters from the company mailbox',
                'Up to 2 branches (Head office plus named shops). Several users can share a branch',
            ],
            'sort' => 20,
        ],
        'practice' => [
            'key' => 'practice',
            'name' => 'Vellisys Pro',
            'kicker' => 'Full house',
            'ribbon' => '',
            'popular' => false,
            'seats' => 4,
            'price_ugx' => 250000,
            'was_ugx' => 350000,
            'cta' => 'Select Vellisys Pro',
            'lead' => 'Up to 4 people on the desk and up to 3 branches. Every document the desk can print.',
            'points' => [
                'Up to 4 people on the desk',
                'Access levels for each extra seat',
                'Everything in Vellisys Business',
                'Custom documents and all letter layouts',
                'Profit & Loss bookkeeping with refunds and returns',
                'Up to 3 branches (Head office plus named shops). Several users can share a branch',
                'Priority onboarding from Vellisys',
            ],
            'sort' => 30,
        ],
    ];
}

function pricing_section_defaults(): array
{
    return [
        'kicker' => 'Packages',
        'heading' => 'Onboard as the discount lasts',
        'lead' => 'Billed per year, shown in {currency}. Change currency in the header. Pay online, choose how you will sign in, then add your logo and colours.',
        'clock_label' => 'Discount ends in',
        'term_label' => 'per year',
        'register_copy' => 'Want us to open the desk for you? {register}. You can also {demo}.',
        'register_label' => 'register without paying',
        'countdown_days' => 3,
        'countdown_hours' => 12,
        'rates' => pricing_ugx_rate_defaults(),
    ];
}

function pricing_points_from_text(string $raw): array
{
    $parts = preg_split("/\r\n|\n|\r/", $raw) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $line = trim((string) $part);
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return $out;
}

function pricing_package_from_row(array $row): array
{
    $pkg = [
        'id' => (int) ($row['id'] ?? 0),
        'key' => (string) ($row['pkg_key'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'kicker' => (string) ($row['kicker'] ?? ''),
        'ribbon' => (string) ($row['ribbon'] ?? ''),
        'popular' => (int) ($row['popular'] ?? 0) === 1,
        'seats' => max(1, min(4, (int) ($row['seats'] ?? 1))),
        'price_ugx' => (float) ($row['price_ugx'] ?? 0),
        'was_ugx' => (float) ($row['was_ugx'] ?? 0),
        'cta' => (string) ($row['cta'] ?? 'Select plan'),
        'lead' => (string) ($row['lead'] ?? ''),
        'points' => pricing_points_from_text((string) ($row['points'] ?? '')),
        'sort' => (int) ($row['sort'] ?? 0),
    ];
    return $pkg;
}

function pricing_package_names(): array
{
    $names = [];
    foreach (pricing_packages() as $pkg) {
        $name = trim((string) ($pkg['name'] ?? ''));
        if ($name !== '') {
            $names[] = $name;
        }
    }
    return $names;
}

function pricing_names_phrase(): string
{
    $names = pricing_package_names();
    if (!$names) {
        return 'a package';
    }
    if (count($names) === 1) {
        return $names[0];
    }
    if (count($names) === 2) {
        return $names[0] . ' or ' . $names[1];
    }
    $last = array_pop($names);
    return implode(', ', $names) . ' or ' . $last;
}

function pricing_legacy_name_map(?array $packages = null): array
{
    $packages = $packages ?? pricing_packages();
    $map = [];
    foreach (pricing_package_defaults() as $key => $def) {
        $legacy = trim((string) ($def['name'] ?? ''));
        $current = trim((string) ($packages[$key]['name'] ?? $legacy));
        if ($legacy !== '' && $current !== '' && strcasecmp($legacy, $current) !== 0) {
            $map[$legacy] = $current;
        }
    }
    uksort($map, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
    return $map;
}

function pricing_swap_legacy_names(string $text, ?array $packages = null): string
{
    foreach (pricing_legacy_name_map($packages) as $old => $new) {
        $text = preg_replace('/\b' . preg_quote($old, '/') . '\b/u', $new, $text) ?? $text;
    }
    return $text;
}

function pricing_display_cta(array $pkg, array $packages): string
{
    $cta = trim((string) ($pkg['cta'] ?? ''));
    $name = trim((string) ($pkg['name'] ?? ''));
    $legacy = array_keys(pricing_legacy_name_map($packages));
    foreach ($packages as $other) {
        $n = trim((string) ($other['name'] ?? ''));
        if ($n !== '') {
            $legacy[] = $n;
        }
    }
    $legacy = array_values(array_unique($legacy));
    usort($legacy, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
    foreach ($legacy as $old) {
        if ($old !== '' && preg_match('/^Select\s+' . preg_quote($old, '/') . '$/iu', $cta)) {
            return $name !== '' ? 'Select ' . $name : $cta;
        }
    }
    if ($cta === '') {
        return $name !== '' ? 'Select ' . $name : 'Select package';
    }
    return pricing_swap_legacy_names($cta, $packages);
}

function pricing_display_points(array $pkg, array $packages): array
{
    $ordered = array_values($packages);
    $idx = 0;
    foreach ($ordered as $i => $item) {
        if (($item['key'] ?? '') === ($pkg['key'] ?? '')) {
            $idx = $i;
            break;
        }
    }
    $prev = $idx > 0 ? trim((string) ($ordered[$idx - 1]['name'] ?? '')) : '';
    $out = [];
    foreach ($pkg['points'] as $point) {
        $line = trim((string) $point);
        if ($prev !== '' && preg_match('/^Everything in\b/iu', $line)) {
            $out[] = 'Everything in ' . $prev;
            continue;
        }
        $out[] = pricing_swap_legacy_names($line, $packages);
    }
    return $out;
}

function pricing_retarget_package_name(string $oldName, string $newName, int $exceptId = 0): void
{
    $oldName = trim($oldName);
    $newName = trim($newName);
    if ($oldName === '' || $newName === '' || strcasecmp($oldName, $newName) === 0) {
        return;
    }
    try {
        $rows = db_all('SELECT id, cta, points, lead FROM landing_packages');
    } catch (Throwable $e) {
        return;
    }
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $cta = (string) ($row['cta'] ?? '');
        $points = (string) ($row['points'] ?? '');
        $lead = (string) ($row['lead'] ?? '');
        $nextCta = $cta;
        if ($id === $exceptId && preg_match('/^Select\s+/iu', $cta)) {
            $nextCta = 'Select ' . $newName;
        } elseif (preg_match('/^Select\s+' . preg_quote($oldName, '/') . '$/iu', $cta)) {
            $nextCta = 'Select ' . $newName;
        }
        $nextPoints = preg_replace('/\b' . preg_quote($oldName, '/') . '\b/u', $newName, $points) ?? $points;
        $nextLead = preg_replace('/\b' . preg_quote($oldName, '/') . '\b/u', $newName, $lead) ?? $lead;
        if ($nextCta === $cta && $nextPoints === $points && $nextLead === $lead) {
            continue;
        }
        db_exec('UPDATE landing_packages SET cta=?, points=?, lead=? WHERE id=?', 'sssi', [$nextCta, $nextPoints, $nextLead, $id]);
    }
}

function pricing_packages(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = folio_remember('pricing_packages', static function (): array {
        try {
            $rows = db_all('SELECT * FROM landing_packages ORDER BY sort, id');
            $out = [];
            foreach ($rows as $row) {
                $pkg = pricing_package_from_row($row);
                if ($pkg['key'] !== '') {
                    $out[$pkg['key']] = $pkg;
                }
            }
            return $out !== [] ? $out : pricing_package_defaults();
        } catch (Throwable $e) {
            return pricing_package_defaults();
        }
    });
    return $cached;
}

function pricing_ugx_rates(): array
{
    return pricing_section()['rates'];
}

function pricing_stock_addon_solo_ugx(): int
{
    return max(0, platform_setting_int('stock_addon_solo_ugx', 50000));
}

function pricing_stock_addon_multi_ugx(): int
{
    return max(0, platform_setting_int('stock_addon_multi_ugx', 100000));
}

function pricing_stock_addon_ugx(string $planKey = ''): int
{
    $pkg = $planKey !== '' ? pricing_package($planKey) : null;
    if (!$pkg) {
        return pricing_stock_addon_multi_ugx();
    }
    return ($pkg['key'] ?? '') === 'solo' ? pricing_stock_addon_solo_ugx() : pricing_stock_addon_multi_ugx();
}

function pricing_package(string $key): ?array
{
    return pricing_packages()[$key] ?? null;
}

function pricing_section(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = folio_remember('pricing_section', static function (): array {
        $base = pricing_section_defaults();
        try {
            $row = db_one('SELECT * FROM landing_pricing WHERE id = 1');
        } catch (Throwable $e) {
            $row = null;
        }
        if (!$row) {
            return $base;
        }
        $rates = $base['rates'];
        $decoded = json_decode((string) ($row['rates_json'] ?? ''), true);
        if (is_array($decoded)) {
            foreach ($rates as $code => $fallback) {
                if ($code === 'UGX') {
                    $rates[$code] = 1.0;
                    continue;
                }
                if (isset($decoded[$code]) && (float) $decoded[$code] > 0) {
                    $rates[$code] = (float) $decoded[$code];
                }
            }
        }
        $out = [
            'kicker' => (string) ($row['kicker'] ?? $base['kicker']),
            'heading' => (string) ($row['heading'] ?? $base['heading']),
            'lead' => (string) ($row['lead'] ?? $base['lead']),
            'clock_label' => (string) ($row['clock_label'] ?? $base['clock_label']),
            'term_label' => (string) ($row['term_label'] ?? $base['term_label']),
            'register_copy' => (string) ($row['register_copy'] ?? $base['register_copy']),
            'register_label' => (string) ($row['register_label'] ?? $base['register_label']),
            'countdown_days' => max(0, min(30, (int) ($row['countdown_days'] ?? $base['countdown_days']))),
            'countdown_hours' => max(0, min(23, (int) ($row['countdown_hours'] ?? $base['countdown_hours']))),
            'rates' => $rates,
        ];
        if (strcasecmp(trim($out['term_label']), 'first year') === 0) {
            $out['term_label'] = 'per year';
        }
        return $out;
    });
    return $cached;
}

function pricing_next_key(string $name): string
{
    $base = pricing_make_key($name);
    $try = $base;
    $n = 2;
    while (db_one('SELECT id FROM landing_packages WHERE pkg_key = ?', 's', [$try])) {
        $try = substr($base, 0, 16) . $n;
        $n++;
        if ($n > 80) {
            $try = substr($base, 0, 12) . bin2hex(random_bytes(2));
            break;
        }
    }
    return $try;
}

function pricing_display_currency(): string
{
    $raw = strtoupper(trim((string) ($_GET['ccy'] ?? $_COOKIE['vellisys_ccy'] ?? 'UGX')));
    $code = isset(pricing_currencies()[$raw]) ? $raw : 'UGX';
    pricing_persist_currency($code);
    return $code;
}

function pricing_persist_currency(string $code): void
{
    if (!isset(pricing_currencies()[$code])) {
        return;
    }
    $_COOKIE['vellisys_ccy'] = $code;
    if (!headers_sent()) {
        setcookie('vellisys_ccy', $code, [
            'expires' => time() + 86400 * 400,
            'path' => '/',
            'samesite' => 'Lax',
        ]);
    }
}

/** Pesapal live checkout currencies. Rwanda francs display on the site; the charge is UGX. */
function pricing_pay_currency(string $display): string
{
    return $display === 'RWF' ? 'UGX' : $display;
}

function checkout_plan_url(string $plan, string $publicId = '', bool $pay = false): string
{
    $query = ['plan' => $plan];
    if ($publicId !== '') {
        $query['o'] = $publicId;
    }
    if ($pay) {
        $query['pay'] = '1';
    }
    return 'checkout.php?' . http_build_query($query);
}

function pricing_staff_label(int $seats): string
{
    return 'Up to ' . max(1, $seats) . ' user' . (max(1, $seats) === 1 ? '' : 's');
}

function pricing_convert_ugx(float $ugx, string $currency): float
{
    $rates = pricing_ugx_rates();
    $rate = (float) ($rates[$currency] ?? 1);
    if ($rate <= 0) {
        $rate = 1;
    }
    $amount = $ugx / $rate;
    $decimals = (int) (pricing_currencies()[$currency]['decimals'] ?? 0);
    return $decimals === 0 ? (float) round($amount) : round($amount, 2);
}

function pricing_format(float $ugx, string $currency): string
{
    $meta = pricing_currencies()[$currency] ?? pricing_currencies()['UGX'];
    $amount = pricing_convert_ugx($ugx, $currency);
    $decimals = (int) $meta['decimals'];
    $num = number_format($amount, $decimals, '.', ',');
    return $currency . ' ' . $num;
}

function pricing_discount_ends_at(): DateTimeImmutable
{
    $section = pricing_section();
    $days = (int) $section['countdown_days'];
    $hours = (int) $section['countdown_hours'];
    $now = desk_now();
    return $now->setTime(0, 0, 0)->modify('+' . $days . ' days')->modify('+' . $hours . ' hours');
}

function pricing_countdown_parts(?DateTimeImmutable $end = null): array
{
    $end = $end ?? pricing_discount_ends_at();
    $remain = max(0, $end->getTimestamp() - desk_now()->getTimestamp());
    return [
        'days' => intdiv($remain, 86400),
        'hours' => intdiv($remain % 86400, 3600),
        'mins' => intdiv($remain % 3600, 60),
        'secs' => $remain % 60,
    ];
}

function pricing_countdown_payload(): array
{
    $end = pricing_discount_ends_at();
    return [
        'iso' => $end->format(DateTimeInterface::ATOM),
        'ms' => (int) $end->format('U') * 1000,
    ];
}

function render_landing_pricing(): void
{
    $ccy = pricing_display_currency();
    $section = pricing_section();
    $packages = pricing_packages();
    if (!$packages) {
        return;
    }
    $clock = pricing_countdown_payload();
    $parts = pricing_countdown_parts();
    $pad = static fn (int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $lead = str_replace(
        '{currency}',
        '<strong data-pricing-ccy-label>' . h($ccy) . '</strong>',
        h($section['lead'])
    );
    $registerLabel = trim((string) $section['register_label']);
    $registerLink = $registerLabel !== ''
        ? '<a href="' . h(url('register.php')) . '">' . h($registerLabel) . '</a>'
        : '';
    $demoLink = '<a href="' . h(url('demo.php')) . '">book a demo</a>';
    $register = str_replace(
        ['{register}', '{demo}'],
        [$registerLink, $demoLink],
        h($section['register_copy'])
    );
    $showClock = ((int) $section['countdown_days'] + (int) $section['countdown_hours']) > 0;
    ?>
    <section class="lp-pricing" id="pricing" data-reveal data-pricing data-ccy="<?= h($ccy) ?>" data-rates="<?= h(json_encode(pricing_ugx_rates())) ?>" data-currencies="<?= h(json_encode(pricing_currencies())) ?>" data-discount-end="<?= h($clock['iso']) ?>" data-discount-days="<?= (int) $section['countdown_days'] ?>" data-discount-hours="<?= (int) $section['countdown_hours'] ?>">
      <?php if (trim((string) $section['kicker']) !== ''): ?>
        <p class="lp-kicker"><?= h($section['kicker']) ?></p>
      <?php endif; ?>
      <?php if (trim((string) $section['heading']) !== ''): ?>
        <h2><?= h($section['heading']) ?></h2>
      <?php endif; ?>
      <?php if (trim((string) $section['lead']) !== ''): ?>
        <p class="lp-pricing-lead"><?= $lead ?></p>
      <?php endif; ?>
      <?php if ($showClock): ?>
      <div class="lp-pricing-clock" data-discount-clock>
        <p class="lp-clock-label"><?= h($section['clock_label']) ?></p>
        <div class="lp-clock-units">
          <span class="lp-clock-unit"><b data-discount-d><?= h($pad($parts['days'])) ?></b><small>days</small></span>
          <span class="lp-clock-unit"><b data-discount-h><?= h($pad($parts['hours'])) ?></b><small>hrs</small></span>
          <span class="lp-clock-unit"><b data-discount-m><?= h($pad($parts['mins'])) ?></b><small>min</small></span>
          <span class="lp-clock-unit"><b data-discount-s><?= h($pad($parts['secs'])) ?></b><small>sec</small></span>
        </div>
      </div>
      <?php endif; ?>
      <div class="lp-price-grid">
        <?php foreach ($packages as $pkg): ?>
          <article class="lp-price-card<?= !empty($pkg['popular']) ? ' is-popular' : '' ?>">
            <header class="lp-price-head">
              <?php if (!empty($pkg['popular']) && trim((string) $pkg['ribbon']) !== ''): ?>
                <p class="lp-price-ribbon"><?= h($pkg['ribbon']) ?></p>
              <?php endif; ?>
              <?php if (trim((string) $pkg['kicker']) !== ''): ?>
                <p class="lp-price-kicker"><?= h($pkg['kicker']) ?></p>
              <?php endif; ?>
              <h3><?= h($pkg['name']) ?></h3>
              <p class="lp-price-seats"><?= h(pricing_staff_label((int) $pkg['seats'])) ?></p>
            </header>
            <div class="lp-price-body">
              <?php if ((float) $pkg['was_ugx'] > (float) $pkg['price_ugx']): ?>
                <p class="lp-price-was" data-ugx="<?= (int) $pkg['was_ugx'] ?>"><?= h(pricing_format((float) $pkg['was_ugx'], $ccy)) ?></p>
              <?php endif; ?>
              <p class="lp-price-now"><strong data-ugx="<?= (int) $pkg['price_ugx'] ?>"><?= h(pricing_format((float) $pkg['price_ugx'], $ccy)) ?></strong><span><?= h($section['term_label']) ?></span></p>
              <?php if (trim((string) $pkg['lead']) !== ''): ?>
                <p class="lp-price-lead"><?= h(pricing_swap_legacy_names($pkg['lead'], $packages)) ?></p>
              <?php endif; ?>
              <?php if (!empty($pkg['points'])): ?>
              <ul>
                <?php foreach (pricing_display_points($pkg, $packages) as $point): ?>
                  <li><?= h($point) ?></li>
                <?php endforeach; ?>
              </ul>
              <?php endif; ?>
              <div class="lp-price-actions">
                <a class="lp-btn <?= !empty($pkg['popular']) ? 'lp-btn-solid' : 'lp-btn-ghost' ?>" href="<?= h(url('checkout.php?plan=' . $pkg['key'])) ?>"><?= h(pricing_display_cta($pkg, $packages)) ?></a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <p class="lp-pricing-stock">Need stock management in the package? It is an add-on of <strong data-ugx="<?= (int) pricing_stock_addon_solo_ugx() ?>"><?= h(pricing_format(pricing_stock_addon_solo_ugx(), $ccy)) ?></strong> on a single-branch package and <strong data-ugx="<?= (int) pricing_stock_addon_multi_ugx() ?>"><?= h(pricing_format(pricing_stock_addon_multi_ugx(), $ccy)) ?></strong> where the package has more than one branch, on any package you pick. Tick it when you proceed to checkout.</p>
      <?php if (trim((string) $section['register_copy']) !== ''): ?>
        <p class="lp-pricing-register"><?= $register ?></p>
      <?php endif; ?>
      <div class="lp-cta lp-cta-band lp-join-cta">
        <a class="lp-btn lp-btn-ghost" href="<?= h(url('register.php')) ?>">Request a desk</a>
        <a class="lp-btn lp-btn-ghost" href="<?= h(url('demo.php')) ?>">Book a demo</a>
      </div>
    </section>
    <?php
}
