<?php
declare(strict_types=1);

function layout_start(string $title, array $user, array $opts = []): void
{
    $brand = branding();
    $color = brand_color();
    $flash = flash();
    $kind = $opts['kind'] ?? ($_GET['kind'] ?? '');
    $nav = [
        ['dashboard.php', 'Desk'],
        ['documents.php?kind=invoice', 'Invoices'],
        ['documents.php?kind=quotation', 'Quotations'],
        ['documents.php?kind=receipt', 'Receipts'],
        ['documents.php?kind=expense', 'Expenses'],
        ['documents.php?kind=letter', 'Letters'],
        ['clients.php', 'Clients'],
        ['reports.php', 'Reports'],
        ['branding.php', 'Branding'],
    ];
    $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> · <?= h($brand['name']) ?></title>
  <link rel="stylesheet" href="<?= h(asset('css/app.css')) ?>">
  <style>:root { --brand: <?= h($color) ?>; --brand-ink: #14300a; }</style>
</head>
<body>
<div class="app">
  <aside class="nav">
    <a class="brand" href="<?= h(url('dashboard.php')) ?>">
      <span class="brand-kicker">Folio</span>
      <strong><?= h($brand['name']) ?></strong>
    </a>
    <nav>
      <?php foreach ($nav as [$href, $label]):
          $file = strtok($href, '?');
          $active = $file === $here && (strpos($href, 'kind=') === false || str_contains($href, 'kind=' . $kind));
          if (in_array($here, ['document_view.php', 'document_new.php', 'document_email.php', 'document_action.php'], true)) {
              $active = $file === 'documents.php' && str_contains($href, 'kind=' . $kind);
          }
          if (in_array($here, ['client_view.php', 'client_edit.php'], true)) {
              $active = $file === 'clients.php';
          }
          ?>
        <a class="<?= $active ? 'is-on' : '' ?>" href="<?= h(url($href)) ?>"><?= h($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="nav-user">
      <span><?= h($user['name']) ?></span>
      <a href="<?= h(url('logout.php')) ?>">Sign out</a>
    </div>
  </aside>
  <div class="main">
    <header class="top">
      <button class="menu-btn" type="button" data-menu>Menu</button>
      <div class="top-actions">
        <button class="btn quick-add" type="button" data-quick title="Quick add">+ Quick add</button>
        <a class="btn" href="<?= h(url('document_new.php?kind=invoice')) ?>">New invoice</a>
      </div>
    </header>
    <?php if ($flash): ?>
      <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['text']) ?></div>
    <?php endif; ?>
    <div class="content">
<?php
}

function layout_end(): void
{
    ?>
    </div>
  </div>
</div>

<div class="quick" hidden data-quick-panel>
  <p>Quick add</p>
  <a href="<?= h(url('document_new.php?kind=invoice')) ?>">Invoice</a>
  <a href="<?= h(url('document_new.php?kind=quotation')) ?>">Quotation</a>
  <a href="<?= h(url('document_new.php?kind=receipt')) ?>">Receipt</a>
  <a href="<?= h(url('document_new.php?kind=expense')) ?>">Expense</a>
  <a href="<?= h(url('document_new.php?kind=letter')) ?>">Letter</a>
  <a href="<?= h(url('client_edit.php')) ?>">Client</a>
</div>
<script src="<?= h(asset('js/app.js')) ?>"></script>
</body>
</html>
<?php
}
