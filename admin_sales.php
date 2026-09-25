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

if (!isset($_GET['range']) && trim((string) ($_GET['from'] ?? '')) === '' && in_array($tab, ['overview', 'leads'], true)) {
    $_GET['range'] = 'this_month';
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
            'rejected_reason' => post('rejected_reason'),
            'notes' => post('notes'),
        ], $id);
        if (empty($saved['ok'])) {
            $error = (string) ($saved['error'] ?? 'Could not save business.');
            $tab = 'lead';
            $leadId = (int) ($id ?? 0);
        } else {
            flash($id ? 'Business updated.' : 'Business created.');
            redirect('admin_sales.php?tab=lead&id=' . (int) $saved['id']);
        }
    } elseif ($action === 'onboard_lead') {
        $lid = (int) post('lead_id');
        $lead = sales_lead($lid);
        if (!$lead || (string) $lead['status'] !== 'interested') {
            flash('Only interested leads can be onboarded.', 'err');
            redirect('admin_sales.php?tab=leads');
        }
        sales_lead_mark_onboarded($lid);
        $qs = http_build_query(array_filter([
            'sales_lead' => $lid,
            'company_name' => $lead['business_name'] ?? '',
            'contact_name' => $lead['contact_name'] ?? '',
            'contact_phone' => $lead['contact_phone'] ?? '',
            'city' => $lead['city'] ?? '',
            'address' => $lead['address'] ?? '',
            'plan' => $lead['package_chosen'] ?? '',
        ]));
        flash('Marked onboarded. Continue company setup.');
        redirect('admin_company_new.php?' . $qs);
    } elseif ($action === 'delete_lead') {
        $done = sales_lead_soft_delete((int) post('lead_id'));
        flash(empty($done['ok']) ? ($done['error'] ?? 'Failed') : 'Not-interested business removed from the list. Reports still count it.', empty($done['ok']) ? 'err' : 'ok');
        redirect('admin_sales.php?tab=leads&range=' . urlencode((string) ($period['preset'] ?? 'this_month')));
    } elseif ($action === 'hard_delete_lead') {
        $done = sales_lead_hard_delete((int) post('lead_id'));
        flash(empty($done['ok']) ? ($done['error'] ?? 'Failed') : 'Not-interested business deleted.', empty($done['ok']) ? 'err' : 'ok');
        redirect('admin_sales.php?tab=leads&range=' . urlencode((string) ($period['preset'] ?? 'this_month')));
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
$top = sales_top_agents($from, $to);
$dailyBoard = sales_agents_daily_progress();
$goalDefaults = sales_goal_defaults();
$leadStatus = (string) ($_GET['status'] ?? '');
$leadOpts = ['from' => $from, 'to' => $to];
if ($filterAgent) {
    $leadOpts['agent_id'] = $filterAgent;
}
if ($leadStatus !== '' && isset(sales_statuses()[$leadStatus])) {
    $leadOpts['status'] = $leadStatus;
}
if ($tab === 'leads') {
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
    $agentLeads = sales_leads_query(['agent_id' => $agentId, 'from' => $agentFrom, 'to' => $agentTo]);
    $agentPending = sales_leads_query(['agent_id' => $agentId, 'follow_bucket' => 'due']);
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
  <a class="planner-tab<?= $tab === 'leads' || $tab === 'lead' ? ' is-on' : '' ?>" href="<?= h(url('admin_sales.php?tab=leads')) ?>"><?= icon('clients', 16) ?><span>Businesses</span></a>
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
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <h2><?= icon('flag', 16) ?>Daily goals · all agents</h2>
    <a class="btn ghost sm" href="<?= h(url('admin_sales.php?tab=targets')) ?>">Edit goals</a>
  </div>
  <div class="pad-form">
    <p class="hint" style="margin:0 0 12px">Defaults: <?= (int) $goalDefaults['daily_reach'] ?> leads · <?= (int) $goalDefaults['daily_sales'] ?> sales (interested + onboarded). Tap an agent for daily, weekly and monthly charts.</p>
    <?php if (!$dailyBoard): ?>
      <p class="empty">No live sales agents yet.</p>
    <?php else: ?>
      <div class="sales-agents-goal-grid">
        <?php foreach ($dailyBoard as $row):
            $a = $row['agent'];
            $p = $row['progress'];
            $clocked = !empty($row['clock']);
            ?>
          <a class="card sales-agent-goal-card" href="<?= h(url('admin_sales.php?tab=agent&agent=' . (int) $a['id'] . '&period=daily')) ?>">
            <div class="card-head">
              <div>
                <h2><?= h($a['name']) ?></h2>
                <p class="muted" style="margin:2px 0 0"><?= h($a['email']) ?><?= $clocked ? ' · Clocked in' : ' · Not clocked in' ?></p>
              </div>
              <?= icon('arrow-right', 16) ?>
            </div>
            <div class="pad-form">
              <?php sales_render_goal_bars($p, ['compact' => true, 'force_reach' => true, 'force_sales' => true]); ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php render_filters('admin_sales.php', array_filter(['tab' => 'overview', 'agent_filter' => $filterAgent ?: null]), ['live' => true]); ?>
<p class="hint" style="margin:-8px 0 16px">Team totals for <?= $period['from'] ? h(format_date($from) . ' - ' . format_date($to)) : 'all dates' ?>.</p>
<form method="get" class="filters" style="margin-bottom:16px">
  <input type="hidden" name="tab" value="overview">
  <input type="hidden" name="range" value="<?= h((string) ($period['preset'] ?? 'this_month')) ?>">
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
<div class="card">
  <div class="card-head"><h2>Top performers</h2></div>
  <div class="table-scroll">
    <table class="grid">
      <thead><tr><th>Agent</th><th class="right">Reach</th><th class="right">Sales</th><th class="right">Onboarded</th><th class="right">Rejected</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($top as $row): ?>
          <tr>
            <td><?= h($row['name']) ?><div class="muted"><?= h($row['email']) ?></div></td>
            <td class="right mono"><?= (int) $row['reach'] ?></td>
            <td class="right mono"><?= (int) $row['sales'] ?></td>
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
$payload = json_encode([
    'pieLabels' => ['Interested', 'Follow up', 'Rejected', 'Onboarded'],
    'pieValues' => [(int) $overall['interested'], (int) $overall['follow_up'], (int) $overall['rejected'], (int) $overall['onboarded']],
    'labels' => array_map(static fn ($r) => date('j M', strtotime((string) $r['date'])), $series),
    'reach' => array_column($series, 'reach'),
    'wins' => array_map(static fn ($r) => (int) $r['interested'] + (int) $r['onboarded'], $series),
    'color' => brand_color(),
], JSON_UNESCAPED_UNICODE);
layout_end('<script src="' . h(asset('js/chart.umd.min.js')) . '"></script><script>
(function(){var d=' . $payload . ';var p=document.getElementById("admin-pie");if(p&&window.Chart)new Chart(p,{type:"doughnut",data:{labels:d.pieLabels,datasets:[{data:d.pieValues,backgroundColor:[d.color,"#c4a35a","#b42318","#0f766e"],borderWidth:0}]},options:{cutout:"58%",plugins:{legend:{position:"bottom"}},maintainAspectRatio:false}});var l=document.getElementById("admin-line");if(l&&window.Chart)new Chart(l,{type:"line",data:{labels:d.labels,datasets:[{label:"Reach",data:d.reach,borderColor:d.color,tension:.3,fill:false},{label:"Sales",data:d.wins,borderColor:"#0f766e",tension:.3,fill:false}]},options:{plugins:{legend:{position:"bottom"}},scales:{y:{beginAtZero:true,ticks:{precision:0}}},maintainAspectRatio:false}});})();
</script>');
return;
endif;

if ($tab === 'leads'):
    $followBucket = (string) ($_GET['bucket'] ?? '');
    if ($followBucket === 'pending') {
        $leadOpts['follow_bucket'] = 'due';
        unset($leadOpts['status']);
        $leads = sales_leads_query($leadOpts);
    } elseif ($followBucket === 'followed') {
        $leadOpts['follow_bucket'] = 'done';
        $leads = sales_leads_query($leadOpts);
    }
    ?>
<?php render_filters('admin_sales.php', array_filter(['tab' => 'leads', 'agent_filter' => $filterAgent ?: null, 'status' => $leadStatus ?: null]), ['live' => true]); ?>
<p class="hint" style="margin:-8px 0 16px">Showing <?= $period['from'] ? h(format_date($from) . ' - ' . format_date($to)) : 'all dates' ?>.</p>
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
    <select name="status" onchange="this.form.submit()">
      <option value="">All</option>
      <?php foreach (sales_statuses() as $k => $label): ?>
        <option value="<?= h($k) ?>" <?= $leadStatus === $k && $followBucket === '' ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Follow-ups
    <select name="bucket" onchange="this.form.submit()">
      <option value="">Any</option>
      <option value="pending" <?= $followBucket === 'pending' ? 'selected' : '' ?>>Not followed up</option>
      <option value="followed" <?= $followBucket === 'followed' ? 'selected' : '' ?>>Followed up</option>
    </select>
  </label>
</form>
<div class="card">
  <div class="card-head">
    <h2>Businesses</h2>
    <a class="btn sm" href="<?= h(url('admin_sales.php?tab=lead')) ?>"><?= icon('plus', 14) ?>Add</a>
  </div>
  <div class="table-scroll">
    <table class="grid">
      <thead><tr><th>Business</th><th>Agent</th><th>Status</th><th>Contact</th><th>City</th><th>Follow-up</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($leads as $lead): ?>
          <tr>
            <td><?= h(trim((string) $lead['business_name']) ?: '-') ?>
              <?php if (($lead['status'] ?? '') === 'rejected' && !empty($lead['rejected_reason'])): ?>
                <div class="muted"><?= h((string) $lead['rejected_reason']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= h((string) $lead['agent_name']) ?></td>
            <td><span class="pill"><?= h(sales_status_label((string) $lead['status'])) ?></span>
              <?php if (!empty($lead['follow_up_done_at'])): ?>
                <div class="muted">Followed <?= h(format_date(substr((string) $lead['follow_up_done_at'], 0, 10))) ?></div>
              <?php elseif (($lead['status'] ?? '') === 'follow_up'): ?>
                <div class="muted">Awaiting follow-up</div>
              <?php endif; ?>
            </td>
            <td><?= h(trim($lead['contact_name'] . ' ' . $lead['contact_phone'])) ?></td>
            <td><?= h((string) $lead['city']) ?></td>
            <td class="date-cell"><?= !empty($lead['follow_up_date']) ? h(format_date($lead['follow_up_date'])) : '-' ?></td>
            <td class="date-cell"><?= h(format_date(substr((string) $lead['created_at'], 0, 10))) ?></td>
            <td class="row-actions">
              <a class="btn ghost sm" href="<?= h(url('admin_sales.php?tab=lead&id=' . (int) $lead['id'])) ?>">Edit</a>
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
  <?php if (!$leads): ?><p class="empty">No businesses in this filter.</p><?php endif; ?>
</div>
<?php layout_end(); return; endif;

if ($tab === 'lead'):
    $packages = sales_packages();
    $status = (string) ($_POST['status'] ?? ($editLead['status'] ?? 'interested'));
    if (!isset(sales_statuses()[$status])) {
        $status = 'interested';
    }
    $agentPick = (int) ($_POST['agent_id'] ?? ($editLead['agent_id'] ?? 0));
    ?>
<div class="page-head" style="margin-top:0">
  <div>
    <h2><?= $editLead ? 'Edit business' : 'New business' ?></h2>
    <p class="lede">Full CRUD for field businesses. Interested clients can be onboarded from the list.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn ghost" href="<?= h(url('admin_sales.php?tab=leads')) ?>">Back</a>
  </div>
</div>
<form method="post" class="card pad-form form-grid" data-admin-lead>
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
  <div data-admin-panel="follow_up">
    <label>Follow-up date</label>
    <input type="date" name="follow_up_date" value="<?= h((string) ($_POST['follow_up_date'] ?? $editLead['follow_up_date'] ?? '')) ?>">
  </div>
  <div class="full" data-admin-panel="rejected">
    <label>Rejection reason</label>
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
  function sync(){
    var st=(form.querySelector('[data-admin-status]')||{}).value||'interested';
    form.querySelectorAll('[data-admin-panel]').forEach(function(p){
      var name=p.getAttribute('data-admin-panel');
      p.hidden = !(st===name || (name==='interested' && (st==='interested'||st==='onboarded')));
    });
  }
  var sel=form.querySelector('[data-admin-status]');
  if(sel) sel.addEventListener('change', sync);
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
    <div><label>Title</label><input name="job_title" value="<?= h((string) ($edit['job_title'] ?? 'Sales agent')) ?>"></div>
    <div><label>Password<?= $edit ? ' (blank = keep)' : '' ?></label><input name="password" type="text" autocomplete="new-password" placeholder="<?= $edit ? 'Leave blank to keep' : 'Auto if blank' ?>"></div>
    <div class="actions" style="grid-column:1/-1"><button class="btn" type="submit"><?= icon('check') ?>Save</button></div>
  </form>
</div>
<?php endif; ?>
<div class="card">
  <div class="table-scroll">
    <table class="grid">
      <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Today</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($agents as $a):
            $day = sales_progress((int) $a['id'], 'daily');
            ?>
          <tr>
            <td><a href="<?= h(url('admin_sales.php?tab=agent&agent=' . (int) $a['id'] . '&period=daily')) ?>"><?= h($a['name']) ?></a></td>
            <td><?= h($a['email']) ?></td>
            <td><span class="pill"><?= h(($a['status'] ?? 'live') === 'suspended' ? 'Suspended' : 'Live') ?></span></td>
            <td class="mono"><?= (int) $day['reach'] ?>/<?= (int) $day['reach_goal'] ?> leads · <?= (int) $day['sales'] ?>/<?= (int) $day['sales_goal'] ?> sales</td>
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
    <p class="lede"><?= h($agentRow['email']) ?> · <?= ($agentRow['status'] ?? '') === 'suspended' ? 'Suspended' : 'Live' ?> · <?= h($periodLabels[$periodKind]) ?> <?= h(format_date($agentFrom)) ?><?= $agentFrom !== $agentTo ? ' – ' . h(format_date($agentTo)) : '' ?></p>
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
    <?php sales_render_goal_bars($agentProgress, ['force_reach' => $periodKind === 'daily', 'force_sales' => true]); ?>
  </div>
</div>
<div class="stats">
  <div class="card stat"><span>Reach</span><strong><?= (int) $agentStats['reach'] ?></strong></div>
  <div class="card stat"><span>Sales (wins)</span><strong><?= (int) $agentStats['wins'] ?></strong></div>
  <div class="card stat"><span>Interested</span><strong><?= (int) $agentStats['interested'] ?></strong></div>
  <div class="card stat"><span>Onboarded</span><strong><?= (int) $agentStats['onboarded'] ?></strong></div>
</div>
<div class="chart-grid equal" style="margin-bottom:16px">
  <div class="card chart-box"><div class="card-head"><h2>Status mix</h2></div><div class="pad-form" style="height:220px"><canvas id="agent-pie"></canvas></div></div>
  <div class="card chart-box"><div class="card-head"><h2><?= $periodKind === 'daily' ? 'Today by status' : 'Reach & sales over time' ?></h2></div><div class="pad-form" style="height:220px"><canvas id="agent-line"></canvas></div></div>
</div>
<div class="desk-grid stock-split" style="margin-bottom:16px">
  <div class="card">
    <div class="card-head"><h2>Not followed up</h2></div>
    <?php if (!$agentPending): ?><p class="empty">None waiting.</p>
    <?php else: ?>
      <div class="table-scroll"><table class="grid"><thead><tr><th>Business</th><th>Due</th></tr></thead><tbody>
        <?php foreach ($agentPending as $lead): ?>
          <tr><td><a href="<?= h(url('admin_sales.php?tab=lead&id=' . (int) $lead['id'])) ?>"><?= h(trim((string) $lead['business_name']) ?: '-') ?></a></td><td><?= h(format_date($lead['follow_up_date'])) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h2>Followed up</h2></div>
    <?php if (!$agentFollowed): ?><p class="empty">No follow-up updates in this period.</p>
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
  <div class="table-scroll"><table class="grid"><thead><tr><th>Business</th><th>Status</th><th>City</th><th>When</th><th></th></tr></thead><tbody>
    <?php foreach ($agentLeads as $lead): ?>
      <tr>
        <td><?= h(trim((string) $lead['business_name']) ?: '-') ?></td>
        <td><?= h(sales_status_label((string) $lead['status'])) ?></td>
        <td><?= h((string) $lead['city']) ?></td>
        <td><?= h(format_date(substr((string) $lead['created_at'], 0, 10))) ?></td>
        <td class="row-actions"><a class="btn ghost sm" href="<?= h(url('admin_sales.php?tab=lead&id=' . (int) $lead['id'])) ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php if (!$agentLeads): ?><p class="empty">No businesses logged in this period.</p><?php endif; ?>
</div>
<?php
$payload = json_encode([
    'pieLabels' => ['Interested', 'Follow up', 'Rejected', 'Onboarded'],
    'pieValues' => [(int) $agentStats['interested'], (int) $agentStats['follow_up'], (int) $agentStats['rejected'], (int) $agentStats['onboarded']],
    'labels' => array_map(static fn ($r) => date('j M', strtotime((string) $r['date'])), $agentSeries),
    'reach' => array_column($agentSeries, 'reach'),
    'wins' => array_map(static fn ($r) => (int) $r['interested'] + (int) $r['onboarded'], $agentSeries),
    'barLabels' => ['Interested', 'Follow up', 'Rejected', 'Onboarded'],
    'barValues' => [(int) $agentStats['interested'], (int) $agentStats['follow_up'], (int) $agentStats['rejected'], (int) $agentStats['onboarded']],
    'daily' => $periodKind === 'daily',
    'color' => brand_color(),
], JSON_UNESCAPED_UNICODE);
layout_end('<script src="' . h(asset('js/chart.umd.min.js')) . '"></script><script>(function(){var d=' . $payload . ';var p=document.getElementById("agent-pie");if(p&&window.Chart)new Chart(p,{type:"doughnut",data:{labels:d.pieLabels,datasets:[{data:d.pieValues,backgroundColor:[d.color,"#c4a35a","#b42318","#0f766e"],borderWidth:0}]},options:{cutout:"58%",plugins:{legend:{position:"bottom"}},maintainAspectRatio:false}});var l=document.getElementById("agent-line");if(l&&window.Chart){if(d.daily){new Chart(l,{type:"bar",data:{labels:d.barLabels,datasets:[{data:d.barValues,backgroundColor:[d.color,"#c4a35a","#b42318","#0f766e"],borderRadius:6}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}},maintainAspectRatio:false}});}else{new Chart(l,{type:"line",data:{labels:d.labels,datasets:[{label:"Reach",data:d.reach,borderColor:d.color,tension:.3,fill:false},{label:"Sales",data:d.wins,borderColor:"#0f766e",tension:.3,fill:false}]},options:{plugins:{legend:{position:"bottom"}},scales:{y:{beginAtZero:true,ticks:{precision:0}}},maintainAspectRatio:false}});}}})();</script>');
return;
endif;

if ($tab === 'targets'): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('flag', 16) ?>Team goals</h2></div>
  <form method="post" class="pad-form form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_goals">
    <div><label>Daily · leads reached</label><input type="number" name="daily_reach" min="0" value="<?= (int) $goalDefaults['daily_reach'] ?>"></div>
    <div><label>Daily · sales (interested + onboarded)</label><input type="number" name="daily_sales" min="0" value="<?= (int) $goalDefaults['daily_sales'] ?>"></div>
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
      $unreadFrom = (int) (db_one(
          'SELECT COUNT(*) c FROM sales_messages WHERE to_user_id = ? AND from_user_id = ? AND read_at IS NULL',
          'ii',
          [(int) $user['id'], (int) $a['id']]
      )['c'] ?? 0);
      ?>
    <a class="chip<?= $with === (int) $a['id'] ? ' is-on' : '' ?>" href="<?= h(url('admin_sales.php?tab=messages&with=' . (int) $a['id'])) ?>"><?= h($a['name']) ?><?= $unreadFrom ? ' (' . $unreadFrom . ')' : '' ?></a>
  <?php endforeach; ?>
</div>
<?php if (!$agentsLive): ?>
  <p class="empty">Create a sales agent first.</p>
<?php else: ?>
<div class="card"><div class="pad-form sales-chat">
  <?php if (empty($thread)): ?><p class="empty">No messages yet.</p>
  <?php else: ?>
    <div class="sales-chat-log">
      <?php foreach ($thread as $m):
          $mine = (int) $m['from_user_id'] === (int) $user['id']; ?>
        <div class="sales-chat-bubble<?= $mine ? ' is-mine' : '' ?>"><strong><?= h($mine ? 'You' : (string) $m['from_name']) ?></strong><p><?= nl2br(h((string) $m['body'])) ?></p></div>
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
