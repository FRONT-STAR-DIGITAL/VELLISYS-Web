<?php
declare(strict_types=1);

function render_top_clock(): void
{
    $now = desk_now();
    ?>
    <div class="top-clock" data-clock>
      <?= icon('clock', 15) ?>
      <span data-clock-date><?= h($now->format('D j M Y')) ?></span>
      <span class="top-clock-dot" aria-hidden="true">·</span>
      <time datetime="<?= h($now->format('c')) ?>" data-clock-time><?= h($now->format('H:i:s')) ?></time>
    </div>
    <?php
}

function render_top_term(?array $company): void
{
    if (!$company) {
        return;
    }
    $state = company_expiry_state($company);
    $class = match ($state) {
        'expired' => 'top-term-expired',
        'soon' => 'top-term-soon',
        'ok' => 'top-term-ok',
        default => 'top-term-none',
    };
    $iconName = in_array($state, ['expired', 'soon'], true) ? 'alert' : 'calendar';
    ?>
    <div class="top-term <?= $class ?>">
      <?= icon($iconName, 15) ?>
      <span class="top-term-left"><?= h(company_remaining_phrase($company)) ?></span>
      <span class="top-clock-dot" aria-hidden="true">·</span>
      <span class="top-term-exp"><?= h(company_expiry_date_label($company)) ?></span>
    </div>
    <?php
}

