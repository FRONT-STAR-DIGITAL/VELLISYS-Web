<?php
declare(strict_types=1);

function pricing_currencies(): array
{
    return [
        'UGX' => ['label' => 'UGX', 'name' => 'Uganda shilling', 'decimals' => 0],
        'KES' => ['label' => 'KES', 'name' => 'Kenya shilling', 'decimals' => 0],
        'USD' => ['label' => 'USD', 'name' => 'US dollar', 'decimals' => 2],
        'EUR' => ['label' => 'EUR', 'name' => 'Euro', 'decimals' => 2],
        'GBP' => ['label' => 'Pound', 'name' => 'Pound sterling', 'decimals' => 2],
        'RWF' => ['label' => 'Rwanda', 'name' => 'Rwanda franc', 'decimals' => 0],
    ];
}

/** Average UGX per 1 unit of that currency. */
function pricing_ugx_rates(): array
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

function pricing_packages(): array
{
    return [
        'solo' => [
            'key' => 'solo',
            'name' => 'Solo',
            'kicker' => 'Starting package',
            'seats' => 1,
            'price_ugx' => 150000,
            'was_ugx' => 200000,
            'cta' => 'Select Solo',
            'lead' => 'One login. The books in your colours. Enough for a founder who writes every sheet.',
            'points' => [
                '1 company admin login',
                'Branded quotations, invoices and receipts',
                'Clients, debtors and share by email or WhatsApp',
                'Print and PDF from the browser',
                'Reports for the person who signs in',
            ],
        ],
        'studio' => [
            'key' => 'studio',
            'name' => 'Studio',
            'kicker' => 'Most companies',
            'popular' => true,
            'seats' => 2,
            'price_ugx' => 200000,
            'was_ugx' => 280000,
            'cta' => 'Select Studio',
            'lead' => 'The common desk: two seats, access levels, and the full sales loop.',
            'points' => [
                '2 logins: company admin plus one',
                'Access levels: Books or Sales',
                'Everything in Solo',
                'Expenses, creditors and delivery notes',
                'Headed correspondence from the company mailbox',
            ],
        ],
        'practice' => [
            'key' => 'practice',
            'name' => 'Practice',
            'kicker' => 'Full house',
            'seats' => 3,
            'price_ugx' => 250000,
            'was_ugx' => 350000,
            'cta' => 'Select Practice',
            'lead' => 'Three seats, access levels, and every document the desk can print.',
            'points' => [
                '3 logins: admin plus two',
                'Access levels for each extra seat',
                'Everything in Studio',
                'Custom documents and all letter layouts',
                'Priority onboarding from Vellisys',
            ],
        ],
    ];
}

function pricing_package(string $key): ?array
{
    return pricing_packages()[$key] ?? null;
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
    $now = desk_now();
    return $now->setTime(0, 0, 0)->modify('+3 days');
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
    $clock = pricing_countdown_payload();
    ?>
    <section class="lp-pricing" id="pricing" data-reveal data-pricing data-ccy="<?= h($ccy) ?>" data-rates="<?= h(json_encode(pricing_ugx_rates())) ?>" data-currencies="<?= h(json_encode(pricing_currencies())) ?>" data-discount-end="<?= h($clock['iso']) ?>">
      <p class="lp-kicker">Packages</p>
      <h2>Onboard as the discount lasts</h2>
      <p class="lp-pricing-lead">First year, in <strong data-pricing-ccy-label><?= h($ccy) ?></strong>. Change currency in the header. Pay, then a Vellisys admin contacts you to open the desk.</p>
      <p class="lp-pricing-clock" data-discount-clock>Discount resets in <b data-discount-h>00</b>:<b data-discount-m>00</b>:<b data-discount-s>00</b></p>
      <div class="lp-price-grid">
        <?php foreach (pricing_packages() as $pkg): ?>
          <article class="lp-price-card<?= !empty($pkg['popular']) ? ' is-popular' : '' ?>">
            <?php if (!empty($pkg['popular'])): ?><p class="lp-price-ribbon">Most companies</p><?php endif; ?>
            <p class="lp-price-kicker"><?= h($pkg['kicker']) ?></p>
            <h3><?= h($pkg['name']) ?></h3>
            <p class="lp-price-was" data-ugx="<?= (int) $pkg['was_ugx'] ?>"><?= h(pricing_format((float) $pkg['was_ugx'], $ccy)) ?></p>
            <p class="lp-price-now"><strong data-ugx="<?= (int) $pkg['price_ugx'] ?>"><?= h(pricing_format((float) $pkg['price_ugx'], $ccy)) ?></strong><span>first year</span></p>
            <p class="lp-price-seats"><?= (int) $pkg['seats'] ?> login<?= $pkg['seats'] === 1 ? '' : 's' ?></p>
            <p class="lp-price-lead"><?= h($pkg['lead']) ?></p>
            <ul>
              <?php foreach ($pkg['points'] as $point): ?>
                <li><?= h($point) ?></li>
              <?php endforeach; ?>
            </ul>
            <a class="lp-btn lp-btn-solid" href="<?= h(url('checkout.php?plan=' . $pkg['key'])) ?>"><?= h($pkg['cta']) ?></a>
          </article>
        <?php endforeach; ?>
      </div>
      <p class="lp-pricing-register">Prefer a call first? <a href="<?= h(url('register.php')) ?>">Register without paying</a> - a Vellisys admin contacts you to onboard.</p>
    </section>
    <?php
}
