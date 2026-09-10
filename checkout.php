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
$orderPublic = (string) ($_GET['o'] ?? post('public_id'));
$existing = $orderPublic !== '' ? order_by_public($orderPublic) : null;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action') ?: 'pay';
    $payload = [
        'plan' => $pkg['key'],
        'currency' => $ccy,
        'name' => post('contact_name'),
        'company' => post('company_name'),
        'email' => strtolower(post('contact_email')),
        'phone' => post('contact_phone'),
        'city' => post('city'),
        'country' => post('country'),
        'status' => $action === 'pay' ? 'pending' : 'draft',
    ];
    $id = $existing ? (int) $existing['id'] : 0;
    if ($id === 0 && post('public_id') !== '') {
        $found = order_by_public(post('public_id'));
        $id = $found ? (int) $found['id'] : 0;
    }
    $saved = ['order' => null];

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
            notify_admin_order($order, 'pending');
            $pay = start_pesapal_payment($order);
            if (empty($pay['ok'])) {
                $fresh = apply_order_payment_status($order, 'failed', (string) ($pay['error'] ?? 'Pesapal did not start.'));
                $error = 'Payment did not start: ' . (string) ($pay['error'] ?? 'Pesapal is unavailable.') . ' We have your details and will contact you.';
                $existing = $fresh;
            } else {
                redirect((string) $pay['redirect']);
            }
        }
    }
    if (!empty($saved['order']['id'])) {
        $existing = db_one('SELECT * FROM website_orders WHERE id = ?', 'i', [(int) $saved['order']['id']]) ?: $saved['order'];
    }
}

$priceNow = pricing_format((float) $pkg['price_ugx'], $ccy);
$priceWas = pricing_format((float) $pkg['was_ugx'], $ccy);
$payCcy = pricing_pay_currency($ccy);
$payNow = pricing_format((float) $pkg['price_ugx'], $payCcy);
$termLabel = pricing_section()['term_label'];
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
<body class="lp">
  <?php public_header('checkout'); ?>
  <main class="lp-checkout" data-pricing data-ccy="<?= h($ccy) ?>" data-rates="<?= h(json_encode(pricing_ugx_rates())) ?>" data-currencies="<?= h(json_encode(pricing_currencies())) ?>">
    <div class="lp-checkout-copy">
      <p class="lp-kicker"><?= h($pkg['kicker']) ?></p>
      <h1><?= h($pkg['name']) ?></h1>
      <p class="lp-checkout-price"><?php if ((float) $pkg['was_ugx'] > (float) $pkg['price_ugx']): ?><s data-ugx="<?= (int) $pkg['was_ugx'] ?>"><?= h($priceWas) ?></s> <?php endif; ?><strong data-ugx="<?= (int) $pkg['price_ugx'] ?>"><?= h($priceNow) ?></strong> <span><?= h($termLabel) ?></span></p>
      <p><?= h($pkg['lead']) ?> After you pay, a Vellisys admin contacts you to onboard the company. You do not get a password until the desk is opened.</p>
      <ul>
        <?php foreach ($pkg['points'] as $point): ?>
          <li><?= h($point) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <form class="lp-checkout-form" method="post" action="<?= h(url('checkout.php?plan=' . $pkg['key'])) ?>" data-checkout-form>
      <?= csrf_field() ?>
      <input type="hidden" name="plan" value="<?= h($pkg['key']) ?>">
      <input type="hidden" name="action" value="pay">
      <input type="hidden" name="public_id" value="<?= h((string) ($existing['public_id'] ?? '')) ?>" data-order-public>
      <h2>Company details</h2>
      <p class="lp-checkout-hint">Pay <span data-ugx="<?= (int) $pkg['price_ugx'] ?>"><?= h($payNow) ?></span> on Pesapal<?= $payCcy !== $ccy ? ' (charged in ' . h($payCcy) . ')' : '' ?>. We email you that payment awaits, then the unpaid invoice. <?= h(product_email()) ?> is copied, and is notified if payment fails.</p>
      <?php if ($error): ?><p class="lp-err"><?= h($error) ?></p><?php endif; ?>
      <label for="contact_name">Your name
        <input id="contact_name" name="contact_name" required autocomplete="name" value="<?= h($take('name', $take('contact_name'))) ?>" placeholder="Jane Okello">
      </label>
      <label for="company_name">Company
        <input id="company_name" name="company_name" required autocomplete="organization" value="<?= h($take('company', $take('company_name'))) ?>" placeholder="Okello Traders Ltd">
      </label>
      <label for="contact_email">Email
        <input id="contact_email" name="contact_email" type="email" required autocomplete="email" value="<?= h($take('email', $take('contact_email'))) ?>" placeholder="accounts@company.com">
      </label>
      <label for="contact_phone">Phone
        <input id="contact_phone" name="contact_phone" required autocomplete="tel" value="<?= h($take('phone', $take('contact_phone'))) ?>" placeholder="+256 700 000 000">
      </label>
      <label for="city">City <span>(optional)</span>
        <input id="city" name="city" autocomplete="address-level2" value="<?= h($take('city')) ?>" placeholder="Kampala">
      </label>
      <label for="country">Country <span>(optional)</span>
        <input id="country" name="country" autocomplete="country-name" list="checkout-countries" value="<?= h($take('country')) ?>" placeholder="Uganda">
      </label>
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
      <button class="lp-btn lp-btn-solid lp-btn-lg" type="submit" data-pay-btn data-ugx="<?= (int) $pkg['price_ugx'] ?>">Pay <?= h($payNow) ?></button>
      <p class="lp-checkout-note">Prefer to be contacted first? <a href="<?= h(url('register.php')) ?>">Register without paying</a>.</p>
    </form>
  </main>
  <?php public_float_widgets(); ?>
  <script src="<?= h(asset('js/landing.js')) ?>"></script>
</body>
</html>
