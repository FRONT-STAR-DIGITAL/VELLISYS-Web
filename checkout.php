<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if ($user = current_user()) {
    redirect(($user['role'] ?? '') === 'platform' ? 'admin_signups.php' : 'dashboard.php');
}

$planKey = strtolower(trim((string) ($_GET['plan'] ?? post('plan'))));
$pkg = pricing_package($planKey);
if (!$pkg) {
    redirect('index.php#pricing');
}
$ccy = pricing_display_currency();
$error = '';
form_mark_open('checkout');
$orderPublic = (string) ($_GET['o'] ?? post('public_id', '', 64));
$existing = $orderPublic !== '' ? order_by_public($orderPublic) : null;
if ($existing && (string) ($existing['plan'] ?? '') !== $pkg['key']) {
    $existing = null;
}

$take = static function (string $key, string $fallback = '') use (&$existing): string {
    $posted = post($key);
    if ($posted !== '') {
        return $posted;
    }
    if ($existing && isset($existing[$key]) && (string) $existing[$key] !== '') {
        return (string) $existing[$key];
    }
    return $fallback;
};

$maybeNotifyDraft = static function (?array $row): void {
    if (!$row || (int) ($row['notified_draft'] ?? 0) !== 0) {
        return;
    }
    if (!filter_var((string) ($row['email'] ?? ''), FILTER_VALIDATE_EMAIL) || trim((string) ($row['company'] ?? '')) === '') {
        return;
    }
    attach_order_signup($row, 'draft');
    notify_admin_order($row, 'draft');
    db_exec('UPDATE website_orders SET notified_draft=1 WHERE id=?', 'i', [(int) $row['id']]);
};

$beginHostedPay = static function (array $order, string $action) use ($pkg): array {
    $reuse = order_hosted_pay_url($order);
    if ($reuse !== '' && $action !== 'repay') {
        return ['ok' => true, 'order' => $order, 'redirect' => $reuse];
    }
    if ($action === 'repay' || in_array((string) ($order['status'] ?? ''), ['failed', 'cancelled'], true)) {
        $order = order_rotate_merchant_ref($order);
    }
    $pay = start_pesapal_payment($order);
    if (empty($pay['ok'])) {
        $fresh = apply_order_payment_status($order, 'failed', (string) ($pay['error'] ?? 'Payment did not start.'));
        return [
            'ok' => false,
            'order' => $fresh,
            'error' => 'Payment did not start: ' . (string) ($pay['error'] ?? 'The processor is unavailable.') . ' We have your details and will contact you.',
        ];
    }
    $fresh = db_one('SELECT * FROM website_orders WHERE id = ?', 'i', [(int) $order['id']]) ?: $order;
    return ['ok' => true, 'order' => $fresh, 'redirect' => (string) $pay['redirect']];
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action', 'pay', 20) ?: 'pay';
    if (!csrf_valid()) {
        if ($action === 'draft') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'session']);
            exit;
        }
        $error = 'Your session expired. Please submit the form again.';
    } elseif (form_is_spam('checkout', 0)) {
        if ($action === 'draft') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'public_id' => '']);
            exit;
        }
        $error = 'Please wait a moment and try again.';
    } elseif (form_rate_blocked($action === 'draft' ? 'checkout_draft' : 'checkout', $action === 'draft' ? 40 : 8)) {
        if ($action === 'draft') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'wait']);
            exit;
        }
        $error = 'Please wait a bit before sending another payment.';
    } else {
        form_rate_hit($action === 'draft' ? 'checkout_draft' : 'checkout');
        $payload = [
            'plan' => $pkg['key'],
            'currency' => $ccy,
            'name' => post('contact_name', '', 80),
            'company' => post('company_name', '', 160),
            'email' => strtolower(post('contact_email', '', 190)),
            'phone' => post('contact_phone', '', 40),
            'city' => post('city', '', 80),
            'country' => post('country', '', 80),
            'status' => $action === 'draft' ? 'draft' : 'pending',
        ];
        $id = $existing ? (int) $existing['id'] : 0;
        if ($id === 0 && post('public_id') !== '') {
            $found = order_by_public(post('public_id'));
            $id = $found ? (int) $found['id'] : 0;
        }
        $saved = ['order' => null];

        try {
            if ($action === 'draft') {
                $saved = save_website_order($payload, $id ?: null);
                $row = $saved['order'] ?? null;
                $maybeNotifyDraft($row);
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'public_id' => $row['public_id'] ?? '']);
                exit;
            }

            if ($payload['name'] === '' || $payload['company'] === '' || !filter_var($payload['email'], FILTER_VALIDATE_EMAIL) || $payload['phone'] === '') {
                $payload['status'] = 'draft';
                $saved = save_website_order($payload, $id ?: null);
                $maybeNotifyDraft($saved['order'] ?? null);
                $error = 'Name, company, email and phone are required before you pay. We kept what you typed.';
            } else {
                $saved = save_website_order($payload, $id ?: null);
                $order = $saved['order'] ?? null;
                if (!$order) {
                    $error = 'Could not save those details. Try again.';
                } else {
                    attach_order_signup($order, 'pending');
                    $started = $beginHostedPay($order, $action);
                    $existing = $started['order'] ?? $order;
                    if (empty($started['ok'])) {
                        $error = (string) ($started['error'] ?? 'Payment did not start.');
                    } else {
                        $payTo = pesapal_hosted_url((string) ($started['redirect'] ?? ''));
                        if ($payTo === '') {
                            $error = 'Payment did not start: the processor did not return a secure page.';
                        } else {
                            $mailOrder = $existing;
                            folio_redirect_then($payTo, static function () use ($mailOrder): void {
                                try {
                                    notify_admin_order($mailOrder, 'pending');
                                } catch (Throwable $e) {
                                    error_log('Vellisys checkout mail: ' . $e->getMessage());
                                }
                            });
                        }
                    }
                }
            }
            if (!empty($saved['order']['id'])) {
                $existing = db_one('SELECT * FROM website_orders WHERE id = ?', 'i', [(int) $saved['order']['id']]) ?: $saved['order'];
            }
        } catch (Throwable $e) {
            error_log('Vellisys checkout: ' . $e->getMessage());
            $error = 'We could not save that just now. Import the Hostinger SQL dump, then try again.';
        }
    }
}

