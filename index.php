<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if ($user = current_user()) {
    redirect(($user['role'] ?? '') === 'platform' ? 'admin_signups.php' : 'dashboard.php');
}
$familiar = landing_cards('familiar');
$help = landing_cards('help');
$steps = landing_cards('steps');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h(product_name()) ?> · Stop losing the books</title>
  <meta name="description" content="Tired of hunting receipts and losing invoices? Vellisys keeps quotations, invoices and receipts on one desk. Register in a minute. We call you, then the books go live.">
  <?php product_icons(); ?>
  <?php folio_font_links(); ?>
  <link rel="stylesheet" href="<?= h(asset('css/landing.css')) ?>">
</head>
<body class="lp">
  <div class="lp-glow lp-glow-a" aria-hidden="true"></div>
  <div class="lp-glow lp-glow-b" aria-hidden="true"></div>

  <header class="lp-nav">
    <a class="lp-brand" href="<?= h(url()) ?>">
      <img class="lp-logo" src="<?= h(product_logo_url()) ?>" alt="<?= h(product_name()) ?>">
    </a>
    <nav>
      <a href="#sound-familiar">Sound familiar?</a>
      <a href="#how-vellisys-helps">What you get</a>
      <a href="#get-a-desk">How it works</a>
      <a class="lp-btn lp-btn-ghost" href="<?= h(url('login.php')) ?>">Sign in to my desk</a>
      <a class="lp-btn lp-btn-solid" href="<?= h(url('register.php')) ?>">Get a desk</a>
    </nav>
  </header>

  <main>
    <section class="lp-hero">
      <div class="lp-hero-copy">
        <h1>Lose track of your financial records?</h1>
        <p class="lp-lead">Tired of receipts in a drawer and invoices living in WhatsApp? Vellisys keeps quotations, invoices, receipts and reports on one desk. Generate a record and share it with a client in a single click. Open the books any time, anywhere you are.</p>
        <div class="lp-cta">
          <a class="lp-btn lp-btn-solid lp-btn-lg" href="<?= h(url('register.php')) ?>">Get my company a desk</a>
          <a class="lp-btn lp-btn-ghost lp-btn-lg" href="<?= h(url('login.php')) ?>">I already have a desk</a>
        </div>
        <p class="lp-note">Four fields. We call you. Then your books go live.</p>
      </div>

      <div class="lp-stage" data-lp-stage>
        <svg class="lp-arrows" viewBox="0 0 640 420" aria-hidden="true">
          <defs>
            <marker id="lp-head" markerWidth="8" markerHeight="8" refX="6" refY="4" orient="auto">
              <path d="M0 0 L8 4 L0 8 Z" fill="#1E4EFF"/>
            </marker>
          </defs>
          <path class="lp-flow lp-flow-1" d="M80 320 C 160 280, 200 120, 310 150" fill="none" stroke="#1E4EFF" stroke-width="2.2" marker-end="url(#lp-head)"/>
          <path class="lp-flow lp-flow-2" d="M560 80 C 500 140, 470 220, 400 210" fill="none" stroke="#08143A" stroke-width="2.2" marker-end="url(#lp-head)"/>
          <path class="lp-flow lp-flow-3" d="M90 90 C 180 70, 240 180, 200 250" fill="none" stroke="#3B82F6" stroke-width="2" marker-end="url(#lp-head)"/>
        </svg>
        <span class="lp-fly lp-fly-1" aria-hidden="true"></span>
        <span class="lp-fly lp-fly-2" aria-hidden="true"></span>
        <span class="lp-fly lp-fly-3" aria-hidden="true"></span>
        <span class="lp-chip lp-float-b">Shared just now</span>
        <span class="lp-chip lp-float-c">Balance due</span>

        <article class="lp-board">
          <header>
            <img src="<?= h(product_mark_url()) ?>" width="22" height="22" alt="">
            <strong>This month</strong>
            <em>Live books</em>
          </header>
          <svg class="lp-line" viewBox="0 0 320 88" role="img" aria-label="Income over the month">
            <polyline points="8,70 48,62 88,48 128,54 168,32 208,28 248,18 312,22" fill="none" stroke="#1E4EFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="312" cy="22" r="4" fill="#08143A"/>
          </svg>
          <div class="lp-bars" aria-hidden="true">
            <span style="height:42%"></span><span style="height:58%"></span><span style="height:36%"></span>
            <span style="height:72%"></span><span style="height:64%"></span><span style="height:88%"></span>
            <span style="height:54%"></span>
          </div>
          <ul class="lp-kpis">
            <li><span>Invoiced</span><b>UGX 12.4m</b></li>
            <li><span>Collected</span><b>UGX 9.1m</b></li>
            <li><span>Open</span><b>UGX 3.3m</b></li>
          </ul>
        </article>

        <article class="lp-mini lp-mini-a">
          <span>Receipt</span>
          <strong>RCT-0004</strong>
          <svg viewBox="0 0 80 36" aria-hidden="true"><path d="M4 28 L18 20 L32 22 L46 12 L62 16 L76 8" fill="none" stroke="#1E4EFF" stroke-width="2.5"/></svg>
        </article>
        <article class="lp-mini lp-mini-b">
          <span>Invoice sent</span>
          <strong>One click</strong>
          <div class="lp-pie" aria-hidden="true"></div>
        </article>
        <article class="lp-mini lp-mini-c">
          <span>Debtors</span>
          <strong>3 open</strong>
          <div class="lp-dots"><i></i><i></i><i></i></div>
        </article>
      </div>
    </section>

    <section class="lp-band" id="sound-familiar">
      <h2>Sound familiar?</h2>
      <div class="lp-grid3">
        <?php foreach ($familiar as $card): ?>
          <article>
            <img class="lp-card-pic" src="<?= h(landing_card_image_url($card)) ?>" alt="">
            <div class="lp-card-copy">
              <h3><?= h($card['title']) ?></h3>
              <p><?= h($card['body']) ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="lp-band lp-band-alt" id="how-vellisys-helps">
      <h2>Here is how Vellisys helps</h2>
      <div class="lp-grid3">
        <?php foreach ($help as $card): ?>
          <article>
            <img class="lp-card-pic" src="<?= h(landing_card_image_url($card)) ?>" alt="">
            <div class="lp-card-copy">
              <h3><?= h($card['title']) ?></h3>
              <p><?= h($card['body']) ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="lp-band" id="get-a-desk">
      <h2>You are three steps away</h2>
      <div class="lp-grid3">
        <?php foreach ($steps as $i => $card): ?>
          <article>
            <img class="lp-card-pic" src="<?= h(landing_card_image_url($card)) ?>" alt="">
            <div class="lp-card-copy">
              <p class="lp-step-n"><?= (int) $i + 1 ?></p>
              <h3><?= h($card['title']) ?></h3>
              <p><?= h($card['body']) ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="lp-cta lp-cta-band">
        <a class="lp-btn lp-btn-solid lp-btn-lg" href="<?= h(url('register.php')) ?>">Get my company a desk</a>
        <a class="lp-btn lp-btn-ghost lp-btn-lg" href="<?= h(url('login.php')) ?>">Sign in to my desk</a>
      </div>
    </section>
  </main>

  <footer class="lp-foot">
    <img class="lp-logo lp-logo-sm" src="<?= h(product_logo_url()) ?>" alt="<?= h(product_name()) ?>">
    <a class="lp-btn lp-btn-solid" href="<?= h(url('register.php')) ?>">Get a desk</a>
  </footer>
  <script src="<?= h(asset('js/landing.js')) ?>"></script>
</body>
</html>
