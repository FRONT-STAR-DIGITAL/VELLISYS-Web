<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$tab = (string) ($_GET['tab'] ?? 'overview');
$allowed = ['overview', 'leads', 'agents', 'targets', 'messages', 'agent', 'lead'];
if (!in_array($tab, $allowed, true)) {
    $tab = 'overview';
}
$agentId = (int) ($_GET['agent'] ?? 0);
$leadId = (int) ($_GET['id'] ?? 0);
$periodKind = (string) ($_GET['period'] ?? 'daily');
if (!in_array($periodKind, ['daily', 'weekly', 'monthly'], true)) {
    $periodKind = 'daily';
}
if ($tab === 'agent' && $agentId < 1) {
    $tab = 'agents';
}
$error = '';
$tempPassword = '';

if (in_array($tab, ['overview', 'leads'], true)) {
    admin_period_default_today();
}
[$from, $to, $period] = sales_period_bounds();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'save_agent') {
        $id = (int) post('agent_id') ?: null;
        $saved = sales_agent_save([
            'name' => post('name'),
            'email' => post('email'),
            'phone' => post('phone'),
            'job_title' => post('job_title'),
            'employee_id' => post('employee_id'),
            'password' => post('password'),
        ], $id);
        if (empty($saved['ok'])) {
            $error = (string) ($saved['error'] ?? 'Could not save agent.');
            $tab = 'agents';
        } else {
            $msg = $id ? 'Sales agent updated.' : 'Sales agent created.';
            if (!empty($saved['password'])) {
                $tempPassword = (string) $saved['password'];
                $msg .= ' Temporary password: ' . $tempPassword;
            }
            flash($msg);
            redirect('admin_sales.php?tab=agents');
        }
    } elseif ($action === 'suspend_agent' || $action === 'restore_agent') {
        $id = (int) post('agent_id');
        $st = $action === 'suspend_agent' ? 'suspended' : 'live';
        $done = sales_agent_set_status($id, $st);
        flash(empty($done['ok']) ? ($done['error'] ?? 'Failed') : ($st === 'suspended' ? 'Agent suspended.' : 'Agent restored.'), empty($done['ok']) ? 'err' : 'ok');
        redirect('admin_sales.php?tab=agents');
    } elseif ($action === 'delete_agent') {
        $done = sales_agent_delete((int) post('agent_id'), (string) post('confirm_email'));
        flash((string) ($done['message'] ?? (empty($done['ok']) ? ($done['error'] ?? 'Failed') : 'Agent deleted.')), empty($done['ok']) ? 'err' : 'ok');
        redirect('admin_sales.php?tab=agents');
    } elseif ($action === 'reset_agent_password') {
        $id = (int) post('agent_id');
        $row = sales_agent($id);
        if (!$row) {
            flash('Agent not found.', 'err');
        } else {
            $pw = function_exists('generate_desk_password') ? generate_desk_password() : ('Vs-' . bin2hex(random_bytes(4)));
            db_exec('UPDATE users SET password_hash=? WHERE id=?', 'si', [password_hash($pw, PASSWORD_DEFAULT), $id]);
            flash('Password reset for ' . $row['name'] . ': ' . $pw);
        }
        redirect('admin_sales.php?tab=agents');
    } elseif ($action === 'save_goals') {
        $saved = sales_goal_defaults_save([
            'daily_reach' => (int) post('daily_reach'),
            'daily_sales' => (int) post('daily_sales'),
            'weekly_reach' => (int) post('weekly_reach'),
            'weekly_sales' => (int) post('weekly_sales'),
            'monthly_reach' => (int) post('monthly_reach'),
            'monthly_sales' => (int) post('monthly_sales'),
        ]);
        flash(empty($saved['ok']) ? ($saved['error'] ?? 'Failed') : 'Daily, weekly and monthly goals saved.', empty($saved['ok']) ? 'err' : 'ok');
        redirect('admin_sales.php?tab=targets');
    } elseif ($action === 'save_target') {
        $saved = sales_target_save([
            'id' => (int) post('target_id'),
            'agent_id' => (int) post('agent_id'),
            'period_start' => post('period_start'),
            'period_end' => post('period_end'),
            'reach_target' => (int) post('reach_target'),
            'sales_target' => (int) post('sales_target'),
            'notes' => post('notes'),
        ]);
        flash(empty($saved['ok']) ? ($saved['error'] ?? 'Failed') : 'Custom period target saved.', empty($saved['ok']) ? 'err' : 'ok');
        redirect('admin_sales.php?tab=targets');
    } elseif ($action === 'delete_target') {
        db_exec('DELETE FROM sales_targets WHERE id = ?', 'i', [(int) post('target_id')]);
        flash('Target removed.');
        redirect('admin_sales.php?tab=targets');
    } elseif ($action === 'save_lead') {
        $id = (int) post('lead_id') ?: null;
        $saved = sales_lead_admin_save([
            'agent_id' => (int) post('agent_id'),
            'status' => post('status'),
            'business_name' => post('business_name'),
            'address' => post('address'),
            'contact_name' => post('contact_name'),
            'contact_phone' => post('contact_phone'),
            'city' => post('city'),
            'nature_of_business' => post('nature_of_business'),
            'package_chosen' => post('package_chosen'),
            'onboard_date' => post('onboard_date'),
            'follow_up_date' => post('follow_up_date'),
            'follow_up_time' => post('follow_up_time'),
            'interest_rating' => (int) post('interest_rating'),
            'rejected_category' => post('rejected_category'),
            'rejected_reason' => post('rejected_reason'),
            'notes' => post('notes'),
        ], $id);
        if (empty($saved['ok'])) {
            $error = (string) ($saved['error'] ?? 'Could not save business.');
            $tab = 'lead';
            $leadId = (int) ($id ?? 0);
        } else {
            $newId = (int) $saved['id'];
            $wantsTest = post('wants_testing') !== '' && (string) post('status') === 'interested';
            if ($wantsTest) {
                $leadRow = sales_lead($newId);
                $agentForTest = (int) ($leadRow['agent_id'] ?? post('agent_id'));
                $made = sales_start_testing_from_lead($newId, $agentForTest, post('desk_email'));
                if (!empty($made['ok'])) {
                    flash(
                        ($id ? 'Business updated.' : 'Business created.')
                        . ' Pro test desk ready: ' . (string) ($made['email'] ?? '')
                        . ' · password ' . (string) ($made['password'] ?? sales_testing_default_password())
                    );
                    redirect('admin_sales.php?tab=lead&id=' . $newId . '#lead-testing');
                }
                if (empty($made['company_id'])) {
                    flash(
                        'Business saved, but test desk was not opened: ' . (string) ($made['error'] ?? 'unknown error'),
                        'err'
                    );
                    redirect('admin_sales.php?tab=lead&id=' . $newId . '#lead-testing');
                }
            }
            flash($id ? 'Business updated.' : 'Business created.');
            redirect('admin_sales.php?tab=lead&id=' . $newId);
        }
    } elseif ($action === 'start_testing') {
        $lid = (int) post('lead_id');
        $lead = sales_lead($lid);
        if (!$lead || (string) ($lead['status'] ?? '') !== 'interested') {
            flash('Mark the business as Interested before opening a test desk.', 'err');
            redirect($lid ? ('admin_sales.php?tab=lead&id=' . $lid) : 'admin_sales.php?tab=leads');
        }
        $made = sales_start_testing_from_lead($lid, (int) ($lead['agent_id'] ?? 0), post('desk_email'));
        if (empty($made['ok'])) {
            flash((string) ($made['error'] ?? 'Could not open the test desk.'), empty($made['company_id']) ? 'err' : 'ok');
            redirect('admin_sales.php?tab=lead&id=' . $lid . '#lead-testing');
        }
        flash(
            $made['name'] . ' test desk is ready: ' . (string) ($made['email'] ?? '')
            . ' · password ' . (string) ($made['password'] ?? sales_testing_default_password())
        );
        redirect('admin_sales.php?tab=lead&id=' . $lid . '#lead-testing');
    } elseif ($action === 'onboard_lead') {
        $lid = (int) post('lead_id');
        $lead = sales_lead($lid);
        if (!$lead || (string) $lead['status'] !== 'interested') {
            flash('Only interested leads can be onboarded.', 'err');
            redirect('admin_sales.php?tab=leads');
        }
        $started = sales_begin_company_onboard($lid);
        if (empty($started['ok'])) {
            flash((string) ($started['error'] ?? 'Could not start onboarding.'), 'err');
            redirect('admin_sales.php?tab=leads');
        }
        $note = 'Company opened as onboarding';
        if (!empty($started['email'])) {
            $note .= '. Temporary desk login ' . $started['email'] . ' · password ' . ($started['password'] ?? '');
        }
        flash($note . '. Finish setup, then mark the company live.');
        redirect('admin_company.php?id=' . (int) $started['company_id']);
    } elseif ($action === 'delete_lead') {
        $done = sales_lead_soft_delete((int) post('lead_id'));
        flash(empty($done['ok']) ? ($done['error'] ?? 'Failed') : 'Not-interested business removed from the list. Reports still count it.', empty($done['ok']) ? 'err' : 'ok');
        redirect('admin_sales.php?tab=leads&range=' . urlencode((string) ($period['preset'] ?? 'today')));
    } elseif ($action === 'hard_delete_lead') {
        $done = sales_lead_hard_delete((int) post('lead_id'));
        flash(empty($done['ok']) ? ($done['error'] ?? 'Failed') : 'Not-interested business deleted.', empty($done['ok']) ? 'err' : 'ok');
        redirect('admin_sales.php?tab=leads&range=' . urlencode((string) ($period['preset'] ?? 'today')));
    } elseif ($action === 'send_message') {
        $to = (int) post('to_user_id');
        $sent = sales_message_send((int) $user['id'], $to, post('body'));
        flash(empty($sent['ok']) ? ($sent['error'] ?? 'Failed') : 'Message sent.', empty($sent['ok']) ? 'err' : 'ok');
        redirect('admin_sales.php?tab=messages&with=' . $to);
    }
}

