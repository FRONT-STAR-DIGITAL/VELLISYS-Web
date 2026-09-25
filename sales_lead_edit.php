<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_sales_agent();
sales_require_clock_in();

$id = (int) ($_GET['id'] ?? 0);
$lead = $id ? sales_lead($id) : null;
if ($id && (!$lead || (int) $lead['agent_id'] !== (int) $user['id'] || !empty($lead['deleted_at']))) {
    flash('Lead not found.', 'err');
    redirect('sales_leads.php');
}
$error = '';
$packages = sales_packages();
$locked = $lead && in_array((string) ($lead['status'] ?? ''), ['onboarded', 'onboarding'], true);
$status = (string) ($_POST['status'] ?? ($lead['status'] ?? 'interested'));
if (!$locked && (!isset(sales_statuses()[$status]) || in_array($status, ['onboarded', 'onboarding'], true))) {
    $status = 'interested';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($locked) {
        flash('This lead is already in onboarding or onboarded.', 'err');
        redirect('sales_lead_edit.php?id=' . $id);
    }
    $saved = sales_lead_save([
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
        'interest_rating' => (int) post('interest_rating'),
        'rejected_category' => post('rejected_category'),
        'rejected_reason' => post('rejected_reason'),
        'notes' => post('notes'),
    ], $id ?: null, (int) $user['id']);
    if (empty($saved['ok'])) {
        $error = (string) ($saved['error'] ?? 'Could not save.');
        $status = (string) post('status');
    } else {
        flash($id ? 'Lead updated.' : 'Lead saved.');
        redirect('sales_lead_edit.php?id=' . (int) $saved['id']);
    }
}

