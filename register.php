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
            'INSERT INTO signups (name, company, email, phone, status) VALUES (?,?,?,?,?)',
            'sssss',
            [$name, $company, $email, $phone, 'new']
        );
        notify_admin_signup([
            'name' => $name,
            'company' => $company,
            'email' => $email,
            'phone' => $phone,
        ]);
        redirect('register.php?ok=1');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register · <?= h(product_name()) ?></title>
  <?php product_icons(); ?>
  <?php folio_font_links(); ?>
  <link rel="stylesheet" href="<?= h(asset('css/landing.css')) ?>">
</head>
<body class="gate">
<div class="gate-shell">
  <?php gate_art(
      'A desk for the company. Live in a few steps.',
      'Leave four fields. We call you. Then quotations, invoices and receipts sit on one desk you can open from anywhere.',
      'Already a member? <a href="' . h(url('login.php')) . '">Log in</a>'
  ); ?>
  <main class="gate-panel">
    <?php if ($ok): ?>
      <div class="gate-box gate-ok">
        <img class="gate-logo" src="<?= h(product_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <h2><em>We have</em> your request</h2>
        <p class="gate-lead">We sent a confirmation to your email from <?= h(product_email()) ?>. A Vellisys admin will reach out to onboard your company and open the desk. No password yet - you get one when the company goes live.</p>
        <a class="gate-submit" href="<?= h(url()) ?>">Back to Vellisys</a>
      </div>
    <?php else: ?>
      <form class="gate-box" method="post" action="<?= h(url('register.php')) ?>" autocomplete="off">
        <?= csrf_field() ?>
        <img class="gate-logo" src="<?= h(product_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <h2><em>Sign up</em> to get a company desk</h2>
        <p class="gate-lead">Four fields. We call you. Then the books are yours.</p>
        <?php if ($error): ?><p class="lp-err"><?= h($error) ?></p><?php endif; ?>
        <label class="gate-field" for="contact_name">Your name
          <input id="contact_name" name="contact_name" required autocomplete="name" value="<?= h(post('contact_name')) ?>" placeholder="Jane Okello">
        </label>
        <label class="gate-field" for="company_name">Company
          <input id="company_name" name="company_name" required autocomplete="organization" value="<?= h(post('company_name')) ?>" placeholder="Okello Traders Ltd">
        </label>
        <label class="gate-field" for="contact_email">Email
          <input id="contact_email" name="contact_email" type="email" required autocomplete="email" value="<?= h(post('contact_email')) ?>" placeholder="accounts@company.ug">
        </label>
        <label class="gate-field" for="contact_phone">Phone
          <input id="contact_phone" name="contact_phone" type="tel" required autocomplete="tel" value="<?= h(post('contact_phone')) ?>" placeholder="+256 700 000 000">
        </label>
        <button class="gate-submit" type="submit">Register</button>
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
