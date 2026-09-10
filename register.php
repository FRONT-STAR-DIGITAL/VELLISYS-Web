<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if ($user = current_user()) {
    redirect(($user['role'] ?? '') === 'platform' ? 'admin_signups.php' : 'dashboard.php');
}

$error = '';
$ok = isset($_GET['ok']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = post('contact_name');
    $company = post('company_name');
    $email = strtolower(post('contact_email'));
    $phone = post('contact_phone');
    if ($name === '' || $company === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Your name, company and a valid email are enough - please fill those in.';
    } elseif (db_one('SELECT id FROM users WHERE email = ?', 's', [$email])) {
        $error = 'That email already has a Vellisys login. Sign in, or use another mailbox.';
    } elseif (db_one("SELECT id FROM signups WHERE email = ? AND status IN ('new','contacted')", 's', [$email])) {
        $error = 'We already have this request. A Vellisys admin will call you.';
    } else {
        db_exec(
            'INSERT INTO signups (name, company, email, phone, status, source) VALUES (?,?,?,?,?,?)',
            'ssssss',
            [$name, $company, $email, $phone, 'new', 'register']
        );
        $signup = [
            'name' => $name,
            'company' => $company,
            'email' => $email,
            'phone' => $phone,
            'source' => 'register',
        ];
        folio_redirect_then('register.php?ok=1', static function () use ($signup): void {
            notify_admin_signup($signup);
        });
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-sw="<?= h(url('sw.js')) ?>" data-pwa-login="<?= h(url('login.php')) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Register · <?= h(product_name()) ?></title>
  <?php product_icons(); ?>
  <?php folio_landing_head(); ?>
</head>
<body class="gate">
<div class="gate-shell">
  <?php gate_art(
      'A desk for the company. Live from anywhere.',
      'Anywhere in the world: register, request a quote, get onboarded. Then quotations, invoices and receipts sit on one desk you can open from anywhere.',
      'Already a member? <a href="' . h(url('login.php')) . '">Log in</a>'
  ); ?>
  <main class="gate-panel">
    <?php if ($ok): ?>
      <div class="gate-box gate-ok">
        <img class="gate-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <h2><em>We have</em> your request</h2>
        <p class="gate-lead">We sent a confirmation to your email from <?= h(product_email()) ?>. A Vellisys admin will contact you to onboard the company and open the desk. No password yet - you get one when the company goes live.</p>
        <a class="gate-submit" href="<?= h(url()) ?>" data-pwa-home="<?= h(url('login.php')) ?>">Back to Vellisys</a>
      </div>
    <?php else: ?>
      <form class="gate-box" method="post" action="<?= h(url('register.php')) ?>" autocomplete="off">
        <?= csrf_field() ?>
        <img class="gate-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <h2><em>Sign up</em> - we contact you to onboard</h2>
        <p class="gate-lead">Four fields. This form does not take payment. A Vellisys admin will contact you to onboard the company. You get a password when the desk goes live. To pay now, <a href="<?= h(url()) ?>#pricing">pick a package</a>. Or <a href="<?= h(url('quote.php')) ?>">request a quote</a>.</p>
        <?php if ($error): ?><p class="lp-err"><?= h($error) ?></p><?php endif; ?>
        <label class="gate-field" for="contact_name">Your name
          <input id="contact_name" name="contact_name" required autocomplete="name" value="<?= h(post('contact_name')) ?>" placeholder="Jane Okello">
        </label>
        <label class="gate-field" for="company_name">Company
          <input id="company_name" name="company_name" required autocomplete="organization" value="<?= h(post('company_name')) ?>" placeholder="Okello Traders Ltd">
        </label>
        <label class="gate-field" for="contact_email">Email
          <input id="contact_email" name="contact_email" type="email" required autocomplete="email" value="<?= h(post('contact_email')) ?>" placeholder="accounts@company.com">
        </label>
        <label class="gate-field" for="contact_phone">Phone
          <input id="contact_phone" name="contact_phone" type="tel" required autocomplete="tel" value="<?= h(post('contact_phone')) ?>" placeholder="+254 700 000 000">
        </label>
        <button class="gate-submit" type="submit">Register</button>
        <p class="gate-switch">Already a member? <a href="<?= h(url('login.php')) ?>">Log in</a></p>
        <p class="gate-home"><a href="<?= h(url()) ?>">Back to <?= h(product_name()) ?></a></p>
      </form>
    <?php endif; ?>
  </main>
</div>
<?php public_float_widgets(); ?>
<script src="<?= h(asset('js/landing.js')) ?>" defer></script>
<script src="<?= h(asset('js/pwa.js')) ?>" defer></script>
</body>
</html>