$clock = sales_today_clock((int) $user['id']);
$rejectCat = (string) ($_POST['rejected_category'] ?? ($lead['rejected_category'] ?? ''));
$interest = (int) ($_POST['interest_rating'] ?? ($lead['interest_rating'] ?? 0));
sales_layout_start($id ? 'Edit lead' : 'New lead', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon($id ? 'pencil' : 'plus') ?><?= $id ? 'Edit lead' : 'New lead' ?></h1>
    <p class="lede">Pick the status first. Rejected needs a reason from the list plus a short explanation. Follow-up needs an interest rating.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn ghost" href="<?= h(url('sales_leads.php')) ?>">Back</a>
  </div>
</div>
<?php if ($error): ?><p class="flash flash-err"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>
<?php if ($lead && in_array(($lead['status'] ?? ''), ['onboarded', 'onboarding'], true)): ?>
  <p class="flash"><?= ($lead['status'] ?? '') === 'onboarding'
    ? 'This lead is in company onboarding. Finish setup under Companies, then mark the desk live.'
    : 'This lead is onboarded. Ask admin if you need changes.' ?></p>
<?php endif; ?>

<form method="post" class="card pad-form sales-lead-form" data-sales-lead>
  <?= csrf_field() ?>
  <fieldset class="sales-status-pick">
    <legend>Status</legend>
    <div class="filter-chips">
      <?php foreach (['interested' => 'Interested', 'follow_up' => 'Follow up', 'rejected' => 'Rejected'] as $k => $label): ?>
        <label class="chip<?= $status === $k ? ' is-on' : '' ?>">
          <input type="radio" name="status" value="<?= h($k) ?>" <?= $status === $k ? 'checked' : '' ?> data-status-radio>
          <?= h($label) ?>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <div data-panel="rejected" <?= $status === 'rejected' ? '' : 'hidden' ?>>
    <label for="rejected_category">Why they rejected Vellisys</label>
    <select id="rejected_category" name="rejected_category">
      <option value="">Choose a reason</option>
      <?php foreach (sales_reject_reasons() as $k => $label): ?>
        <option value="<?= h($k) ?>" <?= $rejectCat === $k ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="rejected_reason" style="margin-top:12px">Explain the rejection</label>
    <textarea id="rejected_reason" name="rejected_reason" rows="3" placeholder="Short note on what they said"><?= h((string) ($_POST['rejected_reason'] ?? $lead['rejected_reason'] ?? '')) ?></textarea>
  </div>

  <div data-panel="details" <?= $status === 'rejected' ? 'hidden' : '' ?>>
    <div class="form-grid">
      <div>
        <label for="business_name">Business name</label>
        <input id="business_name" name="business_name" value="<?= h((string) ($_POST['business_name'] ?? $lead['business_name'] ?? '')) ?>">
      </div>
      <div>
        <label for="city">City / area</label>
        <input id="city" name="city" value="<?= h((string) ($_POST['city'] ?? $lead['city'] ?? ($clock['location_city'] ?? ''))) ?>">
      </div>
      <div class="full">
        <label for="address">Address (building · shop no.)</label>
        <input id="address" name="address" value="<?= h((string) ($_POST['address'] ?? $lead['address'] ?? '')) ?>">
      </div>
      <div>
        <label for="contact_name">Contact person</label>
        <input id="contact_name" name="contact_name" value="<?= h((string) ($_POST['contact_name'] ?? $lead['contact_name'] ?? '')) ?>">
      </div>
      <div>
        <label for="contact_phone">Phone</label>
        <input id="contact_phone" name="contact_phone" inputmode="tel" value="<?= h((string) ($_POST['contact_phone'] ?? $lead['contact_phone'] ?? '')) ?>">
      </div>
    </div>

    <div data-panel="interested" <?= $status === 'interested' ? '' : 'hidden' ?>>
      <div class="form-grid">
        <div>
          <label for="nature_of_business">Nature of business</label>
          <input id="nature_of_business" name="nature_of_business" value="<?= h((string) ($_POST['nature_of_business'] ?? $lead['nature_of_business'] ?? '')) ?>">
        </div>
        <div>
          <label for="package_chosen">Package</label>
          <select id="package_chosen" name="package_chosen">
            <option value="">-</option>
            <?php $pkg = (string) ($_POST['package_chosen'] ?? $lead['package_chosen'] ?? ''); foreach ($packages as $k => $label): ?>
              <option value="<?= h($k) ?>" <?= $pkg === $k ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="onboard_date">Preferred onboarding date</label>
          <input id="onboard_date" name="onboard_date" type="date" value="<?= h((string) ($_POST['onboard_date'] ?? $lead['onboard_date'] ?? '')) ?>">
        </div>
      </div>
    </div>

    <div data-panel="follow_up" <?= $status === 'follow_up' ? '' : 'hidden' ?>>
      <label for="follow_up_date">Follow-up date</label>
      <input id="follow_up_date" name="follow_up_date" type="date" value="<?= h((string) ($_POST['follow_up_date'] ?? $lead['follow_up_date'] ?? '')) ?>">
      <p class="hint">You get a reminder the day before.</p>
      <label for="interest_rating" style="margin-top:12px">Interest in Vellisys (1–5)</label>
      <select id="interest_rating" name="interest_rating">
        <option value="0">Rate interest</option>
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <option value="<?= $i ?>" <?= $interest === $i ? 'selected' : '' ?>><?= $i ?> — <?= $i === 1 ? 'Low' : ($i === 5 ? 'Very high' : '') ?></option>
        <?php endfor; ?>
      </select>
    </div>
  </div>

  <label for="notes">Notes (optional)</label>
  <textarea id="notes" name="notes" rows="3"><?= h((string) ($_POST['notes'] ?? $lead['notes'] ?? '')) ?></textarea>

  <div class="actions" style="margin-top:16px">
    <button class="btn" type="submit" <?= in_array(($lead['status'] ?? ''), ['onboarded', 'onboarding'], true) ? 'disabled' : '' ?>><?= icon('check') ?>Save</button>
  </div>
</form>
<script>
(function(){
  var form = document.querySelector('[data-sales-lead]');
  if (!form) return;
  function sync(){
    var st = (form.querySelector('[data-status-radio]:checked') || {}).value || 'interested';
    form.querySelectorAll('.sales-status-pick .chip').forEach(function(c){
      c.classList.toggle('is-on', !!(c.querySelector('input') && c.querySelector('input').checked));
    });
    form.querySelectorAll('[data-panel]').forEach(function(p){
      var name = p.getAttribute('data-panel');
      if (name === 'details') p.hidden = st === 'rejected';
      else if (name === 'rejected') p.hidden = st !== 'rejected';
      else p.hidden = st !== name;
    });
  }
  form.querySelectorAll('[data-status-radio]').forEach(function(r){ r.addEventListener('change', sync); });
  sync();
})();
</script>
<?php sales_layout_end(); ?>
