<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if ($user = current_user()) {
    redirect(($user['role'] ?? '') === 'platform' ? 'admin_signups.php' : 'dashboard.php');
}

$error = '';
$ok = isset($_GET['ok']);
$emailValue = post('email');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $error = 'Your session expired. Please submit the form again.';
    } else {
        $honey = trim((string) ($_POST['website'] ?? ''));
        $email = strtolower(mb_substr(post('email'), 0, 190));
        $emailValue = $email;
        if ($honey !== '') {
            redirect('forgot.php?ok=1');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter the personal email on your desk login.';
        } else {
            $hits = (int) ($_SESSION['forgot_hour_count'] ?? 0);
            $at = (int) ($_SESSION['forgot_hour_at'] ?? 0);
            if ($at < time() - 3600) {
                $hits = 0;
                $_SESSION['forgot_hour_at'] = time();
            }
            if ($hits >= 3) {
                $error = 'Please wait a bit before sending another request.';
            } else {
                $_SESSION['forgot_hour_count'] = $hits + 1;
                $_SESSION['forgot_hour_at'] = $_SESSION['forgot_hour_at'] ?? time();
                $sent = notify_password_reset_request($email);
                if (empty($sent['ok'])) {
                    $error = 'We could not send that just now. Write to ' . product_email() . ' or call ' . implode(' or ', product_phones()) . '.';
                } else {
                    redirect('forgot.php?ok=1');
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-pwa-login="<?= h(url('login.php')) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Forgot password · <?= h(product_name()) ?></title>
  <?php product_icons(); ?>
  <?php folio_landing_head(); ?>
</head>
<body class="gate">
<div class="gate-shell">
  <?php gate_art(
      'We can get you back on the desk.',
      'Enter the personal email on your desk login. A Vellisys admin will send you a reset password.',
      '',
      [
          'heading_html' => 'We can get you<br>back on the desk.',
      ]
  ); ?>
  <main class="gate-panel">
    <img class="gate-panel-mark" src="<?= h(product_v_mark_url()) ?>" alt="" decoding="async">
    <?php render_gate_home(); ?>
    <div class="gate-stack">
      <?php render_gate_card_mark(); ?>
      <?php if ($ok): ?>
      <div class="gate-box gate-ok">
        <img class="gate-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <h2>We have your request</h2>
        <p class="gate-lead">A Vellisys admin will send a reset password to that personal email. If you need us today, write to <?= h(product_email()) ?> or call <?= h(implode(' or ', product_phones())) ?>.</p>
        <a class="gate-submit" href="<?= h(url('login.php')) ?>">Back to Sign In <?= icon('arrow-right', 18) ?></a>
        <?php render_gate_legal(); ?>
      </div>
      <?php else: ?>
      <form class="gate-box" method="post" action="<?= h(url('forgot.php')) ?>" autocomplete="off">
        <?= csrf_field() ?>
        <img class="gate-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <h2>Forgot password?</h2>
        <p class="gate-lead">Enter your personal email. This asks a Vellisys admin to send you a reset password. <?= h(product_email()) ?> gets the request as soon as you send it.</p>
        <?php if ($error): ?><p class="lp-err"><?= h($error) ?></p><?php endif; ?>
        <div class="lp-hp" aria-hidden="true">
          <label>Website
            <input type="text" name="website" tabindex="-1" autocomplete="off">
          </label>
        </div>
        <label class="gate-field" for="email">Personal email</label>
        <div class="gate-control">
          <?= icon('mail', 18) ?>
          <input id="email" name="email" type="email" required value="<?= h($emailValue) ?>" autocomplete="email" placeholder="you@company.com">
        </div>
        <button class="gate-submit" type="submit">Request a reset password <?= icon('arrow-right', 18) ?></button>
        <p class="gate-or"><span>or</span></p>
        <a class="gate-alt" href="<?= h(url('login.php')) ?>">Back to Sign In</a>
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
