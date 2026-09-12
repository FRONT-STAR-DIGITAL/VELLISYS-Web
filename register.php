<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if (function_exists('record_site_visit')) {
    record_site_visit();
}
if ($user = current_user()) {
    redirect(($user['role'] ?? '') === 'platform' ? 'admin_signups.php' : 'dashboard.php');
}

$token = trim((string) ($_GET['t'] ?? post('t', '', 64)));
$order = $token !== '' ? order_by_onboard_token($token) : null;
if ($order && ($order['status'] ?? '') !== 'paid') {
    $order = null;
}
if ($token === '' || !$order) {
    join_boot('register');
}
if ($order) {
    $order = provision_paid_order($order);
}

$error = '';
$ready = false;
if ($order) {
    $cid = (int) ($order['company_id'] ?? 0);
    $hasAdmin = $cid > 0 && db_one("SELECT id FROM users WHERE company_id = ? AND role = 'admin'", 'i', [$cid]);
    $ready = (bool) $hasAdmin;
}

form_mark_open('register');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order && !$ready) {
    if (!csrf_valid()) {
        $error = 'Your session expired. Please submit the form again.';
    } elseif (form_is_spam('register', 1)) {
        redirect('login.php');
    } elseif (form_rate_blocked('register', 8)) {
        $error = 'Please wait a bit before trying again.';
    } else {
        $pass = post('password', '', 256);
        $again = post('password_confirm', '', 256);
        if ($pass === '' || strlen($pass) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($pass !== $again) {
            $error = 'The two passwords do not match.';
        } else {
            form_rate_hit('register');
            $made = complete_self_onboard($order, [
                'name' => post('contact_name', '', 80),
                'email' => strtolower(post('contact_email', '', 190)),
                'password' => $pass,
            ]);
            if (!empty($made['ok'])) {
                flash('Desk login created. Sign in with the email and password you chose.');
                redirect('login.php?email=' . rawurlencode((string) $made['email']));
            }
            $error = (string) ($made['error'] ?? 'Could not create that login.');
            $ready = !empty($made['ready']);
        }
    }
}

$pkg = $order ? pricing_package((string) $order['plan']) : null;
?>
<!DOCTYPE html>
<html lang="en" data-sw="<?= h(url('sw.js')) ?>" data-pwa-login="<?= h(url('login.php')) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= $order ? 'Set up your desk' : 'Set up your desk' ?> · <?= h(product_name()) ?></title>
  <?php product_icons(); ?>
  <?php folio_landing_head(); ?>
</head>
<body class="gate gate-register">
<div class="gate-shell">
  <?php gate_art(
      'Your desk is paid. Choose how you sign in.',
      'After Pesapal confirms payment, this page is where you set the admin email and password for the company.',
      '',
      [
          'heading_html' => 'Your desk is paid.<br><em>Choose how you sign in.</em>',
      ]
  ); ?>
  <main class="gate-panel">
    <img class="gate-panel-mark" src="<?= h(product_v_mark_url()) ?>" alt="" decoding="async">
    <?php render_gate_home(); ?>
    <div class="gate-stack">
    <?php render_gate_card_mark(); ?>
    <?php if ($ready): ?>
      <div class="gate-box gate-ok">
        <img class="gate-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <h2>This desk already has a login</h2>
        <p class="gate-lead">Sign in with the admin email and password you created for <?= h((string) ($order['company'] ?? 'your company')) ?>.</p>
        <a class="gate-submit" href="<?= h(url('login.php?email=' . rawurlencode((string) ($order['email'] ?? '')))) ?>">Sign in</a>
        <?php render_gate_legal(); ?>
      </div>
    <?php else: ?>
      <form class="gate-box" method="post" action="<?= h(url('register.php?t=' . rawurlencode($token))) ?>" autocomplete="off">
        <?= csrf_field() ?>
        <?= form_honeypot_field() ?>
        <input type="hidden" name="t" value="<?= h($token) ?>">
        <img class="gate-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <h2>Create your admin login</h2>
        <p class="gate-lead">
          <?= h((string) ($order['company'] ?? 'Your company')) ?> paid for <?= h($pkg['name'] ?? 'a desk') ?>.
          Set the name, email and password you will use to sign in. Enter the password twice.
        </p>
        <?php if ($error): ?><p class="lp-err"><?= h($error) ?></p><?php endif; ?>
        <label class="gate-field" for="contact_name">Admin name
          <input id="contact_name" name="contact_name" required maxlength="80" autocomplete="name" value="<?= h(post('contact_name') ?: (string) ($order['name'] ?? '')) ?>" placeholder="Jane Okello">
        </label>
        <label class="gate-field" for="contact_email">Sign-in email
          <input id="contact_email" name="contact_email" type="email" required maxlength="190" autocomplete="username" value="<?= h(post('contact_email') ?: (string) ($order['email'] ?? '')) ?>" placeholder="accounts@company.com">
        </label>
        <label class="gate-field" for="password">Password
          <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
        </label>
        <label class="gate-field" for="password_confirm">Confirm password
          <input id="password_confirm" name="password_confirm" type="password" required minlength="8" autocomplete="new-password" placeholder="Type it again">
        </label>
        <button class="gate-submit" type="submit">Save and go to sign in <?= icon('arrow-right', 18) ?></button>
        <?php render_gate_legal(); ?>
      </form>
    <?php endif; ?>
    </div>
  </main>
</div>
<?php public_float_widgets(); ?>
<script src="<?= h(asset('js/landing.js')) ?>" defer></script>
<script src="<?= h(asset('js/pwa.js')) ?>" defer></script>
</body>
</html>
