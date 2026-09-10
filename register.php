<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if ($user = current_user()) {
    redirect(($user['role'] ?? '') === 'platform' ? 'admin_signups.php' : 'dashboard.php');
}

$error = '';
$ok = isset($_GET['ok']);
$pendingMail = $ok ? take_pending_signup_mail() : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $error = 'Your session expired. Please submit the form again.';
    } else {
        $made = record_website_signup('register');
        if (!empty($made['ok'])) {
            $_SESSION['signup_notify'] = $made['signup'];
            redirect('register.php?ok=1');
        }
        $error = (string) ($made['error'] ?? 'We could not save that just now. Please try again in a moment.');
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
      'Register, get onboarded, then quotations, invoices and receipts sit on one desk you can open from anywhere.',
      '',
      [
          'kicker' => 'Documents simplified',
          'heading_html' => 'A desk for the company.<br><em>Live from anywhere.</em>',
          'tag' => 'Simple tools. Real progress.',
      ]
  ); ?>
  <main class="gate-panel">
    <img class="gate-panel-mark" src="<?= h(product_v_mark_url()) ?>" alt="" decoding="async">
    <div class="gate-stack">
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
        <h2>Create your account</h2>
        <p class="gate-lead">Four fields. This form does not take payment. A Vellisys admin will contact you to onboard the company. You get a password when the desk goes live.</p>
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
        <button class="gate-submit" type="submit">Create Account <?= icon('arrow-right', 18) ?></button>
        <p class="gate-or"><span>or</span></p>
        <a class="gate-alt" href="<?= h(url('login.php')) ?>">Sign In</a>
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
<?php send_pending_signup_mail($pendingMail); ?>
