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
    $note = mb_substr(post('quote_note'), 0, 2000);
    if ($name === '' || $company === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') {
        $error = 'Your name, company, email and phone are enough - please fill those in.';
    } elseif (db_one('SELECT id FROM users WHERE email = ?', 's', [$email])) {
        $error = 'That email already has a Vellisys login. Sign in, or use another mailbox.';
    } elseif (db_one("SELECT id FROM signups WHERE email = ? AND status IN ('new','contacted')", 's', [$email])) {
        $error = 'We already have this request. A Vellisys admin will call you.';
    } else {
        db_exec(
            'INSERT INTO signups (name, company, email, phone, status, source, note) VALUES (?,?,?,?,?,?,?)',
            'sssssss',
            [$name, $company, $email, $phone, 'new', 'quote', $note]
        );
        notify_admin_signup([
            'name' => $name,
            'company' => $company,
            'email' => $email,
            'phone' => $phone,
            'source' => 'quote',
            'note' => $note,
        ]);
        redirect('quote.php?ok=1');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Request a quote · <?= h(product_name()) ?></title>
  <?php product_icons(); ?>
  <?php folio_landing_head(); ?>
</head>
<body class="gate">
<div class="gate-shell">
  <?php gate_art(
      'Ask for a quote. We will call you.',
      'Anywhere in the world: tell us the company, we send a quote, then we onboard the desk. No password yet.',
      'Ready to sign up? <a href="' . h(url('register.php')) . '">Get a desk</a>'
  ); ?>
  <main class="gate-panel">
    <?php if ($ok): ?>
      <div class="gate-box gate-ok">
        <img class="gate-logo" src="<?= h(product_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <h2><em>We have</em> your quote request</h2>
        <p class="gate-lead">We sent a confirmation to your email from <?= h(product_email()) ?>. A Vellisys admin will reach out with a quote and onboard your company. No password yet - you get one when the company goes live.</p>
        <a class="gate-submit" href="<?= h(url()) ?>">Back to Vellisys</a>
      </div>
    <?php else: ?>
      <form class="gate-box" method="post" action="<?= h(url('quote.php')) ?>" autocomplete="off">
        <?= csrf_field() ?>
        <img class="gate-logo" src="<?= h(product_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <h2><em>Request</em> a quote</h2>
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
        <button class="gate-submit" type="submit">Send quote request</button>
        <p class="gate-switch">Already a member? <a href="<?= h(url('login.php')) ?>">Log in</a></p>
        <p class="gate-home"><a href="<?= h(url()) ?>">Back to <?= h(product_name()) ?></a></p>
      </form>
    <?php endif; ?>
  </main>
</div>
<?php public_float_widgets(); ?>
<script src="<?= h(asset('js/landing.js')) ?>"></script>
</body>
</html>
