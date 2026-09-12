<?php
declare(strict_types=1);

/** Public registration and demo booking (no payment). */
function join_intents(): array
{
    return [
        'register' => [
            'source' => 'register',
            'title' => 'Register for a desk',
            'kicker' => 'Manual onboarding',
            'heading' => 'Register. We open the desk.',
            'lead' => 'Prefer we onboard you instead of paying online? Send the company details. A Vellisys admin sees the registration, calls you, and opens the desk.',
            'submit' => 'Send registration',
            'note_label' => 'What you need',
            'note_hint' => 'optional',
            'note_placeholder' => 'Package you have in mind, number of people, or when to call',
            'ok_title' => 'We have your registration',
            'ok_body' => 'Thank you. A confirmation is on its way from {email}. The same mailbox has a copy so we can onboard you.',
            'nav' => 'Register',
        ],
        'demo' => [
            'source' => 'demo',
            'kicker' => 'See the desk',
            'title' => 'Book a demo',
            'heading' => 'Book a walkthrough of the desk.',
            'lead' => 'We will show quotations, invoices, receipts and branding on a live desk, then talk through the package that fits.',
            'submit' => 'Book the demo',
            'note_label' => 'When should we call',
            'note_hint' => 'optional',
            'note_placeholder' => 'A morning this week, or a WhatsApp time that works',
            'ok_title' => 'Demo request received',
            'ok_body' => 'Thank you. We emailed a confirmation from {email}. The team has the same note and will reach you to set a time.',
            'nav' => 'Book a demo',
        ],
    ];
}

function join_intent(string $key): array
{
    $all = join_intents();
    return $all[$key] ?? $all['register'];
}

function join_handle_post(string $intentKey): array
{
    $intent = join_intent($intentKey);
    $source = (string) $intent['source'];
    if (!csrf_valid()) {
        return ['ok' => false, 'error' => 'Your session expired. Please submit the form again.'];
    }
    if (form_is_spam('join-' . $source, 3) || join_fields_look_like_spam()) {
        return ['ok' => true, 'silent' => true];
    }
    if (form_rate_blocked('join-' . $source, 4) || form_rate_blocked('join', 6)) {
        return ['ok' => false, 'error' => 'Please wait a bit before sending another request.'];
    }
    $plan = strtolower(trim(post('join_plan', '', 40)));
    $pkg = $plan !== '' ? pricing_package($plan) : null;
    $noteBits = [];
    if ($pkg) {
        $noteBits[] = 'Package: ' . $pkg['name'];
    }
    $extra = trim(post('join_note', '', 800));
    if ($extra !== '') {
        $noteBits[] = $extra;
    }
    $saved = record_website_signup($source, implode("\n", $noteBits));
    if (empty($saved['ok'])) {
        return ['ok' => false, 'error' => (string) ($saved['error'] ?? 'We could not save that just now.')];
    }
    form_rate_hit('join-' . $source);
    form_rate_hit('join');
    return ['ok' => true, 'signup' => $saved['signup']];
}

function join_boot(string $intentKey): never
{
    if ($user = current_user()) {
        redirect(($user['role'] ?? '') === 'platform' ? 'admin_signups.php' : 'dashboard.php');
    }
    $intent = join_intent($intentKey);
    $error = '';
    form_mark_open('join-' . $intent['source']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = join_handle_post($intentKey);
        if (!empty($result['silent'])) {
            redirect(($intent['source'] === 'demo' ? 'demo.php' : 'register.php') . '?ok=1');
        }
        if (!empty($result['ok']) && !empty($result['signup'])) {
            $file = $intent['source'] === 'demo' ? 'demo.php?ok=1' : 'register.php?ok=1';
            folio_redirect_then($file, static function () use ($result): void {
                notify_admin_signup($result['signup']);
            });
        }
        $error = (string) ($result['error'] ?? 'We could not save that just now.');
    }
    $ok = isset($_GET['ok']);
    join_render_page($intent, $error, $ok);
    exit;
}

