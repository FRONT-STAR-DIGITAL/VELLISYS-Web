<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_sales_agent();
$uid = (int) $user['id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $posted = isset($_POST['day']) && is_array($_POST['day']) ? $_POST['day'] : [];
    $saved = sales_week_plans_save($uid, $posted);
    if (empty($saved['ok'])) {
        $error = (string) ($saved['error'] ?? 'Could not save the plan.');
    } else {
        flash('Weekly plan saved. Next week, each day replaces the same day from this week.');
        redirect('sales_planner.php');
    }
}

$weekStart = sales_week_start();
$weekEnd = (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');
$days = sales_week_plans_for($uid);

sales_layout_start('Weekly planner', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('calendar') ?>Weekly planner</h1>
    <p class="lede">Choose the days you will work this week and the activities for each day. When the next week starts, Monday replaces last Monday, and so on.</p>
  </div>
</div>

<?php if ($error): ?>
  <p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p>
<?php endif; ?>

<p class="hint" style="margin:0 0 14px">This week · <?= h(format_date($weekStart)) ?> – <?= h(format_date($weekEnd)) ?></p>

<form method="post" class="card pad-form sales-week-planner">
  <?= csrf_field() ?>
  <?php foreach ($days as $d => $day): ?>
    <fieldset class="sales-week-day<?= !empty($day['enabled']) ? ' is-on' : '' ?>">
      <legend>
        <label class="check">
          <input type="checkbox" name="day[<?= (int) $d ?>][enabled]" value="1"<?= !empty($day['enabled']) ? ' checked' : '' ?>>
          <strong><?= h((string) $day['label']) ?></strong>
        </label>
      </legend>
      <label for="day-acts-<?= (int) $d ?>">Activities</label>
      <textarea id="day-acts-<?= (int) $d ?>" name="day[<?= (int) $d ?>][activities]" rows="3" maxlength="4000" placeholder="Calls, visits, follow-ups…"><?= h((string) $day['activities']) ?></textarea>
    </fieldset>
  <?php endforeach; ?>
  <div class="actions" style="margin-top:16px">
    <button class="btn" type="submit"><?= icon('check', 16) ?>Save week</button>
  </div>
</form>
<?php
sales_layout_end();