if ($existing && ($existing['status'] ?? '') === 'paid') {
    $existing = refresh_order_from_pesapal($existing);
    $existing = provision_paid_order($existing);
}

$payUrl = order_hosted_pay_url($existing);
$wantPay = isset($_GET['pay']) && (string) $_GET['pay'] !== '0' && (string) $_GET['pay'] !== '';
$step = 'details';
if ($existing && ($existing['status'] ?? '') === 'paid') {
    $step = 'done';
} elseif ($wantPay && $existing && $error === '') {
    if ($payUrl === '') {
        $started = $beginHostedPay($existing, 'pay');
        $existing = $started['order'] ?? $existing;
        $payUrl = pesapal_hosted_url((string) ($started['redirect'] ?? '')) ?: order_hosted_pay_url($existing);
        if (empty($started['ok'])) {
            $error = (string) ($started['error'] ?? 'Payment did not start.');
        }
    }
    if ($payUrl !== '') {
        redirect($payUrl);
    } elseif ($error === '') {
        $error = 'The payment page is not ready yet. Submit the company details again.';
    }
}

$priceNow = pricing_format((float) $pkg['price_ugx'], $ccy);
$priceWas = pricing_format((float) $pkg['was_ugx'], $ccy);
$payCcy = pricing_pay_currency($ccy);
$payNow = pricing_format((float) $pkg['price_ugx'], $payCcy);
$termLabel = pricing_section()['term_label'];
$seats = (int) $pkg['seats'];
$seatLabel = pricing_staff_label($seats);
$formAction = url(checkout_plan_url($pkg['key'], (string) ($existing['public_id'] ?? '')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pkg['name']) ?> · <?= h(product_name()) ?></title>
  <?php product_icons(); ?>
  <?php folio_landing_head(); ?>
</head>
<body class="lp lp-checkout-page">
  <?php public_header('checkout'); ?>
  <main class="lp-checkout<?= $step === 'done' ? ' lp-checkout-done' : '' ?>" data-pricing data-ccy="<?= h($ccy) ?>" data-rates="<?= h(json_encode(pricing_ugx_rates())) ?>" data-currencies="<?= h(json_encode(pricing_currencies())) ?>">
    <ol class="lp-check-steps" aria-label="Checkout">
      <li class="<?= $step === 'details' ? 'is-current' : 'is-done' ?>"><b>1</b><span>Company</span></li>
      <li class="<?= $step === 'pay' ? 'is-current' : ($step === 'done' ? 'is-done' : '') ?>"><b>2</b><span>Pay</span></li>
    </ol>

    <?php if ($step === 'done'): ?>
      <div class="lp-checkout-copy">
        <p class="lp-kicker">Paid</p>
        <h1>We have your payment</h1>
        <p>Thank you. <strong><?= h((string) ($existing['company'] ?? '')) ?></strong> paid for <?= h($pkg['name']) ?>. Set the admin email and password you will use, then sign in. We emailed the same link to <?= h((string) ($existing['email'] ?? '')) ?> from <?= h(product_email()) ?>.</p>
        <div class="lp-cta">
          <?php if (trim((string) ($existing['onboard_token'] ?? '')) !== ''): ?>
            <a class="lp-btn lp-btn-solid" href="<?= h(url('register.php?t=' . rawurlencode((string) $existing['onboard_token']))) ?>">Set up your desk</a>
          <?php endif; ?>
          <a class="lp-btn lp-btn-ghost" href="<?= h(url('login.php')) ?>">Sign in</a>
        </div>
      </div>
    <?php else: ?>
      <aside class="lp-checkout-copy">
        <?php if (trim((string) $pkg['kicker']) !== ''): ?>
          <p class="lp-kicker"><?= h($pkg['kicker']) ?></p>
        <?php elseif (!empty($pkg['popular'])): ?>
          <p class="lp-kicker"><?= h((string) ($pkg['ribbon'] !== '' ? $pkg['ribbon'] : 'Most companies')) ?></p>
        <?php else: ?>
          <p class="lp-kicker">Package</p>
        <?php endif; ?>
        <h1><?= h($pkg['name']) ?></h1>
        <p class="lp-checkout-price">
          <?php if ((float) $pkg['was_ugx'] > (float) $pkg['price_ugx']): ?>
            <s data-ugx="<?= (int) $pkg['was_ugx'] ?>"><?= h($priceWas) ?></s>
          <?php endif; ?>
          <strong data-ugx="<?= (int) $pkg['price_ugx'] ?>"><?= h($priceNow) ?></strong>
          <span><?= h($termLabel) ?></span>
        </p>
        <p class="lp-check-seats"><?= h($seatLabel) ?> · billed <?= h($termLabel) ?></p>
        <p><?= h(pricing_swap_legacy_names($pkg['lead'])) ?> After payment confirms you set your own admin email and password, then sign in and finish branding on Settings.</p>
        <details class="lp-check-points" open>
          <summary>What is included</summary>
          <ul>
            <?php foreach (pricing_display_points($pkg, pricing_packages()) as $point): ?>
              <li><?= h($point) ?></li>
            <?php endforeach; ?>
          </ul>
        </details>
        <p class="lp-check-stay">Continue to pay opens Pesapal in this tab so you can type a phone number or pick a card. When you finish, you return to Vellisys.</p>
        <p class="lp-check-switch"><a href="<?= h(url('index.php#pricing')) ?>">Change package</a></p>
      </aside>

      <form class="lp-checkout-form" method="post" action="<?= h($formAction) ?>" data-checkout-form>
          <?= csrf_field() ?>
          <?= form_honeypot_field() ?>
          <input type="hidden" name="plan" value="<?= h($pkg['key']) ?>">
          <input type="hidden" name="action" value="pay">
          <input type="hidden" name="public_id" value="<?= h((string) ($existing['public_id'] ?? '')) ?>" data-order-public>
          <h2>Company details</h2>
          <p class="lp-checkout-hint">Then continue to Pesapal to pay <span data-ugx="<?= (int) $pkg['price_ugx'] ?>"><?= h($payNow) ?></span><?= $payCcy !== $ccy ? ' (charged in ' . h($payCcy) . ')' : '' ?> in this tab. We email you that payment awaits. <?= h(product_email()) ?> is copied, and is notified if payment fails.</p>
          <?php if ($error): ?><p class="lp-err"><?= h($error) ?></p><?php endif; ?>
          <div class="lp-check-fields">
            <label for="contact_name">Your name
              <input id="contact_name" name="contact_name" required maxlength="80" autocomplete="name" value="<?= h($take('name', $take('contact_name'))) ?>" placeholder="Jane Okello">
            </label>
            <label for="company_name">Company
              <input id="company_name" name="company_name" required maxlength="160" autocomplete="organization" value="<?= h($take('company', $take('company_name'))) ?>" placeholder="Okello Traders Ltd">
            </label>
            <label for="contact_email">Email
              <input id="contact_email" name="contact_email" type="email" required maxlength="190" autocomplete="email" value="<?= h($take('email', $take('contact_email'))) ?>" placeholder="accounts@company.com">
            </label>
            <label for="contact_phone">Phone
              <input id="contact_phone" name="contact_phone" required maxlength="40" autocomplete="tel" value="<?= h($take('phone', $take('contact_phone'))) ?>" placeholder="+256 700 000 000">
            </label>
            <label for="city">City <span>(optional)</span>
              <input id="city" name="city" maxlength="80" autocomplete="address-level2" value="<?= h($take('city')) ?>" placeholder="Kampala">
            </label>
            <label for="country">Country <span>(optional)</span>
              <input id="country" name="country" maxlength="80" autocomplete="country-name" list="checkout-countries" value="<?= h($take('country')) ?>" placeholder="Uganda">
            </label>
          </div>
          <datalist id="checkout-countries">
            <option value="Uganda">
            <option value="Kenya">
            <option value="Tanzania">
            <option value="Rwanda">
            <option value="Burundi">
            <option value="South Sudan">
            <option value="Democratic Republic of the Congo">
            <option value="Nigeria">
            <option value="Ghana">
            <option value="South Africa">
            <option value="United Kingdom">
            <option value="United States">
          </datalist>
          <button class="lp-btn lp-btn-solid lp-btn-lg" type="submit" data-pay-btn data-ugx="<?= (int) $pkg['price_ugx'] ?>">Continue to pay <?= h($payNow) ?></button>
          <p class="lp-checkout-note">Prefer we onboard you? <a href="<?= h(url('register.php')) ?>">Register without paying</a>. Or <a href="<?= h(url('demo.php')) ?>">book a demo</a>.</p>
        </form>
    <?php endif; ?>
  </main>
  <?php public_float_widgets(); ?>
  <script src="<?= h(asset('js/landing.js')) ?>" defer></script>
</body>
</html>