function join_render_page(array $intent, string $error, bool $ok): void
{
    $source = (string) $intent['source'];
    $action = $source === 'demo' ? url('demo.php') : url('register.php');
    $packages = pricing_packages();
    $flash = flash();
    if ($flash && ($flash['type'] ?? '') === 'err' && $error === '') {
        $error = (string) ($flash['text'] ?? '');
    }
    $draft = $_SESSION['join_draft'] ?? [];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($intent['title']) ?> · <?= h(product_name()) ?></title>
  <?php product_icons(); ?>
  <?php folio_landing_head(); ?>
</head>
<body class="lp">
  <?php public_header($source === 'demo' ? 'demo' : 'register'); ?>
  <main class="lp-join-page">
    <section class="lp-join-hero">
      <p class="lp-kicker"><?= h($intent['kicker']) ?></p>
      <h1><?= h($intent['heading']) ?></h1>
      <p class="lp-join-lead"><?= h($intent['lead']) ?></p>
    </section>
    <?php if ($ok): ?>
      <div class="lp-ask-form lp-ask-ok lp-join-form">
        <span class="lp-ask-mark" aria-hidden="true"><?= icon('heart', 26) ?></span>
        <p class="lp-kicker">Thank you</p>
        <h2><?= h($intent['ok_title']) ?></h2>
        <p class="lp-ask-ok-lead"><?= str_replace('{email}', h(product_email()), $intent['ok_body']) ?></p>
        <div class="lp-cta">
          <a class="lp-btn lp-btn-solid" href="<?= h(url()) ?>">Back to Vellisys</a>
          <a class="lp-btn lp-btn-ghost" href="<?= h($source === 'demo' ? url('register.php') : url('demo.php')) ?>"><?= $source === 'demo' ? 'Register instead' : 'Book a demo' ?></a>
        </div>
      </div>
    <?php else: ?>
      <form class="lp-ask-form lp-join-form" method="post" action="<?= h($action) ?>" autocomplete="on">
        <?= csrf_field() ?>
        <?= form_honeypot_field() ?>
        <h2><?= h($intent['title']) ?></h2>
        <p class="lp-ask-hint">Name, company, email and phone. We reply from <?= h(product_email()) ?>.</p>
        <?php if ($error !== ''): ?><p class="lp-err"><?= h($error) ?></p><?php endif; ?>
        <label for="contact_name">Your name
          <input id="contact_name" name="contact_name" required maxlength="80" autocomplete="name" value="<?= h(post('contact_name') ?: (string) ($draft['name'] ?? '')) ?>" placeholder="Jane Okello">
        </label>
        <label for="company_name">Company
          <input id="company_name" name="company_name" required maxlength="160" autocomplete="organization" value="<?= h(post('company_name') ?: (string) ($draft['company'] ?? '')) ?>" placeholder="Okello Traders Ltd">
        </label>
        <label for="contact_email">Email
          <input id="contact_email" name="contact_email" type="email" required maxlength="190" autocomplete="email" value="<?= h(post('contact_email') ?: (string) ($draft['email'] ?? '')) ?>" placeholder="accounts@company.com">
        </label>
        <label for="contact_phone">Phone
          <input id="contact_phone" name="contact_phone" required maxlength="40" autocomplete="tel" value="<?= h(post('contact_phone') ?: (string) ($draft['phone'] ?? '')) ?>" placeholder="+256 700 000 000">
        </label>
        <?php if ($packages): ?>
          <label for="join_plan">Package <span>(optional)</span>
            <select id="join_plan" name="join_plan">
              <option value="">Not sure yet</option>
              <?php foreach ($packages as $pkg): ?>
                <option value="<?= h($pkg['key']) ?>" <?= post('join_plan') === $pkg['key'] ? 'selected' : '' ?>><?= h($pkg['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php endif; ?>
        <label for="join_note"><?= h($intent['note_label']) ?> <span>(<?= h($intent['note_hint']) ?>)</span>
          <textarea id="join_note" name="join_note" maxlength="800" rows="4" placeholder="<?= h($intent['note_placeholder']) ?>"><?= h(post('join_note')) ?></textarea>
        </label>
        <button class="lp-btn lp-btn-solid" type="submit"><?= h($intent['submit']) ?></button>
        <p class="lp-checkout-note">
          <?php if ($source === 'demo'): ?>
            Ready to start without a call? <a href="<?= h(url('register.php')) ?>">Register for onboarding</a> or <a href="<?= h(url()) ?>#pricing">pay for a package</a>.
          <?php else: ?>
            Want a walkthrough first? <a href="<?= h(url('demo.php')) ?>">Book a demo</a>. Or <a href="<?= h(url()) ?>#pricing">pay for a package</a>.
          <?php endif; ?>
        </p>
      </form>
    <?php endif; ?>
  </main>
  <?php public_footer(); ?>
  <script src="<?= h(asset('js/landing.js')) ?>" defer></script>
</body>
</html>
    <?php
}
