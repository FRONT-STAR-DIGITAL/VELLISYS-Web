<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_sales_agent();

$id = (int) ($_GET['id'] ?? 0);
// Clock-in is only required when adding a new lead — nowhere else on sales.
if ($id < 1) {
    sales_require_clock_in();
}
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
    $action = post('action');
    if ($action === 'start_testing') {
        if (!$lead || $locked) {
            flash('Save an interested lead before opening a test desk.', 'err');
            redirect($id ? ('sales_lead_edit.php?id=' . $id) : 'sales_lead_edit.php');
        }
        $made = sales_start_testing_from_lead((int) $lead['id'], (int) $user['id'], post('desk_email'));
        if (empty($made['ok'])) {
            if (!empty($made['company_id'])) {
                flash((string) ($made['error'] ?? 'Testing desk already exists.'));
                redirect('sales_lead_edit.php?id=' . (int) $lead['id'] . '#lead-testing');
            }
            flash((string) ($made['error'] ?? 'Could not open the test desk.'), 'err');
            redirect('sales_lead_edit.php?id=' . (int) $lead['id'] . '#lead-testing');
        }
        $_SESSION['testing_creds'] = [
            'company_id' => (int) $made['company_id'],
            'name' => (string) $made['name'],
            'email' => (string) $made['email'],
            'password' => (string) $made['password'],
            'expires_at' => (string) $made['expires_at'],
        ];
        flash($made['name'] . ' test desk is ready. Login is below.');
        redirect('sales_lead_edit.php?id=' . (int) $lead['id'] . '#lead-testing');
    }
    if ($action === 'set_testing_email') {
        if (!$lead || $locked || empty($lead['company_id'])) {
            flash('Open a test desk first.', 'err');
            redirect($id ? ('sales_lead_edit.php?id=' . $id . '#lead-testing') : 'sales_lead_edit.php');
        }
        $co = db_one('SELECT id, testing_mode, testing_owner_id FROM companies WHERE id = ?', 'i', [(int) $lead['company_id']]);
        if (!$co || empty($co['testing_mode']) || (int) ($co['testing_owner_id'] ?? 0) !== (int) $user['id']) {
            flash('Testing desk not found.', 'err');
            redirect('sales_lead_edit.php?id=' . (int) $lead['id'] . '#lead-testing');
        }
        $done = sales_testing_set_login((int) $co['id'], post('desk_email'), true);
        flash(
            empty($done['ok'])
                ? (string) ($done['error'] ?? 'Could not update login.')
                : ('Desk login set to ' . (string) ($done['email'] ?? '') . ' · password Folio2026'),
            empty($done['ok']) ? 'err' : 'ok'
        );
        redirect('sales_lead_edit.php?id=' . (int) $lead['id'] . '#lead-testing');
    }
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
        'follow_up_time' => post('follow_up_time'),
        'interest_rating' => (int) post('interest_rating'),
        'rejected_category' => post('rejected_category'),
        'rejected_reason' => post('rejected_reason'),
    ], $id ?: null, (int) $user['id']);
    if (empty($saved['ok'])) {
        $error = (string) ($saved['error'] ?? 'Could not save.');
        $status = (string) post('status');
    } else {
        $newId = (int) $saved['id'];
        flash($id ? 'Lead updated.' : 'Lead saved.');
        $wantsTest = post('wants_testing') !== '' && (string) post('status') === 'interested';
        if ($wantsTest) {
            $made = sales_start_testing_from_lead($newId, (int) $user['id'], post('desk_email'));
            if (!empty($made['ok'])) {
                $_SESSION['testing_creds'] = [
                    'company_id' => (int) $made['company_id'],
                    'name' => (string) $made['name'],
                    'email' => (string) $made['email'],
                    'password' => (string) $made['password'],
                    'expires_at' => (string) $made['expires_at'],
                ];
                flash($made['name'] . ' saved. Test desk login is below.');
            } elseif (empty($made['company_id'])) {
                flash('Lead saved, but test desk was not opened: ' . (string) ($made['error'] ?? 'unknown error'), 'err');
            }
        }
        redirect('sales_lead_edit.php?id=' . $newId . ($wantsTest ? '#lead-testing' : ''));
    }
}

