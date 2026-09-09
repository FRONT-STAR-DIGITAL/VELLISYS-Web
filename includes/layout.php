<?php
declare(strict_types=1);

function layout_start(string $title, array $user, array $opts = []): void
{
    $brand = branding();
    $color = brand_color();
    $flash = flash();
    $kind = $opts['kind'] ?? ($_GET['kind'] ?? '');
    $nav = [
        ['dashboard.php', 'Desk', 'desk'],
        ['documents.php?kind=invoice', 'Invoices', 'invoice'],
        ['documents.php?kind=quotation', 'Quotations', 'quotation'],
        ['documents.php?kind=receipt', 'Receipts', 'receipt'],
        ['documents.php?kind=expense', 'Expenses', 'expense'],
        ['documents.php?kind=letter', 'Correspondence', 'letter'],
        ['debtors.php', 'Debtors', 'clients'],
        ['creditors.php', 'Creditors', 'bank'],
        ['clients.php', 'Clients', 'building'],
        ['reports.php', 'Reports', 'reports'],
        ['settings.php', 'Settings', 'settings'],
    ];
    $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> · <?= h($brand['name']) ?></title>
  <?php folio_font_links(); ?>
  <link rel="stylesheet" href="<?= h(asset('css/app.css')) ?>">
  <style>:root { --brand: <?= h($color) ?>; }</style>
</head>
<body class="desk-body">
<div class="app">
  <aside class="nav">
    <a class="brand" href="<?= h(url('dashboard.php')) ?>">
      <img class="brand-mark" src="<?= h(logo_url()) ?>" alt="">
      <span class="brand-kicker">Folio</span>
      <strong><?= h($brand['name']) ?></strong>
    </a>
    <nav>
      <?php foreach ($nav as [$href, $label, $iconName]):
          $file = strtok($href, '?');
          $active = $file === $here && (strpos($href, 'kind=') === false || str_contains($href, 'kind=' . $kind));
          if (in_array($here, ['document_view.php', 'document_new.php', 'document_email.php', 'document_action.php'], true)) {
              $active = $file === 'documents.php' && str_contains($href, 'kind=' . $kind);
              if ($here === 'document_action.php' && isset($_GET['pay'])) {
                  $active = $file === 'creditors.php' || ($file === 'documents.php' && str_contains($href, 'kind=expense'));
              }
              if ($here === 'document_action.php' && isset($_GET['receive'])) {
                  $active = $file === 'debtors.php' || ($file === 'documents.php' && str_contains($href, 'kind=invoice'));
              }
          }
          if (in_array($here, ['client_view.php', 'client_edit.php'], true)) {
              $active = $file === 'clients.php';
          }
          if ($here === 'branding.php') {
              $active = $file === 'settings.php';
          }
          ?>
        <a class="<?= $active ? 'is-on' : '' ?>" href="<?= h(url($href)) ?>"><?= icon($iconName, 17) ?><span><?= h($label) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="nav-user">
      <span class="nav-user-name"><?= icon('user', 16) ?><?= h($user['name']) ?></span>
      <span class="nav-user-mail"><?= h($user['email']) ?></span>
      <a href="<?= h(url('logout.php')) ?>"><?= icon('logout', 15) ?>Sign out</a>
    </div>
  </aside>
  <div class="main">
    <header class="top">
      <button class="btn ghost menu-btn" type="button" data-menu><?= icon('menu', 16) ?>Menu</button>
      <div class="top-actions">
        <button class="btn ghost" type="button" data-quick><?= icon('plus', 16) ?>Quick add</button>
        <a class="btn" href="<?= h(url('document_new.php?kind=invoice')) ?>"><?= icon('invoice', 16) ?>New invoice</a>
      </div>
    </header>
    <?php if ($flash): ?>
      <div class="flash flash-<?= h($flash['type']) ?>"><?= $flash['type'] === 'ok' ? icon('check', 16) : icon('alert', 16) ?><?= h($flash['text']) ?></div>
    <?php endif; ?>
    <div class="content">
<?php
}

function layout_admin_start(string $title, array $user): void
{
    $flash = flash();
    $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $nav = [
        ['admin_companies.php', 'Companies', 'building'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> · Folio admin</title>
  <?php folio_font_links(); ?>
  <link rel="stylesheet" href="<?= h(asset('css/app.css')) ?>">
  <style>:root { --brand: #82B440; }</style>
</head>
<body class="desk-body">
<div class="app">
  <aside class="nav">
    <a class="brand" href="<?= h(url('admin_companies.php')) ?>">
      <span class="brand-kicker">Folio</span>
      <strong>Platform admin</strong>
    </a>
    <nav>
      <?php foreach ($nav as [$href, $label, $iconName]):
          $file = strtok($href, '?');
          $active = $file === $here || ($here === 'admin_company.php' && $file === 'admin_companies.php');
          ?>
        <a class="<?= $active ? 'is-on' : '' ?>" href="<?= h(url($href)) ?>"><?= icon($iconName, 17) ?><span><?= h($label) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="nav-user">
      <span class="nav-user-name"><?= icon('user', 16) ?><?= h($user['name']) ?></span>
      <span class="nav-user-mail"><?= h($user['email']) ?></span>
      <a href="<?= h(url('logout.php')) ?>"><?= icon('logout', 15) ?>Sign out</a>
    </div>
  </aside>
  <div class="main">
    <header class="top">
      <button class="btn ghost menu-btn" type="button" data-menu><?= icon('menu', 16) ?>Menu</button>
      <div class="top-actions">
        <a class="btn" href="<?= h(url('admin_companies.php?new=1')) ?>"><?= icon('plus', 16) ?>New company</a>
      </div>
    </header>
    <?php if ($flash): ?>
      <div class="flash flash-<?= h($flash['type']) ?>"><?= $flash['type'] === 'ok' ? icon('check', 16) : icon('alert', 16) ?><?= h($flash['text']) ?></div>
    <?php endif; ?>
    <div class="content">
<?php
}

function layout_end(string $extra = ''): void
{
    $admin = str_starts_with(basename($_SERVER['SCRIPT_NAME'] ?? ''), 'admin_');
    ?>
    </div>
  </div>
</div>

<?php if (!$admin): ?>
<div class="quick" hidden data-quick-panel>
  <p>Quick add</p>
  <a href="<?= h(url('document_new.php?kind=invoice')) ?>"><?= icon('invoice') ?>Invoice</a>
  <a href="<?= h(url('document_new.php?kind=quotation')) ?>"><?= icon('quotation') ?>Quotation</a>
  <a href="<?= h(url('document_new.php?kind=receipt')) ?>"><?= icon('receipt') ?>Receipt</a>
  <a href="<?= h(url('document_new.php?kind=expense')) ?>"><?= icon('expense') ?>Expense</a>
  <a href="<?= h(url('document_new.php?kind=letter')) ?>"><?= icon('letter') ?>Correspondence</a>
  <a href="<?= h(url('client_edit.php')) ?>"><?= icon('clients') ?>Client</a>
</div>
<?php endif; ?>
<script src="<?= h(asset('js/app.js')) ?>"></script>
<?= $extra ?>
</body>
</html>
<?php
}