function layout_start(string $title, array $user, array $opts = []): void
{
    $brand = branding();
    $deskCompany = current_company();
    $flash = flash();
    $kind = $opts['kind'] ?? ($_GET['kind'] ?? '');
    $primaryKind = desk_primary_kind();
    $nav = array_merge(
        [['dashboard.php', 'Desk', 'desk']],
        desk_kind_nav_items(),
        [
            ['desk_mail.php', 'Email', 'send'],
            ['debtors.php', 'Debtors', 'clients'],
            ['creditors.php', 'Creditors', 'bank'],
            ['clients.php', 'Clients', 'building'],
            ['tutorials.php', 'Tutorials', 'book'],
            ['reports.php', 'Reports', 'reports'],
            ['settings.php', 'Settings', 'settings'],
        ]
    );
    $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> · <?= h(product_name()) ?></title>
  <?php product_icons(); ?>
  <?php folio_css_links(); ?>
  <?php folio_font_links(); ?>
  <style>:root { <?= brand_css_vars() ?> }</style>
</head>
<body class="desk-body">
<div class="app">
  <div class="nav-scrim" data-nav-scrim hidden></div>
  <aside class="nav" data-nav>
    <a class="brand" href="<?= h(url('dashboard.php')) ?>">
      <img class="brand-logo" src="<?= h(logo_url($brand)) ?>" alt="<?= h($brand['name']) ?>">
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
        <a class="<?= $active ? 'is-on' : '' ?>" href="<?= h(url($href)) ?>" title="<?= h($label) ?>"><?= icon($iconName, 18) ?><span><?= h($label) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="nav-user">
      <span class="nav-user-name"><?= icon('user', 16) ?><span><?= h($user['name']) ?></span></span>
      <span class="nav-user-mail"><?= h($user['email']) ?></span>
      <?php if (is_acting_admin()): ?>
        <a href="<?= h(url('admin_desk.php?leave=1')) ?>" title="Leave desk"><?= icon('logout', 15) ?><span>Leave desk</span></a>
      <?php endif; ?>
      <a href="<?= h(url('logout.php')) ?>" title="Sign out"><?= icon('logout', 15) ?><span>Sign out</span></a>
    </div>
  </aside>
  <div class="main">
    <?php if (is_acting_admin()): ?>
      <div class="acting-bar">
        <span>Working the desk for <strong><?= h($brand['name']) ?></strong></span>
        <a href="<?= h(url('admin_desk.php?leave=1')) ?>">Leave desk</a>
      </div>
    <?php endif; ?>
    <header class="top">
      <button class="nav-toggle" type="button" data-nav-toggle aria-label="Menu" aria-expanded="false"><?= icon('menu', 20) ?></button>
      <div class="top-meta">
        <?php render_top_clock(); ?>
        <?php render_top_term($deskCompany); ?>
      </div>
      <div class="top-actions">
        <button class="btn ghost" type="button" data-quick><?= icon('plus', 16) ?>Quick add</button>
        <a class="btn" href="<?= h(url('document_new.php?kind=' . $primaryKind)) ?>"><?= icon(document_kind_icon($primaryKind), 16) ?><?= h(kind_meta($primaryKind)['verb']) ?></a>
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
    $signupNew = new_signup_count();
    $questionNew = new_question_count();
    $nav = [
        ['admin_landing.php', 'Landing', 'image'],
        ['admin_signups.php', 'Sign-ups', 'letter'],
        ['admin_questions.php', 'Questions', 'help'],
        ['admin_companies.php', 'Companies', 'building'],
        ['admin_reports.php', 'Reports', 'reports'],
        ['admin_mail.php', 'Email', 'send'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> · <?= h(product_name()) ?> admin</title>
  <?php product_icons(); ?>
  <?php folio_css_links(); ?>
  <?php folio_font_links(); ?>
  <style>:root { <?= product_css_vars() ?> }</style>
</head>
<body class="desk-body admin-body">
<div class="app">
  <div class="nav-scrim" data-nav-scrim hidden></div>
  <aside class="nav" data-nav>
    <a class="brand" href="<?= h(url('admin_signups.php')) ?>">
      <img class="brand-logo" src="<?= h(product_mark_url()) ?>" alt="<?= h(product_name()) ?>">
      <strong>Platform admin</strong>
    </a>
    <nav>
      <?php foreach ($nav as [$href, $label, $iconName]):
          $file = strtok($href, '?');
          $active = $file === $here
              || ($here === 'admin_company.php' && $file === 'admin_companies.php')
              || ($here === 'admin_question.php' && $file === 'admin_questions.php');
          $count = 0;
          if ($file === 'admin_signups.php') {
              $count = $signupNew;
          } elseif ($file === 'admin_questions.php') {
              $count = $questionNew;
          }
          ?>
        <a class="<?= $active ? 'is-on' : '' ?>" href="<?= h(url($href)) ?>" title="<?= h($label) ?>"<?= $count ? ' data-badge="' . (int) $count . '"' : '' ?>><?= icon($iconName, 18) ?><span><?= h($label) ?><?= $count ? ' (' . $count . ')' : '' ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="nav-user">
      <span class="nav-user-name"><?= icon('user', 16) ?><span><?= h($user['name']) ?></span></span>
      <span class="nav-user-mail"><?= h($user['email']) ?></span>
      <a href="<?= h(url('logout.php')) ?>" title="Sign out"><?= icon('logout', 15) ?><span>Sign out</span></a>
    </div>
  </aside>
  <div class="main">
    <header class="top">
      <button class="nav-toggle" type="button" data-nav-toggle aria-label="Menu" aria-expanded="false"><?= icon('menu', 20) ?></button>
      <div class="top-meta">
        <?php render_top_clock(); ?>
      </div>
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
  <?php foreach (desk_kind_nav_items() as [$href, $label, $iconName, $qKind]): ?>
    <a href="<?= h(url('document_new.php?kind=' . $qKind)) ?>"><?= icon($iconName) ?><?= h($qKind === 'expense' ? 'Expense' : kind_meta($qKind)['singular']) ?></a>
  <?php endforeach; ?>
  <a href="<?= h(url('desk_mail.php')) ?>"><?= icon('send') ?>Email</a>
  <a href="<?= h(url('client_edit.php')) ?>"><?= icon('clients') ?>Client</a>
</div>
<?php endif; ?>
<script src="<?= h(asset('js/app.js')) ?>"></script>
<script src="<?= h(asset('js/sheet-fit.js')) ?>"></script>
<?= $extra ?>
</body>
</html>
<?php
}