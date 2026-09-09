<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if ($user = current_user()) {
    redirect(($user['role'] ?? '') === 'platform' ? 'admin_signups.php' : 'dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h(product_name()) ?> · Stop losing the books</title>
  <meta name="description" content="Lose track of receipts, invoices and who still owes you? Vellisys keeps the books on one desk. Register in a minute. We reach out, then your company is onboarded.">
  <?php product_icons(); ?>
  <?php folio_font_links(); ?>
  <link rel="stylesheet" href="<?= h(asset('css/landing.css')) ?>">
</head>
<body class="lp">
  <div class="lp-glow lp-glow-a" aria-hidden="true"></div>
  <div class="lp-glow lp-glow-b" aria-hidden="true"></div>

  <header class="lp-nav">
    <a class="lp-brand" href="<?= h(url()) ?>">
      <img src="<?= h(product_mark_url()) ?>" width="36" height="36" alt="">
      <span class="lp-name">ellisys</span>
    </a>
    <nav>
      <a href="#pain">The pain</a>
      <a href="#fix">The fix</a>
      <a class="lp-btn lp-btn-ghost" href="<?= h(url('login.php')) ?>">Sign in</a>
      <a class="lp-btn lp-btn-solid" href="<?= h(url('register.php')) ?>">Register</a>
    </nav>
  </header>

  <main>
    <section class="lp-hero">
      <p class="lp-kicker">Accounting for busy companies</p>
      <h1>Lose track of your financial records?</h1>
      <p class="lp-lead">Tired of receipts in a drawer, invoices in a WhatsApp chat, and books stuck on one office PC? Vellisys puts quotations, invoices, receipts and reports on one desk - then you share a record with a client in a single click.</p>
      <div class="lp-cta">
        <a class="lp-btn lp-btn-solid" href="<?= h(url('register.php')) ?>">Register your company</a>
        <a class="lp-btn lp-btn-ghost" href="#fix">See the fix</a>
      </div>
      <p class="lp-note">No password to invent. Leave your details. A Vellisys admin calls you and opens the desk.</p>

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

    <section class="lp-band" id="pain">
      <p class="lp-kicker">The pain</p>
      <h2>This is how the books get away from you</h2>
      <div class="lp-grid3">
        <article>
          <h3>Tired of receipts?</h3>
          <p>Slips in a drawer, photos on a phone, part payments in UGX and USD. You cannot tell what is still owed without a hunt.</p>
        </article>
        <article>
          <h3>You lose track of the records</h3>
          <p>Quotations sit in email. Invoices live in a spreadsheet. Nobody has one number for who still owes the company.</p>
        </article>
        <article>
          <h3>The books stay in one office</h3>
          <p>If you are on the road, the PC is off, or the accountant is out, the records are out of reach.</p>
        </article>
      </div>
    </section>

    <section class="lp-band lp-band-alt" id="fix">
      <p class="lp-kicker">The Vellisys fix</p>
      <h2>One desk. Share a record. Open it anywhere.</h2>
      <div class="lp-grid3">
        <article>
          <h3>Generate and share in one click</h3>
          <p>Issue a quotation, invoice or receipt in your logo and colour, then email or print the sheet. Clients get the record, not a chase.</p>
        </article>
        <article>
          <h3>Any time, anywhere</h3>
          <p>Sign in and the books are there - this month's invoices, receipts, expenses and reports - wherever you are.</p>
        </article>
        <article>
          <h3>The full set, still in order</h3>
          <p>Quotations convert to invoices. Invoices take full or part receipts. Expenses, debtors, creditors and VAT sit in one place.</p>
        </article>
      </div>
    </section>

    <section class="lp-band" id="how">
      <p class="lp-kicker">How you get a desk</p>
      <h2>Register. We call. You go live.</h2>
      <ol class="lp-steps">
        <li>
          <strong>1. Leave your details</strong>
          <span>Name, company, email, phone. That is the whole form.</span>
        </li>
        <li>
          <strong>2. We reach out</strong>
          <span>A Vellisys super admin sees the sign-up and calls you to onboard the company.</span>
        </li>
        <li>
          <strong>3. The desk opens</strong>
          <span>You get a login. The books are yours, on any device.</span>
        </li>
      </ol>
      <div class="lp-cta" style="margin-top:8px">
        <a class="lp-btn lp-btn-solid" href="<?= h(url('register.php')) ?>">Register now</a>
      </div>
    </section>
  </main>

  <footer class="lp-foot">
    <img src="<?= h(product_mark_url()) ?>" width="28" height="28" alt="">
    <span><?= h(product_name()) ?></span>
    <a href="<?= h(url('login.php')) ?>">Already onboarded? Sign in</a>
  </footer>
  <script src="<?= h(asset('js/landing.js')) ?>"></script>
</body>
</html>
