<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if (function_exists('record_site_visit')) {
    record_site_visit();
}
if ($user = current_user()) {
    redirect(($user['role'] ?? '') === 'platform' ? platform_home() : 'dashboard.php');
}

$error = '';
$info = '';
$step = self_register_step();
$state = self_register_state();
$emailValue = (string) ($state['email'] ?? '');
$details = is_array($state['details'] ?? null) ? $state['details'] : [];

if (!platform_signups_open()) {
    $error = 'New registrations are paused. Call ' . implode(' or ', product_phones()) . ' or sign in if you already have a desk.';
    $step = 'closed';
}

form_mark_open('create-account');

$popup = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step !== 'closed') {
    if (!csrf_valid()) {
        $error = 'Your session expired. Please submit the form again.';
    } elseif (form_is_spam('create-account', 1)) {
        // Silent success for bots.
        redirect('create_account.php');
    } else {
        $action = post_plain('action', 40);
        if ($action === 'restart') {
            self_register_clear();
            redirect('create_account.php');
        }

        if ($action === 'send_otp' || $action === 'resend_otp') {
            $emailForOtp = $action === 'resend_otp'
                ? self_register_normalize_email((string) ($state['email'] ?? ''))
                : post_plain('email', 190);
            $result = self_register_start_otp($emailForOtp);
            if (!empty($result['silent'])) {
                redirect('create_account.php');
            }
            if (!empty($result['ok']) && !empty($result['code']) && !empty($result['email'])) {
                $mailEmail = (string) $result['email'];
                $mailCode = (string) $result['code'];
                $mailOtpId = (int) ($result['otp_id'] ?? 0);
                folio_redirect_then('create_account.php?popup=otp', static function () use ($mailEmail, $mailCode, $mailOtpId): void {
                    self_register_deliver_otp($mailEmail, $mailCode, $mailOtpId);
                });
            }
            $error = (string) ($result['error'] ?? 'Could not send that code.');
            $emailValue = self_register_normalize_email($emailForOtp);
            $step = $action === 'resend_otp' ? 'otp' : 'email';
        } elseif ($action === 'verify_otp') {
            $emailValue = self_register_normalize_email((string) ($state['email'] ?? post_plain('email', 190)));
            $result = self_register_verify_otp($emailValue, post_plain('otp', 12));
            if (!empty($result['ok'])) {
                redirect('create_account.php?popup=verified');
            }
            $error = (string) ($result['error'] ?? 'Could not verify that code.');
            $step = 'otp';
        } elseif ($action === 'save_details') {
            $result = self_register_collect_details($_POST);
            if (!empty($result['ok'])) {
                redirect('create_account.php?popup=details');
            }
            $error = (string) ($result['error'] ?? 'Please check the details.');
            $details = [
                'name' => post_plain('contact_name', 80),
                'company' => post_plain('company_name', 160),
                'phone' => post_plain('contact_phone', 40),
                'country' => post_plain('loc_country', 80),
                'city' => post_plain('loc_city', 120),
                'currency' => posted_currency('currency', 'UGX'),
            ];
            $step = 'details';
        } elseif ($action === 'create') {
            if (form_rate_blocked('create-account', 6, 3600)) {
                $error = 'Please wait a bit before trying again.';
                $step = 'password';
            } else {
                $pass = post('password', '', 256);
                $again = post('password_confirm', '', 256);
                $result = self_register_complete($pass, $again);
                $loginUser = is_array($result['user'] ?? null) ? $result['user'] : [];
                if (!empty($result['ok']) && (int) ($loginUser['id'] ?? 0) > 0) {
                    form_rate_hit('create-account', 3600);
                    self_register_login_user($loginUser);
                    flash('Welcome to Vellisys. Your Pro trial is ready.');
                    redirect('dashboard.php');
                }
                $error = (string) ($result['error'] ?? 'Could not create that account.');
                $step = self_register_step();
            }
        } else {
            $error = 'Please try again.';
        }
    }
}

