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
        $made = record_website_signup('quote', post('quote_note'));
        if (!empty($made['ok'])) {
            $_SESSION['signup_notify'] = $made['signup'];
            redirect('quote.php?ok=1');
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
  <title>Request a quote · <?= h(product_name()) ?></title>
  <?php product_icons(); ?>
  <?php folio_landing_head(); ?>
</head>
<body class="gate">
<div class="gate-shell">
  <?php gate_art(
      'Ask for a quote. We will call you.',
      'Tell us the company, we send a quote, then we onboard the desk. No password yet.',
      '',
      [
          'kicker' => 'Documents simplified',
          'heading_html' => 'Ask for a quote.<br><em>We will call you.</em>',
          'tag' => 'Simple tools. Real progress.',
      ]
  ); ?>
  <main class="gate-panel">
    <img class="gate-panel-mark" src="<?= h(product_v_mark_url()) ?>" alt="" decoding="async">
    <div class="gate-stack">
    <?php if ($ok): ?>
      <div class="gate-box gate-ok">
        <img class="gate-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <h2>We have your quote request</h2>
        <p class="gate-lead">We sent a confirmation to your email from <?= h(product_email()) ?>. A Vellisys admin will reach out with a quote and onboard your company. No password yet - you get one when the company goes live.</p>
        <a class="gate-submit" href="<?= h(url()) ?>" data-pwa-home="<?= h(url('login.php')) ?>">Back to Vellisys</a>
      </div>
    <?php else: ?>
      <form class="gate-box" method="post" action="<?= h(url('quote.php')) ?>" autocomplete="off">
        <?= csrf_field() ?>
        <img class="gate-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <h2>Request a quote</h2>
        <p class="gate-lead">Four fields, optional note. We send a quote, then we onboard you. Have a question instead? <a href="<?= h(url()) ?>#ask">Write to us</a>.</p>
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
          <input id="contact_phone" name="contact_phone" type="tel" required autocomplete="tel" value="<?= h(post('contact_phone')) ?>" placeholder="+256 779 971 024">
        </label>
        <label class="gate-field" for="quote_note">What do you need? <span class="gate-optional">optional</span>
          <textarea id="quote_note" name="quote_note" rows="4" maxlength="2000" placeholder="How many people, which documents, when you want to start"><?= h(post('quote_note')) ?></textarea>
        </label>
        <button class="gate-submit" type="submit">Send quote request <?= icon('arrow-right', 18) ?></button>
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
