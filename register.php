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
<body class="lp">
  <div class="lp-glow lp-glow-a" aria-hidden="true"></div>
  <div class="lp-glow lp-glow-b" aria-hidden="true"></div>

  <?php public_header('register'); ?>

  <main class="lp-auth">
    <?php if ($ok): ?>
      <div class="lp-card lp-ok">
        <img src="<?= h(product_logo_url()) ?>" class="lp-logo" alt="<?= h(product_name()) ?>">
        <h1>We have your request</h1>
        <p>A Vellisys admin will reach out to onboard your company and open the desk. No password yet - you get one when the company goes live.</p>
        <a class="lp-btn lp-btn-solid" href="<?= h(url()) ?>">Back to Vellisys</a>
      </div>
    <?php else: ?>
      <form class="lp-card" method="post" action="<?= h(url('register.php')) ?>" autocomplete="off">
        <?= csrf_field() ?>
        <p class="lp-kicker">Get a desk</p>
        <h1>Register your company</h1>
        <p class="lp-form-lead">Four fields. We call you. Then the books are yours.</p>
        <?php if ($error): ?><p class="lp-err"><?= h($error) ?></p><?php endif; ?>
        <label for="contact_name">Your name</label>
        <input id="contact_name" name="contact_name" required autocomplete="name" value="<?= h(post('contact_name')) ?>" placeholder="Jane Okello">
        <label for="company_name">Company</label>
        <input id="company_name" name="company_name" required autocomplete="organization" value="<?= h(post('company_name')) ?>" placeholder="Okello Traders Ltd">
        <label for="contact_email">Email</label>
        <input id="contact_email" name="contact_email" type="email" required autocomplete="email" value="<?= h(post('contact_email')) ?>" placeholder="accounts@company.ug">
        <label for="contact_phone">Phone</label>
        <input id="contact_phone" name="contact_phone" type="tel" required autocomplete="tel" value="<?= h(post('contact_phone')) ?>" placeholder="+256 700 000 000">
        <button class="lp-btn lp-btn-solid" type="submit">Send my details</button>
        <p class="lp-form-note">Already onboarded? <a href="<?= h(url('login.php')) ?>">Sign in</a></p>
      </form>
    <?php endif; ?>
  </main>
  <?php public_footer(); ?>
  <script src="<?= h(asset('js/landing.js')) ?>"></script>
</body>
</html>
