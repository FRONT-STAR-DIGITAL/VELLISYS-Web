<?php
declare(strict_types=1);

function render_top_clock(): void
{
    $now = desk_now();
    ?>
    <div class="top-clock" data-clock>
      <?= icon('calendar', 18) ?>
      <span data-clock-date><?= h($now->format('D j M Y')) ?></span>
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

function render_page_loader(): void
{
    ?>
<style>
.page-loader{position:fixed;inset:0;z-index:400;display:flex;align-items:center;justify-content:center;background:transparent;pointer-events:none}
.page-loader-mark{width:92px;height:92px;border-radius:18px;background:transparent;position:relative;box-shadow:none}
.page-loader-dot{position:absolute;left:50%;top:50%;width:12px;height:12px;margin:-6px 0 0 -6px;border-radius:50%;background:var(--brand);transform:rotate(calc(var(--i)*60deg)) translateY(-22px);animation:page-loader-pulse .9s ease-in-out infinite;animation-delay:calc(var(--i)*.12s)}
@keyframes page-loader-pulse{0%,80%,100%{opacity:.22}40%{opacity:1}}
</style>
<div class="page-loader" id="page-loader" role="status" aria-live="polite" aria-label="Loading">
  <div class="page-loader-mark" aria-hidden="true">
    <span class="page-loader-dot" style="--i:0"></span>
    <span class="page-loader-dot" style="--i:1"></span>
    <span class="page-loader-dot" style="--i:2"></span>
    <span class="page-loader-dot" style="--i:3"></span>
    <span class="page-loader-dot" style="--i:4"></span>
    <span class="page-loader-dot" style="--i:5"></span>
  </div>
</div>
<script>
(function () {
  var el = document.getElementById('page-loader');
  if (!el) return;
  window.setTimeout(function () {
    el.classList.add('is-done');
    window.setTimeout(function () {
      if (el && el.parentNode) el.parentNode.removeChild(el);
    }, 220);
  }, 400);
})();
</script>
    <?php
}

function layout_start(string $title, array $user, array $opts = []): void
{
    $brand = branding();
    $flash = flash();
    $kind = $opts['kind'] ?? ($_GET['kind'] ?? '');
    $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($here !== '' && function_exists('user_can_open') && !user_can_open($here, (string) $kind)) {
        flash('Your login cannot open that page.', 'err');
        redirect('dashboard.php');
    }
    $nav = array_merge(
        [
            ['dashboard.php', 'Desk', 'desk'],
        ],
        desk_kind_nav_items(),
        [
            ['desk_mail.php', 'Email', 'send'],
            ['debtors.php', 'Debtors', 'clients'],
            ['creditors.php', 'Creditors', 'bank'],
            ['clients.php', 'Clients', 'building'],
        ]
    );
    if (company_branches_enabled()) {
        $nav[] = ['branches.php', 'Branches', 'pin'];
    }
    if (company_planner_enabled() && is_desk_admin()) {
        $nav[] = ['planner.php', 'Planner', 'calendar'];
    }
    if (company_pnl_enabled() && is_desk_admin()) {
        $nav[] = ['pnl.php', 'P&L', 'reports'];
    }
    $nav = array_merge($nav, [
        ['activities.php', 'Activities', 'clock'],
        ['tutorials.php', 'Tutorials', 'book'],
        ['reports.php', 'Reports', 'reports'],
        ['settings.php', 'Settings', 'settings'],
    ]);
    if (function_exists('record_site_visit')) {
        record_site_visit();
    }
    $nav = array_values(array_filter($nav, static function (array $item) {
        $file = (string) strtok($item[0], '?');
        $kind = '';
        if (str_contains($item[0], 'kind=')) {
            $kind = (string) substr((string) strstr($item[0], 'kind='), 5);
        }
        return user_can_open($file, $kind);
    }));
    $notes = (company_planner_enabled() && is_desk_admin()) ? enrich_planner_notifications(planner_notifications(40)) : [];
    $noteCount = count($notes);
    if ($noteCount && function_exists('push_schedule_sync')) {
        push_schedule_sync();
    }
    ?>
<!DOCTYPE html>
<html lang="en"<?= function_exists('folio_html_root_attrs') ? folio_html_root_attrs() : '' ?>>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= h($title) ?> · <?= h(product_name()) ?></title>
  <?php if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'document_view.php'): ?>
  <meta name="format-detection" content="telephone=no,email=no,address=no,date=no">
  <?php endif; ?>
  <?php product_icons(); ?>
  <?php folio_css_links(); ?>
  <?php folio_font_links(); ?>
  <style>:root { <?= brand_css_vars() ?> }</style>
</head>
<body class="desk-body">
<?php render_page_loader(); ?>
<div class="app">
  <div class="nav-scrim" data-nav-scrim hidden></div>
  <aside class="nav" data-nav>
    <a class="brand" href="<?= h(url('dashboard.php')) ?>" title="<?= h($brand['name']) ?>">
      <img class="brand-logo" src="<?= h(logo_url($brand)) ?>" alt="<?= h($brand['name']) ?>">
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
          if (str_starts_with($here, 'planner')) {
              $active = $file === 'planner.php' || str_starts_with((string) $file, 'planner');
              if ($file === 'planner.php') {
                  $active = in_array($here, ['planner.php', 'planner_notes.php', 'planner_goals.php', 'planner_budget.php', 'planner_calendar.php'], true);
              } else {
                  $active = $file === $here;
              }
          }
          if (str_starts_with($here, 'pnl')) {
              $active = $file === 'pnl.php' || str_starts_with((string) $file, 'pnl');
          }
          // Refunds and returns live under P&L, not the main kind nav.
          if (in_array($kind, ['refund', 'return_note'], true) && in_array($here, ['documents.php', 'document_view.php', 'document_new.php', 'document_email.php', 'document_action.php'], true)) {
              $active = $file === 'pnl.php';
          }
          if ($here === 'branding.php') {
              $active = $file === 'settings.php';
          }
          if ($here === 'branches.php') {
              $active = $file === 'branches.php';
          }
          ?>
        <a class="<?= $active ? 'is-on' : '' ?>" href="<?= h(url($href)) ?>" title="<?= h($label) ?>"><?= icon($iconName, 18) ?><span><?= h($label) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="nav-user">
      <span class="nav-user-name"><?= icon('user', 16) ?><span><?= h($user['name']) ?></span></span>
      <span class="nav-user-mail"><?= h($user['email']) ?></span>
      <?php if (!empty($user['job_title'])): ?>
        <span class="nav-user-mail"><?= h((string) $user['job_title']) ?></span>
      <?php endif; ?>
      <?php if (company_branches_enabled()): ?>
        <span class="nav-user-mail"><?= h(company_branch_label(isset($user['branch_id']) ? (int) $user['branch_id'] : 0)) ?></span>
      <?php endif; ?>
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
      </div>
      <div class="top-actions">
        <a class="header-settings<?= in_array($here, ['settings.php', 'branding.php'], true) ? ' is-on' : '' ?>" href="<?= h(url('settings.php')) ?>" title="Settings" aria-label="Settings"><?= icon('settings', 20) ?></a>
        <?php if (company_planner_enabled() && is_desk_admin()): ?>
          <details class="top-bell">
            <summary class="header-settings<?= $noteCount ? ' has-badge' : '' ?>" title="Notifications" aria-label="Notifications">
              <?= icon('bell', 20) ?>
              <?php if ($noteCount): ?><span class="top-bell-count"><?= $noteCount > 9 ? '9+' : $noteCount ?></span><?php endif; ?>
            </summary>
            <div class="top-bell-panel">
              <strong>Coming up</strong>
              <?php if (!$notes): ?>
                <p class="muted">No deadlines or essentials right now.</p>
              <?php else: ?>
                <ul>
                  <?php foreach ($notes as $n): ?>
                    <li class="top-bell-item">
                      <div class="top-bell-main">
                        <a href="<?= h($n['href']) ?>">
                          <span class="top-bell-title"><?= h($n['title']) ?></span>
                          <span class="top-bell-meta"><?= h($n['meta']) ?></span>
                        </a>
                        <form class="top-bell-dismiss" method="post" action="<?= h(url('notify_action.php')) ?>">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="dismiss">
                          <input type="hidden" name="key" value="<?= h((string) ($n['key'] ?? '')) ?>">
                          <button type="submit" class="top-bell-x" title="Dismiss" aria-label="Dismiss"><?= icon('x', 14) ?></button>
                        </form>
                      </div>
                      <?php if (!empty($n['actions'])): ?>
                        <div class="top-bell-actions">
                          <?php foreach ($n['actions'] as $act): ?>
                            <?php if (!empty($act['href'])): ?>
                              <a class="<?= h($act['class'] ?? 'btn ghost sm') ?>" href="<?= h($act['href']) ?>"><?= h($act['label']) ?></a>
                            <?php else: ?>
                              <form method="post" action="<?= h(url('notify_action.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="<?= h((string) ($act['action'] ?? '')) ?>">
                                <input type="hidden" name="key" value="<?= h((string) ($n['key'] ?? '')) ?>">
                                <?php if (!empty($n['event_id'])): ?>
                                  <input type="hidden" name="event_id" value="<?= (int) $n['event_id'] ?>">
                                <?php endif; ?>
                                <button class="<?= h($act['class'] ?? 'btn sm') ?>" type="submit"><?= h($act['label']) ?></button>
                              </form>
                            <?php endif; ?>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
              <a class="top-bell-foot" href="<?= h(url('planner.php')) ?>">Open Planner</a>
            </div>
          </details>
        <?php endif; ?>
        <button class="btn ghost" type="button" data-quick><?= icon('plus', 16) ?><span class="quick-label">Quick add</span></button>
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
    if (function_exists('record_site_visit')) {
        record_site_visit();
    }
    $nav = [
        ['admin_landing.php', 'Landing', 'image'],
        ['admin_signups.php', 'Sign-ups', 'letter'],
        ['admin_questions.php', 'Questions', 'help'],
        ['admin_companies.php', 'Companies', 'building'],
        ['admin_reports.php', 'Reports', 'reports'],
        ['admin_mail.php', 'Email', 'send'],
        ['admin_admins.php', 'Admins', 'user'],
    ];
    $notes = platform_notifications(40);
    $noteCount = count($notes);
    if ($noteCount && function_exists('push_schedule_sync')) {
        push_schedule_sync();
    }
    ?>
<!DOCTYPE html>
<html lang="en"<?= function_exists('folio_html_root_attrs') ? folio_html_root_attrs() : '' ?>>
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
<?php render_page_loader(); ?>
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
              || (in_array($here, ['admin_company.php', 'admin_company_new.php'], true) && $file === 'admin_companies.php')
              || ($here === 'admin_question.php' && $file === 'admin_questions.php');
          $count = 0;
          if ($file === 'admin_signups.php') {
              $count = $signupNew;
          } elseif ($file === 'admin_questions.php') {
              $count = $questionNew;
          }
          ?>
        <a class="<?= $active ? 'is-on' : '' ?>" href="<?= h(url($href)) ?>" title="<?= h($label) ?>"<?= $count ? ' data-badge="' . (int) $count . '"' : '' ?>><?= icon($iconName, 18) ?><span><?= h($label) ?></span></a>
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
        <details class="top-bell">
          <summary class="header-settings<?= $noteCount ? ' has-badge' : '' ?>" title="Notifications" aria-label="Notifications">
            <?= icon('bell', 20) ?>
            <?php if ($noteCount): ?><span class="top-bell-count"><?= $noteCount > 9 ? '9+' : $noteCount ?></span><?php endif; ?>
          </summary>
          <div class="top-bell-panel">
            <strong>Platform</strong>
            <?php if (!$notes): ?>
              <p class="muted">No open sign-ups, questions or renewals right now.</p>
            <?php else: ?>
              <ul>
                <?php foreach ($notes as $n): ?>
                  <li class="top-bell-item">
                    <div class="top-bell-main">
                      <a href="<?= h($n['href']) ?>">
                        <span class="top-bell-title"><?= h($n['title']) ?></span>
                        <span class="top-bell-meta"><?= h($n['meta']) ?></span>
                      </a>
                      <form class="top-bell-dismiss" method="post" action="<?= h(url('notify_action.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="dismiss">
                        <input type="hidden" name="key" value="<?= h((string) ($n['key'] ?? '')) ?>">
                        <button type="submit" class="top-bell-x" title="Dismiss" aria-label="Dismiss"><?= icon('x', 14) ?></button>
                      </form>
                    </div>
                    <?php if (!empty($n['actions'])): ?>
                      <div class="top-bell-actions">
                        <?php foreach ($n['actions'] as $act): ?>
                          <?php if (!empty($act['href'])): ?>
                            <a class="<?= h($act['class'] ?? 'btn ghost sm') ?>" href="<?= h($act['href']) ?>"><?= h($act['label']) ?></a>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <a class="top-bell-foot" href="<?= h(url('admin_signups.php')) ?>">Open sign-ups</a>
          </div>
        </details>
        <a class="btn" href="<?= h(url('admin_company_new.php')) ?>"><?= icon('plus', 16) ?>New company</a>
      </div>
    </header>
    <?php if ($flash): ?>
      <div class="flash flash-<?= h($flash['type']) ?>"><?= $flash['type'] === 'ok' ? icon('check', 16) : icon('alert', 16) ?><?= h($flash['text']) ?></div>
    <?php endif; ?>
    <div class="content">
<?php
}

