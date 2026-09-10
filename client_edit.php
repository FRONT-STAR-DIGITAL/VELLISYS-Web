<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$id = (int) ($_GET['id'] ?? post('id'));
$cid = current_company_id();
$party = $id ? db_one('SELECT * FROM parties WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = post('name');
    if ($name === '') {
        flash('Name is required.', 'err');
        redirect('client_edit.php' . ($id ? '?id=' . $id : ''));
    }
    $kind = post('kind') ?: 'customer';
    if (!in_array($kind, ['customer', 'supplier', 'both'], true)) {
        $kind = 'customer';
    }
    $tin = post('tin') ?: null;
    $contact = post('contact_person') ?: null;
    $phone = post('phone') ?: null;
    $phone2 = post('phone2') ?: null;
    $email = post('email') ?: null;
    $address = post('address') ?: null;
    $city = post('city') ?: null;
    $country = post('country') ?: null;
    $notes = post('party_notes') ?: null;
    if ($id && $party) {
        db_exec(
            'UPDATE parties SET name=?, kind=?, tin=?, contact_person=?, phone=?, phone2=?, email=?, address=?, city=?, country=?, notes=? WHERE id=? AND company_id=?',
            'sssssssssssii',
            [$name, $kind, $tin, $contact, $phone, $phone2, $email, $address, $city, $country, $notes, $id, $cid]
        );
        flash('Client updated.');
        redirect('client_view.php?id=' . $id);
    }
    $newId = db_exec(
        'INSERT INTO parties (company_id, name, kind, tin, contact_person, phone, phone2, email, address, city, country, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        'isssssssssss',
        [$cid, $name, $kind, $tin, $contact, $phone, $phone2, $email, $address, $city, $country, $notes]
    );
    flash('Client added.');
    redirect('client_view.php?id=' . $newId);
}

layout_start($party ? 'Edit client' : 'New client', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon($party ? 'pencil' : 'plus') ?><?= $party ? 'Edit client' : 'New client' ?></h1>
    <p class="lede">People and firms you invoice, quote, or pay. Give the address and phones room so they print clearly on the sheet.</p>
  </div>
</div>
<form class="card form-wide" method="post">
  <?= csrf_field() ?>
  <?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
  <div class="form-grid">
    <div>
      <label for="name">Name</label>
      <input id="name" name="name" required value="<?= h($party['name'] ?? '') ?>">
    </div>
    <div>
      <label for="kind">Kind</label>
      <select id="kind" name="kind">
        <?php foreach (['customer' => 'Customer', 'supplier' => 'Supplier', 'both' => 'Customer and supplier'] as $k => $label): ?>
          <option value="<?= h($k) ?>" <?= ($party['kind'] ?? 'customer') === $k ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="contact_person">Contact person</label>
      <input id="contact_person" name="contact_person" value="<?= h($party['contact_person'] ?? '') ?>">
    </div>
    <div>
      <label for="tin">TIN</label>
      <input id="tin" name="tin" value="<?= h($party['tin'] ?? '') ?>">
    </div>
    <div>
      <label for="email">Email</label>
      <input id="email" name="email" type="email" value="<?= h($party['email'] ?? '') ?>">
    </div>
    <div>
      <label for="phone">Phone</label>
      <input id="phone" name="phone" value="<?= h($party['phone'] ?? '') ?>">
    </div>
    <div>
      <label for="phone2">Second phone</label>
      <input id="phone2" name="phone2" value="<?= h($party['phone2'] ?? '') ?>">
    </div>
    <div>
      <label for="city">City</label>
      <input id="city" name="city" value="<?= h($party['city'] ?? '') ?>">
    </div>
    <div>
      <label for="country">Country</label>
      <input id="country" name="country" value="<?= h($party['country'] ?? '') ?>">
    </div>
  </div>
  <label for="address">Address</label>
  <textarea id="address" name="address" rows="5" placeholder="Street, building, P.O. Box…"><?= h($party['address'] ?? '') ?></textarea>
  <label for="party_notes">Notes</label>
  <textarea id="party_notes" name="party_notes" rows="4" placeholder="Delivery hours, gate codes, who to copy on email…"><?= h($party['notes'] ?? '') ?></textarea>
  <div class="actions" style="margin-top:16px">
    <button class="btn" type="submit"><?= icon('check') ?>Save client</button>
    <a class="btn ghost" href="<?= h($id ? url('client_view.php?id=' . $id) : url('clients.php')) ?>">Cancel</a>
  </div>
</form>
<?php layout_end(); ?>