$agents = sales_agents(false);
$filterAgent = (int) ($_GET['agent_filter'] ?? 0);
$overall = sales_stats($filterAgent ?: null, $from, $to);
$series = sales_series($filterAgent ?: null, $from, $to);
$hoursSeries = sales_hours_series($filterAgent ?: null, $from, $to);
$hoursByAgent = sales_hours_series_by_agents($filterAgent ?: null, $from, $to);
$hoursTotal = sales_hours_total($filterAgent ?: null, $from, $to);
$clientTimeSeries = sales_client_time_series($filterAgent ?: null, $from, $to);
$clientTimeByAgent = sales_client_time_series_by_agents($filterAgent ?: null, $from, $to);
$clientTimeAvg = sales_client_time_avg($filterAgent ?: null, $from, $to);
$top = sales_top_agents($from, $to);
$dailyBoard = sales_agents_daily_progress();
$goalDefaults = sales_goal_defaults();
$rejectionReport = sales_rejection_breakdown($filterAgent ?: null, $from, $to);
$leadStatus = (string) ($_GET['status'] ?? '');
$leadOpts = ['from' => $from, 'to' => $to];
if ($filterAgent) {
    $leadOpts['agent_id'] = $filterAgent;
}
if ($leadStatus !== '' && isset(sales_statuses()[$leadStatus])) {
    $leadOpts['status'] = $leadStatus;
}
$followBucket = (string) ($_GET['bucket'] ?? '');
$overdueFollowN = sales_overdue_followups_count($filterAgent ?: null);
if ($tab === 'leads') {
    // Open / overdue / on-test views ignore the date range so nothing due slips out of sight.
    if (in_array($followBucket, ['overdue', 'pending', 'closed', 'followed'], true) || $leadStatus === 'on_test') {
        unset($leadOpts['from'], $leadOpts['to']);
    }
    if ($followBucket === 'overdue') {
        $leadOpts['follow_bucket'] = 'overdue';
        unset($leadOpts['status']);
    } elseif ($followBucket === 'pending') {
        $leadOpts['follow_bucket'] = 'due';
        unset($leadOpts['status']);
    } elseif ($followBucket === 'closed' || $followBucket === 'followed') {
        $leadOpts['follow_bucket'] = 'done';
    }
    if ($leadStatus === 'on_test') {
        $leadOpts['on_test'] = true;
        unset($leadOpts['status']);
    }
    $leads = sales_leads_query($leadOpts);
}
$targets = db_all('SELECT t.*, u.name AS agent_name FROM sales_targets t LEFT JOIN users u ON u.id = t.agent_id ORDER BY t.period_start DESC, t.id DESC LIMIT 50');
$with = (int) ($_GET['with'] ?? 0);
if ($tab === 'messages' && $with) {
    sales_messages_mark_read((int) $user['id'], $with);
    $thread = array_reverse(sales_messages_for((int) $user['id'], $with));
}
$agentRow = $agentId ? sales_agent($agentId) : null;
if ($tab === 'agent' && $agentRow) {
    $agentProgress = sales_progress($agentId, $periodKind);
    $agentFrom = $agentProgress['from'];
    $agentTo = $agentProgress['stat_to'];
    $agentStats = $agentProgress['stats'];
    $agentSeries = sales_series($agentId, $agentFrom, $agentTo);
    $agentHoursSeries = sales_hours_series($agentId, $agentFrom, $agentTo);
    $agentHoursTotal = sales_hours_total($agentId, $agentFrom, $agentTo);
    $agentClientTimeSeries = sales_client_time_series($agentId, $agentFrom, $agentTo);
    $agentClientTimeAvg = sales_client_time_avg($agentId, $agentFrom, $agentTo);
    $agentRejection = sales_rejection_breakdown($agentId, $agentFrom, $agentTo);
    $agentLeads = sales_leads_query(['agent_id' => $agentId, 'from' => $agentFrom, 'to' => $agentTo]);
    $agentPending = sales_leads_query(['agent_id' => $agentId, 'follow_bucket' => 'due']);
    $agentOverdue = sales_leads_query(['agent_id' => $agentId, 'follow_bucket' => 'overdue']);
    $agentFollowed = sales_leads_query(['agent_id' => $agentId, 'follow_bucket' => 'done', 'from' => $agentFrom, 'to' => $agentTo]);
}
$editLead = null;
if ($tab === 'lead') {
    if ($leadId > 0) {
        $editLead = sales_lead($leadId);
        if (!$editLead) {
            flash('Business not found.', 'err');
            redirect('admin_sales.php?tab=leads');
        }
    }
}