function render_desk_calculator(): void
{
    ?>
<div class="desk-calc" data-desk-calc>
  <div class="desk-calc-pad" data-calc-pad hidden>
    <div class="desk-calc-head">
      <strong>Calculator</strong>
      <button type="button" class="desk-calc-close" data-calc-toggle aria-label="Close calculator"><?= icon('x', 18) ?></button>
    </div>
    <div class="desk-calc-tools">
      <button type="button" class="desk-calc-tool" data-calc="history"><?= icon('clock', 16) ?> History</button>
      <button type="button" class="desk-calc-tool" data-calc="copy"><?= icon('copy', 16) ?> Copy</button>
    </div>
    <ol class="desk-calc-history" data-calc-history hidden></ol>
    <div class="desk-calc-screen" data-calc-screen>0</div>
    <div class="desk-calc-keys">
      <button type="button" class="desk-calc-key op" data-calc="clear">C</button>
      <button type="button" class="desk-calc-key op" data-calc="back" aria-label="Backspace"><?= icon('backspace', 16) ?></button>
      <button type="button" class="desk-calc-key op" data-calc="op" data-op="/">÷</button>
      <button type="button" class="desk-calc-key op" data-calc="op" data-op="*">×</button>
      <button type="button" class="desk-calc-key num" data-calc="digit" data-digit="7">7</button>
      <button type="button" class="desk-calc-key num" data-calc="digit" data-digit="8">8</button>
      <button type="button" class="desk-calc-key num" data-calc="digit" data-digit="9">9</button>
      <button type="button" class="desk-calc-key op" data-calc="op" data-op="-">−</button>
      <button type="button" class="desk-calc-key num" data-calc="digit" data-digit="4">4</button>
      <button type="button" class="desk-calc-key num" data-calc="digit" data-digit="5">5</button>
      <button type="button" class="desk-calc-key num" data-calc="digit" data-digit="6">6</button>
      <button type="button" class="desk-calc-key op" data-calc="op" data-op="+">+</button>
      <button type="button" class="desk-calc-key num" data-calc="digit" data-digit="1">1</button>
      <button type="button" class="desk-calc-key num" data-calc="digit" data-digit="2">2</button>
      <button type="button" class="desk-calc-key num" data-calc="digit" data-digit="3">3</button>
      <button type="button" class="desk-calc-key eq" data-calc="eq">=</button>
      <button type="button" class="desk-calc-key num zero" data-calc="digit" data-digit="0">0</button>
      <button type="button" class="desk-calc-key num" data-calc="dot">.</button>
    </div>
  </div>
  <button type="button" class="desk-calc-fab" data-calc-toggle aria-label="Open calculator"><?= icon('calculator', 22) ?></button>
</div>
    <?php
}

