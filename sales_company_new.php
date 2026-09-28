<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_sales_agent();
sales_require_clock_in();

$leadId = (int) ($_GET['lead'] ?? post('lead_id'));
$lead = $leadId ? sales_lead($leadId) : null;
if ($lead && ((int) ($lead['agent_id'] ?? 0) !== (int) $user['id'] || !empty($lead['deleted_at']))) {
    flash('Lead not found.', 'err');
    redirect('sales_leads.php');
}
if ($lead && !empty($lead['company_id'])) {
    $existing = db_one('SELECT id, testing_mode FROM companies WHERE id = ?', 'i', [(int) $lead['company_id']]);
    if ($existing && !empty($existing['testing_mode'])) {
        flash('This lead already has a testing desk.');
        redirect('sales_company.php?id=' . (int) $existing['id']);
    }
}

$error = '';
$pref = static function (string $key) use ($lead): string {
    $posted = post($key);
    if ($posted !== '') {
        return $posted;
    }
    if (!$lead) {
        return '';
    }
    return match ($key) {
        'name', 'business_name' => (string) ($lead['business_name'] ?? ''),
        'contact_name' => (string) ($lead['contact_name'] ?? ''),
        'phone', 'contact_phone' => (string) ($lead['contact_phone'] ?? ''),
        'city' => (string) ($lead['city'] ?? ''),
        'address' => (string) ($lead['address'] ?? ''),
        'nature_of_business' => (string) ($lead['nature_of_business'] ?? ''),
        default => '',
    };
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $made = sales_create_testing_company([
        'name' => post('name'),
        'contact_name' => post('contact_name'),
        'phone' => post('phone'),
        'city' => post('city'),
        'address' => post('address'),
        'nature_of_business' => post('nature_of_business'),
        'lead_id' => $leadId,
        'enabled_kinds' => $_POST['enabled_kinds'] ?? [],
    ], (int) $user['id'], false);
    if (empty($made['ok'])) {
        $error = (string) ($made['error'] ?? 'Could not create the testing desk.');
    } else {
        $_SESSION['testing_creds'] = [
            'company_id' => (int) $made['company_id'],
            'name' => (string) $made['name'],
            'email' => (string) $made['email'],
            'password' => (string) $made['password'],
            'expires_at' => (string) $made['expires_at'],
        ];
        flash($made['name'] . ' is in testing mode for 2 weeks. Hand the login to the client.');
        redirect('sales_companies.php');
    }
}

sales_layout_start('New test desk', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('plus') ?>New test desk</h1>
    <p class="lede">For interested clients who want to try first. Runs strictly for 2 weeks. Login is FirstWord@vellisys.com · password Folio2026.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn ghost" href="<?= h(url('sales_companies.php')) ?>">Back</a>
  </div>
</div>

<?php if ($error): ?><p class="flash flash-err"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>
<?php if ($lead): ?>
  <p class="flash">Prefilling from lead <?= h((string) ($lead['business_name'] ?: '#' . $leadId)) ?>.</p>
<?php endif; ?>

<form method="post" class="card pad-form sales-test-form" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php if ($leadId): ?><input type="hidden" name="lead_id" value="<?= (int) $leadId ?>"><?php endif; ?>

  <fieldset>
    <legend>Business and contact</legend>
    <div class="form-grid">
      <div>
        <label for="name">Business name</label>
        <input id="name" name="name" required value="<?= h($pref('name')) ?>" placeholder="Business name">
      </div>
      <div>
        <label for="nature_of_business">Nature of business</label>
        <input id="nature_of_business" name="nature_of_business" value="<?= h($pref('nature_of_business')) ?>" placeholder="Shop, clinic, transport…">
      </div>
      <div>
        <label for="contact_name">Contact person</label>
        <input id="contact_name" name="contact_name" required value="<?= h($pref('contact_name')) ?>">
      </div>
      <div>
        <label for="phone">Phone</label>
        <input id="phone" name="phone" inputmode="tel" required value="<?= h($pref('phone')) ?>">
      </div>
      <div>
        <label for="city">City / area</label>
        <input id="city" name="city" value="<?= h($pref('city')) ?>">
      </div>
      <div class="full">
        <label for="address">Address</label>
        <input id="address" name="address" value="<?= h($pref('address')) ?>">
      </div>
    </div>
  </fieldset>

  <?php if (function_exists('render_client_fields_admin')) {
      render_client_fields_admin();
  } ?>
  <?php render_desk_kinds_fields(); ?>

  <p class="hint">Testing mode lasts 2 weeks from now. Super admin can extend or shorten later. Full emails and advanced settings come when you promote to onboard.</p>
  <div class="actions" style="margin-top:16px">
    <button class="btn" type="submit"><?= icon('check') ?>Create test desk</button>
    <a class="btn ghost" href="<?= h(url('sales_companies.php')) ?>">Cancel</a>
  </div>
</form>
<?php sales_layout_end(); ?>