layout_admin_start('Sales', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('cart') ?>Sales field</h1>
    <p class="lede">Set daily, weekly and monthly goals. Track each agent’s progress, manage businesses, and onboard interested clients.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn" href="<?= h(url('admin_sales.php?tab=lead')) ?>"><?= icon('plus', 16) ?>New business</a>
    <a class="btn ghost" href="<?= h(url('admin_sales.php?tab=agents&new=1')) ?>"><?= icon('user', 16) ?>New agent</a>
    <a class="btn ghost" href="<?= h(url('admin_sales.php?tab=targets')) ?>"><?= icon('flag', 16) ?>Goals</a>
  </div>
</div>

<nav class="planner-tabs" aria-label="Sales sections">
  <a class="planner-tab<?= $tab === 'overview' ? ' is-on' : '' ?>" href="<?= h(url('admin_sales.php?tab=overview')) ?>"><?= icon('reports', 16) ?><span>Sales</span></a>
  <a class="planner-tab<?= $tab === 'leads' || $tab === 'lead' ? ' is-on' : '' ?>" href="<?= h(url('admin_sales.php?tab=leads')) ?>"><?= icon('clients', 16) ?><span>Businesses<?php if ($overdueFollowN > 0): ?> <span class="sales-follow-count" title="Overdue follow-ups"><?= (int) $overdueFollowN ?></span><?php endif; ?></span></a>
  <a class="planner-tab<?= $tab === 'agents' || $tab === 'agent' ? ' is-on' : '' ?>" href="<?= h(url('admin_sales.php?tab=agents')) ?>"><?= icon('user', 16) ?><span>Agents</span></a>
  <a class="planner-tab<?= $tab === 'targets' ? ' is-on' : '' ?>" href="<?= h(url('admin_sales.php?tab=targets')) ?>"><?= icon('flag', 16) ?><span>Targets</span></a>
  <a class="planner-tab<?= $tab === 'messages' ? ' is-on' : '' ?>" href="<?= h(url('admin_sales.php?tab=messages')) ?>"><?= icon('mail', 16) ?><span>Messages</span></a>
</nav>

<?php if ($error): ?><p class="flash flash-err"><?= h($error) ?></p><?php endif; ?>

<?php if ($tab === 'overview'): ?>
<div class="stats">
  <div class="card stat"><?= icon('clients', 20) ?><span>Reach</span><strong><?= (int) $overall['reach'] ?></strong></div>
  <div class="card stat"><?= icon('heart', 20) ?><span>Interested</span><strong><?= (int) $overall['interested'] ?></strong></div>
  <div class="card stat"><?= icon('check', 20) ?><span>Onboarded</span><strong><?= (int) $overall['onboarded'] ?></strong></div>
  <div class="card stat"><?= icon('flag', 20) ?><span>Sales (wins)</span><strong><?= (int) $overall['wins'] ?></strong></div>
  <div class="card stat"><?= icon('building', 20) ?><span>On test</span><strong><?= (int) ($overall['on_test'] ?? 0) ?></strong><em class="muted"><?= (int) ($overall['testing_active'] ?? 0) ?> active now</em></div>
  <a class="card stat" href="<?= h(url('admin_sales.php?tab=leads&bucket=overdue&range=all' . ($filterAgent ? '&agent_filter=' . $filterAgent : ''))) ?>" style="text-decoration:none;color:inherit">
    <?= icon('calendar', 20) ?>
    <span>Overdue follow-ups</span>
    <strong><?= (int) $overdueFollowN ?></strong>
    <em class="muted">Not attended past due date</em>
  </a>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <h2><?= icon('flag', 16) ?>Daily goals · all agents</h2>
    <a class="btn ghost sm" href="<?= h(url('admin_sales.php?tab=targets')) ?>">Edit goals</a>
  </div>
  <div class="pad-form">
    <p class="hint" style="margin:0 0 12px">Defaults: <?= (int) $goalDefaults['daily_reach'] ?> leads reached · <?= (int) $goalDefaults['daily_sales'] ?> interested clients (sales wins). Bars stay visible past the target and call out how far each agent exceeded it.</p>
    <?php if (!$dailyBoard): ?>
      <p class="empty">No live sales agents yet.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table class="grid sales-agents-goal-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Clock in</th>
              <th>Location</th>
              <th>Targets</th>
              <th>Clock out</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($dailyBoard as $row):
                $a = $row['agent'];
                $p = $row['progress'];
                $clock = $row['clock'] ?? null;
                $hasClock = !empty($clock);
                $clockedOutAt = $hasClock ? trim((string) ($clock['clocked_out_at'] ?? '')) : '';
                $isIn = $hasClock && $clockedOutAt === '';
                $clockInAt = $hasClock ? trim((string) ($clock['clocked_at'] ?? '')) : '';
                $location = $hasClock ? trim((string) ($clock['location_city'] ?? '')) : '';
                $agentHref = url('admin_sales.php?tab=agent&agent=' . (int) $a['id'] . '&period=daily');
                ?>
              <tr>
                <td>
                  <a class="sales-agent-name-link" href="<?= h($agentHref) ?>"><?= h($a['name']) ?></a>
                  <div class="muted"><?= h($a['email']) ?></div>
                  <div class="muted mono">
                    <?php if ($isIn): ?>
                      Clocked in · <?= h(sales_format_hours(sales_clock_minutes($clock) / 60)) ?>
                    <?php elseif ($hasClock): ?>
                      Clocked out · <?= h(sales_format_hours(sales_clock_minutes($clock) / 60)) ?>
                    <?php else: ?>
                      Not clocked in
                    <?php endif; ?>
                  </div>
                </td>
                <td class="mono"><?= $hasClock ? h(sales_format_clock_time($clockInAt)) : '-' ?></td>
                <td><?= $location !== '' ? h($location) : '-' ?></td>
                <td class="sales-agents-goal-targets">
                  <?php sales_render_goal_bars($p, ['compact' => true, 'force_reach' => true, 'force_sales' => true]); ?>
                  <div class="muted" style="margin-top:6px">On test today: <strong class="mono"><?= (int) ($row['on_test'] ?? 0) ?></strong>
                    · active <strong class="mono"><?= (int) ($row['testing_active'] ?? 0) ?></strong></div>
                </td>
                <td class="mono"><?= $clockedOutAt !== '' ? h(sales_format_clock_time($clockedOutAt)) : ($isIn ? 'In field' : '-') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php render_filters('admin_sales.php', array_filter(['tab' => 'overview', 'agent_filter' => $filterAgent ?: null]), ['live' => true, 'today_first' => true]); ?>
<p class="hint" style="margin:-8px 0 16px">Team totals for <?= $period['from'] ? h(format_date($from) . ' - ' . format_date($to)) : 'all dates' ?>.</p>
<form method="get" class="filters" style="margin-bottom:16px">
  <input type="hidden" name="tab" value="overview">
  <input type="hidden" name="range" value="<?= h((string) ($period['preset'] ?? 'today')) ?>">
  <input type="hidden" name="from" value="<?= h($from) ?>">
  <input type="hidden" name="to" value="<?= h($to) ?>">
  <label>Filter by agent
    <select name="agent_filter" onchange="this.form.submit()">
      <option value="">All agents</option>
      <?php foreach ($agents as $a): ?>
        <option value="<?= (int) $a['id'] ?>" <?= $filterAgent === (int) $a['id'] ? 'selected' : '' ?>><?= h($a['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
</form>
<div class="chart-grid equal" style="margin-bottom:16px">
  <div class="card chart-box"><div class="card-head"><h2>Status mix</h2></div><div class="pad-form" style="height:220px"><canvas id="admin-pie"></canvas></div></div>
  <div class="card chart-box"><div class="card-head"><h2>Reach over time</h2></div><div class="pad-form" style="height:220px"><canvas id="admin-line"></canvas></div></div>
</div>
<div class="chart-grid equal sales-hours-charts" style="margin-bottom:16px">
  <div class="card chart-box">
    <div class="card-head">
      <h2><?= icon('clock', 16) ?>Field hours over time</h2>
      <span class="muted"><?= h(sales_format_hours($hoursTotal)) ?> total</span>
    </div>
    <p class="hint" style="margin:0 16px 0">Clock-in / clock-out hours per sales agent - each line is one agent.</p>
    <div class="pad-form" style="height:<?= count($hoursByAgent['agents'] ?? []) > 4 ? '320' : '280' ?>px"><canvas id="admin-hours"></canvas></div>
  </div>
  <div class="card chart-box">
    <div class="card-head">
      <h2><?= icon('clients', 16) ?>Avg time per client</h2>
      <span class="muted"><?= $clientTimeAvg > 0 ? h(rtrim(rtrim(number_format($clientTimeAvg, 1), '0'), '.') . ' min') : '-' ?></span>
    </div>
    <p class="hint" style="margin:0 16px 0">Average minutes from clock-in to first lead, then between leads - each line is one agent.</p>
    <div class="pad-form" style="height:<?= count($clientTimeByAgent['agents'] ?? []) > 4 ? '320' : '280' ?>px"><canvas id="admin-client-time"></canvas></div>
  </div>
</div>
<?php sales_render_rejection_report($rejectionReport, ['title' => 'Rejections by reason']); ?>
<div class="card">
  <div class="card-head"><h2>Top performers</h2></div>
  <div class="table-scroll">
    <table class="grid">
      <thead><tr><th>Agent</th><th class="right">Reach</th><th class="right">Sales</th><th class="right">On test</th><th class="right">Follow up</th><th class="right">Onboarded</th><th class="right">Rejected</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($top as $row): ?>
          <tr>
            <td><?= h($row['name']) ?><div class="muted"><?= h($row['email']) ?></div></td>
            <td class="right mono"><?= (int) $row['reach'] ?></td>
            <td class="right mono"><?= (int) $row['sales'] ?></td>
            <td class="right mono"><?= (int) ($row['on_test'] ?? 0) ?></td>
            <td class="right mono"><?= (int) ($row['follow_up'] ?? 0) ?></td>
            <td class="right mono"><?= (int) ($row['onboarded'] ?? 0) ?></td>
            <td class="right mono"><?= (int) $row['rejected'] ?></td>
            <td class="row-actions"><a class="btn ghost sm" href="<?= h(url('admin_sales.php?tab=agent&agent=' . (int) $row['id'] . '&period=daily')) ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$hourAgentDatasets = [];
foreach (($hoursByAgent['agents'] ?? []) as $agentSeries) {
    $hourAgentDatasets[] = [
        'label' => (string) ($agentSeries['name'] ?? 'Agent'),
        'data' => array_map('floatval', $agentSeries['hours'] ?? []),
        'borderColor' => (string) ($agentSeries['color'] ?? brand_color()),
        'backgroundColor' => 'transparent',
        'tension' => 0.3,
        'fill' => false,
        'pointRadius' => 3,
        'pointHoverRadius' => 5,
        'borderWidth' => 2,
    ];
}
if (!$hourAgentDatasets) {
    $hourAgentDatasets[] = [
        'label' => 'Hours in field',
        'data' => array_map(static fn ($r) => (float) $r['hours'], $hoursSeries),
        'borderColor' => brand_color(),
        'backgroundColor' => brand_color() . '33',
        'tension' => 0.35,
        'fill' => true,
        'pointRadius' => 3,
        'borderWidth' => 2,
    ];
}
$clientTimeAgentDatasets = [];
foreach (($clientTimeByAgent['agents'] ?? []) as $agentSeries) {
    $clientTimeAgentDatasets[] = [
        'label' => (string) ($agentSeries['name'] ?? 'Agent'),
        'data' => array_map('floatval', $agentSeries['minutes'] ?? []),
        'borderColor' => (string) ($agentSeries['color'] ?? brand_color()),
        'backgroundColor' => 'transparent',
        'tension' => 0.3,
        'fill' => false,
        'pointRadius' => 3,
        'pointHoverRadius' => 5,
        'borderWidth' => 2,
    ];
}
if (!$clientTimeAgentDatasets) {
    $clientTimeAgentDatasets[] = [
        'label' => 'Avg minutes / client',
        'data' => array_map(static fn ($r) => (float) $r['minutes'], $clientTimeSeries),
        'borderColor' => '#0f766e',
        'backgroundColor' => 'rgba(15,118,110,.18)',
        'tension' => 0.35,
        'fill' => true,
        'pointRadius' => 3,
        'borderWidth' => 2,
    ];
}
$payload = json_encode([
    'pieLabels' => ['Interested', 'Follow up', 'Rejected', 'Onboarded', 'On test'],
    'pieValues' => [(int) $overall['interested'], (int) $overall['follow_up'], (int) $overall['rejected'], (int) $overall['onboarded'], (int) ($overall['on_test'] ?? 0)],
    'labels' => array_map(static fn ($r) => date('j M', strtotime((string) $r['date'])), $series),
    'reach' => array_column($series, 'reach'),
    'wins' => array_map(static fn ($r) => (int) $r['interested'] + (int) $r['onboarded'], $series),
    'onTest' => array_map(static fn ($r) => (int) ($r['on_test'] ?? 0), $series),
    'hourLabels' => $hoursByAgent['labels'] ?? array_map(static fn ($r) => date('j M', strtotime((string) $r['date'])), $hoursSeries),
    'hourDatasets' => $hourAgentDatasets,
    'clientTimeLabels' => $clientTimeByAgent['labels'] ?? array_map(static fn ($r) => date('j M', strtotime((string) $r['date'])), $clientTimeSeries),
    'clientTimeDatasets' => $clientTimeAgentDatasets,
    'color' => brand_color(),
], JSON_UNESCAPED_UNICODE);
layout_end('<script src="' . h(asset('js/chart.umd.min.js')) . '" defer></script><script defer>
(function(){function go(){if(!window.Chart||typeof window.vellisysChartTooltip!=="function"){setTimeout(go,40);return;}var d=' . $payload . ';var tip=window.vellisysChartTooltip();var piePlug=window.vellisysPiePercentPlugins();var p=document.getElementById("admin-pie");if(p)new Chart(p,{type:"doughnut",data:{labels:d.pieLabels,datasets:[{data:d.pieValues,backgroundColor:[d.color,"#c4a35a","#b42318","#0f766e","#7c3aed"],borderWidth:0}]},options:{cutout:"58%",plugins:{legend:{position:"bottom"},tooltip:tip},maintainAspectRatio:false},plugins:piePlug});var l=document.getElementById("admin-line");if(l)new Chart(l,{type:"line",data:{labels:d.labels,datasets:[{label:"Reach",data:d.reach,borderColor:d.color,tension:.3,fill:false},{label:"Sales",data:d.wins,borderColor:"#0f766e",tension:.3,fill:false},{label:"On test",data:d.onTest||[],borderColor:"#7c3aed",tension:.3,fill:false}]},options:{plugins:{legend:{position:"bottom"},tooltip:tip},scales:{y:{beginAtZero:true,ticks:{precision:0}}},maintainAspectRatio:false}});var h=document.getElementById("admin-hours");if(h)new Chart(h,{type:"line",data:{labels:d.hourLabels,datasets:d.hourDatasets||[]},options:{interaction:{mode:"nearest",axis:"x",intersect:false},plugins:{legend:{display:true,position:"bottom",labels:{boxWidth:12,usePointStyle:true,pointStyle:"circle"}},tooltip:tip},scales:{y:{beginAtZero:true,title:{display:true,text:"Hours"}}},maintainAspectRatio:false}});var ct=document.getElementById("admin-client-time");if(ct)new Chart(ct,{type:"line",data:{labels:d.clientTimeLabels,datasets:d.clientTimeDatasets||[]},options:{interaction:{mode:"nearest",axis:"x",intersect:false},plugins:{legend:{display:true,position:"bottom",labels:{boxWidth:12,usePointStyle:true,pointStyle:"circle"}},tooltip:tip},scales:{y:{beginAtZero:true,title:{display:true,text:"Minutes"}}},maintainAspectRatio:false}});}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);else go();})();
</script>');
return;
endif;

if ($tab === 'leads'):
    $isOverdueView = $followBucket === 'overdue';
    $isOpenFuView = $followBucket === 'pending' || $isOverdueView;
    $statusChoices = [
        'interested' => 'Interested',
        'follow_up' => 'Follow ups',
        'on_test' => 'On testing',
        'onboarding' => 'Onboarding',
        'onboarded' => 'Onboarded',
        'rejected' => 'Rejected',
    ];
    $chipBase = $filterAgent ? ['agent_id' => $filterAgent] : [];
    // Match the list: All / Interested / Follow ups use the active period.
    $chipPeriod = ['from' => $from, 'to' => $to];
    $chipCounts = [
        'all' => sales_leads_count($chipBase + $chipPeriod),
        'interested' => sales_leads_count($chipBase + $chipPeriod + ['status' => 'interested']),
        'follow_up' => sales_leads_count($chipBase + $chipPeriod + ['status' => 'follow_up']),
        // On testing / overdue / closed ignore the date range (same as their chip links).
        'on_test' => sales_leads_count($chipBase + ['on_test' => true]),
        'overdue' => $overdueFollowN,
        'closed' => sales_leads_count($chipBase + ['follow_bucket' => 'done']),
    ];
    $filterQs = static function (array $extra) use ($filterAgent, $period): string {
        $q = array_merge([
            'tab' => 'leads',
            'range' => (string) ($period['preset'] ?? 'all'),
            'from' => (string) ($period['from'] ?? ''),
            'to' => (string) ($period['to'] ?? ''),
        ], $extra);
        if ($filterAgent > 0) {
            $q['agent_filter'] = $filterAgent;
        }
        if (($q['bucket'] ?? '') !== '' || ($q['status'] ?? '') === 'on_test') {
            $q['range'] = 'all';
            unset($q['from'], $q['to']);
        }
        return url('admin_sales.php?' . http_build_query(array_filter($q, static fn ($v) => $v !== '' && $v !== null)));
    };
    $chipCountHtml = static function (int $n): string {
        if ($n < 1) {
            return '';
        }
        return ' <span class="sales-follow-count">' . $n . '</span>';
    };
    ?>
<?php render_filters('admin_sales.php', array_filter([
    'tab' => 'leads',
    'agent_filter' => $filterAgent ?: null,
    'status' => $leadStatus ?: null,
    'bucket' => $followBucket ?: null,
]), ['live' => true, 'today_first' => true]); ?>
<p class="hint" style="margin:-8px 0 16px">
  <?php if ($isOpenFuView || $followBucket === 'closed' || $leadStatus === 'on_test'): ?>
    Showing all dates for this filter (not limited to the period above).
  <?php else: ?>
    Showing <?= $period['from'] ? h(format_date($from) . ' - ' . format_date($to)) : 'all dates' ?>.
  <?php endif; ?>
</p>
<form method="get" class="filters" style="margin-bottom:12px">
  <input type="hidden" name="tab" value="leads">
  <input type="hidden" name="range" value="<?= h((string) ($period['preset'] ?? '')) ?>">
  <input type="hidden" name="from" value="<?= h($period['from'] ?? '') ?>">
  <input type="hidden" name="to" value="<?= h($period['to'] ?? '') ?>">
  <label>Agent
    <select name="agent_filter" onchange="this.form.submit()">
      <option value="">All</option>
      <?php foreach ($agents as $a): ?>
        <option value="<?= (int) $a['id'] ?>" <?= $filterAgent === (int) $a['id'] ? 'selected' : '' ?>><?= h($a['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Status
    <select name="status" onchange="var b=this.form.elements.namedItem('bucket'); if(b) b.value=''; this.form.submit()">
      <option value="">All</option>
      <?php foreach ($statusChoices as $k => $label): ?>
        <option value="<?= h($k) ?>" <?= $leadStatus === $k && $followBucket === '' ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Follow-ups
    <select name="bucket" onchange="var s=this.form.elements.namedItem('status'); if(s) s.value=''; this.form.submit()">
      <option value="">Any</option>
      <option value="overdue" <?= $followBucket === 'overdue' ? 'selected' : '' ?>>Overdue</option>
      <option value="pending" <?= $followBucket === 'pending' ? 'selected' : '' ?>>Open (not attended)</option>
      <option value="closed" <?= in_array($followBucket, ['closed', 'followed'], true) ? 'selected' : '' ?>>Closed</option>
    </select>
  </label>
</form>
<div class="filter-chips" style="margin:0 0 14px">
  <a class="chip<?= $leadStatus === '' && $followBucket === '' ? ' is-on' : '' ?>" href="<?= h($filterQs([])) ?>">All<?= $chipCountHtml((int) $chipCounts['all']) ?></a>
  <a class="chip<?= $leadStatus === 'interested' && $followBucket === '' ? ' is-on' : '' ?>" href="<?= h($filterQs(['status' => 'interested'])) ?>">Interested<?= $chipCountHtml((int) $chipCounts['interested']) ?></a>
  <a class="chip<?= $leadStatus === 'follow_up' && $followBucket === '' ? ' is-on' : '' ?>" href="<?= h($filterQs(['status' => 'follow_up'])) ?>">Follow ups<?= $chipCountHtml((int) $chipCounts['follow_up']) ?></a>
  <a class="chip<?= $leadStatus === 'on_test' ? ' is-on' : '' ?>" href="<?= h($filterQs(['status' => 'on_test'])) ?>">On testing<?= $chipCountHtml((int) $chipCounts['on_test']) ?></a>
  <a class="chip<?= $followBucket === 'overdue' ? ' is-on' : '' ?>" href="<?= h($filterQs(['bucket' => 'overdue'])) ?>">Overdue<?= $chipCountHtml((int) $chipCounts['overdue']) ?></a>
  <a class="chip<?= in_array($followBucket, ['closed', 'followed'], true) ? ' is-on' : '' ?>" href="<?= h($filterQs(['bucket' => 'closed'])) ?>">Closed<?= $chipCountHtml((int) $chipCounts['closed']) ?></a>
</div>
<div class="card">
  <div class="card-head">
    <h2><?= $isOverdueView ? 'Overdue follow-ups' : ($followBucket === 'pending' ? 'Open follow-ups' : ($followBucket === 'closed' || $followBucket === 'followed' ? 'Closed follow-ups' : 'Businesses')) ?></h2>
    <a class="btn sm" href="<?= h(url('admin_sales.php?tab=lead')) ?>"><?= icon('plus', 14) ?>Add</a>
  </div>
  <div class="table-scroll">
    <table class="grid">
      <thead><tr><th>Business</th><th>Agent</th><th>Status</th><?php if ($isOpenFuView): ?><th>Interest</th><?php endif; ?><th>Contact</th><th>City</th><th>Follow-up</th><th>Submitted</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($leads as $lead):
            $testLabel = sales_lead_testing_label($lead);
            $overdue = sales_lead_followup_overdue($lead);
            $interest = (int) ($lead['interest_rating'] ?? 0);
            $rowClass = $overdue ? 'sales-fu-overdue' : '';
            ?>
          <tr<?= $rowClass !== '' ? ' class="' . h($rowClass) . '"' : '' ?>>
            <td><?= h(trim((string) $lead['business_name']) ?: '-') ?>
              <?php if ($testLabel !== ''): ?>
                <span class="pill<?= $testLabel === 'Test ended' ? ' bad' : ' warn' ?>"><?= h($testLabel) ?></span>
              <?php endif; ?>
              <?php if ($overdue): ?>
                <span class="pill bad">Overdue</span>
              <?php endif; ?>
              <?php if (($lead['status'] ?? '') === 'rejected' && !empty($lead['rejected_reason'])): ?>
                <div class="muted"><?= h((string) $lead['rejected_reason']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= h((string) $lead['agent_name']) ?></td>
            <td><span class="<?= h(sales_status_pill_class((string) $lead['status'])) ?>"><?= h(sales_status_label((string) $lead['status'])) ?></span>
              <?php if (!empty($lead['follow_up_done_at'])): ?>
                <div class="muted">Closed <?= h(format_date(substr((string) $lead['follow_up_done_at'], 0, 10))) ?></div>
              <?php elseif (($lead['status'] ?? '') === 'follow_up'): ?>
                <div class="muted"><?= $overdue ? 'Past due - not attended' : 'Awaiting follow-up' ?></div>
              <?php endif; ?>
            </td>
            <?php if ($isOpenFuView): ?>
              <td><span class="<?= h(sales_interest_pill_class($interest)) ?>"><?= h(sales_interest_label($interest)) ?></span></td>
            <?php endif; ?>
            <td><?= h(trim($lead['contact_name'] . ' ' . $lead['contact_phone'])) ?></td>
            <td><?= h((string) $lead['city']) ?></td>
            <td class="date-cell"><?= !empty($lead['follow_up_date']) ? h(sales_format_follow_up($lead)) : '-' ?></td>
            <td class="date-cell mono"><?= h(sales_format_lead_submitted_at($lead['created_at'] ?? null)) ?></td>
            <td class="row-actions">
              <a class="btn<?= $overdue || $isOpenFuView ? '' : ' ghost' ?> sm" href="<?= h(url('admin_sales.php?tab=lead&id=' . (int) $lead['id'])) ?>"><?= $overdue ? 'Review' : 'Edit' ?></a>
              <?php if (($lead['status'] ?? '') === 'interested'): ?>
                <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="onboard_lead"><input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>"><button class="btn sm" type="submit">Onboard</button></form>
              <?php endif; ?>
              <?php if (($lead['status'] ?? '') === 'rejected'): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Remove this not-interested business from the list?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_lead"><input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>"><button class="btn ghost sm" type="submit">Remove</button></form>
                <form method="post" style="display:inline" onsubmit="return confirm('Permanently delete this not-interested business?');"><?= csrf_field() ?><input type="hidden" name="action" value="hard_delete_lead"><input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>"><button class="btn ghost sm" type="submit">Delete</button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!$leads): ?><p class="empty"><?= $isOverdueView ? 'No overdue follow-ups right now.' : 'No businesses in this filter.' ?></p><?php endif; ?>
</div>
<?php layout_end(); return; endif;

if ($tab === 'lead'):
    $packages = sales_packages();
    $status = (string) ($_POST['status'] ?? ($editLead['status'] ?? 'interested'));
    if (!isset(sales_statuses()[$status])) {
        $status = 'interested';
    }
    $agentPick = (int) ($_POST['agent_id'] ?? ($editLead['agent_id'] ?? 0));
    $savedLeadStatus = (string) ($editLead['status'] ?? '');
    $fromFollowOrRejected = in_array($savedLeadStatus, ['follow_up', 'rejected'], true);
    $adminTestCompany = null;
    $adminTestCreds = null;
    $adminTestExpired = false;
    if ($editLead && !empty($editLead['company_id'])) {
        $adminTestCompany = db_one('SELECT * FROM companies WHERE id = ?', 'i', [(int) $editLead['company_id']]);
        if ($adminTestCompany && !empty($adminTestCompany['testing_mode'])) {
            $adminTestCreds = sales_testing_credentials((int) $adminTestCompany['id']);
            $adminTestExpired = company_testing_expired($adminTestCompany);
        } elseif ($adminTestCompany && empty($adminTestCompany['testing_mode'])) {
            // Promoted company.
        } else {
            $adminTestCompany = null;
        }
    }
    $canOfferTesting = !$adminTestCompany || empty($adminTestCompany['testing_mode']);
    $wantsTestingChecked = post('wants_testing') !== '';
    ?>
<div class="page-head" style="margin-top:0">
  <div>
    <h2><?= $editLead ? 'Edit business' : 'New business' ?></h2>
    <p class="lede">Field businesses. Onboard interested clients from the list.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn ghost" href="<?= h(url('admin_sales.php?tab=leads')) ?>">Back</a>
  </div>
</div>
<?php if ($editLead && $savedLeadStatus === 'interested' && $adminTestCompany && !empty($adminTestCompany['testing_mode'])): ?>
  <div class="card pad-form" id="lead-testing" style="margin-bottom:16px">
    <h2 style="margin-top:0">Testing</h2>
    <p class="lede" style="margin-top:0">
      Ends <?= !empty($adminTestCompany['testing_expires_at']) ? h(format_date((string) $adminTestCompany['testing_expires_at'])) : '-' ?>
      · <?= h(company_testing_remaining_label($adminTestCompany)) ?>.
    </p>
    <?php if ($adminTestCreds): ?>
      <p><strong>Username:</strong> <code data-copy><?= h((string) ($adminTestCreds['email'] ?: '-')) ?></code></p>
      <p><strong>Password:</strong> <code data-copy><?= h((string) (($adminTestCreds['password'] !== '' ? $adminTestCreds['password'] : sales_testing_default_password()))) ?></code></p>
    <?php endif; ?>
    <?php if ($adminTestExpired): ?>
      <p class="flash flash-err" style="margin:12px 0 0">Test ended. Extend from Companies or promote to onboard.</p>
    <?php endif; ?>
    <div class="actions wrap-actions" style="margin-top:12px">
      <a class="btn ghost" href="<?= h(url('admin_company.php?id=' . (int) $adminTestCompany['id'])) ?>"><?= icon('building', 14) ?>Open company</a>
    </div>
  </div>
<?php elseif ($editLead && $savedLeadStatus === 'interested' && $canOfferTesting): ?>
  <div class="card pad-form" id="lead-testing" style="margin-bottom:16px">
    <h2 style="margin-top:0">Testing</h2>
    <p class="lede" style="margin-top:0">Open a Pro test desk (stock, Profit &amp; Loss, Planner). Password is Folio2026.</p>
    <form method="post" class="pad-form" style="margin-top:12px;padding:0">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="start_testing">
      <input type="hidden" name="lead_id" value="<?= (int) $editLead['id'] ?>">
      <label for="desk_email_open">Client login email</label>
      <input id="desk_email_open" name="desk_email" type="email" required value="<?= h(post('desk_email')) ?>" placeholder="client@theircompany.com" autocomplete="off">
      <div class="actions" style="margin-top:10px">
        <button class="btn" type="submit"><?= icon('plus', 14) ?>Open test desk</button>
      </div>
    </form>
  </div>
<?php endif; ?>
<form method="post" class="card pad-form form-grid" data-admin-lead<?= $fromFollowOrRejected && $canOfferTesting ? ' data-convert-testing="1"' : '' ?>>
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save_lead">
  <input type="hidden" name="lead_id" value="<?= (int) ($editLead['id'] ?? 0) ?>">
  <div>
    <label>Sales agent</label>
    <select name="agent_id" required>
      <option value="">Select agent</option>
      <?php foreach (sales_agents(false) as $a): ?>
        <option value="<?= (int) $a['id'] ?>" <?= $agentPick === (int) $a['id'] ? 'selected' : '' ?>><?= h($a['name']) ?> (<?= h($a['email']) ?>)</option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label>Status</label>
    <select name="status" data-admin-status>
      <?php foreach (sales_statuses() as $k => $label): ?>
        <option value="<?= h($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label>Business name</label>
    <input name="business_name" value="<?= h((string) ($_POST['business_name'] ?? $editLead['business_name'] ?? '')) ?>">
  </div>
  <div>
    <label>City / area</label>
    <input name="city" value="<?= h((string) ($_POST['city'] ?? $editLead['city'] ?? '')) ?>">
  </div>
  <div class="full">
    <label>Address</label>
    <input name="address" value="<?= h((string) ($_POST['address'] ?? $editLead['address'] ?? '')) ?>">
  </div>
  <div>
    <label>Contact person</label>
    <input name="contact_name" value="<?= h((string) ($_POST['contact_name'] ?? $editLead['contact_name'] ?? '')) ?>">
  </div>
  <div>
    <label>Phone</label>
    <input name="contact_phone" value="<?= h((string) ($_POST['contact_phone'] ?? $editLead['contact_phone'] ?? '')) ?>">
  </div>
  <div data-admin-panel="interested">
    <label>Nature of business</label>
    <input name="nature_of_business" value="<?= h((string) ($_POST['nature_of_business'] ?? $editLead['nature_of_business'] ?? '')) ?>">
  </div>
  <div data-admin-panel="interested">
    <label>Package</label>
    <select name="package_chosen">
      <option value="">-</option>
      <?php $pkg = (string) ($_POST['package_chosen'] ?? $editLead['package_chosen'] ?? ''); foreach ($packages as $k => $label): ?>
        <option value="<?= h($k) ?>" <?= $pkg === $k ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div data-admin-panel="interested">
    <label>Preferred onboarding date</label>
    <input type="date" name="onboard_date" value="<?= h((string) ($_POST['onboard_date'] ?? $editLead['onboard_date'] ?? '')) ?>">
  </div>
  <?php if ($canOfferTesting): ?>
    <div class="full" data-admin-panel="interested" data-admin-testing-block>
      <div class="lead-testing-on-save" style="padding:14px;border:1px solid var(--line);border-radius:10px;background:color-mix(in srgb, var(--brand) 4%, #fff)">
        <h3 style="margin:0 0 6px;font-size:1.05rem"><?= icon('desk', 16) ?>Pro test desk</h3>
        <p class="hint" style="margin:0 0 10px">
          <?= $fromFollowOrRejected
              ? 'This business was a follow-up or rejection. Open a Pro test desk (stock, Profit &amp; Loss, Planner) when you save as Interested.'
              : 'Open a Pro test desk (stock, Profit &amp; Loss, Planner) when you save. Password: Folio2026.' ?>
        </p>
        <label class="check lead-wants-testing">
          <input type="checkbox" name="wants_testing" value="1" data-admin-wants-testing <?= $wantsTestingChecked ? 'checked' : '' ?>>
          Open a test desk when I save
        </label>
        <div data-admin-wants-testing-fields style="margin-top:10px" <?= $wantsTestingChecked ? '' : 'hidden' ?>>
          <label for="desk_email">Client login email</label>
          <input id="desk_email" name="desk_email" type="email" value="<?= h(post('desk_email')) ?>" placeholder="client@theircompany.com" autocomplete="off" data-admin-wants-testing-email>
          <p class="hint" style="margin:4px 0 0">Required when opening a test desk. Password: Folio2026.</p>
        </div>
        <p class="hint" style="margin:8px 0 0">Needs business name, contact person and phone above.</p>
      </div>
    </div>
  <?php endif; ?>
  <div data-admin-panel="follow_up">
    <label>Follow-up date</label>
    <input type="date" name="follow_up_date" value="<?= h((string) ($_POST['follow_up_date'] ?? $editLead['follow_up_date'] ?? '')) ?>">
  </div>
  <div data-admin-panel="follow_up">
    <label>Time <span class="muted">(optional)</span></label>
    <input type="time" name="follow_up_time" value="<?= h(sales_follow_up_time_input((string) ($_POST['follow_up_time'] ?? $editLead['follow_up_time'] ?? ''))) ?>">
  </div>
  <div data-admin-panel="follow_up">
    <label>Interest in Vellisys (1-5)</label>
    <select name="interest_rating">
      <option value="0">Rate interest</option>
      <?php $ir = (int) ($_POST['interest_rating'] ?? ($editLead['interest_rating'] ?? 0)); for ($i = 1; $i <= 5; $i++): ?>
        <option value="<?= $i ?>" <?= $ir === $i ? 'selected' : '' ?>><?= $i ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div data-admin-panel="rejected">
    <label>Why they rejected Vellisys</label>
    <select name="rejected_category">
      <option value="">Choose a reason</option>
      <?php $rc = (string) ($_POST['rejected_category'] ?? ($editLead['rejected_category'] ?? '')); foreach (sales_reject_reasons() as $k => $label): ?>
        <option value="<?= h($k) ?>" <?= $rc === $k ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="full" data-admin-panel="rejected">
    <label>Explain the rejection</label>
    <textarea name="rejected_reason" rows="3"><?= h((string) ($_POST['rejected_reason'] ?? $editLead['rejected_reason'] ?? '')) ?></textarea>
  </div>
  <div class="full">
    <label>Notes</label>
    <textarea name="notes" rows="3"><?= h((string) ($_POST['notes'] ?? $editLead['notes'] ?? '')) ?></textarea>
  </div>
  <div class="actions" style="grid-column:1/-1">
    <button class="btn" type="submit"><?= icon('check') ?>Save business</button>
    <?php if ($editLead && ($editLead['status'] ?? '') === 'rejected'): ?>
      <button class="btn ghost" type="submit" name="action" value="hard_delete_lead" onclick="this.form.querySelector('[name=action]').value='hard_delete_lead'; return confirm('Permanently delete this not-interested business?');">Delete</button>
    <?php endif; ?>
  </div>
</form>
<?php if ($editLead && ($editLead['status'] ?? '') === 'interested'): ?>
  <form method="post" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="action" value="onboard_lead"><input type="hidden" name="lead_id" value="<?= (int) $editLead['id'] ?>"><button class="btn" type="submit"><?= icon('check', 16) ?>Onboard this client</button></form>
<?php endif; ?>
<script>
(function(){
  var form=document.querySelector('[data-admin-lead]');
  if(!form) return;
  var convertTesting=form.getAttribute('data-convert-testing')==='1';
  var wants=form.querySelector('[data-admin-wants-testing]');
  var wantsTouched=false;
  function sync(){
    var st=(form.querySelector('[data-admin-status]')||{}).value||'interested';
    form.querySelectorAll('[data-admin-panel]').forEach(function(p){
      var name=p.getAttribute('data-admin-panel');
      p.hidden = !(st===name || (name==='interested' && (st==='interested'||st==='onboarding'||st==='onboarded')));
    });
    var fields=form.querySelector('[data-admin-wants-testing-fields]');
    var email=form.querySelector('[data-admin-wants-testing-email]');
    if(wants){
      wants.disabled = st!=='interested';
      var show = st==='interested' && wants.checked;
      if(fields) fields.hidden = !show;
      if(email){
        email.required = show;
        email.disabled = !show;
      }
    }
  }
  var sel=form.querySelector('[data-admin-status]');
  if(sel) sel.addEventListener('change', function(){
    if(convertTesting && !wantsTouched && wants){
      wants.checked = this.value==='interested';
    }
    sync();
  });
  if(wants) wants.addEventListener('change', function(){ wantsTouched=true; sync(); });
  sync();
})();
</script>
<?php layout_end(); return; endif;

if ($tab === 'agents'):
    $editId = (int) ($_GET['edit'] ?? 0);
    $edit = $editId ? sales_agent($editId) : null;
    $showNew = isset($_GET['new']) || $edit || $error;
    $deleteId = (int) ($_GET['delete'] ?? 0);
    $deleteRow = $deleteId ? sales_agent($deleteId) : null;
    ?>
<?php if ($deleteRow): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2>Delete <?= h($deleteRow['name']) ?></h2></div>
  <form method="post" class="pad-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_agent">
    <input type="hidden" name="agent_id" value="<?= (int) $deleteRow['id'] ?>">
    <p class="lede">Type <strong><?= h($deleteRow['email']) ?></strong> to confirm. Their leads stay in reports; the login is removed.</p>
    <label>Confirm email</label>
    <input name="confirm_email" type="email" required placeholder="<?= h($deleteRow['email']) ?>" autocomplete="off">
    <div class="actions" style="margin-top:12px">
      <button class="btn" type="submit">Delete agent</button>
      <a class="btn ghost" href="<?= h(url('admin_sales.php?tab=agents')) ?>">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>
<?php if ($showNew): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= $edit ? 'Edit agent' : 'New sales agent' ?></h2></div>
  <form method="post" class="pad-form form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_agent">
    <input type="hidden" name="agent_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div><label>Name</label><input name="name" required value="<?= h((string) ($edit['name'] ?? post('name'))) ?>"></div>
    <div><label>Email</label><input name="email" type="email" required value="<?= h((string) ($edit['email'] ?? post('email'))) ?>"></div>
    <div><label>Phone</label><input name="phone" value="<?= h((string) ($edit['phone'] ?? post('phone'))) ?>"></div>
    <div><label>Employee ID</label><input name="employee_id" value="<?= h((string) ($edit['employee_id'] ?? post('employee_id') ?: ($edit ? sales_employee_id($edit) : ''))) ?>" placeholder="e.g. SA-0001"></div>
    <div><label>Title</label><input name="job_title" value="<?= h((string) ($edit['job_title'] ?? 'Sales agent')) ?>"></div>
    <div><label>Password<?= $edit ? ' (blank = keep)' : '' ?></label><input name="password" type="text" autocomplete="new-password" placeholder="<?= $edit ? 'Leave blank to keep' : 'Auto if blank' ?>"></div>
    <div class="actions" style="grid-column:1/-1"><button class="btn" type="submit"><?= icon('check') ?>Save</button></div>
  </form>
</div>
<?php endif; ?>
<div class="card">
  <div class="table-scroll">
    <table class="grid">
      <thead><tr><th>Name</th><th>Employee ID</th><th>Email</th><th>Status</th><th>Today</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($agents as $a):
            $day = sales_progress((int) $a['id'], 'daily');
            ?>
          <tr>
            <td><a href="<?= h(url('admin_sales.php?tab=agent&agent=' . (int) $a['id'] . '&period=daily')) ?>"><?= h($a['name']) ?></a></td>
            <td class="mono"><?= h(sales_employee_id($a)) ?></td>
            <td><?= h($a['email']) ?></td>
            <td><span class="pill"><?= h(($a['status'] ?? 'live') === 'suspended' ? 'Suspended' : 'Live') ?></span></td>
            <td class="mono">
              <?= (int) $day['reach'] ?>/<?= (int) $day['reach_goal'] ?> leads
              <?php if (sales_goal_over_by((int) $day['reach'], (int) $day['reach_goal']) > 0): ?>
                <span class="sales-goal-chip is-over">+<?= sales_goal_over_by((int) $day['reach'], (int) $day['reach_goal']) ?></span>
              <?php endif; ?>
              · <?= (int) $day['sales'] ?>/<?= (int) $day['sales_goal'] ?> interested
              <?php if (sales_goal_over_by((int) $day['sales'], (int) $day['sales_goal']) > 0): ?>
                <span class="sales-goal-chip is-over">+<?= sales_goal_over_by((int) $day['sales'], (int) $day['sales_goal']) ?></span>
              <?php endif; ?>
            </td>
            <td class="row-actions">
              <a class="btn ghost sm" href="<?= h(url('admin_sales.php?tab=agent&agent=' . (int) $a['id'] . '&period=daily')) ?>">Stats</a>
              <a class="btn ghost sm" href="<?= h(url('admin_sales.php?tab=agents&edit=' . (int) $a['id'])) ?>">Edit</a>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="agent_id" value="<?= (int) $a['id'] ?>">
                <input type="hidden" name="action" value="reset_agent_password"><button class="btn ghost sm" type="submit">Reset p/w</button>
              </form>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="agent_id" value="<?= (int) $a['id'] ?>">
                <input type="hidden" name="action" value="<?= ($a['status'] ?? '') === 'suspended' ? 'restore_agent' : 'suspend_agent' ?>">
                <button class="btn ghost sm" type="submit"><?= ($a['status'] ?? '') === 'suspended' ? 'Restore' : 'Suspend' ?></button>
              </form>
              <a class="btn ghost sm" href="<?= h(url('admin_sales.php?tab=agents&delete=' . (int) $a['id'])) ?>">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!$agents): ?><p class="empty">No sales agents yet. Create one with + New agent.</p><?php endif; ?>
</div>
<?php layout_end(); return; endif;

if ($tab === 'agent' && $agentRow):
    $periodLabels = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];
    ?>
<div class="page-head" style="margin-top:0">
  <div>
    <h2><?= h($agentRow['name']) ?></h2>
    <p class="lede"><?= h($agentRow['email']) ?> · <?= ($agentRow['status'] ?? '') === 'suspended' ? 'Suspended' : 'Live' ?> · <?= h($periodLabels[$periodKind]) ?> <?= h(format_date($agentFrom)) ?><?= $agentFrom !== $agentTo ? ' - ' . h(format_date($agentTo)) : '' ?></p>
  </div>
  <div class="actions page-actions">
    <a class="btn ghost" href="<?= h(url('admin_sales.php?tab=agents')) ?>">All agents</a>
  </div>
</div>
<nav class="sales-period-tabs" aria-label="Performance period">
  <?php foreach ($periodLabels as $k => $label): ?>
    <a class="chip<?= $periodKind === $k ? ' is-on' : '' ?>" href="<?= h(url('admin_sales.php?tab=agent&agent=' . $agentId . '&period=' . $k)) ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('flag', 16) ?><?= h($periodLabels[$periodKind]) ?> goals</h2></div>
  <div class="pad-form">
    <?php
    $agentReachOver = sales_goal_over_by((int) $agentProgress['reach'], (int) $agentProgress['reach_goal']);
    $agentSalesOver = sales_goal_over_by((int) $agentProgress['sales'], (int) $agentProgress['sales_goal']);
    if ($agentReachOver > 0 || $agentSalesOver > 0):
        ?>
      <div class="sales-goal-banner">
        <?= icon('flag', 16) ?>
        <span><?= h($agentRow['name']) ?> exceeded the <?= h(strtolower($periodLabels[$periodKind])) ?> target<?= ($agentReachOver > 0 && $agentSalesOver > 0) ? 's' : '' ?>.</span>
        <?php if ($agentReachOver > 0): ?>
          <span class="sales-goal-over">Reach +<?= $agentReachOver ?> (<?= (int) $agentProgress['reach'] ?>/<?= (int) $agentProgress['reach_goal'] ?>)</span>
        <?php endif; ?>
        <?php if ($agentSalesOver > 0): ?>
          <span class="sales-goal-over">Interested +<?= $agentSalesOver ?> (<?= (int) $agentProgress['sales'] ?>/<?= (int) $agentProgress['sales_goal'] ?>)</span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php sales_render_goal_bars($agentProgress, ['force_reach' => $periodKind === 'daily', 'force_sales' => true]); ?>
  </div>
</div>
<div class="stats">
  <div class="card stat">
    <span>Reach</span>
    <strong><?= (int) $agentStats['reach'] ?><?= (int) $agentProgress['reach_goal'] > 0 ? '/' . (int) $agentProgress['reach_goal'] : '' ?></strong>
    <?php sales_render_goal_chip((int) $agentProgress['reach'], (int) $agentProgress['reach_goal'], 'reach'); ?>
  </div>
  <div class="card stat">
    <span>Interested / sales</span>
    <strong><?= (int) $agentStats['wins'] ?><?= (int) $agentProgress['sales_goal'] > 0 ? '/' . (int) $agentProgress['sales_goal'] : '' ?></strong>
    <?php sales_render_goal_chip((int) $agentProgress['sales'], (int) $agentProgress['sales_goal'], 'interested'); ?>
  </div>
  <div class="card stat"><span>Interested only</span><strong><?= (int) $agentStats['interested'] ?></strong></div>
  <div class="card stat"><span>On test</span><strong><?= (int) ($agentStats['on_test'] ?? 0) ?></strong><em class="muted"><?= (int) ($agentStats['testing_active'] ?? 0) ?> active now</em></div>
  <div class="card stat"><span>Field hours</span><strong><?= h(sales_format_hours($agentHoursTotal)) ?></strong></div>
</div>
<div class="chart-grid equal" style="margin-bottom:16px">
  <div class="card chart-box"><div class="card-head"><h2>Status mix</h2></div><div class="pad-form" style="height:220px"><canvas id="agent-pie"></canvas></div></div>
  <div class="card chart-box"><div class="card-head"><h2><?= $periodKind === 'daily' ? 'Today by status' : 'Reach & sales over time' ?></h2></div><div class="pad-form" style="height:220px"><canvas id="agent-line"></canvas></div></div>
</div>
<div class="chart-grid equal sales-hours-charts" style="margin-bottom:16px">
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('clock', 16) ?>Hours in the field</h2></div>
    <div class="pad-form" style="height:240px"><canvas id="agent-hours"></canvas></div>
  </div>
  <div class="card chart-box">
    <div class="card-head">
      <h2><?= icon('clients', 16) ?>Avg time per client</h2>
      <span class="muted"><?= $agentClientTimeAvg > 0 ? h(rtrim(rtrim(number_format($agentClientTimeAvg, 1), '0'), '.') . ' min') : '-' ?></span>
    </div>
    <p class="hint" style="margin:0 16px 0">Minutes from clock-in to first lead, then between leads.</p>
    <div class="pad-form" style="height:240px"><canvas id="agent-client-time"></canvas></div>
  </div>
</div>
<?php sales_render_rejection_report($agentRejection); ?>
<div class="desk-grid stock-split" style="margin-bottom:16px">
  <div class="card">
    <div class="card-head">
      <h2>Overdue follow-ups</h2>
      <a class="btn ghost sm" href="<?= h(url('admin_sales.php?tab=leads&bucket=overdue&range=all&agent_filter=' . (int) $agentId)) ?>">All overdue</a>
    </div>
    <?php if (!$agentOverdue): ?><p class="empty">None overdue.</p>
    <?php else: ?>
      <div class="table-scroll"><table class="grid"><thead><tr><th>Business</th><th>Due</th></tr></thead><tbody>
        <?php foreach ($agentOverdue as $lead): ?>
          <tr class="sales-fu-overdue"><td><a href="<?= h(url('admin_sales.php?tab=lead&id=' . (int) $lead['id'])) ?>"><?= h(trim((string) $lead['business_name']) ?: '-') ?></a> <span class="pill bad">Overdue</span></td><td><?= h(sales_format_follow_up($lead)) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h2>Open (not attended)</h2></div>
    <?php if (!$agentPending): ?><p class="empty">None waiting.</p>
    <?php else: ?>
      <div class="table-scroll"><table class="grid"><thead><tr><th>Business</th><th>Due</th></tr></thead><tbody>
        <?php foreach ($agentPending as $lead):
            $rowOverdue = sales_lead_followup_overdue($lead);
            ?>
          <tr<?= $rowOverdue ? ' class="sales-fu-overdue"' : '' ?>><td><a href="<?= h(url('admin_sales.php?tab=lead&id=' . (int) $lead['id'])) ?>"><?= h(trim((string) $lead['business_name']) ?: '-') ?></a><?php if ($rowOverdue): ?> <span class="pill bad">Overdue</span><?php endif; ?></td><td><?= h(sales_format_follow_up($lead)) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h2>Closed</h2></div>
    <?php if (!$agentFollowed): ?><p class="empty">No closed follow-ups in this period.</p>
    <?php else: ?>
      <div class="table-scroll"><table class="grid"><thead><tr><th>Business</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($agentFollowed as $lead): ?>
          <tr><td><a href="<?= h(url('admin_sales.php?tab=lead&id=' . (int) $lead['id'])) ?>"><?= h(trim((string) $lead['business_name']) ?: '-') ?></a></td><td><?= h(sales_status_label((string) $lead['status'])) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </div>
</div>
<div class="card">
  <div class="card-head"><h2>Businesses in this period</h2></div>
  <p class="hint" style="margin:0 16px 8px">Submitted time is admin-only - used to judge visit length.</p>
  <div class="table-scroll"><table class="grid"><thead><tr><th>Business</th><th>Status</th><th>City</th><th>Submitted</th><th></th></tr></thead><tbody>
    <?php foreach ($agentLeads as $lead): ?>
      <tr>
        <td><?= h(trim((string) $lead['business_name']) ?: '-') ?></td>
        <td><?= h(sales_status_label((string) $lead['status'])) ?></td>
        <td><?= h((string) $lead['city']) ?></td>
        <td class="date-cell mono"><?= h(sales_format_lead_submitted_at($lead['created_at'] ?? null)) ?></td>
        <td class="row-actions"><a class="btn ghost sm" href="<?= h(url('admin_sales.php?tab=lead&id=' . (int) $lead['id'])) ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php if (!$agentLeads): ?><p class="empty">No businesses logged in this period.</p><?php endif; ?>
</div>
<?php
$payload = json_encode([
    'pieLabels' => ['Interested', 'Follow up', 'Rejected', 'Onboarded', 'On test'],
    'pieValues' => [(int) $agentStats['interested'], (int) $agentStats['follow_up'], (int) $agentStats['rejected'], (int) $agentStats['onboarded'], (int) ($agentStats['on_test'] ?? 0)],
    'labels' => array_map(static fn ($r) => date('j M', strtotime((string) $r['date'])), $agentSeries),
    'reach' => array_column($agentSeries, 'reach'),
    'wins' => array_map(static fn ($r) => (int) $r['interested'] + (int) $r['onboarded'], $agentSeries),
    'onTest' => array_map(static fn ($r) => (int) ($r['on_test'] ?? 0), $agentSeries),
    'barLabels' => ['Interested', 'Follow up', 'Rejected', 'Onboarded', 'On test'],
    'barValues' => [(int) $agentStats['interested'], (int) $agentStats['follow_up'], (int) $agentStats['rejected'], (int) $agentStats['onboarded'], (int) ($agentStats['on_test'] ?? 0)],
    'hourLabels' => array_map(static fn ($r) => date('j M', strtotime((string) $r['date'])), $agentHoursSeries),
    'hours' => array_map(static fn ($r) => (float) $r['hours'], $agentHoursSeries),
    'clientTimeLabels' => array_map(static fn ($r) => date('j M', strtotime((string) $r['date'])), $agentClientTimeSeries),
    'clientTime' => array_map(static fn ($r) => (float) $r['minutes'], $agentClientTimeSeries),
    'daily' => $periodKind === 'daily',
    'color' => brand_color(),
], JSON_UNESCAPED_UNICODE);
layout_end('<script src="' . h(asset('js/chart.umd.min.js')) . '" defer></script><script defer>(function(){function go(){if(!window.Chart||typeof window.vellisysChartTooltip!=="function"){setTimeout(go,40);return;}var d=' . $payload . ';var tip=window.vellisysChartTooltip();var piePlug=window.vellisysPiePercentPlugins();var p=document.getElementById("agent-pie");if(p)new Chart(p,{type:"doughnut",data:{labels:d.pieLabels,datasets:[{data:d.pieValues,backgroundColor:[d.color,"#c4a35a","#b42318","#0f766e","#7c3aed"],borderWidth:0}]},options:{cutout:"58%",plugins:{legend:{position:"bottom"},tooltip:tip},maintainAspectRatio:false},plugins:piePlug});var l=document.getElementById("agent-line");if(l){if(d.daily){new Chart(l,{type:"bar",data:{labels:d.barLabels,datasets:[{data:d.barValues,backgroundColor:[d.color,"#c4a35a","#b42318","#0f766e","#7c3aed"],borderRadius:6}]},options:{plugins:{legend:{display:false},tooltip:tip},scales:{y:{beginAtZero:true,ticks:{precision:0}}},maintainAspectRatio:false}});}else{new Chart(l,{type:"line",data:{labels:d.labels,datasets:[{label:"Reach",data:d.reach,borderColor:d.color,tension:.3,fill:false},{label:"Sales",data:d.wins,borderColor:"#0f766e",tension:.3,fill:false},{label:"On test",data:d.onTest||[],borderColor:"#7c3aed",tension:.3,fill:false}]},options:{plugins:{legend:{position:"bottom"},tooltip:tip},scales:{y:{beginAtZero:true,ticks:{precision:0}}},maintainAspectRatio:false}});}}var h=document.getElementById("agent-hours");if(h)new Chart(h,{type:"line",data:{labels:d.hourLabels,datasets:[{label:"Hours in field",data:d.hours,borderColor:d.color,backgroundColor:d.color+"33",tension:.35,fill:true,pointRadius:3}]},options:{plugins:{legend:{display:false},tooltip:tip},scales:{y:{beginAtZero:true,title:{display:true,text:"Hours"}}},maintainAspectRatio:false}});var ct=document.getElementById("agent-client-time");if(ct)new Chart(ct,{type:"line",data:{labels:d.clientTimeLabels,datasets:[{label:"Avg minutes / client",data:d.clientTime,borderColor:"#0f766e",backgroundColor:"rgba(15,118,110,.18)",tension:.35,fill:true,pointRadius:3}]},options:{plugins:{legend:{display:false},tooltip:tip},scales:{y:{beginAtZero:true,title:{display:true,text:"Minutes"}}},maintainAspectRatio:false}});}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);else go();})();</script>');
return;
endif;

if ($tab === 'targets'): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('flag', 16) ?>Team goals</h2></div>
  <form method="post" class="pad-form form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_goals">
    <div><label>Daily · leads reached</label><input type="number" name="daily_reach" min="0" value="<?= (int) $goalDefaults['daily_reach'] ?>"></div>
    <div><label>Daily · interested clients (sales wins)</label><input type="number" name="daily_sales" min="0" value="<?= (int) $goalDefaults['daily_sales'] ?>"></div>
    <div><label>Weekly · leads reached</label><input type="number" name="weekly_reach" min="0" value="<?= (int) $goalDefaults['weekly_reach'] ?>"></div>
    <div><label>Weekly · sales</label><input type="number" name="weekly_sales" min="0" value="<?= (int) $goalDefaults['weekly_sales'] ?>"></div>
    <div><label>Monthly · leads reached</label><input type="number" name="monthly_reach" min="0" value="<?= (int) $goalDefaults['monthly_reach'] ?>"></div>
    <div><label>Monthly · sales</label><input type="number" name="monthly_sales" min="0" value="<?= (int) $goalDefaults['monthly_sales'] ?>"></div>
    <p class="hint" style="grid-column:1/-1;margin:0">Defaults: daily 10 leads + 2 sales, weekly 10 sales, monthly 30 sales. Agents see these after clock-in.</p>
    <div class="actions" style="grid-column:1/-1"><button class="btn" type="submit"><?= icon('check') ?>Save goals</button></div>
  </form>
</div>
<details class="card" style="margin-bottom:16px">
  <summary class="card-head" style="cursor:pointer;list-style:none"><h2><?= icon('plus', 16) ?>Optional custom period target</h2></summary>
  <form method="post" class="pad-form form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_target">
    <div>
      <label>Agent (blank = team-wide)</label>
      <select name="agent_id">
        <option value="">All agents</option>
        <?php foreach (sales_agents(true) as $a): ?>
          <option value="<?= (int) $a['id'] ?>"><?= h($a['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>From</label><input type="date" name="period_start" required value="<?= h(date('Y-m-01')) ?>"></div>
    <div><label>To</label><input type="date" name="period_end" required value="<?= h(date('Y-m-t')) ?>"></div>
    <div><label>Reach target</label><input type="number" name="reach_target" min="0" value="10"></div>
    <div><label>Sales target</label><input type="number" name="sales_target" min="0" value="2"></div>
    <div class="full"><label>Notes</label><input name="notes"></div>
    <div class="actions" style="grid-column:1/-1"><button class="btn ghost" type="submit">Save custom target</button></div>
  </form>
</details>
<?php if ($targets): ?>
<div class="card">
  <div class="card-head"><h2>Custom period targets</h2></div>
  <div class="table-scroll"><table class="grid"><thead><tr><th>Agent</th><th>Period</th><th class="right">Reach</th><th class="right">Sales</th><th></th></tr></thead><tbody>
    <?php foreach ($targets as $t): ?>
      <tr>
        <td><?= h($t['agent_name'] ?: 'Team-wide') ?></td>
        <td><?= h(format_date($t['period_start']) . ' - ' . format_date($t['period_end'])) ?></td>
        <td class="right mono"><?= (int) $t['reach_target'] ?></td>
        <td class="right mono"><?= (int) $t['sales_target'] ?></td>
        <td class="row-actions"><form method="post" onsubmit="return confirm('Remove target?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_target"><input type="hidden" name="target_id" value="<?= (int) $t['id'] ?>"><button class="btn ghost sm" type="submit">Delete</button></form></td>
      </tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>
<?php layout_end(); return; endif;

if ($tab === 'messages'):
    $agentsLive = sales_agents(true);
    if (!$with && $agentsLive) {
        $with = (int) $agentsLive[0]['id'];
        $thread = array_reverse(sales_messages_for((int) $user['id'], $with));
    }
    ?>
<div class="filter-chips" style="margin:0 0 12px">
  <?php foreach ($agentsLive as $a):
      $unreadFrom = sales_agent_unread_from((int) $a['id']);
      ?>
    <a class="chip<?= $with === (int) $a['id'] ? ' is-on' : '' ?>" href="<?= h(url('admin_sales.php?tab=messages&with=' . (int) $a['id'])) ?>"><?= h($a['name']) ?><?= $unreadFrom ? ' (' . $unreadFrom . ')' : '' ?></a>
  <?php endforeach; ?>
</div>
<?php if (!$agentsLive): ?>
  <p class="empty">Create a sales agent first.</p>
<?php else: ?>
<p class="hint" style="margin:-4px 0 12px">Agent messages notify every super admin. All admins share this inbox and the same dashboard.</p>
<div class="card"><div class="pad-form sales-chat">
  <?php if (empty($thread)): ?><p class="empty">No messages yet.</p>
  <?php else: ?>
    <div class="sales-chat-log">
      <?php foreach ($thread as $m):
          $fromIsAgent = (int) $m['from_user_id'] === (int) $with;
          $mine = !$fromIsAgent;
          ?>
        <div class="sales-chat-bubble<?= $mine ? ' is-mine' : '' ?>">
          <strong><?= h($mine ? 'Vellisys admin' : (string) $m['from_name']) ?></strong>
          <p><?= nl2br(h((string) $m['body'])) ?></p>
          <span><?= h(sales_format_lead_submitted_at($m['created_at'] ?? null)) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <form method="post" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="action" value="send_message"><input type="hidden" name="to_user_id" value="<?= (int) $with ?>">
    <label>Message</label><textarea name="body" rows="3" required></textarea>
    <div class="actions" style="margin-top:10px"><button class="btn" type="submit"><?= icon('send', 16) ?>Send</button></div>
  </form>
</div></div>
<?php endif; ?>
<?php layout_end(); return; endif;

layout_end();