function render_app_tabbar(): void
{
    $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $kind = (string) ($_GET['kind'] ?? '');
    $reportsHref = function_exists('user_can_open') && user_can_open('reports.php') ? 'reports.php' : 'debtors.php';
    $homeOn = $here === 'dashboard.php';
    $clientsOn = in_array($here, ['clients.php', 'client_view.php', 'client_edit.php'], true);
    $repOn = in_array($here, ['reports.php', 'pnl.php', 'pnl_entries.php', 'debtors.php', 'creditors.php'], true)
        || in_array($kind, ['refund', 'return_note'], true);
    ?>
<nav class="app-tabbar" aria-label="App">
  <a class="app-tab<?= $homeOn ? ' is-on' : '' ?>" href="<?= h(url('dashboard.php')) ?>">
    <?= icon('home', 22) ?><span>Home</span>
  </a>
  <a class="app-tab<?= $clientsOn ? ' is-on' : '' ?>" href="<?= h(url('clients.php')) ?>">
    <?= icon('building', 22) ?><span>Clients</span>
  </a>
  <button type="button" class="app-tab app-tab-create" data-quick aria-label="Create">
    <span class="app-tab-plus"><?= icon('plus', 26) ?></span>
    <span>Create</span>
  </button>
  <a class="app-tab<?= $repOn ? ' is-on' : '' ?>" href="<?= h(url($reportsHref)) ?>">
    <?= icon('reports', 22) ?><span>Reports</span>
  </a>
  <button type="button" class="app-tab" data-calc-toggle aria-label="Calculator">
    <?= icon('calculator', 22) ?><span>Calculator</span>
  </button>
</nav>
    <?php
}