$clock = sales_today_clock((int) $user['id']);
$rejectCat = (string) ($_POST['rejected_category'] ?? ($lead['rejected_category'] ?? ''));
$interest = (int) ($_POST['interest_rating'] ?? ($lead['interest_rating'] ?? 0));
$savedStatus = (string) ($lead['status'] ?? '');
$fromFollowOrRejected = in_array($savedStatus, ['follow_up', 'rejected'], true);

$testCompany = null;
$testCreds = null;
$testExpired = false;
if ($lead && !empty($lead['company_id'])) {
    $testCompany = db_one('SELECT * FROM companies WHERE id = ?', 'i', [(int) $lead['company_id']]);
    if ($testCompany && !empty($testCompany['testing_mode'])) {
        $testCreds = sales_testing_credentials((int) $testCompany['id']);
        $testExpired = company_testing_expired($testCompany);
    } elseif ($testCompany && empty($testCompany['testing_mode'])) {
        // Promoted / full company linked to this lead.
    } else {
        $testCompany = null;
    }
}
$canOfferTesting = !$locked && (!$testCompany || empty($testCompany['testing_mode']));
$wantsTestingChecked = post('wants_testing') !== '';
$credsFlash = $_SESSION['testing_creds'] ?? null;
if (is_array($credsFlash)) {
    unset($_SESSION['testing_creds']);
    if (!$testCreds && !empty($credsFlash['email'])) {
        $testCreds = [
            'email' => (string) $credsFlash['email'],
            'password' => (string) $credsFlash['password'],
        ];
    }
}

