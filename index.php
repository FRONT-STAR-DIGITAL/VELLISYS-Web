<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if ($user = current_user()) {
    redirect(($user['role'] ?? '') === 'platform' ? 'admin_signups.php' : 'dashboard.php');
}
$familiar = landing_cards('familiar');
$help = landing_cards('help');
$steps = landing_cards('steps');
$oldPhotos = old_way_photos();
$clients = trust_clients();
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
  <?php public_header('home'); ?>

  <main>
    <section class="lp-hero">
      <div class="lp-hero-copy" data-reveal>
        <h1>Lose track of your financial records?</h1>
        <p class="lp-lead">Tired of receipts in a drawer and invoices living in WhatsApp? Vellisys keeps quotations, invoices, receipts and reports on one desk. Generate a record and share it with a client in a single click. Open the books any time, anywhere you are.</p>
        <div class="lp-cta">
          <a class="lp-btn lp-btn-solid lp-btn-lg" href="<?= h(url('register.php')) ?>">Get my company a desk</a>
          <a class="lp-btn lp-btn-ghost lp-btn-lg" href="<?= h(url('login.php')) ?>">I already have a desk</a>
        </div>
        <p class="lp-note">Four fields. We call you. Then your books go live.</p>
      </div>

      <div class="lp-stage" data-reveal>
        <svg class="lp-arrows" viewBox="0 0 640 400" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
          <path class="lp-flow lp-flow-1" d="M96 200 C 96 86, 544 86, 544 200 C 544 314, 96 314, 96 200" fill="none" stroke="#1E4EFF" stroke-width="2.4"/>
          <path class="lp-flow lp-flow-2" d="M150 200 C 150 118, 490 118, 490 200 C 490 282, 150 282, 150 200" fill="none" stroke="#08143A" stroke-width="1.8"/>
        </svg>

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
          <div class="lp-bars" data-lp-bars aria-hidden="true">
            <span style="--h:42%"></span><span style="--h:58%"></span><span style="--h:36%"></span>
            <span style="--h:72%"></span><span style="--h:64%"></span><span style="--h:88%"></span>
            <span style="--h:54%"></span>
          </div>
          <div class="lp-pies" data-lp-pies>
            <figure>
              <svg class="lp-pie" style="--pie:73" viewBox="0 0 36 36" role="img" aria-label="Collected 73 percent">
                <circle class="lp-pie-track" cx="18" cy="18" r="15.915"></circle>
                <circle class="lp-pie-fill lp-pie-fill-a" cx="18" cy="18" r="15.915" pathLength="100"></circle>
              </svg>
              <figcaption>Collected <b>73%</b></figcaption>
            </figure>
            <figure>
              <svg class="lp-pie" style="--pie:27" viewBox="0 0 36 36" role="img" aria-label="Open 27 percent">
                <circle class="lp-pie-track" cx="18" cy="18" r="15.915"></circle>
                <circle class="lp-pie-fill lp-pie-fill-b" cx="18" cy="18" r="15.915" pathLength="100"></circle>
              </svg>
              <figcaption>Open <b>27%</b></figcaption>
            </figure>
          </div>
          <ul class="lp-kpis">
            <li><span>Invoiced</span><b>UGX 12.4m</b></li>
            <li><span>Collected</span><b>UGX 9.1m</b></li>
            <li><span>Open</span><b>UGX 3.3m</b></li>
          </ul>
        </article>
      </div>
    </section>

    <section class="lp-trust" aria-label="Clients who trust us">
      <p class="lp-kicker">On the desk</p>
      <h2>Clients who trust us</h2>
      <div class="lp-marquee">
        <div class="lp-marquee-track">
          <?php foreach ([$clients, $clients] as $setIndex => $set): ?>
            <?php foreach ($set as $client): ?>
              <img src="<?= h(asset($client['file'])) ?>" alt="<?= $setIndex === 0 ? h($client['name']) : '' ?>" <?= $setIndex === 1 ? 'aria-hidden="true"' : '' ?>>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="lp-send" id="send-in-a-minute" data-reveal>
      <figure class="lp-send-pic">
        <img src="<?= h(asset('img/landing/rec.png')) ?>" alt="A receipt generated on Vellisys, open on a phone and already sent to the client">
        <span class="lp-send-badge" aria-hidden="true">Sent · 48s</span>
        <span class="lp-send-ring" aria-hidden="true"></span>
      </figure>
      <div class="lp-send-copy">
        <p class="lp-kicker">From the desk to their phone</p>
        <h2>Generate it. Send it. They have it in a minute.</h2>
        <p>Raise a quotation, an invoice or a receipt on your Vellisys desk, then share it while the client is still with you. One record, one tap, their copy is on the way.</p>
        <ul class="lp-send-docs">
          <li>Quotation</li>
          <li>Invoice</li>
          <li>Receipt</li>
        </ul>
        <ol class="lp-send-flow">
          <li><b>1</b><span>Raise the document</span></li>
          <li><b>2</b><span>Share the sheet</span></li>
          <li><b>3</b><span>Client opens it</span></li>
        </ol>
        <div class="lp-cta">
          <a class="lp-btn lp-btn-solid lp-btn-lg" href="<?= h(url('register.php')) ?>">Get a desk and send one</a>
        </div>
      </div>
    </section>

    <section class="lp-band" id="sound-familiar" data-reveal>
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

    <section class="lp-compare" id="old-way" data-reveal>
      <div class="lp-compare-old">
        <p class="lp-kicker">Leave this behind</p>
        <h2>The old way</h2>
        <div class="lp-stack" tabindex="0">
          <?php foreach ($oldPhotos as $i => $photo): ?>
            <img src="<?= h(asset(substr($photo, strlen('assets/')))) ?>" alt="The old way of keeping books" style="--i:<?= (int) $i ?>">
          <?php endforeach; ?>
          <span class="lp-x" aria-hidden="true">×</span>
        </div>
      </div>

      <div class="lp-compare-arrow" aria-hidden="true">
        <svg viewBox="0 0 120 80">
          <path class="lp-bridge" d="M8 40 C 40 8, 80 72, 104 40" fill="none" stroke="#1E4EFF" stroke-width="4" stroke-linecap="round"/>
        </svg>
        <span>→</span>
      </div>

      <div class="lp-compare-new">
        <p class="lp-kicker">The Vellisys way</p>
        <h2>One desk. A clear picture.</h2>
        <figure class="lp-new-shot">
          <img class="lp-new-main" src="<?= h(asset('img/landing/nw.png')) ?>" alt="A Vellisys receipt on desktop, laptop and phone">
        </figure>
      </div>
    </section>

    <section class="lp-band lp-band-alt" id="how-vellisys-helps" data-reveal>
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

    <section class="lp-band lp-path" id="get-a-desk" data-reveal>
      <p class="lp-kicker">Get a desk</p>
      <h2>You are three steps away</h2>
      <p class="lp-path-lead">Leave your details. We call you. Then the books go live.</p>
      <ol class="lp-path-steps">
        <?php foreach ($steps as $i => $card): ?>
          <li>
            <span class="lp-path-n"><?= (int) $i + 1 ?></span>
            <article>
              <img class="lp-card-pic" src="<?= h(landing_card_image_url($card)) ?>" alt="">
              <div class="lp-card-copy">
                <h3><?= h($card['title']) ?></h3>
                <p><?= h($card['body']) ?></p>
              </div>
            </article>
          </li>
        <?php endforeach; ?>
      </ol>
      <div class="lp-cta lp-cta-band">
        <a class="lp-btn lp-btn-solid lp-btn-lg" href="<?= h(url('register.php')) ?>">Get my company a desk</a>
        <a class="lp-btn lp-btn-ghost lp-btn-lg" href="<?= h(url('login.php')) ?>">Sign in to my desk</a>
      </div>
    </section>
  </main>

  <?php public_footer(); ?>
  <script src="<?= h(asset('js/landing.js')) ?>"></script>
</body>
</html>