function layout_end(string $extra = ''): void
{
    $admin = str_starts_with(basename($_SERVER['SCRIPT_NAME'] ?? ''), 'admin_');
    ?>
    </div>
  </div>
</div>

<?php if (!$admin):
    $createItems = desk_kind_nav_items();
    $createLead = [];
    $createMore = [];
    foreach ($createItems as $item) {
        $qKind = (string) ($item[3] ?? '');
        if (in_array($qKind, ['invoice', 'quotation'], true)) {
            $createLead[] = $item;
        } else {
            $createMore[] = $item;
        }
    }
    ?>
<div class="quick-scrim" hidden data-quick-scrim></div>
<div class="quick" hidden data-quick-panel>
  <div class="quick-head">
    <div>
      <strong>Create</strong>
      <span>Pick a document or a client</span>
    </div>
    <button type="button" class="quick-close" data-quick-close aria-label="Close"><?= icon('x', 18) ?></button>
  </div>
  <?php if ($createLead): ?>
  <div class="quick-featured">
    <?php foreach ($createLead as [$href, $label, $iconName, $qKind]): ?>
      <a class="quick-card<?= $qKind === 'invoice' ? ' is-primary' : '' ?>" href="<?= h(url('document_new.php?kind=' . $qKind)) ?>">
        <?= icon($iconName, 20) ?>
        <span><?= h(kind_meta($qKind)['singular']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if ($createMore): ?>
  <p>More documents</p>
  <?php foreach ($createMore as [$href, $label, $iconName, $qKind]): ?>
    <a href="<?= h(url('document_new.php?kind=' . $qKind)) ?>"><?= icon($iconName) ?><?= h($qKind === 'expense' ? 'Expense' : kind_meta($qKind)['singular']) ?></a>
  <?php endforeach; ?>
  <?php endif; ?>
  <p>Workspace</p>
  <a href="<?= h(url('desk_mail.php')) ?>"><?= icon('send') ?>Email</a>
  <a href="<?= h(url('client_edit.php')) ?>"><?= icon('clients') ?>Client</a>
</div>
<?php render_desk_calculator(); ?>
<?php render_app_tabbar(); ?>
<?php endif; ?>
<script src="<?= h(asset('js/app.js')) ?>" defer></script>
<script src="<?= h(asset('js/pwa.js')) ?>" defer></script>
<script src="<?= h(asset('js/push.js')) ?>" defer></script>
<?php
$sheetJs = in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['document_view.php', 'document_new.php', 'document_action.php', 'share.php', 'document_download.php', 'document_pdf.php'], true);
if ($sheetJs): ?>
<script src="<?= h(asset('js/sheet-fit.js')) ?>"></script>
<?php endif; ?>
<?= $extra ?>
</body>
</html>
<?php
}