sales_layout_start($id ? 'Edit lead' : 'New lead', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon($id ? 'pencil' : 'plus') ?><?= $id ? 'Edit lead' : 'New lead' ?></h1>
    <p class="lede">Pick the status first. Rejected needs why they rejected Vellisys, an explanation, and nature of business. Follow-up needs an interest rating.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn ghost" href="<?= h(url('sales_leads.php')) ?>">Back</a>
  </div>
</div>
<?php if ($error): ?><p class="flash flash-err"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<?php if ($lead && !$locked && ($lead['status'] ?? '') === 'follow_up'): ?>
  <?php $fuInterest = (int) ($lead['interest_rating'] ?? 0); ?>
  <div class="card pad-form lead-flow-card" id="lead-followup" style="margin-bottom:16px">
    <div class="card-head" style="padding:0;margin-bottom:8px;border:0">
      <h2 style="margin:0"><?= icon('calendar', 16) ?>Open follow-up</h2>
      <div class="actions wrap-actions">
        <span class="<?= h(sales_interest_pill_class($fuInterest)) ?>"><?= h(sales_interest_label($fuInterest)) ?></span>
        <span class="pill warn">Due <?= h(sales_format_follow_up($lead)) ?></span>
      </div>
    </div>
    <p class="lede" style="margin-top:0">Call or message the contact, then change status below to Interested, another Follow up date, or Rejected. If they become Interested and need a trial, open a Pro test desk when you save.</p>
    <?php sales_render_lead_contact($lead); ?>
  </div>
<?php endif; ?>

<?php if ($lead && in_array($savedStatus, ['onboarded', 'onboarding'], true)): ?>
  <div class="card pad-form lead-flow-card" style="margin-bottom:16px">
    <h2 style="margin-top:0"><?= $savedStatus === 'onboarding' ? 'In onboarding' : 'Onboarded' ?></h2>
    <p class="lede" style="margin-top:0">
      <?php if ($savedStatus === 'onboarding'): ?>
        Message admin from Chat if the client needs help while onboarding finishes.
      <?php else: ?>
        This client is live. Message admin if you need a change.
      <?php endif; ?>
    </p>
  </div>
<?php elseif ($lead && $savedStatus === 'interested'): ?>
  <div class="card pad-form lead-flow-card" id="lead-testing" style="margin-bottom:16px">
    <h2 style="margin-top:0">Testing</h2>
    <?php if ($testCompany && !empty($testCompany['testing_mode'])): ?>
      <p class="lede" style="margin-top:0">
        Ends <?= !empty($testCompany['testing_expires_at']) ? h(format_date((string) $testCompany['testing_expires_at'])) : '-' ?>
        · <?= h(company_testing_remaining_label($testCompany)) ?>.
      </p>
      <?php if ($testCreds): ?>
        <p><strong>Username:</strong> <code data-copy><?= h((string) ($testCreds['email'] ?: '-')) ?></code></p>
        <p><strong>Password:</strong> <code data-copy><?= h((string) (($testCreds['password'] !== '' ? $testCreds['password'] : sales_testing_default_password()))) ?></code></p>
      <?php endif; ?>
      <?php if (!$testExpired): ?>
        <form method="post" class="pad-form" style="margin-top:12px;padding:0">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="set_testing_email">
          <label for="desk_email_edit">Client login email</label>
          <input id="desk_email_edit" name="desk_email" type="email" required value="<?= h((string) ($testCreds['email'] ?? '')) ?>" placeholder="client@theircompany.com" autocomplete="off">
          <p class="hint" style="margin:4px 0 0">Saving sets this login and resets the password to Folio2026.</p>
          <div class="actions" style="margin-top:10px">
            <button class="btn ghost sm" type="submit"><?= icon('check', 14) ?>Update login</button>
          </div>
        </form>
      <?php endif; ?>
      <?php if ($testExpired): ?>
        <p class="flash flash-err" style="margin:12px 0 0">Test ended. Message admin to extend or promote.</p>
      <?php endif; ?>
      <div class="actions wrap-actions" style="margin-top:12px">
        <?php if (!$testExpired): ?>
          <a class="btn" href="<?= h(url('sales_desk.php?id=' . (int) $testCompany['id'])) ?>"><?= icon('desk', 14) ?>Open desk</a>
        <?php endif; ?>
        <a class="btn ghost" href="<?= h(url('sales_company.php?id=' . (int) $testCompany['id'])) ?>"><?= icon('building', 14) ?>Desk details</a>
        <a class="btn ghost" href="<?= h(url('sales_messages.php')) ?>"><?= icon('mail', 14) ?>Message admin</a>
      </div>
    <?php elseif ($testCompany && empty($testCompany['testing_mode'])): ?>
      <p class="lede" style="margin-top:0">This lead's company is past testing. Message admin if you need help.</p>
    <?php else: ?>
      <p class="lede" style="margin-top:0">Open a Pro test desk (stock, Profit &amp; Loss, Planner). Enter their preferred login email. Password is Folio2026.</p>
      <form method="post" class="pad-form" style="margin-top:12px;padding:0">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="start_testing">
        <label for="desk_email_open">Client login email</label>
        <input id="desk_email_open" name="desk_email" type="email" required value="<?= h(post('desk_email')) ?>" placeholder="client@theircompany.com" autocomplete="off">
        <p class="hint" style="margin:4px 0 0">Use the email the client wants to sign in with. Password: Folio2026.</p>
        <div class="actions" style="margin-top:10px">
          <button class="btn" type="submit"><?= icon('plus', 14) ?>Open test desk</button>
        </div>
      </form>
      <p class="hint" style="margin-top:10px">Needs business name, contact person and phone saved above.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<form method="post" class="card pad-form sales-lead-form" data-sales-lead<?= $fromFollowOrRejected && $canOfferTesting ? ' data-convert-testing="1"' : '' ?>>
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
    <textarea id="rejected_reason" name="rejected_reason" rows="3" placeholder="What they said about turning Vellisys down"><?= h((string) ($_POST['rejected_reason'] ?? $lead['rejected_reason'] ?? '')) ?></textarea>
    <label for="nature_rejected" style="margin-top:12px">Nature of business</label>
    <input id="nature_rejected" name="nature_of_business" value="<?= h((string) ($_POST['nature_of_business'] ?? $lead['nature_of_business'] ?? '')) ?>" placeholder="Shop, clinic, transport…" <?= $status === 'rejected' ? '' : 'disabled' ?>>
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
          <input id="nature_of_business" name="nature_of_business" value="<?= h((string) ($_POST['nature_of_business'] ?? $lead['nature_of_business'] ?? '')) ?>" <?= $status === 'interested' ? '' : 'disabled' ?>>
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
      <?php if ($canOfferTesting): ?>
        <div class="lead-testing-on-save" style="margin-top:16px;padding:14px;border:1px solid var(--line);border-radius:10px;background:color-mix(in srgb, var(--brand) 4%, #fff)">
          <h3 style="margin:0 0 6px;font-size:1.05rem"><?= icon('desk', 16) ?>Pro test desk</h3>
          <p class="hint" style="margin:0 0 10px">
            <?= $fromFollowOrRejected
                ? 'This lead was a follow-up or rejection. If they need a trial, open a Pro test desk (stock, Profit &amp; Loss, Planner) when you save as Interested.'
                : 'Open a Pro test desk (stock, Profit &amp; Loss, Planner) when you save. Password: Folio2026.' ?>
          </p>
          <label class="check lead-wants-testing">
            <input type="checkbox" name="wants_testing" value="1" data-wants-testing <?= $wantsTestingChecked ? 'checked' : '' ?>>
            Open a test desk when I save
          </label>
          <div data-wants-testing-fields style="margin-top:10px" <?= $wantsTestingChecked ? '' : 'hidden' ?>>
            <label for="desk_email">Client login email</label>
            <input id="desk_email" name="desk_email" type="email" value="<?= h(post('desk_email')) ?>" placeholder="client@theircompany.com" autocomplete="off" data-wants-testing-email>
            <p class="hint" style="margin:4px 0 0">Required when opening a test desk. Password: Folio2026.</p>
          </div>
          <p class="hint" style="margin:8px 0 0">Needs business name, contact person and phone above.</p>
        </div>
      <?php endif; ?>
    </div>

    <div data-panel="follow_up" <?= $status === 'follow_up' ? '' : 'hidden' ?>>
      <div class="form-grid">
        <div>
          <label for="follow_up_date">Follow-up date</label>
          <input id="follow_up_date" name="follow_up_date" type="date" value="<?= h((string) ($_POST['follow_up_date'] ?? $lead['follow_up_date'] ?? '')) ?>">
        </div>
        <div>
          <label for="follow_up_time">Time <span class="muted">(optional)</span></label>
          <input id="follow_up_time" name="follow_up_time" type="time" value="<?= h(sales_follow_up_time_input((string) ($_POST['follow_up_time'] ?? $lead['follow_up_time'] ?? ''))) ?>">
        </div>
      </div>
      <p class="hint">You get a reminder the day before. Add a time if you booked a slot.</p>
      <label for="interest_rating" style="margin-top:12px">Interest in Vellisys (1-5)</label>
      <select id="interest_rating" name="interest_rating">
        <option value="0">Rate interest</option>
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <option value="<?= $i ?>" <?= $interest === $i ? 'selected' : '' ?>><?= $i === 1 ? '1 - Low' : ($i === 5 ? '5 - Very high' : (string) $i) ?></option>
        <?php endfor; ?>
      </select>
    </div>
  </div>

  <div class="actions" style="margin-top:16px">
    <button class="btn" type="submit" <?= in_array(($lead['status'] ?? ''), ['onboarded', 'onboarding'], true) ? 'disabled' : '' ?>><?= icon('check') ?>Save</button>
  </div>
</form>
<script>
(function(){
  var form = document.querySelector('[data-sales-lead]');
  if (!form) return;
  var convertTesting = form.getAttribute('data-convert-testing') === '1';
  var wants = form.querySelector('[data-wants-testing]');
  var wantsTouched = false;
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
    var interestedNature = form.querySelector('#nature_of_business');
    var rejectedNature = form.querySelector('#nature_rejected');
    if (interestedNature) interestedNature.disabled = st !== 'interested';
    if (rejectedNature) rejectedNature.disabled = st !== 'rejected';
    var wantsFields = form.querySelector('[data-wants-testing-fields]');
    var wantsEmail = form.querySelector('[data-wants-testing-email]');
    if (wants) {
      wants.disabled = st !== 'interested';
      var show = st === 'interested' && wants.checked;
      if (wantsFields) wantsFields.hidden = !show;
      if (wantsEmail) {
        wantsEmail.required = show;
        wantsEmail.disabled = !show;
      }
    }
  }
  form.querySelectorAll('[data-status-radio]').forEach(function(r){
    r.addEventListener('change', function(){
      // Follow-up / rejected → Interested: offer the same test desk creation as new interested leads.
      if (convertTesting && !wantsTouched && wants) {
        wants.checked = this.value === 'interested';
      }
      sync();
    });
  });
  if (wants) wants.addEventListener('change', function(){ wantsTouched = true; sync(); });
  sync();
})();
</script>
<?php sales_layout_end(); ?>
