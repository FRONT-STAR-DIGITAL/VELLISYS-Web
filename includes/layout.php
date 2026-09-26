<?php
declare(strict_types=1);

function render_top_clock(): void
{
    $now = desk_now();
    $tz = company_timezone_id();
    if (($_SESSION['role'] ?? '') === 'platform') {
        $tz = 'Africa/Kampala';
    }
    ?>
    <div class="top-clock" data-clock data-timezone="<?= h($tz) ?>" title="<?= h(str_replace('_', ' ', $tz)) ?>">
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

function render_nav_boot_script(): void
{
    ?>
<script>
(function () {
  function navScrim() { return document.querySelector('[data-nav-scrim]'); }
  function setNav(open) {
    open = !!open;
    document.body.classList.toggle('nav-open', open);
    document.querySelectorAll('[data-nav-toggle]').forEach(function (btn) {
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    var scrim = navScrim();
    if (scrim) scrim.hidden = !open;
  }
  window.vellisysSetNav = setNav;
  document.addEventListener('click', function (e) {
    var el = e.target;
    if (el && el.nodeType === 3) el = el.parentElement;
    if (!el || !el.closest) return;
    if (el.closest('[data-nav-toggle]')) {
      e.preventDefault();
      e.stopPropagation();
      setNav(!document.body.classList.contains('nav-open'));
      return;
    }
    if (el.closest('[data-nav-scrim]')) {
      setNav(false);
      return;
    }
    if (el.closest('[data-nav] a') && window.matchMedia('(max-width: 820px)').matches) {
      setNav(false);
    }
  }, true);
})();
</script>
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
    if (function_exists('handle_desk_welcome_dismiss')) {
        handle_desk_welcome_dismiss();
    }
    $brand = branding();
    $flash = flash();
    $kind = $opts['kind'] ?? ($_GET['kind'] ?? '');
    $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($here !== '' && function_exists('user_can_open') && !user_can_open($here, (string) $kind)) {
        flash('Your login cannot open that page.', 'err');
        redirect('dashboard.php');
    }
    $nav = [
        ['dashboard.php', 'Desk', 'desk'],
    ];
    if (function_exists('company_stock_enabled') && company_stock_enabled()) {
        $nav[] = ['sale.php', 'Sale', 'cart'];
        $nav[] = ['stock.php', 'Stock', 'package'];
    }
    $nav = array_merge(
        $nav,
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
        ['feedback.php', 'Feedback', 'help'],
        ['help.php', 'Need Help?', 'phone'],
        ['reports.php', 'Reports', 'reports'],
        ['settings.php', 'Settings', 'settings'],
    ]);
    if (function_exists('record_site_visit')) {
        record_site_visit();
    }
    if (function_exists('touch_user_seen') && !empty($user['id'])) {
        touch_user_seen((int) $user['id']);
    }
    $nav = array_values(array_filter($nav, static function (array $item) {
        $file = (string) strtok($item[0], '?');
        $kind = '';
        if (str_contains($item[0], 'kind=')) {
            $kind = (string) substr((string) strstr($item[0], 'kind='), 5);
        }
        return user_can_open($file, $kind);
    }));
    $notes = function_exists('desk_notifications_enabled') && desk_notifications_enabled()
        ? desk_notifications(40)
        : [];
    $noteCount = count($notes);
    $showBell = function_exists('desk_notifications_enabled') && desk_notifications_enabled();
    $plannerOn = function_exists('company_planner_enabled') && company_planner_enabled() && is_desk_admin();
    $stockOn = function_exists('company_stock_enabled') && company_stock_enabled();
    if ($noteCount && function_exists('push_schedule_sync')) {
        push_schedule_sync();
    }
    if (function_exists('company_backup_maybe')) {
        company_backup_maybe();
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
  <?php render_nav_boot_script(); ?>
</head>
<body class="desk-body<?= $here === 'settings.php' ? ' settings-page' : '' ?><?= (function_exists('sales_demo_active') && sales_demo_active()) ? ' sales-demo-desk' : '' ?>">
<?php render_page_loader(); ?>
<div class="app">
  <div class="nav-scrim" data-nav-scrim hidden></div>
  <button type="button" class="bell-scrim" data-bell-scrim hidden aria-label="Close notifications"></button>
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
          if (in_array($here, ['stock.php', 'sale.php'], true)) {
              $active = $file === $here;
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
      <?php if (function_exists('sales_demo_active') && sales_demo_active()): ?>
        <a href="<?= h(url('sales_demo.php?leave=1')) ?>" title="Leave demo"><?= icon('logout', 15) ?><span>Leave demo</span></a>
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
    <?php if (function_exists('sales_demo_active') && sales_demo_active()): ?>
      <div class="acting-bar">
        <span>Sales demo desk · show this to clients</span>
        <a href="<?= h(url('sales_demo.php?leave=1')) ?>">Leave demo</a>
      </div>
    <?php endif; ?>
    <header class="top">
      <button class="nav-toggle" type="button" data-nav-toggle aria-label="Menu" aria-expanded="false"><?= icon('menu', 20) ?></button>
      <div class="top-meta">
        <?php render_top_clock(); ?>
      </div>
      <div class="top-actions">
        <?php render_top_search(); ?>
        <a class="header-settings<?= in_array($here, ['settings.php', 'branding.php'], true) ? ' is-on' : '' ?>" href="<?= h(url('settings.php')) ?>" title="Settings" aria-label="Settings"><?= icon('settings', 20) ?></a>
        <?php if ($showBell): ?>
          <details class="top-bell">
            <summary class="header-settings<?= $noteCount ? ' has-badge' : '' ?>" title="Notifications" aria-label="Notifications">
              <?= icon('bell', 20) ?>
              <?php if ($noteCount): ?><span class="top-bell-count"><?= $noteCount > 9 ? '9+' : $noteCount ?></span><?php endif; ?>
            </summary>
            <div class="top-bell-panel">
              <strong>Coming up</strong>
              <?php if (!$notes): ?>
                <p class="muted">No deadlines, essentials or low stock right now.</p>
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
              <?php if ($plannerOn && $stockOn): ?>
                <div class="top-bell-feet">
                  <a class="top-bell-foot" href="<?= h(url('planner.php')) ?>">Open Planner</a>
                  <a class="top-bell-foot" href="<?= h(url('stock.php?tab=items')) ?>">Open Stock</a>
                </div>
              <?php elseif ($plannerOn): ?>
                <a class="top-bell-foot" href="<?= h(url('planner.php')) ?>">Open Planner</a>
              <?php elseif ($stockOn): ?>
                <a class="top-bell-foot" href="<?= h(url('stock.php?tab=items')) ?>">Open Stock</a>
              <?php endif; ?>
            </div>
          </details>
        <?php endif; ?>
        <button class="btn ghost" type="button" data-quick><?= icon('plus', 16) ?><span class="quick-label">Quick add</span></button>
      </div>
    </header>
    <?php if ($flash): ?>
      <div class="flash flash-<?= h($flash['type']) ?>"><?= $flash['type'] === 'ok' ? icon('check', 16) : icon('alert', 16) ?><?= h($flash['text']) ?></div>
    <?php endif; ?>
    <?php
      // Open/close day only gates Sale - never block invoices, receipts or other documents.
    ?>
    <div class="content">
<?php
}

function layout_admin_start(string $title, array $user): void
{
    $GLOBALS['folio_layout_admin'] = true;
    if (function_exists('touch_user_seen')) {
        touch_user_seen((int) $user['id']);
    }
    $flash = flash();
    $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $signupNew = new_signup_count();
    $questionNew = new_question_count();
    $feedbackNew = function_exists('desk_feedback_new_count') ? desk_feedback_new_count() : 0;
    if (function_exists('record_site_visit')) {
        record_site_visit();
    }
    $nav = [
        ['admin_dashboard.php', 'Dashboard', 'reports'],
        ['admin_landing.php', 'Landing', 'image'],
        ['admin_signups.php', 'Sign-ups', 'letter'],
        ['admin_sales.php', 'Sales', 'cart'],
        ['admin_sales.php?tab=messages', 'Messages', 'mail'],
        ['sales_demo.php', 'Demo', 'building'],
        ['admin_passwords.php', 'Passwords', 'lock'],
        ['admin_questions.php', 'Questions', 'help'],
        ['admin_feedback.php', 'Feedback', 'letter'],
        ['admin_companies.php', 'Companies', 'building'],
        ['admin_locations.php', 'Locations', 'pin'],
        ['admin_finances.php', 'Finances', 'bank'],
        ['admin_system.php', 'System', 'clock'],
        ['admin_reports.php', 'Reports', 'file'],
        ['admin_mail.php', 'Email', 'send'],
        ['admin_settings.php', 'Settings', 'settings'],
        ['admin_admins.php', 'Admins', 'user'],
    ];
    $notes = platform_notifications(40);
    $noteCount = count($notes);
    $salesMsgUnread = function_exists('sales_admin_unread_count') ? sales_admin_unread_count((int) $user['id']) : 0;
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
  <?php render_nav_boot_script(); ?>
</head>
<body class="desk-body admin-body">
<?php render_page_loader(); ?>
<div class="app">
  <div class="nav-scrim" data-nav-scrim hidden></div>
  <button type="button" class="bell-scrim" data-bell-scrim hidden aria-label="Close notifications"></button>
  <aside class="nav" data-nav>
    <a class="brand" href="<?= h(url('admin_dashboard.php')) ?>">
      <img class="brand-logo" src="<?= h(product_mark_url()) ?>" alt="<?= h(product_name()) ?>">
      <strong>Platform admin</strong>
    </a>
    <nav>
      <?php foreach ($nav as [$href, $label, $iconName]):
          $file = strtok($href, '?');
          $active = $file === $here
              || (in_array($here, ['admin_company.php', 'admin_company_new.php'], true) && $file === 'admin_companies.php')
              || ($here === 'admin_question.php' && $file === 'admin_questions.php')
              || ($here === 'admin_feedback.php' && $file === 'admin_feedback.php')
              || (str_starts_with($here, 'admin_sales') && $file === 'admin_sales.php' && !str_contains($href, 'tab=messages') && (string) ($_GET['tab'] ?? '') !== 'messages')
              || ($file === 'admin_sales.php' && str_contains($href, 'tab=messages') && $here === 'admin_sales.php' && (string) ($_GET['tab'] ?? '') === 'messages')
              || ($here === 'sales_demo.php' && $file === 'sales_demo.php')
              || ($here === 'admin_passwords.php' && $file === 'admin_passwords.php');
          $count = 0;
          if ($file === 'admin_signups.php') {
              $count = $signupNew;
          } elseif ($file === 'admin_questions.php') {
              $count = $questionNew;
          } elseif ($file === 'admin_feedback.php') {
              $count = $feedbackNew;
          } elseif ($file === 'admin_sales.php' && str_contains($href, 'tab=messages')) {
              $count = $salesMsgUnread;
              $active = $here === 'admin_sales.php' && (string) ($_GET['tab'] ?? '') === 'messages';
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
        <?php render_top_search(); ?>
        <a class="header-settings<?= $here === 'admin_settings.php' ? ' is-on' : '' ?>" href="<?= h(url('admin_settings.php')) ?>" title="Settings" aria-label="Settings"><?= icon('settings', 20) ?></a>
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
    $repOn = in_array($here, ['reports.php', 'pnl.php', 'pnl_entries.php', 'pnl_savings.php', 'pnl_banking.php', 'debtors.php', 'creditors.php'], true)
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

function render_admin_tabbar(): void
{
    $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $dashOn = $here === 'admin_dashboard.php';
    $companiesOn = in_array($here, ['admin_companies.php', 'admin_company.php', 'admin_company_new.php'], true);
    $salesOn = str_starts_with($here, 'admin_sales');
    $reportsOn = $here === 'admin_reports.php';
    $systemOn = $here === 'admin_system.php';
    ?>
<nav class="app-tabbar admin-tabbar" aria-label="Admin">
  <a class="app-tab<?= $dashOn ? ' is-on' : '' ?>" href="<?= h(url('admin_dashboard.php')) ?>">
    <?= icon('home', 22) ?><span>Dashboard</span>
  </a>
  <a class="app-tab<?= $companiesOn ? ' is-on' : '' ?>" href="<?= h(url('admin_companies.php')) ?>">
    <?= icon('building', 22) ?><span>Companies</span>
  </a>
  <a class="app-tab<?= $salesOn ? ' is-on' : '' ?>" href="<?= h(url('admin_sales.php')) ?>">
    <?= icon('cart', 22) ?><span>Sales</span>
  </a>
  <a class="app-tab<?= $reportsOn ? ' is-on' : '' ?>" href="<?= h(url('admin_reports.php')) ?>">
    <?= icon('reports', 22) ?><span>Reports</span>
  </a>
  <a class="app-tab<?= $systemOn ? ' is-on' : '' ?>" href="<?= h(url('admin_system.php')) ?>">
    <?= icon('clock', 22) ?><span>System</span>
  </a>
</nav>
    <?php
}

function render_top_search(): void
{
    $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $q = function_exists('search_query') ? search_query() : trim((string) ($_GET['q'] ?? ''));
    $placeholder = function_exists('search_placeholder') ? search_placeholder() : 'Search…';
    $on = $here === 'search.php';
    ?>
    <div class="top-search<?= $on && $q !== '' ? ' is-open' : '' ?>" data-top-search>
      <button class="header-settings top-search-toggle" type="button" data-search-toggle aria-label="Search" title="Search"><?= icon('search', 20) ?></button>
      <form class="top-search-form" action="<?= h(url('search.php')) ?>" method="get" role="search" data-search-form>
        <span class="top-search-icon" aria-hidden="true"><?= icon('search', 16) ?></span>
        <input class="top-search-input" type="search" name="q" value="<?= h($q) ?>" placeholder="<?= h($placeholder) ?>" autocomplete="off" data-search-input aria-label="Search">
        <button class="top-search-close" type="button" data-search-close aria-label="Close search"><?= icon('x', 16) ?></button>
        <div class="top-search-live" data-search-live hidden></div>
      </form>
    </div>
    <?php
}

function layout_end(string $extra = ''): void
{
    $admin = !empty($GLOBALS['folio_layout_admin']) || str_starts_with(basename($_SERVER['SCRIPT_NAME'] ?? ''), 'admin_');
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
  <?php if (function_exists('company_stock_enabled') && company_stock_enabled() && user_can_open('sale.php')): ?>
  <p>Stock</p>
  <a href="<?= h(url('sale.php')) ?>"><?= icon('cart') ?>Sale</a>
  <a href="<?= h(url('stock.php')) ?>"><?= icon('package') ?>Stock</a>
  <?php endif; ?>
  <p>Workspace</p>
  <a href="<?= h(url('desk_mail.php')) ?>"><?= icon('send') ?>Email</a>
  <a href="<?= h(url('client_edit.php')) ?>"><?= icon('clients') ?>Client</a>
</div>
<?php render_desk_calculator(); ?>
<?php render_app_tabbar(); ?>
<?php else: ?>
<?php render_admin_tabbar(); ?>
<?php endif; ?>
<script src="<?= h(asset('js/app.js')) ?>" defer></script>
<script src="<?= h(asset('js/pwa.js')) ?>" defer></script>
<script src="<?= h(asset('js/push.js')) ?>" defer></script>
<?php if (function_exists('current_user') && current_user()): ?>
<script>
(function () {
  var ping = <?= json_encode(url('ping.php')) ?>;
  function beat(ms) {
    var q = ping + (ms ? ('?ms=' + encodeURIComponent(ms)) : '');
    try { fetch(q, { credentials: 'same-origin', cache: 'no-store' }); } catch (e) {}
  }
  var t0 = (window.performance && performance.now) ? performance.now() : 0;
  function first() {
    var ms = t0 && performance.now ? Math.round(performance.now() - t0) : 0;
    beat(ms);
  }
  if (document.readyState === 'complete') first();
  else window.addEventListener('load', first);
  setInterval(function () { beat(0); }, 45000);
})();
</script>
<?php endif; ?>
<?php
$sheetJs = in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['document_view.php', 'document_new.php', 'document_action.php', 'share.php', 'document_download.php', 'document_pdf.php', 'documents.php'], true);
if ($sheetJs): ?>
<script src="<?= h(asset('js/sheet-fit.js')) ?>"></script>
<script src="<?= h(asset('js/pdf-download.js')) ?>" defer data-pdf-bundle="<?= h(asset('js/pdf/vellisys-pdf.js')) ?>"></script>
<?php endif; ?>
<?php if (!$admin && function_exists('render_desk_welcome_pop')) {
    render_desk_welcome_pop(current_user() ?: []);
} ?>
<?= $extra ?>
</body>
</html>
<?php
}