$flash = flash();
if ($flash && ($flash['type'] ?? '') === 'ok' && $error === '') {
    $info = (string) ($flash['text'] ?? '');
} elseif ($flash && ($flash['type'] ?? '') === 'error' && $error === '') {
    $error = (string) ($flash['text'] ?? '');
}
$popupKey = strtolower(trim((string) ($_GET['popup'] ?? '')));
$state = self_register_state();
$emailValue = $emailValue !== '' ? $emailValue : (string) ($state['email'] ?? '');
$otpMailPending = false;
$otpMailRefresh = false;
if ($popupKey === 'otp') {
    $step = self_register_step();
    $otpMailPending = !empty($state['otp_mail_pending']);
    $otpMailFailed = !empty($state['otp_mail_failed']);
    if ($error !== '' || $otpMailFailed) {
        $popup = [
            'title' => 'Could not send the code',
            'body' => $error !== '' ? $error : 'Google could not deliver the verification email. Try again in a moment, or ask the admin to check Settings → Google OTP.',
        ];
        $step = 'email';
        if ($otpMailFailed) {
            unset($state['otp_mail_failed'], $state['otp_mail_pending']);
            self_register_save($state);
        }
    } elseif ($otpMailPending) {
        // step is set to otp before SMTP finishes - do not claim the mail was sent yet.
        $popup = [
            'title' => 'Sending the code',
            'body' => 'We are sending a 6-digit verification code to ' . ($emailValue !== '' ? $emailValue : 'your inbox') . '. This page updates when the email goes out.',
        ];
        $otpMailRefresh = true;
        // Discard this request's session write so a concurrent deliver_otp result
        // (success or failure) is not overwritten by last-write-wins php_sessions.
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && session_status() === PHP_SESSION_ACTIVE) {
            if (function_exists('session_abort')) {
                session_abort();
            } else {
                session_write_close();
            }
        }
    } elseif ($step === 'otp') {
        $popup = [
            'title' => 'Check your email',
            'body' => 'We sent a 6-digit verification code to ' . ($emailValue !== '' ? $emailValue : 'your inbox') . '. Enter it below. It expires in 15 minutes.',
        ];
        $info = $info !== '' ? $info : 'Verification code sent.';
    }
} elseif ($popupKey === 'verified') {
    $popup = [
        'title' => 'Email verified',
        'body' => 'Thanks. Next, add your business details to open a free one-month Pro trial.',
    ];
} elseif ($popupKey === 'details') {
    $popup = [
        'title' => 'Almost done',
        'body' => 'Choose a password to finish creating your account.',
    ];
}
$countries = platform_countries();
$currencies = pricing_currencies();
?>
<!DOCTYPE html>
<html lang="en" data-sw="<?= h(url('sw.js')) ?>" data-pwa-login="<?= h(url('login.php')) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Create account · <?= h(product_name()) ?></title>
  <?php if (!empty($otpMailRefresh)): ?>
  <meta http-equiv="refresh" content="2">
  <?php endif; ?>
  <?php product_icons(); ?>
  <?php folio_landing_head(); ?>
  <style>
    .gate-steps {
      display: flex;
      gap: 6px;
      justify-content: center;
      margin: 0 0 18px;
      list-style: none;
      padding: 0;
    }
    .gate-steps li {
      width: 28px;
      height: 4px;
      border-radius: 999px;
      background: rgba(8, 20, 58, .12);
    }
    .gate-steps li.is-on { background: #1e4eff; }
    .gate-steps li.is-done { background: #8eb0ff; }
    .gate-field select {
      display: block;
      width: 100%;
      margin-top: 6px;
      border: 1px solid #d8dee9;
      background: #fff;
      border-radius: 12px;
      padding: 13px 14px;
      font: inherit;
      font-size: 15px;
      font-weight: 400;
      color: var(--ink, #10182c);
    }
    .gate-field select:focus {
      outline: none;
      border-color: #1e4eff;
      box-shadow: inset 0 0 0 1px #1e4eff;
    }
    .gate-otp input {
      letter-spacing: 0.35em;
      font-weight: 700;
      text-align: center;
      font-size: 1.25rem;
    }
    .gate-back {
      display: inline-flex;
      margin-top: 14px;
      font-size: 13px;
      font-weight: 700;
      color: #1e4eff;
      text-decoration: none;
      background: none;
      border: 0;
      cursor: pointer;
      padding: 0;
    }
  </style>
</head>
<body class="gate gate-register">
<div class="gate-shell">
  <?php gate_art(
      'Create your Vellisys account.',
      'Verify your email, add the business, set a password, and start a free one-month Pro trial.',
      '',
      [
          'heading_html' => 'Create your<br>Vellisys account.<br><em>Free Pro trial.</em>',
      ]
  ); ?>
  <main class="gate-panel">
    <img class="gate-panel-mark" src="<?= h(product_v_mark_url()) ?>" alt="" decoding="async">
    <?php render_gate_home(); ?>
    <div class="gate-stack">
    <?php render_gate_card_mark(); ?>

    <?php if ($step === 'closed'): ?>
      <div class="gate-box gate-ok">
        <img class="gate-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <h2>Registrations paused</h2>
        <p class="gate-lead"><?= h($error) ?></p>
        <a class="gate-submit" href="<?= h(url('login.php')) ?>">Sign in <?= icon('arrow-right', 18) ?></a>
        <?php render_gate_legal(); ?>
      </div>

    <?php elseif ($step === 'otp'): ?>
      <form class="gate-box" method="post" action="<?= h(url('create_account.php')) ?>" autocomplete="off">
        <?= csrf_field() ?>
        <?= form_honeypot_field() ?>
        <input type="hidden" name="action" value="verify_otp">
        <img class="gate-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <ul class="gate-steps" aria-label="Progress">
          <li class="is-done"></li><li class="is-on"></li><li></li><li></li>
        </ul>
        <h2>Enter the code</h2>
        <?php if (!empty($otpMailPending)): ?>
        <p class="gate-lead">Sending a 6-digit code to <strong><?= h($emailValue) ?></strong>. This page updates when the email goes out.</p>
        <?php else: ?>
        <p class="gate-lead">We sent a 6-digit code to <strong><?= h($emailValue) ?></strong>. It expires in 15 minutes.</p>
        <?php endif; ?>
        <?php if ($info): ?><p class="gate-lead" style="color:#0f7a45"><?= h($info) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="lp-err"><?= h($error) ?></p><?php endif; ?>
        <label class="gate-field gate-otp" for="otp">Verification code
          <input id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" minlength="6" required autocomplete="one-time-code" placeholder="••••••">
        </label>
        <button class="gate-submit" type="submit">Verify email <?= icon('arrow-right', 18) ?></button>
        <button class="gate-back" type="submit" name="action" value="resend_otp" formnovalidate>Resend code</button>
        <button class="gate-back" type="submit" name="action" value="restart" formnovalidate style="margin-left:14px">Use a different email</button>
        <?php render_gate_legal(); ?>
      </form>

    <?php elseif ($step === 'details'): ?>
      <form class="gate-box" method="post" action="<?= h(url('create_account.php')) ?>" autocomplete="on">
        <?= csrf_field() ?>
        <?= form_honeypot_field() ?>
        <input type="hidden" name="action" value="save_details">
        <img class="gate-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <ul class="gate-steps" aria-label="Progress">
          <li class="is-done"></li><li class="is-done"></li><li class="is-on"></li><li></li>
        </ul>
        <h2>Business details</h2>
        <p class="gate-lead">Verified as <?= h($emailValue) ?>. Add the desk details in order - then set your password.</p>
        <?php if ($info): ?><p class="gate-lead" style="color:#0f7a45"><?= h($info) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="lp-err"><?= h($error) ?></p><?php endif; ?>
        <label class="gate-field" for="contact_name">Your name
          <input id="contact_name" name="contact_name" required maxlength="80" autocomplete="name" value="<?= h((string) ($details['name'] ?? post('contact_name'))) ?>" placeholder="Jane Okello">
        </label>
        <label class="gate-field" for="company_name">Business name
          <input id="company_name" name="company_name" required maxlength="160" autocomplete="organization" value="<?= h((string) ($details['company'] ?? post('company_name'))) ?>" placeholder="Okello Traders Ltd">
        </label>
        <label class="gate-field" for="contact_phone">Phone number
          <input id="contact_phone" name="contact_phone" required maxlength="40" autocomplete="tel" value="<?= h((string) ($details['phone'] ?? post('contact_phone'))) ?>" placeholder="+256 700 000 000">
        </label>
        <label class="gate-field" for="loc_country">Country
          <select id="loc_country" name="loc_country" required>
            <option value="">Select country</option>
            <?php $pickedCountry = (string) ($details['country'] ?? post('loc_country')); ?>
            <?php foreach ($countries as $c): ?>
              <option value="<?= h($c) ?>" <?= $pickedCountry === $c ? 'selected' : '' ?>><?= h($c) ?></option>
            <?php endforeach; ?>
            <option value="other" <?= $pickedCountry === 'other' || ($pickedCountry !== '' && !in_array($pickedCountry, $countries, true)) ? 'selected' : '' ?>>Other</option>
          </select>
        </label>
        <label class="gate-field" for="loc_country_other">Other country <span class="gate-optional">(if Other)</span>
          <input id="loc_country_other" name="loc_country_other" maxlength="80" value="<?= h(post('loc_country_other')) ?>" placeholder="Country name">
        </label>
        <label class="gate-field" for="loc_city">City / location
          <input id="loc_city" name="loc_city" required maxlength="120" autocomplete="address-level2" value="<?= h((string) ($details['city'] ?? post('loc_city'))) ?>" placeholder="Kampala">
        </label>
        <label class="gate-field" for="currency">Currency
          <select id="currency" name="currency" required>
            <?php $pickedCcy = (string) ($details['currency'] ?? posted_currency('currency', 'UGX')); ?>
            <?php foreach ($currencies as $code => $meta): ?>
              <option value="<?= h($code) ?>" <?= $pickedCcy === $code ? 'selected' : '' ?>><?= h($code) ?> · <?= h((string) ($meta['name'] ?? $code)) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="gate-submit" type="submit">Continue <?= icon('arrow-right', 18) ?></button>
        <button class="gate-back" type="submit" name="action" value="restart" formnovalidate>Start over</button>
        <?php render_gate_legal(); ?>
      </form>

    <?php elseif ($step === 'password'): ?>
      <form class="gate-box" method="post" action="<?= h(url('create_account.php')) ?>" autocomplete="off">
        <?= csrf_field() ?>
        <?= form_honeypot_field() ?>
        <input type="hidden" name="action" value="create">
        <img class="gate-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <ul class="gate-steps" aria-label="Progress">
          <li class="is-done"></li><li class="is-done"></li><li class="is-done"></li><li class="is-on"></li>
        </ul>
        <h2>Set your password</h2>
        <p class="gate-lead">You will sign in with <?= h($emailValue) ?>. Enter the password twice. Use the eye icons to preview.</p>
        <?php if ($info): ?><p class="gate-lead" style="color:#0f7a45"><?= h($info) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="lp-err"><?= h($error) ?></p><?php endif; ?>
        <label class="gate-field" for="password">Password</label>
        <div class="gate-control gate-pw">
          <input id="password" name="password" type="password" required minlength="8" maxlength="128" autocomplete="new-password" placeholder="At least 8 characters">
          <button class="pw-toggle" type="button" data-toggle-password aria-label="Show password" title="Show password" aria-pressed="false">
            <span data-eye><?= icon('eye', 16) ?></span>
            <span data-eye-off hidden><?= icon('eye-off', 16) ?></span>
          </button>
        </div>
        <label class="gate-field" for="password_confirm">Confirm password</label>
        <div class="gate-control gate-pw">
          <input id="password_confirm" name="password_confirm" type="password" required minlength="8" maxlength="128" autocomplete="new-password" placeholder="Type it again">
          <button class="pw-toggle" type="button" data-toggle-password aria-label="Show password" title="Show password" aria-pressed="false">
            <span data-eye><?= icon('eye', 16) ?></span>
            <span data-eye-off hidden><?= icon('eye-off', 16) ?></span>
          </button>
        </div>
        <button class="gate-submit" type="submit">Create account &amp; start Pro trial <?= icon('arrow-right', 18) ?></button>
        <?php render_gate_legal(); ?>
      </form>

    <?php else: ?>
      <form class="gate-box" method="post" action="<?= h(url('create_account.php')) ?>" autocomplete="on">
        <?= csrf_field() ?>
        <?= form_honeypot_field() ?>
        <input type="hidden" name="action" value="send_otp">
        <img class="gate-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <ul class="gate-steps" aria-label="Progress">
          <li class="is-on"></li><li></li><li></li><li></li>
        </ul>
        <h2>Create account</h2>
        <p class="gate-lead">Start with a valid email. Google sends a one-time code to that inbox, then you add business details and a password for a free one-month Pro trial.</p>
        <?php if ($info): ?><p class="gate-lead" style="color:#0f7a45"><?= h($info) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="lp-err"><?= h($error) ?></p><?php endif; ?>
        <label class="gate-field" for="email">Email</label>
        <div class="gate-control">
          <?= icon('mail', 18) ?>
          <input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= h($emailValue) ?>" placeholder="you@company.com">
        </div>
        <button class="gate-submit" type="submit">Send verification code <?= icon('arrow-right', 18) ?></button>
        <p class="gate-or"><span>or</span></p>
        <a class="gate-alt" href="<?= h(url('login.php')) ?>">Already have an account? Sign in</a>
        <?php render_gate_legal(); ?>
      </form>
    <?php endif; ?>
    </div>
  </main>
</div>
<?php public_float_widgets(); ?>
<?php if (is_array($popup) && function_exists('render_public_notice_popup')) {
    render_public_notice_popup((string) ($popup['title'] ?? 'Thank you'), (string) ($popup['body'] ?? ''));
} ?>
<script src="<?= h(asset('js/landing.js')) ?>" defer></script>
<script src="<?= h(asset('js/pwa.js')) ?>" defer></script>
<script src="<?= h(asset('js/push.js')) ?>" defer></script>
</body>
</html>
