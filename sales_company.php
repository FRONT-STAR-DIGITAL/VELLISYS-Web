<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_sales_agent();
sales_require_clock_in();

$id = (int) ($_GET['id'] ?? 0);
$company = sales_testing_company($id, (int) $user['id']);
if (!$company) {
    flash('Testing company not found.', 'err');
    redirect('sales_companies.php');
}

$error = '';
$brand = db_one('SELECT * FROM branding WHERE company_id = ?', 'i', [$id]) ?: [];
$creds = sales_testing_credentials($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'save') {
        $saved = sales_update_testing_company($id, [
            'name' => post('name'),
            'contact_name' => post('contact_name'),
            'phone' => post('phone'),
            'city' => post('city'),
            'address' => post('address'),
            'nature_of_business' => post('nature_of_business'),
            'enabled_kinds' => $_POST['enabled_kinds'] ?? [],
        ], (int) $user['id']);
        if (empty($saved['ok'])) {
            $error = (string) ($saved['error'] ?? 'Could not save.');
        } else {
            flash('Testing company updated.');
            redirect('sales_company.php?id=' . $id);
        }
    }
    $company = sales_testing_company($id, (int) $user['id']) ?: $company;
    $brand = db_one('SELECT * FROM branding WHERE company_id = ?', 'i', [$id]) ?: $brand;
}

$expired = company_testing_expired($company);
$linkedLead = db_one(
    'SELECT id, business_name FROM sales_leads WHERE company_id = ? AND agent_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1',
    'ii',
    [$id, (int) $user['id']]
);
sales_layout_start((string) $company['name'], $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('building') ?><?= h((string) $company['name']) ?></h1>
    <p class="lede">
      Testing mode · <?= h(company_testing_remaining_label($company)) ?>
      <?php if (!empty($company['testing_expires_at'])): ?>
        · ends <?= h(format_date((string) $company['testing_expires_at'])) ?>
      <?php endif; ?>
      <?php if ($linkedLead): ?>
        · lead <a href="<?= h(url('sales_lead_edit.php?id=' . (int) $linkedLead['id'] . '#lead-testing')) ?>"><?= h((string) ($linkedLead['business_name'] ?: '#' . (int) $linkedLead['id'])) ?></a>
      <?php endif; ?>
    </p>
  </div>
  <div class="actions page-actions">
    <?php if ($linkedLead): ?>
      <a class="btn ghost" href="<?= h(url('sales_lead_edit.php?id=' . (int) $linkedLead['id'] . '#lead-testing')) ?>">Lead</a>
    <?php endif; ?>
    <?php if (!$expired): ?>
      <a class="btn" href="<?= h(url('sales_desk.php?id=' . $id)) ?>"><?= icon('desk', 16) ?>Desk</a>
    <?php endif; ?>
    <a class="btn ghost" href="<?= h(url('sales_companies.php')) ?>">Back</a>
  </div>
</div>

<?php if ($error): ?><p class="flash flash-err"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>
<?php if ($expired): ?>
  <p class="flash flash-err">This testing desk has ended. Ask super admin to extend the time or promote to onboard (lasting email + keep or clean data).</p>
<?php endif; ?>

<div class="card pad-form sales-creds-card" style="margin-bottom:16px">
  <h2 style="margin-top:0">Client login</h2>
  <p class="lede" style="margin-top:0">Hand these credentials to the client so they can sign in at the portal.</p>
  <p><strong>Username:</strong> <code><?= h((string) ($creds['email'] ?: '-')) ?></code></p>
  <p><strong>Password:</strong> <code><?= h((string) ($creds['password'] !== '' ? $creds['password'] : '(not stored - ask admin)')) ?></code></p>
</div>

<form method="post" class="card pad-form sales-test-form">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <fieldset>
    <legend>Business and contact</legend>
    <div class="form-grid">
      <div>
        <label for="name">Business name</label>
        <input id="name" name="name" required value="<?= h((string) ($_POST['name'] ?? $company['name'])) ?>">
      </div>
      <div>
        <label for="nature_of_business">Nature of business</label>
        <input id="nature_of_business" name="nature_of_business" value="<?= h((string) ($_POST['nature_of_business'] ?? $company['nature_of_business'] ?? '')) ?>">
      </div>
      <div>
        <label for="contact_name">Contact person</label>
        <input id="contact_name" name="contact_name" value="<?= h((string) ($_POST['contact_name'] ?? $creds['name'] ?? '')) ?>">
      </div>
      <div>
        <label for="phone">Phone</label>
        <input id="phone" name="phone" inputmode="tel" value="<?= h((string) ($_POST['phone'] ?? $brand['phone'] ?? '')) ?>">
      </div>
      <div>
        <label for="city">City / area</label>
        <input id="city" name="city" value="<?= h((string) ($_POST['city'] ?? $brand['city'] ?? '')) ?>">
      </div>
      <div class="full">
        <label for="address">Address</label>
        <input id="address" name="address" value="<?= h((string) ($_POST['address'] ?? $brand['address'] ?? '')) ?>">
      </div>
    </div>
  </fieldset>

  <?php if (function_exists('render_client_fields_admin')) {
      render_client_fields_admin($company);
  } ?>
  <?php render_desk_kinds_fields($company); ?>

  <div class="actions" style="margin-top:16px">
    <button class="btn" type="submit"><?= icon('check') ?>Save</button>
  </div>
</form>
<?php sales_layout_end(); ?>
