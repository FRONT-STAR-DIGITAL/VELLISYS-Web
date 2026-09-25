<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$token = (string) ($_GET['t'] ?? '');

$probe = $id > 0
    ? db_one('SELECT id, company_id, number, status, kind, date, created_at, party_id FROM documents WHERE id = ?', 'i', [$id])
    : null;

$validToken = $probe && hash_equals(document_share_token($probe), $token);
$valid = false;
$voided = false;
$doc = null;
$brand = folio_defaults();
$partyName = '';
$generated = '';
$number = '';
$kindLabel = 'Document';
$companyName = '';
$companyPhone = '';
$companyEmail = '';
$companyWebsite = '';

if ($validToken && $probe) {
    $GLOBALS['folio_company_override'] = (int) $probe['company_id'];
    $doc = load_document($id);
    if ($doc) {
        $brand = branding_for((int) $probe['company_id']);
        branding(true);
        $voided = strtolower((string) ($doc['status'] ?? '')) === 'void';
        $valid = document_is_authenticity_valid($doc);
        $partyName = document_party_display_name($doc);
        $number = (string) ($doc['number'] ?? '');
        $kindLabel = kind_meta((string) ($doc['kind'] ?? ''))['singular'] ?? 'Document';
        $created = trim((string) ($doc['created_at'] ?? ''));
        $generated = $created !== ''
            ? format_date(substr($created, 0, 10)) . (strlen($created) >= 16 ? ' · ' . substr($created, 11, 5) : '')
            : format_date((string) ($doc['date'] ?? ''));
        $companyName = trim((string) ($brand['name'] ?? ''));
        $companyPhone = trim((string) ($brand['phone'] ?? ''));
        $companyEmail = trim((string) ($brand['email'] ?? ''));
        $companyWebsite = trim((string) ($brand['website'] ?? ''));
    }
}

$primary = parse_hex_color((string) ($brand['brand_color'] ?? ''), '#1E4EFF');
$accent = parse_hex_color((string) ($brand['brand_accent'] ?? ''), '#8EB0FF');
$deep = parse_hex_color((string) ($brand['brand_deep'] ?? ''), '#08143A');
$statusTitle = !$validToken || !$doc
    ? 'This document is not valid'
    : ($voided ? 'This document is not valid' : ($valid ? 'This document is valid' : 'This document is not valid'));
$statusClass = ($validToken && $doc && $valid) ? 'is-valid' : 'is-invalid';
$logo = ($validToken && $doc) ? logo_url($brand) : '';
$site = product_site_url();
$product = product_name();

