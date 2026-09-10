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
$reviews = landing_reviews();
$reviewSection = landing_review_section();
$manage = desk_manage_items();
$faqs = landing_faqs();
$_SESSION['ask_form_at'] = time();
pricing_display_currency();
$askFlash = flash();
$askedOk = isset($_GET['asked']);
$askDraft = $_SESSION['ask_draft'] ?? [];
?>
<!DOCTYPE html>
<html lang="en" data-pwa-login="<?= h(url('login.php')) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h(product_name()) ?> · Stop losing the books</title>
  <?php product_icons(); ?>
  <?php folio_landing_head(); ?>
  <script src="<?= h(asset('js/pwa-standalone.js')) ?>" data-cfasync="false"></script>
</head>
<body class="lp">
  <div class="lp-glow lp-glow-a" aria-hidden="true"></div>
  <div class="lp-glow lp-glow-b" aria-hidden="true"></div>
  <?php public_header('home'); ?>

  <main>
    <section class="lp-hero">
      <div class="lp-hero-copy" data-reveal>
        <h1>Lose track of your financial records?</h1>
        <p class="lp-lead">Receipts in a drawer and invoices in WhatsApp never add up. Vellisys keeps quotations, invoices, receipts and reports on one desk, printed in the client's logo and colours, then shared in a click.</p>
        <ul class="lp-hero-points">
          <li>Quotations</li>
          <li>Invoices</li>
          <li>Receipts</li>
          <li>Reports</li>
        </ul>
        <div class="lp-cta">
          <a class="lp-btn lp-btn-solid lp-btn-lg" href="<?= h(url('register.php')) ?>">Get my company a desk</a>
          <a class="lp-btn lp-btn-ghost lp-btn-lg" href="<?= h(url('login.php')) ?>">I already have a desk</a>
        </div>
        <p class="lp-note">Anywhere in the world. <a href="<?= h(url('register.php')) ?>">Register</a>. Get onboarded. Built for East Africa, used across Africa and worldwide.</p>
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
            <li><span>Invoiced</span><b>12.4m</b></li>
            <li><span>Collected</span><b>9.1m</b></li>
            <li><span>Open</span><b>3.3m</b></li>
          </ul>
        </article>
      </div>
    </section>

    <?php if ($clients): ?>
    <section class="lp-trust" id="clients-who-trust-us" aria-label="Clients who trust us">
      <p class="lp-kicker">On the desk</p>
      <h2>Clients who trust us</h2>
      <div class="lp-marquee">
        <div class="lp-marquee-track" data-marquee-track>
          <?php foreach ([$clients, $clients] as $setIndex => $set): ?>
            <div class="lp-marquee-set" data-marquee-set <?= $setIndex === 1 ? 'aria-hidden="true"' : '' ?>>
              <?php foreach ($set as $client): ?>
                <img src="<?= h(trust_client_logo_url($client)) ?>" alt="<?= $setIndex === 0 ? h($client['name']) : '' ?>" <?= $setIndex === 1 ? 'aria-hidden="true"' : '' ?> decoding="async">
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <section class="lp-band" id="sound-familiar" data-reveal>
      <h2>Sound familiar?</h2>
      <div class="lp-grid3">
        <?php foreach ($familiar as $card): ?>
          <article>
            <img class="lp-card-pic" src="<?= h(landing_card_image_url($card)) ?>" alt="<?= h($card['title'] ?? '') ?>" decoding="async">
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
        <div class="lp-stack" data-lp-stack role="button" tabindex="0" aria-expanded="false" aria-label="Paper pile. Tap to spread, tap again to stack.">
          <?php foreach ($oldPhotos as $i => $photo): ?>
            <img src="<?= h(asset(substr($photo, strlen('assets/')))) ?>" alt="Paper receipts from the old way of keeping books" style="--i:<?= (int) $i ?>" loading="lazy" decoding="async">
          <?php endforeach; ?>
          <span class="lp-x" aria-hidden="true">×</span>
        </div>
        <p class="lp-stack-hint">Tap the pile to spread it. Tap again to stack it. That drawer is why the books go missing.</p>
      </div>

      <div class="lp-compare-arrow" aria-hidden="true">
        <svg viewBox="0 0 120 80">
          <path class="lp-bridge" d="M8 40 C 40 8, 80 72, 104 40" fill="none" stroke="#1E4EFF" stroke-width="4" stroke-linecap="round"/>
        </svg>
        <span class="lp-arrow-right">→</span>
        <span class="lp-arrow-down">↓</span>
      </div>

      <div class="lp-compare-new">
        <p class="lp-kicker">The Vellisys way</p>
        <h2>Your brand. Their copy.</h2>
        <p class="lp-compare-lead">Documents are printed and sent in the client's branding. Pick from many templates - letterhead, ledger, twin copy and more - so every quotation, invoice and receipt looks like it came from their office, not a generic pad.</p>
        <figure class="lp-new-shot">
          <img class="lp-new-main" src="<?= h(landing_way_image_url()) ?>" alt="A Vellisys receipt on desktop, laptop and phone" width="1254" height="1254" decoding="async">
        </figure>
      </div>
    </section>

    <section class="lp-band lp-band-alt" id="how-vellisys-helps" data-reveal>
      <h2>Here is how Vellisys helps</h2>
      <div class="lp-grid3">
        <?php foreach ($help as $card): ?>
          <article>
            <img class="lp-card-pic" src="<?= h(landing_card_image_url($card)) ?>" alt="<?= h($card['title'] ?? '') ?>" decoding="async">
            <div class="lp-card-copy">
              <h3><?= h($card['title']) ?></h3>
              <p><?= h($card['body']) ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="lp-send" id="send-in-a-minute" data-reveal>
      <figure class="lp-send-pic">
        <img src="<?= h(asset(is_file(ROOT_PATH . '/assets/img/landing/rec-sm.webp') ? 'img/landing/rec-sm.webp' : 'img/landing/rec.png')) ?>" alt="A receipt generated on Vellisys, open on a phone and already sent to the client" decoding="async" width="1200" height="1200">
        <span class="lp-send-badge" aria-hidden="true">Sent · 48s</span>
        <span class="lp-send-ring" aria-hidden="true"></span>
      </figure>
      <div class="lp-send-copy">
        <p class="lp-kicker">From the desk to their phone</p>
        <h2>Generate it. Send it. They have it in a minute.</h2>
        <p>Raise a quotation, an invoice or a receipt on your desk. The sheet goes out in the client's own logo and colours, from a library of templates you pick once. Share it while they are still with you. One record, one tap, their copy is on the way.</p>
        <ul class="lp-send-docs">
          <li>Quotation</li>
          <li>Invoice</li>
          <li>Receipt</li>
          <li>Delivery note</li>
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

    <section class="lp-manage" id="what-you-manage" data-reveal>
      <p class="lp-kicker">The desk</p>
      <h2>What you get to manage</h2>
      <p class="lp-manage-lead">Quotations, invoices, receipts and expenses on one desk, in the company's branding. Delivery notes, letters, debtors and creditors sit beside them.</p>
      <div class="lp-manage-grid">
        <?php foreach ($manage as $item): ?>
          <article>
            <div class="lp-manage-head">
              <span class="lp-manage-icon" aria-hidden="true"><?= icon($item['icon'], 18) ?></span>
              <h3><?= h($item['title']) ?></h3>
            </div>
            <p><?= h($item['body']) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="lp-band lp-path" id="get-a-desk" data-reveal>
      <p class="lp-kicker">Get a desk</p>
      <h2>You are three steps away</h2>
      <p class="lp-path-lead">Anywhere you are in the world: register, get onboarded. Then the books go live.</p>
      <ol class="lp-path-steps">
        <?php foreach ($steps as $i => $card): ?>
          <li>
            <span class="lp-path-n"><?= (int) $i + 1 ?></span>
            <article>
              <img class="lp-card-pic" src="<?= h(landing_card_image_url($card)) ?>" alt="<?= h($card['title'] ?? '') ?>" decoding="async">
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

    <?php if ($reviews): ?>
    <section class="lp-reviews" id="testimonials" aria-label="Testimonials">
      <p class="lp-kicker"><?= h($reviewSection['kicker']) ?></p>
      <h2><?= h($reviewSection['heading']) ?></h2>
      <div class="lp-marquee lp-reviews-marquee">
        <div class="lp-marquee-track lp-reviews-track" data-marquee-track>
          <?php foreach ([$reviews, $reviews] as $setIndex => $set): ?>
            <div class="lp-marquee-set" data-marquee-set <?= $setIndex === 1 ? 'aria-hidden="true"' : '' ?>>
              <?php foreach ($set as $review): ?>
                <article class="lp-review" <?= $setIndex === 1 ? 'aria-hidden="true"' : '' ?>>
                  <blockquote><?= h($review['quote']) ?></blockquote>
                  <footer>
                    <strong><?= h($review['name']) ?></strong>
                    <?php if (trim((string) ($review['role'] ?? '')) !== ''): ?>
                      <span><?= h($review['role']) ?></span>
                    <?php endif; ?>
                  </footer>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <?php render_landing_pricing(); ?>

    <section class="lp-world lp-pay" id="pay" data-reveal>
      <p class="lp-kicker">Pay</p>
      <h2>Pick a package. Pay on this site.</h2>
      <p class="lp-world-lead">Choose Quill, Ledger or Crest, enter the company, then pay on the checkout page. Mobile money, a card, a bank or a wallet - in the currency you selected. You do not leave Vellisys. We onboard the desk after payment lands.</p>
      <ol class="lp-pay-flow">
        <li><b>1</b><span>Choose a package</span></li>
        <li><b>2</b><span>Enter company details</span></li>
        <li><b>3</b><span>Pay on this page</span></li>
        <li><b>4</b><span>We onboard the desk</span></li>
      </ol>
      <div class="lp-pay-board">
        <div class="lp-pay-col">
          <h3>Ways to pay</h3>
          <?php foreach (pesapal_payment_methods() as $group): ?>
            <p class="lp-pay-group"><?= h($group['group']) ?></p>
            <ul class="lp-pay-chips">
              <?php foreach ($group['items'] as $method): ?>
                <li><?= h($method) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endforeach; ?>
        </div>
        <div class="lp-pay-col">
          <h3>Pay in any currency</h3>
          <p class="lp-pay-note">Packages on this page are shown and charged in the currency you pick. The company desk can bill clients in any three-letter currency you set in Settings.</p>
          <ul class="lp-pay-chips lp-pay-ccy">
            <?php foreach (pricing_currencies() as $code => $meta): ?>
              <li><strong><?= h($code) ?></strong> <?= h($meta['name']) ?></li>
            <?php endforeach; ?>
            <li class="is-more"><strong>... & more</strong> any three-letter code</li>
          </ul>
        </div>
      </div>
      <div class="lp-cta lp-cta-band">
        <a class="lp-btn lp-btn-solid lp-btn-lg" href="<?= h(url()) ?>#pricing">See packages</a>
        <a class="lp-btn lp-btn-ghost lp-btn-lg" href="<?= h(url('register.php')) ?>">Register without paying</a>
      </div>
    </section>

    <section class="lp-ask" id="ask" data-reveal>
      <div class="lp-ask-copy">
        <p class="lp-kicker">Talk to us</p>
        <h2>Have a question?</h2>
        <p class="lp-ask-lead">Short answers below. If yours is not there, send a note - a Vellisys admin reads every one and replies by email.</p>
        <div class="lp-faqs">
          <?php foreach ($faqs as $i => $faq): ?>
            <details class="lp-faq"<?= $i === 0 ? ' open' : '' ?>>
              <summary><?= h($faq['q']) ?></summary>
              <p><?= h($faq['a']) ?><?php if (!empty($faq['link']['href'])): ?> <a href="<?= h(url((string) $faq['link']['href'])) ?>"><?= h((string) ($faq['link']['label'] ?? 'Learn more')) ?></a><?php endif; ?></p>
            </details>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="lp-ask-panel">
        <?php if ($askedOk): ?>
          <div class="lp-ask-form lp-ask-ok">
            <span class="lp-ask-mark" aria-hidden="true"><?= icon('heart', 26) ?></span>
            <p class="lp-kicker">Thank you</p>
            <h3>We have your note</h3>
            <p class="lp-ask-ok-lead">A person on the Vellisys team will read it and reply by email. You are not waiting on a ticket queue.</p>
            <ul class="lp-ask-ok-next">
              <li>
                <span><?= icon('letter', 18) ?></span>
                <p>A short confirmation is on its way from <a href="mailto:<?= h(product_email()) ?>"><?= h(product_email()) ?></a>.</p>
              </li>
              <li>
                <span><?= icon('phone', 18) ?></span>
                <p>If you need us today, call <a href="tel:+<?= h(phone_digits(product_phones()[0])) ?>"><?= h(product_phones()[0]) ?></a>.</p>
              </li>
            </ul>
            <div class="lp-ask-ok-actions">
              <a class="lp-btn lp-btn-solid" href="<?= h(url()) ?>#ask">Ask another</a>
              <a class="lp-btn lp-btn-ghost" href="tel:+<?= h(phone_digits(product_phones()[0])) ?>">Call us</a>
            </div>
          </div>
        <?php else: ?>
          <form class="lp-ask-form" method="post" action="<?= h(url('ask.php')) ?>" autocomplete="off">
            <?= csrf_field() ?>
            <h3>Write to us</h3>
            <p class="lp-ask-hint">Name, email, and your question. Phone is optional.</p>
            <?php if ($askFlash && ($askFlash['type'] ?? '') === 'err'): ?>
              <p class="lp-err"><?= h($askFlash['text']) ?></p>
            <?php endif; ?>
            <div class="lp-hp" aria-hidden="true">
              <label>Website
                <input type="text" name="website" tabindex="-1" autocomplete="off">
              </label>
            </div>
            <label for="ask_name">Your name
              <input id="ask_name" name="ask_name" required maxlength="80" autocomplete="name" value="<?= h($askDraft['name'] ?? '') ?>" placeholder="Jane Okello">
            </label>
            <label for="ask_email">Email
              <input id="ask_email" name="ask_email" type="email" required maxlength="190" autocomplete="email" value="<?= h($askDraft['email'] ?? '') ?>" placeholder="you@company.ug">
            </label>
            <label for="ask_phone">Phone <span>(optional)</span>
              <input id="ask_phone" name="ask_phone" type="tel" maxlength="40" autocomplete="tel" value="<?= h($askDraft['phone'] ?? '') ?>" placeholder="+256 700 000 000">
            </label>
            <label for="ask_message">Question
              <textarea id="ask_message" name="ask_message" required minlength="20" maxlength="2000" rows="5" placeholder="How do we add a second user on the desk?"><?= h($askDraft['message'] ?? '') ?></textarea>
            </label>
            <button class="lp-btn lp-btn-solid" type="submit">Send question</button>
          </form>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <?php public_footer(); ?>
  <script src="<?= h(asset('js/landing.js')) ?>" defer></script>
</body>
</html>