http_response_code(($validToken && $doc) ? 200 : 404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex,nofollow">
  <title><?= h($statusTitle) ?> · <?= h($product) ?></title>
  <?php product_icons(); ?>
  <?php folio_font_links(); ?>
  <style>
    :root {
      --brand: <?= h($primary) ?>;
      --brand-2: <?= h($accent) ?>;
      --brand-3: <?= h($deep) ?>;
      --ink: #10182c;
      --muted: #5c6780;
      --line: rgba(8, 20, 58, .10);
      --ok: #0f7a45;
      --ok-bg: #e8f7ef;
      --bad: #b42318;
      --bad-bg: #fdecec;
      --sans: Montserrat, "Segoe UI", sans-serif;
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; min-height: 100%; }
    body {
      font-family: var(--sans);
      color: var(--ink);
      background:
        radial-gradient(1200px 480px at 10% -10%, color-mix(in srgb, var(--brand) 18%, #fff), transparent 60%),
        radial-gradient(900px 420px at 100% 0%, color-mix(in srgb, var(--brand-2) 22%, #fff), transparent 55%),
        #f4f6fb;
    }
    .verify-wrap {
      width: min(560px, calc(100% - 32px));
      margin: 40px auto 28px;
    }
    .verify-card {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 20px;
      box-shadow: 0 18px 48px rgba(8, 20, 58, .08);
      overflow: hidden;
    }
    .verify-brand {
      padding: 22px 24px 18px;
      background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 12%, #fff), #fff 70%);
      border-bottom: 1px solid var(--line);
      display: flex;
      gap: 14px;
      align-items: center;
    }
    .verify-brand img {
      width: 52px;
      height: 52px;
      object-fit: contain;
      border-radius: 12px;
      background: #fff;
      border: 1px solid var(--line);
    }
    .verify-brand strong {
      display: block;
      font-size: 1.15rem;
      color: var(--brand-3);
      line-height: 1.25;
    }
    .verify-brand span {
      display: block;
      margin-top: 4px;
      font-size: 13px;
      color: var(--muted);
      font-weight: 500;
    }
    .verify-status {
      margin: 22px 24px 8px;
      padding: 16px 18px;
      border-radius: 14px;
      display: flex;
      gap: 12px;
      align-items: flex-start;
    }
    .verify-status.is-valid {
      background: var(--ok-bg);
      color: var(--ok);
      border: 1px solid color-mix(in srgb, var(--ok) 22%, #fff);
    }
    .verify-status.is-invalid {
      background: var(--bad-bg);
      color: var(--bad);
      border: 1px solid color-mix(in srgb, var(--bad) 22%, #fff);
    }
    .verify-status b {
      display: block;
      font-size: 1.2rem;
      line-height: 1.25;
    }
    .verify-status p {
      margin: 6px 0 0;
      font-size: 13px;
      font-weight: 500;
      opacity: .92;
    }
    .verify-grid {
      padding: 8px 24px 8px;
      display: grid;
      gap: 0;
    }
    .verify-row {
      display: grid;
      grid-template-columns: 132px minmax(0, 1fr);
      gap: 12px;
      padding: 14px 0;
      border-top: 1px solid var(--line);
    }
    .verify-row:first-child { border-top: 0; }
    .verify-row span {
      font-size: 12px;
      font-weight: 700;
      letter-spacing: .04em;
      text-transform: uppercase;
      color: var(--muted);
      padding-top: 2px;
    }
    .verify-row strong {
      font-size: 15px;
      font-weight: 650;
      color: var(--ink);
      overflow-wrap: anywhere;
    }
    .verify-row em {
      display: block;
      margin-top: 4px;
      font-style: normal;
      font-size: 13px;
      font-weight: 500;
      color: var(--muted);
    }
    .verify-note {
      margin: 4px 24px 22px;
      padding: 12px 14px;
      border-radius: 12px;
      background: #f7f8fc;
      color: var(--muted);
      font-size: 12.5px;
      line-height: 1.45;
    }
    .verify-powered {
      text-align: center;
      padding: 8px 16px 36px;
      color: var(--muted);
      font-size: 13px;
      font-weight: 600;
    }
    .verify-powered a {
      color: var(--brand);
      text-decoration: none;
      font-weight: 700;
    }
    .verify-powered a:hover { text-decoration: underline; }
    @media (max-width: 560px) {
      .verify-wrap { width: calc(100% - 24px); margin-top: 20px; }
      .verify-brand, .verify-status { margin-left: 16px; margin-right: 16px; }
      .verify-brand { margin-left: 0; margin-right: 0; padding: 18px 16px; }
      .verify-status { margin-left: 16px; margin-right: 16px; }
      .verify-grid { padding-left: 16px; padding-right: 16px; }
      .verify-note { margin-left: 16px; margin-right: 16px; }
      .verify-row { grid-template-columns: 1fr; gap: 4px; }
    }
  </style>
</head>
<body>
  <main class="verify-wrap">
    <article class="verify-card">
      <header class="verify-brand">
        <?php if ($logo !== ''): ?>
          <img src="<?= h($logo) ?>" alt="">
        <?php endif; ?>
        <div>
          <strong><?= h($companyName !== '' ? $companyName : $product) ?></strong>
          <span>Document authenticity check</span>
        </div>
      </header>

      <div class="verify-status <?= h($statusClass) ?>">
        <div>
          <b><?= h($statusTitle) ?></b>
          <?php if (!$validToken || !$doc): ?>
            <p>This link is not recognised. Ask the sender for a fresh copy of the document.</p>
          <?php elseif ($voided): ?>
            <p>The issuer has voided this document. It should not be treated as active.</p>
          <?php elseif ($valid): ?>
            <p>Issued through <?= h($product) ?>. No payment or line-item details are shown on this page.</p>
          <?php else: ?>
            <p>This document could not be confirmed as active.</p>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($validToken && $doc): ?>
        <div class="verify-grid">
          <div class="verify-row">
            <span>Document</span>
            <strong><?= h($number) ?><em><?= h($kindLabel) ?></em></strong>
          </div>
          <div class="verify-row">
            <span>Generated</span>
            <strong><?= h($generated !== '' ? $generated : '—') ?></strong>
          </div>
          <div class="verify-row">
            <span>Sent by</span>
            <strong>
              <?= h($companyName !== '' ? $companyName : '—') ?>
              <?php if ($companyPhone !== '' || $companyEmail !== '' || $companyWebsite !== ''): ?>
                <em>
                  <?= h(implode(' · ', array_values(array_filter([$companyPhone, $companyEmail, $companyWebsite])))) ?>
                </em>
              <?php endif; ?>
            </strong>
          </div>
          <div class="verify-row">
            <span>Sent to</span>
            <strong><?= h($partyName !== '' ? $partyName : '—') ?></strong>
          </div>
        </div>
        <p class="verify-note">This check confirms the document exists on the issuer’s Vellisys desk. It does not show amounts, line items or payment details.</p>
      <?php endif; ?>
    </article>
  </main>
  <p class="verify-powered">Powered by <a href="<?= h($site) ?>"><?= h($product) ?></a></p>
</body>
</html>
